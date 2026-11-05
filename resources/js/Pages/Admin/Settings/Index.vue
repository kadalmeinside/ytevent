<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const activeTab = ref('branding');

// Form 1: Branding & Kontak
const brandingForm = useForm({
    app_name: props.settings.app_name || 'YTEvent Football Competition',
    app_tagline: props.settings.app_tagline || 'Multi-Event Competition Portal',
    footer_copyright: props.settings.footer_copyright || 'YTEvent Multi-Event Football Tournament System',
    contact_email: props.settings.contact_email || 'sekretariat@ytevent.id',
    contact_whatsapp: props.settings.contact_whatsapp || '',
    logo: null,
    favicon: null,
});

const logoPreview = ref(props.settings.app_logo || null);
const faviconPreview = ref(props.settings.app_favicon || null);

const handleLogo = (e) => {
    const file = e.target.files?.[0] || null;
    brandingForm.logo = file;
    brandingForm.clearErrors('logo');
    if (file) logoPreview.value = URL.createObjectURL(file);
};

const handleFavicon = (e) => {
    const file = e.target.files?.[0] || null;
    brandingForm.favicon = file;
    brandingForm.clearErrors('favicon');
    if (file) faviconPreview.value = URL.createObjectURL(file);
};

const submitBranding = () => {
    brandingForm.post(route('admin.settings.branding'), {
        preserveScroll: true,
    });
};

// Form 2: Gateway & Fee MDR
const gatewayForm = useForm({
    payment_gateway_default: props.settings.payment_gateway_default || 'midtrans',
    midtrans_server_key: props.settings.midtrans_server_key || '',
    midtrans_client_key: props.settings.midtrans_client_key || '',
    midtrans_is_production: Boolean(props.settings.midtrans_is_production),
    xendit_secret_key: props.settings.xendit_secret_key || '',
    xendit_public_key: props.settings.xendit_public_key || '',
    xendit_is_production: Boolean(props.settings.xendit_is_production),
    fee_borne_by: props.settings.fee_borne_by || 'team',
    fee_scheme: props.settings.fee_scheme || 'fixed',
    fee_fixed_amount: props.settings.fee_fixed_amount ?? 4500,
    fee_percentage: props.settings.fee_percentage ?? 1.5,
});

const submitGateway = () => {
    gatewayForm.post(route('admin.settings.payment-gateway'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout page-title="Pengaturan Global Sistem" subtitle="Kelola identitas merek, logo, favicon, kredensial payment gateway, dan kebijakan biaya MDR.">
        <Head title="Pengaturan Sistem" />

        <div class="space-y-6">
            <!-- Navigation Tabs -->
            <div class="frosted-card p-1.5 flex items-center gap-1 max-w-md">
                <button
                    type="button"
                    class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all text-center"
                    :class="activeTab === 'branding' ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'branding'"
                >
                    Identitas &amp; Logo Web
                </button>
                <button
                    type="button"
                    class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition-all text-center"
                    :class="activeTab === 'payment' ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = 'payment'"
                >
                    Gateway &amp; Aturan Fee MDR
                </button>
            </div>

            <!-- Tab 1: Branding & Identitas -->
            <div v-if="activeTab === 'branding'" class="frosted-card p-6 sm:p-8 max-w-4xl space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Identitas Merek, Logo &amp; Favicon</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Pengaturan ini langsung diterapkan ke seluruh tampilan navbar, title bar browser, dan footer aplikasi.</p>
                </div>

                <FormErrorBanner :errors="brandingForm.errors" />

                <form @submit.prevent="submitBranding" novalidate class="space-y-6 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nama Website / Aplikasi</label>
                            <input v-model="brandingForm.app_name" type="text" class="input-field text-xs font-semibold" />
                            <InputError :message="brandingForm.errors.app_name" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Slogan / Tagline Portal</label>
                            <input v-model="brandingForm.app_tagline" type="text" class="input-field text-xs" />
                            <InputError :message="brandingForm.errors.app_tagline" />
                        </div>
                    </div>

                    <!-- Logo & Favicon Uploader -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t border-slate-200">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Logo Resmi Aplikasi (PNG/SVG/JPG, Maks 2MB)</label>
                            <div class="flex items-center gap-4 mt-2">
                                <div class="w-16 h-16 rounded-2xl border border-slate-200 bg-white flex items-center justify-center p-2 shadow-sm overflow-hidden shrink-0">
                                    <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="max-h-full max-w-full object-contain" />
                                    <div v-else class="text-[10px] font-bold text-orange-600 bg-orange-100 px-2 py-1 rounded">YT</div>
                                </div>
                                <div class="flex-1">
                                    <input type="file" accept=".png,.jpg,.jpeg,.svg" class="input-field py-1 text-xs" @change="handleLogo" />
                                    <p class="text-[10px] text-slate-500 mt-1">Disarankan beresolusi tajam dengan background transparan.</p>
                                </div>
                            </div>
                            <InputError :message="brandingForm.errors.logo" />
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Favicon Browser (ICO/PNG/SVG, Maks 1MB)</label>
                            <div class="flex items-center gap-4 mt-2">
                                <div class="w-12 h-12 rounded-xl border border-slate-200 bg-white flex items-center justify-center p-1 shadow-sm overflow-hidden shrink-0">
                                    <img v-if="faviconPreview" :src="faviconPreview" alt="Favicon" class="max-h-full max-w-full object-contain" />
                                    <span v-else class="text-xl">⚽</span>
                                </div>
                                <div class="flex-1">
                                    <input type="file" accept=".ico,.png,.svg" class="input-field py-1 text-xs" @change="handleFavicon" />
                                    <p class="text-[10px] text-slate-500 mt-1">Muncul di samping judul tab browser.</p>
                                </div>
                            </div>
                            <InputError :message="brandingForm.errors.favicon" />
                        </div>
                    </div>

                    <!-- Kontak & Footer -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-200">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Email Resmi Sekretariat</label>
                            <input v-model="brandingForm.contact_email" type="email" class="input-field text-xs" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">WhatsApp Bantuan Teknis</label>
                            <input v-model="brandingForm.contact_whatsapp" type="text" class="input-field text-xs tabular-data" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-semibold text-slate-700 mb-1">Teks Hak Cipta (Footer Copyright)</label>
                            <input v-model="brandingForm.footer_copyright" type="text" class="input-field text-xs" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex justify-end">
                        <button type="submit" class="btn-pitch text-xs px-6" :disabled="brandingForm.processing">
                            {{ brandingForm.processing ? 'Menyimpan...' : 'Simpan Identitas Branding' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Payment Gateway & Fee Rules -->
            <div v-else class="frosted-card p-6 sm:p-8 max-w-4xl space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Konfigurasi Gateway &amp; Aturan Biaya MDR / Admin</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Atur penyedia pembayaran otomatis, kunci API, serta siapa yang menanggung biaya transaksi pembayaran.</p>
                </div>

                <FormErrorBanner :errors="gatewayForm.errors" />

                <form @submit.prevent="submitGateway" novalidate class="space-y-6 text-xs">
                    <!-- Default Gateway Selection -->
                    <div class="p-4 rounded-2xl bg-orange-50/50 border border-orange-200/80 space-y-2">
                        <label class="block font-bold text-slate-900 text-sm">Penyedia Gateway Pembayaran Utama</label>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cursor-pointer font-semibold text-slate-800">
                                <input v-model="gatewayForm.payment_gateway_default" type="radio" value="midtrans" class="text-orange-600 focus:ring-orange-500" />
                                <span>Midtrans (Snap &amp; Core API)</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer font-semibold text-slate-800">
                                <input v-model="gatewayForm.payment_gateway_default" type="radio" value="xendit" class="text-orange-600 focus:ring-orange-500" />
                                <span>Xendit (Invoice API)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Midtrans Config -->
                    <div class="p-4 rounded-2xl border border-slate-200 bg-white/70 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <span class="font-bold text-slate-900 text-sm">Kredensial Midtrans</span>
                            <label class="flex items-center gap-2 cursor-pointer font-semibold">
                                <input v-model="gatewayForm.midtrans_is_production" type="checkbox" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500" />
                                <span>Mode Production (Live)</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Server Key</label>
                                <input v-model="gatewayForm.midtrans_server_key" type="password" placeholder="SB-Mid-server-..." class="input-field text-xs font-mono" />
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Client Key</label>
                                <input v-model="gatewayForm.midtrans_client_key" type="text" placeholder="SB-Mid-client-..." class="input-field text-xs font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- Xendit Config -->
                    <div class="p-4 rounded-2xl border border-slate-200 bg-white/70 space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <span class="font-bold text-slate-900 text-sm">Kredensial Xendit</span>
                            <label class="flex items-center gap-2 cursor-pointer font-semibold">
                                <input v-model="gatewayForm.xendit_is_production" type="checkbox" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500" />
                                <span>Mode Production (Live)</span>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Secret API Key</label>
                                <input v-model="gatewayForm.xendit_secret_key" type="password" placeholder="xnd_development_..." class="input-field text-xs font-mono" />
                            </div>
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Public Key / Webhook Token</label>
                                <input v-model="gatewayForm.xendit_public_key" type="text" placeholder="xnd_public_..." class="input-field text-xs font-mono" />
                            </div>
                        </div>
                    </div>

                    <!-- Fee Admin & MDR Rules -->
                    <div class="p-5 rounded-2xl border border-slate-200 bg-white/70 space-y-4">
                        <div>
                            <span class="font-bold text-slate-900 text-sm">Aturan Kebijakan Biaya Admin &amp; MDR</span>
                            <p class="text-slate-500 text-[11px] mt-0.5">Tentukan bagaimana biaya administrasi sistem / fee bank diperhitungkan pada tagihan.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Penanggung Biaya MDR / Admin</label>
                                <select v-model="gatewayForm.fee_borne_by" class="input-field text-xs">
                                    <option value="team">Dibebankan ke Manajer Tim (Ditambahkan ke Tagihan)</option>
                                    <option value="organizer">Ditanggung Panitia (Dipotong dari Biaya Registrasi)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-semibold text-slate-700 mb-1">Skema Perhitungan Biaya</label>
                                <select v-model="gatewayForm.fee_scheme" class="input-field text-xs">
                                    <option value="fixed">Nominal Tetap Saja (Flat Fee)</option>
                                    <option value="percentage">Persentase Saja (%)</option>
                                    <option value="both">Kombinasi (Nominal Tetap + Persentase)</option>
                                </select>
                            </div>

                            <div v-if="gatewayForm.fee_scheme === 'fixed' || gatewayForm.fee_scheme === 'both'">
                                <label class="block font-semibold text-slate-700 mb-1">Biaya Tetap per Transaksi (Rp)</label>
                                <input v-model="gatewayForm.fee_fixed_amount" type="number" min="0" step="500" class="input-field text-xs tabular-data font-bold" />
                            </div>

                            <div v-if="gatewayForm.fee_scheme === 'percentage' || gatewayForm.fee_scheme === 'both'">
                                <label class="block font-semibold text-slate-700 mb-1">Biaya Persentase MDR (%)</label>
                                <input v-model="gatewayForm.fee_percentage" type="number" min="0" max="100" step="0.1" class="input-field text-xs tabular-data font-bold" />
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex justify-end">
                        <button type="submit" class="btn-pitch text-xs px-6" :disabled="gatewayForm.processing">
                            {{ gatewayForm.processing ? 'Menyimpan...' : 'Simpan Kredensial & Aturan Biaya' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
