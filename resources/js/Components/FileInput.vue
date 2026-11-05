<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [File, Object, null],
        default: null,
    },
    accept: {
        type: String,
        default: '*/*',
    },
    maxSizeMb: {
        type: Number,
        default: 2,
    },
    label: {
        type: String,
        default: 'Pilih Berkas',
    },
    hint: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    previewUrl: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const fileInput = ref(null);
const isDragging = ref(false);
const internalError = ref('');

const selectedFile = computed(() => props.modelValue);

const displayFileName = computed(() => {
    if (selectedFile.value instanceof File) {
        return selectedFile.value.name;
    }
    if (props.previewUrl) {
        const parts = props.previewUrl.split('/');
        return parts[parts.length - 1] || 'Berkas Terunggah';
    }
    return '';
});

const displayFileSize = computed(() => {
    if (selectedFile.value instanceof File) {
        const bytes = selectedFile.value.size;
        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} KB`;
        }
        return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
    }
    return '';
});

const isImage = computed(() => {
    if (selectedFile.value instanceof File) {
        return selectedFile.value.type.startsWith('image/');
    }
    if (props.previewUrl) {
        return /\.(jpg|jpeg|png|webp|gif|svg)$/i.test(props.previewUrl);
    }
    return false;
});

const localPreview = computed(() => {
    if (selectedFile.value instanceof File && selectedFile.value.type.startsWith('image/')) {
        return URL.createObjectURL(selectedFile.value);
    }
    return props.previewUrl || '';
});

const openPicker = () => {
    if (props.disabled) return;
    fileInput.value?.click();
};

const handleFileSelect = (file) => {
    internalError.value = '';
    if (!file) return;

    if (props.maxSizeMb && file.size > props.maxSizeMb * 1024 * 1024) {
        internalError.value = `Ukuran berkas (${(file.size / (1024 * 1024)).toFixed(2)} MB) melebihi batas maksimal ${props.maxSizeMb} MB.`;
        return;
    }

    emit('update:modelValue', file);
    emit('change', file);
};

const onInputChange = (event) => {
    const file = event.target.files?.[0] || null;
    handleFileSelect(file);
};

const onDrop = (event) => {
    isDragging.value = false;
    if (props.disabled) return;
    const file = event.dataTransfer?.files?.[0] || null;
    handleFileSelect(file);
};

const clearFile = (e) => {
    e?.stopPropagation();
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    emit('update:modelValue', null);
    emit('change', null);
    internalError.value = '';
};
</script>

<template>
    <div class="w-full">
        <!-- Hidden Native File Input -->
        <input
            ref="fileInput"
            type="file"
            :accept="accept"
            :disabled="disabled"
            class="hidden"
            @change="onInputChange"
        />

        <!-- COMPACT MODE: Inline Button + File Pill -->
        <div v-if="compact" class="flex items-center gap-2.5">
            <button
                type="button"
                :disabled="disabled"
                class="btn-sm-secondary flex items-center gap-1.5"
                @click="openPicker"
            >
                <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span>{{ displayFileName ? 'Ganti Berkas' : label }}</span>
            </button>

            <div v-if="displayFileName" class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-orange-50/70 border border-orange-200 text-xs text-orange-950 font-medium min-w-0">
                <span class="truncate max-w-[180px]">{{ displayFileName }}</span>
                <span v-if="displayFileSize" class="text-[10px] text-slate-500 font-mono">({{ displayFileSize }})</span>
                <button
                    type="button"
                    class="text-slate-400 hover:text-rose-600 ml-1 p-0.5 rounded transition-colors"
                    title="Hapus berkas"
                    @click="clearFile"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <span v-else class="text-xs text-slate-400 italic">Belum ada berkas dipilih</span>
        </div>

        <!-- FULL DROPZONE MODE: Rich Frosted Glass Card -->
        <div
            v-else
            class="relative rounded-2xl border-2 border-dashed transition-all duration-150 cursor-pointer overflow-hidden p-4 sm:p-5 flex flex-col items-center justify-center text-center group"
            :class="[
                error || internalError
                    ? 'border-rose-400 bg-rose-50/40'
                    : isDragging
                    ? 'border-orange-500 bg-orange-50/80 shadow-md ring-4 ring-orange-500/10'
                    : displayFileName
                    ? 'border-orange-400/80 bg-gradient-to-br from-white/95 to-orange-50/40 shadow-sm'
                    : 'border-slate-300 hover:border-orange-500 bg-white/70 hover:bg-orange-50/30'
            ]"
            @click="openPicker"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
        >
            <!-- FILE SELECTED STATE -->
            <div v-if="displayFileName" class="w-full flex items-center justify-between gap-3 text-left">
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Thumbnail preview if image -->
                    <div
                        v-if="isImage && localPreview"
                        class="w-12 h-12 rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs shrink-0 flex items-center justify-center"
                    >
                        <img :src="localPreview" alt="Pratinjau" class="w-full h-full object-cover" />
                    </div>
                    <!-- Icon if document / PDF -->
                    <div
                        v-else
                        class="w-12 h-12 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center shrink-0 border border-orange-200"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>

                    <div class="min-w-0">
                        <p class="text-xs sm:text-sm font-bold text-slate-900 truncate">{{ displayFileName }}</p>
                        <div class="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5">
                            <span v-if="displayFileSize" class="font-mono tabular-data font-medium">{{ displayFileSize }}</span>
                            <span class="text-emerald-700 font-semibold flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Siap Diunggah</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action Button in Pill -->
                <div class="flex items-center gap-1.5 shrink-0" @click.stop>
                    <button
                        type="button"
                        class="btn-sm-secondary text-xs"
                        @click="openPicker"
                    >
                        Ganti
                    </button>
                    <button
                        type="button"
                        class="btn-sm-danger p-1.5"
                        title="Hapus berkas"
                        @click="clearFile"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- EMPTY UPLOAD PROMPT STATE -->
            <div v-else class="space-y-2 py-1">
                <div class="w-10 h-10 rounded-xl bg-orange-50 border border-orange-200 text-orange-600 flex items-center justify-center mx-auto shadow-xs group-hover:scale-105 group-hover:bg-orange-100 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                </div>

                <div>
                    <p class="text-xs sm:text-sm font-bold text-slate-800">
                        <span class="text-orange-600 underline underline-offset-2">Klik untuk memilih berkas</span>
                        <span class="text-slate-600 font-normal hidden sm:inline"> atau seret ke sini</span>
                    </p>
                    <p v-if="hint" class="text-[11px] text-slate-500 mt-0.5">
                        {{ hint }}
                    </p>
                    <p v-else class="text-[11px] text-slate-400 mt-0.5">
                        Maksimal {{ maxSizeMb }} MB (Format: JPG, PNG, atau PDF)
                    </p>
                </div>
            </div>
        </div>

        <!-- Error Message Display -->
        <p v-if="internalError || error" class="mt-1.5 text-xs text-rose-600 font-medium flex items-center gap-1">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ internalError || error }}</span>
        </p>
    </div>
</template>

