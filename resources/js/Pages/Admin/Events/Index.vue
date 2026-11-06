<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah, formatDate } = useFormatters();
</script>

<template>
    <AdminLayout page-title="Manajemen Kompetisi" subtitle="Kelola seluruh turnamen, liga, batasan kuota tim, batas usia kelahiran, dan biaya pendaftaran.">
        <Head title="Manajemen Event" />

        <div class="space-y-5">
            <!-- Action Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-xs text-slate-500 font-medium">Total terdaftar {{ events.length }} event kompetisi.</p>
                <Link :href="route('admin.events.create')" class="btn-pitch">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Buat Kompetisi Baru</span>
                </Link>
            </div>

            <!-- Content Container (Responsive Card List View) -->
            <div class="space-y-3.5">
                <div v-if="events.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p class="font-bold text-slate-800">Belum Ada Kompetisi Dibuat</p>
                    <p class="mt-0.5 text-slate-400">Klik tombol di atas untuk membuat kompetisi pertama Anda.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="evt in events"
                        :key="evt.id"
                        class="frosted-card p-4 sm:p-5 hover:border-orange-300/80 hover:shadow-md transition-all duration-150 bg-white"
                    >
                        <!-- Card Header -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-base shadow-2xs shrink-0 border border-orange-200">
                                    🏆
                                </div>
                                <div>
                                    <h4 class="text-sm sm:text-base font-bold text-slate-900 leading-tight">{{ evt.name }}</h4>
                                    <div class="flex items-center gap-2 mt-0.5 text-xs">
                                        <span class="font-semibold text-slate-600">{{ evt.format_label }}</span>
                                        <span class="text-slate-300">&bull;</span>
                                        <span class="font-mono text-[11px] font-bold px-1.5 py-0.2 rounded bg-orange-50 text-orange-800 border border-orange-200">
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 text-xs items-center">
                            <!-- Venue & Location (Col 1-4) -->
                            <div class="lg:col-span-4 space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Stadion &amp; Kota</span>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm">{{ evt.venue_name }}</p>
                                    <a
                                        v-if="evt.venue_maps_url"
                                        :href="evt.venue_maps_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 transition-colors shadow-2xs"
                                        title="Buka di Google Maps"
                                    >
                                        <svg class="w-2.5 h-2.5 text-orange-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Maps &nearr;
                                    </a>
                                </div>
                                <p class="text-slate-500 text-xs">{{ evt.city }}</p>
                            </div>

                            <!-- Quota Slot Progress (Col 5-7) -->
                            <div class="lg:col-span-4 space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[10px] uppercase font-bold text-slate-400">Kuota Terisi</span>
                                    <span class="font-bold tabular-data text-slate-900 font-mono">
                                        {{ evt.occupied_slots }} / {{ evt.max_teams }} Tim ({{ Math.round((evt.occupied_slots / evt.max_teams) * 100) }}%)
                                    </span>
                                </div>
                                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-gradient-to-r from-orange-500 to-red-600 rounded-full transition-all duration-300"
                                        :style="{ width: `${Math.min(100, (evt.occupied_slots / evt.max_teams) * 100)}%` }"
                                    ></div>
                                </div>
                                <p class="text-[11px] text-slate-500 tabular-data">
                                    Lunas: <strong class="text-slate-800">{{ evt.active_teams }} tim</strong> &bull; Batas bayar: {{ evt.payment_deadline_hours }} jam
                                </p>
                            </div>

                            <!-- Fee & Kick-off (Col 8-10) -->
                            <div class="lg:col-span-2 space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Biaya &amp; Kick-Off</span>
                                <span class="font-bold text-slate-900 tabular-data font-mono block text-xs sm:text-sm">
                                    {{ formatRupiah(evt.registration_fee) }}
                                </span>
                                <span class="text-[11px] text-slate-500 block">
                                    {{ formatDate(evt.match_start_date) }}
                                </span>
                            </div>

                            <!-- Actions (Col 11-12) -->
                            <div class="lg:col-span-2 flex flex-col sm:items-end justify-center gap-2">
                                <Link
                                    :href="route('admin.teams.index', { event_id: evt.id })"
                                    class="btn-sm-secondary w-full sm:w-auto justify-center"
                                >
                                    Daftar Tim ({{ evt.occupied_slots }})
                                </Link>
                                <div class="flex items-center gap-1.5 w-full sm:w-auto justify-end">
                                    <Link
                                        :href="route('events.show', { event: evt.slug })"
                                        target="_blank"
                                        class="p-1.5 text-slate-500 hover:text-orange-600 transition-colors"
                                        title="Buka Halaman Publik"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </Link>
                                    <Link
                                        :href="route('admin.events.edit', { event: evt.id })"
                                        class="btn-sm-pitch w-full sm:w-auto justify-center"
                                    >
                                        Edit Kompetisi
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
