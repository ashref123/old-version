<template>
  <div class="card border">
    <div class="card-body">
      <h5 class="mb-2">Pricing Engine Debug</h5>
      <div v-if="isReconstructed" class="alert alert-warning py-2 small">
        Pricing audit was not captured for this ride. Showing reconstructed values from stored amounts.
        Rule match details (surge window / peak zone) may be incomplete for historical rides.
      </div>
      <div v-if="!audit" class="text-muted">No pricing audit available.</div>
      <template v-else>
        <div class="row g-2 small mb-3">
          <div class="col-md-3"><span class="text-muted">Zone:</span> {{ audit.context?.zone_name || '—' }}</div>
          <div class="col-md-3"><span class="text-muted">Vehicle:</span> {{ audit.context?.vehicle_type || '—' }}</div>
          <div class="col-md-3"><span class="text-muted">Currency:</span> {{ audit.currency?.code }} {{ audit.currency?.symbol }}</div>
          <div class="col-md-3"><span class="text-muted">Phase:</span> {{ audit.phase }}</div>
        </div>

        <div class="mb-3">
          <div class="fw-semibold mb-2">Rules</div>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>Rule</th><th>Matched</th><th>Detail / Skip reason</th></tr></thead>
              <tbody>
                <tr v-for="(rule, idx) in (audit.rules || [])" :key="idx">
                  <td>{{ rule.rule }}</td>
                  <td>
                    <span class="badge" :class="rule.matched ? 'bg-success' : 'bg-secondary'">{{ rule.matched ? 'Yes' : 'No' }}</span>
                  </td>
                  <td class="small">
                    <pre v-if="rule.matched && rule.detail" class="mb-0 small">{{ formatJson(rule.detail) }}</pre>
                    <span v-else class="text-muted">{{ rule.skip_reason || '—' }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="accordion" id="pricingLinesAccordion">
          <div class="accordion-item" v-for="(line, idx) in (audit.lines || [])" :key="line.key || idx">
            <h2 class="accordion-header">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" :data-bs-target="'#pline-' + idx">
                <span class="me-2">{{ line.name }}</span>
                <span class="badge me-2" :class="line.applied ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'">
                  {{ line.applied ? 'Applied' : 'Skipped' }}
                </span>
                <span class="ms-auto me-3 small">{{ line.result != null ? line.result : '' }}</span>
              </button>
            </h2>
            <div :id="'pline-' + idx" class="accordion-collapse collapse" data-bs-parent="#pricingLinesAccordion">
              <div class="accordion-body small">
                <div class="mb-2"><strong>Configured</strong><pre class="mb-0">{{ formatJson(line.configured) }}</pre></div>
                <div class="mb-2"><strong>Formula</strong><div>{{ line.formula }}</div></div>
                <div class="mb-2"><strong>Calculation</strong><div>{{ line.calculation }}</div></div>
                <div class="mb-2"><strong>Result</strong><div>{{ line.result }}</div></div>
                <div v-if="line.skipped_reason" class="text-muted">Skipped: {{ line.skipped_reason }}</div>
                <div class="text-muted">Source: {{ line.source_module }}</div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RidePricingDebug',
  props: {
    audit: { type: Object, default: null },
    isReconstructed: { type: Boolean, default: false },
  },
  methods: {
    formatJson(v) {
      try {
        return JSON.stringify(v, null, 2);
      } catch (_) {
        return String(v);
      }
    },
  },
};
</script>
