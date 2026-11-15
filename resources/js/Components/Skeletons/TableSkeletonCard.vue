<script setup>
defineProps({
    rows: {
        type: Number,
        default: 6,
    },
    columns: {
        type: Number,
        default: 5,
    },
    mode: {
        type: String,
        default: 'table', // 'table' | 'cards'
    },
});
</script>

<template>
    <div role="status" aria-live="polite" aria-busy="true">
        <span class="sr-only">Memuat data...</span>

        <!-- Card-list skeleton (used by Admin/Teams & mobile card queues) -->
        <div v-if="mode === 'cards'" class="space-y-3" aria-hidden="true">
            <div
                v-for="idx in rows"
                :key="idx"
                class="frosted-card p-4 sm:p-5 bg-white space-y-3.5"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="skeleton-block w-11 h-11 rounded-xl shrink-0" />
                        <div class="space-y-1.5">
                            <div class="skeleton-block h-4 w-44 sm:w-56" />
                            <div class="skeleton-block h-3 w-32" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="skeleton-pill h-6 w-24" />
                        <div class="skeleton-pill h-6 w-20" />
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div v-for="col in 4" :key="col" class="space-y-1.5">
                        <div class="skeleton-block h-2.5 w-16" />
                        <div class="skeleton-block h-3.5 w-24" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabular skeleton -->
        <div v-else class="frosted-card bg-white overflow-hidden" aria-hidden="true">
            <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-3">
                <div class="skeleton-block h-4 w-40" />
                <div class="skeleton-block h-3.5 w-24" />
            </div>

            <div class="divide-y divide-slate-100">
                <div
                    v-for="r in rows"
                    :key="r"
                    class="px-4 py-3.5 grid gap-4 items-center"
                    :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }"
                >
                    <div v-for="c in columns" :key="c" class="space-y-1.5">
                        <div
                            class="skeleton-block h-3.5"
                            :class="c === 1 ? 'w-4/5' : (c === columns ? 'w-16 ml-auto rounded-lg h-7' : 'w-3/4')"
                        />
                        <div v-if="c === 1" class="skeleton-block h-2.5 w-1/2" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
