<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    transactions: {
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

const { formatRupiah, formatDateTime } = useFormatters();

const filterState = reactive({
    event_id: props.filters.event_id || '',
    status: props.filters.status || '',
    method: props.filters.method || '',
    search: props.filters.search || '',
});

const applyFilters = () => {
    router.get(
        route('admin.payments.transactions'),
        {
            event_id: filterState.event_id || undefined,
            status: filterState.status || undefined,
            method: filterState.method || undefined,
            search: filterState.search || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    filterState.event_id = '';
    filterState.status = '';
    filterState.method = '';
    filterState.search = '';
    router.get(route('admin.payments.transactions'), {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AdminLayout page-title="Riwayat Seluruh Transaksi &amp; Rekonsiliasi" subtitle="Laporan pembayaran gateway (Midtrans / Xendit), transfer manual, dan rekonsiliasi refund.">
        <Head title="Riwayat Transaksi" />

        <div class="space-y-5">
            <!-- Filter Bar -->
            <form @submit.prevent="applyFilters" novalidate class="frosted-card p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Filter Kompetisi</label>
                        <select v-model="filterState.event_id" class="input-field" @change="applyFilters">
                            <option value="">Semua Kompetisi</option>
                            <option v-for="evt in eventsFilter" :key="evt.id" :value="evt.id">
                                {{ evt.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Status Pembayaran</label>
                        <select v-model="filterState.status" class="input-field" @change="applyFilters">
                            <option value="">Semua Status</option>
                            <option value="paid">Lunas (Paid)</option>
                            <option value="waiting_verification">Menunggu Verifikasi Manual</option>
                            <option value="unpaid">Belum Bayar (Unpaid)</option>
                            <option value="rejected">Ditolak (Rejected)</option>
                            <option value="expired">Kedaluwarsa (Expired)</option>
                            <option value="refund_required">Perlu Refund (Late Over-Quota)</option>
                            <option value="refunded">Dikembalikan (Refunded)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Kanal / Metode</label>
                        <select v-model="filterState.method" class="input-field" @change="applyFilters">
                            <option value="">Semua Metode</option>
                            <option value="manual">Transfer Manual Bank</option>
                            <option value="midtrans">Midtrans</option>
                            <option value="xendit">Xendit</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Cari No. Invoice / Tim</label>
                        <input
                            v-model="filterState.search"
                            type="text"
                            placeholder="INV-... atau nama tim"
                            class="input-field"
                        />
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="btn-pitch text-xs py-2 px-4 flex-1">Cari</button>
                        <button type="button" class="btn-secondary text-xs py-2 px-3" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </form>

            <!-- Transactions Table -->
            <div class="frosted-card overflow-hidden">
                <div v-if="transactions.length === 0" class="p-8 text-center text-xs text-slate-500">
                    Tidak ada transaksi yang cocok dengan filter pencarian.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 uppercase tracking-wide">
                                <th class="py-2.5 px-3.5">No. Invoice</th>
                                <th class="py-2.5 px-3.5">Tim &amp; Kompetisi</th>
                                <th class="py-2.5 px-3.5">Kanal Metode</th>
                                <th class="py-2.5 px-3.5">Nominal Tagihan</th>
                                <th class="py-2.5 px-3.5">Status</th>
                                <th class="py-2.5 px-3.5">Waktu Lunas / Kedaluwarsa</th>
                                <th class="py-2.5 px-3.5 text-right">Bukti / Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70">
                            <tr v-for="tx in transactions" :key="tx.id" class="bg-white hover:bg-slate-50/90 transition-colors">
                                <td class="py-2.5 px-3.5 font-bold font-mono tabular-data text-slate-900">
                                    {{ tx.invoice_number }}
                                </td>
                                <td class="py-2.5 px-3.5 font-medium text-slate-800">
                                    <p class="font-bold text-slate-900">{{ tx.team?.name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ tx.event?.name }}</p>
                                </td>
                                <td class="py-2.5 px-3.5 text-slate-700">
                                    <p class="font-semibold uppercase">{{ tx.method || 'Manual' }}</p>
                                    <p class="text-[11px] text-slate-500">{{ tx.payment_channel || '-' }}</p>
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data font-bold text-slate-900">
                                    {{ formatRupiah(tx.total_amount) }}
                                </td>
                                <td class="py-2.5 px-3.5">
                                    <div class="space-y-0.5">
                                        <StatusBadge :status="tx.status" />
                                        <p v-if="tx.rejection_reason" class="text-[10px] text-rose-700">
                                            Ditolak: {{ tx.rejection_reason }}
                                        </p>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data text-slate-600">
                                    <span v-if="tx.paid_at" class="text-emerald-800 font-semibold block">
                                        Lunas: {{ formatDateTime(tx.paid_at) }}
                                    </span>
                                    <span v-else class="text-slate-500 block">
                                        Expired: {{ formatDateTime(tx.expires_at) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3.5 text-right">
                                    <a
                                        v-if="tx.proof_url"
                                        :href="tx.proof_url"
                                        target="_blank"
                                        class="btn-secondary text-[11px] py-1 px-2.5"
                                    >
                                        Bukti Bayar
                                    </a>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
