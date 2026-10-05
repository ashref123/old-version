<script>
import { Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import { ref } from "vue";
import Multiselect from "@vueform/multiselect";
import '@vueform/multiselect/themes/default.css';
import FormValidation from "@/Components/FormValidation.vue";
import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";
import searchbar from "@/Components/widgets/searchbar.vue";
import { useI18n } from 'vue-i18n';

export default {
  components: {
    Layout,
    PageHeader,
    Head,
    Multiselect,
    searchbar,
    FormValidation,
    flatPickr,
  },

  props: {
    successMessage: String,
    alertMessage: String,
    serviceLocations: Array,
    agent_commission: Object,       
  },

  setup(props) {
    const { t } = useI18n();

    const form = useForm({
      service_location_id: props.agent_commission ? props.agent_commission.service_location_id : '',
      transport_type: props.agent_commission ? props.agent_commission.transport_type : '',
      agent_commision_type: props.agent_commission ? props.agent_commission.agent_commision_type : "",
      agent_commision: props.agent_commission ? props.agent_commission.agent_commision : "",
    });

    const validationRules = {
      service_location_id: { required: true },
      transport_type: { required: true },
      agent_commision_type: { required: true },
      agent_commision: { required: true },
    };

    const validationRef = ref(null);
    const errors = ref({});
    const successMessage = ref(props.successMessage || "");
    const alertMessage = ref(props.alertMessage || "");

    const dismissMessage = () => {
      successMessage.value = "";
      alertMessage.value = "";
    };

     const handleSubmit = async () => {
      errors.value = validationRef.value.validate();
      if (Object.keys(errors.value).length > 0) {
        return;
      }

      try {
        let response;
        if (props.agent_commission && props.agent_commission.id) {
          response = await axios.post(`/agent-commission/update/${props.agent_commission.id}`, form.data());
        } else {
          response = await axios.post('/agent-commission/store', form.data());
        }
        if (response.status === 201 || response.status === 200) {
          successMessage.value = t('agent_commission_saved_successfully');
          form.reset();
          router.visit('/agent-commission');
        } else {
          alertMessage.value = t('failed_to_save_agent_commission');
        }
      } catch (error) {
        if (error.response && error.response.status === 422) {
          errors.value = error.response.data.errors;
        } else {
          console.error(t('error_saving_agent_commission'), error);
          alertMessage.value = t('failed_to_save_agent_commission');
        }
      }
    };



    return {
      form,
      successMessage,
      alertMessage,
      handleSubmit,
      dismissMessage,
      validationRules,
      validationRef,
      errors,
      serviceLocations: props.serviceLocations
    };
  }
};
</script>

<template>
  <Layout>
    <Head title="Agent Commission" />

    <PageHeader 
      :title="agent_commission ? $t('edit') : $t('create')" 
      :pageTitle="$t('agent_commission')" 
      pageLink="/agent-commission"
    />

    <BRow>
      <BCol lg="12">
        <BCard no-body id="tasksList">
          <BCardBody class="border border-dashed border-end-0 border-start-0">

            <!-- FORM -->
            <form @submit.prevent="handleSubmit">
              <FormValidation :form="form" :rules="validationRules" ref="validationRef">

                <div class="row">

                  <!-- SERVICE LOCATION -->
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">
                        {{ $t("service_locations") }} <span class="text-danger">*</span>
                      </label>

                      <Multiselect
                        v-model="form.service_location_id"
                        :options="serviceLocations.map(loc => ({ value: loc.id, label: loc.name }))"
                        :placeholder="$t('select_service_locations')"
                        track-by="value"
                      />

                      <span v-for="(e,i) in errors.service_location_id" :key="i" class="text-danger">{{ e }}</span>
                    </div>
                  </div>

                  <!-- TRANSPORT TYPE -->
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">
                        {{ $t("transport_type") }} <span class="text-danger">*</span>
                      </label>

                      <select class="form-select" v-model="form.transport_type">
                        <option disabled value="">{{ $t("select") }}</option>
                        <option value="taxi">{{ $t("taxi") }}</option>
                        <option value="delivery">{{ $t("delivery") }}</option>
                        <option value="both">{{ $t("all") }}</option>
                      </select>

                      <span v-for="(e,i) in errors.transport_type" :key="i" class="text-danger">{{ e }}</span>
                    </div>
                  </div>

                  <!-- COMMISSION TYPE -->
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">
                        {{ $t("agent_commission_type") }} <span class="text-danger">*</span>
                      </label>

                      <select class="form-select" v-model="form.agent_commision_type">
                        <option disabled value="">{{ $t('select') }}</option>
                        <option value="1">{{ $t('percentage') }}</option>
                        <option value="2">{{ $t('fixed_amount') }}</option>
                      </select>

                      <span v-for="(e,i) in errors.agent_commision_type" :key="i" class="text-danger">{{ e }}</span>
                    </div>
                  </div>

                  <!-- COMMISSION -->
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">
                        {{ $t("agent_commission") }} <span class="text-danger">*</span>
                      </label>

                      <input 
                        type="number"
                        class="form-control"
                        v-model.number="form.agent_commision"
                        :max="form.agent_commision_type == 1 ? 100 : null"
                        :placeholder="$t('enter_agent_commission')"
                      />

                      <span v-for="(e,i) in errors.agent_commision" :key="i" class="text-danger">{{ e }}</span>
                    </div>
                  </div>

                </div>

                <BCardFooter class="text-end">
                  <button type="submit" class="btn btn-success">
                   {{ agent_commission ? $t('update') : $t('save') }}
                  </button>
                </BCardFooter>

              </FormValidation>
            </form>

          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
    <div>
      <div v-if="successMessage" class="custom-alert alert alert-success alert-border-left fade show" role="alert"
        id="alertMsg">
        <div class="alert-content">
          <i class="ri-notification-off-line me-3 align-middle"></i>
          <strong>Success</strong> - {{ successMessage }}
          <button type="button" class="btn-close btn-close-success" @click="dismissMessage"
            aria-label="Close Success Message"></button>
        </div>
      </div>

      <div v-if="alertMessage" class="custom-alert alert alert-danger alert-border-left fade show" role="alert"
        id="alertMsg">
        <div class="alert-content">
          <i class="ri-notification-off-line me-3 align-middle"></i>
          <strong>Alert</strong> - {{ alertMessage }}
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