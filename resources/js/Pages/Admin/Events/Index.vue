<script setup>
import { ref, computed } from 'vue';
import { Link, router, Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah, formatDate } = useFormatters();

const isSyncingAll = ref(false);
const syncingEventId = ref(null);
const activeFilter = ref('all');
const statusFilter = ref('all');
const searchQuery = ref('');

const teamEventsCount = computed(() => props.events.filter(e => e.participant_type !== 'individual').length);
const auditionEventsCount = computed(() => props.events.filter(e => e.participant_type === 'individual').length);
const openEventsCount = computed(() => props.events.filter(e => e.status === 'open').length);
const totalOccupiedSlots = computed(() => props.events.reduce((sum, e) => sum + Number(e.occupied_slots || 0), 0));

const hasActiveFilter = computed(() => {
    return activeFilter.value !== 'all' || statusFilter.value !== 'all' || searchQuery.value.trim() !== '';
});

const resetFilters = () => {
    activeFilter.value = 'all';
    statusFilter.value = 'all';
    searchQuery.value = '';
};

const filteredEvents = computed(() => {
    let list = props.events;
    if (activeFilter.value === 'team') {
        list = list.filter(e => e.participant_type !== 'individual');
    } else if (activeFilter.value === 'individual') {
        list = list.filter(e => e.participant_type === 'individual');
    }

    if (statusFilter.value !== 'all') {
        list = list.filter(e => e.status === statusFilter.value);
    }

    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter(e =>
            (e.name || '').toLowerCase().includes(q) ||
            (e.venue_name || '').toLowerCase().includes(q) ||
            (e.city || '').toLowerCase().includes(q) ||
            (e.age_category || '').toLowerCase().includes(q)
        );
    }

    return list;
});

const quotaBarPercent = (evt) => {
    const cap = evt.participant_type === 'individual' ? (evt.max_participants || 100) : (evt.max_teams || 32);
    if (!cap) return 0;
    return Math.min(100, Math.round((Number(evt.occupied_slots || 0) / cap) * 100));
};

const triggerResyncAll = () => {
    isSyncingAll.value = true;
    router.post(route('admin.events.resync-slots'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncingAll.value = false;
        },
    });
};

const triggerResyncEvent = (evt) => {
    syncingEventId.value = evt.id;
    router.post(route('admin.events.resync-event-slots', { event: evt.id }), {}, {
        preserveScroll: true,
        onFinish: () => {
            syncingEventId.value = null;
        },
    });
};

const confirmTarget = ref(null);
const confirmMode = ref('duplicate'); // 'duplicate' | 'delete'
const confirmProcessing = ref(false);

const openDuplicateModal = (evt) => {
    confirmTarget.value = evt;
    confirmMode.value = 'duplicate';
};

const openDeleteModal = (evt) => {
    confirmTarget.value = evt;
    confirmMode.value = 'delete';
};

const closeConfirmModal = () => {
    if (confirmProcessing.value) return;
    confirmTarget.value = null;
};

const executeConfirmAction = () => {
    if (!confirmTarget.value || confirmProcessing.value) return;
    confirmProcessing.value = true;
    const evt = confirmTarget.value;

    if (confirmMode.value === 'duplicate') {
        router.post(route('admin.events.duplicate', { event: evt.id }), {}, {
            onFinish: () => {
                confirmProcessing.value = false;
                confirmTarget.value = null;
            },
        });
    } else {
        router.delete(route('admin.events.destroy', { event: evt.id }), {
            preserveScroll: true,
            onFinish: () => {
                confirmProcessing.value = false;
                confirmTarget.value = null;
            },
        });
    }
};
</script>

<template>
    <AdminLayout page-title="Manajemen Event &amp; Kompetisi" subtitle="Kelola seluruh turnamen tim beregu, audisi bakat individual, kuota peserta, dan regulasi.">
        <Head title="Manajemen Event &amp; Kompetisi" />

        <div class="space-y-5">
            <!-- Operational Event Summary Strip -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <!-- Card 1: Event Aktif -->
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-emerald-50/90 p-4 sm:p-5 hover:border-emerald-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-emerald-950/[0.04] transition-all duration-300 ease-out flex flex-col justify-between">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none select-none absolute -right-2 top-2 w-20 h-20 text-emerald-500/[0.11] transition-all duration-500 ease-out group-hover:text-emerald-500/[0.20] group-hover:scale-110 group-hover:-translate-x-1.5 group-hover:-translate-y-1 group-hover:-rotate-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="relative z-10">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 block">Event Aktif (Open)</span>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data font-mono tracking-tight mt-1.5">{{ openEventsCount }}</p>
                    </div>
                    <div class="relative z-10 mt-3 pt-2.5 border-t border-emerald-200/50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-600">Dari total:</span>
                        <strong class="text-emerald-800 font-bold tabular-data">{{ events.length }} event</strong>
                    </div>
                </div>

                <!-- Card 2: Turnamen Tim -->
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-orange-50/90 p-4 sm:p-5 hover:border-orange-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-orange-950/[0.04] transition-all duration-300 ease-out flex flex-col justify-between">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none select-none absolute -right-2 top-2 w-20 h-20 text-orange-500/[0.11] transition-all duration-500 ease-out group-hover:text-orange-500/[0.20] group-hover:scale-110 group-hover:-translate-x-1.5 group-hover:-translate-y-1 group-hover:rotate-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <div class="relative z-10">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 block">Turnamen Tim</span>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data font-mono tracking-tight mt-1.5">{{ teamEventsCount }}</p>
                    </div>
                    <div class="relative z-10 mt-3 pt-2.5 border-t border-orange-200/50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-600">Kategori:</span>
                        <strong class="text-orange-800 font-bold">Kompetisi beregu</strong>
                    </div>
                </div>

                <!-- Card 3: Event Perorangan -->
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-amber-50/90 p-4 sm:p-5 hover:border-amber-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-amber-950/[0.04] transition-all duration-300 ease-out flex flex-col justify-between">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none select-none absolute -right-2 top-2 w-20 h-20 text-amber-500/[0.11] transition-all duration-500 ease-out group-hover:text-amber-500/[0.21] group-hover:scale-110 group-hover:-translate-x-1.5 group-hover:-translate-y-1 group-hover:-rotate-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <div class="relative z-10">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 block">Event Perorangan</span>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data font-mono tracking-tight mt-1.5">{{ auditionEventsCount }}</p>
                    </div>
                    <div class="relative z-10 mt-3 pt-2.5 border-t border-amber-200/50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-600">Program:</span>
                        <strong class="text-amber-900 font-bold">Audisi &amp; clinic</strong>
                    </div>
                </div>

                <!-- Card 4: Total Slot Terisi -->
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-sky-50/90 p-4 sm:p-5 hover:border-sky-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-sky-950/[0.04] transition-all duration-300 ease-out flex flex-col justify-between">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none select-none absolute -right-2 top-2 w-20 h-20 text-sky-600/[0.10] transition-all duration-500 ease-out group-hover:text-sky-600/[0.19] group-hover:scale-110 group-hover:-translate-x-1.5 group-hover:-translate-y-1 group-hover:rotate-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <div class="relative z-10">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-600 block">Total Slot Terisi</span>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data font-mono tracking-tight mt-1.5">{{ totalOccupiedSlots }}</p>
                    </div>
                    <div class="relative z-10 mt-3 pt-2.5 border-t border-sky-200/50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-600">Terdaftar:</span>
                        <strong class="text-sky-900 font-bold">Tim &amp; atlet aktif</strong>
                    </div>
                </div>
            </div>

            <!-- Action & Filter Bar -->
            <div class="frosted-card p-3.5 bg-white space-y-3">
                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
                    <!-- Filter Tabs -->
                    <div class="flex flex-wrap items-center gap-1.5 p-1 bg-slate-100 rounded-xl border border-slate-200/80 w-fit" role="tablist" aria-label="Filter tipe event">
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeFilter === 'all'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition min-h-[36px]"
                            :class="activeFilter === 'all' ? 'bg-white text-orange-700 shadow-sm' : 'text-slate-700 hover:text-slate-900'"
                            @click="activeFilter = 'all'"
                        >
                            Semua ({{ events.length }})
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeFilter === 'team'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 min-h-[36px]"
                            :class="activeFilter === 'team' ? 'bg-white text-orange-700 shadow-sm' : 'text-slate-700 hover:text-slate-900'"
                            @click="activeFilter = 'team'"
                        >
                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span>Turnamen Tim ({{ teamEventsCount }})</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="activeFilter === 'individual'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 min-h-[36px]"
                            :class="activeFilter === 'individual' ? 'bg-white text-orange-700 shadow-sm' : 'text-slate-700 hover:text-slate-900'"
                            @click="activeFilter = 'individual'"
                        >
                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Event Perorangan ({{ auditionEventsCount }})</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            class="btn-secondary text-xs inline-flex items-center gap-1.5"
                            :disabled="isSyncingAll"
                            title="Pindai seluruh pendaftaran yang telah melewati batas waktu pembayaran untuk melepas kuota slot"
                            @click="triggerResyncAll"
                        >
                            <svg
                                class="w-4 h-4 text-orange-600 shrink-0"
                                :class="{ 'animate-spin': isSyncingAll }"
                                aria-hidden="true"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>{{ isSyncingAll ? 'Menyinkronkan...' : 'Resync Kuota' }}</span>
                        </button>

                        <Link
                            :href="route('admin.events.create', { participant_type: 'team' })"
                            class="btn-secondary text-xs inline-flex items-center gap-1.5 font-bold border-slate-300"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-700" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Turnamen Tim</span>
                        </Link>

                        <Link
                            :href="route('admin.events.create', { participant_type: 'individual' })"
                            class="btn-pitch text-xs inline-flex items-center gap-1.5 font-bold shadow-sm"
                        >
                            <svg class="w-3.5 h-3.5 text-white" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Event Perorangan</span>
                        </Link>
                    </div>
                </div>

                <!-- Search & Status Filter Row -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 pt-2.5 border-t border-slate-100">
                    <div class="relative flex-1">
                        <label for="events-search-input" class="sr-only">Cari event, venue, kota, atau kategori usia</label>
                        <input
                            id="events-search-input"
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari nama event, stadion/venue, kota, atau kategori usia..."
                            class="input-field text-xs pl-9"
                        />
                        <svg class="w-4 h-4 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <div class="flex items-center gap-2">
                        <label for="events-status-filter" class="sr-only">Filter status event</label>
                        <select id="events-status-filter" v-model="statusFilter" class="select-compact w-full sm:w-44">
                            <option value="all">Semua Status</option>
                            <option value="open">Dibuka (Open)</option>
                            <option value="closed">Ditutup (Closed)</option>
                            <option value="draft">Draft</option>
                            <option value="completed">Selesai (Completed)</option>
                            <option value="cancelled">Dibatalkan (Batal)</option>
                        </select>

                        <button
                            v-if="hasActiveFilter"
                            type="button"
                            class="btn-sm-secondary shrink-0"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Container (Responsive Card List View) -->
            <div class="space-y-3.5">
                <div v-if="filteredEvents.length === 0" class="frosted-card p-10 text-center text-xs text-slate-600 space-y-2.5">
                    <svg class="w-11 h-11 mx-auto text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <template v-if="events.length === 0">
                        <p class="font-bold text-slate-900 text-sm">Belum Ada Event Tersedia</p>
                        <p class="text-slate-600">Buat event pertama dengan menekan "Turnamen Tim" atau "Event Perorangan" di atas.</p>
                    </template>
                    <template v-else>
                        <p class="font-bold text-slate-900 text-sm">Tidak Ada Event Sesuai Filter</p>
                        <p class="text-slate-600">Coba ubah kata kunci pencarian atau filter status untuk melihat daftar event lainnya.</p>
                        <button type="button" class="btn-sm-secondary mt-1" @click="resetFilters">
                            Tampilkan Semua Event
                        </button>
                    </template>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="evt in filteredEvents"
                        :key="evt.id"
                        class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white p-4 sm:p-5 hover:-translate-y-0.5 hover:shadow-md transition-all duration-300 ease-out"
                        :class="evt.participant_type === 'individual' ? 'to-amber-50/45 hover:border-amber-300' : 'to-orange-50/45 hover:border-orange-300'"
                    >
                        <!-- Card Header -->
                        <div class="relative z-10 flex flex-wrap items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-200/70">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-xl bg-gradient-to-br flex items-center justify-center shadow-sm shrink-0 border transition-transform duration-300 ease-out group-hover:scale-110 group-hover:-rotate-6"
                                    :class="evt.participant_type === 'individual' ? 'from-amber-100 to-amber-50 text-amber-700 border-amber-200' : 'from-orange-100 to-orange-50 text-orange-700 border-orange-200'"
                                >
                                    <svg v-if="evt.participant_type === 'individual'" class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <Link
                                            :href="route('admin.events.show', { event: evt.id })"
                                            class="text-sm sm:text-base font-bold text-slate-900 hover:text-orange-700 transition-colors leading-tight"
                                        >
                                            {{ evt.name }}
                                        </Link>
                                        <span
                                            class="px-2 py-0.5 text-[10px] font-bold rounded-md border"
                                            :class="evt.participant_type === 'individual' ? (evt.event_type === 'coaching_clinic' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-purple-50 text-purple-900 border-purple-200') : 'bg-orange-50 text-orange-900 border-orange-200'"
                                        >
                                            {{ evt.participant_type === 'individual' ? (evt.event_type === 'coaching_clinic' ? 'Coaching Clinic' : (evt.event_type === 'audition' ? 'Audisi Atlet' : 'Perorangan')) : 'Turnamen Tim' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5 text-xs">
                                        <span v-if="evt.participant_type !== 'individual'" class="font-semibold text-slate-700">
                                            {{ evt.format_label }}
                                        </span>
                                        <span v-else class="font-medium text-slate-600">
                                            {{ evt.event_type === 'coaching_clinic' ? 'Masterclass & Workshop' : 'Panel Scouting' }}
                                        </span>
                                        <span class="text-slate-400" aria-hidden="true">&bull;</span>
                                        <span class="font-mono text-[11px] font-bold px-1.5 py-0.2 rounded bg-slate-100 text-slate-800 border border-slate-200">
                                            {{ evt.age_category }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <StatusBadge :status="evt.status" />
                            </div>
                        </div>

                        <!-- Card Body Grid -->
                        <div class="relative z-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 text-xs items-center">
                            <!-- Venue & Location (Col 1-3) -->
                            <div class="lg:col-span-3 space-y-1">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-600 block">Lokasi Lapangan</span>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm">{{ evt.venue_name }}</p>
                                    <a
                                        v-if="evt.venue_maps_url"
                                        :href="evt.venue_maps_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-orange-50 text-orange-800 hover:bg-orange-100 border border-orange-200 transition-colors shadow-sm"
                                        :aria-label="`Buka lokasi ${evt.venue_name} di Google Maps`"
                                    >
                                        <svg class="w-2.5 h-2.5 text-orange-700 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Maps</span>
                                    </a>
                                </div>
                                <p class="text-slate-600 text-xs">{{ evt.city }}</p>
                            </div>

                            <!-- Quota Slot Progress (Col 5-7) -->
                            <div class="lg:col-span-4 space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-600">Kuota Terisi</span>
                                    <span v-if="evt.quota_type === 'unlimited'" class="font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded text-[11px] border border-emerald-200">
                                        Buka s.d. Batas Waktu ({{ evt.occupied_slots }} {{ evt.participant_type === 'individual' ? 'atlet' : 'tim' }})
                                    </span>
                                    <span v-else class="font-bold tabular-data text-slate-900 font-mono">
                                        {{ evt.occupied_slots }} / {{ evt.participant_type === 'individual' ? (evt.max_participants || 100) + ' Atlet' : (evt.max_teams || 32) + ' Tim' }}
                                    </span>
                                </div>
                                <div v-if="evt.quota_type !== 'unlimited'" class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="quotaBarPercent(evt) >= 85 ? 'bg-gradient-to-r from-amber-500 to-rose-600' : 'bg-gradient-to-r from-orange-500 to-amber-500'"
                                        :style="{ width: `${quotaBarPercent(evt)}%` }"
                                    ></div>
                                </div>
                                <p class="text-[11px] text-slate-600 tabular-data">
                                    Lunas: <strong class="text-slate-900">{{ evt.active_teams || evt.occupied_slots }} {{ evt.participant_type === 'individual' ? 'atlet' : 'tim' }}</strong> &bull; Batas bayar: {{ evt.payment_deadline_hours }} jam
                                </p>
                            </div>

                            <!-- Fee & Kick-off (Col 8-10) -->
                            <div class="lg:col-span-2 space-y-1">
                                <span class="text-[10px] uppercase font-bold tracking-wider text-slate-600 block">Biaya &amp; Tanggal</span>
                                <span class="font-bold text-slate-900 tabular-data font-mono block text-xs sm:text-sm">
                                    {{ formatRupiah(evt.registration_fee) }}
                                </span>
                                <span class="text-[11px] text-slate-600 block">
                                    {{ formatDate(evt.match_start_date) }}
                                </span>
                            </div>

                            <!-- Actions (Col 10-12) -->
                            <div class="lg:col-span-3 flex items-center justify-end gap-1.5 flex-wrap">
                                <Link
                                    :href="route('admin.events.show', { event: evt.id })"
                                    class="btn-sm-pitch inline-flex items-center gap-1.5 justify-center shadow-sm"
                                    title="Buka workspace detail manajemen event"
                                >
                                    <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                    </svg>
                                    <span>Kelola Event</span>
                                </Link>

                                <Link
                                    v-if="evt.participant_type !== 'individual'"
                                    :href="route('admin.events.tournament', { event: evt.id })"
                                    class="btn-sm-secondary inline-flex items-center gap-1 justify-center"
                                    title="Buka Pusat Kendali Turnamen, Grup &amp; Skor"
                                >
                                    <span>Turnamen</span>
                                </Link>

                                <Link
                                    :href="route('admin.events.edit', { event: evt.id })"
                                    class="btn-sm-secondary inline-flex items-center gap-1 justify-center"
                                    title="Edit konfigurasi dan regulasi event"
                                >
                                    <svg class="w-3.5 h-3.5 text-slate-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    <span>Edit</span>
                                </Link>

                                <button
                                    type="button"
                                    :aria-label="`Sinkronkan kuota slot untuk ${evt.name}`"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:text-orange-700 hover:border-orange-300 transition-colors bg-white shrink-0"
                                    :class="{ 'opacity-50 cursor-not-allowed': syncingEventId === evt.id }"
                                    :disabled="syncingEventId === evt.id"
                                    title="Cek &amp; resync slot kedaluwarsa khusus event ini"
                                    @click="triggerResyncEvent(evt)"
                                >
                                    <svg
                                        class="w-4 h-4 text-orange-600"
                                        :class="{ 'animate-spin': syncingEventId === evt.id }"
                                        aria-hidden="true"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>

                                <button
                                    type="button"
                                    :aria-label="`Duplikasi event ${evt.name} menjadi Draft baru`"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:text-orange-700 hover:border-orange-300 transition-colors bg-white shrink-0"
                                    title="Duplikasi Event (Salin Konfigurasi ke Draft)"
                                    @click="openDuplicateModal(evt)"
                                >
                                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>

                                <button
                                    v-if="(evt.total_registrants || 0) === 0 && Number(evt.occupied_slots || 0) === 0"
                                    type="button"
                                    :aria-label="`Hapus draft event ${evt.name}`"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-rose-200 text-rose-700 hover:bg-rose-50 hover:border-rose-300 transition-colors bg-white shrink-0"
                                    title="Hapus Draft Event (belum ada pendaftar)"
                                    @click="openDeleteModal(evt)"
                                >
                                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>

                                <Link
                                    :href="route('events.show', { event: evt.slug })"
                                    target="_blank"
                                    :aria-label="`Buka halaman publik ${evt.name}`"
                                    class="w-9 h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:text-orange-700 hover:border-orange-300 transition-colors bg-white shrink-0"
                                    title="Buka Halaman Publik"
                                >
                                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="Boolean(confirmTarget)"
            :title="confirmMode === 'delete' ? 'Hapus Draft Event?' : 'Duplikasi Konfigurasi Event?'"
            :description="confirmTarget ? (confirmMode === 'delete'
                ? `Event &quot;${confirmTarget.name}&quot; belum memiliki pendaftar dan akan dihapus dari daftar aktif. Lanjutkan penghapusan?`
                : `Konfigurasi regulasi, sesi, dan tahapan dari &quot;${confirmTarget.name}&quot; akan disalin menjadi event Draft baru tanpa menyalin data pendaftar.`) : ''"
            :confirm-text="confirmMode === 'delete' ? 'Ya, Hapus Draft' : 'Ya, Duplikasi Event'"
            :danger="confirmMode === 'delete'"
            :processing="confirmProcessing"
            @confirm="executeConfirmAction"
            @close="closeConfirmModal"
        />
    </AdminLayout>
</template>
