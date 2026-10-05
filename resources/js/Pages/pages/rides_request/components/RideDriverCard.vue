<template>
  <div v-if="driver" class="card border h-100">
    <div class="card-body">
      <h5 class="card-title mb-3">Driver</h5>
      <div class="d-flex align-items-center gap-3 mb-3">
        <img v-if="driver.profile_picture" :src="driver.profile_picture" class="rounded-circle" width="48" height="48" alt="" />
        <div>
          <div class="fw-semibold">{{ driver.name }}</div>
          <div class="text-muted small">
            {{ driver.status?.available ? 'Available' : 'Unavailable' }}
            · {{ driver.status?.approve ? 'Approved' : 'Not approved' }}
          </div>
        </div>
      </div>
      <div class="small mb-1"><span class="text-muted">Phone:</span> {{ mask ? '**********' : (driver.mobile || '—') }}</div>
      <div class="small mb-1"><span class="text-muted">Email:</span> {{ mask ? '**********' : (driver.email || '—') }}</div>
      <div class="small mb-1"><span class="text-muted">Vehicle:</span>
        {{ [driver.vehicle?.car_make, driver.vehicle?.car_model, driver.vehicle?.car_color, driver.vehicle?.car_number].filter(Boolean).join(' · ') || '—' }}
      </div>
      <div class="small mb-1"><span class="text-muted">Wallet:</span> {{ driver.wallet_balance ?? '—' }}</div>
      <div class="small mb-1"><span class="text-muted">Earnings (ride):</span> {{ driver.earnings_this_ride ?? '—' }}</div>
      <div class="small mb-1"><span class="text-muted">Admin commission:</span> {{ driver.admin_commission_this_ride ?? '—' }}
        <span v-if="driver.commission_percentage != null">(cfg {{ driver.commission_percentage }}{{ driver.commission_type == 1 ? '%' : '' }})</span>
      </div>
      <div class="small mb-1"><span class="text-muted">Acceptance rate:</span> {{ driver.acceptance_rate ?? '—' }}%</div>
      <div class="small mb-1"><span class="text-muted">Reject rate:</span> {{ driver.cancellation_rate ?? '—' }}%</div>
      <div class="small"><span class="text-muted">Documents:</span>
        {{ driver.documents?.approved ?? 0 }}/{{ driver.documents?.total ?? 0 }} approved
      </div>
    </div>
  </div>
  <div v-else class="card border h-100"><div class="card-body text-muted">No driver assigned</div></div>
</template>

<script>
export default {
  name: 'RideDriverCard',
  props: {
    driver: { type: Object, default: null },
    mask: { type: Boolean, default: false },
  },
};
</script>
