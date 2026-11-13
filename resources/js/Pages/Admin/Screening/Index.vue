<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    teams: {
        type: Object,
        required: true,
    },
    metrics: {
        type: Object,
        default: () => ({}),
    },
    filters: {
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
        route('admin.screening.index'),
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

const getStatusBadge = (status) => {
    switch (status) {
        case 'all_passed':
            return {
                label: 'Semua Pemain Sah',
                class: 'bg-emerald-50 text-emerald-800 border-emerald-300',
            };
        case 'partially_rejected':
            return {
                label: 'Perlu Revisi Berkas',
                class: 'bg-rose-50 text-rose-800 border-rose-300',
            };
        case 'in_review':
            return {
                label: 'Sedang Diperiksa',
                class: 'bg-amber-50 text-amber-900 border-amber-300',
            };
        case 'not_started':
        default:
            return {
                label: 'Belum Diajukan',
                class: 'bg-slate-100 text-slate-700 border-slate-300',
            };
    }
};
</script>

<template>
    <AdminLayout
        page-title="Screening &amp; Verifikasi Berkas Pemain"
        subtitle="Pemeriksaan keabsahan dokumen identitas pemain (Akta, KK, KIA, NISN) untuk mencegah manipulasi usia dan pendaftaran ganda."
    >
        <Head title="Antrean Screening Pemain" />

        <div class="space-y-6">
            <!-- Header Switcher Tab Turnamen vs Audisi -->
            <div class="flex items-center justify-between">
                <nav aria-label="Tipe Antrean Screening" class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200">
                    <Link
                        :href="route('admin.screening.index')"
                        aria-current="page"
                        class="px-3.5 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-bold bg-white text-orange-700 border border-slate-200 inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-orange-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Tim Turnamen</span>
                    </Link>
                    <Link
                        :href="route('admin.screening.auditions')"
                        class="px-3.5 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold text-slate-700 hover:text-slate-900 transition-colors inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Peserta Perorangan</span>
                    </Link>
                </nav>
            </div>

            <!-- Asymmetric Operational Metrics Grid (Focal Queue Card + Breakdown Strip) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5">
                <div class="lg:col-span-5 frosted-card p-5 border-orange-200 bg-orange-50/40 flex items-center justify-between gap-4">
                    <div class="space-y-1">
                        <span class="text-xs font-bold text-orange-800 block">Pemain Siap Diperiksa</span>
                        <p class="text-3xl font-black text-slate-900 tabular-nums">{{ metrics.total_submitted_players || 0 }}</p>
                        <span class="text-xs text-slate-700 block">Berkas berstatus diajukan &bull; {{ metrics.teams_in_review || 0 }} tim dalam antrean</span>
                    </div>
                    <div class="text-right border-l border-orange-200 pl-4 shrink-0">
                        <span class="text-xs font-semibold text-amber-800 block">Antrean Tim</span>
                        <p class="text-2xl font-black text-amber-900 tabular-nums mt-0.5">{{ metrics.teams_in_review || 0 }}</p>
                        <span class="text-xs text-slate-600 block">Menunggu validasi</span>
                    </div>
                </div>

                <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div class="frosted-card p-4 space-y-1">
                        <span class="text-xs font-semibold text-slate-600 block">Total Tim Aktif</span>
                        <p class="text-2xl font-black text-slate-900 tabular-nums">{{ metrics.total_teams_active || 0 }}</p>
                        <span class="text-xs text-slate-600 block">Tim terdaftar &amp; lunas</span>
                    </div>

                    <div class="frosted-card p-4 space-y-1 border-rose-200 bg-rose-50/30">
                        <span class="text-xs font-semibold text-rose-800 block">Tim Perlu Revisi</span>
                        <p class="text-2xl font-black text-rose-900 tabular-nums">{{ metrics.teams_partially_rejected || 0 }}</p>
                        <span class="text-xs text-rose-800 block">Terdapat berkas ditolak</span>
                    </div>

                    <div class="frosted-card p-4 space-y-1 border-emerald-200 bg-emerald-50/30">
                        <span class="text-xs font-semibold text-emerald-800 block">Tim Seluruhnya Sah</span>
                        <p class="text-2xl font-black text-emerald-900 tabular-nums">{{ metrics.teams_all_passed || 0 }}</p>
                        <span class="text-xs text-emerald-800 block">Semua berkas lolos</span>
                    </div>
                </div>
            </div>

            <!-- Filter Bar & Search Container -->
            <div class="frosted-card p-4 space-y-3.5 bg-white">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <!-- Search Input & Event Dropdown -->
                    <div class="flex flex-1 flex-wrap items-center gap-2.5">
                        <div class="relative flex-1 min-w-[200px] max-w-sm">
                            <label for="screening-team-search" class="sr-only">Cari nama klub, manajer, atau askot</label>
                            <input
                                id="screening-team-search"
                                v-model="filterState.search"
                                type="text"
                                placeholder="Cari nama klub, manajer, atau askot..."
                                class="input-field text-xs pl-8"
                                @keyup.enter="applyFilters"
                            />
                            <svg class="w-4 h-4 text-slate-500 absolute left-2.5 top-3 sm:top-2.5 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <label for="screening-team-event" class="sr-only">Filter kompetisi atau event</label>
                        <select
                            id="screening-team-event"
                            v-model="filterState.event_id"
                            class="input-field text-xs w-auto min-w-[220px]"
                            @change="applyFilters"
                        >
                            <option value="">Semua Kompetisi / Event</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">
                                {{ e.name }} ({{ e.age_category }})
                            </option>
                        </select>

                        <button type="button" class="btn-pitch text-xs" @click="applyFilters">
                            Terapkan Filter
                        </button>
                    </div>

                    <!-- Quick Status Filter Pills -->
                    <div class="flex flex-wrap items-center gap-1.5 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                        <button
                            type="button"
                            :aria-pressed="filterState.status === ''"
                            class="px-3 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold transition-colors border"
                            :class="filterState.status === '' ? 'bg-orange-600 text-white border-orange-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                            @click="setStatusFilter('')"
                        >
                            Semua
                        </button>
                        <button
                            type="button"
                            :aria-pressed="filterState.status === 'in_review'"
                            class="px-3 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold transition-colors border"
                            :class="filterState.status === 'in_review' ? 'bg-amber-600 text-white border-amber-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                            @click="setStatusFilter('in_review')"
                        >
                            Sedang Diperiksa
                        </button>
                        <button
                            type="button"
                            :aria-pressed="filterState.status === 'partially_rejected'"
                            class="px-3 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold transition-colors border"
                            :class="filterState.status === 'partially_rejected' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                            @click="setStatusFilter('partially_rejected')"
                        >
                            Perlu Revisi
                        </button>
                        <button
                            type="button"
                            :aria-pressed="filterState.status === 'all_passed'"
                            class="px-3 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold transition-colors border"
                            :class="filterState.status === 'all_passed' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'"
                            @click="setStatusFilter('all_passed')"
                        >
                            Semua Sah
                        </button>
                    </div>
                </div>
            </div>

            <!-- Teams Screening Queue List (Responsive Card List View) -->
            <div class="space-y-3.5">
                <!-- Empty State -->
                <div v-if="teams.data.length === 0" class="frosted-card p-12 text-center text-xs text-slate-600 bg-white">
                    <svg class="w-12 h-12 mx-auto mb-2.5 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <p class="font-bold text-slate-800 text-sm">Tidak Ada Tim dalam Antrean Screening</p>
                    <p class="mt-0.5 text-slate-600">Tidak ada tim yang cocok dengan kriteria pencarian atau filter yang dipilih.</p>
                </div>

                <!-- Team Cards -->
                <div
                    v-for="t in teams.data"
                    :key="t.id"
                    class="frosted-card p-4 sm:p-5 hover:border-orange-300 transition-colors duration-150 bg-white"
                >
                    <!-- Top Header: Team Logo, Name, Event & Screening Status -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3.5 mb-3.5 border-b border-slate-100">
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Team Logo / Badge Avatar -->
                            <div class="w-11 h-11 rounded-xl bg-orange-50 border border-orange-200 text-orange-800 flex items-center justify-center font-extrabold text-base uppercase shrink-0 overflow-hidden">
                                <img
                                    v-if="t.logo_url"
                                    :src="t.logo_url"
                                    :alt="`Logo ${t.name}`"
                                    width="44"
                                    height="44"
                                    class="w-full h-full object-cover"
                                />
                                <span v-else>{{ t.name.charAt(0) }}</span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-sm sm:text-base font-bold text-slate-900 truncate leading-tight">{{ t.name }}</h2>
                                    <span class="text-xs font-semibold text-slate-600 tabular-nums">#{{ t.id }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-semibold bg-orange-50 text-orange-800 border border-orange-200">
                                        {{ t.event.name }} &bull; {{ t.event.age_category }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 rounded-md text-xs font-semibold border"
                                        :class="getStatusBadge(t.screening_status).class"
                                    >
                                        {{ getStatusBadge(t.screening_status).label }}
                                    </span>
                                    <span v-if="t.origin_association" class="text-xs text-slate-600 font-medium">
                                        {{ t.origin_association }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="flex items-center gap-2 shrink-0 ml-auto sm:ml-0">
                            <Link
                                :href="route('admin.screening.team', { event: t.event.id, team: t.id })"
                                :class="t.screening_status === 'all_passed' ? 'btn-secondary text-xs flex items-center gap-1.5' : 'btn-pitch text-xs flex items-center gap-1.5'"
                            >
                                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>{{ t.screening_status === 'all_passed' ? 'Lihat Berkas Skuad' : 'Periksa Berkas Skuad' }}</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Card Body Grid: Verification Progress, Breakdown Stats & Contacts -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 text-xs items-center">
                        <!-- Verification Progress Bar & Breakdown (Col 1-7) -->
                        <div class="lg:col-span-7 space-y-2.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-700">
                                    Progres Verifikasi Skuad:
                                    <strong class="text-slate-900 tabular-nums">{{ t.stats.passed }} / {{ t.stats.total_players }} Pemain Sah</strong>
                                </span>
                                <span class="font-bold text-slate-800 tabular-nums">{{ t.stats.verified_percentage }}%</span>
                            </div>

                            <!-- Progress Track -->
                            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden flex">
                                <div
                                    class="bg-emerald-600 transition-all duration-300"
                                    :style="{ width: `${t.stats.total_players > 0 ? (t.stats.passed / t.stats.total_players) * 100 : 0}%` }"
                                    title="Pemain Sah"
                                ></div>
                                <div
                                    class="bg-amber-500 transition-all duration-300"
                                    :style="{ width: `${t.stats.total_players > 0 ? (t.stats.submitted / t.stats.total_players) * 100 : 0}%` }"
                                    title="Menunggu Verifikasi"
                                ></div>
                                <div
                                    class="bg-rose-500 transition-all duration-300"
                                    :style="{ width: `${t.stats.total_players > 0 ? (t.stats.rejected / t.stats.total_players) * 100 : 0}%` }"
                                    title="Perlu Revisi"
                                ></div>
                            </div>

                            <!-- Chips breakdown -->
                            <div class="flex flex-wrap items-center gap-2 pt-0.5">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-amber-50 text-amber-900 border border-amber-200">
                                    <strong class="font-bold tabular-nums">{{ t.stats.submitted }}</strong> Siap Diperiksa
                                </span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    <strong class="font-bold tabular-nums">{{ t.stats.passed }}</strong> Lolos Sah
                                </span>
                                <span v-if="t.stats.rejected > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-rose-50 text-rose-800 border border-rose-200">
                                    <strong class="font-bold tabular-nums">{{ t.stats.rejected }}</strong> Ditolak (Revisi)
                                </span>
                                <span v-if="t.stats.draft > 0" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700">
                                    <strong class="font-bold tabular-nums">{{ t.stats.draft }}</strong> Draf Manajer
                                </span>
                            </div>
                        </div>

                        <!-- Team Contacts & Manager (Col 8-12) -->
                        <div class="lg:col-span-5 flex flex-col justify-center space-y-1.5 sm:border-l sm:border-slate-100 sm:pl-4 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-600 font-medium">Manajer Tim</span>
                                <span class="font-bold text-slate-800">{{ t.manager_name }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-600 font-medium">WhatsApp</span>
                                <a
                                    v-if="t.manager_phone"
                                    :href="`https://wa.me/${t.manager_phone.replace(/[^0-9]/g, '')}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-emerald-700 hover:text-emerald-800 font-semibold tabular-nums"
                                >
                                    {{ t.manager_phone }} [Chat WA]
                                </a>
                                <span v-else class="text-slate-600">-</span>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-slate-600 font-medium">Pelatih Kepala</span>
                                <span class="font-medium text-slate-700">{{ t.head_coach_name || '-' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination Container -->
            <div v-if="teams.links && teams.links.length > 3" class="flex justify-center pt-2">
                <nav aria-label="Navigasi halaman tim" class="flex items-center gap-1">
                    <Component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, i) in teams.links"
                        :key="i"
                        :href="link.url"
                        class="px-3 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold transition-colors inline-flex items-center"
                        :class="
                            link.active
                                ? 'bg-orange-600 text-white'
                                : link.url
                                ? 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'
                                : 'bg-slate-100 text-slate-500 cursor-not-allowed'
                        "
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>
    </AdminLayout>
</template>

