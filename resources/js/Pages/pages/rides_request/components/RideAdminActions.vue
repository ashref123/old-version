<template>
  <div class="d-flex flex-wrap gap-2">
    <button v-if="actions.can_cancel" type="button" class="btn btn-sm btn-danger" @click="$emit('cancel')">Cancel</button>
    <a v-if="actions.can_track" :href="actions.track_url" class="btn btn-sm btn-outline-primary" target="_blank">Track</a>
    <a v-if="actions.can_assign" :href="actions.assign_url" class="btn btn-sm btn-outline-primary">Assign</a>
    <a v-if="actions.can_download_invoice && actions.invoice_url" :href="actions.invoice_url + '?invoice_type=user'" class="btn btn-sm btn-outline-success">Invoice</a>
    <a v-if="actions.report_url" :href="actions.report_url" class="btn btn-sm btn-outline-secondary">Ride Report</a>
    <a v-if="actions.export_pricing_url" :href="actions.export_pricing_url" class="btn btn-sm btn-outline-secondary">Export Pricing</a>
    <a v-if="actions.export_logs_url" :href="actions.export_logs_url" class="btn btn-sm btn-outline-secondary">Export Logs</a>
    <button type="button" class="btn btn-sm btn-outline-dark" @click="$emit('print')">Print</button>
    <button type="button" class="btn btn-sm btn-outline-dark" @click="$emit('copy-ride')">Copy Ride</button>
    <button type="button" class="btn btn-sm btn-outline-dark" @click="$emit('copy-pricing')">Copy Pricing</button>
    <button type="button" class="btn btn-sm btn-outline-dark" @click="$emit('toggle-raw')">Raw JSON</button>
  </div>
</template>

<script>
export default {
  name: 'RideAdminActions',
  props: {
    actions: { type: Object, default: () => ({}) },
  },
  emits: ['cancel', 'print', 'copy-ride', 'copy-pricing', 'toggle-raw'],
};
</script>
