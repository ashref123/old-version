<template>
  <div class="card border">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Fare Breakdown — Estimated vs Final</h5>
        <span v-if="fareDiff?.has_both" class="badge" :class="fareDiff.total_delta > 0 ? 'bg-danger' : (fareDiff.total_delta < 0 ? 'bg-success' : 'bg-secondary')">
          Δ {{ symbol }}{{ fareDiff.total_delta }}
        </span>
      </div>

      <div v-if="!estimated && !final" class="text-muted">No fare data available yet.</div>

      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Component</th>
              <th class="text-end">Estimated</th>
              <th class="text-end">Final</th>
              <th class="text-end">Δ</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.key">
              <td>
                <div class="fw-medium">{{ row.label }}</div>
                <div v-if="row.hint" class="text-muted small">{{ row.hint }}</div>
              </td>
              <td class="text-end">{{ fmt(estimated?.[row.key], row.key) }}</td>
              <td class="text-end">{{ fmt(final?.[row.key], row.key) }}</td>
              <td class="text-end" :class="deltaClass(estimated?.[row.key], final?.[row.key])">
                {{ delta(estimated?.[row.key], final?.[row.key]) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="fareDiff?.reasons?.length" class="mt-3">
        <div class="fw-semibold mb-1">Why the amount changed</div>
        <ul class="mb-0 small">
          <li v-for="(reason, idx) in fareDiff.reasons" :key="idx">{{ reason }}</li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RideFareComparison',
  props: {
    estimated: { type: Object, default: null },
    final: { type: Object, default: null },
    fareDiff: { type: Object, default: null },
    symbol: { type: String, default: '' },
  },
  computed: {
    rows() {
      const f = this.final || this.estimated || {};
      const unit = f.unit || 'KM';
      return [
        { key: 'base_price', label: 'Base Fare', hint: f.base_distance != null ? `First ${f.base_distance} ${unit}` : null },
        { key: 'distance_price', label: 'Distance Charge', hint: f.billable_distance != null ? `${f.billable_distance} × ${this.symbol}${f.price_per_distance}` : null },
        { key: 'time_price', label: 'Time Charge', hint: f.total_time != null ? `${Math.round(Number(f.total_time))} min × ${this.symbol}${f.price_per_time}` : null },
        { key: 'waiting_charge', label: 'Waiting Charge', hint: f.calculated_waiting_time != null ? `${Math.round(Number(f.calculated_waiting_time))} min × ${this.symbol}${f.waiting_charge_per_min}` : null },
        { key: 'airport_surge_fee', label: 'Airport Charge' },
        { key: 'preference_price_total', label: 'Preferences' },
        { key: 'cancellation_fee', label: 'Cancellation Fee' },
        { key: 'additional_charges_amount', label: 'Additional Charges', hint: f.additional_charges_reason },
        { key: 'driver_tips', label: 'Driver Tips' },
        { key: 'service_tax', label: 'Service Tax / GST', hint: f.service_tax_percentage != null ? `${f.service_tax_percentage}%` : null },
        { key: 'promo_discount', label: 'Promo Discount' },
        { key: 'admin_commision', label: 'Admin Commission' },
        { key: 'driver_commision', label: 'Driver Earnings' },
        { key: 'total_distance', label: 'Distance', hint: unit },
        { key: 'total_time', label: 'Duration' },
        { key: 'total_amount', label: 'Total' },
      ];
    },
  },
  methods: {
    fmt(v, key) {
      if (v === null || v === undefined || v === '') return '—';
      if (key === 'total_time' || key === 'calculated_waiting_time') {
        return `${Math.round(Number(v))} min`;
      }
      return `${this.symbol}${v}`;
    },
    delta(e, f) {
      if (e == null || f == null) return '—';
      const d = Number(f) - Number(e);
      if (Math.abs(d) < 0.0001) return '0';
      // Duration deltas in whole minutes
      if (Number.isInteger(Math.round(e)) && Number.isInteger(Math.round(f)) && Math.abs(d - Math.round(d)) < 0.001) {
        const di = Math.round(d);
        return `${di > 0 ? '+' : ''}${di}`;
      }
      return `${d > 0 ? '+' : ''}${d.toFixed(2)}`;
    },
    deltaClass(e, f) {
      if (e == null || f == null) return '';
      const d = Number(f) - Number(e);
      if (d > 0) return 'text-danger';
      if (d < 0) return 'text-success';
      return 'text-muted';
    },
  },
};
</script>
