<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
import { useFormatters } from '@/Composables/useFormatters';

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
    bankAccount: {
        type: Object,
        default: () => ({}),
    },
    sandboxSimulatorEnabled: {
        type: Boolean,
        default: false,
    },
});

const { formatRupiah, formatDateTime, formatDate } = useFormatters();
const { applyValidation, isEmpty, checkFile } = useFormValidator();

const paymentTab = ref('manual');

const canPay = computed(() => {
    if (!props.payment) return false;
    return ['unpaid', 'rejected'].includes(props.payment.status);
});

// Gateway Form
const gatewayForm = useForm({
    provider: props.payment?.method === 'xendit' ? 'xendit' : 'midtrans',
    payment_channel: props.payment?.payment_channel || 'BCA Virtual Account',
});

const initiateGateway = () => {
    if (!props.payment) return;
    const ok = applyValidation(gatewayForm, () => {
        const errs = {};
        if (isEmpty(gatewayForm.provider)) errs.provider = 'Penyedia pembayaran otomatis wajib dipilih.';
        if (isEmpty(gatewayForm.payment_channel)) errs.payment_channel = 'Saluran pembayaran wajib dipilih.';
        return errs;
    });
    if (!ok) return;

    gatewayForm.post(route('manager.payments.gateway', { payment: props.payment.id }), {
        preserveScroll: true,
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

const handleProofFile = (e) => {
    manualForm.proof_file = e.target.files?.[0] || null;
    manualForm.clearErrors('proof_file');
};

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
            errs.transfer_date = 'Tanggal transfer pembayaran wajib diisi.';
        }
        const fCheck = checkFile(
            manualForm.proof_file,
            ['image/jpeg', 'image/png', 'application/pdf'],
            2 * 1024 * 1024
        );
        if (!fCheck.valid) {
            errs.proof_file = 'Bukti transfer wajib diunggah dalam format JPG, PNG, atau PDF (maksimal 2 MB).';
        }
        return errs;
    });

    if (!ok) return;

    manualForm.post(route('manager.payments.manual-proof', { payment: props.payment.id }), {
        forceFormData: true,
        preserveScroll: true,
    });
};

// Reclaim Slot Form
const reclaimForm = useForm({});
const reclaimSlot = () => {
    if (!props.team) return;
    reclaimForm.post(route('manager.payments.reclaim-slot', { team: props.team.id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Pembayaran Pendaftaran Tim">
        <Head title="Pembayaran Pendaftaran" />

        <div v-if="team" class="space-y-6">
            <!-- Invoice Header Summary -->
            <div v-if="payment" class="frosted-card p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Nomor Invoice Pendaftaran</p>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-lg sm:text-xl font-bold text-slate-900 tabular-data">
                                {{ payment.invoice_number }}
                            </span>
                            <StatusBadge :status="payment.status" />
                        </div>
                    </div>

                    <div class="text-left md:text-right">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total Tagihan</p>
                        <p class="text-2xl font-bold text-emerald-800 tabular-data mt-0.5">
                            {{ formatRupiah(payment.total_amount) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-500 block">Tim &amp; Kompetisi</span>
                        <strong class="text-slate-900 mt-0.5 block">{{ team.name }} &mdash; {{ team.event?.name }}</strong>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Batas Waktu Reservasi Slot</span>
                        <strong class="text-slate-900 tabular-data mt-0.5 block">
                            {{ payment.status === 'paid' ? 'Slot Terkunci Permanen' : formatDateTime(payment.expires_at) }}
                        </strong>
                    </div>
                    <div>
                        <span class="text-slate-500 block">Metode Terakhir</span>
                        <strong class="text-slate-900 mt-0.5 block uppercase">
                            {{ payment.payment_channel || payment.method || 'Belum Dipilih' }}
                        </strong>
                    </div>
                </div>
            </div>

            <!-- State: PAID -->
            <div
                v-if="payment?.status === 'paid'"
                class="frosted-card p-6 border-emerald-300 bg-emerald-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div>
                    <h2 class="text-base font-bold text-emerald-950">Pembayaran Pendaftaran Telah Lunas</h2>
                    <p class="text-xs text-emerald-800 mt-1">
                        Diterima pada <strong class="tabular-data">{{ formatDateTime(payment.paid_at) }}</strong>.
                        Slot tim Anda telah resmi dikunci dan menu pengisian nama pemain beserta dokumen kelengkapan telah terbuka penuh.
                    </p>
                </div>
                <Link :href="route('manager.players')" class="btn-pitch shrink-0">
                    Input Nama Pemain &amp; Dokumen &rarr;
                </Link>
            </div>

            <!-- State: WAITING VERIFICATION -->
            <div
                v-else-if="payment?.status === 'waiting_verification'"
                class="frosted-card p-6 border-amber-300 bg-amber-50/70 space-y-3"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-amber-950">Menunggu Pemeriksaan Staf Finance</h2>
                        <p class="text-xs text-amber-800 mt-1">
                            Bukti transfer dari <strong>{{ payment.sender_bank_name }}</strong> a.n. <strong>{{ payment.sender_account_holder }}</strong>
                            (Tanggal: <span class="tabular-data">{{ formatDate(payment.transfer_date) }}</span>) sedang diperiksa.
                            Selama status ini aktif, slot kuota tim Anda aman dan tidak akan hangus otomatis.
                        </p>
                    </div>
                    <a
                        v-if="payment.proof_url"
                        :href="payment.proof_url"
                        target="_blank"
                        class="btn-secondary text-xs shrink-0"
                    >
                        Lihat Bukti Terunggah
                    </a>
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
                        <p class="text-xs text-rose-800 mt-1">
                            Batas waktu pembayaran telah berakhir sehingga slot sementara dilepas kembali. Klik tombol di samping untuk mengunci ulang slot dan menerbitkan invoice baru apabila kuota kompetisi masih tersedia.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn-pitch shrink-0"
                        :disabled="reclaimForm.processing"
                        @click="reclaimSlot"
                    >
                        {{ reclaimForm.processing ? 'Memeriksa Sisa Kuota...' : 'Ajukan Ulang Slot & Buat Invoice Baru' }}
                    </button>
                </div>
            </div>

            <!-- State: REJECTED Notice (Inline, Never Hidden) -->
            <div
                v-if="payment?.status === 'rejected'"
                class="frosted-card p-5 border-rose-300 bg-rose-50/90 space-y-1.5"
            >
                <p class="text-sm font-bold text-rose-900">Bukti Pembayaran Sebelumnya Ditolak oleh Finance</p>
                <p class="text-xs text-rose-800">
                    <strong>Alasan Penolakan:</strong> {{ payment.rejection_reason }}
                </p>
                <p class="text-xs text-rose-700">
                    Silakan unggah ulang bukti transfer yang sah atau gunakan pembayaran instan sebelum batas waktu perpanjangan
                    <strong class="tabular-data">{{ formatDateTime(payment.expires_at) }}</strong>.
                </p>
            </div>

            <!-- Payment Action Channels (Unpaid or Rejected) -->
            <div v-if="canPay" class="frosted-card overflow-hidden">
                <div class="border-b border-slate-200 bg-slate-100/80 px-4 flex items-center gap-2">
                    <button
                        type="button"
                        class="px-4 py-3 text-xs font-semibold border-b-2 transition-colors"
                        :class="paymentTab === 'manual' ? 'border-emerald-700 text-emerald-800' : 'border-transparent text-slate-600 hover:text-slate-900'"
                        @click="paymentTab = 'manual'"
                    >
                        1. Transfer Bank Manual (Unggah Bukti)
                    </button>
                    <button
                        type="button"
                        class="px-4 py-3 text-xs font-semibold border-b-2 transition-colors"
                        :class="paymentTab === 'gateway' ? 'border-emerald-700 text-emerald-800' : 'border-transparent text-slate-600 hover:text-slate-900'"
                        @click="paymentTab = 'gateway'"
                    >
                        2. Pembayaran Otomatis (Midtrans / Xendit)
                    </button>
                </div>

                <!-- Tab 1: Manual Transfer -->
                <div v-if="paymentTab === 'manual'" class="p-6 space-y-6">
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="text-xs space-y-1">
                            <p class="text-slate-500 font-medium uppercase tracking-wide">Rekening Resmi Panitia</p>
                            <p class="text-sm font-bold text-slate-900">{{ bankAccount.bank_name }} &mdash; <span class="tabular-data">{{ bankAccount.account_number }}</span></p>
                            <p class="text-slate-700">a.n. <strong>{{ bankAccount.account_holder }}</strong></p>
                        </div>
                        <div class="text-left sm:text-right">
                            <span class="text-[11px] text-slate-500 block">Nominal Transfer Tepat</span>
                            <strong class="text-base text-emerald-800 tabular-data">{{ formatRupiah(payment.total_amount) }}</strong>
                        </div>
                    </div>

                    <FormErrorBanner :errors="manualForm.errors" />

                    <form @submit.prevent="submitManualProof" novalidate class="space-y-4">
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
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Unggah Bukti Transfer (JPG, PNG, atau PDF &mdash; Maks. 2 MB)
                                </label>
                                <input
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    class="input-field py-1.5 text-xs"
                                    :class="{ 'input-field-error': manualForm.errors.proof_file }"
                                    @change="handleProofFile"
                                />
                                <InputError :message="manualForm.errors.proof_file" />
                            </div>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="btn-pitch" :disabled="manualForm.processing">
                                {{ manualForm.processing ? 'Mengunggah Bukti...' : 'Kirim Bukti Pembayaran Manual' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab 2: Automatic Payment Gateway -->
                <div v-else class="p-6 space-y-6">
                    <FormErrorBanner :errors="gatewayForm.errors" />

                    <form @submit.prevent="initiateGateway" novalidate class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div data-field="provider">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Penyedia Payment Gateway</label>
                                <select
                                    v-model="gatewayForm.provider"
                                    class="input-field"
                                    @change="gatewayForm.clearErrors('provider')"
                                >
                                    <option value="midtrans">Midtrans (Snap VA &amp; QRIS)</option>
                                    <option value="xendit">Xendit (Invoice VA &amp; E-Wallet)</option>
                                </select>
                                <InputError :message="gatewayForm.errors.provider" />
                            </div>

                            <div data-field="payment_channel">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Saluran Pembayaran</label>
                                <select
                                    v-model="gatewayForm.payment_channel"
                                    class="input-field"
                                    @change="gatewayForm.clearErrors('payment_channel')"
                                >
                                    <option value="BCA Virtual Account">BCA Virtual Account</option>
                                    <option value="Mandiri Virtual Account">Mandiri Virtual Account</option>
                                    <option value="BNI Virtual Account">BNI Virtual Account</option>
                                    <option value="BRI Virtual Account">BRI Virtual Account</option>
                                    <option value="QRIS Instan">QRIS Instan</option>
                                </select>
                                <InputError :message="gatewayForm.errors.payment_channel" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn-secondary" :disabled="gatewayForm.processing">
                                {{ gatewayForm.processing ? 'Membuat Kode Bayar...' : 'Buat Instruksi Pembayaran Otomatis' }}
                            </button>
                        </div>
                    </form>

                    <!-- Active Gateway Reference Box -->
                    <div
                        v-if="payment.external_reference_id && ['midtrans', 'xendit'].includes(payment.method)"
                        class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3 text-xs"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-slate-900 uppercase">
                                    Instruksi Aktif: {{ payment.method }} &mdash; {{ payment.payment_channel }}
                                </p>
                                <p class="text-slate-600 mt-0.5">
                                    Kode Referensi Gateway: <strong class="tabular-data text-slate-900">{{ payment.external_reference_id }}</strong>
                                    <span v-if="payment.snap_token" class="ml-2 text-slate-500">
                                        (Snap Token: <code class="tabular-data">{{ payment.snap_token }}</code>)
                                    </span>
                                </p>
                            </div>
                            <a
                                v-if="payment.payment_url && !sandboxSimulatorEnabled"
                                :href="payment.payment_url"
                                target="_blank"
                                class="btn-pitch text-xs"
                            >
                                Buka Halaman Pembayaran
                            </a>
                        </div>

                        <div
                            v-if="sandboxSimulatorEnabled"
                            class="pt-3 border-t border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <p class="text-slate-600">
                                <strong>Mode Sandbox Pengujian Aktif:</strong> Tombol ini mengirim callback Webhook bertanda tangan asli ({{ payment.method.toUpperCase() }}) untuk memverifikasi pelunasan instan.
                            </p>
                            <button
                                type="button"
                                class="btn-pitch text-xs shrink-0"
                                :disabled="simulateForm.processing"
                                @click="triggerSandboxSettlement"
                            >
                                {{ simulateForm.processing ? 'Memproses Webhook...' : 'Simulasikan Pelunasan Instan (Sandbox)' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Riwayat Tagihan Tim -->
            <div v-if="paymentHistory.length > 1" class="frosted-card overflow-hidden">
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-100/70">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-700">Riwayat Invoice Tim</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 text-xs">
                                <th class="py-2.5 px-3.5">No. Invoice</th>
                                <th class="py-2.5 px-3.5">Metode</th>
                                <th class="py-2.5 px-3.5">Nominal</th>
                                <th class="py-2.5 px-3.5">Kedaluwarsa</th>
                                <th class="py-2.5 px-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70 text-xs">
                            <tr v-for="item in paymentHistory" :key="item.id" class="bg-white hover:bg-slate-50/90">
                                <td class="py-2.5 px-3.5 font-semibold tabular-data text-slate-900">{{ item.invoice_number }}</td>
                                <td class="py-2.5 px-3.5 text-slate-600">{{ item.payment_channel || item.method || '-' }}</td>
                                <td class="py-2.5 px-3.5 tabular-data font-medium">{{ formatRupiah(item.total_amount) }}</td>
                                <td class="py-2.5 px-3.5 tabular-data text-slate-500">{{ formatDateTime(item.expires_at) }}</td>
                                <td class="py-2.5 px-3.5 text-right">
                                    <StatusBadge :status="item.status" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </ManagerLayout>
</template>

