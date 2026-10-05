<script>
import { Head } from '@inertiajs/vue3';
import { ref, watch, onMounted, onBeforeUnmount, computed } from "vue";
import { useI18n } from 'vue-i18n';
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import googleMap from '@/Components/googleMap.vue';
import { useSharedState } from '@/composables/useSharedState';
import axios from 'axios';
import { processDriversSnapshot, formatAgeLabel, GODS_EYE_LIST_CAP } from '@/composables/useGodsEyeDrivers';

const RADIUS_OPTIONS = [1, 2, 5, 10, 15, 25, 50];
const STALE_AFTER_SECONDS = 7 * 60;

export default {
    components: {
        Head,
        Layout,
        PageHeader,
        Multiselect,
        googleMap,
    },
    props: {
        firebaseSettings: Object,
        map_key: String,
        service_location: Array,
        vehicle_type: Array,
        baseUrl: String,
        default_location: Object,
        app_for: String,
    },
    setup(props) {
        const { t } = useI18n();
        const selectedServiceLocations = ref(null);
        const selectedVehicleTypes = ref([]);
        const selectedDriverModes = ref([]);
        const searchInput = ref('');
        const searchQuery = ref('');
        const { selectedLocation, setServiceLocation } = useSharedState();

        // Place + radius (required before any drivers are shown)
        const placeQuery = ref('');
        const placeSuggestions = ref([]);
        const selectedPlaceLabel = ref('');
        const searchCenter = ref(null);
        const radiusKm = ref(5);
        let placeDebounceTimer = null;

        const serviceLocations = ref(
            (props.service_location || []).map((loc) => ({ value: loc.id, label: loc.name }))
        );
        const vehicleTypes = ref(
            (props.vehicle_type || []).map((type) => ({ value: type.id, label: type.name }))
        );
        const allowedLocationIds = computed(() =>
            serviceLocations.value.map((loc) => loc.value)
        );

        const driverMarkers = ref({});
        const driverList = ref([]);
        const statusCounts = ref({ online: 0, onride: 0, stuck: 0, offline: 0, total: 0, truncated: false });
        const rawDrivers = ref({});
        const openTripDriverIds = ref(null);
        const firebaseReady = ref(false);
        const hasSnapshot = ref(false);
        const focusLocation = ref(null);
        const driversRefHandle = ref(null);
        let searchDebounceTimer = null;
        let openTripsDebounceTimer = null;

        const eligibilityDrawerOpen = ref(false);
        const eligibilityLoading = ref(false);
        const eligibilityResetting = ref(false);
        const eligibilityError = ref('');
        const eligibilityData = ref(null);
        const selectedDriverRow = ref(null);

        const hasSearchArea = computed(() =>
            !!searchCenter.value
            && Number.isFinite(searchCenter.value.lat)
            && Number.isFinite(searchCenter.value.lng)
            && Number(radiusKm.value) > 0
        );

        const searchArea = computed(() => {
            if (!hasSearchArea.value) return null;
            return { center: searchCenter.value, radiusKm: Number(radiusKm.value) };
        });

        const currentFilters = () => ({
            modes: selectedDriverModes.value || [],
            vehicleTypeIds: selectedVehicleTypes.value,
            serviceLocationId: selectedServiceLocations.value,
            allowedLocationIds: allowedLocationIds.value,
            searchQuery: searchQuery.value,
            center: hasSearchArea.value ? searchCenter.value : null,
            radiusKm: hasSearchArea.value ? Number(radiusKm.value) : null,
        });

        const recomputeVisible = () => {
            const { list, counts, markers } = processDriversSnapshot(
                rawDrivers.value,
                currentFilters(),
                {
                    appFor: props.app_for,
                    baseUrl: props.baseUrl || '',
                    openTripDriverIds: openTripDriverIds.value,
                    vehicleTypeLookup: vehicleTypes.value,
                }
            );
            driverList.value = list;
            statusCounts.value = counts;
            driverMarkers.value = markers;

            if (String(searchQuery.value || '').trim() && list.length === 1) {
                focusLocation.value = { lat: list[0].lat, lng: list[0].lng };
            }

            scheduleOpenTripsRefresh(list);
        };

        const scheduleOpenTripsRefresh = (list) => {
            if (!hasSearchArea.value) return;
            const candidateIds = (list || [])
                .filter((d) => d.status === 'onride' || d.status === 'stuck')
                .map((d) => d.id)
                .filter((id) => id != null);
            if (candidateIds.length === 0) {
                return;
            }
            if (openTripsDebounceTimer) clearTimeout(openTripsDebounceTimer);
            openTripsDebounceTimer = setTimeout(() => {
                refreshOpenTrips(candidateIds);
            }, 400);
        };

        const refreshOpenTrips = async (driverIds) => {
            try {
                const response = await axios.post('/map/gods_eye/drivers/open-trips', {
                    driver_ids: driverIds,
                });
                const openIds = response.data?.open_trip_driver_ids || [];
                // Mark all candidates as known: those not in openIds are stuck.
                openTripDriverIds.value = new Set(openIds.map((id) => String(id)));
                const { list, counts, markers } = processDriversSnapshot(
                    rawDrivers.value,
                    currentFilters(),
                    {
                        appFor: props.app_for,
                        baseUrl: props.baseUrl || '',
                        openTripDriverIds: openTripDriverIds.value,
                        vehicleTypeLookup: vehicleTypes.value,
                    }
                );
                driverList.value = list;
                statusCounts.value = counts;
                driverMarkers.value = markers;
            } catch (err) {
                console.error('Gods Eye open-trips check failed', err);
            }
        };

        const closeEligibilityDrawer = () => {
            eligibilityDrawerOpen.value = false;
            eligibilityData.value = null;
            eligibilityError.value = '';
            selectedDriverRow.value = null;
        };

        const openEligibilityDrawer = async (driver) => {
            if (!driver?.id) return;
            selectedDriverRow.value = driver;
            focusLocation.value = { lat: driver.lat, lng: driver.lng };
            eligibilityDrawerOpen.value = true;
            eligibilityLoading.value = true;
            eligibilityError.value = '';
            eligibilityData.value = null;
            try {
                const response = await axios.get(`/map/gods_eye/driver/${driver.id}/eligibility`);
                eligibilityData.value = response.data;
                if (response.data?.status === 'stuck' || response.data?.status === 'onride') {
                    // Keep list classification aligned with server truth for this driver.
                    const next = new Set(openTripDriverIds.value || []);
                    if (response.data.open_trip_count > 0) {
                        next.add(String(driver.id));
                    } else {
                        next.delete(String(driver.id));
                    }
                    openTripDriverIds.value = next;
                    recomputeVisible();
                }
            } catch (err) {
                console.error('Gods Eye eligibility failed', err);
                eligibilityError.value = err.response?.data?.message || t('gods_eye_eligibility_error');
            } finally {
                eligibilityLoading.value = false;
            }
        };

        const resetDriverAvailability = async () => {
            const id = selectedDriverRow.value?.id || eligibilityData.value?.driver_id;
            if (!id || eligibilityResetting.value) return;
            eligibilityResetting.value = true;
            eligibilityError.value = '';
            try {
                const response = await axios.post(`/map/gods_eye/driver/${id}/reset-availability`);
                eligibilityData.value = response.data?.eligibility || response.data;
                const next = new Set(openTripDriverIds.value || []);
                next.delete(String(id));
                openTripDriverIds.value = next;
                recomputeVisible();
            } catch (err) {
                console.error('Gods Eye reset availability failed', err);
                eligibilityError.value = err.response?.data?.message || t('gods_eye_reset_failed');
            } finally {
                eligibilityResetting.value = false;
            }
        };

        const reasonLabel = (code) => {
            const key = `gods_eye_reason_${code}`;
            const translated = t(key);
            return translated === key ? code : translated;
        };

        const applySnapshot = (driversObj) => {
            hasSnapshot.value = true;
            rawDrivers.value = driversObj || {};
            recomputeVisible();
        };

        const bindDriversListener = () => {
            if (typeof firebase === 'undefined') return;
            const driversRef = firebase.database().ref('drivers');
            if (driversRefHandle.value) {
                driversRefHandle.value.off();
            }
            driversRefHandle.value = driversRef;
            driversRef.on('value', (snapshot) => {
                applySnapshot(snapshot.val() || {});
            });
        };

        const fetchPlaceSuggestions = (input) => {
            if (!props.map_key || !input || input.length < 3) {
                placeSuggestions.value = [];
                return;
            }
            fetch('https://places.googleapis.com/v1/places:autocomplete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Goog-Api-Key': props.map_key,
                    'X-Goog-FieldMask':
                        'suggestions.placePrediction.placeId,suggestions.placePrediction.place,suggestions.placePrediction.text',
                },
                body: JSON.stringify({ input }),
            })
                .then((r) => r.json())
                .then((data) => {
                    placeSuggestions.value = (data.suggestions || [])
                        .filter((s) => s.placePrediction)
                        .map((s) => ({
                            placeId: s.placePrediction.placeId,
                            formattedAddress: s.placePrediction.text?.text || '',
                        }));
                })
                .catch((err) => {
                    console.error('Gods Eye place autocomplete failed', err);
                    placeSuggestions.value = [];
                });
        };

        const onPlaceInput = () => {
            if (placeDebounceTimer) clearTimeout(placeDebounceTimer);
            placeDebounceTimer = setTimeout(() => {
                fetchPlaceSuggestions(placeQuery.value);
            }, 300);
        };

        const selectPlace = async (suggestion) => {
            if (!suggestion?.placeId || !props.map_key) return;
            try {
                const response = await fetch(
                    `https://places.googleapis.com/v1/places/${suggestion.placeId}?fields=location,formattedAddress`,
                    {
                        headers: {
                            'X-Goog-Api-Key': props.map_key,
                            'X-Goog-FieldMask': 'location,formattedAddress',
                        },
                    }
                );
                const data = await response.json();
                const lat = parseFloat(data.location?.latitude);
                const lng = parseFloat(data.location?.longitude);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

                searchCenter.value = { lat, lng };
                selectedPlaceLabel.value =
                    data.formattedAddress || suggestion.formattedAddress || '';
                placeQuery.value = selectedPlaceLabel.value;
                placeSuggestions.value = [];
                recomputeVisible();
            } catch (err) {
                console.error('Gods Eye place details failed', err);
            }
        };

        const clearPlace = () => {
            placeQuery.value = '';
            placeSuggestions.value = [];
            selectedPlaceLabel.value = '';
            searchCenter.value = null;
            driverMarkers.value = {};
            driverList.value = [];
            statusCounts.value = { online: 0, onride: 0, stuck: 0, offline: 0, total: 0, truncated: false };
            openTripDriverIds.value = null;
            closeEligibilityDrawer();
            recomputeVisible();
        };

        const clearFilters = () => {
            selectedVehicleTypes.value = [];
            selectedDriverModes.value = [];
            selectedServiceLocations.value = null;
            searchInput.value = '';
            searchQuery.value = '';
            focusLocation.value = null;
            radiusKm.value = 5;
            clearPlace();
            if (selectedLocation.value && selectedLocation.value !== 'all') {
                setServiceLocation('all');
            }
        };

        const applyFilters = () => {
            searchQuery.value = String(searchInput.value || '').trim();
            recomputeVisible();
        };

        const focusDriver = (driver) => {
            if (!driver) return;
            openEligibilityDrawer(driver);
        };

        const onSearchInput = () => {
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                searchQuery.value = String(searchInput.value || '').trim();
            }, 200);
        };

        watch(selectedLocation, (value) => {
            if (value && value !== 'all') {
                selectedServiceLocations.value = value;
            } else if (value === 'all') {
                selectedServiceLocations.value = null;
            }
        });

        watch(selectedServiceLocations, (value) => {
            if (value && selectedLocation.value !== value) {
                setServiceLocation(value);
            } else if (!value && selectedLocation.value && selectedLocation.value !== 'all') {
                setServiceLocation('all');
            }
        });

        watch(
            [selectedVehicleTypes, selectedDriverModes, selectedServiceLocations, searchQuery, radiusKm],
            () => recomputeVisible(),
            { deep: true }
        );

        const refresh_Method = ref('0');
        const refresh_btn = ref(false);

        const refreshMethod = async (refreshvalue) => {
            localStorage.setItem('refreshMethod', refreshvalue);
            applyRefreshMethod(refreshvalue);
        };

        const refreshBtn = async () => {
            if (typeof firebase === 'undefined') return;
            firebase.database().ref('drivers').once('value', (snapshot) => {
                applySnapshot(snapshot.val() || {});
            });
        };

        const applyRefreshMethod = (refresh) => {
            if (refresh == 0 || refresh === '0') {
                refresh_btn.value = false;
                bindDriversListener();
            } else {
                refresh_btn.value = true;
                if (driversRefHandle.value) {
                    driversRefHandle.value.off();
                    driversRefHandle.value = null;
                }
                refreshBtn();
            }
        };

        onMounted(async () => {
            if (selectedLocation.value && selectedLocation.value !== 'all') {
                selectedServiceLocations.value = selectedLocation.value;
            }

            const firebaseConfig = {
                apiKey: props.firebaseSettings['firebase_api_key'],
                authDomain: props.firebaseSettings['firebase_auth_domain'],
                databaseURL: props.firebaseSettings['firebase_database_url'],
                projectId: props.firebaseSettings['firebase_project_id'],
                storageBucket: props.firebaseSettings['firebase_storage_bucket'],
                messagingSenderId: props.firebaseSettings['firebase_messaging_sender_id'],
                appId: props.firebaseSettings['firebase_app_id'],
            };
            if (!firebase.apps.length) {
                firebase.initializeApp(firebaseConfig);
            }
            firebaseReady.value = true;

            const refresh = localStorage.getItem('refreshMethod') ?? '0';
            refresh_Method.value = refresh;
            applyRefreshMethod(refresh);
        });

        onBeforeUnmount(() => {
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            if (placeDebounceTimer) clearTimeout(placeDebounceTimer);
            if (openTripsDebounceTimer) clearTimeout(openTripsDebounceTimer);
            if (driversRefHandle.value) {
                driversRefHandle.value.off();
                driversRefHandle.value = null;
            }
        });

        const emptyMessage = computed(() => {
            if (!hasSearchArea.value) {
                return t('gods_eye_select_address_hint');
            }
            if (!firebaseReady.value || !hasSnapshot.value) {
                return t('gods_eye_waiting_firebase');
            }
            if (statusCounts.value.total === 0) {
                return t('gods_eye_no_drivers_match');
            }
            return '';
        });

        const statusBadgeClass = (status) => {
            if (status === 'online') return 'bg-success';
            if (status === 'onride') return 'bg-warning text-dark';
            if (status === 'stuck') return 'bg-danger';
            return 'bg-secondary';
        };

        const formatDistance = (km) => {
            if (!Number.isFinite(km)) return '';
            if (km < 1) return `${Math.round(km * 1000)} m`;
            return `${km.toFixed(1)} km`;
        };

        return {
            RADIUS_OPTIONS,
            GODS_EYE_LIST_CAP,
            serviceLocations,
            vehicleTypes,
            selectedServiceLocations,
            selectedDriverModes,
            clearFilters,
            applyFilters,
            selectedVehicleTypes,
            driverMarkers,
            driverList,
            statusCounts,
            searchInput,
            onSearchInput,
            focusDriver,
            focusLocation,
            emptyMessage,
            statusBadgeClass,
            formatDistance,
            placeQuery,
            placeSuggestions,
            onPlaceInput,
            selectPlace,
            clearPlace,
            selectedPlaceLabel,
            radiusKm,
            hasSearchArea,
            searchArea,
            refreshMethod,
            refresh_Method,
            refresh_btn,
            refreshBtn,
            eligibilityDrawerOpen,
            eligibilityLoading,
            eligibilityResetting,
            eligibilityError,
            eligibilityData,
            closeEligibilityDrawer,
            resetDriverAvailability,
            reasonLabel,
            formatAgeLabel,
            STALE_AFTER_SECONDS,
        };
    },
};
</script>

<template>
    <Layout>
        <Head :title="$t('gods-eye')" />
        <PageHeader :title="$t('gods-eye')" :pageTitle="$t('map')" />
        <BRow>
            <BCol lg="12">
                <BCard no-body id="tasksList">
                    <BCardHeader class="border-0">
                        <h4>{{ $t("filters") }}</h4>
                        <div class="row">
                            <div class="col-12 col-lg-5">
                                <div class="mb-3">
                                    <label class="form-label" for="gods_eye_place">{{ $t("gods_eye_search_area") }}</label>
                                    <div class="autocomplete-container">
                                        <div class="input-group">
                                            <input
                                                id="gods_eye_place"
                                                type="text"
                                                class="form-control"
                                                v-model="placeQuery"
                                                @input="onPlaceInput"
                                                autocomplete="off"
                                                :placeholder="$t('gods_eye_address_placeholder')"
                                            />
                                            <BButton
                                                v-if="hasSearchArea"
                                                type="button"
                                                variant="outline-secondary"
                                                @click="clearPlace"
                                            >
                                                <i class="bx bx-x"></i>
                                            </BButton>
                                        </div>
                                        <div v-if="placeSuggestions.length" class="autocomplete-results">
                                            <div
                                                v-for="(item, idx) in placeSuggestions"
                                                :key="item.placeId || idx"
                                                class="autocomplete-item"
                                                @click="selectPlace(item)"
                                            >
                                                {{ item.formattedAddress }}
                                            </div>
                                        </div>
                                    </div>
                                    <small v-if="selectedPlaceLabel" class="text-muted">{{ selectedPlaceLabel }}</small>
                                </div>
                            </div>

                            <div class="col-12 col-lg-2">
                                <div class="mb-3">
                                    <label class="form-label" for="gods_eye_radius">{{ $t("gods_eye_radius_km") }}</label>
                                    <select id="gods_eye_radius" class="form-select" v-model.number="radiusKm">
                                        <option v-for="r in RADIUS_OPTIONS" :key="r" :value="r">{{ r }} km</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-lg-5">
                                <div class="mb-3">
                                    <label class="form-label" for="gods_eye_search">{{ $t("search") }}</label>
                                    <input
                                        id="gods_eye_search"
                                        type="search"
                                        class="form-control"
                                        v-model="searchInput"
                                        @input="onSearchInput"
                                        :disabled="!hasSearchArea"
                                        :placeholder="$t('gods_eye_search_placeholder')"
                                    />
                                </div>
                            </div>

                            <div class="col-12 col-lg-4">
                                <div class="mb-3">
                                    <label for="select_service_location" class="form-label">{{ $t("service_location") }}</label>
                                    <Multiselect
                                        v-model="selectedServiceLocations"
                                        id="select_service_location"
                                        :options="serviceLocations"
                                        label="label"
                                        :can-clear="true"
                                        :searchable="true"
                                        :disabled="!hasSearchArea"
                                        :placeholder="$t('select_service_location')"
                                    />
                                </div>
                            </div>

                            <div class="col-12 col-lg-4">
                                <div class="mb-3">
                                    <label for="select_driver_mode" class="form-label">{{ $t("drivers") }}</label>
                                    <Multiselect
                                        v-model="selectedDriverModes"
                                        id="select_driver_mode"
                                        mode="tags"
                                        :close-on-select="false"
                                        :searchable="true"
                                        :create-option="false"
                                        :disabled="!hasSearchArea"
                                        :options="[
                                            { value: 'offline', label: $t('offline') },
                                            { value: 'online', label: $t('online') },
                                            { value: 'onride', label: $t('onride') },
                                            { value: 'stuck', label: $t('stuck') },
                                        ]"
                                        :placeholder="$t('select_mode')"
                                    />
                                </div>
                            </div>

                            <div class="col-12 col-lg-4">
                                <div class="mb-3">
                                    <label for="vehicle_type" class="form-label">{{ $t("vehicle_types") }}</label>
                                    <Multiselect
                                        v-model="selectedVehicleTypes"
                                        :options="vehicleTypes"
                                        mode="tags"
                                        :close-on-select="false"
                                        :searchable="true"
                                        :can-clear="true"
                                        :create-option="false"
                                        :disabled="!hasSearchArea"
                                        :placeholder="$t('select_vehicle_types')"
                                    />
                                </div>
                            </div>

                            <div class="col-sm-2">
                                <div class="mb-3">
                                    <label for="type" class="form-label">{{ $t("refresh_method") }}</label>
                                    <select
                                        id="type"
                                        class="form-select"
                                        v-model="refresh_Method"
                                        @change="refreshMethod(refresh_Method)"
                                    >
                                        <option value="0">{{ $t("automatic") }}</option>
                                        <option value="1">{{ $t("manual") }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-sm-2" v-if="refresh_btn">
                                <div class="mb-3 mt-4">
                                    <BButton type="button" variant="primary" class="btn btn-md" @click="refreshBtn()">
                                        <i class="bx bx-refresh"></i> {{ $t("refresh") }}
                                    </BButton>
                                </div>
                            </div>

                            <div class="col-12 col-lg-6 d-flex gap-1 align-items-end mb-3">
                                <BButton type="button" variant="success" class="btn btn-md" :disabled="!hasSearchArea" @click="applyFilters">
                                    {{ $t("apply") }}
                                </BButton>
                                <BButton type="button" variant="danger" class="btn btn-md" @click="clearFilters">
                                    {{ $t("clear") }}
                                </BButton>
                            </div>
                        </div>

                        <div v-if="hasSearchArea" class="d-flex flex-wrap gap-2 mb-2">
                            <span class="badge bg-success">{{ $t("online") }}: {{ statusCounts.online }}</span>
                            <span class="badge bg-warning text-dark">{{ $t("onride") }}: {{ statusCounts.onride }}</span>
                            <span class="badge bg-danger">{{ $t("stuck") }}: {{ statusCounts.stuck || 0 }}</span>
                            <span class="badge bg-secondary">{{ $t("offline") }}: {{ statusCounts.offline }}</span>
                            <span class="badge bg-primary">{{ $t("total") }}: {{ statusCounts.total }}</span>
                        </div>

                        <div class="alert alert-info py-2 px-3 mb-0 mt-2 gods-eye-hints">
                            <div class="fw-semibold mb-1">
                                <i class="ri-information-line me-1"></i>{{ $t('gods_eye_hints_title') }}
                            </div>
                            <ul class="mb-0 ps-3 small">
                                <li>{{ $t('gods_eye_hint_online') }}</li>
                                <li>{{ $t('gods_eye_hint_onride') }}</li>
                                <li>
                                    <strong class="text-danger">{{ $t('stuck') }}:</strong>
                                    {{ $t('gods_eye_hint_stuck') }}
                                    <span class="d-block mt-1">{{ $t('gods_eye_hint_stuck_action') }}</span>
                                </li>
                                <li>{{ $t('gods_eye_hint_offline') }}</li>
                                <li>{{ $t('gods_eye_hint_stale') }}</li>
                            </ul>
                        </div>
                    </BCardHeader>

                    <BCardBody class="border border-dashed border-end-0 border-start-0">
                        <BRow>
                            <BCol lg="8" class="mb-3 mb-lg-0">
                                <div id="map" style="height: 520px;">
                                    <googleMap
                                        :baseUrl="baseUrl"
                                        :default_location="default_location"
                                        :nearbyDrivers="driverMarkers"
                                        :focusLocation="focusLocation"
                                        :searchArea="searchArea"
                                        :map_key="map_key"
                                        :libraries="['marker']"
                                    >
                                        {{ $t("map_loading") }}
                                    </googleMap>
                                </div>
                                <p v-if="emptyMessage" class="text-muted mt-2 mb-0">{{ emptyMessage }}</p>
                            </BCol>
                            <BCol lg="4">
                                <div class="gods-eye-driver-list border rounded">
                                    <div class="p-2 border-bottom fw-semibold">
                                        {{ $t("drivers") }}
                                        <span v-if="hasSearchArea">
                                            ({{ Math.min(driverList.length, GODS_EYE_LIST_CAP) }}{{ statusCounts.truncated ? '+' : '' }}/{{ statusCounts.total }})
                                        </span>
                                    </div>
                                    <div class="gods-eye-driver-list-scroll">
                                        <template v-if="hasSearchArea">
                                            <button
                                                v-for="driver in driverList"
                                                :key="driver.id"
                                                type="button"
                                                class="gods-eye-driver-row w-100 text-start border-0 border-bottom bg-transparent"
                                                @click="focusDriver(driver)"
                                            >
                                                <div class="d-flex justify-content-between align-items-start gap-2">
                                                    <div>
                                                        <div class="fw-semibold">{{ driver.name }}</div>
                                                        <div class="small text-muted">
                                                            #{{ driver.id }} · {{ driver.mobile }}
                                                            <span v-if="driver.distanceKm != null">
                                                                · {{ formatDistance(driver.distanceKm) }}
                                                            </span>
                                                            <span v-if="driver.ageLabel">
                                                                · {{ driver.ageLabel }}
                                                                <span
                                                                    v-if="driver.ageSeconds != null && driver.ageSeconds > STALE_AFTER_SECONDS"
                                                                    class="text-danger"
                                                                >({{ $t('stale') }})</span>
                                                            </span>
                                                        </div>
                                                        <div v-if="driver.vehicleTypeLabel" class="small text-body-secondary mt-1">
                                                            {{ $t('vehicle_types') }}: {{ driver.vehicleTypeLabel }}
                                                        </div>
                                                    </div>
                                                    <span class="badge" :class="statusBadgeClass(driver.status)">
                                                        {{ $t(driver.status) }}
                                                    </span>
                                                </div>
                                            </button>
                                            <div v-if="statusCounts.truncated" class="p-2 small text-muted">
                                                {{ $t('gods_eye_list_capped') }}
                                            </div>
                                            <div v-if="!driverList.length" class="p-3 text-muted small">
                                                {{ emptyMessage || $t('gods_eye_no_drivers_match') }}
                                            </div>
                                        </template>
                                        <div v-else class="p-3 text-muted small">
                                            {{ $t('gods_eye_select_address_hint') }}
                                        </div>
                                    </div>
                                </div>
                            </BCol>
                        </BRow>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <div
            v-if="eligibilityDrawerOpen"
            class="gods-eye-drawer-backdrop"
            @click.self="closeEligibilityDrawer"
        >
            <aside class="gods-eye-drawer border-start shadow">
                <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                    <h5 class="mb-0">{{ $t('gods_eye_eligibility_title') }}</h5>
                    <button type="button" class="btn-close" @click="closeEligibilityDrawer"></button>
                </div>
                <div class="p-3 gods-eye-drawer-body">
                    <div v-if="eligibilityLoading" class="text-muted">{{ $t('loading') }}…</div>
                    <div v-else-if="eligibilityError" class="alert alert-danger py-2">{{ eligibilityError }}</div>
                    <template v-else-if="eligibilityData">
                        <div class="mb-3">
                            <div class="fw-semibold">{{ eligibilityData.name }}</div>
                            <div class="small text-muted">#{{ eligibilityData.driver_id }} · {{ eligibilityData.mobile }}</div>
                            <span class="badge mt-1" :class="statusBadgeClass(eligibilityData.status)">
                                {{ $t(eligibilityData.status) }}
                            </span>
                            <span
                                class="badge mt-1 ms-1"
                                :class="eligibilityData.offer_ready ? 'bg-success' : 'bg-secondary'"
                            >
                                {{ eligibilityData.offer_ready ? $t('gods_eye_offer_ready') : $t('gods_eye_not_offer_ready') }}
                            </span>
                        </div>

                        <div
                            v-if="eligibilityData.status === 'stuck' || eligibilityData.can_reset_availability"
                            class="alert alert-warning py-2 small mb-3"
                        >
                            {{ $t('gods_eye_stuck_drawer_hint') }}
                        </div>

                        <div class="mb-3">
                            <div class="fw-semibold small text-uppercase text-muted mb-1">{{ $t('gods_eye_reasons') }}</div>
                            <div class="d-flex flex-wrap gap-1">
                                <span
                                    v-for="code in eligibilityData.reasons"
                                    :key="code"
                                    class="badge"
                                    :class="code === 'offer_ready' ? 'bg-success' : (code === 'stuck_desync' ? 'bg-danger' : 'bg-light text-dark border')"
                                >
                                    {{ reasonLabel(code) }}
                                </span>
                            </div>
                        </div>

                        <div class="row g-2 mb-3 small">
                            <div class="col-6">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted">Firebase</div>
                                    <div>active: {{ eligibilityData.firebase?.is_active ? '1' : '0' }}</div>
                                    <div>available: {{ eligibilityData.firebase?.is_available ? '1' : '0' }}</div>
                                    <div>
                                        last update:
                                        {{ formatAgeLabel(eligibilityData.firebase?.updated_at_age_seconds) || '—' }}
                                        <span v-if="!eligibilityData.firebase?.is_fresh" class="text-danger">({{ $t('stale') }})</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-2 h-100">
                                    <div class="text-muted">MySQL</div>
                                    <div>active: {{ eligibilityData.mysql?.active ? '1' : '0' }}</div>
                                    <div>available: {{ eligibilityData.mysql?.available ? '1' : '0' }}</div>
                                    <div>approve: {{ eligibilityData.mysql?.approve ? '1' : '0' }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="fw-semibold small text-uppercase text-muted mb-1">
                                {{ $t('gods_eye_open_trips') }} ({{ eligibilityData.open_trip_count }})
                            </div>
                            <ul v-if="eligibilityData.open_trips?.length" class="list-unstyled small mb-0">
                                <li v-for="trip in eligibilityData.open_trips" :key="trip.id">
                                    {{ trip.request_number || trip.id }}
                                    <span class="text-muted">
                                        · {{ trip.is_driver_started ? $t('started') : $t('not_started') }}
                                    </span>
                                </li>
                            </ul>
                            <div v-else class="small text-muted">{{ $t('gods_eye_no_open_trips') }}</div>
                        </div>

                        <BButton
                            v-if="eligibilityData.can_reset_availability"
                            type="button"
                            variant="danger"
                            class="w-100"
                            :disabled="eligibilityResetting"
                            @click="resetDriverAvailability"
                        >
                            {{ eligibilityResetting ? $t('loading') : $t('gods_eye_reset_availability') }}
                        </BButton>
                    </template>
                </div>
            </aside>
        </div>
    </Layout>
</template>

<style scoped>
.gods-eye-driver-list {
    height: 520px;
    display: flex;
    flex-direction: column;
    background: var(--vz-secondary-bg, #fff);
}
.gods-eye-driver-list-scroll {
    overflow-y: auto;
    flex: 1;
}
.gods-eye-driver-row {
    padding: 0.75rem 0.85rem;
    cursor: pointer;
}
.gods-eye-driver-row:hover {
    background: rgba(0, 0, 0, 0.04);
}
.autocomplete-container {
    position: relative;
}
.autocomplete-results {
    position: absolute;
    z-index: 20;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    max-height: 240px;
    overflow-y: auto;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
}
.autocomplete-item {
    padding: 0.55rem 0.75rem;
    cursor: pointer;
}
.autocomplete-item:hover {
    background: #f3f6f9;
}
.gods-eye-drawer-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.25);
    z-index: 1050;
    display: flex;
    justify-content: flex-end;
}
.gods-eye-drawer {
    width: min(400px, 100%);
    background: var(--vz-secondary-bg, #fff);
    height: 100%;
    display: flex;
    flex-direction: column;
}
.gods-eye-drawer-body {
    overflow-y: auto;
    flex: 1;
}
</style>
