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
    roles: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const { applyValidation, isEmpty, isEmail } = useFormValidator();

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

// Modal State
const isModalOpen = ref(false);
const editingUser = ref(null);

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
    form.role = props.roles[0]?.name || 'finance';
    form.is_active = true;
    isModalOpen.value = true;
};

const openEditModal = (u) => {
    editingUser.value = u;
    form.reset();
    form.clearErrors();
    form.name = u.name;
    form.email = u.email;
    form.phone = u.phone || '';
    form.role = u.role;
    form.password = '';
    form.password_confirmation = '';
    form.is_active = Boolean(u.is_active);
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingUser.value = null;
    form.reset();
    form.clearErrors();
};

const submitForm = () => {
    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.name)) errs.name = 'Nama lengkap wajib diisi.';
        if (isEmpty(form.email)) errs.email = 'Alamat email wajib diisi.';
        else if (!isEmail(form.email)) errs.email = 'Format email tidak valid.';

        if (!editingUser.value && isEmpty(form.password)) {
            errs.password = 'Kata sandi wajib diisi untuk pengguna baru.';
        }
        if (form.password && form.password.length < 8) {
            errs.password = 'Kata sandi minimal 8 karakter.';
        }
        if (form.password && form.password !== form.password_confirmation) {
            errs.password_confirmation = 'Konfirmasi kata sandi tidak cocok.';
        }
        return errs;
    });

    if (!ok) return;

    if (editingUser.value) {
        form.put(route('admin.users.update', { user: editingUser.value.id }), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('admin.users.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

// Delete Confirmation
const isDeleteModalOpen = ref(false);
const userToDelete = ref(null);

const confirmDelete = (u) => {
    userToDelete.value = u;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (!userToDelete.value) return;
    router.delete(route('admin.users.destroy', { user: userToDelete.value.id }), {
        onSuccess: () => {
            isDeleteModalOpen.value = false;
            userToDelete.value = null;
        },
    });
};
</script>

<template>
    <AdminLayout page-title="Manajemen Pengguna &amp; Panitia" subtitle="Kelola hak akses pengguna internal, penetapan role panitia, dan kredensial login.">
        <Head title="Pengguna Panitia" />

        <div class="space-y-5">
            <!-- Filter Bar & Create Button -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex flex-1 flex-wrap items-center gap-2.5">
                    <input
                        v-model="filterState.search"
                        type="text"
                        placeholder="Cari nama, email, atau telepon..."
                        class="input-field max-w-xs text-xs"
                        @keyup.enter="applyFilters"
                    />
                    <select v-model="filterState.role" class="input-field w-auto text-xs" @change="applyFilters">
                        <option value="">Semua Peran</option>
                        <option value="superadmin">Superadmin</option>
                        <option value="finance">Finance</option>
                        <option value="screening_officer">Screening Officer</option>
                    </select>
                    <button type="button" class="btn-pitch" @click="applyFilters">Cari</button>
                </div>

                <button type="button" class="btn-pitch shrink-0" @click="openCreateModal">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    <span>Tambah Pengguna</span>
                </button>
            </div>

            <!-- Users Container (Responsive Card List View) -->
            <div class="space-y-3.5">
                <div v-if="users.data.length === 0" class="frosted-card p-12 text-center text-xs text-slate-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <p class="font-bold text-slate-800">Tidak ada pengguna ditemukan</p>
                    <p class="mt-0.5 text-slate-400">Silakan sesuaikan kriteria pencarian di atas.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="u in users.data"
                        :key="u.id"
                        class="frosted-card p-4 sm:p-5 hover:border-orange-300/80 hover:shadow-md transition-all duration-150 bg-white"
                    >
                        <!-- Top Header: Avatar, Name, Role & Status -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-3 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-100 to-amber-100 text-orange-800 flex items-center justify-center font-extrabold text-sm uppercase shadow-2xs shrink-0 border border-orange-200">
                                    {{ u.name.charAt(0) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm sm:text-base font-bold text-slate-900 leading-tight">{{ u.name }}</h4>
                                        <span class="text-[11px] text-slate-400 font-mono">#{{ u.id }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border tracking-wider"
                                            :class="
                                                u.role === 'superadmin'
                                                    ? 'bg-rose-50 text-rose-800 border-rose-300'
                                                    : u.role === 'finance'
                                                    ? 'bg-amber-50 text-amber-800 border-amber-300'
                                                    : u.role === 'screening_officer'
                                                    ? 'bg-orange-50 text-orange-800 border-orange-300'
                                                    : 'bg-slate-100 text-slate-800 border-slate-300'
                                            "
                                        >
                                            {{ u.role }}
                                        </span>
                                        <span
                                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                            :class="u.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'"
                                        >
                                            {{ u.is_active ? 'AKTIF' : 'NONAKTIF' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1.5 ml-auto sm:ml-0">
                                <button
                                    type="button"
                                    class="btn-sm-secondary"
                                    @click="openEditModal(u)"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="btn-sm-danger"
                                    @click="confirmDelete(u)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>

                        <!-- Card Body Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-xs items-center">
                            <!-- Email (Col 1-5) -->
                            <div class="lg:col-span-5 space-y-0.5">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Alamat Email</span>
                                <a :href="`mailto:${u.email}`" class="text-xs font-semibold text-slate-800 hover:text-orange-600 transition-colors truncate block">
                                    {{ u.email }}
                                </a>
                            </div>

                            <!-- Phone (Col 6-8) -->
                            <div class="lg:col-span-4 space-y-0.5">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Nomor Telepon / WhatsApp</span>
                                <span class="text-xs font-mono tabular-data font-semibold text-slate-800 block">
                                    {{ u.phone || '-' }}
                                </span>
                            </div>

                            <!-- Registered Date (Col 9-12) -->
                            <div class="lg:col-span-3 space-y-0.5 sm:text-right">
                                <span class="text-[10px] uppercase font-bold text-slate-400 block">Tanggal Registrasi</span>
                                <span class="text-xs text-slate-500 tabular-data font-medium block">
                                    {{ u.created_at }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit User Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="frosted-card bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
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
                        <input v-model="form.name" type="text" placeholder="Nama lengkap panitia" class="input-field" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Alamat Email</label>
                            <input v-model="form.email" type="email" placeholder="nama@organizer.id" class="input-field" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Nomor Telepon / WA</label>
                            <input v-model="form.phone" type="text" placeholder="0812xxxxxxxx" class="input-field tabular-data" />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Peran Akses (Role Spatie)</label>
                        <select v-model="form.role" class="input-field">
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.label }}</option>
                        </select>
                        <InputError :message="form.errors.role" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">
                                {{ editingUser ? 'Kata Sandi Baru (Kosongkan jika tetap)' : 'Kata Sandi Awal' }}
                            </label>
                            <input v-model="form.password" type="password" placeholder="Minimal 8 karakter" class="input-field" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi</label>
                            <input v-model="form.password_confirmation" type="password" placeholder="Ulangi kata sandi" class="input-field" />
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
                        <button type="button" class="btn-secondary" @click="closeModal">Batal</button>
                        <button type="submit" class="btn-pitch" :disabled="form.processing">
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
