<script setup>
import { ref, computed } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import FileInput from '@/Components/FileInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
import { compressImage } from '@/Utils/imageCompressor';

const props = defineProps({
    event: {
        type: Object,
        default: null,
    },
    initialParticipantType: {
        type: String,
        default: 'team',
    },
    formats: {
        type: Object,
        default: () => ({}),
    },
    matchFormats: {
        type: Object,
        default: () => ({}),
    },
    statuses: {
        type: Object,
        default: () => ({}),
    },
    documentTypes: {
        type: Object,
        default: () => ({}),
    },
    eventTypes: {
        type: Object,
        default: () => ({}),
    },
    participantTypes: {
        type: Object,
        default: () => ({}),
    },
    quotaTypes: {
        type: Object,
        default: () => ({}),
    },
    defaultFormPresets: {
        type: Object,
        default: () => ({}),
    },
});

const isEdit = computed(() => Boolean(props.event?.id));
const bannerPreview = ref(props.event?.banner_url || null);
const { applyValidation, isEmpty } = useFormValidator();

const getInitialType = () => {
    if (props.event?.participant_type) return props.event.participant_type;
    if (typeof window !== 'undefined') {
        const urlParam = new URLSearchParams(window.location.search).get('participant_type');
        if (urlParam === 'individual' || urlParam === 'team') return urlParam;
    }
    return props.initialParticipantType || 'team';
};

const initialType = getInitialType();

const form = useForm({
    name: props.event?.name || '',
    event_type: props.event?.event_type || (initialType === 'individual' ? 'audition' : 'tournament'),
    participant_type: initialType,
    quota_type: props.event?.quota_type || 'fixed',
    max_participants: props.event?.max_participants ?? 100,
    position_quota_caps: {
        GK: props.event?.position_quota_caps?.GK ?? null,
        CB: props.event?.position_quota_caps?.CB ?? null,
        FB: props.event?.position_quota_caps?.FB ?? null,
        MF: props.event?.position_quota_caps?.MF ?? null,
        FW: props.event?.position_quota_caps?.FW ?? null,
    },
    form_presets: {
        photo: props.event?.form_presets?.photo ?? false,
        position: props.event?.form_presets?.position ?? true,
        foot_dominance: props.event?.form_presets?.foot_dominance ?? true,
        team_origin: props.event?.form_presets?.team_origin ?? true,
        body_measurements: props.event?.form_presets?.body_measurements ?? true,
        experience: props.event?.form_presets?.experience ?? true,
        jersey_size: props.event?.form_presets?.jersey_size ?? false,
    },
    custom_fields_schema: props.event?.custom_fields_schema || [],
    short_description: props.event?.short_description || '',
    venue_name: props.event?.venue_name || '',
    venue_maps_url: props.event?.venue_maps_url || '',
    city: props.event?.city || '',
    format: props.event?.format || (initialType === 'individual' ? 'audition' : 'cup'),
    match_format: props.event?.match_format || '11_vs_11',
    starting_players_count: props.event?.starting_players_count ?? 11,
    dsp_cutoff_minutes: props.event?.dsp_cutoff_minutes ?? 30,
    age_category: props.event?.age_category || 'U-15',
    min_birth_date: props.event?.min_birth_date || '',
    max_birth_date: props.event?.max_birth_date || '',
    min_players_per_team: props.event?.min_players_per_team ?? 14,
    max_players_per_team: props.event?.max_players_per_team ?? 25,
    min_gk_per_team: props.event?.min_gk_per_team ?? 2,
    max_officials_per_team: props.event?.max_officials_per_team ?? 5,
    show_coach_license: props.event?.show_coach_license ?? true,
    require_coach_license: props.event?.require_coach_license ?? false,
    required_documents: props.event?.required_documents || ['birth_certificate', 'family_card', 'student_card_kia'],
    max_teams: props.event?.max_teams ?? 32,
    registration_fee: props.event?.registration_fee ?? 0,
    prize_info: props.event?.prize_info || '',
    lock_roster_until_paid: props.event?.lock_roster_until_paid ?? true,
    payment_deadline_hours: props.event?.payment_deadline_hours ?? 24,
    reupload_grace_hours: props.event?.reupload_grace_hours ?? 6,
    registration_open_at: props.event?.registration_open_at || '',
    registration_close_at: props.event?.registration_close_at || '',
    match_start_date: props.event?.match_start_date || '',
    match_end_date: props.event?.match_end_date || '',
    contact_person: props.event?.contact_person || '',
    contact_whatsapp: props.event?.contact_whatsapp || '',
    status: props.event?.status || 'draft',
    banner_file: null,
    regulation_pdf: null,
});

const isIndividual = computed(() => form.participant_type === 'individual');
const isAudition = computed(() => isIndividual.value && form.event_type === 'audition');
const isCoachingClinic = computed(() => isIndividual.value && form.event_type === 'coaching_clinic');
const isFestival = computed(() => form.event_type === 'festival');

const totalPositionCaps = computed(() => {
    if (!form.position_quota_caps || typeof form.position_quota_caps !== 'object') return 0;
    return Object.values(form.position_quota_caps).reduce((sum, val) => {
        const n = Number(val);
        return sum + (isNaN(n) || n <= 0 ? 0 : n);
    }, 0);
});

const resetPositionCaps = () => {
    form.position_quota_caps = {
        GK: null,
        CB: null,
        FB: null,
        MF: null,
        FW: null,
    };
};

const availableFormats = computed(() => {
    if (isIndividual.value) {
        return props.formats || {};
    }
    const teamFormats = {};
    for (const [k, v] of Object.entries(props.formats || {})) {
        if (k !== 'audition' && k !== 'coaching_clinic') {
            teamFormats[k] = v;
        }
    }
    return teamFormats;
});

const selectParticipantType = (type) => {
    form.participant_type = type;
    if (type === 'individual') {
        if (form.event_type === 'tournament') {
            form.event_type = 'audition';
        }
        form.format = form.event_type === 'coaching_clinic' ? 'coaching_clinic' : (form.event_type === 'festival' ? 'festival' : 'audition');
    } else {
        if (form.event_type === 'audition' || form.event_type === 'coaching_clinic') {
            form.event_type = 'tournament';
        }
        if (form.format === 'audition' || form.format === 'coaching_clinic') {
            form.format = 'cup';
        }
    }
};

const handleBannerFile = async (raw) => {
    if (!raw) {
        form.banner_file = null;
        bannerPreview.value = props.event?.banner_url || null;
        return;
    }
    const { file } = await compressImage(raw, { maxDimension: 1920, quality: 0.85 });
    form.banner_file = file;
    form.clearErrors('banner_file');
    bannerPreview.value = URL.createObjectURL(file);
};

const handlePdfFile = (raw) => {
    form.regulation_pdf = raw || null;
    form.clearErrors('regulation_pdf');
};

const onMatchFormatChange = () => {
    const val = form.match_format;
    if (props.matchFormats && props.matchFormats[val]) {
        form.starting_players_count = props.matchFormats[val].starting_count;
    }
};

const customFieldTypes = [
    { value: 'text', label: 'Teks singkat' },
    { value: 'textarea', label: 'Teks panjang' },
    { value: 'number', label: 'Angka' },
    { value: 'select', label: 'Pilihan dropdown' },
    { value: 'radio', label: 'Pilihan tunggal' },
    { value: 'url', label: 'URL / tautan' },
];

const addCustomField = () => {
    form.custom_fields_schema.push({
        key: `field_${Date.now()}`,
        label: '',
        type: 'text',
        options: [],
        required: false,
    });
};

const removeCustomField = (index) => {
    form.custom_fields_schema.splice(index, 1);
};

const normalizeCustomOptions = (field) => {
    field.options = String(field.options || '')
        .split(',')
        .map((option) => option.trim())
        .filter(Boolean);
};

const submit = () => {
    form.custom_fields_schema.forEach((field) => {
        if (field.type === 'select' || field.type === 'radio') {
            normalizeCustomOptions(field);
        }
    });

    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.name)) {
            if (isCoachingClinic.value) {
                errs.name = 'Nama coaching clinic / workshop wajib diisi.';
            } else if (isAudition.value) {
                errs.name = 'Nama event audisi / seleksi wajib diisi.';
            } else if (isIndividual.value) {
                errs.name = 'Nama event perorangan wajib diisi.';
            } else {
                errs.name = 'Nama kompetisi turnamen wajib diisi.';
            }
        }
        if (isEmpty(form.venue_name)) {
            errs.venue_name = isIndividual.value ? 'Lokasi atau venue kegiatan wajib diisi.' : 'Lokasi atau stadion utama wajib diisi.';
        }
        if (isEmpty(form.city)) errs.city = 'Kota penyelenggaraan wajib diisi.';
        if (isEmpty(form.age_category)) errs.age_category = 'Label kategori usia wajib diisi.';
        
        if (form.quota_type === 'fixed') {
            if (!isIndividual.value) {
                if (isEmpty(form.max_teams) || Number(form.max_teams) < 2) {
                    errs.max_teams = 'Kuota maksimal tim wajib berupa angka minimal 2 tim.';
                }
                if (isEdit.value && props.event?.occupied_slots && Number(form.max_teams) < props.event.occupied_slots) {
                    errs.max_teams = `Kuota maksimal tidak boleh dikurangi di bawah jumlah tim yang saat ini sedang memegang slot (${props.event.occupied_slots} tim).`;
                }
            } else {
                if (isEmpty(form.max_participants) || Number(form.max_participants) < 2) {
                    errs.max_participants = 'Kuota maksimal peserta wajib berupa angka minimal 2 orang.';
                }
            }
        }
        
        if (isEmpty(form.required_documents) || form.required_documents.length === 0) {
            errs.required_documents = 'Pilih minimal satu jenis dokumen kelengkapan wajib untuk screening.';
        }
        return errs;
    });

    if (!ok) return;

    if (isEdit.value) {
        form.post(route('admin.events.update', { event: props.event.id }), {
            forceFormData: true,
            preserveScroll: true,
        });
    } else {
        form.post(route('admin.events.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AdminLayout
        :page-title="isEdit
            ? `Edit Event: ${event.name}`
            : (isIndividual ? (isCoachingClinic ? 'Buat Coaching Clinic / Workshop Baru' : (isAudition ? 'Buat Event Audisi / Seleksi Pemain Baru' : 'Buat Event Perorangan Baru')) : 'Buat Turnamen Sepak Bola Baru')"
        :subtitle="isIndividual
            ? (isCoachingClinic
                ? 'Atur kuota peserta, kelompok usia, silabus pelatihan, fasilitas peserta, dan jadwal kegiatan.'
                : 'Atur kuota peserta, kelompok usia & cut-off, form preset pendaftaran, berkas screening, serta jadwal kegiatan.')
            : 'Atur batas kuota tim (anti-overbooking), kelompok umur kelayakan pemain, format pertandingan, dan dokumen wajib.'"
    >
        <Head :title="isEdit ? `Edit ${event.name}` : (isIndividual ? 'Buat Event Baru' : 'Buat Turnamen Baru')" />

        <div class="w-full space-y-6">
            <div class="flex items-center justify-between">
                <Link :href="route('admin.events.index')" class="text-xs font-semibold text-slate-600 hover:text-orange-600 transition-colors">
                    &larr; Kembali ke Daftar Event &amp; Kompetisi
                </Link>
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold"
                    :class="isIndividual ? 'bg-amber-50 text-amber-900 border border-amber-300' : 'bg-orange-50 text-orange-950 border border-orange-300'"
                >
                    <svg v-if="isIndividual" class="w-3.5 h-3.5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <svg v-else class="w-3.5 h-3.5 text-orange-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span>{{ isIndividual ? 'Mode Event Perorangan / Mandiri' : 'Mode Turnamen Beregu / Tim' }}</span>
                </span>
            </div>

            <!-- STEP 0: MODEL EVENT SELECTOR (CARD SWITCHER) -->
            <div class="frosted-card p-5 sm:p-6 border border-slate-200">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Model Kepesertaan Event</h2>
                        <p class="text-xs text-slate-500">Pilih model pendaftaran untuk menyesuaikan formulir secara otomatis.</p>
                    </div>
                    <span v-if="isEdit" class="text-[11px] font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                        Model Terkunci saat Edit
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <!-- Card 1: Turnamen Tim Beregu -->
                    <div
                        @click="!isEdit && selectParticipantType('team')"
                        class="p-4 rounded-xl border-2 transition-all relative"
                        :class="[
                            !isIndividual
                                ? 'border-orange-600 bg-orange-50/20 shadow-sm'
                                : 'border-slate-200 bg-slate-50/60 hover:bg-white hover:border-slate-300 opacity-75',
                            isEdit ? 'cursor-not-allowed' : 'cursor-pointer'
                        ]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :class="!isIndividual ? 'bg-orange-600 text-white' : 'bg-slate-200 text-slate-600'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xs font-bold text-slate-900">Format Beregu / Tim</h3>
                                    <span class="text-[10px] font-semibold text-orange-700 uppercase tracking-wider">SSB / Klub / Sekolah</span>
                                </div>
                            </div>
                            <span v-if="!isIndividual" class="text-[11px] font-semibold text-orange-700 bg-orange-100 px-2 py-0.5 rounded border border-orange-200">
                                Aktif
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-600 mt-2.5 leading-relaxed">
                            Pendaftaran per tim, pembagian grup &amp; bagan gugur, susunan DSP starting lineup (11v11 / 7v7), jadwal tanding, dan klasemen/top score.
                        </p>
                    </div>

                    <!-- Card 2: Individu / Perorangan -->
                    <div
                        @click="!isEdit && selectParticipantType('individual')"
                        class="p-4 rounded-xl border-2 transition-all relative"
                        :class="[
                            isIndividual
                                ? 'border-orange-600 bg-orange-50/20 shadow-sm'
                                : 'border-slate-200 bg-slate-50/60 hover:bg-white hover:border-slate-300 opacity-75',
                            isEdit ? 'cursor-not-allowed' : 'cursor-pointer'
                        ]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" :class="isIndividual ? 'bg-orange-600 text-white' : 'bg-slate-200 text-slate-600'">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-xs font-bold text-slate-900">Format Perorangan / Individu</h3>
                                    <span class="text-[10px] font-semibold text-orange-700 uppercase tracking-wider">Atlet / Anak / Mandiri</span>
                                </div>
                            </div>
                            <span v-if="isIndividual" class="text-[11px] font-semibold text-orange-700 bg-orange-100 px-2 py-0.5 rounded border border-orange-200">
                                Aktif
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-600 mt-2.5 leading-relaxed">
                            Pendaftaran mandiri peserta, form preset profil (posisi, antropometri, SSB asal), Digital Pass (E-Tiket) QR, presensi gerbang, dan sertifikat partisipasi atau seleksi.
                        </p>
                    </div>
                </div>
            </div>

            <div class="frosted-card p-6 sm:p-8">
                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-6">
                    <!-- SECTION 1: INFORMASI DASAR EVENT -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200 flex items-center gap-2">
                            <span>1.</span>
                            <span>{{ isIndividual ? 'Informasi Dasar Event Perorangan' : 'Informasi Dasar Kompetisi Turnamen' }}</span>
                        </h2>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div data-field="name" class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    {{ isCoachingClinic
                                        ? 'Nama Coaching Clinic / Workshop'
                                        : (isAudition ? 'Nama Event Audisi / Seleksi' : (isIndividual ? 'Nama Event' : 'Nama Kompetisi / Turnamen')) }}
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    :placeholder="isCoachingClinic
                                        ? 'Contoh: Persija Youth Masterclass Coaching Clinic 2026'
                                        : (isAudition ? 'Contoh: Audisi Terbuka EPA Persija U-16 2026' : 'Contoh: Piala Nusantara U-15 2026')"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.name }"
                                    @input="form.clearErrors('name')"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div data-field="event_type">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Penyelenggaraan</label>
                                <select v-model="form.event_type" class="input-field">
                                    <template v-if="isIndividual">
                                        <option value="audition">Audisi / Seleksi Pemain Berjenjang</option>
                                        <option value="coaching_clinic">Coaching Clinic / Workshop Pelatihan</option>
                                        <option value="festival">Festival Grassroots / Fun Day Mandiri</option>
                                    </template>
                                    <template v-else>
                                        <option value="tournament">Turnamen Kompetisi Tim Beregu</option>
                                        <option value="festival">Festival Sepak Bola Beregu</option>
                                    </template>
                                </select>
                            </div>

                            <div data-field="status">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Status Publikasi</label>
                                <select v-model="form.status" class="input-field">
                                    <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>

                            <!-- KHUSUS TURNAMEN TIM: FORMAT PERTANDINGAN & DSP (SEMBUNYI SAAT PERORANGAN / INDIVIDU) -->
                            <template v-if="!isIndividual">
                                <div data-field="format">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Sistem Turnamen</label>
                                    <select v-model="form.format" class="input-field">
                                        <option v-for="(label, key) in availableFormats" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>

                                <div data-field="match_format">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        Format Jumlah Pemain di Lapangan
                                    </label>
                                    <select v-model="form.match_format" class="input-field" @change="onMatchFormatChange">
                                        <option v-for="(item, key) in matchFormats" :key="key" :value="key">
                                            {{ item.label }}
                                        </option>
                                    </select>
                                    <p class="text-[11px] text-slate-500 mt-1">
                                        Menentukan jumlah starting lineup saat manajer mengisi formulir DSP.
                                    </p>
                                </div>

                                <div data-field="starting_players_count">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        Starter Wajib di DSP (Starting Lineup)
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="form.starting_players_count"
                                            type="number"
                                            class="input-field tabular-data w-28"
                                        />
                                        <span class="text-xs text-slate-500">Pemain (termasuk 1 Kiper)</span>
                                    </div>
                                    <InputError :message="form.errors.starting_players_count" />
                                </div>

                                <div data-field="dsp_cutoff_minutes">
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        Batas Kunci DSP (Menit Sebelum Kick-off)
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="form.dsp_cutoff_minutes"
                                            type="number"
                                            class="input-field tabular-data w-28"
                                        />
                                        <span class="text-xs text-slate-500">Menit sebelum kick-off (Default: 30 menit)</span>
                                    </div>
                                    <InputError :message="form.errors.dsp_cutoff_minutes" />
                                </div>
                            </template>

                            <div data-field="venue_name">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    {{ isIndividual ? 'Venue / Lokasi Kegiatan' : 'Stadion / Lapangan Utama' }}
                                </label>
                                <input
                                    v-model="form.venue_name"
                                    type="text"
                                    :placeholder="isIndividual ? 'Lapangan Latihan Pulomas / National Youth Training Centre' : 'Stadion Madya GBK'"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.venue_name }"
                                    @input="form.clearErrors('venue_name')"
                                />
                                <InputError :message="form.errors.venue_name" />
                            </div>

                            <div data-field="city">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kota Penyelenggaraan</label>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    placeholder="Jakarta Timur"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.city }"
                                    @input="form.clearErrors('city')"
                                />
                                <InputError :message="form.errors.city" />
                            </div>

                            <div data-field="venue_maps_url" class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Link Google Maps Lokasi Lapangan <span class="text-slate-400 font-normal">(Disarankan)</span>
                                </label>
                                <input
                                    v-model="form.venue_maps_url"
                                    type="url"
                                    placeholder="https://maps.google.com/?q=..."
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.venue_maps_url }"
                                    @input="form.clearErrors('venue_maps_url')"
                                />
                                <p class="text-[11px] text-slate-500 mt-1">
                                    Memudahkan peserta langsung membuka navigasi Google Maps menuju lokasi acara.
                                </p>
                                <InputError :message="form.errors.venue_maps_url" />
                            </div>

                            <div class="sm:col-span-2">
                                <RichTextEditor
                                    v-model="form.short_description"
                                    :label="isCoachingClinic
                                        ? 'Silabus, Materi Pelatihan &amp; Kurikulum'
                                        : (isAudition ? 'Petunjuk Teknis &amp; Tahapan Seleksi' : 'Deskripsi &amp; Informasi Kegiatan')"
                                    :placeholder="isCoachingClinic
                                        ? 'Uraikan kurikulum pelatihan, profil instruktur/pelatih, sesi teori & praktik, serta perlengkapan yang wajib dibawa peserta...'
                                        : (isAudition
                                            ? 'Uraikan jadwal sesi, materi tes (antropometri, agility, mini games), pakaian yang wajib dibawa, dan kriteria penilaian...'
                                            : 'Uraian lengkap mengenai kejuaraan, peserta yang disasar, babak turnamen, dan informasi teknis...')"
                                    :error="form.errors.short_description"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: KEBIJAKAN KUOTA & PENGATURAN BIAYA -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200 flex items-center gap-2">
                            <span>2.</span>
                            <span>Kebijakan Kuota Pendaftaran &amp; Biaya</span>
                        </h2>
                        
                        <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200 mb-4">
                            <label class="block text-xs font-bold text-slate-800 mb-2">Model Pembatasan Pendaftaran</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                    :class="form.quota_type === 'fixed' ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                >
                                    <input
                                        v-model="form.quota_type"
                                        type="radio"
                                        value="fixed"
                                        class="mt-0.5 text-orange-600 focus:ring-orange-500"
                                    />
                                    <div class="text-xs">
                                        <span class="font-bold block text-slate-900">Kuota Terbatas (Fixed Quota)</span>
                                        <span class="text-slate-500 text-[11px] block mt-0.5">
                                            {{ isIndividual ? 'Dibatasi kuota pasti (misal: 50 atau 150 peserta). Pendaftaran otomatis ditutup saat penuh.' : 'Dibatasi angka kuota pasti (misal: 16 atau 32 tim). Pendaftaran tutup saat slot penuh.' }}
                                        </span>
                                    </div>
                                </label>
                                <label
                                    class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                    :class="form.quota_type === 'unlimited' ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                >
                                    <input
                                        v-model="form.quota_type"
                                        type="radio"
                                        value="unlimited"
                                        class="mt-0.5 text-orange-600 focus:ring-orange-500"
                                    />
                                    <div class="text-xs">
                                        <span class="font-bold block text-slate-900">Terbuka s.d. Batas Waktu (Open Deadline)</span>
                                        <span class="text-slate-500 text-[11px] block mt-0.5">
                                            Tanpa batasan kuota awal. Pendaftaran terus dibuka dan otomatis terkunci tepat pada tanggal tutup pendaftaran.
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- Slot Tim (Hanya untuk Tim Beregu) -->
                            <div v-if="form.quota_type === 'fixed' && !isIndividual" data-field="max_teams">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Kuota Maksimal Tim (Slot Keras)
                                </label>
                                <input
                                    v-model="form.max_teams"
                                    type="number"
                                    class="input-field tabular-data"
                                    :class="{ 'input-field-error': form.errors.max_teams }"
                                    @input="form.clearErrors('max_teams')"
                                />
                                <p v-if="isEdit" class="text-[10px] text-slate-500 mt-0.5">
                                    Slot terisi saat ini: <strong class="tabular-data text-orange-950 font-bold">{{ event.occupied_slots }} tim</strong>.
                                </p>
                                <InputError :message="form.errors.max_teams" />
                            </div>

                            <!-- Kuota Peserta (Hanya untuk Individu) -->
                            <div v-else-if="form.quota_type === 'fixed' && isIndividual" data-field="max_participants">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Kuota Maksimal Peserta (Slot)
                                </label>
                                <input
                                    v-model="form.max_participants"
                                    type="number"
                                    class="input-field tabular-data"
                                    :class="{ 'input-field-error': form.errors.max_participants }"
                                    @input="form.clearErrors('max_participants')"
                                />
                                <p class="text-[10px] text-slate-500 mt-0.5">
                                    {{ isCoachingClinic ? 'Contoh: 50 peserta workshop' : 'Contoh: 100 atlet seleksi' }}
                                </p>
                                <InputError :message="form.errors.max_participants" />
                            </div>

                            <div v-else class="sm:col-span-1 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs flex items-center">
                                <span>Kuota terbuka tanpa batas awal. Akses pendaftaran otomatis dikunci saat batas waktu penutupan tiba.</span>
                            </div>

                            <div data-field="registration_fee">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    {{ isIndividual ? 'Biaya Pendaftaran per Peserta (Rp)' : 'Biaya Pendaftaran per Tim (Rp)' }}
                                </label>
                                <input
                                    v-model="form.registration_fee"
                                    type="number"
                                    step="10000"
                                    placeholder="0"
                                    class="input-field tabular-data"
                                />
                                <p class="text-[10px] text-slate-500 mt-0.5">Isi 0 jika pendaftaran gratis (free trial / beasiswa).</p>
                                <InputError :message="form.errors.registration_fee" />
                            </div>

                            <div data-field="payment_deadline_hours">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Batas Reservasi Bayar (Jam)</label>
                                <input
                                    v-model="form.payment_deadline_hours"
                                    type="number"
                                    class="input-field tabular-data"
                                />
                                <p class="text-[10px] text-slate-500 mt-0.5">Slot dilepas otomatis jika unpaid lewat durasi ini.</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    {{ isCoachingClinic
                                        ? 'Fasilitas &amp; Paket Peserta'
                                        : (isAudition ? 'Informasi Beasiswa / Benefit Kelolosan' : 'Informasi Hadiah / Trofi') }}
                                </label>
                                <input
                                    v-model="form.prize_info"
                                    type="text"
                                    :placeholder="isCoachingClinic
                                        ? 'Contoh: Jersey Latihan Resmi, Bola, Sertifikat, Konsumsi, Sesi Pelatih Berlisensi'
                                        : (isAudition ? 'Contoh: Beasiswa Penuh Akademi + Kontrak EPA Persija U-16' : 'Total Hadiah Rp 50.000.000 + Trofi Bergilir')"
                                    class="input-field"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Grace Period Bukti Ditolak (Jam)</label>
                                <input
                                    v-model="form.reupload_grace_hours"
                                    type="number"
                                    class="input-field tabular-data"
                                />
                            </div>

                            <!-- Lock Roster (HANYA UNTUK TIM BEREGU) -->
                            <div v-if="!isIndividual" class="sm:col-span-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-700">
                                    <input
                                        v-model="form.lock_roster_until_paid"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                    />
                                    <span>Kunci menu input pemain hingga tim menyelesaikan pembayaran pendaftaran (Status Lunas)</span>
                                </label>
                            </div>

                            <!-- Alokasi Batas Kuota per Posisi (Khusus Event Individu / Audisi Kuota Terbatas) -->
                            <div v-if="isIndividual && form.quota_type === 'fixed'" class="sm:col-span-3 p-4 rounded-xl bg-orange-50/40 border border-orange-200/80">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-orange-200/60">
                                    <div>
                                        <h3 class="text-xs font-bold text-orange-950 flex items-center gap-1.5">
                                            <span>Alokasi Batas Kuota per Posisi (Opsional)</span>
                                            <span class="text-[10px] font-semibold text-orange-700 bg-orange-100 px-2 py-0.5 rounded border border-orange-200">
                                                Scouting Quota
                                            </span>
                                        </h3>
                                        <p class="text-[11px] text-slate-600 mt-0.5">
                                            Batasi kuota maksimal atlet per posisi (GK, CB, FB, MF, FW) agar komposisi seleksi berimbang. Kosongkan jika kuota bebas tanpa batasan posisi.
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <span
                                            class="text-xs font-bold font-mono"
                                            :class="totalPositionCaps > Number(form.max_participants || 0) ? 'text-rose-700' : 'text-orange-950'"
                                        >
                                            Total Alokasi: {{ totalPositionCaps }} / {{ form.max_participants || 0 }} Atlet
                                        </span>
                                        <button
                                            v-if="totalPositionCaps > 0"
                                            type="button"
                                            class="text-[11px] font-semibold text-rose-700 hover:text-rose-900 underline"
                                            @click="resetPositionCaps"
                                        >
                                            Reset Bebas
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-2 sm:grid-cols-5 gap-3">
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 shadow-2xs">
                                        <label class="block text-xs font-bold text-slate-900 mb-1">
                                            GK <span class="font-normal text-slate-500 text-[11px]">(Kiper)</span>
                                        </label>
                                        <input
                                            v-model.number="form.position_quota_caps.GK"
                                            type="number"
                                            min="0"
                                            placeholder="Bebas"
                                            class="input-field tabular-data text-xs"
                                        />
                                    </div>
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 shadow-2xs">
                                        <label class="block text-xs font-bold text-slate-900 mb-1">
                                            CB <span class="font-normal text-slate-500 text-[11px]">(Bek Tengah)</span>
                                        </label>
                                        <input
                                            v-model.number="form.position_quota_caps.CB"
                                            type="number"
                                            min="0"
                                            placeholder="Bebas"
                                            class="input-field tabular-data text-xs"
                                        />
                                    </div>
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 shadow-2xs">
                                        <label class="block text-xs font-bold text-slate-900 mb-1">
                                            FB <span class="font-normal text-slate-500 text-[11px]">(Bek Sayap)</span>
                                        </label>
                                        <input
                                            v-model.number="form.position_quota_caps.FB"
                                            type="number"
                                            min="0"
                                            placeholder="Bebas"
                                            class="input-field tabular-data text-xs"
                                        />
                                    </div>
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 shadow-2xs">
                                        <label class="block text-xs font-bold text-slate-900 mb-1">
                                            MF <span class="font-normal text-slate-500 text-[11px]">(Gelandang)</span>
                                        </label>
                                        <input
                                            v-model.number="form.position_quota_caps.MF"
                                            type="number"
                                            min="0"
                                            placeholder="Bebas"
                                            class="input-field tabular-data text-xs"
                                        />
                                    </div>
                                    <div class="p-2.5 rounded-lg bg-white border border-slate-200 shadow-2xs">
                                        <label class="block text-xs font-bold text-slate-900 mb-1">
                                            FW <span class="font-normal text-slate-500 text-[11px]">(Penyerang)</span>
                                        </label>
                                        <input
                                            v-model.number="form.position_quota_caps.FW"
                                            type="number"
                                            min="0"
                                            placeholder="Bebas"
                                            class="input-field tabular-data text-xs"
                                        />
                                    </div>
                                </div>
                                <p v-if="totalPositionCaps > Number(form.max_participants || 0)" class="text-[11px] text-rose-600 font-semibold mt-2">
                                    Perhatian: Total batas kuota posisi ({{ totalPositionCaps }}) melebihi kuota total peserta ({{ form.max_participants || 0 }}).
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: KELAYAKAN USIA & DOKUMEN SCREENING -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200 flex items-center gap-2">
                            <span>3.</span>
                            <span>{{ isIndividual ? 'Kelayakan Usia &amp; Dokumen Verifikasi Wajib' : 'Regulasi Kelayakan Usia Pemain &amp; Dokumen Skuad' }}</span>
                        </h2>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div data-field="age_category">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kategori Usia</label>
                                <input
                                    v-model="form.age_category"
                                    type="text"
                                    placeholder="Contoh: U-12 / U-15 / Umum"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.age_category }"
                                    @input="form.clearErrors('age_category')"
                                />
                                <InputError :message="form.errors.age_category" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Batas Minimal Tanggal Lahir</label>
                                <input v-model="form.min_birth_date" type="date" class="input-field tabular-data" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Batas Maksimal Tanggal Lahir</label>
                                <input v-model="form.max_birth_date" type="date" class="input-field tabular-data" />
                            </div>

                            <!-- PENGATURAN SKUAD TIM (HANYA DITAMPILKAN PADA EVENT BEREGU) -->
                            <template v-if="!isIndividual">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Min. Pemain per Tim</label>
                                    <input v-model="form.min_players_per_team" type="number" class="input-field tabular-data" />
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Maks. Pemain per Tim</label>
                                    <input v-model="form.max_players_per_team" type="number" class="input-field tabular-data" />
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Maks. Ofisial per Tim</label>
                                    <input v-model="form.max_officials_per_team" type="number" class="input-field tabular-data" />
                                </div>

                                <!-- Pengaturan Syarat Minimal Kiper & Lisensi Pelatih -->
                                <div class="sm:col-span-3 p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-4">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                        <div data-field="min_gk_per_team">
                                            <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                                Syarat Minimal Penjaga Gawang (GK) per Tim
                                            </label>
                                            <div class="grid grid-cols-2 gap-2.5 text-xs">
                                                <label
                                                    class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors"
                                                    :class="Number(form.min_gk_per_team) === 1 ? 'border-orange-500 bg-orange-50 text-orange-950 font-bold' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                                >
                                                    <input
                                                        v-model="form.min_gk_per_team"
                                                        type="radio"
                                                        :value="1"
                                                        class="text-orange-600 focus:ring-orange-500"
                                                    />
                                                    <span>Minimal 1 GK</span>
                                                </label>
                                                <label
                                                    class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors"
                                                    :class="Number(form.min_gk_per_team) === 2 ? 'border-orange-500 bg-orange-50 text-orange-950 font-bold' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                                >
                                                    <input
                                                        v-model="form.min_gk_per_team"
                                                        type="radio"
                                                        :value="2"
                                                        class="text-orange-600 focus:ring-orange-500"
                                                    />
                                                    <span>Minimal 2 GK</span>
                                                </label>
                                            </div>
                                            <p class="text-[11px] text-slate-500 mt-1">
                                                Menentukan jumlah minimal kiper yang wajib lolos screening agar tim dapat mengunci skuad final.
                                            </p>
                                            <InputError :message="form.errors.min_gk_per_team" />
                                        </div>

                                        <div class="space-y-3">
                                            <label class="block text-xs font-bold text-slate-800">
                                                Pengaturan Input Lisensi Pelatih Kepala (Head Coach)
                                            </label>
                                            <label class="flex items-start gap-2.5 cursor-pointer text-xs font-medium text-slate-700 p-2.5 rounded-lg border border-slate-200 bg-white">
                                                <input
                                                    v-model="form.show_coach_license"
                                                    type="checkbox"
                                                    class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                                    @change="!form.show_coach_license && (form.require_coach_license = false)"
                                                />
                                                <div>
                                                    <span class="font-bold text-slate-900 block">Munculkan Kolom Lisensi Pelatih pada Pendaftaran Tim</span>
                                                    <span class="text-[11px] text-slate-500 block mt-0.5">
                                                        Jika tidak dicentang, kolom lisensi kepelatihan akan disembunyikan pada form pendaftaran tim dan ofisial.
                                                    </span>
                                                </div>
                                            </label>

                                            <div v-if="form.show_coach_license" class="pl-3 border-l-2 border-orange-300 space-y-1.5">
                                                <span class="block text-[11px] font-bold text-slate-700">Status Kewajiban Pengisian Lisensi Pelatih:</span>
                                                <div class="grid grid-cols-2 gap-2 text-xs">
                                                    <label
                                                        class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors"
                                                        :class="!form.require_coach_license ? 'border-orange-500 bg-orange-50 text-orange-950 font-bold' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                                    >
                                                        <input
                                                            v-model="form.require_coach_license"
                                                            type="radio"
                                                            :value="false"
                                                            class="text-orange-600 focus:ring-orange-500"
                                                        />
                                                        <span>Opsional</span>
                                                    </label>
                                                    <label
                                                        class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors"
                                                        :class="form.require_coach_license ? 'border-orange-500 bg-orange-50 text-orange-950 font-bold' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                                    >
                                                        <input
                                                            v-model="form.require_coach_license"
                                                            type="radio"
                                                            :value="true"
                                                            class="text-orange-600 focus:ring-orange-500"
                                                        />
                                                        <span>Wajib Diisi</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Checkbox Dokumen Wajib Screening (Berlaku untuk Tim & Individu) -->
                            <div data-field="required_documents" class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 mb-2">
                                    {{ isIndividual ? 'Dokumen Screening Wajib Diunggah Peserta:' : 'Dokumen Wajib Diunggah per Pemain saat Screening Tim:' }}
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                    <label
                                        v-for="(label, docKey) in documentTypes"
                                        :key="docKey"
                                        class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 bg-slate-50 cursor-pointer hover:bg-slate-100 transition-colors"
                                    >
                                        <input
                                            v-model="form.required_documents"
                                            type="checkbox"
                                            :value="docKey"
                                            class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                            @change="form.clearErrors('required_documents')"
                                        />
                                        <span>{{ label }}</span>
                                    </label>
                                </div>
                                <InputError :message="form.errors.required_documents" />
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: KUSTOMISASI FORMULIR PENDAFTARAN PESERTA (FORM PRESETS) -->
                    <div :class="isIndividual ? 'p-5 rounded-xl bg-slate-50 border border-slate-200' : ''">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                <span>4.</span>
                                <span>Kustomisasi Kolom Formulir Peserta (Form Presets)</span>
                            </h2>
                            <span v-if="isIndividual" class="text-[10px] font-semibold text-slate-700 bg-slate-200 px-2 py-0.5 rounded uppercase tracking-wider">
                                Profil Peserta
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5">
                            Pilih kolom profil apa saja yang wajib diisi oleh pendaftar. Data ini akan ditampilkan pada lembar presensi, Digital Pass (E-Tiket), atau berkas kegiatan.
                        </p>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors"
                                :class="form.form_presets.photo ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            >
                                <input
                                    v-model="form.form_presets.photo"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                />
                                <div class="text-xs">
                                    <span class="font-bold block">Unggah Pas Foto Peserta</span>
                                    <span class="text-slate-500 text-[11px] block mt-0.5">Wajibkan unggah pas foto wajah atlet/peserta untuk verifikasi &amp; kartu peserta.</span>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors"
                                :class="form.form_presets.position ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            >
                                <input
                                    v-model="form.form_presets.position"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                />
                                <div class="text-xs">
                                    <span class="font-bold block">Posisi Lapangan</span>
                                    <span class="text-slate-500 text-[11px] block mt-0.5">Pemilihan posisi utama (Kiper, Bek, Gelandang, Penyerang) &amp; posisi sekunder.</span>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors"
                                :class="form.form_presets.foot_dominance ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            >
                                <input
                                    v-model="form.form_presets.foot_dominance"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                />
                                <div class="text-xs">
                                    <span class="font-bold block">Kaki Dominan</span>
                                    <span class="text-slate-500 text-[11px] block mt-0.5">Pengisian kaki dominan (Kanan, Kiri, atau Kedua Kaki).</span>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors"
                                :class="form.form_presets.team_origin ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            >
                                <input
                                    v-model="form.form_presets.team_origin"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                />
                                <div class="text-xs">
                                    <span class="font-bold block">Asal SSB / Klub / Sekolah</span>
                                    <span class="text-slate-500 text-[11px] block mt-0.5">Pengisian nama klub pembinaan atau sekolah asal peserta.</span>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors"
                                :class="form.form_presets.body_measurements ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            >
                                <input
                                    v-model="form.form_presets.body_measurements"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                />
                                <div class="text-xs">
                                    <span class="font-bold block">Antropometri Fisik</span>
                                    <span class="text-slate-500 text-[11px] block mt-0.5">Kolom Tinggi Badan (cm) dan Berat Badan (kg).</span>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors"
                                :class="form.form_presets.experience ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            >
                                <input
                                    v-model="form.form_presets.experience"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                />
                                <div class="text-xs">
                                    <span class="font-bold block">Riwayat Prestasi / Pengalaman</span>
                                    <span class="text-slate-500 text-[11px] block mt-0.5">Kolom riwayat turnamen atau pelatihan yang pernah diikuti sebelumnya.</span>
                                </div>
                            </label>

                            <label
                                class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-colors"
                                :class="form.form_presets.jersey_size ? 'border-orange-500 bg-orange-50/50 text-orange-950 font-medium' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                            >
                                <input
                                    v-model="form.form_presets.jersey_size"
                                    type="checkbox"
                                    class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                />
                                <div class="text-xs">
                                    <span class="font-bold block">Ukuran Kaos / Rompi</span>
                                    <span class="text-slate-500 text-[11px] block mt-0.5">Pilihan ukuran baju (S/M/L/XL/XXL) jika panitia membagikan perlengkapan.</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 p-5 rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-xs font-bold text-slate-900">Pertanyaan Kustom</h3>
                                <p class="text-[11px] text-slate-500 mt-1">Kolom ini akan muncul persis di form publik event individual.</p>
                            </div>
                            <button type="button" class="btn-secondary text-xs" @click="addCustomField">Tambah Kolom</button>
                        </div>
                        <div v-if="form.custom_fields_schema.length" class="mt-4 space-y-3">
                            <div
                                v-for="(field, index) in form.custom_fields_schema"
                                :key="field.key || index"
                                class="grid grid-cols-1 md:grid-cols-12 gap-2 p-3 rounded-lg bg-slate-50 border border-slate-200"
                            >
                                <input v-model="field.key" type="text" class="input-field md:col-span-2" placeholder="Kunci unik" />
                                <input v-model="field.label" type="text" class="input-field md:col-span-3" placeholder="Label pertanyaan" />
                                <select v-model="field.type" class="input-field md:col-span-2">
                                    <option v-for="type in customFieldTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                                <input
                                    v-if="field.type === 'select' || field.type === 'radio'"
                                    :value="Array.isArray(field.options) ? field.options.join(', ') : field.options"
                                    type="text"
                                    class="input-field md:col-span-3"
                                    placeholder="Pilihan dipisah koma"
                                    @input="field.options = $event.target.value"
                                    @blur="normalizeCustomOptions(field)"
                                />
                                <label class="flex items-center gap-1 text-[11px] text-slate-700 md:col-span-1">
                                    <input v-model="field.required" type="checkbox" class="rounded border-slate-300 text-orange-600" />
                                    Wajib
                                </label>
                                <button type="button" class="text-rose-700 text-xs font-semibold md:col-span-1" @click="removeCustomField(index)">Hapus</button>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 5: JADWAL PELAKSANAAN & BERKAS RESMI -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200 flex items-center gap-2">
                            <span>5.</span>
                            <span>Jadwal Pelaksanaan &amp; Berkas Regulasi</span>
                        </h2>

                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pendaftaran Dibuka</label>
                                <input v-model="form.registration_open_at" type="datetime-local" class="input-field tabular-data" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pendaftaran Ditutup</label>
                                <input v-model="form.registration_close_at" type="datetime-local" class="input-field tabular-data" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    {{ isCoachingClinic
                                        ? 'Tanggal Mulai Pelatihan'
                                        : (isAudition ? 'Tanggal Audisi Hari ke-1' : (isIndividual ? 'Tanggal Kegiatan Dimulai' : 'Pertandingan Dimulai (Kick-Off)')) }}
                                </label>
                                <input v-model="form.match_start_date" type="date" class="input-field tabular-data" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    {{ isCoachingClinic
                                        ? 'Tanggal Selesai Pelatihan'
                                        : (isAudition ? 'Tanggal Audisi Selesai (Final Day)' : (isIndividual ? 'Tanggal Kegiatan Selesai' : 'Pertandingan Selesai')) }}
                                </label>
                                <input v-model="form.match_end_date" type="date" class="input-field tabular-data" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kontak PIC Panitia</label>
                                <input v-model="form.contact_person" type="text" placeholder="Sekretariat Panitia Pelaksana" class="input-field" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">WhatsApp PIC Panitia</label>
                                <input v-model="form.contact_whatsapp" type="text" placeholder="0812xxxxxxxx" class="input-field tabular-data" />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                    Poster / Banner Kegiatan (Format JPG/PNG/WebP, Rasio 16:9)
                                </label>
                                <FileInput
                                    v-model="form.banner_file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    :preview-url="bannerPreview"
                                    label="Pilih Banner Kegiatan"
                                    hint="Gambar berukuran besar dikompresi otomatis sebelum diunggah."
                                    :error="form.errors.banner_file"
                                    @change="handleBannerFile"
                                />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                    {{ isCoachingClinic
                                        ? 'Berkas PDF Silabus &amp; Panduan Kegiatan (Maks. 5 MB)'
                                        : (isAudition ? 'Berkas PDF SOP &amp; Petunjuk Teknis Seleksi (Maks. 5 MB)' : 'Berkas PDF Regulasi Teknis Kompetisi (Maks. 5 MB)') }}
                                </label>
                                <FileInput
                                    v-model="form.regulation_pdf"
                                    accept=".pdf"
                                    :preview-url="event?.regulation_pdf_url"
                                    label="Pilih Berkas PDF"
                                    hint="Format berkas: Dokumen PDF resmi (Maksimal 5 MB)"
                                    :error="form.errors.regulation_pdf"
                                    @change="handlePdfFile"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                        <Link :href="route('admin.events.index')" class="btn-secondary">Batal</Link>
                        <button type="submit" class="btn-pitch" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Perbarui Pengaturan Event' : 'Simpan &amp; Publikasikan Event') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
