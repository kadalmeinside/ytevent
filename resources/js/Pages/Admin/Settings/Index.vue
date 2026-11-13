<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import FileInput from '@/Components/FileInput.vue';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    availableChannels: {
        type: Object,
        default: () => ({}),
    },
});

const activeTab = ref('branding');

// Form 1: Branding & Kontak
const brandingForm = useForm({
    app_name: props.settings.app_name || 'YTEvent Football Competition',
    app_tagline: props.settings.app_tagline || 'Multi-Event Competition Portal',
    header_logo_style: props.settings.header_logo_style || 'logo_only', // 'logo_only' atau 'logo_and_text'
    footer_copyright: props.settings.footer_copyright || 'YTEvent Multi-Event Football Tournament System',
    contact_email: props.settings.contact_email || 'sekretariat@ytevent.id',
    contact_whatsapp: props.settings.contact_whatsapp || '',
    logo: null,
    favicon: null,
});

const submitBranding = () => {
    brandingForm.post(route('admin.settings.branding'), {
        preserveScroll: true,
    });
};

// Form 2: Metode Pembayaran (Manual Bank vs Midtrans Gateway)
const initialBankAccounts = Array.isArray(props.settings.manual_bank_accounts) && props.settings.manual_bank_accounts.length > 0
    ? JSON.parse(JSON.stringify(props.settings.manual_bank_accounts))
    : [
        {
            bank_name: 'BCA',
            account_number: '8720192837',
            account_holder: 'PT Event Sepak Bola Indonesia',
            notes: 'KCU Sudirman Jakarta',
        },
    ];

const paymentForm = useForm({
    payment_method_mode: props.settings.payment_method_mode || 'midtrans', // 'midtrans' atau 'manual'
    manual_bank_accounts: initialBankAccounts,
    midtrans_server_key: props.settings.midtrans_server_key || '',
    midtrans_client_key: props.settings.midtrans_client_key || '',
    midtrans_is_production: Boolean(props.settings.midtrans_is_production),
    fee_borne_by: props.settings.fee_borne_by || 'team',
    midtrans_va_fee: props.settings.midtrans_va_fee ?? 4000,
    midtrans_qris_fee_percent: props.settings.midtrans_qris_fee_percent ?? 0.7,
    midtrans_ewallet_fee_percent: props.settings.midtrans_ewallet_fee_percent ?? 1.5,
    midtrans_cc_fee_percent: props.settings.midtrans_cc_fee_percent ?? 2.9,
    midtrans_cc_fee_fixed: props.settings.midtrans_cc_fee_fixed ?? 2000,
    midtrans_channels_mode: props.settings.midtrans_channels_mode || 'all',
    midtrans_active_channels: Array.isArray(props.settings.midtrans_active_channels) ? props.settings.midtrans_active_channels : [],
});

const selectAllChannels = () => {
    paymentForm.midtrans_active_channels = Object.keys(props.availableChannels || {});
};

const deselectAllChannels = () => {
    paymentForm.midtrans_active_channels = [];
};

const addBankAccount = () => {
    paymentForm.manual_bank_accounts.push({
        bank_name: 'BCA',
        account_number: '',
        account_holder: '',
        notes: '',
    });
};

const removeBankAccount = (index) => {
    if (paymentForm.manual_bank_accounts.length > 1) {
        paymentForm.manual_bank_accounts.splice(index, 1);
    }
};

const submitPaymentSettings = () => {
    paymentForm.post(route('admin.settings.payment-gateway'), {
        preserveScroll: true,
    });
};

const simForm = useForm({
    enabled: props.settings.simulation_mode_enabled !== false && props.settings.simulation_mode_enabled !== '0',
});

const toggleSimulationSetting = () => {
    simForm.post(route('simulation.toggle'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout page-title="Pengaturan Global Sistem" subtitle="Kelola identitas merek, logo, favicon, pemilihan metode pembayaran aktif, dan nomor rekening penerima.">
        <Head title="Pengaturan Sistem" />

        <div class="space-y-6">
            <!-- Navigation Tabs -->
            <div class="frosted-card p-1.5 flex flex-wrap items-center gap-1 w-full sm:w-fit">
                <button
                    type="button"
                    class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all text-center cursor-pointer"
                    :class="activeTab === 'branding' ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'branding'"
                >
                    Identitas &amp; Logo Web
                </button>
                <button
                    type="button"
                    class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all text-center cursor-pointer"
                    :class="activeTab === 'payment' ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'payment'"
                >
                    Metode Pembayaran &amp; Rekening
                </button>
                <button
                    type="button"
                    class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all text-center cursor-pointer"
                    :class="activeTab === 'simulation' ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'simulation'"
                >
                    Mode Simulasi &amp; Uji Coba
                </button>
            </div>

            <!-- Tab 1: Branding & Identitas -->
            <div v-if="activeTab === 'branding'" class="frosted-card p-6 sm:p-8 w-full space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Identitas Situs &amp; Branding Merek</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Nama aplikasi, tagline, logo resmi, dan favicon yang tampil di header dan footer publik.</p>
                </div>

                <FormErrorBanner :errors="brandingForm.errors" />

                <form @submit.prevent="submitBranding" novalidate class="space-y-5 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nama Aplikasi / Situs</label>
                            <input v-model="brandingForm.app_name" type="text" class="input-field text-xs" />
                            <InputError :message="brandingForm.errors.app_name" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Tagline Singkat</label>
                            <input v-model="brandingForm.app_tagline" type="text" class="input-field text-xs" />
                            <InputError :message="brandingForm.errors.app_tagline" />
                        </div>
                    </div>

                    <!-- Logo & Favicon with FileInput Component -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
                        <div>
                            <label class="block font-bold text-slate-800 mb-2">Logo Utama Situs (PNG / SVG / JPG)</label>
                            <FileInput
                                v-model="brandingForm.logo"
                                accept=".png,.jpg,.jpeg,.svg,.webp"
                                :preview-url="settings.app_logo"
                                hint="Format: PNG, JPG, atau SVG (Rasio persegi / horizontal transparan)"
                                :error="brandingForm.errors.logo"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-slate-800 mb-2">Favicon Tab Browser (ICO / PNG)</label>
                            <FileInput
                                v-model="brandingForm.favicon"
                                accept=".ico,.png,.svg"
                                :preview-url="settings.app_favicon"
                                hint="Format: ICO atau PNG (Rasio 1:1, maks 1 MB)"
                                :error="brandingForm.errors.favicon"
                            />
                        </div>
                    </div>

                    <!-- Gaya Tampilan Logo di Header -->
                    <div class="pt-2">
                        <label class="block font-bold text-slate-800 mb-1.5">Gaya Tampilan Logo di Header Navbar</label>
                        <p class="text-[11px] text-slate-500 mb-2.5">Pilih apakah logo ditampilkan memanjang kesamping secara mandiri tanpa teks nama, atau berpasangan dengan teks nama situs.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
                                :class="brandingForm.header_logo_style === 'logo_only' ? 'border-orange-500 bg-orange-50/50 ring-1 ring-orange-500/20' : 'border-slate-200 bg-white hover:border-slate-300'"
                            >
                                <input
                                    v-model="brandingForm.header_logo_style"
                                    type="radio"
                                    value="logo_only"
                                    class="mt-0.5 text-orange-600 focus:ring-orange-500"
                                />
                                <div>
                                    <span class="block text-xs font-bold text-slate-900">Hanya Logo Saja (Kesamping / Landscape)</span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                        Menyembunyikan teks nama web di navbar. Logo utama akan tampil membentang horizontal proporsional (tinggi responsif 32–40px, lebar bebas). Sangat ideal untuk logo resmi yang sudah memuat tulisan nama/merek klub.
                                    </span>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
                                :class="brandingForm.header_logo_style === 'logo_and_text' ? 'border-orange-500 bg-orange-50/50 ring-1 ring-orange-500/20' : 'border-slate-200 bg-white hover:border-slate-300'"
                            >
                                <input
                                    v-model="brandingForm.header_logo_style"
                                    type="radio"
                                    value="logo_and_text"
                                    class="mt-0.5 text-orange-600 focus:ring-orange-500"
                                />
                                <div>
                                    <span class="block text-xs font-bold text-slate-900">Logo Ikon + Teks Nama Situs</span>
                                    <span class="block text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                        Menampilkan ikon logo persegi (36x36px) berdampingan dengan tulisan nama situs dan tagline singkat di sampingnya.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Email Kontak Sekretariat</label>
                            <input v-model="brandingForm.contact_email" type="email" class="input-field text-xs" />
                            <InputError :message="brandingForm.errors.contact_email" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">WhatsApp Resmi Panitia</label>
                            <input v-model="brandingForm.contact_whatsapp" type="text" class="input-field text-xs tabular-data" />
                            <InputError :message="brandingForm.errors.contact_whatsapp" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Teks Hak Cipta (Footer Copyright)</label>
                        <input v-model="brandingForm.footer_copyright" type="text" class="input-field text-xs" />
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex justify-end">
                        <button type="submit" class="btn-pitch" :disabled="brandingForm.processing">
                            {{ brandingForm.processing ? 'Menyimpan...' : 'Simpan Identitas Branding' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Payment Method Mode (Manual vs Midtrans) & Multiple Accounts -->
            <div v-else-if="activeTab === 'payment'" class="frosted-card p-6 sm:p-8 w-full space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Konfigurasi Metode Pembayaran Pendaftaran</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pilih salah satu metode pembayaran aktif: <strong>Transfer Manual Bank</strong> (dengan multi-rekening penerima) atau <strong>Payment Gateway Midtrans</strong>.
                    </p>
                </div>

                <FormErrorBanner :errors="paymentForm.errors" />

                <form @submit.prevent="submitPaymentSettings" novalidate class="space-y-6 text-xs">
                    <!-- Exclusive Selection: Manual vs Midtrans -->
                    <div>
                        <label class="block font-bold text-slate-900 text-sm mb-2.5">
                            Pilih Metode Pembayaran Aktif (Harus Salah Satu Dipilih)
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <!-- Option 1: Payment Gateway Midtrans -->
                            <label
                                class="p-4 rounded-2xl border-2 transition-all cursor-pointer flex items-start gap-3.5"
                                :class="paymentForm.payment_method_mode === 'midtrans'
                                    ? 'border-orange-500 bg-orange-50/70 shadow-sm ring-2 ring-orange-500/10'
                                    : 'border-slate-200 bg-white hover:bg-slate-50/80'"
                            >
                                <input
                                    v-model="paymentForm.payment_method_mode"
                                    type="radio"
                                    value="midtrans"
                                    class="text-orange-600 focus:ring-orange-500 mt-1"
                                />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-slate-900">Payment Gateway Midtrans</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-orange-100 text-orange-800">Otomatis</span>
                                    </div>
                                    <p class="text-slate-500 text-[11px] mt-1 leading-relaxed">
                                        Pembayaran instan online via Virtual Account BCA/Mandiri/BNI/BRI, QRIS GoPay, dan E-Wallet. Tim langsung aktif otomatis tanpa pemeriksaan manual staf.
                                    </p>
                                </div>
                            </label>

                            <!-- Option 2: Transfer Bank Manual -->
                            <label
                                class="p-4 rounded-2xl border-2 transition-all cursor-pointer flex items-start gap-3.5"
                                :class="paymentForm.payment_method_mode === 'manual'
                                    ? 'border-orange-500 bg-orange-50/70 shadow-sm ring-2 ring-orange-500/10'
                                    : 'border-slate-200 bg-white hover:bg-slate-50/80'"
                            >
                                <input
                                    v-model="paymentForm.payment_method_mode"
                                    type="radio"
                                    value="manual"
                                    class="text-orange-600 focus:ring-orange-500 mt-1"
                                />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-slate-900">Transfer Bank Manual</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-100 text-amber-800">Verifikasi Panitia</span>
                                    </div>
                                    <p class="text-slate-500 text-[11px] mt-1 leading-relaxed">
                                        Manajer tim mentransfer ke salah satu rekening panitia dan mengunggah struk transfer. Tim panitia finance memeriksa dan menyetujui mutasi pembayaran.
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- IF MANUAL: MULTIPLE BANK ACCOUNTS REPEATER -->
                    <div v-if="paymentForm.payment_method_mode === 'manual'" class="p-5 rounded-2xl border border-slate-200 bg-white/80 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-200">
                            <div>
                                <h3 class="font-bold text-slate-900 text-sm">Daftar Rekening Bank Penerima Pembayaran</h3>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    Anda dapat menambahkan lebih dari satu nomor rekening. Manajer tim dapat memilih rekening yang sesuai saat melakukan transfer.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="btn-sm-pitch shrink-0"
                                @click="addBankAccount"
                            >
                                + Tambah Rekening Penerima
                            </button>
                        </div>

                        <!-- Bank Account List Cards -->
                        <div class="space-y-3">
                            <div
                                v-for="(acc, index) in paymentForm.manual_bank_accounts"
                                :key="index"
                                class="p-4 rounded-xl border border-slate-200 bg-slate-50/80 space-y-3 relative group"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                                        <span class="w-5 h-5 rounded-full bg-orange-100 text-orange-800 font-bold flex items-center justify-center text-[10px]">
                                            {{ index + 1 }}
                                        </span>
                                        <span>Rekening {{ index + 1 }}</span>
                                    </span>
                                    <button
                                        v-if="paymentForm.manual_bank_accounts.length > 1"
                                        type="button"
                                        class="text-xs font-semibold text-rose-600 hover:text-rose-800 hover:underline inline-flex items-center gap-1"
                                        @click="removeBankAccount(index)"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <span>Hapus</span>
                                    </button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Nama Bank</label>
                                        <input
                                            v-model="acc.bank_name"
                                            type="text"
                                            placeholder="Contoh: BCA / Bank Mandiri"
                                            class="input-field text-xs font-semibold"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Nomor Rekening</label>
                                        <input
                                            v-model="acc.account_number"
                                            type="text"
                                            placeholder="Contoh: 1234567890"
                                            class="input-field text-xs font-mono font-bold tabular-data"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label class="block font-semibold text-slate-700 mb-1">Atas Nama Pemilik</label>
                                        <input
                                            v-model="acc.account_holder"
                                            type="text"
                                            placeholder="Contoh: Panitia Pelaksana"
                                            class="input-field text-xs font-medium"
                                            required
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-semibold text-slate-600 mb-1">Catatan / Cabang (Opsional)</label>
                                    <input
                                        v-model="acc.notes"
                                        type="text"
                                        placeholder="Contoh: Kantor Cabang Utama Sudirman"
                                        class="input-field text-xs"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- IF MIDTRANS: CREDENTIALS & MDR FEE RULES -->
                    <div v-else class="space-y-5">
                        <!-- Midtrans API Keys -->
                        <div class="p-5 rounded-2xl border border-slate-200 bg-white/80 space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                                <div>
                                    <span class="font-bold text-slate-900 text-sm">Kredensial API Midtrans</span>
                                    <p class="text-slate-500 text-[11px] mt-0.5">Dapatkan dari Midtrans Dashboard &gt; Settings &gt; Access Keys.</p>
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer font-semibold text-xs text-slate-800">
                                    <input v-model="paymentForm.midtrans_is_production" type="checkbox" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500" />
                                    <span>Mode Production (Live)</span>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Server Key</label>
                                    <input v-model="paymentForm.midtrans_server_key" type="password" placeholder="SB-Mid-server-..." class="input-field text-xs font-mono" />
                                </div>
                                <div>
                                    <label class="block font-semibold text-slate-700 mb-1">Client Key</label>
                                    <input v-model="paymentForm.midtrans_client_key" type="text" placeholder="SB-Mid-client-..." class="input-field text-xs font-mono" />
                                </div>
                            </div>
                        </div>

                        <!-- Midtrans MDR Fee Rules per Payment Channel -->
                        <div class="p-5 rounded-2xl border border-slate-200 bg-white/80 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                                <div>
                                    <span class="font-bold text-slate-900 text-sm">Aturan Biaya Transaksi &amp; MDR Midtrans</span>
                                    <p class="text-slate-500 text-[11px] mt-0.5">Besaran biaya layanan dihitung otomatis berdasarkan kanal pembayaran yang dipilih manajer tim.</p>
                                </div>
                            </div>

                            <!-- Who bears the fee -->
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1.5">Penanggung Biaya Layanan / MDR</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer" :class="paymentForm.fee_borne_by === 'team' ? 'border-orange-500 bg-orange-50/70 font-bold text-orange-950' : 'border-slate-200 bg-white text-slate-700'">
                                        <input v-model="paymentForm.fee_borne_by" type="radio" value="team" class="text-orange-600 focus:ring-orange-500" />
                                        <span>Dibebankan ke Pendaftar / Tim (Biaya ditambahkan ke total tagihan)</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer" :class="paymentForm.fee_borne_by === 'organizer' ? 'border-orange-500 bg-orange-50/70 font-bold text-orange-950' : 'border-slate-200 bg-white text-slate-700'">
                                        <input v-model="paymentForm.fee_borne_by" type="radio" value="organizer" class="text-orange-600 focus:ring-orange-500" />
                                        <span>Ditanggung Penyelenggara (Dipotong dari penerimaan pendaftaran)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Channel Fee Rates -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                                    <label class="block font-bold text-slate-800 text-xs">Virtual Account (VA)</label>
                                    <p class="text-[10px] text-slate-500">BCA, Mandiri, BNI, BRI, Permata</p>
                                    <div class="flex items-center gap-1.5 mt-2">
                                        <span class="text-slate-500 font-bold text-xs">Rp</span>
                                        <input v-model="paymentForm.midtrans_va_fee" type="number" min="0" step="500" class="input-field text-xs font-mono tabular-data font-bold py-1.5" />
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                                    <label class="block font-bold text-slate-800 text-xs">QRIS (Gopay / All e-Wallet)</label>
                                    <p class="text-[10px] text-slate-500">MDR standar Bank Indonesia</p>
                                    <div class="flex items-center gap-1.5 mt-2">
                                        <input v-model="paymentForm.midtrans_qris_fee_percent" type="number" min="0" max="10" step="0.1" class="input-field text-xs font-mono tabular-data font-bold py-1.5" />
                                        <span class="text-slate-500 font-bold text-xs">%</span>
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                                    <label class="block font-bold text-slate-800 text-xs">E-Wallet Langsung</label>
                                    <p class="text-[10px] text-slate-500">GoPay / ShopeePay direct</p>
                                    <div class="flex items-center gap-1.5 mt-2">
                                        <input v-model="paymentForm.midtrans_ewallet_fee_percent" type="number" min="0" max="10" step="0.1" class="input-field text-xs font-mono tabular-data font-bold py-1.5" />
                                        <span class="text-slate-500 font-bold text-xs">%</span>
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                                    <label class="block font-bold text-slate-800 text-xs">Kartu Kredit / Debit</label>
                                    <p class="text-[10px] text-slate-500">Visa / Mastercard / JCB</p>
                                    <div class="flex items-center gap-1 mt-2">
                                        <input v-model="paymentForm.midtrans_cc_fee_percent" type="number" min="0" max="10" step="0.1" class="input-field text-xs font-mono tabular-data font-bold py-1.5 w-16" />
                                        <span class="text-slate-500 font-bold text-xs">% + Rp</span>
                                        <input v-model="paymentForm.midtrans_cc_fee_fixed" type="number" min="0" step="500" class="input-field text-xs font-mono tabular-data font-bold py-1.5 flex-1" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Midtrans Allowed Channels Filter -->
                        <div class="p-5 rounded-2xl border border-slate-200 bg-white/80 space-y-4">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                                <div>
                                    <span class="font-bold text-slate-900 text-sm">Kanal Pembayaran di Midtrans Snap</span>
                                    <p class="text-slate-500 text-[11px] mt-0.5">Tentukan apakah ingin menampilkan semua kanal yang aktif di akun Midtrans atau membatasi ke beberapa kanal tertentu saja.</p>
                                </div>
                            </div>

                            <!-- Mode Selector -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer" :class="paymentForm.midtrans_channels_mode === 'all' ? 'border-orange-500 bg-orange-50/70 font-bold text-orange-950' : 'border-slate-200 bg-white text-slate-700'">
                                    <input v-model="paymentForm.midtrans_channels_mode" type="radio" value="all" class="text-orange-600 focus:ring-orange-500 mt-0.5" />
                                    <div>
                                        <span class="text-xs font-bold block">Tampilkan Semua Kanal (All-in-One Rekomendasi)</span>
                                        <span class="text-[11px] text-slate-500 font-normal block mt-0.5">Seluruh metode pembayaran yang aktif di akun Midtrans Anda otomatis dapat dipilih oleh manajer di jendela Snap.</span>
                                    </div>
                                </label>
                                <label class="flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer" :class="paymentForm.midtrans_channels_mode === 'custom' ? 'border-orange-500 bg-orange-50/70 font-bold text-orange-950' : 'border-slate-200 bg-white text-slate-700'">
                                    <input v-model="paymentForm.midtrans_channels_mode" type="radio" value="custom" class="text-orange-600 focus:ring-orange-500 mt-0.5" />
                                    <div>
                                        <span class="text-xs font-bold block">Pilih Beberapa Kanal Tertentu</span>
                                        <span class="text-[11px] text-slate-500 font-normal block mt-0.5">Batasi pilihan di jendela Snap hanya pada bank atau metode tertentu yang Anda centang di bawah.</span>
                                    </div>
                                </label>
                            </div>

                            <!-- Custom Channels Checklist -->
                            <div v-if="paymentForm.midtrans_channels_mode === 'custom'" class="pt-2 space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-800">Daftar Kanal yang Diizinkan:</span>
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="text-[11px] text-orange-600 hover:underline font-semibold" @click="selectAllChannels">
                                            Pilih Semua
                                        </button>
                                        <span class="text-slate-300">|</span>
                                        <button type="button" class="text-[11px] text-slate-500 hover:underline" @click="deselectAllChannels">
                                            Kosongkan
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                                    <label
                                        v-for="(label, key) in availableChannels"
                                        :key="key"
                                        class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer text-xs transition"
                                        :class="paymentForm.midtrans_active_channels.includes(key) ? 'border-orange-400 bg-orange-50/60 font-semibold text-orange-950' : 'border-slate-200 bg-slate-50/50 text-slate-700 hover:bg-slate-50'"
                                    >
                                        <input
                                            v-model="paymentForm.midtrans_active_channels"
                                            type="checkbox"
                                            :value="key"
                                            class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                        />
                                        <span>{{ label }}</span>
                                    </label>
                                </div>
                                <p v-if="paymentForm.midtrans_active_channels.length === 0" class="text-[11px] text-amber-600 italic">
                                    * Jika tidak ada yang dicentang, Midtrans akan otomatis menampilkan seluruh metode yang aktif di akun Anda.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex justify-end">
                        <button type="submit" class="btn-pitch" :disabled="paymentForm.processing">
                            {{ paymentForm.processing ? 'Menyimpan Pengaturan...' : 'Simpan Pengaturan Pembayaran' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab 3: Mode Simulasi & Uji Coba (Take-Out Ready) -->
            <div v-else class="frosted-card p-6 sm:p-8 w-full space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Pengaturan Modul Simulasi &amp; Isi Data Paksa</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Hidupkan atau matikan sementara tombol melayang (Floating Simulation Button) di halaman Publik, Portal Manajer, dan Portal Admin.
                    </p>
                </div>

                <form @submit.prevent="toggleSimulationSetting" novalidate class="space-y-5 text-xs">
                    <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50 flex items-center justify-between gap-4">
                        <div class="space-y-1">
                            <span class="block text-sm font-bold text-slate-900">Status Tombol Floating Simulasi Cepat</span>
                            <span class="block text-xs text-slate-500 leading-relaxed">
                                Jika diaktifkan, tombol <strong>Simulasi Cepat (Isi Data Paksa)</strong> akan muncul di sudut kanan bawah semua halaman untuk mempercepat simulasi pendaftaran tim, pembayaran, pengisian pemain &amp; dokumen, hingga verifikasi screening admin.
                            </span>
                        </div>
                        <label class="inline-flex items-center gap-2 cursor-pointer shrink-0 font-bold text-slate-900">
                            <input
                                v-model="simForm.enabled"
                                type="checkbox"
                                class="w-4 h-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            />
                            <span>{{ simForm.enabled ? 'AKTIF' : 'NONAKTIF' }}</span>
                        </label>
                    </div>

                    <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 space-y-1">
                        <p class="font-bold">Panduan Take-Out Saat Full Production:</p>
                        <p class="text-[11px] leading-relaxed">
                            Modul ini dirancang terisolasi penuh di <code class="font-mono bg-white/80 px-1 py-0.5 rounded">app/Modules/Simulation</code> dan <code class="font-mono bg-white/80 px-1 py-0.5 rounded">routes/simulation.php</code>. Untuk mematikan secara permanen di server production, cukup setel <code class="font-mono bg-white/80 px-1 py-0.5 rounded">SIMULATION_MODULE_ENABLED=false</code> pada file <code class="font-mono bg-white/80 px-1 py-0.5 rounded">.env</code>.
                        </p>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex justify-end">
                        <button type="submit" class="btn-pitch cursor-pointer" :disabled="simForm.processing">
                            {{ simForm.processing ? 'Menyimpan...' : 'Simpan Status Modul Simulasi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
