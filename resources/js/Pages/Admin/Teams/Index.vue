<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PaymentProofModal from '@/Components/PaymentProofModal.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    teams: {
        type: Array,
        default: () => [],
    },
    eventsFilter: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const { formatRupiah, formatDateTime, formatDate } = useFormatters();

const isPast = (dateStr) => {
    if (!dateStr) return false;
    return new Date(dateStr) < new Date();
};

const isSyncing = ref(false);

const triggerResync = () => {
    isSyncing.value = true;
    const url = filterState.event_id
        ? route('admin.events.resync-event-slots', { event: filterState.event_id })
        : route('admin.events.resync-slots');

    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => {
            isSyncing.value = false;
        },
    });
};

const filterState = reactive({
    event_id: props.filters.event_id || '',
    status: props.filters.status || '',
    screening_status: props.filters.screening_status || '',
    search: props.filters.search || '',
});

// Modal state for payment proof
const selectedPayment = ref(null);
const isProofModalOpen = ref(false);

const openProofModal = (payment) => {
    if (!payment) return;
    selectedPayment.value = payment;
    isProofModalOpen.value = true;
};

const closeProofModal = () => {
    isProofModalOpen.value = false;
    selectedPayment.value = null;
};

const onPaymentVerified = () => {
    applyFilters();
};

const applyFilters = () => {
    router.get(
        route('admin.teams.index'),
        {
            event_id: filterState.event_id || undefined,
            status: filterState.status || undefined,
            screening_status: filterState.screening_status || undefined,
            search: filterState.search || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    filterState.event_id = '';
    filterState.status = '';
    filterState.screening_status = '';
    filterState.search = '';
    router.get(route('admin.teams.index'), {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AdminLayout page-title="Daftar Tim Terdaftar" subtitle="Pantau seluruh tim dari semua kompetisi, status reservasi slot, dan pelunasan invoice.">
        <Head title="Daftar Tim Masuk" />

        <div class="space-y-5">
            <!-- Filter Bar -->
            <form @submit.prevent="applyFilters" novalidate class="frosted-card p-4 sm:p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Pilih Kompetisi</label>
                        <select v-model="filterState.event_id" class="input-field" @change="applyFilters">
                            <option value="">Semua Kompetisi</option>
                            <option v-for="evt in eventsFilter" :key="evt.id" :value="evt.id">
                                {{ evt.name }} ({{ evt.age_category }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Status Pendaftaran</label>
                        <select v-model="filterState.status" class="input-field" @change="applyFilters">
                            <option value="">Semua Status</option>
                            <option value="pending_payment">Menunggu Bayar (Slot Ditahan)</option>
                            <option value="active">Aktif / Lunas</option>
                            <option value="locked_roster">Roster Dikunci (Terakreditasi)</option>
                            <option value="expired">Kedaluwarsa (Slot Dilepas)</option>
                            <option value="disqualified">Didiskualifikasi</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Status Screening Skuad</label>
                        <select v-model="filterState.screening_status" class="input-field" @change="applyFilters">
                            <option value="">Semua Screening</option>
                            <option value="all_passed">Semua Pemain Sah (All Passed)</option>
                            <option value="in_review">Dalam Pemeriksaan (In Review)</option>
                            <option value="has_rejection">Ada Penolakan / Revisi</option>
                            <option value="not_started">Belum Mengajukan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1.5">Cari Nama Tim / Askot / Manajer</label>
                        <input
                            v-model="filterState.search"
                            type="text"
                            placeholder="Ketik nama klub atau manajer..."
                            class="input-field"
                        />
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="btn-pitch flex-1">Filter</button>
                        <button type="button" class="btn-secondary px-3" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </form>

            <!-- Action & Summary Bar with Resync Button -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-3.5 bg-slate-100/80 rounded-xl border border-slate-200">
                <div class="text-xs text-slate-600 font-medium">
                    Menampilkan <strong class="text-slate-900 font-bold tabular-data">{{ teams.length }} tim</strong>
                    <span v-if="filterState.event_id && eventsFilter.find(e => e.id == filterState.event_id)">
                        pada kompetisi <strong class="text-slate-900">{{ eventsFilter.find(e => e.id == filterState.event_id)?.name }}</strong>
                    </span>.
                </div>

                <button
                    type="button"
                    class="btn-secondary text-xs inline-flex items-center gap-1.5 self-start sm:self-auto cursor-pointer"
                    :disabled="isSyncing"
                    title="Pindai dan lepas pendaftaran tim yang telah melewati batas waktu pembayaran"
                    @click="triggerResync"
                >
                    <svg
                        class="w-4 h-4 text-orange-600 shrink-0"
                        :class="{ 'animate-spin': isSyncing }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>{{ isSyncing ? 'Menyinkronkan Slot...' : (filterState.event_id ? 'Cek & Resync Slot Event Ini' : 'Cek & Resync Semua Slot') }}</span>
                </button>
            </div>

            <!-- Teams Container (Responsive Card List View) -->
            <div class="space-y-3.5">
                <div v-if="teams.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="font-bold text-slate-700">Tidak ada tim yang ditemukan</p>
                    <p class="mt-0.5 text-slate-400">Silakan sesuaikan kriteria pencarian di atas.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="t in teams"
                        :key="t.id"
                        class="frosted-card p-4 sm:p-5 hover:border-orange-300/80 hover:shadow-md transition-all duration-150 bg-white"
                    >
                        <!-- Top Header: Crest, Name, Association & Status -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="t.logo_url"
                                    :src="t.logo_url"
                                    :alt="t.name"
                                    class="w-10 h-10 rounded-xl object-cover border border-slate-200 bg-white shadow-2xs shrink-0"
                                />
                                <div v-else class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-100 to-amber-100 text-orange-700 font-extrabold flex items-center justify-center text-sm shadow-2xs shrink-0 border border-orange-200">
                                    {{ t.name.charAt(0) }}
                                </div>
                                <div>
                                    <h4 class="text-sm sm:text-base font-bold text-slate-900 leading-tight">
                                        <Link :href="route('admin.teams.show', { team: t.id })" class="hover:text-orange-600 transition-colors">
                                            {{ t.name }}
                                        </Link>
                                    </h4>
                                    <p class="text-xs text-slate-500 font-medium">{{ t.origin_association }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 flex-wrap">
                                <span
                                    v-if="t.status === 'pending_payment' && isPast(t.slot_expires_at || t.latest_payment?.expires_at)"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300 uppercase tracking-wider"
                                >
                                    Lewat Batas Bayar
                                </span>
                                <span
                                    v-if="t.status === 'active' && t.screening_status === 'all_passed'"
                                    class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300 uppercase tracking-wider"
                                >
                                    Siap Kunci Skuad
                                </span>
                                <StatusBadge :status="t.status" />
                            </div>
                        </div>

                        <!-- Card Body Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 text-xs items-center">
                            <!-- Event & Squad Progress (Col 1-4) -->
                            <div class="lg:col-span-4 space-y-2">
                                <div>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Kompetisi Terdaftar</span>
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm truncate">{{ t.event?.name }}</p>
                                    <span class="inline-block mt-0.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ t.event?.age_category }}
                                    </span>
                                </div>

                                <!-- Squad & Screening Breakdown Pills -->
                                <div class="pt-1.5 border-t border-slate-100 flex flex-wrap items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-200 tabular-data">
                                        {{ t.players_count }}/{{ t.event?.min_players_per_team || 11 }} Pemain
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 tabular-data">
                                        {{ t.officials_count || 0 }} Ofisial
                                    </span>
                                    <span
                                        v-if="t.players_passed_count > 0"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 tabular-data"
                                    >
                                        {{ t.players_passed_count }} Sah
                                    </span>
                                    <span
                                        v-if="t.players_submitted_count > 0"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200 tabular-data"
                                    >
                                        {{ t.players_submitted_count }} Antrean
                                    </span>
                                    <span
                                        v-if="t.players_rejected_count > 0"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200 tabular-data"
                                    >
                                        {{ t.players_rejected_count }} Revisi
                                    </span>
                                </div>
                            </div>

                            <!-- Manager & Contacts (Col 5-7) -->
                            <div class="lg:col-span-3 space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Manajer Tim</span>
                                <p class="font-semibold text-slate-800 truncate">{{ t.manager_name }}</p>
                                <div class="flex flex-col gap-1">
                                    <a
                                        v-if="t.manager_email"
                                        :href="`mailto:${t.manager_email}`"
                                        class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-600 hover:text-orange-600 transition-colors truncate"
                                    >
                                        <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <span class="truncate">{{ t.manager_email }}</span>
                                    </a>
                                    <a
                                        v-if="t.manager_whatsapp"
                                        :href="`https://wa.me/${t.manager_whatsapp.replace(/[^0-9]/g, '')}`"
                                        target="_blank"
                                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition-colors w-fit"
                                    >
                                        <svg class="w-3 h-3 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.97.529 1.954.81 3.033.81 3.181 0 5.767-2.586 5.768-5.766 0-3.18-2.587-5.767-5.767-5.767zm7.659 5.766c0 4.225-3.438 7.662-7.66 7.662-1.309 0-2.551-.334-3.649-.927l-4.041 1.06 1.079-3.941c-.672-1.155-1.049-2.482-1.049-3.854 0-4.225 3.438-7.662 7.66-7.662 4.223 0 7.66 3.437 7.66 7.662z"/>
                                        </svg>
                                        <span>{{ t.manager_whatsapp }}</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Invoice & Payment & Expiry Info (Col 8-10) -->
                            <div class="lg:col-span-3 space-y-1.5">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Status Pembayaran &amp; Batas Waktu</span>
                                <div v-if="t.latest_payment" class="space-y-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-mono text-xs font-bold text-slate-900">{{ t.latest_payment.invoice_number }}</span>
                                        <StatusBadge :status="t.latest_payment.status" />
                                    </div>
                                    <span class="font-mono tabular-data font-bold text-slate-900 block text-xs">
                                        {{ formatRupiah(t.latest_payment.total_amount) }}
                                    </span>
                                </div>
                                <span v-else class="text-slate-400 text-xs italic block">Belum ada tagihan</span>

                                <!-- Expiry Details (EXP) -->
                                <div v-if="t.slot_expires_at || t.latest_payment?.expires_at" class="pt-1.5 border-t border-slate-100 text-xs space-y-0.5">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] uppercase font-bold text-slate-400">Batas Waktu Slot (EXP):</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span
                                            class="font-mono text-[11px] tabular-data px-1.5 py-0.5 rounded border inline-block"
                                            :class="
                                                t.status === 'active' || t.status === 'locked_roster'
                                                    ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                    : isPast(t.slot_expires_at || t.latest_payment?.expires_at)
                                                    ? 'bg-rose-50 text-rose-700 border-rose-300 font-bold'
                                                    : 'bg-amber-50 text-amber-800 border-amber-200 font-medium'
                                            "
                                        >
                                            {{ formatDateTime(t.slot_expires_at || t.latest_payment?.expires_at) }}
                                        </span>
                                        <span
                                            v-if="t.status === 'pending_payment' && isPast(t.slot_expires_at || t.latest_payment?.expires_at)"
                                            class="text-[10px] font-bold text-rose-600"
                                        >
                                            (Lewat Batas)
                                        </span>
                                        <span
                                            v-else-if="t.status === 'expired'"
                                            class="text-[10px] font-bold text-slate-500"
                                        >
                                            (Slot Dilepas)
                                        </span>
                                        <span
                                            v-else-if="t.status === 'active' || t.status === 'locked_roster'"
                                            class="text-[10px] font-medium text-emerald-600"
                                        >
                                            (Terkunci Aman)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions (Col 11-12) -->
                            <div class="lg:col-span-2 flex flex-col sm:items-end justify-center gap-1.5">
                                <Link
                                    :href="route('admin.teams.show', { team: t.id })"
                                    class="btn-sm-pitch w-full sm:w-auto justify-center text-center inline-flex items-center gap-1"
                                >
                                    <span>Detail Tim &amp; Skuad</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </Link>

                                <Link
                                    v-if="t.players_count > 0 && t.event?.id"
                                    :href="route('admin.screening.team', { event: t.event.id, team: t.id })"
                                    class="btn-sm-secondary w-full sm:w-auto justify-center text-center"
                                >
                                    Screening ({{ t.players_submitted_count > 0 ? `${t.players_submitted_count} Antrean` : `${t.players_passed_count}/${t.players_count}` }})
                                </Link>

                                <button
                                    v-if="t.latest_payment?.proof_url"
                                    type="button"
                                    class="btn-sm-secondary w-full sm:w-auto justify-center"
                                    @click="openProofModal(t.latest_payment)"
                                >
                                    <span>Bukti Bayar</span>
                                </button>
                                <button
                                    v-else-if="t.latest_payment"
                                    type="button"
                                    class="btn-sm-secondary w-full sm:w-auto justify-center"
                                    @click="openProofModal(t.latest_payment)"
                                >
                                    Detail Tagihan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Integrated Proof Modal with Verification Capability -->
        <PaymentProofModal
            :show="isProofModalOpen"
            :payment="selectedPayment"
            :can-verify="true"
            @close="closeProofModal"
            @verified="onPaymentVerified"
        />
    </AdminLayout>
</template>
