<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import DocumentViewerModal from '@/Components/DocumentViewerModal.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    team: {
        type: Object,
        required: true,
    },
    players: {
        type: Array,
        required: true,
    },
});

// Active Player State
const activePlayerIndex = ref(0);
const activePlayer = computed(() => props.players[activePlayerIndex.value] || null);

// Active Document State
const selectedDocIndex = ref(0);
const activeDoc = computed(() => {
    if (!activePlayer.value || !activePlayer.value.documents || activePlayer.value.documents.length === 0) {
        return null;
    }
    return activePlayer.value.documents[selectedDocIndex.value] || activePlayer.value.documents[0];
});

// Image Zoom & Viewer State
const imageZoom = ref(1);
const isDocViewerOpen = ref(false);

const zoomIn = () => {
    if (imageZoom.value < 2.5) imageZoom.value += 0.25;
};
const zoomOut = () => {
    if (imageZoom.value > 0.75) imageZoom.value -= 0.25;
};
const resetZoom = () => {
    imageZoom.value = 1;
};

// Select player handler
const selectPlayer = (index) => {
    activePlayerIndex.value = index;
    selectedDocIndex.value = 0;
    resetZoom();
};

const nextPlayer = () => {
    if (activePlayerIndex.value < props.players.length - 1) {
        selectPlayer(activePlayerIndex.value + 1);
    }
};

const prevPlayer = () => {
    if (activePlayerIndex.value > 0) {
        selectPlayer(activePlayerIndex.value - 1);
    }
};

// Operational Rules & Permissions
const isPlayerLocked = computed(() => {
    return Boolean(activePlayer.value?.is_locked || activePlayer.value?.screening_status === 'passed');
});

const isAgeEligible = computed(() => {
    return Boolean(activePlayer.value?.is_age_valid);
});

const hasInvalidDocument = computed(() => {
    return Boolean(activePlayer.value?.documents?.some((d) => d.validation_status === 'invalid'));
});

const hasDocuments = computed(() => {
    return Boolean(activePlayer.value?.documents && activePlayer.value.documents.length > 0);
});

const canApprovePlayer = computed(() => {
    if (!activePlayer.value) return false;
    if (isPlayerLocked.value) return false;
    if (!isAgeEligible.value) return false;
    if (hasInvalidDocument.value) return false;
    if (!hasDocuments.value) return false;
    if (verifyForm.processing) return false;
    return true;
});

const approveDisabledTooltip = computed(() => {
    if (!activePlayer.value) return '';
    if (isPlayerLocked.value) return 'Pemain sudah berstatus Sah dan terkunci permanen.';
    if (!isAgeEligible.value) return 'Tidak dapat disahkan: Tanggal lahir di luar batas regulasi kompetisi.';
    if (hasInvalidDocument.value) return 'Ada berkas berstatus Butuh Revisi. Berkas harus Sah sebelum pemain disahkan.';
    if (!hasDocuments.value) return 'Belum ada berkas dokumen yang diunggah untuk pemain ini.';
    return '';
});

const canRejectPlayer = computed(() => {
    if (!activePlayer.value) return false;
    if (isPlayerLocked.value) return false;
    if (verifyForm.processing) return false;
    return true;
});

const rejectDisabledTooltip = computed(() => {
    if (!activePlayer.value) return '';
    if (isPlayerLocked.value) return 'Pemain sudah berstatus Sah. Buka kunci pemain terlebih dahulu untuk merevisi.';
    return '';
});

// Verification Form
const verifyForm = useForm({
    action: 'passed',
    rejection_reason: '',
    documents: [],
});

const isRejectModalOpen = ref(false);
const isUnlockModalOpen = ref(false);
const unlockForm = useForm({
    reason: '',
});

// Approve Player (Loloskan Sah)
const handleApprove = () => {
    if (!canApprovePlayer.value) return;
    verifyForm.action = 'passed';
    verifyForm.rejection_reason = '';
    verifyForm.documents = activePlayer.value.documents.map((d) => ({
        id: d.id,
        validation_status: d.validation_status === 'invalid' ? 'valid' : d.validation_status,
        notes: d.notes,
    }));

    verifyForm.post(route('admin.screening.player.verify', { player: activePlayer.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            const nextIdx = props.players.findIndex(
                (p, idx) => idx > activePlayerIndex.value && p.screening_status === 'submitted'
            );
            if (nextIdx !== -1) {
                selectPlayer(nextIdx);
            }
        },
    });
};

// Open Reject Modal
const openRejectModal = () => {
    if (!canRejectPlayer.value) return;
    verifyForm.action = 'rejected';
    verifyForm.rejection_reason = activePlayer.value?.rejection_reason || '';
    isRejectModalOpen.value = true;
};

// Submit Rejection
const submitRejection = () => {
    if (!activePlayer.value || verifyForm.processing) return;
    verifyForm.post(route('admin.screening.player.verify', { player: activePlayer.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isRejectModalOpen.value = false;
            verifyForm.reset();
        },
    });
};

// Emergency Unlock
const submitUnlock = () => {
    if (!activePlayer.value || unlockForm.processing) return;
    unlockForm.post(route('admin.screening.player.unlock', { player: activePlayer.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isUnlockModalOpen.value = false;
            unlockForm.reset();
        },
    });
};

// Granular Verify Single Document
const setDocumentStatus = (doc, status) => {
    if (isPlayerLocked.value || doc.validation_status === status) return;
    router.post(
        route('admin.screening.document.verify', { document: doc.id }),
        {
            validation_status: status,
            notes: doc.notes || null,
        },
        { preserveScroll: true }
    );
};
</script>

<template>
    <AdminLayout
        :page-title="`Screening Berkas: ${team.name}`"
        :subtitle="`Pemeriksaan dokumen identitas skuad ${team.name} pada kompetisi ${event.name} (${event.age_category}).`"
    >
        <Head :title="`Screening - ${team.name}`" />

        <div class="space-y-4">
            <!-- Breadcrumbs & Quick Back Nav -->
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-1.5 text-slate-500">
                    <Link :href="route('admin.screening.index')" class="hover:text-orange-600 font-medium">
                        &larr; Kembali ke Antrean Tim
                    </Link>
                    <span>/</span>
                    <span class="font-bold text-slate-900">{{ team.name }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold text-slate-500">
                        Pemain: {{ activePlayerIndex + 1 }} dari {{ players.length }}
                    </span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="btn-sm-secondary px-2.5 py-1 text-xs"
                            :disabled="activePlayerIndex === 0"
                            @click="prevPlayer"
                        >
                            &larr; Prev
                        </button>
                        <button
                            type="button"
                            class="btn-sm-secondary px-2.5 py-1 text-xs"
                            :disabled="activePlayerIndex === players.length - 1"
                            @click="nextPlayer"
                        >
                            Next &rarr;
                        </button>
                    </div>
                </div>
            </div>

            <!-- Team Header Summary Bar -->
            <div class="frosted-card p-4 bg-white border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-50 border border-orange-200 text-orange-800 flex items-center justify-center font-black text-sm uppercase shrink-0 overflow-hidden">
                            <img v-if="team.logo_url" :src="team.logo_url" :alt="team.name" class="w-full h-full object-cover" />
                            <span v-else>{{ team.name.charAt(0) }}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-slate-900">{{ team.name }}</h3>
                                <span class="text-[11px] text-slate-400 font-mono">{{ team.origin_association || '-' }}</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Kategori: <strong class="text-slate-800">{{ event.age_category }}</strong> &bull;
                                Manajer: <strong class="text-slate-800">{{ team.manager_name }}</strong> ({{ team.manager_phone }}) &bull;
                                Pelatih: <strong class="text-slate-800">{{ team.head_coach_name || '-' }}</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Regulation Age Cut-Off Reference Box -->
                    <div class="p-2.5 rounded-xl bg-orange-50/70 border border-orange-200/80 text-[11px] text-orange-950 flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <span class="font-bold block">Regulasi Usia Resmi:</span>
                            <span class="text-orange-900/90 font-mono">
                                Lahir: {{ event.min_birth_date || 'Bebas' }} s/d {{ event.max_birth_date || 'Bebas' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Player Selector Horizontal Tabs Bar -->
            <div class="frosted-card p-2 bg-white overflow-x-auto scrollbar-thin">
                <div class="flex items-center gap-1.5 min-w-max">
                    <button
                        v-for="(p, idx) in players"
                        :key="p.id"
                        type="button"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all border"
                        :class="
                            activePlayerIndex === idx
                                ? 'bg-orange-600 text-white border-orange-600 shadow-sm'
                                : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                        "
                        @click="selectPlayer(idx)"
                    >
                        <span
                            class="font-mono font-bold px-1.5 py-0.5 rounded text-[10px]"
                            :class="activePlayerIndex === idx ? 'bg-orange-700 text-white' : 'bg-white text-slate-700 border border-slate-200'"
                        >
                            #{{ p.jersey_number }}
                        </span>
                        <span class="truncate max-w-[120px]">{{ p.full_name }}</span>

                        <!-- Mini Status Chip -->
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="
                                p.screening_status === 'passed'
                                    ? 'bg-emerald-400'
                                    : p.screening_status === 'rejected'
                                    ? 'bg-rose-400'
                                    : p.screening_status === 'submitted'
                                    ? 'bg-amber-400'
                                    : 'bg-slate-300'
                            "
                            :title="p.screening_status"
                        ></span>
                    </button>
                </div>
            </div>

            <!-- Main Split-View Review Area -->
            <div v-if="activePlayer" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- LEFT PANEL: Player Biodata, Photo, Age Cut-Off & Logs (5 cols on lg) -->
                <div class="lg:col-span-5 space-y-4">
                    <!-- Biodata Card -->
                    <div class="frosted-card p-5 bg-white space-y-4">
                        <div class="flex items-start gap-4">
                            <!-- Official Photo -->
                            <div class="w-20 h-24 rounded-xl border border-slate-200 bg-slate-50 overflow-hidden shrink-0 flex items-center justify-center shadow-xs">
                                <img
                                    v-if="activePlayer.photo_url"
                                    :src="activePlayer.photo_url"
                                    :alt="activePlayer.full_name"
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="text-center p-2 text-slate-400">
                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span class="text-[9px] block mt-1">Tanpa Foto</span>
                                </div>
                            </div>

                            <!-- Name, Jersey, & Status -->
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black bg-orange-600 text-white font-mono">
                                        #{{ activePlayer.jersey_number }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800 uppercase border border-slate-200">
                                        {{ activePlayer.position }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                        :class="
                                            activePlayer.screening_status === 'passed'
                                                ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                                                : activePlayer.screening_status === 'rejected'
                                                ? 'bg-rose-100 text-rose-800 border border-rose-300'
                                                : activePlayer.screening_status === 'submitted'
                                                ? 'bg-amber-100 text-amber-900 border border-amber-300'
                                                : 'bg-slate-100 text-slate-700'
                                        "
                                    >
                                        {{ activePlayer.screening_status }}
                                    </span>
                                </div>

                                <h2 class="text-base font-bold text-slate-900 leading-tight truncate">
                                    {{ activePlayer.full_name }}
                                </h2>
                                <p class="text-xs text-slate-500 truncate">
                                    Nama Punggung: <strong class="text-slate-800">{{ activePlayer.jersey_name || '-' }}</strong>
                                </p>
                                <p class="text-[11px] text-slate-400">
                                    Kaki Dominan: {{ activePlayer.dominant_foot || 'Right' }} &bull;
                                    TB/BB: {{ activePlayer.height_cm ? `${activePlayer.height_cm} cm` : '-' }} / {{ activePlayer.weight_kg ? `${activePlayer.weight_kg} kg` : '-' }}
                                </p>
                            </div>
                        </div>

                        <!-- Age Cut-Off Verification Card -->
                        <div
                            class="p-3.5 rounded-xl border text-xs space-y-1.5"
                            :class="activePlayer.is_age_valid ? 'bg-emerald-50/70 border-emerald-300 text-emerald-950' : 'bg-rose-50 border-rose-300 text-rose-950'"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-bold flex items-center gap-1.5">
                                    <svg v-if="activePlayer.is_age_valid" class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Pemeriksaan Batas Kelahiran (Usia)</span>
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                    :class="activePlayer.is_age_valid ? 'bg-emerald-200 text-emerald-900' : 'bg-rose-200 text-rose-900'"
                                >
                                    {{ activePlayer.is_age_valid ? 'Sah Sesuai Regulasi' : 'Melebihi / Di Luar Batas Usia' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-[11px] pt-1 border-t border-slate-200/60 font-mono">
                                <div>
                                    <span class="text-slate-500 block text-[10px]">Tanggal Lahir:</span>
                                    <span class="font-bold text-slate-800">{{ activePlayer.birth_date_formatted }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500 block text-[10px]">Tempat Lahir:</span>
                                    <span class="font-bold text-slate-800">{{ activePlayer.birth_place || '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- National Identity Information -->
                        <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Nomor Induk Kependudukan (NIK)</span>
                                <span class="font-mono font-bold text-slate-900 tabular-data text-xs">{{ activePlayer.nik }}</span>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Nomor Induk Siswa (NISN)</span>
                                <span class="font-mono font-bold text-slate-900 tabular-data text-xs">{{ activePlayer.nisn || '-' }}</span>
                            </div>
                        </div>

                        <!-- Digital Accreditation Code if passed -->
                        <div v-if="activePlayer.accreditation_code" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] font-bold uppercase block text-emerald-700">Kode Pengesahan Digital</span>
                                <span class="font-mono font-bold text-emerald-950">{{ activePlayer.accreditation_code }}</span>
                            </div>
                            <span class="text-[10px] text-emerald-700">Terverifikasi: {{ activePlayer.verified_at }}</span>
                        </div>

                        <!-- Rejection Note if any -->
                        <div v-if="activePlayer.rejection_reason" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900 space-y-1">
                            <span class="font-bold block text-rose-800">Catatan Penolakan Terakhir:</span>
                            <p class="text-rose-700 leading-relaxed">{{ activePlayer.rejection_reason }}</p>
                        </div>
                    </div>

                    <!-- Screening Audit Trail Mini Logs -->
                    <div v-if="activePlayer.logs && activePlayer.logs.length > 0" class="frosted-card p-4 bg-white space-y-2.5">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Histori Pemeriksaan Pemain</h4>
                        <div class="space-y-2">
                            <div
                                v-for="log in activePlayer.logs"
                                :key="log.id"
                                class="text-[11px] p-2.5 rounded-lg border border-slate-100 bg-slate-50/70 space-y-0.5"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800">{{ log.actor_name }} &bull; {{ log.to_status.toUpperCase() }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">{{ log.created_at }}</span>
                                </div>
                                <p class="text-slate-600 text-[10px] leading-relaxed">{{ log.reason }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Document Viewer & Granular Verification (7 cols on lg) -->
                <div class="lg:col-span-7 flex flex-col space-y-4">
                    <div class="frosted-card p-5 bg-white flex-1 flex flex-col space-y-4">
                        <!-- Document Tabs Header -->
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-200">
                            <div>
                                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Berkas Dokumen Lampiran</h3>
                                <p class="text-[11px] text-slate-500">Pilih dokumen untuk memeriksa scan asli</p>
                            </div>

                            <!-- Document Tab Selector Pills -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <button
                                    v-for="(doc, dIdx) in activePlayer.documents"
                                    :key="doc.id"
                                    type="button"
                                    class="px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5 border transition-all"
                                    :class="
                                        selectedDocIndex === dIdx
                                            ? 'bg-orange-600 text-white border-orange-600 shadow-2xs'
                                            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                    "
                                    @click="selectedDocIndex = dIdx"
                                >
                                    <span>{{ doc.label }}</span>
                                    <span class="inline-flex items-center">
                                        <svg v-if="doc.validation_status === 'valid'" class="w-3.5 h-3.5 shrink-0" :class="selectedDocIndex === dIdx ? 'text-white' : 'text-emerald-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <svg v-else-if="doc.validation_status === 'invalid'" class="w-3.5 h-3.5 shrink-0" :class="selectedDocIndex === dIdx ? 'text-white' : 'text-rose-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <svg v-else class="w-3.5 h-3.5 shrink-0" :class="selectedDocIndex === dIdx ? 'text-white' : 'text-amber-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Active Document Viewer Container -->
                        <div v-if="activeDoc" class="flex-1 flex flex-col space-y-3">
                            <!-- Document Details & Granular Status Control Bar -->
                            <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 truncate">{{ activeDoc.original_name || activeDoc.label }}</span>
                                        <span class="font-mono text-slate-400 text-[11px]">({{ (activeDoc.file_size / 1024).toFixed(0) }} KB)</span>
                                    </div>
                                    <p v-if="activeDoc.document_number" class="text-[11px] text-slate-500 font-mono mt-0.5">
                                        No. Dokumen: <strong>{{ activeDoc.document_number }}</strong>
                                    </p>
                                </div>

                                <!-- Action Buttons: Mark Valid / Invalid per Document -->
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="px-2.5 py-1 rounded-lg text-xs font-bold border transition-colors flex items-center gap-1.5"
                                        :class="[
                                            activeDoc.validation_status === 'valid'
                                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-2xs'
                                                : 'bg-white text-emerald-700 border-emerald-200 hover:bg-emerald-50',
                                            isPlayerLocked ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                        :disabled="isPlayerLocked || activeDoc.validation_status === 'valid'"
                                        :title="isPlayerLocked ? 'Pemain sudah terkunci sah' : (activeDoc.validation_status === 'valid' ? 'Dokumen sudah berstatus sah' : 'Tandai dokumen sah')"
                                        @click="setDocumentStatus(activeDoc, 'valid')"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Dokumen Sah</span>
                                    </button>

                                    <button
                                        type="button"
                                        class="px-2.5 py-1 rounded-lg text-xs font-bold border transition-colors flex items-center gap-1.5"
                                        :class="[
                                            activeDoc.validation_status === 'invalid'
                                                ? 'bg-rose-600 text-white border-rose-600 shadow-2xs'
                                                : 'bg-white text-rose-700 border-rose-200 hover:bg-rose-50',
                                            isPlayerLocked ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                        :disabled="isPlayerLocked || activeDoc.validation_status === 'invalid'"
                                        :title="isPlayerLocked ? 'Pemain sudah terkunci sah' : (activeDoc.validation_status === 'invalid' ? 'Dokumen sudah ditandai perlu revisi' : 'Tandai dokumen butuh revisi')"
                                        @click="setDocumentStatus(activeDoc, 'invalid')"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span>Butuh Revisi</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Document Inspection Canvas (PDF / Image) -->
                            <div class="relative bg-slate-900/5 rounded-2xl border border-slate-200/80 p-3 min-h-[380px] max-h-[520px] flex items-center justify-center overflow-hidden">
                                <!-- PDF Document View -->
                                <div v-if="activeDoc.is_pdf" class="w-full h-full flex flex-col items-center justify-center space-y-3 p-2">
                                    <iframe
                                        :src="activeDoc.preview_url"
                                        class="w-full h-[400px] rounded-xl border border-slate-200 bg-white shadow-inner"
                                        title="Pratinjau PDF"
                                    ></iframe>

                                    <div class="flex items-center gap-2 text-xs">
                                        <button
                                            type="button"
                                            class="btn-sm-pitch inline-flex items-center gap-1.5 shadow-2xs"
                                            @click="isDocViewerOpen = true"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                            </svg>
                                            <span>Perbesar di Modal View</span>
                                        </button>

                                        <a
                                            :href="activeDoc.preview_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn-sm-secondary inline-flex items-center gap-1.5"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                            <span>Tab Baru</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Image Document View -->
                                <div v-else class="relative w-full h-full flex items-center justify-center overflow-auto">
                                    <img
                                        :src="activeDoc.preview_url"
                                        :alt="activeDoc.label"
                                        class="max-w-full max-h-[460px] object-contain rounded-lg shadow-sm transition-transform duration-200 cursor-zoom-in"
                                        :style="{ transform: `scale(${imageZoom})` }"
                                        @click="isDocViewerOpen = true"
                                    />

                                    <!-- Floating Image Zoom Toolbar -->
                                    <div class="absolute bottom-3 right-3 bg-slate-900/85 backdrop-blur-md rounded-xl p-1.5 flex items-center gap-1 shadow-md text-white text-xs border border-white/10">
                                        <button type="button" class="px-2 py-1 hover:bg-white/20 rounded" title="Zoom Out" @click="zoomOut">-</button>
                                        <span class="font-mono text-[10px] px-1 font-semibold">{{ Math.round(imageZoom * 100) }}%</span>
                                        <button type="button" class="px-2 py-1 hover:bg-white/20 rounded" title="Zoom In" @click="zoomIn">+</button>
                                        <button type="button" class="px-2 py-1 hover:bg-white/20 rounded text-[11px]" title="Reset Zoom" @click="resetZoom">1:1</button>
                                        <button
                                            type="button"
                                            class="px-2 py-1 bg-orange-600 hover:bg-orange-700 rounded font-semibold ml-1 inline-flex items-center gap-1 shadow-2xs"
                                            @click="isDocViewerOpen = true"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                            </svg>
                                            <span>Modal View</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No Documents Uploaded State -->
                        <div v-else class="flex-1 flex flex-col items-center justify-center p-12 text-center text-slate-400 space-y-2">
                            <svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-xs font-bold text-slate-700">Belum Ada Dokumen Diunggah</p>
                            <p class="text-[11px] text-slate-400">Manajer tim belum melampirkan berkas scan untuk pemain ini.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Operational Decision Bar (Fixed / Sticky Footer) -->
            <div v-if="activePlayer" class="sticky bottom-4 z-40">
                <div class="frosted-card p-4 bg-slate-900/90 text-white backdrop-blur-xl border border-slate-700 shadow-2xl flex flex-wrap items-center justify-between gap-3 rounded-2xl">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-orange-600 text-white font-mono font-bold flex items-center justify-center text-sm">
                            #{{ activePlayer.jersey_number }}
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-white">{{ activePlayer.full_name }}</span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                    :class="
                                        activePlayer.screening_status === 'passed'
                                            ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                                            : activePlayer.screening_status === 'rejected'
                                            ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                                            : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                                    "
                                >
                                    {{ activePlayer.screening_status.toUpperCase() }}
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-400 block mt-0.5">
                                Verifikasi pemain nomor punggung {{ activePlayer.jersey_number }}
                            </span>
                        </div>
                    </div>

                    <!-- Action Decision Buttons -->
                    <div class="flex items-center gap-2.5">
                        <!-- Emergency Unlock if already passed -->
                        <button
                            v-if="isPlayerLocked"
                            type="button"
                            class="px-3 py-2 rounded-xl text-xs font-semibold bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 border border-slate-700 transition-colors flex items-center gap-1.5"
                            @click="isUnlockModalOpen = true"
                        >
                            <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            <span>Buka Kunci (Unlock)</span>
                        </button>

                        <!-- Reject Button -->
                        <div class="relative group">
                            <button
                                type="button"
                                class="btn-sm-danger px-4 py-2 text-xs font-bold shadow-md flex items-center gap-1.5"
                                :class="{ 'opacity-50 cursor-not-allowed': !canRejectPlayer }"
                                :disabled="!canRejectPlayer"
                                @click="openRejectModal"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>{{ activePlayer.screening_status === 'rejected' ? 'Ubah Catatan Penolakan' : 'Tolak / Minta Revisi' }}</span>
                            </button>
                            <div
                                v-if="rejectDisabledTooltip"
                                class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-950 text-white text-[11px] px-2.5 py-1 rounded-lg shadow-xl whitespace-nowrap z-50 border border-slate-700 pointer-events-none"
                            >
                                {{ rejectDisabledTooltip }}
                            </div>
                        </div>

                        <!-- Approve Button -->
                        <div class="relative group">
                            <button
                                type="button"
                                class="px-5 py-2 rounded-xl text-xs font-bold text-white transition-all flex items-center gap-1.5 shadow-md"
                                :class="[
                                    canApprovePlayer
                                        ? 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 shadow-emerald-600/30 cursor-pointer'
                                        : 'bg-slate-700 text-slate-400 opacity-60 cursor-not-allowed'
                                ]"
                                :disabled="!canApprovePlayer"
                                @click="handleApprove"
                            >
                                <svg v-if="verifyForm.processing" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>
                                    {{
                                        verifyForm.processing
                                            ? 'Menyimpan...'
                                            : (isPlayerLocked ? 'Pemain Sudah Sah' : 'Loloskan Pemain (Sah)')
                                    }}
                                </span>
                            </button>
                            <div
                                v-if="approveDisabledTooltip"
                                class="absolute bottom-full mb-2 right-0 hidden group-hover:block bg-slate-950 text-white text-[11px] px-2.5 py-1 rounded-lg shadow-xl whitespace-nowrap z-50 border border-slate-700 pointer-events-none"
                            >
                                {{ approveDisabledTooltip }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Viewer Modal (Replaces old image-only lightbox) -->
            <DocumentViewerModal
                :is-open="isDocViewerOpen"
                :title="activeDoc?.label || 'Pratinjau Dokumen'"
                :subtitle="`Pemain: ${activePlayer?.full_name} (#${activePlayer?.jersey_number})`"
                :file-url="activeDoc?.preview_url || ''"
                :is-pdf="Boolean(activeDoc?.is_pdf)"
                :original-name="activeDoc?.original_name || ''"
                :document-number="activeDoc?.document_number || ''"
                :file-size="activeDoc?.file_size || 0"
                :validation-status="activeDoc?.validation_status || ''"
                :notes="activeDoc?.notes || ''"
                @close="isDocViewerOpen = false"
            />

            <!-- Rejection Modal -->
            <div
                v-if="isRejectModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            >
                <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Tolak Berkas Pemain</h3>
                            <p class="text-xs text-slate-500">Pemain: {{ activePlayer?.full_name }} (#{{ activePlayer?.jersey_number }})</p>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="isRejectModalOpen = false">&times;</button>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        Tuliskan instruksi atau alasan spesifik mengapa berkas pemain ini ditolak agar manajer tim dapat segera memperbaikinya di portal manajer:
                    </p>

                    <form @submit.prevent="submitRejection" novalidate class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Alasan Penolakan (Minimal 10 Karakter)</label>
                            <textarea
                                v-model="verifyForm.rejection_reason"
                                rows="3"
                                placeholder="Contoh: Foto akta kelahiran buram pada bagian tanggal lahir. Mohon unggah ulang foto yang jelas dan terbaca."
                                class="input-field text-xs resize-none"
                                :class="{ 'input-field-error': verifyForm.errors.rejection_reason }"
                            ></textarea>
                            <InputError :message="verifyForm.errors.rejection_reason" />
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2 text-xs">
                            <button type="button" class="btn-secondary" @click="isRejectModalOpen = false">Batal</button>
                            <button type="submit" class="btn-danger" :disabled="verifyForm.processing">
                                {{ verifyForm.processing ? 'Menyimpan...' : 'Konfirmasi Tolak Berkas' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Emergency Unlock Modal -->
            <div
                v-if="isUnlockModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
            >
                <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Buka Kunci Pemain (Unlock)</h3>
                            <p class="text-xs text-slate-500">Pemain: {{ activePlayer?.full_name }} (#{{ activePlayer?.jersey_number }})</p>
                        </div>
                        <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="isUnlockModalOpen = false">&times;</button>
                    </div>

                    <p class="text-xs text-amber-900 bg-amber-50 p-3 rounded-xl border border-amber-200 leading-relaxed">
                        Tindakan ini akan membatalkan status lolos sah dan mengembalikan pemain ke status <strong>Draft</strong> agar manajer tim dapat mengubah data/berkas.
                    </p>

                    <form @submit.prevent="submitUnlock" novalidate class="space-y-3.5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Alasan Pembukaan Kunci (Opsional)</label>
                            <input
                                v-model="unlockForm.reason"
                                type="text"
                                placeholder="Contoh: Koreksi nomor punggung atau berkas akta keliru"
                                class="input-field text-xs"
                            />
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2 text-xs">
                            <button type="button" class="btn-secondary" @click="isUnlockModalOpen = false">Batal</button>
                            <button type="submit" class="btn-pitch" :disabled="unlockForm.processing">
                                {{ unlockForm.processing ? 'Membuka Kunci...' : 'Ya, Buka Kunci Pemain' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

