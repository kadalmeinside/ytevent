<script setup>
import { ref, nextTick, onMounted, onUnmounted } from 'vue';
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
const codeInputRef = ref(null);
const resultSectionRef = ref(null);

const isLoading = ref(false);
const cameraLoading = ref(false);
const result = ref(null);
const errorMessage = ref('');
const scanHistory = ref([]);
const soundEnabled = ref(true);
const isPhotoZoomOpen = ref(false);

// Camera scanning state
const videoRef = ref(null);
const cameraActive = ref(false);
const cameraSupported = ref(false);
const barcodeDetectorSupported = ref(true);

let mediaStream = null;
let barcodeDetector = null;
let scanInterval = null;
let audioCtx = null;
let lastAutoScannedCode = '';
let lastAutoScannedAt = 0;

const AUTO_SCAN_COOLDOWN_MS = 3000;

const playSound = (type) => {
    if (!soundEnabled.value) return;

    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;

        if (!audioCtx || audioCtx.state === 'closed') {
            audioCtx = new AudioContextClass();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }

        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(587.33, audioCtx.currentTime);
            osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.28, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.35);
        } else if (type === 'warning') {
            osc.frequency.setValueAtTime(440, audioCtx.currentTime);
            osc.frequency.setValueAtTime(440, audioCtx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.22, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.3);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.3);
        } else {
            osc.frequency.setValueAtTime(220, audioCtx.currentTime);
            osc.frequency.setValueAtTime(164.81, audioCtx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.32, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.4);
        }
    } catch {
        // AudioContext blocked until user interaction
    }
};

const focusCodeInput = () => {
    nextTick(() => {
        if (codeInputRef.value && window.innerWidth >= 1024) {
            codeInputRef.value.focus();
        }
    });
};

const scrollResultIntoViewOnMobile = () => {
    nextTick(() => {
        if (window.innerWidth < 1024 && resultSectionRef.value) {
            resultSectionRef.value.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });
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

const handleCheckIn = async (codeToLookup = null, fromCamera = false) => {
    const code = (codeToLookup || inputCode.value || '').trim();
    if (!code || isLoading.value) return;

    if (fromCamera) {
        const now = Date.now();
        if (code === lastAutoScannedCode && now - lastAutoScannedAt < AUTO_SCAN_COOLDOWN_MS) {
            return;
        }
        lastAutoScannedCode = code;
        lastAutoScannedAt = now;
    }

    if (!currentStageId.value) {
        result.value = null;
        errorMessage.value = 'Silakan pilih gelombang atau tahap acara terlebih dahulu sebelum melakukan check-in.';
        playSound('error');
        scrollResultIntoViewOnMobile();
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';
    isPhotoZoomOpen.value = false;

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
            status: response.data.already_checked_in ? 'Sudah Hadir' : 'Hadir',
            status_type: response.data.already_checked_in ? 'warning' : 'success',
            time: response.data.check_in_at || new Date().toLocaleTimeString('id-ID'),
        });
        if (scanHistory.value.length > 8) scanHistory.value.pop();
    } catch (err) {
        const errorData = err.response?.data;
        result.value = errorData?.participant ? errorData : null;
        errorMessage.value = errorData?.message || 'Gagal memverifikasi kode. Periksa koneksi jaringan atau kode Digital Pass.';
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
        scrollResultIntoViewOnMobile();
        focusCodeInput();
    }
};

const clearResult = () => {
    result.value = null;
    errorMessage.value = '';
    isPhotoZoomOpen.value = false;
    lastAutoScannedCode = '';
    focusCodeInput();
};

const clearHistory = () => {
    scanHistory.value = [];
};

const startCamera = async () => {
    errorMessage.value = '';
    cameraLoading.value = true;
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            errorMessage.value = 'Browser Anda tidak mendukung akses kamera langsung.';
            return;
        }

        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
            audio: false,
        });

        if (videoRef.value) {
            videoRef.value.srcObject = mediaStream;
            await videoRef.value.play();
            cameraActive.value = true;
            initBarcodeScanner();
        }
    } catch {
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
        mediaStream.getTracks().forEach((track) => track.stop());
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
            barcodeDetectorSupported.value = true;
            barcodeDetector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'ean_13'] });
            scanInterval = setInterval(async () => {
                if (videoRef.value && videoRef.value.readyState === 4 && !isLoading.value) {
                    try {
                        const barcodes = await barcodeDetector.detect(videoRef.value);
                        if (barcodes.length > 0) {
                            const detectedVal = barcodes[0].rawValue;
                            if (detectedVal) {
                                handleCheckIn(detectedVal, true);
                            }
                        }
                    } catch {
                        // Frame detection error, ignore frame
                    }
                }
            }, 450);
            return;
        } catch {
            barcodeDetectorSupported.value = false;
        }
    } else {
        barcodeDetectorSupported.value = false;
    }
};

const handleKeydown = (event) => {
    if (event.key === 'Escape' && isPhotoZoomOpen.value) {
        isPhotoZoomOpen.value = false;
    }
};

onMounted(() => {
    cameraSupported.value = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    barcodeDetectorSupported.value = typeof window !== 'undefined' && 'BarcodeDetector' in window;
    window.addEventListener('keydown', handleKeydown);
    focusCodeInput();
});

onUnmounted(() => {
    stopCamera();
    window.removeEventListener('keydown', handleKeydown);
    if (audioCtx && audioCtx.state !== 'closed') {
        audioCtx.close().catch(() => {});
    }
});
</script>

<template>
    <AdminLayout
        page-title="Scanner Presensi Event Individual"
        subtitle="Pemindaian Digital Pass (E-Tiket), pencocokan foto anti-joki, dan pencatatan kehadiran gerbang."
    >
        <Head title="Scanner Presensi Event Individual" />

        <template #headerActions>
            <button
                type="button"
                :aria-pressed="soundEnabled ? 'true' : 'false'"
                class="btn-sm-secondary"
                @click="soundEnabled = !soundEnabled"
            >
                <svg class="w-4 h-4 shrink-0" :class="soundEnabled ? 'text-orange-600' : 'text-slate-500'" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        v-if="soundEnabled"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"
                    />
                    <path
                        v-else
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"
                    />
                </svg>
                <span>{{ soundEnabled ? 'Suara Aktif' : 'Suara Bisu' }}</span>
            </button>
        </template>

        <div class="space-y-5">
            <!-- Compact Top Context Toolbar: Event + Stage + Live Counter -->
            <div class="frosted-card p-4 bg-white">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
                    <div class="lg:col-span-5">
                        <label for="scanner-event-select" class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Event Kegiatan
                        </label>
                        <select
                            id="scanner-event-select"
                            v-model="currentEventId"
                            class="input-field"
                            :disabled="isLoading"
                            title="Pilih event untuk memuat tahap atau sesi acara"
                            @change="onEventChange"
                        >
                            <option v-if="events.length === 0" :value="0">
                                Belum ada event individual
                            </option>
                            <option v-for="ev in events" :key="ev.id" :value="ev.id">
                                {{ ev.name }} ({{ ev.city || 'DKI Jakarta' }})
                            </option>
                        </select>
                    </div>

                    <div class="lg:col-span-4">
                        <label for="scanner-stage-select" class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Tahap / Sesi Acara
                        </label>
                        <select
                            id="scanner-stage-select"
                            v-model="currentStageId"
                            class="input-field"
                            :disabled="isLoading || stages.length === 0"
                            :title="stages.length === 0 ? 'Belum ada tahap atau sesi yang dikonfigurasi' : 'Pilih tahap untuk check-in'"
                            @change="onStageChange"
                        >
                            <option v-if="stages.length === 0" :value="0">
                                Belum ada tahap dikonfigurasi
                            </option>
                            <option v-for="stg in stages" :key="stg.id" :value="stg.id">
                                Tahap {{ stg.stage_number }}: {{ stg.stage_name }} ({{ stg.stage_date || 'Hari Ini' }})
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2 lg:col-span-3 flex items-center justify-between gap-2.5 bg-gradient-to-r from-slate-900 via-slate-800 to-orange-900 text-white px-4 py-2.5 rounded-xl border border-slate-800">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-orange-500/20 border border-orange-400/30 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 text-orange-300" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-orange-200 truncate">Hadir Tahap Ini</div>
                                <div class="text-lg font-black tabular-data leading-tight">
                                    {{ liveCount }} <span class="text-xs font-semibold text-slate-200">Peserta</span>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            :aria-pressed="soundEnabled ? 'true' : 'false'"
                            aria-label="Toggle suara notifikasi scan"
                            class="lg:hidden w-10 h-10 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center shrink-0 transition-colors"
                            @click="soundEnabled = !soundEnabled"
                        >
                            <svg class="w-4 h-4 text-white" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    v-if="soundEnabled"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"
                                />
                                <path
                                    v-else
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Ergonomic Workstation Grid:
                 Mobile order: 1. Scanner/Input -> 2. Gatekeeper Result -> 3. Scan History
                 Desktop order: Left column (Scanner + History), Right column (Result Anti-Joki) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
                <!-- Panel 1: Input Manual / USB Scanner & Kamera Live -->
                <div class="order-1 lg:col-span-5 lg:col-start-1 lg:row-start-1 frosted-card p-4 bg-white space-y-4">
                    <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-800">Pemindai Digital Pass</span>
                            <span
                                v-if="cameraActive"
                                class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800 border border-emerald-200"
                            >
                                Kamera Aktif
                            </span>
                        </div>
                        <button
                            v-if="cameraActive"
                            type="button"
                            class="btn-sm-danger"
                            @click="stopCamera"
                        >
                            Matikan Kamera
                        </button>
                    </div>

                    <!-- 1. Live Video Container (Only Visible When Camera Active) -->
                    <div
                        v-show="cameraActive"
                        class="relative bg-slate-900 rounded-xl overflow-hidden aspect-video border border-slate-800 shadow-inner"
                    >
                        <video
                            ref="videoRef"
                            class="absolute inset-0 w-full h-full object-cover"
                            playsinline
                            muted
                        ></video>

                        <div class="absolute inset-0 pointer-events-none flex items-center justify-center p-4">
                            <div class="w-36 h-36 sm:w-44 sm:h-44 max-h-full aspect-square border-2 border-orange-500/85 rounded-2xl relative">
                                <div class="absolute -top-0.5 -left-0.5 w-4 h-4 border-t-4 border-l-4 border-orange-400 rounded-tl-md" />
                                <div class="absolute -top-0.5 -right-0.5 w-4 h-4 border-t-4 border-r-4 border-orange-400 rounded-tr-md" />
                                <div class="absolute -bottom-0.5 -left-0.5 w-4 h-4 border-b-4 border-l-4 border-orange-400 rounded-bl-md" />
                                <div class="absolute -bottom-0.5 -right-0.5 w-4 h-4 border-b-4 border-r-4 border-orange-400 rounded-br-md" />
                            </div>
                        </div>
                    </div>

                    <!-- 2. Primary Action: Manual & Hardware Barcode Gun Input -->
                    <form novalidate class="space-y-2" @submit.prevent="handleCheckIn()">
                        <label for="scanner-manual-code" class="block text-xs font-bold text-slate-800">
                            ID Digital Pass / Scanner Barcode / NIK
                        </label>
                        <div class="flex gap-2">
                            <input
                                id="scanner-manual-code"
                                ref="codeInputRef"
                                v-model="inputCode"
                                type="text"
                                autocomplete="off"
                                placeholder="Ketik atau scan ID Pass / NIK"
                                class="input-field flex-1 font-mono uppercase"
                                :disabled="isLoading"
                                @keydown.esc="inputCode = ''"
                            />
                            <button
                                type="submit"
                                class="btn-pitch shrink-0"
                                :disabled="isLoading || !inputCode.trim()"
                            >
                                {{ isLoading ? 'Memeriksa...' : 'Check-in' }}
                            </button>
                        </div>
                    </form>

                    <!-- 3. Progressive Disclosure Camera Trigger / Compatibility Notice -->
                    <div
                        v-if="!cameraSupported || !barcodeDetectorSupported"
                        class="px-3.5 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs leading-relaxed"
                    >
                        <strong class="font-bold">Kamera QR Nonaktif:</strong>
                        {{ !cameraSupported ? 'Akses kamera tidak tersedia di perangkat/browser ini.' : 'Auto-detect QR kamera tidak didukung native di browser ini.' }}
                        Gunakan kolom input di atas bersama <strong>scanner USB/Bluetooth</strong> atau ketik manual.
                    </div>

                    <div
                        v-else-if="!cameraActive"
                        class="p-3.5 rounded-xl bg-slate-50 border border-dashed border-slate-300 flex items-center justify-between gap-3"
                    >
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-9 h-9 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-700 shrink-0">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-800">Scan via Kamera Perangkat</div>
                                <div class="text-[11px] text-slate-600 truncate">Buka viewfinder untuk baca QR E-Tiket</div>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn-sm-secondary shrink-0"
                            :disabled="cameraLoading"
                            @click="startCamera"
                        >
                            {{ cameraLoading ? 'Menyiapkan...' : 'Buka Kamera' }}
                        </button>
                    </div>
                </div>

                <!-- Panel 2: Hasil Gatekeeper Guard & Anti-Joki Card -->
                <div ref="resultSectionRef" class="order-2 lg:col-span-7 lg:col-start-6 lg:row-start-1 lg:row-span-2 space-y-4">
                    <!-- Loading Lookup Skeleton -->
                    <div
                        v-if="isLoading"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                        class="frosted-card overflow-hidden bg-white border border-slate-200"
                    >
                        <span class="sr-only">Memverifikasi Digital Pass peserta...</span>
                        <div class="skeleton-block h-16 w-full rounded-none" aria-hidden="true" />
                        <div class="p-6 space-y-5" aria-hidden="true">
                            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 pb-5 border-b border-slate-100">
                                <div class="skeleton-block w-32 h-40 rounded-2xl shrink-0" />
                                <div class="flex-1 w-full space-y-3">
                                    <div class="skeleton-pill h-5 w-32" />
                                    <div class="skeleton-block h-7 w-3/4" />
                                    <div class="skeleton-block h-4 w-1/2" />
                                    <div class="grid grid-cols-2 gap-3 pt-2">
                                        <div class="skeleton-block h-4 w-4/5" />
                                        <div class="skeleton-block h-4 w-3/4" />
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="skeleton-block h-12 rounded-xl" />
                                <div class="skeleton-block h-12 rounded-xl" />
                                <div class="skeleton-block h-12 rounded-xl" />
                            </div>
                        </div>
                    </div>

                    <!-- Result Card: Anti-Joki Face Match & Detail -->
                    <div v-else-if="result?.participant" class="frosted-card overflow-hidden shadow-lg border border-slate-200 bg-white">
                        <!-- Top Decision Banner -->
                        <div
                            class="p-4 text-white flex flex-wrap items-center justify-between gap-3"
                            :class="{
                                'bg-gradient-to-r from-emerald-600 to-teal-600': result.gatekeeper_status === 'CHECK_IN_SUCCESS',
                                'bg-gradient-to-r from-amber-600 to-orange-600': result.gatekeeper_status === 'ALREADY_PRESENT',
                                'bg-gradient-to-r from-rose-600 to-red-700': !result.success,
                            }"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center shrink-0">
                                    <svg v-if="result.gatekeeper_status === 'CHECK_IN_SUCCESS'" class="w-5 h-5 text-white" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else-if="result.gatekeeper_status === 'ALREADY_PRESENT'" class="w-5 h-5 text-white" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-white" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-black uppercase tracking-wider">
                                        {{ result.gatekeeper_status === 'CHECK_IN_SUCCESS' ? 'Check-in Berhasil (Boleh Masuk)' : (result.gatekeeper_status === 'ALREADY_PRESENT' ? 'Peserta Sudah Hadir' : 'Akses Masuk Ditolak') }}
                                    </div>
                                    <div class="text-xs text-white/95 mt-0.5">{{ result.message || errorMessage }}</div>
                                </div>
                            </div>
                            <span v-if="result.check_in_at" class="text-xs font-mono font-bold bg-black/20 px-2.5 py-1 rounded-lg tabular-data shrink-0">
                                {{ result.check_in_at }} WIB
                            </span>
                        </div>

                        <!-- Face Match Section (Anti-Joki) -->
                        <div class="p-5 sm:p-6 space-y-5">
                            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 pb-5 border-b border-slate-100">
                                <div class="relative shrink-0 text-center">
                                    <button
                                        v-if="result.participant.photo_url"
                                        type="button"
                                        class="group relative block rounded-2xl overflow-hidden border-4 border-orange-500 shadow-md bg-slate-100 focus-visible:ring-2 focus-visible:ring-orange-600"
                                        title="Klik untuk memperbesar foto atlet"
                                        @click="isPhotoZoomOpen = true"
                                    >
                                        <img
                                            :src="result.participant.photo_url"
                                            :alt="`Foto ${result.participant.full_name}`"
                                            class="w-32 h-40 object-cover group-hover:scale-105 transition-transform duration-200"
                                        />
                                        <span class="absolute inset-x-0 bottom-0 bg-slate-900/75 text-white text-[10px] font-bold py-1">
                                            Perbesar Foto
                                        </span>
                                    </button>
                                    <div
                                        v-else
                                        class="w-32 h-40 rounded-2xl bg-slate-100 border-2 border-dashed border-slate-300 text-slate-600 flex items-center justify-center font-bold text-xs px-2"
                                    >
                                        FOTO TIDAK ADA
                                    </div>
                                    <div class="mt-2 text-[11px] font-bold uppercase text-slate-600">
                                        Cocokkan Wajah Atlet
                                    </div>
                                </div>

                                <div class="flex-1 space-y-2.5 text-center sm:text-left min-w-0">
                                    <div class="flex flex-wrap gap-2 items-center justify-center sm:justify-start">
                                        <div class="inline-block bg-orange-50 border border-orange-200 rounded-xl px-4 py-1.5">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-orange-800">ID Digital Pass</div>
                                            <div class="text-lg sm:text-xl font-black text-orange-800 font-mono tracking-tight">
                                                {{ result.participant.registration_number || ('PASS-' + result.participant.id) }}
                                            </div>
                                        </div>
                                        <div v-if="result.participant.bib_number" class="inline-block bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-1.5">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-600">No. Bib</div>
                                            <div class="text-sm font-black text-slate-900 font-mono tracking-tight mt-0.5">
                                                #{{ result.participant.bib_number }}
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <h2 class="text-xl font-black text-slate-900 break-words">{{ result.participant.full_name }}</h2>
                                        <p v-if="result.participant.nickname" class="text-xs font-bold text-orange-700 mt-0.5">
                                            Panggilan: "{{ result.participant.nickname }}"
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-1.5 justify-center sm:justify-start pt-0.5">
                                        <span
                                            v-if="result.participant.preferred_position"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200"
                                        >
                                            Posisi: {{ result.participant.preferred_position }}
                                        </span>
                                        <span
                                            v-if="result.participant.dominant_foot"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200 capitalize"
                                        >
                                            Kaki: {{ result.participant.dominant_foot }}
                                        </span>
                                        <span
                                            v-if="result.participant.age !== null && result.participant.age !== undefined"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200 tabular-data"
                                        >
                                            Usia: {{ result.participant.age }} Thn<template v-if="result.participant.birth_date"> ({{ result.participant.birth_date }})</template>
                                        </span>
                                        <span
                                            v-if="result.participant.current_club_ssb"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200"
                                        >
                                            Asal: {{ result.participant.current_club_ssb }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Gatekeeper Verification Status Breakdown -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="p-3 rounded-xl border" :class="result.participant.is_paid ? 'bg-emerald-50/80 border-emerald-200' : 'bg-rose-50/80 border-rose-200'">
                                    <div class="text-[11px] font-bold uppercase text-slate-600">Pembayaran</div>
                                    <div class="text-xs font-black mt-0.5" :class="result.participant.is_paid ? 'text-emerald-800' : 'text-rose-800'">
                                        {{ result.participant.is_paid ? 'Lunas' : 'Belum Lunas' }}
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl border" :class="result.participant.screening_status === 'passed' ? 'bg-emerald-50/80 border-emerald-200' : 'bg-rose-50/80 border-rose-200'">
                                    <div class="text-[11px] font-bold uppercase text-slate-600">Screening Berkas</div>
                                    <div class="text-xs font-black mt-0.5" :class="result.participant.screening_status === 'passed' ? 'text-emerald-800' : 'text-rose-800'">
                                        {{ result.participant.screening_status === 'passed' ? 'Sah (Lolos)' : 'Belum Sah' }}
                                    </div>
                                </div>

                                <div class="p-3 rounded-xl border bg-slate-50 border-slate-200">
                                    <div class="text-[11px] font-bold uppercase text-slate-600">Status Tahap</div>
                                    <div class="text-xs font-black text-slate-900 mt-0.5">
                                        {{ result.participant.stage_status_label || 'Terdaftar' }}
                                    </div>
                                </div>
                            </div>

                            <div class="pt-1 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-[11px] text-slate-600">
                                    Siap memindai peserta berikutnya tanpa perlu klik reset.
                                </span>
                                <button
                                    type="button"
                                    class="btn-sm-secondary"
                                    @click="clearResult"
                                >
                                    Tutup Hasil Scan
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Error Only Banner (e.g. Code Not Found / Missing Stage) -->
                    <div v-else-if="errorMessage" class="frosted-card p-5 border-l-4 border-l-rose-600 bg-rose-50/95 text-rose-950 space-y-3">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-xl bg-rose-200 text-rose-800 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h2 class="text-sm font-black text-rose-950">Akses Masuk Lapangan Ditolak</h2>
                                <p class="text-xs text-rose-900 mt-1 leading-relaxed">{{ errorMessage }}</p>
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" class="btn-sm-danger" @click="clearResult">
                                Tutup Peringatan
                            </button>
                        </div>
                    </div>

                    <!-- Idle State Placeholder -->
                    <div v-else class="frosted-card p-8 sm:p-12 text-center bg-white space-y-3">
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-600">
                            <svg class="w-8 h-8" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <h2 class="text-sm sm:text-base font-bold text-slate-900">Menunggu Scan Digital Pass / E-Tiket</h2>
                        <p class="text-xs text-slate-600 max-w-md mx-auto leading-relaxed">
                            Arahkan QR Code Digital Pass ke kamera atau gunakan scanner barcode / ketik ID Digital Pass dan NIK secara manual untuk memverifikasi keabsahan peserta.
                        </p>
                    </div>
                </div>

                <!-- Panel 3: Scan History List (Bawah Kiri Desktop, Paling Bawah Mobile) -->
                <div class="order-3 lg:col-span-5 lg:col-start-1 lg:row-start-2 frosted-card p-4 bg-white">
                    <div class="flex items-center justify-between gap-2 mb-2.5">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800">Riwayat Scan Sesi Ini</h2>
                        <button
                            v-if="scanHistory.length > 0"
                            type="button"
                            class="text-[11px] font-semibold text-slate-600 hover:text-slate-900 underline"
                            @click="clearHistory"
                        >
                            Bersihkan
                        </button>
                    </div>
                    <div v-if="scanHistory.length === 0" class="text-xs text-slate-600 py-3 text-center">
                        Belum ada riwayat scan pada sesi ini.
                    </div>
                    <div v-else class="space-y-1.5 max-h-56 overflow-y-auto">
                        <div
                            v-for="(hist, idx) in scanHistory"
                            :key="idx"
                            class="p-2.5 rounded-xl text-xs flex items-center justify-between gap-2 border"
                            :class="{
                                'bg-emerald-50 text-emerald-950 border-emerald-200': hist.status_type === 'success',
                                'bg-amber-50 text-amber-950 border-amber-200': hist.status_type === 'warning',
                                'bg-rose-50 text-rose-950 border-rose-200': hist.status_type === 'error',
                            }"
                        >
                            <div class="min-w-0 truncate">
                                <strong class="font-mono">{{ hist.code }}</strong>
                                <span class="mx-1 text-slate-500">/</span>
                                <span class="font-semibold">{{ hist.name }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-white/80 border border-black/10">
                                    {{ hist.status }}
                                </span>
                                <span class="text-[11px] font-mono tabular-data">{{ hist.time }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Anti-Joki Photo Zoom Modal -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isPhotoZoomOpen && result?.participant?.photo_url"
                role="dialog"
                aria-modal="true"
                aria-label="Perbesaran foto peserta"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs"
                @click.self="isPhotoZoomOpen = false"
            >
                <div class="bg-white rounded-2xl max-w-md w-full overflow-hidden shadow-2xl border border-slate-200">
                    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-xs font-black text-slate-900 truncate">{{ result.participant.full_name }}</div>
                            <div class="text-[11px] font-mono text-slate-600">{{ result.participant.registration_number }}</div>
                        </div>
                        <button
                            type="button"
                            class="btn-sm-secondary"
                            @click="isPhotoZoomOpen = false"
                        >
                            Tutup
                        </button>
                    </div>
                    <div class="p-4 bg-slate-50 flex justify-center">
                        <img
                            :src="result.participant.photo_url"
                            :alt="`Foto ${result.participant.full_name}`"
                            class="max-h-[70vh] w-auto rounded-xl object-contain border border-slate-200"
                        />
                    </div>
                </div>
            </div>
        </Transition>
    </AdminLayout>
</template>
