<script setup>
defineProps({
    count: {
        type: Number,
        default: 6,
    },
});
</script>

<template>
    <div
        role="status"
        aria-live="polite"
        aria-busy="true"
        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"
    >
        <span class="sr-only">Memuat daftar kompetisi dan event...</span>

        <article
            v-for="idx in count"
            :key="idx"
            aria-hidden="true"
            class="frosted-card overflow-hidden flex flex-col justify-between bg-white"
        >
            <div class="skeleton-block h-40 w-full rounded-none" />

            <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <div class="skeleton-block h-5 w-14 rounded" />
                            <div class="skeleton-block h-5 w-24 rounded" />
                        </div>
                        <div class="skeleton-pill h-5 w-20" />
                    </div>

                    <div class="space-y-2">
                        <div class="skeleton-block h-5 w-11/12" />
                        <div class="skeleton-block h-5 w-3/4" />
                    </div>

                    <div class="space-y-1.5 pt-1">
                        <div class="skeleton-block h-3 w-full" />
                        <div class="skeleton-block h-3 w-4/5" />
                    </div>
                </div>

                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="skeleton-block h-3 w-24" />
                            <div class="skeleton-block h-3 w-16" />
                        </div>
                        <div class="skeleton-pill h-2 w-full" />
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <div class="skeleton-block h-9 sm:h-10 flex-1 rounded-xl" />
                        <div class="skeleton-block h-9 sm:h-10 flex-1 rounded-xl" />
                    </div>
                </div>
            </div>
        </article>
    </div>
</template>
