import { ref, computed } from 'vue';
import axios from 'axios';

export function useRideDossier(dossierUrl) {
    const dossier = ref(null);
    const loading = ref(false);
    const error = ref('');
    const showRawJson = ref(false);

    const fetchDossier = async () => {
        if (!dossierUrl) {
            return;
        }
        loading.value = true;
        error.value = '';
        try {
            const { data } = await axios.get(dossierUrl);
            dossier.value = data.data;
        } catch (e) {
            error.value = e?.response?.data?.message || 'Failed to load ride dossier';
        } finally {
            loading.value = false;
        }
    };

    const overview = computed(() => dossier.value?.overview || null);
    const actions = computed(() => dossier.value?.actions || {});
    const pricingAudit = computed(() => {
        const audits = dossier.value?.pricing_audit || {};
        return audits.final || audits.eta || dossier.value?.pricing_reconstructed?.audit || null;
    });
    const isReconstructed = computed(() => {
        const audits = dossier.value?.pricing_audit || {};
        return !audits.final && !audits.eta && !!dossier.value?.pricing_reconstructed?.available;
    });

    const copyText = async (text, label = 'Copied') => {
        try {
            await navigator.clipboard.writeText(text);
            return label;
        } catch (_) {
            return null;
        }
    };

    const copyRideSummary = async () => {
        const o = overview.value;
        if (!o) return;
        const text = [
            `Ride: ${o.request_number}`,
            `Status: ${o.status}`,
            `Booking: ${o.booking_type}`,
            `Zone: ${o.zone || '-'}`,
            `Vehicle: ${o.vehicle_type || '-'}`,
            `Distance: ${o.total_distance} ${o.unit}`,
            `Duration: ${o.total_duration_mins != null && Number(o.total_duration_mins) >= 60
                ? `${Math.round((Number(o.total_duration_mins) / 60) * 100) / 100} hrs (${Math.round(Number(o.total_duration_mins))} min)`
                : `${Math.round(Number(o.total_duration_mins || 0))} min`}`,
            `Created: ${o.created_at || '-'}`,
            `Completed: ${o.completed_at || '-'}`,
        ].join('\n');
        return copyText(text, 'Ride summary copied');
    };

    const copyPricing = async () => {
        const payload = {
            estimated: dossier.value?.estimated_fare,
            final: dossier.value?.final_fare,
            diff: dossier.value?.fare_diff,
            audit: pricingAudit.value,
        };
        return copyText(JSON.stringify(payload, null, 2), 'Pricing breakdown copied');
    };

    return {
        dossier,
        loading,
        error,
        showRawJson,
        overview,
        actions,
        pricingAudit,
        isReconstructed,
        fetchDossier,
        copyRideSummary,
        copyPricing,
        copyText,
    };
}
