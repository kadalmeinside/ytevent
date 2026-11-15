<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    participant: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['preview-ticket']);

const { formatRupiah } = useFormatters();

const p = computed(() => props.participant);
const isCompleted = computed(() => Boolean(p.value?.event?.is_completed));
const isAuditionType = computed(() => (p.value?.event?.event_type || 'audition') === 'audition');

const stageSteps = computed(() => {
    const stages = p.value?.event?.stages || [];
    const screeningPassed = p.value?.screening_status === 'passed';
    const screeningRejected = p.value?.screening_status === 'rejected';
    const stageStatus = p.value?.stage_status || 'registered';
    const presentStages = p.value?.present_stage_numbers || [];

    const steps = [
        {
            key: 'screening',
            label: 'Screening Berkas',
            sub: screeningPassed ? 'Lolos Verifikasi' : screeningRejected ? 'Perlu Perbaikan' : 'Menunggu Panitia',
            state: screeningPassed ? 'done' : screeningRejected ? 'danger' : 'current',
        },
    ];

    if (stages.length > 0) {
        stages.forEach((stage) => {
            const num = Number(stage.stage_number);
            let state = 'locked';
            let sub = stage.stage_date || 'Terjadwal';

            if (!screeningPassed) {
                state = 'locked';
                sub = 'Menunggu Screening';
            } else if (stageStatus === 'final_selected') {
                state = 'done';
                sub = 'Lolos Tahap';
            } else if (num === 1) {
                if (['day_1_passed', 'day_2_passed'].includes(stageStatus)) {
                    state = 'done';
                    sub = 'Lolos ke Tahap Berikutnya';
                } else if (stageStatus === 'day_1_eliminated') {
                    state = 'danger';
                    sub = 'Belum Lolos';
                } else {
                    state = presentStages.includes(1) ? 'current' : 'current';
                    sub = presentStages.includes(1) ? 'Hadir / Menunggu Hasil' : (stage.stage_date || 'Terdaftar');
                }
            } else if (num === 2) {
                if (stageStatus === 'day_2_passed') {
                    state = 'done';
                    sub = 'Lolos ke Final';
                } else if (stageStatus === 'day_2_eliminated') {
                    state = 'danger';
                    sub = 'Belum Lolos';
                } else if (stageStatus === 'day_1_passed') {
                    state = 'current';
                    sub = presentStages.includes(2) ? 'Hadir / Menunggu Hasil' : (stage.stage_date || 'Siap Tahap 2');
                } else {
                    state = 'locked';
                    sub = 'Menunggu Tahap 1';
                }
            } else if (num >= 3) {
                if (stageStatus === 'day_2_passed') {
                    state = 'current';
                    sub = stage.stage_date || 'Siap Final';
                } else {
                    state = 'locked';
                    sub = `Menunggu Tahap ${num - 1}`;
                }
            }

            steps.push({
                key: `stage-${num}`,
                label: stage.stage_name || `Tahap ${num}`,
                sub,
                state,
            });
        });
    }

    // Final outcome step for audition
    let finalState = 'locked';
    let finalSub = 'Menunggu Seleksi';
    if (stageStatus === 'final_selected') {
        finalState = 'done';
        finalSub = 'Terpilih Skuad Inti';
    } else if (['day_1_eliminated', 'day_2_eliminated'].includes(stageStatus)) {
        finalState = 'danger';
        finalSub = p.value?.stage_status_label || 'Gugur Seleksi';
    }

    steps.push({
        key: 'final',
        label: 'Hasil Seleksi',
        sub: finalSub,
        state: finalState,
    });

    return steps;
});

const stepBadgeClasses = (state) => {
    if (state === 'done') return 'bg-emerald-50 border-emerald-300 text-emerald-950';
    if (state === 'danger') return 'bg-rose-50 border-rose-300 text-rose-950';
    if (state === 'current') return 'bg-orange-50 border-orange-300 text-orange-950';
    return 'bg-slate-50 border-slate-200 text-slate-600';
};

const stepNumberClasses = (state) => {
    if (state === 'done') return 'bg-emerald-700 text-white';
    if (state === 'danger') return 'bg-rose-700 text-white';
    if (state === 'current') return 'bg-orange-700 text-white';
    return 'bg-slate-200 text-slate-700';
};
</script>

<template>
    <article
        class="bg-white border rounded-2xl overflow-hidden shadow-sm"
        :class="
            p.needs_attention
                ? 'border-rose-300'
                : isCompleted
                  ? 'border-slate-300'
                  : 'border-slate-200'
        "
    >
        <!-- Row Header: Event Banner Hero & Pass Status -->
        <div
            class="relative overflow-hidden p-5 sm:p-6 text-white border-l-4"
            :class="
                isCompleted
                    ? 'bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 border-slate-400'
                    : 'bg-gradient-to-br from-slate-950 via-slate-900 to-red-950 border-red-500'
            "
        >
            <img
                v-if="p.event?.banner_url"
                :src="p.event.banner_url"
                alt=""
                aria-hidden="true"
                class="absolute inset-y-0 right-0 w-full sm:w-2/3 h-full object-cover object-center opacity-65 pointer-events-none select-none"
            />
            <div
                class="absolute inset-0 pointer-events-none"
                :class="
                    isCompleted
                        ? 'bg-gradient-to-b from-slate-900/90 via-slate-900/95 to-slate-900 sm:bg-gradient-to-r sm:from-slate-900 sm:via-slate-900/92 sm:via-60% sm:to-slate-900/45'
                        : 'bg-gradient-to-b from-slate-950/90 via-slate-950/95 to-slate-950 sm:bg-gradient-to-r sm:from-slate-950 sm:via-slate-950/92 sm:via-60% sm:to-red-950/35'
                "
                aria-hidden="true"
            ></div>

            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5 min-w-0">
                    <img
                        v-if="p.photo_url"
                        :src="p.photo_url"
                        :alt="`Foto ${p.full_name}`"
                        width="56"
                        height="56"
                        class="w-14 h-14 rounded-xl object-cover border border-white/25 bg-slate-900 shrink-0"
                    />
                    <div
                        v-else
                        class="w-14 h-14 rounded-xl bg-red-600/30 border border-red-400/40 text-white font-black text-sm flex items-center justify-center shrink-0"
                        aria-hidden="true"
                    >
                        {{ p.full_name.substring(0, 2).toUpperCase() }}
                    </div>
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base sm:text-lg font-black text-white truncate">{{ p.full_name }}</h3>
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-white/15 text-white border border-white/25 tabular-data">
                                {{ p.registration_number }}
                            </span>
                            <span
                                v-if="isCompleted"
                                class="px-2 py-0.5 rounded bg-slate-200 text-slate-950 text-[11px] font-bold"
                            >
                                Event Selesai
                            </span>
                        </div>
                        <p class="text-xs text-slate-200">
                            <strong class="text-white">{{ p.event?.name }}</strong>
                            <span v-if="p.event?.age_category"> ({{ p.event.age_category }})</span>
                            <span v-if="p.session"> • {{ p.session.session_name }}</span>
                        </p>
                    </div>
                </div>

                <div class="shrink-0 flex flex-wrap items-center gap-2">
                    <span
                        v-if="p.stage_status === 'final_selected'"
                        class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-black text-xs shadow-sm"
                    >
                        {{ p.stage_status_label }}
                    </span>
                    <span
                        v-else-if="p.can_download_bib"
                        class="px-3 py-1 rounded-lg bg-emerald-500 text-slate-950 font-black text-xs shadow-sm"
                    >
                        Digital Pass Aktif
                    </span>
                    <span
                        v-else-if="p.event?.is_completed || p.event?.status === 'completed'"
                        class="px-3 py-1 rounded-lg bg-slate-800 text-slate-200 border border-white/20 font-semibold text-xs"
                    >
                        Kegiatan Selesai (Arsip)
                    </span>
                    <span
                        v-else
                        class="px-3 py-1 rounded-lg bg-slate-900/90 text-slate-100 border border-white/25 font-semibold text-xs"
                    >
                        E-Tiket Menunggu Kelengkapan
                    </span>
                    <span
                        v-if="p.bib_number"
                        class="px-2.5 py-1 rounded-lg bg-white text-slate-950 text-xs font-black tabular-data"
                    >
                        Bib #{{ p.bib_number }}
                    </span>
                </div>
            </div>
        </div>

        <div class="p-5 space-y-4">
        <!-- Structured Status & Athlete Ledger (Strictly 1 domain per box) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="block text-[11px] font-bold text-slate-600">Status Pembayaran</span>
                <span
                    class="mt-1 inline-block font-bold"
                    :class="p.is_paid ? 'text-emerald-800' : 'text-amber-900'"
                >
                    {{ p.is_paid ? 'Lunas' : 'Menunggu Pembayaran' }}
                </span>
                <span v-if="p.latest_payment" class="block text-[11px] text-slate-600 font-mono mt-0.5 tabular-data">
                    {{ p.latest_payment.invoice_number }} ({{ formatRupiah(p.latest_payment.total_amount) }})
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="block text-[11px] font-bold text-slate-600">Screening Berkas</span>
                <span
                    class="mt-1 inline-block font-bold"
                    :class="
                        p.screening_status === 'passed'
                            ? 'text-emerald-800'
                            : p.screening_status === 'rejected'
                              ? 'text-rose-800'
                              : 'text-slate-900'
                    "
                >
                    {{ p.screening_status_label }}
                </span>
                <span class="block text-[11px] text-slate-600 mt-0.5">
                    {{ p.screening_status === 'passed' ? 'Dokumen peserta dinyatakan sah' : p.screening_status === 'rejected' ? 'Periksa catatan perbaikan' : 'Dalam antrean pemeriksaan panitia' }}
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="block text-[11px] font-bold text-slate-600">Profil Teknis</span>
                <span class="mt-1 block font-bold text-slate-900">
                    {{ p.preferred_position || 'Belum ditentukan' }}
                </span>
                <span class="block text-[11px] text-slate-600 mt-0.5 capitalize">
                    Kaki: {{ p.dominant_foot || '-' }}
                    <template v-if="p.height_cm"> • {{ p.height_cm }} cm / {{ p.weight_kg }} kg</template>
                </span>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                <span class="block text-[11px] font-bold text-slate-600">Asal Klub / Lokasi</span>
                <span class="mt-1 block font-bold text-slate-900 truncate">
                    {{ p.current_club_ssb || 'Independen' }}
                </span>
                <span v-if="p.event?.city || p.event?.match_start_date" class="block text-[11px] text-slate-600 mt-0.5 truncate">
                    {{ p.event?.city || '' }}
                    <template v-if="p.event?.city && p.event?.match_start_date"> • </template>
                    {{ p.event?.match_start_date || '' }}
                </span>
            </div>
        </div>

        <!-- Dedicated Stage Progression Strip (Separated from Screening Berkas) -->
        <div
            v-if="isAuditionType"
            class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-[11px] font-bold text-slate-700 uppercase tracking-wide">
                    Progres Tahap Seleksi
                </span>
                <span class="text-xs font-bold text-slate-900">
                    Status Saat Ini: {{ p.stage_status_label }}
                </span>
            </div>

            <ol class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                <li
                    v-for="(step, idx) in stageSteps"
                    :key="step.key"
                    class="flex items-start gap-2.5 p-2.5 rounded-lg border text-xs"
                    :class="stepBadgeClasses(step.state)"
                >
                    <span
                        class="w-5 h-5 rounded-md font-black text-[11px] flex items-center justify-center shrink-0 mt-0.5 tabular-data"
                        :class="stepNumberClasses(step.state)"
                    >
                        {{ idx + 1 }}
                    </span>
                    <div class="min-w-0">
                        <span class="block font-bold truncate">{{ step.label }}</span>
                        <span class="block text-[11px] opacity-85 truncate">{{ step.sub }}</span>
                    </div>
                </li>
            </ol>
        </div>

        <!-- Screening Rejection Notice -->
        <div
            v-if="p.screening_status === 'rejected' && p.screening_notes"
            class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-950"
        >
            <strong>Catatan Perbaikan Berkas:</strong> {{ p.screening_notes }}
        </div>

        <!-- Action Bar (Compact proportionate buttons) -->
        <div class="pt-3 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="p.can_download_bib"
                    type="button"
                    :aria-label="`Lihat Digital Pass (E-Tiket) ${p.full_name}`"
                    title="Lihat Digital Pass (E-Tiket)"
                    class="h-8 sm:h-9 inline-flex items-center justify-center gap-1.5 px-2.5 sm:px-3.5 rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors whitespace-nowrap"
                    @click="emit('preview-ticket', p)"
                >
                    <svg class="w-4 h-4 text-slate-700 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>Lihat E-Tiket</span>
                </button>
                <a
                    v-if="p.can_download_bib"
                    :href="route().has('audition.bib.download') ? route('audition.bib.download', { participant: p.id }) : '#'"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="h-8 sm:h-9 inline-flex items-center justify-center px-2.5 sm:px-3.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition-colors whitespace-nowrap"
                >
                    Unduh Digital Pass (E-Tiket)
                </a>
                <a
                    v-if="p.can_download_certificate"
                    :href="route().has('audition.certificate.download') ? route('audition.certificate.download', { participant: p.id }) : '#'"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="h-8 sm:h-9 inline-flex items-center justify-center px-2.5 sm:px-3.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition-colors whitespace-nowrap"
                >
                    Unduh Piagam / Sertifikat
                </a>
                <span v-if="!p.can_download_bib" class="text-xs text-slate-600">
                    <template v-if="p.event?.is_completed || p.event?.status === 'completed'">
                        Kegiatan telah selesai. Digital Pass (E-Tiket) sudah berakhir masa berlakunya; silakan unduh Piagam / Sertifikat atau Invoice jika tersedia.
                    </template>
                    <template v-else>
                        Digital Pass aktif setelah pembayaran lunas dan berkas disahkan panitia.
                    </template>
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Link
                    v-if="!p.is_paid && p.latest_payment"
                    :href="route('manager.payments', { participant_id: p.id })"
                    class="h-8 sm:h-9 inline-flex items-center justify-center px-2.5 sm:px-3.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition-colors whitespace-nowrap"
                >
                    Bayar Tagihan
                </Link>
                <Link
                    v-else-if="p.latest_payment"
                    :href="route('manager.payments', { participant_id: p.id })"
                    class="h-8 sm:h-9 inline-flex items-center justify-center px-2.5 sm:px-3.5 rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors whitespace-nowrap"
                >
                    Lihat Invoice
                </Link>
                <a
                    v-if="p.latest_payment?.invoice_url"
                    :href="p.latest_payment.invoice_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="h-8 sm:h-9 inline-flex items-center justify-center px-2.5 sm:px-3.5 rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors whitespace-nowrap"
                >
                    Unduh PDF
                </a>
            </div>
        </div>
        </div>
    </article>
</template>

