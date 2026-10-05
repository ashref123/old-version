<template>
  <div class="card border">
    <div class="card-body">
      <h5 class="mb-3">Ride Logs & Audit Trail</h5>
      <div v-if="!logs" class="text-muted">No logs.</div>
      <template v-else>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Event</th>
                <th>Actor</th>
                <th>When</th>
                <th>Source</th>
                <th>Payload</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(event, idx) in (logs.events || [])" :key="idx">
                <td class="text-capitalize">{{ (event.event || '').replace(/_/g, ' ') }}</td>
                <td>{{ event.actor_type || '—' }}{{ event.actor_id ? ' #' + event.actor_id : '' }}</td>
                <td>{{ event.occurred_at }}</td>
                <td><span class="badge bg-light text-dark">{{ event.source || '—' }}</span></td>
                <td class="small"><code v-if="event.payload">{{ short(event.payload) }}</code></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="rejectedDrivers?.length" class="mt-3">
          <div class="fw-semibold mb-1">Rejected / cancelled by drivers</div>
          <ul class="small mb-0">
            <li v-for="(r, i) in rejectedDrivers" :key="i">
              {{ r.driver_name || ('Driver #' + r.driver_id) }} — {{ r.reason || 'no reason' }}
              <span v-if="r.is_after_accept">(after accept)</span>
              · {{ r.created_at }}
            </li>
          </ul>
        </div>
      </template>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RideAuditLog',
  props: {
    logs: { type: Object, default: null },
    rejectedDrivers: { type: Array, default: () => [] },
  },
  methods: {
    short(payload) {
      try {
        const s = JSON.stringify(payload);
        return s.length > 120 ? s.slice(0, 120) + '…' : s;
      } catch (_) {
        return '';
      }
    },
  },
};
</script>
