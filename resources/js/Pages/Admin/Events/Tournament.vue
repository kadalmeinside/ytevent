<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    eligibleTeams: {
        type: Array,
        default: () => [],
    },
    groups: {
        type: Array,
        default: () => [],
    },
    matches: {
        type: Array,
        default: () => [],
    },
    announcements: {
        type: Array,
        default: () => [],
    },
    leaderboard: {
        type: Object,
        default: () => ({ top_scorers: [], fair_play: [] }),
    },
    stages: {
        type: Object,
        default: () => ({}),
    },
    matchStatuses: {
        type: Object,
        default: () => ({}),
    },
    eventTypes: {
        type: Object,
        default: () => ({}),
    },
    controls: {
        type: Object,
        default: () => ({}),
    },
});

const activeTab = ref('groups'); // 'groups', 'matches', 'announcements', 'leaderboard', 'achievements'
const stageFilter = ref('all');

// --- MODALS STATE ---
const showDrawModal = ref(false);
const showMoveTeamModal = ref(false);
const showGenMatchesModal = ref(false);
const showKnockoutModal = ref(false);
const showAddMatchModal = ref(false);
const showRescheduleModal = ref(false);
const showScoreModal = ref(false);
const showAnnouncementModal = ref(false);
const showAchievementModal = ref(false);

const activeMatchForScore = ref(null);
const activeMatchForReschedule = ref(null);
const activeTeamForAchievement = ref(null);
const teamToMove = ref(null);

// --- FORMS ---
const drawForm = useForm({
    group_count: 4,
    only_locked_roster: false,
});

const moveTeamForm = useForm({
    team_id: null,
    target_group_id: '',
});

const genMatchesForm = useForm({
    start_time: '',
    interval_minutes: 60,
    pitches: 'Lapangan A, Lapangan B',
});

const genKnockoutForm = useForm({
    stage: 'semi_final',
    start_time: '',
    interval_minutes: 60,
    pitches: 'Lapangan Utama',
});

const addMatchForm = useForm({
    stage: 'group',
    event_group_id: '',
    home_team_id: '',
    away_team_id: '',
    kickoff_at: '',
    pitch_name: 'Lapangan Utama',
    referee_name: '',
});

const rescheduleForm = useForm({
    kickoff_at: '',
    pitch_name: '',
    referee_name: '',
    status: 'scheduled',
    match_notes: '',
});

const scoreForm = useForm({
    status: 'completed',
    home_score: 0,
    away_score: 0,
    home_penalty_score: null,
    away_penalty_score: null,
    kickoff_at: '',
    pitch_name: '',
    referee_name: '',
    match_notes: '',
    events: [],
});

const announcementForm = useForm({
    title: '',
    content: '',
    meeting_link: '',
    is_pinned: false,
});

const achievementForm = useForm({
    achievement_title: '',
});

// --- SUBMIT ACTIONS ---
const toggleGroupLock = () => {
    router.post(route('admin.events.groups.toggle-lock', { event: props.event.id }), {}, {
        preserveScroll: true,
    });
};

const openMoveTeamModal = (teamEntry) => {
    teamToMove.value = teamEntry;
    moveTeamForm.team_id = teamEntry.team_id;
    moveTeamForm.target_group_id = '';
    moveTeamForm.clearErrors();
    showMoveTeamModal.value = true;
};

const submitMoveTeam = () => {
    moveTeamForm.post(route('admin.events.groups.move-team', { event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showMoveTeamModal.value = false;
            teamToMove.value = null;
        },
    });
};

const submitDrawGroups = () => {
    drawForm.post(route('admin.events.groups.generate', { event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showDrawModal.value = false;
        },
    });
};

const submitGenMatches = () => {
    genMatchesForm.post(route('admin.events.matches.generate', { event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showGenMatchesModal.value = false;
        },
    });
};

const submitGenKnockout = () => {
    genKnockoutForm.post(route('admin.events.matches.generate-knockout', { event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showKnockoutModal.value = false;
        },
    });
};

const openRescheduleModal = (match) => {
    activeMatchForReschedule.value = match;
    rescheduleForm.kickoff_at = match.kickoff_at || '';
    rescheduleForm.pitch_name = match.pitch_name || '';
    rescheduleForm.referee_name = match.referee_name || '';
    rescheduleForm.status = match.status || 'scheduled';
    rescheduleForm.match_notes = match.match_notes || '';
    rescheduleForm.clearErrors();
    showRescheduleModal.value = true;
};

const submitReschedule = () => {
    if (!activeMatchForReschedule.value) return;
    rescheduleForm.post(route('admin.matches.reschedule', { match: activeMatchForReschedule.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showRescheduleModal.value = false;
            activeMatchForReschedule.value = null;
        },
    });
};

const toggleCertificateRelease = () => {
    router.post(route('admin.events.certificates.toggle-release', { event: props.event.id }), {}, {
        preserveScroll: true,
    });
};

const submitAddMatch = () => {
    addMatchForm.post(route('admin.events.matches.store', { event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showAddMatchModal.value = false;
            addMatchForm.reset();
        },
    });
};

const openScoreModal = (match) => {
    activeMatchForScore.value = match;
    scoreForm.status = match.status;
    scoreForm.home_score = match.home_score ?? 0;
    scoreForm.away_score = match.away_score ?? 0;
    scoreForm.home_penalty_score = match.home_penalty_score;
    scoreForm.away_penalty_score = match.away_penalty_score;
    scoreForm.kickoff_at = match.kickoff_at || '';
    scoreForm.pitch_name = match.pitch_name || '';
    scoreForm.referee_name = match.referee_name || '';
    scoreForm.match_notes = match.match_notes || '';
    scoreForm.events = (match.events || []).map((e) => ({
        team_id: e.team_id,
        player_id: e.player_id,
        event_type: e.event_type,
        minute: e.minute,
        notes: e.notes || '',
    }));
    showScoreModal.value = true;
};

const addEventRow = (teamId = null) => {
    const defaultTeamId = teamId || activeMatchForScore.value?.home_team_id || props.eligibleTeams[0]?.id;
    scoreForm.events.push({
        team_id: defaultTeamId,
        player_id: null,
        event_type: 'goal',
        minute: null,
        notes: '',
    });
};

const removeEventRow = (index) => {
    scoreForm.events.splice(index, 1);
};

const submitScore = () => {
    if (!activeMatchForScore.value) return;
    scoreForm.post(route('admin.matches.result', { match: activeMatchForScore.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showScoreModal.value = false;
            activeMatchForScore.value = null;
        },
    });
};

const deleteMatch = (match) => {
    if (confirm(`Yakin ingin menghapus Pertandingan #${match.match_number} (${match.home_team_name} vs ${match.away_team_name})?`)) {
        router.delete(route('admin.matches.destroy', { match: match.id }), {
            preserveScroll: true,
        });
    }
};

const submitAnnouncement = () => {
    announcementForm.post(route('admin.events.announcements.store', { event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showAnnouncementModal.value = false;
            announcementForm.reset();
        },
    });
};

const deleteAnnouncement = (announcement) => {
    if (confirm(`Hapus pengumuman "${announcement.title}"?`)) {
        router.delete(route('admin.announcements.destroy', { announcement: announcement.id }), {
            preserveScroll: true,
        });
    }
};

const openAchievementModal = (team) => {
    activeTeamForAchievement.value = team;
    achievementForm.achievement_title = team.achievement_title || '';
    showAchievementModal.value = true;
};

const submitAchievement = () => {
    if (!activeTeamForAchievement.value) return;
    achievementForm.post(route('admin.teams.achievement', { team: activeTeamForAchievement.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showAchievementModal.value = false;
            activeTeamForAchievement.value = null;
        },
    });
};

// Players available for selected event row team
const getPlayersForTeam = (teamId) => {
    const t = props.eligibleTeams.find((item) => item.id === Number(teamId));
    return t ? t.players : [];
};

const filteredMatches = computed(() => {
    if (stageFilter.value === 'all') return props.matches;
    return props.matches.filter((m) => m.stage === stageFilter.value);
});
</script>

<template>
    <AdminLayout
        :page-title="`Operasional Turnamen: ${event.name}`"
        :subtitle="`Pusat Kendali Drawing Grup, Jadwal & Skor Pertandingan, Disiplin, dan Penghargaan (${event.age_category} - ${event.venue_name})`"
    >
        <Head :title="`Turnamen: ${event.name}`" />

        <div class="space-y-6">
            <!-- Top Navigation / Breadcrumb -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-xs">
                    <Link :href="route('admin.events.index')" class="text-slate-500 hover:text-orange-600 transition-colors font-medium">
                        &larr; Kembali ke Daftar Event
                    </Link>
                    <span class="text-slate-300">/</span>
                    <span class="font-bold text-slate-800">{{ event.name }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('admin.qr-scanner')"
                        class="btn-secondary text-xs inline-flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        Buka Web QR Scanner Lapangan
                    </Link>
                    <Link
                        :href="route('events.show', { event: event.slug })"
                        target="_blank"
                        class="btn-sm-secondary inline-flex items-center gap-1 text-xs"
                    >
                        <span>Lihat Publik</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Banner SOP Siklus Operasional Turnamen -->
            <div
                class="rounded-2xl border p-4 sm:p-5 transition-all shadow-2xs"
                :class="{
                    'bg-amber-50/90 border-amber-300 text-amber-950': controls?.current_phase === 'registration',
                    'bg-sky-50/90 border-sky-300 text-sky-950': controls?.current_phase === 'technical_meeting',
                    'bg-indigo-50/90 border-indigo-300 text-indigo-950': controls?.current_phase === 'group_drawn',
                    'bg-emerald-50/90 border-emerald-300 text-emerald-950': controls?.current_phase === 'scheduling',
                    'bg-purple-50/90 border-purple-300 text-purple-950': controls?.current_phase === 'matchday',
                    'bg-slate-900 border-slate-800 text-white': controls?.current_phase === 'completed',
                }"
            >
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span
                            class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 font-black text-xs shadow-xs"
                            :class="{
                                'bg-amber-500 text-white': controls?.current_phase === 'registration',
                                'bg-sky-600 text-white': controls?.current_phase === 'technical_meeting',
                                'bg-indigo-600 text-white': controls?.current_phase === 'group_drawn',
                                'bg-emerald-600 text-white': controls?.current_phase === 'scheduling',
                                'bg-purple-600 text-white': controls?.current_phase === 'matchday',
                                'bg-amber-400 text-slate-950': controls?.current_phase === 'completed',
                            }"
                        >
                            <span v-if="controls?.current_phase === 'registration'">1</span>
                            <span v-else-if="controls?.current_phase === 'technical_meeting'">2</span>
                            <span v-else-if="controls?.current_phase === 'group_drawn'">3</span>
                            <span v-else-if="controls?.current_phase === 'scheduling'">4</span>
                            <span v-else-if="controls?.current_phase === 'matchday'">5</span>
                            <svg v-else class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </span>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
                                    :class="{
                                        'bg-amber-200/90 text-amber-900': controls?.current_phase === 'registration',
                                        'bg-sky-200/90 text-sky-900': controls?.current_phase === 'technical_meeting',
                                        'bg-indigo-200/90 text-indigo-900': controls?.current_phase === 'group_drawn',
                                        'bg-emerald-200/90 text-emerald-900': controls?.current_phase === 'scheduling',
                                        'bg-purple-200/90 text-purple-900': controls?.current_phase === 'matchday',
                                        'bg-amber-400/20 text-amber-300': controls?.current_phase === 'completed',
                                    }"
                                >
                                    SOP Turnamen
                                </span>
                                <h3 class="text-xs font-black uppercase tracking-wide">
                                    <template v-if="controls?.current_phase === 'registration'">Fase 1: Pendaftaran &amp; Screening Berkas</template>
                                    <template v-else-if="controls?.current_phase === 'technical_meeting'">Fase 2: Technical Meeting (Siap Drawing Grup)</template>
                                    <template v-else-if="controls?.current_phase === 'group_drawn'">Fase 3: Penataan Grup Selesai (Menunggu Kunci Grup)</template>
                                    <template v-else-if="controls?.current_phase === 'scheduling'">Fase 4: Grup Terkunci Resmi (Siap Rilis Jadwal Pertandingan)</template>
                                    <template v-else-if="controls?.current_phase === 'matchday'">Fase 5: Matchday &amp; Pertandingan Berlangsung</template>
                                    <template v-else>Fase 6: Turnamen Selesai &amp; E-Sertifikat Siap Dirilis</template>
                                </h3>
                            </div>
                            <p class="text-xs leading-relaxed" :class="controls?.current_phase === 'completed' ? 'text-slate-300' : 'text-slate-700'">
                                <template v-if="controls?.current_phase === 'registration'">
                                    Pendaftaran kompetisi masih <strong>OPEN</strong> (slot terisi: {{ event.occupied_slots }} / {{ event.max_teams }} tim).
                                    Undian grup, kunci grup, dan jadwal pertandingan <strong>terkunci</strong> demi integritas kompetisi. Gunakan tab <strong>Pengumuman &amp; Technical Meeting</strong> untuk mengumumkan jadwal TM kepada para manajer tim.
                                </template>
                                <template v-else-if="controls?.current_phase === 'technical_meeting'">
                                    Kuota tim telah terpenuhi atau pendaftaran resmi ditutup. Silakan lakukan <strong>Undian Grup (Drawing)</strong> bersama perwakilan manajer tim pada sesi Technical Meeting.
                                </template>
                                <template v-else-if="controls?.current_phase === 'group_drawn'">
                                    Undian grup telah selesai. Anda dapat menyesuaikan tim antar-grup jika diperlukan, lalu tekan tombol <strong>Kunci Grup</strong> untuk membekukan grup sebelum jadwal pertandingan dibuat.
                                </template>
                                <template v-else-if="controls?.current_phase === 'scheduling'">
                                    Pembagian grup telah resmi dikunci. Anda kini dapat men-generate jadwal pertandingan fase grup (Round-Robin) secara otomatis.
                                </template>
                                <template v-else-if="controls?.current_phase === 'matchday'">
                                    Pertandingan turnamen sedang berlangsung (sisa {{ controls?.uncompleted_matches_count }} laga). Input skor akhir, pencetak gol, dan kartu disiplin pada setiap kartu pertandingan.
                                </template>
                                <template v-else>
                                    Seluruh rangkaian pertandingan turnamen telah selesai. Anda dapat menetapkan predikat juara dan merilis E-Sertifikat digital resmi kepada para manajer tim.
                                </template>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 self-start md:self-center shrink-0">
                        <span class="text-[11px] font-mono font-bold px-2.5 py-1 rounded-lg bg-black/10">
                            Tim Sah: {{ eligibleTeams.length }} / {{ event.max_teams }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation Bar -->
            <div class="frosted-card p-1.5 flex items-center gap-1 overflow-x-auto">
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === 'groups' ? 'bg-orange-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    @click="activeTab = 'groups'"
                >
                    Pembagian Grup &amp; Klasemen ({{ groups.length }})
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === 'matches' ? 'bg-orange-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    @click="activeTab = 'matches'"
                >
                    Jadwal &amp; Hasil Pertandingan ({{ matches.length }})
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === 'announcements' ? 'bg-orange-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    @click="activeTab = 'announcements'"
                >
                    Pengumuman &amp; Technical Meeting ({{ announcements.length }})
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === 'leaderboard' ? 'bg-orange-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    @click="activeTab = 'leaderboard'"
                >
                    Top Scorer &amp; Fair Play
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === 'achievements' ? 'bg-orange-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    @click="activeTab = 'achievements'"
                >
                    Predikat Juara / E-Certificate
                </button>
            </div>

            <!-- ================= TAB 1: GRUP & KLASEMEN ================= -->
            <div v-if="activeTab === 'groups'" class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Pembagian Grup &amp; Live Standings</h2>
                        <p class="text-xs text-slate-500">Terdapat {{ eligibleTeams.length }} tim sah terverifikasi yang siap diundi ke dalam grup.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all border inline-flex items-center gap-1.5 shadow-2xs"
                            :disabled="!controls?.can_toggle_group_lock"
                            :class="[
                                !controls?.can_toggle_group_lock
                                    ? 'opacity-50 cursor-not-allowed bg-slate-100 text-slate-400 border-slate-200'
                                    : (
                                        event.is_group_locked
                                            ? 'bg-amber-50 text-amber-900 border-amber-300 hover:bg-amber-100 cursor-pointer'
                                            : 'bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200 cursor-pointer'
                                    )
                            ]"
                            :title="controls?.lock_block_reason || (event.is_group_locked ? 'Buka Kunci Pembagian Grup' : 'Kunci Pembagian Grup Resmi')"
                            @click="controls?.can_toggle_group_lock && toggleGroupLock()"
                        >
                            <svg v-if="event.is_group_locked" class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <svg v-else class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                            </svg>
                            {{ event.is_group_locked ? 'Grup Terkunci (Buka Kunci)' : 'Kunci Grup' }}
                        </button>

                        <button
                            type="button"
                            class="btn-pitch text-xs inline-flex items-center gap-1.5 transition-all"
                            :disabled="!controls?.can_draw_groups"
                            :class="{ 'opacity-50 cursor-not-allowed': !controls?.can_draw_groups }"
                            :title="controls?.draw_block_reason || 'Undi penempatan grup'"
                            @click="controls?.can_draw_groups && (showDrawModal = true)"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                            Undi &amp; Bentuk Grup Otomatis
                        </button>
                    </div>
                </div>

                <div v-if="groups.length === 0" class="frosted-card p-10 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-lg">
                        #
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">Belum Ada Grup yang Dibentuk</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        <template v-if="controls?.is_registration_open">
                            Pendaftaran tim masih berlangsung ({{ event.occupied_slots }}/{{ event.max_teams }} tim). Sesi undian grup baru dapat dibuka setelah pendaftaran penuh atau resmi ditutup pada Technical Meeting.
                        </template>
                        <template v-else>
                            Klik tombol "Undi &amp; Bentuk Grup Otomatis" di atas untuk membagi tim yang telah lolos verifikasi berkas secara acak dan adil ke dalam grup.
                        </template>
                    </p>
                    <button
                        type="button"
                        class="btn-secondary text-xs inline-flex items-center gap-1.5 transition-all"
                        :disabled="!controls?.can_draw_groups"
                        :class="{ 'opacity-50 cursor-not-allowed': !controls?.can_draw_groups }"
                        :title="controls?.draw_block_reason || 'Mulai drawing grup'"
                        @click="controls?.can_draw_groups && (showDrawModal = true)"
                    >
                        Mulai Drawing Sekarang
                    </button>
                </div>

                <div v-else class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div
                        v-for="group in groups"
                        :key="group.id"
                        class="frosted-card overflow-hidden border border-slate-200/90 shadow-2xs"
                    >
                        <div class="px-5 py-3.5 bg-gradient-to-r from-slate-900 to-slate-800 text-white flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[11px] font-black bg-orange-600 text-white tracking-wider">
                                    {{ group.name }}
                                </span>
                                <span class="text-xs text-slate-300 font-medium">Klasemen Fase Grup</span>
                            </div>
                            <span class="text-[11px] text-slate-400 font-mono">{{ group.standings.length }} Tim</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                        <th class="py-2.5 px-3 w-8 text-center">#</th>
                                        <th class="py-2.5 px-3">Klub / Tim</th>
                                        <th class="py-2.5 px-2 text-center" title="Main">M</th>
                                        <th class="py-2.5 px-2 text-center" title="Menang">Mn</th>
                                        <th class="py-2.5 px-2 text-center" title="Seri">S</th>
                                        <th class="py-2.5 px-2 text-center" title="Kalah">K</th>
                                        <th class="py-2.5 px-2 text-center" title="Gol Memasukkan">GM</th>
                                        <th class="py-2.5 px-2 text-center" title="Gol Kemasukan">GK</th>
                                        <th class="py-2.5 px-2 text-center" title="Selisih Gol">SG</th>
                                        <th class="py-2.5 px-3 text-center font-black text-orange-950 bg-orange-50/60" title="Poin">PTS</th>
                                        <th class="py-2.5 px-2 text-center" title="Kartu Kuning">KK</th>
                                        <th class="py-2.5 px-2 text-center" title="Kartu Merah">KM</th>
                                        <th class="py-2.5 px-2 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr
                                        v-for="(st, sIndex) in group.standings"
                                        :key="st.id"
                                        class="hover:bg-orange-50/30 transition-colors"
                                        :class="{ 'bg-emerald-50/30 font-semibold': sIndex < 2 }"
                                    >
                                        <td class="py-2.5 px-3 text-center font-bold font-mono">
                                            <span
                                                class="w-5 h-5 inline-flex items-center justify-center rounded-full text-[10px]"
                                                :class="sIndex < 2 ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'"
                                            >
                                                {{ sIndex + 1 }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">
                                            {{ st.team_name }}
                                        </td>
                                        <td class="py-2.5 px-2 text-center tabular-data">{{ st.played }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data text-emerald-700 font-semibold">{{ st.won }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data text-slate-500">{{ st.drawn }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data text-rose-700 font-semibold">{{ st.lost }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data">{{ st.goals_for }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data">{{ st.goals_against }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data font-semibold" :class="st.goal_difference > 0 ? 'text-emerald-700' : (st.goal_difference < 0 ? 'text-rose-700' : 'text-slate-600')">
                                            {{ st.goal_difference > 0 ? `+${st.goal_difference}` : st.goal_difference }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center tabular-data font-black text-orange-900 bg-orange-50/60 text-sm">
                                            {{ st.points }}
                                        </td>
                                        <td class="py-2.5 px-2 text-center tabular-data text-amber-600 font-medium">{{ st.yellow_cards }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data text-rose-600 font-bold">{{ st.red_cards }}</td>
                                        <td class="py-2.5 px-2 text-right">
                                            <button
                                                v-if="!event.is_group_locked"
                                                type="button"
                                                class="px-2 py-0.5 rounded text-[11px] font-semibold text-slate-600 hover:text-orange-950 hover:bg-orange-100 transition-colors inline-flex items-center gap-1 border border-slate-200"
                                                title="Pindahkan Tim ke Grup Lain"
                                                @click="openMoveTeamModal(st)"
                                            >
                                                Pindah
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 2: JADWAL & HASIL ================= -->
            <div v-else-if="activeTab === 'matches'" class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Jadwal &amp; Hasil Pertandingan</h2>
                        <p class="text-xs text-slate-500">Kelola skor pertandingan, menit gol, kartu kuning, dan kartu merah langsung dari meja wasit / pengawas.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="groups.length > 0"
                            type="button"
                            class="btn-secondary text-xs inline-flex items-center gap-1.5 transition-all"
                            :disabled="!controls?.can_generate_group_matches"
                            :class="{ 'opacity-50 cursor-not-allowed': !controls?.can_generate_group_matches }"
                            :title="controls?.group_matches_block_reason || 'Generate jadwal Round-Robin'"
                            @click="controls?.can_generate_group_matches && (showGenMatchesModal = true)"
                        >
                            <svg class="w-4 h-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Generate Round-Robin Grup
                        </button>
                        <button
                            v-if="groups.length > 0"
                            type="button"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border border-orange-300 bg-orange-50 text-orange-950 inline-flex items-center gap-1.5 shadow-2xs"
                            :disabled="!controls?.can_generate_knockout"
                            :class="[
                                !controls?.can_generate_knockout
                                    ? 'opacity-50 cursor-not-allowed'
                                    : 'hover:bg-orange-100 cursor-pointer'
                            ]"
                            :title="controls?.knockout_block_reason || 'Generate babak gugur dari tim lolos grup'"
                            @click="controls?.can_generate_knockout && (showKnockoutModal = true)"
                        >
                            <svg class="w-4 h-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Generate Babak Gugur (Knockout)
                        </button>
                        <button
                            type="button"
                            class="btn-pitch text-xs inline-flex items-center gap-1.5"
                            @click="showAddMatchModal = true"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Pertandingan Manual
                        </button>
                    </div>
                </div>

                <div v-if="!event.is_group_locked && groups.length > 0" class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>
                        <strong>Peringatan Jadwal:</strong> Pembagian grup belum dikunci resmi. Pastikan drawing pada sesi Technical Meeting sudah final dan grup dikunci sebelum menyusun jadwal pertandingan agar urutan tim tidak berubah.
                    </span>
                </div>

                <!-- Stage Filter -->
                <div class="flex items-center gap-1 overflow-x-auto pb-1 text-xs">
                    <span class="text-slate-500 font-semibold mr-2 shrink-0">Filter Fase:</span>
                    <button
                        type="button"
                        class="px-3 py-1 rounded-lg font-medium transition-colors whitespace-nowrap"
                        :class="stageFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="stageFilter = 'all'"
                    >
                        Semua ({{ matches.length }})
                    </button>
                    <button
                        v-for="(label, key) in stages"
                        :key="key"
                        type="button"
                        class="px-3 py-1 rounded-lg font-medium transition-colors whitespace-nowrap"
                        :class="stageFilter === key ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="stageFilter = key"
                    >
                        {{ label }}
                    </button>
                </div>

                <!-- Match List -->
                <div v-if="filteredMatches.length === 0" class="frosted-card p-10 text-center space-y-2">
                    <p class="text-sm font-bold text-slate-800">Belum Ada Jadwal Pertandingan</p>
                    <p class="text-xs text-slate-500">Gunakan tombol "Generate Round-Robin Grup" atau buat jadwal manual fase gugur.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="m in filteredMatches"
                        :key="m.id"
                        class="frosted-card p-4 sm:p-5 hover:border-orange-300 transition-all border border-slate-200/90 shadow-2xs"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded font-black bg-slate-900 text-white font-mono">
                                    #{{ m.match_number }}
                                </span>
                                <span class="font-bold text-orange-900 bg-orange-50 px-2 py-0.5 rounded border border-orange-200">
                                    {{ m.stage_label }} {{ m.group_name ? `(${m.group_name})` : '' }}
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-[11px] font-semibold"
                                    :class="{
                                        'bg-slate-100 text-slate-700': m.status === 'scheduled',
                                        'bg-amber-100 text-amber-800 animate-pulse': m.status === 'ongoing',
                                        'bg-emerald-100 text-emerald-800': m.status === 'completed',
                                        'bg-rose-100 text-rose-800': m.status === 'postponed',
                                    }"
                                >
                                    {{ m.status_label }}
                                </span>
                            </div>

                            <div class="flex items-center gap-3 text-slate-500 text-[11px]">
                                <span v-if="m.pitch_name" class="font-medium text-slate-700">{{ m.pitch_name }}</span>
                                <span v-if="m.kickoff_formatted" class="tabular-data font-mono">{{ m.kickoff_formatted }}</span>
                            </div>
                        </div>

                        <!-- Match Body: Home vs Away -->
                        <div class="py-4 grid grid-cols-1 sm:grid-cols-7 items-center gap-3">
                            <!-- Home Team -->
                            <div class="sm:col-span-3 flex items-center justify-start sm:justify-end gap-3 text-right">
                                <div class="space-y-0.5">
                                    <h4 class="text-sm font-black text-slate-900 leading-tight">{{ m.home_team_name }}</h4>
                                    <p v-if="m.home_jersey_color" class="text-[11px] text-slate-400">Jersey: {{ m.home_jersey_color }}</p>
                                </div>
                                <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-900 font-bold flex items-center justify-center shrink-0 text-xs">
                                    {{ m.home_team_name.charAt(0) }}
                                </div>
                            </div>

                            <!-- Score / VS Center -->
                            <div class="sm:col-span-1 flex flex-col items-center justify-center">
                                <div v-if="m.status === 'completed' || m.status === 'ongoing'" class="flex items-center gap-2">
                                    <span class="text-2xl font-black text-slate-900 tabular-data font-mono">{{ m.home_score ?? 0 }}</span>
                                    <span class="text-slate-400 font-bold">-</span>
                                    <span class="text-2xl font-black text-slate-900 tabular-data font-mono">{{ m.away_score ?? 0 }}</span>
                                </div>
                                <div v-else class="text-xs font-black text-slate-400 tracking-wider">
                                    VS
                                </div>
                                <span v-if="m.home_penalty_score !== null" class="text-[10px] text-slate-500 font-mono mt-0.5">
                                    (Pen: {{ m.home_penalty_score }} - {{ m.away_penalty_score }})
                                </span>
                            </div>

                            <!-- Away Team -->
                            <div class="sm:col-span-3 flex items-center justify-start gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-800 font-bold flex items-center justify-center shrink-0 text-xs">
                                    {{ m.away_team_name.charAt(0) }}
                                </div>
                                <div class="space-y-0.5">
                                    <h4 class="text-sm font-black text-slate-900 leading-tight">{{ m.away_team_name }}</h4>
                                    <p v-if="m.away_jersey_color" class="text-[11px] text-slate-400">Jersey: {{ m.away_jersey_color }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Match Events Pill Badges (Goals & Cards) -->
                        <div v-if="m.events && m.events.length > 0" class="pt-2 border-t border-slate-100 flex flex-wrap items-center gap-1.5 text-[11px]">
                            <span class="text-slate-400 font-medium mr-1">Kejadian:</span>
                            <span
                                v-for="ev in m.events"
                                :key="ev.id"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border font-medium"
                                :class="{
                                    'bg-emerald-50 border-emerald-200 text-emerald-900': ev.event_type === 'goal' || ev.event_type === 'penalty_goal',
                                    'bg-amber-50 border-amber-200 text-amber-900': ev.event_type === 'yellow_card',
                                    'bg-rose-50 border-rose-200 text-rose-900': ev.event_type === 'red_card' || ev.event_type === 'second_yellow_card',
                                    'bg-purple-50 border-purple-200 text-purple-900': ev.event_type === 'own_goal',
                                }"
                            >
                                <span class="font-bold">{{ ev.minute ? `${ev.minute}'` : '' }}</span>
                                <span>{{ ev.player_name || ev.team_name }}</span>
                                <span class="text-[10px] text-slate-500 font-normal">({{ eventTypes[ev.event_type] || ev.event_type }})</span>
                            </span>
                        </div>

                        <!-- Actions Footer -->
                        <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                            <span v-if="m.referee_name" class="text-[11px] text-slate-500">
                                Wasit: <strong class="text-slate-700">{{ m.referee_name }}</strong>
                            </span>
                            <div class="flex items-center gap-2 ml-auto">
                                <button
                                    type="button"
                                    class="btn-sm-secondary text-xs inline-flex items-center gap-1"
                                    @click="openRescheduleModal(m)"
                                >
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Reschedule
                                </button>
                                <button
                                    type="button"
                                    class="btn-sm-pitch text-xs inline-flex items-center gap-1"
                                    @click="openScoreModal(m)"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Input Skor &amp; Kejadian
                                </button>
                                <button
                                    type="button"
                                    class="p-1.5 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 hover:border-rose-300 transition-colors"
                                    title="Hapus Pertandingan"
                                    @click="deleteMatch(m)"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 3: PENGUMUMAN & TM ================= -->
            <div v-if="activeTab === 'announcements'" class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Pengumuman &amp; Undangan Technical Meeting</h2>
                        <p class="text-xs text-slate-500">Pengumuman ini langsung muncul sebagai kartu notifikasi resmi di Dashboard Manajer Tim.</p>
                    </div>
                    <button
                        type="button"
                        class="btn-pitch text-xs inline-flex items-center gap-1.5"
                        @click="showAnnouncementModal = true"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Buat Pengumuman Baru
                    </button>
                </div>

                <div v-if="announcements.length === 0" class="frosted-card p-10 text-center space-y-2">
                    <p class="text-sm font-bold text-slate-800">Belum Ada Pengumuman</p>
                    <p class="text-xs text-slate-500">Publikasikan jadwal Technical Meeting, tautan Google Meet / Zoom, atau perubahan jadwal di sini.</p>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="a in announcements"
                        :key="a.id"
                        class="frosted-card p-5 border border-slate-200/90 shadow-2xs relative"
                        :class="{ 'border-l-4 border-l-orange-600 bg-orange-50/20': a.is_pinned }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span v-if="a.is_pinned" class="px-2 py-0.5 rounded text-[10px] font-black bg-orange-600 text-white uppercase tracking-wider">
                                        DISEMATKAN
                                    </span>
                                    <h3 class="text-base font-bold text-slate-900">{{ a.title }}</h3>
                                </div>
                                <p class="text-xs text-slate-500 font-mono">
                                    Oleh: <strong class="text-slate-700">{{ a.author_name }}</strong> &bull; {{ a.created_at }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="text-slate-400 hover:text-rose-600 p-1"
                                title="Hapus Pengumuman"
                                @click="deleteAnnouncement(a)"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <p class="mt-3 text-xs text-slate-700 leading-relaxed whitespace-pre-line">{{ a.content }}</p>

                        <div v-if="a.meeting_link" class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-500">Tautan Virtual Meeting (Zoom / Google Meet):</span>
                            <a
                                :href="a.meeting_link"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn-sm-secondary text-xs inline-flex items-center gap-1 text-orange-900 font-bold"
                            >
                                <span>Buka Ruang Pertemuan &rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 4: LEADERBOARD ================= -->
            <div v-else-if="activeTab === 'leaderboard'" class="space-y-6">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Top Scorer &amp; Klasemen Fair Play</h2>
                    <p class="text-xs text-slate-500">Statistik pemain dan tim dihitung secara otomatis dari kejadian (events) pertandingan yang telah selesai.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Top Scorer Table -->
                    <div class="frosted-card overflow-hidden border border-slate-200/90 shadow-2xs">
                        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase tracking-wider text-orange-400">Peringkat Pencetak Gol Terbanyak</h3>
                            <span class="text-[11px] text-slate-400 font-mono">Golden Boot</span>
                        </div>
                        <div v-if="leaderboard.top_scorers.length === 0" class="p-6 text-center text-xs text-slate-500">
                            Belum ada catatan gol yang dimasukkan.
                        </div>
                        <table v-else class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                    <th class="py-2.5 px-3 w-10 text-center">#</th>
                                    <th class="py-2.5 px-3">Nama Pemain</th>
                                    <th class="py-2.5 px-3">Klub / Tim</th>
                                    <th class="py-2.5 px-3 text-center font-black text-orange-950 bg-orange-50/60">GOL</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="(p, idx) in leaderboard.top_scorers"
                                    :key="p.player_id"
                                    class="hover:bg-orange-50/20"
                                >
                                    <td class="py-2.5 px-3 text-center font-bold font-mono">
                                        <span
                                            class="w-5 h-5 inline-flex items-center justify-center rounded-full text-[10px]"
                                            :class="idx === 0 ? 'bg-amber-400 text-amber-950 font-black' : (idx === 1 ? 'bg-slate-300 text-slate-800' : 'bg-slate-100 text-slate-600')"
                                        >
                                            {{ idx + 1 }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-3 font-bold text-slate-900">
                                        {{ p.player_name }}
                                        <span v-if="p.jersey_number" class="text-slate-400 font-mono text-[10px] ml-1">#{{ p.jersey_number }}</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-600">{{ p.team_name }}</td>
                                    <td class="py-2.5 px-3 text-center tabular-data font-black text-orange-900 bg-orange-50/60 text-sm">
                                        {{ p.total_goals }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Fair Play Table -->
                    <div class="frosted-card overflow-hidden border border-slate-200/90 shadow-2xs">
                        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase tracking-wider text-emerald-400">Peringkat Fair Play Tim</h3>
                            <span class="text-[11px] text-slate-400 font-mono">Disiplin Kartu Terendah</span>
                        </div>
                        <div v-if="leaderboard.fair_play.length === 0" class="p-6 text-center text-xs text-slate-500">
                            Belum ada data pertandingan fair play.
                        </div>
                        <table v-else class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                    <th class="py-2.5 px-3 w-10 text-center">#</th>
                                    <th class="py-2.5 px-3">Klub / Tim</th>
                                    <th class="py-2.5 px-2 text-center" title="Kartu Kuning">KK</th>
                                    <th class="py-2.5 px-2 text-center" title="Kartu Merah">KM</th>
                                    <th class="py-2.5 px-3 text-center font-black text-slate-900">Poin Pelanggaran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr
                                    v-for="(t, idx) in leaderboard.fair_play"
                                    :key="t.team_id"
                                    class="hover:bg-emerald-50/20"
                                >
                                    <td class="py-2.5 px-3 text-center font-bold font-mono text-slate-500">{{ idx + 1 }}</td>
                                    <td class="py-2.5 px-3 font-bold text-slate-900">{{ t.team_name }}</td>
                                    <td class="py-2.5 px-2 text-center tabular-data text-amber-600 font-semibold">{{ t.yellow_cards }}</td>
                                    <td class="py-2.5 px-2 text-center tabular-data text-rose-600 font-bold">{{ t.red_cards }}</td>
                                    <td class="py-2.5 px-3 text-center tabular-data font-black text-slate-800">
                                        {{ t.fair_play_score }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 5: PREDIKAT JUARA & E-CERTIFICATE ================= -->
            <div v-else-if="activeTab === 'achievements'" class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Predikat Piagam Penghargaan &amp; E-Certificate Tim</h2>
                        <p class="text-xs text-slate-500">Tentukan predikat juara (misal: "Juara 1", "Juara 2", "Semifinalis") yang akan tertera pada piagam digital setiap pemain.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all border inline-flex items-center gap-1.5 shadow-2xs"
                            :disabled="!controls?.can_release_certificates && !event.certificates_released"
                            :class="[
                                (!controls?.can_release_certificates && !event.certificates_released)
                                    ? 'opacity-50 cursor-not-allowed bg-slate-100 text-slate-400 border-slate-200'
                                    : (
                                        event.certificates_released
                                            ? 'bg-emerald-600 text-white border-emerald-700 hover:bg-emerald-700 cursor-pointer'
                                            : 'bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200 cursor-pointer'
                                    )
                            ]"
                            :title="controls?.certificates_block_reason || (event.certificates_released ? 'Tutup Akses E-Sertifikat' : 'Rilis E-Sertifikat untuk Manajer Tim')"
                            @click="(controls?.can_release_certificates || event.certificates_released) && toggleCertificateRelease()"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ event.certificates_released ? 'E-Sertifikat: Dirilis ke Manajer' : 'Rilis E-Sertifikat untuk Manajer' }}</span>
                        </button>
                    </div>
                </div>

                <div class="frosted-card overflow-hidden border border-slate-200/90 shadow-2xs">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4">Nama Tim</th>
                                <th class="py-3 px-4">Asosiasi / Kota</th>
                                <th class="py-3 px-4">Pemain Sah</th>
                                <th class="py-3 px-4">Predikat Piagam Saat Ini</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="(team, idx) in eligibleTeams"
                                :key="team.id"
                                class="hover:bg-orange-50/20"
                            >
                                <td class="py-3 px-4 text-center font-bold font-mono text-slate-400">{{ idx + 1 }}</td>
                                <td class="py-3 px-4 font-bold text-slate-900">{{ team.name }}</td>
                                <td class="py-3 px-4 text-slate-600">{{ team.origin_association || '-' }}</td>
                                <td class="py-3 px-4 tabular-data font-semibold text-slate-700">{{ team.players.length }} Pemain</td>
                                <td class="py-3 px-4">
                                    <span
                                        class="px-2.5 py-1 rounded-md text-xs font-bold"
                                        :class="team.achievement_title ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-slate-100 text-slate-500'"
                                    >
                                        {{ team.achievement_title || 'Peserta Resmi (Default)' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button
                                        type="button"
                                        class="btn-sm-secondary text-xs inline-flex items-center gap-1"
                                        @click="openAchievementModal(team)"
                                    >
                                        Edit Predikat
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ================= MODAL: AUTO-DRAW GROUPS ================= -->
        <div v-if="showDrawModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Drawing &amp; Bentuk Grup Otomatis</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showDrawModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitDrawGroups" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Jumlah Grup yang Dibentuk</label>
                        <select v-model="drawForm.group_count" class="form-input text-xs w-full">
                            <option :value="1">1 Grup (Full Round Robin)</option>
                            <option :value="2">2 Grup (Grup A, Grup B)</option>
                            <option :value="3">3 Grup (Grup A, B, C)</option>
                            <option :value="4">4 Grup (Grup A, B, C, D)</option>
                            <option :value="8">8 Grup (Grup A s/d H)</option>
                        </select>
                        <InputError :message="drawForm.errors.group_count" />
                    </div>

                    <div class="p-3 bg-orange-50 rounded-xl border border-orange-200 text-xs text-orange-950 space-y-1">
                        <p class="font-bold">Total Tim Tersedia: {{ eligibleTeams.length }} Tim</p>
                        <p class="text-[11px] leading-relaxed">
                            Sistem akan mengacak urutan tim secara matematis (Fisher-Yates shuffle) dan menempatkannya secara merata ke dalam setiap grup.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="showDrawModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="drawForm.processing">
                            {{ drawForm.processing ? 'Mengundi...' : 'Mulai Pengundian' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: GENERATE ROUND ROBIN ================= -->
        <div v-if="showGenMatchesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Jadwalkan Fase Grup Otomatis</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showGenMatchesModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitGenMatches" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Waktu Mulai Pertandingan Pertama</label>
                        <input type="datetime-local" v-model="genMatchesForm.start_time" class="form-input text-xs w-full" />
                        <InputError :message="genMatchesForm.errors.start_time" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Interval Antar Pertandingan (Menit)</label>
                        <input type="number" v-model="genMatchesForm.interval_minutes" min="15" max="240" step="5" class="form-input text-xs w-full" />
                        <InputError :message="genMatchesForm.errors.interval_minutes" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lapangan / Pitch (Pisahkan koma)</label>
                        <input type="text" v-model="genMatchesForm.pitches" placeholder="Lapangan A, Lapangan B" class="form-input text-xs w-full" />
                        <InputError :message="genMatchesForm.errors.pitches" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="showGenMatchesModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="genMatchesForm.processing">
                            {{ genMatchesForm.processing ? 'Memproses...' : 'Buat Jadwal Otomatis' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: TAMBAH PERTANDINGAN MANUAL ================= -->
        <div v-if="showAddMatchModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Tambah Jadwal Pertandingan Baru</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showAddMatchModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitAddMatch" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Babak / Fase</label>
                            <select v-model="addMatchForm.stage" class="form-input text-xs w-full">
                                <option v-for="(label, key) in stages" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <InputError :message="addMatchForm.errors.stage" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Grup (Opsional)</label>
                            <select v-model="addMatchForm.event_group_id" class="form-input text-xs w-full">
                                <option value="">Bukan Fase Grup</option>
                                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                            </select>
                            <InputError :message="addMatchForm.errors.event_group_id" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tim Tuan Rumah (Home)</label>
                            <select v-model="addMatchForm.home_team_id" class="form-input text-xs w-full">
                                <option value="">-- Menunggu / TBD --</option>
                                <option v-for="t in eligibleTeams" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <InputError :message="addMatchForm.errors.home_team_id" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Tim Tamu (Away)</label>
                            <select v-model="addMatchForm.away_team_id" class="form-input text-xs w-full">
                                <option value="">-- Menunggu / TBD --</option>
                                <option v-for="t in eligibleTeams" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                            <InputError :message="addMatchForm.errors.away_team_id" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Waktu Kick-Off</label>
                            <input type="datetime-local" v-model="addMatchForm.kickoff_at" class="form-input text-xs w-full" />
                            <InputError :message="addMatchForm.errors.kickoff_at" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Lapangan / Venue</label>
                            <input type="text" v-model="addMatchForm.pitch_name" class="form-input text-xs w-full" />
                            <InputError :message="addMatchForm.errors.pitch_name" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Wasit (Opsional)</label>
                        <input type="text" v-model="addMatchForm.referee_name" class="form-input text-xs w-full" />
                        <InputError :message="addMatchForm.errors.referee_name" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="showAddMatchModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="addMatchForm.processing">
                            Simpan Jadwal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: INPUT SKOR & KEJADIAN ================= -->
        <div v-if="showScoreModal && activeMatchForScore" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-4 my-8 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">
                            Input Skor: Pertandingan #{{ activeMatchForScore.match_number }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ activeMatchForScore.home_team_name }} vs {{ activeMatchForScore.away_team_name }}
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showScoreModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form @submit.prevent="submitScore" class="space-y-4">
                    <!-- Status & Waktu -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Status Pertandingan</label>
                            <select v-model="scoreForm.status" class="form-input text-xs w-full">
                                <option v-for="(lbl, stKey) in matchStatuses" :key="stKey" :value="stKey">{{ lbl }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Lapangan</label>
                            <input type="text" v-model="scoreForm.pitch_name" class="form-input text-xs w-full" />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Wasit Utama</label>
                            <input type="text" v-model="scoreForm.referee_name" class="form-input text-xs w-full" />
                        </div>
                    </div>

                    <!-- Papan Skor Utama -->
                    <div class="p-4 bg-slate-900 rounded-2xl text-white">
                        <p class="text-[11px] text-center font-bold text-orange-400 uppercase tracking-wider mb-3">Hasil Akhir Pertandingan</p>
                        <div class="grid grid-cols-5 items-center gap-2 text-center">
                            <div class="col-span-2">
                                <h4 class="text-xs font-bold text-slate-200 truncate">{{ activeMatchForScore.home_team_name }}</h4>
                                <input
                                    type="number"
                                    min="0"
                                    max="99"
                                    v-model="scoreForm.home_score"
                                    class="w-20 mt-1 mx-auto text-center text-3xl font-black text-slate-900 bg-white rounded-xl py-1"
                                />
                            </div>
                            <div class="col-span-1 text-slate-500 font-bold text-xl">-</div>
                            <div class="col-span-2">
                                <h4 class="text-xs font-bold text-slate-200 truncate">{{ activeMatchForScore.away_team_name }}</h4>
                                <input
                                    type="number"
                                    min="0"
                                    max="99"
                                    v-model="scoreForm.away_score"
                                    class="w-20 mt-1 mx-auto text-center text-3xl font-black text-slate-900 bg-white rounded-xl py-1"
                                />
                            </div>
                        </div>

                        <!-- Babak Penalti jika Fase Gugur -->
                        <div v-if="activeMatchForScore.stage !== 'group_stage'" class="mt-4 pt-3 border-t border-slate-800 text-center">
                            <p class="text-[11px] text-slate-400 mb-1">Skor Adu Penalti (Opsional jika imbang):</p>
                            <div class="flex items-center justify-center gap-3">
                                <input
                                    type="number"
                                    min="0"
                                    max="99"
                                    placeholder="Home Pen"
                                    v-model="scoreForm.home_penalty_score"
                                    class="w-20 text-center text-xs font-bold text-slate-900 bg-slate-100 rounded-lg py-1"
                                />
                                <span class="text-slate-500">-</span>
                                <input
                                    type="number"
                                    min="0"
                                    max="99"
                                    placeholder="Away Pen"
                                    v-model="scoreForm.away_penalty_score"
                                    class="w-20 text-center text-xs font-bold text-slate-900 bg-slate-100 rounded-lg py-1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Kejadian Pertandingan (Goals, Cards) -->
                    <div class="space-y-3 pt-2">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Detail Kejadian Gol &amp; Kartu</h4>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    class="btn-sm-secondary text-xs inline-flex items-center gap-1"
                                    @click="addEventRow(activeMatchForScore.home_team_id)"
                                >
                                    + Kejadian Tim Home
                                </button>
                                <button
                                    type="button"
                                    class="btn-sm-secondary text-xs inline-flex items-center gap-1"
                                    @click="addEventRow(activeMatchForScore.away_team_id)"
                                >
                                    + Kejadian Tim Away
                                </button>
                            </div>
                        </div>

                        <div v-if="scoreForm.events.length === 0" class="p-4 bg-slate-50 rounded-xl text-center text-xs text-slate-500">
                            Belum ada kejadian (gol / kartu) yang ditambahkan.
                        </div>

                        <div v-else class="space-y-2 max-h-56 overflow-y-auto pr-1">
                            <div
                                v-for="(ev, evIdx) in scoreForm.events"
                                :key="evIdx"
                                class="p-2.5 rounded-xl border border-slate-200 bg-slate-50/60 grid grid-cols-1 sm:grid-cols-12 gap-2 items-center text-xs"
                            >
                                <div class="sm:col-span-3">
                                    <select v-model="ev.team_id" class="form-input text-xs w-full py-1">
                                        <option :value="activeMatchForScore.home_team_id">{{ activeMatchForScore.home_team_name }}</option>
                                        <option :value="activeMatchForScore.away_team_id">{{ activeMatchForScore.away_team_name }}</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-3">
                                    <select v-model="ev.player_id" class="form-input text-xs w-full py-1">
                                        <option :value="null">-- Pilih Pemain --</option>
                                        <option
                                            v-for="p in getPlayersForTeam(ev.team_id)"
                                            :key="p.id"
                                            :value="p.id"
                                        >
                                            #{{ p.jersey_number }} {{ p.full_name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="sm:col-span-3">
                                    <select v-model="ev.event_type" class="form-input text-xs w-full py-1">
                                        <option v-for="(lbl, tKey) in eventTypes" :key="tKey" :value="tKey">{{ lbl }}</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <input
                                        type="number"
                                        v-model="ev.minute"
                                        placeholder="Menit"
                                        min="1"
                                        max="150"
                                        class="form-input text-xs w-full py-1 text-center font-mono"
                                    />
                                </div>
                                <div class="sm:col-span-1 text-center">
                                    <button
                                        type="button"
                                        class="text-slate-400 hover:text-rose-600 p-1"
                                        title="Hapus Kejadian"
                                        @click="removeEventRow(evIdx)"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Pertandingan / Berita Acara Wasit</label>
                        <textarea v-model="scoreForm.match_notes" rows="2" class="form-input text-xs w-full"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" class="btn-secondary text-xs" @click="showScoreModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="scoreForm.processing">
                            {{ scoreForm.processing ? 'Menyimpan & Menghitung Klasemen...' : 'Simpan Skor & Klasemen' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: PENGUMUMAN ================= -->
        <div v-if="showAnnouncementModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Publikasikan Pengumuman / Undangan TM</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showAnnouncementModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitAnnouncement" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Judul Pengumuman</label>
                        <input type="text" v-model="announcementForm.title" placeholder="Contoh: Undangan Technical Meeting & Jadwal Drawing Resmi" class="form-input text-xs w-full" />
                        <InputError :message="announcementForm.errors.title" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Isi Pesan / Instruksi</label>
                        <textarea v-model="announcementForm.content" rows="4" placeholder="Tulis rincian lokasi, waktu, dresscode, dan berkas yang wajib dibawa..." class="form-input text-xs w-full"></textarea>
                        <InputError :message="announcementForm.errors.content" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Tautan Virtual Meeting (Zoom / Google Meet)</label>
                        <input type="url" v-model="announcementForm.meeting_link" placeholder="https://meet.google.com/..." class="form-input text-xs w-full" />
                        <InputError :message="announcementForm.errors.meeting_link" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_pinned" v-model="announcementForm.is_pinned" class="rounded text-orange-600 focus:ring-orange-500" />
                        <label for="is_pinned" class="text-xs font-medium text-slate-700">Sematkan sebagai Pengumuman Penting (Pinned Banner)</label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="showAnnouncementModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="announcementForm.processing">
                            Publikasikan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: PREDIKAT JUARA ================= -->
        <div v-if="showAchievementModal && activeTeamForAchievement" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Set Predikat Piagam Tim: {{ activeTeamForAchievement.name }}</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showAchievementModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitAchievement" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Predikat Penghargaan</label>
                        <input
                            type="text"
                            v-model="achievementForm.achievement_title"
                            placeholder="Contoh: Juara 1 / Juara 2 / Semifinalis / Peserta Resmi"
                            class="form-input text-xs w-full"
                        />
                        <p class="text-[11px] text-slate-500 mt-1">
                            Predikat ini otomatis tertulis dengan huruf emas di piagam penghargaan PDF setiap pemain tim ini.
                        </p>
                        <InputError :message="achievementForm.errors.achievement_title" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="showAchievementModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="achievementForm.processing">
                            Simpan Predikat
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: PINDAH GRUP ================= -->
        <div v-if="showMoveTeamModal && teamToMove" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Pindahkan Tim Antar Grup</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showMoveTeamModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitMoveTeam" class="space-y-4">
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                        <span class="text-slate-500">Tim yang dipindahkan:</span>
                        <strong class="block text-slate-900 font-bold text-sm mt-0.5">{{ teamToMove.team_name }}</strong>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Grup Tujuan</label>
                        <select v-model="moveTeamForm.target_group_id" class="form-input text-xs w-full">
                            <option value="">-- Pilih Grup Baru --</option>
                            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                        </select>
                        <InputError :message="moveTeamForm.errors.target_group_id" />
                        <InputError :message="moveTeamForm.errors.group" />
                        <InputError :message="moveTeamForm.errors.team_id" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="showMoveTeamModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="moveTeamForm.processing || !moveTeamForm.target_group_id">
                            {{ moveTeamForm.processing ? 'Memindahkan...' : 'Pindahkan Tim' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: GENERATE KNOCKOUT STAGE ================= -->
        <div v-if="showKnockoutModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Generate Babak Gugur (Knockout) Otomatis</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showKnockoutModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitGenKnockout" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Babak Fase Gugur</label>
                        <select v-model="genKnockoutForm.stage" class="form-input text-xs w-full">
                            <option value="round_of_16">Babak 16 Besar (Round of 16)</option>
                            <option value="quarter_final">Babak 8 Besar (Quarter Final)</option>
                            <option value="semi_final">Babak Semifinal (Semi Final)</option>
                            <option value="third_place">Perebutan Juara 3 (Third Place)</option>
                            <option value="final">Partai Puncak (Final)</option>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Sistem secara otomatis memasangkan Juara dan Runner-up grup secara silang (misal: 1A vs 2B, 1B vs 2A).
                        </p>
                        <InputError :message="genKnockoutForm.errors.stage" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Waktu Mulai Pertandingan Pertama</label>
                        <input type="datetime-local" v-model="genKnockoutForm.start_time" class="form-input text-xs w-full" />
                        <InputError :message="genKnockoutForm.errors.start_time" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Interval Waktu Antar Laga (Menit)</label>
                        <input type="number" v-model="genKnockoutForm.interval_minutes" min="15" max="240" step="5" class="form-input text-xs w-full" />
                        <InputError :message="genKnockoutForm.errors.interval_minutes" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lapangan / Venue</label>
                        <input type="text" v-model="genKnockoutForm.pitches" placeholder="Lapangan Utama" class="form-input text-xs w-full" />
                        <InputError :message="genKnockoutForm.errors.pitches" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="showKnockoutModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="genKnockoutForm.processing">
                            {{ genKnockoutForm.processing ? 'Menjadwalkan...' : 'Buat Jadwal Knockout' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL: RESCHEDULE MATCH ================= -->
        <div v-if="showRescheduleModal && activeMatchForReschedule" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">
                        Reschedule Pertandingan #{{ activeMatchForReschedule.match_number }}
                    </h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showRescheduleModal = false">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
                <form @submit.prevent="submitReschedule" class="space-y-4">
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                        <span class="text-slate-500 font-mono">Laga:</span>
                        <strong class="block text-slate-900 font-bold mt-0.5">
                            {{ activeMatchForReschedule.home_team_name }} vs {{ activeMatchForReschedule.away_team_name }}
                        </strong>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Jadwal Kick-Off Baru</label>
                        <input type="datetime-local" v-model="rescheduleForm.kickoff_at" class="form-input text-xs w-full" />
                        <InputError :message="rescheduleForm.errors.kickoff_at" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Lapangan / Venue</label>
                        <input type="text" v-model="rescheduleForm.pitch_name" class="form-input text-xs w-full" />
                        <InputError :message="rescheduleForm.errors.pitch_name" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Status Pertandingan</label>
                        <select v-model="rescheduleForm.status" class="form-input text-xs w-full">
                            <option value="scheduled">Terjadwal (Scheduled)</option>
                            <option value="ongoing">Sedang Berlangsung (Ongoing)</option>
                            <option value="completed">Selesai (Completed)</option>
                            <option value="postponed">Ditunda (Postponed)</option>
                        </select>
                        <InputError :message="rescheduleForm.errors.status" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Wasit</label>
                        <input type="text" v-model="rescheduleForm.referee_name" placeholder="Thoriq Alkatiri" class="form-input text-xs w-full" />
                        <InputError :message="rescheduleForm.errors.referee_name" />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Alasan Penjadwalan Ulang / Catatan</label>
                        <textarea v-model="rescheduleForm.match_notes" rows="2" placeholder="Kondisi cuaca hujan lebat / pergeseran waktu..." class="form-input text-xs w-full"></textarea>
                        <InputError :message="rescheduleForm.errors.match_notes" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" class="btn-secondary text-xs" @click="showRescheduleModal = false">Batal</button>
                        <button type="submit" class="btn-pitch text-xs" :disabled="rescheduleForm.processing">
                            {{ rescheduleForm.processing ? 'Menyimpan...' : 'Simpan Perubahan Jadwal' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
