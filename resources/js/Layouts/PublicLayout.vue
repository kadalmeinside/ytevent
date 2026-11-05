<script setup>
import { computed } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import FlashAlert from '@/Components/FlashAlert.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() =>
    user.value?.roles?.some((r) => ['superadmin', 'finance'].includes(r))
);

const logoutForm = useForm({});
const logout = () => {
    logoutForm.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-50">
        <header class="sticky top-0 z-40 frosted-header">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                <Link :href="route('home')" class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-lg bg-emerald-700 text-white flex items-center justify-center font-bold text-sm tracking-tight shadow-sm">
                        YT
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-900 leading-tight">
                            YTEvent Football
                        </span>
                        <span class="block text-[11px] font-medium text-slate-500 leading-tight">
                            Multi-Event Competition Portal
                        </span>
                    </div>
                </Link>

                <nav class="flex items-center gap-2.5">
                    <Link
                        :href="route('home')"
                        class="text-sm font-medium text-slate-600 hover:text-slate-900 px-3 py-1.5 rounded-lg hover:bg-slate-100/80 transition-colors"
                    >
                        Katalog Kompetisi
                    </Link>

                    <template v-if="user">
                        <Link
                            :href="isAdmin ? route('admin.dashboard') : route('manager.dashboard')"
                            class="btn-pitch text-xs sm:text-sm py-1.5 px-3.5"
                        >
                            {{ isAdmin ? 'Dashboard Panitia' : 'Portal Manajer Tim' }}
                        </Link>
                        <button
                            type="button"
                            class="btn-secondary text-xs sm:text-sm py-1.5 px-3"
                            @click="logout"
                        >
                            Keluar
                        </button>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="btn-secondary text-xs sm:text-sm py-1.5 px-3.5">
                            Login Manajer Tim
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
                <FlashAlert />
            </div>
            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-white/80 mt-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                <p>YTEvent Multi-Event Football Tournament &amp; League Management System</p>
                <div class="flex items-center gap-4">
                    <Link :href="route('home')" class="hover:text-slate-800">Daftar Event</Link>
                    <Link :href="route('admin.login')" class="hover:text-slate-800">Portal Panitia</Link>
                </div>
            </div>
        </footer>
    </div>
</template>

