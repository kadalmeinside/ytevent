<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useFormValidator } from '@/Composables/useFormValidator';

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

const handlePhoto = (e) => {
    const file = e.target.files?.[0] || null;
    form.photo = file;
    form.clearErrors('photo');
    if (file) {
        photoPreview.value = URL.createObjectURL(file);
    }
};

const handleLicense = (e) => {
    form.license_file = e.target.files?.[0] || null;
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
                        class="btn-pitch text-xs"
                        :disabled="officials.length >= team.event?.max_officials_per_team || !team.can_manage_roster"
                        @click="openCreateModal"
                    >
                        + Tambah Ofisial
                    </button>
                </div>
            </div>

            <!-- Officials Table -->
            <div class="frosted-card overflow-hidden">
                <div v-if="officials.length === 0" class="p-8 text-center text-xs text-slate-500">
                    Belum ada ofisial terdaftar.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 text-xs uppercase">
                                <th class="py-2.5 px-3.5">Ofisial</th>
                                <th class="py-2.5 px-3.5">Jabatan</th>
                                <th class="py-2.5 px-3.5">No. Lisensi</th>
                                <th class="py-2.5 px-3.5">Kontak WhatsApp</th>
                                <th class="py-2.5 px-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70 text-xs">
                            <tr v-for="item in officials" :key="item.id" class="bg-white hover:bg-slate-50/90">
                                <td class="py-2.5 px-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <img
                                            v-if="item.photo_url"
                                            :src="item.photo_url"
                                            :alt="item.full_name"
                                            class="w-8 h-8 rounded-lg object-cover border border-slate-200 bg-slate-100"
                                        />
                                        <div v-else class="w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-[10px]">
                                            FOTO
                                        </div>
                                        <span class="font-bold text-slate-900 text-sm">{{ item.full_name }}</span>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3.5 font-semibold text-slate-800">{{ item.role_label }}</td>
                                <td class="py-2.5 px-3.5">
                                    <span v-if="item.license_number" class="tabular-data font-mono">{{ item.license_number }}</span>
                                    <a
                                        v-if="item.license_url"
                                        :href="item.license_url"
                                        target="_blank"
                                        class="ml-2 text-emerald-700 underline text-[11px]"
                                    >
                                        (Lihat Berkas)
                                    </a>
                                    <span v-if="!item.license_number && !item.license_url" class="text-slate-400">-</span>
                                </td>
                                <td class="py-2.5 px-3.5 tabular-data text-slate-700">{{ item.phone || '-' }}</td>
                                <td class="py-2.5 px-3.5 text-right">
                                    <div class="inline-flex items-center gap-1.5" v-if="team.can_manage_roster">
                                        <button
                                            type="button"
                                            class="btn-secondary text-[11px] py-1 px-2"
                                            @click="openEditModal(item)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            v-if="item.role !== 'Head Coach'"
                                            type="button"
                                            class="px-2 py-1 rounded text-[11px] font-semibold text-rose-700 hover:bg-rose-50 border border-rose-200"
                                            @click="officialToDelete = item"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                    <span v-else class="text-[11px] text-slate-400">Terkunci</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Pas Foto (JPG/PNG, maks. 2 MB)</label>
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="photoPreview"
                                    :src="photoPreview"
                                    alt="Foto"
                                    class="w-10 h-10 rounded-lg object-cover border border-slate-200 bg-slate-100 shrink-0"
                                />
                                <input
                                    type="file"
                                    accept=".jpg,.jpeg,.png"
                                    class="input-field py-1.5 text-xs"
                                    @change="handlePhoto"
                                />
                            </div>
                            <InputError :message="form.errors.photo" />
                        </div>

                        <div data-field="license_file">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Sertifikat Lisensi (JPG/PNG/PDF, maks. 2 MB)</label>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="input-field py-1.5 text-xs"
                                @change="handleLicense"
                            />
                            <InputError :message="form.errors.license_file" />
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
