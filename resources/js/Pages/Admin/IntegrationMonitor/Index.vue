<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    webhooks: { type: Object, required: true },
    failedJobs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
    capabilities: { type: Object, default: () => ({}) },
});

const filterState = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
    provider: props.filters.provider || '',
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
    const url = safeRoute('admin.integration-monitor.index', {}, '/admin/system/integration-monitor');
    router.get(url, {
        search: filterState.search || undefined,
        status: filterState.status || undefined,
        provider: filterState.provider || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const resetFilters = () => {
    filterState.search = '';
    filterState.status = '';
    filterState.provider = '';
    applyFilters();
};

const truncate = (value, length = 180) => value && value.length > length ? `${value.slice(0, length)}...` : value;

const retryJob = (job) => {
    const url = safeRoute('admin.integration-monitor.jobs.retry', { id: job.id }, `/admin/system/integration-monitor/jobs/${job.id}/retry`);
    router.post(url, {}, { preserveScroll: true });
};

const forgetJob = (job) => {
    if (window.confirm('Hapus failed job ini dari daftar? Tindakan ini tidak dapat dibatalkan.')) {
        const url = safeRoute('admin.integration-monitor.jobs.forget', { id: job.id }, `/admin/system/integration-monitor/jobs/${job.id}`);
        router.delete(url, { preserveScroll: true });
    }
};
</script>

<template>
    <AdminLayout
        page-title="Monitoring Integrasi"
        subtitle="Pantau webhook payment dan antrean job yang gagal tanpa menyimpan credential provider."
    >
        <Head title="Monitoring Integrasi" />

        <div class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="frosted-card p-4 border-rose-200 bg-rose-50/30">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700">Webhook Gagal</span>
                    <p class="text-2xl font-black text-rose-900 tabular-data">{{ metrics.failed_webhooks || 0 }}</p>
                </div>
                <div class="frosted-card p-4 border-emerald-200 bg-emerald-50/30">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Webhook Diproses</span>
                    <p class="text-2xl font-black text-emerald-900 tabular-data">{{ metrics.processed_webhooks || 0 }}</p>
                </div>
                <div class="frosted-card p-4 border-amber-200 bg-amber-50/30">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Failed Jobs</span>
                    <p class="text-2xl font-black text-amber-900 tabular-data">{{ metrics.failed_jobs || 0 }}</p>
                </div>
            </div>

            <div class="frosted-card p-4 bg-white">
                <div class="flex flex-col lg:flex-row gap-2.5">
                    <input
                        v-model="filterState.search"
                        type="search"
                        placeholder="Cari invoice, reference, queue, atau error..."
                        aria-label="Cari log integrasi"
                        class="input-field text-xs flex-1"
                        @keyup.enter="applyFilters"
                    />
                    <select v-model="filterState.provider" aria-label="Filter provider" class="input-field text-xs" @change="applyFilters">
                        <option value="">Semua provider</option>
                        <option value="midtrans">Midtrans</option>
                        <option value="xendit">Xendit</option>
                    </select>
                    <select v-model="filterState.status" aria-label="Filter status webhook" class="input-field text-xs" @change="applyFilters">
                        <option value="">Semua status</option>
                        <option value="failed">Gagal</option>
                        <option value="processed">Diproses</option>
                    </select>
                    <button type="button" class="btn-secondary text-xs" @click="resetFilters">Reset</button>
                </div>
            </div>

            <section class="frosted-card overflow-hidden bg-white">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-800">Webhook Payment</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase">Waktu</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase">Provider</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase">Invoice</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase">Status</th>
                                <th class="px-4 py-3 font-bold text-slate-500 uppercase">Error</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in webhooks.data" :key="log.id" class="align-top">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ log.created_at }}</td>
                                <td class="px-4 py-3 font-bold uppercase text-slate-700">{{ log.provider }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    <div>{{ log.invoice_number || '—' }}</div>
                                    <div class="text-slate-400">{{ log.external_reference || '—' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 font-bold" :class="log.status === 'failed' ? 'bg-rose-50 text-rose-800' : 'bg-emerald-50 text-emerald-800'">
                                        {{ log.status }} · {{ log.http_status || '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 max-w-lg text-rose-700">{{ log.error_message || 'Tidak ada error' }}</td>
                            </tr>
                            <tr v-if="webhooks.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada webhook.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="frosted-card overflow-hidden bg-white">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-800">Failed Jobs</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    <div v-for="job in failedJobs.data" :key="job.id" class="p-4 text-xs">
                        <div class="flex flex-wrap justify-between gap-2">
                            <span class="font-bold text-slate-800">{{ job.queue }} · {{ job.connection }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500">{{ job.failed_at }}</span>
                                <button
                                    v-if="capabilities.manage_queue"
                                    type="button"
                                    class="btn-sm-secondary"
                                    aria-label="Retry failed job"
                                    @click="retryJob(job)"
                                >
                                    Retry
                                </button>
                                <button
                                    v-if="capabilities.forget_jobs"
                                    type="button"
                                    class="btn-sm-danger"
                                    aria-label="Hapus failed job"
                                    @click="forgetJob(job)"
                                >
                                    Forget
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-rose-700 whitespace-pre-wrap">{{ truncate(job.exception) }}</p>
                    </div>
                    <div v-if="failedJobs.data.length === 0" class="px-4 py-10 text-center text-slate-500">Tidak ada failed job.</div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
