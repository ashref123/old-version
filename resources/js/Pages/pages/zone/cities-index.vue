<script>
import { Link, Head, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Pagination from "@/Components/Pagination.vue";
import Swal from "sweetalert2";
import { ref } from "vue";
import axios from "axios";
import searchbar from "@/Components/widgets/searchbar.vue";
import { mapGetters } from 'vuex';
import { layoutComputed } from "@/state/helpers";

export default {
  components: {
    Layout,
    Head,
    Link,
    PageHeader,
    Pagination,
    searchbar,
  },
  props: {
    zone: Object,
    app_for: String,
  },
  setup(props) {
    const results = ref([]);
    const paginator = ref({});
    const paginatorOption = ref([10, 25, 50, 100, 200]);
    const searchTerm = ref("");
    const filter = ref({
      limit: 10,
    });
    const successMessage = ref("");
    const alertMessage = ref("");
    const toggleStatus = async (id, status) => {
      Swal.fire({
        title: "Are you sure?",
        text: "You want to change the status?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#34c38f",
        cancelButtonColor: "#f46a6a",
        confirmButtonText: "Yes",
      }).then(async (result) => {
        if (result.isConfirmed) {
          try {
            await axios.post(`/zones/popular-cities/update-status`, { id, status });
            const index = results.value.findIndex((item) => item.id === id);
            if (index !== -1) {
              results.value[index].status = status ? 1 : 0;
            }
            Swal.fire("Changed", "Status updated successfully", "success");
          } catch (error) {
            console.error("Error updating popular city status", error);
            Swal.fire("Error", "Failed to update status", "error");
          }
        }
      });
    };

    const fetchDatas = async (page = 1) => {
      try {
        const response = await axios.get(`/zones/popular-cities/fetch/${props.zone.id}`, {
          params: {
            page,
            limit: filter.value.limit,
            search: searchTerm.value,
          },
        });

        results.value = response.data.results;
        paginator.value = response.data.paginator;
      } catch (error) {
        console.error("Error fetching popular cities", error);
      }
    };

    const fetchSearch = (value) => {
      searchTerm.value = value;
      fetchDatas();
    };

    const handlePageChanged = (page) => {
      fetchDatas(page);
    };

    const changeEntriesPerPage = () => {
      fetchDatas();
    };

    const createData = () => {
      router.get(`/zones/popular-cities/create/${props.zone.id}`);
    };

    const editData = (item) => {
      router.get(`/zones/popular-cities/edit/${item.id}`);
    };

    const deleteData = async (dataId) => {
      try {
        await axios.delete(`/zones/popular-cities/delete/${dataId}`);
        const index = results.value.findIndex(data => data.id === dataId);
        if (index !== -1) {
          results.value.splice(index, 1);
        }
        Swal.fire("Success", "Popular city deleted successfully", "success");
      } catch (error) {
        console.error("Error deleting popular city", error);
        Swal.fire("Error", "Failed to delete popular city", "error");
      }
    };

    const deleteModal = (itemId) => {
      Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#34c38f",
        cancelButtonColor: "#f46a6a",
        confirmButtonText: "Yes, delete it!",
      }).then(async (result) => {
        if (result.isConfirmed) {
          await deleteData(itemId);
        }
      });
    };

    const rideTypeLabel = (rideType) => {
      return rideType === "outstation" ? "Outstation" : "Normal";
    };

    return {
      results,
      paginator,
      paginatorOption,
      searchTerm,
      filter,
      successMessage,
      alertMessage,
      fetchDatas,
      fetchSearch,
      handlePageChanged,
      changeEntriesPerPage,
      createData,
      editData,
      deleteModal,
      rideTypeLabel,
      toggleStatus,
    };
  },
  computed: {
    ...layoutComputed,
    ...mapGetters(['permissions']),
  },
  mounted() {
    this.fetchDatas();
  },
};
</script>

<template>
  <Layout>
    <Head title="Popular Cities" />
    <PageHeader :title="$t('popular_cities')" :pageTitle="zone.name" pageLink="/zones" />

    <BRow>
      <BCol lg="12">
        <BCard no-body>
          <BCardHeader class="border-0">
            <BRow class="g-2 align-items-center">
              <BCol md="3">
                <div class="d-flex align-items-center mt-3">
                  <label class="me-2 text-muted">{{ $t("show") }}</label>
                  <select v-model="filter.limit" @change="changeEntriesPerPage" class="form-select form-select-sm w-auto">
                    <option v-for="option in paginatorOption" :key="option" :value="option">
                      {{ option }}
                    </option>
                  </select>
                  <label class="ms-2 text-muted">{{ $t("entries") }}</label>
                </div>
              </BCol>
              <BCol md="auto" class="ms-auto">
                <div class="d-flex align-items-center gap-2">
                  <!-- <searchbar @search="fetchSearch"></searchbar> -->
                  <BButton v-if="permissions.includes('add-popular-cities')" variant="primary" class="float-end" :disabled="app_for === 'demo'" @click="createData">
                    <i class="ri-add-line align-bottom me-1"></i> {{ $t("add_city") }}
                  </BButton>
                </div>
              </BCol>
            </BRow>
          </BCardHeader>
          <BCardBody class="border border-dashed border-end-0 border-start-0">
            <div class="table-responsive">
              <table class="table align-middle position-relative table-nowrap">
                <thead class="table-active">
                  <tr>
                    <th>{{ $t("zone") }}</th>
                    <th>{{ $t("ride_type") }}</th>
                    <th>{{ $t("city") }}</th>
                    <th>{{ $t("shortcode") }}</th>
                    <th>{{ $t("status") }}</th>
                    <th>{{ $t("action") }}</th>
                  </tr>
                </thead>
                <tbody v-if="results.length">
                  <tr v-for="item in results" :key="item.id">
                    <td>{{ zone.name }}</td>
                    <td>{{ rideTypeLabel(item.ride_type) }}</td>
                    <td>
                      <div>{{ item.city_search }}</div>
                    </td>
                    <td>{{ item.shortcode || "-" }}</td>
                    <td v-if="permissions.includes('toggle-popular-cities')">
                      <div :class="{
                        'form-check': true,
                        'form-switch': true,
                        'form-switch-lg': true,
                        'form-switch-success': item.status,
                      }">
                        <input class="form-check-input" type="checkbox" role="switch" :checked="item.status" @click.prevent="toggleStatus(item.id, !item.status)">
                      </div>
                    </td>
                    <td>
                      <BButton v-if="permissions.includes('edit-popular-cities')" class="btn btn-soft-warning btn-sm m-2" @click.prevent="editData(item)" :disabled="app_for === 'demo'">
                        <i class="bx bxs-edit-alt bx-xs"></i>
                      </BButton>
                      <BButton v-if="permissions.includes('delete-popular-cities')" class="btn btn-soft-danger btn-sm m-2" @click.prevent="deleteModal(item.id)" :disabled="app_for === 'demo'">
                        <i class="bx bx-trash bx-xs"></i>
                      </BButton>
                    </td>
                  </tr>
                </tbody>
                <tbody v-else>
                  <tr>
                    <td colspan="7" class="text-center">
                      <img src="@assets/images/search-file.gif" alt="Loading..." style="width:100px" />
                      <h5>{{ $t("no_data_found") }}</h5>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>

    <Pagination :paginator="paginator" @page-changed="handlePageChanged" />
  </Layout>
</template>
