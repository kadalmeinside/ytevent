<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || null);
const canViewAdminDashboard = computed(() => {
    if (!user.value) return false;
    if (user.value.roles?.includes('superadmin')) return true;
    return user.value.permissions?.includes('dashboard.admin.view') || false;
});

const logoutForm = useForm({});
const logout = () => {
    logoutForm.post(route('admin.logout'));
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900 pb-16">
        <Head title="Portal Talent Scout" />

        <!-- Top Sticky Header -->
        <header class="sticky top-0 z-30 bg-slate-900 text-white border-b border-slate-800 shadow-lg">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded bg-orange-600 text-[10px] font-black uppercase tracking-wider text-white">
                            PORTAL SCOUT
                        </span>
                        <span v-if="user?.name" class="text-xs font-semibold text-slate-300 truncate">
                            {{ user.name }}
                        </span>
                    </div>
                    <h1 class="text-base sm:text-lg font-black tracking-tight mt-0.5 truncate">
                        Daftar Event Seleksi &amp; Penilaian Lapangan
                    </h1>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <Link
                        v-if="canViewAdminDashboard"
                        :href="route('admin.dashboard')"
                        class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-bold text-slate-100 transition-colors"
                    >
                        Dashboard Admin
                    </Link>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded-lg bg-rose-600/90 hover:bg-rose-600 text-xs font-bold text-white transition-colors"
                        :disabled="logoutForm.processing"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-5xl mx-auto px-4 sm:px-6 pt-6 space-y-5">
            <div class="rounded-2xl bg-white border border-slate-200 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                <div>
                    <h2 class="text-sm sm:text-base font-bold text-slate-900">Pilih Event Audisi untuk Memulai Penilaian</h2>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Ketuk salah satu event perorangan di bawah ini untuk membuka panel Quick Grade (A/B/C), bintang rating, tag atribut, dan catatan pelatih.
                    </p>
                </div>
                <span class="px-3 py-1.5 rounded-xl bg-orange-50 border border-orange-200 text-xs font-bold text-orange-800 tabular-data shrink-0 self-start sm:self-auto">
                    {{ events.length }} Event Tersedia
                </span>
            </div>

            <div v-if="events.length === 0" class="rounded-2xl bg-white border border-slate-200 p-10 text-center space-y-2 shadow-sm">
                <p class="text-sm font-bold text-slate-900">Belum Ada Event Audisi / Perorangan</p>
                <p class="text-xs text-slate-600">
                    Admin belum membuat event kategori perorangan atau audisi bakat di sistem.
                </p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="evt in events"
                    :key="evt.id"
                    class="group rounded-2xl bg-white border border-slate-200 p-5 hover:border-orange-300 hover:-translate-y-0.5 hover:shadow-md transition-all duration-200 flex flex-col justify-between gap-4"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[10px] font-bold text-slate-800">
                                        {{ evt.age_category }}
                                    </span>
                                    <span class="text-[11px] font-semibold text-orange-800 bg-orange-50 border border-orange-200 px-2 py-0.5 rounded">
                                        {{ evt.event_type_label }}
                                    </span>
                                </div>
                                <h3 class="text-base font-black text-slate-900 mt-2 leading-snug">
                                    {{ evt.name }}
                                </h3>
                            </div>
                            <StatusBadge :status="evt.status" />
                        </div>

                        <div class="text-xs text-slate-600 space-y-1">
                            <p>
                                Lokasi: <strong class="text-slate-900">{{ evt.venue_name }}</strong> ({{ evt.city }})
                            </p>
                            <p>
                                Jadwal: <strong class="text-slate-900 tabular-data">{{ evt.match_start_date || 'Menunggu Jadwal' }}</strong>
                                <span v-if="evt.active_stage_name" class="text-slate-700">
                                    &bull; Tahap aktif: <strong class="text-slate-900">{{ evt.active_stage_name }}</strong>
                                </span>
                            </p>
                        </div>

                        <div class="grid grid-cols-3 gap-2 pt-1 text-center">
                            <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-2.5">
                                <span class="text-[10px] font-bold uppercase text-slate-600 block">Peserta</span>
                                <strong class="text-sm font-black font-mono text-slate-900 tabular-data">{{ evt.participants_count }}</strong>
                            </div>
                            <div class="rounded-xl bg-slate-50 border border-slate-200/80 p-2.5">
                                <span class="text-[10px] font-bold uppercase text-slate-600 block">Tahapan</span>
                                <strong class="text-sm font-black font-mono text-slate-900 tabular-data">{{ evt.stages_count }}</strong>
                            </div>
                            <div class="rounded-xl bg-emerald-50/70 border border-emerald-200/80 p-2.5">
                                <span class="text-[10px] font-bold uppercase text-emerald-800 block">Nilai Anda</span>
                                <strong class="text-sm font-black font-mono text-emerald-900 tabular-data">{{ evt.my_evaluations_count }}</strong>
                            </div>
                        </div>
                    </div>

                    <Link
                        :href="route('scout.events.show', { event: evt.id })"
                        class="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-500 hover:to-red-500 text-white text-xs font-bold text-center shadow-sm transition inline-flex items-center justify-center gap-2"
                    >
                        <span>Buka Panel Penilaian Lapangan</span>
                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </Link>
                </div>
            </div>
        </main>
    </div>
</template>
