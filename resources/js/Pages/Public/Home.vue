<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    formats: {
        type: Object,
        default: () => ({}),
    },
    ageCategories: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
});

const { formatRupiah, formatDate } = useFormatters();

const filterState = reactive({
    search: props.filters.search || '',
    format: props.filters.format || '',
    age_category: props.filters.age_category || '',
    status: props.filters.status || '',
});

const applyFilters = () => {
    router.get(
        route('home'),
        {
            search: filterState.search || undefined,
            format: filterState.format || undefined,
            age_category: filterState.age_category || undefined,
            status: filterState.status || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    filterState.search = '';
    filterState.format = '';
    filterState.age_category = '';
    filterState.status = '';
    router.get(route('home'), {}, { preserveState: true, preserveScroll: true });
};

const quotaPercentage = (event) => {
    if (!event.max_teams) return 0;
    return Math.min(100, Math.round((event.occupied_slots / event.max_teams) * 100));
};
</script>

<template>
    <PublicLayout>
        <Head title="Katalog Kompetisi Sepak Bola" />

        <!-- Subtle Enterprise Frosted Hero -->
        <section class="bg-gradient-to-b from-orange-100/40 via-white/80 to-slate-50/50 border-b border-slate-200/80 backdrop-blur-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
                    <div class="max-w-2xl">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-100/80 text-orange-800 border border-orange-300/80 mb-3 shadow-sm">
                            Multi-Event Football Management
                        </span>
                        <h1 class="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
                            Portal Resmi Pendaftaran Turnamen &amp; Liga Sepak Bola
                        </h1>
                        <p class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Sistem pendaftaran tim terintegrasi dengan reservasi kuota real-time bebas race-condition, pembayaran instan maupun manual, serta kelengkapan berkas screening pemain berbasis kelompok usia.
                        </p>
                    </div>

                    <!-- Platform Stats -->
                    <div class="grid grid-cols-3 gap-3 sm:gap-4 shrink-0">
                        <div class="frosted-card px-4 py-3">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Kompetisi</p>
                            <p class="text-xl sm:text-2xl font-black text-slate-900 tabular-data mt-0.5">{{ stats.total_events || 0 }}</p>
                        </div>
                        <div class="frosted-card px-4 py-3 border-orange-200/80 bg-orange-50/30">
                            <p class="text-[11px] font-bold text-orange-600/80 uppercase tracking-wider">Slot Terisi</p>
                            <p class="text-xl sm:text-2xl font-black text-orange-600 tabular-data mt-0.5">{{ stats.total_teams || 0 }}</p>
                        </div>
                        <div class="frosted-card px-4 py-3">
                            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Pemain Sah</p>
                            <p class="text-xl sm:text-2xl font-black text-slate-900 tabular-data mt-0.5">{{ stats.total_verified_players || 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Filter Bar -->
                <form @submit.prevent="applyFilters" novalidate class="mt-8 frosted-card p-4 sm:p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div class="lg:col-span-2">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Cari Nama Event / Kota</label>
                            <input
                                v-model="filterState.search"
                                type="text"
                                placeholder="Contoh: Youth Cup, Jakarta, Bandung..."
                                class="input-field"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Format Kompetisi</label>
                            <select v-model="filterState.format" class="input-field" @change="applyFilters">
                                <option value="">Semua Format</option>
                                <option v-for="(label, key) in formats" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Kelompok Usia</label>
                            <select v-model="filterState.age_category" class="input-field" @change="applyFilters">
                                <option value="">Semua Usia</option>
                                <option v-for="cat in ageCategories" :key="cat" :value="cat">
                                    {{ cat }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Status Kuota</label>
                            <div class="flex items-center gap-2">
                                <select v-model="filterState.status" class="input-field" @change="applyFilters">
                                    <option value="">Semua Status</option>
                                    <option value="open">Dibuka (Open)</option>
                                    <option value="quota_full">Kuota Penuh</option>
                                    <option value="ongoing">Sedang Berjalan</option>
                                    <option value="completed">Selesai</option>
                                </select>
                                <button type="submit" class="btn-pitch px-4 py-2.5 shrink-0">Cari</button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="filterState.search || filterState.format || filterState.age_category || filterState.status"
                        class="mt-3 pt-2.5 border-t border-slate-200/70 flex items-center justify-between text-xs text-slate-500"
                    >
                        <span>Menampilkan {{ events.length }} kompetisi sesuai filter</span>
                        <button type="button" class="text-orange-600 font-semibold hover:text-orange-700 hover:underline" @click="resetFilters">
                            Reset Filter
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Event Cards Grid -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div v-if="events.length === 0" class="frosted-card p-10 text-center">
                <p class="text-sm font-semibold text-slate-800">Tidak ditemukan kompetisi yang sesuai dengan filter.</p>
                <p class="text-xs text-slate-500 mt-1">Silakan ubah kata kunci pencarian atau reset filter untuk melihat seluruh event aktif.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <article
                    v-for="event in events"
                    :key="event.id"
                    class="frosted-card flex flex-col justify-between overflow-hidden hover:border-orange-300/80 hover:shadow-lg transition-all"
                >
                    <div v-if="event.banner_url" class="h-40 w-full overflow-hidden bg-slate-100">
                        <img :src="event.banner_url" :alt="event.name" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" />
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-900 text-white">
                                        {{ event.age_category }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ event.format_label }}
                                    </span>
                                </div>
                                <StatusBadge :status="event.status" />
                            </div>

                            <Link :href="route('events.show', { event: event.slug })" class="group block">
                                <h2 class="text-base font-bold text-slate-900 group-hover:text-orange-600 transition-colors">
                                    {{ event.name }}
                                </h2>
                            </Link>

                            <p class="mt-1.5 text-xs text-slate-600 line-clamp-2">
                                {{ event.short_description }}
                            </p>

                        <dl class="mt-4 space-y-1.5 text-xs border-t border-slate-200/70 pt-3">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Lokasi / Kota</dt>
                                <dd class="font-medium text-slate-800 text-right truncate">
                                    {{ event.venue_name }}, {{ event.city }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Pelaksanaan</dt>
                                <dd class="font-medium text-slate-800 tabular-data">
                                    {{ formatDate(event.match_start_date) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500">Biaya Pendaftaran</dt>
                                <dd class="font-bold text-slate-900 tabular-data">
                                    {{ formatRupiah(event.registration_fee) }}
                                </dd>
                            </div>
                        </dl>

                        <!-- Quota Progress -->
                        <div class="mt-4 p-3 rounded-xl bg-slate-50/80 border border-slate-200/80">
                            <div class="flex items-center justify-between text-xs mb-1.5">
                                <span class="font-semibold text-slate-700">Kuota Tim Terisi</span>
                                <span class="font-bold tabular-data" :class="event.available_slots === 0 ? 'text-rose-700' : 'text-orange-800'">
                                    {{ event.occupied_slots }} / {{ event.max_teams }} Tim (Sisa {{ event.available_slots }})
                                </span>
                            </div>
                            <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="event.available_slots === 0 ? 'bg-rose-600' : 'bg-gradient-to-r from-orange-500 to-red-600'"
                                    :style="{ width: `${quotaPercentage(event)}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-200/70 flex items-center gap-2">
                        <Link
                            :href="route('events.show', { event: event.slug })"
                            class="btn-secondary flex-1 text-xs py-2"
                        >
                            Detail &amp; Regulasi
                        </Link>
                        <Link
                            v-if="event.can_register"
                            :href="route('events.register', { event: event.slug })"
                            class="btn-pitch flex-1 text-xs py-2"
                        >
                            Daftar Sekarang
                        </Link>
                        <span
                            v-else
                            class="flex-1 text-center text-xs font-semibold py-2 px-3 rounded-lg bg-slate-100 text-slate-500 border border-slate-200 cursor-not-allowed"
                        >
                            {{ event.available_slots === 0 ? 'Kuota Penuh' : 'Ditutup' }}
                        </span>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </PublicLayout>
</template>

