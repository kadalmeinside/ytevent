<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import FileInput from '@/Components/FileInput.vue';
import DocumentViewerModal from '@/Components/DocumentViewerModal.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
import { useFormatters } from '@/Composables/useFormatters';
import { compressImage } from '@/Utils/imageCompressor';

const props = defineProps({
    team: {
        type: Object,
        default: null,
    },
    teamsList: {
        type: Array,
        default: () => [],
    },
    players: {
        type: Array,
        default: () => [],
    },
    positions: {
        type: Object,
        default: () => ({}),
    },
});

const { formatDate } = useFormatters();
const { applyValidation, isEmpty, isValidNik, checkFile } = useFormValidator();

const isModalOpen = ref(false);
const editingPlayer = ref(null);
const photoPreview = ref(null);
const isViewingPlayer = ref(false);

const activeDocPlayer = ref(null);
const isDocModalOpen = ref(false);
const isPreviewModalOpen = ref(false);
const previewDoc = ref(null);
const previewDocSubtitle = ref('');

const previewDocument = (doc, player = null) => {
    previewDoc.value = doc;
    previewDocSubtitle.value = player ? `Pemain: ${player.full_name} (#${player.jersey_number})` : '';
    isPreviewModalOpen.value = true;
};
const selectedDocType = ref('');
const docNumber = ref('');
const docForm = useForm({
    document_type: '',
    document_number: '',
    file: null,
});

const playerForm = useForm({
    full_name: '',
    jersey_name: '',
    jersey_number: '',
    position: 'FW',
    dominant_foot: 'Right',
    birth_place: '',
    birth_date: '',
    nik: '',
    nisn: '',
    height_cm: '',
    weight_kg: '',
    photo: null,
});

const openCreateModal = () => {
    editingPlayer.value = null;
    isViewingPlayer.value = false;
    photoPreview.value = null;
    playerForm.reset();
    playerForm.clearErrors();
    playerForm.position = 'FW';
    playerForm.dominant_foot = 'Right';
    isModalOpen.value = true;
};

const openEditModal = (player) => {
    if (player.screening_status === 'submitted' || player.screening_status === 'passed' || player.is_locked) {
        return;
    }
    editingPlayer.value = player;
    isViewingPlayer.value = false;
    photoPreview.value = player.photo_url;
    playerForm.reset();
    playerForm.clearErrors();
    playerForm.full_name = player.full_name;
    playerForm.jersey_name = player.jersey_name || '';
    playerForm.jersey_number = player.jersey_number;
    playerForm.position = player.position;
    playerForm.dominant_foot = player.dominant_foot;
    playerForm.birth_place = player.birth_place || '';
    playerForm.birth_date = player.birth_date;
    playerForm.nik = player.nik;
    playerForm.nisn = player.nisn || '';
    playerForm.height_cm = player.height_cm || '';
    playerForm.weight_kg = player.weight_kg || '';
    playerForm.photo = null;
    isModalOpen.value = true;
};

const openViewModal = (player) => {
    editingPlayer.value = player;
    isViewingPlayer.value = true;
    photoPreview.value = player.photo_url;
    playerForm.reset();
    playerForm.clearErrors();
    playerForm.full_name = player.full_name;
    playerForm.jersey_name = player.jersey_name || '';
    playerForm.jersey_number = player.jersey_number;
    playerForm.position = player.position;
    playerForm.dominant_foot = player.dominant_foot;
    playerForm.birth_place = player.birth_place || '';
    playerForm.birth_date = player.birth_date;
    playerForm.nik = player.nik;
    playerForm.nisn = player.nisn || '';
    playerForm.height_cm = player.height_cm || '';
    playerForm.weight_kg = player.weight_kg || '';
    playerForm.photo = null;
    isModalOpen.value = true;
};

const handlePhotoChange = async (rawFile) => {
    if (!rawFile) {
        playerForm.photo = null;
        photoPreview.value = editingPlayer.value?.photo_url || null;
        return;
    }
    const { file } = await compressImage(rawFile, { maxDimension: 1400, quality: 0.85 });
    playerForm.photo = file;
    playerForm.clearErrors('photo');
    photoPreview.value = URL.createObjectURL(file);
};

const savePlayer = () => {
    if (isViewingPlayer.value) return;
    const ok = applyValidation(playerForm, () => {
        const errs = {};
        if (isEmpty(playerForm.full_name)) errs.full_name = 'Nama lengkap pemain sesuai Akta Kelahiran wajib diisi.';
        if (isEmpty(playerForm.jersey_number)) {
            errs.jersey_number = 'Nomor punggung wajib diisi antara angka 1 hingga 99.';
        } else {
            const num = Number(playerForm.jersey_number);
            if (num < 1 || num > 99) errs.jersey_number = 'Nomor punggung wajib antara 1 hingga 99.';
        }
        if (isEmpty(playerForm.position)) errs.position = 'Posisi bermain wajib dipilih.';
        if (isEmpty(playerForm.birth_date)) errs.birth_date = 'Tanggal lahir pemain wajib diisi.';
        if (isEmpty(playerForm.nik)) {
            errs.nik = 'Nomor Induk Kependudukan (NIK) wajib terdiri dari 16 digit angka.';
        } else if (!isValidNik(playerForm.nik)) {
            errs.nik = 'Nomor Induk Kependudukan (NIK) wajib terdiri dari 16 digit angka.';
        }

        if (!editingPlayer.value) {
            const pCheck = checkFile(playerForm.photo, ['image/jpeg', 'image/png'], 2 * 1024 * 1024);
            if (!pCheck.valid) {
                errs.photo = 'Pas foto resmi pemain wajib diunggah (format JPG/PNG, maks 2 MB).';
            }
        } else if (playerForm.photo) {
            const pCheck = checkFile(playerForm.photo, ['image/jpeg', 'image/png'], 2 * 1024 * 1024);
            if (!pCheck.valid) {
                errs.photo = 'Pas foto resmi pemain harus berformat JPG/PNG (maks 2 MB).';
            }
        }
        return errs;
    });

    if (!ok) return;

    if (editingPlayer.value) {
        playerForm.post(route('manager.players.update', { player: editingPlayer.value.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    } else {
        playerForm.post(route('manager.players.store', { team: props.team.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    }
};

const playerToDelete = ref(null);
const deleteForm = useForm({});
const confirmDelete = (player) => {
    playerToDelete.value = player;
};
const executeDelete = () => {
    if (!playerToDelete.value) return;
    deleteForm.delete(route('manager.players.destroy', { player: playerToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            playerToDelete.value = null;
        },
    });
};

const activeExistingDoc = computed(() => {
    if (!activeDocPlayer.value || !selectedDocType.value) return null;
    return activeDocPlayer.value.documents?.[selectedDocType.value] || null;
});

const existingDocPreview = computed(() => {
    return activeExistingDoc.value?.preview_url || '';
});

const isDocModalReadOnly = computed(() => {
    if (!activeDocPlayer.value) return false;
    return activeDocPlayer.value.screening_status === 'submitted'
        || activeDocPlayer.value.screening_status === 'passed'
        || Boolean(activeDocPlayer.value.is_locked);
});

// Document Upload Modal
const openDocModal = (player, docType = '') => {
    activeDocPlayer.value = player;

    // Prioritaskan dokumen berstatus invalid jika pemain berstatus rejected
    let targetDocType = docType;
    if (!targetDocType && player.documents) {
        const invalidDoc = Object.values(player.documents).find((d) => d.validation_status === 'invalid');
        if (invalidDoc) {
            targetDocType = invalidDoc.document_type;
        }
    }

    selectedDocType.value = targetDocType || (props.team?.event?.required_documents?.[0]?.type || 'birth_certificate');
    const existing = player.documents?.[selectedDocType.value];
    docNumber.value = existing?.document_number || '';
    docForm.reset();
    docForm.clearErrors();
    docForm.document_type = selectedDocType.value;
    docForm.document_number = docNumber.value;
    docForm.file = null;
    isDocModalOpen.value = true;
};

watch(selectedDocType, (newType) => {
    if (activeDocPlayer.value && newType) {
        const existing = activeDocPlayer.value.documents?.[newType];
        docNumber.value = existing?.document_number || '';
        docForm.document_type = newType;
        docForm.document_number = docNumber.value;
        docForm.clearErrors();
    }
});

const saveDocument = () => {
    if (!activeDocPlayer.value || isDocModalReadOnly.value) return;
    docForm.document_type = selectedDocType.value;
    docForm.document_number = docNumber.value;

    const existing = activeDocPlayer.value?.documents?.[docForm.document_type];
    const isFileRequired = !existing;

    const ok = applyValidation(docForm, () => {
        const errs = {};
        if (isEmpty(docForm.document_type)) errs.document_type = 'Jenis dokumen wajib dipilih.';
        if (isFileRequired || docForm.file) {
            const fCheck = checkFile(docForm.file, ['image/jpeg', 'image/png', 'application/pdf'], 2 * 1024 * 1024);
            if (!fCheck.valid) {
                errs.file = 'Berkas dokumen wajib diunggah dalam format JPG, PNG, atau PDF (maksimal 2 MB).';
            }
        }
        return errs;
    });

    if (!ok) return;

    docForm.post(route('manager.players.documents.store', { player: activeDocPlayer.value.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isDocModalOpen.value = false;
        },
    });
};

const submitPlayerForm = useForm({});
const submitForScreening = (player) => {
    submitPlayerForm.post(route('manager.players.submit', { player: player.id }), {
        preserveScroll: true,
    });
};

const recallPlayerForm = useForm({});
const recallPlayer = (player) => {
    if (!confirm(`Tarik pengajuan ${player.full_name} kembali ke status draft untuk perbaikan berkas/data?`)) return;
    recallPlayerForm.post(route('manager.players.recall', { player: player.id }), {
        preserveScroll: true,
    });
};

const isUnpaidLocked = computed(() => {
    return !props.team?.can_manage_roster && props.team?.status !== 'locked_roster';
});

const isFinalRosterLocked = computed(() => {
    return props.team?.status === 'locked_roster';
});
</script>

<template>
    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Daftar Pemain & Dokumen Screening">
        <Head title="Daftar Pemain & Berkas" />

        <div v-if="team" class="space-y-6">
            <!-- Lock Notification if Unpaid -->
            <div
                v-if="isUnpaidLocked"
                class="frosted-card p-6 border-amber-300 bg-amber-50/75 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div>
                    <h2 class="text-sm font-bold text-amber-950">Akses Pengisian Pemain Terkunci</h2>
                    <p class="text-xs text-amber-800 mt-1">
                        Sesuai regulasi kompetisi <strong>{{ team.event?.name }}</strong>, akses input nama pemain dan dokumen screening terbuka setelah pembayaran pendaftaran tim dinyatakan <strong>LUNAS</strong>.
                    </p>
                </div>
                <Link :href="route('manager.payments')" class="btn-pitch text-xs shrink-0">
                    Selesaikan Pembayaran Tagihan
                </Link>
            </div>

            <div v-else class="space-y-5">
                <!-- Header Actions & Quota Counter -->
                <div class="frosted-card p-4 sm:p-5 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Daftar Skuad Pemain Resmi</h2>
                            <p class="text-xs text-slate-600 mt-0.5">
                                Batas kelahiran kompetisi: <strong class="tabular-data">{{ formatDate(team.event?.min_birth_date) }} &ndash; {{ formatDate(team.event?.max_birth_date) }}</strong>
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <span class="text-[11px] text-slate-500 block">Kuota Skuad Terisi</span>
                                <span class="text-sm font-bold tabular-data" :class="players.length >= team.event?.max_players_per_team ? 'text-rose-700' : 'text-slate-900'">
                                    {{ players.length }} / {{ team.event?.max_players_per_team }} Pemain
                                </span>
                            </div>
                            <button
                                v-if="!isFinalRosterLocked"
                                type="button"
                                class="btn-pitch"
                                :disabled="players.length >= team.event?.max_players_per_team"
                                @click="openCreateModal"
                            >
                                + Tambah Pemain Baru
                            </button>
                            <span
                                v-else
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-extrabold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-300"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                                Skuad Final Terkunci
                            </span>
                        </div>
                    </div>

                    <!-- Real-Time Squad Requirement Checklist Strip -->
                    <div v-if="team.workflow" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 pt-3 border-t border-slate-100">
                        <div
                            class="p-2.5 rounded-xl border flex items-center justify-between text-xs"
                            :class="team.workflow.is_min_players_met ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' : 'bg-amber-50/70 border-amber-200 text-amber-900'"
                        >
                            <span class="font-semibold">1. Kuota Min. Pemain</span>
                            <span class="font-extrabold tabular-data px-1.5 py-0.5 rounded text-[11px]" :class="team.workflow.is_min_players_met ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                {{ team.workflow.counts.players_total }}/{{ team.workflow.counts.min_players_required }}
                            </span>
                        </div>
                        <div
                            class="p-2.5 rounded-xl border flex items-center justify-between text-xs"
                            :class="team.workflow.is_gk_met ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' : 'bg-amber-50/70 border-amber-200 text-amber-900'"
                        >
                            <span class="font-semibold">2. Min. {{ team.workflow.counts.min_gk_required || 2 }} Kiper (GK)</span>
                            <span class="font-extrabold tabular-data px-1.5 py-0.5 rounded text-[11px]" :class="team.workflow.is_gk_met ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                {{ team.workflow.counts.gk_total }}/{{ team.workflow.counts.min_gk_required }} GK
                            </span>
                        </div>
                        <div
                            class="p-2.5 rounded-xl border flex items-center justify-between text-xs"
                            :class="team.workflow.counts.players_draft === 0 && team.workflow.counts.players_rejected === 0 && team.workflow.counts.players_total > 0 ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' : 'bg-amber-50/70 border-amber-200 text-amber-900'"
                        >
                            <span class="font-semibold">3. Status Pengajuan</span>
                            <span class="font-extrabold tabular-data px-1.5 py-0.5 rounded text-[11px]" :class="team.workflow.counts.players_draft === 0 && team.workflow.counts.players_rejected === 0 && team.workflow.counts.players_total > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                {{ team.workflow.counts.players_draft > 0 ? `${team.workflow.counts.players_draft} Draft` : (team.workflow.counts.players_rejected > 0 ? `${team.workflow.counts.players_rejected} Revisi` : 'Semua Diajukan') }}
                            </span>
                        </div>
                        <div
                            class="p-2.5 rounded-xl border flex items-center justify-between text-xs"
                            :class="team.workflow.is_all_players_passed ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' : 'bg-blue-50/70 border-blue-200 text-blue-900'"
                        >
                            <span class="font-semibold">4. Lolos Screening</span>
                            <span class="font-extrabold tabular-data px-1.5 py-0.5 rounded text-[11px]" :class="team.workflow.is_all_players_passed ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'">
                                {{ team.workflow.counts.players_passed }}/{{ team.workflow.counts.players_total }} SAH
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Global Submission Error Banner if any -->
                <FormErrorBanner :errors="submitPlayerForm.errors" />

                <!-- Players Container (Responsive Card List View) -->
                <div class="space-y-3.5">
                    <div v-if="players.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500">
                        <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="font-bold text-slate-800 text-sm">Belum ada pemain yang didaftarkan</p>
                        <p class="mt-1 text-slate-400">Klik tombol <strong>+ Tambah Pemain Baru</strong> di atas untuk mulai melengkapi skuad tim Anda.</p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="p in players"
                            :key="p.id"
                            class="frosted-card p-4 sm:p-5 transition-all duration-150 space-y-3"
                            :class="[
                                p.screening_status === 'rejected'
                                    ? 'border-rose-400/90 bg-rose-50/30 ring-1 ring-rose-400/40 shadow-sm'
                                    : (p.screening_status === 'passed' ? 'border-emerald-200/90 bg-emerald-50/15' : 'bg-white hover:border-orange-300/80 hover:shadow-md')
                            ]"
                        >
                            <!-- Header: Jersey Number, Photo, Name, Position, Status & Actions -->
                            <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="relative shrink-0">
                                        <img
                                            v-if="p.photo_url"
                                            :src="p.photo_url"
                                            :alt="p.full_name"
                                            class="w-12 h-12 rounded-xl object-cover border border-slate-200 bg-slate-100 shadow-2xs"
                                        />
                                        <div
                                            v-else
                                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-100 to-amber-100 text-orange-700 font-extrabold flex items-center justify-center text-sm shadow-2xs border border-orange-200"
                                        >
                                            FOTO
                                        </div>
                                        <span class="absolute -bottom-1 -right-1 font-mono font-black text-[11px] bg-slate-900 text-white px-1.5 py-0.2 rounded-md shadow">
                                            #{{ p.jersey_number }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="text-sm sm:text-base font-bold text-slate-900 leading-tight">{{ p.full_name }}</h4>
                                            <span v-if="p.jersey_name" class="text-xs text-slate-400 font-medium">({{ p.jersey_name }})</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                                                {{ p.position }} ({{ p.dominant_foot }})
                                            </span>
                                            <StatusBadge :status="p.screening_status" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-1.5 ml-auto sm:ml-0 flex-wrap">
                                    <button
                                        v-if="['draft', 'rejected'].includes(p.screening_status) && p.is_complete_for_submission"
                                        type="button"
                                        class="inline-flex items-center gap-1 shadow-2xs text-xs font-bold px-3 py-1.5 rounded-lg text-white"
                                        :class="p.screening_status === 'rejected' ? 'bg-rose-600 hover:bg-rose-700 ring-2 ring-rose-400/40' : 'btn-sm-pitch'"
                                        :disabled="submitPlayerForm.processing"
                                        @click="submitForScreening(p)"
                                    >
                                        <svg v-if="p.screening_status === 'rejected'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>{{ p.screening_status === 'rejected' ? 'Ajukan Ulang Perbaikan' : 'Ajukan Screening' }}</span>
                                    </button>
                                    <button
                                        v-if="p.screening_status === 'submitted' && !p.is_locked"
                                        type="button"
                                        class="btn-sm-secondary inline-flex items-center gap-1 text-amber-800 bg-amber-50 hover:bg-amber-100 border-amber-300"
                                        :disabled="recallPlayerForm.processing"
                                        @click="recallPlayer(p)"
                                        title="Tarik pengajuan kembali ke draft untuk perbaikan berkas/data"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v2m-15-7l4-4m-4 4l4 4" />
                                        </svg>
                                        <span>Tarik Pengajuan</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-sm-secondary inline-flex items-center gap-1"
                                        @click="openDocModal(p)"
                                    >
                                        <svg class="w-3.5 h-3.5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <span>{{ ['draft', 'rejected'].includes(p.screening_status) && !p.is_locked ? 'Berkas Dokumen' : 'Lihat Berkas' }}</span>
                                    </button>
                                    <button
                                        v-if="['draft', 'rejected'].includes(p.screening_status) && !p.is_locked"
                                        type="button"
                                        class="btn-sm-secondary"
                                        @click="openEditModal(p)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="btn-sm-secondary inline-flex items-center gap-1"
                                        @click="openViewModal(p)"
                                        title="Lihat detail data pemain"
                                    >
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Lihat Detail</span>
                                    </button>
                                    <button
                                        v-if="['draft', 'rejected'].includes(p.screening_status) && !p.is_locked"
                                        type="button"
                                        class="btn-sm-danger"
                                        @click="confirmDelete(p)"
                                        title="Hapus data pemain dari skuad"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </div>

                            <!-- Card Body Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-xs items-center">
                                <!-- Birth Info (Col 1-3) -->
                                <div class="lg:col-span-3 space-y-0.5">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Kelahiran</span>
                                    <span class="font-mono tabular-data font-semibold text-slate-800 block text-xs">
                                        {{ formatDate(p.birth_date) }}
                                    </span>
                                    <span class="text-[11px] text-slate-500 block truncate">
                                        {{ p.birth_place || 'Tempat lahir tidak dicantumkan' }}
                                    </span>
                                </div>

                                <!-- NIK & NISN (Col 4-6) -->
                                <div class="lg:col-span-3 space-y-0.5">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">NIK &amp; NISN</span>
                                    <span class="font-mono tabular-data font-semibold text-slate-800 block text-xs">
                                        {{ p.nik }}
                                    </span>
                                    <span class="text-[11px] font-mono text-slate-500 block">
                                        NISN: {{ p.nisn || '-' }}
                                    </span>
                                </div>

                                <!-- Required Documents Status Chips (Col 7-12) -->
                                <div class="lg:col-span-6 space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] uppercase font-bold text-slate-400 block">Kelengkapan Dokumen Wajib</span>
                                        <button
                                            type="button"
                                            class="text-[11px] font-semibold text-orange-600 hover:text-orange-700 hover:underline"
                                            @click="openDocModal(p)"
                                        >
                                            {{ ['draft', 'rejected'].includes(p.screening_status) && !p.is_locked ? '+ Unggah / Ganti' : 'Lihat Berkas' }}
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="doc in team.event?.required_documents"
                                            :key="doc.type"
                                            type="button"
                                            class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold border transition-all text-left"
                                            :class="[
                                                !p.documents[doc.type]
                                                    ? 'bg-rose-50 text-rose-800 border-rose-200 hover:bg-rose-100'
                                                    : (p.documents[doc.type].validation_status === 'invalid'
                                                        ? 'bg-rose-100 text-rose-950 border-rose-400 font-extrabold shadow-2xs hover:bg-rose-200'
                                                        : (p.documents[doc.type].validation_status === 'valid'
                                                            ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100'
                                                            : 'bg-amber-50 text-amber-800 border-amber-300 hover:bg-amber-100'))
                                            ]"
                                            @click="p.documents[doc.type] ? previewDocument(p.documents[doc.type], p) : openDocModal(p, doc.type)"
                                            :title="p.documents[doc.type] ? 'Klik untuk melihat dokumen di modal view' : 'Klik untuk mengunggah dokumen'"
                                        >
                                            <!-- Belum Diunggah -->
                                            <template v-if="!p.documents[doc.type]">
                                                <svg class="w-3 h-3 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                                <span>Belum: {{ doc.label }}</span>
                                            </template>

                                            <!-- Butuh Revisi -->
                                            <template v-else-if="p.documents[doc.type].validation_status === 'invalid'">
                                                <svg class="w-3 h-3 text-rose-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                <span>Revisi: {{ doc.label }}</span>
                                            </template>

                                            <!-- Sah / Valid -->
                                            <template v-else-if="p.documents[doc.type].validation_status === 'valid'">
                                                <svg class="w-3 h-3 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span>Sah: {{ doc.label }}</span>
                                            </template>

                                            <!-- Menunggu Verifikasi -->
                                            <template v-else>
                                                <svg class="w-3 h-3 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Menunggu: {{ doc.label }}</span>
                                            </template>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Rejection Note Alert Banner if rejected -->
                            <div v-if="p.screening_status === 'rejected'" class="p-3.5 rounded-xl bg-rose-50 border border-rose-300 text-xs text-rose-950 space-y-2">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-2 font-bold text-rose-900">
                                        <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span class="tracking-wide">STATUS: DITOLAK / MEMERLUKAN REVISI</span>
                                    </div>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded bg-rose-200/80 text-rose-900 border border-rose-300">
                                        Tindakan Diperlukan
                                    </span>
                                </div>
                                <p v-if="p.rejection_reason" class="text-rose-900 leading-relaxed pl-6">
                                    <strong>Catatan Petugas Screening:</strong> {{ p.rejection_reason }}
                                </p>
                                <div class="pl-6 text-[11px] text-rose-700">
                                    Klik tombol <strong>Edit</strong> untuk memperbaiki biodata atau <strong>Berkas Dokumen</strong> untuk mengunggah ulang berkas yang diminta, kemudian klik <strong>Ajukan Ulang Perbaikan</strong>.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah / Edit / Lihat Pemain -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm overflow-y-auto"
        >
            <div class="bg-white/95 backdrop-blur-xl border border-slate-200 rounded-xl shadow-xl max-w-2xl w-full p-6 my-8">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ isViewingPlayer ? `Detail Data Pemain: ${editingPlayer?.full_name}` : (editingPlayer ? `Edit Data Pemain: ${editingPlayer.full_name}` : 'Tambah Pemain Baru') }}
                    </h3>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-900" @click="isModalOpen = false">
                        Tutup
                    </button>
                </div>

                <div class="mt-4">
                    <!-- Status Alert Banner if in View / Read-Only Mode -->
                    <div
                        v-if="isViewingPlayer && editingPlayer?.screening_status === 'submitted'"
                        class="p-3 mb-4 rounded-xl bg-amber-50 border border-amber-300 text-xs text-amber-950 flex items-start gap-2.5"
                    >
                        <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <span class="font-bold block">Data Pemain Terkunci (Sedang Diperiksa Panitia)</span>
                            <p class="mt-0.5 text-amber-800 leading-relaxed">
                                Data pemain ini sedang dalam proses screening. Untuk mengedit nama, nomor punggung, atau pas foto, silakan tutup modal ini lalu tekan tombol <strong class="font-bold underline">Tarik Pengajuan</strong> terlebih dahulu.
                            </p>
                        </div>
                    </div>

                    <div
                        v-else-if="isViewingPlayer && (editingPlayer?.screening_status === 'passed' || editingPlayer?.is_locked)"
                        class="p-3 mb-4 rounded-xl bg-emerald-50 border border-emerald-300 text-xs text-emerald-950 flex items-start gap-2.5"
                    >
                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <span class="font-bold block">Pemain Sah (Lolos Screening)</span>
                            <p class="mt-0.5 text-emerald-800 leading-relaxed">
                                Data pemain telah diverifikasi sah oleh panitia kompetisi dan terkunci permanen.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="!isViewingPlayer && editingPlayer?.screening_status === 'rejected'"
                        class="p-3 mb-4 rounded-xl bg-rose-50 border border-rose-300 text-xs text-rose-950 flex items-start gap-2.5"
                    >
                        <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <span class="font-bold text-rose-900 block">Mode Revisi: Pemain Ditolak Panitia</span>
                            <p v-if="editingPlayer.rejection_reason" class="mt-0.5 text-rose-800 leading-relaxed">
                                <strong>Catatan Petugas:</strong> {{ editingPlayer.rejection_reason }}
                            </p>
                            <p class="mt-0.5 text-rose-700 leading-relaxed text-[11px]">
                                Silakan perbaiki biodata atau ganti pas foto pemain sesuai catatan di atas, kemudian klik <strong>Simpan Pemain</strong>.
                            </p>
                        </div>
                    </div>

                    <FormErrorBanner :errors="playerForm.errors" />

                    <form @submit.prevent="savePlayer" novalidate class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div data-field="full_name" class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nama Lengkap Sesuai Akta Kelahiran
                                </label>
                                <input
                                    v-model="playerForm.full_name"
                                    type="text"
                                    :disabled="isViewingPlayer"
                                    placeholder="Contoh: Muhammad Rizky Pratama"
                                    class="input-field"
                                    :class="[
                                        isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : '',
                                        { 'input-field-error': playerForm.errors.full_name }
                                    ]"
                                    @input="playerForm.clearErrors('full_name')"
                                />
                                <InputError :message="playerForm.errors.full_name" />
                            </div>

                            <div data-field="jersey_name">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Punggung (Jersey)</label>
                                <input
                                    v-model="playerForm.jersey_name"
                                    type="text"
                                    :disabled="isViewingPlayer"
                                    placeholder="Contoh: RIZKY"
                                    class="input-field uppercase"
                                    :class="isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : ''"
                                />
                            </div>

                            <div data-field="jersey_number">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Punggung (1–99)</label>
                                <input
                                    v-model="playerForm.jersey_number"
                                    type="number"
                                    min="1"
                                    max="99"
                                    :disabled="isViewingPlayer"
                                    placeholder="10"
                                    class="input-field tabular-data"
                                    :class="[
                                        isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : '',
                                        { 'input-field-error': playerForm.errors.jersey_number }
                                    ]"
                                    @input="playerForm.clearErrors('jersey_number')"
                                />
                                <InputError :message="playerForm.errors.jersey_number" />
                            </div>

                            <div data-field="position">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Posisi Bermain</label>
                                <select
                                    v-model="playerForm.position"
                                    :disabled="isViewingPlayer"
                                    class="input-field"
                                    :class="[
                                        isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : '',
                                        { 'input-field-error': playerForm.errors.position }
                                    ]"
                                    @change="playerForm.clearErrors('position')"
                                >
                                    <option v-for="(label, code) in positions" :key="code" :value="code">
                                        {{ label }}
                                    </option>
                                </select>
                                <InputError :message="playerForm.errors.position" />
                            </div>

                            <div data-field="dominant_foot">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kaki Dominan</label>
                                <select
                                    v-model="playerForm.dominant_foot"
                                    :disabled="isViewingPlayer"
                                    class="input-field"
                                    :class="isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : ''"
                                >
                                    <option value="Right">Kanan (Right)</option>
                                    <option value="Left">Kiri (Left)</option>
                                    <option value="Both">Kedua Kaki (Both)</option>
                                </select>
                            </div>

                            <div data-field="birth_date">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tanggal Lahir</label>
                                <input
                                    v-model="playerForm.birth_date"
                                    type="date"
                                    :disabled="isViewingPlayer"
                                    class="input-field tabular-data"
                                    :class="[
                                        isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : '',
                                        { 'input-field-error': playerForm.errors.birth_date }
                                    ]"
                                    @input="playerForm.clearErrors('birth_date')"
                                />
                                <InputError :message="playerForm.errors.birth_date" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tempat Lahir</label>
                                <input
                                    v-model="playerForm.birth_place"
                                    type="text"
                                    :disabled="isViewingPlayer"
                                    placeholder="Kota Kelahiran"
                                    class="input-field"
                                    :class="isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : ''"
                                />
                            </div>

                            <div data-field="nik">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Induk Kependudukan (NIK 16 Digit)</label>
                                <input
                                    v-model="playerForm.nik"
                                    type="text"
                                    maxlength="16"
                                    :disabled="isViewingPlayer"
                                    placeholder="3201xxxxxxxxxxxx"
                                    class="input-field tabular-data"
                                    :class="[
                                        isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : '',
                                        { 'input-field-error': playerForm.errors.nik }
                                    ]"
                                    @input="playerForm.clearErrors('nik')"
                                />
                                <InputError :message="playerForm.errors.nik" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">NISN (Opsional)</label>
                                <input
                                    v-model="playerForm.nisn"
                                    type="text"
                                    :disabled="isViewingPlayer"
                                    placeholder="Nomor Induk Siswa Nasional"
                                    class="input-field tabular-data"
                                    :class="isViewingPlayer ? 'bg-slate-100 text-slate-600 cursor-not-allowed' : ''"
                                />
                            </div>

                            <div data-field="photo" class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                    {{ isViewingPlayer ? 'Pas Foto Resmi Pemain' : 'Pas Foto Resmi Pemain (JPG/PNG, maks. 2 MB)' }}
                                </label>
                                <FileInput
                                    v-model="playerForm.photo"
                                    accept=".jpg,.jpeg,.png"
                                    :preview-url="photoPreview"
                                    :disabled="isViewingPlayer"
                                    label="Pilih Foto Pemain"
                                    hint="Pas foto wajah terbaru pemain dengan background polos (Maks 2 MB)"
                                    :error="playerForm.errors.photo"
                                    @change="handlePhotoChange"
                                />
                            </div>
                        </div>

                        <div class="mt-6 pt-3 border-t border-slate-200 flex items-center justify-end gap-2.5">
                            <template v-if="!isViewingPlayer">
                                <button type="button" class="btn-secondary" @click="isModalOpen = false">
                                    Batal
                                </button>
                                <button type="submit" class="btn-pitch" :disabled="playerForm.processing">
                                    {{ playerForm.processing ? 'Menyimpan...' : (editingPlayer ? 'Perbarui Data Pemain' : 'Simpan Pemain') }}
                                </button>
                            </template>
                            <template v-else>
                                <button type="button" class="btn-secondary" @click="isModalOpen = false">
                                    Tutup
                                </button>
                            </template>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Unggah Dokumen -->
        <div
            v-if="isDocModalOpen && activeDocPlayer"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        >
            <div class="bg-white/95 backdrop-blur-xl border border-slate-200 rounded-xl shadow-xl max-w-lg w-full p-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">
                            {{ isDocModalReadOnly ? 'Pratinjau Berkas Screening' : 'Unggah Berkas Screening' }}
                        </h3>
                        <p class="text-xs text-slate-500">Pemain: {{ activeDocPlayer.full_name }} (#{{ activeDocPlayer.jersey_number }})</p>
                    </div>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-900" @click="isDocModalOpen = false">
                        Tutup
                    </button>
                </div>

                <div class="mt-4">
                    <!-- Status Alert Banner if in submitted/passed status -->
                    <div
                        v-if="activeDocPlayer.screening_status === 'submitted'"
                        class="p-3 mb-3.5 rounded-xl bg-amber-50 border border-amber-300 text-xs text-amber-950 flex items-start gap-2.5"
                    >
                        <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <span class="font-bold block">Pengajuan Berkas Terkunci (Sedang Diperiksa Panitia)</span>
                            <p class="mt-0.5 text-amber-800 leading-relaxed">
                                Berkas ini terkunci karena pemain sudah diajukan ke antrean screening panitia. Untuk mengganti berkas atau mengoreksi data, silakan tutup modal ini lalu tekan tombol <strong class="font-bold underline">Tarik Pengajuan</strong> terlebih dahulu.
                            </p>
                        </div>
                    </div>

                    <div
                        v-else-if="activeDocPlayer.screening_status === 'passed'"
                        class="p-3 mb-3.5 rounded-xl bg-emerald-50 border border-emerald-300 text-xs text-emerald-950 flex items-start gap-2.5"
                    >
                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <div>
                            <span class="font-bold block">Pemain Sah (Lolos Screening)</span>
                            <p class="mt-0.5 text-emerald-800 leading-relaxed">
                                Berkas telah diverifikasi sah oleh panitia kompetisi dan terkunci permanen.
                            </p>
                        </div>
                    </div>

                    <div
                        v-else-if="activeDocPlayer.screening_status === 'rejected'"
                        class="p-3 mb-3.5 rounded-xl bg-rose-50 border border-rose-300 text-xs text-rose-950 flex items-start gap-2.5"
                    >
                        <svg class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <span class="font-bold text-rose-900 block">Pemain Berstatus Ditolak / Perlu Revisi Berkas</span>
                            <p v-if="activeDocPlayer.rejection_reason" class="mt-0.5 text-rose-800 leading-relaxed">
                                <strong>Catatan Petugas:</strong> {{ activeDocPlayer.rejection_reason }}
                            </p>
                            <p class="mt-0.5 text-rose-700 leading-relaxed text-[11px]">
                                Silakan pilih jenis berkas yang perlu diganti di bawah, unggah berkas pengganti yang sah, lalu klik Simpan Berkas.
                            </p>
                        </div>
                    </div>

                    <FormErrorBanner :errors="docForm.errors" />

                    <form @submit.prevent="saveDocument" novalidate class="space-y-4">
                        <div data-field="document_type">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Jenis Dokumen Wajib</label>
                            <select
                                v-model="selectedDocType"
                                class="input-field"
                                :class="{ 'input-field-error': docForm.errors.document_type }"
                            >
                                <option
                                    v-for="doc in team.event?.required_documents"
                                    :key="doc.type"
                                    :value="doc.type"
                                >
                                    {{ doc.label }}
                                </option>
                            </select>
                            <InputError :message="docForm.errors.document_type" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Dokumen Resmi (Opsional)</label>
                            <input
                                v-model="docNumber"
                                type="text"
                                :disabled="isDocModalReadOnly"
                                placeholder="Contoh: No Akta / No Kartu Keluarga"
                                class="input-field"
                                :class="{ 'bg-slate-100 text-slate-500 cursor-not-allowed': isDocModalReadOnly }"
                            />
                        </div>

                        <!-- Berkas yang telah diunggah -->
                        <div
                            v-if="activeExistingDoc"
                            class="p-3.5 rounded-xl border bg-orange-50/50 border-orange-200 text-xs space-y-2.5"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-700 flex items-center justify-center shrink-0 border border-orange-200">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Berkas Terunggah Saat Ini</span>
                                        <p class="font-bold text-slate-900 truncate text-xs">{{ activeExistingDoc.original_name || 'Dokumen Terlampir' }}</p>
                                    </div>
                                </div>

                                <!-- Button View Berkas (Modal View) -->
                                <button
                                    type="button"
                                    class="btn-sm-pitch shrink-0 flex items-center gap-1.5 shadow-2xs"
                                    title="Pratinjau berkas gambar atau PDF langsung di modal view"
                                    @click="previewDocument(activeExistingDoc, activeDocPlayer)"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <span>Pratinjau Berkas</span>
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-600 pt-2 border-t border-orange-200/60">
                                <span v-if="activeExistingDoc.document_number">
                                    No. Dokumen: <strong class="text-slate-800 font-mono">{{ activeExistingDoc.document_number }}</strong>
                                </span>
                                <span v-if="activeExistingDoc.file_size">
                                    Ukuran: <span class="font-mono text-slate-700">{{ (activeExistingDoc.file_size / 1024).toFixed(0) }} KB</span>
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ml-auto"
                                    :class="
                                        activeExistingDoc.validation_status === 'valid'
                                            ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                                            : activeExistingDoc.validation_status === 'invalid'
                                            ? 'bg-rose-100 text-rose-800 border border-rose-300'
                                            : 'bg-amber-100 text-amber-800 border border-amber-300'
                                    "
                                >
                                    {{ activeExistingDoc.validation_status === 'valid' ? 'Lolos Verifikasi' : activeExistingDoc.validation_status === 'invalid' ? 'Ditolak' : 'Menunggu Verifikasi' }}
                                </span>
                            </div>

                            <div v-if="activeExistingDoc.notes" class="text-[11px] text-rose-800 bg-rose-50 p-2 rounded-lg border border-rose-200">
                                <strong>Catatan Petugas Screening:</strong> {{ activeExistingDoc.notes }}
                            </div>
                        </div>

                        <!-- Jika belum ada berkas dan sedang mode readonly / pratinjau -->
                        <div
                            v-else-if="isDocModalReadOnly"
                            class="p-4 rounded-xl border border-slate-200 bg-slate-50 text-center text-xs text-slate-500"
                        >
                            <svg class="w-8 h-8 text-slate-300 mx-auto mb-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="font-bold text-slate-700">Belum Ada Berkas Diunggah</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Dokumen ini belum pernah diunggah untuk pemain {{ activeDocPlayer?.full_name }}.</p>
                        </div>

                        <div v-if="!isDocModalReadOnly" data-field="file">
                            <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                {{ activeExistingDoc ? 'Ganti Berkas Scan / Foto Baru (Opsional)' : 'Pilih Berkas Scan / Foto Dokumen (JPG, PNG, atau PDF — Maks. 2 MB)' }}
                            </label>
                            <FileInput
                                v-model="docForm.file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                :preview-url="existingDocPreview"
                                :disabled="isDocModalReadOnly"
                                label="Pilih Berkas Dokumen"
                                hint="Format: JPG, PNG, atau PDF (maksimal 2 MB)"
                                :error="docForm.errors.file"
                                @change="docForm.clearErrors('file')"
                            />
                        </div>

                        <div class="pt-3 flex justify-end gap-2.5">
                            <template v-if="!isDocModalReadOnly">
                                <button type="button" class="btn-secondary" @click="isDocModalOpen = false">
                                    Batal
                                </button>
                                <button type="submit" class="btn-pitch" :disabled="docForm.processing">
                                    {{ docForm.processing ? 'Mengunggah Berkas...' : 'Simpan Berkas' }}
                                </button>
                            </template>
                            <template v-else>
                                <button type="button" class="btn-secondary" @click="isDocModalOpen = false">
                                    Tutup
                                </button>
                            </template>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Konfirmasi Hapus -->
        <ConfirmModal
            :show="Boolean(playerToDelete)"
            title="Hapus Data Draft Pemain"
            :description="`Apakah Anda yakin ingin menghapus ${playerToDelete?.full_name}? Seluruh berkas lampiran yang diunggah akan dihapus secara permanen.`"
            confirm-text="Ya, Hapus Pemain"
            :danger="true"
            :processing="deleteForm.processing"
            @confirm="executeDelete"
            @close="playerToDelete = null"
        />

        <!-- Document Viewer Modal (Image & PDF) -->
        <DocumentViewerModal
            :is-open="isPreviewModalOpen"
            :title="previewDoc?.label || 'Pratinjau Dokumen'"
            :subtitle="previewDocSubtitle"
            :file-url="previewDoc?.preview_url || ''"
            :is-pdf="Boolean(previewDoc?.is_pdf)"
            :original-name="previewDoc?.original_name || ''"
            :document-number="previewDoc?.document_number || ''"
            :file-size="previewDoc?.file_size || 0"
            :validation-status="previewDoc?.validation_status || ''"
            :notes="previewDoc?.notes || ''"
            @close="isPreviewModalOpen = false"
        />
    </ManagerLayout>
</template>

