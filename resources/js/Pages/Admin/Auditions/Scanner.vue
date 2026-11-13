<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import axios from 'axios';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    selectedEventId: {
        type: Number,
        default: 0,
    },
    stages: {
        type: Array,
        default: () => [],
    },
    selectedStageId: {
        type: Number,
        default: 0,
    },
    attendancesCount: {
        type: Number,
        default: 0,
    },
});

const currentEventId = ref(props.selectedEventId);
const currentStageId = ref(props.selectedStageId);
const liveCount = ref(props.attendancesCount);

const inputCode = ref('');
const isLoading = ref(false);
const cameraLoading = ref(false);
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
const playSound = (type) => {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.35);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.35);
        } else if (type === 'warning') {
            osc.frequency.setValueAtTime(440, ctx.currentTime); // A4
            osc.frequency.setValueAtTime(440, ctx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        } else {
            // Error
            osc.frequency.setValueAtTime(220, ctx.currentTime); // A3
            osc.frequency.setValueAtTime(164.81, ctx.currentTime + 0.15); // E3
            gain.gain.setValueAtTime(0.4, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        }
    } catch {
        // audio context not allowed without interaction
    }
};

const onEventChange = () => {
    router.get(route('admin.auditions.scanner'), {
        event_id: currentEventId.value,
    }, { preserveState: false });
};

const onStageChange = () => {
    router.get(route('admin.auditions.scanner'), {
        event_id: currentEventId.value,
        stage_id: currentStageId.value,
    }, { preserveState: false });
};

const handleCheckIn = async (codeToLookup = null) => {
    const code = (codeToLookup || inputCode.value || '').trim();
    if (!code) return;

    if (!currentStageId.value) {
        errorMessage.value = 'Silakan pilih gelombang / tahap audisi terlebih dahulu.';
        playSound('error');
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post(route('admin.auditions.scanner.check-in'), {
            code,
            stage_id: currentStageId.value,
        });

        result.value = response.data;

        if (response.data.already_checked_in) {
            playSound('warning');
        } else {
            playSound('success');
            liveCount.value = response.data.total_attendances_count || (liveCount.value + 1);
        }

        scanHistory.value.unshift({
            code: response.data.participant?.registration_number || response.data.participant?.screening_code || code,
            name: response.data.participant?.full_name || 'Peserta',
            status: response.data.already_checked_in ? 'Sudah Hadir' : 'Check-in Sukses',
            status_type: response.data.already_checked_in ? 'warning' : 'success',
            time: response.data.check_in_at || new Date().toLocaleTimeString('id-ID'),
        });
        if (scanHistory.value.length > 8) scanHistory.value.pop();

    } catch (err) {
        const errorData = err.response?.data;
        result.value = errorData || null;
        errorMessage.value = errorData?.message || 'Gagal memverifikasi kode. Periksa koneksi jaringan atau kode tiket.';
        playSound('error');

        scanHistory.value.unshift({
            code: errorData?.participant?.registration_number || errorData?.participant?.screening_code || code,
            name: errorData?.participant?.full_name || 'Gagal Check-in',
            status: 'Ditolak: ' + (errorData?.gatekeeper_status || 'ERROR'),
            status_type: 'error',
            time: new Date().toLocaleTimeString('id-ID'),
        });
        if (scanHistory.value.length > 8) scanHistory.value.pop();
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
    cameraLoading.value = true;
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            errorMessage.value = 'Browser Anda tidak mendukung akses kamera langsung.';
            return;
        }

        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
        });

        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            videoRef.value.play();
            cameraActive.value = true;
            initBarcodeScanner();
        }
    } catch (err) {
        console.error('Camera access error:', err);
        cameraActive.value = false;
        errorMessage.value = 'Tidak dapat mengakses kamera. Pastikan izin kamera telah diberikan di browser.';
    } finally {
        cameraLoading.value = false;
    }
};

const stopCamera = () => {
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }
    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }
    cameraActive.value = false;
};

const initBarcodeScanner = () => {
    if ('BarcodeDetector' in window) {
        try {
            barcodeDetector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'ean_13'] });
            scanInterval = setInterval(async () => {
                if (videoRef.value && videoRef.value.readyState === 4 && !isLoading.value) {
                    try {
                        const barcodes = await barcodeDetector.detect(videoRef.value);
                        if (barcodes.length > 0) {
                            const detectedVal = barcodes[0].rawValue;
                            if (detectedVal) {
                                handleCheckIn(detectedVal);
                            }
                        }
                    } catch (e) {
                        // frame detection error, ignore
                    }
                }
            }, 500);
            return;
        } catch {
            // fallback
        }
    }
};

onMounted(() => {
    cameraSupported.value = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
});

onUnmounted(() => {
    stopCamera();
});
</script>

<template>
    <AdminLayout title="Presensi &amp; Check-in Peserta">
        <Head title="Presensi &amp; Check-in Peserta" />

        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="frosted-card p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-orange-50 text-orange-950 border border-orange-200 text-xs font-bold uppercase tracking-wider mb-1">
                        <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span>Gatekeeper Check-in</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Presensi &amp; Check-in Peserta</h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Pemindaian tiket barcode/QR, pemeriksaan identitas peserta, dan pencatatan presensi kehadiran gerbang.
                    </p>
                </div>

                <!-- Live Attendance Counter Badge -->
                <div class="flex items-center gap-3 bg-gradient-to-r from-orange-600 to-red-600 text-white px-5 py-3 rounded-2xl shadow-md">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-orange-100">Kehadiran Hari Ini</div>
                        <div class="text-2xl font-black tabular-nums tracking-tight">{{ liveCount }} <span class="text-xs font-normal text-orange-200">Peserta</span></div>
                    </div>
                </div>
            </div>

            <!-- Event & Stage Selectors -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="frosted-card p-4">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Pilih Event Kegiatan
                    </label>
                    <select
                        v-model="currentEventId"
                        class="input-pitch w-full text-sm"
                        :disabled="isLoading"
                        title="Pilih event untuk memuat tahap atau sesi acara"
                        @change="onEventChange"
                    >
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">
                            {{ ev.name }} ({{ ev.city || 'DKI Jakarta' }})
                        </option>
                    </select>
                </div>

                <div class="frosted-card p-4">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Pilih Tahap / Sesi Acara
                    </label>
                    <select
                        v-model="currentStageId"
                        class="input-pitch w-full text-sm"
                        :disabled="isLoading || stages.length === 0"
                        :title="stages.length === 0 ? 'Belum ada tahap / sesi yang dikonfigurasi' : 'Pilih tahap untuk check-in'"
                        @change="onStageChange"
                    >
                        <option v-if="stages.length === 0" :value="0">
                            (Belum ada tahap dikonfigurasi)
                        </option>
                        <option v-for="stg in stages" :key="stg.id" :value="stg.id">
                            Tahap {{ stg.stage_number }}: {{ stg.stage_name }} ({{ stg.stage_date || 'Hari Ini' }})
                        </option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Kolom Kiri: Scanner Kamera & Input Manual -->
                <div class="lg:col-span-5 space-y-4">
                    <!-- Scanner Box -->
                    <div class="frosted-card p-4 overflow-hidden relative">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-700">Kamera Scanner QR</span>
                            <button
                                v-if="cameraActive"
                                type="button"
                                class="px-2.5 py-1 text-xs font-bold rounded-lg bg-rose-50 text-rose-600 border border-rose-200 hover:bg-rose-100 transition"
                                @click="stopCamera"
                            >
                                Matikan Kamera
                            </button>
                            <button
                                v-else
                                type="button"
                                class="btn-pitch text-xs px-3 py-1"
                                :disabled="cameraLoading || !cameraSupported"
                                :title="!cameraSupported ? 'Kamera tidak tersedia di browser ini' : (cameraLoading ? 'Kamera sedang disiapkan' : 'Aktifkan kamera untuk memindai kode')"
                                @click="startCamera"
                            >
                                <span v-if="cameraLoading">Menyiapkan...</span>
                                <span v-else>Buka Kamera</span>
                            </button>
                        </div>

                        <!-- Video Container -->
                        <div class="relative bg-slate-900 rounded-xl overflow-hidden aspect-video flex items-center justify-center border border-slate-800">
                            <video
                                ref="videoRef"
                                class="w-full h-full object-cover"
                                playsinline
                                muted
                            ></video>

                            <div v-if="cameraActive" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                <div class="w-48 h-48 border-2 border-orange-500 rounded-2xl relative">
                                    <div class="absolute -top-1 -left-1 w-4 h-4 border-t-4 border-l-4 border-orange-400"></div>
                                    <div class="absolute -top-1 -right-1 w-4 h-4 border-t-4 border-r-4 border-orange-400"></div>
                                    <div class="absolute -bottom-1 -left-1 w-4 h-4 border-b-4 border-l-4 border-orange-400"></div>
                                    <div class="absolute -bottom-1 -right-1 w-4 h-4 border-b-4 border-r-4 border-orange-400"></div>
                                </div>
                            </div>

                            <div v-if="!cameraActive" class="text-center p-6 text-slate-400 space-y-2">
                                <svg class="w-12 h-12 mx-auto text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <p class="text-xs">Klik "Buka Kamera" untuk scan QR Digital Pass (E-Tiket) secara otomatis.</p>
                            </div>
                        </div>

                        <!-- Input Manual Fallback -->
                        <form class="mt-4 pt-3 border-t border-slate-100 space-y-2" @submit.prevent="handleCheckIn()">
                            <label class="block text-xs font-bold text-slate-600">
                                Atau Masukkan ID Digital Pass / Kode QR / NIK:
                            </label>
                            <div class="flex gap-2">
                                <input
                                    v-model="inputCode"
                                    type="text"
                                    placeholder="Contoh: AUD-2026-..., SCR-AUD-..., atau NIK"
                                    class="input-pitch flex-1 text-sm font-mono uppercase"
                                    :disabled="isLoading"
                                    :title="isLoading ? 'Pemeriksaan sedang berlangsung' : 'Masukkan ID Digital Pass, kode QR, atau NIK'"
                                />
                                <button
                                    type="submit"
                                    class="btn-pitch text-xs px-4 whitespace-nowrap shadow-sm"
                                    :disabled="isLoading || !inputCode.trim()"
                                    :title="isLoading ? 'Pemeriksaan sedang berlangsung' : (!inputCode.trim() ? 'Masukkan kode terlebih dahulu' : 'Verifikasi dan catat check-in')"
                                >
                                    <span v-if="isLoading">Cek...</span>
                                    <span v-else>Check-in</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Scan History List -->
                    <div class="frosted-card p-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Riwayat Scan Sesi Ini</div>
                        <div v-if="scanHistory.length === 0" class="text-xs text-slate-400 py-3 text-center italic">
                            Belum ada riwayat scan pada sesi ini.
                        </div>
                        <div v-else class="space-y-1.5 max-h-56 overflow-y-auto">
                            <div
                                v-for="(hist, idx) in scanHistory"
                                :key="idx"
                                class="p-2 rounded-lg text-xs flex items-center justify-between gap-2"
                                :class="{
                                    'bg-emerald-50 text-emerald-900 border border-emerald-100': hist.status_type === 'success',
                                    'bg-amber-50 text-amber-900 border border-amber-100': hist.status_type === 'warning',
                                    'bg-rose-50 text-rose-900 border border-rose-100': hist.status_type === 'error',
                                }"
                            >
                                <div class="truncate">
                                    <strong class="font-mono">{{ hist.code }}</strong> - {{ hist.name }}
                                </div>
                                <div class="shrink-0 text-[11px] opacity-80">{{ hist.time }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Hasil Gatekeeper Guard & Anti-Joki Card -->
                <div class="lg:col-span-7">
                    <!-- Error / Ditolak Banner -->
                    <div v-if="errorMessage" class="frosted-card p-5 border-l-4 border-l-rose-600 bg-rose-50/90 text-rose-900 mb-4 space-y-2">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-rose-200 text-rose-700 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-rose-950">Akses Masuk Lapangan Ditolak</h3>
                                <p class="text-xs text-rose-800 mt-0.5 leading-relaxed">{{ errorMessage }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Result Card: Anti-Joki Face Match & Detail -->
                    <div v-if="result?.participant" class="frosted-card overflow-hidden shadow-lg border border-slate-200">
                        <!-- Top Banner -->
                        <div
                            class="p-4 text-white flex items-center justify-between"
                            :class="{
                                'bg-gradient-to-r from-emerald-600 to-teal-600': result.gatekeeper_status === 'CHECK_IN_SUCCESS',
                                'bg-gradient-to-r from-amber-600 to-orange-600': result.gatekeeper_status === 'ALREADY_PRESENT',
                                'bg-gradient-to-r from-rose-600 to-red-700': !result.success,
                            }"
                        >
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg bg-white/20 flex items-center justify-center shrink-0">
                                    <svg v-if="result.gatekeeper_status === 'CHECK_IN_SUCCESS'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else-if="result.gatekeeper_status === 'ALREADY_PRESENT'" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-xs font-black uppercase tracking-wider">
                                        {{ result.gatekeeper_status === 'CHECK_IN_SUCCESS' ? 'Check-in Berhasil' : (result.gatekeeper_status === 'ALREADY_PRESENT' ? 'Peserta Sudah Hadir' : 'Akses Ditolak') }}
                                    </div>
                                    <div class="text-[11px] text-white/90">{{ result.message }}</div>
                                </div>
                            </div>
                            <span v-if="result.check_in_at" class="text-xs font-mono font-bold bg-white/20 px-2.5 py-1 rounded-lg">
                                {{ result.check_in_at }} WIB
                            </span>
                        </div>

                        <!-- Face & Body Match Section (Anti-Joki) -->
                        <div class="p-6 space-y-6">
                            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 pb-5 border-b border-slate-100">
                                <!-- Photo Box with Giant Bib Number -->
                                <div class="relative shrink-0 text-center">
                                    <img
                                        v-if="result.participant.photo_url"
                                        :src="result.participant.photo_url"
                                        :alt="result.participant.full_name"
                                        class="w-32 h-40 rounded-2xl object-cover border-4 border-orange-500 shadow-md bg-slate-100"
                                    />
                                    <div v-else class="w-32 h-40 rounded-2xl bg-slate-100 border-2 border-dashed border-slate-300 text-slate-400 flex items-center justify-center font-bold text-xs">
                                        FOTO TIDAK ADA
                                    </div>
                                    <div class="mt-2 text-[10px] font-bold uppercase text-slate-500">
                                        Cocokkan Wajah Atlet
                                    </div>
                                </div>

                                <!-- Participant Identity -->
                                <div class="flex-1 space-y-2 text-center sm:text-left">
                                    <div class="flex flex-wrap gap-2 items-center justify-center sm:justify-start">
                                        <div class="inline-block bg-orange-50 border border-orange-200 rounded-xl px-4 py-1.5">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-orange-700">ID Digital Pass</div>
                                            <div class="text-xl font-black text-orange-700 font-mono tracking-tight">
                                                {{ result.participant.registration_number || ('PASS-' + result.participant.id) }}
                                            </div>
                                        </div>
                                        <div v-if="result.participant.bib_number" class="inline-block bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-1.5">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">No. Bib (Opsional)</div>
                                            <div class="text-sm font-black text-slate-800 font-mono tracking-tight mt-0.5">
                                                #{{ result.participant.bib_number }}
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <h2 class="text-xl font-black text-slate-900">{{ result.participant.full_name }}</h2>
                                        <p v-if="result.participant.nickname" class="text-xs font-bold text-orange-600">
                                            Nama Panggilan: "{{ result.participant.nickname }}"
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2 justify-center sm:justify-start pt-1">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                                            Posisi: {{ result.participant.preferred_position }}
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                                            Kaki: {{ result.participant.dominant_foot }}
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                                            Usia: {{ result.participant.age }} Thn ({{ result.participant.birth_date }})
                                        </span>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800">
                                            SSB: {{ result.participant.current_club_ssb }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Gatekeeper Verification Status Breakdown -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="p-3 rounded-xl border" :class="result.participant.is_paid ? 'bg-emerald-50/80 border-emerald-200' : 'bg-rose-50/80 border-rose-200'">
                                    <div class="text-[10px] font-bold uppercase text-slate-500">Status Pembayaran</div>
                                    <div class="text-xs font-black mt-0.5" :class="result.participant.is_paid ? 'text-emerald-700' : 'text-rose-700'">
                                        {{ result.participant.is_paid ? 'Lunas' : 'Belum Lunas' }}
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl border" :class="result.participant.screening_status === 'passed' ? 'bg-emerald-50/80 border-emerald-200' : 'bg-rose-50/80 border-rose-200'">
                                    <div class="text-[10px] font-bold uppercase text-slate-500">Screening Berkas</div>
                                    <div class="text-xs font-black mt-0.5" :class="result.participant.screening_status === 'passed' ? 'text-emerald-700' : 'text-rose-700'">
                                        {{ result.participant.screening_status === 'passed' ? 'Sah (Lolos)' : 'Belum Sah' }}
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl border bg-slate-50 border-slate-200">
                                    <div class="text-[10px] font-bold uppercase text-slate-500">Status Tahap Audisi</div>
                                    <div class="text-xs font-black text-slate-800 mt-0.5">
                                        {{ result.participant.stage_status_label }}
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 flex justify-end">
                                <button
                                    type="button"
                                    class="px-4 py-1.5 text-xs font-bold rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-100 transition"
                                    @click="clearResult"
                                >
                                    Tutup / Scan Peserta Berikutnya
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Placeholder Saat Belum Ada Scan -->
                    <div v-else-if="!errorMessage" class="frosted-card p-12 text-center text-slate-400 space-y-3">
                        <div class="w-16 h-16 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-700">Menunggu Scan Digital Pass / E-Tiket</h3>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto">
                            Arahkan QR Code Digital Pass ke kamera atau ketik ID Digital Pass / NIK secara manual di panel kiri untuk memverifikasi keabsahan peserta.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
