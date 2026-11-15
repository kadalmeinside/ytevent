<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage, useForm, router, Head } from '@inertiajs/vue3';
import FlashAlert from '@/Components/FlashAlert.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import WorkflowStepBanner from '@/Components/WorkflowStepBanner.vue';
import SimulationFloatingTool from '@/Components/SimulationFloatingTool.vue';
import PageSkeleton from '@/Components/Skeletons/PageSkeleton.vue';
import { useNavigationLoading } from '@/Composables/useNavigationLoading';

const props = defineProps({
    team: {
        type: Object,
        default: null,
    },
    teamsList: {
        type: Array,
        default: () => [],
    },
    pageTitle: {
        type: String,
        default: 'Portal Akun Peserta & Manajer',
    },
    hideLayoutH1: {
        type: Boolean,
        default: false,
    },
    hideWorkflowBanner: {
        type: Boolean,
        default: false,
    },
    hideTeamContext: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const systemSettings = computed(() => page.props.systemSettings || {});
const mobileMenuOpen = ref(false);
const notifOpen = ref(false);
const { isNavigating, skeletonVariant } = useNavigationLoading();
const readNotifIds = ref([]);

const readStorageKey = computed(() => `ytevent_read_notifs_${user.value?.id || 'guest'}`);

const persistReadIds = (ids) => {
    const unique = Array.from(new Set(ids)).slice(-100);
    readNotifIds.value = unique;
    try {
        window.localStorage.setItem(readStorageKey.value, JSON.stringify(unique));
    } catch {
        // Ignore storage quota errors cleanly
    }
};

const handleEscapeKey = (event) => {
    if (event.key === 'Escape') {
        if (notifOpen.value) notifOpen.value = false;
        if (mobileMenuOpen.value) mobileMenuOpen.value = false;
    }
};

onMounted(() => {
    try {
        const stored = window.localStorage.getItem(readStorageKey.value);
        if (stored) {
            const parsed = JSON.parse(stored);
            if (Array.isArray(parsed)) {
                readNotifIds.value = parsed;
            }
        }
    } catch {
        readNotifIds.value = [];
    }
    window.addEventListener('keydown', handleEscapeKey);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleEscapeKey);
});

const notificationItems = computed(() => page.props.accountNotifications?.items || []);
const isNotificationRead = (id) => readNotifIds.value.includes(id);
const unreadCount = computed(
    () => notificationItems.value.filter((item) => !isNotificationRead(item.id)).length
);

const toggleNotifications = () => {
    mobileMenuOpen.value = false;
    notifOpen.value = !notifOpen.value;
};

const markAllAsRead = () => {
    persistReadIds([...readNotifIds.value, ...notificationItems.value.map((item) => item.id)]);
};

const openNotificationItem = (item) => {
    if (item?.id) {
        persistReadIds([...readNotifIds.value, item.id]);
    }
    notifOpen.value = false;
    if (item?.href) {
        router.visit(item.href);
    }
};

const notifVariantClass = (variant) => {
    switch (variant) {
        case 'danger':
            return 'bg-rose-100 text-rose-900 border-rose-200';
        case 'warning':
            return 'bg-amber-100 text-amber-900 border-amber-200';
        case 'success':
            return 'bg-emerald-100 text-emerald-900 border-emerald-200';
        default:
            return 'bg-blue-100 text-blue-900 border-blue-200';
    }
};

const currentPath = computed(() => page.url.split('?')[0]);

const isUnpaidLocked = computed(() => {
    if (!props.team) return false;
    return !props.team.can_manage_roster && props.team.status !== 'locked_roster';
});

const workflowBadges = computed(() => props.team?.workflow?.badges || {});

const badgeColorClass = (badge) => {
    if (!badge) return '';
    switch (badge.variant) {
        case 'orange':
            return 'bg-gradient-to-r from-orange-600 to-red-600 text-white border-orange-600 shadow-sm';
        case 'red':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'amber':
            return 'bg-amber-100 text-amber-900 border-amber-200';
        case 'blue':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'emerald':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
};

const unpaidAccountCount = computed(() => {
    const sharedCount = page.props.accountNotifications?.unpaid_total_count;
    if (typeof sharedCount === 'number') {
        return sharedCount;
    }
    const unpaidTeams = (props.teamsList || []).filter(
        (t) => !t.is_paid && !t.is_event_completed
    ).length;
    const participants = page.props.auditionParticipants || page.props.participantsList || [];
    const unpaidParticipants = participants.filter(
        (p) => !p.is_paid && !p.event?.is_completed && !p.is_event_completed
    ).length;
    return unpaidTeams + unpaidParticipants;
});

const accountNavItems = computed(() => [
    {
        label: 'Kepesertaan Saya',
        href: route('manager.dashboard'),
        match: '/manager/dashboard',
        icon: 'dashboard',
        badge: null,
    },
    {
        label: 'Transaksi & Pembayaran',
        href: route('manager.payments'),
        match: '/manager/payments',
        icon: 'payment',
        badge: unpaidAccountCount.value > 0
            ? {
                  text: `${unpaidAccountCount.value} Belum Bayar`,
                  variant: 'red',
              }
            : null,
    },
    {
        label: 'Katalog Event',
        href: route('home'),
        match: '/events-catalog',
        icon: 'catalog',
        badge: null,
    },
]);

const teamWorkspaceNavItems = computed(() => {
    if (props.hideTeamContext || !props.team) {
        return [];
    }

    return [
        {
            label: 'Daftar Pemain & Berkas',
            href: route('manager.players'),
            match: '/manager/players',
            locked: isUnpaidLocked.value,
            icon: 'players',
            badge: workflowBadges.value.players || null,
        },
        {
            label: 'Pelatih & Ofisial',
            href: route('manager.officials'),
            match: '/manager/officials',
            locked: isUnpaidLocked.value,
            icon: 'officials',
            badge: workflowBadges.value.officials || null,
        },
        {
            label: 'Akreditasi & Skuad',
            href: route('manager.accreditation.index'),
            match: '/manager/accreditation',
            icon: 'accreditation',
            badge: workflowBadges.value.accreditation || null,
        },
        {
            label: 'Jadwal & DSP Tanding',
            href: route('manager.matches.index'),
            match: '/manager/matches',
            icon: 'accreditation',
            badge: null,
        },
        {
            label: 'Profil Tim',
            href: route('manager.team-profile'),
            match: '/manager/team-profile',
            icon: 'profile',
            badge: null,
        },
    ];
});

const NAV_ICON_PATHS = {
    dashboard: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
    payment: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    catalog: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    players: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    officials: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2',
    accreditation: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    profile: 'M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9',
};

const navSections = computed(() => {
    const sections = [
        {
            key: 'account',
            title: 'Menu Akun',
            ariaLabel: 'Menu Akun',
            showTeamContext: false,
            items: accountNavItems.value,
        },
    ];

    if (!props.hideTeamContext && props.team && teamWorkspaceNavItems.value.length > 0) {
        sections.push({
            key: 'workspace',
            title: 'Workspace Tim Aktif',
            ariaLabel: 'Menu Workspace Tim Aktif',
            showTeamContext: true,
            items: teamWorkspaceNavItems.value,
        });
    }

    return sections;
});

const activeTeamsList = computed(() =>
    (props.teamsList || []).filter((item) => !item.is_event_completed)
);

const switchTeam = (event) => {
    const teamId = event.target.value;
    if (!teamId) return;
    router.post(route('manager.switch-team', { team: teamId }), {}, { preserveScroll: true });
};

const logoutForm = useForm({});
const logout = () => {
    logoutForm.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 flex flex-col lg:flex-row">
        <Head>
            <meta head-key="robots" name="robots" content="noindex, nofollow, noarchive, nosnippet" />
            <meta head-key="googlebot" name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />
        </Head>
        <!-- Mobile Top Navbar (Single subtle backdrop-blur surface per R-10) -->
        <header class="lg:hidden sticky top-0 z-40 frosted-header px-4 h-14 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <button
                    type="button"
                    aria-label="Buka menu navigasi"
                    :aria-expanded="mobileMenuOpen ? 'true' : 'false'"
                    aria-controls="manager-sidebar-nav"
                    class="w-10 h-10 flex items-center justify-center rounded-lg border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 active:scale-95 transition-all"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            v-if="!mobileMenuOpen"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                        <path
                            v-else
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
                <div class="flex items-center gap-2 min-w-0">
                    <template v-if="systemSettings.header_logo_style === 'logo_only' && systemSettings.app_logo_url">
                        <img
                            :src="systemSettings.app_logo_url"
                            :alt="systemSettings.app_name || 'Logo'"
                            width="140"
                            height="28"
                            class="h-7 w-auto max-w-[140px] object-contain"
                        />
                    </template>
                    <template v-else>
                        <img
                            v-if="systemSettings.app_logo_url"
                            :src="systemSettings.app_logo_url"
                            :alt="systemSettings.app_name || 'Logo'"
                            width="28"
                            height="28"
                            class="w-7 h-7 rounded-lg object-contain bg-white border border-slate-200 shrink-0"
                        />
                        <div v-else class="w-7 h-7 rounded-lg bg-orange-600 text-white flex items-center justify-center font-bold text-[11px] shrink-0">
                            {{ (systemSettings.app_name || 'YT').substring(0, 2).toUpperCase() }}
                        </div>
                        <span class="font-bold text-sm text-slate-900 truncate max-w-[160px]">
                            {{ systemSettings.app_name || 'Portal Akun' }}
                        </span>
                    </template>
                </div>
            </div>
            <button
                type="button"
                aria-label="Buka notifikasi akun"
                :aria-expanded="notifOpen ? 'true' : 'false'"
                aria-controls="account-notifications-panel"
                class="relative w-9 h-9 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors shrink-0 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700"
                @click="toggleNotifications"
            >
                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-black flex items-center justify-center tabular-data"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </button>
        </header>

        <!-- Mobile Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileMenuOpen"
                class="fixed inset-0 z-40 bg-slate-900/45 backdrop-blur-[2px] lg:hidden"
                aria-hidden="true"
                @click="mobileMenuOpen = false"
            ></div>
        </Transition>

        <!-- Sidebar Navigation (Matte surface per R-10) -->
        <aside
            id="manager-sidebar-nav"
            class="fixed inset-y-0 left-0 z-50 w-64 sidebar-ambient-surface border-r border-slate-200/80 shrink-0 flex flex-col justify-between lg:sticky lg:top-0 lg:h-screen transition-[transform,visibility] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]"
            :class="mobileMenuOpen ? 'translate-x-0 visible shadow-2xl' : '-translate-x-full invisible pointer-events-none lg:translate-x-0 lg:visible lg:pointer-events-auto'"
        >
            <div class="p-3.5 space-y-3 overflow-y-auto">
                <!-- Brand Header -->
                <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-slate-200/75">
                    <Link :href="route('home')" class="flex items-center group min-w-0">
                        <template v-if="systemSettings.header_logo_style === 'logo_only' && systemSettings.app_logo_url">
                            <img
                                :src="systemSettings.app_logo_url"
                                :alt="systemSettings.app_name || 'Logo'"
                                width="170"
                                height="36"
                                class="h-9 w-auto max-w-[170px] object-contain group-hover:opacity-90 transition-opacity"
                            />
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-2.5">
                                <img
                                    v-if="systemSettings.app_logo_url"
                                    :src="systemSettings.app_logo_url"
                                    :alt="systemSettings.app_name || 'Logo'"
                                    width="36"
                                    height="36"
                                    class="w-9 h-9 rounded-xl object-contain bg-white border border-slate-200/90 shadow-sm shrink-0"
                                />
                                <div v-else class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-600 to-amber-500 text-white shadow-sm flex items-center justify-center font-black text-sm tracking-tight group-hover:scale-105 transition-transform shrink-0">
                                    {{ (systemSettings.app_name || 'YT').substring(0, 2).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <span class="block text-sm font-bold text-slate-900 leading-tight group-hover:text-orange-700 transition-colors truncate">
                                        {{ systemSettings.app_name || 'YTEvent' }}
                                    </span>
                                    <span class="block text-[11px] font-medium text-slate-600 truncate">Portal Akun &amp; Kepesertaan</span>
                                </div>
                            </div>
                        </template>
                    </Link>
                    <button
                        type="button"
                        aria-label="Tutup menu navigasi"
                        class="lg:hidden w-8 h-8 flex items-center justify-center text-slate-600 hover:text-slate-900 rounded-lg border border-slate-300 bg-white cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700"
                        @click="mobileMenuOpen = false"
                    >
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- 2-Layer Navigation Sections -->
                <div class="space-y-3">
                    <div
                        v-for="section in navSections"
                        :key="section.key"
                        class="space-y-1.5"
                        :class="section.key === 'workspace' ? 'pt-2.5 border-t border-slate-200/75' : ''"
                    >
                        <p class="px-2 text-[10px] font-extrabold uppercase tracking-[0.12em] text-slate-600">
                            {{ section.title }}
                        </p>

                        <!-- Team Context Card (Rendered only inside Workspace Tim Aktif section) -->
                        <div v-if="section.showTeamContext && team" class="p-3.5 rounded-xl bg-gradient-to-br from-white via-orange-50/35 to-amber-50/30 border border-orange-200/75 shadow-sm space-y-2.5">
                            <div class="flex items-center gap-2.5">
                                <img
                                    v-if="team.logo_url"
                                    :src="team.logo_url"
                                    :alt="team.name"
                                    width="40"
                                    height="40"
                                    class="w-10 h-10 rounded-lg object-cover border border-slate-200 bg-white shadow-sm shrink-0"
                                />
                                <div v-else class="w-10 h-10 rounded-lg bg-gradient-to-br from-orange-100 to-amber-100 border border-orange-200 text-orange-900 flex items-center justify-center font-bold text-xs shrink-0">
                                    {{ team.name?.substring(0, 2).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold text-slate-900 truncate" :title="team.name">{{ team.name }}</p>
                                    <p class="text-[11px] text-slate-600 truncate" :title="team.event?.name">{{ team.event?.name }}</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-2 border-t border-orange-200/50">
                                <span class="text-[11px] font-medium text-slate-600">Status Tim</span>
                                <StatusBadge :status="team.status" class="scale-95 origin-right" />
                            </div>

                            <div v-if="activeTeamsList.length > 1" class="pt-2 border-t border-orange-200/50">
                                <label for="team-switcher-select" class="block text-[11px] font-bold text-slate-700 mb-1">Pindah Event / Tim</label>
                                <select
                                    id="team-switcher-select"
                                    class="select-compact w-full"
                                    :value="team.id"
                                    @change="switchTeam"
                                >
                                    <option v-for="item in activeTeamsList" :key="item.id" :value="item.id">
                                        {{ item.name }} ({{ item.event_name }})
                                    </option>
                                </select>
                            </div>
                        </div>

                        <nav :aria-label="section.ariaLabel" class="space-y-0.5">
                            <Link
                                v-for="item in section.items"
                                :key="item.href"
                                :href="item.href"
                                class="group sidebar-nav-item"
                                :class="currentPath.startsWith(item.match) ? 'sidebar-nav-item-active' : 'sidebar-nav-item-idle'"
                                @click="mobileMenuOpen = false"
                            >
                                <span
                                    class="sidebar-nav-icon"
                                    aria-hidden="true"
                                    :class="currentPath.startsWith(item.match) ? 'sidebar-nav-icon-active' : 'sidebar-nav-icon-idle'"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="NAV_ICON_PATHS[item.icon] || NAV_ICON_PATHS.profile" />
                                    </svg>
                                </span>

                                <span class="truncate flex-1 transition-transform duration-200 ease-out group-hover:translate-x-0.5">{{ item.label }}</span>

                                <span
                                    v-if="item.badge && !item.locked"
                                    class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-tight border shrink-0"
                                    :class="badgeColorClass(item.badge)"
                                >
                                    {{ item.badge.text }}
                                </span>

                                <span
                                    v-if="item.locked"
                                    class="text-slate-600 group-hover:text-slate-800 shrink-0 ml-1 transition-colors"
                                    title="Menu Terkunci: Selesaikan Pembayaran Terlebih Dahulu"
                                >
                                    <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </span>
                            </Link>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- User Profile & Action Footer -->
            <div class="p-4 flex items-center justify-between gap-2.5 sidebar-footer-surface">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">{{ user?.name }}</p>
                    <p class="text-[11px] text-slate-600 truncate">{{ user?.email }}</p>
                </div>
                <button
                    type="button"
                    class="btn-sm-danger shrink-0 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-700"
                    @click="logout"
                >
                    Keluar
                </button>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="hidden lg:flex h-16 frosted-header px-8 items-center justify-between sticky top-0 z-30">
                <div>
                    <p v-if="hideLayoutH1" class="text-sm font-bold text-slate-900">{{ pageTitle }}</p>
                    <h1 v-else class="text-base font-bold text-slate-900">{{ pageTitle }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    <div v-if="team?.event && !hideTeamContext" class="flex items-center gap-3 text-xs text-slate-700 bg-slate-50 px-3.5 py-1.5 rounded-lg border border-slate-200">
                        <span>{{ team.event.is_completed ? 'Arsip:' : 'Kompetisi:' }} <strong class="text-slate-900 font-semibold">{{ team.event.name }}</strong></span>
                        <span class="text-slate-300" aria-hidden="true">|</span>
                        <span>Kategori: <strong class="text-slate-900 font-semibold">{{ team.event.age_category }}</strong></span>
                    </div>
                    <button
                        type="button"
                        aria-label="Buka notifikasi akun"
                        :aria-expanded="notifOpen ? 'true' : 'false'"
                        aria-controls="account-notifications-panel"
                        class="relative w-9 h-9 flex items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors shrink-0 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700"
                        @click="toggleNotifications"
                    >
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span
                            v-if="unreadCount > 0"
                            class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-black flex items-center justify-center tabular-data"
                        >
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </button>
                </div>
            </header>

            <!-- Click-Outside Backdrop & Notification Dropdown Panel -->
            <Transition
                enter-active-class="transition-opacity duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="notifOpen"
                    class="fixed inset-0 z-40"
                    aria-hidden="true"
                    @click="notifOpen = false"
                ></div>
            </Transition>
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2 scale-[0.98]"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 -translate-y-1.5 scale-[0.98]"
            >
                <section
                    v-if="notifOpen"
                    id="account-notifications-panel"
                    aria-label="Daftar Notifikasi Akun"
                    class="fixed inset-x-3 top-16 sm:inset-x-auto sm:right-6 lg:right-8 sm:top-16 sm:w-96 z-50 bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden"
                >
                <div class="px-4 py-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <h2 class="text-xs sm:text-sm font-black text-slate-900">Notifikasi Akun</h2>
                        <span
                            v-if="unreadCount > 0"
                            class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-900 border border-rose-200 text-[10px] font-bold tabular-data"
                        >
                            {{ unreadCount }} Baru
                        </span>
                    </div>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-[11px] font-bold text-orange-700 hover:text-orange-900 hover:underline cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 rounded px-1.5 py-0.5"
                        @click="markAllAsRead"
                    >
                        Tandai Semua Dibaca
                    </button>
                </div>

                <div v-if="notificationItems.length > 0" class="max-h-[380px] overflow-y-auto divide-y divide-slate-200">
                    <button
                        v-for="item in notificationItems"
                        :key="item.id"
                        type="button"
                        class="w-full text-left p-3.5 hover:bg-slate-50 transition-colors flex flex-col gap-1.5 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-orange-700"
                        :class="!isNotificationRead(item.id) ? 'bg-orange-50/40 border-l-4 border-orange-600' : 'bg-white'"
                        @click="openNotificationItem(item)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span
                                class="px-2 py-0.5 rounded border text-[10px] font-bold uppercase tracking-wide"
                                :class="notifVariantClass(item.variant)"
                            >
                                {{ item.category }}
                            </span>
                            <span v-if="item.time_label" class="text-[10px] text-slate-600 truncate">
                                {{ item.time_label }}
                            </span>
                        </div>
                        <p class="text-xs font-bold text-slate-900 leading-snug">
                            {{ item.title }}
                        </p>
                        <p class="text-[11px] text-slate-600 leading-relaxed">
                            {{ item.message }}
                        </p>
                        <span class="text-[11px] font-bold text-orange-700">
                            {{ item.action_label }} &rarr;
                        </span>
                    </button>
                </div>

                <div v-else class="p-6 text-center space-y-1">
                    <p class="text-xs font-bold text-slate-900">Tidak Ada Notifikasi Baru</p>
                    <p class="text-[11px] text-slate-600 leading-relaxed">
                        Belum ada pembaruan baru. Seluruh status kepesertaan Anda terpantau aman.
                    </p>
                </div>
                </section>
            </Transition>

            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                <PageSkeleton v-if="isNavigating" :variant="skeletonVariant" />
                <div v-show="!isNavigating" class="page-transition-enter">
                    <FlashAlert />
                    <WorkflowStepBanner
                        v-if="!hideWorkflowBanner && !hideTeamContext && team?.workflow?.banner"
                        :banner="team.workflow.banner"
                        :team="team"
                    />
                    <slot />
                </div>
            </main>
        </div>

        <SimulationFloatingTool context-area="manager" :current-team-id="team?.id" :current-event-id="team?.event?.id" />
    </div>
</template>
