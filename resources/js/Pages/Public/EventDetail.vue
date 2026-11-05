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
});

const { formatRupiah, formatDate, formatDateTime } = useFormatters();
const activeTab = ref('summary');

const quotaPercentage = computed(() => {
    if (!props.event.max_teams) return 0;
    return Math.min(100, Math.round((props.event.occupied_slots / props.event.max_teams) * 100));
});
</script>

<template>
    <PublicLayout>
        <Head :title="event.name" />

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-4">
                <Link :href="route('home')" class="text-xs font-semibold text-slate-600 hover:text-orange-600 transition-colors">
                    &larr; Kembali ke Katalog Kompetisi
                </Link>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Event Hero Banner -->
                    <div v-if="event.banner_url" class="rounded-2xl overflow-hidden border border-slate-200/80 shadow-sm bg-slate-100 max-h-72">
                        <img :src="event.banner_url" :alt="event.name" class="w-full h-72 object-cover" />
                    </div>

                    <div class="frosted-card p-6">
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <span class="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-900 text-white">
                                {{ event.age_category }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                {{ event.format_label }}
                            </span>
                            <StatusBadge :status="event.status" />
                        </div>

                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ event.name }}</h1>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ event.short_description }}</p>

                        <div class="mt-5 pt-4 border-t border-slate-200/80 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                            <div>
                                <span class="block text-slate-500">Stadion / Venue</span>
                                <span class="font-semibold text-slate-900 mt-0.5 block">{{ event.venue_name }}</span>
                            </div>
                            <div>
                                <span class="block text-slate-500">Kota</span>
                                <span class="font-semibold text-slate-900 mt-0.5 block">{{ event.city }}</span>
                            </div>
                            <div>
                                <span class="block text-slate-500">Kick-Off Kompetisi</span>
                                <span class="font-semibold text-slate-900 tabular-data mt-0.5 block">{{ formatDate(event.match_start_date) }}</span>
                            </div>
                            <div>
                                <span class="block text-slate-500">Batas Kelahiran Pemain</span>
                                <span class="font-semibold text-slate-900 tabular-data mt-0.5 block">
                                    {{ formatDate(event.min_birth_date) }} &ndash; {{ formatDate(event.max_birth_date) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Information Tabs -->
                    <div class="frosted-card overflow-hidden">
                        <div class="border-b border-slate-200 bg-slate-100/70 px-4 flex items-center gap-1 overflow-x-auto">
                            <button
                                type="button"
                                class="px-4 py-3 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                :class="activeTab === 'summary' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                @click="activeTab = 'summary'"
                            >
                                Ringkasan &amp; Syarat Pemain
                            </button>
                            <button
                                type="button"
                                class="px-4 py-3 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
                                :class="activeTab === 'teams' ? 'border-orange-600 text-orange-900' : 'border-transparent text-slate-600 hover:text-slate-900'"
                                @click="activeTab = 'teams'"
                            >
                                Tim Lunas / Terverifikasi ({{ registeredTeams.length }})
                            </button>
                            <button
                                type="button"
                                class="px-4 py-3 text-xs font-semibold border-b-2 transition-colors whitespace-nowrap"
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
                                    <h3 class="text-sm font-bold text-slate-900 mb-2">Aturan Kuota Komposisi Tim &amp; Kelayakan Usia</h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Rentang Tanggal Lahir Sah</span>
                                            <strong class="text-slate-900 tabular-data mt-0.5 block">
                                                {{ formatDate(event.min_birth_date) }} s/d {{ formatDate(event.max_birth_date) }}
                                            </strong>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Kuota Skuad Pemain per Tim</span>
                                            <strong class="text-slate-900 tabular-data mt-0.5 block">
                                                Minimal {{ event.min_players_per_team }} &ndash; Maksimal {{ event.max_players_per_team }} Pemain
                                            </strong>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Kuota Ofisial &amp; Pelatih</span>
                                            <strong class="text-slate-900 tabular-data mt-0.5 block">
                                                Maksimal {{ event.max_officials_per_team }} Ofisial
                                            </strong>
                                        </div>
                                        <div class="p-3 rounded-lg bg-slate-50 border border-slate-200">
                                            <span class="text-slate-500 block">Hadiah &amp; Penghargaan</span>
                                            <strong class="text-slate-900 mt-0.5 block">
                                                {{ event.prize_info || 'Trofi Kejuaraan & Medali Resmi' }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 mb-2">Dokumen Screening Wajib per Pemain</h3>
                                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                        <li class="flex items-center gap-2 p-2.5 rounded-xl bg-orange-50/70 border border-orange-200/80 text-orange-950 font-medium">
                                            <span>1. Pas Foto Resmi Terbaru (JPG/PNG)</span>
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

                            <!-- Tab: Teams (Desktop Table + Mobile Cards) -->
                            <div v-else-if="activeTab === 'teams'">
                                <div v-if="registeredTeams.length === 0" class="text-center py-10 text-xs text-slate-500 space-y-1">
                                    <svg class="w-10 h-10 mx-auto text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <p class="font-bold text-slate-800 text-sm">Belum Ada Tim Terverifikasi</p>
                                    <p class="text-slate-400">Hanya tim yang telah menyelesaikan pelunasan dan verifikasi yang ditampilkan di sini.</p>
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
                                                <span class="font-mono text-xs font-bold text-slate-400 w-5 text-center">{{ index + 1 }}</span>
                                                <img
                                                    v-if="t.logo_url"
                                                    :src="t.logo_url"
                                                    :alt="t.name"
                                                    class="w-8 h-8 rounded-lg object-cover border border-slate-200 bg-white shrink-0"
                                                />
                                                <div v-else class="w-8 h-8 rounded-lg bg-orange-100 text-orange-800 flex items-center justify-center font-bold text-xs shrink-0">
                                                    {{ t.name.charAt(0) }}
                                                </div>
                                                <div>
                                                    <h5 class="text-xs font-bold text-slate-900 leading-tight">{{ t.name }}</h5>
                                                    <p class="text-[11px] text-slate-500">{{ t.origin_association }}</p>
                                                </div>
                                            </div>
                                            <StatusBadge :status="t.status" />
                                        </div>
                                    </div>
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
                                    <span v-else class="text-slate-500 font-medium">Regulasi dibagikan di Portal Manajer</span>
                                </div>

                                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                                    <p class="font-bold text-slate-900 text-sm">Sekretariat &amp; Kontak Panitia</p>
                                    <p>Penanggung Jawab: <strong>{{ event.contact_person || 'Sekretariat Kompetisi' }}</strong></p>
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
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Biaya Pendaftaran Tim</p>
                        <p class="text-2xl font-black text-slate-900 tabular-data mt-1 tracking-tight">
                            {{ formatRupiah(event.registration_fee) }}
                        </p>

                        <div class="mt-4 p-3.5 rounded-xl bg-slate-50/80 border border-slate-200/90 space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-700">Kapasitas Kuota Maksimal</span>
                                <span class="font-bold tabular-data" :class="event.available_slots === 0 ? 'text-rose-700' : 'text-orange-800'">
                                    {{ event.occupied_slots }} / {{ event.max_teams }} Tim
                                </span>
                            </div>
                            <div class="w-full h-2.5 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="event.available_slots === 0 ? 'bg-rose-600' : 'bg-gradient-to-r from-orange-500 to-red-600'"
                                    :style="{ width: `${quotaPercentage}%` }"
                                />
                            </div>
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                Sisa slot tersedia: <strong class="text-slate-900 tabular-data">{{ event.available_slots }} slot</strong>.
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
                                :href="route('events.register', { event: event.slug })"
                                class="btn-pitch w-full py-2.5"
                            >
                                Daftar Sekarang
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

