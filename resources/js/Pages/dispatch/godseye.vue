<script>
import { Head, router} from '@inertiajs/vue3';
import { ref, watch, onMounted, computed} from "vue";
import { useI18n } from 'vue-i18n';
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import Layout from "@/Layouts/DispatcherPro/dispatchPromain.vue";
import googleMap from '@/Components/googleMap.vue';
import PageHeader from "@/Components/page-header.vue";

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
        app_for:String,
        default_location:Object,
        baseUrl:String,
        total_drivers: Array,
        countries: Array,
        default_flag: String,
        default_dial_code: String,
    },
    setup(props) {
        const { t } = useI18n();
        const map = ref(null);
        // Reactive data for filters
        const selectedVehicleTypes = ref(null);
        const selectedDriverModes = ref([]);
        const selectedMobile = ref(null);
        const drivers_list = ref([]);
        const totalDrivers = ref(props.total_drivers);
         const selectedCountry = ref({
            dial_code: props.default_dial_code || "",
            flag: props.default_flag || "",
        });
         const searchQuery = ref("");
        const filteredCountries = computed(() => {
            return props.countries.filter((country) =>
                country.name.toLowerCase().includes(searchQuery.value.toLowerCase())
            );
        });

        // Options for filters
        const serviceLocations = ref(props.service_location.map(loc => ({ value: loc.id, label: loc.name })));
        const vehicleTypes = ref(props.vehicle_type.map(type => ({ value: type.id, label: type.name })));

        const selectedServiceLocations = ref(serviceLocations.value?.[0]?.value);
        // Map and related variables
        const driverMarkers = ref({});

        const selectedDriver = ref(null);

        const clearFilters = () => {
            selectedDriver.value = null;
            selectedVehicleTypes.value = null;
            selectedDriverModes.value = [];
            selectedMobile.value = null;
            fetchNearbyDrivers();
        }
        const applyFilters = () => {
            selectedDriver.value = null;
            fetchNearbyDrivers();
        }

        const selectCountry = (country) => {
            selectedCountry.value = country;
            form.country = country.dial_code;
        };
        
        watch([selectedVehicleTypes, selectedDriverModes, selectedMobile], () => {
            selectedDriver.value = null;
            fetchNearbyDrivers();
        });

        // Fetch drivers and update markers
        const fetchNearbyDrivers = async() => {
            const driversRef = firebase.database().ref('drivers');

            driversRef.once('value', (snapshot) => {
                const drivers = snapshot.val();

                if (!drivers) return;

                driverMarkers.value = {};
                drivers_list.value = [];

                Object.keys(drivers).forEach(driverId => {
                    const driver = drivers[driverId];
                    const driverGeoHash = driver.g;
                    const decodedDriver = decodeGeohash(driverGeoHash);

                    if (decodedDriver && driver.service_location_id) {
                        const driverLatLng = new google.maps.LatLng(decodedDriver.lat, decodedDriver.lon);
                        // Filter based on selected service location and vehicle types
                        let matchesServiceLocation = driver.service_location_id ? true : false;
                        if(selectedServiceLocations.value && selectedServiceLocations.value !== "all"){
                            matchesServiceLocation = selectedServiceLocations.value == driver.service_location_id ?? false;
                        }

                        let matchesVehicleType = Array.isArray(driver.vehicle_types);
                        if(selectedVehicleTypes.value && Array.isArray(driver.vehicle_types) && driver.vehicle_types.length>0){
                            matchesVehicleType = (Array.isArray(driver.vehicle_types) && driver.vehicle_types.some(type => selectedVehicleTypes.value === type)) ?? false;
                        }
                        
                        let matchesMobileNumber = true;
                        if (selectedMobile.value) {
                            const enteredNumber = selectedCountry.value.dial_code + selectedMobile.value;
                            matchesMobileNumber = enteredNumber === driver.mobile;
                        }

                        let active = selectedDriverModes.value.length>0 ? selectedDriverModes.value.includes("online") : true;
                        let onride = selectedDriverModes.value.length>0 ? selectedDriverModes.value.includes("onride") : true;
                        let offline = selectedDriverModes.value.length>0 ? selectedDriverModes.value.includes("offline") : true;

                        let vehicleTypeIconUrl="";
                        let status="offline";

                        let last_seen = '';
                        if(driver.hasOwnProperty('is_active') && driver.hasOwnProperty('is_available')){
                            if (driver.is_active == 1 && driver.is_available==true && active) {
                                vehicleTypeIconUrl = `/image/map/${driver.vehicle_type_icon}.png`;
                                status="online";
                            } else if (driver.is_active == 1 && driver.is_available==false && onride) {
                                status="onride";
                                vehicleTypeIconUrl = `/image/map/${driver.vehicle_type_icon}.png`;
                            } else {
                                if(offline){
                                    vehicleTypeIconUrl = `/image/map/${driver.vehicle_type_icon}.png`;

                                    let last_seen_time = new Date() - new Date(driver.updated_at);
                                    let seenInMinutes = parseInt(last_seen_time / 60000),
                                        seenInHours = 0,
                                        seenInDays = 0,
                                        seenInWeeks = 0;
                                    if(seenInMinutes > 59){
                                        seenInHours = parseInt(seenInMinutes / 60);
                                        if(seenInHours > 23){
                                        seenInDays = parseInt(seenInHours / 24);
                                        if(seenInDays > 6){
                                            seenInWeeks = parseInt(seenInDays / 7);
                                        }
                                        }
                                    }
                                    if(seenInMinutes <= 1){
                                        last_seen = 'just now';
                                    }
                                    if(seenInMinutes > 1 && seenInMinutes < 59){
                                        last_seen = seenInMinutes + ' minutes ago';
                                    }
                                    if(seenInHours == 1){last_seen = 'An hour ago'}
                                    if(seenInHours > 1){
                                        last_seen = seenInHours + ' hours ago';
                                    }
                                    if(seenInDays == 1){last_seen = 'A day ago'}
                                    if(seenInDays > 1){
                                        last_seen = seenInDays +' days ago';
                                    }
                                    if(seenInWeeks == 1){last_seen = 'A week ago'}
                                    if(seenInWeeks > 1){
                                        last_seen = seenInWeeks + ' weeks ago';
                                    }
                                }
                            }
                        }
                        if (matchesServiceLocation && matchesVehicleType && vehicleTypeIconUrl.length > 0 && matchesMobileNumber ) {
                            const mobile = props.app_for == 'demo' ? "*********" : driver.mobile;

                            driver.status = status;
                            driver.last_seen = last_seen;
                            drivers_list.value.push(driver);

                            driverMarkers.value[driver.id] = {
                                lat: decodedDriver.lat,
                                lng: decodedDriver.lon,
                                type_icon: driver.vehicle_type_icon,
                                name: driver.name,
                                mobile: mobile,
                            };
                        }
                    }
                });
            });
        };

        // Decode geohash to latitude and longitude
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

        // Watch filters and update map when they change
        watch([selectedDriverModes, selectedVehicleTypes, selectedDriverModes], () => {
            fetchNearbyDrivers();
        }),
        onMounted(async() => {

            var firebaseConfig = {
                apiKey: props.firebaseSettings['firebase_api_key'],
                authDomain: props.firebaseSettings['firebase_auth_domain'],
                databaseURL: props.firebaseSettings['firebase_database_url'],
                projectId: props.firebaseSettings['firebase_project_id'],
                storageBucket: props.firebaseSettings['firebase_storage_bucket'],
                messagingSenderId: props.firebaseSettings['firebase_messaging_sender_id'],
                appId: props.firebaseSettings['firebase_app_id'],
            };
            if(!firebase.apps.length){
                firebase.initializeApp(firebaseConfig);
            }

            fetchNearbyDrivers();
            
            const refresh = localStorage.getItem('refreshMethod') ?? 0; 
            refresh_Method.value = refresh;

            applyRefreshMethod(refresh);

        });
        const makebooking = (id) =>{
            router.get("/dispatcher-pro/bookride",{id});
        };

        const viewData = async (id) =>  {
            router.get(`/dispatcher-pro/driver/view-profile/${id}`); 
        };
        

        const refresh_Method = ref();
        const refresh_btn = ref(false);
        const myInterval = ref(null);

        const refreshMethod  = async(refreshvalue) =>{            
            localStorage.setItem('refreshMethod', refreshvalue);
            applyRefreshMethod(refreshvalue);

        }
        const refreshBtn = async ()=>{
            await fetchNearbyDrivers();            
        }
        const applyRefreshMethod = (refresh) => {

            // clear existing interval
            if (myInterval.value) {
                clearInterval(myInterval.value);
                myInterval.value = null;
            }

            if (refresh == 0) {
                refresh_btn.value = false;

                // start interval
                myInterval.value = setInterval(async () => {
                    await fetchNearbyDrivers();
                }, 1000);

            } else {
                refresh_btn.value = true;                
                clearInterval(myInterval.value);
                myInterval.value = null;
            }
        };

        return {
            serviceLocations,
            vehicleTypes,
            selectedDriverModes,
            selectedDriver,
            drivers_list,
            clearFilters,
            applyFilters,
            driverMarkers,
            selectedVehicleTypes,
            totalDrivers,
            makebooking, 
            selectedMobile,
            selectedCountry,
            selectCountry,
            filteredCountries,
            searchQuery,
            viewData,            
            refreshMethod,
            refresh_Method,
            refresh_btn,
            refreshBtn
        };
    },
};
</script>



<template>
    <Layout>

        <Head :title="$t('gods-eye')" />
        <div class="font">
            <PageHeader :title="$t('gods-eye')" :pageTitle="$t('dispatch')" />
              <BRow>
                <BCol xl="3" md="6">
                <BCard no-body class="card-animate">
                    <BCardBody class="short-left-border">
                        <div class="d-flex align-items-center ms-2">
                            <div class="flex-grow-1 overflow-hidden">
                                <p class="text-uppercase fw-medium text-muted text-truncate mb-0">
                                    {{ $t("total_drivers") }}
                                </p>
                            </div>
                        </div>
                        <div class="d-flex align-items-end justify-content-between mt-4 ms-2">
                            <div>
                            <h4 class="fs-22 fw-semibold ff-secondary">
                                {{totalDrivers.total}}
                            </h4>
                            </div>
                            <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title  rounded fs-3" style="background-color: #074E74;">
                                <i class="bx bx-user-circle text-white fs-3"></i>
                            </span>
                            </div>
                        </div>
                    </BCardBody>
                </BCard>
                </BCol>
                <BCol xl="3" md="6">
                    <BCard no-body class="card-animate">
                        <BCardBody class="short-left-border">
                            <div class="d-flex align-items-center ms-2">
                                <div class="flex-grow-1 overflow-hidden">
                                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">
                                        {{ $t("approved_drivers") }}
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex align-items-end justify-content-between mt-4 ms-2">
                                <div>
                                <h4 class="fs-22 fw-semibold ff-secondary">
                                    {{totalDrivers.approved}}
                                </h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title  rounded fs-3" style="background-color: #074E74;">
                                    <i class="bx bx-user-circle text-white fs-3"></i>
                                </span>
                                </div>
                            </div>
                        </BCardBody>
                    </BCard>
                </BCol>
                <BCol xl="3" md="6">
                    <BCard no-body class="card-animate">
                        <BCardBody class="short-left-border">
                            <div class="d-flex align-items-center ms-2">
                                <div class="flex-grow-1 overflow-hidden">
                                    <p class="text-uppercase fw-medium text-muted text-truncate mb-0">
                                        {{ $t("pending_drivers") }}
                                    </p>
                                </div>
                            </div>
                            <div class="d-flex align-items-end justify-content-between mt-4 ms-2">
                                <div>
                                <h4 class="fs-22 fw-semibold ff-secondary">
                                    {{totalDrivers.declined}}
                                </h4>
                                </div>
                                <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title  rounded fs-3" style="background-color: #074E74;">
                                    <i class="bx bx-user-circle text-white fs-3"></i>
                                </span>
                                </div>
                            </div>
                        </BCardBody>
                    </BCard>
                </BCol>
            </BRow>
            <BRow>
                <BCol lg="12">
                <BCard no-body id="tasksList">
                    <BCardHeader class="border-0">
                    <h4>{{$t("driver_filters")}}</h4>
                    <div class="row mt-4">

                        <div class="col-12 col-lg-2">
                            <div class="mb-3">
                                <label for="select_driver_mode" class="form-label">{{$t("driver_mode")}}</label>
                                <Multiselect 
                                v-model="selectedDriverModes"
                                id="select_driver_mode" 
                                mode="tags" 
                                :close-on-select="false"
                                :searchable="true" 
                                :create-option="false"
                                :options="[
                                    { value: 'offline', label: $t('offline') },
                                    { value: 'online', label: $t('online') },
                                    { value: 'onride', label: $t('onride') },
                                ]"
                                :placeholder="$t('select_mode')"
                                />
                            </div>
                        </div>

                        <div class="col-12 col-lg-3">
                            <div class="mb-3">
                                <label for="vehicle_type" class="form-label">{{$t("vehicle_types")}}</label>
                                <Multiselect
                                    v-model="selectedVehicleTypes"
                                    :options="vehicleTypes"
                                    label="label"
                                    track-by="value"
                                    multiple
                                    close-on-select
                                    :placeholder="$t('select_vehicle_types')"
                                />
                            </div>
                        </div>
                        <div class="col-12 col-lg-3">
                            <label class="form-label">{{$t("mobile")}}</label>
                            <div class="input-group" data-input-flag="">
                                <button class="btn btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <img :src="selectedCountry.flag" alt="flag" height="20" class="country-flagimg rounded">
                                    <span class="ms-2 country-codeno">{{ selectedCountry.dial_code }}</span>
                                </button>
                                <input type="text" id="mobile" class="form-control rounded-end flag-input" v-model="selectedMobile" @keydown="preventDefault" :placeholder="$t('enter_number')" @input="validateNumber">
                                <div class="dropdown-menu w-100">
                                    <div class="p-2 px-3 pt-1 searchlist-input">
                                        <input type="text" class="form-control form-control-sm border search-countryList" :placeholder="$t('search_country_name_or_country_code')" v-model="searchQuery">
                                    </div>
                                    <ul class="list-unstyled dropdown-menu-list mb-0">
                                        <li v-for="country in filteredCountries" :key="country.id">
                                            <a href="javascript:void(0);" class="dropdown-item notify-item language py-2" @click="selectCountry(country)">
                                                <img :src="country.flag" alt="flag" class="me-2 rounded" height="18">
                                                <span class="align-middle">{{ country.name }} {{ country.dial_code }}</span>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                                  
                        <div class="col-sm-2">
                            <div>
                                <div class="mb-3">
                                <label for="type" class="form-label" >{{$t("refresh_method")}}
                                    <span class="text-danger">*</span>
                                </label>
                                <select id="type" class="form-select" v-model="refresh_Method" @change="refreshMethod(refresh_Method)">
                                    <option disabled value="">{{ $t("select_refresh_method") }}</option>
                                    <option  value="0">{{ $t("automatic") }}</option>
                                    <option  value="1">{{ $t("manual") }}</option>
                                </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-2" v-if="refresh_btn">
                            <div class="mb-3 mt-4">
                                <BButton type="button" variant="primary" class="btn btn-md" @click="refreshBtn()">
                                    <i class="bx bx-refresh"></i> {{$t("refresh")}}
                                </BButton>
                            </div>
                        </div>
                        <div class="col-12 col-lg-3  gap-1">
                        <BButton type="button" variant="danger" class="btn btn-md mt-4" @click="clearFilters">{{$t("clear")}}</BButton>
                        </div>
                    </div>
                    </BCardHeader>
                    <BCardBody class="border border-dashed border-end-0 border-start-0">
                        <BRow>
                            <BCol lg="4">
                                <div v-if="drivers_list.length>0" class="overflow-auto" style="height: 400px;">
                                    <BCard class="col-lg-12" v-for="(driver) in drivers_list">
                                        <div @click="selectedDriver = driverMarkers[driver.id]" class="accordion-item border-0">
                                            <div class="accordion-header" id="headingThree">
                                                <a class="accordion-button p-2 shadow-none" data-bs-toggle="collapse" href="#" aria-expanded="false" aria-controls="collapseThree">
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 border border-4 rounded-circle" style="border: var(--landing_header_act_text);">
                                                            <img :src="driver.profile_picture" alt="" class="avatar-md rounded-circle">
                                                        </div>
                                                        <div class="d-flex justify-content-around">
                                                        <div class="flex-grow-1 ms-3">
                                                            <h4 class="fs-15 mb-1 fw-semibold" style="color: var(--landing_header_act_text);">{{ driver.name }}</h4>
                                                            <h6 class="fs-15 mb-1 fw-normal" style="color: var(--landing_header_act_text);">{{ app_for == 'demo' ? "*********" :  driver.mobile }}</h6>
                                                            <h6 class="fs-15 mb-1 fw-normal">{{ driver.rating }} <i class="ri-star-fill text-warning align-bottom me-1" /></h6>
                                                        </div>
                                                        <div class="text-end ms-5 d-block">
                                                            <h4 v-if="driver.status == 'offline'" class="fs-15 mb-1 fw-semibold text-danger">{{ driver.status }}</h4>
                                                            <h4 v-if="driver.status == 'online'" class="fs-15 mb-1 fw-semibold text-success">{{ driver.status }}</h4>
                                                            <h4 v-if="driver.status == 'onride'" class="fs-15 mb-1 fw-semibold text-primary">{{ driver.status }}</h4>
                                                            <p class="flex-grow-1 fs-15 mb-1 text-muted mt-3">{{ driver.last_seen }}</p>
                                                        </div>
                                                        </div>
                                                    </div> 
                                                </a>     
                                                <div class="d-flex align-items-center justify-content-end">                                                     
                                                    <div class="align-items-center me-3 mt-4 text-success text-decoration-underline" type="button" @click="viewData(driver.id)"> {{$t("view_profile")}}</div>                                                                                                                                              
                                                    <BButton type="button" variant="primary" class="btn btn-md mt-4" @click="makebooking(driver.id)" v-if="driver.status === 'online'">{{$t("make_booking")}}</BButton>
                                                </div>   
                                            </div>
                                        </div>
                                    </BCard>
                                </div>
                                <div class="d-flex" v-else style="height: 400px;">
                                    <img src="/image/map/no-drivers.png" style="width: 60%;margin:auto;" alt="No Drivers Found">
                                </div>
                            </BCol>
                            <BCol lg="8">
                                <div id="map" style="height: 400px;">
                                    <googleMap
                                        :baseUrl="baseUrl"
                                        :default_location="default_location"
                                        :nearbyDrivers="driverMarkers"
                                        :driver="selectedDriver"
                                        :map_key="map_key"
                                        :libraries="['marker']"
                                    >
                                    {{$t("map_loading")}}
                                    </googleMap>
                                </div>
                            </BCol>
                        </BRow>
                    </BCardBody>
                </BCard>
                </BCol>
            </BRow>
        </div>
   </Layout>
</template>
<style scoped>
@font-face {
  font-family: "Zona Pro";
  src: url("/assets/fonts/ZonaPro-Bold.woff2") format("woff2"),
       url("/assets/fonts/ZonaPro-Bold.woff") format("woff");
  font-weight: normal;
  font-style: normal;
}
@font-face {
  font-family: "Zona Pro";
  src: url("/assets/fonts/ZonaPro-Thin.woff2") format("woff2"),
       url("/assets/fonts/ZonaPro-Thin.woff") format("woff");
  font-weight: normal;
  font-style: normal;
}
.font {
  font-family: "Zona Pro", sans-serif;
}
.short-left-border {
  position: relative;
  padding-left: 15px; /* space for the border */
}

.short-left-border::before {
  content: "";
  position: absolute;
  left: 0;
  top: 31px;       /* distance from top */
  height: 58px;    /* height of the border */
  width: 4px;      /* border width */
  background-color: #032C4A;
  border-bottom-right-radius: 15px;
  border-top-right-radius: 15px;
}
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

</style>
