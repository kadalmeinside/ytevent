<script setup>
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    tnc: {
        type: Object,
        default: null,
    },
    alreadyAccepted: {
        type: Boolean,
        default: false,
    },
    accent: {
        type: String,
        default: 'orange', // 'orange' | 'amber'
    },
});

const emit = defineEmits(['close', 'accept']);

const scrollContainer = ref(null);
const hasScrolledBottom = ref(false);

const updateScrollState = () => {
    const el = scrollContainer.value;
    if (!el) return;
    const isShort = el.scrollHeight <= el.clientHeight + 16;
    const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 24;
    if (isShort || atBottom) {
        hasScrolledBottom.value = true;
    }
};

watch(
    () => props.show,
    async (visible) => {
        if (visible) {
            hasScrolledBottom.value = props.alreadyAccepted;
            await nextTick();
            updateScrollState();
        }
    }
);

const handleAccept = () => {
    emit('accept', props.tnc);
    emit('close');
};

const handleClose = () => {
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show && tnc"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/60 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="`tnc-modal-title-${tnc.id}`"
            @click.self="handleClose"
        >
            <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[88vh]">
                <!-- Header -->
                <div class="px-5 py-4 sm:px-6 border-b border-slate-200 bg-slate-50/80 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider"
                                :class="accent === 'amber'
                                    ? 'bg-amber-100 text-amber-800 border border-amber-200'
                                    : 'bg-orange-100 text-orange-800 border border-orange-200'"
                            >
                                Versi {{ tnc.version }}
                            </span>
                            <span class="text-[11px] font-mono text-slate-400">
                                {{ tnc.code }}
                            </span>
                        </div>
                        <h3
                            :id="`tnc-modal-title-${tnc.id}`"
                            class="text-base sm:text-lg font-bold text-slate-900 leading-snug"
                        >
                            {{ tnc.title }}
                        </h3>
                    </div>

                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/70 transition-colors shrink-0"
                        aria-label="Tutup modal syarat dan ketentuan"
                        @click="handleClose"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Scrollable Document Body -->
                <div
                    ref="scrollContainer"
                    class="px-5 py-5 sm:px-6 overflow-y-auto flex-1 text-sm text-slate-700 leading-relaxed"
                    @scroll="updateScrollState"
                >
                    <div class="rich-text-content" v-html="tnc.content_html" />
                </div>

                <!-- Footer -->
                <div class="px-5 py-4 sm:px-6 border-t border-slate-200 bg-slate-50/90 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="text-[11px] text-slate-500 leading-snug">
                        <template v-if="!hasScrolledBottom">
                            Gulir isi dokumen hingga selesai untuk meninjau seluruh ketentuan.
                        </template>
                        <template v-else>
                            Persetujuan digital akan mencatat versi <strong>v{{ tnc.version }}</strong> beserta waktu konfirmasi Anda.
                        </template>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 shrink-0">
                        <button
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-100 transition-colors"
                            @click="handleClose"
                        >
                            {{ alreadyAccepted ? 'Tutup' : 'Batal' }}
                        </button>
                        <button
                            v-if="!alreadyAccepted"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-white shadow-sm transition-all flex items-center gap-1.5"
                            :class="accent === 'amber'
                                ? 'bg-amber-600 hover:bg-amber-700 focus:ring-2 focus:ring-amber-500/40'
                                : 'bg-orange-600 hover:bg-orange-700 focus:ring-2 focus:ring-orange-500/40'"
                            @click="handleAccept"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Saya Setuju</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
