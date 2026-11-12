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

const isForgotModalOpen = ref(false);

const whatsappNumber = computed(() => {
    const raw = page.props.systemSettings?.contact_whatsapp || '6281234567890';
    return raw.replace(/[^0-9]/g, '');
});

const whatsappHelpUrl = computed(() => {
    const text = encodeURIComponent('Halo Panitia Pelaksana YTEvent, saya manajer tim ingin mengajukan permohonan reset kata sandi akun portal manajer tim kami.');
    return `https://wa.me/${whatsappNumber.value}?text=${text}`;
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
        <Head title="Masuk ke Akun Anda" />

        <div class="max-w-md mx-auto px-4 py-12">
            <div class="frosted-card p-6 sm:p-8">
                <div class="mb-6">
                    <span class="inline-block text-xs font-semibold uppercase tracking-wider text-orange-800 bg-orange-50 border border-orange-200 px-2.5 py-0.5 rounded-md mb-2">
                        Portal Akun Terpadu
                    </span>
                    <h1 class="text-xl font-bold text-slate-900">Masuk ke Akun Anda</h1>
                    <p class="text-xs text-slate-600 mt-1">
                        Satu akun universal untuk mengelola tim turnamen, pendaftaran audisi anak, pembayaran, dan berkas keabsahan.
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

        <!-- Forgot Password Help Modal -->
        <div
            v-if="isForgotModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
        >
            <div class="frosted-card bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Bantuan Lupa Kata Sandi</h3>
                    </div>
                    <button
                        type="button"
                        class="text-slate-400 hover:text-slate-600 text-lg leading-none"
                        @click="isForgotModalOpen = false"
                    >
                        &times;
                    </button>
                </div>

                <div class="space-y-3 text-xs text-slate-600 leading-relaxed">
                    <p>
                        Untuk menjaga keamanan data berkas pemain dan status kepesertaan turnamen, pemulihan atau reset kata sandi akun Manajer Tim dilakukan melalui verifikasi Panitia Pelaksana.
                    </p>

                    <div class="p-3.5 rounded-xl bg-orange-50/70 border border-orange-200 space-y-2">
                        <p class="font-bold text-slate-900 text-xs">Cara Reset Kata Sandi:</p>
                        <ol class="list-decimal list-inside space-y-1 text-slate-700">
                            <li>Hubungi Panitia Pelaksana melalui tombol WhatsApp di bawah.</li>
                            <li>Sebutkan <strong>Nama Tim</strong> dan <strong>Alamat Email</strong> terdaftar.</li>
                            <li>Panitia/Superadmin akan mereset kata sandi melalui panel admin dan memberikan kata sandi baru untuk Anda.</li>
                        </ol>
                    </div>
                </div>

                <div class="pt-2 flex flex-col sm:flex-row items-center gap-2">
                    <a
                        :href="whatsappHelpUrl"
                        target="_blank"
                        class="btn-pitch w-full sm:flex-1 justify-center inline-flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.698c.97.529 1.954.81 3.033.81 3.181 0 5.767-2.586 5.768-5.766 0-3.18-2.587-5.767-5.767-5.767zm7.659 5.766c0 4.225-3.438 7.662-7.66 7.662-1.309 0-2.551-.334-3.649-.927l-4.041 1.06 1.079-3.941c-.672-1.155-1.049-2.482-1.049-3.854 0-4.225 3.438-7.662 7.66-7.662 4.223 0 7.66 3.437 7.66 7.662z"/>
                        </svg>
                        <span>Hubungi Panitia via WA</span>
                    </a>
                    <button
                        type="button"
                        class="btn-secondary w-full sm:w-auto"
                        @click="isForgotModalOpen = false"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>

