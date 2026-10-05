<script>
import { Link, Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/Agent/agentmain.vue";
import PageHeader from "@/Components/page-header.vue";
import Pagination from "@/Components/Pagination.vue";
import Swal from "sweetalert2";
import { ref, watch, computed ,onMounted,reactive } from "vue";
import axios from "axios";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";
import search from "@/Components/widgets/search.vue";
import searchbar from "@/Components/widgets/searchbar.vue";
import { FirebaseError } from 'firebase/app';
import { useI18n } from 'vue-i18n';
import { mapGetters } from 'vuex';
import { useSharedState } from '@/composables/useSharedState';
import "@fullcalendar/core";
import FullCalendar from "@fullcalendar/vue3";
import dayGridPlugin from "@fullcalendar/daygrid";
import timeGridPlugin from "@fullcalendar/timegrid";
import interactionPlugin, { Draggable } from "@fullcalendar/interaction";
import bootstrapPlugin from "@fullcalendar/bootstrap";
import listPlugin from "@fullcalendar/list";
import multiMonthPlugin from '@fullcalendar/multimonth';

export default {
    data() {
        return {
            rightOffcanvas: false,  
            dateTimeConfig: {
                enableTime: true,
                dateFormat: "Y-m-d H:i:ss",
            },
             dateTimeConfigReturnTime:{
                enableTime: true,
                dateFormat: "Y-m-d H:i:ss",
            },
        };
    },
    components: {
        Layout,
        PageHeader,
        Head,
        Pagination,
        Multiselect,
        flatPickr,
        Link,
        search,
        searchbar,
        FullCalendar

    },
    props: {
        successMessage: String,
        alertMessage: String,
        zones: Object,
        firebaseConfig: Object,
        service_location_id: String,
        ongoing_rides: Object,
        enable_outstation:Boolean,
        types: Object,
        map_key: String,
    },
    methods: {
    navigateToInvoice(invoiceType, id) {
        // Navigate to the invoice Blade file
        const url = `/agent/rides_request/download-invoice/${id}?invoice_type=${invoiceType}`;
        window.location.href = url;
    },
    navigateUserInvoice(id) {
        this.navigateToInvoice("user", id);
    },
    navigateDriverInvoice(id) {
        this.navigateToInvoice("driver", id);
    },
  },
    setup(props) {
        const { t } = useI18n();
        const searchTerm = ref("");
        const activeTab = ref('all');
        const filter = useForm({
            ride_status : 'all',
            is_bid_ride : null,
            zone_id : null,
            vehicle_type_id : null,
            is_paid : null,
            service_location_id: props.service_location_id,
            limit:10,
            payment_opt:null,
        });
        const zones = ref(props.zones);
        const types = ref(props.types);
        const ongoing_rides = ref(props.ongoing_rides);
        const results = ref([]);
        const paginator = ref({});
        const modalShow = ref(false);
        const modalFilter = ref(false);
        const deleteItemId = ref(null);
        const successMessage = ref(props.successMessage || '');
        const alertMessage = ref(props.alertMessage || '');
        const paginatorOption = ref({});
        const zoneList = ref([]);
        const dismissMessage = () => {
            successMessage.value = "";
            alertMessage.value = "";
        };
         const errors = ref({});

        const rightOffcanvas = ref(false);
        const filterData = () => {
            fetchDatas();
            modalFilter.value = true;
            rightOffcanvas.value = false;
        };


        const clearFilter = () => {
            filter.reset();
            fetchDatas();
            modalFilter.value = false;
            rightOffcanvas.value = false;
        };

        watch(activeTab, (newTab) => {
        filter.ride_status = newTab;
        fetchDatas(); // Fetch data for the selected tab
    });

    const getInitialView = ()=>  {
        if (window.innerWidth >= 768 && window.innerWidth < 1200) {
            return "timeGridWeek";
        } else if (window.innerWidth <= 768) {
            return "listMonth";
        } else {
            return "dayGridMonth";
        }
    };


        onMounted( async ()=> {
            try{
                const firebaseConfig = props.firebaseConfig;
                if (!firebase.apps.length) {
                    firebase.initializeApp(firebaseConfig);
                }
                const database = firebase.database();
                ongoing_rides.value.forEach((ride) => {
                    const tripRef = database.ref(`requests/${ride}`);
                    tripRef.on('value',function(snapshot){
                        const index = results.value.findIndex(data => data.id === ride);
                        if (index !== -1) {
                            const val =  snapshot.val();
                            if(val.is_completed){
                                results.value[index].is_completed = true;
                            }
                            if(val.accept !== 1){
                                results.value[index].driver_id = null;
                            }
                            if(val.driver_id){
                                results.value[index].driver_id = val.driver_id;
                            }
                            if(val.hasOwnProperty('modified_by_driver')){
                                results.value[index].is_driver_started = 1;
                            }
                            if(val.trip_arrived == 1){
                                results.value[index].is_driver_arrived = true;
                            }
                            if(val.trip_start == 1){
                                results.value[index].is_trip_start = true;
                            }
                            if(val.is_completed){
                                results.value[index].is_completed = true;
                            }
                            if(val.is_cancelled || val.is_cancel){
                                results.value[index].is_cancelled = true;
                            }
                        }
                    });
                })
                fetchDatas();
            } catch (error) {
                console.error(t('error_initializing_firebase_or_fetching_settings'), error);
            }
        });
        const closeModal = () => {
            modalShow.value = false;
        };
        const changeTripTime = async (dataId) => {
            try {
                let response;
                response = await axios.post( `/agent/update-trip-start-time/${dataId.id}`, dataId);
                Swal.fire(t('success'), t('trip_schedule_time_has_been_changed_successfully'), 'success')
                .then(async (result) => {
                    if (result.isConfirmed) {
                        router.get("/agent/schedule_rides");
                    }
                });
            } catch (error) {
                console.log(error);
                Swal.fire(t('error'), error.response.data.error, 'error')
                .then(async (result) => {
                    if (result.isConfirmed) {
                        router.get("/agent/schedule_rides");
                    }
                });
            }
        };

        const deleteModal = async (itemId) => {
            Swal.fire({
                title: "Are you sure?",
                text: "You want to be cancel this ride!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#34c38f",
                cancelButtonColor: "#f46a6a",
                confirmButtonText: "Yes, Cancel!",
                cancelButtonText: "Close",
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        await deleteData(itemId);
                    } catch (error) {
                        console.error(t('error_deleting_data'), error);
                        Swal.fire(t('error'), t('failed_to_cancel_the_data'), "error");
                    }
                }
            });
        };

        const fetchSearch = async (value) => {
            searchTerm.value = value;
            fetchDatas();
        };

        const fetchDatas = async (page = 1) => {
                
            try {
                const params = filter.data();
                if(searchTerm.value.length > 0){
                    params.search = searchTerm.value;
                }
                params.page = page;
                const response = await axios.get(`/agent/schedule_rides/list`);
                results.value = response.data.results.data;         
                modalFilter.value = false;
            } catch (error) {
                console.error(t('error_fetching_requests'), error);
            }
        };
        // **Handle per-page changes**
        const changeEntriesPerPage = () => {
            fetchDatas(); // Fetch new data
        };

        const handlePageChanged = async (page) => {
            fetchDatas(page);
        };

        const rideStatus = (trip) => {
            if(trip.is_cancelled){
                return 'Cancelled';
            }else if(trip.is_completed){
                return 'Completed';
            }else if(trip.is_trip_start){
                return 'On Trip';
            }else if(trip.is_driver_arrived){
                return 'Driver Arrived';
            }else if(trip.is_later && trip.is_driver_started){
                return 'Driver Started';
            }else if(trip.is_driver_started){
                return 'Accepted';
            }else if(!trip.is_later){
                return 'Searching';
            }else{
                return 'Upcoming'
            }
        };
        const editData = async (result) =>  {
            router.get(`/agent/rides_request/view/${result.id}`); 
        };
        const parseCustomDate = (str) =>{

        const cleaned = str.replace(/(\d+)(st|nd|rd|th)/, "$1");
        const year = new Date().getFullYear();
        const finalString = `${cleaned} ${year}`;
        return new Date(finalString);
        };


        const events = computed(() =>
            results.value.map(ride => ({
                id: ride.id,
                title: ride.request_number,
                start: parseCustomDate(ride.converted_trip_start_time),      
                extendedProps: ride ,         
                backgroundColor: "black", 
            }))
        );
        
        const stopovers = reactive([]);
        const maxStopovers = 2;
        
        const vehicleTypes = ref([]);
        const packageTypes = ref([]);
        const rentalVehicleTypes = ref([]);
        const etaParam = useForm({
            pick_lat: "",
            pick_lng: "",
            drop_lat: "",
            drop_lng: "",
            distance: 0,
            distance_in_unit: 0,
            duration: 0,
        });

    
         const formatDate = (date) =>{
            var monthNames = [
                "January",
                "February",
                "March",
                "April",
                "May",
                "June",
                "July",
                "August",
                "September",
                "October",
                "November",
                "December",
            ];
            var d = new Date(date),
                month = "" + monthNames[d.getMonth()],
                day = "" + d.getDate(),
                year = d.getFullYear();
            if (month.length < 2) month = "0" + month;
            if (day.length < 2) day = "0" + day;
            return [day + " " + month, year].join(",");
        };

        const dateStamp = (start, end) =>{
            let date;
            if (end == null) {
                date = formatDate(start);
            }
            else {
                date = formatDate(start);
            }
            return date;
        };
        const formatTime = (params) =>{
            params = new Date(params);
            if (params.getHours() != null) {
                let hour = params.getHours();
                let minute = params.getMinutes() ? params.getMinutes() : "00";
                let timeFormat = hour >= 12 ? "PM" : "AM";
                hour = hour % 12;
                hour = hour ? hour : 12;
                minute = (minute < 10 && minute != 0) ? "0" + minute : minute;
                return hour + ":" + minute + " " + timeFormat;
            }
        };
        const timeStamp = (start, end) =>{
            let time;
            if (formatTime(start)) {
                time = formatTime(start);
            } else {
                time = formatTime(start);
            }
            return time;
        };
        const formatDateTime = (date) =>{
            if (!(date instanceof Date)) return null;

            const pad = (n) => String(n).padStart(2, "0");

            const year = date.getFullYear();
            const month = pad(date.getMonth() + 1); // months start at 0
            const day = pad(date.getDate());
            const hours = pad(date.getHours());
            const minutes = pad(date.getMinutes());
            const seconds = pad(date.getSeconds());

            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        };

        const dataEdit = ref(false);
        const eventModal = ref(false);
        const showModal = ref(false);
        const dispatch_type = ref(t('normal'));
        const editEventDetails = (info) =>{
            calendarOptions.value.edit = info.event;
            calendarOptions.value.eventTitle =  info.event.title;
            calendarOptions.value.event.title =  info.event.title;
            calendarOptions.value.event.type =  info.event.classNames;
            calendarOptions.value.event.id =  info.event.extendedProps.id;
            calendarOptions.value.event =  { ...info.event.extendedProps };


            if(calendarOptions.value.event.is_bid_ride){
                dispatch_type.value = t('bidding');
            }
            if(calendarOptions.value.event.is_rental){
                dispatch_type.value = t('rental');
            }
            if(calendarOptions.value.event.is_out_station){
                if(calendarOptions.value.event.is_round_trip){
                    dispatch_type.value = t('round_trip_outstation_trip');
                }else{
                    dispatch_type.value = t('one_way_outstation_trip');
                }
            }
            stopovers.splice(0, stopovers.length, ...info.event.extendedProps.requestStops?.data || []);



            // trip start time
            const dateStr = info.event.extendedProps.trip_start_time_with_date; 
            const timeStr = info.event.extendedProps.cv_trip_start_time;

            // Split date
            const [day, month, year] = dateStr.split("/").map(Number);

            // Parse time
            let [time, modifier] = timeStr.split(" "); // ["10:27", "PM"]
            let [hours, minutes] = time.split(":").map(Number);

            if (modifier === "PM" && hours !== 12) hours += 12;
            if (modifier === "AM" && hours === 12) hours = 0;

            // Create Date object
            const combinedDate = new Date(year, month - 1, day, hours, minutes);

            // Assign to flatpickr v-model
            calendarOptions.value.event.trip_start_time = combinedDate;

            const retrunDateStr = info.event.extendedProps.retrun_time_with_date;
            const retrunTimeStr = info.event.extendedProps.cv_return_time; 

            // Split date
            if(retrunDateStr != null){
                const [retrunDay, retrunMonth, retrunYear] = retrunDateStr.split("/").map(Number);

                // Parse time
                let [retrunTime, retrunModifier] = retrunTimeStr.split(" "); // ["10:27", "PM"]
                let [retrunHours, retrunMinute] = retrunTime.split(":").map(Number);

                if (retrunModifier === "PM" && retrunHours !== 12) retrunHours += 12;
                if (retrunModifier === "AM" && retrunHours === 12) retrunHours = 0;

                // Create Date object
                const retrunCombinedDate = new Date(retrunYear, retrunMonth - 1, retrunDay, retrunHours, retrunMinute);

                // Assign to flatpickr v-model
                calendarOptions.value.event.return_time = retrunCombinedDate;

            }            
            dataEdit.value = true;
            eventModal.value = true;
            loadVehicleTypes(calendarOptions.value.event);
        };

        const loadRentalPack = async (events) => {
            vehicleTypes.value = [];

            const payload = {
                pick_lat: events.pick_lat,
                pick_lng: events.pick_lng,
                transport_type: events.transport_type,
            };

            const response = await axios.post(
                `/dispatch/request/list_packages`,
                payload
            );

            packageTypes.value = response.data.data;
        };


        const loadVehicleTypes = async (events) => {
            if (events.is_rental == true) {
                loadRentalPack(events);
                return false;
            }
            rentalVehicleTypes.value = [];
            
            const payload = {
                pick_lat: events.pick_lat,
                pick_lng: events.pick_lng,
                transport_type: events.transport_type,
                drop_lat: events.drop_lat,
                drop_lng: events.drop_lng,

            };
            if (events.is_out_station) {
                payload.is_out_station = 1;

                if (events.trip_start_time) {
                    payload.trip_start_time = events.trip_start_time;
                }
                if (events.return_time) {
                    payload.return_time = events.return_time;
                }
            }
            payload.dispatch = 1;

            try{
              const response = await axios.post(
                  `/dispatch/request/eta`,
                  payload
              );
              vehicleTypes.value = response.data.data;
              if(vehicleTypes.value.length>0){
                  if(vehicleTypes.value[0].unit_in_words == 'MILES'){
                      etaParam.distance_in_unit = etaParam.distance * 0.621371;
                      unit.value = t('miles');
                  }else{
                      etaParam.distance_in_unit = etaParam.distance;
                      unit.value = t('km');
                  }
              }

            }catch(error){
                console.error(errors);
            }
        };


        const draggableOptions = async (itemId) => {
            Swal.fire({
                title: "Are you sure?",
                text: `You want to reschedule this ride to ${itemId.newTripStartTime.toLocaleString()}?`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#34c38f",
                cancelButtonColor: "#f46a6a",
                confirmButtonText: "Yes, Cancel!",
                cancelButtonText: "Close",
            }).then(async (result) => {
                if (!result.isConfirmed) {
                    router.get("/agent/schedule_rides");
                    return;
                }

                
                if (itemId.is_trip_start == 1) {
                    Swal.fire(t('error'), t('trip_has_been_started_you_not_change_the_time'), "error")
                        .then(() => {
                            router.get("/agent/schedule_rides");
                        });
                    return;
                }

                
                try {
                    await changeTripTime(itemId);
                } catch (error) {
                    console.error(t('error_deleting_data'), error);
                    Swal.fire(t('error'), t('failed_to_cancel_the_data'), "error");
                }
            });
        };

        const eventBooking = ref(false);
        const selectedDate = ref();

        const calendarOptions = ref({
            timeZone: "local",
            droppable: true,
            navLinks: true,
            plugins: [
                dayGridPlugin,
                timeGridPlugin,
                interactionPlugin,
                bootstrapPlugin,
                listPlugin,
                multiMonthPlugin
            ],
            themeSystem: "bootstrap",
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "multiMonthYear,dayGridMonth,listMonth",
            },
            windowResize: () => {
                getInitialView();
            },
            initialView: 'dayGridMonth',
            events,
            eventContent: (arg) => {
                const ride = arg.event.extendedProps
                return {
                html: `
                    <div style="padding:2px; text-align:left; font-size:12px;color:white;">
                    <strong>${ride.request_number}</strong><br/>
                    <strong>Time - ${ride.cv_trip_start_time}</strong><br/>
                    <strong>Vehicle Type - ${ride.vehicle_type_name}</strong>
                    </div>
                `
                }
            },

            event: {},
            editable: true,
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            weekends: true,
            dataEdit: dataEdit.value,
            showModal: false,
            eventModal: eventModal.value,
            edit: {},
            deleteId: {},
            eventTitle: "",
            eventClick: editEventDetails,
            eventDrop: (info) => {
                const updatedDate = formatDateTime(info.event.start);

               
                draggableOptions({
                    ...info.event.extendedProps,  
                    newTripStartTime: updatedDate,
                });
            },
            dateClick(info) {
                const clickedDate = info.date; 

                const today = new Date();
                today.setHours(0, 0, 0, 0); 

                if (clickedDate < today) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Invalid Date',
                        text: 'You can only book for today or future dates.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                const dateStr = clickedDate.toISOString().split('T')[0];
                const now = new Date();
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                const timeStr = `${hours}:${minutes}:${seconds}`;

                console.log("Date:", dateStr);
                console.log("Current Time:", timeStr);

                eventBooking.value = true;
                selectedDate.value = `${dateStr} ${timeStr}`; 
            }


        });
        const clickDateBooking = async () => {
            Swal.fire({
                title: "Are you sure?",
                text: `You want to reschedule this ride to ${selectedDate.value}?`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#34c38f",
                cancelButtonColor: "#f46a6a",
                confirmButtonText: "Yes, Continue!",
                cancelButtonText: "Close",
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                       router.get("/agent/bookride", { date: selectedDate.value });
                    } catch (error) {
                        console.error(t('error_deleting_data'), error);
                        Swal.fire(t('error'), t('failed_to_cancel_the_data'), "error");
                    }
                }                
                else{
                    router.get("/agent/schedule_rides");
                }
            });
        };
        

        const  deleteEvent = () =>{
            calendarOptions.value.edit.remove();
            eventModal.value = false;
            showModal.value = false;
        };

       
        const editbtn = () =>{
            showModal.value = true,
            eventModal.value = false;
        };

       
        const cancelbtn = () =>{
            showModal.value = false;
            eventModal.value = true;
        };

        

        
        

        watch(
            () => calendarOptions.value.event.rental_package_id,
            async (value) => {
                if (!value) return;

                if (!packageTypes.value || !Array.isArray(packageTypes.value)) {
                console.warn("packageTypes not ready yet");
                return;
                }

                const rentalpack = packageTypes.value.find((pack) => pack.id === value);

                if (!rentalpack) {
                console.warn("No rental package found for id:", value);
                return;
                }

                if (rentalpack.typesWithPrice && rentalpack.typesWithPrice.data) {
                    rentalVehicleTypes.value = rentalpack.typesWithPrice.data;
                }
            }
        );
        const pickSuggestions = ref([]);
        const dropSuggestions = ref([]);
        const stopSuggestions = ref([]);

        const addStopover = (restStops) => {
            console.log("addStopover",restStops.length != maxStopovers);
            if (restStops.length != maxStopovers) {
                stopovers.push({
                    address: "",
                    latitude: null,
                    longitude: null,
                });
            } else {
                Swal.fire(
                    t("maximum_stopovers_reached"),
                    t("you_can_add_up_to_5_stopovers_only"),
                    "warning"
                );
            }
        };

        const fetchAutocompleteResults = (search, type, index = null) => {
            const apiUrl = `https://places.googleapis.com/v1/places:autocomplete`;
            const headers = {
                "Content-Type": "application/json",
                "X-Goog-Api-Key": props.map_key,
                "X-Goog-FieldMask":
                    "suggestions.placePrediction.placeId,suggestions.placePrediction.place,suggestions.placePrediction.text",
            };
            const requestData = {
                input: search,
            };

            fetch(apiUrl, {
                method: "POST",
                headers: headers,
                body: JSON.stringify(requestData),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.suggestions?.length > 0) {
                        if (type == "pickup") {
                            pickSuggestions.value = data.suggestions
                                .filter(
                                    (suggestion) => suggestion.placePrediction
                                )
                                .map((suggestion) => ({
                                    placeId: suggestion.placePrediction.placeId,
                                    formattedAddress: suggestion.placePrediction.text.text,
                                }));
                        } else if (type == "drop") {
                            dropSuggestions.value = data.suggestions
                                .filter(
                                    (suggestion) => suggestion.placePrediction
                                )
                                .map((suggestion) => ({
                                    placeId: suggestion.placePrediction.placeId,
                                    formattedAddress: suggestion.placePrediction.text.text,
                                }));
                        } else if (type == "stop") {
                            stopSuggestions.value[index] = data.suggestions
                                .filter(
                                    (suggestion) => suggestion.placePrediction
                                )
                                .map((suggestion) => ({
                                    placeId: suggestion.placePrediction.placeId,
                                    formattedAddress: suggestion.placePrediction.text.text,
                                }));
                        }
                    }
                })
                .catch((error) => {
                    console.error(
                        "Error fetching autocomplete results:",
                        error
                    );
                });
        };

        const selectSuggestion = async (suggestion, input, index = null) => {
            const headers = {
                "X-Goog-Api-Key": props.map_key,
                "X-Goog-FieldMask": "location",
            };
            fetch(
                `https://places.googleapis.com/v1/places/${suggestion.placeId}?fields=location`,
                {
                    headers: headers,
                }
            )
                .then((response) => response.json())
                .then((data) => {
                    if(input == 'pickup'){
                      etaParam.pick_lat = data.location.latitude;
                      etaParam.pick_lng = data.location.longitude;
                      calendarOptions.value.event.pick_address = suggestion.formattedAddress;

                      const position = {lat: data.location.latitude, lng: data.location.longitude };
                      

                      pickSuggestions.value  = [];

                    }else if(input == 'drop') {
                      etaParam.drop_lat = data.location.latitude;
                      etaParam.drop_lng = data.location.longitude;
                      calendarOptions.value.event.drop_address = suggestion.formattedAddress;

                      const position = {lat: data.location.latitude, lng: data.location.longitude };
                      dropSuggestions.value = [];

                    }else if(input == 'stop') {

                      stopSuggestions.value[index] = [];
                      stopovers[index].address = suggestion.formattedAddress;
                      stopovers[index].latitude = data.location.latitude;
                      stopovers[index].longitude = data.location.longitude;

                    }
                })
                .catch((error) => {
                    console.error(
                        "Error fetching autocomplete results:",
                        error
                    );
                });
        };
        const handleInput = (input,index=null) => {
            if (input == "pickup") {
                const value = calendarOptions.value.event.pick_address || "";
                if (value.length < 3) {
                    pickSuggestions.value = [];
                    return;
                } else {
                    setTimeout(() => {
                        fetchAutocompleteResults(calendarOptions.value.event.pick_address, input);
                    }, 300);
                }
            } else if (input == "drop") {
                if (calendarOptions.value.event.drop_address.length < 3) {
                    dropSuggestions.value = [];
                } else {
                    setTimeout(() => {
                        fetchAutocompleteResults(calendarOptions.value.event.drop_address, input);
                    }, 300);
                }
            } else if (input == "stop") {
                if (calendarOptions.value.event[index]?.address.length < 3) {
                    stopSuggestions.value[index] = [];
                } else {
                    setTimeout(() => {
                        fetchAutocompleteResults(stopovers[index]?.address, input,index);
                    }, 300);
                }
            }
        };
        

        const removeStopover = (index) => {
            stopovers.splice(index, 1);
            console.log("stopovers",stopovers);
        };


        const makeBooking = async (id) => {

            const { pick_lat, pick_lng, drop_lat, drop_lng, distance, duration, } = etaParam.data();

            if (pick_lat) {
                calendarOptions.value.event.pick_lat = pick_lat;
            }
            if (pick_lng) {
                calendarOptions.value.event.pick_lng = pick_lng;
            }
            if (drop_lat) {
                calendarOptions.value.event.drop_lat = drop_lat;
            }
            if (drop_lng) {
                calendarOptions.value.event.drop_lng = drop_lng;
            }
            if (distance) {
                calendarOptions.value.event.distance = distance;
            }
            if (duration) {
                calendarOptions.value.event.duration = duration;
            }

            if (stopovers.length > 0) {
                calendarOptions.value.event.stopovers = JSON.stringify(stopovers);
            }

            const payload = {
            id,
            pick_lat: calendarOptions.value.event.pick_lat,
            pick_lng: calendarOptions.value.event.pick_lng,
            drop_lat: calendarOptions.value.event.drop_lat,
            drop_lng: calendarOptions.value.event.drop_lng,
            trip_start_time: calendarOptions.value.event.trip_start_time, 
            return_time: formatDateTime(calendarOptions.value.event.return_time),       
            rental_package_id:calendarOptions.value.event.rental_package_id,
            stopovers: stopovers,
            };
            payload.vehicle_type = calendarOptions.value.event.zone_type_id;


            try {
                let response;
                
                response = await axios.post( `/agent/update-request/${id}`, calendarOptions.value.event);
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
                                Swal.getContent().querySelector(
                                    "b"
                                ).textContent = Swal.getTimerLeft();
                            }, 100);
                        },
                        didClose: () => {
                            clearInterval(timerInterval);
                        },
                    }).then((result) => {
                        if ( result.dismiss === Swal.DismissReason.timer ) {
                            console.log("I was closed by the timer");
                        }
                    });
                        router.get(
                            "/agent/schedule_rides"
                        );
                    etaParam.reset();
                    vehicleTypes.value = [];
                    rentalVehicleTypes.value = [];
                    stopovers.length = 0;
                } else {
                    alertMessage.value = t( "failed_to_make_booking_contact_admin" );
                }
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    errors.value = error.response.data.errors;
                } else {
                    console.error(t("error_creating_vehicle_price"), error);
                    alertMessage.value = t( "failed_to_make_booking_contact_admin" );
                }
            }
        };

        return {
            results,
            modalShow,
            deleteItemId,
            successMessage,
            alertMessage,
            filterData,
            deleteModal,
            closeModal,
            dismissMessage,
            searchTerm,
            paginator,
            modalFilter,
            clearFilter,
            fetchDatas,
            filter,
            zones,
            types,
            rideStatus,
            handlePageChanged,
            editData,
            activeTab,
            fetchSearch,
            rightOffcanvas,
            paginatorOption,
            changeEntriesPerPage,
            zoneList,
            calendarOptions,
            events,
            eventModal,
            formatDate,
            dispatch_type,
            deleteEvent,
            showModal,
            editbtn,
            cancelbtn,
            dataEdit,
            vehicleTypes,
            rentalVehicleTypes,
            selectSuggestion,
            pickSuggestions,
            dropSuggestions,
            stopSuggestions,
            handleInput,
            makeBooking,
            addStopover,
            stopovers,
            maxStopovers,
            removeStopover,
            packageTypes,
            eventBooking,
            selectedDate,
            clickDateBooking
        };
    },
    computed: {
    ...mapGetters(['permissions']),
  },
};
</script>

<template>
    <Layout>

        <Head title="Rides Request" />
        <div class="font">
            
        <PageHeader :title="$t('index')" :pageTitle="$t('scheduled_rides')" />
        <BRow>
            <BCol lg="12">
                <BCard no-body id="tasksList">
                    <BCardBody>
                         <FullCalendar ref="fullCalendar" :options="calendarOptions" />
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>
         <BModal v-model="eventModal" :title="calendarOptions.event.request_number" hide-footer body-class="p-4"
            header-class="p-3 bg-info-subtle" class="v-modal-custom" centered size="xl">
            <div class="text-end">
                <BLink href="#" class="btn btn-soft-primary" id="edit-event-btn" @click="editbtn" role="" v-if="!calendarOptions.event.is_trip_start">
                    <i class="ri-edit-line"></i>
                    {{$t('edit')}}
                </BLink>
            </div>
            <div class="event-details border-bottom mb-4">
                <h5 class="card-title mb-3">{{ $t('request_details') }}</h5>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="flex-grow-1  align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class="ri-map-pin-fill text-success fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('pickup_location') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.pick_address }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-6" v-if="stopovers.length > 0">
                        <div class="flex-grow-1 align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class="ri-map-pin-fill  text-success fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('stop_location') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4 stop-item" v-for="stops in stopovers" :key="index">
                                   {{ stops.address }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-6">
                        <div class="flex-grow-1 align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class="ri-map-pin-fill  text-danger fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('drop_location') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.drop_address }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="flex-grow-1  align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class="ri-calendar-2-line text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('trip_date_time') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.trip_start_time_with_date }} {{ calendarOptions.event.cv_trip_start_time }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-6" v-if="calendarOptions.event.return_time != null">
                        <div class="flex-grow-1  align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class="ri-calendar-2-line text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('return_time') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.retrun_time_with_date }} {{ calendarOptions.event.cv_return_time }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-6">
                        <div class="flex-grow-1 align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class="ri-taxi-fill  text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('ride_type') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ dispatch_type }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-sm-6">
                        <div class="flex-grow-1  align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class="ri-taxi-fill text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('vehicle_type') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.vehicle_type_name }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-6">
                        <div class="flex-grow-1 align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class="ri-taxi-fill  text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('transport_type') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.transport_type }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                </div>
            </div>
            <div class="event-details border-bottom mb-4">
                <h5 class="card-title mb-3">{{ $t('user_details') }}</h5>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="flex-grow-1  align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class=" ri-user-3-fill text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('name') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.userDetail?.data?.name }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-4">
                        <div class="flex-grow-1 align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class=" ri-phone-fill  text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('mobile') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.userDetail?.data.mobile }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-4 mb-3">
                        <div class="flex-grow-1 align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class=" ri-mail-fill  text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('email') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.userDetail?.data.email }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                </div>
            </div>
             <div class="event-details" v-if="calendarOptions.event.driverDetail">
                <h5 class="card-title mb-3">{{ $t('driver_details') }}</h5>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="flex-grow-1  align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class=" ri-user-3-fill text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('name') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.driverDetail?.data.name }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-4">
                        <div class="flex-grow-1 align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class=" ri-phone-fill  text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('mobile') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.driverDetail?.data.mobile }}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                    <div class="col-sm-4">
                        <div class="flex-grow-1 align-items-center">
                            <div class="flex-shrink-0 me-3"> 
                                <div class="d-flex mb-2">
                                    <div class="flex-grow-1 d-flex align-items-center">
                                        <div class="flex-shrink-0 me-2"> <i class=" ri-mail-fill  text-muted fs-16"></i> </div>
                                        <div class="flex-grow-1">
                                            <h6 class="d-block fw-semibold mb-0 text-muted" id="event-start-date-tag">
                                                {{ $t('email') }}
                                            </h6>
                                        </div>
                                    </div>
                                </div>
                                <h6 class="fw-semibold ms-4">
                                    {{ calendarOptions.event.driverDetail?.data.email}}
                                </h6>
                            </div>
                        </div>                        
                    </div>
                </div>
            </div>
            <div class="hstack gap-2 justify-content-end">
                <BButton  id="btn-delete-event" @click="deleteEvent" class="btn btn-soft-danger">
                    <i class="ri-close-line align-bottom"></i> {{$t('delete')}}
                </BButton>
            </div>
        </BModal>


         <BModal v-model="showModal" :title="calendarOptions.event ? calendarOptions.event.request_number : 'Add Event'" body-class="p-4"
            header-class="p-3 bg-info-subtle" hide-footer class="v-modal-custom" centered="" size="xl">
            <form @submit.prevent="handleSubmit">
                <BRow>
                    <BCol cols="6">
                        <div class="row">
                            <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <label for="pickup" class="form-label">{{ $t("pickup_location") }}</label>
                                <a v-if="(stopovers.length < maxStopovers)" class="btn btn-primary mb-3" @click="addStopover(stopovers)">{{$t("add_stops")}}</a>
                            </div>
                            <div class="autocomplete-container">
                                    <div class="input-group">
                                        <input
                                        type="text"
                                        class="form-control"
                                        v-model="calendarOptions.event.pick_address"
                                        :placeholder="$t('enter_pickup')"
                                        id="pickup"
                                        autocomplete="off"
                                        @input="handleInput('pickup')"
                                        />
                                        <span class="input-group-text" style="background-color: none !important;" @click="PickDropMarker('pickup')" v-if="pickupSelected">
                                            <i class="ri-map-pin-line text-success"></i>
                                        </span>
                                    </div>
                                    <div v-if="pickSuggestions.length > 0" class="autocomplete-results">
                                        <div
                                        v-for="suggestion in pickSuggestions"
                                        :key="suggestion.placeId"
                                        class="autocomplete-item"
                                        @click="selectSuggestion(suggestion,'pickup')"
                                        >
                                        {{ suggestion.formattedAddress }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </BCol>
                    <BCol cols="12" v-if="stopovers.length > 0 ">
                        <div  v-for="(stop, index) in stopovers" :key="index">
                            <div class="mb-3">
                            <label :for="`stop-${index}`" class="form-label">{{$t("stop_location")}}</label>
                            <div class="d-flex align-items-center">
                            <div class="autocomplete-container col-8">
                                    <div class="input-group">
                                        <input
                                        type="text"
                                        class="form-control"
                                        v-model="stop.address"
                                        :placeholder="'Enter stop ' + (index + 1)"
                                        id="`stop-${index}`"
                                        autocomplete="off"
                                        @input="handleInput('stop',index)"
                                        />
                                    </div>
                                    <div v-if="stopSuggestions?.[index]?.length > 0" class="autocomplete-results">
                                        <div
                                        v-for="suggestion in stopSuggestions?.[index]"
                                        :key="suggestion.placeId"
                                        class="autocomplete-item"
                                        @click="selectSuggestion(suggestion,'stop',index)"
                                        >
                                        {{ suggestion.formattedAddress }}
                                        </div>
                                    </div>
                                </div>
                                <i class="bx bx-trash text-danger fs-22 btn" @click="removeStopover(index)"></i>
                            </div>
                            </div>
                        </div>
                    </BCol>
                    <BCol cols="6">
                        <div  v-if="!calendarOptions.event.is_rental">
                            <div class="mb-3">
                                <label for="drop" class="form-label">{{$t("drop_location")}}</label>
                                <div class="autocomplete-container">
                                    <div class="input-group">
                                    <input
                                        type="text"
                                        class="form-control"
                                        v-model="calendarOptions.event.drop_address"
                                        :placeholder="$t('enter_drop')"
                                        id="drop"
                                        autocomplete="off"
                                        @input="handleInput('drop')"
                                    />
                                    <span class="input-group-text" style="background-color: none !important;" @click="PickDropMarker('drop')" v-if="dropSelected">
                                        <i class="ri-map-pin-line text-danger"></i>
                                    </span>
                                    </div>
                                    <div v-if="dropSuggestions.length > 0" class="autocomplete-results">
                                    <div
                                        v-for="suggestion in dropSuggestions"
                                        :key="suggestion.placeId"
                                        class="autocomplete-item"
                                        @click="selectSuggestion(suggestion,'drop')"
                                    >
                                        {{ suggestion.formattedAddress }}
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </BCol>
                    <BCol cols="6">
                        <div class="mb-3">
                            <label for="dispatch-datepicker" class="form-label">{{$t("trip_start_time")}}</label>
                            <flat-pickr :placeholder="$t('select_date')" v-model="calendarOptions.event.trip_start_time" :config="dateTimeConfig"
                            class="form-control flatpickr-input" id="dispatch-datepicker"></flat-pickr>
                        </div>
                    </BCol>
                    <BCol cols="6">
                        <div class="mb-3" v-if="calendarOptions.event.is_round_trip">
                            <label for="dispatch-datepicker" class="form-label">{{$t("return_time")}}</label>
                            <flat-pickr :placeholder="$t('return_time')" v-model="calendarOptions.event.return_time" :config="dateTimeConfigReturnTime"
                            class="form-control flatpickr-input" id="dispatch-datepicker"></flat-pickr>
                        </div>
                    </BCol>
                    <BCol cols="6">
                        <div v-if="calendarOptions.event.is_rental" style="max-width:600px; overflow-x: auto;">
                            <div class="mb-3">
                                <label for="rental_package_id" class="form-label">{{$t("rental_package_id")}}</label>
                                <select id="rental_package_id" class="form-select" v-model="calendarOptions.event.rental_package_id" >
                                <option disabled value="">{{$t("choose_type")}}</option>
                                <option v-for="pack in packageTypes" :key="pack.id" :value="pack.id" >{{ pack.package_name }}</option>
                                </select>
                            </div>
                        </div>
                    </BCol>
                    <BCol cols="12">
                        
                        <div class="row">
                            <!-- Vehicle Select -->
                            <h4 v-if="vehicleTypes.length > 0 && !calendarOptions.event.driverDetail" class="card-title mb-3 flex-grow-1 mt-4">{{$t("select_vehicle")}}</h4>
                            <div v-if="vehicleTypes.length > 0 && !calendarOptions.event.driverDetail" style="max-width:600px; overflow-x: auto;" class="col-12">
                                <div class="mb-3">
                                    <div class="d-flex mt-5">
                                        <div v-for="(vehicleType, index) in vehicleTypes" :key="index" class="select-checkbox-btn text-center">
                                            <label :for="'vehicle_' + vehicleType.zone_type_id" class="select-checkbox-btn-wrapper">
                                                <input
                                                :id="'vehicle_' + vehicleType.zone_type_id"
                                                name="types"
                                                type="radio"
                                                :value="vehicleType.zone_type_id"
                                                v-model="calendarOptions.event.zone_type_id"
                                                class="select-checkbox-btn-input"
                                                />
                                                <span class="select-checkbox-btn-content">
                                                <a class="w-32 me-4 cursor-pointer">
                                                    <div class="text-center mt-2 ms-4 text-dark"><i class="bx bx-time-five mx-1"></i>{{ vehicleType.driver_time || '--' }}</div>
                                                    <div class="w-32 h-32 flex-none image-fit rounded-circle">
                                                    <img alt="" class="rounded-circle img-fluid" :src="vehicleType.vehicle_icon" />
                                                    </div>
                                                    <div class="text-center mt-2 amount text-dark">{{ vehicleType.currency }} {{ vehicleType.total }}</div>
                                                    <div class="text-center mt-2 text-dark">{{ vehicleType.name }}</div>
                                                </a>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <!-- Vehicle Select -->
                            <h4 v-if="rentalVehicleTypes.length > 0 && !calendarOptions.event.driverDetail" class="card-title mb-3 flex-grow-1 mt-4">{{$t("select_vehicle")}}</h4>
                            <div v-if="rentalVehicleTypes.length > 0 && !calendarOptions.event.driverDetail" style="max-width:600px; overflow-x: auto;" class="col-12">
                                <div class="mb-3">
                                    <div class="d-flex mt-5">
                                        <div v-for="(vehicleType, index) in rentalVehicleTypes" :key="index" class="select-checkbox-btn text-center">
                                            <label :for="'vehicle_' + vehicleType.zone_type_id" class="select-checkbox-btn-wrapper">
                                                <input
                                                :id="'vehicle_' + vehicleType.zone_type_id"
                                                name="types"
                                                type="radio"
                                                :value="vehicleType.zone_type_id"
                                                v-model="calendarOptions.event.vehicle_type"
                                                class="select-checkbox-btn-input"
                                                :checked="calendarOptions.event.vehicle_type_id"
                                                />
                                                <span class="select-checkbox-btn-content">
                                                    <a class="w-32 me-4 cursor-pointer">
                                                    <div class="text-center mt-2 ms-4 text-dark"><i class="bx bx-time-five mx-1"></i>{{ vehicleType.driver_time || '--' }}</div>
                                                    <div class="w-32 h-32 flex-none image-fit rounded-circle">
                                                    <img alt="" class="rounded-circle img-fluid" :src="vehicleType.icon" />
                                                    </div>
                                                    <div class="text-center mt-2 amount text-dark">{{ vehicleType.fare_amount }}</div>
                                                    <div class="text-center mt-2 text-dark">{{ vehicleType.name }}</div>
                                                    </a>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </BCol>
                </BRow>

                <div class="text-end pt-3">
                    <BLink href="#" class="btn btn-soft-primary" id="edit-event-btn" @click="cancelbtn" v-if="dataEdit">
                        {{$t('cancel')}}
                    </BLink>
                    <BButton type="submit" variant="success" class="ms-1" @click="makeBooking(calendarOptions.event.id)">
                        {{ dataEdit ? "Update Event" : "Add Event" }}
                    </BButton>
                </div>
            </form>
        </BModal>

        <BModal v-model="eventBooking" title="booking" hide-footer body-class="p-4" header-class="p-3 bg-info-subtle" class="v-modal-custom" centered size="md">
            <BCol cols="6">
                <div class="mb-3">
                    <label for="dispatch-datepicker" class="form-label">{{$t("select_date_time")}}</label>
                    <flat-pickr :placeholder="$t('select_date')" v-model="selectedDate" :config="dateTimeConfig"
                    class="form-control flatpickr-input" id="dispatch-datepicker"></flat-pickr>
                </div>
            </BCol>
            <div class="hstack gap-2 justify-content-end">
                <BButton  id="btn-delete-event" @click="clickDateBooking" class="btn btn-primary">{{$t('make_booking')}}
                </BButton>
            </div>
        </BModal>

        <div>
            <!-- Success Message -->
            <div v-if="successMessage" class="custom-alert alert alert-success alert-border-left fade show" data="alert"
                id="alertMsg">
                <div class="alert-content">
                    <i class="ri-notification-off-line me-3 align-middle"></i> <strong>Success</strong> - {{
                        successMessage }}
                    <button type="button" class="btn-close btn-close-success" @click="dismissMessage"
                        aria-label="Close Success Message"></button>
                </div>
            </div>

            <!-- Alert Message -->
            <div v-if="alertMessage" class="custom-alert alert alert-danger alert-border-left fade show" data="alert"
                id="alertMsg">
                <div class="alert-content">
                    <i class="ri-notification-off-line me-3 align-middle"></i> <strong>Alert</strong> - {{ alertMessage
                    }}
                    <button type="button" class="btn-close btn-close-danger" @click="dismissMessage"
                        aria-label="Close Alert Message"></button>
                </div>
            </div>
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
background-color:black;
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
.col-12.col-lg-6 {
  scroll-behavior: smooth;
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
/* end */
</style>
<style scoped>

.autocomplete-container {
  position: relative;
}

.autocomplete-results {
  border: 1px solid #ccc;
  max-height: 200px;
  overflow-y: auto;
  position: absolute;
  width: 100%;
  background-color: white;
  z-index: 1000;
}

.autocomplete-item {
  padding: 5px;
  cursor: pointer;
}
.stop-item::before {
  content: "• "; 
  color: #000;  
  margin-right: 5px;
}

</style>
