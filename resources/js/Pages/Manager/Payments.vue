<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import PaymentProofModal from '@/Components/PaymentProofModal.vue';
import FileInput from '@/Components/FileInput.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
import { useFormatters } from '@/Composables/useFormatters';

const page = usePage();

const props = defineProps({
    team: {
        type: Object,
        default: null,
    },
    teamsList: {
        type: Array,
        default: () => [],
    },
    payment: {
        type: Object,
        default: null,
    },
    paymentHistory: {
        type: Array,
        default: () => [],
    },
    paymentMethodMode: {
        type: String,
        default: 'midtrans', // 'midtrans' atau 'manual'
    },
    bankAccounts: {
        type: Array,
        default: () => [],
    },
    midtransFeeConfig: {
        type: Object,
        default: () => ({
            fee_borne_by: 'team',
            va_fee: 4000,
            qris_fee_percent: 0.7,
            ewallet_fee_percent: 1.5,
            cc_fee_percent: 2.9,
            cc_fee_fixed: 2000,
        }),
    },
    midtransClientKey: {
        type: String,
        default: '',
    },
    midtransSnapJsUrl: {
        type: String,
        default: 'https://app.midtrans.com/snap/snap.js',
    },
    midtransIsProduction: {
        type: Boolean,
        default: false,
    },
    sandboxSimulatorEnabled: {
        type: Boolean,
        default: false,
    },
});

const { formatRupiah, formatDateTime, formatDate } = useFormatters();
const { applyValidation, isEmpty } = useFormValidator();

// Proof Viewer Modal State
const isProofModalOpen = ref(false);
const selectedProofPayment = ref(null);

const openProofModal = (p) => {
    selectedProofPayment.value = {
        ...p,
        team: props.team,
        event: props.team?.event,
    };
    isProofModalOpen.value = true;
};

const closeProofModal = () => {
    isProofModalOpen.value = false;
    selectedProofPayment.value = null;
};

const canPay = computed(() => {
    if (!props.payment) return false;
    return ['unpaid', 'rejected'].includes(props.payment.status);
});

// Copy Account Number Helper
const copiedAccountIndex = ref(null);
const copyAccountNumber = (accNumber, index) => {
    if (!accNumber) return;
    navigator.clipboard.writeText(accNumber).then(() => {
        copiedAccountIndex.value = index;
        setTimeout(() => {
            if (copiedAccountIndex.value === index) copiedAccountIndex.value = null;
        }, 2200);
    });
};

// Available Midtrans Payment Channels
const midtransChannels = [
    { id: 'BCA Virtual Account', name: 'BCA Virtual Account', type: 'va' },
    { id: 'Mandiri Bill', name: 'Mandiri Bill (VA)', type: 'va' },
    { id: 'BNI Virtual Account', name: 'BNI Virtual Account', type: 'va' },
    { id: 'BRI Virtual Account', name: 'BRI Virtual Account', type: 'va' },
    { id: 'Permata Virtual Account', name: 'Permata Virtual Account', type: 'va' },
    { id: 'QRIS', name: 'QRIS (GoPay / BCA Mobile / All e-Wallet)', type: 'qris' },
    { id: 'GoPay', name: 'GoPay E-Wallet', type: 'ewallet' },
    { id: 'ShopeePay', name: 'ShopeePay E-Wallet', type: 'ewallet' },
    { id: 'Credit Card', name: 'Kartu Kredit / Debit Visa/Mastercard', type: 'cc' },
];

// Gateway Form
const gatewayForm = useForm({
    provider: 'midtrans',
    payment_channel: props.payment?.payment_channel || 'BCA Virtual Account',
});

const currentSelectedChannel = computed(() => {
    return midtransChannels.find((c) => c.id === gatewayForm.payment_channel) || midtransChannels[0];
});

// Dynamic MDR Fee Calculation for Midtrans
const calculatedMdrFee = computed(() => {
    if (!props.payment) return 0;
    const base = Number(props.payment.amount || props.payment.total_amount);
    const cfg = props.midtransFeeConfig || {};
    const type = currentSelectedChannel.value?.type;

    if (type === 'qris') {
        return Math.round(base * ((cfg.qris_fee_percent ?? 0.7) / 100));
    }
    if (type === 'ewallet') {
        return Math.round(base * ((cfg.ewallet_fee_percent ?? 1.5) / 100));
    }
    if (type === 'cc') {
        return Math.round((base * ((cfg.cc_fee_percent ?? 2.9) / 100)) + (cfg.cc_fee_fixed ?? 2000));
    }
    // Default Virtual Account (flat Rp)
    return Number(cfg.va_fee ?? 4000);
});

const feeBorneByTeam = computed(() => {
    return (props.midtransFeeConfig?.fee_borne_by ?? 'team') === 'team';
});

const calculatedTotalPayable = computed(() => {
    if (!props.payment) return 0;
    const base = Number(props.payment.amount || props.payment.total_amount);
    return feeBorneByTeam.value ? base + calculatedMdrFee.value : base;
});

const openSnapPopup = (token = null) => {
    const snapToken = token || props.payment?.snap_token;
    if (!snapToken) return;

    if (props.sandboxSimulatorEnabled) return;

    if (typeof window.snap === 'undefined') {
        if (props.payment?.payment_url) {
            window.open(props.payment.payment_url, '_blank');
        } else {
            alert('Pustaka Midtrans Snap sedang disiapkan. Silakan coba kembali dalam beberapa detik.');
        }
        return;
    }

    window.snap.pay(snapToken, {
        onSuccess: function (result) {
            router.reload({ preserveScroll: true });
        },
        onPending: function (result) {
            router.reload({ preserveScroll: true });
        },
        onError: function (result) {
            console.error('Midtrans Snap Error:', result);
            router.reload({ preserveScroll: true });
        },
        onClose: function () {
            console.log('Customer closed Midtrans Snap popup');
        },
    });
};

const loadSnapScript = () => {
    if (!props.midtransClientKey || props.sandboxSimulatorEnabled) return;

    const checkAutoOpen = () => {
        const queryParams = new URLSearchParams(window.location.search);
        const hasSnapParam = queryParams.has('snap');
        const hasFlashSnap = Boolean(page.props?.flash?.auto_open_snap);
        const alreadyOpenedKey = `snap_opened_${props.payment?.id}`;
        const hasBeenOpened = sessionStorage.getItem(alreadyOpenedKey);

        if ((hasSnapParam || hasFlashSnap || !hasBeenOpened) && props.payment?.snap_token && props.payment?.status === 'unpaid') {
            sessionStorage.setItem(alreadyOpenedKey, '1');
            setTimeout(() => {
                openSnapPopup(props.payment.snap_token);
            }, 300);
        }
    };

    const existing = document.getElementById('midtrans-snap-script');
    if (existing) {
        checkAutoOpen();
        return;
    }

    const script = document.createElement('script');
    script.id = 'midtrans-snap-script';
    script.src = props.midtransSnapJsUrl;
    script.setAttribute('data-client-key', props.midtransClientKey);
    script.async = true;
    script.onload = () => {
        checkAutoOpen();
    };
    document.head.appendChild(script);
};

onMounted(() => {
    loadSnapScript();
});

const initiateGateway = () => {
    if (!props.payment) return;
    gatewayForm.provider = 'midtrans';
    gatewayForm.post(route('manager.payments.gateway', { payment: props.payment.id }), {
        preserveScroll: true,
        onSuccess: (res) => {
            const freshPayment = res.props?.payment;
            if (freshPayment?.snap_token && !props.sandboxSimulatorEnabled) {
                setTimeout(() => {
                    openSnapPopup(freshPayment.snap_token);
                }, 150);
            }
        },
    });
};

const handlePayNow = () => {
    if (!props.payment) return;

    // Jika payment sudah memiliki snap_token dan channel tidak berubah, langsung buka Snap secara instan!
    const channelMatches = props.payment.payment_channel === gatewayForm.payment_channel;
    if (props.payment.snap_token && channelMatches && !props.sandboxSimulatorEnabled) {
        openSnapPopup(props.payment.snap_token);
        return;
    }

    // Jika channel berubah atau belum ada token, submit initiateGateway yang otomatis memicu Snap di onSuccess
    initiateGateway();
};

const simulateForm = useForm({});
const triggerSandboxSettlement = () => {
    if (!props.payment) return;
    simulateForm.post(route('manager.payments.simulate', { payment: props.payment.id }), {
        preserveScroll: true,
    });
};

// Manual Proof Form
const todayStr = new Date().toISOString().slice(0, 10);
const manualForm = useForm({
    sender_bank_name: props.payment?.sender_bank_name || '',
    sender_account_holder: props.payment?.sender_account_holder || '',
    sender_account_number: props.payment?.sender_account_number || '',
    transfer_date: props.payment?.transfer_date || todayStr,
    proof_file: null,
});

const submitManualProof = () => {
    if (!props.payment) return;
    const ok = applyValidation(manualForm, () => {
        const errs = {};
        if (isEmpty(manualForm.sender_bank_name)) {
            errs.sender_bank_name = 'Nama bank pengirim wajib diisi.';
        }
        if (isEmpty(manualForm.sender_account_holder)) {
            errs.sender_account_holder = 'Nama pemilik rekening pengirim wajib diisi.';
        }
        if (isEmpty(manualForm.transfer_date)) {
            errs.transfer_date = 'Tanggal transfer mutasi wajib diisi.';
        }
        if (!manualForm.proof_file) {
            errs.proof_file = 'Bukti transfer wajib diunggah dalam format JPG, PNG, atau PDF (maksimal 2 MB).';
        }
        return errs;
    });

    if (!ok) return;

    manualForm.post(route('manager.payments.manual-proof', { payment: props.payment.id }), {
        preserveScroll: true,
        onSuccess: () => {
            manualForm.reset('proof_file');
        },
    });
};

// Reclaim Slot Form
const reclaimForm = useForm({});
const reclaimSlot = () => {
    reclaimForm.post(route('manager.teams.reclaim-slot', { team: props.team.id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <ManagerLayout
        :team="team"
        :teams-list="teamsList"
        page-title="Pembayaran Pendaftaran Tim"
        subtitle="Selesaikan tagihan pendaftaran kompetisi untuk mengaktifkan slot permanen dan membuka formulir pemain."
    >
        <Head title="Pembayaran Pendaftaran" />

        <div class="space-y-6 max-w-5xl">
            <!-- State: NO ACTIVE BILL -->
            <div v-if="!payment" class="frosted-card p-10 text-center text-xs text-slate-500 space-y-2">
                <svg class="w-10 h-10 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="font-bold text-slate-800 text-sm">Tidak Ada Tagihan Aktif</p>
                <p>Seluruh pendaftaran kompetisi tim ini telah terselesaikan atau gratis.</p>
            </div>

            <!-- State: ALREADY PAID / SETTLED -->
            <div
                v-else-if="payment.status === 'paid'"
                class="frosted-card p-6 border-emerald-300 bg-emerald-50/70 space-y-3"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            <h2 class="text-base font-bold text-emerald-950">Pembayaran Pendaftaran Lunas &amp; Sah</h2>
                        </div>
                        <p class="text-xs text-emerald-800 mt-1">
                            Invoice <strong>{{ payment.invoice_number }}</strong> sejumlah
                            <strong class="tabular-data">{{ formatRupiah(payment.total_amount) }}</strong> telah lunas diverifikasi
                            pada <span class="tabular-data font-semibold">{{ formatDateTime(payment.paid_at) }}</span>.
                            Slot tim Anda kini <strong>AKTIF</strong> dan pengisian nama pemain telah terbuka penuh.
                        </p>
                    </div>
                    <Link :href="route('manager.players')" class="btn-pitch shrink-0">
                        Kelola Daftar Pemain &rarr;
                    </Link>
                </div>
            </div>

            <!-- State: WAITING VERIFICATION (Manual Transfer Uploaded) -->
            <div
                v-else-if="payment.status === 'waiting_verification'"
                class="frosted-card p-6 border-amber-300 bg-amber-50/70 space-y-3"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            <h2 class="text-base font-bold text-amber-950">Menunggu Pemeriksaan Staf Finance Panitia</h2>
                        </div>
                        <p class="text-xs text-amber-800 mt-1">
                            Bukti transfer dari <strong>{{ payment.sender_bank_name }}</strong> a.n. <strong>{{ payment.sender_account_holder }}</strong>
                            (Tanggal: <span class="tabular-data">{{ formatDate(payment.transfer_date) }}</span>) sedang diverifikasi.
                            Selama status ini aktif, slot kuota tim Anda aman dan tidak akan hangus otomatis.
                        </p>
                    </div>
                    <button
                        v-if="payment.proof_url"
                        type="button"
                        class="btn-secondary text-xs shrink-0"
                        @click="openProofModal(payment)"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>Lihat Bukti Terunggah</span>
                    </button>
                </div>
            </div>

            <!-- State: EXPIRED (Reclaim Slot) -->
            <div
                v-else-if="team.status === 'expired' || payment?.status === 'expired'"
                class="frosted-card p-6 border-rose-300 bg-rose-50/75 space-y-4"
            >
                <FormErrorBanner :errors="reclaimForm.errors" />
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-rose-950">Tagihan &amp; Reservasi Slot Kedaluwarsa</h2>
                        <p class="text-xs text-rose-800 mt-1 leading-relaxed">
                            Batas waktu pembayaran telah berakhir sehingga slot sementara dilepas kembali ke kuota umum. Klik tombol di samping untuk mengunci ulang slot dan menerbitkan invoice baru apabila kuota kompetisi masih tersedia.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn-pitch shrink-0"
                        :disabled="reclaimForm.processing"
                        @click="reclaimSlot"
                    >
                        {{ reclaimForm.processing ? 'Memeriksa Sisa Kuota...' : 'Ajukan Ulang Slot & Buat Tagihan Baru' }}
                    </button>
                </div>
            </div>

            <!-- State: REJECTED (Warning Notice) -->
            <div
                v-if="payment?.status === 'rejected'"
                class="frosted-card p-5 border-rose-300 bg-rose-50/90 space-y-1.5"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <p class="text-sm font-bold text-rose-900">Bukti Pembayaran Sebelumnya Ditolak oleh Staf Finance</p>
                </div>
                <p class="text-xs text-rose-800 ml-7">
                    <strong>Alasan Penolakan:</strong> {{ payment.rejection_reason }}
                </p>
                <p class="text-xs text-rose-700 ml-7">
                    Silakan unggah ulang bukti transfer yang sah sebelum batas waktu perpanjangan
                    <strong class="tabular-data">{{ formatDateTime(payment.expires_at) }}</strong>.
                </p>
            </div>

            <!-- ACTIVE PAYMENT WORKFLOW (Unpaid or Rejected) -->
            <div v-if="canPay" class="frosted-card overflow-hidden">
                <!-- ========================================== -->
                <!-- MODE 1: TRANSFER BANK MANUAL (Multi-Rekening) -->
                <!-- ========================================== -->
                <div v-if="paymentMethodMode === 'manual'" class="p-6 space-y-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-orange-600"></span>
                            <h3 class="text-base font-bold text-slate-900">Instruksi Pembayaran &mdash; Transfer Bank Manual</h3>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Silakan lakukan transfer ke salah satu rekening resmi panitia di bawah ini sesuai nominal tepat tagihan invoice Anda.
                        </p>
                    </div>

                    <!-- Destination Bank Accounts Cards Grid -->
                    <div>
                        <span class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-2">
                            Pilihan Rekening Resmi Penerima ({{ bankAccounts.length }} Rekening Tersedia)
                        </span>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                            <div
                                v-for="(acc, idx) in bankAccounts"
                                :key="idx"
                                class="p-4 rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white to-slate-50/80 shadow-xs space-y-2 relative"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-md text-xs font-black uppercase tracking-wider bg-orange-100 text-orange-800 border border-orange-200">
                                        {{ acc.bank_name }}
                                    </span>
                                    <span v-if="acc.notes" class="text-[11px] text-slate-500 truncate max-w-[140px]">{{ acc.notes }}</span>
                                </div>

                                <div class="pt-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-base sm:text-lg font-mono font-bold tabular-data text-slate-900 tracking-wider">
                                            {{ acc.account_number }}
                                        </span>
                                        <button
                                            type="button"
                                            class="btn-sm-secondary text-xs h-7 px-2.5"
                                            @click="copyAccountNumber(acc.account_number, idx)"
                                        >
                                            <svg v-if="copiedAccountIndex === idx" class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                            </svg>
                                            <span>{{ copiedAccountIndex === idx ? 'Tersalin!' : 'Salin Rekening' }}</span>
                                        </button>
                                    </div>
                                    <p class="text-xs text-slate-600 mt-1">
                                        a.n. <strong class="text-slate-900">{{ acc.account_holder }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Nominal Tagihan Box -->
                    <div class="p-4 rounded-xl bg-gradient-to-r from-orange-500/10 via-amber-500/5 to-transparent border border-orange-200/90 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <span class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Nominal Transfer Harus Tepat:</span>
                            <div class="text-2xl font-black font-mono tabular-data text-slate-900 mt-0.5">
                                {{ formatRupiah(payment.total_amount) }}
                            </div>
                        </div>
                        <div class="text-xs text-slate-500 sm:text-right">
                            <span>Batas Akhir Pelunasan:</span>
                            <strong class="block tabular-data text-orange-950 font-bold">{{ formatDateTime(payment.expires_at) }}</strong>
                        </div>
                    </div>

                    <FormErrorBanner :errors="manualForm.errors" />

                    <!-- Upload Proof Form with FileInput -->
                    <form @submit.prevent="submitManualProof" novalidate class="space-y-4 pt-2">
                        <div class="border-t border-slate-200 pt-4">
                            <h4 class="text-sm font-bold text-slate-900 mb-1">Konfirmasi &amp; Unggah Bukti Transfer</h4>
                            <p class="text-xs text-slate-500 mb-4">Lengkapi data rekening pengirim mutasi untuk verifikasi staf finance.</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div data-field="sender_bank_name">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Bank Pengirim</label>
                                    <input
                                        v-model="manualForm.sender_bank_name"
                                        type="text"
                                        placeholder="Contoh: BCA / Mandiri / BRI / BSI"
                                        class="input-field"
                                        :class="{ 'input-field-error': manualForm.errors.sender_bank_name }"
                                        @input="manualForm.clearErrors('sender_bank_name')"
                                    />
                                    <InputError :message="manualForm.errors.sender_bank_name" />
                                </div>

                                <div data-field="sender_account_holder">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pemilik Rekening Pengirim</label>
                                    <input
                                        v-model="manualForm.sender_account_holder"
                                        type="text"
                                        placeholder="Sesuai buku tabungan / mutasi"
                                        class="input-field"
                                        :class="{ 'input-field-error': manualForm.errors.sender_account_holder }"
                                        @input="manualForm.clearErrors('sender_account_holder')"
                                    />
                                    <InputError :message="manualForm.errors.sender_account_holder" />
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Rekening Pengirim (Opsional)</label>
                                    <input
                                        v-model="manualForm.sender_account_number"
                                        type="text"
                                        placeholder="Contoh: 1234567890"
                                        class="input-field tabular-data"
                                    />
                                </div>

                                <div data-field="transfer_date">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Transfer</label>
                                    <input
                                        v-model="manualForm.transfer_date"
                                        type="date"
                                        class="input-field tabular-data"
                                        :class="{ 'input-field-error': manualForm.errors.transfer_date }"
                                        @input="manualForm.clearErrors('transfer_date')"
                                    />
                                    <InputError :message="manualForm.errors.transfer_date" />
                                </div>

                                <div data-field="proof_file" class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                        Berkas Bukti Transfer (JPG, PNG, atau PDF)
                                    </label>
                                    <FileInput
                                        v-model="manualForm.proof_file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        label="Pilih Bukti Transfer"
                                        hint="Format: JPG, PNG, atau PDF (maks. 2 MB)"
                                        :error="manualForm.errors.proof_file"
                                        @change="manualForm.clearErrors('proof_file')"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="btn-pitch" :disabled="manualForm.processing">
                                {{ manualForm.processing ? 'Mengunggah Bukti...' : 'Kirim Bukti Pembayaran Manual' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ========================================== -->
                <!-- MODE 2: MIDTRANS PAYMENT GATEWAY           -->
                <!-- ========================================== -->
                <div v-else class="p-6 space-y-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                            <h3 class="text-base font-bold text-slate-900">Pembayaran Instan &mdash; Midtrans Payment Gateway</h3>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Pilih kanal pembayaran (Virtual Account bank, QRIS, atau E-Wallet). Biaya MDR akan disesuaikan otomatis dengan metode yang Anda pilih.
                        </p>
                    </div>

                    <FormErrorBanner :errors="gatewayForm.errors" />

                    <form @submit.prevent="handlePayNow" novalidate class="space-y-5">
                        <!-- Channel Grid Selector -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">
                                Pilih Kanal Pembayaran Midtrans:
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <label
                                    v-for="ch in midtransChannels"
                                    :key="ch.id"
                                    class="p-3.5 rounded-xl border-2 transition-all cursor-pointer flex items-center justify-between"
                                    :class="gatewayForm.payment_channel === ch.id
                                        ? 'border-orange-500 bg-orange-50/80 shadow-xs ring-2 ring-orange-500/10'
                                        : 'border-slate-200 bg-white hover:bg-slate-50/80'"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <input
                                            v-model="gatewayForm.payment_channel"
                                            type="radio"
                                            :value="ch.id"
                                            class="text-orange-600 focus:ring-orange-500"
                                        />
                                        <span class="text-xs font-bold text-slate-900 truncate">{{ ch.name }}</span>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 shrink-0">
                                        {{ ch.type }}
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Live Cost & MDR Breakdown Box -->
                        <div class="p-4 rounded-xl bg-slate-50/90 border border-slate-200/90 space-y-2.5 text-xs">
                            <span class="font-bold text-slate-800 uppercase text-[11px] block">Rincian Perhitungan Tagihan (MDR):</span>

                            <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-600">Biaya Registrasi Kompetisi</span>
                                <span class="font-mono tabular-data font-bold text-slate-900">{{ formatRupiah(payment.amount || payment.total_amount) }}</span>
                            </div>

                            <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-600">
                                    Biaya Layanan &amp; MDR Midtrans ({{ currentSelectedChannel.name }})
                                </span>
                                <span v-if="feeBorneByTeam" class="font-mono tabular-data font-bold text-orange-700">
                                    + {{ formatRupiah(calculatedMdrFee) }}
                                </span>
                                <span v-else class="text-emerald-700 font-bold">
                                    Gratis (Ditanggung Penyelenggara)
                                </span>
                            </div>

                            <div class="flex items-center justify-between pt-1 text-sm font-bold">
                                <span class="text-slate-900">Total Pembayaran</span>
                                <span class="font-mono tabular-data text-lg text-slate-900">{{ formatRupiah(calculatedTotalPayable) }}</span>
                            </div>
                        </div>

                        <!-- Single Unified Action Footer -->
                        <div class="pt-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <p class="text-[11px] text-slate-500">
                                    Batas waktu: <strong class="tabular-data text-slate-800 font-semibold">{{ formatDateTime(payment.expires_at) }}</strong>
                                </p>
                                <p v-if="payment.external_reference_id" class="text-[11px] text-slate-400 font-mono">
                                    No. Invoice: <strong class="text-slate-700">{{ payment.external_reference_id }}</strong>
                                </p>
                            </div>

                            <div class="flex flex-col sm:items-end gap-1.5">
                                <button
                                    type="submit"
                                    class="btn-pitch px-6 py-2.5 text-xs font-bold shadow-sm inline-flex items-center justify-center gap-2"
                                    :disabled="gatewayForm.processing"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span>{{ gatewayForm.processing ? 'Menyiapkan Midtrans Snap...' : 'Bayar Sekarang via Midtrans Snap &rarr;' }}</span>
                                </button>

                                <div v-if="payment.payment_url && !sandboxSimulatorEnabled" class="text-[11px] text-slate-400">
                                    <span>Pop-up Snap terhalang blocker browser? </span>
                                    <a
                                        :href="payment.payment_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-orange-600 hover:text-orange-700 underline font-semibold"
                                    >
                                        Buka di tab baru &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Sandbox Simulator Action -->
                        <div
                            v-if="sandboxSimulatorEnabled"
                            class="pt-3 border-t border-orange-200/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-orange-50/80 p-3.5 rounded-xl border border-orange-200/60"
                        >
                            <p class="text-orange-950 font-medium text-xs">
                                Mode Sandbox Simulator Aktif &mdash; Simulasikan pembayaran lunas instan tanpa kartu/bank asli.
                            </p>
                            <button
                                type="button"
                                class="btn-sm-pitch shrink-0"
                                :disabled="simulateForm.processing"
                                @click="triggerSandboxSettlement"
                            >
                                {{ simulateForm.processing ? 'Memproses Webhook...' : 'Simulasikan Lunas Instan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Riwayat Tagihan Tim (Responsive Card List View) -->
            <div v-if="paymentHistory.length > 1" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-700">Riwayat Invoice &amp; Transaksi Tim</h3>
                    <span class="text-xs text-slate-500 font-medium">Total {{ paymentHistory.length }} riwayat tagihan</span>
                </div>

                <div class="space-y-2.5">
                    <div
                        v-for="item in paymentHistory"
                        :key="item.id"
                        class="frosted-card p-4 sm:p-5 hover:border-orange-300/80 hover:shadow-md transition-all duration-150 bg-white"
                    >
                        <!-- Header: Invoice, Status & Proof Button -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-100">
                            <div class="flex items-center gap-2.5">
                                <span class="font-mono text-xs font-bold text-slate-900 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200">
                                    {{ item.invoice_number }}
                                </span>
                                <StatusBadge :status="item.status" />
                            </div>

                            <button
                                v-if="item.proof_url"
                                type="button"
                                class="btn-sm-secondary"
                                @click="openProofModal(item)"
                            >
                                <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>Lihat Bukti Bayar</span>
                            </button>
                        </div>

                        <!-- Card Body Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs items-center">
                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Metode Pembayaran</span>
                                <span class="font-semibold text-slate-800 uppercase text-xs">
                                    {{ item.payment_channel || item.method || 'Transfer Manual' }}
                                </span>
                            </div>

                            <div>
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Nominal Tagihan</span>
                                <span class="font-mono tabular-data font-bold text-slate-900 text-sm">
                                    {{ formatRupiah(item.total_amount) }}
                                </span>
                            </div>

                            <div class="sm:text-right">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Batas Waktu / Kedaluwarsa</span>
                                <span class="text-xs font-mono tabular-data text-slate-600">
                                    {{ formatDateTime(item.expires_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integrated Proof Modal for Manager View -->
        <PaymentProofModal
            :show="isProofModalOpen"
            :payment="selectedProofPayment"
            :can-verify="false"
            @close="closeProofModal"
        />
    </ManagerLayout>
</template>
