<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: 'draft',
    },
    label: {
        type: String,
        default: null,
    },
});

const statusMeta = computed(() => {
    const key = String(props.status || 'draft').toLowerCase();

    const dictionary = {
        passed: { text: 'LOLOS', variant: 'emerald' },
        paid: { text: 'LUNAS', variant: 'emerald' },
        active: { text: 'AKTIF', variant: 'emerald' },
        open: { text: 'DIBUKA', variant: 'emerald' },
        valid: { text: 'VALID', variant: 'emerald' },
        all_passed: { text: 'SEMUA LOLOS', variant: 'emerald' },
        locked_roster: { text: 'SKUAD TERKUNCI', variant: 'emerald' },

        submitted: { text: 'DIAJUKAN', variant: 'amber' },
        pending: { text: 'MENUNGGU', variant: 'amber' },
        unpaid: { text: 'BELUM BAYAR', variant: 'amber' },
        pending_payment: { text: 'MENUNGGU BAYAR', variant: 'amber' },
        waiting_verification: { text: 'MENUNGGU VERIFIKASI', variant: 'amber' },
        in_review: { text: 'DALAM PEMERIKSAAN', variant: 'amber' },
        ongoing: { text: 'BERJALAN', variant: 'amber' },

        rejected: { text: 'DITOLAK / REVISI', variant: 'rose' },
        partially_rejected: { text: 'PERLU REVISI', variant: 'rose' },
        invalid: { text: 'TIDAK SAH', variant: 'rose' },
        failed: { text: 'GAGAL', variant: 'rose' },
        expired: { text: 'KEDALUWARSA', variant: 'rose' },
        quota_full: { text: 'KUOTA PENUH', variant: 'rose' },
        disqualified: { text: 'DISKUALIFIKASI', variant: 'rose' },
        refund_required: { text: 'PERLU REFUND', variant: 'rose' },

        draft: { text: 'DRAFT', variant: 'slate' },
        not_started: { text: 'BELUM DIMULAI', variant: 'slate' },
        closed: { text: 'DITUTUP', variant: 'slate' },
        completed: { text: 'SELESAI', variant: 'slate' },
        refunded: { text: 'DIKEMBALIKAN', variant: 'slate' },
    };

    const entry = dictionary[key] || { text: key.toUpperCase(), variant: 'slate' };
    const classesByVariant = {
        emerald: 'bg-emerald-50 text-emerald-800 border-emerald-300',
        amber: 'bg-amber-50 text-amber-800 border-amber-300',
        rose: 'bg-rose-50 text-rose-800 border-rose-300',
        slate: 'bg-slate-100 text-slate-700 border-slate-300',
    };

    return {
        displayText: props.label || entry.text,
        classes: classesByVariant[entry.variant],
    };
});
</script>

<template>
    <span
        class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold tracking-wide uppercase border select-none whitespace-nowrap"
        :class="statusMeta.classes"
    >
        {{ statusMeta.displayText }}
    </span>
</template>

