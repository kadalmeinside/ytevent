<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

defineProps({
    metrics: {
        type: Object,
        required: true,
    },
    eventsOverview: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah } = useFormatters();
</script>

<template>
    <AdminLayout page-title="Dashboard Operasional & Keuangan" subtitle="Ringkasan eksekutif seluruh kompetisi, kuota pendaftaran, dan arus kas masuk.">
        <Head title="Admin Dashboard" />

        <div class="space-y-6">
            <!-- Metrik Finansial & Operasional -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="frosted-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Pendapatan Lunas</p>
                    <p class="text-xl sm:text-2xl font-black text-slate-900 tabular-data mt-1 tracking-tight">
                        {{ formatRupiah(metrics.total_paid_revenue) }}
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Dari pendaftaran tim lunas
                    </p>
                </div>

                <div class="frosted-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Antrean Bukti Transfer</p>
                    <p class="text-xl sm:text-2xl font-bold tabular-data mt-1" :class="metrics.waiting_verification_count > 0 ? 'text-amber-800' : 'text-slate-900'">
                        {{ metrics.waiting_verification_count }} Bukti
                    </p>
                    <Link :href="route('admin.payments.manual-verification')" class="text-[11px] font-semibold text-orange-600 hover:text-orange-700 hover:underline mt-1 inline-block">
                        Buka Antrean Finance &rarr;
                    </Link>
                </div>

                <div class="frosted-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Slot Kuota Terisi</p>
                    <p class="text-xl sm:text-2xl font-black text-slate-900 tabular-data mt-1">
                        {{ metrics.occupied_slots_total }} Tim
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Aktif / Lunas: <strong class="tabular-data text-orange-700 font-bold">{{ metrics.active_teams_total }}</strong> tim
                    </p>
                </div>

                <div class="frosted-card p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Antrean Screening Pemain</p>
                    <p class="text-xl sm:text-2xl font-black text-slate-900 tabular-data mt-1">
                        {{ metrics.players_submitted_count }} Pemain
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Menunggu verifikasi berkas
                    </p>
                </div>
            </div>

            <!-- Over-quota / Refund Alert if any -->
            <div
                v-if="metrics.refund_required_count > 0"
                class="frosted-card p-4 border-rose-300 bg-rose-50/90 flex items-center justify-between gap-3 text-xs text-rose-900"
            >
                <div>
                    <strong>Peringatan Rekonsiliasi:</strong> Terdapat {{ metrics.refund_required_count }} transaksi pembayaran yang masuk setelah kuota kompetisi penuh (*Late Settlement*). Perlu proses pengembalian dana manual.
                </div>
                <Link :href="route('admin.payments.transactions', { status: 'refund_required' })" class="btn-pitch text-xs py-1.5 px-3">
                    Lihat Transaksi
                </Link>
            </div>

            <!-- Events Quota & Revenue Overview Table -->
            <div class="frosted-card overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 bg-slate-100/70 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Pemantauan Kuota &amp; Pendapatan per Kompetisi</h2>
                        <p class="text-xs text-slate-500">Kapasitas maksimal terlindungi dari *race condition* dengan penguncian baris database.</p>
                    </div>
                    <Link :href="route('admin.events.create')" class="btn-pitch text-xs py-1.5 px-3">
                        + Buat Event Baru
                    </Link>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 uppercase tracking-wide">
                                <th class="py-2.5 px-3.5">Nama Kompetisi</th>
                                <th class="py-2.5 px-3.5">Usia &amp; Format</th>
                                <th class="py-2.5 px-3.5">Kapasitas Kuota</th>
                                <th class="py-2.5 px-3.5">Tim Lunas</th>
                                <th class="py-2.5 px-3.5">Pendapatan Lunas</th>
                                <th class="py-2.5 px-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70">
                            <tr v-for="evt in eventsOverview" :key="evt.id" class="bg-white hover:bg-slate-50/90">
                                <td class="py-2.5 px-3.5 font-bold text-slate-900">
                                    <Link :href="route('admin.events.edit', { event: evt.id })" class="hover:text-orange-600 transition-colors">
                                        {{ evt.name }}
                                    </Link>
                                </td>
                                <td class="py-2.5 px-3.5 text-slate-600">
                                    {{ evt.age_category }} &bull; {{ evt.format_label }}
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data font-semibold">
                                    {{ evt.occupied_slots }} / {{ evt.max_teams }} Tim
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data font-semibold text-slate-800">
                                    {{ evt.active_teams }} Tim
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data font-bold text-slate-900">
                                    {{ formatRupiah(evt.revenue_paid) }}
                                </td>
                                <td class="py-2.5 px-3.5 text-right">
                                    <StatusBadge :status="evt.status" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

