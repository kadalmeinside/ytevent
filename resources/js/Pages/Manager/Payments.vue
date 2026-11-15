<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import PaymentProofModal from '@/Components/PaymentProofModal.vue';
import DocumentViewerModal from '@/Components/DocumentViewerModal.vue';
import FileInput from '@/Components/FileInput.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
import { useFormatters } from '@/Composables/useFormatters';

const page = usePage();

const props = defineProps({
    billingType: {
        type: String,
        default: 'team',
    },
    team: {
        type: Object,
        default: null,
    },
    teamsList: {
        type: Array,
        default: () => [],
    },
    auditionParticipant: {
        type: Object,
        default: null,
    },
    participantsList: {
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
    accountPayments: {
        type: Array,
        default: () => [],
    },
    paymentMethodMode: {
        type: String,
        default: 'midtrans', // 'midtrans', 'xendit', 'gateway', atau 'manual'
    },
    activeGatewayProvider: {
        type: String,
        default: 'midtrans',
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

const isParticipantBilling = computed(() => props.billingType === 'audition_participant' || Boolean(props.auditionParticipant));

const canPay = computed(() => {
    if (!props.payment) return false;
    return ['unpaid', 'rejected'].includes(props.payment.status);
});

const resolvedAccountPayments = computed(() =>
    props.accountPayments?.length > 0 ? props.accountPayments : props.paymentHistory || []
);

const currentUrlQuery = computed(() => {
    const pageUrl = page.url || '';
    const queryIdx = pageUrl.indexOf('?');
    if (queryIdx !== -1) {
        return pageUrl.slice(queryIdx + 1);
    }
    if (!pageUrl && typeof window !== 'undefined') {
        return window.location.search.replace(/^\?/, '');
    }
    return '';
});

const hasExplicitDetailParam = computed(() => {
    const params = new URLSearchParams(currentUrlQuery.value);
    return (
        params.has('detail') ||
        params.has('payment_id') ||
        params.has('participant_id') ||
        params.has('team_id') ||
        params.has('team_context') ||
        params.has('feedback') ||
        params.has('check_status') ||
        params.has('order_id') ||
        params.has('transaction_status')
    );
});

const manualDetailOpen = ref(false);
const isDetailDismissed = ref(false);

watch(
    () => page.url,
    () => {
        manualDetailOpen.value = false;
        isDetailDismissed.value = false;
    }
);

const isDetailOpen = computed(() => {
    if (isDetailDismissed.value) return false;
    if (manualDetailOpen.value) return true;
    if (canPay.value) return true;
    if (hasExplicitDetailParam.value) return true;
    if (resolvedAccountPayments.value.length === 0) return true;
    return false;
});

const pageHeading = computed(() =>
    isDetailOpen.value && canPay.value ? 'Checkout & Pembayaran' : 'Transaksi & Riwayat Pembayaran'
);

const pageSubtitle = computed(() =>
    isDetailOpen.value && canPay.value
        ? 'Periksa rincian tagihan dan selesaikan pembayaran pendaftaran Anda sebelum batas waktu berakhir.'
        : 'Pusat riwayat transaksi, invoice, dan unduhan kwitansi PDF seluruh pendaftaran akun Anda.'
);

const paymentItemUrl = (item) => {
    if (item.audition_participant_id) {
        return route('manager.payments', {
            participant_id: item.audition_participant_id,
            payment_id: item.id,
            detail: 1,
        });
    }
    if (item.team_id) {
        return route('manager.payments', {
            team_id: item.team_id,
            team_context: 1,
            payment_id: item.id,
            detail: 1,
        });
    }
    return route('manager.payments', { payment_id: item.id, detail: 1 });
};

const openInvoiceDetail = (item) => {
    if (item.id === props.payment?.id) {
        isDetailDismissed.value = false;
        manualDetailOpen.value = true;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    isDetailDismissed.value = false;
    manualDetailOpen.value = true;
    router.get(paymentItemUrl(item), {}, { preserveScroll: false });
};

const closeInvoiceDetail = () => {
    manualDetailOpen.value = false;
    isDetailDismissed.value = true;
};

const participantRouteParams = (extra = {}) => {
    if (isParticipantBilling.value && props.auditionParticipant?.id) {
        return { participant_id: props.auditionParticipant.id, ...extra };
    }
    return extra;
};

// Proof Viewer Modal State
const isTicketModalOpen = ref(false);
const openTicketModal = () => {
    if (!props.auditionParticipant?.can_download_bib) return;
    isTicketModalOpen.value = true;
};

const isProofModalOpen = ref(false);
const selectedProofPayment = ref(null);

const openProofModal = (p) => {
    selectedProofPayment.value = {
        ...p,
        team: p.team_id && props.team?.id === p.team_id ? props.team : (p.invoice_meta?.bill_to ? { name: p.invoice_meta.bill_to.name } : props.team),
        auditionParticipant: p.audition_participant_id && props.auditionParticipant?.id === p.audition_participant_id
            ? props.auditionParticipant
            : (p.audition_participant_id && p.invoice_meta?.bill_to ? { full_name: p.invoice_meta.bill_to.name } : props.auditionParticipant),
        event: p.invoice_meta?.event || props.auditionParticipant?.event || props.team?.event,
    };
    isProofModalOpen.value = true;
};

const closeProofModal = () => {
    isProofModalOpen.value = false;
    selectedProofPayment.value = null;
};

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

// Gateway Form
const resolvedGatewayProvider = computed(() =>
    props.activeGatewayProvider === 'xendit' ? 'xendit' : 'midtrans'
);

const gatewayForm = useForm({
    provider: resolvedGatewayProvider.value,
    payment_channel: null,
    force_regenerate: false,
});
const gatewayNotice = ref('');

const feeBorneByTeam = computed(() => {
    return (props.midtransFeeConfig?.fee_borne_by ?? 'team') === 'team';
});

const activeGatewayFee = computed(() => {
    if (!props.payment) return 0;
    if (Number(props.payment.gateway_fee) > 0) {
        return Number(props.payment.gateway_fee);
    }
    if (props.paymentMethodMode === 'manual' || !feeBorneByTeam.value) {
        return 0;
    }
    return Number(props.midtransFeeConfig?.va_fee ?? 4000);
});

const totalPayable = computed(() => {
    if (!props.payment) return 0;
    if (Number(props.payment.gateway_fee) > 0) {
        return Number(props.payment.total_amount);
    }
    const base = Number(props.payment.amount);
    return props.paymentMethodMode !== 'manual' && feeBorneByTeam.value
        ? base + activeGatewayFee.value
        : base;
});

const openSnapPopup = (token = null) => {
    const snapToken = token || props.payment?.snap_token;
    if (!snapToken) return;

    if (props.sandboxSimulatorEnabled) return;

    if (typeof window.snap === 'undefined') {
        if (props.payment?.payment_url) {
            window.open(props.payment.payment_url, '_blank');
        } else {
            gatewayNotice.value = 'Pustaka Midtrans Snap sedang disiapkan. Silakan coba kembali dalam beberapa detik.';
        }
        return;
    }

    window.snap.pay(snapToken, {
        onSuccess: function () {
            router.visit(route('manager.payments', participantRouteParams({ feedback: 'finish' })), { preserveScroll: false });
        },
        onPending: function () {
            router.visit(route('manager.payments', participantRouteParams({ feedback: 'unfinish' })), { preserveScroll: true });
        },
        onError: function (result) {
            console.error('Midtrans Snap Error:', result);
            router.visit(route('manager.payments', participantRouteParams({ feedback: 'error' })), { preserveScroll: true });
        },
        onClose: function () {
            console.log('Customer closed Midtrans Snap popup');
        },
    });
};

const feedbackStatus = computed(() => {
    const params = new URLSearchParams(window.location.search);
    const fb = params.get('feedback') || params.get('transaction_status');
    if (fb === 'unfinish' || fb === 'pending') return 'unfinish';
    if (fb === 'error' || fb === 'cancel' || fb === 'deny' || fb === 'expire') return 'error';
    return null;
});

const loadSnapScript = () => {
    if (!props.midtransClientKey || props.sandboxSimulatorEnabled || resolvedGatewayProvider.value === 'xendit') return;

    const checkAutoOpen = () => {
        const queryParams = new URLSearchParams(window.location.search);
        const hasSnapParam = queryParams.has('snap') || queryParams.get('auto_pay') === '1';
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

const isRegenerating = ref(false);
const isCheckingStatus = ref(false);

const checkStatusOnly = () => {
    if (!props.payment || isCheckingStatus.value) return;
    isCheckingStatus.value = true;
    router.get(
        route('manager.payments', participantRouteParams({ check_status: 1 })),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isCheckingStatus.value = false;
            },
        }
    );
};

const handlePayNow = () => {
    if (!props.payment) return;

    const provider = resolvedGatewayProvider.value;

    if (provider === 'xendit') {
        if (props.payment.payment_url && !props.sandboxSimulatorEnabled) {
            window.open(props.payment.payment_url, '_blank');
            return;
        }
    } else if (props.payment.snap_token && !props.sandboxSimulatorEnabled) {
        openSnapPopup(props.payment.snap_token);
        return;
    }

    gatewayForm.provider = provider;
    gatewayForm.payment_channel = null;
    gatewayForm.force_regenerate = false;
    gatewayForm.post(route('manager.payments.gateway', { payment: props.payment.id }), {
        preserveScroll: true,
        onSuccess: (res) => {
            const freshPayment = res.props?.payment;
            if (freshPayment?.status === 'paid') {
                return;
            }
            if (provider === 'xendit' && freshPayment?.payment_url && !props.sandboxSimulatorEnabled) {
                window.open(freshPayment.payment_url, '_blank');
                return;
            }
            if (freshPayment?.snap_token && !props.sandboxSimulatorEnabled) {
                setTimeout(() => {
                    openSnapPopup(freshPayment.snap_token);
                }, 150);
            }
        },
    });
};

const regenerateAndChangeMethod = () => {
    if (!props.payment || isRegenerating.value) return;
    isRegenerating.value = true;
    const provider = resolvedGatewayProvider.value;
    gatewayForm.provider = provider;
    gatewayForm.payment_channel = null;
    gatewayForm.force_regenerate = true;
    gatewayForm.post(route('manager.payments.gateway', { payment: props.payment.id }), {
        preserveScroll: true,
        onSuccess: (res) => {
            isRegenerating.value = false;
            const freshPayment = res.props?.payment;
            // Jika transaksi sebelumnya telah terverifikasi lunas di Midtrans, jangan buka popup Snap
            if (freshPayment?.status === 'paid') {
                return;
            }
            if (provider === 'xendit' && freshPayment?.payment_url && !props.sandboxSimulatorEnabled) {
                window.open(freshPayment.payment_url, '_blank');
                return;
            }
            if (freshPayment?.snap_token && !props.sandboxSimulatorEnabled) {
                setTimeout(() => {
                    openSnapPopup(freshPayment.snap_token);
                }, 150);
            }
        },
        onError: () => {
            isRegenerating.value = false;
        },
    });
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
    if (!props.team?.id) return;
    reclaimForm.post(route('manager.payments.reclaim-slot', { team: props.team.id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <ManagerLayout
        :team="team"
        :teams-list="teamsList"
        :page-title="pageHeading"
        :hide-layout-h1="true"
        :hide-workflow-banner="true"
        :hide-team-context="true"
    >
        <Head :title="pageHeading" />

        <div class="space-y-6 max-w-5xl">
            <!-- Account-Level Billing Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-4 border-b border-slate-200">
                <div class="min-w-0 space-y-1">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                        {{ pageHeading }}
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-600">
                        {{ pageSubtitle }}
                    </p>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button
                        v-if="isDetailOpen && resolvedAccountPayments.length > 0"
                        type="button"
                        class="btn-sm-secondary cursor-pointer"
                        @click="closeInvoiceDetail"
                    >
                        Tutup Rincian Invoice
                    </button>
                    <Link
                        v-else
                        :href="route('manager.dashboard')"
                        class="btn-sm-secondary inline-flex items-center cursor-pointer"
                    >
                        &larr; Kembali ke Kepesertaan Saya
                    </Link>
                </div>
            </div>

            <!-- On-Demand Invoice Detail & Checkout Section -->
            <div v-if="isDetailOpen" id="invoice-detail-section" class="space-y-6">
            <!-- Registered Student / Participant Checkout Summary -->
            <div
                v-if="auditionParticipant"
                class="frosted-card p-5 border-orange-200/80 bg-gradient-to-br from-white via-white to-orange-50/40 space-y-4"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <img
                            v-if="auditionParticipant.photo_url"
                            :src="auditionParticipant.photo_url"
                            :alt="auditionParticipant.full_name"
                            class="w-12 h-12 rounded-xl object-cover border border-slate-200 shrink-0"
                        />
                        <div
                            v-else
                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 text-white font-black text-sm flex items-center justify-center shrink-0"
                        >
                            {{ auditionParticipant.full_name.substring(0, 2).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ auditionParticipant.registration_number }}
                                </span>
                                <span
                                    class="text-[10px] font-bold px-2 py-0.5 rounded border"
                                    :class="auditionParticipant.is_paid ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                                >
                                    {{ auditionParticipant.is_paid ? 'LUNAS' : 'MENUNGGU PEMBAYARAN' }}
                                </span>
                            </div>
                            <h2 class="text-base sm:text-lg font-black text-slate-900 truncate mt-1">
                                {{ auditionParticipant.full_name }}
                            </h2>
                            <p class="text-xs text-slate-600 truncate">
                                <strong class="text-orange-700">{{ auditionParticipant.event?.name }}</strong>
                                <span v-if="auditionParticipant.event?.age_category"> ({{ auditionParticipant.event.age_category }})</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <Link :href="route('manager.dashboard')" class="btn-sm-secondary inline-flex items-center text-xs cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700">
                            &larr; Kembali ke Kepesertaan Saya
                        </Link>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">NIK / Tanggal Lahir</span>
                        <span class="font-semibold text-slate-800 font-mono tabular-data">{{ auditionParticipant.nik }}</span>
                        <span class="block text-[11px] text-slate-500">{{ auditionParticipant.birth_date }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Posisi &amp; Postur</span>
                        <span class="font-semibold text-slate-800">{{ auditionParticipant.preferred_position || '-' }}</span>
                        <span v-if="auditionParticipant.height_cm" class="block text-[11px] text-slate-500">
                            {{ auditionParticipant.height_cm }} cm / {{ auditionParticipant.weight_kg }} kg
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Jadwal / Sesi</span>
                        <span class="font-semibold text-slate-800">
                            {{ auditionParticipant.session?.session_name || auditionParticipant.event?.match_start_date || 'Sesuai Jadwal Panitia' }}
                        </span>
                        <span class="block text-[11px] text-slate-500 truncate">{{ auditionParticipant.event?.venue_name || auditionParticipant.event?.city || '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] uppercase font-bold text-slate-400">Status Berkas</span>
                        <span class="font-semibold text-slate-800">{{ auditionParticipant.screening_status_label }}</span>
                        <span class="block text-[11px] text-slate-500">{{ auditionParticipant.stage_status_label }}</span>
                    </div>
                </div>
            </div>
            <!-- Gateway Feedback Alerts -->
            <div v-if="feedbackStatus === 'unfinish'" class="p-4 rounded-xl border border-amber-300 bg-amber-50/80 flex items-start gap-3 text-xs">
                <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p class="font-bold text-amber-950">Menunggu Pelunasan Pembayaran</p>
                    <p class="text-amber-800 text-[11px] mt-0.5">Instruksi pembayaran Anda telah tercatat di Midtrans. Silakan selesaikan pembayaran sesuai batas waktu yang tertera pada tagihan.</p>
                </div>
            </div>

            <div v-else-if="feedbackStatus === 'error'" class="p-4 rounded-xl border border-rose-300 bg-rose-50/80 flex items-start gap-3 text-xs">
                <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p class="font-bold text-rose-950">Transaksi Belum Berhasil</p>
                    <p class="text-rose-800 text-[11px] mt-0.5">Terjadi kendala pada pembayaran atau transaksi dibatalkan. Anda dapat mencoba kembali dengan mengklik tombol bayar di bawah.</p>
                </div>
            </div>
            <!-- Standardized Invoice / Kwitansi Resmi Card (Unified for All Event Types) -->
            <div v-if="payment && payment.invoice_meta" class="frosted-card overflow-hidden border-slate-200 bg-white shadow-sm">
                <div class="px-5 py-3.5 bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-orange-400">
                            {{ payment.invoice_meta.document_title }}
                        </span>
                        <div class="flex flex-wrap items-center gap-2.5 mt-0.5">
                            <span class="font-mono text-sm sm:text-base font-black tracking-tight text-white">
                                {{ payment.invoice_number }}
                            </span>
                            <StatusBadge :status="payment.status" />
                        </div>
                    </div>
                    <a
                        v-if="payment.invoice_url"
                        :href="payment.invoice_url"
                        target="_blank"
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg bg-white text-slate-900 hover:bg-orange-50 hover:text-orange-700 text-xs font-bold shadow-sm transition-colors shrink-0"
                    >
                        <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>{{ payment.status === 'paid' ? 'Unduh Kwitansi PDF' : 'Unduh Invoice PDF' }}</span>
                    </a>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pb-4 border-b border-slate-100">
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Ditagihkan Kepada</span>
                            <p class="font-bold text-slate-900 mt-0.5 text-sm">
                                {{ payment.invoice_meta.bill_to?.name || '-' }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                {{ payment.invoice_meta.bill_to?.subject_label }}
                                <template v-if="payment.invoice_meta.bill_to?.registration_number">
                                    &bull; <span class="font-mono">{{ payment.invoice_meta.bill_to.registration_number }}</span>
                                </template>
                            </p>
                            <p class="text-[11px] text-slate-500">
                                PJ: {{ payment.invoice_meta.bill_to?.contact_name || '-' }}
                                <template v-if="payment.invoice_meta.bill_to?.contact_phone">
                                    ({{ payment.invoice_meta.bill_to.contact_phone }})
                                </template>
                            </p>
                        </div>

                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Event &amp; Kategori</span>
                            <p class="font-bold text-slate-900 mt-0.5">
                                {{ payment.invoice_meta.event?.name || '-' }}
                            </p>
                            <p class="text-[11px] text-slate-500">
                                {{ payment.invoice_meta.event?.event_type_label }}
                                <template v-if="payment.invoice_meta.event?.age_category">
                                    &bull; {{ payment.invoice_meta.event.age_category }}
                                </template>
                            </p>
                            <p v-if="payment.invoice_meta.event?.session_name" class="text-[11px] text-slate-500">
                                Sesi: {{ payment.invoice_meta.event.session_name }}
                            </p>
                        </div>

                        <div class="sm:text-right">
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Tanggal &amp; Referensi</span>
                            <p class="text-slate-700 mt-0.5">
                                Terbit: <strong class="tabular-data">{{ payment.invoice_meta.issued_at_formatted }}</strong>
                            </p>
                            <p v-if="payment.invoice_meta.paid_at_formatted" class="text-emerald-700 font-semibold">
                                Lunas: <span class="tabular-data">{{ payment.invoice_meta.paid_at_formatted }}</span>
                            </p>
                            <p v-else-if="payment.invoice_meta.expires_at_formatted" class="text-rose-700 font-semibold">
                                Batas Bayar: <span class="tabular-data">{{ payment.invoice_meta.expires_at_formatted }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200 text-[10px] uppercase tracking-wider text-slate-500">
                                    <th class="py-2 pr-3 font-bold">Rincian Item Tagihan</th>
                                    <th class="py-2 px-2 text-center font-bold">Qty</th>
                                    <th class="py-2 pl-3 text-right font-bold">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(line, idx) in (payment.invoice_meta.line_items || [])" :key="idx">
                                    <td class="py-2.5 pr-3">
                                        <p class="font-semibold text-slate-900">{{ line.title }}</p>
                                        <p v-if="line.description" class="text-[11px] text-slate-500">{{ line.description }}</p>
                                    </td>
                                    <td class="py-2.5 px-2 text-center tabular-data text-slate-700">{{ line.quantity }}</td>
                                    <td class="py-2.5 pl-3 text-right font-semibold tabular-data text-slate-900">{{ formatRupiah(line.amount) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-slate-200">
                                    <td colspan="2" class="pt-2.5 text-right font-bold text-slate-700">Total Tagihan Resmi</td>
                                    <td class="pt-2.5 pl-3 text-right text-sm font-black text-slate-900 tabular-data">{{ formatRupiah(payment.total_amount) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- State: NO ACTIVE BILL -->
            <div v-if="!payment" class="frosted-card p-10 text-center text-xs text-slate-500 space-y-3">
                <svg class="w-10 h-10 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="font-bold text-slate-800 text-sm">Tidak Ada Tagihan Aktif</p>
                <p v-if="isParticipantBilling">Seluruh biaya pendaftaran kegiatan untuk peserta ini telah terselesaikan atau berstatus gratis.</p>
                <p v-else>Seluruh pendaftaran kompetisi tim ini telah terselesaikan atau gratis.</p>
                <div>
                    <Link :href="route('manager.dashboard')" class="btn-secondary text-xs inline-flex items-center cursor-pointer">
                        Kembali ke Kepesertaan Saya
                    </Link>
                </div>
            </div>

            <!-- State: ALREADY PAID / SETTLED -->
            <div
                v-else-if="payment.status === 'paid'"
                class="frosted-card p-6 border-emerald-300 bg-emerald-50/70 space-y-3"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-emerald-950">Pembayaran Pendaftaran Lunas &amp; Sah</h2>
                        <p class="text-xs text-emerald-900 mt-1">
                            Invoice <strong>{{ payment.invoice_number }}</strong> sejumlah
                            <strong class="tabular-data">{{ formatRupiah(payment.total_amount) }}</strong> telah lunas diverifikasi
                            pada <span class="tabular-data font-semibold">{{ formatDateTime(payment.paid_at) }}</span>.
                            <template v-if="isParticipantBilling">
                                Kepesertaan <strong>{{ auditionParticipant?.full_name }}</strong> kini <strong>AKTIF</strong> dan siap mengikuti proses verifikasi berkas &amp; kegiatan.
                            </template>
                            <template v-else>
                                Slot tim Anda kini <strong>AKTIF</strong> dan pengisian nama pemain telah terbuka penuh.
                            </template>
                        </p>
                    </div>
                    <div v-if="isParticipantBilling" class="flex flex-wrap items-center gap-2 shrink-0">
                        <button
                            v-if="auditionParticipant?.can_download_bib"
                            type="button"
                            :aria-label="`Lihat Digital Pass (E-Tiket) ${auditionParticipant.full_name}`"
                            title="Lihat Digital Pass (E-Tiket)"
                            class="btn-sm-secondary shrink-0 cursor-pointer"
                            @click="openTicketModal"
                        >
                            <svg class="w-4 h-4 text-slate-700" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <span>Lihat E-Tiket</span>
                        </button>
                        <a
                            v-if="auditionParticipant?.can_download_bib"
                            :href="route('audition.bib.download', { participant: auditionParticipant.id })"
                            target="_blank"
                            class="btn-sm-pitch inline-flex items-center shrink-0 cursor-pointer"
                        >
                            Unduh Digital Pass (E-Tiket)
                        </a>
                        <Link :href="route('manager.dashboard')" class="btn-sm-secondary inline-flex items-center shrink-0 cursor-pointer">
                            &larr; Kembali ke Kepesertaan Saya
                        </Link>
                    </div>
                    <Link v-else :href="route('manager.players')" class="btn-sm-pitch inline-flex items-center shrink-0 cursor-pointer">
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
                        <h2 class="text-base font-bold text-amber-950">Menunggu Pemeriksaan Staf Finance Panitia</h2>
                        <p class="text-xs text-amber-900 mt-1">
                            Bukti transfer dari <strong>{{ payment.sender_bank_name }}</strong> a.n. <strong>{{ payment.sender_account_holder }}</strong>
                            (Tanggal: <span class="tabular-data">{{ formatDate(payment.transfer_date) }}</span>) sedang diverifikasi.
                            Selama status ini aktif, slot kuota pendaftaran Anda aman dan tidak akan hangus otomatis.
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
                v-else-if="team?.status === 'expired' || payment?.status === 'expired'"
                class="frosted-card p-6 border-rose-300 bg-rose-50/75 space-y-4"
            >
                <FormErrorBanner :errors="reclaimForm.errors" />
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-rose-950">Tagihan &amp; Reservasi Slot Kedaluwarsa</h2>
                        <p class="text-xs text-rose-800 mt-1 leading-relaxed">
                            <template v-if="team">
                                Batas waktu pembayaran telah berakhir sehingga slot sementara dilepas kembali ke kuota umum. Klik tombol di samping untuk mengunci ulang slot dan menerbitkan invoice baru apabila kuota kompetisi masih tersedia.
                            </template>
                            <template v-else>
                                Batas waktu pembayaran peserta telah berakhir dan slot reservasi dilepaskan kembali ke kuota umum. Silakan hubungi panitia atau lakukan pendaftaran ulang apabila kuota masih tersedia.
                            </template>
                        </p>
                    </div>
                    <button
                        v-if="team"
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
                        <h3 class="text-base font-bold text-slate-900">Instruksi Pembayaran - Transfer Bank Manual</h3>
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
                <!-- MODE 2: GATEWAY PEMBAYARAN (MIDTRANS / XENDIT) -->
                <!-- ========================================== -->
                <div v-else class="p-6 space-y-6">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Pembayaran Instan - {{ resolvedGatewayProvider === 'xendit' ? 'Xendit Payment Gateway' : 'Midtrans Payment Gateway' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{
                                resolvedGatewayProvider === 'xendit'
                                    ? 'Selesaikan pembayaran melalui tautan invoice resmi Xendit secara aman (Virtual Account, QRIS, e-Wallet, atau Kartu).'
                                    : 'Selesaikan pembayaran menggunakan Midtrans Snap secara aman. Pilihan bank dan metode transfer tersedia langsung di dalam dialog pembayaran.'
                            }}
                        </p>
                    </div>

                    <FormErrorBanner :errors="gatewayForm.errors" />
                    <div v-if="gatewayNotice" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900" role="status">
                        {{ gatewayNotice }}
                    </div>

                    <form @submit.prevent="handlePayNow" novalidate class="space-y-5">
                        <!-- Gateway Info Card -->
                        <div class="p-4 rounded-xl bg-orange-50/60 border border-orange-200/80 flex items-start gap-3.5 text-xs">
                            <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </div>
                            <div class="space-y-1">
                                <p class="font-bold text-orange-950">
                                    {{ resolvedGatewayProvider === 'xendit' ? 'Pilih Metode Pembayaran via Invoice Xendit:' : 'Pilih Metode Pembayaran Langsung di Dialog Snap:' }}
                                </p>
                                <p class="text-orange-900/80 text-[11px] leading-relaxed">
                                    Klik tombol bayar untuk membuka halaman resmi {{ resolvedGatewayProvider === 'xendit' ? 'Xendit' : 'Midtrans Snap' }}. Anda dapat memilih transfer <strong>Virtual Account (BCA, Mandiri, BNI, BRI, Permata)</strong>, <strong>QRIS (BCA Mobile, Livin, GoPay, dll.)</strong>, atau <strong>Kartu Kredit</strong>.
                                </p>
                            </div>
                        </div>

                        <!-- Live Cost & Breakdown Box -->
                        <div class="p-4 rounded-xl bg-slate-50/90 border border-slate-200/90 space-y-2.5 text-xs">
                            <span class="font-bold text-slate-800 uppercase text-[11px] block">Rincian Tagihan Pembayaran:</span>

                            <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-600">{{ isParticipantBilling ? 'Biaya Pendaftaran Peserta' : 'Biaya Pendaftaran Kompetisi' }}</span>
                                <span class="font-mono tabular-data font-bold text-slate-900">{{ formatRupiah(payment.amount) }}</span>
                            </div>

                            <div class="flex items-center justify-between py-1 border-b border-slate-200/60">
                                <span class="text-slate-600">
                                    Biaya Layanan Gateway ({{ resolvedGatewayProvider === 'xendit' ? 'Xendit' : 'Midtrans' }})
                                </span>
                                <span v-if="feeBorneByTeam" class="font-mono tabular-data font-bold text-orange-700">
                                    + {{ formatRupiah(activeGatewayFee) }}
                                </span>
                                <span v-else class="text-emerald-700 font-bold">
                                    Gratis (Ditanggung Penyelenggara)
                                </span>
                            </div>

                            <div class="flex items-center justify-between pt-1 text-sm font-bold">
                                <span class="text-slate-900">Total Tagihan</span>
                                <span class="font-mono tabular-data text-lg text-slate-900">{{ formatRupiah(totalPayable) }}</span>
                            </div>
                        </div>

                        <!-- Single Unified Action Footer -->
                        <div class="pt-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <p class="text-[11px] text-slate-500">
                                    Batas waktu: <strong class="tabular-data text-slate-800 font-semibold">{{ formatDateTime(payment.expires_at) }}</strong>
                                </p>
                                <p v-if="payment.invoice_number" class="text-[11px] text-slate-400 font-mono">
                                    No. Invoice: <strong class="text-slate-700">{{ payment.invoice_number }}</strong>
                                </p>
                            </div>

                            <div class="flex flex-col sm:items-end gap-2">
                                <button
                                    type="submit"
                                    class="btn-pitch px-6 py-2.5 text-xs font-bold shadow-sm inline-flex items-center justify-center gap-2"
                                    :disabled="gatewayForm.processing || isRegenerating"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span>
                                        {{
                                            (gatewayForm.processing || isRegenerating)
                                                ? 'Menyiapkan Pembayaran...'
                                                : (resolvedGatewayProvider === 'xendit' ? 'Bayar Sekarang via Xendit →' : 'Bayar Sekarang via Midtrans Snap →')
                                        }}
                                    </span>
                                </button>

                                <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                                    <button
                                        v-if="payment.snap_token && !sandboxSimulatorEnabled"
                                        type="button"
                                        class="text-orange-600 hover:text-orange-700 font-semibold underline disabled:opacity-50 inline-flex items-center gap-1.5"
                                        :disabled="isRegenerating"
                                        @click="regenerateAndChangeMethod"
                                    >
                                        <svg v-if="isRegenerating" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <span>{{ isRegenerating ? 'Memeriksa status ke Midtrans...' : 'Ganti / Atur Ulang Metode Pembayaran' }}</span>
                                    </button>

                                    <span v-if="payment.snap_token && !sandboxSimulatorEnabled" class="text-slate-300">|</span>

                                    <button
                                        v-if="payment.snap_token && !sandboxSimulatorEnabled"
                                        type="button"
                                        class="text-slate-600 hover:text-slate-800 font-medium underline disabled:opacity-50 inline-flex items-center gap-1"
                                        :disabled="isCheckingStatus"
                                        @click="checkStatusOnly"
                                    >
                                        <svg v-if="isCheckingStatus" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>{{ isCheckingStatus ? 'Memeriksa status...' : 'Cek Status Pembayaran' }}</span>
                                    </button>

                                    <span v-if="payment.snap_token && payment.payment_url && !sandboxSimulatorEnabled" class="text-slate-300">|</span>

                                    <a
                                        v-if="payment.payment_url && !sandboxSimulatorEnabled"
                                        :href="payment.payment_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-slate-500 hover:text-slate-700 underline"
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
                                Mode Sandbox Simulator Aktif - Simulasikan pembayaran lunas instan tanpa kartu/bank asli.
                            </p>
                            <button
                                type="button"
                                class="btn-sm-pitch shrink-0 cursor-pointer"
                                :disabled="simulateForm.processing"
                                @click="triggerSandboxSettlement"
                            >
                                {{ simulateForm.processing ? 'Memproses Webhook...' : 'Simulasikan Lunas Instan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </div>

            <!-- Account-Wide Payment & Invoice Ledger -->
            <section v-if="resolvedAccountPayments.length > 0" aria-labelledby="account-payments-heading" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 id="account-payments-heading" class="text-sm sm:text-base font-black text-slate-900">
                            Daftar Seluruh Transaksi &amp; Kwitansi Akun
                        </h2>
                        <p class="text-xs text-slate-600 mt-0.5">
                            Semua tagihan dan kwitansi pembayaran resmi lintas pendaftaran tim maupun peserta perorangan Anda.
                        </p>
                    </div>
                    <span class="text-xs text-slate-700 font-semibold tabular-data">
                        {{ resolvedAccountPayments.length }} transaksi tercatat
                    </span>
                </div>

                <div class="bg-white border border-slate-200 rounded-2xl divide-y divide-slate-200 overflow-hidden">
                    <div
                        v-for="item in resolvedAccountPayments"
                        :key="item.id"
                        class="p-4 sm:p-5 grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-start lg:items-center text-xs transition-colors"
                        :class="(isDetailOpen && item.id === payment?.id) ? 'bg-orange-50/40' : 'hover:bg-slate-50/60'"
                    >
                        <!-- Col 1: Invoice Number & Status -->
                        <div class="lg:col-span-3 space-y-1.5 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-xs font-bold text-slate-900 px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                                    {{ item.invoice_number }}
                                </span>
                                <StatusBadge :status="item.status" />
                            </div>
                            <span
                                v-if="isDetailOpen && item.id === payment?.id"
                                class="inline-block px-2 py-0.5 rounded bg-orange-100 text-orange-900 border border-orange-200 text-[10px] font-bold"
                            >
                                Sedang Ditampilkan
                            </span>
                        </div>

                        <!-- Col 2: Participant / Team & Event -->
                        <div class="lg:col-span-3 min-w-0 space-y-0.5">
                            <p class="font-bold text-slate-900 text-xs sm:text-sm truncate" :title="item.invoice_meta?.bill_to?.name">
                                {{ item.invoice_meta?.bill_to?.name || '-' }}
                            </p>
                            <p class="text-[11px] text-slate-600 truncate" :title="item.invoice_meta?.event?.name">
                                <span v-if="item.invoice_meta?.bill_to?.subject_label">{{ item.invoice_meta.bill_to.subject_label }} • </span>
                                {{ item.invoice_meta?.event?.name || '-' }}
                            </p>
                        </div>

                        <!-- Col 3: Method & Date -->
                        <div class="lg:col-span-2 min-w-0 space-y-0.5">
                            <span class="block font-semibold text-slate-800 uppercase text-[11px]">
                                {{ item.payment_channel || item.method || 'Gateway / Transfer' }}
                            </span>
                            <span class="block text-[11px] text-slate-600 tabular-data">
                                {{ item.invoice_meta?.paid_at_formatted || item.invoice_meta?.issued_at_formatted || formatDateTime(item.expires_at) }}
                            </span>
                        </div>

                        <!-- Col 4: Nominal -->
                        <div class="lg:col-span-1 lg:text-right">
                            <span class="font-mono tabular-data font-black text-slate-900 text-sm whitespace-nowrap">
                                {{ formatRupiah(item.total_amount) }}
                            </span>
                        </div>

                        <!-- Col 5: Actions -->
                        <div class="lg:col-span-3 flex flex-wrap lg:justify-end items-center gap-2">
                            <button
                                v-if="['unpaid', 'rejected'].includes(item.status) && !(isDetailOpen && item.id === payment?.id)"
                                type="button"
                                class="h-8 px-2.5 sm:px-3 inline-flex items-center justify-center rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                                @click="openInvoiceDetail(item)"
                            >
                                Pilih &amp; Bayar
                            </button>
                            <button
                                v-else-if="!(isDetailOpen && item.id === payment?.id)"
                                type="button"
                                class="h-8 px-2.5 sm:px-3 inline-flex items-center justify-center rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                                @click="openInvoiceDetail(item)"
                            >
                                Lihat Rincian
                            </button>
                            <button
                                v-else
                                type="button"
                                class="h-8 px-2.5 sm:px-3 inline-flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                                @click="closeInvoiceDetail"
                            >
                                Tutup Rincian
                            </button>
                            <a
                                v-if="item.invoice_url"
                                :href="item.invoice_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="h-8 px-2.5 sm:px-3 inline-flex items-center justify-center gap-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                            >
                                <svg class="w-3.5 h-3.5 text-slate-600 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>{{ item.status === 'paid' ? 'Unduh Kwitansi PDF' : 'Unduh Invoice PDF' }}</span>
                            </a>
                            <button
                                v-if="item.proof_url"
                                type="button"
                                class="h-8 px-2.5 sm:px-3 inline-flex items-center justify-center gap-1.5 rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                                @click="openProofModal(item)"
                            >
                                <svg class="w-3.5 h-3.5 text-orange-700 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>Lihat Bukti</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Integrated Proof Modal for Manager View -->
        <PaymentProofModal
            :show="isProofModalOpen"
            :payment="selectedProofPayment"
            :can-verify="false"
            @close="closeProofModal"
        />

        <!-- Digital Pass E-Tiket Viewer Modal -->
        <DocumentViewerModal
            v-if="auditionParticipant?.can_download_bib"
            :is-open="isTicketModalOpen"
            :title="`Digital Pass (E-Tiket) - ${auditionParticipant.full_name}`"
            :subtitle="auditionParticipant.event?.name || ''"
            :file-url="route('audition.bib.download', { participant: auditionParticipant.id, inline: 1 })"
            :document-number="auditionParticipant.registration_number || ''"
            :is-pdf="true"
            @close="isTicketModalOpen = false"
        />
    </ManagerLayout>
</template>
