<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    teamItem: {
        type: Object,
        required: true,
    },
    isSelected: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open-workspace']);

const { formatRupiah } = useFormatters();
const t = computed(() => props.teamItem);
const isCompleted = computed(() => Boolean(t.value?.is_event_completed));

const openWorkspace = () => {
    if (props.isSelected) {
        emit('open-workspace');
        return;
    }

    router.post(
        route('manager.switch-team', { team: t.value.id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('open-workspace');
            },
        }
    );
};
</script>

<template>
    <article
        class="bg-white border rounded-2xl p-5 space-y-4"
        :class="
            t.needs_attention
                ? 'border-rose-300'
                : isCompleted
                  ? 'border-slate-200 bg-slate-50/40'
                  : 'border-slate-200'
        "
    >
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3.5 border-b border-slate-200">
            <div class="flex items-center gap-3.5 min-w-0">
                <img
                    v-if="t.logo_url"
                    :src="t.logo_url"
                    :alt="`Logo ${t.name}`"
                    width="48"
                    height="48"
                    class="w-12 h-12 rounded-xl object-cover border border-slate-200 shrink-0"
                />
                <div
                    v-else
                    class="w-12 h-12 rounded-xl bg-slate-900 text-white font-black text-sm flex items-center justify-center shrink-0"
                    aria-hidden="true"
                >
                    {{ t.name.substring(0, 2).toUpperCase() }}
                </div>
                <div class="min-w-0 space-y-0.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-black text-slate-900 truncate">{{ t.name }}</h3>
                        <span
                            v-if="isCompleted"
                            class="px-2 py-0.5 rounded bg-slate-200 text-slate-800 text-[11px] font-bold"
                        >
                            Event Selesai
                        </span>
                    </div>
                    <p class="text-xs text-slate-700 truncate">
                        <strong>{{ t.event_name }}</strong>
                        <span v-if="t.age_category"> ({{ t.age_category }})</span>
                        <span v-if="t.city || t.match_start_date">
                            • {{ t.city || '' }}
                            <template v-if="t.city && t.match_start_date"> • </template>
                            {{ t.match_start_date || '' }}
                        </span>
                    </p>
                </div>
            </div>

            <div class="shrink-0 flex items-center gap-2">
                <StatusBadge :status="t.status" />
            </div>
        </div>

        <!-- 3-Column Operational Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="block text-[11px] font-bold text-slate-600">Status Pembayaran</span>
                <span
                    class="mt-1 inline-block font-bold"
                    :class="t.is_paid ? 'text-emerald-800' : 'text-amber-900'"
                >
                    {{ t.is_paid ? 'Lunas' : 'Menunggu Pembayaran' }}
                </span>
                <span v-if="t.latest_payment" class="block text-[11px] text-slate-600 font-mono mt-0.5 tabular-data truncate">
                    {{ t.latest_payment.invoice_number }} ({{ formatRupiah(t.latest_payment.total_amount) }})
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="block text-[11px] font-bold text-slate-600">Skuad &amp; Screening</span>
                <span class="mt-1 block font-bold text-slate-900 tabular-data">
                    {{ t.players_passed_count || 0 }}/{{ t.players_count || 0 }} Pemain Lolos
                </span>
                <span
                    v-if="t.players_rejected_count > 0"
                    class="block text-[11px] text-rose-700 font-semibold mt-0.5 tabular-data"
                >
                    {{ t.players_rejected_count }} pemain perlu perbaikan
                </span>
                <span v-else class="block text-[11px] text-slate-600 mt-0.5 tabular-data">
                    Minimal {{ t.min_players_required || 11 }} pemain • {{ t.officials_count || 0 }} ofisial
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="block text-[11px] font-bold text-slate-600">Akreditasi &amp; Roster</span>
                <span
                    class="mt-1 inline-block font-bold"
                    :class="t.is_roster_locked ? 'text-emerald-800' : 'text-slate-900'"
                >
                    {{ t.is_roster_locked ? 'Skuad Terkunci (Sah)' : 'Belum Dikunci' }}
                </span>
                <span class="block text-[11px] text-slate-600 mt-0.5">
                    {{ isCompleted ? 'Arsip turnamen selesai' : (t.is_roster_locked ? 'ID Card & Roster siap cetak' : 'Kelola di Workspace Tim') }}
                </span>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="pt-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2.5">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="min-h-[44px] inline-flex items-center justify-center px-4 rounded-xl text-xs font-bold transition-colors"
                    :class="
                        isCompleted
                            ? 'bg-white hover:bg-slate-50 text-slate-900 border border-slate-300'
                            : 'bg-orange-700 hover:bg-orange-800 text-white'
                    "
                    @click="openWorkspace"
                >
                    {{ isCompleted ? 'Buka Arsip Tim' : 'Kelola Tim' }}
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    v-if="t.latest_payment?.invoice_url"
                    :href="t.latest_payment.invoice_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="min-h-[44px] inline-flex items-center justify-center px-3.5 rounded-xl bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors"
                >
                    {{ t.latest_payment.status === 'paid' ? 'Unduh Kwitansi PDF' : 'Unduh Invoice PDF' }}
                </a>
            </div>
        </div>
    </article>
</template>

