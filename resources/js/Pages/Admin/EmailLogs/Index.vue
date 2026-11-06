<script setup>
import { reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    metrics: {
        type: Object,
        default: () => ({}),
    },
    events: {
        type: Array,
        default: () => [],
    },
    mailableTypes: {
        type: Array,
        default: () => [],
    },
});

const filterState = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
    type: props.filters.type || '',
    event_id: props.filters.event_id || '',
});

const applyFilters = () => {
    router.get(
        route('admin.email-logs.index'),
        {
            search: filterState.search || undefined,
            status: filterState.status || undefined,
            type: filterState.type || undefined,
            event_id: filterState.event_id || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const setStatusFilter = (status) => {
    filterState.status = status;
    applyFilters();
};

const resetFilters = () => {
    filterState.search = '';
    filterState.status = '';
    filterState.type = '';
    filterState.event_id = '';
    applyFilters();
};

// Retry State
const selectedLogForRetry = ref(null);
const showSingleRetryModal = ref(false);
const showBulkRetryModal = ref(false);

const singleRetryForm = useForm({});
const bulkRetryForm = useForm({
    event_id: '',
});

const openSingleRetryModal = (log) => {
    selectedLogForRetry.value = log;
    showSingleRetryModal.value = true;
};

const confirmSingleRetry = () => {
    if (!selectedLogForRetry.value) return;

    singleRetryForm.post(route('admin.email-logs.retry', { log: selectedLogForRetry.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showSingleRetryModal.value = false;
            selectedLogForRetry.value = null;
        },
    });
};

const confirmBulkRetry = () => {
    bulkRetryForm.event_id = filterState.event_id || '';
    bulkRetryForm.post(route('admin.email-logs.retry-failed'), {
        preserveScroll: true,
        onSuccess: () => {
            showBulkRetryModal.value = false;
        },
    });
};

const getStatusBadge = (status) => {
    switch (status) {
        case 'sent':
            return {
                label: 'Terkirim',
                class: 'bg-emerald-50 text-emerald-800 border-emerald-300',
            };
        case 'failed':
            return {
                label: 'Gagal',
                class: 'bg-rose-50 text-rose-800 border-rose-300',
            };
        case 'queued':
        default:
            return {
                label: 'Dalam Antrean',
                class: 'bg-amber-50 text-amber-900 border-amber-300',
            };
    }
};
</script>

<template>
    <AdminLayout
        page-title="Antrean &amp; Log Notifikasi Email"
        subtitle="Pemantauan riwayat transmisi email transaksional (pendaftaran tim, verifikasi pembayaran, hasil screening, dan akreditasi) dengan mekanisme kirim ulang (retry)."
    >
        <Head title="Antrean &amp; Log Email Sistem" />

        <div class="space-y-6">
            <!-- Top Operational Metrics Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div class="frosted-card p-4 space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Notifikasi</span>
                    <p class="text-2xl font-black text-slate-900 tabular-data">{{ metrics.total || 0 }}</p>
                    <span class="text-[11px] text-slate-500 block">Seluruh riwayat pengiriman</span>
                </div>

                <div class="frosted-card p-4 space-y-1 border-emerald-200/80 bg-emerald-50/20">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 block">Berhasil Terkirim</span>
                    <p class="text-2xl font-black text-emerald-900 tabular-data">{{ metrics.sent || 0 }}</p>
                    <span class="text-[11px] text-emerald-700/80 block">Sukses sampai ke penerima</span>
                </div>

                <div class="frosted-card p-4 space-y-1 border-amber-200/80 bg-amber-50/20">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Dalam Antrean (Queued)</span>
                    <p class="text-2xl font-black text-amber-900 tabular-data">{{ metrics.queued || 0 }}</p>
                    <span class="text-[11px] text-amber-700/80 block">Menunggu giliran antrean</span>
                </div>

                <div class="frosted-card p-4 space-y-1 border-rose-200/80 bg-rose-50/20">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-800 block">Pengiriman Gagal</span>
                        <button
                            v-if="metrics.failed > 0"
                            type="button"
                            class="text-[10px] font-bold text-rose-700 underline hover:text-rose-900"
                            @click="showBulkRetryModal = true"
                        >
                            Retry Semua
                        </button>
                    </div>
                    <p class="text-2xl font-black text-rose-900 tabular-data">{{ metrics.failed || 0 }}</p>
                    <span class="text-[11px] text-rose-700/80 block">Perlu tindakan kirim ulang</span>
                </div>
            </div>

            <!-- Filter Bar & Search Container -->
            <div class="frosted-card p-4 space-y-3.5 bg-white">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                    <!-- Search Input & Dropdowns -->
                    <div class="flex flex-1 flex-wrap items-center gap-2.5">
                        <div class="relative flex-1 min-w-[200px] max-w-sm">
                            <input
                                v-model="filterState.search"
                                type="text"
                                placeholder="Cari email penerima, subjek, atau tim..."
                                class="input-field text-xs pl-8"
                                @keyup.enter="applyFilters"
                            />
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <!-- Dropdown Jenis Notifikasi -->
                        <select
                            v-model="filterState.type"
                            class="input-field text-xs w-auto min-w-[190px]"
                            @change="applyFilters"
                        >
                            <option value="">Semua Jenis Notifikasi</option>
                            <option v-for="t in mailableTypes" :key="t.value" :value="t.value">
                                {{ t.label }}
                            </option>
                        </select>

                        <!-- Dropdown Event -->
                        <select
                            v-model="filterState.event_id"
                            class="input-field text-xs w-auto min-w-[190px]"
                            @change="applyFilters"
                        >
                            <option value="">Semua Kompetisi</option>
                            <option v-for="e in events" :key="e.id" :value="e.id">
                                {{ e.name }}
                            </option>
                        </select>

                        <button type="button" class="btn-pitch text-xs" @click="applyFilters">
                            Terapkan Filter
                        </button>

                        <button
                            v-if="filterState.search || filterState.status || filterState.type || filterState.event_id"
                            type="button"
                            class="text-xs text-slate-500 hover:text-slate-800 font-semibold px-2 py-1"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>

                    <!-- Quick Status Filter Pills -->
                    <div class="flex flex-wrap items-center gap-1.5 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100">
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border"
                            :class="filterState.status === '' ? 'bg-orange-600 text-white border-orange-600 shadow-2xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            @click="setStatusFilter('')"
                        >
                            Semua
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border"
                            :class="filterState.status === 'sent' ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            @click="setStatusFilter('sent')"
                        >
                            Terkirim
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border"
                            :class="filterState.status === 'queued' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            @click="setStatusFilter('queued')"
                        >
                            Antrean
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border"
                            :class="filterState.status === 'failed' ? 'bg-rose-600 text-white border-rose-600 shadow-2xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            @click="setStatusFilter('failed')"
                        >
                            Gagal
                        </button>
                    </div>
                </div>
            </div>

            <!-- Email Notification Cards (Responsive Card List View) -->
            <div class="space-y-3.5">
                <!-- Empty State -->
                <div v-if="logs.data.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500 bg-white">
                    <svg class="w-12 h-12 mx-auto mb-2.5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <p class="font-bold text-slate-800 text-sm">Tidak Ada Log Email yang Sesuai</p>
                    <p class="mt-0.5 text-slate-400">Belum ada riwayat email atau tidak ada data yang cocok dengan kriteria filter.</p>
                </div>

                <!-- Log Card Items -->
                <div
                    v-for="log in logs.data"
                    :key="log.id"
                    class="frosted-card p-4 sm:p-5 hover:border-orange-300/80 hover:shadow-md transition-all duration-150 bg-white space-y-3"
                >
                    <!-- Header Row: Recipient, Mailable Type, Status Badge -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Mail Envelope Avatar -->
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                                :class="
                                    log.status === 'sent'
                                        ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
                                        : log.status === 'failed'
                                        ? 'bg-rose-50 border-rose-200 text-rose-700'
                                        : 'bg-amber-50 border-amber-200 text-amber-700'
                                "
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-bold text-slate-900 truncate font-mono">{{ log.recipient_email }}</span>
                                    <span class="text-[11px] font-mono text-slate-400">#{{ log.id }}</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mt-0.5 text-xs">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 uppercase tracking-wider">
                                        {{ log.type_label }}
                                    </span>
                                    <span v-if="log.team_name" class="text-slate-600 font-semibold">
                                        Tim: {{ log.team_name }}
                                    </span>
                                    <span v-if="log.event_name" class="text-slate-400">
                                        &bull; {{ log.event_name }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Action -->
                        <div class="flex items-center gap-2.5 shrink-0 ml-auto sm:ml-0">
                            <span
                                class="px-2.5 py-1 rounded-md text-[11px] font-bold border uppercase tracking-wider"
                                :class="getStatusBadge(log.status).class"
                            >
                                {{ getStatusBadge(log.status).label }}
                            </span>

                            <!-- Retry Button for Failed Logs -->
                            <button
                                v-if="log.is_retryable"
                                type="button"
                                class="px-3 py-1 text-xs font-bold text-white rounded-lg bg-rose-600 hover:bg-rose-700 shadow-2xs transition-all flex items-center gap-1.5"
                                title="Kirim ulang email ini"
                                @click="openSingleRetryModal(log)"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Kirim Ulang</span>
                            </button>
                        </div>
                    </div>

                    <!-- Subject & Details -->
                    <div class="space-y-1.5 text-xs">
                        <div class="flex items-center gap-1.5">
                            <span class="text-slate-400 font-semibold uppercase text-[10px] shrink-0">Subjek:</span>
                            <span class="font-bold text-slate-800 truncate">{{ log.subject || '-' }}</span>
                        </div>

                        <!-- Error Message Alert if Failed -->
                        <div
                            v-if="log.error_message"
                            class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-[11px] text-rose-800 font-mono break-all"
                        >
                            <span class="font-bold text-rose-900 uppercase">Penyebab Gagal:</span>
                            {{ log.error_message }}
                        </div>

                        <!-- Meta Timestamps Row -->
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-[11px] text-slate-500">
                            <div>
                                <span class="text-slate-400">Dibuat:</span>
                                <span class="font-mono font-medium text-slate-700 ml-1">{{ log.created_at }}</span>
                            </div>
                            <div v-if="log.sent_at">
                                <span class="text-slate-400">Terkirim:</span>
                                <span class="font-mono font-medium text-emerald-700 ml-1">{{ log.sent_at }}</span>
                            </div>
                            <div v-if="log.last_attempted_at && log.status !== 'sent'">
                                <span class="text-slate-400">Percobaan Terakhir:</span>
                                <span class="font-mono font-medium text-slate-700 ml-1">{{ log.last_attempted_at }}</span>
                            </div>
                            <div v-if="log.attempts > 1">
                                <span class="text-slate-400">Jumlah Percobaan:</span>
                                <span class="font-mono font-bold text-slate-800 ml-1">{{ log.attempts }}x</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination Container -->
            <div v-if="logs.links && logs.links.length > 3" class="flex justify-center pt-2">
                <nav class="flex items-center gap-1">
                    <Component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, i) in logs.links"
                        :key="i"
                        :href="link.url"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                        :class="
                            link.active
                                ? 'bg-orange-600 text-white'
                                : link.url
                                ? 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'
                                : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                        "
                        v-html="link.label"
                    />
                </nav>
            </div>
        </div>

        <!-- Confirm Modal Single Retry -->
        <ConfirmModal
            :show="showSingleRetryModal"
            title="Konfirmasi Kirim Ulang Email"
            :description="`Kirim ulang notifikasi email (${selectedLogForRetry?.type_label}) ke ${selectedLogForRetry?.recipient_email}?`"
            confirm-text="Kirim Ulang Sekarang"
            cancel-text="Batal"
            :processing="singleRetryForm.processing"
            @confirm="confirmSingleRetry"
            @close="showSingleRetryModal = false"
        />

        <!-- Confirm Modal Bulk Retry -->
        <ConfirmModal
            :show="showBulkRetryModal"
            title="Kirim Ulang Semua Email Gagal"
            :description="`Apakah Anda yakin ingin memproses ulang seluruh (${metrics.failed}) email yang saat ini berstatus gagal?`"
            confirm-text="Proses Ulang Semua"
            cancel-text="Batal"
            :processing="bulkRetryForm.processing"
            @confirm="confirmBulkRetry"
            @close="showBulkRetryModal = false"
        />
    </AdminLayout>
</template>
