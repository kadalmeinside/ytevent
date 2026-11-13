<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import DocumentViewerModal from '@/Components/DocumentViewerModal.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    activeParticipant: {
        type: Object,
        default: null,
    },
    participants: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: null,
    },
    navigation: {
        type: Object,
        default: () => ({ prev_id: null, next_id: null, active_position: 0, total_filtered: 0 }),
    },
    counts: {
        type: Object,
        default: () => ({ total: 0, submitted: 0, rejected: 0, passed: 0, draft: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({ status: 'all', search: '', participant_id: null }),
    },
});

const isAudition = computed(() => props.event.event_type === 'audition');

const activeParticipant = computed(() => props.activeParticipant || props.participants[0] || null);
const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const isNavigating = ref(false);
let searchTimer = null;

// Active Document State
const selectedDocIndex = ref(0);
const activeDoc = computed(() => {
    const docs = activeParticipant.value?.documents || [];
    if (docs.length === 0) return null;
    return docs[selectedDocIndex.value] || docs[0];
});

// Inline Viewer & Modal Viewer State
const imageZoom = ref(1);
const isDocViewerOpen = ref(false);
const photoViewerMode = ref(false);

// Wizard & Decision Modals State
const isWizardModalOpen = ref(false);
const wizardDocIndex = ref(0);
const wizardNotes = ref('');
const isSubmittingDoc = ref(false);
const wizardZoom = ref(1);
const isApproveConfirmModalOpen = ref(false);
const isRejectModalOpen = ref(false);
const isUnlockModalOpen = ref(false);

const zoomIn = () => {
    if (imageZoom.value < 2.5) imageZoom.value = Number((imageZoom.value + 0.25).toFixed(2));
};
const zoomOut = () => {
    if (imageZoom.value > 0.75) imageZoom.value = Number((imageZoom.value - 0.25).toFixed(2));
};
const resetZoom = () => {
    imageZoom.value = 1;
};

const wizardZoomIn = () => {
    if (wizardZoom.value < 2.5) wizardZoom.value = Number((wizardZoom.value + 0.25).toFixed(2));
};
const wizardZoomOut = () => {
    if (wizardZoom.value > 0.75) wizardZoom.value = Number((wizardZoom.value - 0.25).toFixed(2));
};
const resetWizardZoom = () => {
    wizardZoom.value = 1;
};

const visitQueue = ({ participantId = undefined, page = undefined, status = statusFilter.value, search = searchQuery.value } = {}) => {
    const cleanSearch = String(search || '').trim();
    router.get(
        route('admin.screening.auditions.review', { event: props.event.id }),
        {
            participant_id: participantId || undefined,
            page: page || undefined,
            status: status && status !== 'all' ? status : undefined,
            search: cleanSearch || undefined,
        },
        {
            only: ['activeParticipant', 'participants', 'pagination', 'navigation', 'counts', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => {
                isNavigating.value = true;
            },
            onFinish: () => {
                isNavigating.value = false;
                selectedDocIndex.value = 0;
                resetZoom();
            },
        }
    );
};

watch(
    () => props.activeParticipant?.id,
    () => {
        selectedDocIndex.value = 0;
        resetZoom();
    }
);

watch(searchQuery, (val) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        visitQueue({ participantId: undefined, page: 1, status: statusFilter.value, search: val });
    }, 300);
});

const setStatusFilter = (status) => {
    if (statusFilter.value === status) return;
    statusFilter.value = status;
    visitQueue({ participantId: undefined, page: 1, status, search: searchQuery.value });
};

const selectParticipantById = (id) => {
    if (!id || Number(activeParticipant.value?.id) === Number(id)) return;
    visitQueue({ participantId: id, page: props.pagination?.current_page });
};

const nextParticipant = () => {
    if (props.navigation?.next_id) {
        visitQueue({ participantId: props.navigation.next_id });
    }
};

const prevParticipant = () => {
    if (props.navigation?.prev_id) {
        visitQueue({ participantId: props.navigation.prev_id });
    }
};

const goToQueuePage = (pageNum) => {
    if (!props.pagination || pageNum < 1 || pageNum > props.pagination.last_page || pageNum === props.pagination.current_page) {
        return;
    }
    visitQueue({ participantId: activeParticipant.value?.id, page: pageNum });
};

const openDocModal = () => {
    photoViewerMode.value = false;
    isDocViewerOpen.value = true;
};

const openPhotoModal = () => {
    if (!activeParticipant.value?.photo_url) return;
    photoViewerMode.value = true;
    isDocViewerOpen.value = true;
};

// Operational Guards
const isLocked = computed(() => Boolean(activeParticipant.value?.is_locked || activeParticipant.value?.screening_status === 'passed'));
const isAgeEligible = computed(() => Boolean(activeParticipant.value?.is_age_valid));
const hasDocuments = computed(() => Boolean(activeParticipant.value?.documents?.length > 0));
const hasInvalidDocument = computed(() => Boolean(activeParticipant.value?.documents?.some((d) => d.validation_status === 'invalid')));
const hasPendingDocuments = computed(() => Boolean(activeParticipant.value?.documents?.some((d) => d.validation_status === 'pending')));
const allDocumentsValid = computed(() => {
    const docs = activeParticipant.value?.documents || [];
    return docs.length > 0 && docs.every((d) => d.validation_status === 'valid');
});
const invalidDocuments = computed(() => {
    return activeParticipant.value?.documents?.filter((d) => d.validation_status === 'invalid') || [];
});

const verifyForm = useForm({
    status: 'passed',
    notes: '',
});

const unlockForm = useForm({
    reason: '',
});

const canApprove = computed(() => {
    if (!activeParticipant.value) return false;
    if (isLocked.value) return false;
    if (!isAgeEligible.value) return false;
    if (hasInvalidDocument.value) return false;
    if (verifyForm.processing) return false;
    return true;
});

const approveDisabledTooltip = computed(() => {
    if (!activeParticipant.value) return '';
    if (isLocked.value) return 'Peserta sudah berstatus Sah dan Digital Pass telah aktif.';
    if (!isAgeEligible.value) return 'Tidak dapat disahkan: Tanggal lahir peserta di luar batas regulasi event.';
    if (hasInvalidDocument.value) return 'Ada berkas berstatus Butuh Revisi. Berkas harus Sah sebelum pengesahan.';
    return '';
});

const canReject = computed(() => {
    if (!activeParticipant.value) return false;
    if (isLocked.value) return false;
    if (verifyForm.processing) return false;
    return true;
});

const rejectDisabledTooltip = computed(() => {
    if (!activeParticipant.value) return '';
    if (isLocked.value) return 'Peserta sudah berstatus Sah. Buka kunci terlebih dahulu untuk merevisi.';
    return '';
});

// Quick Wizard Controls
const activeWizardDoc = computed(() => {
    const docs = activeParticipant.value?.documents || [];
    return docs[wizardDocIndex.value] || null;
});

const openWizard = (startDocIndex = 0) => {
    if (isLocked.value || !hasDocuments.value) return;
    const docs = activeParticipant.value.documents;
    let initialIndex = startDocIndex;
    if (startDocIndex === 0) {
        const pendingIdx = docs.findIndex((d) => d.validation_status === 'pending');
        if (pendingIdx !== -1) initialIndex = pendingIdx;
    }
    wizardDocIndex.value = Math.max(0, Math.min(initialIndex, docs.length - 1));
    wizardNotes.value = docs[wizardDocIndex.value]?.notes || '';
    resetWizardZoom();
    isWizardModalOpen.value = true;
};

const closeWizard = () => {
    isWizardModalOpen.value = false;
    resetWizardZoom();
};

const selectWizardDoc = (index) => {
    wizardDocIndex.value = index;
    const docs = activeParticipant.value?.documents || [];
    if (docs[index]) {
        wizardNotes.value = docs[index].notes || '';
    }
    resetWizardZoom();
};

const submitWizardDocStatus = async (status) => {
    if (!activeWizardDoc.value || !activeParticipant.value || isSubmittingDoc.value) return;
    isSubmittingDoc.value = true;
    const doc = activeWizardDoc.value;
    const notes = status === 'invalid' ? (wizardNotes.value || 'Dokumen memerlukan perbaikan.') : null;

    try {
        await window.axios.post(route('admin.screening.auditions.document.verify', { participant: activeParticipant.value.id }), {
            document_type: doc.document_type,
            validation_status: status,
            notes,
        });

        doc.validation_status = status;
        doc.notes = notes;

        const totalDocs = activeParticipant.value.documents.length;
        if (wizardDocIndex.value < totalDocs - 1) {
            wizardDocIndex.value++;
            wizardNotes.value = activeParticipant.value.documents[wizardDocIndex.value]?.notes || '';
            resetWizardZoom();
        } else {
            wizardDocIndex.value = totalDocs;
        }
    } catch (error) {
        console.error('Gagal memperbarui status dokumen peserta:', error);
    } finally {
        isSubmittingDoc.value = false;
    }
};

// Granular Per-Document Verification
const setDocumentStatus = async (doc, status) => {
    if (isLocked.value || !activeParticipant.value || doc.validation_status === status) return;
    try {
        await window.axios.post(route('admin.screening.auditions.document.verify', { participant: activeParticipant.value.id }), {
            document_type: doc.document_type,
            validation_status: status,
            notes: doc.notes || null,
        });
        doc.validation_status = status;
    } catch (error) {
        console.error('Gagal memperbarui status dokumen:', error);
    }
};

// Final Approve Flow
const handleApproveClick = () => {
    if (!canApprove.value) return;
    if (hasDocuments.value && hasPendingDocuments.value) {
        openWizard(0);
        return;
    }
    isApproveConfirmModalOpen.value = true;
};

const submitFinalApprove = () => {
    if (!canApprove.value || verifyForm.processing || !activeParticipant.value) return;
    const nextTargetId = props.navigation?.next_id;
    verifyForm.status = 'passed';
    verifyForm.notes = '';

    verifyForm.post(route('admin.screening.auditions.verify', { participant: activeParticipant.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isWizardModalOpen.value = false;
            isApproveConfirmModalOpen.value = false;
            if (nextTargetId) {
                visitQueue({ participantId: nextTargetId });
            }
        },
    });
};

// Reject Flow
const openRejectModal = () => {
    if (!canReject.value) return;
    verifyForm.status = 'rejected';
    verifyForm.notes = activeParticipant.value?.screening_notes || '';
    verifyForm.clearErrors();
    isRejectModalOpen.value = true;
};

const goToRejectFromWizard = () => {
    isWizardModalOpen.value = false;
    const inv = invalidDocuments.value;
    if (inv.length > 0) {
        const reasons = inv.map((d) => `${d.label}: ${d.notes || 'Berkas tidak sesuai / buram'}`).join('; ');
        verifyForm.status = 'rejected';
        verifyForm.notes = `Mohon perbaiki berkas: ${reasons}`;
        verifyForm.clearErrors();
        isRejectModalOpen.value = true;
        return;
    }
    openRejectModal();
};

const submitRejection = () => {
    if (!activeParticipant.value || verifyForm.processing) return;
    verifyForm.status = 'rejected';
    verifyForm.post(route('admin.screening.auditions.verify', { participant: activeParticipant.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isRejectModalOpen.value = false;
        },
    });
};

// Emergency Unlock
const submitUnlock = () => {
    if (!activeParticipant.value || unlockForm.processing) return;
    unlockForm.post(route('admin.screening.auditions.unlock', { participant: activeParticipant.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isUnlockModalOpen.value = false;
            unlockForm.reset();
        },
    });
};

const statusLabel = (st) => {
    if (st === 'passed') return 'SAH';
    if (st === 'rejected') return 'REVISI';
    if (st === 'submitted') return 'REVIEW';
    return 'DRAFT';
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
    if (searchTimer) clearTimeout(searchTimer);
});
</script>

<template>
    <AdminLayout
        :page-title="`Screening Berkas: ${event.name}`"
        :subtitle="`Workstation pemeriksaan dokumen dan validasi identitas peserta ${event.name} (${event.age_category}).`"
    >
        <Head :title="`Screening Peserta - ${event.name}`" />

        <div class="space-y-4">
            <!-- Breadcrumbs & Prev/Next Switcher -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex flex-wrap items-center gap-2 text-slate-600">
                    <Link :href="route('admin.events.show', { event: event.id })" class="hover:text-orange-700 font-semibold inline-flex items-center gap-1 min-h-[44px] sm:min-h-0">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        <span>Kelola Event</span>
                    </Link>
                    <span aria-hidden="true">/</span>
                    <Link :href="route('admin.screening.auditions', { event_id: event.id })" class="hover:text-orange-700 font-medium inline-flex items-center min-h-[44px] sm:min-h-0">
                        Antrean Screening
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span class="font-bold text-slate-900 truncate max-w-[200px]">{{ event.name }}</span>
                </div>

                <div v-if="navigation.total_filtered > 0" class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-600 tabular-nums">
                        Peserta: {{ navigation.active_position || 1 }} dari {{ navigation.total_filtered }}
                    </span>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="btn-sm-secondary px-2.5 py-1 text-xs inline-flex items-center gap-1"
                            :disabled="!navigation.prev_id || isNavigating"
                            @click="prevParticipant"
                        >
                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                            <span>Prev</span>
                        </button>
                        <button
                            type="button"
                            class="btn-sm-secondary px-2.5 py-1 text-xs inline-flex items-center gap-1"
                            :disabled="!navigation.next_id || isNavigating"
                            @click="nextParticipant"
                        >
                            <span>Next</span>
                            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Event Header & Regulation Cut-Off Reference -->
            <div class="frosted-card p-4 bg-white border border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-sm font-black text-slate-900">{{ event.name }}</h2>
                        <span class="px-2 py-0.5 rounded text-xs font-bold bg-orange-50 text-orange-800 border border-orange-200">
                            {{ event.age_category }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Total Pendaftar: <strong class="text-slate-900 tabular-nums">{{ counts.total || 0 }}</strong> peserta &bull;
                        Antrean dipaginasi per 20 peserta agar tetap ringan untuk ribuan pendaftar.
                    </p>
                </div>

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

            <!-- Empty State if No Participants at All -->
            <div v-if="(counts.total || 0) === 0" class="frosted-card p-12 text-center bg-white space-y-2">
                <svg class="w-12 h-12 mx-auto text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <p class="text-sm font-bold text-slate-800">Belum Ada Peserta Terdaftar</p>
                <p class="text-xs text-slate-600">Peserta yang mendaftar pada event ini akan muncul otomatis di workstation screening.</p>
            </div>

            <!-- Scalable Searchable & Paginated Queue Bar -->
            <div v-if="(counts.total || 0) > 0" class="frosted-card p-3.5 bg-white space-y-3 border border-slate-200">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">
                    <!-- Quick Status Filter Chips -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                            :class="statusFilter === 'all' ? 'bg-slate-900 text-white border-slate-900' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'"
                            @click="setStatusFilter('all')"
                        >
                            Semua ({{ counts.total || 0 }})
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                            :class="statusFilter === 'submitted' ? 'bg-amber-600 text-white border-amber-600' : 'bg-amber-50 text-amber-900 border-amber-200 hover:bg-amber-100'"
                            @click="setStatusFilter('submitted')"
                        >
                            Menunggu Review ({{ counts.submitted || 0 }})
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                            :class="statusFilter === 'rejected' ? 'bg-rose-600 text-white border-rose-600' : 'bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100'"
                            @click="setStatusFilter('rejected')"
                        >
                            Perlu Revisi ({{ counts.rejected || 0 }})
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-colors"
                            :class="statusFilter === 'passed' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100'"
                            @click="setStatusFilter('passed')"
                        >
                            Sah ({{ counts.passed || 0 }})
                        </button>
                    </div>

                    <!-- Search & Queue Page Controls -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative w-full sm:w-64">
                            <label for="review-queue-search" class="sr-only">Cari peserta</label>
                            <input
                                id="review-queue-search"
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari nama, NIK, atau ID Digital Pass..."
                                class="input-field text-xs py-1.5"
                            />
                        </div>

                        <div v-if="pagination && pagination.last_page > 1" class="flex items-center gap-1.5 text-xs">
                            <button
                                type="button"
                                class="btn-sm-secondary px-2 py-1 text-xs"
                                :disabled="pagination.current_page <= 1 || isNavigating"
                                @click="goToQueuePage(pagination.current_page - 1)"
                            >
                                &lsaquo; Hal Prev
                            </button>
                            <span class="font-semibold text-slate-600 tabular-nums px-1">
                                Hal {{ pagination.current_page }}/{{ pagination.last_page }}
                            </span>
                            <button
                                type="button"
                                class="btn-sm-secondary px-2 py-1 text-xs"
                                :disabled="pagination.current_page >= pagination.last_page || isNavigating"
                                @click="goToQueuePage(pagination.current_page + 1)"
                            >
                                Hal Next &rsaquo;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Current Page Queue Strip (max 20 items rendered) -->
                <div v-if="participants.length > 0" class="overflow-x-auto scrollbar-thin pb-0.5">
                    <div role="tablist" aria-label="Daftar peserta halaman aktif" class="flex items-center gap-1.5 min-w-max">
                        <button
                            v-for="(p, idx) in participants"
                            :key="p.id"
                            type="button"
                            role="tab"
                            :aria-selected="Number(activeParticipant?.id) === Number(p.id)"
                            class="px-3 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-semibold flex items-center gap-2 transition-colors border cursor-pointer"
                            :class="
                                Number(activeParticipant?.id) === Number(p.id)
                                    ? 'bg-orange-600 text-white border-orange-600'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                            "
                            @click="selectParticipantById(p.id)"
                        >
                            <span
                                class="font-bold px-1.5 py-0.5 rounded text-xs tabular-nums"
                                :class="Number(activeParticipant?.id) === Number(p.id) ? 'bg-orange-700 text-white' : 'bg-white text-slate-700 border border-slate-200'"
                            >
                                {{ p.registration_number || `#${(pagination?.from || 1) + idx}` }}
                            </span>
                            <span class="truncate max-w-[140px]">{{ p.full_name }}</span>
                            <span
                                class="px-1.5 py-0.5 rounded text-[11px] font-bold"
                                :class="
                                    Number(activeParticipant?.id) === Number(p.id)
                                        ? 'bg-white/20 text-white'
                                        : (p.screening_status === 'passed'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : p.screening_status === 'rejected'
                                              ? 'bg-rose-100 text-rose-800'
                                              : 'bg-amber-100 text-amber-900')
                                "
                            >
                                {{ statusLabel(p.screening_status) }}
                            </span>
                        </button>
                    </div>
                </div>
                <div v-else class="text-xs text-slate-500 py-2">
                    Tidak ada peserta yang cocok dengan filter atau pencarian saat ini.
                </div>
            </div>

            <!-- Main Split-View Review Area -->
            <div v-if="activeParticipant" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <!-- LEFT PANEL: Profile, Age Regulation, Payment & Logs (5 cols) -->
                <div class="lg:col-span-5 space-y-4">
                    <div class="frosted-card p-5 bg-white space-y-4">
                        <!-- Photo & Identity -->
                        <div class="flex items-start gap-4">
                            <button
                                type="button"
                                :aria-label="activeParticipant.photo_url ? `Perbesar pas foto ${activeParticipant.full_name}` : 'Pas foto peserta tidak tersedia'"
                                class="w-20 h-24 rounded-xl border border-slate-200 bg-slate-50 overflow-hidden shrink-0 flex items-center justify-center relative group"
                                :class="{ 'cursor-zoom-in': activeParticipant.photo_url }"
                                :title="activeParticipant.photo_url ? 'Klik untuk memperbesar pas foto' : 'Pas foto tidak diunggah'"
                                @click="openPhotoModal"
                            >
                                <img
                                    v-if="activeParticipant.photo_url"
                                    :src="activeParticipant.photo_url"
                                    :alt="`Foto ${activeParticipant.full_name}`"
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
                            </button>

                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="px-2 py-0.5 rounded text-xs font-black bg-slate-900 text-white font-mono">
                                        {{ activeParticipant.registration_number }}
                                    </span>
                                    <span v-if="activeParticipant.bib_number" class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200 tabular-nums">
                                        Bib #{{ activeParticipant.bib_number }}
                                    </span>
                                    <span v-if="activeParticipant.preferred_position" class="px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-800 uppercase border border-slate-200">
                                        {{ activeParticipant.preferred_position }}
                                    </span>
                                    <span
                                        class="px-2 py-0.5 rounded text-xs font-bold border"
                                        :class="
                                            activeParticipant.screening_status === 'passed'
                                                ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                                : activeParticipant.screening_status === 'rejected'
                                                ? 'bg-rose-50 text-rose-800 border-rose-300'
                                                : 'bg-amber-50 text-amber-900 border-amber-300'
                                        "
                                    >
                                        {{ activeParticipant.screening_status_label }}
                                    </span>
                                </div>

                                <h2 class="text-base font-bold text-slate-900 leading-tight truncate">
                                    {{ activeParticipant.full_name }}
                                </h2>
                                <p v-if="activeParticipant.nickname" class="text-xs text-slate-600">
                                    Panggilan: <strong class="text-slate-800">{{ activeParticipant.nickname }}</strong>
                                </p>
                                <p class="text-xs text-slate-600">
                                    Kaki: <strong class="capitalize text-slate-800">{{ activeParticipant.dominant_foot || '-' }}</strong> &bull;
                                    TB/BB: {{ activeParticipant.height_cm ? `${activeParticipant.height_cm} cm` : '-' }} / {{ activeParticipant.weight_kg ? `${activeParticipant.weight_kg} kg` : '-' }} &bull;
                                    Jersey: <strong>{{ activeParticipant.jersey_size || '-' }}</strong>
                                </p>
                            </div>
                        </div>

                        <!-- Age Cut-Off Check Box -->
                        <div
                            class="p-3.5 rounded-xl border text-xs space-y-1.5"
                            :class="activeParticipant.is_age_valid ? 'bg-emerald-50/70 border-emerald-300 text-emerald-950' : 'bg-rose-50 border-rose-300 text-rose-950'"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold flex items-center gap-1.5">
                                    <svg v-if="activeParticipant.is_age_valid" class="w-4 h-4 text-emerald-600 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg v-else class="w-4 h-4 text-rose-600 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Pemeriksaan Batas Kelahiran</span>
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-bold"
                                    :class="activeParticipant.is_age_valid ? 'bg-emerald-200 text-emerald-900' : 'bg-rose-200 text-rose-900'"
                                >
                                    {{ activeParticipant.is_age_valid ? 'Sah Sesuai Regulasi' : 'Di Luar Batas Usia' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-200/60">
                                <div>
                                    <span class="text-slate-600 block">Tanggal Lahir:</span>
                                    <span class="font-bold text-slate-900 tabular-nums">{{ activeParticipant.birth_date_formatted }} ({{ activeParticipant.age }} thn)</span>
                                </div>
                                <div>
                                    <span class="text-slate-600 block">Sesi Terdaftar:</span>
                                    <span class="font-bold text-slate-900">{{ activeParticipant.session_name || 'Sesi Umum' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- NIK, Club & Payment Grid -->
                        <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <div>
                                <span class="text-xs font-semibold text-slate-600 block">NIK Peserta</span>
                                <span class="font-mono font-bold text-slate-900 tabular-nums text-xs">{{ activeParticipant.nik }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-600 block">Status Pembayaran</span>
                                <span
                                    class="inline-block px-2 py-0.5 rounded text-xs font-bold mt-0.5 border"
                                    :class="activeParticipant.is_paid ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-amber-50 text-amber-900 border-amber-200'"
                                >
                                    {{ activeParticipant.is_paid ? 'LUNAS' : 'BELUM LUNAS' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-600 block">Asal SSB / Sekolah</span>
                                <span class="font-bold text-slate-800">{{ activeParticipant.current_club_ssb || 'Independen' }}</span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-slate-600 block">Wali / Kontak Darurat</span>
                                <span class="font-bold text-slate-800 block truncate">{{ activeParticipant.emergency_contact_name }}</span>
                                <span class="text-xs text-slate-600 tabular-nums">{{ activeParticipant.emergency_contact_phone }}</span>
                            </div>
                        </div>

                        <!-- Digital Pass Code if Passed -->
                        <div v-if="activeParticipant.screening_code" class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 flex items-center justify-between gap-2">
                            <div>
                                <span class="text-xs font-semibold block text-emerald-800">Kode Digital Pass Resmi</span>
                                <span class="font-mono font-bold text-emerald-950">{{ activeParticipant.screening_code }}</span>
                                <span v-if="activeParticipant.verified_at" class="block text-xs text-emerald-800 mt-0.5">
                                    Terverifikasi: {{ activeParticipant.verified_at }}
                                </span>
                            </div>
                            <a
                                v-if="activeParticipant.can_download_bib"
                                :href="route('audition.bib.download', { participant: activeParticipant.id })"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn-sm-secondary border-emerald-300 text-emerald-800 hover:bg-emerald-100 shrink-0"
                            >
                                Unduh Digital Pass
                            </a>
                        </div>

                        <!-- Rejection Notes if Rejected -->
                        <div v-if="activeParticipant.screening_notes" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900 space-y-1">
                            <span class="font-bold block text-rose-800">Catatan Revisi Terakhir:</span>
                            <p class="text-rose-800 leading-relaxed">{{ activeParticipant.screening_notes }}</p>
                        </div>
                    </div>

                    <!-- Audit Trail History -->
                    <div v-if="activeParticipant.logs && activeParticipant.logs.length > 0" class="frosted-card p-4 bg-white space-y-2.5">
                        <h3 class="text-xs font-bold text-slate-800">Histori Pemeriksaan Peserta</h3>
                        <div class="space-y-2">
                            <div
                                v-for="log in activeParticipant.logs"
                                :key="log.id"
                                class="text-xs p-2.5 rounded-lg border border-slate-100 bg-slate-50/70 space-y-0.5"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-bold text-slate-800">{{ log.actor_name }}</span>
                                    <span class="text-xs text-slate-600 tabular-nums">{{ log.created_at }}</span>
                                </div>
                                <p class="text-slate-700 text-xs leading-relaxed">{{ log.reason }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT PANEL: Interactive Document Viewer & Granular Verification (7 cols) -->
                <div class="lg:col-span-7 flex flex-col space-y-4">
                    <div class="frosted-card p-5 bg-white flex-1 flex flex-col space-y-4">
                        <!-- Document Tabs Header -->
                        <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-200">
                            <div>
                                <h2 class="text-xs font-bold text-slate-800">Berkas Dokumen Lampiran</h2>
                                <p class="text-xs text-slate-600">Periksa dokumen asli langsung di layar tanpa membuka tab baru</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-if="!isLocked && hasDocuments"
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

                                <div role="tablist" aria-label="Pilih berkas dokumen" class="flex flex-wrap items-center gap-1.5">
                                    <button
                                        v-for="(doc, dIdx) in activeParticipant.documents"
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

                        <!-- Active Document Container -->
                        <div v-if="activeDoc" class="flex-1 flex flex-col space-y-3">
                            <!-- Document Meta & Granular Controls -->
                            <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 truncate">{{ activeDoc.label }}</span>
                                        <span v-if="activeDoc.file_size" class="text-slate-600 text-xs tabular-nums">
                                            ({{ (activeDoc.file_size / 1024).toFixed(0) }} KB)
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-600 font-mono truncate">{{ activeDoc.original_name }}</p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="px-2.5 py-2 sm:py-1 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-bold border transition-colors flex items-center gap-1.5"
                                        :class="[
                                            activeDoc.validation_status === 'valid'
                                                ? 'bg-emerald-600 text-white border-emerald-600'
                                                : 'bg-white text-emerald-800 border-emerald-200 hover:bg-emerald-50',
                                            isLocked ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                        :disabled="isLocked || activeDoc.validation_status === 'valid'"
                                        :title="isLocked ? 'Peserta sudah terkunci sah' : (activeDoc.validation_status === 'valid' ? 'Dokumen sudah berstatus sah' : 'Tandai dokumen ini sah')"
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
                                            isLocked ? 'opacity-50 cursor-not-allowed' : ''
                                        ]"
                                        :disabled="isLocked || activeDoc.validation_status === 'invalid'"
                                        :title="isLocked ? 'Peserta sudah terkunci sah' : (activeDoc.validation_status === 'invalid' ? 'Dokumen sudah ditandai perlu revisi' : 'Tandai dokumen ini butuh revisi')"
                                        @click="setDocumentStatus(activeDoc, 'invalid')"
                                    >
                                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span>Butuh Revisi</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Viewer Canvas -->
                            <div class="relative bg-slate-900/5 rounded-2xl border border-slate-200/80 p-3 min-h-[380px] max-h-[520px] flex items-center justify-center overflow-hidden">
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
                                            @click="openDocModal"
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

                                <div v-else class="relative w-full h-full flex items-center justify-center overflow-auto">
                                    <img
                                        :src="activeDoc.preview_url"
                                        :alt="activeDoc.label"
                                        class="max-w-full max-h-[460px] object-contain rounded-lg transition-transform duration-200 cursor-zoom-in"
                                        :style="{ transform: `scale(${imageZoom})` }"
                                        @click="openDocModal"
                                    />

                                    <div class="absolute bottom-3 right-3 bg-slate-900 rounded-xl p-1.5 flex items-center gap-1 text-white text-xs border border-slate-700">
                                        <button type="button" aria-label="Perkecil gambar" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded" title="Zoom Out" @click="zoomOut">-</button>
                                        <span class="text-xs px-1 font-semibold tabular-nums">{{ Math.round(imageZoom * 100) }}%</span>
                                        <button type="button" aria-label="Perbesar gambar" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded" title="Zoom In" @click="zoomIn">+</button>
                                        <button type="button" aria-label="Reset ukuran gambar" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded text-xs" title="Reset Zoom" @click="resetZoom">1:1</button>
                                        <button
                                            type="button"
                                            class="px-2 py-1 min-h-[36px] sm:min-h-0 bg-orange-600 hover:bg-orange-700 rounded font-semibold ml-1 inline-flex items-center gap-1"
                                            @click="openDocModal"
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

                        <!-- No Docs Uploaded State -->
                        <div v-else class="flex-1 flex flex-col items-center justify-center p-12 text-center text-slate-600 space-y-2">
                            <svg class="w-12 h-12 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-xs font-bold text-slate-800">Belum Ada Dokumen Diunggah</p>
                            <p class="text-xs text-slate-600">Peserta belum melampirkan berkas Akta Lahir atau Kartu Keluarga.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Bottom Decision Bar -->
            <div v-if="activeParticipant" class="sticky bottom-4 z-40">
                <div class="frosted-card p-4 bg-slate-900/95 text-white backdrop-blur-md border border-slate-700 shadow-lg flex flex-wrap items-center justify-between gap-3 rounded-2xl">
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 h-8 rounded-lg bg-orange-600 text-white font-mono font-bold flex items-center justify-center text-xs">
                            {{ activeParticipant.registration_number }}
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-white">{{ activeParticipant.full_name }}</span>
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-bold"
                                    :class="
                                        activeParticipant.screening_status === 'passed'
                                            ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                                            : activeParticipant.screening_status === 'rejected'
                                            ? 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                                            : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                                    "
                                >
                                    {{ activeParticipant.screening_status_label }}
                                </span>
                            </div>
                            <span class="text-xs text-slate-300 block mt-0.5">
                                ID Pass: {{ activeParticipant.registration_number }} &bull; {{ activeParticipant.is_paid ? 'Pembayaran Lunas' : 'Menunggu Pembayaran' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <!-- Emergency Unlock -->
                        <button
                            v-if="isLocked"
                            type="button"
                            class="px-3 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-semibold bg-slate-800 text-slate-200 hover:text-white hover:bg-slate-700 border border-slate-700 transition-colors flex items-center gap-1.5"
                            @click="isUnlockModalOpen = true"
                        >
                            <svg class="w-3.5 h-3.5 text-amber-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            <span>Buka Kunci</span>
                        </button>

                        <!-- Reject Button -->
                        <div class="relative group">
                            <button
                                type="button"
                                class="btn-sm-danger px-4 py-2 text-xs font-bold flex items-center gap-1.5"
                                :class="{ 'opacity-50 cursor-not-allowed': !canReject }"
                                :disabled="!canReject"
                                :title="rejectDisabledTooltip"
                                @click="openRejectModal"
                            >
                                <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>{{ activeParticipant.screening_status === 'rejected' ? 'Ubah Catatan Revisi' : 'Tolak / Minta Revisi' }}</span>
                            </button>
                        </div>

                        <!-- Approve Button -->
                        <div class="relative group">
                            <button
                                type="button"
                                class="px-5 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-xs font-bold text-white transition-colors flex items-center gap-1.5"
                                :class="[
                                    canApprove
                                        ? 'bg-emerald-600 hover:bg-emerald-700 cursor-pointer'
                                        : 'bg-slate-700 text-slate-300 opacity-60 cursor-not-allowed'
                                ]"
                                :disabled="!canApprove"
                                :title="approveDisabledTooltip"
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
                                            : (isLocked
                                                ? 'Digital Pass Aktif'
                                                : (hasDocuments && hasPendingDocuments
                                                    ? 'Verifikasi Berkas & Sahkan'
                                                    : 'Sahkan & Aktifkan Pass'))
                                    }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Viewer Modal -->
            <DocumentViewerModal
                :is-open="isDocViewerOpen"
                :title="photoViewerMode ? 'Pas Foto Resmi Peserta' : (activeDoc?.label || 'Pratinjau Dokumen')"
                :subtitle="`Peserta: ${activeParticipant?.full_name} (${activeParticipant?.registration_number})`"
                :file-url="photoViewerMode ? (activeParticipant?.photo_url || '') : (activeDoc?.preview_url || '')"
                :is-pdf="photoViewerMode ? false : Boolean(activeDoc?.is_pdf)"
                :original-name="photoViewerMode ? 'pas_foto_resmi.jpg' : (activeDoc?.original_name || '')"
                :file-size="photoViewerMode ? 0 : (activeDoc?.file_size || 0)"
                :validation-status="photoViewerMode ? '' : (activeDoc?.validation_status || '')"
                :notes="photoViewerMode ? '' : (activeDoc?.notes || '')"
                @close="isDocViewerOpen = false"
            />

            <!-- Rejection Modal -->
            <div v-if="isRejectModalOpen" role="dialog" aria-modal="true" aria-labelledby="audition-reject-title" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/65" @click.self="isRejectModalOpen = false">
                <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 id="audition-reject-title" class="text-sm font-bold text-slate-900">Tolak Berkas (Minta Revisi)</h3>
                            <p class="text-xs text-slate-600">Peserta: {{ activeParticipant?.full_name }}</p>
                        </div>
                        <button type="button" aria-label="Tutup dialog tolak berkas" class="text-slate-600 hover:text-slate-900 text-lg leading-none min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 flex items-center justify-center rounded-lg" @click="isRejectModalOpen = false">&times;</button>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        Tuliskan alasan penolakan secara spesifik agar orang tua/peserta dapat mengunggah perbaikan dokumen melalui portal dashboard:
                    </p>

                    <form novalidate class="space-y-3.5" @submit.prevent="submitRejection">
                        <div>
                            <label for="audition-reject-notes" class="block text-xs font-semibold text-slate-700 mb-1">Alasan Penolakan (Minimal 10 Karakter)</label>
                            <textarea
                                id="audition-reject-notes"
                                v-model="verifyForm.notes"
                                rows="3"
                                placeholder="Contoh: Foto Akta Kelahiran buram pada bagian tanggal lahir. Mohon unggah ulang scan yang terbaca jelas."
                                class="input-field text-xs resize-none"
                                :class="{ 'input-field-error': verifyForm.errors.notes }"
                                @input="verifyForm.clearErrors('notes')"
                            ></textarea>
                            <InputError :message="verifyForm.errors.notes" />
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2 text-xs">
                            <button type="button" class="btn-secondary" @click="isRejectModalOpen = false">Batal</button>
                            <button type="submit" class="btn-danger" :disabled="verifyForm.processing">
                                {{ verifyForm.processing ? 'Menyimpan...' : 'Kirim Catatan Revisi' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Emergency Unlock Modal -->
            <div v-if="isUnlockModalOpen" role="dialog" aria-modal="true" aria-labelledby="audition-unlock-title" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/65" @click.self="isUnlockModalOpen = false">
                <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 id="audition-unlock-title" class="text-sm font-bold text-slate-900">Buka Kunci Screening</h3>
                            <p class="text-xs text-slate-600">Peserta: {{ activeParticipant?.full_name }}</p>
                        </div>
                        <button type="button" aria-label="Tutup dialog buka kunci" class="text-slate-600 hover:text-slate-900 text-lg leading-none min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 flex items-center justify-center rounded-lg" @click="isUnlockModalOpen = false">&times;</button>
                    </div>

                    <p class="text-xs text-amber-900 bg-amber-50 p-3 rounded-xl border border-amber-200 leading-relaxed">
                        Tindakan ini akan mengembalikan status screening peserta dari <strong>Sah</strong> ke <strong>Menunggu Verifikasi</strong> untuk pemeriksaan ulang.
                    </p>

                    <form novalidate class="space-y-3.5" @submit.prevent="submitUnlock">
                        <div>
                            <label for="audition-unlock-reason" class="block text-xs font-semibold text-slate-700 mb-1">Alasan Pembukaan Kunci (Opsional)</label>
                            <input
                                id="audition-unlock-reason"
                                v-model="unlockForm.reason"
                                type="text"
                                placeholder="Contoh: Koreksi dokumen identitas peserta"
                                class="input-field text-xs"
                            />
                        </div>

                        <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2 text-xs">
                            <button type="button" class="btn-secondary" @click="isUnlockModalOpen = false">Batal</button>
                            <button type="submit" class="btn-pitch" :disabled="unlockForm.processing">
                                {{ unlockForm.processing ? 'Membuka...' : 'Ya, Buka Kunci' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Document Verification Wizard Modal -->
            <div
                v-if="isWizardModalOpen && activeParticipant"
                role="dialog"
                aria-modal="true"
                aria-labelledby="audition-wizard-title"
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
                                    {{ activeParticipant.full_name }} ({{ activeParticipant.registration_number }})
                                </span>
                            </div>
                            <h3 id="audition-wizard-title" class="text-base font-bold text-slate-900 mt-0.5">
                                {{
                                    wizardDocIndex < activeParticipant.documents.length
                                        ? `Langkah ${wizardDocIndex + 1} dari ${activeParticipant.documents.length}: ${activeWizardDoc?.label}`
                                        : 'Langkah Terakhir: Pengesahan & Aktivasi Digital Pass'
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

                    <!-- Stepper Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs">
                        <button
                            v-for="(doc, sIdx) in activeParticipant.documents"
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
                            <span class="px-1.5 py-0.5 rounded text-[11px] font-bold tabular-nums" :class="wizardDocIndex === sIdx ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
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

                        <button
                            type="button"
                            class="px-3 py-2 sm:py-1.5 min-h-[44px] sm:min-h-0 rounded-xl font-semibold whitespace-nowrap flex items-center gap-1.5 border transition-colors shrink-0 cursor-pointer"
                            :class="[
                                wizardDocIndex === activeParticipant.documents.length
                                    ? 'bg-emerald-600 text-white border-emerald-600'
                                    : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                            ]"
                            @click="wizardDocIndex = activeParticipant.documents.length"
                        >
                            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Pengesahan Selesai</span>
                        </button>
                    </div>

                    <!-- Step Content: Doc Inspection -->
                    <div v-if="wizardDocIndex < activeParticipant.documents.length && activeWizardDoc" class="flex-1 flex flex-col space-y-3 min-h-0 overflow-y-auto">
                        <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <span class="font-bold text-slate-800">{{ activeWizardDoc.original_name || activeWizardDoc.label }}</span>
                            <span
                                class="px-2.5 py-0.5 rounded-lg text-xs font-bold inline-flex items-center gap-1 border"
                                :class="
                                    activeWizardDoc.validation_status === 'valid'
                                        ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                        : activeWizardDoc.validation_status === 'invalid'
                                        ? 'bg-rose-100 text-rose-800 border-rose-200'
                                        : 'bg-amber-100 text-amber-800 border-amber-200'
                                "
                            >
                                {{ activeWizardDoc.validation_status === 'valid' ? 'Sah' : (activeWizardDoc.validation_status === 'invalid' ? 'Butuh Revisi' : 'Belum Diverifikasi') }}
                            </span>
                        </div>

                        <div class="relative bg-slate-900/5 rounded-2xl border border-slate-200/80 p-3 h-[340px] sm:h-[390px] flex items-center justify-center overflow-hidden">
                            <div v-if="activeWizardDoc.is_pdf" class="w-full h-full flex flex-col items-center justify-center p-2">
                                <iframe
                                    :src="activeWizardDoc.preview_url"
                                    class="w-full h-full rounded-xl border border-slate-200 bg-white"
                                    title="Pratinjau PDF"
                                ></iframe>
                            </div>

                            <div v-else class="relative w-full h-full flex items-center justify-center overflow-auto">
                                <img
                                    :src="activeWizardDoc.preview_url"
                                    :alt="activeWizardDoc.label"
                                    class="max-w-full max-h-full object-contain rounded-lg transition-transform duration-200"
                                    :style="{ transform: `scale(${wizardZoom})` }"
                                />

                                <div class="absolute bottom-3 right-3 bg-slate-900 rounded-xl p-1.5 flex items-center gap-1 text-white text-xs border border-slate-700">
                                    <button type="button" aria-label="Perkecil gambar wizard" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded font-bold cursor-pointer" title="Zoom Out" @click="wizardZoomOut">-</button>
                                    <span class="text-xs px-1 font-semibold tabular-nums">{{ Math.round(wizardZoom * 100) }}%</span>
                                    <button type="button" aria-label="Perbesar gambar wizard" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded font-bold cursor-pointer" title="Zoom In" @click="wizardZoomIn">+</button>
                                    <button type="button" aria-label="Reset ukuran gambar wizard" class="px-2 py-1 min-w-[36px] min-h-[36px] sm:min-w-0 sm:min-h-0 hover:bg-white/20 rounded text-xs cursor-pointer" title="Reset Zoom" @click="resetWizardZoom">1:1</button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="audition-wizard-notes" class="sr-only">Catatan revisi dokumen</label>
                            <input
                                id="audition-wizard-notes"
                                v-model="wizardNotes"
                                type="text"
                                placeholder="Tuliskan catatan revisi (wajib diisi jika dokumen butuh perbaikan peserta)"
                                class="input-field text-xs"
                            />
                        </div>

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

                    <!-- Step Content: Final Review -->
                    <div v-else class="flex-1 flex flex-col space-y-4 min-h-0 overflow-y-auto py-2">
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
                                        Semua {{ activeParticipant.documents.length }} dokumen peserta telah diperiksa dan dinyatakan valid.
                                    </p>
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl border border-slate-200 bg-slate-50/70 flex flex-col sm:flex-row items-center gap-4">
                                <div class="w-20 h-24 rounded-xl bg-slate-200 overflow-hidden shrink-0 border border-slate-300">
                                    <img v-if="activeParticipant.photo_url" :src="activeParticipant.photo_url" :alt="`Foto ${activeParticipant.full_name}`" width="80" height="96" class="w-full h-full object-cover" />
                                </div>
                                <div class="space-y-1 text-center sm:text-left flex-1 min-w-0">
                                    <div class="font-bold text-slate-900 text-sm">{{ activeParticipant.full_name }}</div>
                                    <p class="text-xs text-slate-600">
                                        Lahir: <strong class="tabular-nums">{{ activeParticipant.birth_date_formatted }}</strong> (Usia {{ activeParticipant.age }} thn) &bull; NIK: <strong class="font-mono">{{ activeParticipant.nik }}</strong>
                                    </p>
                                    <div class="pt-2 flex flex-wrap gap-2 text-xs">
                                        <span v-for="doc in activeParticipant.documents" :key="doc.id" class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1">
                                            <svg class="w-3 h-3 text-emerald-600" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{{ doc.label }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

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
                                        <span>{{ verifyForm.processing ? 'Menyimpan...' : 'Sahkan & Aktifkan Digital Pass' }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

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
                                        Peserta tidak dapat disahkan karena terdapat berkas yang memerlukan perbaikan.
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div v-for="inv in invalidDocuments" :key="inv.id" class="p-3 rounded-xl border border-rose-200 bg-rose-50/50 text-xs text-rose-900">
                                    <div class="font-bold">{{ inv.label }}</div>
                                    <p class="text-xs text-rose-800 mt-0.5">Catatan: {{ inv.notes || 'Berkas tidak sesuai regulasi atau buram.' }}</p>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                                <button type="button" class="btn-secondary text-xs cursor-pointer" @click="selectWizardDoc(0)">
                                    Periksa Kembali Berkas
                                </button>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn-secondary text-xs cursor-pointer" @click="closeWizard">Tutup</button>
                                    <button type="button" class="btn-danger text-xs px-4 py-2 font-bold cursor-pointer" @click="goToRejectFromWizard">
                                        Lanjutkan Kirim Revisi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approve Confirmation Modal -->
            <div v-if="isApproveConfirmModalOpen && activeParticipant" role="dialog" aria-modal="true" aria-labelledby="audition-approve-title" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/65" @click.self="isApproveConfirmModalOpen = false">
                <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                        <div>
                            <h3 id="audition-approve-title" class="text-sm font-bold text-slate-900">Konfirmasi Pengesahan Peserta</h3>
                            <p class="text-xs text-slate-600">Peserta: {{ activeParticipant.full_name }}</p>
                        </div>
                        <button type="button" aria-label="Tutup konfirmasi pengesahan" class="text-slate-600 hover:text-slate-900 text-lg leading-none min-w-[44px] min-h-[44px] sm:min-w-0 sm:min-h-0 flex items-center justify-center rounded-lg" @click="isApproveConfirmModalOpen = false">&times;</button>
                    </div>

                    <div class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 space-y-1.5">
                        <div class="font-bold">Seluruh Berkas Terverifikasi Sah</div>
                        <p class="text-xs text-emerald-800 leading-relaxed">
                            Peserta ini akan dinyatakan <strong>SAH (Lolos Screening)</strong>. Sistem akan menerbitkan Digital Pass (E-Tiket) resmi berdasarkan ID Digital Pass peserta.
                        </p>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2 text-xs">
                        <button type="button" class="btn-secondary cursor-pointer" @click="isApproveConfirmModalOpen = false">Batal</button>
                        <button
                            type="button"
                            class="px-4 py-2 min-h-[44px] sm:min-h-0 rounded-xl text-white font-bold bg-emerald-600 hover:bg-emerald-700 cursor-pointer"
                            :disabled="verifyForm.processing"
                            @click="submitFinalApprove"
                        >
                            {{ verifyForm.processing ? 'Menyimpan...' : 'Ya, Sahkan & Aktifkan Pass' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

