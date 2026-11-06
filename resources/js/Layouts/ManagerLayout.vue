<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm, router } from '@inertiajs/vue3';
import FlashAlert from '@/Components/FlashAlert.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

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
        default: 'Portal Manajer Tim',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const systemSettings = computed(() => page.props.systemSettings || {});
const mobileMenuOpen = ref(false);

const currentPath = computed(() => page.url.split('?')[0]);

const isRosterLocked = computed(() => {
    if (!props.team) return false;
    return !props.team.can_manage_roster;
});

const navItems = computed(() => [
    {
        label: 'Ringkasan Tim',
        href: route('manager.dashboard'),
        match: '/manager/dashboard',
        icon: 'dashboard',
    },
    {
        label: 'Pembayaran Pendaftaran',
        href: route('manager.payments'),
        match: '/manager/payments',
        icon: 'payment',
    },
    {
        label: 'Daftar Pemain & Berkas',
        href: route('manager.players'),
        match: '/manager/players',
        locked: isRosterLocked.value,
        icon: 'players',
    },
    {
        label: 'Pelatih & Ofisial',
        href: route('manager.officials'),
        match: '/manager/officials',
        locked: isRosterLocked.value,
        icon: 'officials',
    },
    {
        label: 'Akreditasi & Skuad',
        href: route('manager.accreditation.index'),
        match: '/manager/accreditation',
        icon: 'accreditation',
    },
    {
        label: 'Profil Tim',
        href: route('manager.team-profile'),
        match: '/manager/team-profile',
        icon: 'profile',
    },
]);

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
    <div class="min-h-screen bg-transparent flex flex-col lg:flex-row">
        <!-- Mobile Top Navbar -->
        <header class="lg:hidden sticky top-0 z-40 frosted-header px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <button
                    type="button"
                    class="p-1.5 rounded-xl border border-slate-200/90 text-slate-700 bg-white/90 shadow-sm"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="flex items-center gap-2">
                    <template v-if="systemSettings.header_logo_style === 'logo_only' && systemSettings.app_logo_url">
                        <img
                            :src="systemSettings.app_logo_url"
                            :alt="systemSettings.app_name || 'Logo'"
                            class="h-7 w-auto max-w-[140px] object-contain"
                        />
                    </template>
                    <template v-else>
                        <img
                            v-if="systemSettings.app_logo_url"
                            :src="systemSettings.app_logo_url"
                            :alt="systemSettings.app_name || 'Logo'"
                            class="w-6 h-6 rounded-lg object-contain bg-white border border-slate-200 shrink-0"
                        />
                        <div v-else class="w-6 h-6 rounded-lg bg-gradient-to-br from-orange-600 to-red-600 text-white flex items-center justify-center font-bold text-[10px] shadow-sm shrink-0">
                            {{ (systemSettings.app_name || 'YT').substring(0, 2).toUpperCase() }}
                        </div>
                        <span class="font-bold text-sm text-slate-900 truncate max-w-[160px]">
                            {{ systemSettings.app_name || 'Portal Manajer' }}
                        </span>
                    </template>
                </div>
            </div>
            <Link :href="route('home')" class="text-xs font-semibold text-orange-600 hover:text-orange-700 hover:underline">
                Katalog Event
            </Link>
        </header>

        <!-- Sidebar Navigation -->
        <aside
            class="w-64 bg-white/80 backdrop-blur-xl border-r border-slate-200/80 shrink-0 flex-col justify-between lg:flex lg:sticky lg:top-0 lg:h-screen z-50 transition-all"
            :class="mobileMenuOpen ? 'fixed inset-y-0 left-0 flex shadow-2xl' : 'hidden'"
        >
            <div class="p-4 space-y-4 overflow-y-auto">
                <!-- Brand Header -->
                <div class="flex items-center justify-between pb-1">
                    <Link :href="route('home')" class="flex items-center group min-w-0">
                        <template v-if="systemSettings.header_logo_style === 'logo_only' && systemSettings.app_logo_url">
                            <img
                                :src="systemSettings.app_logo_url"
                                :alt="systemSettings.app_name || 'Logo'"
                                class="h-9 w-auto max-w-[170px] object-contain group-hover:opacity-90 transition-opacity"
                            />
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-2.5">
                                <img
                                    v-if="systemSettings.app_logo_url"
                                    :src="systemSettings.app_logo_url"
                                    :alt="systemSettings.app_name || 'Logo'"
                                    class="w-9 h-9 rounded-xl object-contain bg-white border border-slate-200/90 shadow-xs shrink-0"
                                />
                                <div v-else class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-600 via-orange-600 to-red-600 text-white flex items-center justify-center font-black text-sm tracking-tight shadow-md shadow-orange-500/20 group-hover:scale-105 transition-transform shrink-0">
                                    {{ (systemSettings.app_name || 'YT').substring(0, 2).toUpperCase() }}
                                </div>
                                <div class="min-w-0">
                                    <span class="block text-sm font-bold text-slate-900 leading-tight group-hover:text-orange-600 transition-colors truncate">
                                        {{ systemSettings.app_name || 'YTEvent' }}
                                    </span>
                                    <span class="block text-[11px] font-medium text-slate-500 truncate">Portal Manajer Tim</span>
                                </div>
                            </div>
                        </template>
                    </Link>
                    <button
                        type="button"
                        class="lg:hidden text-xs text-slate-500 hover:text-slate-800 p-1 rounded-lg border border-slate-200 bg-white"
                        @click="mobileMenuOpen = false"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Team Context Card -->
                <div v-if="team" class="p-3.5 rounded-2xl bg-white/70 backdrop-blur-md border border-slate-200/80 space-y-2.5 shadow-sm">
                    <div class="flex items-center gap-2.5">
                        <img
                            v-if="team.logo_url"
                            :src="team.logo_url"
                            :alt="team.name"
                            class="w-10 h-10 rounded-xl object-cover border border-slate-200/80 bg-white shrink-0 shadow-sm"
                        />
                        <div v-else class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-100 to-amber-100 border border-orange-200/80 text-orange-700 flex items-center justify-center font-bold text-xs shrink-0 shadow-sm">
                            {{ team.name?.substring(0, 2).toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-slate-900 truncate" :title="team.name">{{ team.name }}</p>
                            <p class="text-[11px] text-slate-500 truncate" :title="team.event?.name">{{ team.event?.name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/70">
                        <span class="text-[11px] font-medium text-slate-500">Status Tim</span>
                        <StatusBadge :status="team.status" class="scale-95 origin-right" />
                    </div>

                    <div v-if="teamsList.length > 1" class="pt-1.5 border-t border-slate-100">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Pindah Event / Tim</label>
                        <select
                            class="w-full text-xs border border-slate-300 rounded-xl px-2 py-1.5 bg-white text-slate-800 focus:outline-none focus:ring-1 focus:ring-orange-500 focus:border-orange-500"
                            :value="team.id"
                            @change="switchTeam"
                        >
                            <option v-for="item in teamsList" :key="item.id" :value="item.id">
                                {{ item.name }} ({{ item.event_name }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Nav Menu -->
                <nav class="space-y-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="group flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition-all"
                        :class="
                            currentPath.startsWith(item.match)
                                ? 'bg-gradient-to-r from-orange-500/15 via-orange-500/5 to-transparent text-orange-950 font-bold border-l-4 border-orange-600 shadow-sm'
                                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70 border-l-4 border-transparent'
                        "
                        @click="mobileMenuOpen = false"
                    >
                        <!-- Semantic SVG Icons -->
                        <span class="shrink-0 transition-colors" :class="currentPath.startsWith(item.match) ? 'text-orange-600' : 'text-slate-400 group-hover:text-slate-600'">
                            <!-- Dashboard Icon -->
                            <svg v-if="item.icon === 'dashboard'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <!-- Payment Icon -->
                            <svg v-else-if="item.icon === 'payment'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                            <!-- Players Icon -->
                            <svg v-else-if="item.icon === 'players'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <!-- Officials Icon -->
                            <svg v-else-if="item.icon === 'officials'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                            </svg>
                            <!-- Accreditation Icon -->
                            <svg v-else-if="item.icon === 'accreditation'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <!-- Profile Icon -->
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            </svg>
                        </span>

                        <!-- Menu Label -->
                        <span class="truncate flex-1">{{ item.label }}</span>

                        <!-- Subtle Padlock Icon for Locked State -->
                        <span
                            v-if="item.locked"
                            class="text-slate-400 group-hover:text-slate-500 shrink-0 ml-1 transition-colors"
                            title="Menu Terkunci: Selesaikan Pembayaran Terlebih Dahulu"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                    </Link>
                </nav>
            </div>

            <!-- User Profile & Action Footer -->
            <div class="p-4 border-t border-slate-200/80 space-y-3 bg-slate-50/60 backdrop-blur-sm">
                <div class="min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">{{ user?.name }}</p>
                    <p class="text-[11px] text-slate-500 truncate">{{ user?.email }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('home')" class="btn-sm-secondary flex-1 text-center truncate">
                        Daftar Event Lain
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

        <!-- Main Workspace -->
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="hidden lg:flex h-16 frosted-header px-8 items-center justify-between sticky top-0 z-30">
                <div>
                    <h1 class="text-base font-bold text-slate-900">{{ pageTitle }}</h1>
                </div>
                <div v-if="team?.event" class="flex items-center gap-3 text-xs text-slate-600 bg-white/60 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-slate-200/70 shadow-sm">
                    <span>Kompetisi: <strong class="text-slate-900 font-semibold">{{ team.event.name }}</strong></span>
                    <span class="text-slate-300">|</span>
                    <span>Kategori: <strong class="text-slate-900 font-semibold">{{ team.event.age_category }}</strong></span>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                <FlashAlert />
                <slot />
            </main>
        </div>
    </div>
</template>
