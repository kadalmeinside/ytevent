<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
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
});

const { applyValidation, isEmpty, isValidIndonesianPhone, checkFile } = useFormValidator();
const logoPreview = ref(props.team?.logo_url || null);

const form = useForm({
    origin_association: props.team?.origin_association || '',
    secretariat_address: props.team?.secretariat_address || '',
    manager_name: props.team?.manager_name || '',
    manager_whatsapp: props.team?.manager_whatsapp || '',
    head_coach_name: props.team?.head_coach_name || '',
    head_coach_license: props.team?.head_coach_license || '',
    logo: null,
});

const onLogoChange = async (e) => {
    const rawFile = e.target.files?.[0] || null;
    if (!rawFile) {
        form.logo = null;
        logoPreview.value = props.team?.logo_url || null;
        return;
    }
    const { file } = await compressImage(rawFile, { maxDimension: 1200, quality: 0.85 });
    form.logo = file;
    form.clearErrors('logo');
    logoPreview.value = URL.createObjectURL(file);
};

const submit = () => {
    if (!props.team) return;

    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.origin_association)) errs.origin_association = 'Kota atau Asosiasi Asal (Askot/Askab) wajib diisi.';
        if (isEmpty(form.manager_name)) errs.manager_name = 'Nama lengkap manajer wajib diisi.';
        if (isEmpty(form.manager_whatsapp)) {
            errs.manager_whatsapp = 'Nomor telepon/WhatsApp aktif wajib diisi.';
        } else if (!isValidIndonesianPhone(form.manager_whatsapp)) {
            errs.manager_whatsapp = 'Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890).';
        }
        if (isEmpty(form.head_coach_name)) errs.head_coach_name = 'Nama pelatih kepala wajib diisi.';
        if (form.logo) {
            const c = checkFile(form.logo, ['image/png', 'image/jpeg'], 2 * 1024 * 1024);
            if (!c.valid) {
                errs.logo = c.reason === 'size'
                    ? 'Ukuran berkas logo tidak boleh melebihi 2 MB.'
                    : 'Format berkas logo harus berupa PNG atau JPG.';
            }
        }
        return errs;
    });

    if (!ok) return;

    form.post(route('manager.team-profile.update', { team: props.team.id }), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <ManagerLayout :team="team" :teams-list="teamsList" page-title="Profil Tim & Kontak Penanggung Jawab">
        <Head title="Profil Tim" />

        <div v-if="team" class="max-w-3xl">
            <div class="frosted-card p-6 sm:p-8">
                <div class="mb-5 pb-4 border-b border-slate-200">
                    <h2 class="text-base font-bold text-slate-900">{{ team.name }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Nama tim dikunci pada kompetisi <strong>{{ team.event?.name }}</strong> untuk menjaga integritas bagan dan kuota.
                    </p>
                </div>

                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-4">
                    <div data-field="logo">
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Logo Resmi Tim</label>
                        <div class="flex items-center gap-4">
                            <img
                                v-if="logoPreview"
                                :src="logoPreview"
                                :alt="team.name"
                                class="w-14 h-14 rounded-xl object-cover border border-slate-200 bg-white shrink-0"
                            />
                            <input
                                type="file"
                                accept=".png,.jpg,.jpeg"
                                class="input-field py-1.5 text-xs"
                                :class="{ 'input-field-error': form.errors.logo }"
                                @change="onLogoChange"
                            />
                        </div>
                        <InputError :message="form.errors.logo" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div data-field="origin_association">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Kota / Askot / Askab PSSI</label>
                            <input
                                v-model="form.origin_association"
                                type="text"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.origin_association }"
                                @input="form.clearErrors('origin_association')"
                            />
                            <InputError :message="form.errors.origin_association" />
                        </div>

                        <div data-field="manager_name">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Manajer Tim</label>
                            <input
                                v-model="form.manager_name"
                                type="text"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.manager_name }"
                                @input="form.clearErrors('manager_name')"
                            />
                            <InputError :message="form.errors.manager_name" />
                        </div>

                        <div data-field="manager_whatsapp">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">WhatsApp Aktif Manajer</label>
                            <input
                                v-model="form.manager_whatsapp"
                                type="text"
                                class="input-field tabular-data"
                                :class="{ 'input-field-error': form.errors.manager_whatsapp }"
                                @input="form.clearErrors('manager_whatsapp')"
                            />
                            <InputError :message="form.errors.manager_whatsapp" />
                        </div>

                        <div data-field="head_coach_name">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pelatih Kepala</label>
                            <input
                                v-model="form.head_coach_name"
                                type="text"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.head_coach_name }"
                                @input="form.clearErrors('head_coach_name')"
                            />
                            <InputError :message="form.errors.head_coach_name" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor / Jenis Lisensi Pelatih</label>
                            <input
                                v-model="form.head_coach_license"
                                type="text"
                                placeholder="Contoh: AFC C / PSSI D"
                                class="input-field"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Sekretariat</label>
                            <input
                                v-model="form.secretariat_address"
                                type="text"
                                class="input-field"
                            />
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end">
                        <button type="submit" class="btn-pitch" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan Profil' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </ManagerLayout>
</template>

