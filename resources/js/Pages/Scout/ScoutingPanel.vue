<script setup>
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    stages: {
        type: Array,
        default: () => [],
    },
    currentStage: {
        type: Object,
        default: null,
    },
    participants: {
        type: Array,
        default: () => [],
    },
    isSessionLocked: {
        type: Boolean,
        default: false,
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
});

// Search and filter states
const searchBib = ref('');
const selectedPosition = ref('ALL');
const isLocked = ref(props.isSessionLocked);
const activeStageId = ref(props.currentStage?.id || props.stages[0]?.id || 0);

// Local evaluations map: participantId -> evaluation object
const localEvals = ref({});
props.participants.forEach((p) => {
    localEvals.value[p.id] = p.evaluation
        ? { ...p.evaluation }
        : {
            quick_grade: null,
            rating_stars: 0,
            tags: [],
            coach_notes: '',
            is_saving: false,
            saved_at: null,
        };
});

const presetTags = [
    '#KecepatanTinggi',
    '#VisiBagus',
    '#FisikKuat',
    '#PassingAkurat',
    '#Leadership',
    '#Skill1on1',
    '#AgresifPositif',
    '#PerluFisik',
];

const positionFilters = [
    { label: 'Semua', value: 'ALL' },
    { label: 'Kiper', value: 'KIPER' },
    { label: 'Bek', value: 'BEK' },
    { label: 'Gelandang', value: 'GELANDANG' },
    { label: 'Penyerang', value: 'PENYERANG' },
];

const filteredParticipants = computed(() => {
    return props.participants.filter((p) => {
        // Search bib number or name
        if (searchBib.value.trim()) {
            const query = searchBib.value.trim().toLowerCase();
            const bibMatch = String(p.bib_number || '').includes(query);
            const nameMatch = (p.full_name || '').toLowerCase().includes(query);
            const nickMatch = (p.nickname || '').toLowerCase().includes(query);
            if (!bibMatch && !nameMatch && !nickMatch) return false;
        }

        // Filter position
        if (selectedPosition.value !== 'ALL') {
            const pos = (p.preferred_position || '').toUpperCase();
            if (selectedPosition.value === 'KIPER' && !pos.includes('KIPER') && !pos.includes('GK')) return false;
            if (selectedPosition.value === 'BEK' && !pos.includes('BEK') && !pos.includes('DEFENDER') && !pos.includes('CB') && !pos.includes('LB') && !pos.includes('RB')) return false;
            if (selectedPosition.value === 'GELANDANG' && !pos.includes('GELANDANG') && !pos.includes('MIDFIELDER') && !pos.includes('MF')) return false;
            if (selectedPosition.value === 'PENYERANG' && !pos.includes('PENYERANG') && !pos.includes('FORWARD') && !pos.includes('STRIKER') && !pos.includes('FW') && !pos.includes('SAYAP')) return false;
        }

        return true;
    });
});

const onStageSwitch = (stageId) => {
    router.get(route('scout.events.show', { event: props.event.id }), {
        stage_id: stageId,
    }, { preserveState: false });
};

// Save evaluation to server
const saveEvaluation = async (participantId) => {
    if (isLocked.value) return;

    const data = localEvals.value[participantId];
    if (!data || !activeStageId.value) return;

    data.is_saving = true;

    try {
        const response = await axios.post(route('scout.events.grade', { event: props.event.id }), {
            audition_participant_id: participantId,
            audition_stage_id: activeStageId.value,
            quick_grade: data.quick_grade,
            rating_stars: data.rating_stars,
            tags: data.tags,
            coach_notes: data.coach_notes,
        });

        if (response.data.success) {
            data.saved_at = 'Tersimpan ✓';
            setTimeout(() => {
                if (data.saved_at === 'Tersimpan ✓') data.saved_at = null;
            }, 2500);
        }
    } catch (err) {
        data.saved_at = 'Gagal menyimpan!';
    } finally {
        data.is_saving = false;
    }
};

const setGrade = (participantId, grade) => {
    if (isLocked.value) return;
    const item = localEvals.value[participantId];
    if (!item) return;

    // Toggle off if same clicked
    item.quick_grade = item.quick_grade === grade ? null : grade;
    saveEvaluation(participantId);
};

const setStars = (participantId, star) => {
    if (isLocked.value) return;
    const item = localEvals.value[participantId];
    if (!item) return;

    item.rating_stars = item.rating_stars === star ? 0 : star;
    saveEvaluation(participantId);
};

const toggleTag = (participantId, tag) => {
    if (isLocked.value) return;
    const item = localEvals.value[participantId];
    if (!item) return;

    if (!Array.isArray(item.tags)) item.tags = [];
    const index = item.tags.indexOf(tag);
    if (index >= 0) {
        item.tags.splice(index, 1);
    } else {
        item.tags.push(tag);
    }
    saveEvaluation(participantId);
};

// Debounced note save
let timeoutMap = {};
const onNoteChange = (participantId) => {
    if (isLocked.value) return;
    clearTimeout(timeoutMap[participantId]);
    timeoutMap[participantId] = setTimeout(() => {
        saveEvaluation(participantId);
    }, 800);
};

// Session lock
const lockModalOpen = ref(false);
const isLocking = ref(false);

const handleLockSession = async () => {
    if (!activeStageId.value) return;
    isLocking.value = true;
    try {
        await axios.post(route('scout.events.lock-session', { event: props.event.id }), {
            audition_stage_id: activeStageId.value,
        });
        isLocked.value = true;
        lockModalOpen.value = false;
    } catch (err) {
        alert(err.response?.data?.message || 'Gagal mengunci sesi.');
    } finally {
        isLocking.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-800 pb-20">
        <Head :title="`Scouting: ${event.name}`" />

        <!-- Sticky Header Bar (Mobile Optimized) -->
        <header class="sticky top-0 z-30 bg-slate-900/95 backdrop-blur-md text-white border-b border-slate-800 shadow-lg">
            <div class="max-w-4xl mx-auto px-4 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-orange-600 text-[10px] font-black uppercase tracking-wider">
                                SCOUT PANEL
                            </span>
                            <span v-if="isLocked" class="px-2 py-0.5 rounded bg-red-600 text-[10px] font-bold">
                                🔒 TERKUNCI
                            </span>
                        </div>
                        <h1 class="text-base font-black truncate mt-0.5">{{ event.name }}</h1>
                    </div>

                    <!-- Stage Switcher & Action -->
                    <div class="flex items-center gap-2 shrink-0">
                        <select
                            :value="activeStageId"
                            class="bg-slate-800 border border-slate-700 text-white rounded-lg px-2.5 py-1.5 text-xs font-bold focus:outline-none focus:ring-1 focus:ring-orange-500"
                            @change="onStageSwitch(Number($event.target.value))"
                        >
                            <option v-for="s in stages" :key="s.id" :value="s.id">
                                Tahap {{ s.stage_number }}: {{ s.stage_name }}
                            </option>
                        </select>

                        <button
                            v-if="!isLocked"
                            type="button"
                            class="px-2.5 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold shadow-sm transition"
                            @click="lockModalOpen = true"
                        >
                            Kunci
                        </button>
                    </div>
                </div>

                <!-- Stats Tally Bar -->
                <div class="mt-2.5 pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400">Dinilai:</span>
                        <strong class="text-white">{{ stats.graded_count }} / {{ stats.total_participants }}</strong>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-1 font-bold text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> A: {{ stats.grade_a_count }}
                        </span>
                        <span class="inline-flex items-center gap-1 font-bold text-amber-400">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span> B: {{ stats.grade_b_count }}
                        </span>
                        <span class="inline-flex items-center gap-1 font-bold text-rose-400">
                            <span class="w-2 h-2 rounded-full bg-rose-400"></span> C: {{ stats.grade_c_count }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Search & Position Filter Sticky Bar -->
            <div class="bg-slate-800/90 px-4 py-2 border-t border-slate-700/60">
                <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <!-- Search Input -->
                    <div class="relative flex-1">
                        <input
                            v-model="searchBib"
                            type="text"
                            placeholder="Cari No. Bib (cth: 42) atau Nama..."
                            class="w-full bg-slate-900 border border-slate-700 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-orange-500"
                        />
                        <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <!-- Position Chips -->
                    <div class="flex items-center gap-1 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                        <button
                            v-for="pf in positionFilters"
                            :key="pf.value"
                            type="button"
                            class="px-2.5 py-1 rounded-full text-[11px] font-bold whitespace-nowrap transition"
                            :class="selectedPosition === pf.value ? 'bg-orange-600 text-white' : 'bg-slate-700/80 text-slate-300 hover:bg-slate-700'"
                            @click="selectedPosition = pf.value"
                        >
                            {{ pf.label }}
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="max-w-4xl mx-auto px-4 py-4 space-y-4">
            <!-- Empty state -->
            <div v-if="filteredParticipants.length === 0" class="p-8 text-center bg-white rounded-2xl shadow-sm text-slate-400">
                <p class="text-sm font-bold">Tidak ada peserta yang cocok dengan filter atau pencarian.</p>
                <p class="text-xs mt-1">Coba sesuaikan kata kunci pencarian nomor dada.</p>
            </div>

            <!-- Participant Cards List -->
            <div
                v-for="p in filteredParticipants"
                :key="p.id"
                class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200 transition-shadow hover:shadow-md space-y-3"
            >
                <!-- Card Header: Bib & Basic Specs -->
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <!-- Bib Number Badge -->
                        <div class="relative shrink-0 text-center">
                            <div class="w-14 h-14 rounded-xl bg-orange-50 border-2 border-orange-500 flex flex-col items-center justify-center shadow-inner">
                                <span class="text-[9px] font-black uppercase text-orange-600 tracking-wider">BIB</span>
                                <span class="text-xl font-black text-orange-700 font-mono leading-none">#{{ p.bib_number }}</span>
                            </div>
                            <!-- Presence Dot Indicator -->
                            <span
                                class="inline-block mt-1 text-[9px] font-bold px-1.5 py-0.2 rounded-full"
                                :class="p.is_present ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ p.is_present ? '● Hadir' : '○ Absen' }}
                            </span>
                        </div>

                        <!-- Athlete Info -->
                        <div class="min-w-0">
                            <h2 class="text-base font-black text-slate-900 leading-tight truncate">
                                {{ p.full_name }}
                            </h2>
                            <p v-if="p.nickname" class="text-xs text-orange-600 font-bold truncate">
                                "{{ p.nickname }}"
                            </p>
                            <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-600 mt-1">
                                <span class="font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-800">
                                    {{ p.preferred_position }}
                                </span>
                                <span>• Kaki: <strong>{{ p.dominant_foot }}</strong></span>
                                <span>• {{ p.age }} Thn</span>
                                <span>• {{ p.current_club_ssb }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Autosave Indicator -->
                    <div class="text-[10px] font-bold shrink-0">
                        <span v-if="localEvals[p.id]?.is_saving" class="text-orange-500 animate-pulse">Menyimpan...</span>
                        <span v-else-if="localEvals[p.id]?.saved_at" class="text-emerald-600">{{ localEvals[p.id].saved_at }}</span>
                    </div>
                </div>

                <!-- Grading Buttons: A / B / C (Large Touch Targets for Field Use) -->
                <div class="grid grid-cols-3 gap-2 pt-1">
                    <button
                        type="button"
                        :disabled="isLocked"
                        class="py-2.5 px-3 rounded-xl font-black text-xs sm:text-sm flex items-center justify-center gap-1.5 border-2 transition active:scale-95"
                        :class="localEvals[p.id]?.quick_grade === 'A'
                            ? 'bg-emerald-600 border-emerald-600 text-white shadow-md'
                            : 'bg-white border-slate-200 text-slate-700 hover:border-emerald-300'"
                        @click="setGrade(p.id, 'A')"
                    >
                        <span>A</span>
                        <span class="text-[10px] font-semibold opacity-90 hidden sm:inline">(Lolos)</span>
                    </button>

                    <button
                        type="button"
                        :disabled="isLocked"
                        class="py-2.5 px-3 rounded-xl font-black text-xs sm:text-sm flex items-center justify-center gap-1.5 border-2 transition active:scale-95"
                        :class="localEvals[p.id]?.quick_grade === 'B'
                            ? 'bg-amber-500 border-amber-500 text-white shadow-md'
                            : 'bg-white border-slate-200 text-slate-700 hover:border-amber-300'"
                        @click="setGrade(p.id, 'B')"
                    >
                        <span>B</span>
                        <span class="text-[10px] font-semibold opacity-90 hidden sm:inline">(Review)</span>
                    </button>

                    <button
                        type="button"
                        :disabled="isLocked"
                        class="py-2.5 px-3 rounded-xl font-black text-xs sm:text-sm flex items-center justify-center gap-1.5 border-2 transition active:scale-95"
                        :class="localEvals[p.id]?.quick_grade === 'C'
                            ? 'bg-rose-600 border-rose-600 text-white shadow-md'
                            : 'bg-white border-slate-200 text-slate-700 hover:border-rose-300'"
                        @click="setGrade(p.id, 'C')"
                    >
                        <span>C</span>
                        <span class="text-[10px] font-semibold opacity-90 hidden sm:inline">(Gugur)</span>
                    </button>
                </div>

                <!-- Star Rating (1 to 5 Stars) -->
                <div class="flex items-center justify-between pt-1">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Performa Keseluruhan</span>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="star in 5"
                            :key="star"
                            type="button"
                            :disabled="isLocked"
                            class="p-1 focus:outline-none transition active:scale-125"
                            @click="setStars(p.id, star)"
                        >
                            <svg
                                class="w-6 h-6"
                                :class="star <= (localEvals[p.id]?.rating_stars || 0) ? 'text-amber-400 fill-amber-400' : 'text-slate-300 fill-none'"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Quick Parameter Chip Tags -->
                <div class="flex flex-wrap gap-1.5 pt-1">
                    <button
                        v-for="tag in presetTags"
                        :key="tag"
                        type="button"
                        :disabled="isLocked"
                        class="px-2.5 py-1 rounded-lg text-[10px] font-bold transition active:scale-95"
                        :class="localEvals[p.id]?.tags?.includes(tag)
                            ? 'bg-orange-600 text-white shadow-2xs'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        @click="toggleTag(p.id, tag)"
                    >
                        {{ tag }}
                    </button>
                </div>

                <!-- Coach Notes (Debounced Autosave) -->
                <div class="pt-1">
                    <input
                        v-model="localEvals[p.id].coach_notes"
                        type="text"
                        :disabled="isLocked"
                        placeholder="Catatan pelatih/scout (cth: pengambilan keputusan tenang, perlu latihan fisik)..."
                        class="w-full text-xs px-3 py-2 rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:outline-none focus:border-orange-500"
                        @input="onNoteChange(p.id)"
                    />
                </div>
            </div>
        </main>

        <!-- Lock Confirmation Modal -->
        <div v-if="lockModalOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-sm w-full p-5 shadow-2xl space-y-4">
                <div class="w-12 h-12 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center mx-auto">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div class="text-center">
                    <h3 class="text-base font-black text-slate-900">Kunci Penilaian Tahap Ini?</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Setelah dikunci, nilai tidak dapat diubah lagi dari smartphone Anda di lapangan untuk menjaga integritas data.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 pt-2">
                    <button
                        type="button"
                        class="py-2 text-xs font-bold rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50"
                        @click="lockModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="py-2 text-xs font-bold rounded-xl bg-orange-600 text-white hover:bg-orange-700 shadow-sm"
                        :disabled="isLocking"
                        @click="handleLockSession"
                    >
                        <span v-if="isLocking">Mengunci...</span>
                        <span v-else>Ya, Kunci Final</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

