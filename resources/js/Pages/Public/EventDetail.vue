<script setup>
import { ref, computed } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    registeredTeams: {
        type: Array,
        default: () => [],
    },
    groups: {
        type: Array,
        default: () => [],
    },
    matches: {
        type: Array,
        default: () => [],
    },
    leaderboard: {
        type: Object,
        default: () => ({ top_scorers: [], fair_play: [] }),
    },
    stages: {
        type: Array,
        default: () => [],
    },
    registeredParticipants: {
        type: Array,
        default: () => [],
    },
});

const { formatRupiah, formatDate, formatDateTime, formatBirthDateRange } = useFormatters();
const activeTab = ref('summary');

const isIndividual = computed(() => props.event.participant_type === 'individual');

const totalCapacity = computed(() => {
    return isIndividual.value
        ? (props.event.max_participants || props.event.max_teams || 0)
        : (props.event.max_teams || 0);
});

const quotaPercentage = computed(() => {
    if (!totalCapacity.value) return 0;
    return Math.min(100, Math.round((props.event.occupied_slots / totalCapacity.value) * 100));
});

const participantUnit = computed(() => (isIndividual.value ? 'Peserta' : 'Tim'));

const publicTopScorers = computed(() => props.leaderboard?.top_scorers || []);

const activePositionCaps = computed(() => {
    if (!props.event.position_quota_caps || typeof props.event.position_quota_caps !== 'object') {
        return {};
    }
    const result = {};
    for (const [pos, cap] of Object.entries(props.event.position_quota_caps)) {
        const val = Number(cap);
        if (!isNaN(val) && val > 0) {
            result[pos] = val;
        }
    }
    return result;
});

const hasPositionCaps = computed(() => Object.keys(activePositionCaps.value).length > 0);

const formattedDescription = computed(() => {
    let desc = props.event?.short_description || '';
    if (!desc) return '';
    // If the content contains encoded HTML tags like &lt;p&gt; or &lt;ul&gt;, decode them
    if (desc.includes('&lt;') && desc.includes('&gt;')) {
        desc = desc
            .replace(/&lt;/gi, '<')
            .replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"')
            .replace(/&#39;/gi, "'")
            .replace(/&amp;/gi, '&')
            .replace(/&nbsp;/gi, ' ');
    }
    if (/<[a-z][\s\S]*>/i.test(desc)) {
        return desc;
    }
    return desc.replace(/\n/g, '<br>');
});

const plainDescription = computed(() => {
    const raw = formattedDescription.value.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    if (raw) {
        return raw.slice(0, 160);
    }
    return `Pendaftaran resmi ${props.event.name} (${props.event.age_category}) di ${props.event.venue_name}, ${props.event.city}. Kuota maksimal ${totalCapacity.value} ${participantUnit.value.toLowerCase()}.`;
});

const eventJsonLd = computed(() => {
    const ev = props.event;
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    const detailUrl = `${origin}/events/${ev.slug}`;
    const registerUrl = isIndividual.value
        ? `${origin}/events/${ev.slug}/register-audition`
        : `${origin}/events/${ev.slug}/register`;

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'BreadcrumbList',
                itemListElement: [
                    {
                        '@type': 'ListItem',
                        position: 1,
                        name: isIndividual.value ? 'Katalog Event' : 'Katalog Kompetisi',
                        item: `${origin}/`,
                    },
                    {
                        '@type': 'ListItem',
                        position: 2,
                        name: `${ev.name} (${ev.age_category})`,
                        item: detailUrl,
                    },
                ],
            },
            {
                '@type': 'SportsEvent',
                name: `${ev.name} (${ev.age_category})`,
                description: plainDescription.value,
                sport: 'Soccer',
                startDate: ev.match_start_date || undefined,
                endDate: ev.match_end_date || ev.match_start_date || undefined,
                eventStatus: 'https://schema.org/EventScheduled',
                eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
                maximumAttendeeCapacity: Number(totalCapacity.value || 0),
                remainingAttendeeCapacity: Number(ev.available_slots || 0),
                image: ev.banner_url ? [ev.banner_url] : undefined,
                url: detailUrl,
                location: {
                    '@type': 'Place',
                    name: ev.venue_name,
                    hasMap: ev.venue_maps_url || undefined,
                    address: {
                        '@type': 'PostalAddress',
                        addressLocality: ev.city,
                        addressCountry: 'ID',
                    },
                },
                offers: {
                    '@type': 'Offer',
                    url: registerUrl,
                    price: Number(ev.registration_fee || 0),
                    priceCurrency: 'IDR',
                    availability: ev.can_register ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
                    validFrom: ev.registration_open_at || undefined,
                    validThrough: ev.registration_close_at || undefined,
                },
            },
        ],
    });
});
</script>

<template>
    <PublicLayout>
        <Head :title="`${event.name} (${event.age_category})`">
            <meta head-key="description" name="description" :content="plainDescription" />
            <meta head-key="og:type" property="og:type" content="article" />
            <meta head-key="og:title" property="og:title" :content="`${event.name} (${event.age_category})`" />
            <meta head-key="og:description" property="og:description" :content="plainDescription" />
            <meta v-if="event.banner_url" head-key="og:image" property="og:image" :content="event.banner_url" />
            <meta head-key="twitter:title" name="twitter:title" :content="`${event.name} (${event.age_category})`" />
            <meta head-key="twitter:description" name="twitter:description" :content="plainDescription" />
            <meta v-if="event.banner_url" head-key="twitter:image" name="twitter:image" :content="event.banner_url" />
            <component :is="'script'" head-key="jsonld-event" type="application/ld+json" v-text="eventJsonLd" />
        </Head>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-2 pb-8 sm:pt-3 sm:pb-12">
            <div class="mb-2.5">
                <Link :href="route('home')" class="inline-flex items-center gap-1.5 min-h-[44px] sm:min-h-0 py-1 text-xs font-semibold text-slate-600 hover:text-orange-600 transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    <span>{{ isIndividual ? 'Kembali ke Katalog Event' : 'Kembali ke Katalog Kompetisi' }}</span>
                </Link>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Event Hero Banner -->
                    <div v-if="event.banner_url" class="rounded-2xl overflow-hidden border border-slate-200/80 bg-slate-100 max-h-72">
                        <img
                            :src="event.banner_url"
                            :alt="event.name"
                            width="1280"
                            height="720"
                            fetchpriority="high"
                            decoding="async"
                            class="w-full h-72 object-cover"
                        />
                    </div>

                    <div class="frosted-card p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-700">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-900 text-white">
                                    {{ event.age_category }}
                                </span>
                                <span class="px-2.5 py-1 rounded-lg bg-orange-50 text-orange-900 border border-orange-200">
                                    <template v-if="event.event_type === 'coaching_clinic'">Coaching Clinic &middot; Individu</template>
                                    <template v-else-if="event.event_type === 'audition'">Audisi &amp; Seleksi &middot; Individu</template>
                                    <template v-else-if="isIndividual">{{ event.format_label }} &middot; Individu</template>
                                    <template v-else>Turnamen Tim &middot; {{ event.format_label }}</template>
                                </span>
                            </div>

                            <StatusBadge :status="event.status" />
                        </div>

                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ event.name }}</h1>
                        <div v-if="event.short_description" class="mt-2.5 text-sm text-slate-700 leading-relaxed prose prose-sm max-w-none break-words" v-html="formattedDescription" />

                        <div class="mt-5 pt-4 border-t border-slate-200/80 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                            <div>
                                <span class="block text-slate-600">Stadion / Venue</span>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                    <span class="font-semibold text-slate-900">{{ event.venue_name }}</span>
                                    <a
                                        v-if="event.venue_maps_url"
                                        :href="event.venue_maps_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 min-h-[36px] sm:min-h-0 px-2 py-1 rounded-lg text-[11px] font-semibold bg-orange-50 text-orange-800 hover:bg-orange-100 border border-orange-200 transition-colors"
                                        title="Buka Lokasi di Google Maps"
                                    >
                                        <svg class="w-3 h-3 text-orange-600 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span>Lihat Peta</span>
                                    </a>
                                </div>
                            </div>
                            <div>
                                <span class="block text-slate-600">Kota</span>
                                <span class="font-semibold text-slate-900 mt-0.5 block">{{ event.city }}</span>
                            </div>
                            <div>
                                <span class="block text-slate-600">{{ isIndividual ? 'Jadwal Pelaksanaan' : 'Kick-Off Kompetisi' }}</span>
                                <span class="font-semibold text-slate-900 tabular-data mt-0.5 block">
                                    <template v-if="event.match_end_date && event.match_end_date !== event.match_start_date">
                                        {{ formatDate(event.match_start_date) }} &ndash; {{ formatDate(event.match_end_date) }}
                                    </template>
                                    <template v-else>
                                        {{ formatDate(event.match_start_date) }}
                                    </template>
                                </span>
                            </div>
                            <div>
                                <span class="block text-slate-600">{{ isIndividual ? 'Batas Kelahiran Peserta' : 'Batas Kelahiran Pemain' }}</span>
                                <span class="font-semibold text-slate-900 tabular-data mt-0.5 block">
                                    {{ formatBirthDateRange(event.min_birth_date, event.max_birth_date) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Information Tabs -->
                    <div class="frosted-card overflow-hidden">
                        <div role="tablist" aria-label="Informasi Detail Event" class="border-b border-slate-200 bg-slate-100/70 px-4 flex items-center gap-1 overflow-x-auto">
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="activeTab === 'summary'"
                                class="px-4 py-3 min-h-[44px] sm:min-h-0 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                :class="activeTab === 'summary' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                @click="activeTab = 'summary'"
                            >
                                {{ isIndividual ? 'Ringkasan & Syarat Peserta' : 'Ringkasan & Syarat Pemain' }}
                            </button>

                            <!-- Individual Tabs -->
                            <template v-if="isIndividual">
                                <button
                                    v-if="stages.length > 0"
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeTab === 'stages'"
                                    class="px-4 py-3 min-h-[44px] sm:min-h-0 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                    :class="activeTab === 'stages' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                    @click="activeTab = 'stages'"
                                >
                                    Tahapan Seleksi ({{ stages.length }})
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeTab === 'participants'"
                                    class="px-4 py-3 min-h-[44px] sm:min-h-0 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                    :class="activeTab === 'participants' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                    @click="activeTab = 'participants'"
                                >
                                    Peserta Terverifikasi ({{ registeredParticipants.length }})
                                </button>
                            </template>

                            <!-- Team Tournament Tabs -->
                            <template v-else>
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeTab === 'teams'"
                                    class="px-4 py-3 min-h-[44px] sm:min-h-0 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                    :class="activeTab === 'teams' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                    @click="activeTab = 'teams'"
                                >
                                    Tim Lunas / Terverifikasi ({{ registeredTeams.length }})
                                </button>
                                <button
                                    v-if="groups.length > 0"
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeTab === 'groups'"
                                    class="px-4 py-3 min-h-[44px] sm:min-h-0 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                    :class="activeTab === 'groups' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                    @click="activeTab = 'groups'"
                                >
                                    Grup &amp; Klasemen ({{ groups.length }})
                                </button>
                                <button
                                    v-if="matches.length > 0"
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeTab === 'matches'"
                                    class="px-4 py-3 min-h-[44px] sm:min-h-0 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                    :class="activeTab === 'matches' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                    @click="activeTab = 'matches'"
                                >
                                    Jadwal &amp; Hasil ({{ matches.length }})
                                </button>
                                <button
                                    v-if="leaderboard.top_scorers && leaderboard.top_scorers.length > 0"
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeTab === 'leaderboard'"
                                    class="px-4 py-3 min-h-[44px] sm:min-h-0 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                    :class="activeTab === 'leaderboard' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                    @click="activeTab = 'leaderboard'"
                                >
                                    Top Scorer
                                </button>
                            </template>

                            <button
                                type="button"
                                role="tab"
                                :aria-selected="activeTab === 'contact'"
                                class="px-4 py-3 min-h-[44px] sm:min-h-0 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                :class="activeTab === 'contact' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                @click="activeTab = 'contact'"
                            >
                                Regulasi &amp; Kontak Panitia
                            </button>
                        </div>

                        <div class="p-6">
                            <!-- Tab: Summary -->
                            <div v-if="activeTab === 'summary'" class="space-y-5 text-sm">
                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 mb-2">
                                        {{ isIndividual ? 'Ketentuan Peserta & Kelayakan Usia' : 'Aturan Kuota Komposisi Tim & Kelayakan Usia' }}
                                    </h2>

                                    <!-- Individual summary cards -->
                                    <div v-if="isIndividual" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Rentang Tanggal Lahir Sah</span>
                                            <strong class="text-slate-900 tabular-data mt-0.5 block">
                                                {{ formatBirthDateRange(event.min_birth_date, event.max_birth_date) }}
                                            </strong>
                                            <span class="text-[11px] text-slate-500 mt-1 block">Kategori Usia {{ event.age_category }} (Wajib membawa identitas sah)</span>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Model Partisipasi</span>
                                            <strong class="text-slate-900 mt-0.5 block">
                                                Individu / Perorangan
                                            </strong>
                                            <span class="text-[11px] text-slate-500 mt-1 block">Pendaftaran mandiri oleh atlet atau orang tua / wali</span>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">
                                                {{ event.event_type === 'coaching_clinic' ? 'Format &amp; Metode Pelatihan' : 'Format &amp; Metode Seleksi' }}
                                            </span>
                                            <strong class="text-slate-900 mt-0.5 block">
                                                {{ event.event_type === 'coaching_clinic' ? 'Workshop &amp; Masterclass Terarah' : (event.format_label || 'Audisi & Scouting Panel') }}
                                            </strong>
                                            <span class="text-[11px] text-slate-500 mt-1 block">
                                                {{ event.event_type === 'coaching_clinic' ? 'Pelatihan intensif bersama staf pelatih berlisensi dan pemain pro.' : 'Detail pelaksanaan mengikuti tahapan yang diumumkan panitia.' }}
                                            </span>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">
                                                {{ event.event_type === 'coaching_clinic' ? 'Benefit &amp; Fasilitas Peserta' : 'Hadiah &amp; Beasiswa Program' }}
                                            </span>
                                            <strong class="text-slate-900 mt-0.5 block">
                                                {{ event.prize_info || 'Belum ditentukan oleh panitia' }}
                                            </strong>
                                        </div>
                                    </div>

                                    <!-- Team summary cards -->
                                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Rentang Tanggal Lahir Sah</span>
                                            <strong class="text-slate-900 tabular-data mt-0.5 block">
                                                {{ formatBirthDateRange(event.min_birth_date, event.max_birth_date) }}
                                            </strong>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Kuota Skuad Pemain per Tim</span>
                                            <strong class="text-slate-900 tabular-data mt-0.5 block">
                                                Minimal {{ event.min_players_per_team }} &ndash; Maksimal {{ event.max_players_per_team }} Pemain (Min. {{ event.min_gk_per_team || 2 }} GK)
                                            </strong>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Kuota Ofisial &amp; Pelatih</span>
                                            <strong class="text-slate-900 tabular-data mt-0.5 block">
                                                Maksimal {{ event.max_officials_per_team }} Ofisial
                                                <span v-if="event.show_coach_license !== false">
                                                    ({{ event.require_coach_license ? 'Lisensi Pelatih Wajib' : 'Lisensi Pelatih Opsional' }})
                                                </span>
                                            </strong>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Hadiah &amp; Penghargaan</span>
                                            <strong class="text-slate-900 mt-0.5 block">
                                                {{ event.prize_info || 'Trofi Kejuaraan & Medali Resmi' }}
                                            </strong>
                                        </div>
                                    </div>

                                    <!-- Position caps if available -->
                                    <div v-if="isIndividual && hasPositionCaps" class="mt-4 pt-3 border-t border-slate-200/80">
                                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Alokasi Batas Kuota per Posisi</h3>
                                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
                                            <div
                                                v-for="(cap, pos) in activePositionCaps"
                                                :key="pos"
                                                class="p-2.5 rounded-xl bg-orange-50/60 border border-orange-200/70 text-center"
                                            >
                                                <span class="font-bold text-orange-950 font-mono text-sm block">{{ pos }}</span>
                                                <span class="text-[11px] text-slate-600 mt-0.5 block font-medium">Maks. {{ cap }} Atlet</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h2 class="text-sm font-bold text-slate-900 mb-2">
                                        {{ isIndividual ? 'Dokumen Screening Wajib Peserta' : 'Dokumen Screening Wajib per Pemain' }}
                                    </h2>
                                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                        <li class="flex items-center gap-2 p-2.5 rounded-xl bg-orange-50/70 border border-orange-200/80 text-orange-950 font-medium">
                                            <span>1. Pas Foto Resmi Terbaru (JPG/PNG/WebP)</span>
                                        </li>
                                        <li
                                            v-for="(doc, idx) in event.required_documents"
                                            :key="doc.type"
                                            class="flex items-center gap-2 p-2.5 rounded-xl bg-orange-50/70 border border-orange-200/80 text-orange-950 font-medium"
                                        >
                                            <span>{{ idx + 2 }}. {{ doc.label }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Tab: Stages (Tahapan Seleksi untuk Audisi/Individu) -->
                            <div v-else-if="activeTab === 'stages'" class="space-y-4">
                                <div v-if="stages.length === 0" class="text-center py-10 text-xs text-slate-500 space-y-1">
                                    <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="font-bold text-slate-800 text-sm">Tahapan Seleksi Belum Diterbitkan</p>
                                    <p class="text-slate-600">Jadwal tahapan seleksi akan diumumkan secara berkala oleh tim pemandu bakat / scouting.</p>
                                </div>
                                <div v-else class="space-y-4">
                                    <div class="flex items-center justify-between text-xs text-slate-600 pb-1">
                                        <span>Rangkaian alur seleksi bertahap ({{ stages.length }} Tahap Resmi):</span>
                                    </div>
                                    <div class="relative border-l-2 border-orange-200 ml-4 pl-6 space-y-6">
                                        <div
                                            v-for="stage in stages"
                                            :key="stage.id"
                                            class="relative group"
                                        >
                                            <!-- Stage indicator -->
                                            <div
                                                class="absolute -left-[35px] top-1 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold tabular-nums"
                                                :class="stage.is_completed ? 'bg-emerald-600 text-white' : (stage.is_active ? 'bg-orange-600 text-white ring-4 ring-orange-100' : 'bg-slate-200 text-slate-800')"
                                            >
                                                {{ stage.stage_number }}
                                            </div>

                                            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs hover:border-orange-300 transition-colors">
                                                <div class="flex flex-wrap items-center justify-between gap-2 pb-2 border-b border-slate-100">
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2 py-0.5 rounded font-bold font-mono text-[11px] bg-slate-900 text-white">
                                                            TAHAP {{ stage.stage_number }}
                                                        </span>
                                                        <span
                                                            v-if="stage.is_completed"
                                                            class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                                                        >
                                                            Selesai
                                                        </span>
                                                        <span
                                                            v-else-if="stage.is_active"
                                                            class="px-2 py-0.5 rounded text-[11px] font-bold bg-orange-50 text-orange-700 border border-orange-200"
                                                        >
                                                            Sedang Berjalan
                                                        </span>
                                                        <span
                                                            v-else
                                                            class="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200"
                                                        >
                                                            Terjadwal
                                                        </span>
                                                    </div>
                                                    <span v-if="stage.target_quota" class="text-xs font-semibold text-orange-900 bg-orange-50/80 px-2 py-0.5 rounded border border-orange-200/60 font-mono">
                                                        Target Kuota: {{ stage.target_quota }} Atlet
                                                    </span>
                                                </div>

                                                <h2 class="text-sm font-bold text-slate-900 mt-2.5">{{ stage.stage_name }}</h2>

                                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-700">
                                                    <div class="flex items-center gap-2">
                                                        <svg class="w-4 h-4 text-slate-500 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span class="tabular-data font-semibold text-slate-900">{{ stage.stage_date_formatted || formatDate(stage.stage_date) }}</span>
                                                        <span v-if="stage.start_time" class="tabular-nums text-slate-600">({{ stage.start_time }} - {{ stage.end_time || 'Selesai' }} WIB)</span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <svg class="w-4 h-4 text-slate-500 shrink-0" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        <span class="truncate">{{ stage.venue_name }}</span>
                                                    </div>
                                                </div>

                                                <div v-if="stage.instructions" class="mt-3 pt-2.5 border-t border-slate-100 text-xs text-slate-700 leading-relaxed bg-slate-50/70 p-3 rounded-lg">
                                                    <p class="font-semibold text-slate-900 mb-1">Materi &amp; Tata Tertib Tahapan:</p>
                                                    <p class="whitespace-pre-line">{{ stage.instructions }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab: Participants (Peserta Terverifikasi untuk Audisi/Individu) -->
                            <div v-else-if="activeTab === 'participants'">
                                <div v-if="registeredParticipants.length === 0" class="text-center py-10 text-xs text-slate-600 space-y-1">
                                    <svg class="w-10 h-10 mx-auto text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <p class="font-bold text-slate-900 text-sm">Belum Ada Peserta Terverifikasi</p>
                                    <p class="text-slate-600">Peserta dengan pembayaran terkonfirmasi dan screening berkas sah akan tampil di sini.</p>
                                </div>
                                <div v-else>
                                    <div class="mb-3 flex items-center justify-between text-xs text-slate-600">
                                        <span>Menampilkan {{ registeredParticipants.length }} peserta terverifikasi dengan Digital Pass resmi.</span>
                                    </div>

                                    <!-- DESKTOP TABLE -->
                                    <div class="hidden md:block overflow-x-auto">
                                        <table class="w-full text-left border-collapse">
                                            <thead>
                                                <tr>
                                                    <th class="table-th">ID Digital Pass</th>
                                                    <th class="table-th">Peserta</th>
                                                    <th class="table-th">Posisi Utama</th>
                                                    <th class="table-th">Asal Klub / SSB</th>
                                                    <th class="table-th text-right">Status Seleksi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 bg-white">
                                                <tr
                                                    v-for="p in registeredParticipants"
                                                    :key="p.id"
                                                    class="hover:bg-orange-50/25 transition-colors"
                                                >
                                                    <td class="table-td">
                                                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-lg font-mono font-black text-xs bg-slate-900 text-white whitespace-nowrap">
                                                            {{ p.registration_number || ('PASS-' + p.id) }}
                                                        </span>
                                                    </td>
                                                    <td class="table-td font-semibold text-slate-900">
                                                        <div class="flex items-center gap-2.5">
                                                            <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs shrink-0">#</div>
                                                            <div>
                                                                <span class="block leading-tight font-bold text-slate-900">{{ p.public_label }}</span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="table-td text-xs text-slate-700">
                                                        <span class="font-medium">{{ p.preferred_position || '-' }}</span>
                                                        <span v-if="p.dominant_foot" class="text-slate-600 block text-[11px]">
                                                            Kaki: {{ p.dominant_foot === 'right' ? 'Kanan' : (p.dominant_foot === 'left' ? 'Kiri' : 'Keduanya') }}
                                                        </span>
                                                    </td>
                                                    <td class="table-td text-xs text-slate-600">Informasi peserta disimpan privat</td>
                                                    <td class="table-td text-right">
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-xs font-semibold"
                                                            :class="{
                                                                'bg-emerald-50 text-emerald-800 border border-emerald-200': p.stage_status === 'day_1_passed' || p.stage_status === 'day_2_passed' || p.stage_status === 'final_selected',
                                                                'bg-rose-50 text-rose-800 border border-rose-200': p.stage_status === 'day_1_eliminated' || p.stage_status === 'day_2_eliminated',
                                                                'bg-slate-100 text-slate-800 border border-slate-200': p.stage_status === 'registered' || !p.stage_status || p.stage_status === 'stage_1',
                                                            }"
                                                        >
                                                            {{ p.stage_status_label || 'Terdaftar' }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- MOBILE HIGH-DENSITY CARDS -->
                                    <div class="md:hidden divide-y divide-slate-100">
                                        <div
                                            v-for="p in registeredParticipants"
                                            :key="p.id"
                                            class="py-3 flex items-center justify-between gap-2.5"
                                        >
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <span class="font-mono text-xs font-black text-white bg-slate-900 px-1.5 py-0.5 rounded-lg shrink-0">
                                                    {{ p.registration_number || ('PASS-' + p.id) }}
                                                </span>
                                                <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs shrink-0">#</div>
                                                <div class="min-w-0">
                                                    <h3 class="text-xs font-bold text-slate-900 leading-tight truncate">{{ p.public_label }}</h3>
                                                    <p class="text-[11px] text-slate-600 truncate">{{ p.preferred_position || '-' }}</p>
                                                </div>
                                            </div>
                                            <div class="shrink-0">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-semibold"
                                                    :class="{
                                                        'bg-emerald-50 text-emerald-800 border border-emerald-200': p.stage_status === 'day_1_passed' || p.stage_status === 'day_2_passed' || p.stage_status === 'final_selected',
                                                        'bg-rose-50 text-rose-800 border border-rose-200': p.stage_status === 'day_1_eliminated' || p.stage_status === 'day_2_eliminated',
                                                        'bg-slate-100 text-slate-800 border border-slate-200': p.stage_status === 'registered' || !p.stage_status || p.stage_status === 'stage_1',
                                                    }"
                                                >
                                                    {{ p.stage_status_label || 'Terdaftar' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab: Teams (Desktop Table + Mobile Cards) -->
                            <div v-else-if="activeTab === 'teams'">
                                <div v-if="registeredTeams.length === 0" class="text-center py-10 text-xs text-slate-600 space-y-1">
                                    <svg class="w-10 h-10 mx-auto text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="font-bold text-slate-900 text-sm">Belum Ada Tim Terverifikasi</p>
                                    <p class="text-slate-600">Hanya tim yang telah menyelesaikan pelunasan dan verifikasi yang ditampilkan di sini.</p>
                                </div>
                                <div v-else>
                                    <div class="mb-3 flex items-center justify-between text-xs text-slate-500">
                                        <span>Menampilkan {{ registeredTeams.length }} tim dengan status pembayaran lunas sah.</span>
                                    </div>
                                    <!-- DESKTOP TABLE -->
                                    <div class="hidden md:block overflow-x-auto">
                                        <table class="w-full text-left border-collapse">
                                            <thead>
                                                <tr>
                                                    <th class="table-th w-12">#</th>
                                                    <th class="table-th">Nama Tim</th>
                                                    <th class="table-th">Kota / Askot / Askab</th>
                                                    <th class="table-th text-right">Status Slot</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 bg-white">
                                                <tr
                                                    v-for="(t, index) in registeredTeams"
                                                    :key="t.id"
                                                    class="hover:bg-orange-50/25 transition-colors"
                                                >
                                                    <td class="table-td text-xs tabular-data text-slate-500 font-mono">{{ index + 1 }}</td>
                                                    <td class="table-td font-semibold text-slate-900">
                                                        <div class="flex items-center gap-2.5">
                                                            <img
                                                                v-if="t.logo_url"
                                                                :src="t.logo_url"
                                                                :alt="t.name"
                                                                class="w-7 h-7 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                                                            />
                                                            <div v-else class="w-7 h-7 rounded-lg bg-orange-100 text-orange-800 flex items-center justify-center font-bold text-xs shrink-0">
                                                                {{ t.name.charAt(0) }}
                                                            </div>
                                                            <span>{{ t.name }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="table-td text-xs text-slate-600">{{ t.origin_association }}</td>
                                                    <td class="table-td text-right">
                                                        <StatusBadge :status="t.status" />
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- MOBILE HIGH-DENSITY CARDS -->
                                    <div class="md:hidden divide-y divide-slate-100">
                                        <div
                                            v-for="(t, index) in registeredTeams"
                                            :key="t.id"
                                            class="py-3 flex items-center justify-between gap-2.5"
                                        >
                                            <div class="flex items-center gap-2.5">
                                                <span class="tabular-nums text-xs font-bold text-slate-600 w-5 text-center">{{ index + 1 }}</span>
                                                <img
                                                    v-if="t.logo_url"
                                                    :src="t.logo_url"
                                                    :alt="t.name"
                                                    width="32"
                                                    height="32"
                                                    loading="lazy"
                                                    class="w-8 h-8 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                                                />
                                                <div v-else class="w-8 h-8 rounded-lg bg-orange-100 text-orange-800 flex items-center justify-center font-bold text-xs shrink-0">
                                                    {{ t.name.charAt(0) }}
                                                </div>
                                                <div>
                                                    <h3 class="text-xs font-bold text-slate-900 leading-tight">{{ t.name }}</h3>
                                                    <p class="text-[11px] text-slate-600">{{ t.origin_association }}</p>
                                                </div>
                                            </div>
                                            <StatusBadge :status="t.status" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab: Groups & Standings -->
                            <div v-else-if="activeTab === 'groups'" class="space-y-6">
                                <div v-if="groups.length === 0" class="text-center py-8 text-xs text-slate-600">
                                    Grup turnamen belum dibentuk oleh panitia.
                                </div>
                                <div v-else class="grid grid-cols-1 gap-6">
                                    <div
                                        v-for="g in groups"
                                        :key="g.id"
                                        class="rounded-xl border border-slate-200 overflow-hidden"
                                    >
                                        <div class="px-4 py-2.5 bg-slate-900 text-white flex items-center justify-between text-xs">
                                            <span class="font-bold text-orange-400 tracking-wider">{{ g.name }}</span>
                                            <span class="text-slate-300 tabular-nums">{{ g.standings.length }} Tim</span>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="w-full text-left text-xs border-collapse">
                                                <thead>
                                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                                        <th class="py-2 px-3 w-8 text-center">#</th>
                                                        <th class="py-2 px-3">Klub / Tim</th>
                                                        <th class="py-2 px-2 text-center">M</th>
                                                        <th class="py-2 px-2 text-center">Mn</th>
                                                        <th class="py-2 px-2 text-center">S</th>
                                                        <th class="py-2 px-2 text-center">K</th>
                                                        <th class="py-2 px-2 text-center">GM</th>
                                                        <th class="py-2 px-2 text-center">GK</th>
                                                        <th class="py-2 px-2 text-center">SG</th>
                                                        <th class="py-2 px-3 text-center font-black text-orange-950 bg-orange-50/60">PTS</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-slate-100 bg-white">
                                                    <tr
                                                        v-for="(st, sIdx) in g.standings"
                                                        :key="st.team_id"
                                                        class="hover:bg-orange-50/20"
                                                        :class="{ 'bg-emerald-50/30 font-medium': sIdx < 2 }"
                                                    >
                                                        <td class="py-2 px-3 text-center font-mono font-bold">{{ sIdx + 1 }}</td>
                                                        <td class="py-2 px-3 font-semibold text-slate-900 whitespace-nowrap">{{ st.team_name }}</td>
                                                        <td class="py-2 px-2 text-center tabular-data">{{ st.played }}</td>
                                                        <td class="py-2 px-2 text-center tabular-data text-emerald-700 font-semibold">{{ st.won }}</td>
                                                        <td class="py-2 px-2 text-center tabular-data text-slate-500">{{ st.drawn }}</td>
                                                        <td class="py-2 px-2 text-center tabular-data text-rose-700 font-semibold">{{ st.lost }}</td>
                                                        <td class="py-2 px-2 text-center tabular-data">{{ st.goals_for }}</td>
                                                        <td class="py-2 px-2 text-center tabular-data">{{ st.goals_against }}</td>
                                                        <td class="py-2 px-2 text-center tabular-data font-semibold">
                                                            {{ st.goal_difference > 0 ? `+${st.goal_difference}` : st.goal_difference }}
                                                        </td>
                                                        <td class="py-2 px-3 text-center tabular-data font-black text-orange-900 bg-orange-50/60 text-sm">
                                                            {{ st.points }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab: Matches Fixtures & Results -->
                            <div v-else-if="activeTab === 'matches'" class="space-y-4">
                                <div v-if="matches.length === 0" class="text-center py-8 text-xs text-slate-500">
                                    Jadwal pertandingan belum diterbitkan.
                                </div>
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="m in matches"
                                        :key="m.id"
                                        class="p-4 rounded-xl border border-slate-200 bg-white hover:border-orange-300 transition-colors"
                                    >
                                        <div class="flex items-center justify-between pb-2 border-b border-slate-100 text-xs">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded-lg font-black bg-slate-900 text-white font-mono text-[10px]">#{{ m.match_number }}</span>
                                                <span class="font-bold text-orange-950 bg-orange-50 px-2 py-0.5 rounded-lg text-[11px]">{{ m.stage_label }}</span>
                                                <span v-if="m.group_name" class="text-slate-600 font-medium">({{ m.group_name }})</span>
                                            </div>
                                            <span class="text-slate-600 text-[11px] tabular-nums">{{ m.kickoff_formatted }} &bull; {{ m.pitch_name }}</span>
                                        </div>
                                        <div class="py-3 grid grid-cols-7 items-center gap-2 text-xs">
                                            <div class="col-span-3 text-right font-bold text-slate-900 truncate">
                                                {{ m.home_team_name }}
                                            </div>
                                            <div class="col-span-1 text-center">
                                                <span v-if="m.status === 'completed' || m.status === 'ongoing'" class="text-base font-black text-slate-900 tabular-data">
                                                    {{ m.home_score ?? 0 }} - {{ m.away_score ?? 0 }}
                                                </span>
                                                <span v-else class="text-[11px] font-bold text-slate-600">VS</span>
                                            </div>
                                            <div class="col-span-3 text-left font-bold text-slate-900 truncate">
                                                {{ m.away_team_name }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tab: Top Scorer Leaderboard -->
                            <div v-else-if="activeTab === 'leaderboard'" class="space-y-4">
                                <div class="rounded-xl border border-slate-200 overflow-hidden">
                                    <div class="px-4 py-2.5 bg-slate-900 text-white flex items-center justify-between text-xs">
                                        <span class="font-bold text-orange-400">Peringkat Top Scorer Resmi</span>
                                        <span class="text-slate-300 font-semibold">Golden Boot</span>
                                    </div>
                                    <table class="w-full text-left text-xs border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                                <th class="py-2.5 px-3 w-10 text-center">#</th>
                                                <th class="py-2.5 px-3">Nama Pemain</th>
                                                <th class="py-2.5 px-3">Klub / Tim</th>
                                                <th class="py-2.5 px-3 text-center font-black text-orange-950 bg-orange-50/60">GOL</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            <tr
                                                v-for="(p, idx) in publicTopScorers"
                                                :key="p.player_id"
                                                class="hover:bg-orange-50/20"
                                            >
                                                <td class="py-2.5 px-3 text-center font-bold tabular-nums">{{ idx + 1 }}</td>
                                                <td class="py-2.5 px-3 font-bold text-slate-900">
                                                    {{ p.player_name }}
                                                    <span v-if="p.jersey_number" class="text-slate-600 font-mono text-[10px] ml-1">#{{ p.jersey_number }}</span>
                                                </td>
                                                <td class="py-2.5 px-3 text-slate-600">{{ p.team_name }}</td>
                                                <td class="py-2.5 px-3 text-center tabular-data font-black text-orange-900 bg-orange-50/60 text-sm">
                                                    {{ p.total_goals }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Tab: Contact & Regulation -->
                            <div v-else class="space-y-4 text-xs text-slate-700">
                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-slate-900 text-sm">Dokumen Regulasi Teknis &amp; Laws of the Game</p>
                                        <p class="text-slate-500 mt-0.5">Unduh pedoman teknis resmi turnamen dan tata tertib screening pemain.</p>
                                    </div>
                                    <a
                                        v-if="event.regulation_pdf_url"
                                        :href="event.regulation_pdf_url"
                                        target="_blank"
                                        class="btn-secondary text-xs shrink-0"
                                    >
                                        Unduh PDF Regulasi
                                    </a>
                                    <span v-else class="text-slate-500 font-medium">
                                        {{ isIndividual ? 'Regulasi teknis seleksi dibagikan saat registrasi ulang' : 'Regulasi dibagikan di Portal Manajer' }}
                                    </span>
                                </div>

                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                                    <p class="font-bold text-slate-900 text-sm">Sekretariat &amp; Kontak Panitia</p>
                                    <p>Penanggung Jawab: <strong>{{ event.contact_person || (isIndividual ? 'Panitia Pelaksana Audisi' : 'Sekretariat Kompetisi') }}</strong></p>
                                    <p v-if="event.contact_whatsapp">
                                        WhatsApp Resmi: <strong class="tabular-data">{{ event.contact_whatsapp }}</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Registration & Quota Card -->
                <div class="space-y-4">
                    <div class="frosted-card p-6 sticky top-24">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                            {{ isIndividual ? 'Biaya Pendaftaran Peserta' : 'Biaya Pendaftaran Tim' }}
                        </p>
                        <div class="flex items-baseline gap-2 mt-1">
                            <p class="text-2xl font-black text-slate-900 tabular-data tracking-tight">
                                {{ formatRupiah(event.registration_fee) }}
                            </p>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                                {{ isIndividual ? 'Per Orang / Atlet' : 'Per Tim' }}
                            </span>
                        </div>

                        <div v-if="event.is_unlimited_quota" class="mt-4 p-3.5 rounded-xl bg-emerald-50/80 border border-emerald-200/90 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-emerald-800">Model Pendaftaran</span>
                                <span class="font-bold text-emerald-700 bg-emerald-100/70 px-2 py-0.5 rounded text-[11px] border border-emerald-200">
                                    Terbuka s.d. Batas Waktu
                                </span>
                            </div>
                            <p class="text-[11px] text-emerald-700 leading-relaxed">
                                Pendaftaran dibuka bebas untuk semua peserta hingga tanggal penutupan resmi. Total <strong class="text-emerald-950 font-bold tabular-data">{{ event.occupied_slots }} {{ participantUnit.toLowerCase() }}</strong> telah mendaftar.
                            </p>
                        </div>
                        <div v-else class="mt-4 p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/90 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-700">Kapasitas Kuota Maksimal</span>
                                <span class="font-bold tabular-data" :class="event.available_slots === 0 ? 'text-rose-700' : 'text-orange-800'">
                                    {{ event.occupied_slots }} / {{ totalCapacity }} {{ participantUnit }}
                                </span>
                            </div>
                            <div class="w-full h-2.5 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="event.available_slots === 0 ? 'bg-rose-600' : 'bg-orange-600'"
                                    :style="{ width: `${quotaPercentage}%` }"
                                />
                            </div>
                            <p class="text-[11px] text-slate-600 leading-relaxed">
                                Sisa slot tersedia: <strong class="text-slate-900 tabular-data">{{ event.available_slots }} slot {{ participantUnit.toLowerCase() }}</strong>.
                                Setiap pendaftar langsung menahan slot sementara selama
                                <strong class="tabular-data">{{ event.payment_deadline_hours }} jam</strong> untuk menyelesaikan pembayaran.
                            </p>
                        </div>

                        <dl class="mt-4 space-y-2 text-xs text-slate-600 border-t border-slate-200/80 pt-3">
                            <div class="flex justify-between">
                                <dt>Buka Pendaftaran</dt>
                                <dd class="font-medium text-slate-900 tabular-data">{{ formatDateTime(event.registration_open_at) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Tutup Pendaftaran</dt>
                                <dd class="font-medium text-slate-900 tabular-data">{{ formatDateTime(event.registration_close_at) }}</dd>
                            </div>
                        </dl>

                        <div class="mt-5">
                            <Link
                                v-if="event.can_register"
                                :href="isIndividual ? route('events.register-audition', { event: event.slug }) : route('events.register', { event: event.slug })"
                                class="btn-pitch w-full py-2.5 flex items-center justify-center gap-2"
                            >
                                <span>{{ isIndividual ? 'Daftar Audisi Sekarang' : 'Daftar Tim Sekarang' }}</span>
                            </Link>
                            <button
                                v-else
                                type="button"
                                disabled
                                class="w-full py-2.5 px-4 rounded-lg text-sm font-semibold bg-rose-50 text-rose-800 border border-rose-200 cursor-not-allowed"
                            >
                                {{ event.available_slots === 0 ? 'Kuota Pendaftaran Penuh' : 'Pendaftaran Ditutup' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
