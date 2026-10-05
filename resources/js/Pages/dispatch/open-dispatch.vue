<script>
import { Link,Head,router, useForm } from '@inertiajs/vue3';
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
import Slider from "@vueform/slider";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "leaflet.heat";
import 'leaflet-routing-machine';
import polyline from '@mapbox/polyline';
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";

export default {
  data() {
    return {
      isPocSame: false, // checkbox state
      estimateFare: false,
      dateTimeConfig: {
        enableTime: true,
        dateFormat: "Y-m-d H:i:ss",
        minDate:this.getMinTime(),
      },
      dispatcher_enquiry: false,      
      promo_list: false,

    };
  },

  watch: {
    isPocSame(newVal) {
      if (newVal) {
        this.form.drop_poc_name = this.form.name;
        this.form.drop_poc_mobile = this.form.mobile;
      } else {
        this.form.drop_poc_name = '';
        this.form.drop_poc_mobile = '';
      }
    },
    // Watch for changes in personal info to update POC if the checkbox is checked
    'form.name'(newVal) {
      if (this.isPocSame) {
        this.form.drop_poc_name = newVal;
      }
    },
    'form.mobile'(newVal) {
      if (this.isPocSame) {
        this.form.drop_poc_mobile = newVal;
      }
    },
  },

  components: {
    Layout,
    PageHeader,
    Head,
    Link,
    Scrollbar,
    FormValidation,
    flatPickr,
    Multiselect,
  },

  computed: {
    ...mapGetters(['permissions']),
  },

  props: {
    countries: Array,
    default_flag: String,
    default_dial_code: String,
    default_lat:String,
    default_lng:String,
    successMessage: String,
    alertMessage: String,
    validate: Function,
    transport_type_for_ride: Array,
    ride_type_for_ride: Array,
    is_rental: Boolean,
    goodsTypes: Array,
    firebaseSettings: Object,
    map_key: String,
    transport_type_outstation: Array,
    transport_type_regular: Array,
    transport_type_rental: Array,
    settings: Object,
    schedule_a_ride: Number,
    is_pet_available: Boolean,
    is_luggage_available : Boolean,
    enable_ride_without_destination: Boolean,
    preference: Array,
    app_modules: Array,
    booking_details: Array,
    driver_id: String,
    type: Array,
    driver: Array,
  },

  computed: {
  transport_options() {
    let options = [];

    // Handling 'regular' ride type
    if (this.form.ride_type === 'regular') {
      options = this.transport_type_regular;
      this.form.transport_type = options[0];

    // Handling 'rental' ride type
    } else if (this.form.ride_type === 'rental') {
      // If transport_type_rental is empty, return an empty array to hide dropdown
      if (this.transport_type_rental.length === 0) {
        this.form.transport_type = ''; // Reset selected transport_type
        options = [];
      } else {
        options = this.transport_type_rental;
      }

    // Handling 'outstation' ride type
    } else if (this.form.ride_type === 'outstation') {
      // If transport_type_outstation is empty, return an empty array to hide dropdown
      if (this.transport_type_outstation.length === 0) {
        this.form.transport_type = ''; // Reset selected transport_type
        options = [];
      } else {
        options = this.transport_type_outstation;
      }
    }
    // Automatically select the only available option if there's just one
    if (options.length === 1) {
        this.form.transport_type = options[0]; // Automatically select the single option
      }

    return options;
  },

  dateTimeConfigReturnTime() {
      return {
        enableTime: true,
        dateFormat: "Y-m-d H:i:ss",
        minDate: this.getMinReturnTime(), // Call the method here (minDate and Time for the return time)
      }
    }
},
  setup(props) {
    const promo_list = ref(false);
    const { t } = useI18n();
    const selectedCountry = ref({
      dial_code: props.default_dial_code || '',
      flag: props.default_flag || ''
    });
    const isPocSame = ref(false);

    const routeDetails = ref({});
    let control = null;
    const currentLat = ref(parseFloat(props.default_lat));
    const currentLng = ref(parseFloat(props.default_lng));
    const pickupMarker = ref(null); // Define pickupMarker in setup
    const dropMarker = ref(null); // Define dropMarker in setup
    const stopMarkers = ref([]);
    const map = ref(null);
    const pickupSuggestions = ref([]); // Store pickup suggestions
    const stopSuggestions = ref([]); // Store stop suggestions
    const dropSuggestions = ref([]); // Store stop suggestions
    const driverMarkers = ref({});
    const preferences = ref(props.preference);
    const appModules = ref(props.app_modules);
    const pickup_marker = ref(null);
    const drop_marker = ref(null);
    const pickupSelected = ref(false);
    const dropSelected = ref(false);
    const stopSelected = ref([]);
    const stop_marker = ref([]);
    const enablePromoBtn = ref(false);
    const unit = ref('-');
     const estimated_fare = ref();
      const driverId = ref(props.driver_id);
      const driver = ref(props.driver);
  // Create and manage the
    // Watchers
    watch(isPocSame, (newVal) => {
      if (newVal) {
        form.drop_poc_name = form.name;
        form.drop_poc_mobile = form.mobile;
      } else {
        form.drop_poc_name = '';
        form.drop_poc_mobile = '';
      }
    });
    
    const getMinTime = () => {
      const now = new Date(); //get the today date
      const scheduleMinutes = props.schedule_a_ride ? parseInt(props.schedule_a_ride) : 0; //get the schedule_a_ride
      now.setMinutes(now.getMinutes() + scheduleMinutes);  //add the schedule_a_ride to the today date
      now.setSeconds(0);
      
      const formattedTime = now.getFullYear() + "-" + 
                        String(now.getMonth() + 1).padStart(2, "0") + "-" + 
                        String(now.getDate()).padStart(2, "0") + " " + 
                        String(now.getHours()).padStart(2, "0") + ":" + 
                        String(now.getMinutes()).padStart(2, "0") + ":" + 
                        "00"; // Always set seconds to 00


  console.log("Formatted Time:", formattedTime);
  return formattedTime;
    }

    // Methods
    const timer = () => {
      let timerInterval;
      Swal.fire({
        title: 'Booking alert!',
        html: 'Your Ride has been Booked <b></b> Successfully.',
        timer: 2000,
        timerProgressBar: true,
        didOpen: () => {
          Swal.showLoading();
          timerInterval = setInterval(() => {
            Swal.getContent().querySelector('b').textContent = Swal.getTimerLeft();
          }, 100);
        },
        didClose: () => {
          clearInterval(timerInterval);
        },
      }).then((result) => {
        if (result.dismiss === Swal.DismissReason.timer) {
          console.log('I was closed by the timer');
        }
      });
    };

    const enquiryModel = async () => {
                Swal.fire({
                    title: "Are you sure?",
                    text: `You want to Store as Enquiry`,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#34c38f",
                    cancelButtonColor: "#f46a6a",
                    confirmButtonText: "Yes, Store Enquiry!",
                    cancelButtonText: "Close",
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                           storeRequestEnquiry();
                        } catch (error) {
                            console.error(t('error_deleting_data'), error);
                            Swal.fire(t('error'), t('failed_to_cancel_the_data'), "error");
                        }
                    } 
                });
            };
    
            // store the request Enquiry
            const storeRequestEnquiry = async () => {
                const { pick_lat, pick_lng, drop_lat, drop_lng, distance, duration, } = etaParam.data();
    
                if (pick_lat) {
                    form.pick_lat = pick_lat;
                }
                if (pick_lng) {
                    form.pick_lng = pick_lng;
                }
                if (drop_lat) {
                    form.drop_lat = drop_lat;
                }
                if (drop_lng) {
                    form.drop_lng = drop_lng;
                }
    
                 try {
                    let response;
                    
    
                    let formData = new FormData();
    
                    formData.append('name', form.name);
                    formData.append('mobile', form.mobile);
                    formData.append('pick_lat', form.pick_lat);
                    formData.append('pick_lng', form.pick_lng);
                    formData.append('drop_lat', form.drop_lat);
                    formData.append('drop_lng', form.drop_lng);
                    formData.append('pick_address', form.pick_address);
                    formData.append('drop_address', form.drop_address);
                    formData.append('country',form.country);
                    console.log("data",form.data());
                    
                    response = await axios.post( `/dispatch-pro/request-enquiry`, formData);
                    form.reset();
                    etaParam.reset();
                    initializeMap();
                    rentalVehicleTypes.value = [];
                    vehicleTypes.value = [];
                    stopovers.length = 0;
                    form.service = '';
                    router.get('/dispatcher-pro/bookride');
                 } catch (error) {
                    if (error.response && error.response.status === 422) {
                        errors.value = error.response.data.errors;
                    } else {
                        console.error(t("error_creating_vehicle_price"), error);
                        alertMessage.value = t( "failed_to_make_booking_contact_admin" );
                    }
                }
            };

    const drawRoute = () => {
      if(form.is_rental && etaParam.pick_lat && etaParam.pick_lng) {
          if (control) {
              form.poly_line = '';
              map.value.removeControl(control); // Remove existing route control
          }
          if (stop_marker.value) {
            stop_marker.value = [];
          }
          if (drop_marker.value) {
            drop_marker.value.remove();
            drop_marker.value = null;
          }
          const position = { lat: parseFloat(etaParam.pick_lat), lng: parseFloat(etaParam.pick_lng  ) };
          createPickupMarker(position);
          const bounds = L.latLngBounds([pickup_marker.value.getLatLng()]);
          map.value.fitBounds(bounds, { padding: [50, 50] });
          return;
      }
      if (!pickup_marker.value || !drop_marker.value) return;
      if (!pickup_marker.value.getLatLng() || !drop_marker.value.getLatLng()) return;

      if (control) {
          form.poly_line = '';
          map.value.removeControl(control); // Remove existing route control
      }
      loadVehicleTypes();
      const allWayPoints = [
                pickup_marker.value.getLatLng(),
                ...stop_marker.value.map(stop => L.latLng(stop.latitude, stop.longitude)),
                drop_marker.value.getLatLng(),
            ];
        const bounds = L.latLngBounds(allWayPoints);
        map.value.fitBounds(bounds, { padding: [50, 50] });
        if (control !== null && map.value) {
            form.poly_line = '';
            map.value.removeControl(control);
        }
        if(errors.value.map) {
  delete errors.value.map;
}
 else {
  errors.value.map = t('service_not_available');
};

        control = L.Routing.control({
            waypoints: allWayPoints,
            routeWhileDragging: false,
            createMarker: function() { return null; },
            addWaypoints: false,
            lineOptions: {
              styles: [{ color: 'blue', weight: 4 }]
            },

        }).on('routesfound', function(e) {
            const route = e.routes[0];

            const coords = route.coordinates.map(c => [c.lat, c.lng]);

            form.poly_line = polyline.encode(coords);


            etaParam.distance = route.summary.totalDistance;
            etaParam.duration = Math.round((route.summary.totalTime) / 60);

          return verifyError(errors);

        }).addTo(map.value);
    };

    const createPickupMarker = (latlng = {lat : currentLat.value ,lng : currentLat.value}) => {
      if (pickup_marker.value) {
        pickup_marker.value.remove();
      }

      const pickupIcon = L.icon({
        iconUrl: '/image/map/pickup.png',
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
      });

      pickup_marker.value = L.marker([latlng.lat, latlng.lng], { icon: pickupIcon, draggable:false } ).addTo(map.value);

      pickup_marker.value.on('dragend', (event) => {
        const newLatLng = event.target.getLatLng();
        const { lat, lng } = newLatLng;
        console.log("New pick position:", lat, lng);
        console.log(reverseGeocode(lat, lng));
      });

    };

    const createDropMarker = (latlng = {lat : currentLat.value, lng : currentLat.value}) => {
      if (drop_marker.value) {
        drop_marker.value.remove();
      }

      const dropIcon = L.icon({
        iconUrl: '/image/map/drop.png',
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
      });
      drop_marker.value = L.marker(latlng, { icon:  dropIcon, draggable:false } ).addTo(map.value);

      drop_marker.value.on('dragend', (event) => {
        const newLatLng = event.target.getLatLng();
        const { lat, lng } = newLatLng;
        console.log("New Drop position:", lat, lng);
        console.log(reverseGeocode(lat, lng));
      });

    };

    const createStopMarker = (index,latlng = {lat : currentLat.value, lng : currentLat.value}) => {
      if (stop_marker.value[index]) {
        stop_marker.value[index].remove();
      }

      const dropIcon = L.icon({
        iconUrl: `/image/map/${index}.png`,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
      });
      stop_marker.value[index] = L.marker(latlng, { icon:  dropIcon, draggable:false } ).addTo(map.value);

      stop_marker.value[index].on('dragend', (event) => {
        const newLatLng = event.target.getLatLng();
        const { lat, lng } = newLatLng;
        console.log(`New stop ${index} position:`, lat, lng);
        console.log(reverseGeocode(lat, lng));
      });

    };
    const fetchPickupSuggestions = async () => {
      const query = form.pick_address;
      if (query.length > 2) {
        const response = await axios.get(
          `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`
        );
        pickupSuggestions.value = response.data.map((suggestion) => ({
          display_name: suggestion.display_name,
          lat: suggestion.lat,
          lon: suggestion.lon,
        }));
      } else {
        pickupSuggestions.value = [];
      }
    };

    const fetchDropSuggestions = async () => {
      const query = form.drop_address;
      if (query.length > 2) {
        const response = await axios.get(
          `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`
        );
        dropSuggestions.value = response.data.map((suggestion) => ({
          display_name: suggestion.display_name,
          lat: suggestion.lat,
          lon: suggestion.lon,
        }));
      } else {
        dropSuggestions.value = [];
      }
    };

    const stopovers = reactive([]);

    watch(
      () => stopovers,
      (newVal) => {
        if (newVal) {
          drawRoute();
        }
      }
    );
    const fetchStopSuggestions = async (index) => {
      const query = stopovers[index].address;
      if (query.length > 2) {
        const response = await axios.get(
          `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`
        );
        stopSuggestions.value[index] = response.data.map((suggestion) => ({
          display_name: suggestion.display_name,
          lat: suggestion.lat,
          lon: suggestion.lon,
        }));
      } else {
        stopSuggestions.value[index] = [];
      }
    };
    function reverseGeocode(lat, lon) {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1&accept-language=en`;
      let suggestion = {};
        fetch(url)
            .then(response => response.json())
            .then(data => {
              suggestion = data;
            })
            .catch(error => {
                console.error("Error:", error);
            });
      return suggestion;
    }

    const selectPickupSuggestion = (suggestion) => {
      form.pick_address = suggestion.display_name;
      pickupSelected.value = true
      const position = { lat: parseFloat(suggestion.lat), lng: parseFloat(suggestion.lon) };
      pickupSuggestions.value = [];

      etaParam.pick_lat = position.lat;
      etaParam.pick_lng = position.lng;
      if (pickup_marker.value) {
        pickup_marker.value.setLatLng(position);
        map.value.setView(position, 12);
      } else {
        map.value.setView(position, 12);
        createPickupMarker(position);
        fetchNearbyDrivers(position);
      }
      if (drop_marker.value) {
        drawRoute();
      }

      loadVehicleTypes();
    };

    const selectDropSuggestion = (suggestion) => {
      form.drop_address = suggestion.display_name;
        dropSelected.value = true
      const position = { lat: parseFloat(suggestion.lat), lng: parseFloat(suggestion.lon) };
      dropSuggestions.value = [];
      etaParam.drop_lat = position.lat;
      etaParam.drop_lng = position.lng;

      if (drop_marker.value) {
        drop_marker.value.setLatLng(position);
      } else {
        createDropMarker(position);
      }
      if (pickup_marker.value) {
        drawRoute();
      }
    };

    const selectStopSuggestion = (index, suggestion) => {
      stopovers[index].address = suggestion.display_name;
      stopovers[index].latitude = suggestion.lat;
      stopovers[index].longitude = suggestion.lon;
      const position = { lat: parseFloat(suggestion.lat), lng: parseFloat(suggestion.lon) };
      createStopMarker(index,position);
      stopMarkers.value[index].setLatLng(position);
      stopSelected.value[index] =true;
      stopSuggestions.value[index] = [];
      drawRoute();
    };

    const form = useForm({
      mobile: "",
      name: null,
      user_id: null,
      pick_address: "",
      drop_address: "",
      terminal: null,
      enterance: null,
      pick_lat: "",
      pick_lng: "",
      drop_lat: null,
      drop_lng: null,
      distance:0,
      stops:'[]',
      duration:0,
      vehicle_type: null,
      poly_line: '',
      payment_opt: 1,
      goods_type_id: null,
      transport_type: '',
      pickup_poc_name: null,
      drop_poc_name: null,
      goods_type_quantity: null,
      is_later: 0,
      assign_method: 0,
      is_rental: 0,
      rental_package_id: null,
      ride_type: 'normal',
      trip_start_time: null,
      country: props.default_dial_code,
      drop_poc_mobile: null,
      is_pet_available:0,
      is_luggage_available:0,
      preferences:[],
       driver_id: null,
      promo_code:null
    });

    const scrollContainer = ref(null);
    const enableBooking = ref(false);
    watch(
      () => form.name,
      (newVal) => {
        if (isPocSame.value) {
          form.drop_poc_name = newVal;
        }
      }
    );

    watch(
      () => form.mobile,
      (newVal) => {
        if (isPocSame.value) {
          form.drop_poc_mobile = newVal;
        }
      }
    );

    const nameChangeShow = debounce((name)=>{
        if(name && form.vehicle_type) {
          scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
          enableBooking.value = true;
        }else{
          enableBooking.value = false;
        }
    },300);
    watch(() => form.name,async ( name) => {
        nameChangeShow(name);
    });
    // For Airport
    const showAiportTerminal = ref(false);
    const showTrainStationEnterance = ref(false);

    const validationRules = {
      name: { required: true },
      pick_address: { required: true },
      mobile: { required: true },
      transport_type: { required: true },
      vehicle_type: { required: true }
    };
    const errors = ref({});
    const validationRef = ref(null);
    const vehicleTypes = ref([]);
    const selectedVehicleType = computed(() => {
      if (!form.vehicle_type) {
        return null;
      }
      return vehicleTypes.value.find((type) => type.zone_type_id === form.vehicle_type) || null;
    });
    const showGoodsType = computed(() => {
      return form.transport_type === "delivery" && !!selectedVehicleType.value?.enable_goods_type;
    });
    const packageTypes = ref([]);
    const rentalVehicleTypes = ref([]);

    const etaParam = useForm({
      pick_lat: "",
      pick_lng: "",
      drop_lat: "",
      drop_lng: "",
      distance: 0,
      distance_in_unit: 0,
      duration: 0
    });

    // Reactive ref to store formatted time
const formattedDuration = ref("0m");

// Watch for changes in etaParam.duration and update formattedDuration
watch(() => etaParam.duration, (newDuration) => {
  const hours = Math.floor(newDuration / 60);
  const minutes = newDuration % 60;
  formattedDuration.value = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
});

    const serviceVerify = async () => {
  const address = [];
  const pick_location = {
    latitude: etaParam.pick_lat,
    longitude: etaParam.pick_lng
  };
  address.push(pick_location);

  if (Array.isArray(stopovers)) {
    stopovers.forEach((stop) => {
      const stop_location = {
        latitude: stop.latitude,
        longitude: stop.longitude
      };
      address.push(stop_location);
    });
  }

  if (etaParam.drop_lat) {
    const drop_location = {
      latitude: etaParam.drop_lat,
      longitude: etaParam.drop_lng
    };
    address.push(drop_location);
  }

  const payload = {
    address : address,
    ride_type : form.ride_type
  }
  
  try {
      const response = await axios.post("/dispatch-pro/serviceVerify", payload );

  } catch (error) {
    if (error.response && error.response.status === 422) {
      errors.value = error.response.data.errors;
    } else if (error.response && error.response.status === 500) {
      if(error.response.data.message == "service not available with this location"){
        errors.value.pick_address = [t('service_unavailable')];
      }else if (error.response.data.message == "Outstation service not available within this location"){
        delete errors.value.pick_address;
        errors.value.drop_address = [t('outstation_service_unavailable')];
      } else if ( error.response.data.message == "Pick up and Drop should not be in the same zone") {
          delete errors.value.pick_address;
          errors.value.drop_address = [t("same_zone_outstation_unavailable")];
      }else {
        const data = error.response.data;
        delete errors.value.pick_address;
        delete errors.value.drop_address;
        address.forEach((location,key) => {
          if (!errors.value.stop) {
            errors.value.stop = {};
          }
          if(key!==0){
            if(key !== address.length) {
              if(data[key]['available']) {
                errors.value.stop[key] = [t('ride_service_unavailable')];
              }else{
                delete errors.value.stop[key];
              }
            }else{
              if(data[key]['available']) {
                errors.value.drop_address = [t('ride_service_unavailable')];
              }else{
                delete errors.value.drop_address;
              }
            }
          }
        })
      }
      return verifyError(errors);

    } else {
      console.error(t('error_creating_vehicle_price'), error);
      alertMessage.value = t('failed_to_make_booking_contact_admin');
    }
  }
}

        const verifyError = (errors) =>{

            if (Object.keys(errors.value).length > 0) {
                enableBooking.value = false;
                if (scrollContainer.value) {
                    scrollContainer.value.scrollTop = 0;
                }
                return false;
            }

            delete errors.value.stop;
            enableBooking.value = true;
            scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
            return true;
        }
    const bookValidate = async() => {
  errors.value = validationRef.value.validate();


  if (etaParam.pick_lat) {
    serviceVerify();
  }
  if(form.is_later) {
  if(!form.trip_start_time){
    errors.value.trip_start_time = [t('required')];
  }else{
    delete errors.value.trip_start_time;
  }
}
if(!form.is_rental) {
  if ((!props.enable_ride_without_destination || form.is_out_station) && !form.drop_address && form.drop_address.length == 0) {
    errors.value.drop_address = [t('required')];
  }else{
    delete errors.value.drop_address;
  }
}else{
  form.drop_lat = null;
  form.drop_lng = null;
  etaParam.drop_lat = '';
  etaParam.drop_lng = '';
  form.drop_address = '';
  if(!form.rental_package_id){
    errors.value.rental_package_id = [t('required')];
  }else{
    delete errors.value.rental_package_id;
  }
}
if(form.is_out_station) {
  if(!form.trip_start_time){
    errors.value.trip_start_time = [t('required')];
  }else{
    delete errors.value.trip_start_time;
  }
}
if(form.is_out_station && form.is_round_trip) {
  if(!form.return_time){
    errors.value.return_time = [t('required')];
  }else{
    delete errors.value.return_time;
  }
}
if(form.transport_type == "delivery" && form.ride_type != 'rental') {
  if(showGoodsType.value){
    if(!form.goods_type_id){
      errors.value.goods_type_id = [t('required')];
    }else{
      delete errors.value.goods_type_id;
    }
  }else if(errors.value.goods_type_id){
    delete errors.value.goods_type_id;
  }
  if(!form.drop_poc_name){
    errors.value.drop_poc_name = [t('required')];
  }else{
    delete errors.value.drop_poc_name;
  }
  if(!form.drop_poc_mobile){
    errors.value.drop_poc_mobile = [t('required')];
  }else{
    delete errors.value.drop_poc_mobile;
  }
}

if(stopovers.length > 0) {
  stopovers.forEach((item,index) => {
      if (!errors.value.stop) {
        errors.value.stop = {};
      }
      if(!item.latitude) {
        errors.value.stop[index] = [t('required')];
      }else{
        if(errors.value.stop[index]) {
          delete errors.value.stop[index];
        }
      }
  });
}
  return verifyError(errors);

}
    const loadVehicleTypes = async () => {
  if(bookValidate()){
    if(form.is_rental){
      enableBooking.value = false;
      loadRentalPack();
      return false;
    }else{
      enableBooking.value = true;
    }
  }else{
    enableBooking.value = false;
    return false;
  }
  rentalVehicleTypes.value = [];

      const { pick_lat, pick_lng, distance, duration, drop_lat, drop_lng } = etaParam.data();

      const payload = {
        pick_lat,
        pick_lng,
        distance,
        duration,
        if_dispatch: 1,
        transport_type: form.transport_type,
      };

      if (drop_lat) {
        payload.drop_lat = drop_lat;
      }

      if (drop_lng) {
        payload.drop_lng = drop_lng;
      }
      if (form.is_out_station) {

      payload.is_out_station = 1;

        if (form.trip_start_time) {
          payload.trip_start_time = form.trip_start_time;
        }
        if (form.return_time) {
          payload.return_time = form.return_time;
        }
      }
       console.log("form.is_later",form.is_later);
        if (form.is_later == 1) {
            payload.is_later = 1;

            if (form.trip_start_time) {
                payload.trip_start_time = form.trip_start_time;
            }
        }
      payload.is_dispatch = 1;

      if (stopovers.length > 0) {
        payload.stops = JSON.stringify(stopovers);
      }
      if(props.type?.length){
          console.log("props.type",props.type);
          payload.driver_vehicle_type = props.type;
      }
      if(form.promo_code){
          payload.promo_code = form.promo_code;
      }
            try{
                const response = await axios.post(`/dispatch-pro/request/eta`, payload);
                vehicleTypes.value = response.data.data;
                if(vehicleTypes.value.length>0){
                    if(vehicleTypes.value[0].unit_in_words == 'MILES'){
                        etaParam.distance_in_unit = (etaParam.distance * 0.621371/1000).toFixed(2);
                        unit.value = t('miles');
                    }else{
                        etaParam.distance_in_unit = (etaParam.distance/1000).toFixed(2);
                        unit.value = t('km');
                    }
                }
                fetchNearbyDrivers(pickup_marker.value);
            }catch(error){
                console.error(errors);
            }
    };

    const applyPromo = async () => {
      try {
          const response = await axios.post(
              `/dispatch-pro/promocode-redeem`,
              {
                  promo_code: form.promo_code
              }
          );
          
          console.log("response", response);
          
          // ADD THIS: If promo apply is successful, fetch the ETA again!
          if (response.data && response.data.success) {
              await loadVehicleTypes();
              
              // Optional: Show a sweetalert success message here
          } else {
              // Optional: Show an error that the promo is invalid
          }
          
      } catch(error) {
          console.error(errors);
      }
  };

    const loadRentalPack = async() => {
      vehicleTypes.value = [];

      const { pick_lat, pick_lng } = etaParam.data();


      const payload = {
        pick_lat,
        pick_lng,
        transport_type: form.transport_type,
      };
      if(props.type?.length){
        console.log("props.type",props.type);
        payload.driver_vehicle_type = props.type;
      }

      // 1) Send the promo code so the backend applies it to the packages
      if (form.promo_code) {
          payload.promo_code = form.promo_code;
      }
      
      const response = await axios.post(`/dispatch-pro/request/list_packages`, payload);

      packageTypes.value = response.data.data;

      // 2) Update the currently selected rental vehicle list with the new discounted prices
      if (form.rental_package_id) {
          const rentalpack = packageTypes.value.find((pack) => pack.id === form.rental_package_id);
          if (rentalpack) {
              rentalVehicleTypes.value = rentalpack.typesWithPrice.data;
          }
      }
    };
    const modalShow = ref(false);
    const successMessage = ref(props.successMessage || '');
    const alertMessage = ref(props.alertMessage || '');

    const dismissMessage = () => {
      successMessage.value = "";
      alertMessage.value = "";
    };

    const maxStopovers = 2;

    const addStopover = () => {

      if (stopovers.length < maxStopovers) {
        stopovers.push({ address: "", latitude: null, longitude: null });
      } else {
        Swal.fire(t('maximum_stopovers_reached'), t('you_can_add_up_to_5_stopovers_only'), 'warning');
      }
    };

    const removeStopover = (index) => {
      // Remove marker from map
      if (stop_marker[index]) {
        console.log("removing", stop_marker[index]);
        stop_marker[index].setMap(null);
      }

      // Remove stopover from array
      stopovers.splice(index, 1);

      // Reinitialize autocomplete for remaining stops
      stopovers.forEach((_, i) => {
        addStopMarker(i);
      });

      // Redraw the route
      if (index > 0) {
        drawRoute();
      }
};


const animateDriverMovement = (marker, prevLatLng, currentLatLng, currentBearing,vehicleTypeIconUrl) => {
  const numSteps = 60;
  const timeInterval = 2000;
  const delay = timeInterval / numSteps;

  let startLat = prevLatLng.lat;
  let startLng = prevLatLng.lng;

  let endLat = currentLatLng.lat;
  let endLng = currentLatLng.lng;

  const easeInOutQuad = (t) => t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
};

watch(
  () => form.promo_code,
  async (promo) => {
      if (promo) {
          enablePromoBtn.value = true;
      }
      else{
          enablePromoBtn.value = false;
      }
  }
);


const calculateTime = (distance) => {
  if(distance<2){
    return '3 Mins';
  }else if(distance > 2 && distance <5){
    return '5 Mins';
  }
  else if(distance > 5 && distance <7){
    return '10 Mins';
  }else{
    return '15 mins';
  }
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

const updateDriverMarker = (driver) => {
  const driverLatLng = L.latLng(driver.latitude, driver.longitude);
  const vehicleTypeIconUrl = `/image/map/${driver.vehicle_type_icon}.png`;
  if (driverMarkers[driver.id]) {
    animateDriverMovement(driverMarkers[driver.id], driverMarkers[driver.id].getLatLng(), driverLatLng, driver.bearing ,vehicleTypeIconUrl);
  } else {
    driverMarkers[driver.id] = L.marker(driverLatLng, {
      icon: L.icon({
        iconUrl: vehicleTypeIconUrl,
        iconSize: [30, 30]
      }),
      draggable: false // Not needed for drivers
    }).addTo(map.value);
    animateDriverMovement(driverMarkers[driver.id], driverMarkers[driver.id].getLatLng(), driverLatLng, driver.bearing,vehicleTypeIconUrl);
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
  // Fetch nearby drivers
  const fetchNearbyDrivers = async (pickupLocation) => {
    const driversRef = firebase.database().ref('drivers');
    vehicleTypes.value.forEach((vehicleType) => {
      vehicleType.driver_time = null;
      vehicleType.driver_distance = null;
    });
    driversRef.on('value', (snapshot) => {
        snapshot.forEach((childSnapshot) => {
        const driver = childSnapshot.val();
        const driverGeoHash = driver.g;
        const driverLocation = decodeGeohash(driverGeoHash);

        if (driverLocation) {
          const distance = calculateDistance(pickupLocation.lat, pickupLocation.lng, driverLocation.lat, driverLocation.lon);
          var flag = true;
          if(form.vehicle_type){
            flag = false;
            var type_id = vehicleTypes.value?.find((type)=> {
              return (type.zone_type_id === form.vehicle_type);
            })?.type_id;
            if(type_id && driver.vehicle_types && driver.vehicle_types.includes(type_id)){
              flag = true;
            }
          }
          if(distance <= 10 && driver.is_available && driver.is_active){
            setEtaTime(driver,distance);
          }
          if (distance <= 10 && driver.is_available && driver.is_active && flag) {
            const driverLatLng = L.latLng(driverLocation.lat, driverLocation.lon);
            const vehicleTypeIconUrl = `/image/map/${driver.vehicle_type_icon}.png`;

            const icon = L.icon({
              iconUrl: vehicleTypeIconUrl,
              iconSize: [30, 30],
            });
            if (driverMarkers[driver.id]) {
              animateDriverMovement(driverMarkers[driver.id], driverMarkers[driver.id].getLatLng(), driverLatLng, driver.bearing,vehicleTypeIconUrl);
              driverMarkers[driver.id].setIcon(icon);
            } else {
              const driverMarker = L.marker(driverLatLng, {
                icon: icon
              }).addTo(map.value);
              driverMarkers[driver.id] = driverMarker;
              animateDriverMovement(driverMarker, driverMarker.getLatLng(), driverLatLng, driver.bearing,vehicleTypeIconUrl);
            }

          } else if (driverMarkers[driver.id]) {
            driverMarkers[driver.id].remove();
            delete driverMarkers[driver.id];
          }
        }
      });
    });
    driversRef.on('child_changed', (childSnapshot) => {
      const driver = childSnapshot.val();
        const driverGeoHash = driver.g;
        const driverLocation = decodeGeohash(driverGeoHash);
        if (!driver.is_active && driverLocation) {
        vehicleTypes.value.forEach((vehicleType) => {
          if (driver.vehicle_types.some(type => type === vehicleType.id)) {
            fetchNearbyDrivers({lat :etaParam.pick_lat ,lng : etaParam.pick_lng});
          }
        });
      }

      if(driver.lat && driver.lng)
      updateDriverMarker(driver);
    });
  };
  const setEtaTime = (driver,distance) => {

    const time = calculateTime(distance);
    vehicleTypes.value.forEach((vehicleType) => {
    if (driver.vehicle_types && driver.vehicle_types.includes(vehicleType.type_id) && (!vehicleType.driver_distance || vehicleType.driver_distance > distance)) {
        vehicleType.driver_time = time;
      }
    });
  }
// Initialize Map
const initializeMap = async () => {
  if (control !== null && map.value) {
      form.poly_line = '';
      map.value.removeControl(control);
  }
  if (pickupMarker.value) {
    pickupMarker.value.remove();
  }
  if (dropMarker.value) {
    dropMarker.value.remove();
    dropMarker.value = null;
  }
  if (stopMarkers.value) {
    stopMarkers.value = [];
  }
  
  if (Object.keys(driverMarkers.value).length) {
    driverMarkers.value = {}; // Reset the object
  }
  if(map.value) {
    map.value = null;
  }
  map.value = L.map('map',{
      zoomControl: false,
      dragging: false,
      scrollWheelZoom: false,
      doubleClickZoom: false,
      touchZoom: false
  }).setView([currentLat.value, currentLng.value], 12);
  
  map.value.dragging.disable();
  map.value.scrollWheelZoom.disable();
  map.value.doubleClickZoom.disable();
  map.value.touchZoom.disable();

  L.tileLayer(window.osmTileUrlTemplate, {
      maxZoom: 19,
  }).addTo(map.value);


    watch(() => form.pick_address, (newVal) => {
      if (!newVal) {
        pickupSuggestions.value = [];
      }
    });
    watch(() => stopovers, (newVal) => {
      newVal.forEach((_, index) => {
        watch(() => stopovers[index].address, (newVal) => {
          if (!newVal) {
            stopSuggestions[index] = [];
          }
        });
      });
   });
   watch(() => form.vehicle_type,async ( value) => {
  if(value){
      const selectedVehicle = vehicleTypes.value.find(v => v.zone_type_id === value);
        const selectedRental = rentalVehicleTypes.value.find(v => v.zone_type_id === value);

        if (selectedVehicle) {
            estimated_fare.value = selectedVehicle;        // take total
        } 
      fetchNearbyDrivers({lat :etaParam.pick_lat ,lng : etaParam.pick_lng});
      if(bookValidate()){
        enableBooking.value = true;
      }else{
        enableBooking.value = false;
      }
  }
});
watch(() => form.transport_type,async ( value) => {
  if(value){
    if(value == 'taxi'){
      form.goods_type_id = null;
      form.goods_type_quantity = null;
    }else{
      if(!showGoodsType.value){
        form.goods_type_id = null;
        form.goods_type_quantity = null;
      }else{
        form.goods_type_quantity = form.goods_type_quantity ?? 0;
      }
    }
    if(form.pick_address && bookValidate()) {
      loadVehicleTypes();
    }
  }
});

watch(() => showGoodsType.value,(value) => {
  if(!value){
    form.goods_type_id = null;
    form.goods_type_quantity = null;
    if(errors.value.goods_type_id){
      delete errors.value.goods_type_id;
    }
    if(errors.value.goods_type_quantity){
      delete errors.value.goods_type_quantity;
    }
  }
});

watch(() => form.rental_package_id,async ( value) => {
  if(value){
    const rentalpack = packageTypes.value.find((pack)=> {
      return (pack.id === value);
    });
    const types = rentalpack.typesWithPrice.data;

    rentalVehicleTypes.value = types;
  }
});
watch(() => form.is_later,
async (value) => {
    if(value == 0 && form.ride_type == 'outstation'){                    
        changelater();
        const serviceItem = appModules.value.find(
            (item) => item.service_type === 'normal' && item.transport_type === 'taxi'
        );
        form.service = serviceItem ? serviceItem.id : null;
        form.is_out_station = 0;
        form.transport_type = serviceItem.transport_type;
         form.is_later = '0'; 
    }
    if (form.pick_address) {
        bookValidate();
        loadVehicleTypes();
    }
}
);
watch(
() => form.ride_type,
async (value) => {
    if (value == 'outstation' && form.is_later == 0) {
        form.is_later = '1'; 
    }
}
);
watch(() => form.ride_type,async (value) => {
  if(form.pick_address && bookValidate()){
      loadVehicleTypes();
      drawRoute();
  }
});
 watch(
  () => form.trip_start_time,
  async (value) => {
      if (value) {
          if(form.is_later == 1){                        
                await loadVehicleTypes();
          }
      }
  }
);
const nameChangeShow = debounce((name)=>{
    if(name && form.vehicle_type) {
      scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
      enableBooking.value = true;
    }else{
      enableBooking.value = false;
    }
},300);
watch(() => form.name,async ( name) => {
    nameChangeShow(name);
    if(form.pick_address) {
      bookValidate();
    }
});
watch(() => form.return_time,async ( value) => {
  if(value){
    loadVehicleTypes();
  }
});
watch(() => etaParam.pick_lat,async ( pickLat) => {
if (pickLat) {
  loadVehicleTypes();
  bookValidate();
}
});
watch(() => etaParam.distance,async ( value) => {
if (value) {
  await loadVehicleTypes();
  await bookValidate();
}
});


  const removeDriverMarker = (driverId) => {
    if (driverMarkers[driverId]) {
      driverMarkers[driverId].remove();
      delete driverMarkers[driverId];
    }
  };


  return map;
};

// Submit a Booking

const makeBooking = async ()=>{
const bookvalidate = await bookValidate()
  if (!bookvalidate) {
      return false;
  }

enableBooking.value = false;

const { pick_lat, pick_lng, drop_lat,distance,duration, drop_lng } = etaParam.data();

if (pick_lat) {
form.pick_lat = pick_lat;
}
if (pick_lng) {
form.pick_lng = pick_lng;
}
if (drop_lat) {
form.drop_lat = drop_lat;
}
if (drop_lng) {
form.drop_lng = drop_lng;
}

if (distance) {
form.distance = distance;
}
if (duration) {
form.duration = duration;
}
if(stopovers.length>0){
form.stops = JSON.stringify(stopovers);
}
form.preferences = JSON.stringify(form.preferences)

 if(props.driver_id){
    form.driver_id = props.driver_id;
}



try {
        let response;
        response = await axios.post(`/dispatch-pro/create-request`, form.data());
        if (response.data.success === true) {


      let timerInterval;
      Swal.fire({
        title: "Booking Successfull",
        html: "Your Ride has been Booked Successfully.",
        timer: 2000,
        timerProgressBar: true,
        didOpen: () => {
          Swal.showLoading();
          timerInterval = setInterval(() => {
            Swal.getContent().querySelector("b").textContent =
              Swal.getTimerLeft();
          }, 100);
        },
        didClose: () => {
          clearInterval(timerInterval);
        },
      }).then((result) => {
        if (
          /* Read more about handling dismissals below */
          result.dismiss === Swal.DismissReason.timer
        ) {
          console.log("I was closed by the timer"); // eslint-disable-line
        }
      enableBooking.value = true;
      });
          
      if(form.assign_method == 1){
        router.get('ongoing_request/assign/'+response.data.data.id);
      }
      if(form.driver_id || props.booking_details != ""){                        
          router.get('/dispatcher-pro/bookride');
        }
          form.reset();
          etaParam.reset();
          initializeMap();
          rentalVehicleTypes.value = [];
          vehicleTypes.value = [];
          stopovers.length = 0;
          form.service = '';
          router.get("/dispatcher-pro/bookride");

        } else {
          alertMessage.value = t('failed_to_make_booking_contact_admin');
        }
      } catch (error) {
        if (error.response && error.response.status === 422) {
          errors.value = error.response.data.errors;
        } else {
          console.error(t('error_creating_vehicle_price'), error);
          alertMessage.value = t('failed_to_make_booking_contact_admin');
        }
      }

}
const showHidebtn =  ref(true);
    onMounted(async () => {
      
      fetchPromoList();

      // Set up Firebase
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

      await initializeMap();

      if (props.transport_type_regular.length > 0) {
          form.transport_type = props.transport_type_regular[0];
      }

      if(props.booking_details != ""){
                console.log("props.booking_details",props.booking_details);
                etaParam.pick_lat = props.booking_details?.pick_lat;
                etaParam.pick_lng = props.booking_details?.pick_lng;
                etaParam.drop_lat = props.booking_details?.drop_lat;
                etaParam.drop_lng = props.booking_details?.drop_lng;
                form.pick_address = props.booking_details?.pick_address;
                form.drop_address = props.booking_details?.drop_address;
                form.mobile = props.booking_details?.mobile;
                form.name = props.booking_details?.name;

                if(etaParam.pick_lat){
                    pickup_marker.value = 
                    { 
                        lat: parseFloat(etaParam.pick_lat), 
                        lng: parseFloat(etaParam.pick_lng)
                    };
                    pickupSelected.value = true
                }    
                if(etaParam.drop_lat){
                    drop_marker.value = 
                    { 
                        lat: parseFloat(etaParam.drop_lat), 
                        lng: parseFloat(etaParam.drop_lng)
                    };
                    dropSelected.value = true
                }  
                 showHidebtn.value = false;   
                drawRoute();         
            }

    });


const mobile = ref('');

const searchQuery = ref('');

const filteredCountries = computed(() => {
return props.countries.filter(country =>
country.name.toLowerCase().includes(searchQuery.value.toLowerCase())
);
});

const changelater = () => {
  const selected = appModules.value.find(
      (item) => item.id === form.service
  );
  const type = selected.service_type;
  if (type == "outstation") {
      form.is_out_station = 1;
      form.is_rental = 0; 
      form.ride_type = type;          
      form.transport_type = selected.transport_type;
      form.rental_package_id = ""
      form.is_later = '1'; 
                estimated_fare.value ='';
                form.vehicle_type='';
  } else if (type == "rental") {
      form.is_out_station = 0;
      form.is_rental = 1;   
      form.ride_type = type; 
      form.transport_type = selected.transport_type;  
      form.rental_package_id = ""  ;
                estimated_fare.value ='';
                form.vehicle_type='';
      console.log("form.ride_type",form.ride_type); 
  } else {
      form.is_out_station = 0;
      form.is_rental = 0;           
      form.transport_type = selected.transport_type;
      form.ride_type = type;   
      form.rental_package_id = '' ;
                estimated_fare.value ='';
                form.vehicle_type='';
  }
};
const selectCountry = (country) => {
selectedCountry.value = country;
form.country=country.dial_code;
};



const validateNumber = debounce(async (event) => {
event.target.value = event.target.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');
const response = await axios.get(`/dispatch-pro/fetch-user-detail`, { params:{mobile:form.mobile} });

if(form.pick_address){
    bookValidate();
  }
if(response.data.data){
form.name = response.data.data.name;
form.user_id = response.data.data.id;

}else{
form.user_id=null;
form.name=null;
}

}, 300); // Adjust the debounce delay as needed

const  getMinReturnTime = () =>{
  const durationInMinutes = etaParam.duration; // eta duration in minutes
  const trip_start_time = form.trip_start_time; // getting trip_start_time

  // Convert trip_start_time to a Date object
  const startTime = new Date(trip_start_time);

  // Calculate return time by adding trip_start_time + durationInMinutes
  startTime.setMinutes(startTime.getMinutes() + durationInMinutes); // Add durationInMinutes to the start time

  return startTime;
}

const showMap = ref(false);
        
  const showPickDropMarker = async() =>{
      if(pickup_marker.value){
          pickupMarker.value = pickup_marker.value;  
          showMap.value = true;            
      } 
      if(drop_marker.value){
          dropMarker.value = drop_marker.value;
          showMap.value = true;
      }
      if (stop_marker.value.length > 0) {
          // Assign all stop markers that are defined
          stopMarkers.value = stop_marker.value.filter(marker => marker);
          showMap.value = true;
      }
      showMap.value = true; 
  }
  const PickDropMarker = async(input, index = null) =>{
    if(input === 'pickup'){
        pickupMarker.value = pickup_marker.value;
        showMap.value = true;  
        stopMarkers.value = null; 
        dropMarker.value = null;
    }
    if(input === 'drop'){     
        dropMarker.value = drop_marker.value;
        stopMarkers.value = null; 
        pickupMarker.value = null;
        showMap.value = true;
    }
    if(input === 'stop' && index != null){     
        stopMarkers.value = [stop_marker.value[index]]; 
        dropMarker.value = null; 
        pickupMarker.value = null;
        showMap.value = true;
    }
}

    const results = ref([]); // Spread the results to make them reactive

    const fetchPromoList = async (page = 1) => {
        try {
            const response = await axios.get(`/dispatch-pro/promocode-list`);
            results.value = response.data.data;
            console.log("results.value",results.value);
            modalFilter.value = false;
        } catch (error) {
            console.error(t('error_fetching_admin'), error);
        }
    };

    const copyPromo = (code) => {
        form.promo_code = code;
        enablePromoBtn.value = true;
    };


return {
form,
modalShow,
successMessage,
alertMessage,
dismissMessage,
validationRef,
validationRules,
isPocSame,
pickupMarker,
dropMarker,
stopMarkers,
pickupSuggestions,
stopSuggestions,
dropSuggestions,
timer,
changelater,
getMinTime,
packageTypes,
getMinReturnTime,
rentalVehicleTypes,
scrollContainer,
enableBooking,
fetchPickupSuggestions,
fetchDropSuggestions,
fetchStopSuggestions,
selectPickupSuggestion,
selectDropSuggestion,
selectStopSuggestion,
errors,
mobile,
showGoodsType,
selectedCountry,
searchQuery,
filteredCountries,
selectCountry,
validateNumber,
showAiportTerminal,
showTrainStationEnterance,
loadVehicleTypes,
etaParam,
vehicleTypes,
makeBooking,
stopovers,
removeStopover,
addStopover,
maxStopovers,
formattedDuration,
unit,
preferences,
appModules,
showMap,
showPickDropMarker,
PickDropMarker,
pickupSelected,
dropSelected,
stopSelected,
estimated_fare,
storeRequestEnquiry,
enquiryModel,
driverId,
driver,
showHidebtn,
enablePromoBtn,
applyPromo,
fetchPromoList,
results,
copyPromo,
promo_list,
};
},

};
</script>

<template>
  <Layout>
    <Head title="Taxi Ride" />
    <div class="font">
          <PageHeader :title="$t('bookings')" :pageTitle="$t('dispatch')" pageLink="/dispatch"/>
    <BRow>
      <BCol lg="12">
        <BCard no-body id="tasksList">
          <BCardHeader class="border-0">
            <BLink @click="showPickDropMarker()">
              <h6 class="text-success text-decoration-underline text-decoration-underline-success heart text-end" >{{$t('view_map')}}</h6>
            </BLink>
          </BCardHeader>
            <BCardBody class="border border-dashed border-end-0 border-start-0">
              <form @submit.prevent="handleSubmit">
                <FormValidation :form="form" :rules="validationRules" ref="validationRef">
                  <div class="row">
                    <div class="col-12 col-lg-6 booking" ref="scrollContainer">
                      <div class="row">
                        <div class="col-12">
                          <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                              <label for="pickup" class="form-label">{{ $t("pickup_location") }}
                                <span class="text-danger">*</span>
                              </label>
                              <a v-if="form.ride_type=='normal' && (stopovers.length < maxStopovers)" class="btn btn-primary mb-3" @click="addStopover">{{$t("add_stops")}}</a>
                            </div>
                            <div class="input-group">
                              <input type="text" class="form-control" v-model="form.pick_address" :placeholder="$t('enter_pickup')" id="pickup" @input="fetchPickupSuggestions" autocomplete="off">
                              <span class="input-group-text" style="background-color: none !important;" @click="PickDropMarker('pickup')" v-if="pickupSelected">
                                <i class="ri-map-pin-line text-success"></i>
                              </span>
                            </div>
                            <ul v-if="pickupSuggestions.length">
                              <li v-for="(suggestion, index) in pickupSuggestions" :key="index" @click="selectPickupSuggestion(suggestion)">
                                {{ suggestion.display_name }}
                              </li>
                            </ul>
                            <div class="col-12" v-if="errors.map">
                              <div class="mb-3">
                                <span v-for="(error, index) in errors.map" :key="index" class="text-danger">{{ error }}</span>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="col-12" v-if="form.ride_type=='normal'" v-for="(stop, index) in stopovers" :key="index">
                          <div class="mb-3">
                            <label :for="`stop-${index}`" class="form-label">{{$t("stop_location")}}</label>
                            <div class="d-flex align-items-center">
                              <div class="input-group">
                                <input type="text" class="form-control" v-model="stop.address" :placeholder="'Enter stop ' + (index + 1)" :id="`stop-${index}`" @input="fetchStopSuggestions(index)" autocomplete="off">
                                <span class="input-group-text" style="background-color: none !important;" @click="PickDropMarker('stop',index)" v-if="stopSelected[index]">
                                  <i class="ri-map-pin-line text-danger"></i>
                                </span>
                              </div>
                              <i class="bx bx-trash text-danger fs-22 btn" @click="removeStopover(index)"></i>
                            </div>
                            <ul v-if="stopSuggestions[index]?.length">
                              <li v-for="(suggestion, key) in stopSuggestions[index]" :key="key" @click="selectStopSuggestion(index,suggestion)">
                                {{ suggestion.display_name }}
                              </li>
                            </ul>
                            <span v-for="(error, index) in errors.stop?.[index]" :key="index" class="text-danger">{{ error }}</span>
                          </div>
                        </div>
                        <div class="col-12" v-if="showAiportTerminal">
                          <div class="mb-3">
                            <label for="terminal" class="form-label">{{$t("aiport_terminal_info")}}</label>
                            <input type="text" class="form-control" v-model="form.terminal" placeholder="$t('enter_terminal_info')" id="terminal" >
                          </div>
                        </div>
                        <div class="col-12" v-if="showTrainStationEnterance">
                          <div class="mb-3">
                            <label for="enterance" class="form-label">{{$t("station_enterance_info")}}</label>
                            <input type="text" class="form-control" v-model="form.enterance" placeholder="$t('enter_enterance_info')" id="enterance" >
                          </div>
                        </div>

                        <div class="col-12" v-if="form.ride_type!=='rental'">
                          <div class="mb-3">
                            <label for="drop" class="form-label">{{$t("drop_location")}}
                              <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                              <input type="text" class="form-control" v-model="form.drop_address" :placeholder="$t('enter_drop')" @input="fetchDropSuggestions" id="drop" >
                              <span class="input-group-text" style="background-color: none !important;" @click="PickDropMarker('drop')" v-if="dropSelected">
                                <i class="ri-map-pin-line text-danger"></i>
                              </span>
                            </div>
                            <ul v-if="dropSuggestions.length">
                              <li v-for="(suggestion, index) in dropSuggestions" :key="index" @click="selectDropSuggestion(suggestion)">
                                {{ suggestion.display_name }}
                              </li>
                            </ul>
                            <div class="col-12" v-if="errors.map">
                              <div class="mb-3">
                                <span v-for="(error, index) in errors.map" :key="index" class="text-danger">{{ error }}</span>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="d-flex align-items-center mt-3" v-if="form.ride_type!=='rental'">
                                            <div class="mb-4">
                                                <div class="d-flex align-items-center">            
                                                    <div class="flex-shrink-0">
                                                        <h5 class="mb-0">{{$t("distance")}}:</h5>
                                                    </div>
                                                    <div class="ms-2">
                                                        <h6 class="mb-0">{{(etaParam.distance_in_unit/1000).toFixed(2)}} {{unit}}</h6>
                                                    </div>
                                                </div>
                                            </div> 
                                             <div class="mb-4 ms-5">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <h5 class="mb-0">{{$t("duration")}}:</h5>
                                                    </div>
                                                    <div class="ms-2">
                                                        <h6 class="mb-0">{{ formattedDuration }}</h6>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                        <!-- Ride info -->
                        <div class="row">
                          <div class="col-sm-6">
                              <div class="mb-3">
                                  <label for="type" class="form-label">{{$t("booking_type")}}
                                    <span class="text-danger">*</span>
                                  </label>
                                  <select id="type" class="form-select" v-model="form.is_later">
                                  <option disabled value="">{{$t("choose_type")}}</option>
                                  <option value=0>{{$t("instant_booking")}}</option>
                                  <option value=1>{{$t("book_later")}}</option>
                                  </select>
                              </div>
                          </div>
                          <div class="col-sm-6">
                            <div class="col-12">
                              <div class="mb-3">
                                <label for="type" class="form-label" >{{$t("app_services")}}
                                  <span class="text-danger">*</span>
                                </label>
                                <select id="type" class="form-select" v-model="form.service" @change = changelater()>
                                  <option disabled value="">{{ $t("choose_app_services") }}</option>
                                  <option v-for="(type, index) in appModules"  :key="index" :value="type.id">
                                  {{ type.name }}
                                  </option>
                                </select>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- POC info -->
                        <h4 v-if="form.transport_type=='delivery' && form.is_rental != 1" class="card-title mb-2 flex-grow-1 mt-4">{{$t("poc_info")}}</h4>
                        <div v-if="form.transport_type=='delivery' && form.is_rental != 1" class="px-2 mb-2">
                          <span class="badge bg-dark-subtle text-dark p-1 fs-12 text-center">
                            <div class="form-check m-2 mx-3">                            
                              <input class="form-check-input" type="checkbox" id="poc" v-model="isPocSame">
                              <label class="form-check-label mt-1" for="poc">
                              {{$t("check_if_poc_info_as_same_as_personal_info")}}
                              </label>
                            </div>
                          </span>
                        </div>
                        <div v-if="form.transport_type=='delivery' && form.is_rental != 1" class="col-6">
                          <div class="mb-3">
                            <label for="poc_name" class="form-label">{{$t("drop_poc_name")}}
                              <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" v-model="form.drop_poc_name" :placeholder="$t('enter_poc_name')" id="poc_name" :disabled="isPocSame" >
                          </div>
                        </div>
                        <div v-if="form.transport_type=='delivery' && form.is_rental != 1" class="col-6">
                          <div class="mb-3">
                            <label for="poc_mobile" class="form-label">{{$t("drop_poc_mobile")}}
                              <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" v-model="form.drop_poc_mobile" :placeholder="$t('enter_poc_mobile')" id="poc_mobile" :disabled="isPocSame" >
                          </div>
                        </div>
                        <h4 v-if="form.transport_type=='delivery' && showGoodsType" class="card-title mb-2 flex-grow-1 mt-4">{{$t("select_goods")}}</h4>
                        <div v-if="form.transport_type=='delivery' && showGoodsType" class="col-6">
                          <div class="mb-3">
                            <label for="goods_type" class="form-label">{{$t("goods_type")}}
                              <span class="text-danger">*</span>
                            </label>
                            <select id="goods_type" class="form-select" v-model="form.goods_type_id">
                              <option disabled value="">{{$t("choose_type")}}</option>
                              <option v-for="goodsType in goodsTypes" :key="goodsType.id" :value="goodsType.id">{{ goodsType.goods_type_name }}</option>
                            </select>
                          </div>
                        </div>
                        <div v-if="form.transport_type=='delivery' && showGoodsType" class="col-6">
                          <div class="mb-3">
                            <label for="goods_type_quantity" class="form-label">{{$t("goods_quantity")}}
                              <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" v-model="form.goods_type_quantity" :placeholder="$t('enter_quantity')" id="goods_type_quantity" >
                          </div>
                        </div>

                        <!-- rental package -->
                        <h4 v-if="form.is_rental == 1" class="card-title mb-3 flex-grow-1 mt-4">{{$t("select_pack")}}</h4>
                        <div v-if="form.is_rental == 1" style="max-width:600px; overflow-x: auto;" class="col-12">
                          <div class="mb-3">
                            <label for="rental_package_id" class="form-label">{{$t("rental_package_id")}}
                              <span class="text-danger">*</span>
                            </label>
                            <select id="rental_package_id" class="form-select" v-model="form.rental_package_id">
                              <option disabled value="">{{$t("choose_type")}}</option>
                              <option v-for="pack in packageTypes" :key="pack.id" :value="pack.id">{{ pack.package_name }}</option>
                            </select>
                          </div>
                        </div>

                        <div class="col-12" v-if="form.is_later === '1'">
                          <div class="mb-3">
                            <label for="dispatch-datepicker" class="form-label">{{$t("date")}}
                              <span class="text-danger">*</span>
                            </label>
                            <flat-pickr :placeholder="$t('select_date')" v-model="form.trip_start_time" :config="dateTimeConfig"
                            class="form-control flatpickr-input" id="dispatch-datepicker"></flat-pickr>
                          </div>
                        </div>
                        <div class="row">
                          <div v-if="form.is_out_station == 1" class="mt-4 mb-4">
                              <h4 class="card-title mb-3 flex-grow-1">{{$t("outstation")}}
                                <span class="text-danger">*</span>
                              </h4>
                              <div>
                                  <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="is_round_trip"
                                      id="oneway" :value=0 v-model="form.is_round_trip">
                                      <label class="form-check-label" for="oneway">{{$t("one_way_outstation_trip")}}</label>
                                  </div>
                                  <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="is_round_trip"
                                      id="roundtrip" :value=1 v-model="form.is_round_trip">
                                      <label class="form-check-label" for="roundtrip">{{$t("round_trip_outstation_trip")}}</label>
                                  </div>
                              </div>
                          </div>
                        </div>

                        <div class="col-12" v-if="form.is_round_trip == 1 && form.is_out_station == 1">
                          <div class="mb-3">
                            <label for="dispatch-datepicker" class="form-label">{{$t("return_time")}}
                              <span class="text-danger">*</span>
                            </label>
                            <flat-pickr :placeholder="$t('return_time')" v-model="form.return_time" :config="dateTimeConfigReturnTime"
                            class="form-control flatpickr-input" id="dispatch-datepicker"></flat-pickr>
                          </div>
                        </div>

                        <div class="d-flex align-items-center">
                          <div class="col-sm-6 col-lg-6">
                              <div class="mb-3">
                                  <div class="d-flex">
                                      <label for="name" class="form-label">{{$t("promo")}}</label>
                                      <BLink @click="promo_list = !promo_list" class="ms-2">
                                          <h6 class="text-success float-end d-flex align-items-center me-3 text-decoration-underline text-decoration-underline-success">
                                              <!-- <i class="bx bx-info-circle fs-20 me-1"></i> -->
                                              {{$t('promo_list')}}
                                          </h6>
                                      </BLink>  
                                  </div>                                       
                                  <input type="text" class="form-control" v-model="form.promo_code" :placeholder="$t('enter_promo')" id="promo" >
                              </div>
                          </div>
                          <div class="mt-3 ms-2">
                              <button type="button" :disabled="!enablePromoBtn"   @click="applyPromo" class="btn btn-primary">{{$t("apply")}}</button>
                          </div>
                      </div>
                        
                        <h4 v-if="vehicleTypes.length > 0" class="card-title mb-3 flex-grow-1 mt-4">{{$t("select_vehicle")}}
                          <span class="text-danger">*</span>
                        </h4>
                        <div v-if="vehicleTypes.length > 0" style="max-width:600px; overflow-x: auto;" class="col-12">
                          <div class="mb-3">
                            <div class="d-flex mt-5">
                              <div v-for="(vehicleType, index) in vehicleTypes" :key="index" class="select-checkbox-btn text-center">
                                <label :for="'vehicle_' + vehicleType.zone_type_id" class="select-checkbox-btn-wrapper">
                                  <input
                                  :id="'vehicle_' + vehicleType.zone_type_id"
                                  name="types"
                                  type="radio"
                                  :value="vehicleType.zone_type_id"
                                  v-model="form.vehicle_type"
                                  class="select-checkbox-btn-input"
                                  />
                                  <span class="select-checkbox-btn-content">
                                    <a class="w-32 me-4 cursor-pointer">
                                      <div class="text-center mt-2 ms-4 text-dark"><i class="bx bx-time-five mx-1"></i>{{ vehicleType.driver_time || '--' }}</div>
                                      <div class="w-32 h-32 flex-none image-fit rounded-circle">
                                        <img alt="" class="rounded-circle img-fluid" :src="vehicleType.vehicle_icon" />
                                      </div>
                                        <div class="text-center mt-2 amount text-dark" v-if="vehicleType.discounted_totel">
    
                                          <!-- Original price (strikethrough) -->
                                          <span class="text-muted text-decoration-line-through me-2">
                                              {{ vehicleType.currency }} {{ vehicleType.total }}
                                          </span>
                                          <br/>

                                          <!-- Discounted price -->
                                          <span class="fw-bold text-success">
                                              {{ vehicleType.currency }} {{ vehicleType.discounted_totel }}
                                          </span>

                                      </div>
                                      <div class="text-center mt-2 amount text-dark" v-else>{{ vehicleType.currency }} {{ vehicleType.total }}</div>
                                      <div class="text-center mt-2 text-dark">{{ vehicleType.name }}</div>
                                    </a>
                                  </span>
                                </label>
                              </div>
                            </div>
                          </div>
                        </div>
                        <h4 v-if="rentalVehicleTypes.length > 0" class="card-title mb-3 flex-grow-1 mt-4">{{$t("select_vehicle")}}
                          <span class="text-danger">*</span>
                        </h4>
                        <div v-if="rentalVehicleTypes.length > 0" style="max-width:600px; overflow-x: auto;" class="col-12">
                          <div class="mb-3">
                            <div class="d-flex mt-5">
                              <div v-for="(vehicleType, index) in rentalVehicleTypes" :key="index" class="select-checkbox-btn text-center">
                                <label :for="'vehicle_' + vehicleType.zone_type_id" class="select-checkbox-btn-wrapper">
                                  <input
                                  :id="'vehicle_' + vehicleType.zone_type_id"
                                  name="types"
                                  type="radio"
                                  :value="vehicleType.zone_type_id"
                                  v-model="form.vehicle_type"
                                  class="select-checkbox-btn-input"
                                  />
                                  <span class="select-checkbox-btn-content">
                                    <a class="w-32 me-4 cursor-pointer">
                                      <div class="text-center mt-2 ms-4"><i class="bx bx-time-five mx-1"></i>{{ vehicleType.driver_time || '--' }}</div>
                                      <div class="w-32 h-32 flex-none image-fit rounded-circle">
                                        <img alt="" class="rounded-circle img-fluid text-dark" :src="vehicleType.icon" />
                                      </div>
                                      <div class="text-center mt-2 amount text-dark" v-if="vehicleType.discounted_total">
                                          <!-- 3) Display Original price as strikethrough -->
                                          <span class="text-muted text-decoration-line-through me-2">
                                              {{ vehicleType.currency }} {{ vehicleType.fare_amount }}
                                          </span>
                                          <br/>
                                          <!-- 4) Display Discounted price -->
                                          <span class="fw-bold text-success">
                                              {{ vehicleType.currency }} {{ vehicleType.discounted_total }}
                                          </span>
                                      </div>
                                      <div class="text-center mt-2 amount text-dark" v-else>{{ vehicleType.fare_amount }}</div>
                                      <div class="text-center mt-2 text-dark">{{ vehicleType.name }}</div>
                                    </a>
                                  </span>
                                </label>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- map  -->
                    <div class="col-12 col-lg-5">
                      <div class="row">
                        <!-- Personal info -->
                        <h4 class="card-title mb-3 flex-grow-1">{{$t("customer_details")}}</h4>
                        <div class="col-sm-6 col-lg-12 mb-3">  
                          <div>
                            <label class="form-label">{{$t("mobile")}}
                              <span class="text-danger">*</span>
                            </label>
                            <div class="input-group" data-input-flag="">
                              <button class="btn btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img :src="selectedCountry.flag" alt="flag" height="20" class="country-flagimg rounded">
                                <span class="ms-2 country-codeno">{{ selectedCountry.dial_code }}</span>
                              </button>
                              <input type="text" class="form-control rounded-end flag-input" @keydown="preventScroll" v-model="form.mobile" :placeholder="$t('enter_number')" @input="validateNumber">
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
                        </div>
                        <div class="col-sm-6 col-lg-12 mb-3">
                          <div class="mb-3">
                            <label for="name" class="form-label">{{$t("name")}}
                              <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" v-model="form.name" :placeholder="$t('enter_name')" id="name" >
                          </div>
                        </div>
                      </div>
                      <div class="col-sm-6 col-lg-12" v-if="form.transport_type != 'delivery'">
                        <div class="mb-3">
                          <label for="select_preference" class="form-label">{{$t("preference_for_user")}}</label>
                          <Multiselect
                            mode="tags" 
                            id="select_categoires"
                            v-model="form.preferences"
                            :options="preferences.map(preference => ({value:preference.id,label:preference.name}))"
                            multiple
                            :close-on-select="false"
                            :searchable="true"
                            :create-option="false"
                            :placeholder="$t('select_preference')"
                          />
                        </div>
                      </div>
                      <!-- Vehicle Select -->                          
                      
                      <!-- Asiign Driver -->
                      <div class="mt-4 mb-4" v-if="driver === null">
                        <h4 class="card-title mb-3 flex-grow-1">{{$t("assign")}}</h4>
                        <div>
                          <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="inlineRadioOptions" :checked="form.assign_method == 1" id="WithoutinlineRadio1" value=1 v-model="form.assign_method">
                            <label class="form-check-label" for="WithoutinlineRadio1">{{$t("manual_assign")}}</label>
                          </div>
                          <div class="form-check form-check-inline" v-if="!driverId">
                            <input class="form-check-input" type="radio" name="inlineRadioOptions" id="WithoutinlineRadio2" value=0 v-model="form.assign_method" :checked="form.assign_method == 0">
                            <label class="form-check-label" for="WithoutinlineRadio2">{{$t("automatic_assign")}}</label>
                          </div>
                        </div>
                      </div>
                      <div class="mb-4" v-if="estimated_fare">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 d-flex align-items-center">
                                <BLink @click="estimateFare = !estimateFare">
                                    <h6 class="text-success float-end d-flex align-items-center">
                                        <i class="bx bx-info-circle fs-20 me-1"></i>
                                        {{$t('estimated_fare')}}
                                    </h6>
                                </BLink>
                            </div>
                        </div>
                    </div>
                    <div class="card-title" v-if="driver != null">{{$t("driver_details")}}:</div>
                      <BCard class="shadow card-animate ms-2 border" v-if="driver != null">
                          <BCardBody>
                              <div class="d-flex align-items-center justify-content-between">
                                  <div class="mb-2">
                                      <div class="d-flex align-items-center">
                                          <div class="flex-shrink-0">
                                              <h5 class="mb-0">{{$t("name")}}:</h5>
                                          </div>
                                          <div class="ms-2">
                                              <h6 class="mb-0">{{ driver.name }}</h6>
                                          </div>
                                      </div>
                                  </div>
                                  <div class="mb-2">
                                      <div class="d-flex align-items-center">
                                          <div class="flex-shrink-0">
                                              <h5 class="mb-0">{{$t("mobile")}}:</h5>
                                          </div>
                                          <div class="ms-2">
                                              <h6 class="mb-0">{{ driver.mobile_number }}</h6>
                                          </div>
                                      </div>
                                  </div>      
                              </div>
                              <div class="d-flex align-items-center justify-content-between">
                                  <div class="mb-2">
                                      <div class="d-flex align-items-center">
                                          <div class="flex-shrink-0">
                                              <h5 class="mb-0">{{$t("vehicle_type")}}:</h5>
                                          </div>
                                          <div class="ms-2">
                                              <h6 class="mb-0">{{ driver.vehicle_type_name }}</h6>
                                          </div>
                                      </div>
                                  </div>
                                  <div class="mb-2">
                                      <div class="d-flex align-items-center">
                                          <div class="flex-shrink-0">
                                              <h5 class="mb-0">{{$t("transport_type")}}:</h5>
                                          </div>
                                          <div class="ms-2">
                                              <h6 class="mb-0">{{ driver.transport_type }}</h6>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                              
                          </BCardBody>
                      </BCard>
                      <div class="d-flex" v-if="showHidebtn">
                        <span class="me-2">{{ $t('enquiry') }} - </span>
                        <BLink @click="dispatcher_enquiry = !dispatcher_enquiry">
                            <h6 class="text-success  d-flex align-items-center me-3 text-decoration-underline text-decoration-underline-success">
                                {{$t('how_it_works')}}
                            </h6>
                        </BLink> 
                    </div>
                    <div class="row">
                      <div class="col-lg-6">
                        <div style="float:inline-end">
                            <button type="submit"   @click="enquiryModel()" :disabled="!enableBooking" class="btn btn-primary" v-if="showHidebtn">{{$t("enquiry")}}</button>
                        </div>
                      </div>
                        <div class="col-lg-6">
                          <div style="float:inline-start">
                              <button type="submit"  :disabled="!enableBooking"  @click="makeBooking" class="btn btn-primary">{{$t("make_booking")}}</button>
                          </div>
                      </div>
                    </div>  
                    </div>  
                  </div>
                </FormValidation>
              </form>
                <BModal v-model="showMap" hide-footer :title="$t('map_view')" class="v-modal-custom" size="lg">
                  <div class="mb-3 text-center m-auto">
                    <div id="map" style="height: 500px;"> {{$t("map_loading")}}</div>
                    <div id="route-directions"></div>
                  </div>
              </BModal>
            </BCardBody>
        </BCard>
    </BCol>
</BRow>
    </div>
    <!-- modal -->

    

         <BModal v-model="promo_list" hide-footer :title="$t('promo_list')" class="v-modal-custom" size="md">
            <div class="container"> 
                <ul class="list-unstyled vstack gap-3">
                    <li v-for="result in results">
                        <div class="d-flex border-bottom pb-2">
                            <div class="flex-shrink-0 text-success me-2 mt-1">
                                <i class="bx bxs-offer fs-20 align-middle"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center">
                                    <span class="fs-16 fw-bold text-primary">{{ result.code }}</span>
                                    <i class="bx bx-copy ms-3 text-muted fs-18 custom-copy-hover" style="cursor:pointer;" @click="copyPromo(result.code); promo_list = false;" title="Copy and Apply"></i>
                                </div>
                                <p class="text-muted mb-0 mt-1">Save upto {{ result.maximum_discount_amount }} on your ride</p>
                            </div>
                        </div>
                    </li>
                </ul>              
            </div>
        </BModal>
        <BModal v-model="estimateFare" hide-footer :title="$t('estimated_ride_fare')" class="v-modal-custom" size="md">
         <div class="container">
            <div class="mt-4 d-flex align-items-end justify-content-between border-bottom pb-2">       
                <div>
                    <h6>{{$t('vehicle_type')}}</h6>
                    <p>{{estimated_fare?.type_name || ''}}</p>                    
                </div>

                <div class="ms-2">
                    <img class="rounded-circle avatar-xl" alt="200x200" :src="estimated_fare?.vehicle_icon || ''">
                </div>
            </div>
            <div class="mt-4 d-flex align-items-end justify-content-between">       
                <div>{{$t('base_price')}}</div>
                <div class="ms-2">
                    <p class="mb-0">{{estimated_fare?.currency || ''}}{{ estimated_fare?.base_price || ''}}</p>
                </div>
            </div>
            <div class="mt-4 d-flex align-items-end justify-content-between">       
                    <p>{{$t('sedan')}}</p>                    
                <div>{{$t('distance_price')}} ({{ estimated_fare?.distance || ''}}  {{$t("km")}} * {{estimated_fare?.currency || ''}} {{ estimated_fare?.price_per_distance || ''}} / {{$t("km")}})</div>
                <div class="ms-2">
                    <p class="mb-0">{{estimated_fare?.currency || ''}}{{ estimated_fare?.distance_price || ''}}</p>
                </div>
            </div>
            <div class="mt-4 d-flex align-items-end justify-content-between">       
                <div>{{$t('time_price')}} ({{ estimated_fare?.time || ''}}  {{$t("min")}} * {{estimated_fare?.currency || ''}} {{ estimated_fare?.price_per_time || ''}} / {{$t("min")}})</div>
                <div class="ms-2">
                    <p class="mb-0">{{estimated_fare?.currency || ''}}{{ estimated_fare?.time_price || ''}}</p>
                </div>
            </div>
            <div class="mt-4 d-flex align-items-end justify-content-between">       
                <div>{{$t('tax')}} ({{ (estimated_fare?.tax) || '' }} %)</div>
                <div class="ms-2">
                    <p class="mb-0">{{estimated_fare?.currency || ''}}{{ estimated_fare?.tax_amount || ''}}</p>
                </div>
            </div>
            <div class="mt-4 d-flex align-items-end justify-content-between">       
                <div>{{$t('convenience_fee')}}</div>
                <div class="ms-2">
                    <p class="mb-0">{{estimated_fare?.currency || ''}}{{ estimated_fare?.without_discount_admin_commision || ''}}</p>
                </div>
            </div>
            <div class="mt-4 d-flex align-items-end justify-content-between border-top pt-2">       
                <div>{{$t('total')}}</div>
                <div class="ms-2">
                    <p class="mb-0">{{estimated_fare?.currency || ''}}{{ estimated_fare?.total || ''}}</p>
                </div>
            </div>
            <div class="mt-4 d-flex">       
                <div><i class="bx bxs-info-circle fs-20 me-1 text-danger"></i></div>
                <div class="ms-2">
                    <p class="text-muted mb-0">Wating Charge of {{ estimated_fare?.waiting_charge || ''}} per minute, it will apply after the driver has been wating for {{ estimated_fare?.free_waiting_time_in_mins_after_trip_start || ''}} minutes</p>
                </div>
            </div>
         </div>
        </BModal>

          <BModal v-model="dispatcher_enquiry" hide-footer :title="$t('dispatcher_enquiry')" class="v-modal-custom" size="md">
            <div class="container"> 
                <ul class="list-unstyled vstack gap-3">
                    <li>
                        <div class="d-flex">
                        <div class="flex-shrink-0 text-success me-1">
                            <i class="ri-checkbox-circle-fill fs-15 align-middle"></i>
                        </div>
                        <div class="flex-grow-1">Users can enquire about the approximate price based on their pickup and drop-off locations.</div>
                        </div>
                    </li>
                    <li>
                        <div class="d-flex">
                        <div class="flex-shrink-0 text-success me-1">
                            <i class="ri-checkbox-circle-fill fs-15 align-middle"></i>
                        </div>
                        <div class="flex-grow-1">After making an enquiry, if you click the Enquiry button, the system will store the user’s enquiry details.</div>
                        </div>
                    </li>
                    <li>
                    
                        <div class="d-flex">
                        <div class="flex-shrink-0 text-success me-1">
                            <i class="ri-checkbox-circle-fill fs-15 align-middle"></i>
                        </div>
                        <div class="flex-grow-1">Later, you can follow up with the user to check whether they converted into a ride or not.</div>
                        </div>
                    </li>
                </ul>              
            </div>
        </BModal>

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
.booking{
    border-right: 1px;
    border-right-color:#dbdbdb;
    border-right-style: solid;
    max-height:500px;
    overflow-y: auto;
}
@media only screen and (max-width: 769px) {
.booking{
    border-right: none;
    max-height:100%;
    overflow-y: auto;
}
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

.leaflet-routing-container {
  display:none
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
.text-danger {
padding-top: 5px;
}

:root {
--primary: #222222;
--primary-hover: {{ $side -> value }};
}
/* payments */
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
background-color: black;
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
font-family: Arial, sans-serif; /* Ensure the font supports the rupee symbol */
font-size: 16px; /* Adjust the size as needed */
white-space: nowrap; /* Prevent text from wrapping */
letter-spacing: 0.1em; /* Adjust spacing if needed */
}
/* end */
</style>
