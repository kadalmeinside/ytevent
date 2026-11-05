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
    },
    {
        label: 'Pembayaran Pendaftaran',
        href: route('manager.payments'),
        match: '/manager/payments',
    },
    {
        label: 'Daftar Pemain & Berkas',
        href: route('manager.players'),
        match: '/manager/players',
        locked: isRosterLocked.value,
    },
    {
        label: 'Pelatih & Ofisial',
        href: route('manager.officials'),
        match: '/manager/officials',
        locked: isRosterLocked.value,
    },
    {
        label: 'Profil Tim',
        href: route('manager.team-profile'),
        match: '/manager/team-profile',
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
    <div class="min-h-screen bg-slate-50 flex flex-col lg:flex-row">
        <!-- Mobile Top Navbar -->
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
                <span class="font-bold text-sm text-slate-900">Portal Manajer Tim</span>
            </div>
            <Link :href="route('home')" class="text-xs font-medium text-emerald-700 hover:underline">
                Katalog Event
            </Link>
        </header>

        <!-- Sidebar Navigation -->
        <aside
            class="w-64 bg-white/85 backdrop-blur-xl border-r border-slate-200 shrink-0 flex-col justify-between lg:flex lg:sticky lg:top-0 lg:h-screen z-50"
            :class="mobileMenuOpen ? 'fixed inset-y-0 left-0 flex shadow-xl' : 'hidden'"
        >
            <div class="p-4 space-y-5 overflow-y-auto">
                <div class="flex items-center justify-between">
                    <Link :href="route('home')" class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-emerald-700 text-white flex items-center justify-center font-bold text-xs">
                            YT
                        </div>
                        <div>
                            <span class="block text-sm font-bold text-slate-900 leading-tight">YTEvent</span>
                            <span class="block text-[11px] text-slate-500">Portal Manajer Tim</span>
                        </div>
                    </Link>
                    <button
                        type="button"
                        class="lg:hidden text-xs text-slate-500 px-2 py-1"
                        @click="mobileMenuOpen = false"
                    >
                        Tutup
                    </button>
                </div>

                <!-- Team Context Card -->
                <div v-if="team" class="p-3 rounded-xl bg-slate-50 border border-slate-200/90 space-y-2">
                    <div class="flex items-center gap-2.5">
                        <img
                            v-if="team.logo_url"
                            :src="team.logo_url"
                            :alt="team.name"
                            class="w-9 h-9 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                        />
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-slate-900 truncate">{{ team.name }}</p>
                            <p class="text-[11px] text-slate-500 truncate">{{ team.event?.name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-1 border-t border-slate-200/70">
                        <span class="text-[11px] text-slate-500">Status Tim</span>
                        <StatusBadge :status="team.status" />
                    </div>

                    <div v-if="teamsList.length > 1" class="pt-1.5">
                        <label class="block text-[11px] font-medium text-slate-500 mb-1">Pindah Event / Tim</label>
                        <select
                            class="w-full text-xs border border-slate-300 rounded-lg px-2 py-1.5 bg-white text-slate-800"
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
                        class="flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                        :class="
                            currentPath.startsWith(item.match)
                                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200/80 font-semibold'
                                : 'text-slate-700 hover:bg-slate-100/80'
                        "
                        @click="mobileMenuOpen = false"
                    >
                        <span>{{ item.label }}</span>
                        <span
                            v-if="item.locked"
                            class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-slate-200 text-slate-600"
                        >
                            Terkunci
                        </span>
                    </Link>
                </nav>
            </div>

            <div class="p-4 border-t border-slate-200 space-y-2.5 bg-slate-50/70">
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-900 truncate">{{ user?.name }}</p>
                    <p class="text-[11px] text-slate-500 truncate">{{ user?.email }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('home')" class="btn-secondary flex-1 text-xs py-1.5 px-2.5">
                        Daftar Event Lain
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

        <!-- Main Workspace -->
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="hidden lg:flex h-16 frosted-header px-8 items-center justify-between sticky top-0 z-30">
                <div>
                    <h1 class="text-base font-bold text-slate-900">{{ pageTitle }}</h1>
                </div>
                <div v-if="team?.event" class="flex items-center gap-3 text-xs text-slate-600">
                    <span>Kompetisi: <strong class="text-slate-900">{{ team.event.name }}</strong></span>
                    <span class="text-slate-300">|</span>
                    <span>Kategori: <strong class="text-slate-900">{{ team.event.age_category }}</strong></span>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                <FlashAlert />
                <slot />
            </main>
        </div>
    </div>
</template>

