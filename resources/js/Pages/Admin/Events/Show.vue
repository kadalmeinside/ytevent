<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    positionBreakdown: {
        type: Array,
        default: () => [],
    },
    sessions: {
        type: Array,
        default: () => [],
    },
    stages: {
        type: Array,
        default: () => [],
    },
    participants: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah, formatDate } = useFormatters();

const isIndividual = computed(() => props.event.participant_type === 'individual');
const isAudition = computed(() => props.event.event_type === 'audition');

const activeTab = ref('overview'); // 'overview', 'participants', 'operations'
const searchQuery = ref('');
const statusFilter = ref('all');
const syncing = ref(false);

const triggerResync = () => {
    if (syncing.value) return;
    syncing.value = true;
    router.post(route('admin.events.resync-event-slots', { event: props.event.id }), {}, {
        preserveScroll: true,
        onFinish: () => {
            syncing.value = false;
        },
    });
};

const filteredParticipants = computed(() => {
    let list = props.participants;
    if (statusFilter.value !== 'all') {
        list = list.filter((p) => p.screening_status === statusFilter.value);
    }
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter((p) =>
            (p.full_name || '').toLowerCase().includes(q) ||
            (p.registration_number || '').toLowerCase().includes(q) ||
            (p.nik || '').toLowerCase().includes(q) ||
            (p.current_club_ssb || '').toLowerCase().includes(q)
        );
    }
    return list;
});

const filteredTeams = computed(() => {
    let list = props.teams;
    if (statusFilter.value !== 'all') {
        if (statusFilter.value === 'all_passed' || statusFilter.value === 'in_review' || statusFilter.value === 'partially_rejected') {
            list = list.filter((t) => t.screening_status === statusFilter.value);
        } else {
            list = list.filter((t) => t.status === statusFilter.value);
        }
    }
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter((t) =>
            (t.name || '').toLowerCase().includes(q) ||
            (t.manager_name || '').toLowerCase().includes(q) ||
            (t.origin_association || '').toLowerCase().includes(q)
        );
    }
    return list;
});

const quotaCapacity = computed(() => {
    return isIndividual.value ? (props.event.max_participants || 100) : (props.event.max_teams || 32);
});

const quotaPercent = computed(() => {
    if (props.event.quota_type === 'unlimited') return 100;
    const max = quotaCapacity.value;
    if (!max) return 0;
    return Math.min(100, Math.round((props.event.occupied_slots / max) * 100));
});

const screeningBadgeClass = (status) => {
    switch (status) {
        case 'passed':
        case 'all_passed':
            return 'bg-emerald-50 text-emerald-800 border-emerald-300';
        case 'rejected':
        case 'partially_rejected':
            return 'bg-rose-50 text-rose-800 border-rose-300';
        case 'submitted':
        case 'in_review':
            return 'bg-amber-50 text-amber-900 border-amber-300';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-300';
    }
};
</script>

<template>
    <AdminLayout
        :page-title="event.name"
        :subtitle="`Workspace manajemen event ${event.event_type_label} (${event.age_category}).`"
    >
        <Head :title="`Kelola Event - ${event.name}`" />

        <div class="space-y-6">
            <!-- Breadcrumb Navigation -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-1.5 text-slate-500">
                    <Link :href="route('admin.events.index')" class="hover:text-orange-600 font-semibold inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Daftar Event</span>
                    </Link>
                    <span>/</span>
                    <span class="font-bold text-slate-900 truncate max-w-xs sm:max-w-md">{{ event.name }}</span>
                </div>
            </div>

            <!-- Event Workspace Header Card -->
            <div class="frosted-card p-5 sm:p-6 bg-white space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div class="flex items-start gap-3.5 min-w-0">
                        <div
                            class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 border"
                            :class="isIndividual ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-slate-100 text-slate-800 border-slate-200'"
                        >
                            <svg v-if="isIndividual" class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <svg v-else class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>

                        <div class="min-w-0 space-y-1.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">{{ event.name }}</h2>
                                <StatusBadge :status="event.status" />
                                <span
                                    class="px-2 py-0.5 text-[11px] font-bold rounded-lg border"
                                    :class="isIndividual ? 'bg-orange-50 text-orange-900 border-orange-200' : 'bg-slate-100 text-slate-800 border-slate-200'"
                                >
                                    {{ isIndividual ? (event.event_type === 'coaching_clinic' ? 'Coaching Clinic' : 'Audisi Atlet') : 'Turnamen Tim' }}
                                    &middot; {{ event.age_category }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                                <span>
                                    Lokasi: <strong class="text-slate-900">{{ event.venue_name }}</strong> ({{ event.city }})
                                </span>
                                <span>
                                    Biaya: <strong class="text-slate-900 tabular-data">{{ formatRupiah(event.registration_fee) }}</strong>
                                </span>
                                <span>
                                    Pelaksanaan: <strong class="text-slate-900 tabular-data">{{ formatDate(event.match_start_date) }}</strong>
                                </span>
                                <span v-if="event.min_birth_date || event.max_birth_date">
                                    Batas Lahir: <strong class="tabular-data text-slate-900">{{ event.min_birth_date || 'Bebas' }} s/d {{ event.max_birth_date || 'Bebas' }}</strong>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Workspace Quick Actions -->
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <Link
                            :href="route('events.show', { event: event.slug })"
                            target="_blank"
                            class="btn-sm-secondary inline-flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            <span>Halaman Publik</span>
                        </Link>

                        <button
                            type="button"
                            class="btn-sm-secondary inline-flex items-center gap-1.5"
                            :class="{ 'opacity-50 cursor-not-allowed': syncing }"
                            :disabled="syncing"
                            title="Cek dan lepaskan slot yang melewati batas waktu pembayaran"
                            @click="triggerResync"
                        >
                            <svg
                                class="w-3.5 h-3.5 text-orange-600"
                                :class="{ 'animate-spin': syncing }"
                                aria-hidden="true"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Sync Kuota</span>
                        </button>

                        <Link
                            :href="route('admin.events.edit', { event: event.id })"
                            class="btn-sm-pitch inline-flex items-center gap-1.5"
                        >
                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Edit Konfigurasi</span>
                        </Link>
                    </div>
                </div>

                <!-- Quota Progress Bar -->
                <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <div class="flex-1 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-700">Kapasitas Terisi</span>
                            <span v-if="event.quota_type === 'unlimited'" class="font-bold text-emerald-700 tabular-data">
                                {{ event.occupied_slots }} {{ isIndividual ? 'Peserta' : 'Tim' }} (Tanpa Batas Kuota)
                            </span>
                            <span v-else class="font-bold text-slate-900 tabular-data">
                                {{ event.occupied_slots }} / {{ quotaCapacity }} {{ isIndividual ? 'Peserta' : 'Tim' }} ({{ quotaPercent }}%)
                            </span>
                        </div>
                        <div v-if="event.quota_type !== 'unlimited'" class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-orange-600 rounded-full transition-all duration-300"
                                :style="{ width: `${quotaPercent}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workspace Navigation Tabs -->
            <div role="tablist" aria-label="Menu Workspace Event" class="flex flex-wrap items-center gap-1.5 p-1.5 bg-white rounded-2xl border border-slate-200">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'overview'"
                    class="px-4 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-2"
                    :class="activeTab === 'overview' ? 'bg-orange-600 text-white' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                    @click="activeTab = 'overview'"
                >
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Ringkasan &amp; Kuota</span>
                </button>

                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'participants'"
                    class="px-4 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-2"
                    :class="activeTab === 'participants' ? 'bg-orange-600 text-white' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                    @click="activeTab = 'participants'"
                >
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ isIndividual ? `Peserta & Screening (${participants.length})` : `Tim & Screening (${teams.length})` }}</span>
                </button>

                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === 'operations'"
                    class="px-4 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-bold transition-colors inline-flex items-center gap-2"
                    :class="activeTab === 'operations' ? 'bg-orange-600 text-white' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900'"
                    @click="activeTab = 'operations'"
                >
                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <span>Operasional &amp; Lapangan</span>
                </button>
            </div>

            <!-- TAB 1: OVERVIEW -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <!-- Asymmetric Operational Priority Summary -->
                <div v-if="isIndividual" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    <!-- Focal Card: Antrean Screening -->
                    <div class="frosted-card p-5 lg:col-span-5 border-orange-300 bg-orange-50/40 flex flex-col justify-between gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="text-xs font-bold text-orange-900 block">Antrean Screening Aktif</span>
                                <p class="text-3xl font-black text-slate-900 mt-1 tabular-data">{{ summary.submitted || 0 }}</p>
                                <span class="text-xs text-slate-700 mt-0.5 block">Peserta menunggu pemeriksaan berkas</span>
                            </div>
                            <Link
                                v-if="participants.length > 0"
                                :href="route('admin.screening.auditions.review', { event: event.id })"
                                class="btn-sm-pitch shrink-0"
                            >
                                Mulai Periksa
                            </Link>
                        </div>
                    </div>

                    <!-- Secondary Breakdown Strip -->
                    <div class="frosted-card p-5 lg:col-span-7 grid grid-cols-2 sm:grid-cols-4 gap-4 items-center">
                        <div>
                            <span class="text-xs font-semibold text-slate-600 block">Total Pendaftar</span>
                            <p class="text-xl font-black text-slate-900 mt-1 tabular-data">{{ summary.total || 0 }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-600 block">Lunas</span>
                            <p class="text-xl font-black text-emerald-700 mt-1 tabular-data">{{ summary.paid || 0 }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-600 block">Digital Pass Aktif</span>
                            <p class="text-xl font-black text-slate-900 mt-1 tabular-data">{{ summary.digital_pass_active || 0 }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-600 block">Perlu Revisi</span>
                            <p class="text-xl font-black text-rose-700 mt-1 tabular-data">{{ summary.rejected || 0 }}</p>
                        </div>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    <div class="frosted-card p-5 lg:col-span-5 border-orange-300 bg-orange-50/40 flex flex-col justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold text-orange-900 block">Skuad Final Terkunci</span>
                            <p class="text-3xl font-black text-slate-900 mt-1 tabular-data">
                                {{ summary.locked_roster || 0 }}
                                <span class="text-sm font-semibold text-slate-600">/ {{ summary.total || 0 }} Tim</span>
                            </p>
                            <span class="text-xs text-slate-700 mt-0.5 block">Tim siap bertanding &amp; cetak ID Card</span>
                        </div>
                    </div>

                    <div class="frosted-card p-5 lg:col-span-7 grid grid-cols-3 gap-4 items-center">
                        <div>
                            <span class="text-xs font-semibold text-slate-600 block">Tim Mendaftar</span>
                            <p class="text-xl font-black text-slate-900 mt-1 tabular-data">{{ summary.total || 0 }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-600 block">Lunas &amp; Aktif</span>
                            <p class="text-xl font-black text-emerald-700 mt-1 tabular-data">{{ summary.paid || 0 }}</p>
                        </div>
                        <div>
                            <span class="text-xs font-semibold text-slate-600 block">Laga Selesai</span>
                            <p class="text-xl font-black text-slate-900 mt-1 tabular-data">{{ summary.completed_matches_count || 0 }} / {{ summary.matches_count || 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Position Quota Breakdown (Audition) -->
                <div v-if="isIndividual && positionBreakdown.length > 0" class="frosted-card p-5 bg-white space-y-3">
                    <h2 class="text-sm font-bold text-slate-900">Alokasi &amp; Realisasi Kuota per Posisi</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <div
                            v-for="pos in positionBreakdown"
                            :key="pos.position"
                            class="p-3 rounded-xl border border-slate-200 bg-slate-50/60 space-y-1.5"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-black text-slate-900">{{ pos.position }}</span>
                                <span class="font-mono font-bold text-slate-700">{{ pos.occupied }} / {{ pos.cap }}</span>
                            </div>
                            <div class="w-full h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full bg-orange-600 rounded-full"
                                    :style="{ width: `${Math.min(100, Math.round((pos.occupied / Math.max(1, pos.cap)) * 100))}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sessions & Stages for Individual Events -->
                <div v-if="isIndividual && (sessions.length > 0 || stages.length > 0)" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div v-if="sessions.length > 0" class="frosted-card p-5 bg-white space-y-3">
                        <h2 class="text-sm font-bold text-slate-900">Jadwal Sesi / Gelombang</h2>
                        <div class="space-y-2">
                            <div
                                v-for="s in sessions"
                                :key="s.id"
                                class="p-3 rounded-xl border border-slate-200 flex items-center justify-between text-xs"
                            >
                                <div>
                                    <p class="font-bold text-slate-900">{{ s.session_name }}</p>
                                    <p class="text-[11px] text-slate-600">{{ s.session_date || '-' }} &middot; {{ s.start_time || '-' }} - {{ s.end_time || '-' }}</p>
                                </div>
                                <span class="tabular-data font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800 border border-slate-200">
                                    {{ s.occupied_slots }} / {{ s.max_quota }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="stages.length > 0" class="frosted-card p-5 bg-white space-y-3">
                        <h2 class="text-sm font-bold text-slate-900">Tahapan Seleksi &amp; Presensi</h2>
                        <div class="space-y-2">
                            <div
                                v-for="st in stages"
                                :key="st.id"
                                class="p-3 rounded-xl border border-slate-200 flex items-center justify-between text-xs"
                            >
                                <div>
                                    <p class="font-bold text-slate-900">Tahap {{ st.stage_number }}: {{ st.stage_name }}</p>
                                    <p class="text-[11px] text-slate-500">Tanggal: {{ st.stage_date || 'Tentatif' }}</p>
                                </div>
                                <span class="font-mono font-bold px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    Hadir: {{ st.present_count }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: PARTICIPANTS / TEAMS & SCREENING -->
            <div v-if="activeTab === 'participants'" class="space-y-4">
                <!-- Filter Bar + Direct Review Action -->
                <div class="frosted-card p-4 bg-white flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2 flex-1">
                        <label for="workspace-search" class="sr-only">Cari peserta atau tim</label>
                        <input
                            id="workspace-search"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="isIndividual ? 'Cari nama peserta, No. Reg, NIK...' : 'Cari nama tim, manajer, askot...'"
                            class="input-field text-xs py-2 w-full sm:w-64"
                        />

                        <label for="workspace-status" class="sr-only">Filter status screening</label>
                        <select id="workspace-status" v-model="statusFilter" class="input-field text-xs py-2 w-full sm:w-44">
                            <option value="all">Semua Status</option>
                            <template v-if="isIndividual">
                                <option value="submitted">Menunggu Verifikasi</option>
                                <option value="passed">Berkas Sah (Lolos)</option>
                                <option value="rejected">Perlu Revisi</option>
                            </template>
                            <template v-else>
                                <option value="in_review">Sedang Diperiksa</option>
                                <option value="all_passed">Lolos Screening 100%</option>
                                <option value="partially_rejected">Perlu Revisi</option>
                                <option value="locked_roster">Skuad Final Terkunci</option>
                            </template>
                        </select>
                    </div>

                    <Link
                        v-if="isIndividual && participants.length > 0"
                        :href="route('admin.screening.auditions.review', { event: event.id })"
                        class="btn-pitch text-xs inline-flex items-center gap-1.5 justify-center"
                    >
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>Buka Workstation Screening Berurutan</span>
                    </Link>
                </div>

                <!-- Individual Participants List -->
                <template v-if="isIndividual">
                    <div v-if="filteredParticipants.length === 0" class="frosted-card p-10 text-center text-xs text-slate-500">
                        Belum ada peserta terdaftar pada event ini atau tidak ditemukan pada filter saat ini.
                    </div>
                    <div v-else class="frosted-card overflow-hidden bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[10px] font-bold">
                                        <th class="py-3 px-4">Peserta &amp; ID Pass</th>
                                        <th class="py-3 px-4">Posisi / Sesi</th>
                                        <th class="py-3 px-4">Pembayaran</th>
                                        <th class="py-3 px-4">Status Berkas</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="p in filteredParticipants" :key="p.id" class="hover:bg-orange-50/20">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-3">
                                                <img
                                                    v-if="p.photo_url"
                                                    :src="p.photo_url"
                                                    :alt="p.full_name"
                                                    class="w-9 h-9 rounded-xl object-cover border border-slate-200 shrink-0"
                                                />
                                                <div v-else class="w-9 h-9 rounded-xl bg-orange-100 text-orange-800 font-black flex items-center justify-center shrink-0">
                                                    {{ p.full_name.substring(0, 2).toUpperCase() }}
                                                </div>
                                                <div>
                                                    <div class="font-bold text-slate-900">{{ p.full_name }}</div>
                                                    <div class="font-mono text-[11px] text-slate-500">
                                                        {{ p.registration_number }}
                                                        <span v-if="p.bib_number" class="ml-1 text-orange-700 font-bold">• Rompi #{{ p.bib_number }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-slate-600">
                                            <div class="font-semibold text-slate-800">{{ p.preferred_position || '-' }}</div>
                                            <div class="text-[11px] text-slate-500">{{ p.session_name || 'Sesi Umum' }}</div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span
                                                class="px-2 py-0.5 rounded-md text-[11px] font-bold border"
                                                :class="p.is_paid ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-900 border-amber-200'"
                                            >
                                                {{ p.is_paid ? 'Lunas' : 'Menunggu Bayar' }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex flex-col items-start gap-1">
                                                <span class="px-2 py-0.5 rounded-md text-[11px] font-bold border" :class="screeningBadgeClass(p.screening_status)">
                                                    {{ p.screening_status_label }}
                                                </span>
                                                <span class="text-[10px] text-slate-500 font-mono">
                                                    {{ p.docs_valid }} / {{ p.docs_total }} Dokumen Sah
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
                                            <a
                                                v-if="p.can_download_bib"
                                                :href="route('audition.bib.download', { participant: p.id })"
                                                target="_blank"
                                                class="btn-sm-secondary inline-flex items-center gap-1"
                                                title="Unduh Digital Pass (E-Tiket)"
                                            >
                                                <span>Pass PDF</span>
                                            </a>
                                            <Link
                                                :href="`${route('admin.screening.auditions.review', { event: event.id })}?participant_id=${p.id}`"
                                                class="btn-sm-pitch inline-flex items-center gap-1"
                                            >
                                                <span>Periksa Berkas</span>
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <!-- Team List -->
                <template v-else>
                    <div v-if="filteredTeams.length === 0" class="frosted-card p-10 text-center text-xs text-slate-500">
                        Belum ada tim terdaftar pada event ini atau tidak ditemukan pada filter saat ini.
                    </div>
                    <div v-else class="frosted-card overflow-hidden bg-white">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase text-[10px] font-bold">
                                        <th class="py-3 px-4">Klub / Tim</th>
                                        <th class="py-3 px-4">Manajer</th>
                                        <th class="py-3 px-4">Status Pembayaran</th>
                                        <th class="py-3 px-4">Progres Screening</th>
                                        <th class="py-3 px-4 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="t in filteredTeams" :key="t.id" class="hover:bg-orange-50/20">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-3">
                                                <img
                                                    v-if="t.logo_url"
                                                    :src="t.logo_url"
                                                    :alt="t.name"
                                                    class="w-9 h-9 rounded-xl object-cover border border-slate-200 shrink-0"
                                                />
                                                <div v-else class="w-9 h-9 rounded-xl bg-orange-100 text-orange-800 font-black flex items-center justify-center shrink-0">
                                                    {{ t.name.charAt(0) }}
                                                </div>
                                                <div>
                                                    <div class="font-bold text-slate-900">{{ t.name }}</div>
                                                    <div class="text-[11px] text-slate-500">{{ t.origin_association || '-' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-slate-600">
                                            <div class="font-semibold text-slate-800">{{ t.manager_name }}</div>
                                            <div class="font-mono text-[11px] text-slate-500">{{ t.manager_whatsapp }}</div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <StatusBadge :status="t.status" />
                                        </td>
                                        <td class="py-3 px-4">
                                            <div class="flex flex-col items-start gap-1">
                                                <span class="px-2 py-0.5 rounded-md text-[11px] font-bold border" :class="screeningBadgeClass(t.screening_status)">
                                                    {{ t.passed_players_count }} / {{ t.players_count }} Pemain Sah
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-right space-x-1.5 whitespace-nowrap">
                                            <Link
                                                :href="route('admin.teams.show', { team: t.id })"
                                                class="btn-sm-secondary inline-flex items-center gap-1"
                                            >
                                                <span>Dossier Tim</span>
                                            </Link>
                                            <Link
                                                :href="route('admin.screening.team', { event: event.id, team: t.id })"
                                                class="btn-sm-pitch inline-flex items-center gap-1"
                                            >
                                                <span>Periksa Skuad</span>
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>

            <!-- TAB 3: OPERATIONS & FIELD TOOLS -->
            <div v-if="activeTab === 'operations'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <template v-if="isIndividual">
                    <!-- 1. Gatekeeper Digital Pass Scanner -->
                    <div class="frosted-card p-5 bg-white flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 border border-orange-200 flex items-center justify-center">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-black text-slate-900">Scanner Presensi Gerbang (Digital Pass)</h2>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Pindai QR Digital Pass (E-Tiket) peserta dengan kamera atau ketik kode tiket untuk validasi anti-joki dan absensi gerbang.
                            </p>
                        </div>
                        <Link
                            :href="route('admin.auditions.scanner', { event_id: event.id })"
                            class="btn-pitch text-xs justify-center w-full"
                        >
                            Buka Scanner Gerbang
                        </Link>
                    </div>

                    <!-- 2. Panel Scout (Audition Only) -->
                    <div v-if="isAudition" class="frosted-card p-5 bg-white flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-800 border border-slate-200 flex items-center justify-center">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-black text-slate-900">Panel Scout Lapangan</h2>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Antarmuka cepat untuk tim pemandu bakat memberikan grade A/B/C, skor bintang, dan catatan teknis langsung dari pinggir lapangan.
                            </p>
                        </div>
                        <Link
                            :href="route('scout.events.show', { event: event.id })"
                            target="_blank"
                            class="btn-secondary text-xs justify-center w-full"
                        >
                            Buka Panel Scout
                        </Link>
                    </div>

                    <!-- 3. Kelolosan & Piagam / Sertifikat -->
                    <div class="frosted-card p-5 bg-white flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-black text-slate-900">
                                {{ isAudition ? 'Kelolosan Multi-Tahap & Piagam' : 'Rekap Presensi & Rilis Sertifikat' }}
                            </h2>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                {{ isAudition ? 'Promosikan peserta ke tahap berikutnya, tetapkan hasil seleksi akhir, dan rilis E-Sertifikat resmi.' : 'Konfirmasi kelulusan kehadiran workshop dan rilis E-Sertifikat partisipasi coaching clinic.' }}
                            </p>
                        </div>
                        <Link
                            :href="route('admin.events.auditions.qualifications', { event: event.id })"
                            class="btn-pitch text-xs justify-center w-full"
                        >
                            {{ isAudition ? 'Kelola Kelolosan & Piagam' : 'Kelola Presensi & Sertifikat' }}
                        </Link>
                    </div>
                </template>

                <!-- Team Tournament Operational Tools -->
                <template v-else>
                    <div class="frosted-card p-5 bg-white flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-600 border border-orange-200 flex items-center justify-center">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-black text-slate-900">Pusat Kendali Turnamen, Grup &amp; Skor</h2>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Pengundian grup, generate jadwal round-robin/knockout, input hasil pertandingan, klasemen, pengumuman, dan rilis piagam tim.
                            </p>
                        </div>
                        <Link
                            :href="route('admin.events.tournament', { event: event.id })"
                            class="btn-pitch text-xs justify-center w-full"
                        >
                            Buka Pusat Turnamen &amp; Skor
                        </Link>
                    </div>

                    <div class="frosted-card p-5 bg-white flex flex-col justify-between space-y-4">
                        <div class="space-y-2">
                            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-black text-slate-900">QR Scanner Pertandingan &amp; ID Card</h2>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Verifikasi ID Card keabsahan pemain tim saat pemeriksaan daftar susunan pemain (DSP) di lapangan.
                            </p>
                        </div>
                        <Link
                            :href="route('admin.qr-scanner')"
                            class="btn-secondary text-xs justify-center w-full"
                        >
                            Buka QR Scanner Matchday
                        </Link>
                    </div>
                </template>
            </div>
        </div>
    </AdminLayout>
</template>

