<script>
import { Link, Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/DispatcherPro/dispatchPromain.vue";
import PageHeader from "@/Components/page-header.vue";
import Pagination from "@/Components/Pagination.vue";
import Swal from "sweetalert2";
import { ref, watch, computed ,onMounted } from "vue";
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


    },
    methods: {
    
    navigateToInvoice(invoiceType, id) {
        // Navigate to the invoice Blade file
        const url = `/dispatcher-pro/rides_request/download-invoice/${id}?invoice_type=${invoiceType}`;
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
        const activeTab = ref('request_pending');
        const filter = useForm({
            converted_as_ride : 'request_pending',
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
        const results = ref([]); // Spread the results to make them reactive
        const paginator = ref({}); // Spread the results to make them reactive
        const modalShow = ref(false);
        const modalFilter = ref(false);
        const deleteItemId = ref(null);
        const successMessage = ref(props.successMessage || '');
        const alertMessage = ref(props.alertMessage || '');
        const paginatorOption = ref({}); // Spread the results to make them reactive
        const zoneList = ref([]); // Spread the results to make them reactive

        const dismissMessage = () => {
            successMessage.value = "";
            alertMessage.value = "";
        };

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
        filter.converted_as_ride = newTab;
        console.log("filter.converted_as_ride",filter.converted_as_ride);
        fetchDatas(); // Fetch data for the selected tab
    });


        onMounted( async ()=> {
            fetchDatas();
        });
        const closeModal = () => {
            modalShow.value = false;
        };
        const cancelData = async (dataId) => {
            try {
                console.log("dataId",dataId);
                const response = await axios.get(`/dispatcher-pro/request_enquiries/delete/${dataId}`);
                results.value = response.data.request;
                router.get('/dispatcher-pro/request_enquiries')
                modalShow.value = false;
                Swal.fire(t('success'), t('trip_cancelled_successfully'), 'success');
            } catch (error) {
                console.log(error);
                Swal.fire(t('error'), t('failed_to_cancel_trip'), 'error');
            }
        };

        const cancelModal = async (itemId) => {
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
                        await cancelData(itemId);
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
                const response = await axios.get(`/dispatcher-pro/request_enquiries/list`, { params });
                results.value = response.data.results;
                console.log("results.value",results.value);
                paginator.value = response.data.paginator;
                updatePaginatorOptions(paginator.value.total);// Update paginator options dynamically
                modalFilter.value = false;
            } catch (error) {
                console.error(t('error_fetching_requests'), error);
            }
        };
        const updatePaginatorOptions = () => {
            paginatorOption.value = [10, 25, 50, 100,200,500]; // Default static options
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
            router.get(`/dispatcher-pro/rides_request/view/${result.id}`); 
        };
        const makeBooking = async (result) =>{
            router.get("/dispatcher-pro/bookride", { 
                pick_lat: result.pick_lat,
                pick_lng: result.pick_lng,
                drop_lat: result.drop_lat,
                drop_lng: result.drop_lng,
                pick_address: result.pick_address,
                drop_address: result.drop_address,
                mobile: result.user_detail.mobile,
                name: result.user_detail.name
            });
        }

        return {
            results,
            modalShow,
            deleteItemId,
            successMessage,
            alertMessage,
            filterData,
            cancelModal,
            closeModal,
            cancelData,
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
            makeBooking,
        };
    },
    computed: {
    ...mapGetters(['permissions']),
  },
};
</script>

<template>
    <Layout>

        <Head title="Rides Request Enquires" />
        <div class="font">
            
        <PageHeader :title="$t('index')" :pageTitle="$t('ride_enquires')" />
        <BRow>
            <BCol lg="12">
                <BCard no-body id="tasksList">

                    <BCardHeader class="border-0">
                        <BRow class="g-2">
                            <BCol md="2">
                                <div class="d-flex align-items-center mt-3">
                                    <label class="me-2 text-muted">{{$t("show")}}</label>
                                    <select v-model="filter.limit" @change="changeEntriesPerPage" class="form-select form-select-sm w-auto">
                                    <option v-for="option in paginatorOption" :key="option" :value="option">
                                        {{ option }}
                                    </option>
                                    </select>
                                    <label class="ms-2 text-muted">{{$t("entries")}}</label>
                                </div>
                            </BCol>
                            <BCol md="6">
                                <div class="card-header">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <ul class="nav nav-tabs-custom card-header-tabs border-bottom-0" role="tablist">
                                                    <li class="nav-item" role="presentation">
                                                        <a class="nav-link fw-semibold btn" :class="{ active: activeTab === 'request_pending' }" 
                                                        @click="activeTab = 'request_pending'" role="tab" aria-selected="false">
                                                            {{$t("request_pending")}} 
                                                        </a>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <a class="nav-link fw-semibold btn" :class="{ active: activeTab === 'converted_as_ride' }" 
                                                        @click="activeTab = 'converted_as_ride'" role="tab" aria-selected="false">
                                                            {{$t("converted_as_ride")}}
                                                        </a>
                                                    </li>
                                                    <li class="nav-item" role="presentation">
                                                        <a class="nav-link fw-semibold btn" :class="{ active: activeTab === 'cancelled' }" 
                                                        @click="activeTab = 'cancelled'" role="tab" aria-selected="false">
                                                            {{$t("cancelled")}}
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                            </BCol>
                            <BCol md="auto" class="ms-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <searchbar @search="fetchSearch"></searchbar>
                                </div>
                            </BCol>
                        </BRow>
                    </BCardHeader>
                    <BCardBody class="border border-dashed border-end-0 border-start-0">
                        <div class="table-responsive">
                            <table class="table align-middle position-relative table-nowrap">
                                <thead class="table-active">
                                    <tr>
                                        <th scope="col"> {{$t("pick_address")}}</th>
                                        <th scope="col"> {{$t("drop_address")}}</th>
                                        <th scope="col"> {{$t("user_name")}}</th>
                                        <th scope="col"> {{$t("mobile")}}</th>
                                        <th scope="col"> {{$t("status")}}</th>
                                        <th scope="col"> {{$t("action")}}</th>
                                    </tr>
                                </thead>
                                <tbody v-if="results.length > 0">
                                    <tr v-for="(result, index) in results" :key="index">
                                        <td class="pick-address">{{ result.pick_address}}</td> 
                                        <td class="drop-address">{{ result.drop_address }}</td> 
                                        <td>{{ result.user_detail ? result.user_detail.name : '----' }}</td>       
                                        <td>{{ result.user_detail ? result.user_detail.mobile_number: '----' }}</td>
                                         <td>
                                            <template v-if="result.is_cancelled == 1 && result.converted_as_ride == 0">
                                                <BBadge variant="danger" class="text-uppercase">{{$t("cancelled")}}</BBadge>
                                            </template>
                                             <template v-else-if="result.is_cancelled == 0 && result.converted_as_ride">
                                                <BBadge variant="success" class="text-uppercase">{{$t("converted_as_ride")}}</BBadge>
                                            </template>                                            
                                            <template v-else>
                                                <BBadge variant="warning" class="text-uppercase">{{$t("request_pending")}}</BBadge>
                                            </template> 
                                        </td>  
                                        <td>                                           
                                            <BButton class="btn btn-soft-info btn-sm m-2" size="sm" type="button" v-if="!result.converted_as_ride &&  !result.is_cancelled">
                                            <div class="dropdown">
                                                <a class="text-reset" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <span class="text-muted fs-18"><i class="mdi mdi-dots-vertical"></i></span>
                                                </a>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="#"  @click="makeBooking(result)" v-if="permissions.includes('dispatcher-request-enquiry-booking')">
                                                        {{$t("converted_as_ride")}}
                                                    </a>
                                                     <a class="dropdown-item" href="#" @click="cancelModal(result.id)" v-if="permissions.includes('dispatcher-request-enquiry-cancel')">
                                                        {{$t("cancel")}}
                                                    </a>
                                                </div>
                                            </div>
                                            </BButton>
                                            <div v-else>-</div>
                                        </td>
                                     </tr>
                                </tbody>
                                <tbody v-else>
                                    <tr>
                                        <td colspan="10" class="text-center">
                                            <img src="@assets/images/search-file.gif" alt="Loading..." style="width:100px" />
                                            <h5> {{$t("no_data_found")}}</h5>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                     </BCardBody>
                </BCard>
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

        <!-- Pagination -->
        <Pagination :paginator="paginator" @page-changed="handlePageChanged" />
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
.table {
  border-collapse: collapse;
  width: 100%;
}

.table td, .table th {
  padding-right: 8px;
}

.pick-address,
.drop-address {
  max-width: 350px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
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
