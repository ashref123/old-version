<script>
import { Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Multiselect from "@vueform/multiselect";
import { ref, watch, computed } from "vue";
import axios from "axios";
import ImageUpload from "@/Components/ImageUpload.vue";

export default {
  components: {
    Layout,
    Head,
    PageHeader,
    Multiselect,
    ImageUpload,
  },
  props: {
    zone: Object,
    zones: Array,
    popularCity: Object,
    selectedZoneId: [String, Number],
    googleMapKey: String,
    app_for: String,
  },
  setup(props) {
    const form = useForm({
      zone_id: props.popularCity ? props.popularCity.zone_id : props.selectedZoneId || "",
      ride_type: props.popularCity ? props.popularCity.ride_type : "normal",
      city_search: props.popularCity ? props.popularCity.city_search : "",
      lat: props.popularCity ? props.popularCity.lat : "",
      lng: props.popularCity ? props.popularCity.lng : "",
      image: props.popularCity ? props.popularCity.image || "" : "",
      image_removed: false,
      shortcode: props.popularCity ? props.popularCity.shortcode : "",
      description: props.popularCity ? props.popularCity.description : "",
    });

    const search = ref(props.popularCity ? props.popularCity.city_search : "");
    const suggestions = ref([]);
    const successMessage = ref("");
    const alertMessage = ref("");
    const errors = ref({});
    const pageLink = computed(() => `/zones/popular-cities/${form.zone_id || props.selectedZoneId}`);

    watch(search, (value) => {
      form.city_search = value;
    });

    const handleInput = () => {
      if (search.value.length < 3) {
        suggestions.value = [];
        return;
      }

      setTimeout(() => {
        fetchAutocompleteResults(search.value);
      }, 300);
    };

    const fetchAutocompleteResults = (value) => {
      if (!props.googleMapKey) {
        return;
      }

      fetch("https://places.googleapis.com/v1/places:autocomplete", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Goog-Api-Key": props.googleMapKey,
          "X-Goog-FieldMask": "suggestions.placePrediction.placeId,suggestions.placePrediction.text",
        },
        body: JSON.stringify({ input: value }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.suggestions?.length > 0) {
            suggestions.value = data.suggestions
              .filter((suggestion) => suggestion.placePrediction)
              .map((suggestion) => ({
                placeId: suggestion.placePrediction.placeId,
                formattedAddress: suggestion.placePrediction.text.text,
              }));
          }
        })
        .catch((error) => {
          console.error("Error fetching autocomplete results:", error);
        });
    };

    const selectSuggestion = (suggestion) => {
      if (!props.googleMapKey) {
        return;
      }

      fetch(`https://places.googleapis.com/v1/places/${suggestion.placeId}?fields=location`, {
        headers: {
          "X-Goog-Api-Key": props.googleMapKey,
          "X-Goog-FieldMask": "location",
        },
      })
        .then((response) => response.json())
        .then((data) => {
          const latitude = data.location?.latitude ?? "";
          const longitude = data.location?.longitude ?? "";

          search.value = suggestion.formattedAddress;
          suggestions.value = [];
          form.city_search = suggestion.formattedAddress;
          form.lat = latitude;
          form.lng = longitude;
        })
        .catch((error) => {
          console.error("Error fetching place details:", error);
        });
    };

    const handleImageSelected = (file) => {
      form.image = file;
      form.image_removed = false;
    };

    const handleImageRemoved = () => {
      form.image = null;
      form.image_removed = true;
    };

    const handleSubmit = async () => {
      try {
        const endpoint = props.popularCity?.id
          ? `/zones/popular-cities/update/${props.popularCity.id}`
          : '/zones/popular-cities/store';

        const formData = new FormData();
        const payload = form.data();

        Object.keys(payload).forEach((key) => {
          if (key === 'image' && payload[key] instanceof File) {
            formData.append('image', payload[key]);
          } else if (key === 'image_removed') {
            if (payload[key]) {
              formData.append('image_removed', '1');
            }
          } else if (key === 'image') {
            // Keep the existing server-side image when editing unless a new file is selected or the user removed it.
            return;
          } else if (payload[key] !== null && payload[key] !== undefined) {
            formData.append(key, payload[key]);
          }
        });

        const response = await axios.post(endpoint, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        if (response.status === 200 || response.status === 201) {
          router.get(`/zones/popular-cities/${form.zone_id}`);
        }
      } catch (error) {
        if (error.response?.status === 422) {
          errors.value = error.response.data.errors || {};
          return;
        }

        if (error.response?.status === 403) {
          alertMessage.value = error.response.data.alertMessage;
          return;
        }

        console.error("Error saving popular city", error);
      }
    };

    return {
      form,
      search,
      suggestions,
      successMessage,
      alertMessage,
      errors,
      pageLink,
      selectedZoneId: props.selectedZoneId,
      handleInput,
      selectSuggestion,
      handleImageSelected,
      handleImageRemoved,
      handleSubmit,
    };
  },
};
</script>

<template>
  <Layout>
    <Head title="Popular City" />
    <PageHeader
      :title="popularCity ? $t('edit') : $t('create')"
      :pageTitle="$t('popular_cities')"
      :pageLink="pageLink"
    />

    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardBody class="border border-dashed border-end-0 border-start-0">
            <form @submit.prevent="handleSubmit">
              <BRow>
                <BCol lg="6">
                  <div class="mb-3">
                    <label class="form-label">{{ $t("zone") }} <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="form.zone_id" disabled>
                      <option value="" disabled>{{ $t("select_zone") }}</option>
                      <option v-for="zoneItem in zones" :key="zoneItem.id" :value="zoneItem.id">
                        {{ zoneItem.name }}
                      </option>
                    </select>
                    <span v-if="errors.zone_id" class="text-danger">{{ errors.zone_id[0] }}</span>
                  </div>
                </BCol>
                <BCol lg="6">
                  <div class="mb-3">
                    <label class="form-label">{{ $t("ride_type") }} <span class="text-danger">*</span></label>
                    <select class="form-select" v-model="form.ride_type">
                      <option value="normal">Normal</option>
                      <option value="outstation">Outstation</option>
                    </select>
                    <span v-if="errors.ride_type" class="text-danger">{{ errors.ride_type[0] }}</span>
                  </div>
                </BCol>
              </BRow>

              <BRow>
                <BCol lg="6">
                  <div class="mb-3 position-relative">
                    <label class="form-label">{{ $t("city") }} <span class="text-danger">*</span></label>
                    <input
                      type="text"
                      class="form-control"
                      :placeholder="$t('search_for_a_city')"
                      v-model="search"
                      @input="handleInput"
                    />
                    <span v-if="errors.city_search" class="text-danger">{{ errors.city_search[0] }}</span>

                    <div v-if="suggestions.length > 0" class="autocomplete-results">
                      <div
                        v-for="suggestion in suggestions"
                        :key="suggestion.placeId"
                        class="autocomplete-item"
                        @click="selectSuggestion(suggestion)"
                      >
                        {{ suggestion.formattedAddress }}
                      </div>
                    </div>
                  </div>
                </BCol>
                <BCol lg="6">
                  <div class="mb-3">
                    <label class="form-label">{{ $t("shortcode") }}</label>
                    <input type="text" class="form-control" v-model="form.shortcode" :placeholder="$t('enter_short_code')"/>
                    <span v-if="errors.shortcode" class="text-danger">{{ errors.shortcode[0] }}</span>
                  </div>
                </BCol>
              </BRow>

              <input type="hidden" v-model="form.lat" />
              <input type="hidden" v-model="form.lng" />

              <!-- <BRow>
                <BCol lg="6">
                  <div class="mb-3">
                    <label class="form-label">{{ $t("shortcode") }}</label>
                    <input type="text" class="form-control" v-model="form.shortcode" />
                    <span v-if="errors.shortcode" class="text-danger">{{ errors.shortcode[0] }}</span>
                  </div>
                </BCol>
              </BRow> -->

              <BRow>
                <BCol lg="6">
                  <div class="mb-3">
                    <label class="form-label">{{ $t("description") }}</label>
                    <textarea class="form-control" rows="4" v-model="form.description"></textarea>
                    <span v-if="errors.description" class="text-danger">{{ errors.description[0] }}</span>
                  </div>
                </BCol>
              </BRow>

                <BRow>
                <BCol lg="12">
                  <div class="mb-3">
                    <label class="form-label">{{ $t("image") }}</label>
                    <ImageUpload
                      :imageType="'cities'"
                      :initialImageUrl="form.image"
                      :flexStyle="'0 0 calc(50% - 20px)'"
                      :aspectRatio="'3 / 2'"
                      @image-selected="handleImageSelected"
                      @image-removed="handleImageRemoved"
                    />
                    <small class="text-muted">Recommended size: 300 x 200</small>
                    <span v-if="errors.image" class="text-danger d-block">{{ errors.image[0] }}</span>
                  </div>
                </BCol>
              </BRow>

              <div class="text-end">
                <button type="submit" class="btn btn-primary" :disabled="app_for === 'demo'">
                  {{ popularCity ? $t('update') : $t('save') }}
                </button>
              </div>
            </form>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.autocomplete-results {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 20;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
  max-height: 240px;
  overflow-y: auto;
}

.autocomplete-item {
  padding: 0.75rem 1rem;
  cursor: pointer;
}

.autocomplete-item:hover {
  background: #f8f9fa;
}
</style>
