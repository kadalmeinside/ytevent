<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
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

// Active Player State (supports ?player_id= query parameter or defaults to first submitted player)
const resolveInitialPlayerIndex = () => {
    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search);
        const requestedId = Number(params.get('player_id'));
        if (requestedId) {
            const idx = props.players.findIndex((p) => Number(p.id) === requestedId);
            if (idx !== -1) return idx;
        }
    }
    const firstSubmittedIdx = props.players.findIndex((p) => p.screening_status === 'submitted');
    return firstSubmittedIdx !== -1 ? firstSubmittedIdx : 0;
};

const activePlayerIndex = ref(resolveInitialPlayerIndex());
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

// Quick Verification Wizard & Confirmation State
const isWizardModalOpen = ref(false);
const wizardDocIndex = ref(0);
const wizardNotes = ref('');
const isSubmittingDoc = ref(false);
const wizardZoom = ref(1);
const isApproveConfirmModalOpen = ref(false);

const zoomIn = () => {
    if (imageZoom.value < 2.5) imageZoom.value += 0.25;
};
const zoomOut = () => {
    if (imageZoom.value > 0.75) imageZoom.value -= 0.25;
};
const resetZoom = () => {
    imageZoom.value = 1;
};

const wizardZoomIn = () => {
    if (wizardZoom.value < 2.5) wizardZoom.value += 0.25;
};
const wizardZoomOut = () => {
    if (wizardZoom.value > 0.75) wizardZoom.value -= 0.25;
};
const resetWizardZoom = () => {
    wizardZoom.value = 1;
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

const hasPendingDocuments = computed(() => {
    return Boolean(activePlayer.value?.documents?.some((d) => d.validation_status === 'pending'));
});

const allDocumentsValid = computed(() => {
    return Boolean(
        activePlayer.value?.documents &&
        activePlayer.value.documents.length > 0 &&
        activePlayer.value.documents.every((d) => d.validation_status === 'valid')
    );
});

const invalidDocuments = computed(() => {
    return activePlayer.value?.documents?.filter((d) => d.validation_status === 'invalid') || [];
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

const activeWizardDoc = computed(() => {
    if (!activePlayer.value?.documents?.length) return null;
    return activePlayer.value.documents[wizardDocIndex.value] || null;
});

const openWizard = (startDocIndex = 0) => {
    if (isPlayerLocked.value || !activePlayer.value?.documents?.length) return;
    let initialIndex = startDocIndex;
    if (startDocIndex === 0) {
        const pendingIdx = activePlayer.value.documents.findIndex((d) => d.validation_status === 'pending');
        if (pendingIdx !== -1) {
            initialIndex = pendingIdx;
        }
    }
    wizardDocIndex.value = Math.max(0, Math.min(initialIndex, activePlayer.value.documents.length - 1));
    wizardNotes.value = activePlayer.value.documents[wizardDocIndex.value]?.notes || '';
    resetWizardZoom();
    isWizardModalOpen.value = true;
};

const closeWizard = () => {
    isWizardModalOpen.value = false;
    resetWizardZoom();
};

const selectWizardDoc = (index) => {
    wizardDocIndex.value = index;
    if (activePlayer.value?.documents?.[index]) {
        wizardNotes.value = activePlayer.value.documents[index].notes || '';
    }
    resetWizardZoom();
};

const submitWizardDocStatus = async (status) => {
    if (!activeWizardDoc.value || isSubmittingDoc.value) return;

    isSubmittingDoc.value = true;
    const doc = activeWizardDoc.value;
    const notes = status === 'invalid' ? (wizardNotes.value || 'Dokumen memerlukan perbaikan.') : null;

    try {
        const res = await window.axios.post(route('admin.screening.document.verify', { document: doc.id }), {
            validation_status: status,
            notes: notes,
        });

        // Update status dokumen lokal langsung secara reaktif
        doc.validation_status = status;
        doc.notes = notes;
        if (res.data?.player && activePlayer.value) {
            activePlayer.value.screening_status = res.data.player.screening_status;
            activePlayer.value.is_locked = res.data.player.is_locked;
            activePlayer.value.rejection_reason = res.data.player.rejection_reason;
        }

        // Auto advance ke dokumen berikutnya TANPA reload halaman dan TANPA menutup modal
        if (wizardDocIndex.value < activePlayer.value.documents.length - 1) {
            wizardDocIndex.value++;
            wizardNotes.value = activePlayer.value.documents[wizardDocIndex.value]?.notes || '';
            resetWizardZoom();
        } else {
            // Berada di dokumen terakhir, alihkan ke langkah review final di dalam modal
            wizardDocIndex.value = activePlayer.value.documents.length;
        }
    } catch (error) {
        console.error('Gagal memperbarui status dokumen:', error);
    } finally {
        isSubmittingDoc.value = false;
    }
};

// Tombol Loloskan Pemain (Sah) - Memicu Wizard jika ada dokumen pending, atau Modal Konfirmasi jika sudah sah semua
const handleApproveClick = () => {
    if (!canApprovePlayer.value) return;

    // Jika masih ada dokumen yang belum diverifikasi (status pending), buka wizard verifikasi cepat
    if (hasPendingDocuments.value) {
        openWizard();
        return;
    }

    // Jika seluruh dokumen sudah sah (valid), buka modal konfirmasi
    if (allDocumentsValid.value) {
        isApproveConfirmModalOpen.value = true;
        return;
    }
};

// Eksekusi Loloskan Pemain Sah
const submitFinalApprove = () => {
    if (!canApprovePlayer.value || verifyForm.processing) return;
    verifyForm.action = 'passed';
    verifyForm.rejection_reason = '';
    verifyForm.documents = activePlayer.value.documents.map((d) => ({
        id: d.id,
        validation_status: 'valid',
        notes: d.notes,
    }));

    verifyForm.post(route('admin.screening.player.verify', { player: activePlayer.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isWizardModalOpen.value = false;
            isApproveConfirmModalOpen.value = false;
            const nextIdx = props.players.findIndex(
                (p, idx) => idx > activePlayerIndex.value && p.screening_status === 'submitted'
            );
            if (nextIdx !== -1) {
                selectPlayer(nextIdx);
            }
        },
    });
};

const goToRejectFromWizard = () => {
    isWizardModalOpen.value = false;
    const invalidList = invalidDocuments.value;
    if (invalidList.length > 0) {
        const reasons = invalidList
            .map((d) => `${d.label}: ${d.notes || 'Berkas tidak sesuai / tidak terbaca'}`)
            .join('; ');
        verifyForm.rejection_reason = `Mohon perbaiki berkas: ${reasons}`;
    }
    openRejectModal();
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
const setDocumentStatus = async (doc, status) => {
    if (isPlayerLocked.value || doc.validation_status === status) return;
    try {
        const res = await window.axios.post(route('admin.screening.document.verify', { document: doc.id }), {
            validation_status: status,
            notes: doc.notes || null,
        });
        doc.validation_status = status;
        if (res.data?.player && activePlayer.value) {
            activePlayer.value.screening_status = res.data.player.screening_status;
            activePlayer.value.is_locked = res.data.player.is_locked;
            activePlayer.value.rejection_reason = res.data.player.rejection_reason;
        }
    } catch (error) {
        console.error('Gagal memperbarui status dokumen:', error);
    }
};

const handleKeydown = (e) => {
    if (e.key !== 'Escape') return;
    if (isDocViewerOpen.value) {
        isDocViewerOpen.value = false;
        return;
    }
    if (isApproveConfirmModalOpen.value) {
        isApproveConfirmModalOpen.value = false;
        return;
    }
    if (isWizardModalOpen.value) {
        closeWizard();
        return;
    }
    if (isUnlockModalOpen.value) {
        isUnlockModalOpen.value = false;
        return;
    }
    if (isRejectModalOpen.value) {
        isRejectModalOpen.value = false;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
});

const playerStatusBadgeLabel = (status) => {
    if (status === 'passed') return 'SAH';
    if (status === 'rejected') return 'REVISI';
    if (status === 'submitted') return 'REVIEW';
    return 'DRAFT';
};
</script>

<template>
    <AdminLayout
        :page-title="`Screening Berkas: ${team.name}`"
        :subtitle="`Pemeriksaan dokumen identitas skuad ${team.name} pada kompetisi ${event.name} (${event.age_category}).`"
    >
        <Head :title="`Screening - ${team.name}`" />

        <div class="space-y-4">
            <!-- Team All Passed / Accreditation Readiness Banner -->
            <div
                v-if="team.screening_status === 'all_passed' || (players.length > 0 && players.every((p) => p.screening_status === 'passed'))"
                class="rounded-2xl border p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
                :class="
                    team.status === 'locked_roster'
                        ? 'bg-emerald-50/90 border-emerald-300 text-emerald-950'
                        : 'bg-amber-50/90 border-amber-300 text-amber-950'
                "
            >
                <div class="flex items-start gap-3">
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5 text-white"
                        :class="team.status === 'locked_roster' ? 'bg-emerald-600' : 'bg-amber-600'"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xs sm:text-sm font-extrabold">
                            {{
                                team.status === 'locked_roster'
                                    ? 'Seluruh Pemain Lolos & Skuad Final Telah Dikunci Manajer'
                                    : 'Seluruh Pemain Tim Ini Telah Disahkan (Lolos Screening 100%)'
                            }}
                        </h2>
                        <p class="text-xs mt-0.5 leading-relaxed opacity-90">
                            {{
                                team.status === 'locked_roster'
                                    ? 'Tim telah menyelesaikan proses akreditasi. Panitia dapat mengunduh salinan Album Roster dan ID Card resmi di bawah ini.'
                                    : 'Portal Manajer Tim saat ini menampilkan peringatan akreditasi agar manajer segera menekan tombol Kunci Skuad Final.'
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <a
                        :href="route('admin.teams.accreditation.roster-sheet', { team: team.id })"
                        class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-bold bg-white text-slate-800 border border-slate-300 hover:bg-slate-50 transition-colors"
                    >
                        <span>Unduh Roster PDF</span>
                    </a>
                    <a
                        :href="route('admin.teams.accreditation.id-cards', { team: team.id })"
                        class="inline-flex items-center gap-1.5 px-3 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors"
                    >
                        <span>Unduh ID Card PDF</span>
                    </a>
                </div>
            </div>

            <!-- Breadcrumbs & Quick Back Nav -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-1.5 text-slate-600">
                    <Link :href="route('admin.screening.index')" class="hover:text-orange-700 font-medium inline-flex items-center gap-1 min-h-[44px] sm:min-h-0">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        <span>Kembali ke Antrean Tim</span>
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span class="font-bold text-slate-900">{{ team.name }}</span>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 tabular-nums">
                        Pemain: {{ activePlayerIndex + 1 }} dari {{ players.length }}
                    </span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="btn-sm-secondary px-2.5 py-1 text-xs inline-flex items-center gap-1"
                            :disabled="activePlayerIndex === 0"
                            @click="prevPlayer"
                        >
                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span>Prev</span>
                        </button>
                        <button
                            type="button"
                            class="btn-sm-secondary px-2.5 py-1 text-xs inline-flex items-center gap-1"
                            :disabled="activePlayerIndex === players.length - 1"
                            @click="nextPlayer"
                        >
                            <span>Next</span>
                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Team Header Summary Bar -->
            <div class="frosted-card p-4 bg-white border border-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-50 border border-orange-200 text-orange-800 flex items-center justify-center font-black text-sm uppercase shrink-0 overflow-hidden">
                            <img v-if="team.logo_url" :src="team.logo_url" :alt="`Logo ${team.name}`" width="40" height="40" class="w-full h-full object-cover" />
                            <span v-else>{{ team.name.charAt(0) }}</span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-sm font-bold text-slate-900">{{ team.name }}</h2>
                                <span class="text-xs text-slate-600">{{ team.origin_association || '-' }}</span>
                            </div>
                            <p class="text-xs text-slate-600 mt-0.5">
                                Kategori: <strong class="text-slate-800">{{ event.age_category }}</strong> &bull;
                                Manajer: <strong class="text-slate-800">{{ team.manager_name }}</strong> (<span class="tabular-nums">{{ team.manager_phone }}</span>) &bull;
                                Pelatih: <strong class="text-slate-800">{{ team.head_coach_name || '-' }}</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Regulation Age Cut-Off Reference Box -->
                    <div class="p-2.5 rounded-xl bg-orange-50/70 border border-orange-200/80 text-xs text-orange-950 flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-600 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <span class="font-bold block">Regulasi Usia Resmi:</span>
                            <span class="text-orange-900 tabular-nums">
                                <template v-if="!event.min_birth_date && !event.max_birth_date">Semua Usia (Tanpa Batas)</template>
                                <template v-else-if="event.min_birth_date && !event.max_birth_date">Lahir mulai {{ event.min_birth_date }}</template>
                                <template v-else-if="!event.min_birth_date && event.max_birth_date">Lahir maks. {{ event.max_birth_date }}</template>
                                <template v-else>Lahir: {{ event.min_birth_date }} s/d {{ event.max_birth_date }}</template>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Player Selector Horizontal Tabs Bar -->
            <div class="frosted-card p-2 bg-white overflow-x-auto scrollbar-thin">
                <div role="tablist" aria-label="Daftar pemain dalam skuad" class="flex items-center gap-1.5 min-w-max">
                    <button
                        v-for="(p, idx) in players"
                        :key="p.id"
                        type="button"
                        role="tab"
                        :aria-selected="activePlayerIndex === idx"
                        class="px-3 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-semibold flex items-center gap-2 transition-colors border"
                        :class="
                            activePlayerIndex === idx
                                ? 'bg-orange-600 text-white border-orange-600'
                                : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                        "
                        @click="selectPlayer(idx)"
                    >
                        <span
                            class="font-bold px-1.5 py-0.5 rounded text-xs tabular-nums"
                            :class="activePlayerIndex === idx ? 'bg-orange-700 text-white' : 'bg-white text-slate-700 border border-slate-200'"
                        >
                            #{{ p.jersey_number }}
                        </span>
                        <span class="truncate max-w-[120px]">{{ p.full_name }}</span>

                        <span
                            class="px-1.5 py-0.5 rounded text-[11px] font-bold"
                            :class="
                                activePlayerIndex === idx
                                    ? 'bg-white/20 text-white'
                                    : (p.screening_status === 'passed'
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : p.screening_status === 'rejected'
                                        ? 'bg-rose-100 text-rose-800'
                                        : p.screening_status === 'submitted'
                                        ? 'bg-amber-100 text-amber-900'
                                        : 'bg-slate-200 text-slate-700')
                            "
                        >
                            {{ playerStatusBadgeLabel(p.screening_status) }}
                        </span>
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
                            <div class="w-20 h-24 rounded-xl border border-slate-200 bg-slate-50 overflow-hidden shrink-0 flex items-center justify-center">
                                <img
                                    v-if="activePlayer.photo_url"
                                    :src="activePlayer.photo_url"
                                    :alt="`Foto ${activePlayer.full_name}`"
                                    width="80"
                                    height="96"
                                    class="w-full h-full object-cover"
                                />
                                <div v-else class="text-center p-2 text-slate-600">
                                    <svg class="w-8 h-8 mx-auto" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <span class="text-xs block mt-1">Tanpa Foto</span>
                                </div>
                            </div>

                            <!-- Name, Jersey, & Status -->
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="px-2 py-0.5 rounded text-xs font-black bg-orange-600 text-white tabular-nums">
                                        #{{ activePlayer.jersey_number }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-800 uppercase border border-slate-200">
                                        {{ activePlayer.position }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 rounded text-xs font-bold"
                                        :class="
                                            activePlayer.screening_status === 'passed'
                                                ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                                                : activePlayer.screening_status === 'rejected'
                                                ? 'bg-rose-100 text-rose-800 border border-rose-300'
                                                : activePlayer.screening_status === 'submitted'
                                                ? 'bg-amber-100 text-amber-900 border border-amber-300'
                                                : 'bg-slate-100 text-slate-700 border border-slate-200'
                                        "
                                    >
                                        {{ playerStatusBadgeLabel(activePlayer.screening_status) }}
                                    </span>
                                </div>

                                <h2 class="text-base font-bold text-slate-900 leading-tight truncate">
                                    {{ activePlayer.full_name }}
                                </h2>
                                <p class="text-xs text-slate-600 truncate">
                                    Nama Punggung: <strong class="text-slate-800">{{ activePlayer.jersey_name || '-' }}</strong>
                                </p>
                                <p class="text-xs text-slate-600">
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
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold flex items-center gap-1.5">
                                    <svg v-if="activePlayer.is_age_valid" class="w-4 h-4 text-emerald-600 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 text-rose-600 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Pemeriksaan Batas Kelahiran (Usia)</span>
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-bold"
                                    :class="activePlayer.is_age_valid ? 'bg-emerald-200 text-emerald-900' : 'bg-rose-200 text-rose-900'"
                                >
                                    {{ activePlayer.is_age_valid ? 'Sah Sesuai Regulasi' : 'Melebihi / Di Luar Batas Usia' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-200/60">
                                <div>
                                    <span class="text-slate-600 block">Tanggal Lahir:</span>
                                    <span class="font-bold text-slate-900 tabular-nums">{{ activePlayer.birth_date_formatted }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-600 block">Tempat Lahir:</span>
                                    <span class="font-bold text-slate-900">{{ activePlayer.birth_place || '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- National Identity Information -->
                        <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <div>
                                <span class="text-xs font-semibold text-slate-600 block">Nomor Induk Kependudukan (NIK)</span>
                                <span class="font-mono font-bold text-slate-900 tabular-nums text-xs">{{ activePlayer.nik }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-600 block">Nomor Induk Siswa (NISN)</span>
                                <span class="font-mono font-bold text-slate-900 tabular-nums text-xs">{{ activePlayer.nisn || '-' }}</span>
                            </div>
                        </div>

                        <!-- Digital Accreditation Code if passed -->
                        <div v-if="activePlayer.accreditation_code" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 flex items-center justify-between gap-2">
                            <div>
                                <span class="text-xs font-semibold block text-emerald-800">Kode Pengesahan Digital</span>
                                <span class="font-mono font-bold text-emerald-950">{{ activePlayer.accreditation_code }}</span>
                            </div>
                            <span class="text-xs text-emerald-800">Terverifikasi: {{ activePlayer.verified_at }}</span>
                        </div>

                        <!-- Rejection Note if any -->
                        <div v-if="activePlayer.rejection_reason" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900 space-y-1">
                            <span class="font-bold block text-rose-800">Catatan Penolakan Terakhir:</span>
                            <p class="text-rose-800 leading-relaxed">{{ activePlayer.rejection_reason }}</p>
                        </div>
                    </div>

                    <!-- Screening Audit Trail Mini Logs -->
                    <div v-if="activePlayer.logs && activePlayer.logs.length > 0" class="frosted-card p-4 bg-white space-y-2.5">
                        <h3 class="text-xs font-bold text-slate-800">Histori Pemeriksaan Pemain</h3>
                        <div class="space-y-2">
                            <div
                                v-for="log in activePlayer.logs"
                                :key="log.id"
                                class="text-xs p-2.5 rounded-lg border border-slate-100 bg-slate-50/70 space-y-0.5"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-800">{{ log.actor_name }} &bull; {{ log.to_status.toUpperCase() }}</span>
                                    <span class="text-xs text-slate-600 tabular-nums">{{ log.created_at }}</span>
                                </div>
                                <p class="text-slate-700 text-xs leading-relaxed">{{ log.reason }}</p>
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
                                <h2 class="text-xs font-bold text-slate-800">Berkas Dokumen Lampiran</h2>
                                <p class="text-xs text-slate-600">Pilih dokumen untuk memeriksa scan asli</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Quick Wizard Trigger Button -->
                                <button
                                    v-if="!isPlayerLocked && activePlayer.documents && activePlayer.documents.length > 0"
                                    type="button"
                                    class="px-2.5 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-bold bg-orange-50 text-orange-800 hover:bg-orange-100 border border-orange-200 transition-colors flex items-center gap-1.5"
                                    title="Verifikasi cepat semua dokumen satu per satu secara berurutan"
                                    @click="openWizard(0)"
                                >
                                    <svg class="w-3.5 h-3.5 text-orange-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span>Verifikasi Cepat Semua Berkas</span>
                                </button>

                                <!-- Document Tab Selector Pills -->
                                <div role="tablist" aria-label="Pilih berkas dokumen" class="flex flex-wrap items-center gap-1.5">
                                    <button
                                        v-for="(doc, dIdx) in activePlayer.documents"
                                        :key="doc.id"
                                        type="button"
                                        role="tab"
                                        :aria-selected="selectedDocIndex === dIdx"
                                        class="px-2.5 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold flex items-center gap-1.5 border transition-colors"
                                        :class="
                                            selectedDocIndex === dIdx
                                                ? 'bg-orange-600 text-white border-orange-600'
                                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                                        "
                                        @click="selectedDocIndex = dIdx"
                                    >
                                        <span>{{ doc.label }}</span>
                                        <span class="inline-flex items-center">
                                            <svg v-if="doc.validation_status === 'valid'" class="w-3.5 h-3.5 shrink-0" aria-hidden="true" :class="selectedDocIndex === dIdx ? 'text-white' : 'text-emerald-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg v-else-if="doc.validation_status === 'invalid'" class="w-3.5 h-3.5 shrink-0" aria-hidden="true" :class="selectedDocIndex === dIdx ? 'text-white' : 'text-rose-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5 shrink-0" aria-hidden="true" :class="selectedDocIndex === dIdx ? 'text-white' : 'text-amber-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Active Document Viewer Container -->
                        <div v-if="activeDoc" class="flex-1 flex flex-col space-y-3">
                            <!-- Document Details & Granular Status Control Bar -->
                            <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 truncate">{{ activeDoc.original_name || activeDoc.label }}</span>
                                        <span class="text-slate-600 text-xs tabular-nums">({{ (activeDoc.file_size / 1024).toFixed(0) }} KB)</span>
                                    </div>
                                    <p v-if="activeDoc.document_number" class="text-xs text-slate-600 font-mono mt-0.5">
                                        No. Dokumen: <strong>{{ activeDoc.document_number }}</strong>
                                    </p>
                                </div>

                                <!-- Action Buttons: Mark Valid / Invalid per Document -->
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="px-2.5 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-bold border transition-colors flex items-center gap-1.5"
                                        :class="[
                                            activeDoc.validation_status === 'valid'
                                                ? 'bg-emerald-600 text-white border-emerald-600'
                                                : 'bg-white text-emerald-800 border-emerald-200 hover:bg-emerald-50',
                                            isPlayerLocked ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                        :disabled="isPlayerLocked || activeDoc.validation_status === 'valid'"
                                        :title="isPlayerLocked ? 'Pemain sudah terkunci sah' : (activeDoc.validation_status === 'valid' ? 'Dokumen sudah berstatus sah' : 'Tandai dokumen sah')"
                                        @click="setDocumentStatus(activeDoc, 'valid')"
                                    >
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Dokumen Sah</span>
                                    </button>

                                    <button
                                        type="button"
                                        class="px-2.5 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-bold border transition-colors flex items-center gap-1.5"
                                        :class="[
                                            activeDoc.validation_status === 'invalid'
                                                ? 'bg-rose-600 text-white border-rose-600'
                                                : 'bg-white text-rose-800 border-rose-200 hover:bg-rose-50',
                                            isPlayerLocked ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                        :disabled="isPlayerLocked || activeDoc.validation_status === 'invalid'"
                                        :title="isPlayerLocked ? 'Pemain sudah terkunci sah' : (activeDoc.validation_status === 'invalid' ? 'Dokumen sudah ditandai perlu revisi' : 'Tandai dokumen butuh revisi')"
                                        @click="setDocumentStatus(activeDoc, 'invalid')"
                                    >
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                        class="w-full h-[400px] rounded-xl border border-slate-200 bg-white"
                                        title="Pratinjau PDF"
                                    ></iframe>

                                    <div class="flex items-center gap-2 text-xs">
                                        <button
                                            type="button"
                                            class="btn-sm-pitch inline-flex items-center gap-1.5"
                                            @click="isDocViewerOpen = true"
                                        >
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                        class="max-w-full max-h-[460px] object-contain rounded-lg transition-transform duration-200 cursor-zoom-in"
                                        :style="{ transform: `scale(${imageZoom})` }"
                                        @click="isDocViewerOpen = true"
                                    />

                                    <!-- Floating Image Zoom Toolbar -->
                                    <div class="absolute bottom-3 right-3 bg-slate-900 rounded-xl p-1.5 flex items-center gap-1 text-white text-xs border border-slate-700">
                                        <button type="button" aria-label="Perkecil gambar" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded" title="Perkecil gambar" @click="zoomOut">-</button>
                                        <span class="text-xs px-1 font-semibold tabular-nums">{{ Math.round(imageZoom * 100) }}%</span>
                                        <button type="button" aria-label="Perbesar gambar" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded" title="Perbesar gambar" @click="zoomIn">+</button>
                                        <button type="button" aria-label="Reset ukuran gambar" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded text-xs" title="Reset Zoom" @click="resetZoom">1:1</button>
                                        <button
                                            type="button"
                                            class="px-2 py-1 min-h-[36px] sm:min-h-0 bg-orange-600 hover:bg-orange-700 rounded font-semibold ml-1 inline-flex items-center gap-1"
                                            @click="isDocViewerOpen = true"
                                        >
                                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                            </svg>
                                            <span>Modal View</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No Documents Uploaded State -->
                        <div v-else class="flex-1 flex flex-col items-center justify-center p-12 text-center text-slate-600 space-y-2">
                            <svg class="w-12 h-12 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-xs font-bold text-slate-800">Belum Ada Dokumen Diunggah</p>
                            <p class="text-xs text-slate-600">Manajer tim belum melampirkan berkas scan untuk pemain ini.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Operational Decision Bar (Fixed / Sticky Footer) -->
            <div v-if="activePlayer" class="sticky bottom-4 z-40">
                <div class="frosted-card p-4 bg-slate-900/95 text-white backdrop-blur-md border border-slate-700 shadow-lg flex flex-wrap items-center justify-between gap-3 rounded-2xl">
                    <div class="flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg bg-orange-600 text-white font-bold flex items-center justify-center text-sm tabular-nums">
                            #{{ activePlayer.jersey_number }}
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-white">{{ activePlayer.full_name }}</span>
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-bold"
                                    :class="
                                        activePlayer.screening_status === 'passed'
                                            ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                                            : activePlayer.screening_status === 'rejected'
                                            ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                                            : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                                    "
                                >
                                    {{ playerStatusBadgeLabel(activePlayer.screening_status) }}
                                </span>
                            </div>
                            <span class="text-xs text-slate-300 block mt-0.5">
                                Verifikasi pemain nomor punggung {{ activePlayer.jersey_number }}
                            </span>
                        </div>
                    </div>

                    <!-- Action Decision Buttons -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Emergency Unlock if already passed -->
                        <button
                            v-if="isPlayerLocked"
                            type="button"
                            class="px-3 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-semibold bg-slate-800 text-slate-200 hover:text-white hover:bg-slate-700 border border-slate-700 transition-colors flex items-center gap-1.5"
                            @click="isUnlockModalOpen = true"
                        >
                            <svg class="w-3.5 h-3.5 text-amber-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            <span>Buka Kunci (Unlock)</span>
                        </button>

                        <!-- Reject Button -->
                        <div class="relative group">
                            <button
                                type="button"
                                class="btn-sm-danger px-4 py-2 text-xs font-bold flex items-center gap-1.5"
                                :class="{ 'opacity-50 cursor-not-allowed': !canRejectPlayer }"
                                :disabled="!canRejectPlayer"
                                @click="openRejectModal"
                            >
                                <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>{{ activePlayer.screening_status === 'rejected' ? 'Ubah Catatan Penolakan' : 'Tolak / Minta Revisi' }}</span>
                            </button>
                            <div
                                v-if="rejectDisabledTooltip"
                                class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 hidden group-hover:block bg-slate-950 text-white text-xs px-2.5 py-1 rounded-lg whitespace-nowrap z-50 border border-slate-700 pointer-events-none"
                            >
                                {{ rejectDisabledTooltip }}
                            </div>
                        </div>

                        <!-- Approve Button -->
                        <div class="relative group">
                            <button
                                type="button"
                                class="px-5 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-bold text-white transition-colors flex items-center gap-1.5"
                                :class="[
                                    canApprovePlayer
                                        ? 'bg-emerald-600 hover:bg-emerald-700 cursor-pointer'
                                        : 'bg-slate-700 text-slate-300 opacity-60 cursor-not-allowed'
                                ]"
                                :disabled="!canApprovePlayer"
                                @click="handleApproveClick"
                            >
                                <svg v-if="verifyForm.processing" class="w-4 h-4 animate-spin text-white" aria-hidden="true" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>
                                    {{
                                        verifyForm.processing
                                            ? 'Menyimpan...'
                                            : (isPlayerLocked
                                                ? 'Pemain Sudah Sah'
                                                : (hasPendingDocuments
                                                    ? 'Verifikasi Berkas & Sahkan'
                                                    : 'Loloskan Pemain (Sah)'))
                                    }}
                                </span>
                            </button>
                            <div
                                v-if="approveDisabledTooltip"
                                class="absolute bottom-full mb-2 right-0 hidden group-hover:block bg-slate-950 text-white text-xs px-2.5 py-1 rounded-lg whitespace-nowrap z-50 border border-slate-700 pointer-events-none"
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
                role="dialog"
                aria-modal="true"
                aria-labelledby="reject-player-title"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/65"
                @click.self="isRejectModalOpen = false"
            >
                <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 id="reject-player-title" class="text-sm font-bold text-slate-900">Tolak Berkas Pemain</h3>
                            <p class="text-xs text-slate-600">Pemain: {{ activePlayer?.full_name }} (#{{ activePlayer?.jersey_number }})</p>
                        </div>
                        <button type="button" aria-label="Tutup dialog tolak berkas" class="text-slate-600 hover:text-slate-900 text-lg leading-none min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 flex items-center justify-center rounded-lg" @click="isRejectModalOpen = false">&times;</button>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        Tuliskan instruksi atau alasan spesifik mengapa berkas pemain ini ditolak agar manajer tim dapat segera memperbaikinya di portal manajer:
                    </p>

                    <form @submit.prevent="submitRejection" novalidate class="space-y-3.5">
                        <div>
                            <label for="team-reject-reason" class="block text-xs font-semibold text-slate-700 mb-1">Alasan Penolakan (Minimal 10 Karakter)</label>
                            <textarea
                                id="team-reject-reason"
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
                role="dialog"
                aria-modal="true"
                aria-labelledby="unlock-player-title"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/65"
                @click.self="isUnlockModalOpen = false"
            >
                <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 id="unlock-player-title" class="text-sm font-bold text-slate-900">Buka Kunci Pemain (Unlock)</h3>
                            <p class="text-xs text-slate-600">Pemain: {{ activePlayer?.full_name }} (#{{ activePlayer?.jersey_number }})</p>
                        </div>
                        <button type="button" aria-label="Tutup dialog buka kunci" class="text-slate-600 hover:text-slate-900 text-lg leading-none min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 flex items-center justify-center rounded-lg" @click="isUnlockModalOpen = false">&times;</button>
                    </div>

                    <p class="text-xs text-amber-900 bg-amber-50 p-3 rounded-xl border border-amber-200 leading-relaxed">
                        Tindakan ini akan membatalkan status lolos sah dan mengembalikan pemain ke status <strong>Draft</strong> agar manajer tim dapat mengubah data/berkas.
                    </p>

                    <form @submit.prevent="submitUnlock" novalidate class="space-y-3.5">
                        <div>
                            <label for="team-unlock-reason" class="block text-xs font-semibold text-slate-700 mb-1">Alasan Pembukaan Kunci (Opsional)</label>
                            <input
                                id="team-unlock-reason"
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

            <!-- Quick Document Verification Wizard Modal -->
            <div
                v-if="isWizardModalOpen && activePlayer"
                role="dialog"
                aria-modal="true"
                aria-labelledby="team-wizard-title"
                class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 bg-slate-950/75 overflow-y-auto"
                @click.self="closeWizard"
            >
                <div class="frosted-card bg-white rounded-2xl max-w-4xl w-full p-5 sm:p-6 shadow-xl border border-slate-200 space-y-4 max-h-[92vh] flex flex-col">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-orange-100 text-orange-800 border border-orange-200">
                                    Verifikasi Cepat Dokumen
                                </span>
                                <span class="text-xs text-slate-600" aria-hidden="true">&bull;</span>
                                <span class="text-xs font-semibold text-slate-700">
                                    #{{ activePlayer.jersey_number }} {{ activePlayer.full_name }} ({{ activePlayer.position }})
                                </span>
                            </div>
                            <h3 id="team-wizard-title" class="text-base font-bold text-slate-900 mt-0.5">
                                {{
                                    wizardDocIndex < activePlayer.documents.length
                                        ? `Langkah ${wizardDocIndex + 1} dari ${activePlayer.documents.length}: ${activeWizardDoc?.label}`
                                        : 'Langkah Terakhir: Pengesahan Kelolosan Pemain'
                                }}
                            </h3>
                        </div>

                        <button
                            type="button"
                            aria-label="Tutup Wizard Verifikasi"
                            class="text-slate-600 hover:text-slate-900 text-2xl leading-none p-1 min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 flex items-center justify-center rounded-lg"
                            title="Tutup Wizard"
                            @click="closeWizard"
                        >
                            &times;
                        </button>
                    </div>

                    <!-- Step Stepper Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                        <button
                            v-for="(doc, sIdx) in activePlayer.documents"
                            :key="doc.id"
                            type="button"
                            class="px-3 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-xl font-semibold whitespace-nowrap flex items-center gap-1.5 border transition-colors shrink-0 cursor-pointer"
                            :class="[
                                wizardDocIndex === sIdx
                                    ? 'bg-orange-600 text-white border-orange-600'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                            ]"
                            @click="selectWizardDoc(sIdx)"
                        >
                            <span class="w-4 h-4 rounded flex items-center justify-center text-[11px] font-bold tabular-nums" :class="wizardDocIndex === sIdx ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
                                {{ sIdx + 1 }}
                            </span>
                            <span>{{ doc.label }}</span>
                            <span class="inline-flex items-center">
                                <svg v-if="doc.validation_status === 'valid'" class="w-3.5 h-3.5" aria-hidden="true" :class="wizardDocIndex === sIdx ? 'text-white' : 'text-emerald-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else-if="doc.validation_status === 'invalid'" class="w-3.5 h-3.5" aria-hidden="true" :class="wizardDocIndex === sIdx ? 'text-white' : 'text-rose-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <svg v-else class="w-3.5 h-3.5" aria-hidden="true" :class="wizardDocIndex === sIdx ? 'text-white' : 'text-amber-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                        </button>

                        <!-- Final Step Pill -->
                        <button
                            type="button"
                            class="px-3 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-xl font-semibold whitespace-nowrap flex items-center gap-1.5 border transition-colors shrink-0 cursor-pointer"
                            :class="[
                                wizardDocIndex === activePlayer.documents.length
                                    ? 'bg-emerald-600 text-white border-emerald-600'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                            ]"
                            @click="wizardDocIndex = activePlayer.documents.length"
                        >
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Pengesahan Selesai</span>
                        </button>
                    </div>

                    <!-- Step Content: Document Inspection -->
                    <div v-if="wizardDocIndex < activePlayer.documents.length && activeWizardDoc" class="flex-1 flex flex-col space-y-3 min-h-0 overflow-y-auto">
                        <!-- Doc Meta Bar -->
                        <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-800">{{ activeWizardDoc.original_name || activeWizardDoc.label }}</span>
                                <span v-if="activeWizardDoc.document_number" class="font-mono text-slate-600 text-xs">
                                    (No: {{ activeWizardDoc.document_number }})
                                </span>
                            </div>

                            <!-- Current Status Badge -->
                            <div>
                                <span
                                    class="px-2.5 py-0.5 rounded-lg text-xs font-bold inline-flex items-center gap-1"
                                    :class="[
                                        activeWizardDoc.validation_status === 'valid'
                                            ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                            : activeWizardDoc.validation_status === 'invalid'
                                            ? 'bg-rose-100 text-rose-800 border border-rose-200'
                                            : 'bg-amber-100 text-amber-800 border border-amber-200'
                                    ]"
                                >
                                    <svg v-if="activeWizardDoc.validation_status === 'valid'" class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else-if="activeWizardDoc.validation_status === 'invalid'" class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>{{ activeWizardDoc.validation_status === 'valid' ? 'Sah' : (activeWizardDoc.validation_status === 'invalid' ? 'Butuh Revisi' : 'Belum Diverifikasi') }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Viewer Canvas -->
                        <div class="relative bg-slate-900/5 rounded-2xl border border-slate-200/80 p-3 h-[340px] sm:h-[390px] flex items-center justify-center overflow-hidden">
                            <!-- PDF View -->
                            <div v-if="activeWizardDoc.is_pdf" class="w-full h-full flex flex-col items-center justify-center p-2">
                                <iframe
                                    :src="activeWizardDoc.preview_url"
                                    class="w-full h-full rounded-xl border border-slate-200 bg-white"
                                    title="Pratinjau PDF"
                                ></iframe>
                            </div>

                            <!-- Image View -->
                            <div v-else class="relative w-full h-full flex items-center justify-center overflow-auto">
                                <img
                                    :src="activeWizardDoc.preview_url"
                                    :alt="activeWizardDoc.label"
                                    class="max-w-full max-h-full object-contain rounded-lg transition-transform duration-200"
                                    :style="{ transform: `scale(${wizardZoom})` }"
                                />

                                <!-- Floating Toolbar -->
                                <div class="absolute bottom-3 right-3 bg-slate-900 rounded-xl p-1.5 flex items-center gap-1 text-white text-xs border border-slate-700">
                                    <button type="button" aria-label="Perkecil gambar wizard" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded font-bold cursor-pointer" title="Zoom Out" @click="wizardZoomOut">-</button>
                                    <span class="text-xs px-1 font-semibold tabular-nums">{{ Math.round(wizardZoom * 100) }}%</span>
                                    <button type="button" aria-label="Perbesar gambar wizard" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded font-bold cursor-pointer" title="Zoom In" @click="wizardZoomIn">+</button>
                                    <button type="button" aria-label="Reset ukuran gambar wizard" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded text-xs cursor-pointer" title="Reset Zoom" @click="resetWizardZoom">1:1</button>
                                </div>
                            </div>
                        </div>

                        <!-- Optional Revision Note Input (Inline) -->
                        <div>
                            <label for="team-wizard-notes" class="sr-only">Catatan revisi dokumen</label>
                            <input
                                id="team-wizard-notes"
                                v-model="wizardNotes"
                                type="text"
                                placeholder="Tuliskan catatan revisi (wajib diisi jika berkas butuh perbaikan manajer, misal: foto buram / NIK terpotong)"
                                class="input-field text-xs"
                            />
                        </div>

                        <!-- Wizard Step Actions -->
                        <div class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="btn-secondary text-xs px-3 py-2 cursor-pointer"
                                    :disabled="wizardDocIndex === 0"
                                    @click="selectWizardDoc(wizardDocIndex - 1)"
                                >
                                    Sebelumnya
                                </button>
                                <button
                                    type="button"
                                    class="btn-secondary text-xs px-3 py-2 text-slate-700 cursor-pointer"
                                    @click="selectWizardDoc(wizardDocIndex + 1)"
                                >
                                    Lewati
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center gap-2.5">
                                <button
                                    type="button"
                                    class="btn-danger text-xs px-4 py-2 font-bold flex items-center gap-1.5 cursor-pointer"
                                    :disabled="isSubmittingDoc"
                                    @click="submitWizardDocStatus('invalid')"
                                >
                                    <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Tolak / Butuh Revisi</span>
                                </button>

                                <button
                                    type="button"
                                    class="btn-pitch text-xs px-5 py-2 font-bold flex items-center gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700 cursor-pointer"
                                    :disabled="isSubmittingDoc"
                                    @click="submitWizardDocStatus('valid')"
                                >
                                    <svg v-if="isSubmittingDoc" class="w-4 h-4 animate-spin" aria-hidden="true" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Sahkan Dokumen Ini</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step Content: Final Confirmation / Summary Screen -->
                    <div v-else class="flex-1 flex flex-col space-y-4 min-h-0 overflow-y-auto py-2">
                        <!-- Case A: All Documents Valid -->
                        <div v-if="allDocumentsValid" class="space-y-4">
                            <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-emerald-950">Seluruh Berkas Dokumen Telah Sah!</h4>
                                    <p class="text-xs text-emerald-800 mt-0.5">
                                        Semua {{ activePlayer.documents.length }} dokumen pemain telah diperiksa dan dinyatakan valid. Usia pemain juga telah lolos verifikasi cut-off usia kompetisi.
                                    </p>
                                </div>
                            </div>

                            <!-- Player Summary Card -->
                            <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/70 flex flex-col sm:flex-row items-center gap-4">
                                <div class="w-20 h-24 rounded-xl bg-slate-200 overflow-hidden shrink-0 border border-slate-300">
                                    <img v-if="activePlayer.photo_url" :src="activePlayer.photo_url" :alt="`Foto ${activePlayer.full_name}`" width="80" height="96" class="w-full h-full object-cover" />
                                </div>
                                <div class="space-y-1 text-center sm:text-left flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                        <span class="w-6 h-6 rounded bg-orange-600 text-white font-bold text-xs flex items-center justify-center tabular-nums">
                                            #{{ activePlayer.jersey_number }}
                                        </span>
                                        <span class="font-bold text-slate-900 text-sm">{{ activePlayer.full_name }}</span>
                                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-slate-200 text-slate-700 uppercase">
                                            {{ activePlayer.position }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-600">
                                        Lahir: <strong>{{ activePlayer.birth_date }}</strong> (Usia {{ activePlayer.age }} thn) &bull; NIK: <strong class="font-mono">{{ activePlayer.nik }}</strong>
                                    </p>
                                    <div class="pt-2 flex flex-wrap gap-2 text-xs">
                                        <span v-for="doc in activePlayer.documents" :key="doc.id" class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                                            <svg class="w-3 h-3 text-emerald-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{{ doc.label }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Final Actions -->
                            <div class="pt-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                                <button type="button" class="btn-secondary text-xs cursor-pointer" @click="selectWizardDoc(0)">
                                    Periksa Kembali Berkas
                                </button>

                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn-secondary text-xs cursor-pointer" @click="closeWizard">Batal</button>
                                    <button
                                        type="button"
                                        class="px-5 py-2.5 min-h-[44px] sm:min-h-0 rounded-xl font-bold text-xs text-white bg-emerald-600 hover:bg-emerald-700 flex items-center gap-1.5 cursor-pointer"
                                        :disabled="verifyForm.processing"
                                        @click="submitFinalApprove"
                                    >
                                        <svg v-if="verifyForm.processing" class="w-4 h-4 animate-spin" aria-hidden="true" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>Konfirmasi Loloskan Pemain (Sah)</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Case B: There are Invalid Documents -->
                        <div v-else class="space-y-4">
                            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                                    <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-rose-950">Pemeriksaan Selesai: Berkas Butuh Revisi</h4>
                                    <p class="text-xs text-rose-800 mt-0.5">
                                        Pemain tidak dapat diloloskan karena terdapat berkas yang dinyatakan tidak valid atau memerlukan perbaikan oleh manajer tim.
                                    </p>
                                </div>
                            </div>

                            <!-- List of Invalid Docs -->
                            <div class="space-y-2">
                                <h5 class="text-xs font-bold text-slate-800">Daftar Berkas Bermasalah:</h5>
                                <div
                                    v-for="inv in invalidDocuments"
                                    :key="inv.id"
                                    class="p-3 rounded-xl border border-rose-200 bg-rose-50/50 text-xs text-rose-900 space-y-0.5"
                                >
                                    <div class="font-bold flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-rose-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span>{{ inv.label }}</span>
                                    </div>
                                    <p class="text-xs text-rose-800 pl-5">
                                        Catatan: {{ inv.notes || 'Berkas tidak sesuai regulasi atau foto buram.' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="pt-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                                <button type="button" class="btn-secondary text-xs cursor-pointer" @click="selectWizardDoc(0)">
                                    Periksa Kembali Berkas
                                </button>

                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn-secondary text-xs cursor-pointer" @click="closeWizard">Tutup</button>
                                    <button
                                        type="button"
                                        class="btn-danger text-xs px-4 py-2 font-bold flex items-center gap-1.5 cursor-pointer"
                                        @click="goToRejectFromWizard"
                                    >
                                        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span>Lanjutkan Tolak Pemain untuk Revisi</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approve Confirmation Modal (When all documents are already valid) -->
            <div
                v-if="isApproveConfirmModalOpen && activePlayer"
                role="dialog"
                aria-modal="true"
                aria-labelledby="team-approve-title"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/65"
                @click.self="isApproveConfirmModalOpen = false"
            >
                <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 id="team-approve-title" class="text-sm font-bold text-slate-900">Konfirmasi Kelolosan Pemain</h3>
                            <p class="text-xs text-slate-600">Pemain: {{ activePlayer.full_name }} (#{{ activePlayer.jersey_number }})</p>
                        </div>
                        <button type="button" aria-label="Tutup konfirmasi kelolosan" class="text-slate-600 hover:text-slate-900 text-lg leading-none cursor-pointer min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 flex items-center justify-center rounded-lg" @click="isApproveConfirmModalOpen = false">&times;</button>
                    </div>

                    <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 space-y-2">
                        <div class="font-bold flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>Seluruh Berkas Telah Terverifikasi Sah</span>
                        </div>
                        <p class="text-xs text-emerald-800 leading-relaxed">
                            Pemain ini akan dinyatakan <strong>SAH (Lolos Verifikasi)</strong>, data akan dikunci resmi, dan kode akreditasi digital pertandingan akan diterbitkan.
                        </p>
                    </div>

                    <div class="text-xs space-y-1.5 p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <div class="flex justify-between">
                            <span class="text-slate-600">Nomor Punggung / Posisi:</span>
                            <span class="font-bold text-slate-800">#{{ activePlayer.jersey_number }} / {{ activePlayer.position }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Tanggal Lahir:</span>
                            <span class="font-bold text-slate-800 tabular-nums">{{ activePlayer.birth_date }} (Usia {{ activePlayer.age }} thn)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Status Kelayakan Usia:</span>
                            <span class="font-bold text-emerald-700">Lolos Regulasi Usia {{ event.age_category }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-600">Dokumen Sah:</span>
                            <span class="font-bold text-slate-800 tabular-nums">{{ activePlayer.documents.length }} Dokumen Sah</span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2 text-xs">
                        <button type="button" class="btn-secondary cursor-pointer" @click="isApproveConfirmModalOpen = false">Batal</button>
                        <button
                            type="button"
                            class="px-4 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-white font-bold bg-emerald-600 hover:bg-emerald-700 flex items-center gap-1.5 cursor-pointer"
                            :disabled="verifyForm.processing"
                            @click="submitFinalApprove"
                        >
                            <svg v-if="verifyForm.processing" class="w-4 h-4 animate-spin text-white" aria-hidden="true" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ verifyForm.processing ? 'Menyimpan...' : 'Ya, Loloskan Pemain (Sah)' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

