<script setup>
import { reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
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
    participantTypes: {
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

const page = usePage();
const systemSettings = computed(() => page.props.systemSettings || {});
const { formatRupiah, formatDate } = useFormatters();

const seoTitle = computed(() => 'Katalog Kompetisi & Turnamen Sepak Bola Resmi');
const seoDescription = computed(() => {
    const appName = systemSettings.value.app_name || 'Persija Development';
    return `Portal resmi pendaftaran turnamen dan liga sepak bola ${appName}. Reservasi slot tim real-time, pembayaran terverifikasi, screening dokumen usia pemain, dan penerbitan ID Card akreditasi.`;
});

const structuredCatalogJsonLd = computed(() => {
    const items = (props.events || []).map((ev, idx) => ({
        '@type': 'ListItem',
        position: idx + 1,
        item: {
            '@type': 'SportsEvent',
            name: `${ev.name} (${ev.age_category})`,
            sport: 'Soccer',
            startDate: ev.match_start_date || undefined,
            endDate: ev.match_end_date || ev.match_start_date || undefined,
            eventStatus: 'https://schema.org/EventScheduled',
            eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
            url: typeof window !== 'undefined' ? `${window.location.origin}/events/${ev.slug}` : `/events/${ev.slug}`,
            location: {
                '@type': 'Place',
                name: ev.venue_name,
                address: {
                    '@type': 'PostalAddress',
                    addressLocality: ev.city,
                    addressCountry: 'ID',
                },
            },
            offers: {
                '@type': 'Offer',
                price: Number(ev.registration_fee || 0),
                priceCurrency: 'IDR',
                availability: ev.can_register ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            },
        },
    }));

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'ItemList',
        name: 'Katalog Turnamen & Liga Sepak Bola Resmi',
        numberOfItems: items.length,
        itemListElement: items,
    });
});

const filterState = reactive({
    search: props.filters.search || '',
    format: props.filters.format || '',
    participant_type: props.filters.participant_type || '',
    age_category: props.filters.age_category || '',
    status: props.filters.status || '',
});

const applyFilters = () => {
    router.get(
        route('home'),
        {
            search: filterState.search || undefined,
            format: filterState.format || undefined,
            participant_type: filterState.participant_type || undefined,
            age_category: filterState.age_category || undefined,
            status: filterState.status || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    filterState.search = '';
    filterState.format = '';
    filterState.participant_type = '';
    filterState.age_category = '';
    filterState.status = '';
    router.get(route('home'), {}, { preserveState: true, preserveScroll: true });
};

const quotaPercentage = (event) => {
    const capacity = event.participant_type === 'individual'
        ? (event.max_participants || event.max_teams || 0)
        : (event.max_teams || 0);
    if (!capacity) return 0;
    return Math.min(100, Math.round((event.occupied_slots / capacity) * 100));
};

const stripHtml = (html) => {
    if (!html) return '';
    let text = String(html);
    // Decode HTML entities if present (e.g. &lt;ul&gt; -> <ul>, &nbsp; -> space)
    text = text
        .replace(/&lt;/gi, '<')
        .replace(/&gt;/gi, '>')
        .replace(/&quot;/gi, '"')
        .replace(/&#39;/gi, "'")
        .replace(/&amp;/gi, '&')
        .replace(/&nbsp;/gi, ' ');
    // Strip HTML tags
    text = text.replace(/<[^>]*>/g, ' ');
    // Strip any unclosed tag at the end
    text = text.replace(/<[^>]*$/g, ' ');
    // Clean any leaked inline Tailwind/CSS declarations if raw markup was stripped
    text = text.replace(/--tw-[\w-]+:[^;]+;?/gi, ' ');
    // Normalize spaces
    return text.replace(/\s+/g, ' ').trim();
};
</script>

<template>
    <PublicLayout>
        <Head :title="seoTitle">
            <meta head-key="description" name="description" :content="seoDescription" />
            <meta head-key="og:title" property="og:title" :content="seoTitle" />
            <meta head-key="og:description" property="og:description" :content="seoDescription" />
            <meta head-key="twitter:title" name="twitter:title" :content="seoTitle" />
            <meta head-key="twitter:description" name="twitter:description" :content="seoDescription" />
            <component :is="'script'" head-key="jsonld-catalog" type="application/ld+json" v-text="structuredCatalogJsonLd" />
        </Head>

        <!-- Hero Section (Seamless with Page Canvas, Identical to EventDetail) -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-4 sm:pt-6 sm:pb-6">
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-orange-100/80 text-orange-800 border border-orange-300/80 mb-3 shadow-2xs">
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
            <form @submit.prevent="applyFilters" novalidate class="mt-6 frosted-card p-4 sm:p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
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
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1">Peserta</label>
                        <select v-model="filterState.participant_type" class="input-field" @change="applyFilters">
                            <option value="">Individu &amp; Tim</option>
                            <option v-for="(label, key) in participantTypes" :key="key" :value="key">
                                {{ label }}
                            </option>
                        </select>
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
                    v-if="filterState.search || filterState.format || filterState.participant_type || filterState.age_category || filterState.status"
                    class="mt-3 pt-2.5 border-t border-slate-200/70 flex items-center justify-between text-xs text-slate-500"
                >
                    <span>Menampilkan {{ events.length }} kompetisi sesuai filter</span>
                    <button type="button" class="text-orange-600 font-semibold hover:text-orange-700 hover:underline" @click="resetFilters">
                        Reset Filter
                    </button>
                </div>
            </form>
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
                    <div v-if="event.banner_url" class="relative h-40 w-full overflow-hidden bg-slate-100">
                        <img :src="event.banner_url" :alt="event.name" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" />
                        <span
                            class="absolute left-3 bottom-3 px-2.5 py-1 rounded-md text-[11px] font-black tracking-wide bg-slate-950/85 text-white border border-white/30 shadow-sm"
                        >
                            {{ event.event_type === 'coaching_clinic' ? 'COACHING CLINIC' : (event.event_type === 'audition' ? 'AUDISI ATLET' : event.participant_type_label) }}
                        </span>
                    </div>
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-900 text-white">
                                        {{ event.age_category }}
                                    </span>
                                    <span
                                        v-if="event.event_type === 'coaching_clinic'"
                                        class="px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                    >
                                        Coaching Clinic
                                    </span>
                                    <span
                                        v-else-if="event.event_type === 'audition'"
                                        class="px-2 py-0.5 rounded text-xs font-bold bg-purple-50 text-purple-800 border border-purple-200"
                                    >
                                        Audisi Bakat
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200"
                                    >
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

                            <p v-if="stripHtml(event.short_description)" class="mt-1.5 text-xs text-slate-600 line-clamp-2 leading-relaxed">
                                {{ stripHtml(event.short_description) }}
                            </p>

                        <dl class="mt-4 space-y-2 text-xs border-t border-slate-200/70 pt-3">
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-slate-500 shrink-0 whitespace-nowrap pt-0.5 font-medium">Lokasi / Kota</dt>
                                <dd class="text-right min-w-0 max-w-[65%]">
                                    <span class="block font-semibold text-slate-900 leading-snug line-clamp-2" :title="event.venue_name">
                                        {{ event.venue_name }}
                                    </span>
                                    <span class="block text-[11px] text-slate-500 truncate mt-0.5" :title="event.city">
                                        {{ event.city }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500 shrink-0 whitespace-nowrap">Pelaksanaan</dt>
                                <dd class="font-medium text-slate-800 tabular-data">
                                    {{ formatDate(event.match_start_date) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-slate-500 shrink-0 whitespace-nowrap">Biaya Pendaftaran</dt>
                                <dd class="font-bold text-slate-900 tabular-data">
                                    {{ formatRupiah(event.registration_fee) }}
                                </dd>
                            </div>
                        </dl>

                        <!-- Quota Progress -->
                        <div v-if="event.is_unlimited_quota" class="mt-4 p-3 rounded-xl bg-emerald-50/80 border border-emerald-200/80">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-emerald-800">Model Pendaftaran</span>
                                <span class="font-bold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded text-[11px] border border-emerald-200">
                                    Buka s.d. Deadline
                                </span>
                            </div>
                            <p class="text-[11px] text-emerald-700 mt-1">
                                Kuota terbuka bebas. <strong class="text-emerald-950 font-bold tabular-data">{{ event.occupied_slots }} pendaftar</strong> telah bergabung.
                            </p>
                        </div>
                        <div v-else class="mt-4 p-3 rounded-xl bg-slate-50/80 border border-slate-200/80">
                            <div class="flex items-center justify-between text-xs mb-1.5">
                                <span class="font-semibold text-slate-700">
                                    {{ event.participant_type === 'individual' ? 'Peserta Terdaftar' : 'Tim Terdaftar' }}
                                </span>
                                <span class="font-bold tabular-data" :class="event.available_slots === 0 ? 'text-rose-700' : 'text-orange-800'">
                                    {{ event.occupied_slots }} / {{ event.participant_type === 'individual' ? (event.max_participants || event.max_teams) : event.max_teams }} {{ event.participant_type === 'individual' ? 'Peserta' : 'Tim' }} (Sisa {{ event.available_slots }})
                                </span>
                            </div>
                            <div v-if="event.participant_type === 'individual'" class="flex items-center justify-between text-[11px] text-slate-500 mb-2">
                                <span>Peserta Terverifikasi</span>
                                <span class="font-bold text-emerald-700 tabular-data">{{ event.verified_participants }}</span>
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
                            class="btn-secondary flex-1 text-center"
                        >
                            Detail &amp; Regulasi
                        </Link>
                        <Link
                            v-if="event.can_register"
                            :href="event.participant_type === 'individual' ? route('events.register-audition', { event: event.slug }) : route('events.register', { event: event.slug })"
                            class="btn-pitch flex-1 text-center"
                        >
                            {{ event.registration_label }}
                        </Link>
                        <span
                            v-else
                            class="flex-1 h-10 inline-flex items-center justify-center text-center text-xs font-semibold px-3 rounded-xl bg-slate-100 text-slate-500 border border-slate-200 cursor-not-allowed"
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
