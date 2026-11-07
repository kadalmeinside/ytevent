<script setup>
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import FlashAlert from '@/Components/FlashAlert.vue';
import { useFormValidator } from '@/Composables/useFormValidator';

const { applyValidation, isEmpty, isValidEmail } = useFormValidator();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.email)) {
            errs.email = 'Alamat email staf wajib diisi.';
        } else if (!isValidEmail(form.email)) {
            errs.email = 'Format alamat email yang dimasukkan tidak valid.';
        }
        if (isEmpty(form.password)) {
            errs.password = 'Kata sandi wajib diisi.';
        }
        return errs;
    });

    if (!ok) return;

    form.post(route('admin.login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <Head title="Login Panitia Internal">
            <meta head-key="robots" name="robots" content="noindex, nofollow, noarchive, nosnippet" />
            <meta head-key="googlebot" name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />
        </Head>

        <div class="max-w-md w-full mx-auto">
            <div class="text-center mb-6">
                <Link :href="route('home')" class="inline-flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                        ADM
                    </div>
                    <div class="text-left">
                        <span class="block text-base font-bold text-slate-900 leading-tight">YTEvent Control Center</span>
                        <span class="block text-xs text-slate-500">Superadmin &amp; Finance Portal</span>
                    </div>
                </Link>
            </div>

            <FlashAlert />

            <div class="frosted-card p-6 sm:p-8">
                <div class="mb-5">
                    <h1 class="text-lg font-bold text-slate-900">Autentikasi Staf Internal</h1>
                    <p class="text-xs text-slate-600 mt-1">
                        Khusus peran Superadmin dan Finance untuk pengelolaan kompetisi dan verifikasi pembayaran.
                    </p>
                </div>

                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-4">
                    <div data-field="email">
                        <label for="admin-email" class="block text-xs font-semibold text-slate-700 mb-1">
                            Email Resmi Staf
                        </label>
                        <input
                            id="admin-email"
                            v-model="form.email"
                            type="text"
                            inputmode="email"
                            autocomplete="username"
                            placeholder="superadmin@ytevent.id"
                            class="input-field"
                            :class="{ 'input-field-error': form.errors.email }"
                            @input="form.clearErrors('email')"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div data-field="password">
                        <label for="admin-password" class="block text-xs font-semibold text-slate-700 mb-1">
                            Kata Sandi
                        </label>
                        <input
                            id="admin-password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Masukkan kata sandi"
                            class="input-field"
                            :class="{ 'input-field-error': form.errors.password }"
                            @input="form.clearErrors('password')"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <button type="submit" class="btn-pitch w-full" :disabled="form.processing">
                        {{ form.processing ? 'Memverifikasi Sesi...' : 'Masuk ke Control Center' }}
                    </button>
                </form>

                <div class="mt-5 pt-4 border-t border-slate-200/80 flex items-center justify-between text-xs text-slate-500">
                    <Link :href="route('home')" class="hover:text-slate-800">&larr; Kembali ke Portal Publik</Link>
                    <Link :href="route('login')" class="text-orange-600 hover:text-orange-700 font-semibold hover:underline">Login Manajer Tim</Link>
                </div>
            </div>
        </div>
    </div>
</template>

