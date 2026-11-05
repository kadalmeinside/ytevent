<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Konfirmasi Tindakan',
    },
    description: {
        type: String,
        default: '',
    },
    confirmText: {
        type: String,
        default: 'Ya, Lanjutkan',
    },
    cancelText: {
        type: String,
        default: 'Batal',
    },
    danger: {
        type: Boolean,
        default: false,
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['confirm', 'close']);
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/35 backdrop-blur-sm"
    >
        <div class="bg-white/95 backdrop-blur-xl border border-slate-200 rounded-xl shadow-lg max-w-md w-full p-6">
            <h3 class="text-base font-bold text-slate-900">{{ title }}</h3>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ description }}</p>

            <slot />

            <div class="mt-6 flex items-center justify-end gap-2.5">
                <button
                    type="button"
                    class="btn-secondary"
                    :disabled="processing"
                    @click="$emit('close')"
                >
                    {{ cancelText }}
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex items-center justify-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg text-white transition-colors disabled:opacity-60"
                    :class="danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-700 hover:bg-emerald-800'"
                    @click="$emit('confirm')"
                >
                    {{ processing ? 'Memproses...' : confirmText }}
                </button>
            </div>
        </div>
    </div>
</template>

