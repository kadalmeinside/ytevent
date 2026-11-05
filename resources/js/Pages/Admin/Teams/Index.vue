<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PaymentProofModal from '@/Components/PaymentProofModal.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    teams: {
        type: Array,
        default: () => [],
    },
    eventsFilter: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const { formatRupiah, formatDateTime } = useFormatters();

const filterState = reactive({
    event_id: props.filters.event_id || '',
    status: props.filters.status || '',
    search: props.filters.search || '',
});

// Modal state for payment proof
const selectedPayment = ref(null);
const isProofModalOpen = ref(false);

const openProofModal = (payment) => {
    if (!payment) return;
    selectedPayment.value = payment;
    isProofModalOpen.value = true;
};

const closeProofModal = () => {
    isProofModalOpen.value = false;
    selectedPayment.value = null;
};

const onPaymentVerified = () => {
    applyFilters();
};

const applyFilters = () => {
    router.get(
        route('admin.teams.index'),
        {
            event_id: filterState.event_id || undefined,
            status: filterState.status || undefined,
            search: filterState.search || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    filterState.event_id = '';
    filterState.status = '';
    filterState.search = '';
    router.get(route('admin.teams.index'), {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AdminLayout page-title="Daftar Tim Terdaftar" subtitle="Pantau seluruh tim dari semua kompetisi, status reservasi slot, dan pelunasan invoice.">
        <Head title="Daftar Tim Masuk" />

        <div class="space-y-5">
            <!-- Filter Bar -->
            <form @submit.prevent="applyFilters" novalidate class="frosted-card p-4 sm:p-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3.5 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Pilih Kompetisi</label>
                        <select v-model="filterState.event_id" class="input-field" @change="applyFilters">
                            <option value="">Semua Kompetisi</option>
                            <option v-for="evt in eventsFilter" :key="evt.id" :value="evt.id">
                                {{ evt.name }} ({{ evt.age_category }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Status Tim</label>
                        <select v-model="filterState.status" class="input-field" @change="applyFilters">
                            <option value="">Semua Status</option>
                            <option value="pending_payment">Menunggu Bayar (Slot Ditahan)</option>
                            <option value="active">Aktif / Lunas</option>
                            <option value="locked_roster">Roster Dikunci</option>
                            <option value="expired">Kedaluwarsa (Slot Dilepas)</option>
                            <option value="disqualified">Didiskualifikasi</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Cari Nama Tim / Askot / Manajer</label>
                        <input
                            v-model="filterState.search"
                            type="text"
                            placeholder="Ketik nama klub atau manajer..."
                            class="input-field"
                        />
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="btn-pitch flex-1">Filter</button>
                        <button type="button" class="btn-secondary px-3" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </form>

            <!-- Teams Container (Dual View: Desktop Table + Mobile High-Density List) -->
            <div class="frosted-card overflow-hidden">
                <div v-if="teams.length === 0" class="p-12 text-center text-xs text-slate-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="font-bold text-slate-700">Tidak ada tim yang ditemukan</p>
                    <p class="mt-0.5 text-slate-400">Silakan sesuaikan kriteria pencarian di atas.</p>
                </div>

                <div v-else>
                    <!-- DESKTOP TABLE VIEW -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="table-th">Tim &amp; Asosiasi</th>
                                    <th class="table-th">Kompetisi</th>
                                    <th class="table-th">Manajer &amp; WhatsApp</th>
                                    <th class="table-th">Tagihan &amp; Invoice</th>
                                    <th class="table-th">Pemain</th>
                                    <th class="table-th">Status Tim</th>
                                    <th class="table-th text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="t in teams" :key="t.id" class="hover:bg-orange-50/25 transition-colors">
                                    <td class="table-td font-bold text-slate-900">
                                        <div class="flex items-center gap-2.5">
                                            <img
                                                v-if="t.logo_url"
                                                :src="t.logo_url"
                                                :alt="t.name"
                                                class="w-8 h-8 rounded-lg object-cover border border-slate-200 bg-white shadow-xs shrink-0"
                                            />
                                            <div v-else class="w-8 h-8 rounded-lg bg-orange-100 text-orange-700 font-bold flex items-center justify-center text-xs shrink-0">
                                                {{ t.name.charAt(0) }}
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-900 leading-tight">{{ t.name }}</p>
                                                <p class="text-[11px] text-slate-500 font-normal">{{ t.origin_association }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="table-td text-slate-800">
                                        <p class="font-semibold text-xs">{{ t.event?.name }}</p>
                                        <span class="text-[10px] text-slate-500 font-mono">{{ t.event?.age_category }}</span>
                                    </td>
                                    <td class="table-td text-slate-700">
                                        <p class="font-medium text-xs">{{ t.manager_name }}</p>
                                        <a
                                            :href="`https://wa.me/${t.manager_whatsapp.replace(/[^0-9]/g, '')}`"
                                            target="_blank"
                                            class="text-[11px] tabular-data text-emerald-600 hover:text-emerald-700 font-medium inline-flex items-center gap-1"
                                        >
                                            <span>{{ t.manager_whatsapp }}</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                    </td>
                                    <td class="table-td">
                                        <div v-if="t.latest_payment" class="space-y-0.5">
                                            <span class="tabular-data font-mono font-bold text-slate-900 block text-xs">
                                                {{ t.latest_payment.invoice_number }}
                                            </span>
                                            <div class="flex items-center gap-1.5">
                                                <span class="tabular-data text-[11px] text-slate-600 font-semibold">
                                                    {{ formatRupiah(t.latest_payment.total_amount) }}
                                                </span>
                                                <StatusBadge :status="t.latest_payment.status" />
                                            </div>
                                        </div>
                                        <span v-else class="text-slate-400 text-xs">-</span>
                                    </td>
                                    <td class="table-td tabular-data font-semibold text-slate-800 text-xs">
                                        <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ t.players_count }} Pemain
                                        </span>
                                    </td>
                                    <td class="table-td">
                                        <StatusBadge :status="t.status" />
                                    </td>
                                    <td class="table-td text-right">
                                        <button
                                            v-if="t.latest_payment?.proof_url"
                                            type="button"
                                            class="btn-sm-pitch"
                                            @click="openProofModal(t.latest_payment)"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Bukti Bayar</span>
                                        </button>
                                        <button
                                            v-else-if="t.latest_payment"
                                            type="button"
                                            class="btn-sm-secondary"
                                            @click="openProofModal(t.latest_payment)"
                                        >
                                            Detail Tagihan
                                        </button>
                                        <span v-else class="text-slate-400 text-xs">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE HIGH-DENSITY CARD LIST VIEW -->
                    <div class="md:hidden divide-y divide-slate-100">
                        <div
                            v-for="t in teams"
                            :key="t.id"
                            class="p-4 hover:bg-orange-50/20 transition-colors space-y-3"
                        >
                            <!-- Top row: Crest, Name & Team Status -->
                            <div class="flex items-start justify-between gap-2.5">
                                <div class="flex items-center gap-2.5">
                                    <img
                                        v-if="t.logo_url"
                                        :src="t.logo_url"
                                        :alt="t.name"
                                        class="w-10 h-10 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                                    />
                                    <div v-else class="w-10 h-10 rounded-lg bg-orange-100 text-orange-700 font-bold flex items-center justify-center text-sm shrink-0">
                                        {{ t.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900 leading-snug">{{ t.name }}</h4>
                                        <p class="text-xs text-slate-500">{{ t.origin_association }}</p>
                                    </div>
                                </div>
                                <StatusBadge :status="t.status" />
                            </div>

                            <!-- Event & Roster Info -->
                            <div class="flex items-center justify-between text-xs text-slate-600 bg-slate-50/80 rounded-lg px-3 py-2 border border-slate-200/60">
                                <div>
                                    <span class="font-semibold text-slate-800">{{ t.event?.name }}</span>
                                    <span class="text-[11px] text-slate-500 block font-mono">{{ t.event?.age_category }}</span>
                                </div>
                                <span class="font-mono text-xs font-bold text-slate-700 px-2 py-0.5 rounded bg-white border border-slate-200">
                                    {{ t.players_count }} Pemain
                                </span>
                            </div>

                            <!-- Manager & WhatsApp -->
                            <div class="flex items-center justify-between text-xs">
                                <div class="text-slate-600">
                                    <span class="text-slate-400 block text-[10px] uppercase font-bold">Manajer</span>
                                    <span class="font-medium text-slate-900">{{ t.manager_name }}</span>
                                </div>
                                <a
                                    :href="`https://wa.me/${t.manager_whatsapp.replace(/[^0-9]/g, '')}`"
                                    target="_blank"
                                    class="text-xs text-emerald-700 hover:text-emerald-800 font-medium inline-flex items-center gap-1 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200"
                                >
                                    <span>WA: {{ t.manager_whatsapp }}</span>
                                </a>
                            </div>

                            <!-- Latest Payment Box & Action -->
                            <div v-if="t.latest_payment" class="flex items-center justify-between pt-2 border-t border-slate-100">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono text-xs font-bold text-slate-900">{{ t.latest_payment.invoice_number }}</span>
                                        <StatusBadge :status="t.latest_payment.status" />
                                    </div>
                                    <span class="font-mono tabular-data text-xs text-slate-600 block mt-0.5">
                                        {{ formatRupiah(t.latest_payment.total_amount) }}
                                    </span>
                                </div>

                                <button
                                    v-if="t.latest_payment.proof_url"
                                    type="button"
                                    class="btn-sm-pitch"
                                    @click="openProofModal(t.latest_payment)"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Bukti Bayar</span>
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="btn-sm-secondary"
                                    @click="openProofModal(t.latest_payment)"
                                >
                                    Detail Tagihan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integrated Proof Modal with Verification Capability -->
        <PaymentProofModal
            :show="isProofModalOpen"
            :payment="selectedPayment"
            :can-verify="true"
            @close="closeProofModal"
            @verified="onPaymentVerified"
        />
    </AdminLayout>
</template>
