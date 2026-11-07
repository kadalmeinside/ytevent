<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage, useForm, Link } from '@inertiajs/vue3';
import FlashAlert from '@/Components/FlashAlert.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const systemSettings = computed(() => page.props.systemSettings || {});
const isAdmin = computed(() =>
    user.value?.roles?.some((r) => ['superadmin', 'finance'].includes(r))
);

const isScrolled = ref(false);

const handleScroll = () => {
    isScrolled.value = window.scrollY > 15;
};

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const logoutForm = useForm({});
const logout = () => {
    logoutForm.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen flex flex-col bg-transparent">
        <header
            class="sticky top-0 z-40 transition-all duration-300"
            :class="[
                isScrolled
                    ? 'bg-white/95 backdrop-blur-xl border-b border-slate-200/90 shadow-sm py-0'
                    : 'bg-transparent border-b border-transparent shadow-none py-1 sm:py-2'
            ]"
        >
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 h-14 sm:h-16 flex items-center justify-between gap-2 sm:gap-4">
                <!-- Brand / Logo Area -->
                <Link :href="route('home')" class="flex items-center min-w-0 group shrink-0">
                    <!-- Tampilan 1: Hanya Logo Saja (Landscape / Memanjang Kesamping) -->
                    <template v-if="systemSettings?.header_logo_style === 'logo_only' && systemSettings?.app_logo_url">
                        <img
                            :src="systemSettings.app_logo_url"
                            :alt="systemSettings.app_name || 'Logo'"
                            class="h-8 sm:h-9 md:h-10 w-auto max-w-[190px] sm:max-w-[260px] md:max-w-[320px] object-contain shrink-0 group-hover:opacity-90 transition-opacity"
                        />
                    </template>

                    <!-- Tampilan 2: Logo Ikon + Teks Nama Situs (atau Fallback jika belum ada logo) -->
                    <template v-else>
                        <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
                            <img
                                v-if="systemSettings?.app_logo_url"
                                :src="systemSettings.app_logo_url"
                                :alt="systemSettings.app_name || 'Logo'"
                                class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl object-contain bg-white border border-slate-200/90 shadow-xs shrink-0"
                            />
                            <div
                                v-else
                                class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-orange-600 via-orange-600 to-red-600 text-white flex items-center justify-center font-black text-xs sm:text-sm tracking-tight shadow-sm shadow-orange-500/25 shrink-0 group-hover:scale-105 transition-transform"
                            >
                                {{ (systemSettings?.app_name || 'YT').substring(0, 2).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xs sm:text-base font-black text-slate-900 tracking-tight leading-none group-hover:text-orange-600 transition-colors truncate">
                                    {{ systemSettings?.app_name || 'YTEvent' }}
                                </span>
                                <span class="hidden md:block text-[11px] font-medium text-slate-500 leading-tight truncate mt-0.5">
                                    {{ systemSettings?.app_tagline || 'Multi-Event Competition Portal' }}
                                </span>
                            </div>
                        </div>
                    </template>
                </Link>

                <!-- Navigation & Actions Area -->
                <nav class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                    <Link
                        :href="route('home')"
                        class="hidden md:inline-flex text-xs sm:text-sm font-semibold text-slate-600 hover:text-orange-600 px-3 py-1.5 rounded-xl hover:bg-orange-50/60 transition-colors"
                    >
                        Katalog Kompetisi
                    </Link>

                    <template v-if="user">
                        <Link
                            :href="isAdmin ? route('admin.dashboard') : route('manager.dashboard')"
                            class="btn-pitch text-xs sm:text-sm h-8 sm:h-10 px-2.5 sm:px-4"
                        >
                            {{ isAdmin ? 'Dashboard' : 'Portal Tim' }}
                        </Link>
                        <button
                            type="button"
                            class="btn-secondary text-xs sm:text-sm h-8 sm:h-10 px-2 sm:px-3"
                            @click="logout"
                        >
                            Keluar
                        </button>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="btn-pitch h-8 sm:h-10 px-2.5 sm:px-4 text-xs sm:text-sm font-bold shadow-md shadow-orange-500/20"
                        >
                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-orange-100 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            <span class="hidden sm:inline">Login Manajer Tim</span>
                            <span class="sm:hidden">Login Manajer</span>
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <div
                v-if="$page.props.flash?.success || $page.props.flash?.error || $page.props.flash?.warning || $page.props.flash?.info"
                class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-2"
            >
                <FlashAlert />
            </div>
            <slot />
        </main>

        <footer class="frosted-header border-t border-slate-200/80 mt-16 py-6 text-center text-xs text-slate-500">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-2">
                <p>{{ systemSettings?.footer_copyright || 'YTEvent Multi-Event Football Tournament & League Management System' }}</p>
                <div class="flex items-center justify-center gap-4 text-slate-400">
                    <Link :href="route('home')" class="hover:text-slate-700">Katalog Kompetisi</Link>
                    <span>&bull;</span>
                    <Link :href="route('login')" class="hover:text-slate-700">Portal Manajer Tim</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
