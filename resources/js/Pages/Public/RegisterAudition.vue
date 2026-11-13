<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, Link, Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import FileInput from '@/Components/FileInput.vue';
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

const presets = computed(() => {
    const raw = props.event.form_presets || {};
    return {
        ...raw,
        foot_dominance: raw.foot_dominance ?? raw.foot ?? false,
        jersey_size: raw.jersey_size ?? raw.jersey ?? false,
    };
});
const customFields = computed(() => props.event.custom_fields_schema || []);
const sessions = computed(() => props.event.sessions || []);
const requiredDocuments = computed(() => (props.event.required_documents || []).map((document) => document.type || document));
const hasExtraDocument = computed(() => requiredDocuments.value.some((document) => !['birth_certificate', 'family_card'].includes(document)));

const form = useForm({
    // Guardian
    guardian_name: props.prefillUser?.name || '',
    guardian_email: props.prefillUser?.email || '',
    guardian_phone: props.prefillUser?.phone || '',
    guardian_password: '',
    guardian_password_confirmation: '',

    // Athlete
    full_name: '',
    nickname: '',
    nik: '',
    birth_date: '',
    gender: 'male',
    event_session_id: '',

    // Presets
    preferred_position: '',
    secondary_position: '',
    dominant_foot: 'right',
    height_cm: '',
    weight_kg: '',
    current_club_ssb: '',
    playing_experience: '',
    jersey_size: 'M',

    // Safety & Contact
    emergency_contact_name: '',
    emergency_contact_phone: '',
    emergency_contact_relation: '',
    medical_notes: '',
    liability_waiver: false,

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

const submit = () => {
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
            errs.jersey_size = 'Ukuran jersey / rompi wajib dipilih.';
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
            if (isEmpty(form.guardian_name)) {
                errs.guardian_name = 'Nama lengkap orang tua / wali wajib diisi.';
            }

            if (isEmpty(form.guardian_email)) {
                errs.guardian_email = 'Alamat email wajib diisi.';
            } else if (!isValidEmail(form.guardian_email)) {
                errs.guardian_email = 'Format alamat email tidak valid.';
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

        // 5. Berkas Unggahan
        if (props.event?.form_presets?.photo) {
            const photoCheck = checkFile(form.photo, ['image/png', 'image/jpeg', 'image/webp'], 3 * 1024 * 1024);
            if (!photoCheck.valid) {
                if (photoCheck.reason === 'missing') errs.photo = 'Pas foto resmi peserta wajib diunggah.';
                else if (photoCheck.reason === 'mime') errs.photo = 'Pas foto harus berformat JPG, PNG, atau WEBP.';
                else if (photoCheck.reason === 'size') errs.photo = 'Ukuran pas foto maksimal 3 MB.';
            }
        }

        const birthCheck = checkFile(form.birth_certificate, ['image/png', 'image/jpeg', 'application/pdf'], 4 * 1024 * 1024);
        if (requiredDocuments.value.includes('birth_certificate') && !birthCheck.valid) {
            if (birthCheck.reason === 'missing') errs.birth_certificate = 'Scan Akta Kelahiran wajib diunggah.';
            else errs.birth_certificate = 'Berkas Akta Kelahiran harus berupa JPG, PNG, atau PDF (maks. 4 MB).';
        }

        const kkCheck = checkFile(form.family_card, ['image/png', 'image/jpeg', 'application/pdf'], 4 * 1024 * 1024);
        if (requiredDocuments.value.includes('family_card') && !kkCheck.valid) {
            if (kkCheck.reason === 'missing') errs.family_card = 'Scan Kartu Keluarga (KK) wajib diunggah.';
            else errs.family_card = 'Berkas Kartu Keluarga harus berupa JPG, PNG, atau PDF (maks. 4 MB).';
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
                        Data akun portal untuk memantau status kelolosan, hasil penilaian, dan tiket nomor dada.
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
                        <div data-field="guardian_name">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap Orang Tua / Wali *</label>
                            <input
                                v-model="form.guardian_name"
                                type="text"
                                placeholder="Contoh: Bambang Pamungkas"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.guardian_name }"
                            />
                            <InputError :message="form.errors.guardian_name" />
                        </div>

                        <div data-field="guardian_email">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email Aktif *</label>
                            <input
                                v-model="form.guardian_email"
                                type="email"
                                placeholder="nama@email.com"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.guardian_email }"
                            />
                            <InputError :message="form.errors.guardian_email" />
                        </div>

                        <div data-field="guardian_phone">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor WhatsApp Aktif *</label>
                            <input
                                v-model="form.guardian_phone"
                                type="tel"
                                placeholder="081234567890"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.guardian_phone }"
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
                                :class="{ 'input-field-error': form.errors.guardian_password }"
                            />
                            <InputError :message="form.errors.guardian_password" />
                        </div>

                        <div data-field="guardian_password_confirmation" class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi *</label>
                            <input
                                v-model="form.guardian_password_confirmation"
                                type="password"
                                placeholder="Ulangi kata sandi"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.guardian_password_confirmation }"
                            />
                            <InputError :message="form.errors.guardian_password_confirmation" />
                        </div>
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div data-field="full_name">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap Atlet *</label>
                            <input
                                v-model="form.full_name"
                                type="text"
                                placeholder="Nama lengkap sesuai Akta Lahir"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.full_name }"
                            />
                            <InputError :message="form.errors.full_name" />
                        </div>

                        <div data-field="nickname">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Panggilan Lapangan</label>
                            <input
                                v-model="form.nickname"
                                type="text"
                                placeholder="Nama panggilan di jersey / rompi"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.nickname }"
                            />
                            <InputError :message="form.errors.nickname" />
                        </div>

                        <div data-field="nik">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">NIK (16 Digit) *</label>
                            <input
                                v-model="form.nik"
                                type="text"
                                maxlength="16"
                                placeholder="3171xxxxxxxxxxxx"
                                class="input-field font-mono"
                                :class="{ 'input-field-error': form.errors.nik }"
                            />
                            <InputError :message="form.errors.nik" />
                        </div>

                        <div data-field="birth_date">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Lahir *</label>
                            <input
                                v-model="form.birth_date"
                                type="date"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.birth_date }"
                            />
                            <InputError :message="form.errors.birth_date" />
                        </div>

                        <div data-field="gender">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Kelamin *</label>
                            <select
                                v-model="form.gender"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.gender }"
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
                                :class="{ 'input-field-error': form.errors.event_session_id }"
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
                                :class="{ 'input-field-error': form.errors.preferred_position }"
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
                                :class="{ 'input-field-error': form.errors.secondary_position }"
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
                                :class="{ 'input-field-error': form.errors.dominant_foot }"
                            >
                                <option value="right">Kaki Kanan</option>
                                <option value="left">Kaki Kiri (Kidal)</option>
                                <option value="both">Kedua Kaki (Ambidextrous)</option>
                            </select>
                            <InputError :message="form.errors.dominant_foot" />
                        </div>

                        <!-- Ukuran Jersey / Rompi -->
                        <div v-if="presets.jersey_size" data-field="jersey_size">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Ukuran Jersey / Rompi *</label>
                            <select
                                v-model="form.jersey_size"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.jersey_size }"
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
                                :class="{ 'input-field-error': form.errors.height_cm }"
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
                                :class="{ 'input-field-error': form.errors.weight_kg }"
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
                                :class="{ 'input-field-error': form.errors.current_club_ssb }"
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
                                :class="{ 'input-field-error': form.errors.playing_experience }"
                            ></textarea>
                            <InputError :message="form.errors.playing_experience" />
                        </div>
                    </div>

                    <div v-if="customFields.length > 0" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div
                            v-for="field in customFields"
                            :key="field.key || field.name"
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
                            />
                            <select
                                v-else-if="field.type === 'select' || field.type === 'radio'"
                                v-model="form.custom_answers[field.key || field.name]"
                                class="input-field"
                            >
                                <option value="">Pilih {{ field.label || field.name }}</option>
                                <option v-for="option in (field.options || [])" :key="option" :value="option">{{ option }}</option>
                            </select>
                            <input
                                v-else
                                v-model="form.custom_answers[field.key || field.name]"
                                :type="field.type === 'number' ? 'number' : (field.type === 'url' ? 'url' : 'text')"
                                class="input-field"
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
                                :class="{ 'input-field-error': form.errors.emergency_contact_name }"
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
                                :class="{ 'input-field-error': form.errors.emergency_contact_phone }"
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
                                :class="{ 'input-field-error': form.errors.emergency_contact_relation }"
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
                                :class="{ 'input-field-error': form.errors.medical_notes }"
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
                        Dokumen wajib diverifikasi oleh Panitia Screening sebelum nomor dada diterbitkan.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Pas Foto (Hanya jika preset photo aktif) -->
                        <div v-if="event.form_presets?.photo" data-field="photo">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Pas Foto Resmi Peserta *</label>
                            <FileInput
                                accept="image/png,image/jpeg,image/webp"
                                :max-size-m-b="3"
                                placeholder="Pilih berkas foto peserta (JPG/PNG)"
                                @change="handlePhotoChange"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Foto wajah jelas setengah badan, latar polos.</p>
                            <InputError :message="form.errors.photo" />
                        </div>

                        <!-- Akta Kelahiran -->
                        <div v-if="requiredDocuments.includes('birth_certificate')" data-field="birth_certificate">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Scan Akta Kelahiran *</label>
                            <FileInput
                                accept="image/png,image/jpeg,application/pdf"
                                :max-size-m-b="4"
                                placeholder="Pilih Akta Lahir (PDF/JPG/PNG)"
                                @change="handleBirthCertChange"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Untuk verifikasi cut-off tanggal lahir.</p>
                            <InputError :message="form.errors.birth_certificate" />
                        </div>

                        <!-- Kartu Keluarga -->
                        <div v-if="requiredDocuments.includes('family_card')" data-field="family_card">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Scan Kartu Keluarga (KK) *</label>
                            <FileInput
                                accept="image/png,image/jpeg,application/pdf"
                                :max-size-m-b="4"
                                placeholder="Pilih Kartu Keluarga (PDF/JPG/PNG)"
                                @change="handleFamilyCardChange"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Untuk verifikasi NIK dan data orang tua.</p>
                            <InputError :message="form.errors.family_card" />
                        </div>

                        <!-- Dokumen Tambahan -->
                        <div v-if="hasExtraDocument" data-field="additional_doc">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Dokumen Tambahan yang Dipilih Panitia *</label>
                            <FileInput
                                accept="image/png,image/jpeg,application/pdf"
                                :max-size-m-b="4"
                                placeholder="KIA / Rapor / NISN (PDF/JPG/PNG)"
                                @change="handleAdditionalDocChange"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">
                                {{ (props.event.required_documents || []).filter((document) => !['birth_certificate', 'family_card'].includes(document.type || document)).map((document) => document.label || document).join(', ') }}
                            </p>
                            <InputError :message="form.errors.additional_doc" />
                        </div>
                    </div>
                </div>

                <!-- SEKSI 6: PERNYATAAN BEBAS TANGGUNG JAWAB MEDIS (LIABILITY WAIVER) -->
                <div class="frosted-card p-6 bg-amber-50/40 border-amber-200/80">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 mb-1 flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-amber-600 text-white font-bold text-xs flex items-center justify-center">6</span>
                        <span>Surat Pernyataan Bebas Tanggung Jawab Medis</span>
                    </h2>
                    <p class="text-xs text-slate-600 mb-4">
                        Pernyataan hukum resmi orang tua/wali atlet sebelum mengikuti tes fisik lapangan.
                    </p>

                    <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white/90 border border-amber-200 cursor-pointer">
                        <input
                            v-model="form.liability_waiver"
                            type="checkbox"
                            class="mt-0.5 rounded border-amber-300 text-orange-600 focus:ring-orange-500 shrink-0"
                        />
                        <span class="text-xs text-slate-700 leading-relaxed">
                            <strong>Saya selaku orang tua / wali sah menyatakan</strong> bahwa anak yang didaftarkan dalam kondisi sehat jasmani dan rohani untuk mengikuti aktivitas fisik berat seleksi sepak bola, serta membebaskan panitia pelaksana dari tuntutan hukum atas risiko cedera olahraga yang terjadi selama rangkaian kegiatan audisi. *
                        </span>
                    </label>
                    <InputError :message="form.errors.liability_waiver" class="mt-2" />
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
                        <span>{{ form.processing ? 'Memproses Pendaftaran...' : 'Kirim Pendaftaran Audisi' }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </PublicLayout>
</template>
