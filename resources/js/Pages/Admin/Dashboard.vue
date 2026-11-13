<script setup>
import { ref } from 'vue';
import { Link, Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    metrics: {
        type: Object,
        required: true,
    },
    eventsOverview: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah, formatDate } = useFormatters();

const isSyncing = ref(false);

const triggerResync = () => {
    isSyncing.value = true;
    router.post(route('admin.events.resync-slots'), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncing.value = false;
        },
    });
};
</script>

<template>
    <AdminLayout page-title="Dashboard Operasional & Keuangan" subtitle="Ringkasan eksekutif seluruh kompetisi, kuota pendaftaran tim, dan arus kas masuk.">
        <Head title="Admin Dashboard" />

        <div class="space-y-6">
            <!-- Executive KPI Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Total Pendapatan Lunas -->
                <div class="frosted-card p-4 sm:p-5 bg-white hover:border-emerald-300 hover:shadow-md transition-all duration-150 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Total Pendapatan Lunas</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data mt-2 font-mono tracking-tight">
                            {{ formatRupiah(metrics.total_paid_revenue) }}
                        </p>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">Tim Lunas:</span>
                        <strong class="text-emerald-700 font-bold tabular-data">{{ metrics.active_teams_total }} tim terkonfirmasi</strong>
                    </div>
                </div>

                <!-- Card 2: Antrean Bukti Transfer -->
                <div class="frosted-card p-4 sm:p-5 bg-white hover:border-amber-300 hover:shadow-md transition-all duration-150 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Antrean Verifikasi Manual</span>
                            <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black tabular-data mt-2 font-mono tracking-tight" :class="metrics.waiting_verification_count > 0 ? 'text-amber-800' : 'text-slate-900'">
                            {{ metrics.waiting_verification_count }} <span class="text-base font-bold text-slate-500">Bukti</span>
                        </p>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">Status Pemeriksaan:</span>
                        <Link
                            :href="route('admin.payments.manual-verification')"
                            class="font-bold text-orange-600 hover:text-orange-700 inline-flex items-center gap-1 transition-colors"
                        >
                            <span>Buka Antrean &rarr;</span>
                        </Link>
                    </div>
                </div>

                <!-- Card 3: Slot Kuota Terisi -->
                <div class="frosted-card p-4 sm:p-5 bg-white hover:border-orange-300 hover:shadow-md transition-all duration-150 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Kapasitas Slot Terisi</span>
                            <div class="w-8 h-8 rounded-lg bg-orange-50 border border-orange-200 text-orange-600 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data mt-2 font-mono tracking-tight">
                            {{ metrics.occupied_slots_total }} <span class="text-base font-bold text-slate-500">Tim</span>
                        </p>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">Klub Lunas: <strong class="text-slate-900 font-bold tabular-data">{{ metrics.active_teams_total }}</strong></span>
                        <button
                            type="button"
                            class="font-bold text-orange-600 hover:text-orange-700 inline-flex items-center gap-1 transition-colors cursor-pointer"
                            :disabled="isSyncing"
                            title="Pindai dan lepas pendaftaran yang telat bayar"
                            @click="triggerResync"
                        >
                            <svg class="w-3 h-3 text-orange-600 shrink-0" :class="{ 'animate-spin': isSyncing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>{{ isSyncing ? 'Sinkron...' : 'Cek Slot' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Card 4: Screening Berkas Pemain -->
                <div class="frosted-card p-4 sm:p-5 bg-white hover:border-indigo-300 hover:shadow-md transition-all duration-150 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Screening Berkas Pemain</span>
                            <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-200 text-indigo-600 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                                </svg>
                            </div>
                        </div>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data mt-2 font-mono tracking-tight">
                            {{ metrics.players_submitted_count }} <span class="text-base font-bold text-slate-500">Pemain</span>
                        </p>
                    </div>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px]">
                        <span class="text-slate-500">
                            Siap Kunci: <strong class="text-amber-700 font-bold tabular-data">{{ metrics.ready_to_lock_teams_count || 0 }}</strong>
                            &bull; Final: <strong class="text-emerald-700 font-bold tabular-data">{{ metrics.locked_teams_count || 0 }}</strong>
                        </span>
                        <Link
                            :href="route('admin.screening.index')"
                            class="font-bold text-orange-600 hover:text-orange-700 inline-flex items-center gap-1 transition-colors"
                        >
                            <span>Screening &rarr;</span>
                        </Link>
                    </div>
                </div>
            </div>

            <div
                v-if="(metrics.failed_webhooks_count || 0) > 0 || (metrics.failed_jobs_count || 0) > 0"
                class="frosted-card p-4 border-rose-300 bg-rose-50/90 flex items-center justify-between gap-3 text-xs text-rose-900"
            >
                <div>
                    <strong>Gangguan Integrasi:</strong>
                    {{ metrics.failed_webhooks_count || 0 }} webhook gagal dan
                    {{ metrics.failed_jobs_count || 0 }} job gagal perlu diperiksa.
                </div>
                <Link :href="route('admin.email-logs.index')" class="btn-sm-pitch shrink-0">
                    Buka Monitoring
                </Link>
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

            <!-- Quick Action Shortcuts Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 bg-slate-100/80 rounded-xl border border-slate-200">
                <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">Pintas Operasional</span>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn-sm-secondary inline-flex items-center gap-1.5 cursor-pointer"
                        :disabled="isSyncing"
                        title="Pindai dan lepas seluruh pendaftaran yang telah melewati batas waktu pembayaran"
                        @click="triggerResync"
                    >
                        <svg
                            class="w-3.5 h-3.5 text-orange-600 shrink-0"
                            :class="{ 'animate-spin': isSyncing }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>{{ isSyncing ? 'Menyinkronkan...' : 'Cek & Resync Slot' }}</span>
                    </button>
                    <Link :href="route('admin.events.create')" class="btn-sm-pitch">
                        + Buat Event Baru
                    </Link>
                    <Link :href="route('admin.payments.manual-verification')" class="btn-sm-secondary">
                        Verifikasi Transfer ({{ metrics.waiting_verification_count }})
                    </Link>
                    <Link :href="route('admin.teams.index')" class="btn-sm-secondary">
                        Kelola Tim Masuk
                    </Link>
                    <Link :href="route('home')" target="_blank" class="btn-sm-secondary inline-flex items-center gap-1">
                        <span>Portal Publik</span>
                        <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Events Quota & Revenue Overview Container (Responsive Card List View) -->
            <div class="space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wide">Pemantauan Kuota &amp; Arus Kas per Event</h2>
                        <p class="text-xs text-slate-500">Kapasitas kuota maksimal terlindungi dari *race condition* dengan penguncian baris database.</p>
                    </div>
                    <button
                        type="button"
                        class="btn-sm-secondary inline-flex items-center gap-1.5 shrink-0 w-fit cursor-pointer"
                        :disabled="isSyncing"
                        title="Pindai pendaftaran kedaluwarsa dan perbarui status kuota event"
                        @click="triggerResync"
                    >
                        <svg
                            class="w-3.5 h-3.5 text-orange-600 shrink-0"
                            :class="{ 'animate-spin': isSyncing }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>{{ isSyncing ? 'Memeriksa...' : 'Cek Slot Sekarang' }}</span>
                    </button>
                </div>

                <div v-if="eventsOverview.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500">
                    <p class="font-bold text-slate-800 text-sm">Belum Ada Event Dibuat</p>
                    <p class="mt-1 text-slate-400">Klik tombol buat event di atas untuk memulai event pertama Anda.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="evt in eventsOverview"
                        :key="evt.id"
                        class="frosted-card p-4 sm:p-5 hover:border-orange-300 hover:shadow-md transition-all duration-150 bg-white"
                    >
                        <!-- Top Header: Trophy, Name, Category & Status -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-base shadow-2xs shrink-0 border border-orange-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.45 1-1 1H8c-.55 0-1 .45-1 1v1h10v-1c0-.55-.45-1-1-1h-1c-.55 0-1-.45-1-1v-2.34c3.27-.47 5.76-3.23 5.76-6.66V4H5.24v4c0 3.43 2.49 6.19 5.76 6.66z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm sm:text-base font-bold text-slate-900 leading-tight">
                                        <Link :href="route('admin.events.edit', { event: evt.id })" class="hover:text-orange-600 transition-colors">
                                            {{ evt.name }}
                                        </Link>
                                    </h4>
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
                            <!-- Quota Progress (Col 1-5) -->
                            <div class="lg:col-span-5 space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-[10px] uppercase font-bold text-slate-400">Kapasitas Kuota</span>
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
                                <p class="text-[11px] text-slate-500">
                                    Tim terdaftar lunas: <strong class="text-slate-800 font-semibold">{{ evt.active_teams }} tim</strong>
                                </p>
                            </div>

                            <!-- Revenue (Col 6-8) -->
                            <div class="lg:col-span-4 space-y-1 bg-slate-50/80 p-2.5 rounded-lg border border-slate-200/60">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Pendapatan Lunas</span>
                                <span class="text-sm sm:text-base font-bold text-slate-900 tabular-data font-mono block">
                                    {{ formatRupiah(evt.revenue_paid) }}
                                </span>
                                <span class="text-[10px] text-slate-500 block">
                                    Biaya per tim: {{ formatRupiah(evt.registration_fee) }}
                                </span>
                            </div>

                            <!-- Actions (Col 9-12) -->
                            <div class="lg:col-span-3 flex sm:flex-col sm:items-end justify-center gap-2">
                                <Link
                                    :href="route('admin.teams.index', { event_id: evt.id })"
                                    class="btn-sm-secondary w-full sm:w-auto justify-center text-center"
                                >
                                    Daftar Tim ({{ evt.occupied_slots }})
                                </Link>
                                <Link
                                    :href="route('admin.events.edit', { event: evt.id })"
                                    class="btn-sm-pitch w-full sm:w-auto justify-center text-center"
                                >
                                    Edit Event
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
