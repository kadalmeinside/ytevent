<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    team: {
        type: Object,
        default: null,
    },
    teamsList: {
        type: Array,
        default: () => [],
    },
    matches: {
        type: Array,
        default: () => [],
    },
    eligiblePlayers: {
        type: Array,
        default: () => [],
    },
    standingsGroups: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('matches'); // 'matches', 'standings', 'certificates'
const activeMatchForLineup = ref(null);
const showLineupModal = ref(false);

const lineupForm = useForm({
    starting_player_ids: [],
    substitute_player_ids: [],
    captain_player_id: null,
});

const openLineupModal = (match) => {
    activeMatchForLineup.value = match;
    lineupForm.starting_player_ids = [...(match.starting_player_ids || [])];
    lineupForm.substitute_player_ids = [...(match.substitute_player_ids || [])];
    lineupForm.captain_player_id = match.captain_player_id || null;
    lineupForm.clearErrors();
    showLineupModal.value = true;
};

const closeLineupModal = () => {
    showLineupModal.value = false;
    activeMatchForLineup.value = null;
};

const togglePlayerRole = (playerId, targetRole) => {
    const id = Number(playerId);
    const inStarting = lineupForm.starting_player_ids.includes(id);
    const inSubs = lineupForm.substitute_player_ids.includes(id);

    if (targetRole === 'starting') {
        if (inStarting) {
            lineupForm.starting_player_ids = lineupForm.starting_player_ids.filter((x) => x !== id);
            if (lineupForm.captain_player_id === id) {
                lineupForm.captain_player_id = lineupForm.starting_player_ids[0] || null;
            }
        } else {
            lineupForm.starting_player_ids.push(id);
            lineupForm.substitute_player_ids = lineupForm.substitute_player_ids.filter((x) => x !== id);
            if (!lineupForm.captain_player_id) {
                lineupForm.captain_player_id = id;
            }
        }
    } else if (targetRole === 'sub') {
        if (inSubs) {
            lineupForm.substitute_player_ids = lineupForm.substitute_player_ids.filter((x) => x !== id);
        } else {
            lineupForm.substitute_player_ids.push(id);
            lineupForm.starting_player_ids = lineupForm.starting_player_ids.filter((x) => x !== id);
            if (lineupForm.captain_player_id === id) {
                lineupForm.captain_player_id = lineupForm.starting_player_ids[0] || null;
            }
        }
    }
};

const setCaptain = (playerId) => {
    const id = Number(playerId);
    // Auto promote to starting if not already
    if (!lineupForm.starting_player_ids.includes(id)) {
        lineupForm.starting_player_ids.push(id);
        lineupForm.substitute_player_ids = lineupForm.substitute_player_ids.filter((x) => x !== id);
    }
    lineupForm.captain_player_id = id;
};

const submitLineup = () => {
    if (!activeMatchForLineup.value) return;
    lineupForm.post(route('manager.matches.lineup.save', { match: activeMatchForLineup.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            closeLineupModal();
        },
    });
};

const getAvailabilityForActiveMatch = (playerId) => {
    if (!activeMatchForLineup.value) return null;
    return activeMatchForLineup.value.players_availability?.find((p) => p.id === Number(playerId));
};
</script>

<template>
    <ManagerLayout
        :team="team"
        :teams-list="teamsList"
        page-title="Jadwal & DSP Tanding"
    >
        <Head title="Jadwal Pertandingan & DSP" />

        <div class="space-y-6">
            <!-- Header Summary Banner -->
            <div class="frosted-card p-6 bg-gradient-to-r from-slate-900 to-slate-800 text-white relative overflow-hidden">
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-md text-xs font-black bg-orange-600 text-white uppercase tracking-wider">
                                Matchday Operations
                            </span>
                            <span v-if="team?.event" class="text-xs text-slate-300 font-medium">
                                {{ team.event.name }} ({{ team.event.age_category }})
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                            Jadwal Pertandingan &amp; Digital DSP
                        </h1>
                        <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
                            Kirim Daftar Susunan Pemain (DSP) resmi sebelum kick-off pertandingan, pantau akumulasi kartu dan sanksi disiplin, serta unduh lembar DSP dan E-Certificate pemain.
                        </p>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <div class="px-4 py-3 rounded-xl bg-white/10 backdrop-blur-xs border border-white/10 text-center">
                            <span class="block text-[11px] text-slate-400 font-semibold">Total Laga</span>
                            <span class="text-xl font-black text-white tabular-data font-mono">{{ matches.length }}</span>
                        </div>
                        <div class="px-4 py-3 rounded-xl bg-white/10 backdrop-blur-xs border border-white/10 text-center">
                            <span class="block text-[11px] text-slate-400 font-semibold">Pemain Sah</span>
                            <span class="text-xl font-black text-emerald-400 tabular-data font-mono">{{ eligiblePlayers.length }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Buttons -->
            <div class="frosted-card p-1.5 flex items-center gap-1 overflow-x-auto">
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === 'matches' ? 'bg-orange-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    @click="activeTab = 'matches'"
                >
                    Jadwal Pertandingan Tim ({{ matches.length }})
                </button>
                <button
                    v-if="standingsGroups.length > 0"
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === 'standings' ? 'bg-orange-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    @click="activeTab = 'standings'"
                >
                    Klasemen Grup &amp; Poin
                </button>
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
                    :class="activeTab === 'certificates' ? 'bg-orange-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                    @click="activeTab = 'certificates'"
                >
                    Piagam Penghargaan / E-Certificate ({{ eligiblePlayers.length }})
                </button>
            </div>

            <!-- TAB 1: MATCHES LIST -->
            <div v-if="activeTab === 'matches'" class="space-y-4">
                <div v-if="matches.length === 0" class="frosted-card p-12 text-center space-y-3">
                    <div class="w-12 h-12 mx-auto rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-lg">
                        VS
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">Belum Ada Jadwal Pertandingan</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        Panitia pelaksana belum menerbitkan jadwal pertandingan untuk grup atau tim Anda. Jadwal akan otomatis muncul di sini setelah sesi drawing selesai.
                    </p>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="m in matches"
                        :key="m.id"
                        class="frosted-card p-5 border border-slate-200/90 shadow-2xs hover:border-orange-300 transition-all"
                    >
                        <!-- Header Bar -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded font-black bg-slate-900 text-white font-mono">
                                    #{{ m.match_number }}
                                </span>
                                <span class="font-bold text-orange-950 bg-orange-50 px-2 py-0.5 rounded border border-orange-200">
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
                                <span class="font-medium text-slate-700">{{ m.pitch_name }}</span>
                                <span v-if="m.kickoff_formatted" class="tabular-data font-mono">{{ m.kickoff_formatted }}</span>
                            </div>
                        </div>

                        <!-- Teams Matchup -->
                        <div class="py-5 grid grid-cols-1 sm:grid-cols-7 items-center gap-3">
                            <!-- Home Team -->
                            <div
                                class="sm:col-span-3 flex items-center justify-start sm:justify-end gap-3 text-right"
                                :class="{ 'font-black': m.is_home }"
                            >
                                <div>
                                    <h4 class="text-sm text-slate-900 leading-tight" :class="m.is_home ? 'font-black text-orange-950' : 'font-bold'">
                                        {{ m.home_team_name }}
                                        <span v-if="m.is_home" class="text-[10px] text-orange-600 block">(Tim Anda)</span>
                                    </h4>
                                </div>
                                <div
                                    class="w-9 h-9 rounded-xl font-bold flex items-center justify-center shrink-0 text-xs"
                                    :class="m.is_home ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-700'"
                                >
                                    {{ m.home_team_name.charAt(0) }}
                                </div>
                            </div>

                            <!-- VS / Score -->
                            <div class="sm:col-span-1 flex flex-col items-center justify-center">
                                <div v-if="m.status === 'completed' || m.status === 'ongoing'" class="flex items-center gap-2">
                                    <span class="text-2xl font-black text-slate-900 tabular-data font-mono">{{ m.home_score ?? 0 }}</span>
                                    <span class="text-slate-400 font-bold">-</span>
                                    <span class="text-2xl font-black text-slate-900 tabular-data font-mono">{{ m.away_score ?? 0 }}</span>
                                </div>
                                <div v-else class="text-xs font-black text-slate-400 tracking-wider">
                                    VS
                                </div>
                            </div>

                            <!-- Away Team -->
                            <div
                                class="sm:col-span-3 flex items-center justify-start gap-3"
                                :class="{ 'font-black': !m.is_home }"
                            >
                                <div
                                    class="w-9 h-9 rounded-xl font-bold flex items-center justify-center shrink-0 text-xs"
                                    :class="!m.is_home ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-700'"
                                >
                                    {{ m.away_team_name.charAt(0) }}
                                </div>
                                <div>
                                    <h4 class="text-sm text-slate-900 leading-tight" :class="!m.is_home ? 'font-black text-orange-950' : 'font-bold'">
                                        {{ m.away_team_name }}
                                        <span v-if="!m.is_home" class="text-[10px] text-orange-600 block">(Tim Anda)</span>
                                    </h4>
                                </div>
                            </div>
                        </div>

                        <!-- Footer: DSP Status & Actions -->
                        <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500 font-medium">Status DSP:</span>
                                <span
                                    v-if="m.lineup_submitted"
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200"
                                >
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    DSP Sudah Diserahkan
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-200"
                                >
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    DSP Belum Diajukan
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <a
                                    v-if="m.lineup_submitted"
                                    :href="route('manager.matches.lineup.pdf', { match: m.id })"
                                    target="_blank"
                                    class="btn-sm-secondary inline-flex items-center gap-1 text-slate-700"
                                >
                                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Cetak DSP (PDF)
                                </a>

                                <button
                                    v-if="m.status !== 'completed'"
                                    type="button"
                                    class="btn-sm-pitch inline-flex items-center gap-1"
                                    @click="openLineupModal(m)"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    {{ m.lineup_submitted ? 'Ubah DSP' : 'Susun DSP Tanding' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: STANDINGS -->
            <div v-else-if="activeTab === 'standings'" class="space-y-6">
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div
                        v-for="group in standingsGroups"
                        :key="group.id"
                        class="frosted-card overflow-hidden border border-slate-200/90 shadow-2xs"
                    >
                        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between">
                            <span class="text-xs font-black tracking-wider text-orange-400 uppercase">{{ group.name }}</span>
                            <span class="text-[11px] text-slate-400 font-mono">{{ group.standings.length }} Tim</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                        <th class="py-2.5 px-3 w-8 text-center">#</th>
                                        <th class="py-2.5 px-3">Klub / Tim</th>
                                        <th class="py-2.5 px-2 text-center">M</th>
                                        <th class="py-2.5 px-2 text-center">Mn</th>
                                        <th class="py-2.5 px-2 text-center">S</th>
                                        <th class="py-2.5 px-2 text-center">K</th>
                                        <th class="py-2.5 px-2 text-center">GM</th>
                                        <th class="py-2.5 px-2 text-center">GK</th>
                                        <th class="py-2.5 px-2 text-center">SG</th>
                                        <th class="py-2.5 px-3 text-center font-black text-orange-950 bg-orange-50/60">PTS</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr
                                        v-for="(st, sIdx) in group.standings"
                                        :key="st.team_id"
                                        :class="{ 'bg-orange-50/60 font-bold': st.is_my_team }"
                                    >
                                        <td class="py-2.5 px-3 text-center font-mono font-bold">{{ sIdx + 1 }}</td>
                                        <td class="py-2.5 px-3 font-semibold text-slate-900 whitespace-nowrap">
                                            {{ st.team_name }}
                                            <span v-if="st.is_my_team" class="text-orange-600 font-bold ml-1">(Tim Anda)</span>
                                        </td>
                                        <td class="py-2.5 px-2 text-center tabular-data">{{ st.played }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data text-emerald-700 font-semibold">{{ st.won }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data text-slate-500">{{ st.drawn }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data text-rose-700 font-semibold">{{ st.lost }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data">{{ st.goals_for }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data">{{ st.goals_against }}</td>
                                        <td class="py-2.5 px-2 text-center tabular-data font-semibold">
                                            {{ st.goal_difference > 0 ? `+${st.goal_difference}` : st.goal_difference }}
                                        </td>
                                        <td class="py-2.5 px-3 text-center tabular-data font-black text-orange-900 bg-orange-50/60 text-sm">
                                            {{ st.points }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: E-CERTIFICATES -->
            <div v-else-if="activeTab === 'certificates'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">E-Certificate / Piagam Penghargaan Pemain</h2>
                        <p class="text-xs text-slate-500">Unduh piagam resmi bertanda tangan digital panitia dan QR verifikasi untuk seluruh pemain sah.</p>
                    </div>
                </div>

                <div v-if="eligiblePlayers.length === 0" class="frosted-card p-10 text-center text-xs text-slate-500">
                    Belum ada pemain yang berstatus lolos screening verifikasi berkas.
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="p in eligiblePlayers"
                        :key="p.id"
                        class="frosted-card p-4 border border-slate-200/90 shadow-2xs flex flex-col justify-between gap-3"
                    >
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-900 text-orange-400 font-mono font-black flex items-center justify-center text-sm shrink-0">
                                #{{ p.jersey_number }}
                            </div>
                            <div class="space-y-0.5 min-w-0">
                                <h4 class="text-sm font-bold text-slate-900 truncate">{{ p.full_name }}</h4>
                                <p class="text-xs text-slate-500 font-medium">{{ p.position }}</p>
                                <p class="text-[10px] text-slate-400 font-mono">{{ p.accreditation_code }}</p>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                Terverifikasi Sah
                            </span>
                            <a
                                :href="route('manager.players.certificate', { player: p.id })"
                                target="_blank"
                                class="btn-sm-secondary text-xs inline-flex items-center gap-1 font-bold text-orange-950"
                            >
                                <svg class="w-3.5 h-3.5 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Unduh Piagam
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MODAL: LINEUP / DSP ================= -->
        <div v-if="showLineupModal && activeMatchForLineup" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
            <div class="bg-white rounded-2xl max-w-3xl w-full p-6 shadow-2xl space-y-4 my-8 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Pengajuan Daftar Susunan Pemain (DSP)
                        </h3>
                        <p class="text-xs text-slate-500">
                            Laga #{{ activeMatchForLineup.match_number }} vs {{ activeMatchForLineup.opponent_name }} &bull; {{ activeMatchForLineup.pitch_name }}
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeLineupModal">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Validation Error Banner -->
                <InputError :message="lineupForm.errors.lineup" />

                <!-- Counters Summary Bar -->
                <div class="p-3 bg-slate-900 text-white rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-4">
                        <div>
                            <span class="text-slate-400">Starting Lineup:</span>
                            <strong class="ml-1 text-emerald-400 tabular-data font-mono text-sm">{{ lineupForm.starting_player_ids.length }}</strong>
                        </div>
                        <div>
                            <span class="text-slate-400">Cadangan:</span>
                            <strong class="ml-1 text-amber-400 tabular-data font-mono text-sm">{{ lineupForm.substitute_player_ids.length }}</strong>
                        </div>
                    </div>
                    <div>
                        <span class="text-slate-400">Kapten Tim:</span>
                        <strong class="ml-1 text-orange-400 font-bold">
                            {{ eligiblePlayers.find(p => p.id === lineupForm.captain_player_id)?.full_name || 'Belum Dipilih' }}
                        </strong>
                    </div>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                <th class="py-2.5 px-3 w-12 text-center">No</th>
                                <th class="py-2.5 px-3">Nama Pemain &amp; Posisi</th>
                                <th class="py-2.5 px-3 text-center">Status Kelayakan</th>
                                <th class="py-2.5 px-3 text-center">Starting</th>
                                <th class="py-2.5 px-3 text-center">Cadangan</th>
                                <th class="py-2.5 px-3 text-center">Kapten (C)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="p in eligiblePlayers"
                                :key="p.id"
                                class="hover:bg-slate-50/50"
                                :class="{ 'bg-rose-50/40 opacity-75': getAvailabilityForActiveMatch(p.id)?.is_suspended }"
                            >
                                <td class="py-2.5 px-3 text-center font-mono font-bold text-slate-800">
                                    #{{ p.jersey_number }}
                                </td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-900">{{ p.full_name }}</div>
                                    <div class="text-[11px] text-slate-500 font-medium">{{ p.position }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <div v-if="getAvailabilityForActiveMatch(p.id)?.is_suspended" class="inline-flex flex-col items-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-200">
                                            SANKSI DISIPLIN
                                        </span>
                                        <span class="text-[10px] text-rose-700 mt-0.5">
                                            {{ getAvailabilityForActiveMatch(p.id)?.suspension_reason }}
                                        </span>
                                    </div>
                                    <div v-else class="flex items-center justify-center gap-1.5 text-[11px]">
                                        <span class="text-emerald-700 font-bold">&check; Layak</span>
                                        <span
                                            v-if="getAvailabilityForActiveMatch(p.id)?.yellow_cards > 0"
                                            class="px-1.5 py-0.2 rounded text-[10px] bg-amber-100 text-amber-900 font-bold"
                                            title="Kartu Kuning Berjalan"
                                        >
                                            {{ getAvailabilityForActiveMatch(p.id)?.yellow_cards }} KK
                                        </span>
                                    </div>
                                </td>
                                <!-- Starting Checkbox -->
                                <td class="py-2.5 px-3 text-center">
                                    <button
                                        type="button"
                                        :disabled="getAvailabilityForActiveMatch(p.id)?.is_suspended"
                                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-colors"
                                        :class="lineupForm.starting_player_ids.includes(p.id) ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        @click="togglePlayerRole(p.id, 'starting')"
                                    >
                                        {{ lineupForm.starting_player_ids.includes(p.id) ? 'Starting XI' : 'Pilih' }}
                                    </button>
                                </td>
                                <!-- Sub Checkbox -->
                                <td class="py-2.5 px-3 text-center">
                                    <button
                                        type="button"
                                        :disabled="getAvailabilityForActiveMatch(p.id)?.is_suspended"
                                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-colors"
                                        :class="lineupForm.substitute_player_ids.includes(p.id) ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                        @click="togglePlayerRole(p.id, 'sub')"
                                    >
                                        {{ lineupForm.substitute_player_ids.includes(p.id) ? 'Cadangan' : 'Pilih' }}
                                    </button>
                                </td>
                                <!-- Captain Radio -->
                                <td class="py-2.5 px-3 text-center">
                                    <input
                                        type="radio"
                                        name="captain_radio"
                                        :checked="lineupForm.captain_player_id === p.id"
                                        :disabled="getAvailabilityForActiveMatch(p.id)?.is_suspended"
                                        class="text-orange-600 focus:ring-orange-500 cursor-pointer"
                                        @change="setCaptain(p.id)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <p class="text-xs text-slate-500">
                        Pastikan susunan pemain telah disepakati oleh tim pelatih sebelum disimpan.
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-secondary text-xs" @click="closeLineupModal">
                            Batal
                        </button>
                        <button
                            type="button"
                            class="btn-pitch text-xs"
                            :disabled="lineupForm.processing || lineupForm.starting_player_ids.length === 0"
                            @click="submitLineup"
                        >
                            {{ lineupForm.processing ? 'Menyimpan DSP...' : 'Simpan &amp; Sahkan DSP' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </ManagerLayout>
</template>

