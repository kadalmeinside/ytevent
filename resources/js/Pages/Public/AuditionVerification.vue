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
    participant: {
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
    <Head :title="isVerified ? `Verifikasi Atlet: ${participant?.full_name} (Bib #${participant?.bib_number})` : 'Verifikasi Peserta Audisi Tidak Ditemukan'">
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
                    <span>Persija Talent Scouting Verification</span>
                </div>
                <h1 class="text-lg font-black text-slate-900 tracking-tight">{{ event?.name || 'Audisi & Seleksi Bakat' }}</h1>
                <p class="text-xs text-slate-500">Pusat Pemeriksaan Keabsahan &amp; Anti-Joki Peserta</p>
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
                            <div class="text-xs font-black uppercase tracking-wider">PESERTA SAH &amp; TERVERIFIKASI</div>
                            <div class="text-[10px] text-white/90">Lolos Verifikasi Dokumen &amp; Lunas</div>
                        </div>
                    </div>
                    <span v-if="event?.session_name" class="text-[11px] font-bold bg-white/20 px-2.5 py-0.5 rounded">
                        {{ event.session_name }}
                    </span>
                </div>

                <!-- Konten Profil Peserta -->
                <div class="p-5 space-y-4">
                    <!-- Foto & Identitas Pokok -->
                    <div class="flex items-center gap-4 pb-4 border-b border-slate-100">
                        <div class="relative shrink-0">
                            <img
                                v-if="participant?.photo_url"
                                :src="participant.photo_url"
                                :alt="participant.full_name"
                                class="w-20 h-24 rounded-xl object-cover border-2 border-orange-500 shadow-md bg-slate-100"
                            />
                            <div v-else class="w-20 h-24 rounded-xl bg-slate-100 border border-slate-300 text-slate-400 flex items-center justify-center font-bold text-xs">
                                NO FOTO
                            </div>
                            <span class="absolute -bottom-2 -right-2 px-2.5 py-0.5 rounded-md bg-orange-600 text-white font-black text-xs shadow-sm">
                                Bib #{{ participant?.bib_number }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                <span class="px-2 py-0.5 rounded bg-orange-100 text-orange-800 font-bold text-[11px]">{{ participant?.preferred_position || 'Pemain' }}</span>
                                <span class="text-[11px] text-slate-500 font-semibold">{{ participant?.age }} Tahun</span>
                            </div>
                            <h2 class="text-base font-black text-slate-900 leading-tight truncate" :title="participant?.full_name">{{ participant?.full_name }}</h2>
                            <p v-if="participant?.nickname" class="text-xs text-orange-600 font-bold truncate">Panggilan: "{{ participant?.nickname }}"</p>
                            <p class="text-[11px] text-slate-500 truncate mt-1">Lahir: {{ participant?.birth_date }}</p>
                        </div>
                    </div>

                    <!-- Informasi Teknis & Asal Klub -->
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500">SSB / Klub Asal:</span>
                            <span class="font-bold text-slate-800">{{ participant?.current_club_ssb }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500">Kaki Dominan:</span>
                            <span class="font-bold text-slate-800 uppercase">{{ participant?.dominant_foot || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500">Tinggi / Berat:</span>
                            <span class="font-bold text-slate-800">{{ participant?.height_cm ? `${participant.height_cm} cm` : '-' }} / {{ participant?.weight_kg ? `${participant.weight_kg} kg` : '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200/60">
                            <span class="text-slate-500">Status Seleksi:</span>
                            <span class="font-bold text-emerald-700">{{ participant?.stage_status_label }}</span>
                        </div>
                    </div>

                    <!-- Detail Verifikasi Panitia -->
                    <div class="pt-2 text-center text-[11px] text-slate-400 space-y-1">
                        <div>No. Reg: <span class="font-mono font-bold text-slate-600">{{ participant?.registration_number }}</span></div>
                        <div>NIK: <span class="font-mono text-slate-500">{{ participant?.masked_nik }}</span></div>
                        <div>Kode Screening: <span class="font-mono text-slate-600 font-semibold">{{ participant?.screening_code }}</span></div>
                        <div v-if="participant?.screening_verified_at" class="text-slate-500">
                            Diverifikasi pada: {{ participant?.screening_verified_at }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Jika Tidak Valid / Belum Sah -->
            <div v-else class="bg-white/90 backdrop-blur-xl rounded-2xl border border-red-200 shadow-xl overflow-hidden p-6 text-center space-y-4">
                <div class="w-14 h-14 mx-auto rounded-full bg-red-100 text-red-600 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-900">Peserta Tidak Terverifikasi</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Kode QR <span class="font-mono font-bold text-slate-700">{{ code }}</span> tidak valid, berkas screening belum disetujui, atau pembayaran belum diselesaikan.
                    </p>
                </div>
            </div>

            <!-- Footer Kembali -->
            <div class="text-center pt-2">
                <Link :href="route('home')" class="text-xs font-bold text-orange-600 hover:text-orange-700 transition">
                    &larr; Kembali ke Beranda
                </Link>
            </div>
        </div>
    </div>
</template>
