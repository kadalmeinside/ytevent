<script setup>
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

defineProps({
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
</script>

<template>
    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Ringkasan Tim & Status Pendaftaran">
        <Head title="Dashboard Manajer Tim" />

        <div v-if="!team" class="frosted-card p-8 text-center">
            <h2 class="text-base font-bold text-slate-900">Anda Belum Memiliki Tim Terdaftar</h2>
            <p class="text-xs text-slate-600 mt-1">Silakan pilih kompetisi di Katalog Event untuk mendaftarkan tim baru.</p>
            <Link :href="route('home')" class="btn-pitch mt-4 text-xs">Lihat Katalog Kompetisi</Link>
        </div>

        <div v-else class="space-y-6">
            <!-- Status Banner -->
            <div
                v-if="team.status === 'pending_payment'"
                class="frosted-card p-5 border-amber-300 bg-amber-50/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div>
                    <p class="text-sm font-bold text-amber-900">Slot Kuota Ditahan Sementara &mdash; Menunggu Pelunasan</p>
                    <p class="text-xs text-amber-800 mt-1">
                        Batas waktu reservasi slot: <strong class="tabular-data">{{ formatDateTime(summary?.latest_payment?.expires_at || team.slot_expires_at) }}</strong>.
                        Selesaikan pembayaran agar status tim menjadi AKTIF dan pengisian daftar pemain terbuka.
                    </p>
                </div>
                <Link :href="route('manager.payments')" class="btn-pitch shrink-0 text-xs">
                    Selesaikan Pembayaran
                </Link>
            </div>

            <div
                v-else-if="team.status === 'expired'"
                class="frosted-card p-5 border-rose-300 bg-rose-50/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div>
                    <p class="text-sm font-bold text-rose-900">Reservasi Slot Kedaluwarsa</p>
                    <p class="text-xs text-rose-800 mt-1">
                        Batas waktu pembayaran telah terlewati sehingga slot kuota dilepas. Anda dapat mengajukan ulang slot pada menu Pembayaran selama kuota event masih tersedia.
                    </p>
                </div>
                <Link :href="route('manager.payments')" class="btn-pitch shrink-0 text-xs">
                    Ajukan Ulang Slot
                </Link>
            </div>

            <!-- Key Operational Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="frosted-card p-4">
                    <p class="text-xs font-medium text-slate-500">Status Pendaftaran Tim</p>
                    <div class="mt-2 flex items-center justify-between">
                        <StatusBadge :status="team.status" />
                        <span class="text-xs text-slate-500">{{ team.origin_association }}</span>
                    </div>
                </div>

                <div class="frosted-card p-4">
                    <p class="text-xs font-medium text-slate-500">Tagihan Pendaftaran</p>
                    <p class="text-lg font-bold text-slate-900 tabular-data mt-1">
                        {{ formatRupiah(summary?.latest_payment?.total_amount || team.event?.registration_fee) }}
                    </p>
                    <div class="mt-1.5 flex items-center justify-between text-xs">
                        <span class="tabular-data text-slate-500">{{ summary?.latest_payment?.invoice_number || '-' }}</span>
                        <StatusBadge v-if="summary?.latest_payment" :status="summary.latest_payment.status" />
                    </div>
                </div>

                <div class="frosted-card p-4">
                    <p class="text-xs font-medium text-slate-500">Progres Kuota Pemain</p>
                    <p class="text-lg font-bold text-slate-900 tabular-data mt-1">
                        {{ summary?.players_total || 0 }} / {{ team.event?.max_players_per_team }} Pemain
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Dokumen lengkap: <strong class="tabular-data text-emerald-700">{{ summary?.players_docs_complete || 0 }}</strong> |
                        Min. skuad: <strong class="tabular-data">{{ team.event?.min_players_per_team }}</strong>
                    </p>
                </div>

                <div class="frosted-card p-4">
                    <p class="text-xs font-medium text-slate-500">Pelatih &amp; Ofisial</p>
                    <p class="text-lg font-bold text-slate-900 tabular-data mt-1">
                        {{ summary?.officials_total || 0 }} / {{ team.event?.max_officials_per_team }} Ofisial
                    </p>
                    <p class="text-[11px] text-slate-500 mt-1 truncate">
                        Head Coach: <strong class="text-slate-800">{{ team.head_coach_name }}</strong>
                    </p>
                </div>
            </div>

            <!-- Next Step Checklist -->
            <div class="frosted-card p-6">
                <h2 class="text-sm font-bold text-slate-900 mb-4">Alur Persiapan Tim Menuju Pertandingan</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                    <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900">Langkah 1: Pembayaran</span>
                            <StatusBadge :status="summary?.latest_payment?.status || 'unpaid'" />
                        </div>
                        <p class="text-slate-600 leading-relaxed">
                            Pilih pembayaran otomatis (Virtual Account / QRIS) atau unggah bukti transfer bank manual.
                        </p>
                        <Link :href="route('manager.payments')" class="inline-block font-semibold text-emerald-700 hover:underline pt-1">
                            Buka Halaman Pembayaran &rarr;
                        </Link>
                    </div>

                    <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900">Langkah 2: Biodata &amp; Dokumen Pemain</span>
                            <StatusBadge :status="team.can_manage_roster ? 'open' : 'draft'" :label="team.can_manage_roster ? 'TERBUKA' : 'TERKUNCI'" />
                        </div>
                        <p class="text-slate-600 leading-relaxed">
                            Input nama lengkap pemain, nomor punggung, NIK, tanggal lahir, pas foto, serta dokumen wajib kompetisi.
                        </p>
                        <Link :href="route('manager.players')" class="inline-block font-semibold text-emerald-700 hover:underline pt-1">
                            Kelola Daftar Pemain &rarr;
                        </Link>
                    </div>

                    <div class="p-4 rounded-xl border border-slate-200 bg-white space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900">Langkah 3: Data Pelatih &amp; Ofisial</span>
                            <span class="tabular-data font-semibold text-slate-700">{{ summary?.officials_total || 0 }} Orang</span>
                        </div>
                        <p class="text-slate-600 leading-relaxed">
                            Lengkapi lisensi kepelatihan Head Coach beserta staf pendamping di bangku cadangan.
                        </p>
                        <Link :href="route('manager.officials')" class="inline-block font-semibold text-emerald-700 hover:underline pt-1">
                            Kelola Ofisial Tim &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </ManagerLayout>
</template>

