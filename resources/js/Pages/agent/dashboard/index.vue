<script>
import { Link, Head, useForm, router } from '@inertiajs/vue3';
import Layout from "@/Layouts/Agent/agentmain.vue";
import PageHeader from "@/Components/page-header.vue";
import Pagination from "@/Components/Pagination.vue";
import Swal from "sweetalert2";
import { ref, onMounted, watch, computed } from "vue";
import axios from "axios";
import { debounce } from 'lodash';
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";
import useVuelidate from "@vuelidate/core";
import { UsersIcon } from '@zhuowenli/vue-feather-icons';
import getChartColorsArray from "@/common/getChartColorsArray";
import { useSharedState } from '@/composables/useSharedState';
import { useI18n } from 'vue-i18n';
import { layoutComputed } from "@/state/helpers";
import { mapGetters } from 'vuex';
import Warning from "@/Components/warning.vue";

export default {
  props: {
    firebaseSettings: Object,

  },
  data() {
    return {
      selectedServiceLocation: null, // To store the selected service location
    };
  },
  computed: {
    ...layoutComputed,
    ...mapGetters(['permissions']),
    layoutType: {
      get() {
        return this.$store ? this.$store.state.layout.layoutType : {} || {};
      },
    },
  },
  components: {
    Layout,
    PageHeader,
    Head,
    Pagination,
    Multiselect,
    flatPickr,
    Link,
    UsersIcon,
    Warning
  },
  setup(props) {
    const { t } = useI18n();
    const { playAudioOnce, selectedLocation } = useSharedState();
    const series = ref([]);
    const chartOptions = ref({});
    const overall = ref([]);
    const overallChartOptions = ref({});
    const recentSearchesChartOptions = ref({});
    const recentSearch = ref([]);
    const cancellation = ref([]);
    const cancelChartOptions = ref({});
    const sosRequests = ref([]);
    const seriesOverallTrip = ref([]);
    const cancelledtrips = ref({
      auto_cancelled : 0,
      user_cancelled : 0,
      driver_cancelled : 0,
      dispatcher_cancelled : 0,
      total_cancelled : 0,
    });
    const chartOptionsOverallTrip = ref({});
    const earningData = ref({
        card : 0,
        cash : 0,
        wallet : 0,
        total : 0,
        admin_commision : 0,
        driver_commision : 0,
    });

    const todayEarnings = ref(earningData.value);
    const overallEarnings = ref(earningData.value);

    const totalDrivers = ref({
        approved : 0,
        declined : 0,
        approve_percentage : 0,
        decline_percentage : 0,
        total : 0,
    });
    const totalUsers = ref(0);
    const currencySymbol = ref('');

    const fetchDashboardData = async () => {
      try {
        const response = await axios.get('/dashboard/data',{ params:{service_location_id : selectedLocation.value}});
        totalDrivers.value = response.data.totalDrivers;
        totalUsers.value = response.data.totalUsers;
        currencySymbol.value = response.data.currencySymbol;

      } catch (error) {
        console.error(t('error_fetching_today_earnings'), error);
      }
    }

    // Fetch data for today earnings chart
    const fetchTodayEarnings = async () => {
      try {
        const response = await axios.get('/agent/dashboard/today-earnings',{ params:{service_location_id : selectedLocation.value}});
        series.value = [
          Number(response.data.today.completed),
          Number(response.data.today.cancelled),
          Number(response.data.today.scheduled),
        ];

        todayEarnings.value = response.data.today.earnings;
        chartOptions.value = {
          labels: [t('completed'), t('cancelled'), t('scheduled')],
          chart: {
            type: "donut",
            height: 219,
          },
          plotOptions: {
            pie: {
              size: 100,
              donut: {
                size: "75%",
              },
            },
          },
          dataLabels: {
            enabled: false,
          },
          legend: {
            show: false,
            position: "bottom",
            horizontalAlign: "center",
            offsetX: 0,
            offsetY: 0,
            markers: {
              width: 20,
              height: 6,
              radius: 2,
            },
            itemMargin: {
              horizontal: 12,
              vertical: 0,
            },
          },
          stroke: {
            width: 0,
          },
          yaxis: {
            labels: {
              formatter: function (value) {
                return value;
              },
            },
            tickAmount: 4,
            min: 0,
          },
          colors: getChartColorsArray('["--vz-primary", "--vz-warning", "--vz-info"]'),
        };

        seriesOverallTrip.value = [
          Number(response.data.overall.completed),
          Number(response.data.overall.cancelled),
          Number(response.data.overall.scheduled),
        ];
        overallEarnings.value = response.data.overall.earnings;
        chartOptionsOverallTrip.value = generateChartOptions('overall');
      } catch (error) {
        console.error(t('error_fetching_today_earnings'), error);
      }
    };

    const requestEnquiry = ref([]);
     const chartOptionsrequestEnquiry = ref({});

    // Fetch data for today earnings chart
    const fetchrequestEnquiry = async () => {
      try {
        const response = await axios.get('/agent/dashboard/request-enquires-chart',{ params:{service_location_id : selectedLocation.value}});
        requestEnquiry.value = [
          Number(response.data.overall.converted_as_ride),
          Number(response.data.overall.cancelled),
          Number(response.data.overall.enquiry_pending),
        ];
        chartOptionsrequestEnquiry.value = {
          labels: [t('converted_as_ride'), t('cancelled'), t('enquiry_pending')],
          chart: {
            type: "donut",
            height: 219,
          },
          plotOptions: {
            pie: {
              size: 100,
              donut: {
                size: "75%",
              },
            },
          },
          dataLabels: {
            enabled: false,
          },
          legend: {
            show: false,
            position: "bottom",
            horizontalAlign: "center",
            offsetX: 0,
            offsetY: 0,
            markers: {
              width: 20,
              height: 6,
              radius: 2,
            },
            itemMargin: {
              horizontal: 12,
              vertical: 0,
            },
          },
          stroke: {
            width: 0,
          },
          yaxis: {
            labels: {
              formatter: function (value) {
                return value;
              },
            },
            tickAmount: 4,
            min: 0,
          },
          colors: getChartColorsArray('["--vz-primary", "--vz-warning", "--vz-info"]'),
        };
      } catch (error) {
        console.error(t('error_fetching_today_earnings'), error);
      }
    };


    // Helper function to generate chart options
    const generateChartOptions = (type) => {
      return {
        labels: [t('completed'), t('cancelled'), t('scheduled')],
        chart: {
          type: 'donut',
          height: 219,
        },
        plotOptions: {
          pie: {
            size: 100,
            donut: {
              size: '75%',
            },
          },
        },
        dataLabels: {
          enabled: false,
        },
        legend: {
          show: false,
          position: 'bottom',
          horizontalAlign: 'center',
          markers: {
            width: 20,
            height: 6,
            radius: 2,
          },
        },
        stroke: {
          width: 0,
        },
        yaxis: {
          labels: {
            formatter: function (value) {
              return value;
            },
          },
          tickAmount: 4,
          min: 0,
        },
        colors: getChartColorsArray(
          type === 'today'
            ? '["--vz-primary", "--vz-warning", "--vz-info"]'
            : '["--vz-secondary", "--vz-danger", "--vz-success"]'
        ),
      };
    };

    const sos_update = async(sos) => {
      try {

        const response = await axios.get(`rides-request/detail/${sos.req_id}`);
        if (response.status === 200) {
          let trip = response.data.request;
          let sosData = {
            isUser: sos.is_user,
            isDriver: sos.is_driver,
            userName: trip.userDetail?.data?.name,
            driverName: trip.driverDetail?.data?.name,
            request_id: sos.req_id,
            date: response.data.current_time,
          };
          const existingIndex = sosRequests.value.findIndex(
            (request) => request.request_id === sosData.request_id
          );
          if (existingIndex === -1) {
            sosRequests.value.push(sosData);
          } else {
            sosRequests.value[existingIndex] = sosData;
          }
          playAudioOnce();
            Swal.fire({
              title: t('notified_sos'),
              text: t('sos_has_been_notified_proceed_to_details'),
              icon: "warning",
              showCancelButton: true,
              confirmButtonColor: "#34c38f",
              cancelButtonColor: "#f46a6a",
              confirmButtonText: t('check'),
              cancelButtonText: t('cancel')
          }).then(async (result) => {
              if (result.isConfirmed) {
                  router.get('/rides-request/view/'+sos.req_id);
              }
          });
        }
      }catch (error) {
        console.error(error);
      }
    }


    // Fetch data for overall earnings chart
    const fetchOverallEarnings = async () => {
      try {
        const response = await axios.get('/agent/dashboard/overall-earnings',{ params:{service_location_id : selectedLocation.value}});
        overall.value = [
          {
            name: t('overall_earnings'),
            data: response.data.earnings.values,
          },
        ];
        overallChartOptions.value = {
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
            categories: response.data.earnings.months, // x Axis months
          },
          yaxis: {
            labels: {
              formatter: function (value) {
                return value.toFixed(1);
              },
            },
            tickAmount: 5,
            min: 0,
            max: Math.max(...response.data.earnings.values) * 1.1, // Adjust max value dynamically
          },
          colors: getChartColorsArray('["--vz-success"]'),
          fill: {
            opacity: 0,
            colors: ["#0AB39C", "#F06548"],
            type: "solid",
          },
        };
      } catch (error) {
        console.error(t('error_fetching_overall_earnings'), error);
      }
    };

     const fetchRecentSearches = async () => {
      try {
        const response = await axios.get('/agent/dashboard/recent-searches',{ params:{service_location_id : selectedLocation.value}});
        recentSearch.value = [
          {
            name: t('recent_searches'),
            data: response.data.searches.values,
          },
        ];
        recentSearchesChartOptions.value = {
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
            categories: response.data.searches.months, // x Axis months
          },
          yaxis: {
            labels: {
              formatter: function (value) {
                return value.toFixed(1);
              },
            },
            tickAmount: 5,
            min: 0,
            max: Math.max(...response.data.searches.values) * 1.1, // Adjust max value dynamically
          },
          colors: getChartColorsArray('["--vz-success"]'),
          fill: {
            opacity: 0,
            colors: ["#0AB39C", "#F06548"],
            type: "solid",
          },
        };
      } catch (error) {
        console.error(t('error_fetching_recent_searches'), error);
      }
    };

    // Fetch data for cancellation chart
    const fetchCancellationData = async () => {
      try {
        const response = await axios.get('/agent/dashboard/cancel-chart',{ params:{service_location_id : selectedLocation.value}});
        cancelledtrips.value = response.data['data'];
        cancellation.value = [
          {
            name: t('cancelled_due_to_no_drivers'),
            type: "bar",
            data: response.data['a'],
          },
          {
            name: t('cancelled_by_users'),
            type: "bar",
            data: response.data['u'],
          },
          {
            name: t('cancelled_by_drivers'),
            type: "bar",
            data: response.data['d'],
          },
        ];
        cancelChartOptions.value = {
          chart: {
            height: 374,
            type: "line",
            toolbar: {
              show: false,
            },
          },
          stroke: {
            curve: "smooth",
            dashArray: [0, 0, 0],
            width: [0, 0, 0],
          },
          fill: {
            opacity: [1, 1, 1],
          },
          markers: {
            size: [0, 0, 0],
            strokeWidth: 2,
            hover: {
              size: 4,
            },
          },
          xaxis: {
            categories: response.data['y'],
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
          colors: getChartColorsArray('["--vz-primary", "--vz-warning", "--vz-success"]'),
          tooltip: {
            shared: true,
            y: [
              {
                formatter: function (y) {
                  if (typeof y !== "undefined") {
                    return y.toFixed(0);
                  }
                  return y;
                },
              },
              {
                formatter: function (y) {
                  if (typeof y !== "undefined") {
                    return y.toFixed(0);
                  }
                  return y;
                },
              },
              {
                formatter: function (y) {
                  if (typeof y !== "undefined") {
                    return y.toFixed(0);
                  }
                  return y;
                },
              },
            ],
          },
        };
      } catch (error) {
        console.error(t('error_fetching_cancellation_data'), error);
      }
    };

    const fetchAllData = async() => {
        await fetchDashboardData();
        await fetchTodayEarnings();
        await fetchOverallEarnings();
        await fetchCancellationData();
        await fetchrequestEnquiry();
    }
    // Call APIs on component mount
    onMounted(async() => {
      fetchUnAssignedRides();
      try{
        let shouldProcessSosChildAdded = false;
        setTimeout(()=> {
          shouldProcessSosChildAdded = true;
        },4000);
        await fetchAllData();
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
      }catch (error) {
        console.error(error);
      }
    });

    watch (()=>selectedLocation.value, (value) => {
      if(value){
          fetchAllData();
      }
    })
    const results = ref([]);
    const fetchUnAssignedRides = async (page = 1) => {
      try {
          const response = await axios.get(`/agent/unassigned-rides`);
          results.value = response.data.results.data;
          console.log("results",results.value);
      } catch (error) {
          console.error(t('error_fetching_unassigned_rides'), error);
      }
  };
  const assignDriver = (id) =>{
    router.get("ongoing_request/assign/" + id);
  }

  const drivers_list = ref([]);
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
  const fetchNearbyDrivers = async() => {
      const driversRef = firebase.database().ref('drivers');

      driversRef.once('value', (snapshot) => {
          const drivers = snapshot.val();

          if (!drivers) return;
          drivers_list.value = [];

          Object.keys(drivers).forEach(driverId => {
              const driver = drivers[driverId];

              if (driver.service_location_id) { 
                let status="offline";
                let vehicleTypeIconUrl="";

                let last_seen = '';
                if(driver.hasOwnProperty('is_active') && driver.hasOwnProperty('is_available')){
                    if (driver.is_active == 1 && driver.is_available==true) {
                        vehicleTypeIconUrl = `/image/map/${driver.vehicle_type_icon}.png`;
                        status="online";
                        driver.status = status;                                
                        drivers_list.value.push(driver);
                    } 
                  }
              }
          });
      });
  };
    const viewData = async (id) =>  {
      router.get(`/agent/driver/view-profile/${id}`); 
    };


    return {
      series,
      chartOptions,
      overall,
      sosRequests,
      overallChartOptions,
      cancellation,
      cancelChartOptions,
      seriesOverallTrip,
      chartOptionsOverallTrip,
      todayEarnings,
      overallEarnings,
      totalDrivers,
      totalUsers,
      cancelledtrips,
      currencySymbol,
      fetchRecentSearches,
      recentSearchesChartOptions,
      recentSearch,
      results,
      assignDriver,
      drivers_list,
      viewData,
      requestEnquiry,
      chartOptionsrequestEnquiry
    };
  },

  methods: {},
};
</script>


<template>
  <Layout>
    <Warning />
    <PageHeader :title="$t('agent-dashboard')" :pageTitle="$t('dashboard')" />
    <!-- rides -->
    <BRow>
      <BCol xl="7" md="12" lg="7">
        <BCard no-body class="card-height-100">
          <BCardHeader class="align-items-center d-flex py-0">
            <BCardTitle class="mb-0 flex-grow-1 p-3">{{ $t("today_trips") }}</BCardTitle>
          </BCardHeader>
          <BCardBody>
            <apexchart class="apex-charts" dir="ltr" height="219" :series="series" :options="chartOptions"></apexchart>

            <div class="table-responsive mt-3">
              <table class="table table-borderless table-sm table-centered align-middle table-nowrap mb-0">
                <tbody class="border-0">
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-primary me-2"></i>{{ $t("completed_rides") }}
                      </h4>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-warning me-2"></i>{{ $t("cancelled_rides") }}
                      </h4>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-info me-2"></i>{{ $t("scheduled_rides") }}
                      </h4>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
 
      <BCol xl="5" md="12" lg="5">
        <div class="row">
          <div class="col-md-12">
            <div class="card card-animate">
              <div class="card-body short-left-border">
                <div class="d-flex justify-content-between">
                  <div class="ms-2">
                    <p class="fw-medium text-muted mb-0">{{ $t("today_earnings") }}</p>
                    <h2 class="mt-4 ff-secondary fw-semibold">
                    <span class="counter-value" data-target="97.66">{{currencySymbol}}</span>
                    {{ todayEarnings.total.toFixed(2) }} </h2>
                  </div>
                  <div class="mt-4">
                    <div class="avatar-sm flex-shrink-0">
                      <span class="avatar-title bg-info-subtle rounded-circle fs-2">
                        <i class="bx bx-money text-info icon-lg"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div><!-- end card body -->
            </div> <!-- end card-->
          </div> <!-- end col-->

          <div class="col-md-12">
            <div class="card card-animate">
              <div class="card-body short-left-border">
                <div class="d-flex justify-content-between">
                  <div class="ms-2">
                    <p class="fw-medium text-muted mb-0">{{ $t("overall_earnings") }}</p>
                    <h2 class="mt-4 ff-secondary fw-semibold">
                      <span class="counter-value" data-target="97.66">{{currencySymbol}}</span>
                      {{ overallEarnings.total.toFixed(2) }} 
                    </h2>  
                  </div>
                  <div class="mt-4">
                    <div class="avatar-sm flex-shrink-0">
                      <span class="avatar-title bg-danger-subtle rounded-circle fs-2">
                        <i class="bx bx-money text-danger icon-lg"></i>
                      </span>
                    </div>
                  </div>
                </div>
              </div><!-- end card body -->
            </div> <!-- end card-->
          </div> <!-- end col-->
        </div>
      </Bcol>

      <!-- Overall Trips Chart -->
      <BCol xl="6" md="12" lg="6">
        <BCard no-body class="card-height-100">
          <BCardHeader class="align-items-center d-flex py-0">
            <BCardTitle class="mb-0 flex-grow-1 p-3">{{ $t("overall_trips") }}</BCardTitle>
          </BCardHeader>
          <BCardBody>
            <apexchart class="apex-charts" dir="ltr" height="219" :series="seriesOverallTrip" :options="chartOptionsOverallTrip"></apexchart>
            <div class="table-responsive mt-3">
              <table class="table table-borderless table-sm table-centered align-middle table-nowrap mb-0">
                <tbody class="border-0">
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-secondary me-2"></i>{{ $t("completed_rides") }}
                      </h4>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-danger me-2"></i>{{ $t("cancelled_rides") }}
                      </h4>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-success me-2"></i>{{ $t("scheduled_rides") }}
                      </h4>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
       <BCol xl="6" md="12" lg="6">
        <BCard no-body>
          <BCardBody class="p-0">
            <BRow class="g-0">
              <BCol xxl="12">
                <div class="">
                  <BCardHeader class="align-items-center d-flex">
                    <BCardTitle class="mb-0 flex-grow-1">{{ $t("overall_earnings") }}</BCardTitle>
                  </BCardHeader>
                  <apexchart class="apex-charts" height="350" dir="ltr" :series="overall" :options="overallChartOptions"></apexchart>
                </div>
              </BCol>       
            </BRow>
          </BCardBody>
        </BCard>
      </BCol>

  </BRow>

    <BRow class="mb-5 mt-3">
      <BCol xl="12" md="12" lg="12">
        <BCard no-body class="card-height-100">
          <BCardHeader class="align-items-center d-flex py-0">
            <BCardTitle class="mb-0 flex-grow-1 p-3">{{ $t("request_enquiry") }}</BCardTitle>
          </BCardHeader>
          <BCardBody>
            <apexchart class="apex-charts" dir="ltr" height="219" :series="requestEnquiry" :options="chartOptionsrequestEnquiry"></apexchart>

            <div class="table-responsive mt-3">
              <table class="table table-borderless table-sm table-centered align-middle table-nowrap mb-0">
                <tbody class="border-0">
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-primary me-2"></i>{{ $t("converted_as_ride") }}
                      </h4>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-warning me-2"></i>{{ $t("cancelled_rides") }}
                      </h4>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <h4 class="text-truncate fs-14 fs-medium mb-0">
                        <i class="ri-stop-fill align-middle fs-18 text-info me-2"></i>{{ $t("enquiry_pending") }}
                      </h4>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
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
.address-length {
    display: inline-block;
    width: 250px;
    white-space: nowrap;
    overflow: hidden !important;
    text-overflow: ellipsis;
}
.assign-ride{
    max-height:350px;
    overflow-y: auto;
}
@media only screen and (max-width: 769px) {
.assign-ride{
    max-height:500px;
    overflow-y: auto;
}
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

.short-left-border-assign {
  position: relative;
  padding-left: 15px; /* space for the border */
}

.short-left-border-assign::before {
  content: "";
  position: absolute;
  left: 0;
  top: 55px;       /* distance from top */
  height: 108px;    /* height of the border */
  width: 4px;      /* border width */
  background-color: #032C4A;
  border-bottom-right-radius: 15px;
  border-top-right-radius: 15px;
}
.activity-feed .feed-item {
  position: relative;
  border-left: 2px dashed #D4D6D8;
  padding-bottom: 0px;
}

.activity-feed .feed-item:last-child {
  border-color: transparent;
}
.activity-feed .drop-feed-item:last-child {
  border-color: transparent;
}

.activity-feed .feed-item::before,
.activity-feed .feed-item::after {
  content: "";
  position: absolute;
  border-radius: 50%;
}

.activity-feed .feed-item::before {
  /* Outer circle */
  top: 0;
  left: -9px;
  width: 16px;
  height: 16px;
  background: #ffffff;
  border: 3px solid #4a4a4a;
}

.activity-feed .feed-item::after {
  /* Inner circle */
  top: 4px;
  left: -5px;
  width: 8px;
  height: 8px;
  background: #4a4a4a;
}

.activity-feed .drop-feed-item {
  position: relative;
  border-left: 2px dashed #D4D6D8;
}

.activity-feed .drop-feed-item::after {
  content: "\ee17"; /* bx-map-pin icon */
  position: absolute;
  top: 0;
  left: -9px;
  font-family: 'boxicons' !important;
  font-weight: normal;
  font-style: normal;
  font-size: 20px;
  color: #032C4A;
  line-height: 1;
}
</style>