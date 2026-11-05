<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import { useFormatters } from '@/Composables/useFormatters';
import { useFormValidator } from '@/Composables/useFormValidator';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    payment: {
        type: Object,
        default: null,
    },
    canVerify: {
        type: Boolean,
        default: false,
    },
    verificationRoute: {
        type: String,
        default: 'admin.payments.manual-verification',
    },
});

const emit = defineEmits(['close', 'verified']);

const { formatRupiah, formatDateTime, formatDate } = useFormatters();
const { applyValidation, isEmpty } = useFormValidator();

const isRejecting = ref(false);
const isImageZoomed = ref(false);

const verifyForm = useForm({
    action: 'approve',
    rejection_reason: '',
});

const isWaitingVerification = computed(() => {
    return props.payment && props.payment.status === 'waiting_verification';
});

const handleClose = () => {
    isRejecting.value = false;
    isImageZoomed.value = false;
    verifyForm.reset();
    verifyForm.clearErrors();
    emit('close');
};

const handleApprove = () => {
    if (!props.payment) return;
    verifyForm.action = 'approve';
    verifyForm.rejection_reason = '';

    verifyForm.post(route('admin.payments.verify', { payment: props.payment.id }), {
        preserveScroll: true,
        onSuccess: () => {
            emit('verified', { action: 'approve', paymentId: props.payment.id });
            handleClose();
        },
    });
};

const handleReject = () => {
    if (!props.payment) return;

    const ok = applyValidation(verifyForm, () => {
        const errs = {};
        if (isEmpty(verifyForm.rejection_reason) || verifyForm.rejection_reason.trim().length < 10) {
            errs.rejection_reason = 'Alasan penolakan wajib diisi secara jelas (minimal 10 karakter) untuk panduan manajer tim.';
        }
        return errs;
    });

    if (!ok) return;

    verifyForm.action = 'reject';
    verifyForm.post(route('admin.payments.verify', { payment: props.payment.id }), {
        preserveScroll: true,
        onSuccess: () => {
            emit('verified', { action: 'reject', paymentId: props.payment.id });
            handleClose();
        },
    });
};

const goToManualQueue = () => {
    handleClose();
    router.visit(route(props.verificationRoute));
};
</script>

<template>
    <div
        v-if="show && payment"
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4"
        @click.self="handleClose"
    >
        <div
            class="frosted-card bg-white w-full max-w-4xl max-h-[92vh] flex flex-col overflow-hidden shadow-2xl border border-slate-200/90 animate-in fade-in zoom-in-95 duration-150"
            role="dialog"
            aria-modal="true"
        >
            <!-- Header Modal -->
            <div class="px-5 py-3.5 bg-gradient-to-r from-slate-50 via-white to-orange-50/30 border-b border-slate-200 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center text-orange-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-slate-900">Detail &amp; Bukti Pembayaran</h3>
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                {{ payment.invoice_number }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500">
                            {{ payment.team?.name ? `${payment.team.name} • ` : '' }}{{ payment.event?.name || 'Kompetisi' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <StatusBadge :status="payment.status" />
                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                        @click="handleClose"
                        title="Tutup Modal"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Body Grid: Preview + Details -->
            <div class="p-5 overflow-y-auto flex-1 grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- Col Left: Proof Viewer (7 cols on lg) -->
                <div class="lg:col-span-7 flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Berkas Bukti Transfer</span>
                        <a
                            v-if="payment.proof_url"
                            :href="payment.proof_url"
                            target="_blank"
                            class="text-xs text-orange-600 hover:text-orange-700 font-semibold inline-flex items-center gap-1"
                        >
                            <span>Buka di Tab Baru</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>

                    <!-- Viewer Container -->
                    <div class="bg-slate-900/5 rounded-xl border border-slate-200/90 p-2 flex-1 flex items-center justify-center min-h-[260px] max-h-[460px] overflow-hidden relative">
                        <!-- PDF View -->
                        <div v-if="payment.proof_is_pdf && payment.proof_url" class="text-center p-6 space-y-3">
                            <svg class="w-16 h-16 text-rose-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                            <div>
                                <p class="text-sm font-bold text-slate-800">Dokumen PDF Terlampir</p>
                                <p class="text-xs text-slate-500 mt-0.5">Bukti pembayaran diunggah dalam format PDF resmi perbankan.</p>
                            </div>
                            <a
                                :href="payment.proof_url"
                                target="_blank"
                                class="btn-sm-pitch inline-flex mt-2"
                            >
                                Lihat Dokumen PDF Lengkap
                            </a>
                        </div>

                        <!-- Image View -->
                        <div v-else-if="payment.proof_url" class="relative group max-h-full flex items-center justify-center">
                            <img
                                :src="payment.proof_url"
                                alt="Bukti Transfer Bank"
                                class="max-h-[420px] w-auto max-w-full rounded-lg object-contain shadow-sm cursor-zoom-in transition-transform duration-200 group-hover:scale-[1.01]"
                                @click="isImageZoomed = !isImageZoomed"
                            />
                            <button
                                type="button"
                                class="absolute bottom-3 right-3 bg-slate-900/75 hover:bg-slate-900 text-white text-[11px] px-2.5 py-1.5 rounded-lg backdrop-blur-sm shadow flex items-center gap-1.5 opacity-90 group-hover:opacity-100 transition-opacity"
                                @click="isImageZoomed = true"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                                </svg>
                                <span>Perbesar</span>
                            </button>
                        </div>

                        <!-- No Proof Available -->
                        <div v-else class="text-center p-8 text-slate-400">
                            <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-xs font-semibold text-slate-600">Belum Ada Bukti Pembayaran</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Manajer tim belum mengunggah struk transfer untuk tagihan ini.</p>
                        </div>
                    </div>
                </div>

                <!-- Col Right: Metadata & Bank Details (5 cols on lg) -->
                <div class="lg:col-span-5 flex flex-col justify-between space-y-4">
                    <div class="space-y-4">
                        <!-- Nominal Tagihan Card -->
                        <div class="p-3.5 rounded-xl bg-gradient-to-br from-orange-500/10 via-orange-500/5 to-transparent border border-orange-200/80">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-orange-950/70 block">
                                Nominal Tagihan
                            </span>
                            <div class="text-2xl font-black font-mono tabular-data text-slate-900 mt-1">
                                {{ formatRupiah(payment.total_amount || payment.amount) }}
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-xs text-slate-600">
                                <span class="capitalize font-semibold text-slate-800">{{ payment.method || 'Transfer Bank' }}</span>
                                <span v-if="payment.payment_channel" class="text-slate-400">&bull;</span>
                                <span v-if="payment.payment_channel" class="uppercase text-[11px]">{{ payment.payment_channel }}</span>
                            </div>
                        </div>

                        <!-- Informasi Transfer Bank -->
                        <div class="bg-slate-50/80 rounded-xl p-3.5 border border-slate-200/80 space-y-2.5">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600 block">
                                Rincian Rekening Pengirim
                            </span>

                            <div class="space-y-1.5 text-xs">
                                <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                                    <span class="text-slate-500">Bank Pengirim</span>
                                    <span class="font-bold text-slate-900 uppercase">{{ payment.sender_bank_name || '-' }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                                    <span class="text-slate-500">Nama Pemilik Rekening</span>
                                    <span class="font-bold text-slate-900">{{ payment.sender_account_holder || '-' }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                                    <span class="text-slate-500">Nomor Rekening</span>
                                    <span class="font-mono font-semibold text-slate-900 tabular-data">{{ payment.sender_account_number || '-' }}</span>
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <span class="text-slate-500">Tanggal Transfer</span>
                                    <span class="font-medium text-slate-800 tabular-data">
                                        {{ payment.transfer_date ? formatDate(payment.transfer_date) : '-' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Notes -->
                        <div v-if="payment.rejection_reason" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 space-y-1">
                            <div class="font-bold flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Alasan Penolakan Terakhir</span>
                            </div>
                            <p class="text-[11px] leading-relaxed">{{ payment.rejection_reason }}</p>
                        </div>

                        <div v-if="payment.paid_at" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">
                            <div class="font-bold">Terverifikasi Lunas</div>
                            <p class="text-[11px] text-emerald-700 mt-0.5">
                                Diverifikasi pada {{ formatDateTime(payment.paid_at) }}
                                <span v-if="payment.verifier_name"> oleh {{ payment.verifier_name }}</span>.
                            </p>
                        </div>
                    </div>

                    <!-- Inline Rejection Form Area -->
                    <div v-if="isRejecting" class="p-3.5 bg-rose-50/70 border border-rose-200 rounded-xl space-y-2.5 animate-in fade-in duration-150">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-rose-900">Alasan Penolakan Bukti Transfer</label>
                            <button type="button" class="text-xs text-slate-500 hover:text-slate-800" @click="isRejecting = false">Batal</button>
                        </div>
                        <textarea
                            v-model="verifyForm.rejection_reason"
                            rows="2"
                            class="input-field text-xs py-2"
                            :class="{ 'input-field-error': verifyForm.errors.rejection_reason }"
                            placeholder="Tuliskan alasan penolakan yang jelas (contoh: Nominal mutasi tidak cocok, nama pengirim berbeda)..."
                        ></textarea>
                        <InputError :message="verifyForm.errors.rejection_reason" />

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button
                                type="button"
                                class="btn-sm-secondary"
                                @click="isRejecting = false"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                class="btn-sm-danger"
                                :disabled="verifyForm.processing"
                                @click="handleReject"
                            >
                                {{ verifyForm.processing ? 'Menyimpan...' : 'Konfirmasi Tolak' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Modal: Actions -->
            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2.5 shrink-0">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="btn-secondary"
                        @click="handleClose"
                    >
                        Tutup
                    </button>
                </div>

                <!-- Admin Inline Verification Buttons -->
                <div v-if="canVerify" class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn-secondary text-xs"
                        @click="goToManualQueue"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span>Halaman Verifikasi Lengkap</span>
                    </button>

                    <template v-if="isWaitingVerification && !isRejecting">
                        <button
                            type="button"
                            class="btn-danger"
                            @click="isRejecting = true"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span>Tolak Pembayaran</span>
                        </button>

                        <button
                            type="button"
                            class="btn-success"
                            :disabled="verifyForm.processing"
                            @click="handleApprove"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>{{ verifyForm.processing ? 'Menyetujui...' : 'Setujui Pembayaran (Lunas)' }}</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Fullscreen Zoom Lightbox -->
        <div
            v-if="isImageZoomed && payment.proof_url"
            class="fixed inset-0 z-60 bg-black/90 flex items-center justify-center p-4 cursor-zoom-out"
            @click="isImageZoomed = false"
        >
            <img
                :src="payment.proof_url"
                alt="Bukti Transfer Perbesar"
                class="max-h-[95vh] max-w-[95vw] object-contain rounded shadow-2xl"
            />
            <button
                type="button"
                class="absolute top-4 right-4 text-white/80 hover:text-white p-2 rounded-full bg-white/10 hover:bg-white/20"
                @click.stop="isImageZoomed = false"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
</template>

