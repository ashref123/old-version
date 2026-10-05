<script>
import { Link, Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Pagination from "@/Components/Pagination.vue";
import Swal from "sweetalert2";
import { ref, watch, onMounted } from "vue";
import axios from "axios";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";
import search from "@/Components/widgets/search.vue";
import searchbar from "@/Components/widgets/searchbar.vue";
import getChartColorsArray from "@/common/getChartColorsArray";
import { useI18n } from 'vue-i18n';
import { BCard, BCardBody } from 'bootstrap-vue-next';


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
        Pagination,
        Multiselect,
        flatPickr,
        Link,
        search,
        searchbar,
        getChartColorsArray,
    },
    props: {
        successMessage: String,
        alertMessage: String,
        agent: Object,
        currency: Array,
        agent_date: String,
        agent_wallet: Object,
        completed_ride_count: Number,
        canceled_ride_count: Number,
        acceptance_rate: Number,
        cancellation_rate: Number,
        app_for:String,
        map_key: String,
        earnings_data: Object,
        default_lat:String,
        default_lng:String,
        trip_data: Object,
        firebaseSettings:Object,
        earningsChartData:Object,
        ongoing_rides: Object,
        types: Object,
        today: Object,
        overall: Object,

        tripsChartData: {
            type: Object,
            default: () => ({
                months: [],
                completed: [],
                cancelled: [],
            }),
        },


    },
    setup(props) {
        const map = ref(null);
        const { t } = useI18n();
        const selectedServiceLocations = ref([]);
        const selectedVehicleTypes = ref([]);
    
         
           const types = ref(props.types);
            const ongoing_rides = ref(props.ongoing_rides);
                const modalFilter = ref(false);

        // Calculate the maximum value from the earnings data
        const maxValue = Math.max(...(props.earningsChartData.values || []));
        
        // Set a margin by multiplying with a factor (e.g., 1.2) and round to 2 decimal places
        const maxYValue = (maxValue * 1.2).toFixed(2);
    // Earning Chart Data and Options
    const earning = ref([
      {
        name: t('earnings'),
        data: props.earningsChartData.values || [],
      },
    ]);
       const rightOffcanvas = ref(false);
           const filterData = () => {
             fetchRequestDatas();
            modalFilter.value = true;
           rightOffcanvas.value = false;
       };

          const clearFilter = () => {
            filter2.reset();
            fetchRequestDatas();
            modalFilter.value = false;
            rightOffcanvas.value = false;
        };

         


    const earningOptions = ref({
      chart: {
        height: 100,
        type: "area",
        toolbar: "false",
      },
      dataLabels: {
        enabled: false,
      },
      stroke: {
        curve: "smooth",
        width: 3,
      },
      xaxis: {
        categories: props.earningsChartData.months || [],
      },
      yaxis: {
        labels: {
          formatter: function (value) {
             return value.toFixed(2);
          },
        },
        tickAmount: 5,
        min: 0,
        max: Number(maxYValue), // Use the computed max value
      },
      colors: getChartColorsArray('[ "--vz-success"]'),
      fill: {
        opacity: 0.5,
        colors: ["#0AB39C", "#F06548"],
        type: "solid",
      },
    });

    
    
        const mobileFromUser = (user) => {
            if(props.app_for && props.app_for == "demo"){
                return "***********";
            }
            return user.mobile_number;
        }

        const emailFromUser = (user) => {
            if(props.app_for && props.app_for == "demo"){
                return "***********";
            }
            return user.email
        }

    
    
    const series = ref([
            {
                name: t('completed'),
                type: "bar",
                data: props.tripsChartData.completed || [],
            },
            {
                name: t('cancelled'),
                type: "bar",
                data: props.tripsChartData.cancelled || [],
            },
            {
                name: t('upcoming'),
                type: "bar",
                data: props.tripsChartData.upcoming || [],
            },
        ]);

        const tripOptions = ref({
            chart: {
                height: 374,
                type: "line",
                toolbar: {
                    show: false,
                },
            },
            stroke: {
                curve: "smooth",
                dashArray: [0, 3, 0],
                width: [0, 1, 0],
            },
            fill: {
                opacity: [1, 1, 1],
            },
            markers: {
                size: [0, 4, 0],
                strokeWidth: 2,
                hover: {
                    size: 4,
                },
            },
            xaxis: {
                categories: props.tripsChartData.months || [],
                axisTicks: {
                    show: false,
                },
                axisBorder: {
                    show: false,
                },
            },
            grid: {
                show: true,
                xaxis: {
                    lines: {
                        show: true,
                    },
                },
                yaxis: {
                    lines: {
                        show: false,
                    },
                },
                padding: {
                    top: 0,
                    right: -2,
                    bottom: 15,
                    left: 10,
                },
            },
            legend: {
                show: true,
                horizontalAlign: "center",
                offsetX: 0,
                offsetY: -5,
                markers: {
                    width: 9,
                    height: 9,
                    radius: 6,
                },
                itemMargin: {
                    horizontal: 10,
                    vertical: 0,
                },
            },
            plotOptions: {
                bar: {
                    columnWidth: "30%",
                    barHeight: "70%",
                },
            },
            colors: getChartColorsArray('["--vz-success", "--vz-danger", "--vz-warning"]'),
        });







 const initializeMap = async() => {
        
        const map = new google.maps.Map(document.getElementById('map'), {
            center: { lat: parseFloat(props.default_lat), lng: parseFloat(props.default_lng) },
            zoom: 15,
        });

        let driverMarker = null;

        const driversRef = firebase.database().ref('drivers/driver_' + props.agent.id);

        // Listen for location changes in Firebase
        driversRef.on('value', (snapshot) => {
            const driverData = snapshot.val();

            if (driverData && driverData.l && driverData.l[0] && driverData.l[1]) {
                const position = { lat: driverData.l[0], lng: driverData.l[1] };

                // Determine the correct icon URL based on driver's status
                let vehicleTypeIconUrl;
                    vehicleTypeIconUrl = `/image/map/${driverData.vehicle_type_icon}.png`;

                // If marker doesn't exist, create one
                if (!driverMarker) {
                    driverMarker = new google.maps.Marker({
                        position: position,
                        map: map,
                        icon: {
                            url: vehicleTypeIconUrl,
                            scaledSize: new google.maps.Size(30, 30),
                        },
                        title: 'Driver Location',
                    });
                } else {
                    // If marker already exists, update its position and icon
                    driverMarker.setPosition(position);
                    driverMarker.setIcon({
                        url: vehicleTypeIconUrl,
                        scaledSize: new google.maps.Size(30, 30),
                    });
                }

                // Optionally, center the map on the driver's new position
                map.setCenter(position);
            }
        });
    };


        const searchTerm1 = ref("");
        const searchTerm2 = ref("");
        const filter1 = useForm({ all: "", locked: "", limit: 15});
        const filter2 = useForm({
            ride_status : 'all',
            is_bid_ride : null,
            is_paid : null,
            payment_opt:null,
            limit:15 
        });
        const filter3 = useForm({ all: "", locked: "",limit:10 });
        const paginatorOption = ref({}); // Spread the results to make them reactive
        const withdrawalpaginatorOption = ref({});

        const filter4 = useForm({ all: "", locked: "",limit:10 });
        const planpaginatorOption = ref({});

        const ride_count = props.completed_ride_count;

        const cancel_ride_count = props.canceled_ride_count;




        const form = ref({
            amount: '',
            operation: 'add', // Default to 'add'
        });
        const validationMessage = ref('');
        const isAmountValid = ref(false);

        const validateForm = () => {
            if (!form.value.amount) {
                isAmountValid.value = false;
            } else {
                validationMessage.value = '';
                isAmountValid.value = true;
            }
        };

        const handleSubmit = async () => {
            validateForm();
            if (!isAmountValid.value) return;

            try {
                let formData = new FormData();
                for (let key in form.value) {
                    formData.append(key, form.value[key]);
                }

                let response = await axios.post(`/approved-drivers/wallet-add-amount/${props.agnet.id}`, formData);

                if (response.status === 200) {
                    props.successMessage = t('amount_adjusted_successfully');
                    form.value.amount = '';
                    form.value.operation = 'add'; // Reset form operation
                    router.get(`/approved-drivers/view-profile/${props.agent.id}`);
                } else {
                    props.alertMessage = t('failed_to_adjust_amount');
                }
            } catch (error) {
                console.error(t('error_adjusting_amount'), error);
                props.alertMessage = t('failed_to_adjust_amount');
            }
        };

        onMounted(async() => {
          
            var firebaseConfig = {
                apiKey: props.firebaseSettings['firebase_api_key'],
                authDomain: props.firebaseSettings['firebase_auth_domain'],
                databaseURL: props.firebaseSettings['firebase_database_url'],
                projectId: props.firebaseSettings['firebase_project_id'],
                storageBucket:  props.firebaseSettings['firebase_storage_bucket'],
                messagingSenderId: props.firebaseSettings['firebase_messaging_sender_id'],
                appId: props.firebaseSettings['firebase_app_id'],
            };
            if(firebase.apps.length == 0){
                firebase.initializeApp(firebaseConfig);
            }
            const mapKey = props.map_key;

           
        });
        watch(() => form.value.amount, validateForm);

        const results1 = ref([]);
        const paginator1 = ref({});
        const results2 = ref([]);
        const paginator2 = ref({});
        const requests = ref([]); // Spread the results to make them reactive
        const withdrawalResults = ref([]);
        const withdrawalPaginator = ref({});
        const ratingResults = ref([]);
        const ratingPaginator = ref({});
        const documentResults = ref([]);
        const planResults = ref([]);
        const planPaginator = ref({});

        const fetchDatas1 = async (page = 1) => {
            try {
                const params = filter1.data();
                params.page = page;
                const response = await axios.get(`/approved-drivers/wallet-history/list/${props.agent.id}`, { params });
                results1.value = response.data.results;
                paginator1.value = response.data.paginator;
                updatePaginatorOptions(paginator1.value.total);// Update paginator options dynamically

            } catch (error) {
                console.error(t('error_fetching_first_list_of_data'), error);
            }
        };
        const fetchRequestDatas = async (page = 1) => {
                const params = filter2.data();
                params.page = page;
                const response = await axios.get(`/agents/request/list/${props.agent.id}`, { params });
                requests.value = response.data.requests;
                paginator2.value = response.data.paginator;
                updatePaginatorOptions(paginator2.value.total);// Update paginator options dynamically

        };
        const updatePaginatorOptions = () => {
            paginatorOption.value = [15, 25, 50, 100, 200, 500]; // Default static options
        };
        const withdrawalPaginatorOptions = () => {
            withdrawalpaginatorOption.value = [10, 25, 50, 100, 200, 500]; // Default static options
        };
        // **Handle per-page changes**
        const changeRequestDatasPerPage = () => {
            fetchRequestDatas(); // Fetch new data
        };
        const changeWithdrawalDatasPerPage = () => {
            fetchWithdrawalDatas(); // Fetch new data
        };

        const fetchWithdrawalDatas = async (page = 1) => {
            try {
                const params = { 
                    agent_id: props.agent.id ,
                    page : page
                };
                params.limit = filter3.limit;
                const response = await axios.get(`/agents/withdrawal-request/${props.agent.id}`, { params });
                withdrawalResults.value = response.data.results;
                console.log("withdrawalResults.value",withdrawalResults.value);
                withdrawalPaginator.value = response.data.paginator;
                withdrawalPaginatorOptions(withdrawalPaginator.value.total);// Update paginator options dynamically

            } catch (error) {
                console.error(t('error_fetching_withdrawal_request_drivers'), error);
            }
        };
        const successMessage = ref(props.successMessage || '');
        const alertMessage = ref(props.alertMessage || '');

        const documentNameFront = ref(null);
        const documentNameBack = ref(null);
        const showModal = ref(false);
        const imageUrl = ref(null);
        const backImageUrl = ref(null);  // To hold the back image URL

        const disapproveModelShow = ref(false);
        const document_id = ref(null);

        const closeModal = () => {
            showModal.value = false;
            imageUrl.value = null;
            backImageUrl.value = null;
        };
        // Updated method to view document and show modal
        const viewDocument = (document) => {
            // Assuming 'document.image' contains the URL of the image
            imageUrl.value = document.image;
            backImageUrl.value = document.back_image; // Back image URL
            showModal.value = true;
            documentNameFront.value = document.document_name_front ?? t("document"); 
            documentNameBack.value = document.document_name_back ?? t("document");
        };

        const reasonform = useForm({
            reason: '',
        });

        const handlePageChanged1 = async (page) => {
            fetchDatas1(page);
        };

        const handlePageChanged2 = async (page) => {
            fetchRequestDatas(page);
        };

        const handleWithdrawalChanged = async (page) => {
            fetchWithdrawalDatas(page);
        };
        const bankInfo = ref({});
        const walletBalance = ref({});

        const fetchWalletData = async () => {
            try {
            const res = await axios.get(`/agents/payment-method/${props.agent.id}`);
            console.log("res",res);
            if (res.data.success) {
                bankInfo.value =  res.data.bank_info_exists;
                walletBalance.value = res.data.wallet_balance;
            }
            } catch (error) {
            console.error('Error fetching wallet data:', error);
            Swal.fire('Error', 'Failed to load wallet data', 'error');
            }
        };


        return {
            form,
            validationMessage,
            isAmountValid,
            handleSubmit,
            searchTerm1,
            searchTerm2,
            mobileFromUser,
            emailFromUser,
            results1,
            paginator1,
            results2,
            paginator2,
            withdrawalResults,
            withdrawalPaginator,
            ratingResults,
            ratingPaginator,
            documentResults,
            successMessage,
            alertMessage,
            fetchDatas1,
            fetchRequestDatas,
            fetchWithdrawalDatas,
            handlePageChanged1,
            handlePageChanged2,
            handleWithdrawalChanged,
            ride_count,
            cancel_ride_count,
            map,
            selectedServiceLocations,
            selectedVehicleTypes,
            requests,
            earning,
            earningOptions,
            series,
            tripOptions,
            filter4,
            viewDocument,
            closeModal,
            showModal, // Export ref to use in the template
            imageUrl,  // Export ref to use in the template
            backImageUrl, // Export ref for back image
            documentNameFront,
            documentNameBack,
            changeRequestDatasPerPage,
            filter2,
            paginatorOption,
            filter1,
            changeWithdrawalDatasPerPage,
            filter3,
            withdrawalpaginatorOption,
            rightOffcanvas,
            types,
            ongoing_rides,
            modalFilter,
            filterData,
            clearFilter,
            reasonform,
            disapproveModelShow,
            document_id,
            bankInfo,
            fetchWalletData,
            walletBalance

        };
    },
    mounted() {
        this.fetchRequestDatas();
        this.fetchWithdrawalDatas();
        this.fetchWalletData();
    },
};
</script>


<template>
    <Layout>

        <Head title="Agent Profile" />
        <PageHeader :title="$t('agent_profile')" :pageTitle="$t('agent_profile')" pageLink="/agents"/>
        <BRow>
            <BCol lg="12">
                <BCard no-body id="tasksList">

                    <BCardHeader class="border-0">
                        <div class="row">
                            <div class="col-sm-4 mt-3 profile-border">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <img class="rounded-circle avatar-xl" alt="200x200" :src="agent.profile_picture">
                                    </div>
                                    <div class="ms-2">
                                        <h5>{{agent.first_name}}</h5> 
                                        <p>{{agent.service_location_name}}</p> 
                                    </div>
                                 </div>                                
                            </div>
                            <div class="col-sm-3 mt-4 profile-border">                               
                                <div class=" d-flex align-items-center ">
                                    <i class=" ri-phone-line" style="font-size:20px"></i> &nbsp;&nbsp;
                                    <span>{{agent.mobile}}</span>
                                </div>                                
                                <div class=" d-flex align-items-center ">
                                    <i class="ri-mail-line" style="font-size:20px"></i> &nbsp;&nbsp;
                                    <span>{{agent.email}}</span>
                                </div>  
                                <div class=" d-flex align-items-center ">
                                    <i class="  ri-logout-box-r-line" style="font-size:20px"></i> &nbsp;&nbsp;
                                    <span>{{agent_date}}</span>
                                </div>  
                            </div>
                        </div>
                        <div class="border-bottom mt-4"></div>
                        <div>
                            <!-- Nav tabs -->
                                <ul class="nav nav-tabs  mt-4" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#agent-profile" role="tab" aria-selected="false">
                                            {{$t("agent_profile")}}
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link " data-bs-toggle="tab" href="#request-list" role="tab" aria-selected="false">
                                            {{$t("request_list")}}
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#withdrawal-history" role="tab" aria-selected="false">
                                            {{$t("withdrawal_history")}}
                                        </a>
                                    </li>
                                </ul>
                        </div>

                    </BCardHeader>
                </BCard>
                        <!-- Tab panes -->
                        <div class="tab-content  text-muted">
                            <div class="tab-pane active  p-3" id="agent-profile" role="tabpanel">                                            
                                <BCard>
                                    <BCardBody>
                                        <h5 class="mb-4 mt-4">{{$t("general_report")}}</h5>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="row  row-cols-lg-4 row-cols-1">
                                                        <div class="col">
                                                            <div class="card card-body border  card-hover">
                                                                <div class="d-flex mb-4 align-items-center">
                                                                    <div>
                                                                        <div class="avatar-sm flex-shrink-0">
                                                                            <span class="avatar-title bg-success-subtle rounded-circle fs-2">
                                                                                <i class=" bx bx-car text-success icon-lg"></i>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <h1 class="mb-1"> {{ today.today_total_trip }} </h1>
                                                                <h5 class="card-text text-muted">{{$t("today_trips")}}</h5>            
                                                            </div>
                                                        </div><!-- end col -->
                                                        <div class="col">
                                                            <div class="card card-body border  card-hover">
                                                                <div class="d-flex mb-4 align-items-center">
                                                                    <div>
                                                                        <div class="avatar-sm flex-shrink-0">
                                                                            <span class="avatar-title bg-success-subtle rounded-circle fs-2">
                                                                                <i class="bx bx-money text-success icon-lg"></i>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <h1 class="mb-1"> {{ today.earnings.total }}</h1>
                                                                <h5 class="card-text text-muted">{{$t("today_earnings")}}</h5>        
                                                            </div>
                                                        </div><!-- end col -->
                                                        <div class="col">
                                                            <div class="card card-body border  card-hover">
                                                                <div class="d-flex mb-4 align-items-center">
                                                                    <div>
                                                                        <div class="avatar-sm flex-shrink-0">
                                                                            <span class="avatar-title bg-primary-subtle rounded-circle fs-2">
                                                                                <i class=" bx bx-car text-primary icon-lg"></i>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <h1 class="mb-1"> {{ overall.overall_total_trip }}</h1>
                                                                <h5 class="card-text text-muted">{{$t('total_trips')}}</h5>         
                                                            </div>
                                                        </div><!-- end col -->
                                                        <div class="col">
                                                            <div class="card card-body border  card-hover">
                                                                <div class="d-flex mb-4 align-items-center">
                                                                    <div>
                                                                        <div class="avatar-sm flex-shrink-0">
                                                                            <span class="avatar-title bg-primary-subtle rounded-circle fs-2">
                                                                                <i class="bx bx-money text-primary icon-lg"></i>
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <h1 class="mb-1"> {{ overall.earnings.total }}</h1>
                                                                <h5 class="card-text text-muted">{{$t('total_earnings')}}</h5>      
                                                            </div>
                                                        </div><!-- end col -->
                                                        
                                                    </div><!-- end row -->
                                                </div><!-- end col -->
                                            </div><!-- end row --> 

                                            <h5 class="mb-4 mt-4">{{$t('earnings')}}</h5>
                                            <div class="row">
                                                <div class="col-sm-6 col-md-12 col-lg-12 col-xl-7">
                                                    <apexchart
                                                        class="apex-charts"
                                                        height="450"
                                                        dir="ltr"
                                                        :series="earning"
                                                        :options="earningOptions"
                                                    ></apexchart>           
                                                </div>
                                                <div class="col-sm-12 col-md-12 col-lg-12 col-xl-6">
                                                    <h5 class="mb-4 mt-4">{{$t("trips")}}</h5>
                                                    <apexchart
                                                        class="apex-charts"
                                                        height="350"
                                                        dir="ltr"
                                                        :series="series"
                                                        :options="tripOptions"
                                                    ></apexchart>                                                    
                                                </div>
                                            </div>
                                    </BCardBody>
                                </BCard>
                            </div>
                                        
                            <div class="tab-pane  p-3" id="request-list" role="tabpanel">
                                <BCard>
                                    <BCardBody>
                                        <div class="row  row-cols-lg-2 row-cols-1">
                                            <div class="col-lg-3 col-md-6 col-sm-12">
                                                <div class="card card-body border card-hover">
                                                    <div class="d-flex mb-4 align-items-center">
                                                        <div>
                                                            <div class="avatar-sm flex-shrink-0">
                                                                <span class="avatar-title bg-success-subtle rounded-circle fs-2">
                                                                    <i class=" bx bx-car text-success icon-lg"></i>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex">
                                                        <h3 class="mb-1">{{ overall.completed }}</h3>
                                                    </div>                                                                
                                                    <h5 class="card-text text-muted">{{$t("completed_rides")}}</h5>        
                                                </div>
                                            </div><!-- end col -->
                                             <div class="col-lg-3 col-md-6 col-sm-12">
                                                <div class="card card-body border card-hover">
                                                    <div class="d-flex mb-4 align-items-center">
                                                        <div>
                                                            <div class="avatar-sm flex-shrink-0">
                                                                <span class="avatar-title bg-warning-subtle rounded-circle fs-2">
                                                                    <i class=" bx bx-car text-warning icon-lg"></i>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex">
                                                        <h3 class="mb-1">{{ overall.scheduled}}</h3>
                                                    </div>
                                                    <h5 class="card-text text-muted">{{$t("upcoming_rides")}}</h5>    
                                                </div>
                                            </div><!-- end col -->
                                            <div class="col-lg-3 col-md-6 col-sm-12">
                                                <div class="card card-body border card-hover">
                                                    <div class="d-flex mb-4 align-items-center">
                                                        <div>
                                                            <div class="avatar-sm flex-shrink-0">
                                                                <span class="avatar-title bg-danger-subtle rounded-circle fs-2">
                                                                    <i class=" bx bx-car text-danger icon-lg"></i>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex">
                                                        <h3 class="mb-1">{{ overall.cancelled}}</h3>
                                                    </div>
                                                    <h5 class="card-text text-muted">{{$t("cancelled_rides")}}</h5>     
                                                </div>
                                            </div><!-- end col -->
                                                                                                    
                                        </div><!-- end row -->
                                        
                                          
                                            <BRow class="g-2">
                                        <BCol md="3" class="mb-3">
                                            <div class="d-flex align-items-center mt-3">
                                                <label class="me-2 text-muted">{{$t("show")}}</label>
                                                <select v-model="filter2.limit" @change="changeRequestDatasPerPage" class="form-select form-select-sm w-auto">
                                                <option v-for="option in paginatorOption" :key="option" :value="option">
                                                    {{ option }}
                                                </option>
                                                </select>
                                                <label class="ms-2 text-muted">{{$t("entries")}}</label>
                                            </div>
                                        </BCol>
                                      <BCol md="auto" class="ms-auto">
                                                <div class="d-flex align-items-center gap-2">
                                 
                                            <BButton variant="danger" @click="rightOffcanvas = true">
                                              <i class="ri-filter-2-line me-1 align-bottom"></i>  {{$t("filters")}}
                                              </BButton>
                                             </div>
                                       </BCol>

                                </BRow>
                                        <div class="table-responsive">
                                            <table class="table align-middle position-relative table-nowrap">
                                                <thead class="table-active">
                                                    <tr>
                                                        
                                                        <th scope="col">{{ $t("request_id") }}</th>
                                                        <th scope="col">{{ $t("date") }}</th>
                                                        <th scope="col">{{ $t("user_name") }}</th>
                                                        <th scope="col">{{ $t("driver_name") }}</th>
                                                        <th scope="col">{{ $t("trip_Status") }}</th>
                                                        <th scope="col">{{ $t("paid") }}</th>
                                                        <th scope="col">{{$t("payment_option")}}</th>
                                                        <th scope="col">{{$t("agent_commission")}}</th>
                                                       
                                                    </tr>
                                                </thead>
                                                <tbody v-if="requests.length > 0">
                                                    <tr v-for="(request, index) in requests" :key="index">
                                                      
                                                        <td>{{ request.request_number }}</td>
                                                        <td>{{ request.converted_created_at }}</td>
                                                        <td>{{ request.user_name }}</td>
                                                        <td>{{ request.driver_name }}</td>
                                                        <td>{{ request.trip_status }}</td>
                                                        <td>{{ request.trip_payment }}</td>
                                                       
                                                        <td>
                                                            <BBadge :class="{
                                                                'text-uppercase':true,
                                                                'text-bg-success': request.is_paid,
                                                                'text-bg-danger': !request.is_paid,
                                                                }">{{ request.payment_opt == 1 ? 'Cash' : (request.payment_opt == 2 ? 'Wallet' : 'Card') }} </BBadge>
                                                        </td> 
                                                         <td v-if="request.request_bill?.agent_commision">{{ request.request_bill?.agent_commision }}</td> 
                                                         <td v-else>-</td> 
                                                    </tr>
                                                </tbody>
                                                <tbody v-else>
                                                    <tr>
                                                        <td colspan="7" class="text-center">
                                                            <img src="@assets/images/search-file.gif" alt="Loading..." style="width:100px" />
                                                            <h5>{{$t("no_data_found")}}</h5>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <Pagination :paginator="paginator2" @page-changed="handlePageChanged2" />
                                        </div>
                                    </BCardBody>
                                </BCard>
                                <BOffcanvas v-model="rightOffcanvas" placement="end" :title="$t('filters')" header-class="bg-light"
                                body-class="p-0 overflow-hidden" footer-class="border-top p-3 text-center">
                                <BFrom action="" class="d-flex flex-column justify-content-end h-100">
                                    <div class="offcanvas-body">
                                        <div class="mb-4">
                                            <label for="datepicker-range" class="form-label text-muted text-uppercase fw-semibold mb-3"> {{$t("status")}}</label>
                                            <select v-model="filter2.ride_status" class="form-select">
                                                <option value="all">{{$t("all")}}</option>
                                                <option value="completed">{{$t("completed")}}</option>
                                                <option value="cancelled">{{$t("cancelled")}}</option>
                                                <option value="upcoming">{{$t("upcoming")}}</option>
                                                <option value="ontrip">{{$t("on_trip")}}</option>
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label for="payment-status-select" class="form-label text-muted text-uppercase fw-semibold mb-3"> {{$t("payment_status")}}</label>
                                            <div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="payment_status"
                                                        id="not_paid" value=1 v-model="filter2.is_paid">
                                                    <label class="form-check-label" for="not_paid"> {{$t("paid")}}</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="payment_status"
                                                        id="paid" value=0 v-model="filter2.is_paid">
                                                    <label class="form-check-label" for="paid"> {{$t("not_paid")}}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label for="dispatch-type-select" class="form-label text-muted text-uppercase fw-semibold mb-3"> {{$t("ride_type")}}</label>
                                            <div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="dispatch-type"
                                                        id="normal" value=0 v-model="filter2.is_bid_ride">
                                                    <label class="form-check-label" for="normal"> {{$t("regural")}}</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="dispatch-type"
                                                        id="bidding" value=1 v-model="filter2.is_bid_ride">
                                                    <label class="form-check-label" for="bidding"> {{$t("bidding")}}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label for="payment-status-select" class="form-label text-muted text-uppercase fw-semibold mb-3"> {{$t("payment_option")}}</label>
                                            <div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="payment_opt"
                                                        id="card" value=0 v-model="filter2.payment_opt">
                                                    <label class="form-check-label" for="card"> {{$t("card")}}</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="payment_opt"
                                                        id="cash" value=1 v-model="filter2.payment_opt">
                                                    <label class="form-check-label" for="cash"> {{$t("cash")}}</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="payment_opt"
                                                        id="wallet" value=2 v-model="filter2.payment_opt">
                                                    <label class="form-check-label" for="wallet"> {{$t("wallet")}}</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                        </div>
                                    </div>
                                    <!--end offcanvas-body-->
                                    <div class="offcanvas-footer border-top p-3 text-center hstack gap-2">
                                        <BButton variant="light" @click="clearFilter"class="w-100"> {{$t("clear_filter")}}</BButton>
                                        <BButton type="submit" @click="filterData" variant="success" class="w-100">
                                            {{$t("apply")}}
                                        </BButton>
                                    </div>
                                    <!--end offcanvas-footer-->
                                </BFrom>
                                </BOffcanvas>
                            </div>

                            <div class="tab-pane  p-3" id="withdrawal-history" role="tabpanel">
                                <BCard>
                                    <BCardBody>
                                        <div class="col-sm-3">
                                <div class="card border card-border-primary">
                                    <div class="card-body">
                                        <h5>{{$t("balance_amount")}}</h5>
                                        <div class="row mt-5">
                                            <div class="col-6">
                                                <div class="avatar-sm flex-shrink-0">
                                                    <span class="avatar-title bg-success-subtle rounded-circle fs-2">
                                                        <i class="bx bx-money text-success icon-lg"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <h5  style="text-align:end;">{{walletBalance}}</h5>                                                
                                            </div>    
                                        </div>                                         
                                    </div>
                                </div>                                        
                            </div>
                                        <div v-for="(method, methodIndex) in bankInfo" :key="methodIndex" class="col-sm-12">
                                            <div class="card border card-border-primary">
                                                <div class="card-body">
                                                    <h5 class="mb-3">{{ $t(method.method_name) }}</h5>
                                                    <div v-if="method.fields.length > 0">
                                                        <div class="d-flex" v-for="(field, fieldIndex) in method.fields" :key="fieldIndex">
                                                            <i 
                                                                v-if="field.value" 
                                                                class="ri-checkbox-circle-fill text-success">
                                                            </i>
                                                            <h6 class="ms-2">
                                                                {{ $t(field.field_name) }}: 
                                                                <span>{{ field.value || $t("No data available") }}</span>
                                                            </h6>
                                                        </div>
                                                    </div>
                                                    <div v-else>
                                                        <p>{{ $t("No fields available for this method") }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <BCol md="3" class="mb-3">
                                            <div class="d-flex align-items-center mt-3">
                                                <label class="me-2 text-muted">{{$t("show")}}</label>
                                                <select v-model="filter3.limit" @change="changeWithdrawalDatasPerPage" class="form-select form-select-sm w-auto">
                                                <option v-for="option in withdrawalpaginatorOption" :key="option" :value="option">
                                                    {{ option }}
                                                </option>
                                                </select>
                                                <label class="ms-2 text-muted">{{$t("entries")}}</label>
                                            </div>
                                        </BCol>
                                        <div class="table-responsive">
                                            <table class="table align-middle position-relative table-nowrap">
                                                <thead class="table-active">
                                                    <tr>
                                                        <th scope="col">{{$t("date")}}</th>
                                                        <th scope="col">{{$t("name")}}</th>
                                                        <th scope="col">{{$t("mobile_number")}}</th>
                                                        <th scope="col">{{$t("requested_amount")}}</th>
                                                        <th scope="col">{{$t("status")}}</th>
                                                      
                                                    </tr>
                                                </thead>
                                                <tbody v-if="withdrawalResults.length > 0">
                                                    <tr v-for="(result, index) in withdrawalResults" :key="index">
                                                        <td> {{ result.created_at }}</td>
                                                        <td> {{ result.agent_name }}</td>
                                                        <td>{{ result.agent_mobile }}</td>
                                                        <td>  {{ result.requested_currency }}{{ result.requested_amount }}</td>

                                                        <td>
                                                            <template v-if="result.payment_status == 'approved'">
                                                                <BBadge variant="success" class="text-uppercase">{{$t("approved")}}</BBadge>
                                                            </template>
                                                            <template v-else-if="result.payment_status == 'requested'">
                                                                <BBadge variant="warning" class="text-uppercase">{{$t("requested")}}</BBadge>
                                                            </template>
                                                            <template v-else>
                                                                <BBadge variant="danger" class="text-uppercase">{{$t('declined')}}</BBadge>
                                                            </template>
                                                        </td>
                                                       
                                                    </tr>
                                                </tbody>
                                                <tbody v-else>
                                                    <tr>
                                                        <td colspan="7" class="text-center">
                                                            <img src="@assets/images/search-file.gif" alt="Loading..." style="width:100px" />
                                                            <h5>{{$t("no_data_found")}}</h5>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <Pagination :paginator="withdrawalPaginator" @page-changed="handleWithdrawalChanged" />
                                        </div>
                                    </BCardBody>
                                </BCard>
                                
                            </div>

                        </div>  
                    </BCol>
                        </BRow>
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
.rtl .custom-alert {
    max-width: 600px;
    float: right;
    position: fixed;
    top: 100px;
    right: 80px;
}

.card-hover:hover{
    box-shadow: 0 5px 15px;
    transition: box-shadow 0.3s ease-in-out;
}
.ltr .profile-border{
    border-right:1px solid #e9ebec;
}
.rtl .profile-border{
    border-left:1px solid #e9ebec;
}


@media only screen and (max-width: 426px) {
    .profile-border{
        border-right:0px;
    }
}
</style>