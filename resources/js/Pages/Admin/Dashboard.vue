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
                <Link :href="route('admin.payments.transactions', { status: 'refund_required' })" class="btn-sm-pitch shrink-0">
                    Lihat Transaksi
                </Link>
            </div>

            <!-- Events Quota & Revenue Overview Container -->
            <div class="frosted-card overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 bg-slate-100/70 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Pemantauan Kuota &amp; Pendapatan per Kompetisi</h2>
                        <p class="text-xs text-slate-500">Kapasitas maksimal terlindungi dari *race condition* dengan penguncian baris database.</p>
                    </div>
                    <Link :href="route('admin.events.create')" class="btn-pitch">
                        + Buat Event Baru
                    </Link>
                </div>

                <!-- DESKTOP TABLE VIEW -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr>
                                <th class="table-th">Nama Kompetisi</th>
                                <th class="table-th">Usia &amp; Format</th>
                                <th class="table-th">Kapasitas Kuota</th>
                                <th class="table-th">Tim Lunas</th>
                                <th class="table-th">Pendapatan Lunas</th>
                                <th class="table-th text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="evt in eventsOverview" :key="evt.id" class="hover:bg-orange-50/25 transition-colors">
                                <td class="table-td font-bold text-slate-900">
                                    <Link :href="route('admin.events.edit', { event: evt.id })" class="hover:text-orange-600 transition-colors">
                                        {{ evt.name }}
                                    </Link>
                                </td>
                                <td class="table-td text-slate-600">
                                    {{ evt.age_category }} &bull; {{ evt.format_label }}
                                </td>
                                <td class="table-td tabular-data font-semibold">
                                    {{ evt.occupied_slots }} / {{ evt.max_teams }} Tim
                                </td>
                                <td class="table-td tabular-data font-semibold text-slate-800">
                                    {{ evt.active_teams }} Tim
                                </td>
                                <td class="table-td tabular-data font-bold text-slate-900">
                                    {{ formatRupiah(evt.revenue_paid) }}
                                </td>
                                <td class="table-td text-right">
                                    <StatusBadge :status="evt.status" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE HIGH-DENSITY CARD LIST VIEW -->
                <div class="md:hidden divide-y divide-slate-100">
                    <div
                        v-for="evt in eventsOverview"
                        :key="evt.id"
                        class="p-4 hover:bg-orange-50/20 transition-colors space-y-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 leading-snug">
                                    <Link :href="route('admin.events.edit', { event: evt.id })" class="hover:text-orange-600">
                                        {{ evt.name }}
                                    </Link>
                                </h4>
                                <p class="text-xs text-slate-500">{{ evt.age_category }} &bull; {{ evt.format_label }}</p>
                            </div>
                            <StatusBadge :status="evt.status" />
                        </div>

                        <div class="grid grid-cols-2 gap-2 bg-slate-50/80 rounded-lg p-2.5 border border-slate-200/60 text-xs">
                            <div>
                                <span class="text-[10px] text-slate-500 uppercase font-bold block">Kuota Terisi</span>
                                <span class="font-mono tabular-data font-bold text-slate-900">{{ evt.occupied_slots }} / {{ evt.max_teams }} Tim</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500 uppercase font-bold block">Pendapatan Lunas</span>
                                <span class="font-mono tabular-data font-bold text-slate-900">{{ formatRupiah(evt.revenue_paid) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

