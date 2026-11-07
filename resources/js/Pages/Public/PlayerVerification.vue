<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    isVerified: {
        type: Boolean,
        default: false,
    },
    code: {
        type: String,
        required: true,
    },
    player: {
        type: Object,
        default: null,
    },
    team: {
        type: Object,
        default: null,
    },
    event: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head :title="isVerified ? `Verifikasi Sah: ${player?.full_name} (#${player?.jersey_number})` : 'Verifikasi Pemain Tidak Ditemukan'">
        <meta head-key="robots" name="robots" content="noindex, nofollow, noarchive, nosnippet" />
        <meta head-key="googlebot" name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />
    </Head>

    <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col justify-between py-6 px-4 sm:px-6">
        <div class="max-w-md w-full mx-auto space-y-4">
            <!-- Header Brand -->
            <div class="text-center space-y-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-100 text-orange-800 text-[11px] font-bold uppercase tracking-wider">
                    <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>Persija Development Verification</span>
                </div>
                <h1 class="text-lg font-black text-slate-900 tracking-tight">{{ event?.name || 'Kompetisi Resmi Sepak Bola' }}</h1>
                <p class="text-xs text-slate-500">Pusat Pemeriksaan Keabsahan Pemain Digital Lapangan</p>
            </div>

            <!-- Card Utama Jika Terverifikasi Sah -->
            <div v-if="isVerified" class="bg-white/90 backdrop-blur-xl rounded-2xl border border-slate-200 shadow-xl overflow-hidden">
                <!-- Status Bar Hijau Sah -->
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-3.5 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs font-black uppercase tracking-wider">AKREDITASI SAH & RESMI</div>
                            <div class="text-[10px] text-white/90">Lolos Verifikasi Dokumen Panitia</div>
                        </div>
                    </div>
                    <span class="text-[11px] font-mono font-bold bg-white/20 px-2 py-0.5 rounded">
                        {{ event?.age_category }}
                    </span>
                </div>

                <!-- Konten Profil Pemain -->
                <div class="p-5 space-y-4">
                    <!-- Foto & Identitas Pokok -->
                    <div class="flex items-center gap-4 pb-4 border-b border-slate-100">
                        <div class="relative shrink-0">
                            <img
                                v-if="player?.photo_url"
                                :src="player.photo_url"
                                :alt="player.full_name"
                                class="w-20 h-24 rounded-xl object-cover border-2 border-orange-500 shadow-md bg-slate-100"
                            />
                            <div v-else class="w-20 h-24 rounded-xl bg-slate-100 border border-slate-300 text-slate-400 flex items-center justify-center font-bold text-xs">
                                NO FOTO
                            </div>
                            <span class="absolute -bottom-2 -right-2 px-2 py-0.5 rounded-md bg-orange-600 text-white font-black text-xs shadow-sm">
                                #{{ player?.jersey_number }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 mb-1">
                                <span class="px-2 py-0.5 rounded bg-sky-100 text-sky-800 font-bold text-[11px]">{{ player?.position }}</span>
                                <span class="text-[11px] text-slate-500 font-semibold">{{ player?.age }} Tahun</span>
                            </div>
                            <h2 class="text-base font-black text-slate-900 leading-tight truncate" :title="player?.full_name">{{ player?.full_name }}</h2>
                            <p class="text-xs text-orange-600 font-bold truncate">Nama Punggung: {{ player?.jersey_name }}</p>
                            <p class="text-[11px] text-slate-500 truncate mt-1">TTL: {{ player?.birth_place }}, {{ player?.birth_date }}</p>
                        </div>
                    </div>

                    <!-- Informasi Klub / Tim -->
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center gap-3">
                        <img
                            v-if="team?.logo_url"
                            :src="team.logo_url"
                            :alt="team.name"
                            class="w-10 h-10 rounded-lg object-contain bg-white border border-slate-200 p-1 shrink-0"
                        />
                        <div v-else class="w-10 h-10 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-xs shrink-0">
                            {{ team?.name?.substring(0, 2).toUpperCase() }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tim Terdaftar</div>
                            <h3 class="text-xs font-bold text-slate-900 truncate">{{ team?.name }}</h3>
                            <p class="text-[10px] text-slate-500 truncate">Askot/Askab: {{ team?.origin_association }}</p>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="inline-block px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                {{ team?.is_roster_locked ? 'Roster Final' : 'Aktif' }}
                            </span>
                        </div>
                    </div>

                    <!-- Detail Validasi Teknis -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/60">
                            <span class="text-[10px] font-semibold text-slate-400 block">NIK Terdaftar</span>
                            <span class="font-mono font-bold text-slate-800">{{ player?.masked_nik }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/60">
                            <span class="text-[10px] font-semibold text-slate-400 block">Nomor NISN</span>
                            <span class="font-mono font-bold text-slate-800">{{ player?.nisn || '-' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/60">
                            <span class="text-[10px] font-semibold text-slate-400 block">Kaki Dominan</span>
                            <span class="font-bold text-slate-800">{{ player?.dominant_foot || 'Kanan' }}</span>
                        </div>
                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200/60">
                            <span class="text-[10px] font-semibold text-slate-400 block">Tinggi / Berat</span>
                            <span class="font-bold text-slate-800">{{ player?.height_cm ? `${player.height_cm} cm` : '-' }} / {{ player?.weight_kg ? `${player.weight_kg} kg` : '-' }}</span>
                        </div>
                    </div>

                    <!-- Jejak Audit Verifikasi -->
                    <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1">
                        <div class="flex items-center justify-between">
                            <span>Kode Akreditasi:</span>
                            <span class="font-mono font-bold text-orange-600">{{ player?.accreditation_code }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Waktu Pengesahan:</span>
                            <span class="font-medium text-slate-700">{{ player?.verified_at }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Petugas Verifikator:</span>
                            <span class="font-medium text-slate-700">{{ player?.verified_by_name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Footer Keamanan -->
                <div class="bg-slate-50 px-4 py-3 border-t border-slate-200 text-center text-[10px] text-slate-400">
                    Sertifikat digital resmi turnamen Persija Development. Dokumen ini sah dan diverifikasi secara langsung melalui database pusat.
                </div>
            </div>

            <!-- Card Jika Tidak Terverifikasi / Kode Salah -->
            <div v-else class="bg-white rounded-2xl border border-rose-200 shadow-xl p-6 text-center space-y-4">
                <div class="w-14 h-14 rounded-2xl bg-rose-100 text-rose-600 mx-auto flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-lg font-black text-slate-900">Data Akreditasi Tidak Valid</h2>
                    <p class="text-xs text-rose-700 font-semibold mt-1">
                        Kode Akreditasi "<span class="font-mono">{{ code }}</span>" tidak ditemukan atau pemain belum disahkan oleh panitia screening.
                    </p>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 text-left space-y-1">
                    <div class="font-bold text-slate-800">Petunjuk untuk Wasit / Match Commissioner:</div>
                    <ul class="list-disc list-inside text-[11px] space-y-0.5 text-slate-500">
                        <li>Pastikan pemindaian QR Code dilakukan langsung dari ID Card atau Album Roster fisik resmi.</li>
                        <li>Pemain yang belum berstatus sah dilarang bermain pada pertandingan resmi.</li>
                        <li>Hubungi panitia pelaksana meja screening untuk konfirmasi status administrasi.</li>
                    </ul>
                </div>
            </div>

            <!-- Footer Tautan -->
            <div class="text-center pt-2">
                <Link :href="route('home')" class="text-xs text-slate-500 hover:text-orange-600 transition-colors font-medium">
                    ← Kembali ke Halaman Utama Turnamen
                </Link>
            </div>
        </div>
    </div>
</template>

