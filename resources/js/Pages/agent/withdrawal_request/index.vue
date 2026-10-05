<script>
import { Link, Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/Agent/agentmain.vue";
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
import { refineEventDef } from '@fullcalendar/core/internal';

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


    },
    methods: {
  },
    setup(props) {
        const { t } = useI18n();
        const searchTerm = ref("");
        const activeTab = ref('request_pending');
        const filter = useForm({
            service_location_id: props.service_location_id,
            limit:10,
        });
        const results = ref([]); // Spread the results to make them reactive
        const paginator = ref({}); // Spread the results to make them reactive
        const modalShow = ref(false);
        const modalFilter = ref(false);
        const deleteItemId = ref(null);
        const successMessage = ref(props.successMessage || '');
        const alertMessage = ref(props.alertMessage || '');
        const paginatorOption = ref({}); // Spread the results to make them reactive

        const dismissMessage = () => {
            successMessage.value = "";
            alertMessage.value = "";
        };
        const payment_method =  ref(false);

        const rightOffcanvas = ref(false);


        onMounted( async ()=> {
            fetchDatas();
            fetchWalletData();
        });

        const fetchDatas = async (page = 1) => {
                
            try {
                const params = filter.data();
                if(searchTerm.value.length > 0){
                    params.search = searchTerm.value;
                }
                params.page = page;
                const response = await axios.get(`/agent/withdrawal-request/list`, { params });
                results.value = response.data.results;
                console.log("results.value",results.value);
                paginator.value = response.data.paginator;
            } catch (error) {
                console.error(t('error_fetching_requests'), error);
            }
        };
        const methods = ref([]);        
        const payment = ref();
        const fetchMethods = async() =>{
            try {
                const response = await fetch('/agent/withdrawal-request/payment-method');
                const json = await response.json();
                if (json.success) {
                methods.value = json.data;
                console.log(" this.methods", methods.value);

                    const savedMethod = methods.value.find(m => 
                        m.driver_bank_info?.data?.length > 0
                    );

                    if (savedMethod) {
                        payment.value = savedMethod;      // PRE-SELECT
                        selectMethod(savedMethod);        // FILL FORM
                    }
                }
            } catch (error) {
                console.error('Error fetching methods:', error);
            }
        }
                   
            const selectedMethod=  ref(null);
            const formValues = ref({});
            const showWithdrawalModal = ref(false);
            const withdrawalAmount = ref('');

            watch(showWithdrawalModal, (isOpen) => {
            if (!isOpen) {
                withdrawalAmount.value = '';
            }
            });
        
        const selectMethod = (method) =>{
            selectedMethod.value = method;
            formValues.value = {};

            const savedInfos = method.driver_bank_info?.data || [];

            method.fields.data.forEach(field => {
                const savedInfo = savedInfos.find(info => info.field_id === field.id);
                formValues.value[field.id] = savedInfo ? savedInfo.value : '';
            });
        }

        const  submit = async() =>{
            if (!selectedMethod.value) return;

            const formData = new FormData();
            formData.append('method_id', selectedMethod.value.id);

            for (const fieldId in formValues.value) {
                const field = selectedMethod.value.fields.data.find(f => f.id == fieldId);
                if (field) {
                formData.append(field.input_field_name, formValues.value[fieldId]);
                }
            }

            try {
                const response = await axios.post('/agent/withdrawal-request/update/bankinfo', formData);

                if (response.data.success) {
                    payment_method.value = false;
                    selectedMethod.value = null;
                    formValues.value = {};

                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Bank info updated successfully!',
                    });
                    router.get('/agent/withdrawal-request');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.data.message || 'Something went wrong!',
                    });
                }
            } catch (error) {
                Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.response?.data?.message || 'Something went wrong!',
                });
            }
        }

        const submitWithdrawal = async () => {
            if (!withdrawalAmount.value || withdrawalAmount.value <= 0) {
                Swal.fire({
                icon: 'warning',
                title: 'Invalid Amount',
                text: 'Please enter a valid withdrawal amount',
                });
                return;
            }

            try {
                const response = await axios.post('/agent/withdrawal-request/request-for-withdrawal', {
                requested_amount: withdrawalAmount.value,
                });
                console.log("response",response);

                if (response.data.success) {
                showWithdrawalModal.value = false;
                withdrawalAmount.value = '';

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Withdrawal request submitted successfully!',
                });
                
                router.get('/agent/withdrawal-request');
                } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.data.message || 'Something went wrong!',
                });
                }
            } catch (error) {
                Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.response?.data?.message || 'Something went wrong!',
                });
            }
        };
        const walletBalance = ref(0);
        const currency = ref('');
        const currencySymbol = ref('');
        const transactions = ref([]);
        const bankInfo = ref({});

        const fetchWalletData = async () => {
            try {
            const res = await axios.get('/agent/withdrawal-request/wallet/history');
            console.log("res",res);
            if (res.data.success) {
                walletBalance.value = res.data.wallet_balance;
                currency.value = res.data.currency_code;
                currencySymbol.value = res.data.currency_symbol;
                console.log("walletBalance.value ",walletBalance.value );
                bankInfo.value =  res.data.bank_info_exists;

                transactions.value = res.data.wallet_history.data.map(item => ({
                id: item.id,
                title: item.remarks,
                time: item.created_at,
                type: item.is_credit === 1 ? 'credit' : 'debit',
                amount: item.amount,
                logo: '/wallet-icon.png', // optional custom logo
                }));
            }
            } catch (error) {
            console.error('Error fetching wallet data:', error);
            Swal.fire('Error', 'Failed to load wallet data', 'error');
            }
        };

        return {
            results,
            successMessage,
            alertMessage,
            dismissMessage,
            searchTerm,
            paginator,
            fetchDatas,
            filter,
            activeTab,
            rightOffcanvas,
            payment_method,
            fetchMethods,
            methods,
            selectMethod,
            selectedMethod,
            formValues,
            submit,
            payment,
            showWithdrawalModal,
            submitWithdrawal,
            withdrawalAmount,
            fetchWalletData,
            walletBalance,
            currency,
            currencySymbol,
            transactions,
            bankInfo
        };
    },
    computed: {
        ...mapGetters(['permissions']),
    },
        
    created() {
        this.fetchMethods(); 
    },
};
</script>

<template>
    <Layout>

        <Head title="Wallet" />
        <div class="font">
            
        <PageHeader :title="$t('index')" :pageTitle="$t('wallet')" />
        <BRow>
            <BCol lg="12">
                <BCard no-body id="tasksList">
                    <BCardHeader class="border-0">
                        <BRow class="g-2">
                            <BCol md="2">
                            </BCol>
                            <BCol md="auto" class="ms-auto">
                                <div class="d-flex align-items-center gap-2">
                        
                                    <button type="button" class="btn btn-primary btn-label waves-effect right waves-light" @click="payment_method = !payment_method">
                                        <i class="ri-arrow-right-line label-icon align-middle fs-16 ms-2"></i> {{$t('update_payment_method')}}
                                    </button>
                                    <button type="button" class="btn btn-primary btn-label waves-effect right waves-light" @click="showWithdrawalModal = !showWithdrawalModal">
                                        <i class="ri-arrow-down-line label-icon align-middle fs-16 ms-2"></i> {{$t('withdrawal')}}
                                    </button>
                                </div>
                            </BCol>
                        </BRow>
                    </BCardHeader>
                    <BCardBody class="border border-dashed border-end-0 border-start-0">
                        <div class="row">
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
                        </div>
                        <BRow>
                            <BCol lg="6">
                                <BCard class="border">
                                    <BCardHeader>
                                        <h4 class="mb-3 flex-grow-1 text-start text-muted badge bg-secondary-subtle fs-18">{{$t("recent_transactions")}}</h4>
                                    </BCardHeader>
                                    <BCardBody>                        
                                        <!-- Transactions -->
                                        <div class="transactions">
                                            <div v-if="transactions && transactions.length > 0" data-simplebar="init" class="mx-n3 overflow-y-scroll" style="height: 500px;">
                                                <div class="transaction bg-light  mx-auto" v-for="txn in transactions" :key="txn.id">
                                                    <div class="t-icon-container">
                                                        <i  class="ri-arrow-left-right-line fs-18"></i>
                                                    </div>
                                                    <div class="t-details">
                                                        <div class="t-title">{{ $t(txn.title) }}</div>
                                                        <div class="t-time">{{ txn.time }}</div>
                                                    </div>
                                                    <div class="t-amount" :class="{ red: txn.type === 'debit' }">
                                                        {{ txn.type === 'credit' ? '+' : '-' }}{{ txn.amount }}{{ currency }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="card d-grid place-items-center text-center">
                                                <img class="m-auto" src="@assets/images/trans.png" alt="Loading..." style="width:100px" />
                                                <h5 class="text-center text-muted mt-3">{{$t("no_transactions_available")}}</h5>
                                            </div>
                                        </div>
                                    </BCardBody>
                                </BCard>
                            </BCol>  
                            <BCol lg="6">
                                <BCard class="border">
                                    <BCardHeader>
                                        <h4 class="mb-3 flex-grow-1 text-start text-muted badge bg-secondary-subtle fs-18">{{$t("withdrawal_request")}}</h4>
                                    </BCardHeader>
                                    <BCardBody>   
                                        <div class="transactions">
                                            <div v-if="results && results.length > 0" data-simplebar="init" class="mx-n3 overflow-y-scroll" style="height: 500px;">
                                                <div class="transaction bg-light  mx-auto" v-for="result in results" :key="result.id">
                                                    <div class="t-icon-container">
                                                        <i  class="ri-arrow-down-line fs-18"></i>
                                                    </div>
                                                    <div class="t-details">
                                                        <div class="t-title text-uppercase">{{ result.payment_status }}</div>
                                                        <div class="t-time">{{ result.converted_created_at }}</div>
                                                    </div>
                                                    <div>
                                                        <h6 class="text-success">{{ result.requested_amount }}{{ result.requested_currency }}</h6>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="card d-grid place-items-center text-center">
                                                <img class="m-auto" src="@assets/images/trans.png" alt="Loading..." style="width:100px" />
                                                <h5 class="text-center text-muted mt-3">{{$t("no_transactions_available")}}</h5>
                                            </div>
                                        </div>  
                                    </BCardBody>
                                </BCard>
                            </BCol>                      
                        </BRow>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <!-- payment method update model -->
        <BModal v-model="payment_method" hide-footer :title="$t('payment_method')" class="v-modal-custom" size="md">
            <div class="col-sm-6">
                <div class="col-12">
                    <div class="mb-3">
                    <label for="type" class="form-label" >{{$t("payment_method")}}
                        <span class="text-danger">*</span>
                    </label>
                    <select id="type" class="form-select"  v-model="payment" @change="selectMethod(payment)">
                        <option disabled value="">{{ $t("select_payment_method") }}</option>
                        <option v-for="(type, index) in methods"  :key="index" :value="type">
                            {{ type.method_name }}
                        </option>
                    </select>
                    </div>
                </div>
            </div>
            <div class="mt-5" v-if="selectedMethod">
                <h5>{{ $t('update_info_for') }} : {{ selectedMethod.method_name }}</h5>
                
                <div class="col-sm-6" v-for="field in selectedMethod.fields.data" :key="field.id">
                    <div class="mb-3">
                        <label for="name" class="form-label">{{field.input_field_name}}</label>
                        <input :type="field.input_field_type" class="form-control" :placeholder="field.placeholder"
                            v-model="formValues[field.id]" id="name"  />
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary" @click="submit"> {{  $t('save') }}</button>
                    </div>
                </div>
            </div>           
        </BModal>


        <!-- Modal withdrawal -->
        <BModal v-model="showWithdrawalModal" title="Withdraw Amount" hide-footer>
        <div class="mb-3">
            <label for="withdrawalAmount" class="form-label">{{$t("enter_amount")}}</label>
            <input
            type="number"
            class="form-control"
            v-model="withdrawalAmount"
            id="withdrawalAmount"
            :placeholder="$t('enter_amount_to_withdraw')"
            />
        </div>
        <div class="text-end">
            <button class="btn btn-danger me-2" @click="showWithdrawalModal = false">{{$t("cancel")}}</button>
            <button class="btn btn-success" @click="submitWithdrawal">{{$t("withdraw")}}</button>
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
/* Transactions */
.transactions {
  margin-bottom: 30px;
  /* padding: 15px; */
}

.t-desc {
  font-weight: 600;
  margin:20px 10px;
  display: block;
}

.transaction {
  background: white;
  padding: 20px;
  border-radius: 14px;
  margin: 15px 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.t-icon-container {
  width: 40px;
}

.t-icon {
  width: 100%;
  border-radius: 50%;
  box-shadow: 2px 2px 10px rgba(0,0,0,0.2);
}

.t-details {
  flex: 1;
  margin-left: 10px;
}

.t-title {
  font-weight: 600;
  font-size: 0.9rem;
}

.t-time {
  font-size: 0.7rem;
  color: #999;
}

.t-amount {
  font-size: 0.9rem;
  color: #06d778;
  font-weight: 600;
}

.t-amount.red {
  color: #f4532d;
}
/* ===== Responsive Styles ===== */
@media (max-width: 768px) {

.transactions {
  padding: 15px;
}

.transaction {
  margin: 10px 5px;
  padding: 15px;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}

.t-icon-container {
  width: 35px;
}

.t-title {
  font-size: 0.85rem;
}

.t-time {
  font-size: 0.65rem;
}

.t-amount {
  font-size: 0.9rem;
  align-self: flex-end;
}

}

@media (max-width: 480px) {

.transaction {
  padding: 12px;
}

.t-title {
  font-size: 0.8rem;
}

.t-time {
  font-size: 0.6rem;
}

.t-amount {
  font-size: 0.85rem;
}
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
