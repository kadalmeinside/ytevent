<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useFormValidator } from '@/Composables/useFormValidator';

const props = defineProps({
    queue: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah, formatDateTime, formatDate } = useFormatters();
const { applyValidation, isEmpty } = useFormValidator();

const selectedPayment = ref(props.queue[0] || null);

const selectPayment = (p) => {
    selectedPayment.value = p;
};

// Approval
const approveForm = useForm({
    action: 'approve',
    rejection_reason: '',
});

const approvePayment = (payment) => {
    approveForm.action = 'approve';
    approveForm.rejection_reason = '';
    approveForm.post(route('admin.payments.verify', { payment: payment.id }), {
        preserveScroll: true,
        onSuccess: () => {
            const next = props.queue.find((q) => q.id !== payment.id);
            selectedPayment.value = next || null;
        },
    });
};

// Rejection
const isRejectModalOpen = ref(false);
const rejectForm = useForm({
    action: 'reject',
    rejection_reason: '',
});

const openRejectModal = () => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectForm.action = 'reject';
    isRejectModalOpen.value = true;
};

const executeReject = () => {
    if (!selectedPayment.value) return;

    const ok = applyValidation(rejectForm, () => {
        const errs = {};
        if (isEmpty(rejectForm.rejection_reason) || rejectForm.rejection_reason.trim().length < 10) {
            errs.rejection_reason = 'Alasan penolakan wajib diisi secara jelas (minimal 10 karakter) sebagai petunjuk perbaikan bagi Manajer Tim.';
        }
        return errs;
    });

    if (!ok) return;

    rejectForm.post(route('admin.payments.verify', { payment: selectedPayment.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isRejectModalOpen.value = false;
            const next = props.queue.find((q) => q.id !== selectedPayment.value.id);
            selectedPayment.value = next || null;
        },
    });
};
</script>

<template>
    <AdminLayout page-title="Antrean Verifikasi Pembayaran Manual" subtitle="Periksa bukti transfer mutasi bank Manajer Tim berdampingan dengan nominal tagihan.">
        <Head title="Verifikasi Transfer Manual" />

        <div v-if="queue.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500">
            <svg class="w-10 h-10 text-emerald-600 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm font-bold text-slate-900">Seluruh Bukti Transfer Telah Diverifikasi</p>
            <p class="mt-1 text-slate-500">Tidak ada pembayaran manual yang sedang menunggu pemeriksaan saat ini.</p>
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-5">
            <!-- Left: List Queue -->
            <div class="lg:col-span-5 frosted-card overflow-hidden flex flex-col">
                <div class="p-3.5 bg-slate-100/80 border-b border-slate-200 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wide text-slate-700">
                        Antrean Masuk ({{ queue.length }} Tagihan)
                    </span>
                    <span class="text-[11px] text-slate-500">Klik baris untuk memeriksa</span>
                </div>

                <div class="divide-y divide-slate-200/70 overflow-y-auto max-h-[70vh]">
                    <div
                        v-for="item in queue"
                        :key="item.id"
                        class="p-3.5 cursor-pointer transition-colors text-xs"
                        :class="selectedPayment?.id === item.id ? 'bg-orange-50/80 border-l-4 border-orange-600' : 'bg-white hover:bg-slate-50/90'"
                        @click="selectPayment(item)"
                    >
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <span class="font-bold tabular-data text-slate-900 text-sm font-mono">{{ item.invoice_number }}</span>
                            <StatusBadge :status="item.status" />
                        </div>
                        <p class="font-semibold text-slate-800 text-sm">{{ item.team?.name }}</p>
                        <p class="text-[11px] text-slate-500">{{ item.event?.name }}</p>
                        <div class="mt-2 pt-2 border-t border-slate-200/60 flex items-center justify-between">
                            <span class="text-slate-500">{{ item.sender_bank_name }} a.n {{ item.sender_account_holder }}</span>
                            <strong class="text-orange-950 tabular-data text-sm font-black">{{ formatRupiah(item.total_amount) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Split-View Side-by-Side Proof Viewer & Actions -->
            <div v-if="selectedPayment" class="lg:col-span-7 space-y-4">
                <div class="frosted-card p-5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
                        <div>
                            <span class="text-[11px] font-semibold text-slate-500 uppercase">Verifikasi Tagihan</span>
                            <h2 class="text-lg font-bold text-slate-900 tabular-data font-mono">{{ selectedPayment.invoice_number }}</h2>
                            <p class="text-xs text-slate-600">
                                Klub: <strong>{{ selectedPayment.team?.name }}</strong> &bull; Manajer: {{ selectedPayment.team?.manager_name }} ({{ selectedPayment.team?.manager_whatsapp }})
                            </p>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="text-[11px] text-slate-500 block">Nominal Wajib Transfer</span>
                            <span class="text-xl font-black text-slate-900 tabular-data">{{ formatRupiah(selectedPayment.total_amount) }}</span>
                        </div>
                    </div>

                    <!-- Transfer Details -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div>
                            <span class="text-slate-500 block">Bank Pengirim</span>
                            <strong class="text-slate-900 mt-0.5 block">{{ selectedPayment.sender_bank_name }}</strong>
                        </div>
                        <div>
                            <span class="text-slate-500 block">Nama Rekening</span>
                            <strong class="text-slate-900 mt-0.5 block">{{ selectedPayment.sender_account_holder }}</strong>
                        </div>
                        <div>
                            <span class="text-slate-500 block">No. Rekening</span>
                            <strong class="text-slate-900 tabular-data mt-0.5 block">{{ selectedPayment.sender_account_number || '-' }}</strong>
                        </div>
                        <div>
                            <span class="text-slate-500 block">Tanggal Transfer</span>
                            <strong class="text-slate-900 tabular-data mt-0.5 block">{{ formatDate(selectedPayment.transfer_date) }}</strong>
                        </div>
                    </div>

                    <!-- Proof File Viewer Container -->
                    <div class="rounded-xl border border-slate-300 bg-slate-100 p-3 min-h-[350px] flex items-center justify-center overflow-hidden">
                        <template v-if="selectedPayment.proof_url">
                            <iframe
                                v-if="selectedPayment.proof_is_pdf"
                                :src="selectedPayment.proof_url"
                                class="w-full h-[450px] rounded border bg-white"
                            />
                            <div v-else class="text-center">
                                <a :href="selectedPayment.proof_url" target="_blank" class="inline-block group">
                                    <img
                                        :src="selectedPayment.proof_url"
                                        alt="Bukti Transfer"
                                        class="max-h-[450px] w-auto mx-auto rounded shadow-sm group-hover:opacity-95 transition-opacity"
                                    />
                                    <span class="text-[11px] text-slate-500 mt-2 inline-block font-semibold group-hover:text-orange-600 transition-colors">
                                        Klik gambar untuk membuka ukuran penuh di tab baru &rarr;
                                    </span>
                                </a>
                            </div>
                        </template>
                        <span v-else class="text-xs text-slate-500">Berkas bukti transfer tidak tersedia.</span>
                    </div>

                    <!-- Action Decision Buttons -->
                    <div class="pt-2 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            class="btn-danger"
                            :disabled="approveForm.processing"
                            @click="openRejectModal"
                        >
                            Tolak Bukti Transfer...
                        </button>

                        <button
                            type="button"
                            class="btn-pitch"
                            :disabled="approveForm.processing"
                            @click="approvePayment(selectedPayment)"
                        >
                            {{ approveForm.processing ? 'Memverifikasi...' : 'Setujui Pembayaran (LUNAS)' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Alasan Penolakan -->
        <div
            v-if="isRejectModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        >
            <div class="bg-white/95 backdrop-blur-xl border border-slate-200 rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-base font-bold text-slate-900">Tolak Bukti Pembayaran Manual</h3>
                <p class="text-xs text-slate-600 mt-1">
                    Invoice: <strong class="tabular-data font-mono">{{ selectedPayment?.invoice_number }}</strong> &mdash; {{ selectedPayment?.team?.name }}
                </p>

                <div class="mt-4">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Catatan Alasan Penolakan (Wajib min. 10 karakter)
                    </label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        placeholder="Contoh: Nominal transfer kurang Rp 50.000 atau gambar struk tidak terbaca jelas..."
                        class="input-field text-xs"
                        :class="{ 'input-field-error': rejectForm.errors.rejection_reason }"
                        @input="rejectForm.clearErrors('rejection_reason')"
                    />
                    <InputError :message="rejectForm.errors.rejection_reason" />
                    <p class="text-[11px] text-slate-500 mt-1.5 leading-relaxed">
                        Manajer Tim akan menerima email penolakan beserta kutipan catatan ini dan diberikan perpanjangan waktu untuk mengunggah ulang bukti transfer sebelum slot kuota dilepas.
                    </p>

                    <div class="mt-5 flex items-center justify-end gap-2.5">
                        <button type="button" class="btn-secondary" @click="isRejectModalOpen = false">
                            Batal
                        </button>
                        <button
                            type="button"
                            class="btn-danger"
                            :disabled="rejectForm.processing"
                            @click="executeReject"
                        >
                            {{ rejectForm.processing ? 'Menolak...' : 'Konfirmasi Tolak Bukti' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

