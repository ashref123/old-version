<template>
  <div v-if="customer" class="card border h-100">
    <div class="card-body">
      <h5 class="card-title mb-3">Customer</h5>
      <div class="d-flex align-items-center gap-3 mb-3">
        <img v-if="customer.profile_picture" :src="customer.profile_picture" class="rounded-circle" width="48" height="48" alt="" />
        <div>
          <div class="fw-semibold">{{ customer.name }}</div>
          <div class="text-muted small">{{ customer.customer_type }} · {{ customer.loyalty_status }}</div>
        </div>
      </div>
      <div class="small mb-1"><span class="text-muted">Phone:</span> {{ mask ? '**********' : (customer.mobile || '—') }}</div>
      <div class="small mb-1"><span class="text-muted">Email:</span> {{ mask ? '**********' : (customer.email || '—') }}</div>
      <div class="small mb-1"><span class="text-muted">Wallet:</span> {{ customer.wallet_balance ?? '—' }}</div>
      <div class="small mb-1"><span class="text-muted">Reward points:</span> {{ customer.reward_points ?? 0 }}</div>
      <div class="small mb-1"><span class="text-muted">Previous rides:</span> {{ customer.previous_ride_count ?? 0 }}</div>
      <div class="small"><span class="text-muted">Cancellations:</span> {{ customer.cancellation_count ?? 0 }}</div>
    </div>
  </div>
  <div v-else class="card border h-100"><div class="card-body text-muted">No customer data</div></div>
</template>

<script>
export default {
  name: 'RideCustomerCard',
  props: {
    customer: { type: Object, default: null },
    mask: { type: Boolean, default: false },
  },
};
</script>
