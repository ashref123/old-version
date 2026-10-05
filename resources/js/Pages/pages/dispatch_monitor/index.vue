<script>
import { Head, Link } from "@inertiajs/vue3";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import Multiselect from "@vueform/multiselect";
import "@vueform/multiselect/themes/default.css";
import { computed, onMounted, onBeforeUnmount, ref, watch } from "vue";
import axios from "axios";
import Swal from "sweetalert2";
import { debounce } from "lodash";
import { useI18n } from "vue-i18n";

export default {
  components: { Layout, PageHeader, Head, Link, Multiselect },
  props: {
    firebaseConfig: Object,
    acceptDuration: { type: Number, default: 30 },
    serverTimeoutSeconds: { type: Number, default: 60 },
    app_for: String,
    initialRides: { type: Array, default: () => [] },
    initialMeta: {
      type: Object,
      default: () => ({ current_page: 1, last_page: 1, total: 0 }),
    },
  },
  setup(props) {
    const { t } = useI18n();
    const rides = ref([...(props.initialRides || [])]);
    const meta = ref({
      current_page: props.initialMeta?.current_page || 1,
      last_page: props.initialMeta?.last_page || 1,
      total: props.initialMeta?.total || 0,
      per_page: props.initialMeta?.per_page || 20,
    });
    const selectedId = ref(null);
    const detail = ref(null);
    const loadingList = ref(false);
    const loadingDetail = ref(false);
    const reSearching = ref(false);
    const search = ref("");
    const transportType = ref("all");
    const selectedUserId = ref(null);
    const userOptions = ref([]);
    const firebaseLiveMeta = ref(null);
    const nearbyDrivers = ref([]);
    const listPollTimer = ref(null);
    const detailPollTimer = ref(null);
    const tickTimer = ref(null);
    const searchDebounce = ref(null);
    const listRequestSeq = ref(0);
    const nowMs = ref(Date.now());
    const metaListener = ref(null);
    const driversListener = ref(null);
    const database = ref(null);
    const pageReady = ref(false);

    const selectedRide = computed(() =>
      rides.value.find((r) => r.id === selectedId.value) || null
    );

    const statusBadgeClass = (status) => {
      if (status === "offered") return "bg-success";
      if (status === "no_meta_yet") return "bg-secondary";
      if (status === "waiting_next") return "bg-warning text-dark";
      return "bg-info";
    };

    const toneClass = (tone) => {
      if (tone === "green") return "border-success";
      if (tone === "red") return "border-danger";
      if (tone === "amber") return "border-warning";
      return "border-secondary";
    };

    const formatAge = (seconds) => {
      const s = Math.max(0, Math.floor(Number(seconds) || 0));
      if (s < 60) return `${s}s`;
      const m = Math.floor(s / 60);
      const rem = s % 60;
      if (m < 60) return `${m}m ${rem}s`;
      const h = Math.floor(m / 60);
      return `${h}h ${m % 60}m`;
    };

    const formatAgeClock = (seconds) => {
      const s = Math.max(0, Math.floor(Number(seconds) || 0));
      const h = Math.floor(s / 3600);
      const m = Math.floor((s % 3600) / 60);
      const rem = s % 60;
      if (h > 0) {
        return `${h}:${String(m).padStart(2, "0")}:${String(rem).padStart(2, "0")}`;
      }
      return `${String(m).padStart(2, "0")}:${String(rem).padStart(2, "0")}`;
    };

    const formatClock = (seconds) => {
      const s = Math.max(0, Math.floor(Number(seconds) || 0));
      const m = Math.floor(s / 60);
      const rem = s % 60;
      return `${String(m).padStart(2, "0")}:${String(rem).padStart(2, "0")}`;
    };

    const parseOfferTime = (value) => {
      if (!value) return null;
      let ms = Date.parse(value);
      if (Number.isNaN(ms) && typeof value === "string" && value.includes(" ")) {
        ms = Date.parse(value.replace(" ", "T"));
      }
      return Number.isNaN(ms) ? null : ms;
    };

    const liveSearchAgeSeconds = (ride) => {
      // Depend on nowMs so age ticks every second.
      const _tick = nowMs.value;
      const createdMs = parseOfferTime(ride?.created_at);
      if (createdMs) {
        return Math.max(0, Math.floor((_tick - createdMs) / 1000));
      }
      return Math.max(0, Number(ride?.search_age_seconds) || 0);
    };

    const ageToneClass = (seconds) => {
      const s = Number(seconds) || 0;
      if (s >= 300) return "is-critical"; // 5m+
      if (s >= 120) return "is-warn"; // 2m+
      if (s >= 60) return "is-aging";
      return "is-fresh";
    };

    const ageLabel = (seconds) => {
      const s = Number(seconds) || 0;
      if (s >= 300) return "Stale";
      if (s >= 120) return "Aging";
      if (s >= 60) return "Warm";
      return "Fresh";
    };

    const liveOfferTimers = (offer) => {
      // Depend on nowMs so this recalculates every second.
      const _tick = nowMs.value;
      const acceptWindow =
        Number(offer.accept_window_seconds) ||
        Number(detail.value?.accept_duration) ||
        props.acceptDuration ||
        30;
      const serverWindow =
        Number(offer.server_timeout_seconds) ||
        Number(detail.value?.server_timeout_seconds) ||
        props.serverTimeoutSeconds ||
        60;
      const offeredMs = parseOfferTime(offer.offered_at);
      const elapsed = offeredMs
        ? Math.max(0, Math.floor((_tick - offeredMs) / 1000))
        : Number(offer.elapsed_seconds) || 0;
      const acceptRemaining = Math.max(0, acceptWindow - elapsed);
      const serverRemaining = Math.max(0, serverWindow - elapsed);
      const acceptPct = acceptWindow
        ? Math.min(100, Math.round((acceptRemaining / acceptWindow) * 100))
        : 0;
      const serverPct = serverWindow
        ? Math.min(100, Math.round((serverRemaining / serverWindow) * 100))
        : 0;
      let state = "waiting";
      let stateLabel = "Waiting";
      if (elapsed >= serverWindow) {
        state = "likely_timed_out";
        stateLabel = "Timed out";
      } else if (elapsed >= acceptWindow) {
        state = "past_app_timer";
        stateLabel = "Past app timer";
      }
      return {
        elapsed,
        acceptWindow,
        serverWindow,
        acceptRemaining,
        serverRemaining,
        acceptPct,
        serverPct,
        state,
        stateLabel,
      };
    };

    const timerToneClass = (remaining, window) => {
      if (remaining <= 0) return "is-expired";
      if (remaining <= Math.max(5, window * 0.2)) return "is-critical";
      if (remaining <= Math.max(10, window * 0.4)) return "is-warn";
      return "is-ok";
    };

    const ringStyle = (pct, tone) => {
      const color =
        tone === "is-expired"
          ? "#adb5bd"
          : tone === "is-critical"
            ? "#dc3545"
            : tone === "is-warn"
              ? "#fd7e14"
              : "#0d6efd";
      return {
        background: `conic-gradient(${color} ${pct}%, rgba(0,0,0,0.08) 0)`,
      };
    };

    const stopListPolling = () => {
      if (listPollTimer.value) {
        clearInterval(listPollTimer.value);
        listPollTimer.value = null;
      }
    };

    const startListPolling = () => {
      stopListPolling();
      if (!selectedUserId.value) return;
      listPollTimer.value = setInterval(() => {
        if (!pageReady.value || !selectedUserId.value) return;
        fetchList(meta.value.current_page || 1);
      }, 4000);
    };

    const fetchUsers = async (query) => {
      if (!query || String(query).trim().length < 2) {
        userOptions.value = [];
        return;
      }
      try {
        const res = await axios.get("/dispatch-monitor/users", {
          params: { search: String(query).trim() },
        });
        userOptions.value = (res.data.results || []).map((u) => ({
          value: u.id,
          label: u.label || `${u.name || ""} (${u.mobile || ""})`,
        }));
      } catch (e) {
        console.error(e);
        userOptions.value = [];
      }
    };

    const onUserSearch = debounce(fetchUsers, 300);

    const resetListState = () => {
      rides.value = [];
      meta.value = {
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 20,
      };
      clearDetailPanel();
    };

    const fetchList = async (page = 1) => {
      if (!selectedUserId.value) {
        resetListState();
        loadingList.value = false;
        return;
      }
      const seq = ++listRequestSeq.value;
      loadingList.value = true;
      try {
        const response = await axios.get("/dispatch-monitor/rides", {
          params: {
            page,
            user_id: selectedUserId.value,
            search: search.value || undefined,
            transport_type: transportType.value || "all",
          },
        });
        // Ignore stale responses when filters change quickly.
        if (seq !== listRequestSeq.value) return;
        rides.value = response.data.data || [];
        meta.value = response.data.meta || meta.value;

        if (
          selectedId.value &&
          !rides.value.some((r) => r.id === selectedId.value)
        ) {
          // Ride left searching list (accepted / cancelled / completed).
          clearDetailPanel();
        }

        if (!selectedId.value && rides.value.length) {
          selectRide(rides.value[0].id);
        }
      } catch (e) {
        if (seq !== listRequestSeq.value) return;
        console.error(e);
      } finally {
        if (seq === listRequestSeq.value) {
          loadingList.value = false;
        }
      }
    };

    const onTransportChange = () => {
      if (!selectedUserId.value) return;
      fetchList(1);
    };

    const onSearchInput = () => {
      if (!selectedUserId.value) return;
      if (searchDebounce.value) clearTimeout(searchDebounce.value);
      searchDebounce.value = setTimeout(() => {
        fetchList(1);
      }, 350);
    };

    watch(selectedUserId, async (userId) => {
      stopListPolling();
      resetListState();
      if (!userId) return;
      await fetchList(1);
      startListPolling();
    });

    const clearDetailPanel = () => {
      selectedId.value = null;
      detail.value = null;
      firebaseLiveMeta.value = null;
      nearbyDrivers.value = [];
      if (detailPollTimer.value) {
        clearInterval(detailPollTimer.value);
        detailPollTimer.value = null;
      }
      if (metaListener.value) {
        metaListener.value.off();
        metaListener.value = null;
      }
      if (driversListener.value) {
        driversListener.value.off();
        driversListener.value = null;
      }
    };

    const fetchDetail = async (id) => {
      if (!id) {
        clearDetailPanel();
        return;
      }
      loadingDetail.value = true;
      try {
        const response = await axios.get(`/dispatch-monitor/${id}`);
        // Ride accepted/cancelled while detail was open — clear the panel.
        if (response.data?.still_searching === false) {
          clearDetailPanel();
          return;
        }
        detail.value = response.data;
      } catch (e) {
        console.error(e);
        // 404 / gone — clear stale detail.
        clearDetailPanel();
      } finally {
        loadingDetail.value = false;
      }
    };

    const haversineKm = (lat1, lon1, lat2, lon2) => {
      if (
        lat1 == null ||
        lon1 == null ||
        lat2 == null ||
        lon2 == null ||
        Number.isNaN(Number(lat1)) ||
        Number.isNaN(Number(lon1)) ||
        Number.isNaN(Number(lat2)) ||
        Number.isNaN(Number(lon2))
      ) {
        return null;
      }
      const toRad = (v) => (Number(v) * Math.PI) / 180;
      const R = 6371;
      const dLat = toRad(lat2 - lat1);
      const dLon = toRad(lon2 - lon1);
      const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(toRad(lat1)) *
          Math.cos(toRad(lat2)) *
          Math.sin(dLon / 2) *
          Math.sin(dLon / 2);
      return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    };

    const driverMatchesVehicleType = (driverNode, vehicleTypeId) => {
      if (!vehicleTypeId) return true;
      const wanted = String(vehicleTypeId);
      const single = driverNode.vehicle_type;
      if (single != null && String(single) === wanted) return true;
      const types = driverNode.vehicle_types;
      if (Array.isArray(types) && types.map(String).includes(wanted)) return true;
      return false;
    };

    const firebaseUpdatedAgeSeconds = (updatedAt) => {
      if (updatedAt == null || updatedAt === "") return null;
      let ms =
        typeof updatedAt === "number"
          ? updatedAt < 1e12
            ? updatedAt * 1000
            : updatedAt
          : Date.parse(updatedAt);
      if (Number.isNaN(ms)) return null;
      return Math.max(0, Math.floor((Date.now() - ms) / 1000));
    };

    const formatAgeShort = (ageSeconds) => {
      if (ageSeconds == null || !Number.isFinite(ageSeconds)) return "—";
      const sec = Math.max(0, Math.floor(ageSeconds));
      if (sec < 60) return `${sec}s ago`;
      const mins = Math.floor(sec / 60);
      if (mins < 60) return `${mins}m ago`;
      const hours = Math.floor(mins / 60);
      const remMins = mins % 60;
      if (hours < 24) {
        return remMins > 0 ? `${hours}h ${remMins}m ago` : `${hours}h ago`;
      }
      const days = Math.floor(hours / 24);
      const remHours = hours % 24;
      if (days < 30) {
        return remHours > 0 ? `${days}d ${remHours}h ago` : `${days}d ago`;
      }
      const months = Math.floor(days / 30);
      const remDays = days % 30;
      return remDays > 0 ? `${months}mo ${remDays}d ago` : `${months}mo ago`;
    };

    const eligibilityReasonLabel = (reason) => {
      const labels = {
        rejected: "Rejected this ride",
        offline: "Offline",
        unavailable: "Unavailable (not free)",
        stale: "Stale location (>7 min)",
        vehicle_mismatch: "Wrong vehicle type",
        outside_radius: "Outside search radius",
      };
      return labels[reason] || reason || "—";
    };

    const isDriverEligibleForRide = (driverId, driverNode, eligibility) => {
      if (!eligibility) {
        return { ok: true, reason: null };
      }

      const rejected = new Set(
        (eligibility.rejected_driver_ids || []).map(String)
      );
      if (rejected.has(String(driverId))) {
        return { ok: false, reason: "rejected" };
      }

      if (eligibility.require_online !== false) {
        const online =
          driverNode.is_active == 1 || driverNode.is_active === true;
        if (!online) {
          return { ok: false, reason: "offline" };
        }
      }

      const isAvailable =
        driverNode.is_available == 1 || driverNode.is_available === true;
      if (!isAvailable) {
        return { ok: false, reason: "unavailable" };
      }

      const staleMinutes = Number(eligibility.stale_after_minutes) || 7;
      if (driverNode.updated_at) {
        const ageSeconds = firebaseUpdatedAgeSeconds(driverNode.updated_at);
        if (
          ageSeconds != null &&
          ageSeconds > staleMinutes * 60
        ) {
          return { ok: false, reason: "stale" };
        }
      } else {
        return { ok: false, reason: "stale" };
      }

      if (
        !driverMatchesVehicleType(driverNode, eligibility.vehicle_type_id)
      ) {
        return { ok: false, reason: "vehicle_mismatch" };
      }

      return { ok: true, reason: null };
    };

    const listenNearbyDrivers = (ride) => {
      if (driversListener.value) {
        driversListener.value.off();
        driversListener.value = null;
      }
      nearbyDrivers.value = [];
      if (!database.value || !ride?.pick_lat || !ride?.pick_lng) return;
      const eligibility = detail.value?.driver_eligibility || null;
      const radius = Number(
        eligibility?.search_radius_km || ride.driver_search_radius || 30
      );
      const refPath = database.value.ref("drivers");
      driversListener.value = refPath;
      refPath.on("value", (snapshot) => {
        const offeredIds = new Set(
          (detail.value?.active_offers || []).map((o) => String(o.driver_id))
        );
        const rules = detail.value?.driver_eligibility || eligibility;
        const val = snapshot.val() || {};
        const list = [];
        Object.keys(val).forEach((key) => {
          const d = val[key] || {};
          // Driver app stores l as {0: lat, 1: lng} or array
          let lat;
          let lng;
          if (Array.isArray(d.l) && d.l.length >= 2) {
            lat = d.l[0];
            lng = d.l[1];
          } else if (d.l && typeof d.l === "object") {
            lat = d.l[0] ?? d.l["0"];
            lng = d.l[1] ?? d.l["1"];
          } else {
            lat = d.lat;
            lng = d.lng;
          }
          const dist = haversineKm(ride.pick_lat, ride.pick_lng, lat, lng);
          if (dist == null || dist > radius) return;
          const driverId = String(d.id || key.replace("driver_", ""));
          const check = isDriverEligibleForRide(driverId, d, rules);
          const isActive = d.is_active == 1 || d.is_active === true;
          const isAvailable = d.is_available == 1 || d.is_available === true;
          const ageSeconds = firebaseUpdatedAgeSeconds(d.updated_at);
          const offered = offeredIds.has(driverId);
          list.push({
            id: driverId,
            name: d.name || key,
            is_active: isActive,
            is_available: isAvailable,
            distance_km: dist,
            offered,
            ineligible_reason: check.ok ? null : check.reason,
            age_seconds: ageSeconds,
            age_label: formatAgeShort(ageSeconds),
            can_be_offered:
              check.ok && isActive && isAvailable && !offered,
          });
        });
        list.sort((a, b) => {
          if (a.offered !== b.offered) return a.offered ? -1 : 1;
          if (a.can_be_offered !== b.can_be_offered) {
            return a.can_be_offered ? -1 : 1;
          }
          return a.distance_km - b.distance_km;
        });
        nearbyDrivers.value = list.slice(0, 40);
      });
    };

    const listenFirebaseMeta = (requestId) => {
      if (metaListener.value) {
        metaListener.value.off();
        metaListener.value = null;
      }
      firebaseLiveMeta.value = null;
      if (!database.value || !requestId) return;
      const refPath = database.value.ref(`request-meta/${requestId}`);
      metaListener.value = refPath;
      refPath.on("value", (snapshot) => {
        firebaseLiveMeta.value = snapshot.val();
      });
    };

    const selectRide = async (id) => {
      selectedId.value = id;
      await fetchDetail(id);
      listenFirebaseMeta(id);
      if (detail.value?.ride) {
        listenNearbyDrivers(detail.value.ride);
      }
      if (detailPollTimer.value) clearInterval(detailPollTimer.value);
      detailPollTimer.value = setInterval(async () => {
        if (!selectedId.value) return;
        await fetchDetail(selectedId.value);
        if (detail.value?.ride) {
          listenNearbyDrivers(detail.value.ride);
        }
      }, 4000);
    };

    const forceReSearch = async () => {
      if (!selectedId.value || props.app_for === "demo") {
        if (props.app_for === "demo") {
          Swal.fire(t("error"), t("you_are_not_authorised"), "error");
        }
        return;
      }
      const confirm = await Swal.fire({
        title: "Force re-search?",
        text: "This clears current offers for this ride and runs dispatch again.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, re-search",
      });
      if (!confirm.isConfirmed) return;
      reSearching.value = true;
      try {
        const response = await axios.post(
          `/dispatch-monitor/${selectedId.value}/re-search`
        );
        Swal.fire(
          response.data.success ? "Done" : t("error"),
          response.data.message || "Re-search finished",
          response.data.success ? "success" : "error"
        );
        await fetchList(meta.value.current_page || 1);
        await fetchDetail(selectedId.value);
      } catch (e) {
        Swal.fire(
          t("error"),
          e?.response?.data?.message || "Failed to re-search",
          "error"
        );
      } finally {
        reSearching.value = false;
      }
    };

    const cancelRide = async () => {
      if (!selectedId.value) return;
      const confirm = await Swal.fire({
        title: "Cancel this search ride?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Cancel ride",
      });
      if (!confirm.isConfirmed) return;
      try {
        await axios.get(`/rides-request/cancel/${selectedId.value}`);
        Swal.fire(t("success"), t("trip_cancelled_successfully"), "success");
        clearDetailPanel();
        await fetchList(1);
      } catch (e) {
        Swal.fire(t("error"), t("failed_to_cancel_trip"), "error");
      }
    };

    onMounted(async () => {
      try {
        if (props.firebaseConfig?.apiKey && typeof firebase !== "undefined") {
          if (!firebase.apps.length) {
            firebase.initializeApp(props.firebaseConfig);
          }
          database.value = firebase.database();
        }
      } catch (e) {
        console.error("Firebase init failed", e);
      }

      // Wait for a user selection before listing / polling rides.
      pageReady.value = true;
      tickTimer.value = setInterval(() => {
        nowMs.value = Date.now();
      }, 1000);
    });

    onBeforeUnmount(() => {
      pageReady.value = false;
      stopListPolling();
      if (detailPollTimer.value) clearInterval(detailPollTimer.value);
      if (tickTimer.value) clearInterval(tickTimer.value);
      if (searchDebounce.value) clearTimeout(searchDebounce.value);
      if (metaListener.value) metaListener.value.off();
      if (driversListener.value) driversListener.value.off();
    });

    return {
      t,
      rides,
      meta,
      selectedId,
      detail,
      loadingList,
      loadingDetail,
      reSearching,
      search,
      transportType,
      selectedUserId,
      userOptions,
      onUserSearch,
      firebaseLiveMeta,
      nearbyDrivers,
      selectedRide,
      statusBadgeClass,
      toneClass,
      formatAge,
      formatAgeClock,
      liveSearchAgeSeconds,
      ageToneClass,
      ageLabel,
      formatClock,
      liveOfferTimers,
      timerToneClass,
      ringStyle,
      fetchList,
      onTransportChange,
      onSearchInput,
      selectRide,
      forceReSearch,
      cancelRide,
      eligibilityReasonLabel,
      acceptDuration: props.acceptDuration,
      serverTimeoutSeconds: props.serverTimeoutSeconds,
    };
  },
};
</script>

<template>
  <Layout>
    <Head :title="$t('dispatch-monitor')" />
    <PageHeader
      :title="$t('dispatch-monitor')"
      :pageTitle="$t('dispatch-monitor')"
    />

    <div class="alert alert-info py-2 px-3 mb-3 dispatch-monitor-hints">
      <div class="fw-semibold mb-1">
        <i class="ri-information-line me-1"></i
        >{{ $t("dispatch_monitor_hints_title") }}
      </div>
      <ul class="mb-0 ps-3 small">
        <li>{{ $t("dispatch_monitor_hint_why") }}</li>
        <li>{{ $t("dispatch_monitor_hint_how") }}</li>
        <li>{{ $t("dispatch_monitor_hint_offers") }}</li>
        <li>{{ $t("dispatch_monitor_hint_nearby") }}</li>
        <li>{{ $t("dispatch_monitor_hint_ineligible") }}</li>
        <li>{{ $t("dispatch_monitor_hint_actions") }}</li>
      </ul>
    </div>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <h5 class="mb-0 flex-grow-1">{{ $t("searching-rides") }}</h5>
            <span class="badge bg-primary">{{ meta.total || 0 }}</span>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label form-label-sm mb-1">Customer</label>
              <Multiselect
                v-model="selectedUserId"
                :close-on-select="true"
                :options="userOptions"
                :searchable="true"
                :internal-search="false"
                :clear-on-select="false"
                :can-clear="true"
                placeholder="Search name / mobile / email…"
                @search-change="onUserSearch"
              />
              <div class="form-text small">
                Choose a customer to load and listen to their searching rides.
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-7">
                <input
                  v-model="search"
                  type="text"
                  class="form-control form-control-sm"
                  :placeholder="$t('search')"
                  :disabled="!selectedUserId"
                  @input="onSearchInput"
                />
              </div>
              <div class="col-5">
                <select
                  v-model="transportType"
                  class="form-select form-select-sm"
                  :disabled="!selectedUserId"
                  @change="onTransportChange"
                >
                  <option value="all">All types</option>
                  <option value="taxi">Taxi</option>
                  <option value="delivery">Delivery</option>
                </select>
              </div>
            </div>

            <div
              v-if="!selectedUserId"
              class="text-muted small text-center py-4"
            >
              Select a customer to start monitoring their dispatch.
            </div>
            <div
              v-else-if="loadingList && !rides.length"
              class="text-muted small"
            >
              Loading…
            </div>
            <div
              v-else-if="!rides.length"
              class="text-muted small text-center py-4"
            >
              No searching rides for this customer right now.
            </div>
            <div class="list-group list-group-flush dispatch-monitor-list">
              <button
                v-for="ride in rides"
                :key="ride.id"
                type="button"
                class="list-group-item list-group-item-action px-0"
                :class="{ active: selectedId === ride.id }"
                @click="selectRide(ride.id)"
              >
                <div class="d-flex justify-content-between gap-2">
                  <div>
                    <div class="fw-semibold">{{ ride.request_number }}</div>
                    <div class="small opacity-75 text-truncate" style="max-width: 220px">
                      {{ ride.pick_address || "—" }}
                    </div>
                    <div class="small mt-1">
                      <span class="badge" :class="statusBadgeClass(ride.status)">
                        {{ ride.status_label }}
                      </span>
                      <span class="ms-1 text-uppercase">{{ ride.transport_type }}</span>
                      <span
                        v-if="ride.trip_dispatch_type_label"
                        class="ms-1 badge bg-light text-dark"
                        >{{ ride.trip_dispatch_type_label }}</span
                      >
                    </div>
                  </div>
                  <div class="text-end small">
                    <div
                      class="age-chip"
                      :class="ageToneClass(liveSearchAgeSeconds(ride))"
                    >
                      <span class="age-chip-clock">{{
                        formatAgeClock(liveSearchAgeSeconds(ride))
                      }}</span>
                      <span class="age-chip-label">age</span>
                    </div>
                    <div class="mt-1 text-muted">
                      Offers: {{ ride.active_offer_count }}
                    </div>
                    <div class="text-muted">
                      Tries: {{ ride.attempt_for_schedule }}
                    </div>
                  </div>
                </div>
              </button>
            </div>

            <div
              v-if="meta.last_page > 1"
              class="d-flex justify-content-between align-items-center mt-3"
            >
              <button
                class="btn btn-sm btn-light"
                :disabled="meta.current_page <= 1"
                @click="fetchList(meta.current_page - 1)"
              >
                Prev
              </button>
              <span class="small"
                >{{ meta.current_page }} / {{ meta.last_page }}</span
              >
              <button
                class="btn btn-sm btn-light"
                :disabled="meta.current_page >= meta.last_page"
                @click="fetchList(meta.current_page + 1)"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card min-vh-75">
          <div class="card-header d-flex flex-wrap gap-2 align-items-center">
            <h5 class="mb-0 flex-grow-1">
              {{ $t("dispatch-detail") }}
              <span v-if="detail?.ride" class="text-muted fs-6">
                — {{ detail.ride.request_number }}
              </span>
            </h5>
            <template v-if="detail?.ride">
              <a
                class="btn btn-sm btn-outline-primary"
                :href="detail.links.assign"
                target="_blank"
                >Assign</a
              >
              <a
                class="btn btn-sm btn-outline-secondary"
                :href="detail.links.view"
                target="_blank"
                >View trip</a
              >
              <button
                class="btn btn-sm btn-warning"
                :disabled="reSearching"
                @click="forceReSearch"
              >
                {{ reSearching ? "Searching…" : "Force re-search" }}
              </button>
              <button class="btn btn-sm btn-danger" @click="cancelRide">
                Cancel
              </button>
            </template>
          </div>

          <div class="card-body">
            <div v-if="!selectedId" class="text-muted text-center py-5">
              Select a searching ride to inspect dispatch.
              <div class="small mt-2">
                Detail clears automatically when a ride is accepted or cancelled.
              </div>
            </div>
            <div v-else-if="loadingDetail && !detail" class="text-muted">
              Loading detail…
            </div>
            <div v-else-if="detail" class="row g-3">
              <div class="col-12">
                <div class="border rounded p-3 bg-light-subtle">
                  <div class="row g-2 small">
                    <div class="col-md-6">
                      <strong>User:</strong>
                      {{ detail.ride.user_name || "—" }}
                      <span v-if="detail.ride.user_mobile"
                        >({{ detail.ride.user_mobile }})</span
                      >
                    </div>
                    <div class="col-md-3">
                      <strong>Type:</strong>
                      {{ detail.ride.transport_type }}
                      <span v-if="detail.ride.is_later" class="badge bg-info ms-1"
                        >Later</span
                      >
                    </div>
                    <div class="col-md-3">
                      <strong class="d-block mb-1">Age</strong>
                      <div
                        class="age-panel"
                        :class="
                          ageToneClass(liveSearchAgeSeconds(detail.ride))
                        "
                      >
                        <div class="age-panel-clock">
                          {{
                            formatAgeClock(liveSearchAgeSeconds(detail.ride))
                          }}
                        </div>
                        <div class="age-panel-meta">
                          <span class="age-panel-badge">{{
                            ageLabel(liveSearchAgeSeconds(detail.ride))
                          }}</span>
                          <span class="age-panel-human">{{
                            formatAge(liveSearchAgeSeconds(detail.ride))
                          }}</span>
                          searching
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <strong>Pickup:</strong> {{ detail.ride.pick_address }}
                    </div>
                    <div class="col-md-6">
                      <strong>Drop:</strong> {{ detail.ride.drop_address || "—" }}
                    </div>
                    <div class="col-md-3">
                      <strong>Attempts:</strong>
                      {{ detail.ride.attempt_for_schedule }}
                    </div>
                    <div class="col-md-3">
                      <strong>Dispatch:</strong>
                      <span class="badge bg-dark-subtle text-dark ms-1">
                        {{
                          detail.ride.trip_dispatch_type_label || "One By One"
                        }}
                      </span>
                    </div>
                    <div class="col-md-3">
                      <strong>Assign:</strong>
                      <span class="badge bg-primary-subtle text-primary ms-1">
                        {{
                          detail.ride.assign_method_label || "Automatic Assign"
                        }}
                      </span>
                    </div>
                    <div class="col-md-3">
                      <strong>Search radius:</strong>
                      {{ detail.ride.driver_search_radius }} km
                    </div>
                    <div class="col-md-6">
                      <strong>Timer windows:</strong>
                      App accept
                      <span class="text-primary fw-semibold"
                        >{{ detail.accept_duration }}s</span
                      >
                      · Server
                      <span class="text-danger fw-semibold"
                        >{{ detail.server_timeout_seconds }}s</span
                      >
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <h6 class="mb-2">Active offers (MySQL)</h6>
                <div
                  v-if="!detail.active_offers?.length"
                  class="small text-muted border rounded p-3"
                >
                  No active RequestMeta rows. Ride may be waiting for the next
                  search cycle.
                </div>
                <template
                  v-for="offer in detail.active_offers"
                  :key="offer.meta_id"
                >
                  <div
                    v-for="timers in [liveOfferTimers(offer)]"
                    :key="'t-' + offer.meta_id"
                    class="offer-card border rounded p-3 mb-3"
                    :class="{
                      'border-success': timers.state === 'waiting',
                      'border-warning': timers.state === 'past_app_timer',
                      'border-danger': timers.state === 'likely_timed_out',
                    }"
                  >
                    <div class="d-flex justify-content-between gap-2 mb-3">
                      <div>
                        <div class="fw-semibold">{{ offer.driver_name }}</div>
                        <div class="small text-muted">
                          #{{ offer.driver_id }}
                          <span v-if="offer.driver_mobile"
                            >· {{ offer.driver_mobile }}</span
                          >
                        </div>
                        <div class="small mt-1">
                          <span class="badge bg-secondary-subtle text-secondary">
                            {{
                              offer.assign_method_label ||
                              detail.ride.trip_dispatch_type_label
                            }}
                          </span>
                          <span class="ms-1 text-muted">
                            {{
                              offer.distance_to_pickup != null
                                ? Number(offer.distance_to_pickup).toFixed(2) +
                                  " km"
                                : "—"
                            }}
                          </span>
                        </div>
                      </div>
                      <span
                        class="badge align-self-start text-uppercase"
                        :class="{
                          'bg-success': timers.state === 'waiting',
                          'bg-warning text-dark':
                            timers.state === 'past_app_timer',
                          'bg-danger': timers.state === 'likely_timed_out',
                        }"
                        >{{ timers.stateLabel }}</span
                      >
                    </div>

                    <div class="timer-row">
                      <div
                        class="timer-ring"
                        :class="
                          timerToneClass(
                            timers.acceptRemaining,
                            timers.acceptWindow
                          )
                        "
                        :style="
                          ringStyle(
                            timers.acceptPct,
                            timerToneClass(
                              timers.acceptRemaining,
                              timers.acceptWindow
                            )
                          )
                        "
                      >
                        <div class="timer-ring-inner">
                          <div class="timer-clock">
                            {{ formatClock(timers.acceptRemaining) }}
                          </div>
                          <div class="timer-caption">App</div>
                        </div>
                      </div>
                      <div
                        class="timer-ring"
                        :class="
                          timerToneClass(
                            timers.serverRemaining,
                            timers.serverWindow
                          )
                        "
                        :style="
                          ringStyle(
                            timers.serverPct,
                            timerToneClass(
                              timers.serverRemaining,
                              timers.serverWindow
                            )
                          )
                        "
                      >
                        <div class="timer-ring-inner">
                          <div class="timer-clock">
                            {{ formatClock(timers.serverRemaining) }}
                          </div>
                          <div class="timer-caption">Server</div>
                        </div>
                      </div>
                      <div class="timer-meta small text-muted">
                        <div>
                          Offered
                          {{
                            offer.offered_at_display || offer.offered_at || "—"
                          }}
                        </div>
                        <div>Elapsed {{ formatAge(timers.elapsed) }}</div>
                        <div class="mt-2">
                          <div class="d-flex justify-content-between">
                            <span>App window</span>
                            <span
                              >{{ timers.acceptRemaining }}s /
                              {{ timers.acceptWindow }}s</span
                            >
                          </div>
                          <div class="progress progress-thin mb-2">
                            <div
                              class="progress-bar"
                              :class="{
                                'bg-primary':
                                  timers.acceptRemaining >
                                  timers.acceptWindow * 0.4,
                                'bg-warning':
                                  timers.acceptRemaining <=
                                    timers.acceptWindow * 0.4 &&
                                  timers.acceptRemaining > 0,
                                'bg-secondary': timers.acceptRemaining <= 0,
                              }"
                              role="progressbar"
                              :style="{ width: timers.acceptPct + '%' }"
                            ></div>
                          </div>
                          <div class="d-flex justify-content-between">
                            <span>Server timeout</span>
                            <span
                              >{{ timers.serverRemaining }}s /
                              {{ timers.serverWindow }}s</span
                            >
                          </div>
                          <div class="progress progress-thin">
                            <div
                              class="progress-bar"
                              :class="{
                                'bg-success':
                                  timers.serverRemaining >
                                  timers.serverWindow * 0.4,
                                'bg-danger':
                                  timers.serverRemaining <=
                                    timers.serverWindow * 0.4 &&
                                  timers.serverRemaining > 0,
                                'bg-secondary': timers.serverRemaining <= 0,
                              }"
                              role="progressbar"
                              :style="{ width: timers.serverPct + '%' }"
                            ></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>
              </div>

              <div class="col-md-6">
                <h6 class="mb-2">Firebase request-meta (live)</h6>
                <pre
                  class="small border rounded p-3 bg-dark text-white"
                  style="max-height: 220px; overflow: auto"
                  >{{
                    firebaseLiveMeta
                      ? JSON.stringify(firebaseLiveMeta, null, 2)
                      : detail.firebase_meta
                        ? JSON.stringify(detail.firebase_meta, null, 2)
                        : "No Firebase meta node"
                  }}</pre
                >

                <h6 class="mb-2 mt-3">Rejected drivers</h6>
                <div
                  v-if="!detail.rejected?.length"
                  class="small text-muted border rounded p-3"
                >
                  No rejects yet.
                </div>
                <div
                  v-for="row in detail.rejected"
                  :key="row.id"
                  class="border border-danger rounded p-2 mb-2 small"
                >
                  <strong>{{ row.driver_name }}</strong> (#{{ row.driver_id }})
                  <div>{{ row.created_at }}</div>
                  <div v-if="row.reason || row.custom_reason">
                    {{ row.custom_reason || row.reason }}
                  </div>
                </div>
              </div>

              <div class="col-12">
                <h6 class="mb-2">
                  Eligible drivers nearby
                  <span
                    v-if="detail.driver_eligibility"
                    class="small text-muted fw-normal"
                  >
                    ·
                    {{
                      detail.driver_eligibility.vehicle_type_name ||
                      detail.ride.vehicle_type_name ||
                      "vehicle match"
                    }}
                    · within
                    {{ detail.driver_eligibility.search_radius_km }} km
                  </span>
                </h6>
                <div
                  v-if="!nearbyDrivers.length"
                  class="small text-muted border rounded p-3"
                >
                  No drivers found near pickup right now (outside radius or no
                  live Firebase location).
                </div>
                <div v-else class="table-responsive">
                  <table class="table table-sm table-striped align-middle mb-0">
                    <thead>
                      <tr>
                        <th>Driver</th>
                        <th>Distance</th>
                        <th>Online</th>
                        <th>Availability</th>
                        <th>Freshness</th>
                        <th>Offer status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="d in nearbyDrivers"
                        :key="d.id"
                        :class="{
                          'table-success': d.offered,
                          'table-warning': !d.offered && !d.can_be_offered,
                        }"
                      >
                        <td>{{ d.name }} (#{{ d.id }})</td>
                        <td>{{ d.distance_km.toFixed(2) }} km</td>
                        <td>
                          <span
                            class="badge"
                            :class="d.is_active ? 'bg-success' : 'bg-secondary'"
                            >{{ d.is_active ? "Online" : "Offline" }}</span
                          >
                        </td>
                        <td>
                          <span
                            class="badge"
                            :class="
                              d.is_available
                                ? 'bg-success'
                                : 'bg-danger'
                            "
                            :title="
                              d.is_available
                                ? 'Driver is free to take a ride'
                                : 'Firebase is_available is false — cannot receive regular offers'
                            "
                            >{{
                              d.is_available ? "Free" : "Unavailable"
                            }}</span
                          >
                        </td>
                        <td>
                          <span
                            class="small"
                            :class="
                              d.ineligible_reason === 'stale'
                                ? 'text-danger'
                                : 'text-muted'
                            "
                            >{{ d.age_label }}</span
                          >
                        </td>
                        <td>
                          <span
                            class="badge"
                            :class="
                              d.offered
                                ? 'bg-primary'
                                : d.can_be_offered
                                  ? 'bg-success-subtle text-success'
                                  : 'bg-light text-dark'
                            "
                            >{{
                              d.offered
                                ? "Offered now"
                                : d.can_be_offered
                                  ? "Eligible to offer"
                                  : eligibilityReasonLabel(d.ineligible_reason)
                            }}</span
                          >
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="col-12">
                <h6 class="mb-2">Dispatch timeline</h6>
                <div class="timeline-list">
                  <div
                    v-for="(event, idx) in detail.timeline"
                    :key="idx"
                    class="border-start border-3 ps-3 mb-3"
                    :class="toneClass(event.tone)"
                  >
                    <div class="fw-semibold">{{ event.label }}</div>
                    <div class="small text-muted">
                      {{ event.at || "—" }} · {{ event.type }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Layout>
</template>

<style scoped>
.dispatch-monitor-list {
  max-height: 65vh;
  overflow: auto;
}
.min-vh-75 {
  min-height: 75vh;
}
.list-group-item.active {
  background-color: rgba(13, 110, 253, 0.08);
  color: inherit;
  border-color: rgba(13, 110, 253, 0.25);
}
.offer-card {
  background: linear-gradient(180deg, rgba(13, 110, 253, 0.03), transparent);
}
.age-chip {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 64px;
  padding: 0.2rem 0.45rem;
  border-radius: 0.5rem;
  background: rgba(13, 110, 253, 0.08);
  line-height: 1.1;
}
.age-chip-clock {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  font-size: 0.95rem;
  letter-spacing: 0.02em;
}
.age-chip-label {
  font-size: 0.62rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6c757d;
}
.age-chip.is-fresh .age-chip-clock {
  color: #198754;
}
.age-chip.is-aging .age-chip-clock {
  color: #0d6efd;
}
.age-chip.is-warn .age-chip-clock {
  color: #fd7e14;
}
.age-chip.is-critical {
  background: rgba(220, 53, 69, 0.08);
}
.age-chip.is-critical .age-chip-clock {
  color: #dc3545;
  animation: timer-pulse 1s ease-in-out infinite;
}
.age-panel {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.55rem 0.75rem;
  border-radius: 0.75rem;
  background: #fff;
  border: 1px solid rgba(0, 0, 0, 0.06);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.age-panel-clock {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  font-size: 1.35rem;
  letter-spacing: 0.02em;
  line-height: 1;
  min-width: 4.5rem;
}
.age-panel-meta {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  color: #6c757d;
  font-size: 0.75rem;
}
.age-panel-badge {
  display: inline-flex;
  align-self: flex-start;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  background: rgba(25, 135, 84, 0.12);
  color: #198754;
}
.age-panel-human {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  color: #495057;
}
.age-panel.is-fresh .age-panel-clock {
  color: #198754;
}
.age-panel.is-aging .age-panel-clock {
  color: #0d6efd;
}
.age-panel.is-aging .age-panel-badge {
  background: rgba(13, 110, 253, 0.12);
  color: #0d6efd;
}
.age-panel.is-warn .age-panel-clock {
  color: #fd7e14;
}
.age-panel.is-warn .age-panel-badge {
  background: rgba(253, 126, 20, 0.14);
  color: #fd7e14;
}
.age-panel.is-critical .age-panel-clock {
  color: #dc3545;
  animation: timer-pulse 1s ease-in-out infinite;
}
.age-panel.is-critical .age-panel-badge {
  background: rgba(220, 53, 69, 0.12);
  color: #dc3545;
}
.timer-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
}
.timer-ring {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.04);
  transition: background 0.35s ease;
}
.timer-ring-inner {
  width: 68px;
  height: 68px;
  border-radius: 50%;
  background: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
}
.timer-clock {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  font-size: 0.95rem;
  line-height: 1.1;
  letter-spacing: 0.02em;
}
.timer-caption {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6c757d;
  margin-top: 2px;
}
.timer-ring.is-ok .timer-clock {
  color: #0d6efd;
}
.timer-ring.is-warn .timer-clock {
  color: #fd7e14;
}
.timer-ring.is-critical .timer-clock {
  color: #dc3545;
  animation: timer-pulse 1s ease-in-out infinite;
}
.timer-ring.is-expired .timer-clock {
  color: #adb5bd;
}
.timer-meta {
  flex: 1 1 160px;
  min-width: 140px;
}
.progress-thin {
  height: 6px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.06);
}
.progress-thin .progress-bar {
  border-radius: 999px;
  transition: width 0.9s linear;
}
@keyframes timer-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.55;
  }
}
</style>
