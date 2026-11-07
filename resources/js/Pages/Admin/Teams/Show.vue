<script setup>
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import DocumentViewerModal from '@/Components/DocumentViewerModal.vue';
import PaymentProofModal from '@/Components/PaymentProofModal.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    team: {
        type: Object,
        required: true,
    },
    readiness: {
        type: Object,
        default: () => ({}),
    },
    players: {
        type: Array,
        default: () => [],
    },
    officials: {
        type: Array,
        default: () => [],
    },
    payments: {
        type: Array,
        default: () => [],
    },
    canManageStatus: {
        type: Boolean,
        default: false,
    },
});

const { formatRupiah, formatDateTime, formatDate } = useFormatters();

const activeTab = ref('players'); // 'players' | 'officials' | 'administration'
const playerStatusFilter = ref('all');
const playerSearch = ref('');

const filteredPlayers = computed(() => {
    return props.players.filter((p) => {
        if (playerStatusFilter.value !== 'all' && p.screening_status !== playerStatusFilter.value) {
            return false;
        }
        if (playerSearch.value.trim()) {
            const q = playerSearch.value.trim().toLowerCase();
            return (
                p.full_name?.toLowerCase().includes(q) ||
                p.nik?.toLowerCase().includes(q) ||
                p.nisn?.toLowerCase().includes(q) ||
                String(p.jersey_number).includes(q) ||
                p.position?.toLowerCase().includes(q)
            );
        }
        return true;
    });
});

// Document Viewer Modal state
const viewerState = ref({
    show: false,
    url: '',
    title: '',
    subtitle: '',
    isPdf: false,
});

const openDocumentPreview = ({ url, title, subtitle = '', isPdf = false }) => {
    if (!url) return;
    viewerState.value = {
        show: true,
        url,
        title,
        subtitle,
        isPdf,
    };
};

const closeDocumentPreview = () => {
    viewerState.value.show = false;
};

// Payment Proof Modal state
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
    router.reload({ preserveScroll: true });
};

// Admin Status & Notes Management
const notesForm = useForm({
    admin_notes: props.team.admin_notes || '',
});

const saveAdminNotes = () => {
    notesForm.put(route('admin.teams.update-notes', { team: props.team.id }), {
        preserveScroll: true,
    });
};

const statusActionModal = ref({
    show: false,
    mode: '', // 'unlock' | 'force_lock' | 'disqualify' | 'reactivate'
    title: '',
    description: '',
});

const statusActionForm = useForm({
    status: '',
    reason: '',
});

const openStatusActionModal = (mode) => {
    statusActionForm.reset();
    statusActionForm.clearErrors();

    if (mode === 'unlock') {
        statusActionModal.value = {
            show: true,
            mode: 'unlock',
            title: 'Buka Kunci Skuad Final (Unlock Roster)',
            description: 'Tindakan ini akan mengembalikan status tim ke AKTIF sehingga Manajer Tim dapat menambah, mengedit, atau mengganti pemain sebelum dikunci kembali.',
        };
    } else if (mode === 'force_lock') {
        statusActionModal.value = {
            show: true,
            mode: 'force_lock',
            title: 'Kunci Skuad Final oleh Panitia (Force Lock)',
            description: 'Seluruh syarat kelayakan skuad tim ini telah terpenuhi. Mengunci skuad akan menerbitkan akreditasi resmi dan mengirim email konfirmasi ke manajer.',
        };
    } else if (mode === 'disqualify') {
        statusActionForm.status = 'disqualified';
        statusActionModal.value = {
            show: true,
            mode: 'disqualify',
            title: 'Diskualifikasi Tim dari Kompetisi',
            description: 'Tim yang didiskualifikasi akan kehilangan hak bertanding dan slot kuotanya pada kompetisi ini akan dilepaskan.',
        };
    } else if (mode === 'reactivate') {
        statusActionForm.status = 'active';
        statusActionModal.value = {
            show: true,
            mode: 'reactivate',
            title: 'Pulihkan Status Aktif Tim',
            description: 'Mengembalikan status tim menjadi AKTIF kembali (selama slot kuota kompetisi masih tersedia).',
        };
    }
};

const closeStatusActionModal = () => {
    statusActionModal.value.show = false;
};

const submitStatusAction = () => {
    const mode = statusActionModal.value.mode;
    if (mode === 'force_lock') {
        statusActionForm.post(route('admin.teams.force-lock-roster', { team: props.team.id }), {
            preserveScroll: true,
            onSuccess: () => closeStatusActionModal(),
        });
        return;
    }

    if (!statusActionForm.reason || statusActionForm.reason.trim().length < 5) {
        statusActionForm.setError('reason', 'Alasan wajib diisi minimal 5 karakter sebagai catatan audit.');
        return;
    }

    if (mode === 'unlock') {
        statusActionForm.post(route('admin.teams.unlock-roster', { team: props.team.id }), {
            preserveScroll: true,
            onSuccess: () => closeStatusActionModal(),
        });
    } else {
        statusActionForm.post(route('admin.teams.update-status', { team: props.team.id }), {
            preserveScroll: true,
            onSuccess: () => closeStatusActionModal(),
        });
    }
};

const docChipClass = (doc) => {
    if (!doc.uploaded) {
        return 'bg-slate-100 text-slate-500 border-slate-200 cursor-not-allowed';
    }
    if (doc.validation_status === 'valid') {
        return 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100 cursor-pointer';
    }
    if (doc.validation_status === 'invalid') {
        return 'bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100 cursor-pointer';
    }
    return 'bg-amber-50 text-amber-900 border-amber-200 hover:bg-amber-100 cursor-pointer';
};
</script>

<template>
    <AdminLayout
        :page-title="`Detail Tim: ${team.name}`"
        :subtitle="`Dossier lengkap status kesiapan, skuad pemain, berkas screening, ofisial, dan riwayat pembayaran.`"
    >
        <Head :title="`Detail Tim - ${team.name}`" />

        <div class="space-y-5">
            <!-- Top Navigation Breadcrumb -->
            <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-1.5 text-slate-500">
                    <Link :href="route('admin.teams.index')" class="hover:text-orange-600 font-semibold">
                        &larr; Kembali ke Daftar Tim
                    </Link>
                    <span>/</span>
                    <span class="font-bold text-slate-900">{{ team.name }}</span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="team.event?.id"
                        :href="route('admin.screening.team', { event: team.event.id, team: team.id })"
                        class="btn-sm-pitch inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        <span>Buka Workspace Screening</span>
                    </Link>
                    <a
                        :href="route('admin.teams.accreditation.roster-sheet', { team: team.id })"
                        class="btn-sm-secondary inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Unduh Roster PDF</span>
                    </a>
                    <a
                        :href="route('admin.teams.accreditation.id-cards', { team: team.id })"
                        class="btn-sm-secondary inline-flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                        <span>Unduh ID Card PDF</span>
                    </a>
                    <button
                        v-if="canManageStatus && team.status === 'locked_roster'"
                        type="button"
                        class="btn-sm-secondary text-amber-800 border-amber-300 bg-amber-50 hover:bg-amber-100 inline-flex items-center gap-1.5 cursor-pointer"
                        @click="openStatusActionModal('unlock')"
                    >
                        <span>Buka Kunci Skuad</span>
                    </button>
                    <button
                        v-if="canManageStatus && team.status === 'active' && team.workflow?.is_ready_to_lock"
                        type="button"
                        class="btn-sm-secondary text-emerald-800 border-emerald-300 bg-emerald-50 hover:bg-emerald-100 inline-flex items-center gap-1.5 cursor-pointer"
                        @click="openStatusActionModal('force_lock')"
                    >
                        <span>Kunci Skuad (Force Lock)</span>
                    </button>
                    <button
                        v-if="canManageStatus && team.status !== 'disqualified'"
                        type="button"
                        class="btn-sm-secondary text-rose-800 border-rose-300 bg-rose-50 hover:bg-rose-100 inline-flex items-center gap-1.5 cursor-pointer"
                        @click="openStatusActionModal('disqualify')"
                    >
                        <span>Diskualifikasi Tim</span>
                    </button>
                    <button
                        v-if="canManageStatus && team.status === 'disqualified'"
                        type="button"
                        class="btn-sm-secondary text-emerald-800 border-emerald-300 bg-emerald-50 hover:bg-emerald-100 inline-flex items-center gap-1.5 cursor-pointer"
                        @click="openStatusActionModal('reactivate')"
                    >
                        <span>Pulihkan Status Aktif</span>
                    </button>
                </div>
            </div>

            <!-- Team Dossier Header Card -->
            <div class="frosted-card p-5 sm:p-6 bg-white space-y-5">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                    <div class="flex items-start sm:items-center gap-4">
                        <img
                            v-if="team.logo_url"
                            :src="team.logo_url"
                            :alt="team.name"
                            class="w-16 h-16 rounded-2xl object-cover border border-slate-200 bg-white shadow-sm shrink-0"
                        />
                        <div
                            v-else
                            class="w-16 h-16 rounded-2xl bg-gradient-to-br from-orange-100 to-amber-100 text-orange-700 font-black flex items-center justify-center text-xl shadow-sm shrink-0 border border-orange-200"
                        >
                            {{ team.name.substring(0, 2).toUpperCase() }}
                        </div>

                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg sm:text-xl font-black text-slate-900">{{ team.name }}</h2>
                                <StatusBadge :status="team.status" />
                                <span
                                    v-if="team.workflow?.is_ready_to_lock"
                                    class="px-2.5 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-wider bg-amber-100 text-amber-900 border border-amber-300"
                                >
                                    Menunggu Manajer Kunci Skuad
                                </span>
                            </div>
                            <p class="text-xs text-slate-600 font-medium">
                                {{ team.origin_association || 'Asosiasi belum dicantumkan' }}
                                <span class="text-slate-300 mx-1.5">|</span>
                                Kompetisi: <strong class="text-slate-900">{{ team.event?.name }} ({{ team.event?.age_category }})</strong>
                            </p>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 pt-0.5">
                                <span>Manajer: <strong class="text-slate-800">{{ team.manager_name }}</strong></span>
                                <span>&bull;</span>
                                <span>Head Coach: <strong class="text-slate-800">{{ team.head_coach_name || '-' }}</strong></span>
                                <span v-if="team.roster_locked_at">&bull;</span>
                                <span v-if="team.roster_locked_at" class="text-emerald-700 font-semibold">
                                    Dikunci pada: {{ team.roster_locked_at }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Summary KPI Counters -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 shrink-0">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-center">
                            <span class="block text-[10px] font-bold uppercase text-slate-400">Total Pemain</span>
                            <span class="text-base font-black text-slate-900 tabular-data">
                                {{ team.workflow?.counts?.players_total || 0 }}/{{ team.workflow?.counts?.max_players_allowed || 25 }}
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-50/70 border border-emerald-200 text-center">
                            <span class="block text-[10px] font-bold uppercase text-emerald-700">Pemain Sah</span>
                            <span class="text-base font-black text-emerald-800 tabular-data">
                                {{ team.workflow?.counts?.players_passed || 0 }} ({{ team.workflow?.counts?.gk_passed || 0 }} GK)
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-blue-50/70 border border-blue-200 text-center">
                            <span class="block text-[10px] font-bold uppercase text-blue-700">Antrean / Revisi</span>
                            <span class="text-base font-black text-blue-900 tabular-data">
                                {{ team.workflow?.counts?.players_submitted || 0 }} / {{ team.workflow?.counts?.players_rejected || 0 }}
                            </span>
                        </div>
                        <div class="p-3 rounded-xl bg-orange-50/70 border border-orange-200 text-center">
                            <span class="block text-[10px] font-bold uppercase text-orange-700">Ofisial Bench</span>
                            <span class="text-base font-black text-orange-900 tabular-data">
                                {{ team.workflow?.counts?.officials_total || 0 }}/{{ team.workflow?.counts?.max_officials_allowed || 5 }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 5-Stage Team Readiness & Accreditation Strip -->
                <div v-if="team.workflow" class="space-y-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-800 uppercase tracking-wider text-[11px]">
                            Status Pemenuhan Syarat Turnamen (Tahap {{ team.workflow.current_step }} dari {{ team.workflow.total_steps }})
                        </span>
                        <span
                            class="px-2.5 py-0.5 rounded-full text-[11px] font-bold border"
                            :class="
                                team.workflow.is_roster_locked
                                    ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                    : team.workflow.is_ready_to_lock
                                      ? 'bg-amber-100 text-amber-900 border-amber-300'
                                      : 'bg-slate-100 text-slate-700 border-slate-200'
                            "
                        >
                            {{
                                team.workflow.is_roster_locked
                                    ? 'Terakreditasi Resmi (Final)'
                                    : team.workflow.is_ready_to_lock
                                      ? 'Syarat Lengkap — Menunggu Kunci Skuad'
                                      : 'Masih Dalam Proses Pemenuhan Syarat'
                            }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5 text-xs">
                        <div
                            class="p-3 rounded-xl border flex items-center justify-between"
                            :class="team.workflow.is_paid ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : 'bg-amber-50/60 border-amber-200 text-amber-950'"
                        >
                            <div>
                                <span class="text-[10px] font-extrabold uppercase block opacity-75">Tahap 1</span>
                                <span class="font-bold">Pelunasan Tagihan</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase" :class="team.workflow.is_paid ? 'bg-emerald-200/70 text-emerald-900' : 'bg-amber-200/70 text-amber-900'">
                                {{ team.workflow.is_paid ? 'LUNAS' : 'BELUM' }}
                            </span>
                        </div>

                        <div
                            class="p-3 rounded-xl border flex items-center justify-between"
                            :class="team.workflow.is_min_players_met && team.workflow.is_gk_met ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : 'bg-amber-50/60 border-amber-200 text-amber-950'"
                        >
                            <div>
                                <span class="text-[10px] font-extrabold uppercase block opacity-75">Tahap 2</span>
                                <span class="font-bold">Kuota &amp; Min {{ team.workflow.counts.min_gk_required || 2 }} GK</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold tabular-data" :class="team.workflow.is_min_players_met && team.workflow.is_gk_met ? 'bg-emerald-200/70 text-emerald-900' : 'bg-amber-200/70 text-amber-900'">
                                {{ team.workflow.counts.players_total }}/{{ team.workflow.counts.min_players_required }} ({{ team.workflow.counts.gk_total }} GK)
                            </span>
                        </div>

                        <div
                            class="p-3 rounded-xl border flex items-center justify-between"
                            :class="team.workflow.is_officials_met ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : 'bg-amber-50/60 border-amber-200 text-amber-950'"
                        >
                            <div>
                                <span class="text-[10px] font-extrabold uppercase block opacity-75">Tahap 3</span>
                                <span class="font-bold">Min. 1 Ofisial</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold tabular-data" :class="team.workflow.is_officials_met ? 'bg-emerald-200/70 text-emerald-900' : 'bg-amber-200/70 text-amber-900'">
                                {{ team.workflow.counts.officials_total }} Ofisial
                            </span>
                        </div>

                        <div
                            class="p-3 rounded-xl border flex items-center justify-between"
                            :class="team.workflow.is_all_players_passed && team.workflow.is_gk_passed_met ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : 'bg-blue-50/60 border-blue-200 text-blue-950'"
                        >
                            <div>
                                <span class="text-[10px] font-extrabold uppercase block opacity-75">Tahap 4</span>
                                <span class="font-bold">Screening Panitia</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold tabular-data" :class="team.workflow.is_all_players_passed && team.workflow.is_gk_passed_met ? 'bg-emerald-200/70 text-emerald-900' : 'bg-blue-200/70 text-blue-900'">
                                {{ team.workflow.counts.players_passed }}/{{ team.workflow.counts.players_total }} SAH
                            </span>
                        </div>

                        <div
                            class="p-3 rounded-xl border flex items-center justify-between"
                            :class="team.workflow.is_roster_locked ? 'bg-emerald-50/60 border-emerald-200 text-emerald-950' : (team.workflow.is_ready_to_lock ? 'bg-orange-50 border-orange-300 text-orange-950' : 'bg-slate-50 border-slate-200 text-slate-600')"
                        >
                            <div>
                                <span class="text-[10px] font-extrabold uppercase block opacity-75">Tahap 5</span>
                                <span class="font-bold">Kunci Skuad Final</span>
                            </div>
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                :class="team.workflow.is_roster_locked ? 'bg-emerald-200/70 text-emerald-900' : (team.workflow.is_ready_to_lock ? 'bg-orange-600 text-white' : 'bg-slate-200 text-slate-700')"
                            >
                                {{ team.workflow.is_roster_locked ? 'TERKUNCI' : (team.workflow.is_ready_to_lock ? 'SIAP KUNCI' : 'BELUM') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Tabs Navigation -->
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-2">
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="
                        activeTab === 'players'
                            ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-sm'
                            : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200'
                    "
                    @click="activeTab = 'players'"
                >
                    Daftar Pemain &amp; Berkas ({{ players.length }})
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="
                        activeTab === 'officials'
                            ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-sm'
                            : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200'
                    "
                    @click="activeTab = 'officials'"
                >
                    Pelatih &amp; Ofisial ({{ officials.length }})
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer"
                    :class="
                        activeTab === 'administration'
                            ? 'bg-gradient-to-r from-orange-600 to-red-600 text-white shadow-sm'
                            : 'bg-white text-slate-600 hover:text-slate-900 border border-slate-200'
                    "
                    @click="activeTab = 'administration'"
                >
                    Profil Klub &amp; Riwayat Tagihan ({{ payments.length }})
                </button>
            </div>

            <!-- TAB 1: DAFTAR PEMAIN & BERKAS -->
            <div v-if="activeTab === 'players'" class="space-y-4">
                <!-- Filter & Search Bar for Players -->
                <div class="frosted-card p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white">
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <button
                            v-for="tab in [
                                { key: 'all', label: `Semua (${players.length})` },
                                { key: 'passed', label: `Sah (${team.workflow?.counts?.players_passed || 0})` },
                                { key: 'submitted', label: `Antrean (${team.workflow?.counts?.players_submitted || 0})` },
                                { key: 'rejected', label: `Revisi (${team.workflow?.counts?.players_rejected || 0})` },
                                { key: 'draft', label: `Draft (${team.workflow?.counts?.players_draft || 0})` },
                            ]"
                            :key="tab.key"
                            type="button"
                            class="px-3 py-1.5 rounded-lg font-bold transition-colors cursor-pointer"
                            :class="
                                playerStatusFilter === tab.key
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            "
                            @click="playerStatusFilter = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="w-full sm:w-64">
                        <input
                            v-model="playerSearch"
                            type="text"
                            placeholder="Cari nama, NIK, NISN, atau #punggung..."
                            class="input-field text-xs"
                        />
                    </div>
                </div>

                <div v-if="filteredPlayers.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500 bg-white">
                    <p class="font-bold text-slate-800 text-sm">Tidak ada data pemain yang sesuai filter</p>
                    <p class="mt-1 text-slate-400">Ubah kata kunci pencarian atau pilih tab status pemain lainnya.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="p in filteredPlayers"
                        :key="p.id"
                        class="frosted-card p-4 sm:p-5 bg-white transition-all space-y-3"
                        :class="
                            p.screening_status === 'rejected'
                                ? 'border-rose-300 bg-rose-50/20'
                                : p.screening_status === 'passed'
                                  ? 'border-emerald-200/90'
                                  : 'border-slate-200'
                        "
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                            <div class="flex items-center gap-3.5">
                                <div class="relative shrink-0">
                                    <button
                                        v-if="p.photo_url"
                                        type="button"
                                        class="block rounded-xl overflow-hidden border border-slate-200 shadow-2xs hover:ring-2 hover:ring-orange-500 transition-all cursor-pointer"
                                        title="Klik untuk memperbesar pas foto pemain"
                                        @click="openDocumentPreview({ url: p.photo_url, title: `Pas Foto - ${p.full_name}`, subtitle: `#${p.jersey_number} (${p.position})` })"
                                    >
                                        <img :src="p.photo_url" :alt="p.full_name" class="w-12 h-12 object-cover bg-slate-100" />
                                    </button>
                                    <div
                                        v-else
                                        class="w-12 h-12 rounded-xl bg-slate-100 text-slate-500 font-extrabold flex items-center justify-center text-[10px] border border-slate-200"
                                    >
                                        NO FOTO
                                    </div>
                                    <span class="absolute -bottom-1 -right-1 font-mono font-black text-[11px] bg-slate-900 text-white px-1.5 py-0.2 rounded-md shadow">
                                        #{{ p.jersey_number }}
                                    </span>
                                </div>

                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h4 class="text-sm sm:text-base font-bold text-slate-900">{{ p.full_name }}</h4>
                                        <span v-if="p.jersey_name" class="text-xs text-slate-400 font-medium">({{ p.jersey_name }})</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                                            {{ p.position }} &bull; Kaki {{ p.dominant_foot }}
                                        </span>
                                        <StatusBadge :status="p.screening_status" />
                                        <span
                                            v-if="p.accreditation_code"
                                            class="px-2 py-0.5 rounded font-mono text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                        >
                                            ID: {{ p.accreditation_code }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Link
                                    v-if="team.event?.id"
                                    :href="route('admin.screening.team', { event: team.event.id, team: team.id, player_id: p.id })"
                                    class="btn-sm-secondary inline-flex items-center gap-1"
                                >
                                    <span>Periksa di Workspace</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </Link>
                            </div>
                        </div>

                        <!-- Biodata & Document Chips Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-xs items-center">
                            <div class="lg:col-span-3 space-y-0.5">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Tempat, Tanggal Lahir &amp; Usia</span>
                                <span class="font-mono font-semibold text-slate-800 block">
                                    {{ p.birth_date_formatted || '-' }}
                                    <span v-if="p.age !== null" class="text-slate-500 font-sans">({{ p.age }} thn)</span>
                                </span>
                                <span class="text-[11px] text-slate-500 block truncate">{{ p.birth_place || '-' }}</span>
                            </div>

                            <div class="lg:col-span-3 space-y-0.5">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Identitas NIK &amp; NISN</span>
                                <span class="font-mono font-semibold text-slate-800 block">NIK: {{ p.nik || '-' }}</span>
                                <span class="text-[11px] font-mono text-slate-500 block">NISN: {{ p.nisn || '-' }}</span>
                            </div>

                            <div class="lg:col-span-2 space-y-0.5">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Postur &amp; Verifikator</span>
                                <span class="font-mono font-semibold text-slate-800 block">
                                    {{ p.height_cm ? `${p.height_cm} cm` : '-' }} / {{ p.weight_kg ? `${p.weight_kg} kg` : '-' }}
                                </span>
                                <span class="text-[11px] text-slate-500 block truncate">
                                    {{ p.verifier_name ? `Oleh: ${p.verifier_name}` : 'Belum diverifikasi' }}
                                </span>
                            </div>

                            <div class="lg:col-span-4 space-y-1">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">
                                    Berkas Dokumen Wajib (Klik untuk Pratinjau)
                                </span>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="doc in p.documents"
                                        :key="doc.type"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold border transition-all"
                                        :class="docChipClass(doc)"
                                        :disabled="!doc.uploaded"
                                        @click="
                                            doc.uploaded &&
                                                openDocumentPreview({
                                                    url: doc.url,
                                                    title: `${doc.label} - ${p.full_name}`,
                                                    subtitle: `Status: ${doc.validation_status.toUpperCase()}`,
                                                    isPdf: doc.is_pdf,
                                                })
                                        "
                                    >
                                        <span>{{ doc.label }}</span>
                                        <span class="uppercase opacity-80">
                                            ({{ !doc.uploaded ? 'Kosong' : doc.validation_status }})
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Rejection Note if any -->
                        <div
                            v-if="p.rejection_reason"
                            class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900"
                        >
                            <strong>Catatan Revisi Screening:</strong> {{ p.rejection_reason }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: PELATIH & OFISIAL -->
            <div v-else-if="activeTab === 'officials'" class="space-y-3">
                <div v-if="officials.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500 bg-white">
                    <p class="font-bold text-slate-800 text-sm">Belum ada pelatih atau ofisial yang didaftarkan oleh manajer</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="off in officials"
                        :key="off.id"
                        class="frosted-card p-4 sm:p-5 bg-white flex items-start justify-between gap-4"
                    >
                        <div class="flex items-start gap-3.5 min-w-0">
                            <button
                                v-if="off.photo_url"
                                type="button"
                                class="w-12 h-12 rounded-xl overflow-hidden border border-slate-200 shrink-0 cursor-pointer"
                                @click="openDocumentPreview({ url: off.photo_url, title: `Foto Ofisial - ${off.full_name}`, subtitle: off.role_label })"
                            >
                                <img :src="off.photo_url" :alt="off.full_name" class="w-full h-full object-cover" />
                            </button>
                            <div
                                v-else
                                class="w-12 h-12 rounded-xl bg-orange-100 text-orange-700 font-extrabold flex items-center justify-center text-sm shrink-0 border border-orange-200"
                            >
                                {{ off.full_name.charAt(0) }}
                            </div>

                            <div class="space-y-1 min-w-0">
                                <h4 class="text-sm font-bold text-slate-900 truncate">{{ off.full_name }}</h4>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-orange-50 text-orange-800 border border-orange-200">
                                    {{ off.role_label }}
                                </span>
                                <p class="text-xs text-slate-600 font-mono pt-1">
                                    Lisensi: <strong>{{ off.license_number || '-' }}</strong>
                                </p>
                                <p class="text-xs text-slate-600 font-mono">
                                    WhatsApp: <strong>{{ off.phone || '-' }}</strong>
                                </p>
                            </div>
                        </div>

                        <button
                            v-if="off.license_url"
                            type="button"
                            class="btn-sm-secondary shrink-0 cursor-pointer"
                            @click="
                                openDocumentPreview({
                                    url: off.license_url,
                                    title: `Lisensi Kepelatihan - ${off.full_name}`,
                                    subtitle: off.license_number || off.role_label,
                                    isPdf: off.license_is_pdf,
                                })
                            "
                        >
                            Lihat Lisensi
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 3: PROFIL KLUB & RIWAYAT TAGIHAN -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <div class="lg:col-span-5 frosted-card p-5 bg-white space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100">
                        Identitas Klub &amp; Kontak Manajer
                    </h3>
                    <dl class="space-y-3 text-xs">
                        <div>
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Nama Tim Resmi</dt>
                            <dd class="font-bold text-slate-900 mt-0.5">{{ team.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Asosiasi / Askot / Askab</dt>
                            <dd class="font-semibold text-slate-800 mt-0.5">{{ team.origin_association || '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Alamat Sekretariat</dt>
                            <dd class="text-slate-700 mt-0.5 leading-relaxed">{{ team.secretariat_address || '-' }}</dd>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Manajer Tim</dt>
                            <dd class="font-bold text-slate-900 mt-0.5">{{ team.manager_name }}</dd>
                            <dd class="text-slate-600 font-mono mt-0.5">{{ team.manager_email }}</dd>
                            <dd class="text-slate-600 font-mono mt-0.5">{{ team.manager_whatsapp }}</dd>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <dt class="text-[10px] font-bold uppercase text-slate-400">Pelatih Kepala (Head Coach)</dt>
                            <dd class="font-bold text-slate-900 mt-0.5">{{ team.head_coach_name || '-' }}</dd>
                            <dd class="text-slate-600 font-mono mt-0.5">Lisensi: {{ team.head_coach_license || '-' }}</dd>
                        </div>
                    </dl>

                    <!-- Catatan Internal Panitia -->
                    <div class="pt-4 border-t border-slate-200 space-y-2.5">
                        <h4 class="text-xs font-bold text-slate-900">Catatan Internal Panitia (Audit &amp; Koordinasi)</h4>
                        <form v-if="canManageStatus" @submit.prevent="saveAdminNotes" novalidate class="space-y-2">
                            <textarea
                                v-model="notesForm.admin_notes"
                                rows="4"
                                placeholder="Tambahkan catatan khusus panitia terkait tim ini..."
                                class="input-field text-xs font-mono"
                            ></textarea>
                            <InputError :message="notesForm.errors.admin_notes" />
                            <div class="flex justify-end">
                                <button type="submit" class="btn-sm-pitch cursor-pointer" :disabled="notesForm.processing">
                                    {{ notesForm.processing ? 'Menyimpan...' : 'Simpan Catatan Panitia' }}
                                </button>
                            </div>
                        </form>
                        <pre v-else class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 whitespace-pre-wrap font-mono">{{ team.admin_notes || 'Belum ada catatan internal panitia.' }}</pre>
                    </div>
                </div>

                <div class="lg:col-span-7 frosted-card p-5 bg-white space-y-4">
                    <h3 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100">
                        Riwayat Tagihan &amp; Bukti Pembayaran
                    </h3>

                    <div v-if="payments.length === 0" class="py-8 text-center text-xs text-slate-500">
                        Belum ada riwayat invoice untuk tim ini.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="pay in payments"
                            :key="pay.id"
                            class="p-4 rounded-xl border border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs"
                        >
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-slate-900">{{ pay.invoice_number }}</span>
                                    <StatusBadge :status="pay.status" />
                                </div>
                                <p class="font-mono font-black text-sm text-slate-900 tabular-data">
                                    {{ formatRupiah(pay.total_amount) }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    Metode: {{ pay.channel || 'Transfer Manual' }}
                                    <span v-if="pay.sender_bank_name"> &bull; Bank: {{ pay.sender_bank_name }} ({{ pay.sender_account_holder }})</span>
                                </p>
                            </div>

                            <button
                                type="button"
                                class="btn-sm-pitch cursor-pointer"
                                @click="openProofModal(pay)"
                            >
                                {{ pay.proof_url ? 'Lihat Bukti Transfer' : 'Detail Invoice' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Pratinjau Dokumen / Pas Foto -->
        <DocumentViewerModal
            :show="viewerState.show"
            :url="viewerState.url"
            :title="viewerState.title"
            :subtitle="viewerState.subtitle"
            :is-pdf="viewerState.isPdf"
            @close="closeDocumentPreview"
        />

        <!-- Modal Bukti Pembayaran -->
        <PaymentProofModal
            :show="isProofModalOpen"
            :payment="selectedPayment"
            :can-verify="true"
            @close="closeProofModal"
            @verified="onPaymentVerified"
        />

        <!-- Modal Kendali Status Tim (Unlock / Force Lock / Disqualify / Reactivate) -->
        <div
            v-if="statusActionModal.show"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            @click.self="closeStatusActionModal"
        >
            <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ statusActionModal.title }}</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ statusActionModal.description }}</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-700 text-lg leading-none" @click="closeStatusActionModal">
                        &times;
                    </button>
                </div>

                <form @submit.prevent="submitStatusAction" novalidate class="space-y-4">
                    <div v-if="statusActionModal.mode !== 'force_lock'">
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Alasan Resmi / Catatan Audit <span class="text-rose-600">*</span>
                        </label>
                        <textarea
                            v-model="statusActionForm.reason"
                            rows="3"
                            placeholder="Tuliskan alasan perubahan status tim (minimal 5 karakter)..."
                            class="input-field text-xs"
                            :class="{ 'input-field-error': statusActionForm.errors.reason }"
                            @input="statusActionForm.clearErrors('reason')"
                        ></textarea>
                        <InputError :message="statusActionForm.errors.reason || statusActionForm.errors.status || statusActionForm.errors.roster" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="closeStatusActionModal">
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="btn-pitch text-xs"
                            :disabled="statusActionForm.processing"
                        >
                            {{ statusActionForm.processing ? 'Memproses...' : 'Konfirmasi & Eksekusi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

