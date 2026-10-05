<script>
import { Link, Head } from '@inertiajs/vue3';
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";
import { ref, onMounted } from "vue";
import axios from "axios";
import { useI18n } from 'vue-i18n';
import googleMap from '@/Components/googleMap.vue';
import RideDossierSections from './components/RideDossierSections.vue';
import { useRideDossier } from './composables/useRideDossier';

export default {
    components: {
        Layout,
        PageHeader,
        Head,
        Link,
        googleMap,
        RideDossierSections,
    },
    props: {
        successMessage: String,
        alertMessage: String,
        serviceLocations: Object,
        googleMapKey: String,
        app_for: String,
        rejected_drivers: Object,
        firebaseConfig: Object,
        baseUrl: String,
        request: Object,
        dossierUrl: String,
    },
    setup(props) {
        const { t } = useI18n();
        const result = ref(props.request);
        const modalShow = ref(false);
        const proof = ref(props.request.requestProofs?.data || []);
        const stops = ref(props.request.requestStops?.data || []);
        const successMessage = ref(props.successMessage || '');
        const alertMessage = ref(props.alertMessage || '');
        const rideStatus = ref('');
        const driverOption = ref({});

        const {
            dossier,
            loading,
            error,
            showRawJson,
            pricingAudit,
            isReconstructed,
            fetchDossier,
            copyRideSummary,
            copyPricing,
        } = useRideDossier(props.dossierUrl);

        const dismissMessage = () => {
            successMessage.value = "";
            alertMessage.value = "";
        };

        const decodeGeohash = (geohash) => {
            const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';
            const BITS = [16, 8, 4, 2, 1];
            let isEven = true;
            let latMin = -90, latMax = 90;
            let lonMin = -180, lonMax = 180;
            let lat, lon;
            if (!geohash) return null;
            for (let i = 0; i < geohash.length; i++) {
                let c = geohash.charAt(i);
                let cd = BASE32.indexOf(c);
                for (let j = 0; j < 5; j++) {
                    let mask = BITS[j];
                    if (isEven) {
                        let lonMid = (lonMin + lonMax) / 2;
                        if (cd & mask) lonMin = lonMid; else lonMax = lonMid;
                    } else {
                        let latMid = (latMin + latMax) / 2;
                        if (cd & mask) latMin = latMid; else latMax = latMid;
                    }
                    isEven = !isEven;
                }
            }
            lat = (latMin + latMax) / 2;
            lon = (lonMin + lonMax) / 2;
            return { lat, lon };
        };

        const formatDateTime = () => {
            return new Date().toLocaleDateString('en-US', {
                weekday: 'short', day: 'numeric', month: 'short', year: 'numeric',
            });
        };

        const deleteModal = async (itemId) => {
            Swal.fire({
                title: "Are you sure?",
                text: "You want to be cancel this ride!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#34c38f",
                cancelButtonColor: "#f46a6a",
                confirmButtonText: "Yes, Cancel it!",
                cancelButtonText: "Close",
            }).then(async (swalResult) => {
                if (!swalResult.isConfirmed) return;
                try {
                    const response = await axios.get(`/rides-request/cancel/${itemId}`);
                    result.value = response.data.request || result.value;
                    result.value.is_cancelled = true;
                    rideStatus.value = t("ride_cancelled");
                    Swal.fire(t('success'), t('trip_cancelled_successfully'), 'success');
                    fetchDossier();
                } catch (e) {
                    Swal.fire(t('error'), t('failed_to_cancel_trip'), 'error');
                }
            });
        };

        const printPage = () => window.print();

        const onCopyRide = async () => {
            const msg = await copyRideSummary();
            if (msg) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: msg, showConfirmButton: false, timer: 1500 });
        };

        const onCopyPricing = async () => {
            const msg = await copyPricing();
            if (msg) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: msg, showConfirmButton: false, timer: 1500 });
        };

        onMounted(async () => {
            fetchDossier();

            if (result.value.is_cancelled) {
                rideStatus.value = t("ride_cancelled");
            } else if (result.value.is_completed) {
                rideStatus.value = t("ride_completed");
            } else if (result.value.is_trip_start) {
                rideStatus.value = t("on_trip");
            } else if (result.value.is_driver_arrived) {
                rideStatus.value = t("driver_arrived");
            } else if (result.value.is_later && result.value.is_driver_started) {
                rideStatus.value = t("driver_started");
            } else if (result.value.driver_id) {
                rideStatus.value = t("accepted");
            } else if (!result.value.is_later) {
                rideStatus.value = t("searching");
            } else {
                rideStatus.value = t("upcoming");
            }

            if (!result.value.is_cancelled && !result.value.is_completed) {
                try {
                    const firebaseConfig = props.firebaseConfig;
                    if (!firebase.apps.length) {
                        firebase.initializeApp(firebaseConfig);
                    }
                    const database = firebase.database();
                    const tripRef = database.ref(`requests/${result.value.id}`);
                    tripRef.on('value', (snapshot) => {
                        const val = snapshot.val();
                        if (!val) return;
                        if (val.hasOwnProperty('is_completed')) {
                            result.value.is_completed = true;
                            rideStatus.value = t("ride_completed");
                            setTimeout(() => window.location.reload(), 2000);
                        }
                        if (val.accept !== 1) {
                            result.value.driver_id = null;
                            rideStatus.value = t("searching");
                        }
                        if (val.driver_id && result.value.driver_id && !props.request.driverDetail) {
                            window.location.reload();
                        }
                        if (result.value.is_later && val.hasOwnProperty('modified_by_driver')) {
                            rideStatus.value = t("driver_started");
                            result.value.is_driver_started = 1;
                        }
                        if (val.trip_arrived == 1) {
                            rideStatus.value = t("driver_arrived");
                            result.value.is_driver_arrived = true;
                            if (!result.value.converted_arrived_at) {
                                result.value.converted_arrived_at = formatDateTime();
                            }
                        }
                        if (val.trip_start == 1) {
                            rideStatus.value = t("on_trip");
                            result.value.is_trip_start = true;
                            if (!result.value.converted_trip_start_time) {
                                result.value.converted_trip_start_time = formatDateTime();
                            }
                        }
                        if (val.is_cancelled || val.is_cancel) {
                            result.value.is_cancelled = true;
                            setTimeout(() => window.location.reload(), 2000);
                        }
                    });
                    if (result.value.driverDetail?.data && result.value.is_driver_started) {
                        database.ref(`drivers/driver_${props.request.driverDetail.data?.id}`).on('value', (snapshot) => {
                            const driver = snapshot.val();
                            const driverLocation = decodeGeohash(driver?.g);
                            if (driver && driverLocation) {
                                driverOption.value = {
                                    lat: driverLocation.lat,
                                    lng: driverLocation.lon,
                                    type_icon: driver.vehicle_type_icon,
                                    bearing: driver.bearing,
                                };
                            }
                        });
                    }
                } catch (e) {
                    console.error('Error initializing Firebase or fetching settings:', e);
                }
            }
        });

        return {
            result,
            modalShow,
            successMessage,
            alertMessage,
            deleteModal,
            stops,
            proof,
            rideStatus,
            dismissMessage,
            driverOption,
            dossier,
            loading,
            error,
            showRawJson,
            pricingAudit,
            isReconstructed,
            printPage,
            onCopyRide,
            onCopyPricing,
        };
    },
};
</script>

<template>
    <Layout>
        <Head title="Ride Details" />
        <PageHeader :title="$t('view_details')" :pageTitle="$t('view_details')" pageLink="/rides-request"/>

        <div v-if="successMessage" class="alert alert-success custom-alert no-print" @click="dismissMessage">{{ successMessage }}</div>
        <div v-if="alertMessage" class="alert alert-danger custom-alert no-print" @click="dismissMessage">{{ alertMessage }}</div>

        <BRow class="no-print mb-3">
            <BCol lg="12">
                <BCard no-body>
                    <BCardHeader>
                        <h4 class="mb-1">{{ $t("map_view") }}</h4>
                    </BCardHeader>
                    <BCardBody>
                        <div style="height: 400px;">
                            <googleMap
                                :baseUrl="baseUrl"
                                :pick_location="{lat:result.pick_lat, lng:result.pick_lng}"
                                :drop_location="{lat:result.drop_lat, lng:result.drop_lng}"
                                :driver="driverOption"
                                :stops="result.stops"
                                :polyline="result.poly_line"
                                :libraries="['marker','geometry']"
                                :map_key="googleMapKey"
                            >
                                {{ $t("map_loading") }}
                            </googleMap>
                        </div>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <RideDossierSections
            :dossier="dossier"
            :loading="loading"
            :error="error"
            :ride-status="rideStatus"
            :mask="app_for === 'demo'"
            :pricing-audit="pricingAudit"
            :is-reconstructed="isReconstructed"
            :show-raw-json="showRawJson"
            @cancel="deleteModal(result.id)"
            @print="printPage"
            @copy-ride="onCopyRide"
            @copy-pricing="onCopyPricing"
            @toggle-raw="showRawJson = !showRawJson"
        />
    </Layout>
</template>

<style>
.custom-alert {
    max-width: 600px;
    float: right;
    position: fixed;
    top: 90px;
    right: 20px;
    z-index: 30;
}
</style>
