<script setup>
import { ref, reactive } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
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

const { formatRupiah, formatDateTime, formatDate } = useFormatters();

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

// Refund Completion Modal State
const refundModal = ref({
    show: false,
    payment: null,
});

const refundForm = useForm({
    refund_notes: '',
});

const openRefundModal = (tx) => {
    refundForm.reset();
    refundForm.clearErrors();
    refundModal.value = {
        show: true,
        payment: tx,
    };
};

const closeRefundModal = () => {
    refundModal.value = {
        show: false,
        payment: null,
    };
};

const submitMarkRefunded = () => {
    if (!refundModal.value.payment) return;
    if (!refundForm.refund_notes || refundForm.refund_notes.trim().length < 5) {
        refundForm.setError('refund_notes', 'Catatan atau nomor referensi transfer pengembalian dana minimal 5 karakter.');
        return;
    }

    refundForm.post(route('admin.payments.mark-refunded', { payment: refundModal.value.payment.id }), {
        preserveScroll: true,
        onSuccess: () => {
            closeRefundModal();
        },
    });
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

            <!-- Export & Summary Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3.5 bg-slate-100/80 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-600 font-medium">
                    Menampilkan <strong class="text-slate-900 font-bold tabular-data">{{ transactions.length }} transaksi</strong>.
                </div>

                <a
                    :href="route('admin.payments.transactions.export', {
                        event_id: filterState.event_id || undefined,
                        status: filterState.status || undefined,
                        method: filterState.method || undefined,
                        search: filterState.search || undefined,
                    })"
                    class="btn-secondary text-xs inline-flex items-center gap-1.5 self-start sm:self-auto"
                >
                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Ekspor Laporan Transaksi (CSV)</span>
                </a>
            </div>

            <!-- Transactions Container (Responsive Card List View) -->
            <div class="space-y-3.5">
                <div v-if="transactions.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="font-bold text-slate-700">Tidak ada transaksi yang cocok</p>
                    <p class="mt-0.5 text-slate-400">Silakan sesuaikan filter pencarian di atas.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="tx in transactions"
                        :key="tx.id"
                        class="frosted-card p-4 sm:p-5 hover:border-orange-300/80 hover:shadow-md transition-all duration-150 cursor-pointer bg-white"
                        @click="openProofModal(tx)"
                    >
                        <!-- Card Header -->
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-3 mb-3 border-b border-slate-100 text-xs">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-xs font-bold text-slate-900 px-2.5 py-1 rounded-md bg-slate-100 border border-slate-200">
                                    {{ tx.invoice_number }}
                                </span>
                                <span class="text-slate-400">&bull;</span>
                                <span class="text-slate-500 font-medium">
                                    Dibuat: {{ formatDateTime(tx.created_at) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <StatusBadge :status="tx.status" />
                            </div>
                        </div>

                        <!-- Card Body Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 text-xs items-center">
                            <!-- Team & Event (Col 1-5) -->
                            <div class="md:col-span-5 space-y-1">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-lg bg-orange-50 border border-orange-200 text-orange-700 flex items-center justify-center font-bold text-sm shrink-0">
                                        {{ tx.team?.name?.charAt(0) || 'T' }}
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="font-bold text-slate-900 text-sm truncate">{{ tx.team?.name }}</h4>
                                        <p class="text-slate-500 text-xs truncate">{{ tx.event?.name }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Channel & Method (Col 6-8) -->
                            <div class="md:col-span-3 space-y-1 bg-slate-50/80 p-2.5 rounded-lg border border-slate-200/60">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] uppercase font-bold text-slate-500">Kanal &amp; Metode</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-white border border-slate-200 text-slate-700">
                                        {{ tx.method || 'Manual' }}
                                    </span>
                                </div>
                                <p class="text-slate-800 font-semibold truncate uppercase">
                                    {{ tx.payment_channel || (tx.method === 'manual' ? 'Transfer Bank BCA' : 'Online Payment') }}
                                </p>
                            </div>

                            <!-- Amount & Timestamps (Col 9-10) -->
                            <div class="md:col-span-2 space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-500 block">Total Tagihan</span>
                                <span class="text-sm font-bold text-slate-900 tabular-data block font-mono">
                                    {{ formatRupiah(tx.total_amount) }}
                                </span>
                                <span v-if="tx.paid_at" class="text-[11px] text-emerald-700 font-semibold block">
                                    Lunas: {{ formatDate(tx.paid_at) }}
                                </span>
                                <span v-else class="text-[11px] text-slate-500 block">
                                    Batas: {{ formatDate(tx.expires_at) }}
                                </span>
                            </div>

                            <!-- Actions (Col 11-12) -->
                            <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2" @click.stop>
                                <button
                                    v-if="tx.status === 'refund_required'"
                                    type="button"
                                    class="btn-sm-pitch w-full sm:w-auto justify-center cursor-pointer"
                                    @click="openRefundModal(tx)"
                                >
                                    <span>Tandai Refund Selesai</span>
                                </button>
                                <button
                                    v-if="tx.proof_url"
                                    type="button"
                                    class="btn-sm-pitch w-full sm:w-auto justify-center"
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
                                    class="btn-sm-secondary w-full sm:w-auto justify-center"
                                    @click="openProofModal(tx)"
                                >
                                    Detail
                                </button>
                            </div>
                        </div>

                        <!-- Rejection note if applicable -->
                        <div v-if="tx.rejection_reason" class="mt-3 p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800">
                            <span class="font-bold">Alasan Ditolak:</span> {{ tx.rejection_reason }}
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

        <!-- Modal Penyelesaian Pengembalian Dana (Refund) -->
        <div
            v-if="refundModal.show && refundModal.payment"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            @click.self="closeRefundModal"
        >
            <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Konfirmasi Penyelesaian Refund</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Invoice: <strong class="font-mono text-slate-800">{{ refundModal.payment.invoice_number }}</strong>
                            &bull; Tim: <strong class="text-slate-800">{{ refundModal.payment.team?.name }}</strong>
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700 text-lg leading-none" @click="closeRefundModal">
                        &times;
                    </button>
                </div>

                <form @submit.prevent="submitMarkRefunded" novalidate class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Nomor Referensi / Catatan Transfer Pengembalian Dana <span class="text-rose-600">*</span>
                        </label>
                        <textarea
                            v-model="refundForm.refund_notes"
                            rows="3"
                            placeholder="Contoh: Dikembalikan via BCA ke rek 1234567890 a.n. Budi pada 07/11/2026 (Ref: TRF-99812)"
                            class="input-field text-xs"
                            :class="{ 'input-field-error': refundForm.errors.refund_notes }"
                            @input="refundForm.clearErrors('refund_notes')"
                        ></textarea>
                        <InputError :message="refundForm.errors.refund_notes || refundForm.errors.payment" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="closeRefundModal">
                            Batal
                        </button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="refundForm.processing">
                            {{ refundForm.processing ? 'Menyimpan...' : 'Simpan & Tandai Refunded' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
