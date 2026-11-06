<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Pratinjau Dokumen',
    },
    subtitle: {
        type: String,
        default: '',
    },
    fileUrl: {
        type: String,
        default: '',
    },
    isPdf: {
        type: Boolean,
        default: false,
    },
    originalName: {
        type: String,
        default: '',
    },
    documentNumber: {
        type: String,
        default: '',
    },
    fileSize: {
        type: [Number, String],
        default: 0,
    },
    validationStatus: {
        type: String,
        default: '',
    },
    notes: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close']);

// Zoom & Rotation State for Images
const zoom = ref(1);
const rotation = ref(0);

const resetTransform = () => {
    zoom.value = 1;
    rotation.value = 0;
};

watch(
    () => props.fileUrl,
    () => {
        resetTransform();
    }
);

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            resetTransform();
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
);

const zoomIn = () => {
    if (zoom.value < 3) zoom.value = Number((zoom.value + 0.25).toFixed(2));
};

const zoomOut = () => {
    if (zoom.value > 0.5) zoom.value = Number((zoom.value - 0.25).toFixed(2));
};

const rotateClockwise = () => {
    rotation.value = (rotation.value + 90) % 360;
};

const handleKeyDown = (e) => {
    if (e.key === 'Escape' && props.isOpen) {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    document.body.style.overflow = '';
});

const formattedSize = computed(() => {
    const bytes = Number(props.fileSize) || 0;
    if (bytes === 0) return '';
    return `${(bytes / 1024).toFixed(0)} KB`;
});
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-slate-950/80 backdrop-blur-md transition-opacity duration-200"
        @click.self="emit('close')"
    >
        <div class="relative flex flex-col w-full max-w-5xl h-[92vh] max-h-[92vh] bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-200 bg-white/95 backdrop-blur-sm shrink-0">
                <div class="min-w-0 pr-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-sm sm:text-base font-bold text-slate-900 truncate">{{ title }}</h3>
                        <!-- Status Badge -->
                        <span
                            v-if="validationStatus"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                            :class="
                                validationStatus === 'valid'
                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                    : validationStatus === 'invalid'
                                    ? 'bg-rose-50 text-rose-800 border-rose-300'
                                    : 'bg-amber-50 text-amber-800 border-amber-300'
                            "
                        >
                            <svg v-if="validationStatus === 'valid'" class="w-3 h-3 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <svg v-else-if="validationStatus === 'invalid'" class="w-3 h-3 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <svg v-else class="w-3 h-3 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ validationStatus === 'valid' ? 'Dokumen Sah' : validationStatus === 'invalid' ? 'Perlu Revisi' : 'Menunggu Verifikasi' }}</span>
                        </span>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-slate-500 mt-0.5 flex-wrap">
                        <span v-if="subtitle" class="font-medium text-slate-700">{{ subtitle }}</span>
                        <span v-if="originalName" class="truncate font-mono text-[11px]">({{ originalName }})</span>
                        <span v-if="documentNumber" class="font-mono text-[11px] text-slate-600 font-semibold">No: {{ documentNumber }}</span>
                        <span v-if="formattedSize" class="font-mono text-[11px] text-slate-400">{{ formattedSize }}</span>
                    </div>
                </div>

                <!-- Right Action Buttons -->
                <div class="flex items-center gap-2 shrink-0">
                    <a
                        :href="fileUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn-sm-secondary hidden sm:inline-flex items-center gap-1.5 text-xs"
                        title="Buka dokumen di tab baru atau unduh"
                    >
                        <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                        <span>Tab Baru</span>
                    </a>

                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                        title="Tutup [ESC]"
                        @click="emit('close')"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Notes Banner if any -->
            <div
                v-if="notes"
                class="px-5 py-2.5 bg-rose-50 border-b border-rose-200 text-xs text-rose-900 flex items-start gap-2 shrink-0"
            >
                <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="min-w-0">
                    <strong class="font-bold">Catatan Verifikator:</strong> {{ notes }}
                </div>
            </div>

            <!-- Modal Content Area -->
            <div class="relative flex-1 bg-slate-900/5 overflow-hidden flex items-center justify-center">
                <!-- PDF Viewer (iframe) -->
                <div v-if="isPdf" class="w-full h-full flex flex-col p-2 sm:p-4">
                    <iframe
                        :src="fileUrl"
                        class="w-full flex-1 rounded-xl bg-white border border-slate-200 shadow-inner"
                        title="Pratinjau PDF"
                    ></iframe>
                </div>

                <!-- Image Viewer with Zoom & Rotate -->
                <div
                    v-else
                    class="relative w-full h-full overflow-auto flex items-center justify-center p-4 select-none"
                >
                    <img
                        :src="fileUrl"
                        :alt="title"
                        class="max-w-full max-h-full object-contain rounded-lg shadow-lg transition-transform duration-200 ease-out origin-center"
                        :style="{
                            transform: `scale(${zoom}) rotate(${rotation}deg)`,
                        }"
                    />

                    <!-- Floating Image Controls Bar -->
                    <div class="absolute bottom-4 right-4 bg-slate-900/85 backdrop-blur-md rounded-xl px-3 py-1.5 flex items-center gap-1.5 shadow-xl text-white text-xs border border-white/10 z-10">
                        <button
                            type="button"
                            class="p-1 hover:bg-white/20 rounded transition-colors"
                            title="Perkecil (-)"
                            @click="zoomOut"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                            </svg>
                        </button>

                        <span class="font-mono text-[11px] px-1 font-semibold text-slate-200">
                            {{ Math.round(zoom * 100) }}%
                        </span>

                        <button
                            type="button"
                            class="p-1 hover:bg-white/20 rounded transition-colors"
                            title="Perbesar (+)"
                            @click="zoomIn"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </button>

                        <div class="w-px h-4 bg-white/20 mx-1"></div>

                        <button
                            type="button"
                            class="p-1 hover:bg-white/20 rounded transition-colors"
                            title="Putar 90° Searah Jarum Jam"
                            @click="rotateClockwise"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            class="px-2 py-0.5 hover:bg-white/20 rounded font-semibold text-[11px] transition-colors"
                            title="Reset Tampilan (1:1)"
                            @click="resetTransform"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

