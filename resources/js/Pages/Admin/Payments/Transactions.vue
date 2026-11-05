<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PaymentProofModal from '@/Components/PaymentProofModal.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    transactions: {
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
    method: props.filters.method || '',
    search: props.filters.search || '',
});

// Modal State
const selectedPayment = ref(null);
const isProofModalOpen = ref(false);

const openProofModal = (tx) => {
    selectedPayment.value = tx;
    isProofModalOpen.value = true;
};

const closeProofModal = () => {
    isProofModalOpen.value = false;
    selectedPayment.value = null;
};

const onPaymentVerified = () => {
    // Reload data to reflect new status
    applyFilters();
};

const applyFilters = () => {
    router.get(
        route('admin.payments.transactions'),
        {
            event_id: filterState.event_id || undefined,
            status: filterState.status || undefined,
            method: filterState.method || undefined,
            search: filterState.search || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    filterState.event_id = '';
    filterState.status = '';
    filterState.method = '';
    filterState.search = '';
    router.get(route('admin.payments.transactions'), {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AdminLayout page-title="Riwayat Seluruh Transaksi &amp; Rekonsiliasi" subtitle="Laporan pembayaran gateway (Midtrans / Xendit), transfer manual, dan rekonsiliasi refund.">
        <Head title="Riwayat Transaksi" />

        <div class="space-y-5">
            <!-- Filter Bar -->
            <form @submit.prevent="applyFilters" novalidate class="frosted-card p-4 sm:p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Filter Kompetisi</label>
                        <select v-model="filterState.event_id" class="input-field" @change="applyFilters">
                            <option value="">Semua Kompetisi</option>
                            <option v-for="evt in eventsFilter" :key="evt.id" :value="evt.id">
                                {{ evt.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Status Pembayaran</label>
                        <select v-model="filterState.status" class="input-field" @change="applyFilters">
                            <option value="">Semua Status</option>
                            <option value="paid">Lunas (Paid)</option>
                            <option value="waiting_verification">Menunggu Verifikasi Manual</option>
                            <option value="unpaid">Belum Bayar (Unpaid)</option>
                            <option value="rejected">Ditolak (Rejected)</option>
                            <option value="expired">Kedaluwarsa (Expired)</option>
                            <option value="refund_required">Perlu Refund (Late Over-Quota)</option>
                            <option value="refunded">Dikembalikan (Refunded)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Kanal / Metode</label>
                        <select v-model="filterState.method" class="input-field" @change="applyFilters">
                            <option value="">Semua Metode</option>
                            <option value="manual">Transfer Manual Bank</option>
                            <option value="midtrans">Midtrans</option>
                            <option value="xendit">Xendit</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Cari No. Invoice / Tim</label>
                        <input
                            v-model="filterState.search"
                            type="text"
                            placeholder="INV-... atau nama tim"
                            class="input-field"
                        />
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="btn-pitch flex-1">Cari</button>
                        <button type="button" class="btn-secondary px-3" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </form>

            <!-- Transactions Container (Dual View: Desktop Table + Mobile High-Density List) -->
            <div class="frosted-card overflow-hidden">
                <div v-if="transactions.length === 0" class="p-12 text-center text-xs text-slate-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="font-bold text-slate-700">Tidak ada transaksi yang cocok</p>
                    <p class="mt-0.5 text-slate-400">Silakan sesuaikan filter pencarian di atas.</p>
                </div>

                <div v-else>
                    <!-- DESKTOP TABLE VIEW -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="table-th">No. Invoice</th>
                                    <th class="table-th">Tim &amp; Kompetisi</th>
                                    <th class="table-th">Kanal Metode</th>
                                    <th class="table-th">Nominal Tagihan</th>
                                    <th class="table-th">Status</th>
                                    <th class="table-th">Waktu Lunas / Kedaluwarsa</th>
                                    <th class="table-th text-right">Aksi &amp; Bukti</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="tx in transactions"
                                    :key="tx.id"
                                    class="hover:bg-orange-50/25 transition-colors cursor-pointer"
                                    @click="openProofModal(tx)"
                                >
                                    <td class="table-td font-bold font-mono tabular-data text-slate-900">
                                        {{ tx.invoice_number }}
                                    </td>
                                    <td class="table-td font-medium text-slate-800">
                                        <p class="font-bold text-slate-900">{{ tx.team?.name }}</p>
                                        <p class="text-[11px] text-slate-500">{{ tx.event?.name }}</p>
                                    </td>
                                    <td class="table-td text-slate-700">
                                        <span class="font-semibold uppercase text-xs">{{ tx.method || 'Manual' }}</span>
                                        <span v-if="tx.payment_channel" class="text-[11px] text-slate-500 block uppercase">{{ tx.payment_channel }}</span>
                                    </td>
                                    <td class="table-td tabular-data font-bold text-slate-900">
                                        {{ formatRupiah(tx.total_amount) }}
                                    </td>
                                    <td class="table-td">
                                        <div class="space-y-0.5">
                                            <StatusBadge :status="tx.status" />
                                            <p v-if="tx.rejection_reason" class="text-[10px] text-rose-700 font-medium">
                                                Ditolak: {{ tx.rejection_reason }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="table-td tabular-data text-slate-600">
                                        <span v-if="tx.paid_at" class="text-emerald-700 font-semibold block">
                                            Lunas: {{ formatDateTime(tx.paid_at) }}
                                        </span>
                                        <span v-else class="text-slate-500 block">
                                            Expired: {{ formatDateTime(tx.expires_at) }}
                                        </span>
                                    </td>
                                    <td class="table-td text-right" @click.stop>
                                        <button
                                            v-if="tx.proof_url"
                                            type="button"
                                            class="btn-sm-pitch"
                                            @click="openProofModal(tx)"
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
                                            @click="openProofModal(tx)"
                                        >
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE HIGH-DENSITY CARD LIST VIEW -->
                    <div class="md:hidden divide-y divide-slate-100">
                        <div
                            v-for="tx in transactions"
                            :key="tx.id"
                            class="p-4 hover:bg-orange-50/20 transition-colors space-y-3"
                            @click="openProofModal(tx)"
                        >
                            <!-- Top row: Invoice & Status -->
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-xs font-bold text-slate-900 px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                                    {{ tx.invoice_number }}
                                </span>
                                <StatusBadge :status="tx.status" />
                            </div>

                            <!-- Team & Event -->
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 leading-snug">{{ tx.team?.name }}</h4>
                                <p class="text-xs text-slate-500">{{ tx.event?.name }}</p>
                            </div>

                            <!-- Amount & Method Info -->
                            <div class="grid grid-cols-2 gap-2 bg-slate-50/90 rounded-xl p-2.5 border border-slate-200/70 text-xs">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Nominal</span>
                                    <span class="font-mono tabular-data font-bold text-slate-900">{{ formatRupiah(tx.total_amount) }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-500 block">Metode</span>
                                    <span class="font-semibold text-slate-800 uppercase">{{ tx.method || 'Manual' }}</span>
                                    <span v-if="tx.payment_channel" class="text-[10px] text-slate-500 block">{{ tx.payment_channel }}</span>
                                </div>
                            </div>

                            <!-- Rejection Reason if any -->
                            <div v-if="tx.rejection_reason" class="p-2 rounded-lg bg-rose-50 border border-rose-200 text-[11px] text-rose-800">
                                <span class="font-bold">Alasan Ditolak:</span> {{ tx.rejection_reason }}
                            </div>

                            <!-- Timing & Action Button -->
                            <div class="flex items-center justify-between pt-1 text-xs" @click.stop>
                                <div class="text-[11px] text-slate-500">
                                    <span v-if="tx.paid_at" class="text-emerald-700 font-semibold">
                                        Lunas: {{ formatDateTime(tx.paid_at) }}
                                    </span>
                                    <span v-else>
                                        Expired: {{ formatDateTime(tx.expires_at) }}
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    :class="tx.proof_url ? 'btn-sm-pitch' : 'btn-sm-secondary'"
                                    @click="openProofModal(tx)"
                                >
                                    <svg v-if="tx.proof_url" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>{{ tx.proof_url ? 'Bukti Bayar' : 'Lihat Detail' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integrated Proof Modal with Inline Verification -->
        <PaymentProofModal
            :show="isProofModalOpen"
            :payment="selectedPayment"
            :can-verify="true"
            @close="closeProofModal"
            @verified="onPaymentVerified"
        />
    </AdminLayout>
</template>
