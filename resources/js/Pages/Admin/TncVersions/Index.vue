<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import TncAgreementModal from '@/Components/TncAgreementModal.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    versions: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            total_codes: 0,
            total_versions: 0,
            active_versions: 0,
            total_acceptances: 0,
        }),
    },
    scopes: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const { applyValidation, isEmpty } = useFormValidator();
const { formatDateTime } = useFormatters();

const filterState = reactive({
    search: props.filters?.search || '',
    scope: props.filters?.scope || '',
    status: props.filters?.status || '',
});

const applyFilters = () => {
    router.get(
        route('admin.tnc-versions.index'),
        {
            search: filterState.search || undefined,
            scope: filterState.scope || undefined,
            status: filterState.status || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    filterState.search = '';
    filterState.scope = '';
    filterState.status = '';
    applyFilters();
};

const isCreateModalOpen = ref(false);
const bumpSource = ref(null);
const previewTnc = ref(null);
const processingToggleId = ref(null);

const form = useForm({
    title: '',
    code: '',
    scope: 'all',
    summary: '',
    content: '',
    is_active: true,
});

const nextBumpVersionLabel = computed(() => {
    if (!bumpSource.value) return 'v1.0 (Baru)';
    return `v${Number(bumpSource.value.version_number || 1) + 1}.0`;
});

const openCreateModal = () => {
    bumpSource.value = null;
    form.reset();
    form.clearErrors();
    form.scope = 'all';
    form.is_active = true;
    isCreateModalOpen.value = true;
};

const openBumpModal = (item) => {
    bumpSource.value = item;
    form.clearErrors();
    form.title = item.title;
    form.code = item.code;
    form.scope = item.scope;
    form.summary = item.summary || '';
    form.content = item.content_html || '';
    form.is_active = true;
    isCreateModalOpen.value = true;
};

const closeCreateModal = () => {
    isCreateModalOpen.value = false;
    bumpSource.value = null;
    form.clearErrors();
};

const submit = () => {
    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.title) || form.title.trim().length < 3) {
            errs.title = 'Judul dokumen Syarat & Ketentuan wajib diisi minimal 3 karakter.';
        }
        if (isEmpty(form.scope)) {
            errs.scope = 'Cakupan jenis event wajib dipilih.';
        }
        const strippedContent = String(form.content || '').replace(/<[^>]+>/g, '').trim();
        if (!strippedContent) {
            errs.content = 'Isi lengkap dokumen Syarat & Ketentuan wajib diisi.';
        }
        return errs;
    });

    if (!ok) return;

    form.post(route('admin.tnc-versions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
        },
    });
};

const toggleActive = (item) => {
    processingToggleId.value = item.id;
    router.post(
        route('admin.tnc-versions.toggle-active', { tncVersion: item.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingToggleId.value = null;
            },
        }
    );
};

const openPreviewModal = (item) => {
    const sampleEventName = 'Persija Young Tiger Cup 2026';
    const sampleDeadline = '24';
    const interpolate = (str) => String(str || '')
        .replaceAll('{{event_name}}', sampleEventName)
        .replaceAll('{{payment_deadline_hours}}', sampleDeadline);

    previewTnc.value = {
        ...item,
        summary: interpolate(item.summary),
        content_html: interpolate(item.content_html),
    };
};

const scopeBadgeClass = (scope) => ({
    team: 'bg-blue-50 text-blue-700 border-blue-200',
    audition: 'bg-amber-50 text-amber-800 border-amber-200',
    coaching_clinic: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    individual: 'bg-purple-50 text-purple-700 border-purple-200',
    all: 'bg-slate-100 text-slate-700 border-slate-200',
}[scope] || 'bg-slate-100 text-slate-700 border-slate-200');
</script>

<template>
    <AdminLayout page-title="Versi Syarat & Ketentuan (TnC)">
        <Head title="Versi Syarat & Ketentuan (TnC)" />

        <div class="space-y-6">
            <!-- Header & Action -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Manajemen Versi Syarat &amp; Ketentuan (TnC Versioning)
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Kelola dokumen Syarat &amp; Ketentuan secara terpusat dan immutable untuk seluruh event tim maupun perorangan.
                    </p>
                </div>
                <button
                    type="button"
                    class="btn-pitch text-xs sm:text-sm self-start sm:self-auto"
                    @click="openCreateModal"
                >
                    + Buat Dokumen TnC Baru
                </button>
            </div>

            <!-- Stats Strip -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
                <div class="frosted-card p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Kode Dokumen Unik</p>
                    <p class="text-2xl font-bold text-slate-900 tabular-data mt-1">{{ stats.total_codes }}</p>
                </div>
                <div class="frosted-card p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Versi Tersimpan</p>
                    <p class="text-2xl font-bold text-slate-900 tabular-data mt-1">{{ stats.total_versions }}</p>
                </div>
                <div class="frosted-card p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Versi Aktif</p>
                    <p class="text-2xl font-bold text-emerald-700 tabular-data mt-1">{{ stats.active_versions }}</p>
                </div>
                <div class="frosted-card p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Persetujuan Digital</p>
                    <p class="text-2xl font-bold text-orange-700 tabular-data mt-1">{{ stats.total_acceptances }}</p>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="frosted-card p-4">
                <form class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end" @submit.prevent="applyFilters">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Cari Judul atau Kode</label>
                        <input
                            v-model="filterState.search"
                            type="text"
                            placeholder="Contoh: individual-audition-waiver atau Kompetisi..."
                            class="input-field text-xs"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Cakupan Event</label>
                        <select v-model="filterState.scope" class="input-field text-xs" @change="applyFilters">
                            <option value="">Semua Cakupan</option>
                            <option v-for="(label, key) in scopes" :key="key" :value="key">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Status</label>
                            <select v-model="filterState.status" class="input-field text-xs" @change="applyFilters">
                                <option value="">Semua</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-secondary text-xs py-2 px-3">Cari</button>
                        <button
                            v-if="filterState.search || filterState.scope || filterState.status"
                            type="button"
                            class="text-xs font-semibold text-slate-500 hover:text-slate-800 py-2 px-2"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- Card List View -->
            <div v-if="versions.length === 0" class="frosted-card p-8 text-center">
                <p class="text-sm font-semibold text-slate-700">Tidak ada versi Syarat &amp; Ketentuan yang cocok dengan filter.</p>
                <p class="text-xs text-slate-500 mt-1">Ubah filter pencarian atau terbitkan dokumen Syarat &amp; Ketentuan baru.</p>
            </div>

            <div v-else class="space-y-3.5">
                <div
                    v-for="item in versions"
                    :key="item.id"
                    class="frosted-card p-5 transition-all"
                    :class="!item.is_active ? 'opacity-80 bg-slate-50/80' : ''"
                >
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div class="space-y-2.5 min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-orange-100 text-orange-800 border border-orange-200 tabular-data">
                                    v{{ item.version }}
                                </span>
                                <h2 class="text-sm sm:text-base font-bold text-slate-900">
                                    {{ item.title }}
                                </h2>
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold border"
                                    :class="scopeBadgeClass(item.scope)"
                                >
                                    {{ scopes[item.scope] || item.scope }}
                                </span>
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold border"
                                    :class="item.is_active
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                        : 'bg-slate-200 text-slate-600 border-slate-300'"
                                >
                                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                                <span
                                    v-if="item.is_immutable"
                                    title="Sudah disetujui oleh peserta/tim dan tidak dapat diubah secara historis"
                                    class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200"
                                >
                                    Terkunci Audit ({{ item.accepted_total }} persetujuan)
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                <span>Kode: <code class="font-mono text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">{{ item.code }}</code></span>
                                <span>Hash SHA-256: <code class="font-mono text-slate-600">{{ String(item.content_hash || '').slice(0, 12) }}...</code></span>
                                <span>Pembuat: <strong class="text-slate-700">{{ item.creator_name }}</strong></span>
                                <span v-if="item.published_at">Terbit: {{ formatDateTime(item.published_at) }}</span>
                            </div>

                            <p v-if="item.summary" class="text-xs text-slate-600 leading-relaxed bg-slate-50/90 border border-slate-200/80 rounded-xl p-3">
                                <span class="font-semibold text-slate-700">Ringkasan Checkbox:</span> {{ item.summary }}
                            </p>

                            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600 pt-0.5">
                                <span>
                                    Tertaut di <strong class="text-slate-900 tabular-data">{{ item.events_count }}</strong> event
                                </span>
                                <span>
                                    Persetujuan Tim: <strong class="text-slate-900 tabular-data">{{ item.accepted_teams_count }}</strong>
                                </span>
                                <span>
                                    Persetujuan Individu: <strong class="text-slate-900 tabular-data">{{ item.accepted_participants_count }}</strong>
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap lg:flex-col items-stretch gap-2 shrink-0 lg:w-48">
                            <button
                                type="button"
                                class="btn-secondary text-xs justify-center"
                                @click="openPreviewModal(item)"
                            >
                                Pratinjau Modal
                            </button>
                            <button
                                type="button"
                                class="px-3 py-2 rounded-xl text-xs font-bold text-orange-700 bg-orange-50 border border-orange-200 hover:bg-orange-100 transition-colors text-center"
                                @click="openBumpModal(item)"
                            >
                                Terbitkan Versi Baru
                            </button>
                            <button
                                type="button"
                                class="px-3 py-2 rounded-xl text-xs font-semibold border transition-colors text-center"
                                :class="item.is_active
                                    ? 'text-slate-700 bg-white border-slate-200 hover:bg-slate-100'
                                    : 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100'"
                                :disabled="processingToggleId === item.id"
                                @click="toggleActive(item)"
                            >
                                {{ processingToggleId === item.id ? 'Memproses...' : (item.is_active ? 'Nonaktifkan Versi' : 'Aktifkan Kembali') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Buat / Bump Versi TnC -->
        <Teleport to="body">
            <div
                v-if="isCreateModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/60 backdrop-blur-sm"
                role="dialog"
                aria-modal="true"
                @click.self="closeCreateModal"
            >
                <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[90vh]">
                    <div class="px-5 py-4 sm:px-6 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900">
                                    {{ bumpSource ? 'Terbitkan Revisi Versi Baru' : 'Buat Dokumen Syarat & Ketentuan Baru' }}
                                </h3>
                                <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-orange-100 text-orange-800 border border-orange-200">
                                    {{ nextBumpVersionLabel }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Versi sebelumnya tetap tersimpan secara permanen untuk menjaga integritas audit peserta yang sudah menyetujuinya.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/70"
                            @click="closeCreateModal"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form novalidate class="overflow-y-auto p-5 sm:p-6 space-y-4" @submit.prevent="submit">
                        <FormErrorBanner :errors="form.errors" />

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Judul Dokumen *
                                </label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    placeholder="Contoh: Syarat & Ketentuan Audisi Nasional 2026"
                                    class="input-field"
                                    @input="form.clearErrors('title')"
                                />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Cakupan Event *
                                </label>
                                <select
                                    v-model="form.scope"
                                    class="input-field"
                                    @change="form.clearErrors('scope')"
                                >
                                    <option v-for="(label, key) in scopes" :key="key" :value="key">
                                        {{ label }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.scope" />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Kode Dokumen (Grouping Versi)
                                </label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    :readonly="Boolean(bumpSource)"
                                    placeholder="Kosongkan untuk generate otomatis dari judul (contoh: regulasi-kompetisi-2026)"
                                    class="input-field font-mono text-xs"
                                    :class="bumpSource ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : ''"
                                />
                                <p class="text-[11px] text-slate-500 mt-1">
                                    {{ bumpSource
                                        ? `Revisi akan otomatis diterbitkan sebagai versi ${nextBumpVersionLabel} pada kode "${bumpSource.code}".`
                                        : 'Jika diisi kode yang sudah ada, sistem otomatis menaikkan nomor versinya (v2.0, v3.0, dst).' }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Ringkasan Teks di Samping Checkbox (Opsional)
                            </label>
                            <textarea
                                v-model="form.summary"
                                rows="2"
                                placeholder="Kosongkan untuk teks standar otomatis. Placeholder tersedia: {{event_name}}, {{payment_deadline_hours}}"
                                class="input-field text-xs"
                            />
                            <InputError :message="form.errors.summary" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Isi Lengkap Dokumen (Ditampilkan di Modal Persetujuan) *
                            </label>
                            <RichTextEditor
                                v-model="form.content"
                                placeholder="Tuliskan pasal, aturan teknis, ketentuan kesehatan/medis, atau regulasi resmi..."
                            />
                            <InputError :message="form.errors.content" />
                        </div>

                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            />
                            <span>Langsung aktifkan sebagai pilihan pada pembuatan event</span>
                        </label>

                        <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2.5">
                            <button
                                type="button"
                                class="btn-secondary text-xs"
                                @click="closeCreateModal"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="btn-pitch text-xs"
                                :disabled="form.processing"
                            >
                                {{ form.processing ? 'Menerbitkan...' : `Terbitkan ${nextBumpVersionLabel}` }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Preview Modal Identik Tampilan Pendaftar -->
        <TncAgreementModal
            :show="Boolean(previewTnc)"
            :tnc="previewTnc"
            :already-accepted="true"
            accent="orange"
            @close="previewTnc = null"
        />
    </AdminLayout>
</template>

