<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    participants: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    counts: {
        type: Object,
        default: () => ({}),
    },
});

const filterState = reactive({
    event_id: props.filters.event_id || '',
    status: props.filters.status || '',
    search: props.filters.search || '',
});

const applyFilters = () => {
    router.get(
        route('admin.screening.auditions'),
        {
            event_id: filterState.event_id || undefined,
            status: filterState.status || undefined,
            search: filterState.search || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const setStatusFilter = (status) => {
    filterState.status = status;
    applyFilters();
};

// Modal Keputusan Screening
const selectedParticipant = ref(null);
const isApproveModalOpen = ref(false);
const isRejectModalOpen = ref(false);

const approveForm = useForm({
    status: 'passed',
});

const rejectForm = useForm({
    status: 'rejected',
    notes: '',
});

const openApproveModal = (p) => {
    selectedParticipant.value = p;
    isApproveModalOpen.value = true;
};

const openRejectModal = (p) => {
    selectedParticipant.value = p;
    rejectForm.notes = p.screening_notes || '';
    isRejectModalOpen.value = true;
};

const submitApprove = () => {
    if (!selectedParticipant.value) return;
    approveForm.post(route('admin.screening.auditions.verify', { participant: selectedParticipant.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isApproveModalOpen.value = false;
            selectedParticipant.value = null;
        },
    });
};

const submitReject = () => {
    if (!selectedParticipant.value) return;
    rejectForm.post(route('admin.screening.auditions.verify', { participant: selectedParticipant.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isRejectModalOpen.value = false;
            selectedParticipant.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout page-title="Verifikasi Screening Atlet Audisi">
        <Head title="Verifikasi Screening Audisi" />

        <div class="space-y-6">
            <!-- Header & Switcher Tab Turnamen vs Audisi -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <span>Screening Keabsahan Atlet</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Pemeriksaan berkas resmi Akta Lahir, Kartu Keluarga, dan aktivasi Digital Pass (E-Tiket) atlet.
                    </p>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200 self-start sm:self-auto">
                    <Link
                        :href="route('admin.screening.index')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Tim Turnamen</span>
                    </Link>
                    <Link
                        :href="route('admin.screening.auditions')"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white text-orange-600 shadow-xs inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Peserta Perorangan</span>
                    </Link>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="frosted-card p-4">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Atlet</span>
                    <p class="text-2xl font-black text-slate-900 mt-1 tabular-data">{{ counts.total || 0 }}</p>
                </div>
                <div
                    class="frosted-card p-4 cursor-pointer hover:border-blue-400 transition-colors"
                    :class="{ 'ring-2 ring-blue-500': filterState.status === 'submitted' }"
                    @click="setStatusFilter('submitted')"
                >
                    <span class="text-[11px] font-bold uppercase tracking-wider text-blue-600">Menunggu Review</span>
                    <p class="text-2xl font-black text-blue-700 mt-1 tabular-data">{{ counts.submitted || 0 }}</p>
                </div>
                <div
                    class="frosted-card p-4 cursor-pointer hover:border-emerald-400 transition-colors"
                    :class="{ 'ring-2 ring-emerald-500': filterState.status === 'passed' }"
                    @click="setStatusFilter('passed')"
                >
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Lolos (Sah)</span>
                    <p class="text-2xl font-black text-emerald-700 mt-1 tabular-data">{{ counts.passed || 0 }}</p>
                </div>
                <div
                    class="frosted-card p-4 cursor-pointer hover:border-rose-400 transition-colors"
                    :class="{ 'ring-2 ring-rose-500': filterState.status === 'rejected' }"
                    @click="setStatusFilter('rejected')"
                >
                    <span class="text-[11px] font-bold uppercase tracking-wider text-rose-600">Perlu Revisi</span>
                    <p class="text-2xl font-black text-rose-700 mt-1 tabular-data">{{ counts.rejected || 0 }}</p>
                </div>
            </div>

            <!-- Filters Area -->
            <div class="frosted-card p-4 sm:p-5 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    <!-- Search Input -->
                    <div class="w-full sm:w-64">
                        <input
                            v-model="filterState.search"
                            type="text"
                            placeholder="Cari atlet, NIK, No. Reg..."
                            class="input-field text-xs py-2"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- Event Select -->
                    <select
                        v-model="filterState.event_id"
                        class="input-field text-xs py-2 w-full sm:w-56"
                        @change="applyFilters"
                    >
                        <option value="">Semua Event Audisi</option>
                        <option v-for="evt in events" :key="evt.id" :value="evt.id">
                            {{ evt.name }} ({{ evt.age_category }})
                        </option>
                    </select>

                    <!-- Status Select -->
                    <select
                        v-model="filterState.status"
                        class="input-field text-xs py-2 w-full sm:w-44"
                        @change="applyFilters"
                    >
                        <option value="">Semua Status</option>
                        <option value="submitted">Menunggu Review</option>
                        <option value="passed">Lolos (Sah)</option>
                        <option value="rejected">Perlu Revisi</option>
                    </select>

                    <button
                        type="button"
                        class="btn-pitch text-xs py-2 px-3"
                        @click="applyFilters"
                    >
                        Terapkan
                    </button>
                </div>
            </div>

            <!-- Participants Card List -->
            <div v-if="participants.data.length > 0" class="space-y-4">
                <div
                    v-for="p in participants.data"
                    :key="p.id"
                    class="frosted-card p-5 hover:shadow-md transition-shadow border-slate-200/90"
                >
                    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                        <!-- Left: Photo & Athlete Profile -->
                        <div class="flex items-start gap-4 min-w-0 flex-1">
                            <img
                                v-if="p.photo_url"
                                :src="p.photo_url"
                                :alt="p.full_name"
                                class="w-14 h-14 rounded-2xl object-cover border border-slate-200 shadow-xs shrink-0"
                            />
                            <div
                                v-else
                                class="w-14 h-14 rounded-2xl bg-gradient-to-br from-orange-500 to-red-600 text-white font-black text-base flex items-center justify-center shrink-0 shadow-xs"
                            >
                                {{ p.full_name.substring(0, 2).toUpperCase() }}
                            </div>

                            <div class="min-w-0 space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-base font-black text-slate-900 truncate">
                                        {{ p.full_name }}
                                        <span v-if="p.nickname" class="text-xs font-semibold text-slate-500">({{ p.nickname }})</span>
                                    </h3>
                                    <!-- Bib Badge -->
                                    <span v-if="p.bib_number" class="px-2 py-0.5 rounded-full text-xs font-black bg-orange-600 text-white shadow-2xs">
                                        Bib #{{ p.bib_number }}
                                    </span>
                                    <span v-else class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                        Bib Belum Ada
                                    </span>
                                </div>

                                <p class="text-xs font-semibold text-orange-600">
                                    {{ p.event?.name }} <span class="text-slate-400 font-normal">({{ p.event?.age_category }})</span>
                                    <span v-if="p.session_name" class="text-slate-500 font-normal">• {{ p.session_name }}</span>
                                </p>

                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 pt-0.5">
                                    <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-[11px] font-semibold text-slate-700">NIK: {{ p.nik }}</span>
                                    <span>•</span>
                                    <span>Lahir: <strong>{{ p.birth_date }}</strong></span>
                                    <span>•</span>
                                    <span>Posisi: <strong>{{ p.preferred_position || '-' }}</strong></span>
                                    <span>•</span>
                                    <span>Kaki: <strong class="capitalize">{{ p.dominant_foot || '-' }}</strong></span>
                                    <span>•</span>
                                    <span>Asal: <strong>{{ p.current_club_ssb || 'Independen' }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- Center: Document Links -->
                        <div class="flex items-center gap-2 shrink-0 flex-wrap">
                            <a
                                :href="p.birth_cert_url"
                                target="_blank"
                                class="btn-secondary text-xs py-1.5 px-2.5 inline-flex items-center gap-1.5 border-slate-300"
                            >
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Akta Lahir ↗</span>
                            </a>

                            <a
                                :href="p.family_card_url"
                                target="_blank"
                                class="btn-secondary text-xs py-1.5 px-2.5 inline-flex items-center gap-1.5 border-slate-300"
                            >
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span>KK ↗</span>
                            </a>

                            <a
                                v-if="p.additional_doc_url"
                                :href="p.additional_doc_url"
                                target="_blank"
                                class="btn-secondary text-xs py-1.5 px-2.5 inline-flex items-center gap-1.5 border-slate-300"
                            >
                                <span>Dok. Tambahan ↗</span>
                            </a>
                        </div>

                        <!-- Right: Decision Actions & Status -->
                        <div class="flex items-center gap-2 shrink-0">
                            <span
                                class="px-2.5 py-1 rounded-xl text-xs font-bold border"
                                :class="
                                    p.screening_status === 'passed'
                                        ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                        : p.screening_status === 'rejected'
                                          ? 'bg-rose-50 text-rose-800 border-rose-300'
                                          : 'bg-blue-50 text-blue-800 border-blue-300'
                                "
                            >
                                {{ p.screening_status_label }}
                            </span>

                            <button
                                type="button"
                                class="btn-pitch text-xs py-1.5 px-3 bg-emerald-600 hover:bg-emerald-700 border-emerald-600 shadow-2xs inline-flex items-center gap-1.5"
                                @click="openApproveModal(p)"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Sah (Lolos)</span>
                            </button>

                            <button
                                type="button"
                                class="btn-secondary text-xs py-1.5 px-3 text-rose-700 hover:bg-rose-50 border-rose-200 inline-flex items-center gap-1.5"
                                @click="openRejectModal(p)"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>Tolak Revisi</span>
                            </button>
                        </div>
                    </div>

                    <!-- Rejection Reason Warning -->
                    <div v-if="p.screening_status === 'rejected' && p.screening_notes" class="mt-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                        <strong class="font-bold">Alasan Penolakan / Catatan Petugas:</strong>
                        <p class="mt-0.5">{{ p.screening_notes }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="frosted-card p-12 text-center text-slate-500 text-sm">
                Tidak ada data peserta audisi yang cocok dengan kriteria filter saat ini.
            </div>
        </div>

        <!-- MODAL APPROVE -->
        <div v-if="isApproveModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="frosted-card p-6 max-w-md w-full bg-white shadow-2xl space-y-4">
                <h3 class="text-base font-bold text-slate-900">Konfirmasi Pengesahan Berkas</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Apakah Anda yakin ingin menyatakan berkas keabsahan atlet <strong>{{ selectedParticipant?.full_name }}</strong> sebagai <strong>SAH (LOLOS)</strong>?
                    Sistem akan otomatis mengaktifkan Digital Pass (E-Tiket), alokasi rompi, dan kode verifikasi keabsahan resmi.
                </p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary text-xs" @click="isApproveModalOpen = false">Batal</button>
                    <button type="button" class="btn-pitch text-xs bg-emerald-600 hover:bg-emerald-700" :disabled="approveForm.processing" @click="submitApprove">
                        {{ approveForm.processing ? 'Memproses...' : 'Ya, Sahkan Atlet' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL REJECT -->
        <div v-if="isRejectModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="frosted-card p-6 max-w-md w-full bg-white shadow-2xl space-y-4">
                <h3 class="text-base font-bold text-rose-900">Tolak Berkas (Wajib Revisi)</h3>
                <p class="text-xs text-slate-600">
                    Tuliskan alasan penolakan secara spesifik agar orang tua atlet dapat mengunggah perbaikan dokumen yang benar (min. 10 karakter).
                </p>
                <div>
                    <textarea
                        v-model="rejectForm.notes"
                        rows="3"
                        placeholder="Contoh: Foto Akta Kelahiran buram / NIK pada KK tidak terbaca jelas..."
                        class="input-field text-xs"
                    ></textarea>
                    <p v-if="rejectForm.errors.notes" class="text-xs text-rose-600 mt-1">{{ rejectForm.errors.notes }}</p>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary text-xs" @click="isRejectModalOpen = false">Batal</button>
                    <button type="button" class="btn-pitch text-xs bg-rose-600 hover:bg-rose-700" :disabled="rejectForm.processing" @click="submitReject">
                        {{ rejectForm.processing ? 'Memproses...' : 'Kirim Catatan Revisi' }}
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

