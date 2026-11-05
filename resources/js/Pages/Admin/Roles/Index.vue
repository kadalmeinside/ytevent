<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
    allPermissions: {
        type: Array,
        required: true,
    },
});

const selectedRole = ref(props.roles[0] || null);

const form = useForm({
    permissions: selectedRole.value ? [...selectedRole.value.permissions] : [],
});

const selectRole = (role) => {
    selectedRole.value = role;
    form.clearErrors();
    form.permissions = [...role.permissions];
};

const togglePermission = (permName) => {
    if (selectedRole.value?.name === 'superadmin') return;
    const idx = form.permissions.indexOf(permName);
    if (idx > -1) {
        form.permissions.splice(idx, 1);
    } else {
        form.permissions.push(permName);
    }
};

const savePermissions = () => {
    if (!selectedRole.value || selectedRole.value.name === 'superadmin') return;
    form.post(route('admin.roles.update', { role: selectedRole.value.id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AdminLayout page-title="Hak Akses &amp; Manajemen Peran (RBAC)" subtitle="Atur izin hak akses (permissions) Spatie untuk setiap peran kerja panitia secara terisolasi.">
        <Head title="Hak Akses & Role Spatie" />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Roles List Selector -->
            <div class="lg:col-span-4 frosted-card p-4 space-y-2">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Daftar Peran (Roles)</p>
                <button
                    v-for="r in roles"
                    :key="r.id"
                    type="button"
                    class="w-full text-left p-3.5 rounded-xl border text-xs transition-all flex items-center justify-between"
                    :class="
                        selectedRole?.id === r.id
                            ? 'bg-orange-50/80 border-orange-500/80 shadow-xs text-orange-950 font-bold'
                            : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-700'
                    "
                    @click="selectRole(r)"
                >
                    <div>
                        <div class="text-sm font-bold">{{ r.label }}</div>
                        <div class="text-[11px] font-mono text-slate-500 mt-0.5">slug: {{ r.name }}</div>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-bold border border-slate-200">
                        {{ r.name === 'superadmin' ? 'SEMUA IZIN' : `${r.permissions.length} Izin` }}
                    </span>
                </button>
            </div>

            <!-- Right: Permission Checkboxes Grouped -->
            <div v-if="selectedRole" class="lg:col-span-8 frosted-card p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Hak Akses: {{ selectedRole.label }}</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Tentukan modul yang dapat diakses dan dioperasikan oleh peran ini.</p>
                    </div>
                    <button
                        v-if="selectedRole.name !== 'superadmin'"
                        type="button"
                        class="btn-pitch"
                        :disabled="form.processing"
                        @click="savePermissions"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Hak Akses Peran' }}
                    </button>
                </div>

                <div v-if="selectedRole.name === 'superadmin'" class="p-4 rounded-xl bg-orange-50/70 border border-orange-200 text-xs text-orange-900 leading-relaxed">
                    <strong>Peran Super Administrator</strong> memiliki hak akses penuh tanpa batas ke seluruh modul sistem (Bypass RBAC Gate). Hak akses ini tidak dapat dikurangi demi integritas operasional.
                </div>

                <div v-else class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <label
                            v-for="p in allPermissions"
                            :key="p.id"
                            class="p-3 rounded-xl border transition-all flex items-start gap-2.5 cursor-pointer"
                            :class="
                                form.permissions.includes(p.name)
                                    ? 'bg-orange-50/50 border-orange-300 text-orange-950 font-semibold'
                                    : 'bg-white hover:bg-slate-50 border-slate-200 text-slate-700'
                            "
                        >
                            <input
                                type="checkbox"
                                :checked="form.permissions.includes(p.name)"
                                class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                @change="togglePermission(p.name)"
                            />
                            <div>
                                <span class="block">{{ p.label }}</span>
                                <span class="font-mono text-[10px] text-slate-400 block">{{ p.name }}</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

