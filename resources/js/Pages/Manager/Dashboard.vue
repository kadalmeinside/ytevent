<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link, Head, router, usePage } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DocumentViewerModal from '@/Components/DocumentViewerModal.vue';
import AuditionParticipantCard from '@/Components/Manager/AuditionParticipantCard.vue';
import { useFormatters } from '@/Composables/useFormatters';

const props = defineProps({
    team: {
        type: Object,
        default: null,
    },
    teamsList: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: null,
    },
    auditionParticipants: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const { formatDateTime } = useFormatters();

const totalRegistrations = computed(() => props.teamsList.length + props.auditionParticipants.length);

const getUrlParams = () => {
    if (typeof window === 'undefined') {
        return new URLSearchParams();
    }
    return new URLSearchParams(window.location.search);
};

const getInitialView = () => {
    const params = getUrlParams();
    if (params.get('view') === 'team' && props.team) {
        return 'team';
    }
    if (params.get('view') === 'audition' && props.auditionParticipants.length > 0) {
        return 'audition';
    }
    return 'hub';
};

const getInitialParticipantId = () => {
    const params = getUrlParams();
    const requested = Number(params.get('participant_id') || 0);
    if (requested > 0 && props.auditionParticipants.some((p) => p.id === requested)) {
        return requested;
    }
    return props.auditionParticipants?.[0]?.id ?? null;
};

const activeView = ref(getInitialView());
const selectedParticipantId = ref(getInitialParticipantId());

watch(
    () => page.url,
    () => {
        const params = getUrlParams();
        const requestedView = params.get('view');
        const requestedParticipant = Number(params.get('participant_id') || 0);

        if (requestedView === 'team' && props.team) {
            activeView.value = 'team';
        } else if (requestedView === 'audition' && props.auditionParticipants.length > 0) {
            activeView.value = 'audition';
            if (requestedParticipant > 0 && props.auditionParticipants.some((p) => p.id === requestedParticipant)) {
                selectedParticipantId.value = requestedParticipant;
            }
        } else if (!requestedView) {
            activeView.value = 'hub';
        }
    }
);

const selectedParticipant = computed(() =>
    props.auditionParticipants.find((p) => p.id === selectedParticipantId.value)
    ?? props.auditionParticipants[0]
    ?? null
);

const isNavigating = ref(false);
let removeStartListener = null;
let removeFinishListener = null;

const ticketModal = ref({
    isOpen: false,
    title: '',
    subtitle: '',
    fileUrl: '',
    documentNumber: '',
});

const openTicketPreview = (participant) => {
    if (!participant?.can_download_bib || !route().has('audition.bib.download')) return;
    ticketModal.value = {
        isOpen: true,
        title: `Digital Pass (E-Tiket) - ${participant.full_name}`,
        subtitle: participant.event?.name || '',
        fileUrl: route('audition.bib.download', { participant: participant.id, inline: 1 }),
        documentNumber: participant.registration_number || '',
    };
};

const closeTicketPreview = () => {
    ticketModal.value.isOpen = false;
};

onMounted(() => {
    removeStartListener = router.on('start', () => {
        isNavigating.value = true;
    });
    removeFinishListener = router.on('finish', () => {
        isNavigating.value = false;
    });
});

onUnmounted(() => {
    if (typeof removeStartListener === 'function') removeStartListener();
    if (typeof removeFinishListener === 'function') removeFinishListener();
});

const activeTeams = computed(() => props.teamsList.filter((t) => !t.is_event_completed));
const pastTeams = computed(() => props.teamsList.filter((t) => Boolean(t.is_event_completed)));
const activeAuditions = computed(() => props.auditionParticipants.filter((p) => !p.event?.is_completed));
const pastAuditions = computed(() => props.auditionParticipants.filter((p) => Boolean(p.event?.is_completed)));

const urgentItems = computed(() => {
    const items = [];

    activeTeams.value.forEach((t) => {
        if (!t.is_paid || t.status === 'pending_payment') {
            items.push({
                key: `team-pay-${t.id}`,
                label: `Tagihan tim ${t.name} (${t.event_name}) menunggu pelunasan.`,
                actionLabel: 'Bayar Tagihan',
                type: 'link',
                href: route('manager.payments', { team_id: t.id, team_context: 1 }),
            });
        } else if (t.players_rejected_count > 0) {
            items.push({
                key: `team-rej-${t.id}`,
                label: `${t.players_rejected_count} berkas pemain pada tim ${t.name} perlu perbaikan.`,
                actionLabel: 'Perbaiki Berkas',
                type: 'team-switch',
                teamId: t.id,
            });
        }
    });

    activeAuditions.value.forEach((p) => {
        if (!p.is_paid) {
            items.push({
                key: `aud-pay-${p.id}`,
                label: `Tagihan pendaftaran ${p.full_name} (${p.event?.name}) belum lunas.`,
                actionLabel: 'Bayar Tagihan',
                type: 'link',
                href: route('manager.payments', { participant_id: p.id }),
            });
        } else if (p.screening_status === 'rejected') {
            items.push({
                key: `aud-rej-${p.id}`,
                label: `Berkas peserta ${p.full_name} (${p.event?.name}) memerlukan perbaikan.`,
                actionLabel: 'Lihat Catatan',
                type: 'audition-detail',
                participantId: p.id,
            });
        }
    });

    return items;
});

const openHub = () => {
    activeView.value = 'hub';
};

const openTeamWorkspace = (teamId) => {
    if (props.team?.id === teamId) {
        activeView.value = 'team';
        return;
    }
    router.post(
        route('manager.switch-team', { team: teamId }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                activeView.value = 'team';
            },
        }
    );
};

const openParticipantDetail = (participantId) => {
    selectedParticipantId.value = participantId;
    activeView.value = 'audition';
};

const teamStatusSummary = (t) => {
    if (!t?.is_paid || t?.status === 'pending_payment') {
        return { text: 'Menunggu Pelunasan', cls: 'bg-rose-100 text-rose-800 border-rose-200' };
    }
    if ((t.players_rejected_count || 0) > 0) {
        return { text: `${t.players_rejected_count} Berkas Perlu Perbaikan`, cls: 'bg-rose-100 text-rose-800 border-rose-200' };
    }
    if (t.is_roster_locked) {
        return { text: 'Skuad Terkunci & Siap', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    }
    return {
        text: `${t.players_passed_count || 0}/${t.players_count || 0} Pemain Lolos`,
        cls: 'bg-blue-100 text-blue-800 border-blue-200',
    };
};

const auditionStatusSummary = (p) => {
    if (!p?.is_paid) {
        return { text: 'Menunggu Pelunasan', cls: 'bg-rose-100 text-rose-800 border-rose-200' };
    }
    if (p.screening_status === 'rejected') {
        return { text: 'Berkas Perlu Perbaikan', cls: 'bg-rose-100 text-rose-800 border-rose-200' };
    }
    if (p.stage_status === 'final_selected') {
        return { text: 'Lolos Final / Terpilih', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    }
    if (p.stage_status === 'day_2_passed') {
        return { text: 'Lolos Tahap 2', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    }
    if (p.stage_status === 'day_1_passed') {
        return { text: 'Lolos Tahap 1', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    }
    if (p.stage_status === 'day_1_eliminated' || p.stage_status === 'day_2_eliminated') {
        return { text: 'Selesai di Seleksi', cls: 'bg-slate-200 text-slate-800 border-slate-300' };
    }
    if (p.screening_status === 'passed') {
        return { text: 'Screening Lolos & Siap Hadir', cls: 'bg-blue-100 text-blue-800 border-blue-200' };
    }
    return { text: 'Menunggu Screening Berkas', cls: 'bg-amber-100 text-amber-900 border-amber-200' };
};

const calculatePercent = (current, total) => {
    if (!total || total <= 0) return 0;
    return Math.min(100, Math.round((current / total) * 100));
};

const bannerCtaHref = computed(() => {
    const ctaRoute = props.team?.workflow?.banner?.cta_route;
    const teamPaymentsUrl = route('manager.payments', props.team?.id ? { team_id: props.team.id, team_context: 1 } : {});
    if (!ctaRoute || ctaRoute === 'manager.payments') return teamPaymentsUrl;
    return route().has(ctaRoute) ? route(ctaRoute) : teamPaymentsUrl;
});

const readinessStages = computed(() => {
    if (!props.team) return [];
    const wf = props.team.workflow || {};
    const counts = wf.counts || {};
    const isPaid = Boolean(wf.is_paid);

    return [
        {
            step: 1,
            title: 'Pembayaran Pendaftaran',
            statusLabel: isPaid ? 'Lunas' : (props.team.status === 'expired' ? 'Kedaluwarsa' : 'Menunggu Bayar'),
            done: isPaid,
            active: !isPaid && wf.current_step === 1,
            warning: props.team.status === 'expired' || props.summary?.latest_payment?.status === 'rejected',
            detail: isPaid
                ? `Invoice ${props.summary?.latest_payment?.invoice_number || ''} telah lunas. Menu skuad dan ofisial terbuka penuh.`
                : 'Selesaikan tagihan pendaftaran agar slot tim terkunci dan pengisian daftar pemain terbuka.',
            actionLabel: isPaid ? 'Lihat Rincian Pembayaran' : 'Bayar Tagihan Sekarang',
            href: route('manager.payments', props.team?.id ? { team_id: props.team.id, team_context: 1 } : { team_context: 1 }),
            locked: false,
        },
        {
            step: 2,
            title: `Daftar Pemain & Min. ${counts.min_gk_required || 2} Penjaga Gawang`,
            statusLabel: (wf.is_all_players_submitted_or_passed && wf.is_gk_met)
                ? 'Lengkap & Diajukan'
                : counts.players_rejected > 0
                    ? `${counts.players_rejected} Perlu Revisi`
                    : `${counts.players_total || 0}/${counts.min_players_required || 11} Pemain`,
            done: Boolean(wf.is_all_players_submitted_or_passed && wf.is_gk_met),
            active: isPaid && wf.current_step === 2,
            warning: (counts.players_rejected || 0) > 0,
            detail: `Minimal ${counts.min_players_required || 11} pemain dan ${counts.min_gk_required || 2} GK (tercatat ${counts.gk_total || 0} GK) dengan berkas lengkap.`,
            actionLabel: 'Kelola Daftar Pemain',
            href: route('manager.players'),
            locked: !isPaid,
        },
        {
            step: 3,
            title: 'Pelatih Kepala & Ofisial Tim',
            statusLabel: wf.is_officials_met ? `${counts.officials_total || 1} Terdaftar` : 'Minimal 1 Ofisial',
            done: Boolean(wf.is_officials_met),
            active: isPaid && wf.current_step === 3,
            warning: false,
            detail: `Minimal 1 Pelatih Kepala atau Ofisial resmi mendampingi tim (saat ini ${counts.officials_total || 0} terdaftar).`,
            actionLabel: 'Kelola Ofisial Tim',
            href: route('manager.officials'),
            locked: !isPaid,
        },
        {
            step: 4,
            title: 'Verifikasi Screening Panitia',
            statusLabel: (wf.is_all_players_passed && wf.is_gk_passed_met)
                ? 'Semua Lolos'
                : `${counts.players_passed || 0}/${counts.players_total || 0} Sah`,
            done: Boolean(wf.is_all_players_passed && wf.is_gk_passed_met),
            active: isPaid && wf.current_step === 4 && !wf.is_ready_to_lock,
            warning: false,
            detail: 'Panitia memeriksa keabsahan berkas setiap pemain yang sudah Anda ajukan.',
            actionLabel: 'Pantau Status Screening',
            href: route('manager.players'),
            locked: !isPaid,
        },
        {
            step: 5,
            title: 'Kunci Skuad Final & ID Card Pertandingan',
            statusLabel: wf.is_roster_locked
                ? 'Final'
                : wf.is_ready_to_lock
                    ? 'Siap Dikunci'
                    : 'Menunggu Tahap 1-4',
            done: Boolean(wf.is_roster_locked),
            active: Boolean(wf.is_ready_to_lock) && !wf.is_roster_locked,
            warning: false,
            detail: 'Kunci daftar skuad final setelah seluruh pemain sah agar Album Roster dan ID Card QR dapat diunduh.',
            actionLabel: wf.is_roster_locked ? 'Unduh Roster & ID Card' : 'Buka Halaman Akreditasi',
            href: route('manager.accreditation.index'),
            locked: !isPaid,
        },
    ];
});
</script>

<template>
    <ManagerLayout
        :team="team"
        :teams-list="teamsList"
        :page-title="activeView === 'team' ? 'Workspace Tim' : activeView === 'audition' ? 'Detail Seleksi Peserta' : 'Kepesertaan Saya'"
        :hide-layout-h1="true"
        :hide-workflow-banner="true"
        :hide-team-context="activeView !== 'team'"
    >
        <Head :title="activeView === 'team' ? 'Workspace Tim' : activeView === 'audition' ? 'Detail Seleksi Peserta' : 'Kepesertaan Saya'" />

        <div class="space-y-6">
            <!-- VIEW 1: HUB KEPESERTAAN SAYA -->
            <div v-if="activeView === 'hub'" class="space-y-6">
                <!-- Clean Account Header -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-4 border-b border-slate-200">
                    <div class="min-w-0 space-y-1">
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                            Kepesertaan Saya
                        </h1>
                        <p v-if="totalRegistrations > 0" class="text-xs sm:text-sm text-slate-600">
                            {{ activeTeams.length + activeAuditions.length }} kepesertaan aktif dan {{ pastTeams.length + pastAuditions.length }} riwayat selesai dalam akun Anda.
                        </p>
                        <p v-else class="text-xs sm:text-sm text-slate-600">
                            Kelola pendaftaran kompetisi tim maupun kepesertaan perorangan Anda dalam satu akun.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <span v-if="isNavigating" role="status" class="text-xs font-medium text-slate-600">
                            Memuat halaman...
                        </span>
                        <Link
                            :href="route('manager.payments')"
                            class="h-8 sm:h-9 inline-flex items-center justify-center px-3 sm:px-3.5 rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 focus-visible:ring-offset-2 whitespace-nowrap"
                        >
                            Riwayat Pembayaran
                        </Link>
                        <Link
                            :href="route('home')"
                            class="h-8 sm:h-9 inline-flex items-center justify-center px-3 sm:px-3.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 focus-visible:ring-offset-2 whitespace-nowrap"
                        >
                            Daftar Event Baru
                        </Link>
                    </div>
                </div>

                <!-- Empty State (Zero Registrations) -->
                <section
                    v-if="totalRegistrations === 0"
                    class="bg-white border border-slate-200 rounded-2xl p-8 max-w-xl mx-auto my-6 text-center space-y-3"
                >
                    <h2 class="text-base sm:text-lg font-bold text-slate-900">Belum Ada Kepesertaan Terdaftar</h2>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Anda belum mendaftarkan tim turnamen ataupun peserta perorangan. Jelajahi katalog event untuk memulai pendaftaran baru.
                    </p>
                    <div class="pt-2">
                        <Link
                            :href="route('home')"
                            class="inline-flex items-center justify-center h-9 sm:h-10 px-4 rounded-lg sm:rounded-xl bg-orange-700 hover:bg-orange-800 text-white text-xs sm:text-sm font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 focus-visible:ring-offset-2"
                        >
                            Jelajahi Katalog Event
                        </Link>
                    </div>
                </section>

                <template v-else>
                    <!-- Urgent Action Bar (Rendered ONLY when critical actions exist) -->
                    <section
                        v-if="urgentItems.length > 0"
                        aria-label="Tindakan yang perlu segera diselesaikan"
                        class="bg-rose-50 border border-rose-300 rounded-2xl p-4 space-y-2.5"
                    >
                        <h2 class="text-sm font-black text-rose-950">
                            Perlu Tindakan Segera ({{ urgentItems.length }})
                        </h2>
                        <ul class="divide-y divide-rose-200">
                            <li
                                v-for="item in urgentItems"
                                :key="item.key"
                                class="py-2 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs"
                            >
                                <span class="font-medium text-rose-950">{{ item.label }}</span>
                                <Link
                                    v-if="item.type === 'link'"
                                    :href="item.href"
                                    class="h-8 sm:h-9 inline-flex items-center justify-center px-3 rounded-lg bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-800 focus-visible:ring-offset-2 self-start sm:self-auto shrink-0 whitespace-nowrap"
                                >
                                    {{ item.actionLabel }}
                                </Link>
                                <button
                                    v-else-if="item.type === 'team-switch'"
                                    type="button"
                                    class="h-8 sm:h-9 px-3 rounded-lg bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-800 focus-visible:ring-offset-2 self-start sm:self-auto shrink-0 whitespace-nowrap"
                                    @click="openTeamWorkspace(item.teamId)"
                                >
                                    {{ item.actionLabel }}
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="h-8 sm:h-9 px-3 rounded-lg bg-rose-700 hover:bg-rose-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-800 focus-visible:ring-offset-2 self-start sm:self-auto shrink-0 whitespace-nowrap"
                                    @click="openParticipantDetail(item.participantId)"
                                >
                                    {{ item.actionLabel }}
                                </button>
                            </li>
                        </ul>
                    </section>

                    <!-- Seksi 1: Kepesertaan Aktif -->
                    <section aria-labelledby="active-events-heading" class="space-y-3">
                        <h2 id="active-events-heading" class="text-base sm:text-lg font-black text-slate-900">
                            Kepesertaan Aktif ({{ activeTeams.length + activeAuditions.length }})
                        </h2>

                        <div
                            v-if="activeTeams.length === 0 && activeAuditions.length === 0"
                            class="bg-white border border-slate-200 rounded-2xl p-6 text-center space-y-1.5"
                        >
                            <p class="text-sm font-bold text-slate-900">Tidak Ada Kepesertaan Aktif Saat Ini</p>
                            <p class="text-xs text-slate-600">
                                Semua pendaftaran Anda sebelumnya telah selesai diselenggarakan. Pilih event terbuka untuk mendaftar musim baru.
                            </p>
                        </div>

                        <div
                            v-else
                            :class="(activeTeams.length + activeAuditions.length) > 1 ? 'grid grid-cols-1 lg:grid-cols-2 gap-5' : 'space-y-4'"
                        >
                            <!-- Active Teams (Event Banner + Protective Gradient Scrim Cards) -->
                            <article
                                v-for="item in activeTeams"
                                :key="`active-team-${item.id}`"
                                class="group bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between"
                            >
                                <!-- Top Event Banner Hero Zone -->
                                <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-orange-950 border-l-4 border-orange-500 p-5 text-white min-h-[144px] flex flex-col justify-between gap-3">
                                    <img
                                        v-if="item.event_banner_url"
                                        :src="item.event_banner_url"
                                        alt=""
                                        aria-hidden="true"
                                        class="absolute inset-y-0 right-0 w-full sm:w-2/3 h-full object-cover object-center opacity-70 group-hover:scale-[1.02] transition-transform duration-500 pointer-events-none select-none"
                                    />
                                    <div
                                        class="absolute inset-0 bg-gradient-to-b from-slate-950/90 via-slate-950/95 to-slate-950 sm:bg-gradient-to-r sm:from-slate-950 sm:via-slate-950/92 sm:via-60% sm:to-orange-950/35 pointer-events-none"
                                        aria-hidden="true"
                                    ></div>

                                    <div class="relative z-10 flex flex-wrap items-center justify-between gap-2">
                                        <span class="px-2.5 py-0.5 rounded-md bg-orange-500/20 text-orange-200 border border-orange-400/35 text-[11px] font-bold uppercase tracking-wide">
                                            Tim Turnamen
                                        </span>
                                        <span
                                            class="px-2.5 py-0.5 rounded-full text-[11px] font-black"
                                            :class="item.is_paid && item.status !== 'pending_payment'
                                                ? 'bg-emerald-500 text-slate-950'
                                                : 'bg-amber-400 text-slate-950'"
                                        >
                                            {{ item.is_paid && item.status !== 'pending_payment' ? 'Pembayaran Lunas' : 'Menunggu Pembayaran' }}
                                        </span>
                                    </div>

                                    <div class="relative z-10 flex items-center gap-3.5 min-w-0">
                                        <img
                                            v-if="item.logo_url"
                                            :src="item.logo_url"
                                            :alt="`Logo ${item.name}`"
                                            width="48"
                                            height="48"
                                            class="w-12 h-12 rounded-xl object-cover border border-white/25 bg-slate-900 shrink-0"
                                        />
                                        <div
                                            v-else
                                            class="w-12 h-12 rounded-xl bg-orange-600/30 border border-orange-400/40 text-orange-100 font-black text-sm flex items-center justify-center shrink-0"
                                            aria-hidden="true"
                                        >
                                            {{ item.name?.substring(0, 2).toUpperCase() || 'TM' }}
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="text-base sm:text-lg font-black text-white leading-snug truncate">
                                                {{ item.name }}
                                            </h3>
                                            <p class="text-xs text-slate-200 truncate">
                                                <strong class="font-semibold text-white">{{ item.event_name }}</strong>
                                                <span v-if="item.age_category"> ({{ item.age_category }})</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom Summary & Targeted CTA Zone -->
                                <div class="p-4 sm:p-5 bg-white space-y-4 flex-1 flex flex-col justify-between">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                            <span class="block text-[11px] font-bold text-slate-600">Kesiapan Skuad</span>
                                            <span class="mt-1 block font-bold text-slate-900">
                                                {{ teamStatusSummary(item).text }}
                                            </span>
                                            <span class="block text-[11px] text-slate-600 mt-0.5 tabular-data">
                                                {{ item.players_count || 0 }} Pemain • {{ item.officials_count || 0 }} Ofisial
                                            </span>
                                        </div>
                                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                            <span class="block text-[11px] font-bold text-slate-600">Jadwal &amp; Kota</span>
                                            <span class="mt-1 block font-bold text-slate-900 tabular-data">
                                                {{ item.match_start_date || 'Segera Diumumkan' }}
                                            </span>
                                            <span class="block text-[11px] text-slate-600 mt-0.5 truncate">
                                                {{ item.city || 'Lihat Workspace Tim' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 pt-1">
                                        <Link
                                            v-if="!item.is_paid || item.status === 'pending_payment'"
                                            :href="route('manager.payments', { team_id: item.id, team_context: 1 })"
                                            class="h-8 sm:h-9 inline-flex items-center justify-center px-3 sm:px-3.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 focus-visible:ring-offset-2 whitespace-nowrap"
                                        >
                                            Bayar Pendaftaran
                                        </Link>
                                        <button
                                            type="button"
                                            class="h-8 sm:h-9 inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 whitespace-nowrap"
                                            @click="openTeamWorkspace(item.id)"
                                        >
                                            <span>Masuk Workspace Tim</span>
                                            <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </article>

                            <!-- Active Auditions (Event Banner + Protective Gradient Scrim Cards) -->
                            <article
                                v-for="p in activeAuditions"
                                :key="`active-aud-${p.id}`"
                                class="group bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col justify-between"
                            >
                                <!-- Top Event Banner Hero Zone -->
                                <div class="relative overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-red-950 border-l-4 border-red-500 p-5 text-white min-h-[144px] flex flex-col justify-between gap-3">
                                    <img
                                        v-if="p.event?.banner_url"
                                        :src="p.event.banner_url"
                                        alt=""
                                        aria-hidden="true"
                                        class="absolute inset-y-0 right-0 w-full sm:w-2/3 h-full object-cover object-center opacity-70 group-hover:scale-[1.02] transition-transform duration-500 pointer-events-none select-none"
                                    />
                                    <div
                                        class="absolute inset-0 bg-gradient-to-b from-slate-950/90 via-slate-950/95 to-slate-950 sm:bg-gradient-to-r sm:from-slate-950 sm:via-slate-950/92 sm:via-60% sm:to-red-950/35 pointer-events-none"
                                        aria-hidden="true"
                                    ></div>

                                    <div class="relative z-10 flex flex-wrap items-center justify-between gap-2">
                                        <span class="px-2.5 py-0.5 rounded-md bg-red-500/20 text-red-200 border border-red-400/35 text-[11px] font-bold uppercase tracking-wide">
                                            Peserta Perorangan
                                        </span>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span
                                                v-if="p.bib_number"
                                                class="px-2 py-0.5 rounded-md bg-white text-slate-950 text-[11px] font-black tabular-data"
                                            >
                                                Bib #{{ p.bib_number }}
                                            </span>
                                            <span
                                                class="px-2.5 py-0.5 rounded-full text-[11px] font-black"
                                                :class="p.is_paid
                                                    ? 'bg-emerald-500 text-slate-950'
                                                    : 'bg-amber-400 text-slate-950'"
                                            >
                                                {{ p.is_paid ? 'Pembayaran Lunas' : 'Menunggu Pembayaran' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="relative z-10 flex items-center gap-3.5 min-w-0">
                                        <img
                                            v-if="p.photo_url"
                                            :src="p.photo_url"
                                            :alt="`Foto ${p.full_name}`"
                                            width="48"
                                            height="48"
                                            class="w-12 h-12 rounded-xl object-cover border border-white/25 bg-slate-900 shrink-0"
                                        />
                                        <div
                                            v-else
                                            class="w-12 h-12 rounded-xl bg-red-600/30 border border-red-400/40 text-red-100 font-black text-sm flex items-center justify-center shrink-0"
                                            aria-hidden="true"
                                        >
                                            {{ p.full_name?.substring(0, 2).toUpperCase() || 'PS' }}
                                        </div>
                                        <div class="min-w-0">
                                            <h3 class="text-base sm:text-lg font-black text-white leading-snug truncate">
                                                {{ p.full_name }}
                                            </h3>
                                            <p class="text-xs text-slate-200 truncate">
                                                <strong class="font-semibold text-white">{{ p.event?.name }}</strong>
                                                <span v-if="p.event?.age_category"> ({{ p.event.age_category }})</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom Summary & Targeted CTA Zone -->
                                <div class="p-4 sm:p-5 bg-white space-y-4 flex-1 flex flex-col justify-between">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                            <span class="block text-[11px] font-bold text-slate-600">Status Seleksi</span>
                                            <span class="mt-1 block font-bold text-slate-900">
                                                {{ auditionStatusSummary(p).text }}
                                            </span>
                                            <span class="block text-[11px] text-slate-600 font-mono mt-0.5 tabular-data">
                                                {{ p.registration_number }}
                                            </span>
                                        </div>
                                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                                            <span class="block text-[11px] font-bold text-slate-600">Jadwal &amp; Lokasi</span>
                                            <span class="mt-1 block font-bold text-slate-900 tabular-data">
                                                {{ p.event?.match_start_date || 'Lihat Jadwal Seleksi' }}
                                            </span>
                                            <span class="block text-[11px] text-slate-600 mt-0.5 truncate">
                                                {{ p.event?.city || p.preferred_position || 'Peserta Seleksi' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 pt-1">
                                        <Link
                                            v-if="!p.is_paid"
                                            :href="route('manager.payments', { participant_id: p.id })"
                                            class="h-8 sm:h-9 inline-flex items-center justify-center px-3 sm:px-3.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 focus-visible:ring-offset-2 whitespace-nowrap"
                                        >
                                            Bayar Tagihan
                                        </Link>
                                        <button
                                            v-if="p.can_download_bib && route().has('audition.bib.download')"
                                            type="button"
                                            class="h-8 sm:h-9 inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 whitespace-nowrap"
                                            @click="openTicketPreview(p)"
                                        >
                                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Lihat E-Tiket</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="h-8 sm:h-9 px-3 sm:px-3.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-900 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 focus-visible:ring-offset-2 whitespace-nowrap"
                                            @click="openParticipantDetail(p.id)"
                                        >
                                            Detail Seleksi
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <!-- Seksi 2: Riwayat Event Selesai (Slim 1-Row Archive List) -->
                    <section
                        v-if="pastTeams.length > 0 || pastAuditions.length > 0"
                        aria-labelledby="past-events-heading"
                        class="pt-4 border-t border-slate-200 space-y-3"
                    >
                        <h2 id="past-events-heading" class="text-base font-bold text-slate-900">
                            Riwayat Event Selesai ({{ pastTeams.length + pastAuditions.length }})
                        </h2>

                        <div class="bg-white border border-slate-200 rounded-2xl divide-y divide-slate-200">
                            <!-- Past Teams -->
                            <div
                                v-for="item in pastTeams"
                                :key="`past-team-${item.id}`"
                                class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="relative w-14 h-12 rounded-xl overflow-hidden bg-gradient-to-br from-slate-900 to-slate-800 border border-slate-200 shrink-0 flex items-center justify-center">
                                        <img
                                            v-if="item.event_banner_url"
                                            :src="item.event_banner_url"
                                            alt=""
                                            aria-hidden="true"
                                            class="absolute inset-0 w-full h-full object-cover opacity-80"
                                        />
                                        <div class="absolute inset-0 bg-slate-950/35 pointer-events-none" aria-hidden="true"></div>
                                        <span class="relative z-10 text-[11px] font-black text-white uppercase tracking-wide">TIM</span>
                                    </div>
                                    <div class="min-w-0 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-bold text-slate-900">{{ item.name }}</span>
                                            <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-300 text-slate-700 text-[11px] font-semibold">
                                                Tim
                                            </span>
                                            <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-300 text-slate-800 text-[11px] font-bold">
                                                Event Selesai
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-700 truncate">
                                            {{ item.event_name }}
                                            <span v-if="item.age_category"> ({{ item.age_category }})</span>
                                            • {{ teamStatusSummary(item).text }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        class="h-8 px-2.5 sm:px-3 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-900 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 focus-visible:ring-offset-2 whitespace-nowrap"
                                        @click="openTeamWorkspace(item.id)"
                                    >
                                        Lihat Arsip
                                    </button>
                                </div>
                            </div>

                            <!-- Past Auditions -->
                            <div
                                v-for="p in pastAuditions"
                                :key="`past-aud-${p.id}`"
                                class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <div class="relative w-14 h-12 rounded-xl overflow-hidden bg-gradient-to-br from-slate-900 to-red-950 border border-slate-200 shrink-0 flex items-center justify-center">
                                        <img
                                            v-if="p.event?.banner_url"
                                            :src="p.event.banner_url"
                                            alt=""
                                            aria-hidden="true"
                                            class="absolute inset-0 w-full h-full object-cover opacity-80"
                                        />
                                        <div class="absolute inset-0 bg-slate-950/35 pointer-events-none" aria-hidden="true"></div>
                                        <span class="relative z-10 text-[11px] font-black text-white uppercase tracking-wide">AUD</span>
                                    </div>
                                    <div class="min-w-0 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-bold text-slate-900">{{ p.full_name }}</span>
                                            <span class="px-2 py-0.5 rounded bg-orange-50 border border-orange-200 text-orange-900 text-[11px] font-semibold">
                                                Peserta
                                            </span>
                                            <span
                                                class="px-2 py-0.5 rounded border text-[11px] font-bold"
                                                :class="auditionStatusSummary(p).cls"
                                            >
                                                {{ auditionStatusSummary(p).text }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-700 truncate">
                                            {{ p.event?.name }}
                                            <span v-if="p.registration_number"> • No. {{ p.registration_number }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                    <a
                                        v-if="p.can_download_certificate && route().has('audition.certificate.download')"
                                        :href="route('audition.certificate.download', { participant: p.id })"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="h-8 inline-flex items-center justify-center px-2.5 sm:px-3 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 whitespace-nowrap"
                                    >
                                        Unduh Piagam
                                    </a>
                                    <button
                                        type="button"
                                        class="h-8 px-2.5 sm:px-3 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-900 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 focus-visible:ring-offset-2 whitespace-nowrap"
                                        @click="openParticipantDetail(p.id)"
                                    >
                                        Lihat Arsip
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>
            </div>

            <!-- VIEW 2: WORKSPACE TIM -->
            <div v-else-if="activeView === 'team'" class="space-y-6">
                <!-- Drill-down Back Bar -->
                <div class="flex flex-wrap items-center justify-between gap-2.5">
                    <button
                        type="button"
                        class="h-8 sm:h-9 inline-flex items-center gap-1.5 px-2.5 sm:px-3.5 rounded-lg bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                        @click="openHub"
                    >
                        &larr; Kembali ke Kepesertaan Saya
                    </button>

                    <Link
                        v-if="team"
                        :href="route('manager.payments', { team_id: team.id, team_context: 1 })"
                        class="h-8 sm:h-9 inline-flex items-center justify-center px-3 sm:px-3.5 rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                    >
                        Transaksi &amp; Pembayaran
                    </Link>
                </div>

                <!-- Event Banner Identity Hero Card (Rendered when team exists) -->
                <div
                    v-if="team"
                    class="relative overflow-hidden rounded-2xl border border-slate-800 bg-gradient-to-br from-slate-950 via-slate-900 to-orange-950 border-l-4 border-orange-500 p-5 sm:p-6 text-white shadow-sm"
                >
                    <img
                        v-if="team.event?.banner_url"
                        :src="team.event.banner_url"
                        alt=""
                        aria-hidden="true"
                        class="absolute inset-y-0 right-0 w-full sm:w-2/3 h-full object-cover object-center opacity-65 pointer-events-none select-none"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-b from-slate-950/90 via-slate-950/95 to-slate-950 sm:bg-gradient-to-r sm:from-slate-950 sm:via-slate-950/92 sm:via-60% sm:to-orange-950/35 pointer-events-none"
                        aria-hidden="true"
                    ></div>

                    <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5">
                        <div class="flex items-center gap-4 min-w-0">
                            <img
                                v-if="team.logo_url"
                                :src="team.logo_url"
                                :alt="`Logo ${team.name}`"
                                width="64"
                                height="64"
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl object-cover border border-white/25 bg-slate-900 shrink-0"
                            />
                            <div
                                v-else
                                class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-orange-600/30 border border-orange-400/40 text-orange-100 font-black text-base flex items-center justify-center shrink-0"
                                aria-hidden="true"
                            >
                                {{ team.name?.substring(0, 2).toUpperCase() || 'TM' }}
                            </div>
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded-md bg-orange-500/20 text-orange-200 border border-orange-400/35 text-[11px] font-bold uppercase tracking-wide">
                                        Workspace Tim
                                    </span>
                                    <StatusBadge :status="team.status" />
                                </div>
                                <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight truncate">
                                    {{ team.name }}
                                </h1>
                                <p v-if="team.event" class="text-xs sm:text-sm text-slate-200">
                                    <strong class="font-semibold text-white">{{ team.event.name }}</strong>
                                    <span v-if="team.event.age_category"> ({{ team.event.age_category }})</span>
                                    <span v-if="team.event.format_label"> • {{ team.event.format_label }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <Link
                                :href="route(team.workflow?.is_roster_locked ? 'manager.accreditation.index' : !team.workflow?.is_paid ? 'manager.payments' : 'manager.players', !team.workflow?.is_paid ? { team_id: team.id, team_context: 1 } : {})"
                                class="h-8 sm:h-9 inline-flex items-center justify-center px-3 sm:px-4 rounded-lg bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 shadow-sm whitespace-nowrap"
                            >
                                {{ team.workflow?.is_roster_locked ? 'Buka Akreditasi & ID Card' : !team.workflow?.is_paid ? 'Bayar Pendaftaran' : 'Kelola Daftar Pemain' }}
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State (No Team Registered) -->
                <section
                    v-if="!team"
                    class="bg-white border border-slate-200 rounded-2xl p-8 max-w-xl mx-auto my-6 text-center space-y-3"
                >
                    <h2 class="text-base sm:text-lg font-bold text-slate-900">Belum Ada Tim Terdaftar</h2>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Akun Anda belum mendaftarkan tim ke turnamen atau liga. Pilih kompetisi yang sedang dibuka untuk memulai pendaftaran tim.
                    </p>
                    <div class="pt-2">
                        <Link
                            :href="route('home')"
                            class="inline-flex items-center justify-center h-9 sm:h-10 px-4 rounded-lg sm:rounded-xl bg-orange-700 hover:bg-orange-800 text-white text-xs sm:text-sm font-bold transition-colors cursor-pointer whitespace-nowrap"
                        >
                            Pilih Kompetisi di Katalog
                        </Link>
                    </div>
                </section>

                <template v-else>
                    <!-- Completed Event Archive Notice -->
                    <div
                        v-if="team.event?.is_completed"
                        class="p-4 rounded-2xl bg-slate-100 border border-slate-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                        <div class="space-y-0.5">
                            <span class="inline-block px-2 py-0.5 rounded bg-slate-200 text-slate-900 text-[11px] font-bold">
                                Mode Arsip Event Selesai
                            </span>
                            <p class="text-xs sm:text-sm text-slate-800">
                                Kompetisi <strong>{{ team.event.name }}</strong> telah berakhir. Anda sedang melihat arsip kesiapan dan dokumen akreditasi tim.
                            </p>
                        </div>
                    </div>

                    <!-- Concise Unpaid Billing Callout (Rendered ONLY when team is unpaid) -->
                    <div
                        v-if="!team.workflow?.is_paid"
                        class="p-4 rounded-2xl bg-amber-50 border border-amber-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                    >
                        <div class="space-y-0.5 text-xs">
                            <p class="font-bold text-amber-950 text-sm">
                                Pembayaran Pendaftaran Belum Selesai
                            </p>
                            <p class="text-amber-900">
                                <template v-if="summary?.latest_payment">
                                    Invoice <strong class="font-mono">{{ summary.latest_payment.invoice_number }}</strong> menunggu penyelesaian pembayaran.
                                </template>
                                <template v-else>
                                    Selesaikan pembayaran pendaftaran tim untuk membuka menu skuad dan ofisial.
                                </template>
                            </p>
                        </div>
                        <Link
                            :href="route('manager.payments', { team_id: team.id, team_context: 1 })"
                            class="h-8 sm:h-9 inline-flex items-center justify-center px-3 sm:px-3.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition-colors shrink-0 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                        >
                            Bayar Sekarang
                        </Link>
                    </div>

                    <!-- Focused Team Readiness Focal Card -->
                    <section
                        aria-labelledby="focal-action-heading"
                        class="bg-white border rounded-2xl p-5 sm:p-6 shadow-sm flex flex-col justify-between gap-5"
                        :class="team.status === 'expired' || (summary?.players_rejected || 0) > 0
                            ? 'border-rose-300'
                            : team.workflow?.is_roster_locked
                                ? 'border-emerald-300'
                                : 'border-slate-300'"
                    >
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs font-bold text-slate-600 tabular-data">
                                    Tahap {{ team.workflow?.current_step || 1 }} dari {{ team.workflow?.total_steps || 5 }}
                                </span>
                                <StatusBadge :status="team.status" />
                            </div>

                            <div class="space-y-1.5">
                                <h2 id="focal-action-heading" class="text-lg sm:text-xl font-black text-slate-900 leading-snug">
                                    {{ team.workflow?.banner?.title || 'Status Pendaftaran Tim' }}
                                </h2>
                                <p class="text-xs sm:text-sm text-slate-700 leading-relaxed">
                                    {{ team.workflow?.banner?.message || 'Pantau kelengkapan pembayaran, daftar pemain, ofisial, dan akreditasi tim Anda dari ringkasan di bawah.' }}
                                </p>
                            </div>

                            <!-- Payment or Slot Deadline Notice -->
                            <div
                                v-if="team.status === 'pending_payment' && (summary?.latest_payment?.expires_at || team.slot_expires_at)"
                                class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-950"
                            >
                                Batas pelunasan sebelum slot dilepas:
                                <strong class="font-bold tabular-data">{{ formatDateTime(summary?.latest_payment?.expires_at || team.slot_expires_at) }}</strong>
                            </div>
                            <div
                                v-else-if="team.status === 'expired'"
                                class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-950"
                            >
                                Batas waktu pembayaran telah berakhir. Gunakan menu Transaksi &amp; Pembayaran untuk mengamankan slot baru apabila kuota masih tersedia.
                            </div>
                            <div
                                v-else-if="summary?.latest_payment?.rejection_reason"
                                class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-950"
                            >
                                <strong>Catatan Penolakan Pembayaran:</strong> {{ summary.latest_payment.rejection_reason }}
                            </div>
                        </div>

                        <div class="pt-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3">
                            <div class="text-xs text-slate-600">
                                Asosiasi: <strong class="font-semibold text-slate-900">{{ team.origin_association || 'Belum diisi' }}</strong>
                            </div>
                            <Link
                                v-if="team.workflow?.banner?.cta_label"
                                :href="bannerCtaHref"
                                class="inline-flex items-center justify-center h-8 sm:h-9 px-3.5 sm:px-4 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition-colors whitespace-nowrap cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700"
                            >
                                {{ team.workflow.banner.cta_label }}
                            </Link>
                        </div>
                    </section>

                    <!-- BAND B (Asymmetric 7/5): Vertical Readiness Rail + Live Squad Sheet & Noticeboard -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                        <!-- Left 7 cols: Vertical 5-Stage Match-Sheet Checklist -->
                        <section
                            aria-labelledby="readiness-rail-heading"
                            class="lg:col-span-7 bg-white border border-slate-200 rounded-2xl p-5 sm:p-6"
                        >
                            <div class="pb-4 mb-4 border-b border-slate-200">
                                <h2 id="readiness-rail-heading" class="text-base font-bold text-slate-900">
                                    Alur 5 Tahap Kesiapan Tim
                                </h2>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    Penuhi setiap tahapan secara berurutan hingga ID Card pertandingan siap dicetak.
                                </p>
                            </div>

                            <ol class="divide-y divide-slate-200">
                                <li
                                    v-for="stage in readinessStages"
                                    :key="stage.step"
                                    class="py-4 first:pt-0 last:pb-0 flex flex-col sm:flex-row sm:items-start justify-between gap-3"
                                >
                                    <div class="flex items-start gap-3.5 min-w-0">
                                        <span
                                            class="w-7 h-7 rounded-lg text-xs font-black flex items-center justify-center shrink-0 mt-0.5 tabular-data"
                                            :class="
                                                stage.done
                                                    ? 'bg-emerald-100 text-emerald-900 border border-emerald-300'
                                                    : stage.warning
                                                        ? 'bg-rose-100 text-rose-900 border border-rose-300'
                                                        : stage.active
                                                            ? 'bg-orange-700 text-white'
                                                            : 'bg-slate-100 text-slate-700 border border-slate-300'
                                            "
                                        >
                                            <svg v-if="stage.done" class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span v-else>{{ stage.step }}</span>
                                        </span>

                                        <div class="min-w-0 space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm font-bold text-slate-900">
                                                    {{ stage.title }}
                                                </h3>
                                                <span
                                                    class="px-2 py-0.5 rounded text-[11px] font-bold border tabular-data"
                                                    :class="
                                                        stage.done
                                                            ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                                                            : stage.warning
                                                                ? 'bg-rose-50 text-rose-800 border-rose-200'
                                                                : stage.active
                                                                    ? 'bg-orange-50 text-orange-900 border-orange-200'
                                                                    : 'bg-slate-100 text-slate-700 border-slate-200'
                                                    "
                                                >
                                                    {{ stage.statusLabel }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-slate-600 leading-relaxed">
                                                {{ stage.detail }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="shrink-0 sm:pl-4 flex items-center">
                                        <span
                                            v-if="stage.locked"
                                            class="text-xs font-medium text-slate-600"
                                        >
                                            Terkunci hingga lunas
                                        </span>
                                        <Link
                                            v-else
                                            :href="stage.href"
                                            class="h-8 sm:h-9 inline-flex items-center px-3 rounded-lg text-xs font-bold transition-colors whitespace-nowrap cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700"
                                            :class="stage.active
                                                ? 'bg-orange-700 hover:bg-orange-800 text-white'
                                                : 'text-orange-800 hover:text-orange-950 hover:bg-orange-50 border border-slate-300 bg-white'"
                                        >
                                            {{ stage.actionLabel }}
                                        </Link>
                                    </div>
                                </li>
                            </ol>
                        </section>

                        <!-- Right 5 cols: Squad Ledger & Committee Noticeboard -->
                        <div class="lg:col-span-5 space-y-5">
                            <!-- Squad & Officials Real Composition -->
                            <section
                                aria-labelledby="squad-composition-heading"
                                class="bg-white border border-slate-200 rounded-2xl p-5 space-y-4"
                            >
                                <div class="flex items-center justify-between gap-2 pb-3 border-b border-slate-200">
                                    <h2 id="squad-composition-heading" class="text-sm font-bold text-slate-900">
                                        Komposisi Skuad &amp; Ofisial
                                    </h2>
                                    <span class="text-xs font-bold tabular-data text-slate-700">
                                        {{ calculatePercent(summary?.players_total || 0, team.event?.max_players_per_team) }}% Kuota
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="text-xs text-slate-600">Pemain Terdaftar</span>
                                        <span class="text-base font-black text-slate-900 tabular-data">
                                            {{ summary?.players_total || 0 }} / {{ team.event?.max_players_per_team || 0 }}
                                        </span>
                                    </div>
                                    <div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden" role="progressbar" :aria-valuenow="calculatePercent(summary?.players_total || 0, team.event?.max_players_per_team)" aria-valuemin="0" aria-valuemax="100" aria-label="Progres pengisian kuota pemain">
                                        <div
                                            class="h-full bg-orange-700 rounded-full transition-all duration-300"
                                            :style="{ width: `${calculatePercent(summary?.players_total || 0, team.event?.max_players_per_team)}%` }"
                                        ></div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 pt-2 text-xs">
                                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                            <span class="text-slate-600">Berkas Lengkap</span>
                                            <strong class="tabular-data text-slate-900">{{ summary?.players_docs_complete || 0 }}</strong>
                                        </div>
                                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                            <span class="text-slate-600">Lolos Screening</span>
                                            <strong class="tabular-data text-emerald-800">{{ summary?.players_passed || 0 }}</strong>
                                        </div>
                                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                            <span class="text-slate-600">Min. Pemain</span>
                                            <strong class="tabular-data text-slate-900">{{ team.event?.min_players_per_team || 0 }}</strong>
                                        </div>
                                        <div
                                            class="p-2.5 rounded-xl border flex items-center justify-between"
                                            :class="(summary?.players_rejected || 0) > 0 ? 'bg-rose-50 border-rose-200' : 'bg-slate-50 border-slate-200'"
                                        >
                                            <span :class="(summary?.players_rejected || 0) > 0 ? 'text-rose-900 font-semibold' : 'text-slate-600'">Perlu Revisi</span>
                                            <strong class="tabular-data" :class="(summary?.players_rejected || 0) > 0 ? 'text-rose-900' : 'text-slate-900'">
                                                {{ summary?.players_rejected || 0 }}
                                            </strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-3 border-t border-slate-200 flex items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <span class="block text-slate-600">Pelatih Kepala</span>
                                        <strong class="text-slate-900 font-semibold truncate block">{{ team.head_coach_name || 'Belum diisi' }}</strong>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="block text-slate-600">Ofisial</span>
                                        <strong class="tabular-data text-slate-900 font-bold">
                                            {{ summary?.officials_total || 0 }} / {{ team.event?.max_officials_per_team || 0 }}
                                        </strong>
                                    </div>
                                </div>
                            </section>

                            <!-- Committee Announcements (Always present with honest empty state per R-27) -->
                            <section
                                aria-labelledby="announcements-heading"
                                class="bg-white border border-slate-200 rounded-2xl p-5"
                            >
                                <h2 id="announcements-heading" class="text-sm font-bold text-slate-900 pb-3 border-b border-slate-200">
                                    Pengumuman Panitia Pelaksana
                                </h2>

                                <div v-if="summary?.announcements?.length > 0" class="divide-y divide-slate-200">
                                    <article
                                        v-for="announcement in summary.announcements"
                                        :key="announcement.id"
                                        class="py-3.5 first:pt-3 last:pb-0 space-y-2"
                                    >
                                        <div class="flex items-center justify-between gap-2 flex-wrap">
                                            <span
                                                v-if="announcement.is_pinned"
                                                class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-rose-100 text-rose-900 border border-rose-200"
                                            >
                                                Penting
                                            </span>
                                            <span v-else class="text-[11px] font-semibold text-slate-600">Informasi Resmi</span>
                                            <span class="text-[11px] text-slate-600 tabular-data">{{ announcement.created_at }}</span>
                                        </div>
                                        <h3 class="text-sm font-bold text-slate-900">{{ announcement.title }}</h3>
                                        <p class="text-xs text-slate-700 whitespace-pre-line leading-relaxed">{{ announcement.content }}</p>
                                        <a
                                            v-if="announcement.meeting_link"
                                            :href="announcement.meeting_link"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center justify-center h-8 sm:h-9 px-3 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 whitespace-nowrap"
                                        >
                                            Buka Tautan Meeting
                                        </a>
                                    </article>
                                </div>
                                <p v-else class="pt-3 text-xs text-slate-600 leading-relaxed">
                                    Belum ada pengumuman baru dari panitia untuk event ini. Undangan Technical Meeting dan pembaruan jadwal akan tampil di sini.
                                </p>
                            </section>
                        </div>
                    </div>
                </template>
            </div>

            <!-- VIEW 3: DETAIL SELEKSI PESERTA PERORANGAN -->
            <div v-else-if="activeView === 'audition'" class="space-y-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200">
                    <div class="min-w-0 space-y-1.5">
                        <button
                            type="button"
                            class="h-8 sm:h-9 inline-flex items-center gap-1.5 px-2.5 sm:px-3.5 rounded-lg bg-white hover:bg-slate-50 border border-slate-300 text-slate-800 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                            @click="openHub"
                        >
                            &larr; Kembali ke Kepesertaan Saya
                        </button>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                            Detail Seleksi Peserta
                        </h1>
                        <p v-if="selectedParticipant" class="text-xs sm:text-sm text-slate-600">
                            <strong class="font-semibold text-slate-900">{{ selectedParticipant.full_name }}</strong>
                            <span v-if="selectedParticipant.event?.name"> pada {{ selectedParticipant.event.name }}</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <select
                            v-if="auditionParticipants.length > 1"
                            v-model.number="selectedParticipantId"
                            aria-label="Pilih peserta audisi"
                            class="select-compact"
                        >
                            <option v-for="p in auditionParticipants" :key="p.id" :value="p.id">
                                {{ p.full_name }} ({{ p.event?.name }})
                            </option>
                        </select>
                        <Link
                            v-if="selectedParticipant"
                            :href="route('manager.payments', { participant_id: selectedParticipant.id })"
                            class="h-8 sm:h-9 inline-flex items-center justify-center px-3 sm:px-3.5 rounded-lg bg-white hover:bg-slate-50 text-slate-900 border border-slate-300 text-xs font-bold transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-700 whitespace-nowrap"
                        >
                            Transaksi &amp; Pembayaran
                        </Link>
                    </div>
                </div>

                <AuditionParticipantCard
                    v-if="selectedParticipant"
                    :participant="selectedParticipant"
                    @preview-ticket="openTicketPreview"
                />

                <!-- Empty State (No Individual Participant Registered) -->
                <section
                    v-else
                    class="bg-white border border-slate-200 rounded-2xl p-8 text-center max-w-xl mx-auto my-6 space-y-3"
                >
                    <h2 class="text-base font-bold text-slate-900">Belum Ada Pendaftaran Peserta Perorangan</h2>
                    <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                        Belum ada peserta yang terdaftar atas akun ini untuk program seleksi bakat, audisi akademi, atau coaching clinic.
                    </p>
                    <div class="pt-2">
                        <Link
                            :href="route('home')"
                            class="h-9 sm:h-10 inline-flex items-center justify-center px-4 rounded-lg sm:rounded-xl bg-orange-700 hover:bg-orange-800 text-white text-xs sm:text-sm font-bold transition-colors cursor-pointer whitespace-nowrap"
                        >
                            Jelajahi Event Terbuka
                        </Link>
                    </div>
                </section>
            </div>
        </div>

        <DocumentViewerModal
            :is-open="ticketModal.isOpen"
            :title="ticketModal.title"
            :subtitle="ticketModal.subtitle"
            :file-url="ticketModal.fileUrl"
            :document-number="ticketModal.documentNumber"
            :is-pdf="true"
            @close="closeTicketPreview"
        />
    </ManagerLayout>
</template>
