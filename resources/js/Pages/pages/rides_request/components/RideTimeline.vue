<template>
  <div class="card border">
    <div class="card-body">
      <h5 class="mb-3">Ride Timeline</h5>
      <div v-if="!events?.length" class="text-muted">No timeline events.</div>
      <ul v-else class="ride-timeline list-unstyled mb-0">
        <li v-for="(event, idx) in events" :key="idx" class="ride-timeline-item">
          <div class="dot" :class="tone(event.event)"></div>
          <div>
            <div class="fw-semibold text-capitalize">{{ label(event.event) }}</div>
            <div class="small text-muted">
              {{ event.occurred_at || '—' }}
              <span v-if="event.actor_type"> · {{ event.actor_type }}{{ event.actor_id ? ' #' + event.actor_id : '' }}</span>
              <span v-if="event.source === 'synthetic'"> · reconstructed</span>
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RideTimeline',
  props: {
    events: { type: Array, default: () => [] },
  },
  methods: {
    label(event) {
      return (event || '').replace(/_/g, ' ');
    },
    tone(event) {
      const e = (event || '').toLowerCase();
      if (e.includes('cancel') || e.includes('reject')) return 'bg-danger';
      if (e.includes('complete') || e.includes('payment')) return 'bg-success';
      if (e.includes('accept') || e.includes('start') || e.includes('arriv')) return 'bg-primary';
      return 'bg-secondary';
    },
  },
};
</script>

<style scoped>
.ride-timeline-item {
  position: relative;
  padding-left: 1.5rem;
  padding-bottom: 1rem;
  border-left: 2px solid #e9ecef;
  margin-left: 0.35rem;
}
.ride-timeline-item:last-child {
  border-left-color: transparent;
  padding-bottom: 0;
}
.dot {
  position: absolute;
  left: -0.45rem;
  top: 0.25rem;
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
}
</style>
