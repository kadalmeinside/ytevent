<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import FileInput from '@/Components/FileInput.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
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
    officials: {
        type: Array,
        default: () => [],
    },
    officialRoles: {
        type: Object,
        default: () => ({}),
    },
});

const { applyValidation, isEmpty, isValidIndonesianPhone, checkFile } = useFormValidator();

const isModalOpen = ref(false);
const editingOfficial = ref(null);
const photoPreview = ref(null);
const officialToDelete = ref(null);

const form = useForm({
    full_name: '',
    role: 'Assistant Coach',
    license_number: '',
    phone: '',
    photo: null,
    license_file: null,
});

const openCreateModal = () => {
    editingOfficial.value = null;
    photoPreview.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'Assistant Coach';
    isModalOpen.value = true;
};

const openEditModal = (item) => {
    editingOfficial.value = item;
    photoPreview.value = item.photo_url;
    form.reset();
    form.clearErrors();
    form.full_name = item.full_name;
    form.role = item.role;
    form.license_number = item.license_number || '';
    form.phone = item.phone || '';
    form.photo = null;
    form.license_file = null;
    isModalOpen.value = true;
};

const handlePhoto = async (rawFile) => {
    if (!rawFile) {
        form.photo = null;
        photoPreview.value = editingOfficial.value?.photo_url || null;
        return;
    }
    const { file } = await compressImage(rawFile, { maxDimension: 1200, quality: 0.85 });
    form.photo = file;
    form.clearErrors('photo');
    photoPreview.value = URL.createObjectURL(file);
};

const handleLicense = (rawFile) => {
    form.license_file = rawFile || null;
    form.clearErrors('license_file');
};

const submitOfficial = () => {
    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.full_name)) errs.full_name = 'Nama lengkap pelatih atau ofisial wajib diisi.';
        if (isEmpty(form.role)) errs.role = 'Jabatan ofisial wajib dipilih.';
        if (!isEmpty(form.phone) && !isValidIndonesianPhone(form.phone)) {
            errs.phone = 'Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890).';
        }
        if (form.photo) {
            const pCheck = checkFile(form.photo, ['image/jpeg', 'image/png'], 2 * 1024 * 1024);
            if (!pCheck.valid) errs.photo = 'Pas foto ofisial harus berformat JPG atau PNG (maks 2 MB).';
        }
        if (form.license_file) {
            const lCheck = checkFile(form.license_file, ['image/jpeg', 'image/png', 'application/pdf'], 2 * 1024 * 1024);
            if (!lCheck.valid) errs.license_file = 'Berkas lisensi harus berformat JPG, PNG, atau PDF (maks 2 MB).';
        }
        return errs;
    });

    if (!ok) return;

    if (editingOfficial.value) {
        form.post(route('manager.officials.update', { team: props.team.id, official: editingOfficial.value.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    } else {
        form.post(route('manager.officials.store', { team: props.team.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                isModalOpen.value = false;
            },
        });
    }
};

const deleteForm = useForm({});
const executeDelete = () => {
    if (!officialToDelete.value) return;
    deleteForm.delete(route('manager.officials.destroy', { team: props.team.id, official: officialToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            officialToDelete.value = null;
        },
    });
};
</script>

<template>
    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Data Pelatih & Ofisial Tim">
        <Head title="Pelatih & Ofisial Tim" />

        <div v-if="team" class="space-y-6">
            <div class="frosted-card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Staf Kepelatihan &amp; Ofisial Bangku Cadangan</h2>
                    <p class="text-xs text-slate-600 mt-0.5">
                        Maksimal kuota ofisial pada kompetisi ini: <strong class="tabular-data">{{ team.event?.max_officials_per_team }} orang</strong>.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold tabular-data text-slate-700">
                        {{ officials.length }} / {{ team.event?.max_officials_per_team }} Ofisial
                    </span>
                    <button
                        type="button"
                        class="btn-pitch"
                        :disabled="officials.length >= team.event?.max_officials_per_team || !team.can_manage_roster"
                        @click="openCreateModal"
                    >
                        + Tambah Ofisial
                    </button>
                </div>
            </div>

            <!-- Officials Container (Responsive Card List View) -->
            <div class="space-y-3.5">
                <div v-if="officials.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="font-bold text-slate-700">Belum ada ofisial terdaftar</p>
                    <p class="mt-0.5 text-slate-400">Klik tombol "Tambah Ofisial Baru" di atas untuk mendaftarkan staf pelatih atau ofisial tim.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="item in officials"
                        :key="item.id"
                        class="frosted-card p-4 sm:p-5 hover:border-orange-300/80 hover:shadow-md transition-all duration-150 bg-white"
                    >
                        <!-- Top Header: Photo, Name, Role & Action Buttons -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="item.photo_url"
                                    :src="item.photo_url"
                                    :alt="item.full_name"
                                    class="w-11 h-11 rounded-xl object-cover border border-slate-200 bg-white shadow-2xs shrink-0"
                                />
                                <div v-else class="w-11 h-11 rounded-xl bg-gradient-to-br from-orange-100 to-amber-100 text-orange-700 font-extrabold flex items-center justify-center text-sm shadow-2xs shrink-0 border border-orange-200">
                                    {{ item.full_name.charAt(0) }}
                                </div>
                                <div>
                                    <h4 class="text-sm sm:text-base font-bold text-slate-900 leading-tight">{{ item.full_name }}</h4>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-orange-50 text-orange-800 border border-orange-200">
                                            {{ item.role_label }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 ml-auto sm:ml-0" v-if="team.can_manage_roster">
                                <button
                                    type="button"
                                    class="btn-sm-secondary"
                                    @click="openEditModal(item)"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="item.role !== 'Head Coach'"
                                    type="button"
                                    class="btn-sm-danger"
                                    @click="officialToDelete = item"
                                >
                                    Hapus
                                </button>
                            </div>
                            <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">
                                Terkunci
                            </span>
                        </div>

                        <!-- Card Body Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-xs items-center">
                            <!-- Lisensi (Col 1-6) -->
                            <div class="lg:col-span-6 space-y-0.5">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Nomor &amp; Berkas Lisensi</span>
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-semibold text-slate-800">
                                        {{ item.license_number || 'Tidak ada nomor lisensi' }}
                                    </span>
                                    <a
                                        v-if="item.license_url"
                                        :href="item.license_url"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-orange-600 hover:text-orange-700 underline"
                                    >
                                        [Lihat Berkas Lisensi &nearr;]
                                    </a>
                                </div>
                            </div>

                            <!-- Phone (Col 7-12) -->
                            <div class="lg:col-span-6 space-y-0.5 sm:text-right">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Kontak Telepon / WhatsApp</span>
                                <span class="text-xs font-mono tabular-data font-semibold text-slate-800">
                                    {{ item.phone || '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Form Ofisial -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm"
        >
            <div class="bg-white/95 backdrop-blur-xl border border-slate-200 rounded-xl shadow-xl max-w-lg w-full p-6">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h3 class="text-sm font-bold text-slate-900">
                        {{ editingOfficial ? 'Edit Ofisial Tim' : 'Tambah Ofisial Tim' }}
                    </h3>
                    <button type="button" class="text-xs text-slate-500 hover:text-slate-900" @click="isModalOpen = false">
                        Tutup
                    </button>
                </div>

                <div class="mt-4">
                    <FormErrorBanner :errors="form.errors" />

                    <form @submit.prevent="submitOfficial" novalidate class="space-y-4">
                        <div data-field="full_name">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                            <input
                                v-model="form.full_name"
                                type="text"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.full_name }"
                                @input="form.clearErrors('full_name')"
                            />
                            <InputError :message="form.errors.full_name" />
                        </div>

                        <div data-field="role">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Jabatan Ofisial</label>
                            <select v-model="form.role" class="input-field">
                                <option v-for="(label, code) in officialRoles" :key="code" :value="code">
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor Lisensi (Jika Ada)</label>
                            <input v-model="form.license_number" type="text" class="input-field" />
                        </div>

                        <div data-field="phone">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor WhatsApp</label>
                            <input v-model="form.phone" type="text" placeholder="0812xxxxxxxx" class="input-field tabular-data" />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div data-field="photo">
                            <label class="block text-xs font-bold text-slate-800 mb-1.5">Pas Foto Ofisial (Maks. 2 MB)</label>
                            <FileInput
                                v-model="form.photo"
                                accept=".jpg,.jpeg,.png"
                                :preview-url="photoPreview"
                                label="Pilih Foto Ofisial"
                                hint="Pas foto wajah terbaru background polos"
                                :error="form.errors.photo"
                                @change="handlePhoto"
                            />
                        </div>

                        <div data-field="license_file">
                            <label class="block text-xs font-bold text-slate-800 mb-1.5">Sertifikat Lisensi Kepelatihan (Maks. 2 MB)</label>
                            <FileInput
                                v-model="form.license_file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                label="Pilih Berkas Lisensi"
                                hint="Format: JPG, PNG, atau PDF (opsional untuk ofisial non-pelatih)"
                                :error="form.errors.license_file"
                                @change="handleLicense"
                            />
                        </div>

                        <div class="pt-3 flex justify-end gap-2.5">
                            <button type="button" class="btn-secondary" @click="isModalOpen = false">Batal</button>
                            <button type="submit" class="btn-pitch" :disabled="form.processing">
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Ofisial' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="Boolean(officialToDelete)"
            title="Hapus Data Ofisial"
            :description="`Apakah Anda yakin ingin menghapus data ofisial ${officialToDelete?.full_name}?`"
            confirm-text="Ya, Hapus"
            :danger="true"
            :processing="deleteForm.processing"
            @confirm="executeDelete"
            @close="officialToDelete = null"
        />
    </ManagerLayout>
</template>

