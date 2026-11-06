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
const systemSettings = computed(() => page.props.systemSettings || {});
const mobileMenuOpen = ref(false);
const currentPath = computed(() => page.url.split('?')[0]);

const can = (permission) => {
    if (!user.value) return false;
    if (user.value.roles?.includes('superadmin')) return true;
    return user.value.permissions?.includes(permission) || false;
};

const navSections = computed(() => {
    const sections = [
        {
            title: 'Operasional',
            items: [
                {
                    label: 'Dashboard',
                    href: route('admin.dashboard'),
                    match: '/admin/dashboard',
                    icon: 'dashboard',
                    show: can('dashboard.admin.view'),
                },
                {
                    label: 'Kompetisi',
                    href: route('admin.events.index'),
                    match: '/admin/events',
                    icon: 'trophy',
                    show: can('events.view'),
                },
                {
                    label: 'Daftar Tim',
                    href: route('admin.teams.index'),
                    match: '/admin/teams',
                    icon: 'teams',
                    show: can('teams.view_all'),
                },
            ],
        },
        {
            title: 'Keuangan & Mutasi',
            items: [
                {
                    label: 'Verifikasi Transfer',
                    href: route('admin.payments.manual-verification'),
                    match: '/admin/payments/manual-verification',
                    icon: 'verify',
                    show: can('payments.verify_manual'),
                },
                {
                    label: 'Riwayat Transaksi',
                    href: route('admin.payments.transactions'),
                    match: '/admin/payments/transactions',
                    icon: 'transactions',
                    show: can('payments.view_all'),
                },
            ],
        },
        {
            title: 'Sistem & Akses',
            items: [
                {
                    label: 'Manajemen Pengguna',
                    href: route('admin.users.index'),
                    match: '/admin/users',
                    icon: 'users',
                    show: user.value?.roles?.includes('superadmin'),
                },
                {
                    label: 'Hak Akses & Role',
                    href: route('admin.roles.index'),
                    match: '/admin/roles',
                    icon: 'roles',
                    show: user.value?.roles?.includes('superadmin'),
                },
                {
                    label: 'Pengaturan Global',
                    href: route('admin.settings.index'),
                    match: '/admin/settings',
                    icon: 'settings',
                    show: user.value?.roles?.includes('superadmin'),
                },
            ],
        },
    ];

    return sections
        .map((sec) => ({
            ...sec,
            items: sec.items.filter((i) => i.show),
        }))
        .filter((sec) => sec.items.length > 0);
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
                <template v-if="systemSettings.header_logo_style === 'logo_only' && systemSettings.app_logo_url">
                    <img
                        :src="systemSettings.app_logo_url"
                        :alt="systemSettings.app_name || 'Logo'"
                        class="h-7 w-auto max-w-[140px] object-contain"
                    />
                </template>
                <template v-else>
                    <span class="font-bold text-sm text-slate-900 truncate max-w-[180px]">
                        {{ systemSettings.app_name || 'Portal Panitia' }}
                    </span>
                </template>
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
                    <Link :href="route('admin.dashboard')" class="flex items-center group min-w-0">
                        <template v-if="systemSettings.header_logo_style === 'logo_only' && systemSettings.app_logo_url">
                            <img
                                :src="systemSettings.app_logo_url"
                                :alt="systemSettings.app_name || 'Logo'"
                                class="h-8 w-auto max-w-[170px] object-contain group-hover:opacity-90 transition-opacity"
                            />
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-2.5">
                                <img
                                    v-if="systemSettings.app_logo_url"
                                    :src="systemSettings.app_logo_url"
                                    :alt="systemSettings.app_name || 'Logo'"
                                    class="w-8 h-8 rounded-xl object-contain bg-white border border-slate-200 shadow-xs shrink-0"
                                />
                                <div v-else class="w-8 h-8 rounded-xl bg-gradient-to-br from-slate-900 to-slate-800 text-white flex items-center justify-center font-bold text-xs shadow-sm group-hover:scale-105 transition-transform shrink-0">
                                    ADM
                                </div>
                                <div class="min-w-0">
                                    <span class="block text-sm font-bold text-slate-900 leading-tight truncate">{{ systemSettings.app_name || 'Control Center' }}</span>
                                    <span class="block text-[11px] font-medium text-slate-500 truncate">Operasional &amp; Finance</span>
                                </div>
                            </div>
                        </template>
                    </Link>
                    <button
                        type="button"
                        class="lg:hidden text-xs text-slate-500 hover:text-slate-800 p-1 rounded-lg border border-slate-200 bg-white"
                        @click="mobileMenuOpen = false"
                    >
                        Tutup
                    </button>
                </div>

                <nav class="space-y-4 pt-1">
                    <div v-for="section in navSections" :key="section.title" class="space-y-1">
                        <p class="px-3 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                            {{ section.title }}
                        </p>
                        <div class="space-y-0.5">
                            <Link
                                v-for="item in section.items"
                                :key="item.href"
                                :href="item.href"
                                class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all duration-150"
                                :class="
                                    currentPath.startsWith(item.match)
                                        ? 'bg-orange-500/10 text-orange-950 font-bold border-l-[3px] border-orange-600 shadow-2xs'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 border-l-[3px] border-transparent'
                                "
                                @click="mobileMenuOpen = false"
                            >
                                <!-- Icons -->
                                <span class="w-4 h-4 shrink-0" :class="currentPath.startsWith(item.match) ? 'text-orange-600' : 'text-slate-400'">
                                    <!-- Dashboard Icon -->
                                    <svg v-if="item.icon === 'dashboard'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <rect width="7" height="9" x="3" y="3" rx="1" stroke-width="2"/>
                                        <rect width="7" height="5" x="14" y="3" rx="1" stroke-width="2"/>
                                        <rect width="7" height="9" x="14" y="12" rx="1" stroke-width="2"/>
                                        <rect width="7" height="5" x="3" y="16" rx="1" stroke-width="2"/>
                                    </svg>
                                    <!-- Trophy Icon -->
                                    <svg v-else-if="item.icon === 'trophy'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6M18 9h1.5a2.5 2.5 0 0 0 0-5H18M4 22h16M10 14.66V17c0 .55-.45 1-1 1H8c-.55 0-1 .45-1 1v1h10v-1c0-.55-.45-1-1-1h-1c-.55 0-1-.45-1-1v-2.34c3.27-.47 5.76-3.23 5.76-6.66V4H5.24v4c0 3.43 2.49 6.19 5.76 6.66z" />
                                    </svg>
                                    <!-- Teams Icon -->
                                    <svg v-else-if="item.icon === 'teams'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                    <!-- Verify Icon -->
                                    <svg v-else-if="item.icon === 'verify'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <!-- Transactions Icon -->
                                    <svg v-else-if="item.icon === 'transactions'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <rect width="20" height="14" x="2" y="5" rx="2" stroke-width="2"/>
                                        <line x1="2" x2="22" y1="10" y2="10" stroke-width="2"/>
                                    </svg>
                                    <!-- Users Icon -->
                                    <svg v-else-if="item.icon === 'users'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm10 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                                    </svg>
                                    <!-- Roles Icon -->
                                    <svg v-else-if="item.icon === 'roles'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 2l-2 2m-1.5 6.5l3-3m-3.5 3.5L12 16l-4 4-4-4 4-4 5-5m0 0a4 4 0 1 1 5.66 5.66L17 9.5z" />
                                    </svg>
                                    <!-- Settings Icon -->
                                    <svg v-else fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <circle cx="12" cy="12" r="3" stroke-width="2"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                                    </svg>
                                </span>
                                <span class="truncate">{{ item.label }}</span>
                            </Link>
                        </div>
                    </div>
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
                    <Link :href="route('home')" class="btn-sm-secondary flex-1">
                        Portal Publik
                    </Link>
                    <button
                        type="button"
                        class="btn-sm-danger"
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

