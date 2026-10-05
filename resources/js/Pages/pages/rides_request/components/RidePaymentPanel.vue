<template>
  <div class="card border">
    <div class="card-body">
      <h5 class="mb-3">Payment Information</h5>
      <div v-if="!payment" class="text-muted">No payment data.</div>
      <template v-else>
        <div class="row g-2 small mb-3">
          <div class="col-md-3"><span class="text-muted">Method:</span> {{ payment.payment_method }}</div>
          <div class="col-md-3"><span class="text-muted">Status:</span>
            <span class="badge" :class="payment.is_paid ? 'bg-success' : 'bg-warning text-dark'">{{ payment.payment_status }}</span>
          </div>
          <div class="col-md-3"><span class="text-muted">Paid at:</span> {{ payment.paid_at || '—' }}</div>
          <div class="col-md-3" v-if="payment.payment_party"><span class="text-muted">Paid by:</span> {{ payment.payment_party }}</div>
          <div class="col-md-3"><span class="text-muted">Txn / Intent:</span> {{ payment.payment_intent_id || '—' }}</div>
          <div class="col-md-3"><span class="text-muted">Customer paid:</span> {{ payment.customer_paid ?? '—' }}</div>
          <div class="col-md-3"><span class="text-muted">Driver earnings:</span> {{ payment.driver_earnings ?? '—' }}</div>
          <div class="col-md-3"><span class="text-muted">Admin commission:</span> {{ payment.admin_commission ?? '—' }}</div>
          <div class="col-md-3"><span class="text-muted">Tips:</span> {{ payment.driver_tips ?? '—' }}</div>
        </div>

        <div v-if="payment.transactions?.length" class="mb-3">
          <div class="fw-semibold mb-1">Gateway transactions</div>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>ID</th><th>Amount</th><th>Status</th><th>When</th></tr></thead>
              <tbody>
                <tr v-for="t in payment.transactions" :key="t.id">
                  <td class="small">{{ t.id }}</td>
                  <td>{{ t.amount }} {{ t.currency }}</td>
                  <td>{{ t.status }} / {{ t.is_paid ? 'paid' : 'unpaid' }}</td>
                  <td>{{ t.created_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="payment.user_wallet_history?.length" class="mb-2">
          <div class="fw-semibold mb-1">Wallet usage</div>
          <ul class="small mb-0">
            <li v-for="(w, i) in payment.user_wallet_history" :key="'uw'+i">
              {{ w.is_credit ? '+' : '-' }}{{ w.amount }} — {{ w.remarks || 'wallet' }} ({{ w.created_at }})
            </li>
          </ul>
        </div>

        <div v-if="payment.driver_wallet_history?.length">
          <div class="fw-semibold mb-1">Driver settlement</div>
          <ul class="small mb-0">
            <li v-for="(w, i) in payment.driver_wallet_history" :key="'dw'+i">
              {{ w.is_credit ? '+' : '-' }}{{ w.amount }} — {{ w.remarks || 'settlement' }} ({{ w.created_at }})
            </li>
          </ul>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RidePaymentPanel',
  props: {
    payment: { type: Object, default: null },
  },
};
</script>
