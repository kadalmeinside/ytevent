<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
    filterState.status = filterState.status === status ? '' : status;
    applyFilters();
};
</script>

<template>
    <AdminLayout page-title="Verifikasi Screening Peserta Perorangan">
        <Head title="Verifikasi Screening Perorangan" />

        <div class="space-y-6">
            <!-- Header & Switcher Tab Turnamen vs Audisi -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <span>Screening Keabsahan Peserta</span>
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-600 mt-1">
                        Pemeriksaan berkas administrasi, validasi batas usia, dan aktivasi Digital Pass (E-Tiket) peserta audisi &amp; coaching clinic.
                    </p>
                </div>

                <!-- Navigation Tabs -->
                <nav aria-label="Tipe Antrean Screening" class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200 self-start sm:self-auto">
                    <Link
                        :href="route('admin.screening.index')"
                        class="px-3.5 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold text-slate-700 hover:text-slate-900 transition-colors inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Tim Turnamen</span>
                    </Link>
                    <Link
                        :href="route('admin.screening.auditions')"
                        aria-current="page"
                        class="px-3.5 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-bold bg-white text-orange-700 border border-slate-200 inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-orange-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>Peserta Perorangan</span>
                    </Link>
                </nav>
            </div>

            <!-- Asymmetric Metric Cards (Focal Queue Button + Secondary Strip) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 sm:gap-4">
                <button
                    type="button"
                    :aria-pressed="filterState.status === 'submitted'"
                    class="lg:col-span-5 frosted-card p-5 text-left border-orange-200 bg-orange-50/40 hover:border-orange-400 transition-colors flex items-center justify-between gap-4 min-h-[44px]"
                    :class="{ 'ring-2 ring-orange-500 border-orange-500': filterState.status === 'submitted' }"
                    @click="setStatusFilter('submitted')"
                >
                    <div>
                        <span class="text-xs font-bold text-orange-800 block">Antrean Menunggu Review</span>
                        <p class="text-3xl font-black text-slate-900 mt-1 tabular-nums">{{ counts.submitted || 0 }}</p>
                        <span class="text-xs text-slate-700 block mt-0.5">Klik untuk filter berkas diajukan</span>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white border border-orange-200 text-orange-800 shrink-0">
                        {{ filterState.status === 'submitted' ? 'Filter Aktif' : 'Filter Antrean' }}
                    </span>
                </button>

                <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div class="frosted-card p-4 flex flex-col justify-between">
                        <span class="text-xs font-semibold text-slate-600">Total Atlet</span>
                        <p class="text-2xl font-black text-slate-900 mt-1 tabular-nums">{{ counts.total || 0 }}</p>
                    </div>
                    <button
                        type="button"
                        :aria-pressed="filterState.status === 'passed'"
                        class="frosted-card p-4 text-left hover:border-emerald-400 transition-colors min-h-[44px] flex flex-col justify-between"
                        :class="{ 'ring-2 ring-emerald-500 border-emerald-500': filterState.status === 'passed' }"
                        @click="setStatusFilter('passed')"
                    >
                        <span class="text-xs font-semibold text-emerald-800">Lolos (Sah)</span>
                        <p class="text-2xl font-black text-emerald-800 mt-1 tabular-nums">{{ counts.passed || 0 }}</p>
                    </button>
                    <button
                        type="button"
                        :aria-pressed="filterState.status === 'rejected'"
                        class="frosted-card p-4 text-left hover:border-rose-400 transition-colors min-h-[44px] flex flex-col justify-between"
                        :class="{ 'ring-2 ring-rose-500 border-rose-500': filterState.status === 'rejected' }"
                        @click="setStatusFilter('rejected')"
                    >
                        <span class="text-xs font-semibold text-rose-800">Perlu Revisi</span>
                        <p class="text-2xl font-black text-rose-800 mt-1 tabular-nums">{{ counts.rejected || 0 }}</p>
                    </button>
                </div>
            </div>

            <!-- Filters Area -->
            <div class="frosted-card p-4 sm:p-5 flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                <div class="flex flex-wrap items-center gap-2 flex-1">
                    <!-- Search Input -->
                    <div class="w-full sm:w-64">
                        <label for="auditions-search" class="sr-only">Cari nama atlet, NIK, atau nomor registrasi</label>
                        <input
                            id="auditions-search"
                            v-model="filterState.search"
                            type="text"
                            placeholder="Cari atlet, NIK, No. Reg..."
                            class="input-field text-xs"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- Event Select -->
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <label for="auditions-event" class="sr-only">Filter event perorangan</label>
                        <select
                            id="auditions-event"
                            v-model="filterState.event_id"
                            class="input-field text-xs w-full sm:w-56"
                            @change="applyFilters"
                        >
                            <option value="">Semua Event Perorangan</option>
                            <option v-for="evt in events" :key="evt.id" :value="evt.id">
                                {{ evt.name }} ({{ evt.age_category }})
                            </option>
                        </select>
                        <Link
                            v-if="filterState.event_id"
                            :href="route('admin.screening.auditions.review', { event: filterState.event_id })"
                            class="btn-pitch text-xs shrink-0 inline-flex items-center gap-1.5"
                        >
                            <span>Buka Split-View Event</span>
                        </Link>
                        <Link
                            v-if="filterState.event_id"
                            :href="route('admin.events.show', { event: filterState.event_id })"
                            class="btn-secondary text-xs shrink-0 inline-flex items-center gap-1.5"
                        >
                            <span>Kelola Event</span>
                        </Link>
                    </div>

                    <!-- Status Select -->
                    <label for="auditions-status" class="sr-only">Filter status screening</label>
                    <select
                        id="auditions-status"
                        v-model="filterState.status"
                        class="input-field text-xs w-full sm:w-44"
                        @change="applyFilters"
                    >
                        <option value="">Semua Status</option>
                        <option value="submitted">Menunggu Review</option>
                        <option value="passed">Lolos (Sah)</option>
                        <option value="rejected">Perlu Revisi</option>
                    </select>

                    <button
                        type="button"
                        class="btn-pitch text-xs"
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
                    class="frosted-card p-5 hover:border-orange-300 transition-colors border-slate-200/90"
                >
                    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                        <!-- Left: Photo & Athlete Profile -->
                        <div class="flex items-start gap-4 min-w-0 flex-1">
                            <img
                                v-if="p.photo_url"
                                :src="p.photo_url"
                                :alt="`Foto ${p.full_name}`"
                                width="56"
                                height="56"
                                class="w-14 h-14 rounded-xl object-cover border border-slate-200 shrink-0"
                            />
                            <div
                                v-else
                                class="w-14 h-14 rounded-xl bg-orange-600 text-white font-black text-base flex items-center justify-center shrink-0"
                            >
                                {{ p.full_name.substring(0, 2).toUpperCase() }}
                            </div>

                            <div class="min-w-0 space-y-1.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-base font-black text-slate-900 truncate">
                                        {{ p.full_name }}
                                        <span v-if="p.nickname" class="text-xs font-semibold text-slate-600">({{ p.nickname }})</span>
                                    </h3>
                                    <span
                                        v-if="p.can_download_bib || p.bib_number"
                                        class="px-2 py-0.5 rounded-md text-xs font-bold bg-orange-50 text-orange-800 border border-orange-200"
                                    >
                                        Digital Pass Aktif<template v-if="p.bib_number"> &middot; Rompi #{{ p.bib_number }}</template>
                                    </span>
                                    <span v-else class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                                        Tiket Belum Terbit
                                    </span>
                                </div>

                                <p class="text-xs font-semibold text-orange-700">
                                    <Link
                                        v-if="p.event?.id"
                                        :href="route('admin.events.show', { event: p.event.id })"
                                        class="hover:underline"
                                    >
                                        {{ p.event?.name }}
                                    </Link>
                                    <span v-else>{{ p.event?.name }}</span>
                                    <span class="text-slate-600 font-normal"> ({{ p.event?.age_category }})</span>
                                    <span v-if="p.session_name" class="text-slate-600 font-normal"> &middot; {{ p.session_name }}</span>
                                </p>

                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-700 pt-0.5">
                                    <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-xs font-semibold text-slate-800">NIK: {{ p.nik || '-' }}</span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span>Lahir: <strong>{{ p.birth_date || '-' }}</strong></span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span>Posisi: <strong>{{ p.preferred_position || '-' }}</strong></span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span>Kaki: <strong class="capitalize">{{ p.dominant_foot || '-' }}</strong></span>
                                    <span aria-hidden="true">&middot;</span>
                                    <span>Asal: <strong>{{ p.current_club_ssb || 'Independen' }}</strong></span>
                                </div>

                                <!-- Status & Progress Pills -->
                                <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                    <span
                                        class="px-2 py-0.5 rounded-md text-xs font-semibold border"
                                        :class="p.is_paid ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200'"
                                    >
                                        {{ p.is_paid ? 'Pembayaran Lunas' : 'Menunggu Pembayaran' }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 rounded-md text-xs font-semibold border"
                                        :class="p.is_age_valid ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'"
                                    >
                                        {{ p.is_age_valid ? 'Sah Sesuai Usia' : 'Cek Batas Usia' }}
                                    </span>
                                    <span
                                        v-if="p.docs_total > 0"
                                        class="px-2 py-0.5 rounded-md text-xs font-semibold border"
                                        :class="
                                            p.docs_invalid > 0
                                                ? 'bg-rose-50 text-rose-800 border-rose-200'
                                                : p.docs_valid === p.docs_total
                                                  ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                  : 'bg-slate-50 text-slate-700 border-slate-200'
                                        "
                                    >
                                        {{ p.docs_valid || 0 }} / {{ p.docs_total }} Berkas Sah
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Screening Status & Split-View Workstation CTA -->
                        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
                            <span
                                class="px-2.5 py-1 rounded-lg text-xs font-bold border"
                                :class="
                                    p.screening_status === 'passed'
                                        ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                        : p.screening_status === 'rejected'
                                          ? 'bg-rose-50 text-rose-800 border-rose-300'
                                          : 'bg-amber-50 text-amber-900 border-amber-300'
                                "
                            >
                                {{ p.screening_status_label }}
                            </span>

                            <Link
                                v-if="p.event?.id"
                                :href="`${route('admin.screening.auditions.review', { event: p.event.id })}?participant_id=${p.id}`"
                                :class="p.screening_status === 'passed' ? 'btn-secondary text-xs inline-flex items-center gap-1.5' : 'btn-pitch text-xs inline-flex items-center gap-1.5'"
                            >
                                <svg v-if="p.screening_status === 'passed'" class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ p.screening_status === 'passed' ? 'Lihat Berkas (Split-View)' : 'Periksa Berkas (Split-View)' }}</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Rejection Reason Warning -->
                    <div v-if="p.screening_status === 'rejected' && p.screening_notes" class="mt-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900">
                        <strong class="font-bold">Alasan Penolakan / Catatan Petugas:</strong>
                        <p class="mt-0.5">{{ p.screening_notes }}</p>
                    </div>
                </div>
            </div>

            <div v-else class="frosted-card p-12 text-center text-slate-600 text-sm">
                Tidak ada data peserta audisi yang cocok dengan kriteria filter saat ini.
            </div>

            <!-- Pagination Container -->
            <div v-if="participants.links && participants.links.length > 3" class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-2">
                <p class="text-xs text-slate-600 tabular-nums">
                    Menampilkan <strong class="text-slate-900">{{ participants.from || 0 }}&ndash;{{ participants.to || 0 }}</strong> dari
                    <strong class="text-slate-900">{{ participants.total || 0 }}</strong> peserta
                </p>
                <nav aria-label="Navigasi halaman peserta" class="flex flex-wrap items-center justify-center gap-1">
                    <Component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, i) in participants.links"
                        :key="i"
                        :href="link.url"
                        preserve-scroll
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

