<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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

const showPassword = ref(false);

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
    <div class="min-h-screen bg-slate-100 text-slate-900">
        <Head title="Login Panitia Internal">
            <meta head-key="robots" name="robots" content="noindex, nofollow, noarchive, nosnippet" />
            <meta head-key="googlebot" name="googlebot" content="noindex, nofollow, noarchive, nosnippet" />
        </Head>

        <main class="grid min-h-screen lg:grid-cols-[0.96fr_1.04fr]">
            <aside class="relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_12%,rgba(234,88,12,0.28),transparent_32%),linear-gradient(145deg,transparent_40%,rgba(15,23,42,0.88)_100%)]"></div>

                <Link :href="route('home')" class="relative inline-flex w-fit items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-orange-600 text-sm font-black">YT</div>
                    <div>
                        <p class="font-bold">YTEvent</p>
                        <p class="mt-0.5 text-xs text-slate-400">Control Center</p>
                    </div>
                </Link>

                <div class="relative max-w-md">
                    <p class="text-xs font-bold uppercase text-orange-400">Workspace internal</p>
                    <h1 class="mt-3 text-4xl font-black leading-tight">Operasi event dalam satu kendali.</h1>
                    <p class="mt-5 max-w-sm text-sm leading-6 text-slate-300">
                        Kelola peserta, pembayaran, screening, turnamen, dan laporan dari portal yang dibatasi berdasarkan peran.
                    </p>

                    <div class="mt-9 grid grid-cols-3 gap-3">
                        <div class="border-l border-white/15 pl-3">
                            <p class="text-xs text-slate-400">Akses</p>
                            <p class="mt-1 text-sm font-bold">Berbasis role</p>
                        </div>
                        <div class="border-l border-white/15 pl-3">
                            <p class="text-xs text-slate-400">Keamanan</p>
                            <p class="mt-1 text-sm font-bold">Sesi terautentikasi</p>
                        </div>
                        <div class="border-l border-white/15 pl-3">
                            <p class="text-xs text-slate-400">Operasi</p>
                            <p class="mt-1 text-sm font-bold">Audit siap</p>
                        </div>
                    </div>
                </div>

                <p class="relative text-xs text-slate-500">Hanya akun staf yang memiliki izin portal internal.</p>
            </aside>

            <section class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-8 lg:px-12">
                <div class="w-full max-w-md page-transition-enter">
                    <div class="mb-6 lg:hidden">
                        <Link :href="route('home')" class="inline-flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-950 text-sm font-black text-white">YT</div>
                            <div>
                                <p class="font-bold text-slate-950">YTEvent</p>
                                <p class="text-xs text-slate-500">Control Center</p>
                            </div>
                        </Link>
                    </div>

                    <FlashAlert />

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_20px_60px_rgba(15,23,42,0.10)] sm:p-8">
                        <div class="mb-6">
                            <p class="text-xs font-bold uppercase text-orange-700">Autentikasi staf</p>
                            <h2 class="mt-2 text-2xl font-black text-slate-950">Masuk ke Control Center</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                Gunakan akun resmi yang telah ditetapkan oleh superadmin.
                            </p>
                        </div>

                        <FormErrorBanner :errors="form.errors" />

                        <form @submit.prevent="submit" novalidate class="space-y-5">
                            <div data-field="email">
                                <label for="admin-email" class="mb-1.5 block text-xs font-bold text-slate-700">
                                    Email resmi staf
                                </label>
                                <input
                                    id="admin-email"
                                    v-model="form.email"
                                    type="text"
                                    inputmode="email"
                                    autocomplete="username"
                                    placeholder="nama@persijadevelopment.id"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.email }"
                                    @input="form.clearErrors('email')"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div data-field="password">
                                <label for="admin-password" class="mb-1.5 block text-xs font-bold text-slate-700">
                                    Kata sandi
                                </label>
                                <div class="relative">
                                    <input
                                        id="admin-password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        autocomplete="current-password"
                                        placeholder="Masukkan kata sandi"
                                        class="input-field pr-12"
                                        :class="{ 'input-field-error': form.errors.password }"
                                        @input="form.clearErrors('password')"
                                    />
                                    <button
                                        type="button"
                                        class="absolute right-2 top-1/2 min-h-10 min-w-10 -translate-y-1/2 inline-flex items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                        :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                        :aria-pressed="showPassword"
                                        @click="showPassword = !showPassword"
                                    >
                                        <svg v-if="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            <circle cx="12" cy="12" r="3" stroke-width="2" />
                                        </svg>
                                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3l18 18M10.585 10.585A2 2 0 0012 14a2 2 0 001.414-.586M9.88 5.11A9.953 9.953 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.96 9.96 0 01-1.745 3.097M6.61 6.61C4.45 7.814 2.94 9.718 2.458 12c1.274 4.057 5.065 7 9.542 7 1.17 0 2.292-.202 3.327-.572" />
                                        </svg>
                                    </button>
                                </div>
                                <InputError :message="form.errors.password" />
                            </div>

                            <label class="inline-flex min-h-11 items-center gap-2 text-xs text-slate-600">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                />
                                Ingat sesi saya pada perangkat ini
                            </label>

                            <button type="submit" class="btn-pitch w-full" :disabled="form.processing">
                                {{ form.processing ? 'Memverifikasi sesi...' : 'Masuk ke Control Center' }}
                            </button>
                        </form>

                        <div class="mt-6 flex items-center justify-between border-t border-slate-200 pt-5 text-xs">
                            <Link :href="route('home')" class="min-h-11 inline-flex items-center font-semibold text-slate-600 hover:text-slate-950">
                                Kembali ke portal
                            </Link>
                            <Link :href="route('login')" class="min-h-11 inline-flex items-center font-semibold text-orange-700 hover:text-orange-800">
                                Login manajer tim
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>

