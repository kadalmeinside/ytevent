<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    actions: { type: Array, default: () => [] },
    entityTypes: { type: Array, default: () => [] },
    actors: { type: Array, default: () => [] },
});

const filterState = reactive({
    search: props.filters.search || '',
    action: props.filters.action || '',
    entity_type: props.filters.entity_type || '',
    actor_id: props.filters.actor_id || '',
});

const safeRoute = (name, params = {}, fallback = '') => {
    try {
        const routeFn = typeof window !== 'undefined' && typeof window.route === 'function' ? window.route : null;
        if (routeFn && typeof routeFn().has === 'function' && routeFn().has(name)) {
            return routeFn(name, params);
        }
    } catch {
        // Fallback
    }
    return fallback;
};

const applyFilters = () => {
    const url = safeRoute('admin.audit-logs.index', {}, '/admin/system/audit-logs');
    router.get(url, {
        search: filterState.search || undefined,
        action: filterState.action || undefined,
        entity_type: filterState.entity_type || undefined,
        actor_id: filterState.actor_id || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    filterState.search = '';
    filterState.action = '';
    filterState.entity_type = '';
    filterState.actor_id = '';
    applyFilters();
};

const exportLogs = () => {
    const query = {
        search: filterState.search || undefined,
        action: filterState.action || undefined,
        entity_type: filterState.entity_type || undefined,
        actor_id: filterState.actor_id || undefined,
    };
    const cleanQuery = Object.fromEntries(Object.entries(query).filter(([_, v]) => v !== undefined));
    const qs = new URLSearchParams(cleanQuery).toString();
    const fallback = '/admin/system/audit-logs/export-csv' + (qs ? `?${qs}` : '');
    window.location.href = safeRoute('admin.audit-logs.export', query, fallback);
};

const actionLabel = (action) => action.replaceAll('_', ' ');
</script>

<template>
    <AdminLayout
        page-title="Audit Trail Operasional"
        subtitle="Riwayat perubahan penting pada pembayaran, screening, verifikasi, dan operasi turnamen."
    >
        <Head title="Audit Trail Operasional" />

        <div class="space-y-5">
            <div class="frosted-card p-4 bg-white">
                <div class="flex flex-col lg:flex-row gap-2.5">
                    <input
                        v-model="filterState.search"
                        type="search"
                        placeholder="Cari action, alasan, atau status..."
                        class="input-field text-xs flex-1"
                        @keyup.enter="applyFilters"
                    />
                    <select v-model="filterState.action" class="input-field text-xs" @change="applyFilters">
                        <option value="">Semua action</option>
                        <option v-for="action in actions" :key="action" :value="action">
                            {{ actionLabel(action) }}
                        </option>
                    </select>
                    <select v-model="filterState.entity_type" class="input-field text-xs" @change="applyFilters">
                        <option value="">Semua entitas</option>
                        <option v-for="entity in entityTypes" :key="entity.value" :value="entity.value">
                            {{ entity.label }}
                        </option>
                    </select>
                    <select v-model="filterState.actor_id" class="input-field text-xs" @change="applyFilters">
                        <option value="">Semua actor</option>
                        <option v-for="actor in actors" :key="actor.id" :value="actor.id">
                            {{ actor.name }}
                        </option>
                    </select>
                    <button type="button" class="btn-secondary text-xs" @click="resetFilters">Reset</button>
                    <button type="button" class="btn-primary text-xs whitespace-nowrap" @click="exportLogs">
                        Export CSV
                    </button>
                </div>
            </div>

            <div class="frosted-card overflow-hidden bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wider">Waktu</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wider">Entitas</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wider">Perubahan</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wider">Actor</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase tracking-wider">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in logs.data" :key="log.id" class="align-top hover:bg-slate-50/70">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ log.created_at }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full border border-orange-200 bg-orange-50 px-2 py-1 font-bold capitalize text-orange-800">
                                        {{ actionLabel(log.action) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-700">
                                    {{ log.entity_type }} #{{ log.entity_id }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    <span v-if="log.from_status || log.to_status">
                                        {{ log.from_status || '—' }} <span class="text-slate-400">→</span> {{ log.to_status || '—' }}
                                    </span>
                                    <span v-else class="text-slate-400">Tidak ada perubahan status</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="log.actor" class="font-semibold text-slate-700">{{ log.actor.name }}</div>
                                    <div v-if="log.actor" class="text-slate-400">{{ log.actor.email }}</div>
                                    <span v-else class="text-slate-400">Sistem</span>
                                </td>
                                <td class="px-4 py-3 max-w-xs text-slate-600">{{ log.reason || '—' }}</td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-4 py-12 text-center text-slate-500">Belum ada audit log yang sesuai filter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="logs.links && logs.links.length > 3" class="flex justify-center gap-1 p-4 border-t border-slate-100">
                    <template v-for="(link, index) in logs.links" :key="index">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="px-3 py-1.5 rounded-lg text-xs"
                            :class="link.active ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                            v-html="link.label"
                        />
                        <span v-else class="px-3 py-1.5 rounded-lg text-xs text-slate-300" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
