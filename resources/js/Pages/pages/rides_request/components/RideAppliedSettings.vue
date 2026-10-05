<template>
  <div class="card border">
    <div class="card-body">
      <h5 class="mb-2">Applied Settings</h5>
      <div v-if="settings?.notice" class="alert py-2 small" :class="settings.historical ? 'alert-warning' : 'alert-info'">
        {{ settings.notice }}
      </div>
      <div v-if="!settings?.items?.length" class="text-muted">No settings snapshot.</div>
      <div v-else class="table-responsive">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Setting</th>
              <th>Value</th>
              <th>Source</th>
              <th>Applied</th>
              <th>Why</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in settings.items" :key="idx">
              <td>{{ item.name }}</td>
              <td><code>{{ format(item.value) }}</code></td>
              <td class="small">{{ item.source_module }}</td>
              <td>
                <span class="badge" :class="item.applied ? 'bg-success' : 'bg-secondary'">{{ item.applied ? 'Yes' : 'No' }}</span>
                <span v-if="item.is_current_config" class="badge bg-warning-subtle text-warning ms-1">current</span>
              </td>
              <td class="small text-muted">{{ item.why }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'RideAppliedSettings',
  props: {
    settings: { type: Object, default: null },
  },
  methods: {
    format(v) {
      if (v === null || v === undefined || v === '') return '—';
      if (typeof v === 'object') return JSON.stringify(v);
      return String(v);
    },
  },
};
</script>
