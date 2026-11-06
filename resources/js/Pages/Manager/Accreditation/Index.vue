<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    readiness: {
        type: Object,
        required: true,
    },
    passedPlayers: {
        type: Array,
        default: () => [],
    },
    officials: {
        type: Array,
        default: () => [],
    },
    teamsList: {
        type: Array,
        default: () => [],
    },
});

const isLockModalOpen = ref(false);
const activeQrModalPlayer = ref(null);

const lockForm = useForm({});

const confirmLockRoster = () => {
    lockForm.post(route('manager.teams.lock-roster', { team: props.team.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isLockModalOpen.value = false;
        },
    });
};

const openQrModal = (player) => {
    activeQrModalPlayer.value = player;
};

const closeQrModal = () => {
    activeQrModalPlayer.value = null;
};
</script>

<template>
    <Head :title="`Akreditasi & Skuad Final - ${team.name}`" />

    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Akreditasi Skuad Resmi">
        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header Section -->
            <div class="frosted-card p-5 sm:p-6 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-orange-600">Akreditasi Kompetisi Resmi</span>
                        <span class="text-slate-300">•</span>
                        <span class="text-xs text-slate-500">{{ team.event?.name }} ({{ team.event?.age_category }})</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Pengesahan Skuad & Dokumen Pertandingan</h1>
                    <p class="text-xs sm:text-sm text-slate-600 mt-1">
                        Kunci skuad final tim setelah seluruh pemain memenuhi verifikasi keabsahan dokumen untuk mengunduh Album Roster Wasit dan Kartu Akreditasi resmi.
                    </p>
                </div>

                <!-- Status Pill -->
                <div class="flex items-center gap-3">
                    <div v-if="readiness.is_locked" class="px-4 py-2 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 flex items-center gap-2 shadow-sm">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <div>
                            <div class="text-xs font-black uppercase tracking-wider">SKUAD TERKUNCI RESMI</div>
                            <div class="text-[11px] text-emerald-700">{{ readiness.roster_locked_at }}</div>
                        </div>
                    </div>
                    <div v-else class="px-4 py-2 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 flex items-center gap-2 shadow-sm">
                        <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <div class="text-xs font-black uppercase tracking-wider">SKUAD BELUM DIKUNCI</div>
                            <div class="text-[11px] text-amber-700">Tahap Verifikasi Berkas</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Readiness Checklist Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Total Pemain Lolos -->
                <div class="frosted-card p-4 rounded-xl border flex flex-col justify-between" :class="readiness.is_min_players_met ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200'">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pemain Sah (Lolos)</span>
                        <span class="p-1.5 rounded-lg" :class="readiness.is_min_players_met ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                            <svg v-if="readiness.is_min_players_met" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </span>
                    </div>
                    <div class="my-2">
                        <div class="text-2xl font-black text-slate-900 tabular-nums">
                            {{ readiness.passed_players_count }} <span class="text-xs font-normal text-slate-500">/ min {{ readiness.min_players_required }}</span>
                        </div>
                    </div>
                    <div class="text-[11px]" :class="readiness.is_min_players_met ? 'text-emerald-700 font-semibold' : 'text-slate-500'">
                        {{ readiness.is_min_players_met ? 'Kuota minimal terpenuhi' : `Kurang ${readiness.min_players_required - readiness.passed_players_count} pemain sah` }}
                    </div>
                </div>

                <!-- Card 2: Kiper (GK) Sah -->
                <div class="frosted-card p-4 rounded-xl border flex flex-col justify-between" :class="readiness.is_gk_met ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200'">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Penjaga Gawang (GK)</span>
                        <span class="p-1.5 rounded-lg" :class="readiness.is_gk_met ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                            <svg v-if="readiness.is_gk_met" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </span>
                    </div>
                    <div class="my-2">
                        <div class="text-2xl font-black text-slate-900 tabular-nums">
                            {{ readiness.gk_passed_count }} <span class="text-xs font-normal text-slate-500">/ min 2 GK</span>
                        </div>
                    </div>
                    <div class="text-[11px]" :class="readiness.is_gk_met ? 'text-emerald-700 font-semibold' : 'text-slate-500'">
                        {{ readiness.is_gk_met ? 'Syarat 2 kiper sah terpenuhi' : `Kurang ${Math.max(0, 2 - readiness.gk_passed_count)} kiper sah` }}
                    </div>
                </div>

                <!-- Card 3: Ofisial Tim -->
                <div class="frosted-card p-4 rounded-xl border flex flex-col justify-between" :class="readiness.is_officials_met ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200'">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Tim Pelatih & Ofisial</span>
                        <span class="p-1.5 rounded-lg" :class="readiness.is_officials_met ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                            <svg v-if="readiness.is_officials_met" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </span>
                    </div>
                    <div class="my-2">
                        <div class="text-2xl font-black text-slate-900 tabular-nums">
                            {{ readiness.officials_count }} <span class="text-xs font-normal text-slate-500">Terdaftar</span>
                        </div>
                    </div>
                    <div class="text-[11px]" :class="readiness.is_officials_met ? 'text-emerald-700 font-semibold' : 'text-slate-500'">
                        {{ readiness.is_officials_met ? 'Ofisial tim telah terdaftar' : 'Belum mendaftarkan ofisial' }}
                    </div>
                </div>

                <!-- Card 4: Antrean Pemeriksaan -->
                <div class="frosted-card p-4 rounded-xl border flex flex-col justify-between" :class="readiness.has_in_review_players ? 'border-amber-200 bg-amber-50/20' : 'border-slate-200'">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Antrean Pemeriksaan</span>
                        <span class="p-1.5 rounded-lg" :class="readiness.has_in_review_players ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">
                            <svg v-if="!readiness.has_in_review_players" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                    </div>
                    <div class="my-2">
                        <div class="text-2xl font-black text-slate-900 tabular-nums">
                            {{ readiness.submitted_players_count }} <span class="text-xs font-normal text-slate-500">Menunggu</span>
                        </div>
                    </div>
                    <div class="text-[11px]" :class="readiness.has_in_review_players ? 'text-amber-800 font-semibold' : 'text-slate-500'">
                        {{ readiness.has_in_review_players ? 'Menunggu screening panitia' : 'Seluruh antrean telah diperiksa' }}
                    </div>
                </div>
            </div>

            <!-- Action & Download Control Banner -->
            <div class="frosted-card p-5 rounded-2xl border border-slate-200/90 shadow-sm">
                <!-- Jika Skuad Sudah Terkunci -->
                <div v-if="readiness.is_locked" class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Dokumen Pertandingan Resmi Siap Diunduh</h3>
                            <p class="text-xs text-slate-600">Gunakan dokumen ini untuk keperluan Technical Meeting dan pemeriksaan wasit saat matchday.</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        <a
                            :href="route('manager.accreditation.roster-sheet', { team: team.id })"
                            target="_blank"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-orange-600 to-red-600 text-white font-bold text-xs shadow-sm hover:from-orange-700 hover:to-red-700 transition-all"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Unduh Album Roster (PDF)</span>
                        </a>

                        <a
                            :href="route('manager.accreditation.id-cards', { team: team.id })"
                            target="_blank"
                            class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs shadow-sm hover:bg-slate-800 transition-all"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                            </svg>
                            <span>Unduh ID Cards Lanyard (PDF)</span>
                        </a>
                    </div>
                </div>

                <!-- Jika Skuad Belum Terkunci -->
                <div v-else class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Kunci Skuad Final Tim</h3>
                            <p class="text-xs text-slate-600">
                                Selesaikan seluruh verifikasi berkas pemain. Setelah skuad dikunci, daftar pemain tidak dapat diubah lagi dan dokumen akreditasi resmi akan diterbitkan.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <button
                            type="button"
                            :disabled="!readiness.is_ready || lockForm.processing"
                            :title="!readiness.is_ready ? (readiness.blocking_reasons[0] || 'Syarat belum lengkap') : 'Klik untuk mengunci skuad final'"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs shadow-sm transition-all"
                            :class="
                                readiness.is_ready && !lockForm.processing
                                    ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white hover:from-orange-700 hover:to-red-700 shadow-orange-500/20'
                                    : 'bg-slate-200 text-slate-400 cursor-not-allowed border border-slate-300'
                            "
                            @click="isLockModalOpen = true"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span>Kunci Skuad Final</span>
                        </button>
                    </div>
                </div>

                <!-- Blocking Reasons Alert jika belum ready -->
                <div v-if="!readiness.is_locked && !readiness.is_ready" class="mt-4 pt-4 border-t border-slate-200/80">
                    <div class="p-3 rounded-xl bg-amber-50/80 border border-amber-200 text-xs text-amber-900 space-y-1">
                        <div class="font-bold flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Syarat Penguncian Skuad Belum Terpenuhi:</span>
                        </div>
                        <ul class="list-disc list-inside pl-1 text-[11px] text-amber-800 space-y-0.5">
                            <li v-for="(reason, idx) in readiness.blocking_reasons" :key="idx">{{ reason }}</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Daftar Pemain Terakreditasi (Sah) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Daftar Pemain Sah ({{ passedPlayers.length }})</h2>
                        <p class="text-xs text-slate-500">Pemain yang telah lolos verifikasi dan tercantum di Roster Sheet pertandingan.</p>
                    </div>
                    <Link
                        v-if="!readiness.is_locked"
                        :href="route('manager.players')"
                        class="text-xs text-orange-600 hover:text-orange-700 font-bold inline-flex items-center gap-1"
                    >
                        <span>Kelola Pemain</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                <div v-if="passedPlayers.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="player in passedPlayers"
                        :key="player.id"
                        class="frosted-card p-3.5 rounded-xl border border-slate-200/80 flex items-center gap-3 hover:border-orange-300 transition-all shadow-xs"
                    >
                        <img
                            v-if="player.photo_url"
                            :src="player.photo_url"
                            :alt="player.full_name"
                            class="w-14 h-16 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                        />
                        <div v-else class="w-14 h-16 rounded-lg bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center font-bold text-xs shrink-0">
                            FOTO
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 mb-0.5">
                                <span class="px-1.5 py-0.5 rounded bg-orange-600 text-white font-bold text-[10px]">#{{ player.jersey_number }}</span>
                                <span class="px-1.5 py-0.5 rounded bg-sky-100 text-sky-800 font-bold text-[10px]">{{ player.position }}</span>
                                <span class="text-[10px] text-emerald-700 font-bold ml-auto flex items-center gap-0.5">
                                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Sah</span>
                                </span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900 truncate" :title="player.full_name">{{ player.full_name }}</h4>
                            <p class="text-[11px] text-slate-500 truncate">TTL: {{ player.birth_date }} ({{ player.age }} thn)</p>
                            <p class="text-[10px] font-mono text-slate-400 truncate">ID: {{ player.accreditation_code }}</p>
                        </div>

                        <button
                            type="button"
                            class="p-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 shrink-0"
                            title="Tampilkan Kode QR Verifikasi"
                            @click="openQrModal(player)"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div v-else class="frosted-card p-8 rounded-2xl text-center border border-dashed border-slate-300">
                    <svg class="w-10 h-10 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <h3 class="text-sm font-bold text-slate-800">Belum Ada Pemain yang Berstatus Sah</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                        Pemain yang telah Anda ajukan sedang dalam antrean pemeriksaan panitia pelaksana. Hasil screening akan otomatis tampil di sini.
                    </p>
                </div>
            </div>

            <!-- Daftar Ofisial Terdaftar -->
            <div class="space-y-3 pt-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Ofisial & Tim Pelatih ({{ officials.length }})</h2>
                        <p class="text-xs text-slate-500">Staf resmi tim yang mendampingi di bench pertandingan.</p>
                    </div>
                    <Link
                        v-if="!readiness.is_locked"
                        :href="route('manager.officials')"
                        class="text-xs text-orange-600 hover:text-orange-700 font-bold inline-flex items-center gap-1"
                    >
                        <span>Kelola Ofisial</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>

                <div v-if="officials.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="official in officials"
                        :key="official.id"
                        class="frosted-card p-3 rounded-xl border border-slate-200/80 flex items-center gap-3 shadow-xs"
                    >
                        <img
                            v-if="official.photo_url"
                            :src="official.photo_url"
                            :alt="official.full_name"
                            class="w-12 h-14 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                        />
                        <div v-else class="w-12 h-14 rounded-lg bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center font-bold text-[10px] shrink-0">
                            FOTO
                        </div>

                        <div class="min-w-0 flex-1">
                            <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-bold text-[10px] mb-0.5">{{ official.role }}</span>
                            <h4 class="text-xs font-bold text-slate-900 truncate">{{ official.full_name }}</h4>
                            <p class="text-[10px] text-slate-500 truncate">Lisensi: {{ official.license_number || '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal: Kunci Skuad Final -->
        <div v-if="isLockModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Konfirmasi Kunci Skuad Final</h3>
                        <p class="text-xs text-slate-500">Tindakan ini bersifat permanen.</p>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-2">
                    <p class="font-bold">Perhatikan konsekuensi penguncian skuad:</p>
                    <ul class="list-disc list-inside space-y-1 text-[11px] text-amber-800">
                        <li>Daftar pemain, nomor punggung, dan berkas akan dikunci permanen dan tidak dapat diubah lagi.</li>
                        <li>Sistem akan menerbitkan Album Roster Sheet resmi wasit dan ID Cards akreditasi ber-QR Code.</li>
                        <li>Pastikan seluruh nama dan nomor punggung pemain telah sesuai sebelum melanjutkan.</li>
                    </ul>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button
                        type="button"
                        class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 rounded-xl border border-slate-200 bg-white"
                        @click="isLockModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="lockForm.processing"
                        class="px-4 py-2 text-xs font-bold text-white rounded-xl bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 shadow-sm transition-all"
                        @click="confirmLockRoster"
                    >
                        <span v-if="lockForm.processing">Memproses...</span>
                        <span v-else>Ya, Kunci Skuad Sekarang</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- QR Code Preview Modal -->
        <div v-if="activeQrModalPlayer" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 text-center space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                    <span class="text-xs font-bold text-slate-900">QR Code Verifikasi Lapangan</span>
                    <button type="button" class="text-slate-400 hover:text-slate-600 p-1" @click="closeQrModal">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-slate-900">{{ activeQrModalPlayer.full_name }}</h3>
                    <p class="text-xs text-orange-600 font-bold">#{{ activeQrModalPlayer.jersey_number }} • {{ activeQrModalPlayer.position }}</p>
                    <p class="text-[11px] font-mono text-slate-500">ID: {{ activeQrModalPlayer.accreditation_code }}</p>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 inline-block">
                    <!-- SVG Link Direct Render / Link to verification -->
                    <a
                        :href="route('public.player.verify', { code: activeQrModalPlayer.accreditation_code })"
                        target="_blank"
                        class="inline-block p-2 bg-white rounded-lg border border-slate-200 shadow-xs hover:border-orange-500 transition-colors"
                    >
                        <svg class="w-36 h-36 mx-auto text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    </a>
                </div>

                <p class="text-[11px] text-slate-500">
                    Wasit atau pengawas pertandingan dapat memindai kode ini langsung dari ID Card atau Roster Sheet untuk memastikan keabsahan pemain di lapangan.
                </p>

                <div class="pt-2">
                    <a
                        :href="route('public.player.verify', { code: activeQrModalPlayer.accreditation_code })"
                        target="_blank"
                        class="block w-full py-2 text-xs font-bold text-orange-600 bg-orange-50 hover:bg-orange-100 rounded-xl border border-orange-200 transition-colors"
                    >
                        Buka Halaman Verifikasi Publik ↗
                    </a>
                </div>
            </div>
        </div>
    </ManagerLayout>
</template>

