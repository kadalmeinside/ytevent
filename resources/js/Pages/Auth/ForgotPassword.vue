<script setup>
import { useForm, Link, Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import { useFormValidator } from '@/Composables/useFormValidator';

const { applyValidation, isEmpty, isValidEmail } = useFormValidator();

const form = useForm({
    email: '',
});

const submit = () => {
    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.email)) {
            errs.email = 'Alamat email terdaftar wajib diisi.';
        } else if (!isValidEmail(form.email)) {
            errs.email = 'Format alamat email yang dimasukkan tidak valid.';
        }
        return errs;
    });

    if (!ok) return;

    form.post(route('password.email'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <PublicLayout>
        <Head title="Lupa Kata Sandi">
            <meta head-key="robots" name="robots" content="noindex, nofollow" />
        </Head>

        <div class="max-w-md mx-auto px-4 py-12">
            <div class="frosted-card p-6 sm:p-8">
                <div class="mb-6">
                    <span class="inline-block text-xs font-semibold uppercase tracking-wider text-orange-800 bg-orange-50 border border-orange-200 px-2.5 py-0.5 rounded-md mb-2">
                        Pemulihan Akses Akun
                    </span>
                    <h1 class="text-xl font-bold text-slate-900">Lupa Kata Sandi</h1>
                    <p class="text-xs text-slate-600 mt-1">
                        Masukkan alamat email Manajer Tim yang terdaftar. Kami akan mengirimkan tautan pemulihan kata sandi ke email Anda.
                    </p>
                </div>

                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-4">
                    <div data-field="email">
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">
                            Alamat Email Terdaftar
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="text"
                            inputmode="email"
                            autocomplete="email"
                            placeholder="manajer@ssbnusantara.id"
                            class="input-field"
                            :class="{ 'input-field-error': form.errors.email }"
                            @input="form.clearErrors('email')"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <button type="submit" class="btn-pitch w-full" :disabled="form.processing">
                        {{ form.processing ? 'Mengirim Tautan...' : 'Kirim Tautan Atur Ulang Kata Sandi' }}
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-200/80 text-center text-xs text-slate-600">
                    Sudah ingat kata sandi Anda?
                    <Link :href="route('login')" class="font-semibold text-orange-600 hover:text-orange-700 hover:underline">
                        Kembali ke Halaman Login
                    </Link>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
