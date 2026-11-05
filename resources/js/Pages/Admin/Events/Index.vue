<script setup>
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
            <div class="flex items-center justify-between">
                <p class="text-xs text-slate-500">Total terdaftar {{ events.length }} event kompetisi.</p>
                <Link :href="route('admin.events.create')" class="btn-pitch text-xs py-2 px-3.5">
                    + Buat Kompetisi Baru
                </Link>
            </div>

            <div class="frosted-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 uppercase tracking-wide">
                                <th class="py-2.5 px-3.5">Nama Kompetisi</th>
                                <th class="py-2.5 px-3.5">Lokasi &amp; Kota</th>
                                <th class="py-2.5 px-3.5">Kategori Usia</th>
                                <th class="py-2.5 px-3.5">Kapasitas Kuota</th>
                                <th class="py-2.5 px-3.5">Biaya Pendaftaran</th>
                                <th class="py-2.5 px-3.5">Kick-Off</th>
                                <th class="py-2.5 px-3.5">Status</th>
                                <th class="py-2.5 px-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70">
                            <tr v-for="evt in events" :key="evt.id" class="bg-white hover:bg-slate-50/90 transition-colors">
                                <td class="py-2.5 px-3.5 font-bold text-slate-900">
                                    <p class="text-sm">{{ evt.name }}</p>
                                    <p class="text-[11px] text-slate-500 font-normal">{{ evt.format_label }}</p>
                                </td>
                                <td class="py-2.5 px-3.5 text-slate-700">
                                    {{ evt.venue_name }}, {{ evt.city }}
                                </td>
                                <td class="py-2.5 px-3.5 font-semibold text-slate-800">
                                    {{ evt.age_category }}
                                </td>
                                <td class="py-2.5 px-3.5">
                                    <div class="space-y-0.5">
                                        <span class="font-bold tabular-data text-slate-900">
                                            {{ evt.occupied_slots }} / {{ evt.max_teams }} Tim
                                        </span>
                                        <p class="text-[10px] text-slate-500 tabular-data">
                                            Lunas: {{ evt.active_teams }} tim | Hold: {{ evt.payment_deadline_hours }}h
                                        </p>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3.5 font-bold tabular-data text-slate-900">
                                    {{ formatRupiah(evt.registration_fee) }}
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data text-slate-700">
                                    {{ formatDate(evt.match_start_date) }}
                                </td>
                                <td class="py-2.5 px-3.5">
                                    <StatusBadge :status="evt.status" />
                                </td>
                                <td class="py-2.5 px-3.5 text-right">
                                    <Link
                                        :href="route('admin.events.edit', { event: evt.id })"
                                        class="btn-secondary text-[11px] py-1 px-2.5"
                                    >
                                        Ubah Pengaturan
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
