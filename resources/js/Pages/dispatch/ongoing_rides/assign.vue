<script>
import { Link,Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/DispatcherPro/dispatchPromain.vue";
import PageHeader from "@/Components/page-header.vue";
import Swal from "sweetalert2";
import { ref, watch,reactive,computed,onMounted } from "vue";
import axios from "axios";
import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";
import { Scrollbar } from 'swiper/modules';
import FormValidation from "@/Components/FormValidation.vue";
import debounce from 'lodash/debounce';
import { mapGetters } from 'vuex';
import { useI18n } from 'vue-i18n';
import googleMap from '@/Components/googleMap.vue';

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
Link,
Scrollbar,
FormValidation,
googleMap,
flatPickr,
},
computed: {
    ...mapGetters(['permissions']),
    
  },
props: {
successMessage: String,
alertMessage: String,
firebaseSettings:Array,
app_for:String,
map_key:String,
driver_radius: Number,
result: Object,
baseUrl:String,
preferenceIds: Array,
},

methods: {
    timer() {
      let timerInterval;
      Swal.fire({
        title: "Booking alert!",
        html: "Your Ride has been Booked <b></b> Successfully.",
        timer: 2000,
        timerProgressBar: true,
        onBeforeOpen: () => {
          Swal.showLoading();
          timerInterval = setInterval(() => {
            Swal.getContent().querySelector("b").textContent =
              Swal.getTimerLeft();
          }, 100);
        },
        onClose: () => {
          clearInterval(timerInterval);
        },
      }).then((result) => {
        if (
          /* Read more about handling dismissals below */
          result.dismiss === Swal.DismissReason.timer
        ) {
          console.log("I was closed by the timer"); // eslint-disable-line
        }
      });
    },
  },

setup(props) {
  const { t } = useI18n();


const modalShow = ref(false);
const driver_radius = ref(props.driver_radius ?? 10);
const successMessage = ref(props.successMessage || '');
const alertMessage = ref(props.alertMessage || '');

const driverSearch = ref('');
const dismissMessage = () => {
successMessage.value = "";
alertMessage.value = "";
};

const selectedDriver = ref(null);
const fleetDrivers = ref([]);
const modalDriver = ref(null);
const result = ref(props.result);
console.log("result",result.value);
const driverMarkers = ref({});

const assignModal = (driver) => {
  modalDriver.value = driver;
  modalShow.value = true;
}

const assignDriver = async (driver) => {
  try {
    const response = await axios.post(`/dispatcher-pro/ongoing_request/assign-driver/${result.value.id}`,{ 'driver_id' : driver.id});
    if (response.data.status) {
      modalShow.value = false;
      Swal.fire(t('success'), t('users_assigned_successfully'), 'success');
    } else {
      Swal.fire(t('Warning'),response.data.message , 'warning');
      console.log( response );
    }
  } catch (error) {
    Swal.fire(t('Warning'), response.data.message, 'warning');
    console.error( error );
  }
}

const fetchNearbyDrivers = () => {
driverMarkers.value = {};

const driversRef = firebase.database().ref('drivers');
driversRef.on('value', (snapshot) => {
fleetDrivers.value = [];
snapshot.forEach((childSnapshot) => {
const driver = childSnapshot.val();

const now = Date.now();
const sevenMinutesAgo = now - (15 * 60 * 1000);
if(driver.updated_at < sevenMinutesAgo) {
    return;
}

const driverGeoHash = driver.g;
const driverLocation = decodeGeohash(driverGeoHash);
let searchIncludes = true;

if(driverSearch.value.length > 0 && driver.name) {
  searchIncludes = driver.name.includes(driverSearch.value) || driver.mobile.includes(driverSearch.value)
}

if (driverLocation && searchIncludes) {
const distance = calculateDistance(result.value.pick_lat, result.value.pick_lng, driverLocation.lat, driverLocation.lon);
const preferenceArray = Object.values(driver.preferences || {});
const preference_available = props.preferenceIds.length === 0 
    ? true 
    : props.preferenceIds.every(pref => preferenceArray.includes(pref));
console.log("preference_available",preference_available);

if (distance <= driver_radius.value && driver.is_available && driver.is_active && driver.vehicle_types.includes(result.value.vehicle_type_id) && preference_available) {
fleetDrivers.value.push(driver);

const name = props.app_for == 'demo' ? "*********" : driver.name;
const mobile = props.app_for == 'demo' ? "*********" : driver.mobile;
selectedDriver.value = null;
driverMarkers.value[driver.id] = {
    lat: driverLocation.lat,
    lng: driverLocation.lon,
    type_icon: driver.vehicle_type_icon,
    name: name,
    mobile: mobile,
};


}
}

});
});

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

const calculateDistance = (lat1, lon1, lat2, lon2) => {
const R = 6371;
const dLat = (lat2 - lat1) * Math.PI / 180;
const dLon = (lon2 - lon1) * Math.PI / 180;
const a =
Math.sin(dLat / 2) * Math.sin(dLat / 2) +
Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
Math.sin(dLon / 2) * Math.sin(dLon / 2);
const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
const distance = R * c;
return distance;
};


onMounted(async () => {
    try{
        var firebaseConfig = {
            apiKey: props.firebaseSettings['firebase_api_key'],
            authDomain: props.firebaseSettings['firebase_auth_domain'],
            databaseURL: props.firebaseSettings['firebase_database_url'],
            projectId: props.firebaseSettings['firebase_project_id'],
            storageBucket:  props.firebaseSettings['firebase_storage_bucket'],
            messagingSenderId: props.firebaseSettings['firebase_messaging_sender_id'],
            appId: props.firebaseSettings['firebase_app_id'],
        };

        if (!firebase.apps.length) {
            firebase.initializeApp(firebaseConfig);
        }
        const requestRef = firebase.database().ref('requests').child(result.value.id);

        requestRef.on('value', (snapshot) => {
          const val = snapshot.val();
          if ((val && (val.is_accept || 'driver_id' in val))) {
              router.get(`/dispatcher-pro/rides_request/view/${result.value.id}`);
          }
        });
      fetchNearbyDrivers();
    }catch (error) {
      console.error(t('error_initializing_firebase_or_fetching_settings'), error);
    }
});


return {
modalShow,
selectedDriver,
successMessage,
driverSearch,
alertMessage,
modalDriver,
assignDriver,
assignModal,
driverMarkers,
fleetDrivers,
dismissMessage,
errors:{},
};
},


};
</script>

<template>
<Layout>
  <Head title="Taxi Ride" />
  <PageHeader :title="$t('assign_driver')" :pageTitle="$t('dispatch')" pageLink="/dispatcher-pro/ongoing_request"/>
  <div class="assign-drivers">
    <BRow>
      <BCol>
        <BCard>
          <BCardBody>
            <BRow>
              <h4 class="mb-4">{{$t("trip_details")}}</h4>
              <div class="col-xxl-5 col-md-6">
                <div class="card card-animate border border-dashed border-primary">
                  <div class="card-body">
                    <h4 class="mb-4 text-success badge bg-success-subtle fs-18 text-center">{{$t("pickup_location")}}</h4>
                    <h6 class="fs-14 mb-4">{{ result.pick_address }}</h6>
                    
                    <h4 class="mb-4 text-muted badge bg-secondary-subtle fs-18 text-center" v-if="result.requestStops.data.length > 0">{{$t("stop_location")}}</h4>
                    <br/>
                    <button class="btn btn-light dropdown-toggle mb-4" type="button" id="stop_locations_list" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" v-if="result.requestStops.data.length > 0">
                      {{ $t('view_stop_location') }}
                    </button>
                    <ul class="dropdown-menu overflow-auto" style="max-width:500px" aria-labelledby="stop_locations_list">
                      <li v-for="(stop,index) in result.requestStops.data">
                        <a class="dropdown-item overflow-auto" > {{ stop.address }}</a>
                      </li>
                    </ul>
                    <br/>
                    
                    <h4 class="mb-4 text-danger badge bg-danger-subtle fs-18 text-center">{{$t("drop_location")}}</h4>
                    <h6 class="fs-14 mb-4">{{ result.drop_address }}</h6>
                  </div>
                </div>
              </div>
              <div class="col-xxl-4 col-md-6">
                <div class="card card-animate border border-dashed border-primary">
                  <div class="card-body">
                    <h4 class="mb-3 flex-grow-1 text-start text-muted badge bg-secondary-subtle fs-18">{{$t("request")}}</h4>
                    <div class="flex-grow-1 mt-2 text-center">
                      <img src="@assets/images/mark.gif" class="img-fluid" style="width:55px;height:55px">
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("zone")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">{{ result.zone_name ?? '-' }}</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("transport_type")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">
                        <span v-if="result.transport_type === 'taxi'">{{ $t('taxi') }}</span>
                        <span v-else-if="result.transport_type === 'delivery'">{{ $t('delivery') }}</span>
                        <span v-else>{{ $t('all') }}</span>
                        </h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("vehicle_type")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">{{ result.vehicle_type_name ?? '-' }}</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("trip_time")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">{{ result.is_later ? result.converted_trip_start_time :result.converted_created_at }}</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center mb-2" v-if="result.rental_package_name !== '-'">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("rental_pack")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">{{ result.rental_package_name }}</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center mb-2" v-if="result.is_round_trip">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("return_time")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">{{ result.return_time }}</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center mb-2" v-if="result.requestPreferences.data?.length > 0">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("preferences")}}:</p>
                      </div>
                      <div class="ms-2" v-for="(preference, index) in result.requestPreferences.data" :key="index">
                        <h6 class="mb-0">{{ preference.name }} {{ index+1 == result.requestPreferences?.data.length ? '': ',' }}</h6>
                      </div>
                    </div>
                    <h4 class="mb-1 flex-grow-1 text-start text-muted badge bg-secondary-subtle fs-18">{{$t("estimated_ride_fare")}}: {{ result.request_eta_amount }}</h4>
                  </div>
                </div>
              </div>
              <div class="col-xxl-3 col-md-6">
                <div class="card card-animate border border-dashed border-primary">
                  <div class="card-body">
                    <h4 class="mb-3 flex-grow-1 text-start text-muted badge bg-secondary-subtle fs-18">{{$t("user_details")}}</h4>
                    <div class="flex-grow-1 mt-2 text-center">
                      <img :src="result.userDetail ? result.userDetail.data?.profile_picture : '@assets/images/user.gif'" class="img-fluid" style="width:55px;height:55px">
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("name")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">{{ result.userDetail ? result.userDetail.data?.name : '-' }}</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("email")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">{{ result.userDetail ? result.userDetail.data?.email : '-' }}</h6>
                      </div>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                      <div class="flex-shrink-0">
                        <p class="text-muted mb-0">{{$t("mobile")}}:</p>
                      </div>
                      <div class="flex-grow-1 ms-2">
                        <h6 class="mb-0">{{ result.userDetail ? result.userDetail.data?.mobile : '-' }}</h6>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
    <BRow>
      <BCol lg="12">
        <BCard no-body id="tasksList">
          <BCardHeader class="border-0">
            <h4>{{$t("drivers_list")}}</h4>          
          </BCardHeader>
          <BCardBody class="border border-dashed border-end-0 border-start-0">
    
            <div class="position-relative col-lg-4">
              <input type="text" class="form-control"  v-model="driverSearch" placeholder="Search..." autocomplete="off" id="search-options" value="">
            </div>

            <div class="row">
              <div class="col-12 col-lg-6" style="overflow-x: auto;">
                <div class="row">
                  <BRow>
                    <BCard no-body>
                      <BCardBody class="overflow-y-auto" style="max-height: 500px; width: 100%">
                        <BCol xl="12">
                          <div class="row" >
                            <!-- card -->
                            <div v-for="driver in fleetDrivers" :key="driver.driver_id" class="row">
                              <div class="col-sm-2 border-end d-flex align-items-center justify-content-center">
                                <div>
                                  <img class="rounded-circle avatar-md" alt="200x200" :src="driver.profile_picture">
                                </div>
                              </div>
                              <div class="col-sm-4 mt-3 border-end">
                                <div class=" d-flex align-items-center ">
                                  <i class="ri-user-line" style="font-size:20px"></i> &nbsp;&nbsp;
                                  <span>{{driver.name}}</span>
                                </div>
                                <div class=" d-flex align-items-center ">
                                  <i class=" ri-phone-line" style="font-size:20px"></i> &nbsp;&nbsp;
                                  <span>{{driver.mobile}}</span>
                                </div>
                                <div class=" d-flex align-items-center ">
                                  <i class="ri-star-fill" style="font-size:20px"></i> &nbsp;&nbsp;
                                  <span>{{ driver.rating }} </span>
                                </div>
                              </div>
                              <div class="col-sm-6 d-flex align-items-center">
                                <div class="row">
                                  <div class="col-lg-12">
                                    <div>
                                      <button @click="assignModal(driver)" type="button" class="btn btn-info btn-label waves-effect waves-light"><i class="ri-car-line label-icon fs-20 align-middle me-2"></i> {{ $t('assign') }}</button>
                                    </div>
                                  </div>
                                  <div class="col-lg-12">
                                    <ul class="d-flex list-inline mb-0 mt-3">
                                      <li class="list-inline-item avatar-xs">
                                        <a href="javascript:void(0);" class="avatar-title bg-success-subtle text-success fs-15 rounded" data-bs-toggle="tooltip" data-bs-placement="top" :title=" $t('today_rides_distance') ">
                                          <i class="ri-pin-distance-line"></i>
                                        </a>
                                        <h6 class="mt-2" style="width: max-content;">{{driver.total_kms ? driver.total_kms : '-'}} </h6>
                                      </li>
                                      <li class="list-inline-item avatar-xs ms-5">
                                        <a href="javascript:void(0);" class="avatar-title bg-danger-subtle text-danger fs-15 rounded" data-bs-toggle="tooltip" data-bs-placement="top" :title=" $t('today_rides_taken') ">
                                          <i class=" ri-police-car-fill"></i>
                                        </a>
                                        <h6 class="mt-2" style="width: max-content;">{{driver.total_rides_taken}}</h6>
                                      </li>
                                      <li class="list-inline-item avatar-xs ms-5">
                                        <a href="javascript:void(0);" class="avatar-title bg-warning-subtle text-warning fs-15 rounded" data-bs-toggle="tooltip" data-bs-placement="top" :title=" $t('today_active') ">
                                          <i class=" ri-map-pin-time-line"></i>
                                        </a>
                                        <h6 class="mt-2" style="width: max-content;">{{driver.total_active_hrs ? driver.total_active_hrs : '-'}}</h6>
                                      </li>
                                      <li class="list-inline-item avatar-xs ms-5">
                                        <a href="javascript:void(0);" class="avatar-title bg-info-subtle text-info fs-15 rounded" data-bs-toggle="tooltip" data-bs-placement="top" :title=" $t('current_location')"  @click="() => {selectedDriver =  null; selectedDriver = driverMarkers[driver.id]}" >
                                          <i class="bx bx-current-location"></i>
                                        </a>
                                      </li>
                                    </ul>
                                  </div>
                                </div>
                              </div>
                            </div>
                            <!--end card-->
                          </div>
                        </BCol>
                      </BCardBody>
                    </BCard>
                  </BRow>
                </div>
              </div>

              <!-- map  -->
              <div class="col-12 col-lg-6">
                <div class="mb-3 text-center m-auto">
                  <div id="map" style="height: 500px;">
                    <googleMap
                        :baseUrl="baseUrl"
                        :pick_location="{lat:result.pick_lat, lng:result.pick_lng}"
                        :nearbyDrivers="driverMarkers"
                        :driver="selectedDriver"
                        :map_key="map_key"
                        :libraries="['marker','geometry','geocoding']"
                    >
                    {{$t("map_loading")}}
                    </googleMap>
                  </div>
                </div>
              </div>  
            </div>

          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
    <div>

        <!-- modal -->
        <BModal v-model="modalShow" hide-footer :title="$t('driver_details')" class="v-modal-custom" size="sm">
          <BCard>
          <BCardBody>
          <BRow>
            <div class="col-lg-12">
              <div class="card-header">
                  <h6 class="card-title mb-0">{{ $t('confirm_assign_driver') }}</h6>
              </div>
              <div class="card-body p-4 text-center">
                  <div class="mx-auto avatar-md mb-3">
                      <img :src="modalDriver?.profile_picture" alt="2" class="avatar-md rounded-circle">
                  </div>
                  <h5 class="card-title mb-1"> {{ modalDriver?.name }}</h5>
                  <p class="text-muted mb-0">{{ app_for == 'demo' ? '***********' : modalDriver?.mobile }}</p>
              </div>
              <p class="text-muted mb-0">{{ $t('confirm_assign_driver_info') }}</p>
            </div>
          </BRow>
          </BCardBody>
          </BCard>
              <div class="modal-footer v-modal-footer">
                <BLink href="javascript:void(0);" class="btn btn-link link-warning fw-medium"
                    @click="modalShow = false">
                    <i class="ri-close-line me-1 align-middle"></i> {{$t('close')}}
                </BLink>
                <button type="button" class="btn btn-soft-info waves-effect waves-light" @click="assignDriver(modalDriver)">
                  <i class="ri-car-line me-1 align-middle"></i> {{$t('assign_driver')}}
                </button>
            </div>
        </BModal>
    <!-- modal end -->
    </div>
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
:is(.h1,
.h2,
.h3,
.h4,
.h5,
.h6,
h1,
h2,
h3,
h4,
h5,
h6) {
    font-family: "Zona Pro", sans-serif !important;
}
.assign-drivers {
  font-family: "Zona Pro", sans-serif;
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

:root {
--primary: #222222;
--primary-hover: {{ $side -> value }};
}
.select-form{
position: relative;
width: 100%;
margin-bottom: 18px;
}

.select-checkbox-group {
display: flex;
align-items: center;
position: relative;
}
.select-checkbox-btn {
margin-right: 15px;
margin-bottom: 15px;
}
.select-checkbox-btn-wrapper {
display: flex;
align-items: center;
justify-content: center;
width: fit-content;
position: relative;
}
.select-checkbox-btn-input {
clip: rect(0 0 0 0);
-webkit-clip-path: inset(100%);
clip-path: inset(100%);
height: 1px;
overflow: hidden;
position: absolute;
white-space: nowrap;
width: 1px;
}
.select-checkbox-btn-input:checked + .select-checkbox-btn-content {
border-color: var(--primary);
color: var(--primary);
}
.select-checkbox-btn-input:checked + .select-checkbox-btn-content:before {
transform: scale(1);
opacity: 1;
background-color: var(--primary);
border-color: var(--primary);
}
.select-checkbox-btn-input:checked
+ .select-checkbox-btn-content
.select-checkbox-btn-icon,
.select-checkbox-btn-input:checked
+ .select-checkbox-btn-content
.select-checkbox-btn-label {
color: var(--primary);
}
.select-checkbox-btn-input:focus + .select-checkbox-btn-content {
border-color: var(--primary);
}
.select-checkbox-btn-input:focus + .select-checkbox-btn-content:before {
transform: scale(1);
opacity: 1;
}

.select-checkbox-btn-content {
display: flex;
flex-direction: column;
align-items: center;
justify-content: center;
width: 140px;
min-height: 140px;
border-radius: 10px;
border: 0.1rem solid #dfe2e6;
background-color: #fff;
transition: border-color ease-in-out 0.15s, box-shadow ease-in-out 0.15s,
-webkit-box-shadow ease-in-out 0.15s;
cursor: pointer;
position: relative;
user-select: none;
appearance: none;
}
.select-checkbox-btn-content:before {
content: "";
position: absolute;
width: 22px;
height: 22px;
border: 0.1rem solid #bbc1e1;
background-color: #fff;
border-radius: 9999px;
top: 5px;
left: 5px;
opacity: 0;
transform: scale(0);
transition: 0.25s ease;
background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='192' height='192' fill='%23FFFFFF' viewBox='0 0 256 256'%3E%3Crect width='256' height='256' fill='none'%3E%3C/rect%3E%3Cpolyline points='216 72.005 104 184 48 128.005' fill='none' stroke='%23FFFFFF' stroke-linecap='round' stroke-linejoin='round' stroke-width='32'%3E%3C/polyline%3E%3C/svg%3E");
background-size: 12px;
background-repeat: no-repeat;
background-position: 50% 50%;
display: flex;
align-items: center;
justify-content: center;
}
.select-checkbox-btn-content:hover {
border-color: var(--primary);
}
.select-checkbox-btn-content:hover:before {
transform: scale(1);
opacity: 1;
}

.select-checkbox-btn-icon {
transition: 0.375s ease;
color: #3c3c3cc7;
}
.select-checkbox-btn-icon svg {
width: 50px;
height: 50px;
}

.select-checkbox-btn-label {
color: #3c3c3cc7;
transition: 0.375s ease;
text-align: center;
}
.marker {
transition: transform 0.5s ease-out;
}

.amount {
font-family: Arial, sans-serif;
font-size: 16px; 
white-space: nowrap;
letter-spacing: 0.1em;
}
</style>
