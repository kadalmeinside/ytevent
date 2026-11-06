<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    banner: {
        type: Object,
        required: true,
    },
    team: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

const isCurrentCtaPage = computed(() => {
    if (!props.banner?.cta_route) return false;
    try {
        const targetUrl = new URL(route(props.banner.cta_route), window.location.origin);
        return currentPath.value === targetUrl.pathname;
    } catch {
        return false;
    }
});

const variantStyles = computed(() => {
    switch (props.banner?.variant) {
        case 'orange':
            return {
                wrapper: 'bg-gradient-to-r from-orange-600 via-orange-600 to-red-600 text-white border-orange-500 shadow-lg shadow-orange-600/20',
                iconBox: 'bg-white/20 text-white border border-white/30',
                stepBadge: 'bg-white/20 text-white border border-white/30',
                title: 'text-white',
                message: 'text-orange-50',
                cta: 'bg-white text-orange-700 hover:bg-orange-50 font-bold shadow-sm border border-white',
            };
        case 'emerald':
            return {
                wrapper: 'bg-emerald-50/90 backdrop-blur-md border-emerald-300/90 text-emerald-950 shadow-sm',
                iconBox: 'bg-emerald-600 text-white shadow-xs',
                stepBadge: 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                title: 'text-emerald-950',
                message: 'text-emerald-800',
                cta: 'bg-emerald-600 text-white hover:bg-emerald-700 font-bold shadow-xs',
            };
        case 'red':
            return {
                wrapper: 'bg-red-50/95 backdrop-blur-md border-red-300 text-red-950 shadow-sm',
                iconBox: 'bg-red-600 text-white shadow-xs',
                stepBadge: 'bg-red-100 text-red-800 border border-red-200',
                title: 'text-red-950',
                message: 'text-red-800',
                cta: 'bg-red-600 text-white hover:bg-red-700 font-bold shadow-xs',
            };
        case 'blue':
            return {
                wrapper: 'bg-blue-50/90 backdrop-blur-md border-blue-200/90 text-blue-950 shadow-sm',
                iconBox: 'bg-blue-600 text-white shadow-xs',
                stepBadge: 'bg-blue-100 text-blue-800 border border-blue-200',
                title: 'text-blue-950',
                message: 'text-blue-800',
                cta: 'bg-blue-600 text-white hover:bg-blue-700 font-bold shadow-xs',
            };
        default: // amber
            return {
                wrapper: 'bg-amber-50/95 backdrop-blur-md border-amber-300/90 text-amber-950 shadow-sm',
                iconBox: 'bg-amber-600 text-white shadow-xs',
                stepBadge: 'bg-amber-100 text-amber-900 border border-amber-200',
                title: 'text-amber-950',
                message: 'text-amber-800',
                cta: 'bg-amber-600 text-white hover:bg-amber-700 font-bold shadow-xs',
            };
    }
});
</script>

<template>
    <div
        v-if="banner"
        class="mb-6 rounded-2xl border p-4 sm:p-5 transition-all"
        :class="variantStyles.wrapper"
    >
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-start gap-3.5 min-w-0">
                <!-- Semantic SVG Icon -->
                <div
                    class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                    :class="variantStyles.iconBox"
                >
                    <!-- Accredited / Passed Icon -->
                    <svg v-if="banner.type === 'accredited'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <!-- Ready to Lock Accreditation Alert Icon -->
                    <svg v-else-if="banner.type === 'ready_to_lock'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <!-- Warning / Revision Icon -->
                    <svg v-else-if="banner.type === 'revision_required' || banner.type === 'payment_required'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <!-- Screening / Roster Progress Icon -->
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>

                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-wider"
                            :class="variantStyles.stepBadge"
                        >
                            {{ banner.step_label }}
                        </span>
                        <span
                            v-if="team?.workflow"
                            class="text-[11px] font-semibold opacity-85"
                        >
                            • Progres Tahap {{ team.workflow.current_step }}/{{ team.workflow.total_steps }}
                        </span>
                    </div>
                    <h2 class="text-sm sm:text-base font-extrabold leading-snug" :class="variantStyles.title">
                        {{ banner.title }}
                    </h2>
                    <p class="text-xs sm:text-sm leading-relaxed" :class="variantStyles.message">
                        {{ banner.message }}
                    </p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5 shrink-0 lg:pl-4">
                <template v-if="banner.type === 'accredited' && team?.id">
                    <a
                        :href="route('manager.accreditation.roster-sheet', { team: team.id })"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-white text-emerald-800 border border-emerald-300 hover:bg-emerald-100 transition-colors shadow-2xs"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Unduh Roster PDF
                    </a>
                    <a
                        :href="route('manager.accreditation.id-cards', { team: team.id })"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-xs"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                        Unduh ID Card PDF
                    </a>
                </template>
                <Link
                    v-else-if="banner.cta_route && !isCurrentCtaPage"
                    :href="route(banner.cta_route)"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs transition-all"
                    :class="variantStyles.cta"
                >
                    <span>{{ banner.cta_label }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </Link>
            </div>
        </div>
    </div>
</template>

