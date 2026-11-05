<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const dismissed = ref(false);

const activeAlert = computed(() => {
    const flash = page.props.flash || {};
    if (flash.error) {
        return {
            type: 'error',
            message: flash.error,
            classes: 'bg-rose-50/95 border-rose-300 text-rose-900',
        };
    }
    if (flash.warning) {
        return {
            type: 'warning',
            message: flash.warning,
            classes: 'bg-amber-50/95 border-amber-300 text-amber-900',
        };
    }
    if (flash.success) {
        return {
            type: 'success',
            message: flash.success,
            classes: 'bg-emerald-50/95 border-emerald-300 text-emerald-900',
        };
    }
    if (flash.info) {
        return {
            type: 'info',
            message: flash.info,
            classes: 'bg-slate-100/95 border-slate-300 text-slate-800',
        };
    }
    return null;
});

watch(
    () => page.props.flash,
    () => {
        dismissed.value = false;
    },
    { deep: true }
);
</script>

<template>
    <div
        v-if="activeAlert && !dismissed"
        class="backdrop-blur-md border px-4 py-3 rounded-xl text-sm flex items-center justify-between gap-3 mb-5"
        :class="activeAlert.classes"
        role="status"
    >
        <span class="font-medium">{{ activeAlert.message }}</span>
        <button
            type="button"
            class="text-xs font-semibold opacity-75 hover:opacity-100 px-2 py-1 rounded"
            @click="dismissed = true"
        >
            Tutup
        </button>
    </div>
</template>

