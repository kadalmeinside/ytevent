<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, Link, Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import { useFormValidator } from '@/Composables/useFormValidator';

const page = usePage();
const { applyValidation, isEmpty, isValidEmail } = useFormValidator();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const systemSettings = computed(() => page.props.systemSettings || {});

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
        <Head title="Masuk ke Akun Anda" />

        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-14">
            <div class="grid overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-[0_20px_60px_rgba(15,23,42,0.10)] lg:grid-cols-[0.9fr_1.1fr]">
                <aside class="relative hidden min-h-[580px] flex-col justify-between overflow-hidden bg-slate-950 p-10 text-white lg:flex">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(234,88,12,0.30),transparent_42%),radial-gradient(circle_at_bottom_right,rgba(220,38,38,0.18),transparent_38%)]"></div>
                    <div class="relative">
                        <Link :href="route('home')" class="inline-flex items-center gap-3">
                            <img
                                v-if="systemSettings.app_logo_url"
                                :src="systemSettings.app_logo_url"
                                :alt="systemSettings.app_name || 'YTEvent'"
                                class="h-11 w-11 rounded-lg object-contain bg-white p-1"
                            />
                            <div v-else class="flex h-11 w-11 items-center justify-center rounded-lg bg-orange-600 text-sm font-black">YT</div>
                            <div>
                                <div class="font-bold">{{ systemSettings.app_name || 'YTEvent' }}</div>
                                <div class="mt-0.5 text-xs text-slate-300">Portal peserta dan manajemen tim</div>
                            </div>
                        </Link>
                    </div>

                    <div class="relative max-w-sm">
                        <p class="mb-4 text-xs font-bold uppercase text-orange-400">Pusat aktivitas kompetisi</p>
                        <h2 class="text-3xl font-black leading-tight tracking-tight">Kelola perjalanan tim Anda.</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-300">
                            Pantau pembayaran, kelengkapan pemain, screening, akreditasi, jadwal, dan Digital Pass dari satu akun.
                        </p>

                        <div class="mt-8 grid grid-cols-2 gap-3">
                            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                                <div class="text-lg font-black text-white">Tim</div>
                                <div class="mt-1 text-xs text-slate-300">Roster &amp; dokumen</div>
                            </div>
                            <div class="rounded-lg border border-white/10 bg-white/5 p-4">
                                <div class="text-lg font-black text-white">Event</div>
                                <div class="mt-1 text-xs text-slate-300">Skor &amp; jadwal</div>
                            </div>
                        </div>
                    </div>

                    <p class="relative text-xs text-slate-400">Akses aman untuk manajer tim dan peserta audisi.</p>
                </aside>

                <div class="p-6 sm:p-10 lg:p-12">
                <div class="mb-6">
                    <span class="inline-flex items-center rounded-md border border-orange-200 bg-orange-50 px-2.5 py-1 text-xs font-bold text-orange-800">
                        Portal akun terpadu
                    </span>
                    <h1 class="mt-4 text-2xl font-black text-slate-950">Masuk ke akun Anda</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Kelola turnamen, audisi, pembayaran, dan berkas dari satu akun.
                    </p>
                </div>

                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-4">
                    <div data-field="email">
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-1">
                            Alamat Email Akun
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="text"
                            inputmode="email"
                            autocomplete="username"
                            placeholder="nama@email.com"
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
                        <div class="relative">
                            <input
                                id="password"
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

                    <div class="flex items-center justify-between pt-1">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            />
                            <span>Ingat sesi saya</span>
                        </label>

                        <Link
                            :href="route('password.request')"
                            class="text-xs font-semibold text-orange-600 hover:text-orange-700 hover:underline transition-colors"
                        >
                            Lupa kata sandi?
                        </Link>
                    </div>

                    <button type="submit" class="btn-pitch w-full" :disabled="form.processing">
                        {{ form.processing ? 'Memverifikasi...' : 'Masuk ke Akun Saya' }}
                    </button>
                </form>

                <div class="mt-6 pt-5 border-t border-slate-200/80 text-center text-xs text-slate-600">
                    Belum mendaftar turnamen atau audisi?
                    <Link :href="route('home')" class="font-semibold text-orange-600 hover:text-orange-700 hover:underline">
                        Lihat Program & Kompetisi di Katalog Event
                    </Link>
                </div>
                </div>
            </div>
        </div>

    </PublicLayout>
</template>

