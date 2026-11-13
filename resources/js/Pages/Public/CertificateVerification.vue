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
    certificate: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head :title="isVerified ? `Piagam Sah: ${certificate?.recipient_name}` : 'Verifikasi Piagam Tidak Valid'">
        <meta head-key="robots" name="robots" content="noindex, nofollow, noarchive, nosnippet" />
    </Head>

    <div class="min-h-screen bg-slate-50 text-slate-800 flex flex-col justify-between py-8 px-4 sm:px-6">
        <div class="max-w-md w-full mx-auto space-y-4">
            <!-- Header Brand -->
            <div class="text-center space-y-1">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-100 text-orange-800 text-[11px] font-bold uppercase tracking-wider">
                    <span>Persija Certificate Integrity</span>
                </div>
                <h1 class="text-lg font-black text-slate-900 tracking-tight">Verifikasi Keaslian E-Sertifikat Digital</h1>
                <p class="text-xs text-slate-500">Sistem Validasi Dokumen Penghargaan Resmi Persija Development</p>
            </div>

            <!-- Card Hasil Verifikasi Sah -->
            <div v-if="isVerified" class="bg-white rounded-2xl border border-slate-200 shadow-xl overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 text-white p-4 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-xs font-black uppercase tracking-wider">SERTIFIKAT RESMI &amp; TERVERIFIKASI</div>
                        <div class="text-[11px] text-white/90">Diterbitkan oleh Sistem Resmi Persija Development</div>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <div class="text-center pb-4 border-b border-slate-100">
                        <div class="text-[11px] text-slate-500 uppercase font-bold">Penerima Piagam:</div>
                        <h2 class="text-xl font-black text-slate-900 mt-0.5">{{ certificate.recipient_name }}</h2>
                        <div v-if="certificate.registration_number" class="text-xs font-mono font-bold text-orange-600 mt-0.5">
                            ID Digital Pass: {{ certificate.registration_number }}
                        </div>
                        <div v-else-if="certificate.bib_number" class="text-xs font-bold text-orange-600 mt-0.5">
                            No. Bib: #{{ certificate.bib_number }}
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-orange-50/80 border border-orange-200 text-center space-y-1">
                        <div class="text-[10px] font-black uppercase tracking-wider text-orange-700">Predikat Penghargaan</div>
                        <div class="text-sm font-black text-orange-950">{{ certificate.predicate }}</div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-500">Event / Kegiatan:</span>
                            <span class="font-bold text-slate-900 text-right">{{ certificate.event_name }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-500">Kategori:</span>
                            <span class="font-bold text-slate-900">{{ certificate.type }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-500">Posisi:</span>
                            <span class="font-bold text-slate-900">{{ certificate.position || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1">
                            <span class="text-slate-500">Klub / SSB:</span>
                            <span class="font-bold text-slate-900">{{ certificate.club || '-' }}</span>
                        </div>
                    </div>

                    <div class="pt-3 text-center text-[10px] font-mono text-slate-400">
                        No. Seri Sertifikat: <strong>{{ code }}</strong>
                    </div>
                </div>
            </div>

            <!-- Card Hasil Tidak Valid -->
            <div v-else class="bg-white rounded-2xl border border-rose-200 shadow-xl overflow-hidden p-6 text-center space-y-3">
                <div class="w-12 h-12 mx-auto rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-900">Sertifikat Tidak Ditemukan</h2>
                    <p class="text-xs text-slate-500 mt-1">
                        Nomor seri <span class="font-mono font-bold text-slate-700">{{ code }}</span> tidak terdaftar dalam basis data penerbitan sertifikat resmi.
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

