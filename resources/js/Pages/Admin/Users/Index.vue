<script setup>
import { ref, reactive } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useFormValidator } from '@/Composables/useFormValidator';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    roles: {
        type: Array,
        default: () => [],
    },
});

const { applyValidation, isEmpty } = useFormValidator();

const isModalOpen = ref(false);
const editingUser = ref(null);
const userToDelete = ref(null);
const isDeleteModalOpen = ref(false);

const filterState = reactive({
    search: props.filters.search || '',
    role: props.filters.role || '',
});

const applyFilters = () => {
    router.get(
        route('admin.users.index'),
        {
            search: filterState.search || undefined,
            role: filterState.role || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const form = useForm({
    name: '',
    email: '',
    phone: '',
    role: 'finance',
    password: '',
    password_confirmation: '',
    is_active: true,
});

const openCreateModal = () => {
    editingUser.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'finance';
    form.is_active = true;
    isModalOpen.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.phone = user.phone || '';
    form.role = user.role || 'finance';
    form.password = '';
    form.password_confirmation = '';
    form.is_active = Boolean(user.is_active);
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingUser.value = null;
};

const submitForm = () => {
    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.name)) errs.name = 'Nama lengkap pengguna wajib diisi.';
        if (isEmpty(form.email)) errs.email = 'Alamat email wajib diisi.';
        if (!editingUser.value) {
            if (isEmpty(form.password)) errs.password = 'Kata sandi awal wajib diisi.';
            else if (form.password.length < 8) errs.password = 'Kata sandi minimal 8 karakter.';
            if (form.password !== form.password_confirmation) errs.password_confirmation = 'Konfirmasi kata sandi tidak cocok.';
        } else if (form.password) {
            if (form.password.length < 8) errs.password = 'Kata sandi baru minimal 8 karakter.';
            if (form.password !== form.password_confirmation) errs.password_confirmation = 'Konfirmasi kata sandi tidak cocok.';
        }
        return errs;
    });

    if (!ok) return;

    if (editingUser.value) {
        form.post(route('admin.users.update', { user: editingUser.value.id }), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('admin.users.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const confirmDelete = (user) => {
    userToDelete.value = user;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!userToDelete.value) return;
    router.delete(route('admin.users.destroy', { user: userToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            userToDelete.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout page-title="Manajemen Pengguna Panitia" subtitle="Kelola akun internal panitia, pembagian peran kerja, dan status akses login.">
        <Head title="Manajemen Pengguna" />

        <div class="space-y-6">
            <!-- Top Action & Search Bar -->
            <div class="frosted-card p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2.5 flex-1 max-w-xl">
                    <input
                        v-model="filterState.search"
                        type="text"
                        placeholder="Cari nama atau email pengguna..."
                        class="input-field py-2 text-xs flex-1"
                        @keyup.enter="applyFilters"
                    />
                    <select v-model="filterState.role" class="input-field py-2 text-xs w-auto" @change="applyFilters">
                        <option value="">Semua Peran</option>
                        <option value="superadmin">Superadmin</option>
                        <option value="finance">Finance</option>
                        <option value="screening_officer">Screening Officer</option>
                    </select>
                    <button type="button" class="btn-pitch py-2 px-3 text-xs" @click="applyFilters">Cari</button>
                </div>

                <button type="button" class="btn-pitch text-xs py-2 px-4 shrink-0" @click="openCreateModal">
                    + Tambah Pengguna Baru
                </button>
            </div>

            <!-- Users Table -->
            <div class="frosted-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-100/90 text-slate-700 border-b border-slate-200 uppercase tracking-wide">
                                <th class="py-3 px-4">Nama Pengguna</th>
                                <th class="py-3 px-4">Email &amp; Kontak</th>
                                <th class="py-3 px-4">Peran (Role)</th>
                                <th class="py-3 px-4">Status Akun</th>
                                <th class="py-3 px-4">Terdaftar</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/70">
                            <tr v-if="users.data.length === 0">
                                <td colspan="6" class="py-8 text-center text-slate-500">
                                    Tidak ada data pengguna ditemukan.
                                </td>
                            </tr>
                            <tr v-for="u in users.data" :key="u.id" class="bg-white hover:bg-slate-50/90 transition-colors">
                                <td class="py-3 px-4 font-bold text-slate-900 text-sm">
                                    {{ u.name }}
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    <div class="font-medium text-slate-900">{{ u.email }}</div>
                                    <div class="text-[11px] text-slate-400 tabular-data">{{ u.phone || '-' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="px-2.5 py-0.5 rounded-md text-[11px] font-bold uppercase border"
                                        :class="
                                            u.role === 'superadmin'
                                                ? 'bg-rose-50 text-rose-800 border-rose-300'
                                                : u.role === 'finance'
                                                ? 'bg-amber-50 text-amber-800 border-amber-300'
                                                : 'bg-orange-50 text-orange-800 border-orange-300'
                                        "
                                    >
                                        {{ u.role }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                        :class="u.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
                                    >
                                        {{ u.is_active ? 'AKTIF' : 'NONAKTIF' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 tabular-data text-slate-500 text-[11px]">
                                    {{ u.created_at }}
                                </td>
                                <td class="py-3 px-4 text-right space-x-2">
                                    <button
                                        type="button"
                                        class="font-semibold text-orange-600 hover:text-orange-700 hover:underline"
                                        @click="openEditModal(u)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="font-semibold text-rose-600 hover:text-rose-700 hover:underline"
                                        @click="confirmDelete(u)"
                                    >
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create / Edit User Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <h2 class="text-base font-bold text-slate-900">
                        {{ editingUser ? 'Edit Pengguna Panitia' : 'Tambah Pengguna Panitia Baru' }}
                    </h2>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeModal">&times;</button>
                </div>

                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submitForm" novalidate class="space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                        <input v-model="form.name" type="text" placeholder="Nama lengkap panitia" class="input-field text-xs" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Alamat Email</label>
                            <input v-model="form.email" type="email" placeholder="nama@organizer.id" class="input-field text-xs" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nomor Telepon / WA</label>
                            <input v-model="form.phone" type="text" placeholder="0812xxxxxxxx" class="input-field text-xs tabular-data" />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Peran Akses (Role Spatie)</label>
                        <select v-model="form.role" class="input-field text-xs">
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.label }}</option>
                        </select>
                        <InputError :message="form.errors.role" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                {{ editingUser ? 'Kata Sandi Baru (Kosongkan jika tetap)' : 'Kata Sandi Awal' }}
                            </label>
                            <input v-model="form.password" type="password" placeholder="Minimal 8 karakter" class="input-field text-xs" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi</label>
                            <input v-model="form.password_confirmation" type="password" placeholder="Ulangi kata sandi" class="input-field text-xs" />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500" />
                            <span>Akun Aktif (Dapat masuk ke Portal Panitia)</span>
                        </label>
                    </div>

                    <div class="pt-3 border-t border-slate-200 flex items-center justify-end gap-2">
                        <button type="button" class="btn-secondary text-xs" @click="closeModal">Batal</button>
                        <button type="submit" class="btn-pitch text-xs px-5" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : (editingUser ? 'Perbarui Pengguna' : 'Simpan Pengguna') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Confirm Delete Modal -->
        <ConfirmModal
            :show="isDeleteModalOpen"
            title="Konfirmasi Hapus Pengguna"
            :message="`Apakah Anda yakin ingin menghapus akun panitia ${userToDelete?.name} (${userToDelete?.email})? Tindakan ini tidak dapat dibatalkan.`"
            confirm-label="Ya, Hapus Pengguna"
            :danger="true"
            @close="isDeleteModalOpen = false"
            @confirm="executeDelete"
        />
    </AdminLayout>
</template>
