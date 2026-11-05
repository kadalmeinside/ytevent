<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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

const { formatRupiah, formatDateTime } = useFormatters();

const filterState = reactive({
    event_id: props.filters.event_id || '',
    status: props.filters.status || '',
    search: props.filters.search || '',
});

const applyFilters = () => {
    router.get(
        route('admin.teams.index'),
        {
            event_id: filterState.event_id || undefined,
            status: filterState.status || undefined,
            search: filterState.search || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    filterState.event_id = '';
    filterState.status = '';
    filterState.search = '';
    router.get(route('admin.teams.index'), {}, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <AdminLayout page-title="Daftar Tim Terdaftar" subtitle="Pantau seluruh tim dari semua kompetisi, status reservasi slot, dan pelunasan invoice.">
        <Head title="Daftar Tim Masuk" />

        <div class="space-y-5">
            <!-- Filter Bar -->
            <form @submit.prevent="applyFilters" novalidate class="frosted-card p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Pilih Kompetisi</label>
                        <select v-model="filterState.event_id" class="input-field" @change="applyFilters">
                            <option value="">Semua Kompetisi</option>
                            <option v-for="evt in eventsFilter" :key="evt.id" :value="evt.id">
                                {{ evt.name }} ({{ evt.age_category }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Status Tim</label>
                        <select v-model="filterState.status" class="input-field" @change="applyFilters">
                            <option value="">Semua Status</option>
                            <option value="pending_payment">Menunggu Bayar (Slot Ditahan)</option>
                            <option value="active">Aktif / Lunas</option>
                            <option value="locked_roster">Roster Dikunci</option>
                            <option value="expired">Kedaluwarsa (Slot Dilepas)</option>
                            <option value="disqualified">Didiskualifikasi</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-600 mb-1">Cari Nama Tim / Askot / Manajer</label>
                        <input
                            v-model="filterState.search"
                            type="text"
                            placeholder="Ketik nama klub atau manajer..."
                            class="input-field"
                        />
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="btn-pitch text-xs py-2 px-4 flex-1">Filter</button>
                        <button type="button" class="btn-secondary text-xs py-2 px-3" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </form>

            <!-- Teams Table -->
            <div class="frosted-card overflow-hidden">
                <div v-if="teams.length === 0" class="p-8 text-center text-xs text-slate-500">
                    Tidak ditemukan data tim yang sesuai dengan kriteria pencarian.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 uppercase tracking-wide">
                                <th class="py-2.5 px-3.5">Tim &amp; Asosiasi</th>
                                <th class="py-2.5 px-3.5">Kompetisi</th>
                                <th class="py-2.5 px-3.5">Manajer &amp; WhatsApp</th>
                                <th class="py-2.5 px-3.5">Tagihan &amp; Invoice</th>
                                <th class="py-2.5 px-3.5">Pemain Terdaftar</th>
                                <th class="py-2.5 px-3.5">Status Slot</th>
                                <th class="py-2.5 px-3.5">Terdaftar Pada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70">
                            <tr v-for="t in teams" :key="t.id" class="bg-white hover:bg-slate-50/90 transition-colors">
                                <td class="py-2.5 px-3.5 font-bold text-slate-900">
                                    <div class="flex items-center gap-2.5">
                                        <img
                                            v-if="t.logo_url"
                                            :src="t.logo_url"
                                            :alt="t.name"
                                            class="w-7 h-7 rounded object-cover border border-slate-200 bg-white"
                                        />
                                        <div>
                                            <p class="text-sm leading-tight">{{ t.name }}</p>
                                            <p class="text-[11px] text-slate-500 font-normal">{{ t.origin_association }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3.5 text-slate-800 font-semibold">
                                    <p>{{ t.event?.name }}</p>
                                    <span class="text-[10px] text-slate-500">{{ t.event?.age_category }}</span>
                                </td>
                                <td class="py-2.5 px-3.5 text-slate-700">
                                    <p class="font-medium">{{ t.manager_name }}</p>
                                    <p class="text-[11px] tabular-data text-slate-500">{{ t.manager_whatsapp }}</p>
                                </td>
                                <td class="py-2.5 px-3.5">
                                    <div v-if="t.latest_payment" class="space-y-0.5">
                                        <span class="tabular-data font-mono font-bold text-slate-900 block">
                                            {{ t.latest_payment.invoice_number }}
                                        </span>
                                        <div class="flex items-center gap-1.5">
                                            <span class="tabular-data text-[11px] text-slate-600 font-medium">
                                                {{ formatRupiah(t.latest_payment.total_amount) }}
                                            </span>
                                            <StatusBadge :status="t.latest_payment.status" />
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data font-semibold text-slate-800">
                                    {{ t.players_count }} Pemain
                                </td>
                                <td class="py-2.5 px-3.5">
                                    <StatusBadge :status="t.status" />
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data text-slate-500">
                                    {{ formatDateTime(t.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

