<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';
import FormErrorBanner from '@/Components/FormErrorBanner.vue';
import FileInput from '@/Components/FileInput.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { useFormValidator } from '@/Composables/useFormValidator';
import { compressImage } from '@/Utils/imageCompressor';

const props = defineProps({
    event: {
        type: Object,
        default: null,
    },
    formats: {
        type: Object,
        default: () => ({}),
    },
    statuses: {
        type: Object,
        default: () => ({}),
    },
    documentTypes: {
        type: Object,
        default: () => ({}),
    },
});

const isEdit = computed(() => Boolean(props.event?.id));
const bannerPreview = ref(props.event?.banner_url || null);
const { applyValidation, isEmpty } = useFormValidator();

const form = useForm({
    name: props.event?.name || '',
    short_description: props.event?.short_description || '',
    venue_name: props.event?.venue_name || '',
    venue_maps_url: props.event?.venue_maps_url || '',
    city: props.event?.city || '',
    format: props.event?.format || 'cup',
    age_category: props.event?.age_category || 'U-15',
    min_birth_date: props.event?.min_birth_date || '',
    max_birth_date: props.event?.max_birth_date || '',
    min_players_per_team: props.event?.min_players_per_team ?? 14,
    max_players_per_team: props.event?.max_players_per_team ?? 25,
    min_gk_per_team: props.event?.min_gk_per_team ?? 2,
    max_officials_per_team: props.event?.max_officials_per_team ?? 5,
    show_coach_license: props.event?.show_coach_license ?? true,
    require_coach_license: props.event?.require_coach_license ?? false,
    required_documents: props.event?.required_documents || ['birth_certificate', 'family_card', 'student_card_kia'],
    max_teams: props.event?.max_teams ?? 32,
    registration_fee: props.event?.registration_fee ?? 0,
    prize_info: props.event?.prize_info || '',
    lock_roster_until_paid: props.event?.lock_roster_until_paid ?? true,
    payment_deadline_hours: props.event?.payment_deadline_hours ?? 24,
    reupload_grace_hours: props.event?.reupload_grace_hours ?? 6,
    registration_open_at: props.event?.registration_open_at || '',
    registration_close_at: props.event?.registration_close_at || '',
    match_start_date: props.event?.match_start_date || '',
    match_end_date: props.event?.match_end_date || '',
    contact_person: props.event?.contact_person || '',
    contact_whatsapp: props.event?.contact_whatsapp || '',
    status: props.event?.status || 'draft',
    banner_file: null,
    regulation_pdf: null,
});

const handleBannerFile = async (raw) => {
    if (!raw) {
        form.banner_file = null;
        bannerPreview.value = props.event?.banner_url || null;
        return;
    }
    const { file } = await compressImage(raw, { maxDimension: 1920, quality: 0.85 });
    form.banner_file = file;
    form.clearErrors('banner_file');
    bannerPreview.value = URL.createObjectURL(file);
};

const handlePdfFile = (raw) => {
    form.regulation_pdf = raw || null;
    form.clearErrors('regulation_pdf');
};

const submit = () => {
    const ok = applyValidation(form, () => {
        const errs = {};
        if (isEmpty(form.name)) errs.name = 'Nama kompetisi wajib diisi.';
        if (isEmpty(form.venue_name)) errs.venue_name = 'Lokasi atau stadion utama wajib diisi.';
        if (isEmpty(form.city)) errs.city = 'Kota penyelenggaraan wajib diisi.';
        if (isEmpty(form.age_category)) errs.age_category = 'Label kategori usia wajib diisi.';
        if (isEmpty(form.max_teams) || Number(form.max_teams) < 2) {
            errs.max_teams = 'Kuota maksimal tim wajib berupa angka minimal 2 tim.';
        }
        if (isEdit.value && props.event?.occupied_slots && Number(form.max_teams) < props.event.occupied_slots) {
            errs.max_teams = `Kuota maksimal tidak boleh dikurangi di bawah jumlah tim yang saat ini sedang memegang slot (${props.event.occupied_slots} tim).`;
        }
        if (isEmpty(form.required_documents) || form.required_documents.length === 0) {
            errs.required_documents = 'Pilih minimal satu jenis dokumen kelengkapan wajib untuk pemain.';
        }
        return errs;
    });

    if (!ok) return;

    if (isEdit.value) {
        form.post(route('admin.events.update', { event: props.event.id }), {
            forceFormData: true,
            preserveScroll: true,
        });
    } else {
        form.post(route('admin.events.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AdminLayout
        :page-title="isEdit ? `Edit Kompetisi: ${event.name}` : 'Buat Kompetisi Sepak Bola Baru'"
        subtitle="Atur batas kuota tim (anti-overbooking), kelompok umur kelayakan pemain, batas waktu reservasi, dan dokumen wajib."
    >
        <Head :title="isEdit ? `Edit ${event.name}` : 'Buat Event Baru'" />

        <div class="max-w-4xl">
            <div class="mb-4">
                <Link :href="route('admin.events.index')" class="text-xs font-semibold text-slate-600 hover:text-orange-600 transition-colors">
                    &larr; Kembali ke Daftar Kompetisi
                </Link>
            </div>

            <div class="frosted-card p-6 sm:p-8">
                <FormErrorBanner :errors="form.errors" />

                <form @submit.prevent="submit" novalidate class="space-y-6">
                    <!-- Section 1: Informasi Dasar -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200">
                            1. Informasi Dasar Kompetisi
                        </h2>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div data-field="name" class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Kompetisi / Turnamen</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Contoh: Piala Nusantara U-15 2026"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.name }"
                                    @input="form.clearErrors('name')"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div data-field="format">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Format Kompetisi</label>
                                <select v-model="form.format" class="input-field">
                                    <option v-for="(label, key) in formats" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>

                            <div data-field="status">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Status Kompetisi</label>
                                <select v-model="form.status" class="input-field">
                                    <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>

                            <div data-field="venue_name">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Stadion / Lapangan Utama</label>
                                <input
                                    v-model="form.venue_name"
                                    type="text"
                                    placeholder="Stadion Madya GBK"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.venue_name }"
                                    @input="form.clearErrors('venue_name')"
                                />
                                <InputError :message="form.errors.venue_name" />
                            </div>

                            <div data-field="city">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kota Penyelenggaraan</label>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    placeholder="Jakarta Pusat"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.city }"
                                    @input="form.clearErrors('city')"
                                />
                                <InputError :message="form.errors.city" />
                            </div>

                            <div data-field="venue_maps_url" class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Link Google Maps Lokasi Stadion <span class="text-slate-400 font-normal">(Opsional)</span>
                                </label>
                                <input
                                    v-model="form.venue_maps_url"
                                    type="url"
                                    placeholder="https://maps.google.com/?q=..."
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.venue_maps_url }"
                                    @input="form.clearErrors('venue_maps_url')"
                                />
                                <p class="text-[11px] text-slate-500 mt-1">
                                    Masukkan URL lengkap Google Maps agar calon peserta / manajer tim dapat langsung menavigasi ke stadion melalui tombol Maps di halaman detail.
                                </p>
                                <InputError :message="form.errors.venue_maps_url" />
                            </div>

                            <div class="sm:col-span-2">
                                <RichTextEditor
                                    v-model="form.short_description"
                                    label="Deskripsi &amp; Informasi Kompetisi"
                                    placeholder="Uraian lengkap mengenai kejuaraan, peserta yang disasar, babak turnamen, dan informasi teknis..."
                                    :error="form.errors.short_description"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Kuota Maksimal & Keuangan -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200">
                            2. Kuota Maksimal Tim &amp; Pengaturan Keuangan
                        </h2>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div data-field="max_teams">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Kuota Maksimal Tim (Slot Keras)
                                </label>
                                <input
                                    v-model="form.max_teams"
                                    type="number"
                                    min="2"
                                    class="input-field tabular-data"
                                    :class="{ 'input-field-error': form.errors.max_teams }"
                                    @input="form.clearErrors('max_teams')"
                                />
                                <p v-if="isEdit" class="text-[10px] text-slate-500 mt-0.5">
                                    Slot terisi saat ini: <strong class="tabular-data text-orange-950 font-bold">{{ event.occupied_slots }} tim</strong>.
                                </p>
                                <InputError :message="form.errors.max_teams" />
                            </div>

                            <div data-field="registration_fee">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Biaya Pendaftaran (Rp)</label>
                                <input
                                    v-model="form.registration_fee"
                                    type="number"
                                    min="0"
                                    step="50000"
                                    class="input-field tabular-data"
                                />
                                <InputError :message="form.errors.registration_fee" />
                            </div>

                            <div data-field="payment_deadline_hours">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Batas Reservasi Bayar (Jam)</label>
                                <input
                                    v-model="form.payment_deadline_hours"
                                    type="number"
                                    min="1"
                                    max="168"
                                    class="input-field tabular-data"
                                />
                                <p class="text-[10px] text-slate-500 mt-0.5">Slot dilepas otomatis jika unpaid lewat durasi ini.</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Informasi Hadiah / Trofi</label>
                                <input
                                    v-model="form.prize_info"
                                    type="text"
                                    placeholder="Total Hadiah Rp 50.000.000 + Trofi Bergilir"
                                    class="input-field"
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Grace Period Bukti Ditolak (Jam)</label>
                                <input
                                    v-model="form.reupload_grace_hours"
                                    type="number"
                                    min="1"
                                    max="72"
                                    class="input-field tabular-data"
                                />
                            </div>

                            <div class="sm:col-span-3 p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-slate-700">
                                    <input
                                        v-model="form.lock_roster_until_paid"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                    />
                                    <span>Kunci menu input pemain hingga tim menyelesaikan pembayaran pendaftaran (Status Lunas)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Regulasi Usia & Skuad -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200">
                            3. Regulasi Kelayakan Usia Pemain &amp; Dokumen Wajib
                        </h2>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div data-field="age_category">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kategori Usia</label>
                                <input
                                    v-model="form.age_category"
                                    type="text"
                                    placeholder="Contoh: U-12 / U-15 / Senior"
                                    class="input-field"
                                    :class="{ 'input-field-error': form.errors.age_category }"
                                    @input="form.clearErrors('age_category')"
                                />
                                <InputError :message="form.errors.age_category" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Batas Minimal Tanggal Lahir</label>
                                <input v-model="form.min_birth_date" type="date" class="input-field tabular-data" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Batas Maksimal Tanggal Lahir</label>
                                <input v-model="form.max_birth_date" type="date" class="input-field tabular-data" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Min. Pemain per Tim</label>
                                <input v-model="form.min_players_per_team" type="number" min="5" class="input-field tabular-data" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Maks. Pemain per Tim</label>
                                <input v-model="form.max_players_per_team" type="number" min="5" class="input-field tabular-data" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Maks. Ofisial per Tim</label>
                                <input v-model="form.max_officials_per_team" type="number" min="1" class="input-field tabular-data" />
                            </div>

                            <!-- Pengaturan Syarat Minimal Penjaga Gawang (GK) & Lisensi Pelatih -->
                            <div class="sm:col-span-3 p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                    <div data-field="min_gk_per_team">
                                        <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                            Syarat Minimal Penjaga Gawang (GK) per Tim
                                        </label>
                                        <div class="grid grid-cols-2 gap-2.5 text-xs">
                                            <label
                                                class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors"
                                                :class="Number(form.min_gk_per_team) === 1 ? 'border-orange-500 bg-orange-50 text-orange-950 font-bold' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                            >
                                                <input
                                                    v-model="form.min_gk_per_team"
                                                    type="radio"
                                                    :value="1"
                                                    class="text-orange-600 focus:ring-orange-500"
                                                />
                                                <span>Minimal 1 GK</span>
                                            </label>
                                            <label
                                                class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-colors"
                                                :class="Number(form.min_gk_per_team) === 2 ? 'border-orange-500 bg-orange-50 text-orange-950 font-bold' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                            >
                                                <input
                                                    v-model="form.min_gk_per_team"
                                                    type="radio"
                                                    :value="2"
                                                    class="text-orange-600 focus:ring-orange-500"
                                                />
                                                <span>Minimal 2 GK</span>
                                            </label>
                                        </div>
                                        <p class="text-[11px] text-slate-500 mt-1">
                                            Menentukan jumlah minimal kiper yang wajib lolos screening agar tim dapat mengunci skuad final.
                                        </p>
                                        <InputError :message="form.errors.min_gk_per_team" />
                                    </div>

                                    <div class="space-y-3">
                                        <label class="block text-xs font-bold text-slate-800">
                                            Pengaturan Input Lisensi Pelatih Kepala (Head Coach)
                                        </label>
                                        <label class="flex items-start gap-2.5 cursor-pointer text-xs font-medium text-slate-700 p-2.5 rounded-lg border border-slate-200 bg-white">
                                            <input
                                                v-model="form.show_coach_license"
                                                type="checkbox"
                                                class="mt-0.5 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                                @change="!form.show_coach_license && (form.require_coach_license = false)"
                                            />
                                            <div>
                                                <span class="font-bold text-slate-900 block">Munculkan Kolom Lisensi Pelatih pada Pendaftaran &amp; Portal Tim</span>
                                                <span class="text-[11px] text-slate-500 block mt-0.5">
                                                    Jika tidak dicentang, kolom lisensi kepelatihan akan disembunyikan pada form pendaftaran tim dan ofisial.
                                                </span>
                                            </div>
                                        </label>

                                        <div v-if="form.show_coach_license" class="pl-3 border-l-2 border-orange-300 space-y-1.5">
                                            <span class="block text-[11px] font-bold text-slate-700">Status Kewajiban Pengisian Lisensi Pelatih:</span>
                                            <div class="grid grid-cols-2 gap-2 text-xs">
                                                <label
                                                    class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors"
                                                    :class="!form.require_coach_license ? 'border-orange-500 bg-orange-50 text-orange-950 font-bold' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                                >
                                                    <input
                                                        v-model="form.require_coach_license"
                                                        type="radio"
                                                        :value="false"
                                                        class="text-orange-600 focus:ring-orange-500"
                                                    />
                                                    <span>Opsional</span>
                                                </label>
                                                <label
                                                    class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors"
                                                    :class="form.require_coach_license ? 'border-orange-500 bg-orange-50 text-orange-950 font-bold' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'"
                                                >
                                                    <input
                                                        v-model="form.require_coach_license"
                                                        type="radio"
                                                        :value="true"
                                                        class="text-orange-600 focus:ring-orange-500"
                                                    />
                                                    <span>Wajib Diisi</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Checkbox Dokumen Wajib -->
                            <div data-field="required_documents" class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 mb-2">
                                    Dokumen Wajib Diunggah per Pemain saat Screening:
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                    <label
                                        v-for="(label, docKey) in documentTypes"
                                        :key="docKey"
                                        class="flex items-center gap-2 p-2.5 rounded-lg border border-slate-200 bg-slate-50 cursor-pointer hover:bg-slate-100"
                                    >
                                        <input
                                            v-model="form.required_documents"
                                            type="checkbox"
                                            :value="docKey"
                                            class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                            @change="form.clearErrors('required_documents')"
                                        />
                                        <span>{{ label }}</span>
                                    </label>
                                </div>
                                <InputError :message="form.errors.required_documents" />
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Jadwal & Berkas -->
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 pb-2 border-b border-slate-200">
                            4. Jadwal Pendaftaran &amp; Pelaksanaan
                        </h2>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pendaftaran Dibuka</label>
                                <input v-model="form.registration_open_at" type="datetime-local" class="input-field tabular-data" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pendaftaran Ditutup</label>
                                <input v-model="form.registration_close_at" type="datetime-local" class="input-field tabular-data" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pertandingan Dimulai (Kick-Off)</label>
                                <input v-model="form.match_start_date" type="date" class="input-field tabular-data" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Pertandingan Selesai</label>
                                <input v-model="form.match_end_date" type="date" class="input-field tabular-data" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Kontak PIC Panitia</label>
                                <input v-model="form.contact_person" type="text" placeholder="Sekretariat Panpel" class="input-field" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">WhatsApp PIC</label>
                                <input v-model="form.contact_whatsapp" type="text" placeholder="0812xxxxxxxx" class="input-field tabular-data" />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                    Banner Gambar Kompetisi (Format JPG/PNG/WebP, Rasio 16:9 / Landscape)
                                </label>
                                <FileInput
                                    v-model="form.banner_file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    :preview-url="bannerPreview"
                                    label="Pilih Banner Kompetisi"
                                    hint="Gambar berukuran besar dikompresi otomatis sebelum diunggah."
                                    :error="form.errors.banner_file"
                                    @change="handleBannerFile"
                                />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-800 mb-1.5">
                                    Berkas PDF Regulasi Teknis Kompetisi (Maks. 5 MB)
                                </label>
                                <FileInput
                                    v-model="form.regulation_pdf"
                                    accept=".pdf"
                                    :preview-url="event?.regulation_pdf_url"
                                    label="Pilih Berkas PDF Regulasi"
                                    hint="Format berkas: Dokumen PDF resmi (Maksimal 5 MB)"
                                    :error="form.errors.regulation_pdf"
                                    @change="handlePdfFile"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                        <Link :href="route('admin.events.index')" class="btn-secondary">Batal</Link>
                        <button type="submit" class="btn-pitch" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Perbarui Pengaturan Kompetisi' : 'Simpan Kompetisi Baru') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

