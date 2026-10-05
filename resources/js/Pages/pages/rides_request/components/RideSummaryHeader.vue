<template>
  <div class="ride-sticky-header card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
      <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="mb-0">{{ overview.request_number }}</h4>
            <span class="badge" :class="statusClass">{{ statusLabel }}</span>
            <span class="badge bg-secondary-subtle text-secondary">{{ overview.booking_type }}</span>
            <span class="badge bg-info-subtle text-info" v-if="overview.is_surge_applied">Surge</span>
          </div>
          <div class="text-muted small">
            {{ overview.ride_type }} · {{ overview.service_type }} · {{ overview.zone || '-' }} · {{ overview.vehicle_type || '-' }}
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <slot name="actions" />
        </div>
      </div>
      <div class="row g-2 mt-3 small">
        <div class="col-6 col-md-3 col-xl-2" v-for="item in metaItems" :key="item.label">
          <div class="text-muted">{{ item.label }}</div>
          <div class="fw-semibold">{{ item.value || '—' }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RideSummaryHeader',
  props: {
    overview: { type: Object, required: true },
    rideStatus: { type: String, default: '' },
  },
  computed: {
    statusLabel() {
      return this.rideStatus || this.overview.status || '—';
    },
    statusClass() {
      const s = (this.overview.status || '').toLowerCase();
      if (s === 'completed') return 'bg-success';
      if (s === 'cancelled') return 'bg-danger';
      if (s === 'searching' || s === 'scheduled') return 'bg-warning text-dark';
      return 'bg-primary';
    },
    metaItems() {
      const o = this.overview || {};
      let cancelBy = null;

      if (o.cancel_method != null && o.cancel_method !== '') {
        const cm = String(o.cancel_method).toLowerCase();
        if (cm === '0' || cm === 'automatic') {
          cancelBy = 'System';
        } else if (cm === '1' || cm === 'user') {
          cancelBy = 'User';
        } else if (cm === '2' || cm === 'driver') {
          cancelBy = 'Driver';
        } else if (cm === '3' || cm === 'dispatcher') {
          cancelBy = 'Dispatcher';
        } else {
          cancelBy = o.cancel_method;
        }
      }

      const cancelReason = o.cancel_reason || o.custom_reason || o.reason || null;

      return [
        { label: 'Created', value: o.created_at },
        { label: 'Accepted', value: o.accepted_at },
        { label: 'Arrived', value: o.arrived_at },
        { label: 'Started', value: o.started_at },
        { label: 'Completed', value: o.completed_at },
        { label: 'Cancelled', value: o.cancelled_at },
        { label: 'Cancelled by', value: cancelBy },
        { label: 'Cancel reason', value: cancelReason },
        { label: 'Paid by', value: o.payment_party },
        { label: 'Duration', value: this.formatDuration(o.total_duration_mins) },
        { label: 'Waiting', value: this.formatMinutes(o.waiting_time_mins) },
        { label: 'Distance', value: o.total_distance != null ? `${o.total_distance} ${o.unit}` : null },
        { label: 'Driver rating', value: o.driver_rating },
        { label: 'Customer rating', value: o.customer_rating },
        { label: 'OTP', value: o.ride_otp },
      ];
    },
  },
  methods: {
    formatMinutes(mins) {
      if (mins == null || mins === '') return null;
      return `${Math.round(Number(mins))} min`;
    },
    /** Under 60 min → whole minutes; 60+ → hours with decimals. */
    formatDuration(mins) {
      if (mins == null || mins === '') return null;
      const m = Math.round(Number(mins));
      if (m < 60) return `${m} min`;
      const hours = Math.round((m / 60) * 100) / 100;
      return `${hours} hrs (${m} min)`;
    },
  },
};
</script>

<style scoped>
.ride-sticky-header {
  position: sticky;
  top: 70px;
  z-index: 20;
  background: var(--vz-secondary-bg, #fff);
}
</style>
