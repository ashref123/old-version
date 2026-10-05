<script>
import { Link, Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Pagination from "@/Components/Pagination.vue";
import Swal from "sweetalert2";
import { ref, onMounted } from "vue";
import axios from "axios";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";
import search from "@/Components/widgets/search.vue";
import searchbar from "@/Components/widgets/searchbar.vue";
import { useI18n } from 'vue-i18n';
import L from "leaflet";
import 'leaflet-routing-machine';
import "leaflet/dist/leaflet.css";
import polyline from '@mapbox/polyline';
import RideDossierSections from './components/RideDossierSections.vue';
import { useRideDossier } from './composables/useRideDossier';


export default {
    data() {
        return {
            rightOffcanvas: false,
        };
    },
    components: {
        Layout,
        PageHeader,
        Head,
        Multiselect,
        flatPickr,
        Link,
        search,
        searchbar,
        RideDossierSections,
    },
    props: {
        successMessage: String,
        alertMessage: String,

        app_for: String,
        pick_icon: String,
        drop_icon: String,
        stop_icon: String,
        firebaseConfig: Object,
        request: Object,
        dossierUrl: String,
    },
    setup(props) {
        const { t } = useI18n();
        const result = ref(props.request);
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
        const modalShow = ref(false);
        const showStops = ref(false);
        const proof = ref(props.request.requestProofs.data);
        const stops = ref(props.request ? props.request.requestStops.data :  []);
        const rejected_drivers = ref(props.request ? props.request.rejectedDrivers.data :  []);
        const successMessage = ref(props.successMessage || '');
        const alertMessage = ref(props.alertMessage || '');
        const map = ref(null);
        const driverMarker = ref(null);
        const currentLat = ref(null);
        const currentLng = ref(null);
        const pickupMarker = ref(null);
        const dropMarker = ref(null);
        const stopMarker = ref([]);

        const user_mobile = ref("**********");
        const user_email = ref("**********");
        const driver_mobile =ref("**********");
        const driver_email =ref("**********");

        const dismissMessage = () => {
            successMessage.value = "";
            alertMessage.value = "";
        };

        const initializeMap = async () => {
            map.value = L.map('map',{
                zoomControl: false,
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                touchZoom: false
            }).setView([result.value.pick_lat, result.value.pick_lng], 12);
            
            map.value.dragging.disable();
            map.value.scrollWheelZoom.disable();
            map.value.doubleClickZoom.disable();
            map.value.touchZoom.disable();

            L.tileLayer(window.osmTileUrlTemplate, {
                maxZoom: 19,
            }).addTo(map.value);

        
            const pickupIcon = L.icon({
                iconUrl: '/image/map/pickup.png',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });

            const dropIcon = L.icon({
                iconUrl: '/image/map/drop.png',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });

            pickupMarker.value = L.marker([result.value.pick_lat, result.value.pick_lng], { icon: pickupIcon, draggable:false } ).addTo(map.value);
            dropMarker.value = L.marker([result.value.drop_lat, result.value.drop_lng], { icon:  dropIcon, draggable:false } ).addTo(map.value);

            stops.value.forEach( (stop,index)=> {
                const stopIcon = L.icon({
                    iconUrl: '/image/map/'+index+'.png',
                    iconSize: [32, 32],
                    iconAnchor: [16, 32],
                    popupAnchor: [0, -32]
                });
                const marker = L.marker([stop.latitude, stop.longitude], { icon:  stopIcon, draggable:false } ).addTo(map.value);
                stopMarker.value[index] = marker;
            })
            if(result.value.poly_line) {
                const decodedCoordinates = polyline.decode(result.value.poly_line);
            
                let routePolyline = L.polyline(decodedCoordinates, { color: 'blue' }).addTo(map.value);
                map.value.fitBounds(routePolyline);
            }
        };

        const closeModal = () => {
            modalShow.value = false;
        };
        const deleteData = async (dataId) => {
            try {
                const response = await axios.get(`/rides-request/cancel/${dataId}`);
                result.value = response.data.request;
                modalShow.value = false;
                Swal.fire(t('success'), t('trip_cancelled_successfully'), 'success');
            } catch (error) {
                Swal.fire(t('error'), t('failed_to_cancel_trip'), 'error');
            }
        };

        const rideStatus = ref('');

        const cancelledBy = (cancel) =>{
            if(cancel.cancel_method == 0){
                return t('automatic');
            }else if(cancel.cancel_method == 1){
                return t('cancelled_by_user');
            }else if(cancel.cancel_method == 2){
                return t('cancelled_by_driver');
            }else{
                return t('cancelled_by_dispatcher')
            }
        };
        const formatDateTime = () => {
            const now = new Date();
            const options = { 
                weekday: 'short', 
                day: 'numeric', 
                month: 'short', 
                year: 'numeric' 
            };
            return now.toLocaleDateString('en-US', options);
        }

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
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        await deleteData(itemId);
                    } catch (error) {
                        console.error(t('error_deleting_data'), error);
                        Swal.fire(t('error'), t('failed_to_delete_the_data'), "error");
                    }
                }
            });
        };

        onMounted( async ()=> {
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
                if (val) {
                    if (val.hasOwnProperty('is_completed')) {
                        result.value.is_completed = true;
                        rideStatus.value = t("ride_completed");
                        setTimeout(() => {
                            window.location.reload();
                         }, 2000);
                    }
                    if (val.accept !== 1) {
                        result.value.driver_id = null;
                        rideStatus.value = t("searching");

                    }
                    if (val.driver_id && result.value.driver_id) {
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
                        setTimeout(() => {
                            window.location.reload();
                         }, 2000);
                        
                    }
                }
            });
            if(result.value.driverDetail?.data && result.value.is_driver_started){

            const driversRef = firebase.database().ref('drivers/driver_' + result.value.driverDetail?.data?.id);

                driversRef.on('value', (snapshot) => {
                        const driver = snapshot.val();
                        const driverLocation = decodeGeohash(driver.g);

                    if (driverLocation) {
                        const driverLatLng = [driverLocation.lat, driverLocation.lon];
                        currentLat.value = driverLocation.lat;
                        currentLng.value = driverLocation.lon;

                        let vehicleTypeIconUrl = driver.vehicle_type_icon 
                            ? `/image/map/${driver.vehicle_type_icon}.png` 
                            : "";

                        if (vehicleTypeIconUrl.length > 0) {
                            if (driverMarker.value) {
                                // Animate the movement

                                const toLatLng = { lat: driverLocation.lat, lng: driverLocation.lon };
                                animateDriverMovement(driverMarker.value, toLatLng);

                                // Update the icon if needed
                                driverMarker.value.setIcon(
                                    L.icon({
                                        iconUrl: vehicleTypeIconUrl,
                                        iconSize: [30, 30],
                                    })
                                );
                            } else {
                                // Create the marker only if it doesn't exist
                                driverMarker.value = L.marker(driverLatLng, {
                                    icon: L.icon({
                                        iconUrl: vehicleTypeIconUrl,
                                        iconSize: [30, 30],
                                    }),
                                }).addTo(map.value);

                                map.value.setView([currentLat.value, currentLng.value], 15);
                            }
                        } else if (driverMarker.value) {
                            map.value.removeLayer(driverMarker.value);
                            driverMarker.value = null;
                        }
                    }

                });
            }
        } catch (error) {
            console.error('Error initializing Firebase or fetching settings:', error);
        }
    }else{
        
        if(result.value.is_cancelled){
            rideStatus.value = t("ride_cancelled");
        }else if(result.value.is_completed){
            rideStatus.value = t("ride_completed");
        }else if(result.value.is_result.value_start){
            rideStatus.value = t("on_trip");
        }else if(result.value.is_driver_arrived){
            rideStatus.value = t("driver_arrived");
        }else if(result.value.is_later && result.value.is_driver_started){
            rideStatus.value = t("driver_started");
        }else if(result.value.driver_id){
            rideStatus.value = t("accepted");
        }else if(!result.value.is_later){
            rideStatus.value = t("searching");
        }else{
            rideStatus.value = t("upcoming");
        }
    }
        });
        onMounted(() => {
            initializeMap();
            fetchDossier();
        });

        const printPage = () => window.print();
        const onCopyRide = async () => {
            const msg = await copyRideSummary();
            if (msg) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: msg, showConfirmButton: false, timer: 1500 });
        };
        const onCopyPricing = async () => {
            const msg = await copyPricing();
            if (msg) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: msg, showConfirmButton: false, timer: 1500 });
        };

        const animateDriverMovement = (marker, toLatLng) => {
    if (!marker || !toLatLng || isNaN(toLatLng.lat) || isNaN(toLatLng.lng)) {
        console.error("Invalid marker or destination coordinates:", marker, toLatLng);
        return;
    }

    const duration = 2000;
    const start = performance.now();
    const easeInOutQuad = (t) => (t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t);

    const fromLat = marker.getLatLng().lat;
    const fromLng = marker.getLatLng().lng;
    const toLat = toLatLng.lat;
    const toLng = toLatLng.lng;

    if (isNaN(fromLat) || isNaN(fromLng)) {
        console.error("Invalid marker starting position:", marker.getLatLng());
        return;
    }

    const animateStep = (time) => {
        const elapsed = time - start;
        const t = Math.min(elapsed / duration, 1);
        const easedT = easeInOutQuad(t);

        const currentLat = fromLat + (toLat - fromLat) * easedT;
        const currentLng = fromLng + (toLng - fromLng) * easedT;

        // Ensure valid LatLng values
        if (isNaN(currentLat) || isNaN(currentLng)) {
            console.error("Calculated invalid LatLng during animation:", { currentLat, currentLng });
            return;
        }

        // Update marker position
        marker.setLatLng([currentLat, currentLng]);

        // Continue animation if not finished
        if (t < 1) {
            requestAnimationFrame(animateStep);
        }
    };

    requestAnimationFrame(animateStep);
};


        const decodeGeohash = (geohash) => {
const BASE32 = '0123456789bcdefghjkmnpqrstuvwxyz';
const BITS = [16, 8, 4, 2, 1];
let isEven = true;
let latMin = -90,
latMax = 90;
let lonMin = -180,
lonMax = 180;
let lat, lon;

if (geohash) {
for (let i = 0; i < geohash.length; i++) {
let c = geohash.charAt(i);
let cd = BASE32.indexOf(c);
for (let j = 0; j < 5; j++) {
let mask = BITS[j];
if (isEven) {
let lonMid = (lonMin + lonMax) / 2;
if (cd & mask) {
lonMin = lonMid;
} else {
lonMax = lonMid;
}
} else {
let latMid = (latMin + latMax) / 2;
if (cd & mask) {
latMin = latMid;
} else {
latMax = latMid;
}
}
isEven = !isEven;
}
}
lat = (latMin + latMax) / 2;
lon = (lonMin + lonMax) / 2;
return { lat: lat, lon: lon };
}
};


        if(props.app_for != "demo"){
            if(result.value.userDetail){
                user_mobile.value = result.value.userDetail.data?.mobile;
                user_email.value = result.value.userDetail.data?.email;
            }
            if(result.value.driverDetail){
                driver_mobile.value = result.value.driverDetail.data?.mobile;
                driver_email.value = result.value.driverDetail.data?.email;
            }
        }

        const dispatch_type = ref(t('normal'));
         // Normal Ride
         if (!result.value.is_bid_ride && !result.value.is_rental && !result.value.is_out_station) {
            if (result.value.is_later) {
                dispatch_type.value = `${t('normal')} (${t('scheduled')})`;
            } else {
                dispatch_type.value = t('normal');
            }
        }

        // Bidding Ride
        if (result.value.is_bid_ride) {
            if (result.value.is_later) {
                dispatch_type.value = `${t('bidding')} (${t('scheduled')})`;
            } else {
                dispatch_type.value = t('bidding');
            }
        }

        if(result.value.is_rental){
            dispatch_type.value = t('rental');
        }
        if(result.value.is_out_station){
            if(result.value.is_round_trip){
                dispatch_type.value = t('round_trip_outstation_trip');
            }else{
                dispatch_type.value = t('one_way_outstation_trip');
            }
        }
        return {
            result,
            modalShow,
            successMessage,
            alertMessage,
            rejected_drivers,
            deleteModal,
            closeModal,
            deleteData,
            stops,
            user_mobile,
            user_email,
            driver_mobile,
            driver_email,
            proof,
            showStops,
            rideStatus,
            dispatch_type,
            dismissMessage,
            cancelledBy,
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

        <BRow class="no-print mb-3">
            <BCol lg="12">
                <BCard no-body>
                    <BCardHeader>
                        <h4 class="mb-1">{{ $t("map_view") }}</h4>
                    </BCardHeader>
                    <BCardBody>
                        <div id="map" style="height: 400px;"></div>
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
}
.leaflet-control-container .leaflet-routing-container-hide {
  display: none;
}
.rtl .custom-alert {
  max-width: 600px;
  float: left;
  top: -300px;
  right: 10px;
}
@media only screen and (max-width: 1024px) {
  .custom-alert {
  max-width: 600px;
  float: right;
  position: fixed;
  top: 90px;
  right: 20px;
}
.rtl .custom-alert {
  max-width: 600px;
  float: left;
  top: -230px;
  right: 10px;
}
}
.profile-timeline .accordion-item::before {
    content: "";
    border-left: 2px dashed var(--vz-border-color);
    position: absolute;
    height: 100%;
    left: 46px;
}
</style>
