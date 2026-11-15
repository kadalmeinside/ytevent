<script setup>
import CatalogGridSkeleton from './CatalogGridSkeleton.vue';
import TableSkeletonCard from './TableSkeletonCard.vue';

defineProps({
    variant: {
        type: String,
        default: 'page', // 'dashboard' | 'catalog' | 'table' | 'detail' | 'page'
    },
});
</script>

<template>
    <div role="status" aria-live="polite" aria-busy="true" class="space-y-6">
        <span class="sr-only">Memuat konten halaman...</span>

        <!-- Catalog Variant -->
        <template v-if="variant === 'catalog'">
            <div class="frosted-card p-5 bg-white space-y-3" aria-hidden="true">
                <div class="skeleton-block h-6 w-52" />
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div v-for="i in 5" :key="i" class="skeleton-block h-10 w-full rounded-xl" />
                </div>
            </div>
            <CatalogGridSkeleton :count="6" />
        </template>

        <!-- Dashboard Variant -->
        <template v-else-if="variant === 'dashboard'">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" aria-hidden="true">
                <div
                    v-for="i in 4"
                    :key="i"
                    class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 via-white to-slate-100/70 p-5 space-y-3"
                >
                    <div class="skeleton-block h-3 w-28" />
                    <div class="skeleton-block h-8 w-36" />
                    <div class="skeleton-block h-3 w-40" />
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6" aria-hidden="true">
                <div v-for="card in 2" :key="card" class="frosted-card p-5 bg-white space-y-4">
                    <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-100">
                        <div class="skeleton-block h-4 w-44" />
                        <div class="skeleton-pill h-6 w-20" />
                    </div>
                    <div v-for="row in 4" :key="row" class="flex items-center justify-between gap-3 py-1.5">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="skeleton-block w-9 h-9 rounded-xl shrink-0" />
                            <div class="space-y-1.5 flex-1">
                                <div class="skeleton-block h-3.5 w-3/5" />
                                <div class="skeleton-block h-2.5 w-2/5" />
                            </div>
                        </div>
                        <div class="skeleton-pill h-6 w-20 shrink-0" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4" aria-hidden="true">
                <div v-for="k in 3" :key="k" class="frosted-card p-4 bg-white space-y-3">
                    <div class="flex items-center gap-3">
                        <div class="skeleton-block w-10 h-10 rounded-xl shrink-0" />
                        <div class="space-y-1.5 flex-1">
                            <div class="skeleton-block h-4 w-3/4" />
                            <div class="skeleton-block h-3 w-1/2" />
                        </div>
                    </div>
                    <div class="skeleton-pill h-2 w-full" />
                </div>
            </div>
        </template>

        <!-- Table / List Variant -->
        <template v-else-if="variant === 'table'">
            <div class="frosted-card p-4 bg-white space-y-3" aria-hidden="true">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div v-for="f in 4" :key="f" class="skeleton-block h-9 w-full rounded-xl" />
                </div>
            </div>
            <TableSkeletonCard :rows="6" :columns="5" />
        </template>

        <!-- Detail Page Variant -->
        <template v-else-if="variant === 'detail'">
            <div class="frosted-card overflow-hidden bg-white" aria-hidden="true">
                <div class="skeleton-block h-48 sm:h-56 w-full rounded-none" />
                <div class="p-6 space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="skeleton-pill h-6 w-20" />
                        <div class="skeleton-pill h-6 w-28" />
                        <div class="skeleton-pill h-6 w-24" />
                    </div>
                    <div class="skeleton-block h-7 w-3/4 sm:w-1/2" />
                    <div class="space-y-2">
                        <div class="skeleton-block h-3.5 w-full" />
                        <div class="skeleton-block h-3.5 w-5/6" />
                        <div class="skeleton-block h-3.5 w-2/3" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5" aria-hidden="true">
                <div class="lg:col-span-2 frosted-card p-5 bg-white space-y-3">
                    <div class="skeleton-block h-5 w-48" />
                    <div class="skeleton-block h-3.5 w-full" />
                    <div class="skeleton-block h-3.5 w-11/12" />
                    <div class="skeleton-block h-3.5 w-4/5" />
                </div>
                <div class="frosted-card p-5 bg-white space-y-3">
                    <div class="skeleton-block h-5 w-36" />
                    <div class="skeleton-block h-10 w-full rounded-xl" />
                    <div class="skeleton-block h-10 w-full rounded-xl" />
                </div>
            </div>
        </template>

        <!-- Generic Page Variant -->
        <template v-else>
            <div class="frosted-card p-5 bg-white space-y-3" aria-hidden="true">
                <div class="skeleton-block h-6 w-56" />
                <div class="skeleton-block h-3.5 w-80 max-w-full" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5" aria-hidden="true">
                <div v-for="c in 2" :key="c" class="frosted-card p-5 bg-white space-y-3">
                    <div class="skeleton-block h-5 w-44" />
                    <div class="skeleton-block h-3.5 w-full" />
                    <div class="skeleton-block h-3.5 w-5/6" />
                    <div class="skeleton-block h-9 w-36 rounded-xl pt-2" />
                </div>
            </div>
        </template>
    </div>
</template>
