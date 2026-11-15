<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({ event_id: null, days: 14 }),
    },
    events: {
        type: Array,
        default: () => [],
    },
    kpi: {
        type: Object,
        required: true,
    },
    teamFunnel: {
        type: Object,
        required: true,
    },
    individualFunnel: {
        type: Object,
        required: true,
    },
    dailyTrends: {
        type: Array,
        default: () => [],
    },
    documentRejections: {
        type: Array,
        default: () => [],
    },
    demographics: {
        type: Object,
        default: () => ({ positions: [], top_clubs: [], scouting: {} }),
    },
    eventComparison: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah } = useFormatters();

const selectedEventId = ref(props.filters.event_id ? String(props.filters.event_id) : '');
const selectedDays = ref(Number(props.filters.days || 14));
const hoveredDay = ref(null);

const applyFilters = (overrides = {}) => {
    const nextEventId = overrides.event_id !== undefined ? overrides.event_id : selectedEventId.value;
    const nextDays = overrides.days !== undefined ? overrides.days : selectedDays.value;
    selectedEventId.value = nextEventId;
    selectedDays.value = nextDays;

    router.get(
        route('admin.analytics.index'),
        {
            event_id: nextEventId || undefined,
            days: nextDays,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const stepPercent = (value, base) => {
    if (!base || base <= 0) return 0;
    return Math.min(100, Math.round((value / base) * 100));
};

const teamFunnelSteps = computed(() => {
    const base = props.teamFunnel.registered || 0;
    return [
        { key: 'registered', label: 'Tim Mendaftar', count: props.teamFunnel.registered || 0, pct: base > 0 ? 100 : 0, color: 'bg-slate-700' },
        { key: 'paid', label: 'Pembayaran Lunas', count: props.teamFunnel.paid || 0, pct: stepPercent(props.teamFunnel.paid, base), color: 'bg-emerald-600' },
        { key: 'screening_passed', label: 'Lolos Screening 100%', count: props.teamFunnel.screening_passed || 0, pct: stepPercent(props.teamFunnel.screening_passed, base), color: 'bg-sky-600' },
        { key: 'locked_roster', label: 'Skuad Final Terkunci', count: props.teamFunnel.locked_roster || 0, pct: stepPercent(props.teamFunnel.locked_roster, base), color: 'bg-orange-600' },
    ];
});

const individualFunnelSteps = computed(() => {
    const base = props.individualFunnel.registered || 0;
    return [
        { key: 'registered', label: 'Peserta Mendaftar', count: props.individualFunnel.registered || 0, pct: base > 0 ? 100 : 0, color: 'bg-slate-700' },
        { key: 'paid', label: 'Lunas / Terkonfirmasi', count: props.individualFunnel.paid || 0, pct: stepPercent(props.individualFunnel.paid, base), color: 'bg-emerald-600' },
        { key: 'screening_passed', label: 'Berkas Sah (Lolos)', count: props.individualFunnel.screening_passed || 0, pct: stepPercent(props.individualFunnel.screening_passed, base), color: 'bg-sky-600' },
        { key: 'attended_gate', label: 'Hadir Check-in Gerbang', count: props.individualFunnel.attended_gate || 0, pct: stepPercent(props.individualFunnel.attended_gate, base), color: 'bg-amber-600' },
        { key: 'qualified', label: 'Lolos Seleksi / Skuad Inti', count: props.individualFunnel.qualified || 0, pct: stepPercent(props.individualFunnel.qualified, base), color: 'bg-orange-600' },
    ];
});

const periodTotals = computed(() => {
    return props.dailyTrends.reduce(
        (acc, day) => {
            acc.revenue += Number(day.revenue_paid || 0);
            acc.registrations += Number(day.total_registrations || 0);
            return acc;
        },
        { revenue: 0, registrations: 0 }
    );
});

const maxTrendRevenue = computed(() => {
    const max = Math.max(...props.dailyTrends.map((d) => Number(d.revenue_paid || 0)), 0);
    return max > 0 ? max : 1;
});

const maxTrendRegistrations = computed(() => {
    const max = Math.max(...props.dailyTrends.map((d) => Number(d.total_registrations || 0)), 0);
    return max > 0 ? max : 1;
});

const registrationPolylinePoints = computed(() => {
    const list = props.dailyTrends;
    if (list.length === 0) return '';
    if (list.length === 1) return '300,140';
    return list
        .map((day, idx) => {
            const x = Math.round((idx / (list.length - 1)) * 580) + 10;
            const y = 150 - Math.round((Number(day.total_registrations || 0) / maxTrendRegistrations.value) * 120);
            return `${x},${y}`;
        })
        .join(' ');
});

const scoutingTotal = computed(() => Number(props.demographics?.scouting?.total_evaluations || 0));
const scoutPercent = (count) => {
    if (!scoutingTotal.value) return 0;
    return Math.round((Number(count || 0) / scoutingTotal.value) * 100);
};
</script>

<template>
    <AdminLayout
        page-title="Analitik &amp; Laporan Operasional"
        subtitle="Pantau funnel konversi pendaftar, tren pendapatan harian, kendala verifikasi berkas, dan demografi atlet."
    >
        <Head title="Analitik & Laporan Operasional" />

        <div class="space-y-6">
            <!-- Filter Toolbar -->
            <div class="frosted-card p-4 sm:p-5 bg-white flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <div class="w-full sm:w-80">
                        <label for="analytics-event-filter" class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-600 mb-1">
                            Cakupan Kompetisi / Event
                        </label>
                        <select
                            id="analytics-event-filter"
                            :value="selectedEventId"
                            class="input-field w-full text-xs py-2"
                            @change="applyFilters({ event_id: $event.target.value })"
                        >
                            <option value="">Semua Event &amp; Kompetisi ({{ events.length }})</option>
                            <option v-for="evt in events" :key="evt.id" :value="String(evt.id)">
                                {{ evt.name }} ({{ evt.age_category }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-600 mb-1">
                            Rentang Tren Harian
                        </span>
                        <div class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 p-1 gap-1">
                            <button
                                v-for="d in [7, 14, 30]"
                                :key="d"
                                type="button"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
                                :class="selectedDays === d ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                                @click="applyFilters({ days: d })"
                            >
                                {{ d }} Hari
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <Link :href="route('admin.events.index')" class="btn-sm-secondary">
                        Manajemen Event
                    </Link>
                    <Link :href="route('admin.payments.transactions')" class="btn-sm-secondary">
                        Riwayat Transaksi
                    </Link>
                </div>
            </div>

            <!-- Executive KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Total Pendapatan Lunas -->
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-emerald-50/90 p-4 sm:p-5 hover:border-emerald-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-emerald-950/[0.04] transition-all duration-300 ease-out flex flex-col justify-between">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none select-none absolute -right-2 top-2.5 w-24 h-24 text-emerald-500/[0.11] transition-all duration-500 ease-out group-hover:text-emerald-500/[0.20] group-hover:scale-110 group-hover:-translate-x-1.5 group-hover:-translate-y-1 group-hover:-rotate-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="relative z-10">
                        <span class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-600">Total Pendapatan Lunas</span>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data mt-2 font-mono tracking-tight">
                            {{ formatRupiah(kpi.total_revenue_paid) }}
                        </p>
                    </div>
                    <div class="relative z-10 mt-3 pt-3 border-t border-emerald-200/50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-600">{{ kpi.paid_invoices_count }} invoice lunas</span>
                        <span class="font-bold text-slate-700 tabular-data">Pending: {{ formatRupiah(kpi.pending_revenue) }}</span>
                    </div>
                </div>

                <!-- Card 2: Rasio Konversi Bayar -->
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-orange-50/90 p-4 sm:p-5 hover:border-orange-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-orange-950/[0.04] transition-all duration-300 ease-out flex flex-col justify-between">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none select-none absolute -right-2 top-2.5 w-24 h-24 text-orange-500/[0.11] transition-all duration-500 ease-out group-hover:text-orange-500/[0.20] group-hover:scale-110 group-hover:-translate-x-1.5 group-hover:-translate-y-1 group-hover:rotate-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                    <div class="relative z-10">
                        <span class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-600">Rasio Konversi Bayar</span>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data mt-2 font-mono tracking-tight">
                            {{ kpi.paid_conversion_rate }}%
                        </p>
                    </div>
                    <div class="relative z-10 mt-3 pt-3 border-t border-orange-200/50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-600">Dari total pendaftar:</span>
                        <strong class="text-orange-800 font-bold tabular-data">{{ kpi.paid_registrants }} / {{ kpi.total_registrants }}</strong>
                    </div>
                </div>

                <!-- Card 3: Rasio Lolos Screening -->
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-sky-50/90 p-4 sm:p-5 hover:border-sky-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-sky-950/[0.04] transition-all duration-300 ease-out flex flex-col justify-between">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none select-none absolute -right-2 top-2.5 w-24 h-24 text-sky-600/[0.10] transition-all duration-500 ease-out group-hover:text-sky-600/[0.19] group-hover:scale-110 group-hover:-translate-x-1.5 group-hover:-translate-y-1 group-hover:-rotate-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="relative z-10">
                        <span class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-600">Rasio Lolos Screening</span>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data mt-2 font-mono tracking-tight">
                            {{ kpi.screening_pass_rate }}%
                        </p>
                    </div>
                    <div class="relative z-10 mt-3 pt-3 border-t border-sky-200/50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-600">Kelengkapan berkas sah</span>
                        <Link :href="route('admin.screening.index')" class="font-bold text-sky-800 hover:underline">
                            Antrean Screening &rarr;
                        </Link>
                    </div>
                </div>

                <!-- Card 4: Total Atlet Terdata -->
                <div class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-white via-white to-amber-50/90 p-4 sm:p-5 hover:border-amber-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-amber-950/[0.04] transition-all duration-300 ease-out flex flex-col justify-between">
                    <svg
                        aria-hidden="true"
                        class="pointer-events-none select-none absolute -right-2 top-2.5 w-24 h-24 text-amber-500/[0.11] transition-all duration-500 ease-out group-hover:text-amber-500/[0.21] group-hover:scale-110 group-hover:-translate-x-1.5 group-hover:-translate-y-1 group-hover:rotate-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <div class="relative z-10">
                        <span class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-600">Total Atlet Terdata</span>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 tabular-data mt-2 font-mono tracking-tight">
                            {{ kpi.total_athletes }}
                            <span class="text-base font-bold text-slate-600 font-sans">Atlet</span>
                        </p>
                    </div>
                    <div class="relative z-10 mt-3 pt-3 border-t border-amber-200/50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-600">Pemain Tim + Peserta Audisi</span>
                        <span class="font-bold text-amber-900 tabular-data">{{ kpi.total_registrants }} entitas</span>
                    </div>
                </div>
            </div>

            <!-- Dual Conversion Funnels -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Team Tournament Funnel -->
                <div class="frosted-card p-5 sm:p-6 bg-white space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Funnel Konversi Turnamen Tim</h2>
                            <p class="text-xs text-slate-600 mt-0.5">Alur dari registrasi tim hingga penguncian skuad final (locked roster).</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-orange-50 border border-orange-200 text-orange-800 text-[11px] font-bold">
                            Jalur Tim
                        </span>
                    </div>

                    <div class="space-y-3 pt-1">
                        <div v-for="step in teamFunnelSteps" :key="step.key" class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-800">{{ step.label }}</span>
                                <span class="font-mono font-bold text-slate-900 tabular-data">
                                    {{ step.count }}
                                    <span class="text-slate-600 font-sans font-semibold ml-1">({{ step.pct }}%)</span>
                                </span>
                            </div>
                            <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-300"
                                    :class="step.color"
                                    :style="{ width: `${step.pct}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Individual / Audition Funnel -->
                <div class="frosted-card p-5 sm:p-6 bg-white space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Funnel Konversi Audisi &amp; Perorangan</h2>
                            <p class="text-xs text-slate-600 mt-0.5">Alur peserta dari registrasi, check-in gerbang, hingga lolos seleksi.</p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-[11px] font-bold">
                            Jalur Perorangan
                        </span>
                    </div>

                    <div class="space-y-3 pt-1">
                        <div v-for="step in individualFunnelSteps" :key="step.key" class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-800">{{ step.label }}</span>
                                <span class="font-mono font-bold text-slate-900 tabular-data">
                                    {{ step.count }}
                                    <span class="text-slate-600 font-sans font-semibold ml-1">({{ step.pct }}%)</span>
                                </span>
                            </div>
                            <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-300"
                                    :class="step.color"
                                    :style="{ width: `${step.pct}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Revenue & Registration Trend Chart -->
            <div class="frosted-card p-5 sm:p-6 bg-white space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Tren Pendapatan &amp; Pendaftaran Harian ({{ selectedDays }} Hari Terakhir)</h2>
                        <p class="text-xs text-slate-600 mt-0.5">Batang jingga menunjukkan pendapatan lunas; garis gelap menunjukkan jumlah pendaftar baru per hari.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-xs">
                        <div class="px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200">
                            <span class="text-slate-600 block text-[10px] font-bold uppercase">Total {{ selectedDays }} Hari</span>
                            <strong class="font-mono font-bold text-emerald-800 tabular-data">{{ formatRupiah(periodTotals.revenue) }}</strong>
                        </div>
                        <div class="px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-200">
                            <span class="text-slate-600 block text-[10px] font-bold uppercase">Pendaftar Baru</span>
                            <strong class="font-mono font-bold text-slate-900 tabular-data">{{ periodTotals.registrations }} entitas</strong>
                        </div>
                    </div>
                </div>

                <div class="relative rounded-xl border border-slate-200/80 bg-slate-50/70 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3 text-xs">
                        <div class="flex items-center gap-4">
                            <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                                <span class="w-3 h-3 rounded bg-orange-500 inline-block" aria-hidden="true"></span>
                                Pendapatan Lunas (Rp)
                            </span>
                            <span class="inline-flex items-center gap-1.5 font-semibold text-slate-700">
                                <span class="w-3 h-0.5 bg-slate-800 inline-block" aria-hidden="true"></span>
                                Pendaftar Baru (Tim &amp; Atlet)
                            </span>
                        </div>
                        <div v-if="hoveredDay" class="font-mono text-[11px] font-bold text-slate-800 tabular-data bg-white px-2.5 py-1 rounded-lg border border-slate-200 shadow-sm">
                            {{ hoveredDay.label }}: {{ formatRupiah(hoveredDay.revenue_paid) }} | {{ hoveredDay.total_registrations }} pendaftar
                        </div>
                    </div>

                    <svg viewBox="0 0 600 175" class="w-full h-44 overflow-visible" role="img" aria-label="Grafik tren pendapatan dan pendaftaran harian">
                        <!-- Horizontal grid lines -->
                        <line x1="10" y1="30" x2="590" y2="30" stroke="#e2e8f0" stroke-dasharray="4 4" />
                        <line x1="10" y1="90" x2="590" y2="90" stroke="#e2e8f0" stroke-dasharray="4 4" />
                        <line x1="10" y1="150" x2="590" y2="150" stroke="#cbd5e1" />

                        <!-- Revenue Bars -->
                        <g v-for="(day, idx) in dailyTrends" :key="day.date">
                            <rect
                                :x="dailyTrends.length > 1 ? Math.round((idx / dailyTrends.length) * 580) + 12 : 285"
                                :y="150 - Math.round((Number(day.revenue_paid || 0) / maxTrendRevenue) * 115)"
                                :width="Math.max(4, Math.floor(540 / Math.max(1, dailyTrends.length)) - 3)"
                                :height="Math.max(2, Math.round((Number(day.revenue_paid || 0) / maxTrendRevenue) * 115))"
                                rx="3"
                                class="fill-orange-500/80 hover:fill-orange-600 transition-colors cursor-pointer"
                                @mouseenter="hoveredDay = day"
                                @mouseleave="hoveredDay = null"
                            />
                        </g>

                        <!-- Registrations Polyline -->
                        <polyline
                            v-if="dailyTrends.length > 1"
                            fill="none"
                            stroke="#0f172a"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            :points="registrationPolylinePoints"
                        />

                        <!-- Registration Dots & Sparse X Labels -->
                        <g v-for="(day, idx) in dailyTrends" :key="`pt-${day.date}`">
                            <circle
                                :cx="dailyTrends.length > 1 ? Math.round((idx / (dailyTrends.length - 1)) * 580) + 10 : 300"
                                :cy="150 - Math.round((Number(day.total_registrations || 0) / maxTrendRegistrations) * 120)"
                                r="3.5"
                                class="fill-white stroke-slate-900 cursor-pointer"
                                stroke-width="2"
                                @mouseenter="hoveredDay = day"
                                @mouseleave="hoveredDay = null"
                            />
                            <text
                                v-if="idx === 0 || idx === dailyTrends.length - 1 || idx % Math.max(1, Math.floor(dailyTrends.length / 6)) === 0"
                                :x="dailyTrends.length > 1 ? Math.round((idx / (dailyTrends.length - 1)) * 580) + 10 : 300"
                                y="168"
                                text-anchor="middle"
                                class="fill-slate-600 text-[10px] font-medium"
                            >
                                {{ day.label }}
                            </text>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- Bottleneck, Demographics & Scouting Insights -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Document Rejection Bottlenecks -->
                <div class="frosted-card p-5 sm:p-6 bg-white space-y-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Kendala Verifikasi Dokumen</h2>
                        <p class="text-xs text-slate-600 mt-0.5">Jenis berkas yang paling sering berstatus ditolak (invalid) saat screening.</p>
                    </div>

                    <div v-if="documentRejections.length === 0" class="rounded-xl bg-emerald-50/70 border border-emerald-200 p-4 text-xs text-emerald-900">
                        Belum ada catatan penolakan dokumen pada filter event yang dipilih.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="doc in documentRejections" :key="doc.type" class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-800 truncate pr-2">{{ doc.label }}</span>
                                <span class="font-mono font-bold text-rose-700 tabular-data shrink-0">
                                    {{ doc.count }} ({{ doc.share_percent }}%)
                                </span>
                            </div>
                            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-rose-500 rounded-full" :style="{ width: `${doc.share_percent}%` }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Position & Club Demographics -->
                <div class="frosted-card p-5 sm:p-6 bg-white space-y-5">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Demografi Posisi &amp; Asal Klub</h2>
                        <p class="text-xs text-slate-600 mt-0.5">Sebaran posisi bermain atlet dan asal SSB / asosiasi teratas.</p>
                    </div>

                    <div class="space-y-2.5">
                        <div v-for="pos in demographics.positions" :key="pos.code" class="space-y-1">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-800">{{ pos.label }}</span>
                                <span class="font-mono font-bold text-slate-900 tabular-data">{{ pos.count }} ({{ pos.percent }}%)</span>
                            </div>
                            <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-slate-800 rounded-full" :style="{ width: `${pos.percent}%` }"></div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-200/80 space-y-2">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-600">Top Asal Klub / SSB</h3>
                        <div v-if="(demographics.top_clubs || []).length === 0" class="text-xs text-slate-600">
                            Belum ada data asal klub/SSB tercatat.
                        </div>
                        <div v-else class="space-y-1.5 text-xs">
                            <div v-for="club in demographics.top_clubs" :key="club.name" class="flex items-center justify-between gap-2">
                                <span class="text-slate-800 font-medium truncate">{{ club.name }}</span>
                                <span class="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded tabular-data shrink-0">{{ club.count }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scouting Evaluation Summary -->
                <div class="frosted-card p-5 sm:p-6 bg-white flex flex-col justify-between space-y-5">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h2 class="text-base font-bold text-slate-900">Evaluasi Talent Scout</h2>
                                <p class="text-xs text-slate-600 mt-0.5">Rekap nilai Grade A/B/C dari panel penilaian lapangan.</p>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-bold uppercase text-slate-600 block">Rata Rating</span>
                                <span class="text-lg font-black font-mono text-amber-700 tabular-data">
                                    {{ demographics.scouting?.avg_stars || 0 }} / 5
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2.5">
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 text-center">
                                <span class="text-[10px] font-extrabold uppercase text-emerald-800 block">Grade A</span>
                                <strong class="text-xl font-black font-mono text-emerald-900 tabular-data block mt-0.5">
                                    {{ demographics.scouting?.grade_a || 0 }}
                                </strong>
                                <span class="text-[11px] text-emerald-800 tabular-data">{{ scoutPercent(demographics.scouting?.grade_a) }}%</span>
                            </div>
                            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-center">
                                <span class="text-[10px] font-extrabold uppercase text-amber-900 block">Grade B</span>
                                <strong class="text-xl font-black font-mono text-amber-900 tabular-data block mt-0.5">
                                    {{ demographics.scouting?.grade_b || 0 }}
                                </strong>
                                <span class="text-[11px] text-amber-900 tabular-data">{{ scoutPercent(demographics.scouting?.grade_b) }}%</span>
                            </div>
                            <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 text-center">
                                <span class="text-[10px] font-extrabold uppercase text-rose-800 block">Grade C</span>
                                <strong class="text-xl font-black font-mono text-rose-900 tabular-data block mt-0.5">
                                    {{ demographics.scouting?.grade_c || 0 }}
                                </strong>
                                <span class="text-[11px] text-rose-800 tabular-data">{{ scoutPercent(demographics.scouting?.grade_c) }}%</span>
                            </div>
                        </div>

                        <p class="text-xs text-slate-600">
                            Total penilaian tersimpan: <strong class="text-slate-900 font-mono tabular-data">{{ scoutingTotal }}</strong> evaluasi.
                        </p>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-between gap-2">
                        <Link :href="route('scout.index')" class="btn-sm-pitch">
                            Buka Portal Scout &rarr;
                        </Link>
                        <Link :href="route('admin.auditions.scanner')" class="btn-sm-secondary">
                            Scanner Gerbang
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Event Performance Comparison & Quick CSV Exports -->
            <div class="frosted-card overflow-hidden bg-white">
                <div class="p-5 sm:p-6 border-b border-slate-200/80 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Perbandingan Performa Event &amp; Ekspor Laporan</h2>
                        <p class="text-xs text-slate-600 mt-0.5">Evaluasi keterisian kuota, realisasi pendapatan, dan rasio kelolosan berkas per kompetisi.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-700 uppercase text-[10px] font-bold">
                                <th class="py-3 px-4">Kompetisi / Event</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Keterisian Kuota</th>
                                <th class="py-3 px-4">Pendapatan Lunas</th>
                                <th class="py-3 px-4">Lolos Screening</th>
                                <th class="py-3 px-4 text-right">Laporan CSV &amp; Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="eventComparison.length === 0">
                                <td colspan="6" class="py-10 text-center text-slate-600">
                                    Belum ada event terdaftar untuk dibandingkan.
                                </td>
                            </tr>
                            <tr v-for="row in eventComparison" :key="row.id" class="hover:bg-orange-50/20">
                                <td class="py-3.5 px-4">
                                    <Link :href="route('admin.events.show', { event: row.id })" class="font-bold text-slate-900 hover:text-orange-700">
                                        {{ row.name }}
                                    </Link>
                                    <div class="text-[11px] text-slate-600 mt-0.5">
                                        {{ row.event_type_label }} &bull; {{ row.age_category }}
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <StatusBadge :status="row.status" />
                                </td>
                                <td class="py-3.5 px-4 font-mono tabular-data">
                                    <span v-if="row.quota_type === 'unlimited'" class="font-bold text-slate-900">
                                        {{ row.occupied }} (Tanpa Batas)
                                    </span>
                                    <span v-else class="font-bold text-slate-900">
                                        {{ row.occupied }} / {{ row.capacity }}
                                        <span class="text-slate-600 font-sans font-semibold ml-1">({{ row.fill_rate }}%)</span>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-emerald-800 tabular-data">
                                    {{ formatRupiah(row.revenue_paid) }}
                                </td>
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900 tabular-data">
                                    {{ row.screening_pass_rate }}%
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-1.5 flex-wrap">
                                        <template v-if="row.participant_type === 'individual'">
                                            <a
                                                :href="route('admin.events.export-participants', { event: row.id })"
                                                class="btn-sm-secondary text-[11px]"
                                            >
                                                Peserta CSV
                                            </a>
                                            <a
                                                :href="route('admin.events.export-scouting', { event: row.id })"
                                                class="btn-sm-secondary text-[11px]"
                                            >
                                                Scouting CSV
                                            </a>
                                        </template>
                                        <template v-else>
                                            <a
                                                :href="route('admin.teams.export', { event_id: row.id })"
                                                class="btn-sm-secondary text-[11px]"
                                            >
                                                Tim CSV
                                            </a>
                                            <a
                                                :href="route('admin.teams.export-players', { event_id: row.id })"
                                                class="btn-sm-secondary text-[11px]"
                                            >
                                                Pemain CSV
                                            </a>
                                        </template>
                                        <Link :href="route('admin.events.show', { event: row.id })" class="btn-sm-pitch text-[11px]">
                                            Workspace
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
