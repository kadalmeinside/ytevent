<script setup>
import { ref, computed } from 'vue';
import { Link, Head } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
    summary: {
        type: Object,
        default: null,
    },
    auditionParticipants: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah, formatDateTime } = useFormatters();

const activeTab = ref(
    props.team || props.teamsList.length > 0 ? 'team' : (props.auditionParticipants.length > 0 ? 'audition' : 'team')
);

const calculatePercent = (current, total) => {
    if (!total || total <= 0) return 0;
    return Math.min(100, Math.round((current / total) * 100));
};
</script>

<template>
    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Portal Akun Terpadu">
        <Head title="Dasbor Akun Saya" />

        <!-- Tab Selector: Tim Turnamen vs Audisi Anak -->
        <div v-if="teamsList.length > 0 || auditionParticipants.length > 0" class="flex items-center gap-2 p-1.5 bg-slate-100/90 rounded-2xl w-fit border border-slate-200/80 mb-6">
            <button
                type="button"
                class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all"
                :class="activeTab === 'team' ? 'bg-white text-orange-600 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                @click="activeTab = 'team'"
            >
                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span>Tim Turnamen</span>
                <span v-if="teamsList.length > 0" class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="activeTab === 'team' ? 'bg-orange-100 text-orange-700' : 'bg-slate-200 text-slate-600'">
                    {{ teamsList.length }}
                </span>
            </button>
            <button
                type="button"
                class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition-all"
                :class="activeTab === 'audition' ? 'bg-white text-orange-600 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                @click="activeTab = 'audition'"
            >
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Pendaftaran Perorangan</span>
                <span v-if="auditionParticipants.length > 0" class="px-1.5 py-0.5 rounded-full text-[10px] font-black" :class="activeTab === 'audition' ? 'bg-orange-100 text-orange-700' : 'bg-slate-200 text-slate-600'">
                    {{ auditionParticipants.length }}
                </span>
            </button>
        </div>

        <!-- Section: Tim Turnamen -->
        <div v-if="activeTab === 'team'">
            <div v-if="!team" class="frosted-card p-8 text-center max-w-xl mx-auto my-6">
                <div class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center mx-auto mb-3 shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h2 class="text-base font-bold text-slate-900">Anda Belum Memiliki Tim Terdaftar</h2>
                <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">Silakan pilih kompetisi di Katalog Event untuk mendaftarkan tim baru.</p>
                <Link :href="route('home')" class="btn-pitch mt-5 inline-flex items-center gap-1.5">Lihat Katalog Kompetisi</Link>
            </div>

            <div v-else class="space-y-6">
            <!-- Fase 6.1: Papan Pengumuman & Undangan Technical Meeting dari Panitia -->
            <div
                v-if="summary?.announcements?.length > 0"
                class="space-y-3"
            >
                <div
                    v-for="announcement in summary.announcements"
                    :key="announcement.id"
                    class="frosted-card p-5 border-l-4 border-l-orange-600 bg-white shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span v-if="announcement.is_pinned" class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-red-100 text-red-800 border border-red-200">
                                    PENTING
                                </span>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-orange-600">Pengumuman Panitia Pelaksana</span>
                                <span class="text-slate-300">•</span>
                                <span class="text-[11px] text-slate-400">{{ announcement.created_at }}</span>
                            </div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900">{{ announcement.title }}</h3>
                            <p class="text-xs text-slate-600 whitespace-pre-line leading-relaxed">{{ announcement.content }}</p>
                        </div>
                        <a
                            v-if="announcement.meeting_link"
                            :href="announcement.meeting_link"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn-pitch text-xs shrink-0 inline-flex items-center gap-1.5 shadow-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <span>Buka Link Meeting</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Key Operational Cards (3-Tier Layout Anti-Collision) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Status Pendaftaran Tim -->
                <div class="frosted-card p-4 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Status Pendaftaran Tim</span>
                            <span class="text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-3 flex items-center">
                            <StatusBadge :status="team.status" class="text-xs px-3 py-1 font-bold shadow-sm" />
                        </div>
                    </div>
                    <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center gap-1.5 text-xs text-slate-500">
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="truncate" :title="team.origin_association">{{ team.origin_association || 'Asosiasi Kota/Kabupaten' }}</span>
                    </div>
                </div>

                <!-- Card 2: Tagihan Pendaftaran -->
                <div class="frosted-card p-4 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tagihan Pendaftaran</span>
                            <StatusBadge v-if="summary?.latest_payment" :status="summary.latest_payment.status" class="scale-90 origin-right" />
                        </div>
                        <p class="text-xl sm:text-2xl font-black text-slate-900 tabular-data mt-2 tracking-tight">
                            {{ formatRupiah(summary?.latest_payment?.total_amount || team.event?.registration_fee) }}
                        </p>

                        <!-- Contextual Payment Due / Paid Information -->
                        <div
                            v-if="team.status === 'pending_payment' && (summary?.latest_payment?.expires_at || team.slot_expires_at)"
                            class="mt-2.5 flex items-center gap-1.5 text-[11px] text-amber-800 bg-amber-50/90 border border-amber-200/80 px-2 py-1 rounded-lg"
                        >
                            <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="truncate">
                                Batas Bayar: <strong class="tabular-data font-semibold text-amber-950">{{ formatDateTime(summary?.latest_payment?.expires_at || team.slot_expires_at) }}</strong>
                            </span>
                        </div>
                        <div
                            v-else-if="team.status === 'expired'"
                            class="mt-2.5 flex items-center gap-1.5 text-[11px] text-rose-800 bg-rose-50/90 border border-rose-200/80 px-2 py-1 rounded-lg"
                        >
                            <svg class="w-3.5 h-3.5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="truncate font-semibold text-rose-950">Slot Telah Kedaluwarsa</span>
                        </div>
                        <div
                            v-else-if="summary?.latest_payment?.paid_at"
                            class="mt-2.5 flex items-center gap-1.5 text-[11px] text-emerald-800 bg-emerald-50/90 border border-emerald-200/80 px-2 py-1 rounded-lg"
                        >
                            <svg class="w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="truncate">
                                Lunas: <strong class="tabular-data font-semibold text-emerald-950">{{ formatDateTime(summary.latest_payment.paid_at) }}</strong>
                            </span>
                        </div>
                    </div>
                    <div class="mt-4 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs">
                        <span class="text-[11px] font-medium text-slate-400">No. Invoice</span>
                        <span class="font-mono text-xs text-slate-600 bg-slate-100/80 px-2 py-0.5 rounded border border-slate-200/60 tabular-data">
                            {{ summary?.latest_payment?.invoice_number || '-' }}
                        </span>
                    </div>
                </div>

                <!-- Card 3: Progres Kuota Pemain -->
                <div class="frosted-card p-4 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Progres Kuota Pemain</span>
                            <span class="text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </span>
                        </div>
                        <div class="mt-2 flex items-baseline justify-between">
                            <p class="text-xl sm:text-2xl font-black text-slate-900 tabular-data tracking-tight">
                                {{ summary?.players_total || 0 }} <span class="text-xs font-semibold text-slate-500">/ {{ team.event?.max_players_per_team }} Pemain</span>
                            </p>
                            <span class="text-xs font-bold text-orange-600 tabular-data">
                                {{ calculatePercent(summary?.players_total || 0, team.event?.max_players_per_team) }}%
                            </span>
                        </div>
                        <!-- Mini Progress Bar Persija Flame -->
                        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mt-2">
                            <div
                                class="h-full bg-gradient-to-r from-orange-500 to-red-600 rounded-full transition-all duration-300"
                                :style="{ width: `${calculatePercent(summary?.players_total || 0, team.event?.max_players_per_team)}%` }"
                            ></div>
                        </div>
                    </div>
                    <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                        <span>Lengkap: <strong class="tabular-data text-emerald-700 font-semibold">{{ summary?.players_docs_complete || 0 }}</strong></span>
                        <span>Min: <strong class="tabular-data text-slate-800 font-semibold">{{ team.event?.min_players_per_team }}</strong></span>
                    </div>
                </div>

                <!-- Card 4: Pelatih & Ofisial -->
                <div class="frosted-card p-4 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pelatih &amp; Ofisial</span>
                            <span class="text-slate-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                </svg>
                            </span>
                        </div>
                        <p class="text-xl sm:text-2xl font-black text-slate-900 tabular-data mt-2 tracking-tight">
                            {{ summary?.officials_total || 0 }} <span class="text-xs font-semibold text-slate-500">/ {{ team.event?.max_officials_per_team }} Ofisial</span>
                        </p>
                    </div>
                    <div class="mt-4 pt-2.5 border-t border-slate-100 text-[11px] text-slate-500 truncate" :title="team.head_coach_name">
                        Pelatih: <strong class="text-slate-800 font-semibold">{{ team.head_coach_name || '-' }}</strong>
                    </div>
                </div>
            </div>

            <!-- 5-Stage End-to-End Team Readiness Stepper -->
            <div class="frosted-card p-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Alur 5 Tahap Kesiapan Tim Sampai Terakreditasi Resmi</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Setiap 1 syarat terpenuhi, sistem akan memandu Anda ke tahap berikutnya hingga ID Card siap diunduh.
                        </p>
                    </div>
                    <div v-if="team.workflow" class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-50 border border-orange-200 text-xs font-bold text-orange-800 shrink-0">
                        <span>Tahap Aktif: {{ team.workflow.current_step }} dari {{ team.workflow.total_steps }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3.5">
                    <!-- Step 1: Pembayaran -->
                    <div
                        class="p-4 rounded-2xl border flex flex-col justify-between space-y-3 transition-all"
                        :class="
                            team.workflow?.is_paid
                                ? 'border-emerald-200 bg-emerald-50/35'
                                : 'border-orange-300 bg-orange-50/50 ring-1 ring-orange-300/60 shadow-sm'
                        "
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="w-6 h-6 rounded-lg font-black text-xs flex items-center justify-center shrink-0"
                                    :class="team.workflow?.is_paid ? 'bg-emerald-600 text-white' : 'bg-orange-600 text-white'"
                                >
                                    1
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider border"
                                    :class="
                                        team.workflow?.is_paid
                                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                            : 'bg-orange-100 text-orange-800 border-orange-200'
                                    "
                                >
                                    {{ team.workflow?.is_paid ? 'LUNAS' : 'TAHAP AKTIF' }}
                                </span>
                            </div>
                            <h3 class="font-bold text-xs sm:text-sm text-slate-900">Pembayaran Pendaftaran</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Selesaikan pembayaran tagihan pendaftaran agar kuota tim terkunci dan menu skuad terbuka.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-200/70">
                            <Link :href="route('manager.payments')" class="inline-flex items-center gap-1 text-xs font-bold text-orange-600 hover:text-orange-700">
                                <span>{{ team.workflow?.is_paid ? 'Lihat Bukti Lunas' : 'Bayar Sekarang' }}</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                        </div>
                    </div>

                    <!-- Step 2: Kuota Pemain & Min 2 GK -->
                    <div
                        class="p-4 rounded-2xl border flex flex-col justify-between space-y-3 transition-all"
                        :class="
                            team.workflow?.is_all_players_submitted_or_passed && team.workflow?.is_gk_met
                                ? 'border-emerald-200 bg-emerald-50/35'
                                : team.workflow?.counts?.players_rejected > 0
                                  ? 'border-rose-300 bg-rose-50/50 ring-1 ring-rose-300/60 shadow-sm'
                                  : team.workflow?.current_step === 2
                                    ? 'border-orange-300 bg-orange-50/50 ring-1 ring-orange-300/60 shadow-sm'
                                    : 'border-slate-200/90 bg-white/70'
                        "
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="w-6 h-6 rounded-lg font-black text-xs flex items-center justify-center shrink-0"
                                    :class="
                                        team.workflow?.is_all_players_submitted_or_passed && team.workflow?.is_gk_met
                                            ? 'bg-emerald-600 text-white'
                                            : team.workflow?.counts?.players_rejected > 0
                                              ? 'bg-rose-600 text-white'
                                              : team.workflow?.current_step === 2
                                                ? 'bg-orange-600 text-white'
                                                : 'bg-slate-200 text-slate-700'
                                    "
                                >
                                    2
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider border tabular-data"
                                    :class="
                                        team.workflow?.is_all_players_submitted_or_passed && team.workflow?.is_gk_met
                                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                            : team.workflow?.counts?.players_rejected > 0
                                              ? 'bg-rose-100 text-rose-800 border-rose-200'
                                              : 'bg-amber-100 text-amber-900 border-amber-200'
                                    "
                                >
                                    {{
                                        team.workflow?.counts?.players_rejected > 0
                                            ? `${team.workflow.counts.players_rejected} REVISI`
                                            : `${team.workflow?.counts?.players_total || 0}/${team.workflow?.counts?.min_players_required || 11} PEMAIN`
                                    }}
                                </span>
                            </div>
                            <h3 class="font-bold text-xs sm:text-sm text-slate-900">Skuad &amp; Min. {{ team.workflow?.counts?.min_gk_required || 2 }} Kiper (GK)</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Wajib min. {{ team.workflow?.counts?.min_players_required || 11 }} pemain, min. {{ team.workflow?.counts?.min_gk_required || 2 }} GK (saat ini {{ team.workflow?.counts?.gk_total || 0 }} GK), dan diajukan ke panitia.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-200/70">
                            <Link
                                v-if="team.workflow?.is_paid"
                                :href="route('manager.players')"
                                class="inline-flex items-center gap-1 text-xs font-bold text-orange-600 hover:text-orange-700"
                            >
                                <span>Kelola Daftar Pemain</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                            <span v-else class="text-[11px] text-slate-400 font-medium">Menunggu pelunasan</span>
                        </div>
                    </div>

                    <!-- Step 3: Pelatih & Ofisial -->
                    <div
                        class="p-4 rounded-2xl border flex flex-col justify-between space-y-3 transition-all"
                        :class="
                            team.workflow?.is_officials_met
                                ? 'border-emerald-200 bg-emerald-50/35'
                                : team.workflow?.current_step === 3
                                  ? 'border-orange-300 bg-orange-50/50 ring-1 ring-orange-300/60 shadow-sm'
                                  : 'border-slate-200/90 bg-white/70'
                        "
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="w-6 h-6 rounded-lg font-black text-xs flex items-center justify-center shrink-0"
                                    :class="
                                        team.workflow?.is_officials_met
                                            ? 'bg-emerald-600 text-white'
                                            : team.workflow?.current_step === 3
                                              ? 'bg-orange-600 text-white'
                                              : 'bg-slate-200 text-slate-700'
                                    "
                                >
                                    3
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider border tabular-data"
                                    :class="
                                        team.workflow?.is_officials_met
                                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                            : 'bg-amber-100 text-amber-900 border-amber-200'
                                    "
                                >
                                    {{ team.workflow?.is_officials_met ? `${team.workflow.counts.officials_total} OFISIAL` : 'WAJIB 1+' }}
                                </span>
                            </div>
                            <h3 class="font-bold text-xs sm:text-sm text-slate-900">Pelatih &amp; Ofisial Tim</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Daftarkan minimal 1 Pelatih Kepala / Ofisial pendamping resmi beserta foto &amp; lisensi.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-200/70">
                            <Link
                                v-if="team.workflow?.is_paid"
                                :href="route('manager.officials')"
                                class="inline-flex items-center gap-1 text-xs font-bold text-orange-600 hover:text-orange-700"
                            >
                                <span>Kelola Ofisial Tim</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                            <span v-else class="text-[11px] text-slate-400 font-medium">Menunggu pelunasan</span>
                        </div>
                    </div>

                    <!-- Step 4: Verifikasi Screening Panitia -->
                    <div
                        class="p-4 rounded-2xl border flex flex-col justify-between space-y-3 transition-all"
                        :class="
                            team.workflow?.is_all_players_passed && team.workflow?.is_gk_passed_met
                                ? 'border-emerald-200 bg-emerald-50/35'
                                : team.workflow?.counts?.players_submitted > 0
                                  ? 'border-blue-300 bg-blue-50/40'
                                  : 'border-slate-200/90 bg-white/70'
                        "
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="w-6 h-6 rounded-lg font-black text-xs flex items-center justify-center shrink-0"
                                    :class="
                                        team.workflow?.is_all_players_passed && team.workflow?.is_gk_passed_met
                                            ? 'bg-emerald-600 text-white'
                                            : team.workflow?.counts?.players_submitted > 0
                                              ? 'bg-blue-600 text-white'
                                              : 'bg-slate-200 text-slate-700'
                                    "
                                >
                                    4
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider border tabular-data"
                                    :class="
                                        team.workflow?.is_all_players_passed && team.workflow?.is_gk_passed_met
                                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                            : team.workflow?.counts?.players_submitted > 0
                                              ? 'bg-blue-100 text-blue-800 border-blue-200'
                                              : 'bg-slate-100 text-slate-600 border-slate-200'
                                    "
                                >
                                    {{
                                        team.workflow?.is_all_players_passed && team.workflow?.is_gk_passed_met
                                            ? 'SEMUA SAH'
                                            : `${team.workflow?.counts?.players_passed || 0}/${team.workflow?.counts?.players_total || 0} SAH`
                                    }}
                                </span>
                            </div>
                            <h3 class="font-bold text-xs sm:text-sm text-slate-900">Verifikasi Screening</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Pemeriksaan keabsahan dokumen wajib seluruh pemain oleh Panitia Screening.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-200/70">
                            <Link
                                v-if="team.workflow?.is_paid"
                                :href="route('manager.players')"
                                class="inline-flex items-center gap-1 text-xs font-bold text-orange-600 hover:text-orange-700"
                            >
                                <span>Pantau Status Berkas</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                            <span v-else class="text-[11px] text-slate-400 font-medium">Menunggu pengajuan</span>
                        </div>
                    </div>

                    <!-- Step 5: Kunci Skuad Final & Cetak ID Card -->
                    <div
                        class="p-4 rounded-2xl border flex flex-col justify-between space-y-3 transition-all"
                        :class="
                            team.workflow?.is_roster_locked
                                ? 'border-emerald-300 bg-emerald-50/50 shadow-sm'
                                : team.workflow?.is_ready_to_lock
                                  ? 'border-orange-400 bg-gradient-to-br from-orange-50 via-amber-50 to-red-50 ring-2 ring-orange-400/60 shadow-md'
                                  : 'border-slate-200/90 bg-white/70'
                        "
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="w-6 h-6 rounded-lg font-black text-xs flex items-center justify-center shrink-0"
                                    :class="
                                        team.workflow?.is_roster_locked
                                            ? 'bg-emerald-600 text-white'
                                            : team.workflow?.is_ready_to_lock
                                              ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white'
                                              : 'bg-slate-200 text-slate-700'
                                    "
                                >
                                    5
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider border"
                                    :class="
                                        team.workflow?.is_roster_locked
                                            ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                            : team.workflow?.is_ready_to_lock
                                              ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white border-orange-600'
                                              : 'bg-slate-100 text-slate-600 border-slate-200'
                                    "
                                >
                                    {{
                                        team.workflow?.is_roster_locked
                                            ? 'FINAL'
                                            : team.workflow?.is_ready_to_lock
                                              ? 'SIAP KUNCI!'
                                              : 'BELUM SIAP'
                                    }}
                                </span>
                            </div>
                            <h3 class="font-bold text-xs sm:text-sm text-slate-900">Akreditasi &amp; ID Card</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Kunci skuad final setelah seluruh pemain lolos untuk menerbitkan Album Roster &amp; ID Card QR.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-200/70">
                            <Link
                                :href="route('manager.accreditation.index')"
                                class="inline-flex items-center gap-1 text-xs font-bold"
                                :class="
                                    team.workflow?.is_ready_to_lock
                                        ? 'text-orange-700 hover:text-orange-800 underline'
                                        : 'text-orange-600 hover:text-orange-700'
                                "
                            >
                                <span>{{ team.workflow?.is_roster_locked ? 'Unduh ID Card & Roster' : (team.workflow?.is_ready_to_lock ? 'Kunci Skuad Sekarang' : 'Cek Syarat Akreditasi') }}</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- Section: Pendaftaran Audisi Anak -->
        <div v-if="activeTab === 'audition'" class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <span>Pendaftaran Kegiatan Perorangan</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-700">
                            {{ auditionParticipants.length }} Peserta
                        </span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pantau proses verifikasi berkas, tahapan kegiatan, nomor dada, atau sertifikat peserta Anda.
                    </p>
                </div>
                <Link :href="route('home')" class="btn-pitch text-xs sm:text-sm self-start sm:self-auto inline-flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Daftar Audisi / Event Lain</span>
                </Link>
            </div>

            <div v-if="auditionParticipants.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="p in auditionParticipants"
                    :key="p.id"
                    class="frosted-card p-5 flex flex-col justify-between space-y-4 hover:shadow-md transition-shadow border-slate-200/90"
                >
                    <div class="space-y-3">
                        <!-- Header: Photo & Child Identity -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="p.photo_url"
                                    :src="p.photo_url"
                                    :alt="p.full_name"
                                    class="w-12 h-12 rounded-xl object-cover border border-slate-200 shadow-2xs shrink-0"
                                />
                                <div
                                    v-else
                                    class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 text-white font-black text-sm flex items-center justify-center shrink-0 shadow-2xs"
                                >
                                    {{ p.full_name.substring(0, 2).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm sm:text-base font-black text-slate-900 truncate">
                                        {{ p.full_name }}
                                        <span v-if="p.nickname" class="text-xs font-semibold text-slate-500">({{ p.nickname }})</span>
                                    </h3>
                                    <p class="text-xs font-semibold text-orange-600 truncate mt-0.5">
                                        {{ p.event?.name }} <span v-if="p.event?.age_category" class="text-slate-400 font-normal">({{ p.event.age_category }})</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Bib Number Badge -->
                            <div class="shrink-0 text-right">
                                <div v-if="p.bib_number" class="px-2.5 py-1 rounded-xl bg-orange-600 text-white font-black text-xs shadow-2xs">
                                    Bib #{{ p.bib_number }}
                                </div>
                                <div v-else class="text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-lg border border-slate-200">
                                    Bib Belum Terbit
                                </div>
                            </div>
                        </div>

                        <!-- Registration & Status Badges -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                            <!-- Reg Number -->
                            <span class="text-[10px] font-bold font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                {{ p.registration_number }}
                            </span>

                            <!-- Payment Status -->
                            <span
                                class="text-[10px] font-bold px-2 py-0.5 rounded border"
                                :class="p.is_paid ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
                            >
                                {{ p.is_paid ? 'LUNAS' : 'MENUNGGU PEMBAYARAN' }}
                            </span>

                            <!-- Screening Status -->
                            <span
                                class="text-[10px] font-bold px-2 py-0.5 rounded border"
                                :class="
                                    p.screening_status === 'passed'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                        : p.screening_status === 'rejected'
                                          ? 'bg-rose-50 text-rose-700 border-rose-200'
                                          : 'bg-blue-50 text-blue-700 border-blue-200'
                                "
                            >
                                {{ p.screening_status_label }}
                            </span>

                            <!-- Stage Status -->
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200">
                                {{ p.stage_status_label }}
                            </span>
                        </div>

                        <!-- Technical Details Table -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-2.5 rounded-xl bg-slate-50/80 border border-slate-200/70 text-xs">
                            <div>
                                <span class="block text-[10px] text-slate-400 font-bold uppercase">Posisi</span>
                                <span class="font-bold text-slate-800">{{ p.preferred_position || '-' }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-400 font-bold uppercase">Kaki Dominan</span>
                                <span class="font-bold text-slate-800 capitalize">{{ p.dominant_foot || '-' }}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-400 font-bold uppercase">Postur (TB/BB)</span>
                                <span class="font-bold text-slate-800">{{ p.height_cm ? `${p.height_cm} cm / ${p.weight_kg} kg` : '-' }}</span>
                            </div>
                            <div class="col-span-2 sm:col-span-3 pt-1 border-t border-slate-200/60 flex items-center justify-between">
                                <span class="text-[11px] text-slate-500 truncate">Asal: <strong class="text-slate-700">{{ p.current_club_ssb || 'Independen' }}</strong></span>
                                <span v-if="p.session" class="text-[11px] text-orange-600 font-semibold">{{ p.session.session_name }}</span>
                            </div>
                        </div>

                        <!-- Screening Rejection Warning -->
                        <div v-if="p.screening_status === 'rejected' && p.screening_notes" class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                            <p class="font-bold">Catatan Perbaikan Berkas:</p>
                            <p class="mt-0.5">{{ p.screening_notes }}</p>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap">
                        <div class="flex items-center gap-2 flex-wrap">
                            <a
                                v-if="p.can_download_bib"
                                :href="route().has('audition.bib.download') ? route('audition.bib.download', { participant: p.id }) : '#'"
                                target="_blank"
                                class="btn-pitch text-xs inline-flex items-center gap-1.5 shadow-2xs"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Cetak Nomor Dada</span>
                            </a>

                            <a
                                v-if="p.can_download_certificate"
                                :href="route().has('audition.certificate.download') ? route('audition.certificate.download', { participant: p.id }) : '#'"
                                target="_blank"
                                class="btn-sm-secondary text-xs inline-flex items-center gap-1.5 border-orange-300 text-orange-800 hover:bg-orange-50"
                            >
                                <span>📜 Piagam</span>
                            </a>
                        </div>
                        <div
                            v-if="!p.can_download_bib"
                            class="text-[11px] text-slate-400 italic"
                        >
                            Nomor dada aktif setelah berkas sah &amp; lunas.
                        </div>

                        <Link
                            v-if="!p.is_paid && p.latest_payment"
                            :href="route('manager.payments')"
                            class="btn-pitch text-xs inline-flex items-center gap-1"
                        >
                            <span>Bayar Tagihan</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Empty State for Auditions -->
            <div v-else class="frosted-card p-8 text-center max-w-xl mx-auto my-6">
                <div class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center mx-auto mb-3 shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">Belum Ada Pendaftaran Audisi Anak</h3>
                <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
                    Daftarkan anak Anda pada program seleksi bakat, audisi akademi, atau coaching clinic resmi.
                </p>
                <Link :href="route('home')" class="btn-pitch mt-5 inline-flex items-center gap-1.5">
                    <span>Lihat Program Audisi di Katalog</span>
                </Link>
            </div>
        </div>
    </ManagerLayout>
</template>
