<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm, Head } from '@inertiajs/vue3';
import FlashAlert from '@/Components/FlashAlert.vue';
import InputError from '@/Components/InputError.vue';
import SimulationFloatingTool from '@/Components/SimulationFloatingTool.vue';

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
const adminNotifications = computed(() => page.props.adminNotifications || {});
const mobileMenuOpen = ref(false);
const currentPath = computed(() => page.url.split('?')[0]);

const isItemActive = (item) => {
    if (!item?.match) return false;
    if (Array.isArray(item.match)) {
        return item.match.some((m) => currentPath.value.startsWith(m));
    }
    return currentPath.value.startsWith(item.match);
};

const can = (permission) => {
    if (!user.value) return false;
    if (user.value.roles?.includes('superadmin')) return true;
    return user.value.permissions?.includes(permission) || false;
};

const badgeColorClass = (variant) => {
    switch (variant) {
        case 'orange':
            return 'bg-gradient-to-r from-orange-600 to-red-600 text-white border-orange-600 shadow-2xs';
        case 'red':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'amber':
            return 'bg-amber-100 text-amber-900 border-amber-200';
        case 'emerald':
            return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};

const safeRoute = (name, params = {}, fallback = '#') => {
    try {
        const routeFn = typeof window !== 'undefined' && typeof window.route === 'function' ? window.route : null;
        if (routeFn && typeof routeFn().has === 'function' && routeFn().has(name)) {
            return routeFn(name, params);
        }
    } catch {
        // Fallback gracefully
    }
    return fallback;
};

const navSections = computed(() => {
    const notif = adminNotifications.value || {};
    const sections = [
        {
            title: 'Operasional',
            items: [
                {
                    label: 'Dashboard',
                    href: safeRoute('admin.dashboard', {}, '/admin/dashboard'),
                    match: '/admin/dashboard',
                    icon: 'dashboard',
                    show: can('dashboard.admin.view'),
                    badge: notif.total_actionable > 0
                        ? { text: String(notif.total_actionable), variant: 'orange', title: 'Total antrean operasional aktif' }
                        : null,
                },
                {
                    label: 'Kompetisi',
                    href: safeRoute('admin.events.index', {}, '/admin/events'),
                    match: '/admin/events',
                    icon: 'trophy',
                    show: can('events.view'),
                    badge: null,
                },
                {
                    label: 'Daftar Tim',
                    href: safeRoute('admin.teams.index', {}, '/admin/teams'),
                    match: '/admin/teams',
                    icon: 'teams',
                    show: can('teams.view_all'),
                    badge: notif.ready_to_lock_teams_count > 0
                        ? { text: `${notif.ready_to_lock_teams_count} SIAP`, variant: 'amber', title: 'Tim lolos screening menunggu kunci skuad final' }
                        : notif.locked_teams_count > 0
                          ? { text: `${notif.locked_teams_count} FINAL`, variant: 'emerald', title: 'Tim telah kunci skuad final' }
                          : null,
                },
                {
                    label: 'Screening Pemain',
                    href: safeRoute('admin.screening.index', {}, '/admin/screening'),
                    match: '/admin/screening',
                    icon: 'screening',
                    show: can('screening.view_queue'),
                    badge: notif.pending_screening_players_count > 0
                        ? { text: String(notif.pending_screening_players_count), variant: 'orange', title: 'Pemain menunggu verifikasi screening' }
                        : null,
                },
                {
                    label: 'QR Scanner Lapangan',
                    href: safeRoute('admin.qr-scanner', {}, '/admin/qr-scanner'),
                    match: '/admin/qr-scanner',
                    icon: 'screening',
                    show: can('screening.view_queue'),
                    badge: null,
                },
            ],
        },
        {
            title: 'Keuangan & Mutasi',
            items: [
                {
                    label: 'Verifikasi Transfer',
                    href: safeRoute('admin.payments.manual-verification', {}, '/admin/payments/manual-verification'),
                    match: '/admin/payments/manual-verification',
                    icon: 'verify',
                    show: can('payments.verify_manual'),
                    badge: notif.pending_payments_count > 0
                        ? { text: String(notif.pending_payments_count), variant: 'amber', title: 'Bukti transfer menunggu verifikasi' }
                        : null,
                },
                {
                    label: 'Riwayat Transaksi',
                    href: safeRoute('admin.payments.transactions', {}, '/admin/payments/transactions'),
                    match: '/admin/payments/transactions',
                    icon: 'transactions',
                    show: can('payments.view_all'),
                    badge: notif.refund_required_count > 0
                        ? { text: `${notif.refund_required_count} REFUND`, variant: 'red', title: 'Transaksi memerlukan tindak lanjut refund' }
                        : null,
                },
            ],
        },
        {
            title: 'Sistem & Akses',
            items: [
                {
                    label: 'Manajemen Pengguna',
                    href: safeRoute('admin.users.index', {}, '/admin/users'),
                    match: '/admin/users',
                    icon: 'users',
                    show: user.value?.roles?.includes('superadmin'),
                    badge: null,
                },
                {
                    label: 'Hak Akses & Role',
                    href: safeRoute('admin.roles.index', {}, '/admin/roles'),
                    match: '/admin/roles',
                    icon: 'roles',
                    show: user.value?.roles?.includes('superadmin'),
                    badge: null,
                },
                {
                    label: 'Antrean Email',
                    href: safeRoute('admin.email-logs.index', {}, '/admin/system/email-logs'),
                    match: ['/admin/system/email-logs', '/admin/email-logs'],
                    icon: 'mail',
                    show: can('email_logs.manage'),
                    badge: notif.failed_emails_count > 0
                        ? { text: String(notif.failed_emails_count), variant: 'red', title: 'Pengiriman email gagal' }
                        : null,
                },
                {
                    label: 'Audit Trail',
                    href: safeRoute('admin.audit-logs.index', {}, '/admin/system/audit-logs'),
                    match: ['/admin/system/audit-logs', '/admin/audit-logs', '/admin/audit-trail'],
                    icon: 'transactions',
                    show: can('audit_logs.view'),
                    badge: null,
                },
                {
                    label: 'Monitoring Integrasi',
                    href: safeRoute('admin.integration-monitor.index', {}, '/admin/system/integration-monitor'),
                    match: ['/admin/system/integration-monitor', '/admin/integration-monitor'],
                    icon: 'transactions',
                    show: can('audit_logs.view') || can('system.queue.manage'),
                    badge: notif.failed_webhooks_count > 0 || notif.failed_jobs_count > 0
                        ? { text: String((notif.failed_webhooks_count || 0) + (notif.failed_jobs_count || 0)), variant: 'red', title: 'Webhook atau job gagal' }
                        : null,
                },
                {
                    label: 'Pengaturan Global',
                    href: safeRoute('admin.settings.index', {}, '/admin/settings'),
                    match: '/admin/settings',
                    icon: 'settings',
                    show: user.value?.roles?.includes('superadmin'),
                    badge: null,
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

const isPasswordModalOpen = ref(false);
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const openPasswordModal = () => {
    passwordForm.reset();
    passwordForm.clearErrors();
    isPasswordModalOpen.value = true;
};

const closePasswordModal = () => {
    isPasswordModalOpen.value = false;
};

const submitPasswordUpdate = () => {
    passwordForm.put(route('admin.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            isPasswordModalOpen.value = false;
        },
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col lg:flex-row">
        <Head>
            <meta head-key="robots" name="robots" content="noindex, nofollow, noarchive, nosnippet" />
            <meta head-key="googlebot" name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />
        </Head>
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
                                    isItemActive(item)
                                        ? 'bg-orange-500/10 text-orange-950 font-bold border-l-[3px] border-orange-600 shadow-2xs'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 border-l-[3px] border-transparent'
                                "
                                @click="mobileMenuOpen = false"
                            >
                                <!-- Icons -->
                                <span class="w-4 h-4 shrink-0" :class="isItemActive(item) ? 'text-orange-600' : 'text-slate-400'">
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
                                    <!-- Screening Icon -->
                                    <svg v-else-if="item.icon === 'screening'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
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
                                    <!-- Mail Icon -->
                                    <svg v-else-if="item.icon === 'mail'" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <!-- Settings Icon -->
                                    <svg v-else fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-4 h-4">
                                        <circle cx="12" cy="12" r="3" stroke-width="2"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
                                    </svg>
                                </span>
                                <span class="truncate flex-1">{{ item.label }}</span>
                                <span
                                    v-if="item.badge"
                                    :title="item.badge.title || ''"
                                    class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-tight border shrink-0"
                                    :class="badgeColorClass(item.badge.variant)"
                                >
                                    {{ item.badge.text }}
                                </span>
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
                        class="btn-sm-secondary cursor-pointer"
                        title="Ubah Kata Sandi Akun Panitia"
                        @click="openPasswordModal"
                    >
                        Sandi
                    </button>
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
                <div class="flex items-center gap-3">
                    <!-- Quick Operational Queue Pills -->
                    <div
                        v-if="adminNotifications.pending_screening_players_count > 0 || adminNotifications.pending_payments_count > 0 || adminNotifications.ready_to_lock_teams_count > 0"
                        class="flex items-center gap-2"
                    >
                        <Link
                            v-if="adminNotifications.pending_screening_players_count > 0 && can('screening.view_queue')"
                            :href="route('admin.screening.index')"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-orange-50 text-orange-800 border border-orange-200 hover:bg-orange-100 transition-colors"
                        >
                            <span>Screening: {{ adminNotifications.pending_screening_players_count }} Pemain</span>
                        </Link>
                        <Link
                            v-if="adminNotifications.pending_payments_count > 0 && can('payments.verify_manual')"
                            :href="route('admin.payments.manual-verification')"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-900 border border-amber-200 hover:bg-amber-100 transition-colors"
                        >
                            <span>Transfer: {{ adminNotifications.pending_payments_count }} Antrean</span>
                        </Link>
                        <Link
                            v-if="adminNotifications.ready_to_lock_teams_count > 0 && can('teams.view_all')"
                            :href="route('admin.teams.index')"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition-colors"
                            title="Tim yang sudah lolos screening semua pemain dan tinggal mengunci skuad final"
                        >
                            <span>Siap Akreditasi: {{ adminNotifications.ready_to_lock_teams_count }} Tim</span>
                        </Link>
                    </div>
                    <slot name="headerActions" />
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                <FlashAlert />
                <slot />
            </main>
        </div>

        <!-- Modal Ubah Kata Sandi Akun Panitia / Admin -->
        <div
            v-if="isPasswordModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            @click.self="closePasswordModal"
        >
            <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Ubah Kata Sandi Akun Panitia</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ user?.name }} ({{ user?.email }})</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700 text-lg leading-none" @click="closePasswordModal">
                        &times;
                    </button>
                </div>

                <form @submit.prevent="submitPasswordUpdate" novalidate class="space-y-3.5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi Saat Ini</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            class="input-field text-xs"
                            :class="{ 'input-field-error': passwordForm.errors.current_password }"
                            @input="passwordForm.clearErrors('current_password')"
                        />
                        <InputError :message="passwordForm.errors.current_password" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi Baru (Minimal 8 Karakter)</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            class="input-field text-xs"
                            :class="{ 'input-field-error': passwordForm.errors.password }"
                            @input="passwordForm.clearErrors('password')"
                        />
                        <InputError :message="passwordForm.errors.password" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Konfirmasi Kata Sandi Baru</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="input-field text-xs"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="closePasswordModal">
                            Batal
                        </button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="passwordForm.processing">
                            {{ passwordForm.processing ? 'Menyimpan...' : 'Simpan Kata Sandi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <SimulationFloatingTool context-area="admin" />
    </div>
</template>
