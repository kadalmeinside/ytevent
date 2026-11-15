<script setup>
import { ref, reactive, computed } from 'vue';
import { useForm, usePage, Link, Head } from '@inertiajs/vue3';
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
    existingChildren: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const { formatRupiah, formatDate } = useFormatters();
const {
    applyValidation,
    isEmpty,
    isValidEmail,
    isValidPassword,
    isValidIndonesianPhone,
    checkFile,
} = useFormValidator();

const toPresetBool = (value, fallback = false) => {
    if (value === undefined || value === null || value === '') return fallback;
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value !== 0;
    const normalized = String(value).trim().toLowerCase();
    if (['1', 'true', 'yes', 'on'].includes(normalized)) return true;
    if (['0', 'false', 'no', 'off'].includes(normalized)) return false;
    return fallback;
};

const presets = computed(() => {
    const raw = props.event.form_presets || {};
    return {
        photo: toPresetBool(raw.photo, false),
        position: toPresetBool(raw.position, true),
        foot_dominance: toPresetBool(raw.foot_dominance ?? raw.foot, false),
        team_origin: toPresetBool(raw.team_origin, false),
        body_measurements: toPresetBool(raw.body_measurements, false),
        experience: toPresetBool(raw.experience, false),
        jersey_size: toPresetBool(raw.jersey_size ?? raw.jersey, false),
    };
});
const customFields = computed(() => props.event.custom_fields_schema || []);
const sessions = computed(() => props.event.sessions || []);
const requiredDocuments = computed(() => (props.event.required_documents || []).map((document) => document.type || document));
const hasExtraDocument = computed(() => requiredDocuments.value.some((document) => !['birth_certificate', 'family_card'].includes(document)));

const form = useForm({
    source_participant_id: null,

    // Guardian
    guardian_name: props.prefillUser?.name || '',
    guardian_email: props.prefillUser?.email || '',
    guardian_phone: props.prefillUser?.phone || '',
    guardian_password: '',
    guardian_password_confirmation: '',

    // Athlete
    full_name: '',
    nik: '',
    birth_date: '',
    gender: 'male',
    event_session_id: '',

    // Presets
    preferred_position: '',
    secondary_position: '',
    dominant_foot: presets.value.foot_dominance ? 'right' : '',
    height_cm: '',
    weight_kg: '',
    current_club_ssb: '',
    playing_experience: '',
    jersey_size: presets.value.jersey_size ? 'M' : '',

    // Safety & Contact
    emergency_contact_name: '',
    emergency_contact_phone: '',
    emergency_contact_relation: '',
    medical_notes: '',
    liability_waiver: false,
    tnc_version_id: null,

    // Files
    photo: null,
    birth_certificate: null,
    family_card: null,
    additional_doc: null,

    // Dynamic Answers
    custom_answers: {},
});

const positionOptions = [
    { value: 'GK', label: 'Penjaga Gawang (Goalkeeper)' },
    { value: 'CB', label: 'Bek Tengah (Center Back)' },
    { value: 'LB', label: 'Bek Kiri (Left Back)' },
    { value: 'RB', label: 'Bek Kanan (Right Back)' },
    { value: 'DMF', label: 'Gelandang Bertahan (Defensive Midfielder)' },
    { value: 'CMF', label: 'Gelandang Tengah (Central Midfielder)' },
    { value: 'AMF', label: 'Gelandang Serang (Attacking Midfielder)' },
    { value: 'LW', label: 'Penyerang Sayap Kiri (Left Winger)' },
    { value: 'RW', label: 'Penyerang Sayap Kanan (Right Winger)' },
    { value: 'CF', label: 'Penyerang Tengah / Striker (Center Forward)' },
];

const handlePhotoChange = async (file) => {
    if (!file) {
        form.photo = null;
        return;
    }
    form.photo = file;
    form.clearErrors('photo');
    const { file: compressed } = await compressImage(file, { maxDimension: 1200, quality: 0.85 });
    form.photo = compressed;
    form.clearErrors('photo');
};

const handleBirthCertChange = (file) => {
    form.birth_certificate = file;
    form.clearErrors('birth_certificate');
};

const handleFamilyCardChange = (file) => {
    form.family_card = file;
    form.clearErrors('family_card');
};

const handleAdditionalDocChange = (file) => {
    form.additional_doc = file;
    form.clearErrors('additional_doc');
};

const showTncModal = ref(false);
const activeTnc = computed(() => props.event?.tnc || null);
const hasAttemptedSubmit = ref(false);
const touchedFields = reactive({});

// Inline Email Check & Login untuk Akun Orang Tua / Wali yang Sudah Terdaftar
const emailAccountExists = ref(false);
const checkingEmail = ref(false);
const lastCheckedEmail = ref('');
const inlineLoginForm = useForm({
    email: '',
    password: '',
    redirect_to: 'register-audition',
});

const checkGuardianEmailAccount = async () => {
    if (isAuthenticated.value) return;
    const normalized = String(form.guardian_email || '').trim().toLowerCase();
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
            form.clearErrors('guardian_password', 'guardian_password_confirmation');
        }
    } catch {
        emailAccountExists.value = false;
    } finally {
        checkingEmail.value = false;
    }
};

const handleGuardianEmailInput = () => {
    const normalized = String(form.guardian_email || '').trim().toLowerCase();
    if (normalized !== lastCheckedEmail.value) {
        emailAccountExists.value = false;
        inlineLoginForm.clearErrors();
    }
    onFieldInput('guardian_email');
};

const handleGuardianEmailBlur = () => {
    onFieldBlur('guardian_email');
    checkGuardianEmailAccount();
};

const submitInlineLogin = () => {
    inlineLoginForm.email = String(form.guardian_email || '').trim().toLowerCase();
    inlineLoginForm.redirect_to = 'register-audition';
    inlineLoginForm.post(route('events.inline-login', { event: props.event.slug }), {
        preserveScroll: true,
        preserveState: false,
    });
};

// Pemilihan Anak dari Daftar Anak Akun Orang Tua
const selectedChildId = ref(null);
const selectedChild = computed(() =>
    props.existingChildren.find((child) => child.id === selectedChildId.value) || null
);
const hasAnyEligibleExistingChild = computed(() =>
    props.existingChildren.some((child) => !child.already_in_this_event && child.age_eligible)
);
const showAthleteForm = computed(() =>
    !isAuthenticated.value || props.existingChildren.length === 0 || selectedChildId.value !== null
);

const clearChildSelection = () => {
    selectedChildId.value = null;
    form.source_participant_id = null;
    form.clearErrors();
};

const selectExistingChild = (child) => {
    if (!child || child.already_in_this_event || !child.age_eligible) {
        return;
    }
    selectedChildId.value = child.id;
    form.source_participant_id = child.id;
    form.full_name = child.full_name || '';
    form.nik = child.nik || '';
    form.birth_date = child.birth_date || '';
    form.gender = child.gender || 'male';
    form.preferred_position = child.preferred_position || '';
    form.secondary_position = child.secondary_position || '';
    form.dominant_foot = child.dominant_foot || (presets.value.foot_dominance ? 'right' : '');
    form.height_cm = child.height_cm ? String(child.height_cm) : '';
    form.weight_kg = child.weight_kg ? String(child.weight_kg) : '';
    form.current_club_ssb = child.current_club_ssb || '';
    form.playing_experience = child.playing_experience || '';
    form.jersey_size = child.jersey_size || (presets.value.jersey_size ? 'M' : '');
    form.emergency_contact_name = child.emergency_contact_name || '';
    form.emergency_contact_phone = child.emergency_contact_phone || '';
    form.emergency_contact_relation = child.emergency_contact_relation || '';
    form.medical_notes = child.medical_notes || '';
    form.photo = null;
    form.birth_certificate = null;
    form.family_card = null;
    form.clearErrors();
};

const selectNewChild = () => {
    selectedChildId.value = 'new';
    form.source_participant_id = null;
    form.full_name = '';
    form.nik = '';
    form.birth_date = '';
    form.gender = 'male';
    form.preferred_position = '';
    form.secondary_position = '';
    form.dominant_foot = presets.value.foot_dominance ? 'right' : '';
    form.height_cm = '';
    form.weight_kg = '';
    form.current_club_ssb = '';
    form.playing_experience = '';
    form.jersey_size = presets.value.jersey_size ? 'M' : '';
    form.emergency_contact_name = '';
    form.emergency_contact_phone = '';
    form.emergency_contact_relation = '';
    form.medical_notes = '';
    form.photo = null;
    form.birth_certificate = null;
    form.family_card = null;
    form.clearErrors();
};

const handleLiabilityChange = (event) => {
    const checked = Boolean(event?.target?.checked);
    if (checked && activeTnc.value) {
        form.liability_waiver = false;
        if (event?.target) event.target.checked = false;
        form.tnc_version_id = null;
        showTncModal.value = true;
        return;
    }
    if (!checked) {
        form.liability_waiver = false;
        form.tnc_version_id = null;
        return;
    }
    form.liability_waiver = true;
    form.clearErrors('liability_waiver', 'tnc_version_id');
};

const acceptTnc = (tnc) => {
    form.liability_waiver = true;
    form.tnc_version_id = tnc?.id || activeTnc.value?.id || null;
    form.clearErrors('liability_waiver', 'tnc_version_id');
};

const validateSingleField = (field) => {
    switch (field) {
        case 'full_name':
            return isEmpty(form.full_name) ? 'Nama lengkap atlet wajib diisi.' : '';
        case 'nik':
            if (isEmpty(form.nik)) return 'NIK 16 digit wajib diisi.';
            return /^[0-9]{16}$/.test(form.nik) ? '' : 'NIK harus berupa 16 digit angka.';
        case 'birth_date':
            return isEmpty(form.birth_date) ? 'Tanggal lahir atlet wajib diisi.' : '';
        case 'preferred_position':
            return presets.value.position && isEmpty(form.preferred_position) ? 'Posisi utama wajib dipilih.' : '';
        case 'dominant_foot':
            return presets.value.foot_dominance && isEmpty(form.dominant_foot) ? 'Kaki dominan wajib dipilih.' : '';
        case 'height_cm':
            return presets.value.body_measurements && isEmpty(form.height_cm) ? 'Tinggi badan wajib diisi.' : '';
        case 'weight_kg':
            return presets.value.body_measurements && isEmpty(form.weight_kg) ? 'Berat badan wajib diisi.' : '';
        case 'current_club_ssb':
            return presets.value.team_origin && isEmpty(form.current_club_ssb) ? 'Asal SSB / Klub / Sekolah wajib diisi.' : '';
        case 'jersey_size':
            return presets.value.jersey_size && isEmpty(form.jersey_size) ? 'Ukuran jersey / kaos wajib dipilih.' : '';
        case 'emergency_contact_name':
            return isEmpty(form.emergency_contact_name) ? 'Nama kontak darurat kedua wajib diisi.' : '';
        case 'emergency_contact_phone':
            if (isEmpty(form.emergency_contact_phone)) return 'Nomor WhatsApp kontak darurat wajib diisi.';
            return isValidIndonesianPhone(form.emergency_contact_phone)
                ? ''
                : 'Masukkan nomor WhatsApp kontak darurat yang valid.';
        case 'guardian_name':
            return !isAuthenticated.value && isEmpty(form.guardian_name)
                ? 'Nama lengkap orang tua / wali wajib diisi.'
                : '';
        case 'guardian_email':
            if (!isAuthenticated.value) {
                if (isEmpty(form.guardian_email)) return 'Alamat email wajib diisi.';
                if (!isValidEmail(form.guardian_email)) return 'Format alamat email tidak valid.';
            }
            return '';
        case 'guardian_phone':
            if (!isAuthenticated.value) {
                if (isEmpty(form.guardian_phone)) return 'Nomor WhatsApp wajib diisi.';
                if (!isValidIndonesianPhone(form.guardian_phone)) return 'Masukkan nomor WhatsApp Indonesia yang valid.';
            }
            return '';
        case 'guardian_password':
            if (!isAuthenticated.value) {
                if (isEmpty(form.guardian_password)) return 'Kata sandi wajib diisi.';
                if (!isValidPassword(form.guardian_password)) {
                    return 'Kata sandi minimal 8 karakter dan wajib mengandung huruf serta angka.';
                }
            }
            return '';
        case 'guardian_password_confirmation':
            if (!isAuthenticated.value && form.guardian_password_confirmation !== form.guardian_password) {
                return 'Konfirmasi kata sandi tidak cocok.';
            }
            return '';
        default:
            return '';
    }
};

const onFieldInput = (field) => {
    const hadError = Boolean(form.errors[field]);
    form.clearErrors(field);
    const err = validateSingleField(field);
    if (!err && !isEmpty(form[field])) {
        touchedFields[field] = true;
    } else if (err && (hasAttemptedSubmit.value || touchedFields[field] || hadError)) {
        form.setError(field, err);
    }
    if (field === 'guardian_password' && !isEmpty(form.guardian_password_confirmation)) {
        onFieldInput('guardian_password_confirmation');
    }
};

const onFieldBlur = (field) => {
    touchedFields[field] = true;
    const err = validateSingleField(field);
    if (err) {
        form.setError(field, err);
    } else {
        form.clearErrors(field);
    }
};

const onCustomFieldInput = (fieldDef) => {
    const key = fieldDef.key || fieldDef.name;
    const errorKey = `custom_answers.${key}`;
    form.clearErrors(errorKey);
    touchedFields[errorKey] = true;
    if (fieldDef.required && isEmpty(form.custom_answers[key]) && hasAttemptedSubmit.value) {
        form.setError(errorKey, `${fieldDef.label || key} wajib diisi.`);
    }
};

const fieldClass = (field, extra = '') => {
    if (form.errors[field]) return `${extra} input-field-error`.trim();
    const val = form[field];
    if ((touchedFields[field] || hasAttemptedSubmit.value) && !isEmpty(val) && !validateSingleField(field)) {
        return `${extra} input-field-valid`.trim();
    }
    return extra;
};

const customFieldClass = (fieldDef) => {
    const key = fieldDef.key || fieldDef.name;
    const errorKey = `custom_answers.${key}`;
    if (form.errors[errorKey]) return 'input-field-error';
    if ((touchedFields[errorKey] || hasAttemptedSubmit.value) && !isEmpty(form.custom_answers[key])) {
        return 'input-field-valid';
    }
    return '';
};

const paymentConfig = computed(() => props.event?.payment_config || {});
const baseFee = computed(() => Number(props.event?.registration_fee || 0));
const isGatewayPayment = computed(() => ['midtrans', 'xendit', 'gateway'].includes(paymentConfig.value.mode || 'manual'));
const gatewayProviderLabel = computed(() =>
    (paymentConfig.value.provider || 'midtrans') === 'xendit'
        ? 'Xendit Payment Gateway'
        : 'Midtrans Snap (VA, QRIS, e-Wallet, Kartu)'
);
const estimatedGatewayFee = computed(() => {
    if (baseFee.value <= 0 || !isGatewayPayment.value) return 0;
    if ((paymentConfig.value.fee_borne_by || 'team') !== 'team') return 0;
    return Number(paymentConfig.value.va_fee ?? 4000);
});

const submit = () => {
    hasAttemptedSubmit.value = true;
    const ok = applyValidation(form, () => {
        const errs = {};

        // 1. Data Atlet
        if (isEmpty(form.full_name)) {
            errs.full_name = 'Nama lengkap atlet wajib diisi.';
        }

        if (isEmpty(form.nik)) {
            errs.nik = 'NIK 16 digit wajib diisi.';
        } else if (!/^[0-9]{16}$/.test(form.nik)) {
            errs.nik = 'NIK harus berupa 16 digit angka.';
        }

        if (isEmpty(form.birth_date)) {
            errs.birth_date = 'Tanggal lahir atlet wajib diisi.';
        }

        // 2. Presets
        if (presets.value.position && isEmpty(form.preferred_position)) {
            errs.preferred_position = 'Posisi utama wajib dipilih.';
        }

        if (presets.value.foot_dominance && isEmpty(form.dominant_foot)) {
            errs.dominant_foot = 'Kaki dominan wajib dipilih.';
        }

        if (presets.value.body_measurements) {
            if (isEmpty(form.height_cm)) errs.height_cm = 'Tinggi badan wajib diisi.';
            if (isEmpty(form.weight_kg)) errs.weight_kg = 'Berat badan wajib diisi.';
        }

        if (presets.value.team_origin && isEmpty(form.current_club_ssb)) {
            errs.current_club_ssb = 'Asal SSB / Klub / Sekolah wajib diisi.';
        }

        if (presets.value.jersey_size && isEmpty(form.jersey_size)) {
            errs.jersey_size = 'Ukuran jersey / kaos wajib dipilih.';
        }

        // 3. Kontak Darurat Cadangan
        if (isEmpty(form.emergency_contact_name)) {
            errs.emergency_contact_name = 'Nama kontak darurat kedua wajib diisi.';
        }

        if (isEmpty(form.emergency_contact_phone)) {
            errs.emergency_contact_phone = 'Nomor WhatsApp kontak darurat wajib diisi.';
        }

        // 4. Data Wali jika belum login
        if (!isAuthenticated.value) {
            if (isEmpty(form.guardian_email)) {
                errs.guardian_email = 'Alamat email wajib diisi.';
            } else if (!isValidEmail(form.guardian_email)) {
                errs.guardian_email = 'Format alamat email tidak valid.';
            } else if (emailAccountExists.value) {
                errs.guardian_email = 'Email ini sudah terdaftar. Silakan masuk menggunakan kata sandi Anda di Seksi 1 terlebih dahulu.';
            }

            if (!emailAccountExists.value) {
                if (isEmpty(form.guardian_name)) {
                    errs.guardian_name = 'Nama lengkap orang tua / wali wajib diisi.';
                }

                if (isEmpty(form.guardian_phone)) {
                    errs.guardian_phone = 'Nomor WhatsApp wajib diisi.';
                }

                if (isEmpty(form.guardian_password)) {
                    errs.guardian_password = 'Kata sandi wajib diisi.';
                } else if (!isValidPassword(form.guardian_password)) {
                    errs.guardian_password = 'Kata sandi minimal 8 karakter dan wajib mengandung huruf serta angka.';
                }

                if (form.guardian_password_confirmation !== form.guardian_password) {
                    errs.guardian_password_confirmation = 'Konfirmasi kata sandi tidak cocok.';
                }
            }
        }

        // 5. Berkas Unggahan
        if (presets.value.photo && (form.photo || !selectedChild.value?.has_photo)) {
            const photoCheck = checkFile(form.photo, ['image/png', 'image/jpeg', 'image/webp'], 3 * 1024 * 1024);
            if (!photoCheck.valid) {
                if (photoCheck.reason === 'missing') errs.photo = 'Pas foto resmi peserta wajib diunggah.';
                else if (photoCheck.reason === 'mime') errs.photo = 'Pas foto harus berformat JPG, PNG, atau WEBP.';
                else if (photoCheck.reason === 'size') errs.photo = 'Ukuran pas foto maksimal 3 MB.';
            }
        }

        if (requiredDocuments.value.includes('birth_certificate') && (form.birth_certificate || !selectedChild.value?.has_birth_certificate)) {
            const birthCheck = checkFile(form.birth_certificate, ['image/png', 'image/jpeg', 'application/pdf'], 4 * 1024 * 1024);
            if (!birthCheck.valid) {
                if (birthCheck.reason === 'missing') errs.birth_certificate = 'Scan Akta Kelahiran wajib diunggah.';
                else errs.birth_certificate = 'Berkas Akta Kelahiran harus berupa JPG, PNG, atau PDF (maks. 4 MB).';
            }
        }

        if (requiredDocuments.value.includes('family_card') && (form.family_card || !selectedChild.value?.has_family_card)) {
            const kkCheck = checkFile(form.family_card, ['image/png', 'image/jpeg', 'application/pdf'], 4 * 1024 * 1024);
            if (!kkCheck.valid) {
                if (kkCheck.reason === 'missing') errs.family_card = 'Scan Kartu Keluarga (KK) wajib diunggah.';
                else errs.family_card = 'Berkas Kartu Keluarga harus berupa JPG, PNG, atau PDF (maks. 4 MB).';
            }
        }

        const additionalCheck = checkFile(form.additional_doc, ['image/png', 'image/jpeg', 'application/pdf'], 4 * 1024 * 1024);
        if (hasExtraDocument.value && !additionalCheck.valid) {
            errs.additional_doc = additionalCheck.reason === 'missing'
                ? 'Dokumen tambahan yang dipilih panitia wajib diunggah.'
                : 'Dokumen tambahan harus berupa JPG, PNG, atau PDF (maks. 4 MB).';
        }

        for (const field of customFields.value) {
            const key = field.key || field.name;
            if (field.required && isEmpty(form.custom_answers[key])) {
                errs[`custom_answers.${key}`] = `${field.label || key} wajib diisi.`;
            }
        }

        // 6. Liability Waiver
        if (!form.liability_waiver) {
            errs.liability_waiver = 'Anda wajib mencentang persetujuan pernyataan bebas tanggung jawab medis.';
        }

        return errs;
    });

    if (!ok) return;

    form.post(route('events.register-audition.store', { event: props.event.slug }), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <PublicLayout>
        <Head :title="`Pendaftaran Audisi - ${event.name}`" />

        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8">
            <div class="mb-6">
                <Link
                    :href="route('events.show', { event: event.slug })"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-orange-600 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Kembali ke Detail Event</span>
                </Link>
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-100 text-orange-700">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>{{ event.event_type === 'coaching_clinic' ? 'Pendaftaran Coaching Clinic & Workshop' : (event.event_type === 'audition' ? 'Pendaftaran Audisi & Seleksi Bakat' : 'Pendaftaran Event Perorangan') }}</span>
                        </span>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight mt-1">
                            {{ event.name }}
                        </h1>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-500 block">Biaya Registrasi:</span>
                        <span class="text-lg font-black text-orange-600 tabular-data">
                            {{ event.registration_fee > 0 ? formatRupiah(event.registration_fee) : 'Gratis / Free' }}
                        </span>
                    </div>
                </div>
            </div>

            <FormErrorBanner :errors="form.errors" />

            <form @submit.prevent="submit" novalidate class="space-y-6">
                <!-- SEKSI 1: DATA WALI / ORANG TUA -->
                <div class="frosted-card p-6">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 mb-1 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">1</span>
                        <span>Identitas Orang Tua / Wali Pendaftar</span>
                    </h2>
                    <p class="text-xs text-slate-500 mb-4">
                        Data akun portal untuk memantau status verifikasi berkas, penilaian, dan Digital Pass (E-Tiket).
                    </p>

                    <div v-if="isAuthenticated" class="p-3.5 rounded-xl bg-orange-50/80 border border-orange-200/80 text-xs text-slate-700 flex items-center justify-between">
                        <div>
                            <span class="text-slate-500">Mendaftar sebagai:</span>
                            <strong class="ml-1 text-slate-900">{{ prefillUser?.name }} ({{ prefillUser?.email }})</strong>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                            Akun Terverifikasi
                        </span>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div data-field="guardian_email" class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email Aktif *</label>
                            <input
                                v-model="form.guardian_email"
                                type="email"
                                placeholder="nama@email.com"
                                class="input-field"
                                :class="fieldClass('guardian_email')"
                                @input="handleGuardianEmailInput"
                                @blur="handleGuardianEmailBlur"
                            />
                            <p v-if="checkingEmail" class="text-[11px] text-slate-500 mt-1">
                                Memeriksa status akun email...
                            </p>
                            <InputError :message="form.errors.guardian_email" />
                        </div>

                        <!-- Inline Login Prompt when Guardian Email Already Exists -->
                        <div
                            v-if="emailAccountExists"
                            class="sm:col-span-2 p-4 rounded-xl bg-orange-50/90 border border-orange-200 space-y-3"
                        >
                            <div>
                                <p class="text-xs font-bold text-orange-950">
                                    Email Sudah Terdaftar di Sistem
                                </p>
                                <p class="text-[11px] text-orange-900 mt-0.5 leading-relaxed">
                                    Akun dengan email <strong>{{ form.guardian_email }}</strong> sudah terdaftar. Masukkan kata sandi Anda untuk masuk dan menampilkan daftar anak yang terdaftar di akun Anda.
                                </p>
                            </div>

                            <div data-field="login_password">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi Akun Anda *</label>
                                <input
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
                                    {{ inlineLoginForm.processing ? 'Memverifikasi & Memuat Daftar...' : 'Masuk & Muat Daftar Anak' }}
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
                            <div data-field="guardian_name">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap Orang Tua / Wali *</label>
                                <input
                                    v-model="form.guardian_name"
                                    type="text"
                                    placeholder="Contoh: Bambang Pamungkas"
                                    class="input-field"
                                    :class="fieldClass('guardian_name')"
                                    @input="onFieldInput('guardian_name')"
                                    @blur="onFieldBlur('guardian_name')"
                                />
                                <InputError :message="form.errors.guardian_name" />
                            </div>

                            <div data-field="guardian_phone">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor WhatsApp Aktif *</label>
                                <input
                                    v-model="form.guardian_phone"
                                    type="tel"
                                    placeholder="081234567890"
                                    class="input-field"
                                    :class="fieldClass('guardian_phone')"
                                    @input="onFieldInput('guardian_phone')"
                                    @blur="onFieldBlur('guardian_phone')"
                                />
                                <InputError :message="form.errors.guardian_phone" />
                            </div>

                            <div data-field="guardian_password">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi Akun Baru *</label>
                                <input
                                    v-model="form.guardian_password"
                                    type="password"
                                    placeholder="Minimal 8 karakter"
                                    class="input-field"
                                    :class="fieldClass('guardian_password')"
                                    @input="onFieldInput('guardian_password')"
                                    @blur="onFieldBlur('guardian_password')"
                                />
                                <InputError :message="form.errors.guardian_password" />
                            </div>

                            <div data-field="guardian_password_confirmation">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi *</label>
                                <input
                                    v-model="form.guardian_password_confirmation"
                                    type="password"
                                    placeholder="Ulangi kata sandi"
                                    class="input-field"
                                    :class="fieldClass('guardian_password_confirmation')"
                                    @input="onFieldInput('guardian_password_confirmation')"
                                    @blur="onFieldBlur('guardian_password_confirmation')"
                                />
                                <InputError :message="form.errors.guardian_password_confirmation" />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- SEKSI 2: DATA ATLET ANAK -->
                <div class="frosted-card p-6">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 mb-1 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">2</span>
                        <span>Biodata Atlet Peserta Audisi</span>
                    </h2>
                    <p class="text-xs text-slate-500 mb-4">
                        Isi data calon pemain secara akurat sesuai Akta Kelahiran dan Kartu Keluarga resmi.
                    </p>

                    <!-- Daftar Anak yang Sudah Ada pada Akun Orang Tua / Wali -->
                    <div
                        v-if="isAuthenticated && existingChildren.length > 0"
                        class="mb-5 p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold text-slate-900">
                                    Pilih Anak dari Akun Anda
                                </p>
                                <p class="text-[11px] text-slate-600 mt-0.5">
                                    Pilih anak yang sudah pernah didaftarkan sebelumnya untuk mengisi biodata dan berkas secara otomatis, atau daftarkan anak baru lainnya.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <template v-for="child in existingChildren" :key="child.id">
                                <button
                                    v-if="child.already_in_this_event || !child.age_eligible"
                                    type="button"
                                    disabled
                                    class="text-left p-3 rounded-xl border transition-colors opacity-65 cursor-not-allowed flex flex-col justify-between gap-2"
                                    :class="child.already_in_this_event ? 'bg-slate-100 border-slate-200' : 'bg-rose-50/40 border-rose-200'"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <img
                                                v-if="child.photo_url"
                                                :src="child.photo_url"
                                                :alt="child.full_name"
                                                class="w-9 h-9 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                                            />
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-slate-800 truncate">{{ child.full_name }}</p>
                                                <p class="text-[11px] text-slate-500 font-mono truncate">NIK: {{ child.nik }}</p>
                                            </div>
                                        </div>
                                        <span
                                            v-if="child.already_in_this_event"
                                            class="shrink-0 px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-200 text-slate-700"
                                        >
                                            Sudah Terdaftar di Event Ini
                                        </span>
                                        <span
                                            v-else
                                            class="shrink-0 px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-100 text-rose-700"
                                        >
                                            Usia Di Luar Kategori
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-500 flex flex-wrap items-center justify-between gap-1 pt-1 border-t border-slate-200/70">
                                        <span>Lahir: {{ child.birth_date_formatted || '-' }}</span>
                                        <span v-if="child.already_in_this_event" class="font-mono font-semibold text-slate-600">
                                            No. Reg: {{ child.this_event_registration_number }}
                                        </span>
                                        <span v-else class="text-rose-700">
                                            {{ child.age_ineligible_reason }}
                                        </span>
                                    </div>
                                </button>

                                <button
                                    v-else
                                    type="button"
                                    class="text-left p-3 rounded-xl border transition-all flex flex-col justify-between gap-2"
                                    :class="selectedChildId === child.id
                                        ? 'border-orange-500 bg-orange-50/80 ring-1 ring-orange-500/30'
                                        : 'border-slate-200 bg-white hover:border-orange-300 hover:bg-orange-50/30'"
                                    @click="selectExistingChild(child)"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <img
                                                v-if="child.photo_url"
                                                :src="child.photo_url"
                                                :alt="child.full_name"
                                                class="w-9 h-9 rounded-lg object-cover border border-orange-200 bg-white shrink-0"
                                            />
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-slate-900 truncate">{{ child.full_name }}</p>
                                                <p class="text-[11px] text-slate-500 font-mono truncate">NIK: {{ child.nik }}</p>
                                            </div>
                                        </div>
                                        <span
                                            class="shrink-0 px-2 py-0.5 rounded-md text-[10px] font-bold"
                                            :class="selectedChildId === child.id
                                                ? 'bg-orange-600 text-white'
                                                : 'bg-slate-100 text-slate-700'"
                                        >
                                            {{ selectedChildId === child.id ? 'Sedang Dipilih' : 'Pilih Anak Ini' }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-600 flex flex-wrap items-center justify-between gap-1 pt-1 border-t border-slate-200/70">
                                        <span>Lahir: {{ child.birth_date_formatted || '-' }}</span>
                                        <span v-if="child.preferred_position" class="font-semibold text-slate-700">
                                            Posisi: {{ child.preferred_position }}
                                        </span>
                                    </div>
                                </button>
                            </template>

                            <!-- Opsi Daftarkan Anak Baru Lainnya -->
                            <button
                                type="button"
                                class="text-left p-3 rounded-xl border border-dashed transition-all flex items-center justify-between gap-2 min-h-[76px]"
                                :class="selectedChildId === 'new'
                                    ? 'border-orange-500 bg-orange-50/70 text-orange-800 ring-1 ring-orange-500/30'
                                    : 'border-slate-300 bg-white text-slate-700 hover:border-orange-400 hover:text-orange-700'"
                                @click="selectNewChild"
                            >
                                <span class="flex items-center gap-2 min-w-0">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span class="text-xs font-bold">Daftarkan Anak Baru Lainnya</span>
                                </span>
                                <span
                                    v-if="selectedChildId === 'new'"
                                    class="shrink-0 px-2 py-0.5 rounded-md text-[10px] font-bold bg-orange-600 text-white"
                                >
                                    Formulir Aktif
                                </span>
                            </button>
                        </div>

                        <!-- Petunjuk sebelum pengguna memilih profil atau mendaftarkan anak baru -->
                        <div
                            v-if="selectedChildId === null"
                            class="p-3 rounded-xl bg-white border border-slate-200 text-xs text-slate-700 flex flex-wrap items-center justify-between gap-2"
                        >
                            <p v-if="hasAnyEligibleExistingChild" class="text-[11px] leading-relaxed">
                                Klik salah satu profil anak yang memenuhi syarat di atas untuk memuat datanya, atau klik <strong>Daftarkan Anak Baru Lainnya</strong> untuk membuka formulir pendaftaran baru.
                            </p>
                            <p v-else class="text-[11px] leading-relaxed">
                                Anak pada akun Anda sudah terdaftar di event ini atau berada di luar kategori usia. Klik <strong>Daftarkan Anak Baru Lainnya</strong> jika ingin mendaftarkan anak/saudara lainnya.
                            </p>
                            <Link
                                v-if="!hasAnyEligibleExistingChild"
                                :href="route('manager.dashboard')"
                                class="btn-secondary h-8 px-3 text-[11px] font-semibold rounded-lg shrink-0"
                            >
                                Ke Dashboard Akun
                            </Link>
                        </div>
                    </div>

                    <div v-if="showAthleteForm" class="space-y-4">
                        <div
                            v-if="isAuthenticated && existingChildren.length > 0"
                            class="p-3 rounded-xl bg-orange-50/70 border border-orange-200 flex flex-wrap items-center justify-between gap-2 text-xs"
                        >
                            <span class="font-semibold text-orange-950">
                                <template v-if="selectedChild">
                                    Menggunakan Profil Anak: <strong>{{ selectedChild.full_name }}</strong>
                                </template>
                                <template v-else>
                                    Mode Pendaftaran: <strong>Anak Baru Lainnya</strong>
                                </template>
                            </span>
                            <button
                                type="button"
                                class="text-xs font-bold text-orange-700 hover:underline shrink-0"
                                @click="clearChildSelection"
                            >
                                {{ selectedChild ? 'Ubah Pilihan' : 'Tutup Formulir' }}
                            </button>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div data-field="full_name" class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap Atlet *</label>
                            <input
                                v-model="form.full_name"
                                type="text"
                                placeholder="Nama lengkap sesuai Akta Lahir"
                                class="input-field"
                                :class="fieldClass('full_name')"
                                @input="onFieldInput('full_name')"
                                @blur="onFieldBlur('full_name')"
                            />
                            <InputError :message="form.errors.full_name" />
                        </div>

                        <div data-field="nik">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">NIK (16 Digit) *</label>
                            <input
                                v-model="form.nik"
                                type="text"
                                maxlength="16"
                                inputmode="numeric"
                                :readonly="Boolean(selectedChild)"
                                placeholder="3171xxxxxxxxxxxx"
                                class="input-field font-mono"
                                :class="fieldClass('nik', selectedChild ? 'bg-slate-100 text-slate-700 cursor-not-allowed' : '')"
                                @input="onFieldInput('nik')"
                                @blur="onFieldBlur('nik')"
                            />
                            <p v-if="selectedChild" class="text-[11px] text-slate-500 mt-1">
                                NIK dikunci mengikuti data profil anak yang dipilih.
                            </p>
                            <InputError :message="form.errors.nik" />
                        </div>

                        <div data-field="birth_date">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Lahir *</label>
                            <input
                                v-model="form.birth_date"
                                type="date"
                                class="input-field"
                                :class="fieldClass('birth_date')"
                                @input="onFieldInput('birth_date')"
                                @change="onFieldInput('birth_date')"
                                @blur="onFieldBlur('birth_date')"
                            />
                            <InputError :message="form.errors.birth_date" />
                        </div>

                        <div data-field="gender">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kelamin *</label>
                            <select
                                v-model="form.gender"
                                class="input-field"
                                :class="fieldClass('gender')"
                                @change="onFieldInput('gender')"
                            >
                                <option value="male">Laki-laki (Putra)</option>
                                <option value="female">Perempuan (Putri)</option>
                            </select>
                            <InputError :message="form.errors.gender" />
                        </div>

                        <!-- Session / Gelombang Waktu jika ada -->
                        <div v-if="sessions.length > 0" data-field="event_session_id">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Pilih Gelombang / Sesi Waktu Lapangan</label>
                            <select
                                v-model="form.event_session_id"
                                class="input-field"
                                :class="fieldClass('event_session_id')"
                                @change="onFieldInput('event_session_id')"
                            >
                                <option value="">Pilih sesi yang tersedia</option>
                                <option
                                    v-for="s in sessions"
                                    :key="s.id"
                                    :value="s.id"
                                    :disabled="s.is_full"
                                >
                                    {{ s.session_name }} {{ s.is_full ? '(Penuh)' : '' }}
                                </option>
                            </select>
                            <InputError :message="form.errors.event_session_id" />
                        </div>
                    </div>
                    </div>
                </div>

                <template v-if="showAthleteForm">
                <!-- SEKSI 3: DATA TEKNIS & PRESETS -->
                <div class="frosted-card p-6">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 mb-1 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">3</span>
                        <span>Spesifikasi Teknis &amp; Rekam Jejak</span>
                    </h2>
                    <p class="text-xs text-slate-500 mb-4">
                        Informasi posisi bermain, kaki dominan, postur fisik, dan pengalaman bertanding.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Posisi Utama -->
                        <div v-if="presets.position" data-field="preferred_position">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Posisi Utama Bermain *</label>
                            <select
                                v-model="form.preferred_position"
                                class="input-field"
                                :class="fieldClass('preferred_position')"
                                @change="onFieldInput('preferred_position')"
                                @blur="onFieldBlur('preferred_position')"
                            >
                                <option value="">Pilih Posisi Utama</option>
                                <option v-for="opt in positionOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.preferred_position" />
                        </div>

                        <!-- Posisi Alternatif -->
                        <div v-if="presets.position" data-field="secondary_position">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Posisi Alternatif</label>
                            <select
                                v-model="form.secondary_position"
                                class="input-field"
                                :class="fieldClass('secondary_position')"
                                @change="onFieldInput('secondary_position')"
                            >
                                <option value="">Pilih Posisi Alternatif (Opsional)</option>
                                <option v-for="opt in positionOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.secondary_position" />
                        </div>

                        <!-- Kaki Dominan -->
                        <div v-if="presets.foot_dominance" data-field="dominant_foot">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Kaki Dominan *</label>
                            <select
                                v-model="form.dominant_foot"
                                class="input-field"
                                :class="fieldClass('dominant_foot')"
                                @change="onFieldInput('dominant_foot')"
                            >
                                <option value="right">Kaki Kanan</option>
                                <option value="left">Kaki Kiri (Kidal)</option>
                                <option value="both">Kedua Kaki (Ambidextrous)</option>
                            </select>
                            <InputError :message="form.errors.dominant_foot" />
                        </div>

                        <!-- Ukuran Jersey / Kaos -->
                        <div v-if="presets.jersey_size" data-field="jersey_size">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Ukuran Jersey / Kaos *</label>
                            <select
                                v-model="form.jersey_size"
                                class="input-field"
                                :class="fieldClass('jersey_size')"
                                @change="onFieldInput('jersey_size')"
                            >
                                <option value="S">S (Small)</option>
                                <option value="M">M (Medium)</option>
                                <option value="L">L (Large)</option>
                                <option value="XL">XL (Extra Large)</option>
                                <option value="XXL">XXL</option>
                            </select>
                            <InputError :message="form.errors.jersey_size" />
                        </div>

                        <!-- Antropometri TB/BB -->
                        <div v-if="presets.body_measurements" data-field="height_cm">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tinggi Badan (cm) *</label>
                            <input
                                v-model="form.height_cm"
                                type="number"
                                min="80"
                                max="230"
                                placeholder="Contoh: 152"
                                class="input-field"
                                :class="fieldClass('height_cm')"
                                @input="onFieldInput('height_cm')"
                                @blur="onFieldBlur('height_cm')"
                            />
                            <InputError :message="form.errors.height_cm" />
                        </div>

                        <div v-if="presets.body_measurements" data-field="weight_kg">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Berat Badan (kg) *</label>
                            <input
                                v-model="form.weight_kg"
                                type="number"
                                min="20"
                                max="150"
                                placeholder="Contoh: 44"
                                class="input-field"
                                :class="fieldClass('weight_kg')"
                                @input="onFieldInput('weight_kg')"
                                @blur="onFieldBlur('weight_kg')"
                            />
                            <InputError :message="form.errors.weight_kg" />
                        </div>

                        <!-- Asal SSB / Klub -->
                        <div v-if="presets.team_origin" data-field="current_club_ssb" class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Asal SSB / Klub / Sekolah Saat Ini *</label>
                            <input
                                v-model="form.current_club_ssb"
                                type="text"
                                placeholder="Contoh: SSB Macan Kemayoran Jakarta"
                                class="input-field"
                                :class="fieldClass('current_club_ssb')"
                                @input="onFieldInput('current_club_ssb')"
                                @blur="onFieldBlur('current_club_ssb')"
                            />
                            <InputError :message="form.errors.current_club_ssb" />
                        </div>

                        <!-- Pengalaman Bertanding -->
                        <div v-if="presets.experience" data-field="playing_experience" class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Riwayat Pengalaman / Turnamen yang Pernah Diikuti</label>
                            <textarea
                                v-model="form.playing_experience"
                                rows="3"
                                placeholder="Tuliskan nama turnamen, liga usia muda, atau prestasi yang pernah diraih..."
                                class="input-field"
                                :class="fieldClass('playing_experience')"
                                @input="onFieldInput('playing_experience')"
                            ></textarea>
                            <InputError :message="form.errors.playing_experience" />
                        </div>
                    </div>

                    <div v-if="customFields.length > 0" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div
                            v-for="field in customFields"
                            :key="field.key || field.name"
                            :data-field="`custom_answers.${field.key || field.name}`"
                            class="sm:col-span-2"
                            :class="{ 'sm:col-span-1': field.type === 'number' || field.type === 'select' || field.type === 'radio' }"
                        >
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                {{ field.label || field.name }}
                                <span v-if="field.required" class="text-rose-600">*</span>
                            </label>
                            <textarea
                                v-if="field.type === 'textarea'"
                                v-model="form.custom_answers[field.key || field.name]"
                                rows="3"
                                class="input-field"
                                :class="customFieldClass(field)"
                                @input="onCustomFieldInput(field)"
                            />
                            <select
                                v-else-if="field.type === 'select' || field.type === 'radio'"
                                v-model="form.custom_answers[field.key || field.name]"
                                class="input-field"
                                :class="customFieldClass(field)"
                                @change="onCustomFieldInput(field)"
                            >
                                <option value="">Pilih {{ field.label || field.name }}</option>
                                <option v-for="option in (field.options || [])" :key="option" :value="option">{{ option }}</option>
                            </select>
                            <input
                                v-else
                                v-model="form.custom_answers[field.key || field.name]"
                                :type="field.type === 'number' ? 'number' : (field.type === 'url' ? 'url' : 'text')"
                                class="input-field"
                                :class="customFieldClass(field)"
                                @input="onCustomFieldInput(field)"
                            />
                            <InputError :message="form.errors[`custom_answers.${field.key || field.name}`]" />
                        </div>
                    </div>
                </div>

                <!-- SEKSI 4: KONTAK DARURAT & CATATAN MEDIS -->
                <div class="frosted-card p-6">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 mb-1 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">4</span>
                        <span>Kontak Darurat Medis Lapangan</span>
                    </h2>
                    <p class="text-xs text-slate-500 mb-4">
                        SOP Keselamatan: Kontak darurat cadangan yang dapat dihubungi tim medis jika orang tua berhalangan.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div data-field="emergency_contact_name">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kontak Darurat *</label>
                            <input
                                v-model="form.emergency_contact_name"
                                type="text"
                                placeholder="Nama kerabat / wali kedua"
                                class="input-field"
                                :class="fieldClass('emergency_contact_name')"
                                @input="onFieldInput('emergency_contact_name')"
                                @blur="onFieldBlur('emergency_contact_name')"
                            />
                            <InputError :message="form.errors.emergency_contact_name" />
                        </div>

                        <div data-field="emergency_contact_phone">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor WhatsApp Darurat *</label>
                            <input
                                v-model="form.emergency_contact_phone"
                                type="tel"
                                placeholder="08xxxxxxxxxx"
                                class="input-field"
                                :class="fieldClass('emergency_contact_phone')"
                                @input="onFieldInput('emergency_contact_phone')"
                                @blur="onFieldBlur('emergency_contact_phone')"
                            />
                            <InputError :message="form.errors.emergency_contact_phone" />
                        </div>

                        <div data-field="emergency_contact_relation">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Hubungan Keluarga</label>
                            <input
                                v-model="form.emergency_contact_relation"
                                type="text"
                                placeholder="Contoh: Ibu / Paman / Pelatih"
                                class="input-field"
                                :class="fieldClass('emergency_contact_relation')"
                                @input="onFieldInput('emergency_contact_relation')"
                            />
                            <InputError :message="form.errors.emergency_contact_relation" />
                        </div>

                        <div data-field="medical_notes" class="sm:col-span-3">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan Riwayat Medis / Alergi</label>
                            <textarea
                                v-model="form.medical_notes"
                                rows="2"
                                placeholder="Tuliskan riwayat asma, cedera bawaan, atau alergi obat tertentu (jika ada)..."
                                class="input-field"
                                :class="fieldClass('medical_notes')"
                                @input="onFieldInput('medical_notes')"
                            ></textarea>
                            <InputError :message="form.errors.medical_notes" />
                        </div>
                    </div>
                </div>

                <!-- SEKSI 5: UNGGAH DOKUMEN KEABSAHAN -->
                <div class="frosted-card p-6">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 mb-1 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-orange-100 text-orange-700 font-bold text-xs flex items-center justify-center">5</span>
                        <span>Unggah Berkas Keabsahan Atlet</span>
                    </h2>
                    <p class="text-xs text-slate-500 mb-4">
                        Dokumen wajib diverifikasi oleh Panitia Screening sebelum Digital Pass (E-Tiket) resmi diterbitkan.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Pas Foto (Hanya jika preset photo aktif) -->
                        <div v-if="presets.photo" data-field="photo">
                            <div
                                v-if="selectedChild?.has_photo && !form.photo"
                                class="mb-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-3 text-xs"
                            >
                                <img
                                    v-if="selectedChild.photo_url"
                                    :src="selectedChild.photo_url"
                                    :alt="selectedChild.full_name"
                                    class="w-9 h-9 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                                />
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800">Pas foto sebelumnya digunakan otomatis</p>
                                    <p class="text-[11px] text-slate-500">Unggah file baru di bawah hanya jika ingin mengganti foto.</p>
                                </div>
                            </div>
                            <FileInput
                                :model-value="form.photo"
                                accept="image/png,image/jpeg,image/webp"
                                :max-size-mb="3"
                                :label="selectedChild?.has_photo ? 'Pas Foto Resmi Peserta (Opsional - Ganti Foto)' : 'Pas Foto Resmi Peserta *'"
                                :hint="selectedChild?.has_photo ? 'Biarkan kosong untuk memakai pas foto tersimpan di akun.' : 'Foto wajah jelas setengah badan, latar polos (JPG/PNG/WEBP, maks. 3 MB).'"
                                :error="form.errors.photo"
                                @update:model-value="handlePhotoChange"
                            />
                        </div>

                        <!-- Akta Kelahiran -->
                        <div v-if="requiredDocuments.includes('birth_certificate')" data-field="birth_certificate">
                            <div
                                v-if="selectedChild?.has_birth_certificate && !form.birth_certificate"
                                class="mb-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs"
                            >
                                <p class="font-semibold text-slate-800">Berkas Akta Kelahiran tersimpan digunakan otomatis</p>
                                <p class="text-[11px] text-slate-500">Unggah ulang hanya jika ingin memperbarui berkas Akta Kelahiran.</p>
                            </div>
                            <FileInput
                                v-model="form.birth_certificate"
                                accept="image/png,image/jpeg,application/pdf"
                                :max-size-mb="4"
                                :label="selectedChild?.has_birth_certificate ? 'Scan Akta Kelahiran (Opsional - Ganti Berkas)' : 'Scan Akta Kelahiran *'"
                                :hint="selectedChild?.has_birth_certificate ? 'Biarkan kosong untuk memakai berkas Akta Kelahiran tersimpan di akun.' : 'Untuk verifikasi cut-off tanggal lahir (PDF/JPG/PNG, maks. 4 MB).'"
                                :error="form.errors.birth_certificate"
                                @change="handleBirthCertChange"
                            />
                        </div>

                        <!-- Kartu Keluarga -->
                        <div v-if="requiredDocuments.includes('family_card')" data-field="family_card">
                            <div
                                v-if="selectedChild?.has_family_card && !form.family_card"
                                class="mb-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs"
                            >
                                <p class="font-semibold text-slate-800">Berkas Kartu Keluarga (KK) tersimpan digunakan otomatis</p>
                                <p class="text-[11px] text-slate-500">Unggah ulang hanya jika ingin memperbarui berkas KK.</p>
                            </div>
                            <FileInput
                                v-model="form.family_card"
                                accept="image/png,image/jpeg,application/pdf"
                                :max-size-mb="4"
                                :label="selectedChild?.has_family_card ? 'Scan Kartu Keluarga (KK) (Opsional - Ganti Berkas)' : 'Scan Kartu Keluarga (KK) *'"
                                :hint="selectedChild?.has_family_card ? 'Biarkan kosong untuk memakai berkas Kartu Keluarga tersimpan di akun.' : 'Untuk verifikasi NIK dan data orang tua (PDF/JPG/PNG, maks. 4 MB).'"
                                :error="form.errors.family_card"
                                @change="handleFamilyCardChange"
                            />
                        </div>

                        <!-- Dokumen Tambahan -->
                        <div v-if="hasExtraDocument" data-field="additional_doc">
                            <FileInput
                                v-model="form.additional_doc"
                                accept="image/png,image/jpeg,application/pdf"
                                :max-size-mb="4"
                                label="Dokumen Tambahan yang Dipilih Panitia *"
                                :hint="(props.event.required_documents || []).filter((document) => !['birth_certificate', 'family_card'].includes(document.type || document)).map((document) => document.label || document).join(', ')"
                                :error="form.errors.additional_doc"
                                @change="handleAdditionalDocChange"
                            />
                        </div>
                    </div>
                </div>

                <!-- SEKSI 6: SYARAT & KETENTUAN -->
                <div data-field="liability_waiver" class="frosted-card p-6 bg-amber-50/40 border-amber-200/80">
                    <div class="flex flex-wrap items-start justify-between gap-2 mb-1">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-amber-600 text-white font-bold text-xs flex items-center justify-center">6</span>
                            <span>Syarat &amp; Ketentuan</span>
                        </h2>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="activeTnc?.version"
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                            >
                                v{{ activeTnc.version }}
                            </span>
                            <button
                                v-if="activeTnc"
                                type="button"
                                class="text-xs font-semibold text-amber-800 hover:underline"
                                @click="showTncModal = true"
                            >
                                {{ form.liability_waiver ? 'Baca ulang ketentuan' : 'Baca isi ketentuan' }}
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-slate-600 mb-4">
                        Klik kotak persetujuan untuk membaca dan menyetujui dokumen Syarat &amp; Ketentuan versi {{ activeTnc?.version || '1.0' }}.
                    </p>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white/90 border border-amber-200 cursor-pointer">
                        <input
                            v-model="form.liability_waiver"
                            type="checkbox"
                            class="mt-0.5 rounded border-amber-300 text-orange-600 focus:ring-orange-500 shrink-0"
                            @change="handleLiabilityChange"
                        />
                        <span class="text-xs text-slate-700 leading-relaxed">
                            <template v-if="activeTnc?.summary">
                                {{ activeTnc.summary }} *
                            </template>
                            <template v-else>
                                <strong>Saya selaku orang tua / wali sah menyatakan</strong> bahwa anak yang didaftarkan dalam kondisi sehat jasmani dan rohani untuk mengikuti aktivitas fisik berat seleksi sepak bola, serta membebaskan panitia pelaksana dari tuntutan hukum atas risiko cedera olahraga yang terjadi selama rangkaian kegiatan audisi. *
                            </template>
                        </span>
                    </label>
                    <InputError :message="form.errors.liability_waiver || form.errors.tnc_version_id" class="mt-2" />

                    <TncAgreementModal
                        :show="showTncModal"
                        :tnc="activeTnc"
                        :already-accepted="Boolean(form.liability_waiver)"
                        accent="amber"
                        @close="showTncModal = false"
                        @accept="acceptTnc"
                    />
                </div>

                <!-- SEKSI 7: RINGKASAN BIAYA & PEMBAYARAN -->
                <div class="frosted-card p-6 bg-slate-50/70 border-slate-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-slate-900 text-white font-bold text-xs flex items-center justify-center">7</span>
                            <span>Ringkasan Biaya Pendaftaran</span>
                        </h2>
                        <span
                            class="text-[11px] font-bold px-2.5 py-0.5 rounded-full border"
                            :class="baseFee > 0 ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'"
                        >
                            {{ baseFee > 0 ? (isGatewayPayment ? gatewayProviderLabel : 'Transfer Bank Manual') : 'Pendaftaran Gratis' }}
                        </span>
                    </div>

                    <div class="rounded-xl bg-white border border-slate-200/90 p-4 space-y-2.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-600">Biaya Pendaftaran Peserta ({{ event.name }})</span>
                            <span class="font-mono tabular-data font-bold text-slate-900">
                                {{ baseFee > 0 ? formatRupiah(baseFee) : 'Gratis (Rp 0)' }}
                            </span>
                        </div>

                        <div v-if="baseFee > 0 && isGatewayPayment" class="flex items-center justify-between">
                            <span class="text-slate-600">Estimasi Biaya Layanan Payment Gateway</span>
                            <span v-if="estimatedGatewayFee > 0" class="font-mono tabular-data font-semibold text-orange-700">
                                + s.d. {{ formatRupiah(estimatedGatewayFee) }}
                            </span>
                            <span v-else class="font-semibold text-emerald-700">Gratis (Ditanggung Penyelenggara)</span>
                        </div>

                        <div v-if="baseFee > 0" class="flex items-center justify-between">
                            <span class="text-slate-600">Batas Waktu Pembayaran</span>
                            <span class="font-semibold text-slate-800">{{ event.payment_deadline_hours || 24 }} jam setelah formulir dikirim</span>
                        </div>

                        <div class="flex items-center justify-between pt-2.5 border-t border-slate-200 text-sm font-bold">
                            <span class="text-slate-900">Total yang Dibayar</span>
                            <span class="font-mono tabular-data text-lg text-orange-600">
                                {{ baseFee > 0 ? formatRupiah(baseFee + estimatedGatewayFee) : 'Rp 0' }}
                            </span>
                        </div>
                    </div>

                    <p class="text-[11px] text-slate-500 mt-3 leading-relaxed">
                        <template v-if="baseFee > 0">
                            Setelah formulir dikirim, Anda langsung diarahkan ke <strong>halaman checkout peserta terdaftar</strong> untuk menyelesaikan pembayaran
                            <template v-if="isGatewayPayment && (paymentConfig.provider || 'midtrans') === 'midtrans'">
                                dan jendela <strong>Midtrans Snap</strong> akan terbuka otomatis.
                            </template>
                            <template v-else-if="isGatewayPayment">
                                melalui invoice <strong>Xendit</strong>.
                            </template>
                            <template v-else>
                                melalui transfer rekening bank resmi panitia.
                            </template>
                        </template>
                        <template v-else>
                            Kegiatan ini bebas biaya pendaftaran. Setelah dikirim, Anda langsung diarahkan ke portal peserta untuk memantau status verifikasi berkas.
                        </template>
                    </p>
                </div>

                <!-- SUBMIT BUTTON -->
                <div class="flex items-center justify-between gap-4 pt-2">
                    <Link
                        :href="route('events.show', { event: event.slug })"
                        class="btn-secondary text-xs sm:text-sm"
                    >
                        Batal
                    </Link>

                    <button
                        type="submit"
                        class="btn-pitch px-6 py-3 text-sm font-bold shadow-md shadow-orange-500/25 flex items-center gap-2"
                        :disabled="form.processing"
                    >
                        <span>
                            {{
                                form.processing
                                    ? 'Memproses Pendaftaran...'
                                    : (baseFee > 0 ? 'Kirim Pendaftaran & Bayar' : 'Kirim Pendaftaran Audisi')
                            }}
                        </span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
                </template>
            </form>
        </div>
    </PublicLayout>
</template>
