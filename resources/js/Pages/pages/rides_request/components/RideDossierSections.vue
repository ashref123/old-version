<template>
  <div>
    <div v-if="loading" class="alert alert-info">Loading ride dossier…</div>
    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-if="dossier">
      <RideSummaryHeader v-if="dossier.overview" :overview="dossier.overview" :ride-status="rideStatus">
        <template #actions>
          <RideAdminActions
            :actions="dossier.actions || {}"
            @cancel="$emit('cancel')"
            @print="$emit('print')"
            @copy-ride="$emit('copy-ride')"
            @copy-pricing="$emit('copy-pricing')"
            @toggle-raw="$emit('toggle-raw')"
          />
        </template>
      </RideSummaryHeader>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <RideCustomerCard :customer="dossier.customer" :mask="mask" />
        </div>
        <div class="col-md-6">
          <RideDriverCard :driver="dossier.driver" :mask="mask" />
        </div>
      </div>

      <div class="card border mb-3" v-if="dossier.locations">
        <div class="card-body">
          <h5 class="mb-3">Trip Locations</h5>
          <div class="row g-3 small">
            <div class="col-md-6">
              <div class="fw-semibold">Pickup</div>
              <div>{{ dossier.locations.pickup?.address || '—' }}</div>
              <div class="text-muted">{{ dossier.locations.pickup?.lat }}, {{ dossier.locations.pickup?.lng }}</div>
            </div>
            <div class="col-md-6">
              <div class="fw-semibold">Drop</div>
              <div>{{ dossier.locations.drop?.address || '—' }}</div>
              <div class="text-muted">{{ dossier.locations.drop?.lat }}, {{ dossier.locations.drop?.lng }}</div>
            </div>
            <div class="col-12" v-if="dossier.locations.stops?.length">
              <div class="fw-semibold">Stops</div>
              <ol class="mb-0">
                <li v-for="(stop, i) in dossier.locations.stops" :key="i">{{ stop.address }}</li>
              </ol>
            </div>
            <div class="col-md-4"><span class="text-muted">Estimated distance:</span> {{ dossier.locations.estimated_distance ?? '—' }}</div>
            <div class="col-md-4"><span class="text-muted">Actual distance:</span> {{ dossier.locations.actual_distance ?? '—' }}</div>
            <div class="col-md-4"><span class="text-muted">Route path:</span> {{ dossier.locations.request_path ? 'Available' : (dossier.locations.poly_line ? 'Polyline only' : '—') }}</div>
          </div>
        </div>
      </div>

      <div class="accordion ride-dossier-accordion mb-3" id="rideDossierAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rd-fare">Fare Breakdown</button>
          </h2>
          <div id="rd-fare" class="accordion-collapse collapse show" data-bs-parent="#rideDossierAccordion">
            <div class="accordion-body">
              <RideFareComparison
                :estimated="dossier.estimated_fare"
                :final="dossier.final_fare"
                :fare-diff="dossier.fare_diff"
                :symbol="dossier.overview?.currency_symbol || ''"
              />
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rd-pricing">Pricing Engine Debug</button>
          </h2>
          <div id="rd-pricing" class="accordion-collapse collapse" data-bs-parent="#rideDossierAccordion">
            <div class="accordion-body">
              <RidePricingDebug :audit="pricingAudit" :is-reconstructed="isReconstructed" />
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rd-timeline">Ride Timeline</button>
          </h2>
          <div id="rd-timeline" class="accordion-collapse collapse" data-bs-parent="#rideDossierAccordion">
            <div class="accordion-body">
              <RideTimeline :events="dossier.timeline || []" />
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rd-payment">Payment</button>
          </h2>
          <div id="rd-payment" class="accordion-collapse collapse" data-bs-parent="#rideDossierAccordion">
            <div class="accordion-body">
              <RidePaymentPanel :payment="dossier.payment" />
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rd-logs">Logs & Audit</button>
          </h2>
          <div id="rd-logs" class="accordion-collapse collapse" data-bs-parent="#rideDossierAccordion">
            <div class="accordion-body">
              <RideAuditLog :logs="dossier.logs" :rejected-drivers="dossier.rejected_drivers || []" />
            </div>
          </div>
        </div>

        <div class="accordion-item">
          <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rd-settings">Applied Settings</button>
          </h2>
          <div id="rd-settings" class="accordion-collapse collapse" data-bs-parent="#rideDossierAccordion">
            <div class="accordion-body">
              <RideAppliedSettings :settings="dossier.applied_settings" />
            </div>
          </div>
        </div>
      </div>

      <div v-if="dossier.ratings?.length" class="card border mb-3">
        <div class="card-body">
          <h5>Ratings</h5>
          <div v-for="(r, i) in dossier.ratings" :key="i" class="small mb-1">
            {{ r.user_rating ? 'Customer → Driver' : 'Driver → Customer' }}: {{ r.rating }}
            <span v-if="r.comment"> — {{ r.comment }}</span>
          </div>
        </div>
      </div>

      <div v-if="dossier.proofs?.length" class="card border mb-3">
        <div class="card-body">
          <h5>Request Proof</h5>
          <div class="d-flex flex-wrap gap-2">
            <img v-for="(p, i) in dossier.proofs" :key="i" :src="p.proof_image" alt="proof" style="max-width:120px;max-height:120px;object-fit:cover" class="rounded border" />
          </div>
        </div>
      </div>

      <div v-if="showRawJson" class="card border mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Raw JSON / API Response</h5>
            <button type="button" class="btn btn-sm btn-light" @click="$emit('toggle-raw')">Close</button>
          </div>
          <pre class="small bg-light p-3 rounded" style="max-height:480px;overflow:auto">{{ prettyRaw }}</pre>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import RideSummaryHeader from './RideSummaryHeader.vue';
import RideAdminActions from './RideAdminActions.vue';
import RideCustomerCard from './RideCustomerCard.vue';
import RideDriverCard from './RideDriverCard.vue';
import RideFareComparison from './RideFareComparison.vue';
import RidePricingDebug from './RidePricingDebug.vue';
import RideTimeline from './RideTimeline.vue';
import RidePaymentPanel from './RidePaymentPanel.vue';
import RideAuditLog from './RideAuditLog.vue';
import RideAppliedSettings from './RideAppliedSettings.vue';

export default {
  name: 'RideDossierSections',
  components: {
    RideSummaryHeader,
    RideAdminActions,
    RideCustomerCard,
    RideDriverCard,
    RideFareComparison,
    RidePricingDebug,
    RideTimeline,
    RidePaymentPanel,
    RideAuditLog,
    RideAppliedSettings,
  },
  props: {
    dossier: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    rideStatus: { type: String, default: '' },
    mask: { type: Boolean, default: false },
    pricingAudit: { type: Object, default: null },
    isReconstructed: { type: Boolean, default: false },
    showRawJson: { type: Boolean, default: false },
  },
  emits: ['cancel', 'print', 'copy-ride', 'copy-pricing', 'toggle-raw'],
  computed: {
    prettyRaw() {
      try {
        return JSON.stringify(this.dossier?.raw || this.dossier, null, 2);
      } catch (_) {
        return '';
      }
    },
  },
};
</script>

<style>
@media print {
  .ride-sticky-header,
  .btn,
  .accordion-button,
  .no-print {
    display: none !important;
  }
  .accordion-collapse {
    display: block !important;
    height: auto !important;
  }
}
</style>
