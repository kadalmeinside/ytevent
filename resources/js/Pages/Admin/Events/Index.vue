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

            <!-- Content Container (Desktop Table + Mobile Cards) -->
            <div class="frosted-card overflow-hidden">
                <div v-if="events.length === 0" class="p-12 text-center text-xs text-slate-500">
                    <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p class="font-bold text-slate-800">Belum Ada Kompetisi Dibuat</p>
                    <p class="mt-0.5 text-slate-400">Klik tombol di atas untuk membuat kompetisi pertama Anda.</p>
                </div>

                <div v-else>
                    <!-- DESKTOP TABLE VIEW -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="table-th">Nama Kompetisi</th>
                                    <th class="table-th">Lokasi &amp; Kota</th>
                                    <th class="table-th">Kategori Usia</th>
                                    <th class="table-th">Kapasitas Kuota</th>
                                    <th class="table-th">Biaya Pendaftaran</th>
                                    <th class="table-th">Kick-Off</th>
                                    <th class="table-th">Status</th>
                                    <th class="table-th text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="evt in events" :key="evt.id" class="hover:bg-orange-50/25 transition-colors">
                                    <td class="table-td font-bold text-slate-900">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-xs shrink-0">
                                                🏆
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-slate-900">{{ evt.name }}</p>
                                                <p class="text-[11px] text-slate-500 font-normal">{{ evt.format_label }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="table-td text-slate-700">
                                        <p class="font-medium text-xs">{{ evt.venue_name }}</p>
                                        <p class="text-[11px] text-slate-500">{{ evt.city }}</p>
                                    </td>
                                    <td class="table-td font-semibold text-slate-800">
                                        <span class="font-mono text-xs px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                                            {{ evt.age_category }}
                                        </span>
                                    </td>
                                    <td class="table-td">
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-bold tabular-data text-slate-900">
                                                    {{ evt.occupied_slots }} / {{ evt.max_teams }} Tim
                                                </span>
                                                <span class="text-[10px] text-slate-500">
                                                    {{ Math.round((evt.occupied_slots / evt.max_teams) * 100) }}%
                                                </span>
                                            </div>
                                            <!-- Mini Progress Bar -->
                                            <div class="w-28 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                <div
                                                    class="h-full bg-gradient-to-r from-orange-500 to-red-600 rounded-full"
                                                    :style="{ width: `${Math.min(100, (evt.occupied_slots / evt.max_teams) * 100)}%` }"
                                                ></div>
                                            </div>
                                            <p class="text-[10px] text-slate-500 tabular-data">
                                                Lunas: {{ evt.active_teams }} tim &bull; Hold: {{ evt.payment_deadline_hours }}j
                                            </p>
                                        </div>
                                    </td>
                                    <td class="table-td font-bold tabular-data text-slate-900">
                                        {{ formatRupiah(evt.registration_fee) }}
                                    </td>
                                    <td class="table-td tabular-data text-slate-700">
                                        {{ formatDate(evt.match_start_date) }}
                                    </td>
                                    <td class="table-td">
                                        <StatusBadge :status="evt.status" />
                                    </td>
                                    <td class="table-td text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <Link
                                                :href="route('admin.teams.index', { event_id: evt.id })"
                                                class="btn-sm-secondary"
                                                title="Lihat Tim Terdaftar"
                                            >
                                                Tim ({{ evt.occupied_slots }})
                                            </Link>
                                            <Link
                                                :href="route('admin.events.edit', { event: evt.id })"
                                                class="btn-sm-pitch"
                                            >
                                                Edit
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE HIGH-DENSITY CARD LIST VIEW -->
                    <div class="md:hidden divide-y divide-slate-100">
                        <div
                            v-for="evt in events"
                            :key="evt.id"
                            class="p-4 hover:bg-orange-50/20 transition-colors space-y-3"
                        >
                            <!-- Top: Name, Age Category & Status -->
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-orange-100 text-orange-800">
                                            {{ evt.age_category }}
                                        </span>
                                        <span class="text-xs text-slate-500">{{ evt.format_label }}</span>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-900 mt-1">{{ evt.name }}</h4>
                                </div>
                                <StatusBadge :status="evt.status" />
                            </div>

                            <!-- Venue & Kick-off -->
                            <div class="text-xs text-slate-600 bg-slate-50/80 rounded-lg p-2.5 border border-slate-200/60 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Stadion / Venue:</span>
                                    <span class="font-semibold text-slate-900">{{ evt.venue_name }}, {{ evt.city }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">Kick-Off:</span>
                                    <span class="font-mono tabular-data font-medium text-slate-800">{{ formatDate(evt.match_start_date) }}</span>
                                </div>
                            </div>

                            <!-- Quota Progress & Fee -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-slate-500">Kuota Terisi:</span>
                                    <span class="font-mono font-bold text-slate-900 tabular-data">
                                        {{ evt.occupied_slots }} / {{ evt.max_teams }} Tim ({{ evt.active_teams }} Lunas)
                                    </span>
                                </div>
                                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full bg-gradient-to-r from-orange-500 to-red-600 rounded-full"
                                        :style="{ width: `${Math.min(100, (evt.occupied_slots / evt.max_teams) * 100)}%` }"
                                    ></div>
                                </div>
                            </div>

                            <!-- Bottom: Fee & Actions -->
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                <div>
                                    <span class="text-[10px] text-slate-400 block uppercase font-bold">Biaya Pendaftaran</span>
                                    <span class="font-mono tabular-data font-bold text-slate-900 text-sm">
                                        {{ formatRupiah(evt.registration_fee) }}
                                    </span>
                                </div>

                                <div class="flex items-center gap-1.5">
                                    <Link
                                        :href="route('admin.teams.index', { event_id: evt.id })"
                                        class="btn-sm-secondary"
                                    >
                                        Daftar Tim
                                    </Link>
                                    <Link
                                        :href="route('admin.events.edit', { event: evt.id })"
                                        class="btn-sm-pitch"
                                    >
                                        Edit
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
