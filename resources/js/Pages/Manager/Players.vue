<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
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

const activeDocPlayer = ref(null);
const isDocModalOpen = ref(false);
const selectedDocType = ref('');
const docNumber = ref('');
const docFile = ref(null);
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
    photoPreview.value = null;
    playerForm.reset();
    playerForm.clearErrors();
    playerForm.position = 'FW';
    playerForm.dominant_foot = 'Right';
    isModalOpen.value = true;
};

const openEditModal = (player) => {
    editingPlayer.value = player;
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

const handlePhotoChange = async (e) => {
    const rawFile = e.target.files?.[0] || null;
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

// Document Upload Modal
const openDocModal = (player, docType = '') => {
    activeDocPlayer.value = player;
    selectedDocType.value = docType || (props.team?.event?.required_documents?.[0]?.type || 'birth_certificate');
    const existing = player.documents?.[selectedDocType.value];
    docNumber.value = existing?.document_number || '';
    docFile.value = null;
    docForm.reset();
    docForm.clearErrors();
    isDocModalOpen.value = true;
};

const handleDocFile = (e) => {
    docFile.value = e.target.files?.[0] || null;
    docForm.file = docFile.value;
    docForm.clearErrors('file');
};

const saveDocument = () => {
    if (!activeDocPlayer.value) return;
    docForm.document_type = selectedDocType.value;
    docForm.document_number = docNumber.value;
    docForm.file = docFile.value;

    const ok = applyValidation(docForm, () => {
        const errs = {};
        if (isEmpty(docForm.document_type)) errs.document_type = 'Jenis dokumen wajib dipilih.';
        const fCheck = checkFile(docForm.file, ['image/jpeg', 'image/png', 'application/pdf'], 2 * 1024 * 1024);
        if (!fCheck.valid) {
            errs.file = 'Berkas dokumen wajib diunggah dalam format JPG, PNG, atau PDF (maksimal 2 MB).';
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

const isRosterLocked = computed(() => {
    return !props.team?.can_manage_roster;
});
</script>

<template>
    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Daftar Pemain & Dokumen Screening">
        <Head title="Daftar Pemain & Berkas" />

        <div v-if="team" class="space-y-6">
            <!-- Lock Notification if Unpaid -->
            <div
                v-if="isRosterLocked"
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
                <div class="frosted-card p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
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
                            type="button"
                            class="btn-pitch text-xs"
                            :disabled="players.length >= team.event?.max_players_per_team"
                            @click="openCreateModal"
                        >
                            + Tambah Pemain Baru
                        </button>
                    </div>
                </div>

                <!-- Global Submission Error Banner if any -->
                <FormErrorBanner :errors="submitPlayerForm.errors" />

                <!-- Players Table / List -->
                <div class="frosted-card overflow-hidden">
                    <div v-if="players.length === 0" class="p-10 text-center text-xs text-slate-500">
                        Belum ada pemain yang didaftarkan. Klik tombol <strong>+ Tambah Pemain Baru</strong> untuk mulai melengkapi skuad tim.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 text-xs uppercase tracking-wide">
                                    <th class="py-2.5 px-3.5">No</th>
                                    <th class="py-2.5 px-3.5">Pemain</th>
                                    <th class="py-2.5 px-3.5">Posisi</th>
                                    <th class="py-2.5 px-3.5">Tanggal Lahir</th>
                                    <th class="py-2.5 px-3.5">NIK (16 Digit)</th>
                                    <th class="py-2.5 px-3.5">Kelengkapan Berkas</th>
                                    <th class="py-2.5 px-3.5">Status Screening</th>
                                    <th class="py-2.5 px-3.5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 text-xs">
                                <tr
                                    v-for="p in players"
                                    :key="p.id"
                                    class="bg-white hover:bg-slate-50/90 transition-colors"
                                >
                                    <td class="py-2.5 px-3.5 font-black tabular-data text-orange-600 text-sm">
                                        #{{ p.jersey_number }}
                                    </td>
                                    <td class="py-2.5 px-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <img
                                                v-if="p.photo_url"
                                                :src="p.photo_url"
                                                :alt="p.full_name"
                                                class="w-8 h-8 rounded-lg object-cover border border-slate-200 bg-slate-100 shrink-0"
                                            />
                                            <div class="w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-[10px] shrink-0" v-else>
                                                FOTO
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-900 text-sm leading-tight">{{ p.full_name }}</p>
                                                <p class="text-[11px] text-slate-500 leading-tight">{{ p.jersey_name || '-' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3.5 font-semibold text-slate-800">
                                        {{ p.position }} ({{ p.dominant_foot }})
                                    </td>
                                    <td class="py-2.5 px-3.5 tabular-data text-slate-700">
                                        {{ formatDate(p.birth_date) }}
                                    </td>
                                    <td class="py-2.5 px-3.5 font-mono tabular-data text-slate-700">
                                        {{ p.nik }}
                                    </td>
                                    <td class="py-2.5 px-3.5">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="doc in team.event?.required_documents"
                                                    :key="doc.type"
                                                    class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold border"
                                                    :class="p.documents[doc.type] ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-rose-50 text-rose-800 border-rose-200'"
                                                >
                                                    {{ doc.label.split(' ')[0] }}: {{ p.documents[doc.type] ? 'ADA' : 'BELUM' }}
                                                </span>
                                            </div>
                                            <button
                                                v-if="!p.is_locked"
                                                type="button"
                                                class="text-[11px] font-semibold text-orange-600 hover:text-orange-700 hover:underline inline-block mt-0.5"
                                                @click="openDocModal(p)"
                                            >
                                                + Unggah / Ganti Berkas
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3.5">
                                        <div class="space-y-1">
                                            <StatusBadge :status="p.screening_status" />
                                            <p v-if="p.rejection_reason" class="text-[11px] text-rose-700 leading-tight">
                                                <strong>Catatan:</strong> {{ p.rejection_reason }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3.5 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <!-- Tombol Ajukan Screening -->
                                            <button
                                                v-if="['draft', 'rejected'].includes(p.screening_status) && p.is_complete_for_submission"
                                                type="button"
                                                class="btn-pitch text-[11px] py-1 px-2.5"
                                                :disabled="submitPlayerForm.processing"
                                                @click="submitForScreening(p)"
                                            >
                                                Ajukan
                                            </button>

                                            <!-- Tombol Edit -->
                                            <button
                                                v-if="!p.is_locked"
                                                type="button"
                                                class="btn-secondary text-[11px] py-1 px-2"
                                                @click="openEditModal(p)"
                                            >
                                                Edit
                                            </button>

                                            <!-- Tombol Hapus Draft -->
                                            <button
                                                v-if="p.screening_status === 'draft' && !p.is_locked"
                                                type="button"
                                                class="px-2 py-1 rounded text-[11px] font-semibold text-rose-700 hover:bg-rose-50 border border-rose-200"
                                                @click="confirmDelete(p)"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Tambah / Edit Pemain -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm overflow-y-auto"
        >
            <div class="bg-white/95 backdrop-blur-xl border border-slate-200 rounded-xl shadow-xl max-w-2xl w-full p-6 my-8">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ editingPlayer ? `Edit Data Pemain: ${editingPlayer.full_name}` : 'Tambah Pemain Baru' }}
                    </h3>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-900" @click="isModalOpen = false">
                        Tutup
                    </button>
                </div>

                <div class="mt-4">
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
                                    placeholder="Contoh: Muhammad Rizky Pratama"
                                    class="input-field"
                                    :class="{ 'input-field-error': playerForm.errors.full_name }"
                                    @input="playerForm.clearErrors('full_name')"
                                />
                                <InputError :message="playerForm.errors.full_name" />
                            </div>

                            <div data-field="jersey_name">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Punggung (Jersey)</label>
                                <input
                                    v-model="playerForm.jersey_name"
                                    type="text"
                                    placeholder="Contoh: RIZKY"
                                    class="input-field uppercase"
                                />
                            </div>

                            <div data-field="jersey_number">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Punggung (1–99)</label>
                                <input
                                    v-model="playerForm.jersey_number"
                                    type="number"
                                    min="1"
                                    max="99"
                                    placeholder="10"
                                    class="input-field tabular-data"
                                    :class="{ 'input-field-error': playerForm.errors.jersey_number }"
                                    @input="playerForm.clearErrors('jersey_number')"
                                />
                                <InputError :message="playerForm.errors.jersey_number" />
                            </div>

                            <div data-field="position">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Posisi Bermain</label>
                                <select
                                    v-model="playerForm.position"
                                    class="input-field"
                                    :class="{ 'input-field-error': playerForm.errors.position }"
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
                                <select v-model="playerForm.dominant_foot" class="input-field">
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
                                    class="input-field tabular-data"
                                    :class="{ 'input-field-error': playerForm.errors.birth_date }"
                                    @input="playerForm.clearErrors('birth_date')"
                                />
                                <InputError :message="playerForm.errors.birth_date" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tempat Lahir</label>
                                <input
                                    v-model="playerForm.birth_place"
                                    type="text"
                                    placeholder="Kota Kelahiran"
                                    class="input-field"
                                />
                            </div>

                            <div data-field="nik">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Induk Kependudukan (NIK 16 Digit)</label>
                                <input
                                    v-model="playerForm.nik"
                                    type="text"
                                    maxlength="16"
                                    placeholder="3201xxxxxxxxxxxx"
                                    class="input-field tabular-data"
                                    :class="{ 'input-field-error': playerForm.errors.nik }"
                                    @input="playerForm.clearErrors('nik')"
                                />
                                <InputError :message="playerForm.errors.nik" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">NISN (Opsional)</label>
                                <input
                                    v-model="playerForm.nisn"
                                    type="text"
                                    placeholder="Nomor Induk Siswa Nasional"
                                    class="input-field tabular-data"
                                />
                            </div>

                            <div data-field="photo" class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Pas Foto Resmi Pemain (JPG/PNG, maks. 2 MB)
                                </label>
                                <div class="flex items-center gap-4">
                                    <img
                                        v-if="photoPreview"
                                        :src="photoPreview"
                                        alt="Preview"
                                        class="w-12 h-12 rounded-lg object-cover border border-slate-200 bg-slate-100 shrink-0"
                                    />
                                    <input
                                        type="file"
                                        accept=".jpg,.jpeg,.png"
                                        class="input-field py-1.5 text-xs"
                                        :class="{ 'input-field-error': playerForm.errors.photo }"
                                        @change="handlePhotoChange"
                                    />
                                </div>
                                <InputError :message="playerForm.errors.photo" />
                            </div>
                        </div>

                        <div class="mt-6 pt-3 border-t border-slate-200 flex items-center justify-end gap-2.5">
                            <button type="button" class="btn-secondary" @click="isModalOpen = false">
                                Batal
                            </button>
                            <button type="submit" class="btn-pitch" :disabled="playerForm.processing">
                                {{ playerForm.processing ? 'Menyimpan...' : (editingPlayer ? 'Perbarui Data Pemain' : 'Simpan Pemain') }}
                            </button>
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
                        <h3 class="text-sm font-bold text-slate-900">Unggah Berkas Screening</h3>
                        <p class="text-xs text-slate-500">Pemain: {{ activeDocPlayer.full_name }} (#{{ activeDocPlayer.jersey_number }})</p>
                    </div>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-900" @click="isDocModalOpen = false">
                        Tutup
                    </button>
                </div>

                <div class="mt-4">
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
                                placeholder="Contoh: No Akta / No Kartu Keluarga"
                                class="input-field"
                            />
                        </div>

                        <div data-field="file">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Pilih Berkas Scan / Foto (JPG, PNG, atau PDF &mdash; Maks. 2 MB)
                            </label>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="input-field py-1.5 text-xs"
                                :class="{ 'input-field-error': docForm.errors.file }"
                                @change="handleDocFile"
                            />
                            <InputError :message="docForm.errors.file" />
                        </div>

                        <div class="pt-3 flex justify-end gap-2.5">
                            <button type="button" class="btn-secondary" @click="isDocModalOpen = false">
                                Batal
                            </button>
                            <button type="submit" class="btn-pitch" :disabled="docForm.processing">
                                {{ docForm.processing ? 'Mengunggah Berkas...' : 'Simpan Berkas' }}
                            </button>
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
    </ManagerLayout>
</template>

