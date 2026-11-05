<script setup>
import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
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
            errs.email = 'Alamat email wajib diisi.';
        } else if (!isValidEmail(form.email)) {
            errs.email = 'Format alamat email yang dimasukkan tidak valid.';
        }
        if (isEmpty(form.password)) {
            errs.password = 'Kata sandi wajib diisi.';
        }
        return errs;
    });

    if (!ok) return;

    form.post(route('login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <PublicLayout>
        <Head title="Login Manajer Tim" />

        <div class="max-w-md mx-auto px-4 py-12">
            <div class="frosted-card p-6 sm:p-8">
                <div class="mb-6">
                    <span class="inline-block text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-md mb-2">
                        Portal Manajer Tim
                    </span>
                    <h1 class="text-xl font-bold text-slate-900">Masuk ke Akun Tim</h1>
                    <p class="text-xs text-slate-600 mt-1">
                        Kelola pembayaran pendaftaran, biodata pemain, dan unggah berkas kelengkapan kompetisi.
                    </p>
                </div>

                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-4">
                    <div data-field="email">
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">
                            Alamat Email Manajer
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="text"
                            inputmode="email"
                            autocomplete="username"
                            placeholder="manajer@ssbnusantara.id"
                            class="input-field"
                            :class="{ 'input-field-error': form.errors.email }"
                            @input="form.clearErrors('email')"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div data-field="password">
                        <label for="password" class="block text-xs font-semibold text-slate-700 mb-1">
                            Kata Sandi
                        </label>
                        <input
                            id="password"
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

                    <div class="flex items-center justify-between pt-1">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-600"
                            />
                            <span>Ingat sesi saya</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-pitch w-full py-2.5" :disabled="form.processing">
                        {{ form.processing ? 'Memverifikasi...' : 'Masuk ke Portal Manajer' }}
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-200/80 text-center text-xs text-slate-600">
                    Belum mendaftarkan tim?
                    <Link :href="route('home')" class="font-semibold text-emerald-700 hover:underline">
                        Pilih kompetisi di Katalog Event
                    </Link>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

