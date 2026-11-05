<script setup>
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
});

const { formatRupiah, formatDateTime } = useFormatters();

const calculatePercent = (current, total) => {
    if (!total || total <= 0) return 0;
    return Math.min(100, Math.round((current / total) * 100));
};
</script>

<template>
    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Ringkasan Tim & Status Pendaftaran">
        <Head title="Dashboard Manajer Tim" />

        <div v-if="!team" class="frosted-card p-8 text-center max-w-xl mx-auto my-12">
            <div class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center mx-auto mb-3 shadow-sm">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h2 class="text-base font-bold text-slate-900">Anda Belum Memiliki Tim Terdaftar</h2>
            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">Silakan pilih kompetisi di Katalog Event untuk mendaftarkan tim baru.</p>
            <Link :href="route('home')" class="btn-pitch mt-5 text-xs">Lihat Katalog Kompetisi</Link>
        </div>

        <div v-else class="space-y-6">
            <!-- Liquid Status Banner: Slot Kuota Ditahan Sementara -->
            <div
                v-if="team.status === 'pending_payment'"
                class="frosted-card p-5 border-orange-300/80 bg-gradient-to-r from-orange-50/90 via-amber-50/70 to-red-50/80 backdrop-blur-xl shadow-[0_4px_24px_rgba(234,88,12,0.06)] flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-orange-500/10 border border-orange-400/30 text-orange-600 flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-orange-950 flex items-center gap-2">
                            <span>Slot Kuota Ditahan Sementara &mdash; Menunggu Pelunasan</span>
                        </p>
                        <p class="text-xs text-orange-900/80 mt-1 leading-relaxed">
                            Batas waktu reservasi slot: <strong class="tabular-data font-semibold text-orange-950">{{ formatDateTime(summary?.latest_payment?.expires_at || team.slot_expires_at) }}</strong>.
                            Selesaikan pembayaran sebelum batas waktu berakhir agar slot tidak kedaluwarsa dan pengisian pemain terbuka.
                        </p>
                    </div>
                </div>
                <Link :href="route('manager.payments')" class="btn-pitch shrink-0 text-xs shadow-md shadow-orange-500/20 whitespace-nowrap">
                    Selesaikan Pembayaran &rarr;
                </Link>
            </div>

            <!-- Liquid Status Banner: Slot Kedaluwarsa -->
            <div
                v-else-if="team.status === 'expired'"
                class="frosted-card p-5 border-rose-300/80 bg-gradient-to-r from-rose-50/90 via-red-50/60 to-rose-50/80 backdrop-blur-xl shadow-[0_4px_24px_rgba(225,29,72,0.06)] flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div class="flex items-start gap-3.5">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-400/30 text-rose-600 flex items-center justify-center shrink-0 shadow-sm mt-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-rose-950">Reservasi Slot Kedaluwarsa</p>
                        <p class="text-xs text-rose-900/80 mt-1 leading-relaxed">
                            Batas waktu pembayaran telah terlewati sehingga slot kuota dilepas ke publik. Anda dapat mengajukan ulang slot pendaftaran selama kuota event masih tersedia.
                        </p>
                    </div>
                </div>
                <Link :href="route('manager.payments')" class="btn-pitch shrink-0 text-xs whitespace-nowrap">
                    Ajukan Ulang Slot &rarr;
                </Link>
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

            <!-- Next Step Checklist: Alur Persiapan Tim Menuju Pertandingan -->
            <div class="frosted-card p-6">
                <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Alur Persiapan Tim Menuju Pertandingan</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Ikuti tahapan wajib administrasi turnamen secara berurutan.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Step 1: Pembayaran -->
                    <div class="p-4 rounded-2xl border border-slate-200/90 bg-white/70 backdrop-blur-md flex flex-col justify-between space-y-3 hover:border-orange-200 transition-colors shadow-sm">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-orange-100 text-orange-700 font-black text-xs flex items-center justify-center">1</span>
                                    <span class="font-bold text-xs sm:text-sm text-slate-900">Pembayaran</span>
                                </div>
                                <StatusBadge :status="summary?.latest_payment?.status || 'unpaid'" class="scale-90 origin-right" />
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Selesaikan biaya pendaftaran via Virtual Account, QRIS otomatis, atau konfirmasi transfer bank manual.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <Link :href="route('manager.payments')" class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-600 hover:text-orange-700 transition-colors">
                                <span>Buka Halaman Pembayaran</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                        </div>
                    </div>

                    <!-- Step 2: Biodata & Dokumen Pemain -->
                    <div class="p-4 rounded-2xl border border-slate-200/90 bg-white/70 backdrop-blur-md flex flex-col justify-between space-y-3 hover:border-orange-200 transition-colors shadow-sm">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-700 font-black text-xs flex items-center justify-center">2</span>
                                    <span class="font-bold text-xs sm:text-sm text-slate-900">Biodata &amp; Berkas</span>
                                </div>
                                <StatusBadge :status="team.can_manage_roster ? 'open' : 'draft'" :label="team.can_manage_roster ? 'TERBUKA' : 'TERKUNCI'" class="scale-90 origin-right" />
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Input nama pemain, nomor punggung, NIK, tanggal lahir, pas foto, serta berkas wajib screening.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <Link
                                v-if="team.can_manage_roster"
                                :href="route('manager.players')"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-600 hover:text-orange-700 transition-colors"
                            >
                                <span>Kelola Daftar Pemain</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                            <span v-else class="text-xs text-slate-400 font-medium inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                Terbuka setelah pembayaran lunas
                            </span>
                        </div>
                    </div>

                    <!-- Step 3: Data Pelatih & Ofisial -->
                    <div class="p-4 rounded-2xl border border-slate-200/90 bg-white/70 backdrop-blur-md flex flex-col justify-between space-y-3 hover:border-orange-200 transition-colors shadow-sm">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-lg bg-slate-100 text-slate-700 font-black text-xs flex items-center justify-center">3</span>
                                    <span class="font-bold text-xs sm:text-sm text-slate-900">Pelatih &amp; Ofisial</span>
                                </div>
                                <!-- Micro-Pill Terpisah & Proporsional -->
                                <span class="tabular-data text-[11px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200/80">
                                    {{ summary?.officials_total || 0 }} Orang
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                Lengkapi lisensi kepelatihan Head Coach beserta staf medis/ofisial pendamping di bangku cadangan.
                            </p>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <Link
                                v-if="team.can_manage_roster"
                                :href="route('manager.officials')"
                                class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-600 hover:text-orange-700 transition-colors"
                            >
                                <span>Kelola Ofisial Tim</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </Link>
                            <span v-else class="text-xs text-slate-400 font-medium inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                Terbuka setelah pembayaran lunas
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ManagerLayout>
</template>
