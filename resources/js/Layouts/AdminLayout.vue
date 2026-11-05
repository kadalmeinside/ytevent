<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm } from '@inertiajs/vue3';
import FlashAlert from '@/Components/FlashAlert.vue';

defineProps({
    pageTitle: {
        type: String,
        default: 'Dashboard Internal Panitia',
    },
    subtitle: {
        type: String,
        default: '',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const mobileMenuOpen = ref(false);
const currentPath = computed(() => page.url.split('?')[0]);

const can = (permission) => {
    if (!user.value) return false;
    if (user.value.roles?.includes('superadmin')) return true;
    return user.value.permissions?.includes(permission) || false;
};

const navGroups = computed(() => {
    const items = [
        {
            label: 'Dashboard Operasional',
            href: route('admin.dashboard'),
            match: '/admin/dashboard',
            show: can('dashboard.admin.view'),
        },
        {
            label: 'Manajemen Kompetisi',
            href: route('admin.events.index'),
            match: '/admin/events',
            show: can('events.view'),
        },
        {
            label: 'Daftar Tim Masuk',
            href: route('admin.teams.index'),
            match: '/admin/teams',
            show: can('teams.view_all'),
        },
        {
            label: 'Verifikasi Transfer Manual',
            href: route('admin.payments.manual-verification'),
            match: '/admin/payments/manual-verification',
            show: can('payments.verify_manual'),
        },
        {
            label: 'Riwayat Transaksi',
            href: route('admin.payments.transactions'),
            match: '/admin/payments/transactions',
            show: can('payments.view_all'),
        },
        {
            label: 'Manajemen Pengguna',
            href: route('admin.users.index'),
            match: '/admin/users',
            show: user.value?.roles?.includes('superadmin'),
        },
        {
            label: 'Hak Akses & Role',
            href: route('admin.roles.index'),
            match: '/admin/roles',
            show: user.value?.roles?.includes('superadmin'),
        },
        {
            label: 'Pengaturan Global',
            href: route('admin.settings.index'),
            match: '/admin/settings',
            show: user.value?.roles?.includes('superadmin'),
        },
    ];

    return items.filter((i) => i.show);
});

const roleBadge = computed(() => {
    if (user.value?.roles?.includes('superadmin')) return 'SUPERADMIN';
    if (user.value?.roles?.includes('finance')) return 'FINANCE';
    return 'STAFF';
});

const logoutForm = useForm({});
const logout = () => {
    logoutForm.post(route('admin.logout'));
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col lg:flex-row">
        <header class="lg:hidden sticky top-0 z-40 frosted-header px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    class="p-1.5 rounded-lg border border-slate-200 text-slate-700 bg-white"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <span class="font-bold text-sm text-slate-900">Portal Panitia YTEvent</span>
            </div>
            <span class="text-[11px] font-semibold uppercase px-2 py-0.5 rounded-md bg-orange-50 text-orange-800 border border-orange-200">
                {{ roleBadge }}
            </span>
        </header>

        <aside
            class="w-64 bg-white/80 backdrop-blur-xl border-r border-slate-200/80 shrink-0 flex-col justify-between lg:flex lg:sticky lg:top-0 lg:h-screen z-50"
            :class="mobileMenuOpen ? 'fixed inset-y-0 left-0 flex shadow-2xl' : 'hidden'"
        >
            <div class="p-4 space-y-5 overflow-y-auto">
                <div class="flex items-center justify-between">
                    <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5 group">
                        <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-900 to-slate-800 text-white flex items-center justify-center font-bold text-xs shadow-sm group-hover:scale-105 transition-transform">
                            ADM
                        </div>
                        <div>
                            <span class="block text-sm font-bold text-slate-900 leading-tight">Control Center</span>
                            <span class="block text-[11px] font-medium text-slate-500">Operasional &amp; Finance</span>
                        </div>
                    </Link>
                    <button
                        type="button"
                        class="lg:hidden text-xs text-slate-500 hover:text-slate-800 p-1 rounded-lg border border-slate-200 bg-white"
                        @click="mobileMenuOpen = false"
                    >
                        Tutup
                    </button>
                </div>

                <nav class="space-y-1">
                    <Link
                        v-for="item in navGroups"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center justify-between px-3 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition-all"
                        :class="
                            currentPath.startsWith(item.match)
                                ? 'bg-gradient-to-r from-orange-500/15 via-orange-500/5 to-transparent text-orange-950 font-bold border-l-4 border-orange-600 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 border-l-4 border-transparent'
                        "
                        @click="mobileMenuOpen = false"
                    >
                        <span>{{ item.label }}</span>
                    </Link>
                </nav>
            </div>

            <div class="p-4 border-t border-slate-200/80 space-y-3 bg-slate-50/60 backdrop-blur-sm">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-slate-900 truncate">{{ user?.name }}</p>
                        <p class="text-[11px] text-slate-500 truncate">{{ user?.email }}</p>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-orange-50 text-orange-800 border border-orange-200 shrink-0">
                        {{ roleBadge }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('home')" class="btn-secondary flex-1 text-xs py-1.5 px-2.5">
                        Portal Publik
                    </Link>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </aside>

        <div class="flex-1 min-w-0 flex flex-col">
            <header class="hidden lg:flex h-16 frosted-header px-8 items-center justify-between sticky top-0 z-30">
                <div>
                    <h1 class="text-base font-bold text-slate-900">{{ pageTitle }}</h1>
                    <p v-if="subtitle" class="text-xs text-slate-500">{{ subtitle }}</p>
                </div>
                <slot name="headerActions" />
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                <FlashAlert />
                <slot />
            </main>
        </div>
    </div>
</template>

