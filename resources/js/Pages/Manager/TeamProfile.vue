<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
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
});

const { applyValidation, isEmpty, isValidIndonesianPhone, isValidPassword, checkFile } = useFormValidator();
const logoPreview = ref(props.team?.logo_url || null);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submitPasswordChange = () => {
    const ok = applyValidation(passwordForm, () => {
        const errs = {};
        if (isEmpty(passwordForm.current_password)) {
            errs.current_password = 'Kata sandi saat ini wajib diisi.';
        }
        if (isEmpty(passwordForm.password)) {
            errs.password = 'Kata sandi baru wajib diisi.';
        } else if (!isValidPassword(passwordForm.password)) {
            errs.password = 'Kata sandi baru minimal 8 karakter dan wajib mengandung huruf serta angka.';
        }
        if (isEmpty(passwordForm.password_confirmation)) {
            errs.password_confirmation = 'Konfirmasi kata sandi baru wajib diisi.';
        } else if (passwordForm.password_confirmation !== passwordForm.password) {
            errs.password_confirmation = 'Konfirmasi kata sandi baru tidak cocok.';
        }
        return errs;
    });

    if (!ok) return;

    passwordForm.put(route('manager.password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
    });
};

const form = useForm({
    origin_association: props.team?.origin_association || '',
    secretariat_address: props.team?.secretariat_address || '',
    manager_name: props.team?.manager_name || '',
    manager_whatsapp: props.team?.manager_whatsapp || '',
    head_coach_name: props.team?.head_coach_name || '',
    head_coach_license: props.team?.head_coach_license || '',
    logo: null,
});

const onLogoChange = async (rawFile) => {
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
        if (props.team?.event?.show_coach_license !== false && props.team?.event?.require_coach_license) {
            if (isEmpty(form.head_coach_license)) {
                errs.head_coach_license = 'Nomor atau jenis lisensi pelatih kepala wajib diisi sesuai regulasi kompetisi ini.';
            } else if (form.head_coach_license.trim().length < 2) {
                errs.head_coach_license = 'Nomor atau jenis lisensi pelatih kepala minimal 2 karakter.';
            }
        }
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
                        <label class="block text-xs font-bold text-slate-800 mb-1.5">Logo Resmi Tim</label>
                        <FileInput
                            v-model="form.logo"
                            accept=".png,.jpg,.jpeg"
                            :preview-url="logoPreview"
                            label="Pilih Logo Klub"
                            hint="Format: PNG, JPG (Maks 2 MB, rasio 1:1 persegi)"
                            :error="form.errors.logo"
                            @change="onLogoChange"
                        />
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

                        <div v-if="team.event?.show_coach_license !== false" data-field="head_coach_license">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Nomor / Jenis Lisensi Pelatih
                                <span v-if="team.event?.require_coach_license" class="text-rose-500 font-bold">* (Wajib)</span>
                                <span v-else class="text-slate-400 font-normal">(Opsional)</span>
                            </label>
                            <input
                                v-model="form.head_coach_license"
                                type="text"
                                placeholder="Contoh: AFC C / PSSI D"
                                class="input-field"
                                :class="{ 'input-field-error': form.errors.head_coach_license }"
                                @input="form.clearErrors('head_coach_license')"
                            />
                            <InputError :message="form.errors.head_coach_license" />
                        </div>

                        <div :class="team.event?.show_coach_license === false ? 'sm:col-span-2' : ''">
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

            <!-- Kartu Keamanan Akun & Ubah Kata Sandi Mandiri -->
            <div class="frosted-card p-6 sm:p-8 mt-6">
                <div class="mb-5 pb-4 border-b border-slate-200">
                    <h2 class="text-base font-bold text-slate-900">Keamanan Akun &amp; Ubah Kata Sandi</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Perbarui kata sandi akun portal manajer Anda secara berkala. Gunakan minimal 8 karakter kombinasi huruf dan angka.
                    </p>
                </div>

                <form @submit.prevent="submitPasswordChange" novalidate class="space-y-4">
                    <div data-field="current_password">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi Saat Ini</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Masukkan kata sandi saat ini"
                            class="input-field"
                            :class="{ 'input-field-error': passwordForm.errors.current_password }"
                            @input="passwordForm.clearErrors('current_password')"
                        />
                        <InputError :message="passwordForm.errors.current_password" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div data-field="password">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi Baru</label>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Minimal 8 karakter (huruf & angka)"
                                class="input-field"
                                :class="{ 'input-field-error': passwordForm.errors.password }"
                                @input="passwordForm.clearErrors('password')"
                            />
                            <InputError :message="passwordForm.errors.password" />
                        </div>

                        <div data-field="password_confirmation">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi Baru</label>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                placeholder="Ulangi kata sandi baru"
                                class="input-field"
                                :class="{ 'input-field-error': passwordForm.errors.password_confirmation }"
                                @input="passwordForm.clearErrors('password_confirmation')"
                            />
                            <InputError :message="passwordForm.errors.password_confirmation" />
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="btn-pitch" :disabled="passwordForm.processing">
                            {{ passwordForm.processing ? 'Memperbarui...' : 'Perbarui Kata Sandi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </ManagerLayout>
</template>

