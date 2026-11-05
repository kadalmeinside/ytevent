<script setup>
import { computed } from 'vue';

const props = defineProps({
    errors: {
        type: Object,
        default: () => ({}),
    },
});

const errorList = computed(() => {
    return Object.values(props.errors || {}).filter(Boolean);
});
</script>

<template>
    <div
        v-if="errorList.length > 0"
        class="bg-rose-50/90 backdrop-blur-md border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm mb-5"
        role="alert"
    >
        <div class="flex items-start gap-2.5">
            <svg
                class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z"
                    clip-rule="evenodd"
                />
            </svg>
            <div>
                <p class="font-semibold text-rose-900">
                    Terdapat {{ errorList.length }} kolom yang perlu diperbaiki sebelum melanjutkan:
                </p>
                <ul class="mt-1 list-disc list-inside space-y-0.5 text-xs text-rose-700">
                    <li v-for="(err, idx) in errorList" :key="idx">
                        {{ err }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

