<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

const inputCode = ref('');
const isLoading = ref(false);
const result = ref(null);
const errorMessage = ref('');
const scanHistory = ref([]);

// Camera scanning state
const videoRef = ref(null);
const cameraActive = ref(false);
const cameraSupported = ref(false);
let mediaStream = null;
let barcodeDetector = null;
let scanInterval = null;

// Sound feedback using Web Audio API
const playSound = (isSuccess) => {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        if (isSuccess) {
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.35);
        } else {
            osc.frequency.setValueAtTime(220, ctx.currentTime); // A3
            osc.frequency.setValueAtTime(164.81, ctx.currentTime + 0.15); // E3
            gain.gain.setValueAtTime(0.4, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        }
    } catch {
        // audio context not allowed without interaction, ignore
    }
};

const handleLookup = async (codeToLookup = null) => {
    const code = (codeToLookup || inputCode.value || '').trim();
    if (!code) return;

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post(route('admin.qr-scanner.lookup'), { code });
        if (response.data.found) {
            result.value = response.data;
            playSound(response.data.is_eligible);
            // prepend to history
            scanHistory.value.unshift({
                code: response.data.code,
                name: response.data.full_name,
                team: response.data.team_name,
                is_eligible: response.data.is_eligible,
                entity_type: response.data.entity_type,
                timestamp: new Date().toLocaleTimeString('id-ID'),
            });
            if (scanHistory.value.length > 10) scanHistory.value.pop();
        } else {
            result.value = null;
            errorMessage.value = response.data.message || 'Kode tidak ditemukan dalam sistem akreditasi resmi.';
            playSound(false);
        }
    } catch (err) {
        result.value = null;
        errorMessage.value = err.response?.data?.message || 'Gagal memverifikasi kode. Periksa koneksi jaringan.';
        playSound(false);
    } finally {
        isLoading.value = false;
        inputCode.value = '';
    }
};

const clearResult = () => {
    result.value = null;
    errorMessage.value = '';
};

// Camera initiation
const startCamera = async () => {
    errorMessage.value = '';
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            errorMessage.value = 'Browser Anda tidak mendukung akses kamera langsung.';
            return;
        }

        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });

        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            videoRef.value.play();
            cameraActive.value = true;

            if ('BarcodeDetector' in window) {
                barcodeDetector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39'] });
                scanInterval = setInterval(async () => {
                    if (!videoRef.value || videoRef.value.readyState < 2 || isLoading.value || result.value) return;
                    try {
                        const barcodes = await barcodeDetector.detect(videoRef.value);
                        if (barcodes.length > 0) {
                            const detected = barcodes[0].rawValue;
                            if (detected) {
                                handleLookup(detected);
                            }
                        }
                    } catch {
                        // detection error, ignore frame
                    }
                }, 400);
            }
        }
    } catch (err) {
        cameraActive.value = false;
        errorMessage.value = 'Izin kamera ditolak atau kamera sedang digunakan aplikasi lain. Anda tetap dapat menggunakan input manual atau scanner barcode USB/Bluetooth.';
    }
};

const stopCamera = () => {
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => track.stop());
        mediaStream = null;
    }
    cameraActive.value = false;
};

onMounted(() => {
    cameraSupported.value = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;
});

onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <AdminLayout
        page-title="Web QR Scanner Lapangan"
        subtitle="Verifikasi keabsahan pemain & ofisial, status screening, dan deteksi sanksi disiplin kartu merah / akumulasi secara instan saat matchday."
    >
        <Head title="Web QR Scanner Matchday" />

        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Breadcrumb Navigation -->
            <div class="flex items-center justify-between">
                <Link :href="route('admin.screening.index')" class="text-xs text-slate-500 hover:text-orange-600 font-medium transition-colors">
                    &larr; Kembali ke Antrean Screening
                </Link>
                <span class="text-xs font-mono font-bold text-slate-400">Matchday Inspection Mode</span>
            </div>

            <!-- Scanner Control Card -->
            <div class="frosted-card p-6 border border-slate-200/90 shadow-sm space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Pemindai ID Card &amp; Kode Akreditasi</h2>
                        <p class="text-xs text-slate-500">
                            Arahkan kamera ke QR Code pada ID Card pemain/ofisial, atau ketik / scan dengan alat barcode scanner genggam.
                        </p>
                    </div>

                    <button
                        v-if="!cameraActive"
                        type="button"
                        class="btn-pitch text-xs inline-flex items-center gap-1.5 self-start sm:self-auto shrink-0"
                        @click="startCamera"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Aktifkan Kamera Live
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn-secondary text-xs inline-flex items-center gap-1.5 self-start sm:self-auto text-rose-700 hover:bg-rose-50 shrink-0"
                        @click="stopCamera"
                    >
                        Matikan Kamera
                    </button>
                </div>

                <!-- Video Stream Viewport -->
                <div v-show="cameraActive" class="relative rounded-2xl overflow-hidden bg-black max-w-md mx-auto aspect-4/3 flex items-center justify-center border-2 border-orange-500 shadow-lg">
                    <video ref="videoRef" class="w-full h-full object-cover" playsinline autoplay muted></video>
                    <!-- Targeting Reticle Overlay -->
                    <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                        <div class="w-56 h-56 border-2 border-dashed border-white/80 rounded-2xl animate-pulse"></div>
                    </div>
                    <span class="absolute bottom-3 px-3 py-1 rounded-full bg-black/60 text-white text-[11px] font-medium backdrop-blur-xs">
                        Posisikan QR Code di dalam kotak
                    </span>
                </div>

                <!-- Manual / Barcode Gun Input Form -->
                <form @submit.prevent="() => handleLookup()" class="flex flex-col sm:flex-row items-center gap-2">
                    <div class="relative w-full">
                        <input
                            type="text"
                            v-model="inputCode"
                            placeholder="Scan Barcode / Ketik Kode Akreditasi (Contoh: YT-E01-PL0001 atau OFF-0001)..."
                            class="form-input text-xs w-full py-2.5 font-mono uppercase tracking-wider"
                            autofocus
                        />
                        <button
                            v-if="inputCode"
                            type="button"
                            class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600 text-xs font-bold"
                            @click="inputCode = ''"
                        >
                            &times;
                        </button>
                    </div>
                    <button
                        type="submit"
                        class="btn-pitch text-xs px-6 py-2.5 w-full sm:w-auto shrink-0 inline-flex items-center justify-center gap-1.5"
                        :disabled="isLoading || !inputCode.trim()"
                    >
                        <svg v-if="isLoading" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ isLoading ? 'Memindai...' : 'Periksa Data' }}</span>
                    </button>
                </form>

                <!-- Error Notice -->
                <div v-if="errorMessage" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ errorMessage }}</span>
                    </div>
                    <button type="button" class="text-xs font-bold text-rose-700 hover:underline" @click="errorMessage = ''">Tutup</button>
                </div>
            </div>

            <!-- ================= RESULT INSPECTION CARD ================= -->
            <div
                v-if="result"
                class="rounded-3xl p-6 sm:p-8 border-2 shadow-xl transition-all"
                :class="result.is_eligible
                    ? 'bg-emerald-500/10 border-emerald-500 text-slate-900'
                    : 'bg-rose-500/10 border-rose-500 text-slate-900'"
            >
                <!-- Status Banner -->
                <div
                    class="py-3 px-4 rounded-2xl text-center font-black tracking-wider text-sm sm:text-base flex items-center justify-center gap-2 shadow-xs mb-6"
                    :class="result.is_eligible ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'"
                >
                    <svg v-if="result.is_eligible" class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>{{ result.status_label }}</span>
                </div>

                <!-- Entity Profile Details -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-6 items-center">
                    <!-- Photo & Number -->
                    <div class="sm:col-span-1 flex flex-col items-center justify-center text-center space-y-2">
                        <div class="relative">
                            <img
                                v-if="result.photo_url"
                                :src="result.photo_url"
                                :alt="result.full_name"
                                class="w-28 h-36 object-cover rounded-2xl border-2 border-white shadow-md bg-white"
                            />
                            <div
                                v-else
                                class="w-28 h-36 rounded-2xl bg-slate-200 border-2 border-white shadow-md flex items-center justify-center font-bold text-2xl text-slate-500"
                            >
                                {{ result.full_name?.charAt(0) }}
                            </div>
                            <span
                                v-if="result.jersey_number"
                                class="absolute -bottom-2 -right-2 px-2.5 py-0.5 rounded-lg bg-slate-900 text-orange-400 font-mono font-black text-sm shadow-md"
                            >
                                #{{ result.jersey_number }}
                            </span>
                        </div>
                        <span class="text-xs font-mono font-bold text-slate-500">{{ result.code }}</span>
                    </div>

                    <!-- Details -->
                    <div class="sm:col-span-3 space-y-3">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                {{ result.entity_type === 'player' ? 'Pemain Resmi' : 'Ofisial Tim' }}
                            </span>
                            <h3 class="text-xl sm:text-2xl font-black text-slate-900 leading-tight">
                                {{ result.full_name }}
                            </h3>
                            <p class="text-xs font-semibold text-orange-950 mt-0.5">
                                {{ result.position || result.role }} &bull; {{ result.team_name }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-slate-200/80">
                            <div>
                                <span class="text-slate-500 block">Kompetisi / Event:</span>
                                <strong class="text-slate-800">{{ result.event_name }} ({{ result.age_category }})</strong>
                            </div>
                            <div v-if="result.birth_date">
                                <span class="text-slate-500 block">Tanggal Lahir:</span>
                                <strong class="text-slate-800 tabular-data">{{ result.birth_date }}</strong>
                            </div>
                            <div v-if="result.license_number">
                                <span class="text-slate-500 block">No. Lisensi Pelatih:</span>
                                <strong class="text-slate-800 font-mono">{{ result.license_number }}</strong>
                            </div>
                        </div>

                        <!-- Disciplinary Details for Player -->
                        <div v-if="result.entity_type === 'player'" class="pt-2 border-t border-slate-200/80 flex flex-wrap items-center gap-3 text-xs">
                            <span class="text-slate-500">Catatan Disiplin Turnamen:</span>
                            <span class="px-2 py-0.5 rounded-md font-bold text-[11px] bg-amber-100 text-amber-900 border border-amber-300">
                                {{ result.yellow_cards || 0 }} Kartu Kuning
                            </span>
                            <span class="px-2 py-0.5 rounded-md font-bold text-[11px] bg-rose-100 text-rose-900 border border-rose-300">
                                {{ result.red_cards || 0 }} Kartu Merah
                            </span>
                            <span v-if="result.is_suspended" class="font-bold text-rose-700">
                                &bull; {{ result.suspension_reason }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer Next Action -->
                <div class="mt-6 pt-4 border-t border-slate-200/80 flex items-center justify-between">
                    <span class="text-xs text-slate-500">Verifikasi lapangan resmi &bull; YT Event Management</span>
                    <button
                        type="button"
                        class="btn-pitch text-xs px-5 py-2 inline-flex items-center gap-1.5"
                        @click="clearResult"
                    >
                        <span>Scan Pemain Berikutnya &rarr;</span>
                    </button>
                </div>
            </div>

            <!-- ================= SCAN HISTORY LOG ================= -->
            <div v-if="scanHistory.length > 0" class="frosted-card p-5 border border-slate-200/90 shadow-2xs space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Riwayat Scan Terakhir (Sesi Ini)</h3>
                    <span class="text-[11px] text-slate-400">{{ scanHistory.length }} data</span>
                </div>
                <div class="divide-y divide-slate-100 text-xs">
                    <div
                        v-for="(item, idx) in scanHistory"
                        :key="idx"
                        class="py-2.5 flex items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="w-2.5 h-2.5 rounded-full shrink-0"
                                :class="item.is_eligible ? 'bg-emerald-500' : 'bg-rose-500'"
                            ></span>
                            <span class="font-mono font-bold text-slate-700">{{ item.code }}</span>
                            <span class="font-bold text-slate-900">{{ item.name }}</span>
                            <span class="text-slate-400">({{ item.team }})</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold"
                                :class="item.is_eligible ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                            >
                                {{ item.is_eligible ? 'SAH' : 'TIDAK LAYAK' }}
                            </span>
                            <span class="text-[11px] text-slate-400 font-mono">{{ item.timestamp }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
