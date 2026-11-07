<script setup>
import { useForm, Link, Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import { useFormValidator } from '@/Composables/useFormValidator';

const props = defineProps({
    token: {
        type: String,
        required: true,
    },
    email: {
        type: String,
        default: '',
    },
});

const { applyValidation, isEmpty, isValidEmail, isValidPassword } = useFormValidator();

const form = useForm({
    token: props.token,
    email: props.email || '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.email)) {
            errs.email = 'Alamat email wajib diisi.';
        } else if (!isValidEmail(form.email)) {
            errs.email = 'Format alamat email tidak valid.';
        }
        if (isEmpty(form.password)) {
            errs.password = 'Kata sandi baru wajib diisi.';
        } else if (!isValidPassword(form.password)) {
            errs.password = 'Kata sandi baru minimal 8 karakter dan wajib mengandung huruf serta angka.';
        }
        if (isEmpty(form.password_confirmation)) {
            errs.password_confirmation = 'Konfirmasi kata sandi baru wajib diisi.';
        } else if (form.password_confirmation !== form.password) {
            errs.password_confirmation = 'Konfirmasi kata sandi baru tidak cocok.';
        }
        return errs;
    });

    if (!ok) return;

    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <PublicLayout>
        <Head title="Atur Ulang Kata Sandi">
            <meta head-key="robots" name="robots" content="noindex, nofollow" />
        </Head>

        <div class="max-w-md mx-auto px-4 py-12">
            <div class="frosted-card p-6 sm:p-8">
                <div class="mb-6">
                    <span class="inline-block text-xs font-semibold uppercase tracking-wider text-orange-800 bg-orange-50 border border-orange-200 px-2.5 py-0.5 rounded-md mb-2">
                        Atur Ulang Kata Sandi
                    </span>
                    <h1 class="text-xl font-bold text-slate-900">Buat Kata Sandi Baru</h1>
                    <p class="text-xs text-slate-600 mt-1">
                        Silakan masukkan kata sandi baru untuk akun Anda (minimal 8 karakter kombinasi huruf dan angka).
                    </p>
                </div>

                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-4">
                    <div data-field="email">
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">
                            Alamat Email
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="text"
                            inputmode="email"
                            autocomplete="email"
                            class="input-field"
                            :class="{ 'input-field-error': form.errors.email }"
                            @input="form.clearErrors('email')"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div data-field="password">
                        <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">
                            Kata Sandi Baru
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter (huruf & angka)"
                            class="input-field"
                            :class="{ 'input-field-error': form.errors.password }"
                            @input="form.clearErrors('password')"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div data-field="password_confirmation">
                        <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 mb-1">
                            Konfirmasi Kata Sandi Baru
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            placeholder="Ulangi kata sandi baru"
                            class="input-field"
                            :class="{ 'input-field-error': form.errors.password_confirmation }"
                            @input="form.clearErrors('password_confirmation')"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <button type="submit" class="btn-pitch w-full" :disabled="form.processing">
                        {{ form.processing ? 'Menyimpan Kata Sandi...' : 'Simpan Kata Sandi Baru' }}
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-200/80 text-center text-xs text-slate-600">
                    <Link :href="route('login')" class="font-semibold text-orange-600 hover:text-orange-700 hover:underline">
                        &larr; Kembali ke Halaman Login
                    </Link>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

