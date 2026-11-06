<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Tuliskan deskripsi lengkap event, peraturan umum, atau informasi tambahan...',
    },
    minHeight: {
        type: String,
        default: '180px',
    },
});

const emit = defineEmits(['update:modelValue']);

const editorRef = ref(null);
const isCodeView = ref(false);
const rawHtml = ref('');

onMounted(() => {
    rawHtml.value = props.modelValue || '';
    if (editorRef.value) {
        editorRef.value.innerHTML = props.modelValue || '';
    }
});

watch(
    () => props.modelValue,
    (newVal) => {
        const currentVal = isCodeView.value ? rawHtml.value : editorRef.value?.innerHTML || '';
        if (newVal !== currentVal) {
            rawHtml.value = newVal || '';
            if (editorRef.value) {
                editorRef.value.innerHTML = newVal || '';
            }
        }
    }
);

const handleInput = () => {
    if (editorRef.value) {
        const html = editorRef.value.innerHTML;
        rawHtml.value = html;
        emit('update:modelValue', html);
    }
};

const handleRawInput = (e) => {
    rawHtml.value = e.target.value;
    emit('update:modelValue', e.target.value);
    if (editorRef.value) {
        editorRef.value.innerHTML = e.target.value;
    }
};

const exec = (command, value = null) => {
    if (isCodeView.value) return;
    document.execCommand(command, false, value);
    if (editorRef.value) {
        editorRef.value.focus();
        handleInput();
    }
};

const addLink = () => {
    if (isCodeView.value) return;
    const url = prompt('Masukkan URL tautan (contoh: https://persija.id):');
    if (url) {
        exec('createLink', url);
    }
};

const toggleCodeView = () => {
    if (isCodeView.value) {
        // Switching back from Code View to Visual Editor
        if (editorRef.value) {
            editorRef.value.innerHTML = rawHtml.value;
        }
        isCodeView.value = false;
    } else {
        // Switching from Visual Editor to Code View
        rawHtml.value = editorRef.value ? editorRef.value.innerHTML : (props.modelValue || '');
        isCodeView.value = true;
    }
};

const formatBlock = (tag) => {
    if (isCodeView.value) return;
    exec('formatBlock', `<${tag}>`);
};
</script>

<template>
    <div class="space-y-1.5">
        <div v-if="label" class="flex items-center justify-between">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">
                {{ label }}
            </label>
            <span class="text-[11px] text-slate-400">Editor Visual &amp; HTML</span>
        </div>

        <div
            class="rounded-xl border bg-white shadow-xs overflow-hidden transition-all duration-150"
            :class="[
                error ? 'border-red-400 focus-within:border-red-500 focus-within:ring-2 focus-within:ring-red-500/20' : 'border-slate-300 focus-within:border-orange-500 focus-within:ring-2 focus-within:ring-orange-500/20'
            ]"
        >
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-1 p-2 bg-slate-50/90 border-b border-slate-200 text-slate-700 text-xs">
                <!-- Headings / Formatting -->
                <div class="flex items-center space-x-1 border-r border-slate-200 pr-1.5 mr-1">
                    <button
                        type="button"
                        class="px-2 py-1 rounded hover:bg-slate-200 font-semibold text-xs transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="formatBlock('p')"
                        title="Teks Biasa / Paragraf"
                    >
                        P
                    </button>
                    <button
                        type="button"
                        class="px-2 py-1 rounded hover:bg-slate-200 font-bold text-xs transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="formatBlock('h2')"
                        title="Judul Bagian (H2)"
                    >
                        H2
                    </button>
                    <button
                        type="button"
                        class="px-2 py-1 rounded hover:bg-slate-200 font-bold text-xs transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="formatBlock('h3')"
                        title="Sub Judul (H3)"
                    >
                        H3
                    </button>
                </div>

                <!-- Text Style: Bold, Italic, Underline -->
                <div class="flex items-center space-x-1 border-r border-slate-200 pr-1.5 mr-1">
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 font-bold transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="exec('bold')"
                        title="Tebal (Ctrl+B)"
                    >
                        <span class="font-extrabold">B</span>
                    </button>
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 italic font-serif transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="exec('italic')"
                        title="Miring (Ctrl+I)"
                    >
                        <span class="italic font-bold">I</span>
                    </button>
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 underline transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="exec('underline')"
                        title="Garis Bawah (Ctrl+U)"
                    >
                        <span class="underline font-bold">U</span>
                    </button>
                </div>

                <!-- Lists -->
                <div class="flex items-center space-x-1 border-r border-slate-200 pr-1.5 mr-1">
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="exec('insertUnorderedList')"
                        title="Daftar Poin (Bullets)"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <line x1="3" y1="6" x2="3.01" y2="6"></line>
                            <line x1="3" y1="12" x2="3.01" y2="12"></line>
                            <line x1="3" y1="18" x2="3.01" y2="18"></line>
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="exec('insertOrderedList')"
                        title="Daftar Nomor (Numbered)"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="10" y1="6" x2="21" y2="6"></line>
                            <line x1="10" y1="12" x2="21" y2="12"></line>
                            <line x1="10" y1="18" x2="21" y2="18"></line>
                            <path d="M4 6h1v4"></path>
                            <path d="M4 10h2"></path>
                            <path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path>
                        </svg>
                    </button>
                </div>

                <!-- Alignment -->
                <div class="flex items-center space-x-1 border-r border-slate-200 pr-1.5 mr-1">
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="exec('justifyLeft')"
                        title="Rata Kiri"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="17" y1="10" x2="3" y2="10"></line>
                            <line x1="21" y1="6" x2="3" y2="6"></line>
                            <line x1="21" y1="14" x2="3" y2="14"></line>
                            <line x1="17" y1="18" x2="3" y2="18"></line>
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="exec('justifyCenter')"
                        title="Rata Tengah"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="10" x2="6" y2="10"></line>
                            <line x1="21" y1="6" x2="3" y2="6"></line>
                            <line x1="21" y1="14" x2="3" y2="14"></line>
                            <line x1="18" y1="18" x2="6" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <!-- Links & Tools -->
                <div class="flex items-center space-x-1 border-r border-slate-200 pr-1.5 mr-1">
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="addLink"
                        title="Sisipkan Tautan (Link)"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="w-7 h-7 flex items-center justify-center rounded hover:bg-slate-200 transition-colors"
                        :class="{'opacity-40 cursor-not-allowed': isCodeView}"
                        :disabled="isCodeView"
                        @click="exec('removeFormat')"
                        title="Hapus Pemformatan"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <!-- Source / HTML Code toggle -->
                <div class="ml-auto">
                    <button
                        type="button"
                        class="px-2.5 py-1 rounded text-xs font-mono font-medium transition-colors border"
                        :class="isCodeView ? 'bg-orange-600 text-white border-orange-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'"
                        @click="toggleCodeView"
                        :title="isCodeView ? 'Kembali ke Editor Visual' : 'Lihat / Edit Kode HTML'"
                    >
                        &lt;/&gt; {{ isCodeView ? 'Visual' : 'HTML' }}
                    </button>
                </div>
            </div>

            <!-- Visual Contenteditable Container -->
            <div
                v-show="!isCodeView"
                ref="editorRef"
                contenteditable="true"
                class="w-full p-3.5 text-sm text-slate-900 focus:outline-none overflow-y-auto prose prose-sm max-w-none leading-relaxed"
                :style="{ minHeight: minHeight }"
                :data-placeholder="placeholder"
                @input="handleInput"
            ></div>

            <!-- Raw HTML Textarea -->
            <textarea
                v-show="isCodeView"
                :value="rawHtml"
                rows="8"
                class="w-full p-3.5 text-xs font-mono bg-slate-900 text-slate-100 focus:outline-none resize-y border-0"
                :style="{ minHeight: minHeight }"
                placeholder="<p>Kode HTML...</p>"
                @input="handleRawInput"
            ></textarea>
        </div>

        <p v-if="error" class="text-xs text-red-600 font-medium">
            {{ error }}
        </p>
    </div>
</template>

<style scoped>
[contenteditable]:empty:before {
    content: attr(data-placeholder);
    color: #94a3b8;
    cursor: text;
    pointer-events: none;
}
</style>
