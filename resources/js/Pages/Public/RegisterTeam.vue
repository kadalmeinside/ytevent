<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import FileInput from '@/Components/FileInput.vue';
import TncAgreementModal from '@/Components/TncAgreementModal.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
import { useFormatters } from '@/Composables/useFormatters';
import { compressImage } from '@/Utils/imageCompressor';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    prefillUser: {
        type: Object,
        default: null,
    },
    existingTeam: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const { formatRupiah, formatDate, formatBirthDateRange } = useFormatters();
const {
    applyValidation,
    isEmpty,
    isValidEmail,
    isValidPassword,
    isValidIndonesianPhone,
    checkFile,
} = useFormValidator();

const form = useForm({
    team_name: props.existingTeam?.name || '',
    logo: null,
    origin_association: props.existingTeam?.origin_association || '',
    secretariat_address: props.existingTeam?.secretariat_address || '',
    manager_name: props.existingTeam?.manager_name || props.prefillUser?.name || '',
    email: props.prefillUser?.email || '',
    password: '',
    password_confirmation: '',
    manager_whatsapp: props.existingTeam?.manager_whatsapp || props.prefillUser?.phone || '',
    head_coach_name: props.existingTeam?.head_coach_name || '',
    head_coach_license: props.existingTeam?.head_coach_license || '',
    terms_accepted: false,
    tnc_version_id: null,
});

const showTncModal = ref(false);
const activeTnc = computed(() => props.event?.tnc || null);
const hasAttemptedSubmit = ref(false);
const touchedFields = reactive({});

const emailAccountExists = ref(false);
const checkingEmail = ref(false);
const lastCheckedEmail = ref('');
const inlineLoginForm = useForm({
    email: '',
    password: '',
    redirect_to: 'register',
});

const checkEmailAccount = async () => {
    if (isAuthenticated.value) return;
    const normalized = String(form.email || '').trim().toLowerCase();
    if (!normalized || !isValidEmail(normalized)) {
        emailAccountExists.value = false;
        lastCheckedEmail.value = '';
        return;
    }
    if (lastCheckedEmail.value === normalized) {
        return;
    }
    checkingEmail.value = true;
    try {
        const { data } = await window.axios.post(route('events.check-email'), {
            email: normalized,
        });
        lastCheckedEmail.value = normalized;
        emailAccountExists.value = Boolean(data?.exists);
        if (emailAccountExists.value) {
            form.clearErrors('password', 'password_confirmation');
        }
    } catch {
        emailAccountExists.value = false;
    } finally {
        checkingEmail.value = false;
    }
};

const handleEmailInput = () => {
    const normalized = String(form.email || '').trim().toLowerCase();
    if (normalized !== lastCheckedEmail.value) {
        emailAccountExists.value = false;
        inlineLoginForm.clearErrors();
    }
    onFieldInput('email');
};

const handleEmailBlur = () => {
    onFieldBlur('email');
    checkEmailAccount();
};

const submitInlineLogin = () => {
    inlineLoginForm.email = String(form.email || '').trim().toLowerCase();
    inlineLoginForm.redirect_to = 'register';
    inlineLoginForm.post(route('events.inline-login', { event: props.event.slug }), {
        preserveScroll: true,
        preserveState: false,
    });
};

const handleTermsChange = (event) => {
    const checked = Boolean(event?.target?.checked);
    if (checked && activeTnc.value) {
        form.terms_accepted = false;
        if (event?.target) event.target.checked = false;
        form.tnc_version_id = null;
        showTncModal.value = true;
        return;
    }
    if (!checked) {
        form.terms_accepted = false;
        form.tnc_version_id = null;
        return;
    }
    form.terms_accepted = true;
    form.clearErrors('terms_accepted', 'tnc_version_id');
};

const acceptTnc = (tnc) => {
    form.terms_accepted = true;
    form.tnc_version_id = tnc?.id || activeTnc.value?.id || null;
    form.clearErrors('terms_accepted', 'tnc_version_id');
};

const validateTeamField = (field) => {
    switch (field) {
        case 'team_name':
            if (isEmpty(form.team_name)) return 'Nama tim wajib diisi.';
            if (form.team_name.trim().length < 3) return 'Nama tim minimal terdiri dari 3 karakter.';
            return '';
        case 'origin_association':
            return isEmpty(form.origin_association) ? 'Kota atau Asosiasi Asal (Askot/Askab) wajib diisi.' : '';
        case 'manager_name':
            return isEmpty(form.manager_name) ? 'Nama lengkap manajer atau penanggung jawab wajib diisi.' : '';
        case 'manager_whatsapp':
            if (isEmpty(form.manager_whatsapp)) return 'Nomor telepon/WhatsApp aktif wajib diisi.';
            if (!isValidIndonesianPhone(form.manager_whatsapp)) return 'Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890).';
            return '';
        case 'head_coach_name':
            return isEmpty(form.head_coach_name) ? 'Nama pelatih kepala wajib diisi.' : '';
        case 'head_coach_license':
            if (props.event?.show_coach_license !== false && props.event?.require_coach_license) {
                if (isEmpty(form.head_coach_license)) return 'Nomor atau jenis lisensi pelatih kepala wajib diisi sesuai regulasi kompetisi ini.';
                if (form.head_coach_license.trim().length < 2) return 'Nomor atau jenis lisensi pelatih kepala minimal 2 karakter.';
            }
            return '';
        case 'email':
            if (!isAuthenticated.value) {
                if (isEmpty(form.email)) return 'Alamat email aktif wajib diisi.';
                if (!isValidEmail(form.email)) return 'Format alamat email yang dimasukkan tidak valid.';
            }
            return '';
        case 'password':
            if (!isAuthenticated.value) {
                if (isEmpty(form.password)) return 'Kata sandi wajib diisi.';
                if (!isValidPassword(form.password)) return 'Kata sandi minimal 8 karakter dan wajib mengandung huruf serta angka.';
            }
            return '';
        case 'password_confirmation':
            if (!isAuthenticated.value) {
                if (isEmpty(form.password_confirmation)) return 'Konfirmasi kata sandi wajib diisi.';
                if (form.password_confirmation !== form.password) return 'Konfirmasi kata sandi tidak cocok dengan kata sandi yang dimasukkan.';
            }
            return '';
        default:
            return '';
    }
};

const onFieldInput = (field) => {
    const hadError = Boolean(form.errors[field]);
    form.clearErrors(field);
    const err = validateTeamField(field);
    if (!err && !isEmpty(form[field])) {
        touchedFields[field] = true;
    } else if (err && (hasAttemptedSubmit.value || touchedFields[field] || hadError)) {
        form.setError(field, err);
    }
    if (field === 'password' && !isEmpty(form.password_confirmation)) {
        onFieldInput('password_confirmation');
    }
};

const onFieldBlur = (field) => {
    touchedFields[field] = true;
    const err = validateTeamField(field);
    if (err) {
        form.setError(field, err);
    } else {
        form.clearErrors(field);
    }
};

const fieldClass = (field, extra = '') => {
    if (form.errors[field]) return `${extra} input-field-error`.trim();
    const val = form[field];
    if ((touchedFields[field] || hasAttemptedSubmit.value) && !isEmpty(val) && !validateTeamField(field)) {
        return `${extra} input-field-valid`.trim();
    }
    return extra;
};

const paymentConfig = computed(() => props.event?.payment_config || {});
const baseFee = computed(() => Number(props.event?.registration_fee || 0));
const isGatewayPayment = computed(() => ['midtrans', 'xendit', 'gateway'].includes(paymentConfig.value.mode || 'manual'));
const estimatedGatewayFee = computed(() => {
    if (baseFee.value <= 0 || !isGatewayPayment.value) return 0;
    if ((paymentConfig.value.fee_borne_by || 'team') !== 'team') return 0;
    return Number(paymentConfig.value.va_fee ?? 4000);
});

const askotSuggestions = [
    'Askot PSSI Jakarta Pusat',
    'Askot PSSI Jakarta Selatan',
    'Askot PSSI Jakarta Timur',
    'Askot PSSI Bandung',
    'Askab PSSI Bogor',
    'Askot PSSI Bekasi',
    'Askab PSSI Tangerang',
    'Askot PSSI Surabaya',
    'Askot PSSI Semarang',
];

const handleLogoChange = async (file) => {
    if (!file) {
        form.logo = null;
        return;
    }
    const { file: compressed } = await compressImage(file, { maxDimension: 1200, quality: 0.85 });
    form.logo = compressed;
    form.clearErrors('logo');
};

const submit = () => {
    hasAttemptedSubmit.value = true;
    const ok = applyValidation(form, () => {
        const errs = {};

        if (isEmpty(form.team_name)) {
            errs.team_name = 'Nama tim wajib diisi.';
        } else if (form.team_name.trim().length < 3) {
            errs.team_name = 'Nama tim minimal terdiri dari 3 karakter.';
        }

        if (form.logo || !props.existingTeam?.logo_url) {
            const logoCheck = checkFile(form.logo, ['image/png', 'image/jpeg'], 2 * 1024 * 1024);
            if (!logoCheck.valid) {
                if (logoCheck.reason === 'missing') errs.logo = 'Logo resmi tim wajib diunggah.';
                else if (logoCheck.reason === 'mime') errs.logo = 'Format berkas logo harus berupa PNG atau JPG.';
                else if (logoCheck.reason === 'size') errs.logo = 'Ukuran berkas logo tidak boleh melebihi 2 MB.';
            }
        }

        if (isEmpty(form.origin_association)) {
            errs.origin_association = 'Kota atau Asosiasi Asal (Askot/Askab) wajib diisi.';
        }

        if (isEmpty(form.manager_name)) {
            errs.manager_name = 'Nama lengkap manajer atau penanggung jawab wajib diisi.';
        }

        if (!isAuthenticated.value) {
            if (isEmpty(form.email)) {
                errs.email = 'Alamat email aktif wajib diisi.';
            } else if (!isValidEmail(form.email)) {
                errs.email = 'Format alamat email yang dimasukkan tidak valid.';
            } else if (emailAccountExists.value) {
                errs.email = 'Email ini sudah terdaftar. Silakan masukkan kata sandi akun Anda di bagian atas untuk masuk dan memuat data tim.';
            }

            if (!emailAccountExists.value) {
                if (isEmpty(form.password)) {
                    errs.password = 'Kata sandi wajib diisi.';
                } else if (!isValidPassword(form.password)) {
                    errs.password = 'Kata sandi minimal 8 karakter dan wajib mengandung huruf serta angka.';
                }

                if (isEmpty(form.password_confirmation)) {
                    errs.password_confirmation = 'Konfirmasi kata sandi wajib diisi.';
                } else if (form.password_confirmation !== form.password) {
                    errs.password_confirmation = 'Konfirmasi kata sandi tidak cocok dengan kata sandi yang dimasukkan.';
                }
            }
        }

        if (isEmpty(form.manager_whatsapp)) {
            errs.manager_whatsapp = 'Nomor telepon/WhatsApp aktif wajib diisi.';
        } else if (!isValidIndonesianPhone(form.manager_whatsapp)) {
            errs.manager_whatsapp = 'Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890).';
        }

        if (isEmpty(form.head_coach_name)) {
            errs.head_coach_name = 'Nama pelatih kepala wajib diisi.';
        }

        if (props.event?.show_coach_license !== false && props.event?.require_coach_license) {
            if (isEmpty(form.head_coach_license)) {
                errs.head_coach_license = 'Nomor atau jenis lisensi pelatih kepala wajib diisi sesuai regulasi kompetisi ini.';
            } else if (form.head_coach_license.trim().length < 2) {
                errs.head_coach_license = 'Nomor atau jenis lisensi pelatih kepala minimal 2 karakter.';
            }
        }

        if (!form.terms_accepted) {
            errs.terms_accepted = 'Anda wajib menyetujui syarat, ketentuan, dan regulasi kompetisi untuk melanjutkan pendaftaran.';
        }

        return errs;
    });

    if (!ok) return;

    form.post(route('events.register.store', { event: props.event.slug }), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <PublicLayout>
        <Head :title="`Daftar Tim — ${event.name} (${event.age_category})`">
            <meta
                head-key="description"
                name="description"
                :content="`Formulir pendaftaran resmi tim sepak bola untuk kompetisi ${event.name} (${event.age_category}) di ${event.venue_name}, ${event.city}. Sisa slot tersedia: ${event.available_slots} dari ${event.max_teams} tim.`"
            />
            <meta head-key="og:type" property="og:type" content="article" />
            <meta head-key="og:title" property="og:title" :content="`Daftar Tim — ${event.name} (${event.age_category})`" />
            <meta
                head-key="og:description"
                property="og:description"
                :content="`Formulir pendaftaran resmi tim sepak bola untuk kompetisi ${event.name} (${event.age_category}) di ${event.venue_name}, ${event.city}. Sisa slot tersedia: ${event.available_slots} dari ${event.max_teams} tim.`"
            />
            <meta v-if="event.banner_url" head-key="og:image" property="og:image" :content="event.banner_url" />
            <meta head-key="twitter:title" name="twitter:title" :content="`Daftar Tim — ${event.name} (${event.age_category})`" />
            <meta
                head-key="twitter:description"
                name="twitter:description"
                :content="`Formulir pendaftaran resmi tim sepak bola untuk kompetisi ${event.name} (${event.age_category}) di ${event.venue_name}, ${event.city}. Sisa slot tersedia: ${event.available_slots} dari ${event.max_teams} tim.`"
            />
            <meta v-if="event.banner_url" head-key="twitter:image" name="twitter:image" :content="event.banner_url" />
        </Head>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-4">
                <Link :href="route('events.show', { event: event.slug })" class="text-xs font-semibold text-slate-600 hover:text-orange-600 transition-colors">
                    &larr; Kembali ke Halaman Kompetisi
                </Link>
            </div>

            <!-- Event & Quota Context Header -->
            <div class="frosted-card p-5 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-900 text-white">
                            {{ event.age_category }}
                        </span>
                        <StatusBadge :status="event.status" />
                    </div>
                    <h1 class="text-lg font-bold text-slate-900">Formulir Pendaftaran Tim: {{ event.name }}</h1>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Batas kelahiran pemain: <strong class="tabular-data">{{ formatBirthDateRange(event.min_birth_date, event.max_birth_date) }}</strong>
                    </p>
                </div>

                <div class="text-left sm:text-right shrink-0 p-3.5 rounded-xl bg-orange-50/50 border border-orange-200/80">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Sisa Kuota Saat Ini</p>
                    <p class="text-base font-black tabular-data" :class="event.available_slots === 0 ? 'text-rose-700' : 'text-orange-800'">
                        {{ event.available_slots }} dari {{ event.max_teams }} Slot
                    </p>
                    <p class="text-xs font-semibold text-slate-800 tabular-data mt-0.5">
                        Biaya: {{ formatRupiah(event.registration_fee) }}
                    </p>
                </div>
            </div>

            <!-- Quota Full State -->
            <div
                v-if="!event.can_register && !form.errors.event_quota"
                class="frosted-card p-8 text-center border-rose-200 bg-rose-50/60"
            >
                <p class="text-base font-bold text-rose-900">Kuota Pendaftaran Telah Penuh / Ditutup</p>
                <p class="text-xs text-rose-700 mt-1 max-w-lg mx-auto">
                    Seluruh {{ event.max_teams }} slot tim untuk kompetisi ini sedang terisi. Apabila ada pendaftar yang melewati batas waktu pembayaran ({{ event.payment_deadline_hours }} jam), slot akan terbuka kembali secara otomatis.
                </p>
                <Link :href="route('events.show', { event: event.slug })" class="btn-secondary mt-4 text-xs">
                    Pantau Status Kompetisi
                </Link>
            </div>

            <!-- Registration Form -->
            <div v-else class="frosted-card p-6 sm:p-8">
                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-6">
                    <!-- Kredensial Akun Manajer / Deteksi Akun Lama -->
                    <div v-if="!isAuthenticated">
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200">
                            1. Akun Portal Manajer Tim
                        </h2>
                        <p class="text-xs text-slate-500 mt-2">
                            Masukkan alamat email Anda terlebih dahulu. Jika Anda sudah pernah mendaftar sebelumnya, sistem akan menampilkan form kata sandi untuk memuat identitas tim Anda secara otomatis.
                        </p>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div data-field="email" class="sm:col-span-2">
                                <label for="reg_email" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Alamat Email Aktif (Digunakan untuk Login &amp; Invoice)
                                </label>
                                <input
                                    id="reg_email"
                                    v-model="form.email"
                                    type="email"
                                    inputmode="email"
                                    placeholder="manajer@klubsepakbola.id"
                                    class="input-field"
                                    :class="fieldClass('email')"
                                    @input="handleEmailInput"
                                    @blur="handleEmailBlur"
                                />
                                <p v-if="checkingEmail" class="text-[11px] text-slate-500 mt-1">
                                    Memeriksa status akun email...
                                </p>
                                <InputError :message="form.errors.email" />
                            </div>

                            <!-- Inline Login Prompt when Email Already Exists -->
                            <div
                                v-if="emailAccountExists"
                                class="sm:col-span-2 p-4 rounded-xl bg-orange-50/90 border border-orange-200 space-y-3"
                            >
                                <div>
                                    <p class="text-xs font-bold text-orange-950">
                                        Email Sudah Terdaftar di Sistem
                                    </p>
                                    <p class="text-[11px] text-orange-900 mt-0.5 leading-relaxed">
                                        Anda sudah memiliki akun dengan email <strong>{{ form.email }}</strong>. Masukkan kata sandi akun Anda untuk masuk dan memuat identitas tim Anda secara otomatis.
                                    </p>
                                </div>

                                <div data-field="login_password">
                                    <label for="inline_login_password" class="block text-xs font-semibold text-slate-700 mb-1">
                                        Kata Sandi Akun Anda
                                    </label>
                                    <input
                                        id="inline_login_password"
                                        v-model="inlineLoginForm.password"
                                        type="password"
                                        placeholder="Masukkan kata sandi akun Anda"
                                        class="input-field bg-white"
                                        @keydown.enter.prevent="submitInlineLogin"
                                    />
                                    <InputError :message="inlineLoginForm.errors.login_password || inlineLoginForm.errors.password || inlineLoginForm.errors.email" />
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                                    <button
                                        type="button"
                                        class="btn-pitch h-9 sm:h-10 px-4 text-xs font-bold rounded-lg"
                                        :disabled="inlineLoginForm.processing"
                                        @click="submitInlineLogin"
                                    >
                                        {{ inlineLoginForm.processing ? 'Memverifikasi & Memuat Tim...' : 'Masuk & Muat Data Tim' }}
                                    </button>
                                    <Link
                                        :href="route('password.request')"
                                        class="text-xs font-semibold text-orange-700 hover:underline"
                                    >
                                        Lupa kata sandi?
                                    </Link>
                                </div>
                            </div>

                            <template v-else>
                                <div data-field="password">
                                    <label for="reg_password" class="block text-xs font-semibold text-slate-700 mb-1">
                                        Kata Sandi Akun Baru (Min. 8 karakter huruf &amp; angka)
                                    </label>
                                    <input
                                        id="reg_password"
                                        v-model="form.password"
                                        type="password"
                                        placeholder="Kombinasi huruf dan angka"
                                        class="input-field"
                                        :class="fieldClass('password')"
                                        @input="onFieldInput('password')"
                                        @blur="onFieldBlur('password')"
                                    />
                                    <InputError :message="form.errors.password" />
                                </div>

                                <div data-field="password_confirmation">
                                    <label for="reg_password_confirmation" class="block text-xs font-semibold text-slate-700 mb-1">
                                        Konfirmasi Kata Sandi
                                    </label>
                                    <input
                                        id="reg_password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        placeholder="Ketik ulang kata sandi"
                                        class="input-field"
                                        :class="fieldClass('password_confirmation')"
                                        @input="onFieldInput('password_confirmation')"
                                        @blur="onFieldBlur('password_confirmation')"
                                    />
                                    <InputError :message="form.errors.password_confirmation" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <div v-else class="p-3.5 rounded-xl bg-orange-50/70 border border-orange-200 text-xs text-orange-950">
                        Anda sedang login sebagai <strong>{{ page.props.auth.user.email }}</strong>. Pendaftaran tim ini akan langsung dikaitkan ke akun Manajer Tim Anda.
                    </div>

                    <!-- Identitas Tim yang Sudah Terdaftar pada Akun -->
                    <div
                        v-if="existingTeam"
                        class="p-3.5 rounded-xl bg-orange-50/80 border border-orange-200 text-xs text-orange-950 flex items-center gap-3"
                    >
                        <img
                            v-if="existingTeam.logo_url"
                            :src="existingTeam.logo_url"
                            :alt="existingTeam.name"
                            class="w-10 h-10 rounded-lg object-cover border border-orange-200 bg-white shrink-0"
                        />
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900">
                                Mendaftarkan Tim Akun Anda: {{ existingTeam.name }}
                            </p>
                            <p class="text-[11px] text-orange-900 mt-0.5 leading-relaxed">
                                1 akun menggunakan identitas tim yang sama. Nama tim dikunci otomatis, sedangkan data kontak dan logo tetap dapat Anda tinjau sebelum dikirim.
                            </p>
                        </div>
                    </div>

                    <!-- Bagian Identitas Klub / SSB -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200">
                            {{ isAuthenticated ? '1' : '2' }}. Identitas Tim / Klub / SSB
                        </h2>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div data-field="team_name">
                                <label for="team_name" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nama Resmi Tim
                                </label>
                                <input
                                    id="team_name"
                                    v-model="form.team_name"
                                    type="text"
                                    :readonly="Boolean(existingTeam)"
                                    placeholder="Contoh: SSB Garuda Muda"
                                    class="input-field"
                                    :class="fieldClass('team_name', existingTeam ? 'bg-slate-100 text-slate-700 cursor-not-allowed' : '')"
                                    @input="onFieldInput('team_name')"
                                    @blur="onFieldBlur('team_name')"
                                />
                                <p v-if="existingTeam" class="text-[11px] text-slate-500 mt-1">
                                    Nama tim dikunci mengikuti identitas tim akun Anda.
                                </p>
                                <InputError :message="form.errors.team_name" />
                            </div>

                            <div data-field="origin_association">
                                <label for="origin_association" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Kota / Asosiasi Asal (Askot / Askab PSSI)
                                </label>
                                <input
                                    id="origin_association"
                                    v-model="form.origin_association"
                                    type="text"
                                    list="askot-options"
                                    placeholder="Contoh: Askot PSSI Jakarta Selatan"
                                    class="input-field"
                                    :class="fieldClass('origin_association')"
                                    @input="onFieldInput('origin_association')"
                                    @blur="onFieldBlur('origin_association')"
                                />
                                <datalist id="askot-options">
                                    <option v-for="opt in askotSuggestions" :key="opt" :value="opt" />
                                </datalist>
                                <InputError :message="form.errors.origin_association" />
                            </div>

                            <div data-field="logo" class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Logo Resmi Tim
                                    <span v-if="!existingTeam?.logo_url" class="text-rose-500">*</span>
                                    <span v-else class="text-slate-500 font-normal">(Opsional, menggunakan logo tim saat ini)</span>
                                </label>
                                <div
                                    v-if="existingTeam?.logo_url && !form.logo"
                                    class="mb-2.5 p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-3 text-xs"
                                >
                                    <img
                                        :src="existingTeam.logo_url"
                                        :alt="existingTeam.name"
                                        class="w-9 h-9 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                                    />
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-800">Logo tim saat ini akan digunakan otomatis</p>
                                        <p class="text-[11px] text-slate-500">Unggah berkas baru di bawah hanya jika ingin memperbarui logo untuk event ini.</p>
                                    </div>
                                </div>
                                <FileInput
                                    :model-value="form.logo"
                                    accept=".png,.jpg,.jpeg"
                                    :max-size-mb="2"
                                    :label="existingTeam?.logo_url ? 'Ganti Logo Tim (Opsional)' : 'Pilih atau Tarik Logo Tim'"
                                    :hint="existingTeam?.logo_url ? 'Biarkan kosong untuk tetap memakai logo tim saat ini, atau unggah PNG / JPG baru (maks. 2 MB)' : 'Format PNG / JPG maks. 2 MB (disarankan rasio 1:1)'"
                                    :error="form.errors.logo"
                                    @update:model-value="handleLogoChange"
                                />
                            </div>

                            <div class="sm:col-span-2">
                                <label for="secretariat_address" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Alamat Sekretariat / Markas Latihan (Opsional)
                                </label>
                                <input
                                    id="secretariat_address"
                                    v-model="form.secretariat_address"
                                    type="text"
                                    placeholder="Jl. Stadion Utama No. 10..."
                                    class="input-field"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Bagian Data Penanggung Jawab & Pelatih -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200">
                            {{ isAuthenticated ? '2' : '3' }}. Data Manajer Tim &amp; Pelatih Kepala
                        </h2>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div data-field="manager_name">
                                <label for="manager_name" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nama Lengkap Manajer Tim
                                </label>
                                <input
                                    id="manager_name"
                                    v-model="form.manager_name"
                                    type="text"
                                    placeholder="Nama penanggung jawab tim"
                                    class="input-field"
                                    :class="fieldClass('manager_name')"
                                    @input="onFieldInput('manager_name')"
                                    @blur="onFieldBlur('manager_name')"
                                />
                                <InputError :message="form.errors.manager_name" />
                            </div>

                            <div data-field="manager_whatsapp">
                                <label for="manager_whatsapp" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nomor Telepon / WhatsApp Aktif
                                </label>
                                <input
                                    id="manager_whatsapp"
                                    v-model="form.manager_whatsapp"
                                    type="text"
                                    placeholder="081234567890"
                                    class="input-field tabular-data"
                                    :class="fieldClass('manager_whatsapp')"
                                    @input="onFieldInput('manager_whatsapp')"
                                    @blur="onFieldBlur('manager_whatsapp')"
                                />
                                <InputError :message="form.errors.manager_whatsapp" />
                            </div>

                            <div :class="event.show_coach_license === false ? 'sm:col-span-2' : ''" data-field="head_coach_name">
                                <label for="head_coach_name" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nama Pelatih Kepala (Head Coach)
                                </label>
                                <input
                                    id="head_coach_name"
                                    v-model="form.head_coach_name"
                                    type="text"
                                    placeholder="Nama lengkap pelatih kepala"
                                    class="input-field"
                                    :class="fieldClass('head_coach_name')"
                                    @input="onFieldInput('head_coach_name')"
                                    @blur="onFieldBlur('head_coach_name')"
                                />
                                <InputError :message="form.errors.head_coach_name" />
                            </div>

                            <div v-if="event.show_coach_license !== false" data-field="head_coach_license">
                                <label for="head_coach_license" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Lisensi Kepelatihan
                                    <span v-if="event.require_coach_license" class="text-rose-500 font-bold">* (Wajib)</span>
                                    <span v-else class="text-slate-400 font-normal">(Opsional di tahap awal)</span>
                                </label>
                                <input
                                    id="head_coach_license"
                                    v-model="form.head_coach_license"
                                    type="text"
                                    placeholder="Contoh: Lisensi D PSSI / AFC C"
                                    class="input-field"
                                    :class="fieldClass('head_coach_license')"
                                    @input="onFieldInput('head_coach_license')"
                                    @blur="onFieldBlur('head_coach_license')"
                                />
                                <InputError :message="form.errors.head_coach_license" />
                            </div>
                        </div>
                    </div>

                    <!-- Bagian Persetujuan Syarat & Ketentuan -->
                    <div data-field="terms_accepted" class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-bold text-slate-800">
                                Syarat &amp; Ketentuan
                            </span>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="activeTnc?.version"
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-orange-100 text-orange-800 border border-orange-200"
                                >
                                    v{{ activeTnc.version }}
                                </span>
                                <button
                                    v-if="activeTnc"
                                    type="button"
                                    class="text-xs font-semibold text-orange-700 hover:underline"
                                    @click="showTncModal = true"
                                >
                                    {{ form.terms_accepted ? 'Baca ulang dokumen' : 'Baca isi ketentuan' }}
                                </button>
                            </div>
                        </div>

                        <label class="flex items-start gap-2.5 cursor-pointer text-xs text-slate-700 leading-relaxed">
                            <input
                                v-model="form.terms_accepted"
                                type="checkbox"
                                class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                @change="handleTermsChange"
                            />
                            <span>
                                <template v-if="activeTnc?.summary">
                                    {{ activeTnc.summary }}
                                </template>
                                <template v-else>
                                    Saya menyatakan bahwa seluruh data yang diisikan adalah benar, menyetujui regulasi teknis kompetisi <strong>{{ event.name }}</strong>, serta memahami bahwa slot kuota hanya ditahan selama <strong>{{ event.payment_deadline_hours }} jam</strong> hingga pembayaran diselesaikan.
                                </template>
                            </span>
                        </label>
                        <InputError :message="form.errors.terms_accepted || form.errors.tnc_version_id" />

                        <TncAgreementModal
                            :show="showTncModal"
                            :tnc="activeTnc"
                            :already-accepted="Boolean(form.terms_accepted)"
                            accent="orange"
                            @close="showTncModal = false"
                            @accept="acceptTnc"
                        />
                    </div>

                    <!-- Ringkasan Tagihan Tim -->
                    <div class="p-4 rounded-xl bg-slate-50/90 border border-slate-200 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-800 uppercase text-[11px]">Ringkasan Tagihan Pendaftaran</span>
                            <span class="text-[11px] font-semibold text-slate-500">
                                {{ baseFee > 0 ? (isGatewayPayment ? 'Pembayaran Online Otomatis' : 'Transfer Bank Manual') : 'Bebas Biaya' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between pt-1 border-t border-slate-200/70">
                            <span class="text-slate-600">Biaya Pendaftaran Tim</span>
                            <span class="font-mono tabular-data font-bold text-slate-900">
                                {{ baseFee > 0 ? formatRupiah(baseFee) : 'Gratis (Rp 0)' }}
                            </span>
                        </div>
                        <div v-if="baseFee > 0 && isGatewayPayment" class="flex items-center justify-between">
                            <span class="text-slate-600">Estimasi Biaya Layanan Gateway</span>
                            <span v-if="estimatedGatewayFee > 0" class="font-mono tabular-data font-semibold text-orange-700">
                                + s.d. {{ formatRupiah(estimatedGatewayFee) }}
                            </span>
                            <span v-else class="font-semibold text-emerald-700">Gratis (Ditanggung Penyelenggara)</span>
                        </div>
                        <div class="flex items-center justify-between pt-1.5 border-t border-slate-200 text-sm font-bold">
                            <span class="text-slate-900">Estimasi Total Tagihan</span>
                            <span class="font-mono tabular-data text-base text-orange-700">
                                {{ baseFee > 0 ? formatRupiah(baseFee + estimatedGatewayFee) : 'Rp 0' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                        <p class="text-xs text-slate-500">
                            Slot kuota dikunci secara atomik saat formulir dikirim untuk mencegah bentrok pendaftaran.
                        </p>
                        <button type="submit" class="btn-pitch w-full sm:w-auto px-6" :disabled="form.processing">
                            {{ form.processing ? 'Mengunci Slot & Memproses...' : 'Kirim Pendaftaran & Amankan Slot' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>

