<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    stages: {
        type: Array,
        default: () => [],
    },
    participants: {
        type: Array,
        default: () => [],
    },
    stageStatuses: {
        type: Object,
        default: () => ({}),
    },
});

const searchQuery = ref('');
const filterPosition = ref('ALL');
const filterStatus = ref('ALL');
const filterConsensus = ref('ALL');

const selectedIds = ref([]);
const selectAll = ref(false);

const actionModalOpen = ref(false);

const advanceForm = useForm({
    participant_ids: [],
    target_stage_status: 'day_1_passed',
    dispatch_email: true,
    notes: '',
});

const filteredParticipants = computed(() => {
    return props.participants.filter((p) => {
        if (searchQuery.value.trim()) {
            const q = searchQuery.value.trim().toLowerCase();
            const bibMatch = String(p.bib_number || '').includes(q);
            const nameMatch = (p.full_name || '').toLowerCase().includes(q);
            const nickMatch = (p.nickname || '').toLowerCase().includes(q);
            if (!bibMatch && !nameMatch && !nickMatch) return false;
        }

        if (filterPosition.value !== 'ALL') {
            const pos = (p.preferred_position || '').toUpperCase();
            if (filterPosition.value === 'KIPER' && !pos.includes('KIPER') && !pos.includes('GK')) return false;
            if (filterPosition.value === 'BEK' && !pos.includes('BEK') && !pos.includes('CB') && !pos.includes('LB') && !pos.includes('RB')) return false;
            if (filterPosition.value === 'GELANDANG' && !pos.includes('GELANDANG') && !pos.includes('MF')) return false;
            if (filterPosition.value === 'PENYERANG' && !pos.includes('PENYERANG') && !pos.includes('FW') && !pos.includes('STRIKER') && !pos.includes('SAYAP')) return false;
        }

        if (filterStatus.value !== 'ALL' && p.stage_status !== filterStatus.value) {
            return false;
        }

        if (filterConsensus.value === 'HAS_A' && (p.consensus?.grade_a_count || 0) < 1) {
            return false;
        }
        if (filterConsensus.value === 'HIGH_STARS' && (p.consensus?.avg_stars || 0) < 4.0) {
            return false;
        }

        return true;
    });
});

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = filteredParticipants.value.map((p) => p.id);
    } else {
        selectedIds.value = [];
    }
};

const openAdvanceModal = (targetStatus = 'day_1_passed') => {
    if (selectedIds.value.length === 0) return;
    advanceForm.participant_ids = [...selectedIds.value];
    advanceForm.target_stage_status = targetStatus;
    actionModalOpen.value = true;
};

const submitAdvance = () => {
    advanceForm.post(route('admin.events.auditions.advance', { event: props.event.id }), {
        onSuccess: () => {
            actionModalOpen.value = false;
            selectedIds.value = [];
            selectAll.value = false;
        },
    });
};
</script>

<template>
    <AdminLayout :title="`Keputusan Kelolosan: ${event.name}`">
        <Head :title="`Kelolosan Audisi - ${event.name}`" />

        <div class="space-y-6">
            <!-- Header Bar -->
            <div class="frosted-card p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('admin.events.index')" class="text-xs font-bold text-slate-500 hover:text-slate-800 transition">
                            &larr; Kembali ke Daftar Kompetisi
                        </Link>
                        <span class="text-slate-300">•</span>
                        <span class="text-xs font-bold text-orange-600 uppercase tracking-wider">Multi-Stage Audition Control</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Keputusan Kelolosan &amp; Piagam Audisi</h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-0.5">
                        Konsolidasi hasil penilaian scout, promosi kelolosan hari-demi-hari, pengiriman email massal, dan penerbitan sertifikat digital.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="route('admin.auditions.scanner', { event_id: event.id })"
                        class="px-3 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition flex items-center gap-1.5"
                    >
                        <span>🛡️ Buka Scanner Lapangan</span>
                    </Link>
                </div>
            </div>

            <!-- Bulk Action Toolbar (Floating or visible when selected > 0) -->
            <div
                v-if="selectedIds.length > 0"
                class="frosted-card p-4 bg-orange-50/95 border-2 border-orange-400 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-lg sticky top-4 z-20"
            >
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-orange-600 text-white font-black text-xs flex items-center justify-center shrink-0">
                        {{ selectedIds.length }}
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900 uppercase tracking-wide">Peserta Dipilih Untuk Keputusan</div>
                        <div class="text-[11px] text-slate-600">Pilih status kelolosan yang akan diterapkan secara masal:</div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition"
                        @click="openAdvanceModal('day_1_passed')"
                    >
                        ✓ Lolos ke Hari 2
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-sm transition"
                        @click="openAdvanceModal('day_1_eliminated')"
                    >
                        ✕ Gugur Hari 1
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-sm transition"
                        @click="openAdvanceModal('day_2_passed')"
                    >
                        ✓ Lolos ke Final Day
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 bg-gradient-to-r from-orange-600 to-red-600 text-white rounded-xl text-xs font-black shadow-md transition"
                        @click="openAdvanceModal('final_selected')"
                    >
                        ⭐ Terpilih Skuad Utama
                    </button>
                </div>
            </div>

            <!-- Filters Toolbar -->
            <div class="frosted-card p-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Cari Peserta</label>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Ketik Bib # atau nama atlet..."
                        class="input-pitch w-full text-xs"
                    />
                </div>

                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Posisi</label>
                    <select v-model="filterPosition" class="input-pitch w-full text-xs">
                        <option value="ALL">Semua Posisi</option>
                        <option value="KIPER">Kiper</option>
                        <option value="BEK">Bek</option>
                        <option value="GELANDANG">Gelandang</option>
                        <option value="PENYERANG">Penyerang</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Status Kelolosan</label>
                    <select v-model="filterStatus" class="input-pitch w-full text-xs">
                        <option value="ALL">Semua Status</option>
                        <option v-for="(lbl, val) in stageStatuses" :key="val" :value="val">
                            {{ lbl }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Rekomendasi Scout</label>
                    <select v-model="filterConsensus" class="input-pitch w-full text-xs">
                        <option value="ALL">Semua Konsensus</option>
                        <option value="HAS_A">Minimal 1 Scout Memilih A</option>
                        <option value="HIGH_STARS">Rating Bintang &ge; 4.0</option>
                    </select>
                </div>
            </div>

            <!-- Participants Data Table -->
            <div class="frosted-card overflow-hidden shadow-sm border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 uppercase text-[10px] font-black tracking-wider">
                                <th class="p-3 w-10 text-center">
                                    <input
                                        v-model="selectAll"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <th class="p-3 w-16 text-center">No. Bib</th>
                                <th class="p-3">Identitas Atlet</th>
                                <th class="p-3">Posisi &amp; SSB</th>
                                <th class="p-3 text-center">Absensi Sesi</th>
                                <th class="p-3">Konsensus Pemandu Bakat</th>
                                <th class="p-3">Status Saat Ini</th>
                                <th class="p-3 text-right">Piagam</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredParticipants.length === 0">
                                <td colspan="8" class="p-8 text-center text-slate-400">
                                    Tidak ada data peserta yang sesuai dengan filter.
                                </td>
                            </tr>
                            <tr
                                v-for="p in filteredParticipants"
                                :key="p.id"
                                class="hover:bg-slate-50/80 transition"
                                :class="selectedIds.includes(p.id) ? 'bg-orange-50/40' : ''"
                            >
                                <td class="p-3 text-center">
                                    <input
                                        v-model="selectedIds"
                                        type="checkbox"
                                        :value="p.id"
                                        class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                                    />
                                </td>

                                <td class="p-3 text-center">
                                    <div class="w-12 h-10 rounded-lg bg-orange-50 border border-orange-300 flex items-center justify-center font-mono font-black text-sm text-orange-700 mx-auto">
                                        #{{ p.bib_number }}
                                    </div>
                                </td>

                                <td class="p-3">
                                    <div class="font-bold text-slate-900 text-sm">{{ p.full_name }}</div>
                                    <div v-if="p.nickname" class="text-orange-600 font-semibold text-[11px]">"{{ p.nickname }}"</div>
                                    <div class="text-[11px] text-slate-400">Usia {{ p.age }} Thn ({{ p.birth_date }})</div>
                                </td>

                                <td class="p-3">
                                    <span class="inline-block px-2 py-0.5 rounded bg-slate-100 text-slate-800 font-bold text-[11px] mb-0.5">
                                        {{ p.preferred_position }}
                                    </span>
                                    <div class="text-[11px] text-slate-500 font-medium truncate max-w-[140px]">{{ p.current_club_ssb }}</div>
                                </td>

                                <td class="p-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <span
                                            v-for="stg in stages"
                                            :key="stg.id"
                                            class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] font-bold"
                                            :class="p.attended_stages.includes(stg.id) ? 'bg-emerald-100 text-emerald-800 font-black' : 'bg-slate-100 text-slate-400'"
                                            :title="`Tahap ${stg.stage_number}: ${p.attended_stages.includes(stg.id) ? 'Hadir' : 'Belum/Tidak Hadir'}`"
                                        >
                                            H{{ stg.stage_number }}
                                        </span>
                                    </div>
                                </td>

                                <td class="p-3">
                                    <div v-if="p.consensus?.total_scouts > 0" class="space-y-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">
                                                {{ p.consensus.grade_a_count }}A
                                            </span>
                                            <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold text-[10px]">
                                                {{ p.consensus.grade_b_count }}B
                                            </span>
                                            <span class="px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 font-bold text-[10px]">
                                                {{ p.consensus.grade_c_count }}C
                                            </span>
                                            <span class="text-amber-500 font-bold ml-1">★ {{ p.consensus.avg_stars }}</span>
                                        </div>
                                        <div v-if="p.consensus.tags.length > 0" class="flex flex-wrap gap-1">
                                            <span v-for="t in p.consensus.tags.slice(0, 2)" :key="t" class="text-[9px] px-1 py-0.2 rounded bg-slate-100 text-slate-600">
                                                {{ t }}
                                            </span>
                                        </div>
                                    </div>
                                    <div v-else class="text-[11px] text-slate-400 italic">
                                        Belum ada nilai scout
                                    </div>
                                </td>

                                <td class="p-3">
                                    <span
                                        class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold"
                                        :class="{
                                            'bg-emerald-100 text-emerald-800': p.stage_status.includes('passed') || p.stage_status === 'final_selected',
                                            'bg-rose-100 text-rose-800': p.stage_status.includes('eliminated'),
                                            'bg-slate-100 text-slate-700': p.stage_status === 'registered',
                                        }"
                                    >
                                        {{ p.stage_status_label }}
                                    </span>
                                </td>

                                <td class="p-3 text-right">
                                    <a
                                        :href="route('audition.certificate.download', { participant: p.id })"
                                        target="_blank"
                                        class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-orange-50 hover:text-orange-600 border border-slate-200 transition inline-flex items-center gap-1"
                                        title="Unduh E-Sertifikat Resmi"
                                    >
                                        <span>📜 Piagam</span>
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Advance Stage Decision Confirmation Modal -->
        <div v-if="actionModalOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-black text-slate-900">Konfirmasi Keputusan Kelolosan</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="actionModalOpen = false">&times;</button>
                </div>

                <div class="p-3 rounded-xl bg-orange-50 border border-orange-200 text-xs text-orange-900 space-y-1">
                    <div>Total Peserta Terpilih: <strong class="text-orange-950 font-black">{{ advanceForm.participant_ids.length }} Peserta</strong></div>
                    <div>Target Status: <strong class="text-orange-950 font-black">{{ stageStatuses[advanceForm.target_stage_status] || advanceForm.target_stage_status }}</strong></div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Status Kelolosan Tujuan:</label>
                        <select v-model="advanceForm.target_stage_status" class="input-pitch w-full text-xs">
                            <option v-for="(lbl, val) in stageStatuses" :key="val" :value="val">
                                {{ lbl }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Tambahan untuk Email (Opsional):</label>
                        <textarea
                            v-model="advanceForm.notes"
                            rows="3"
                            placeholder="Contoh: Jadwal Hari 2 dimulai pukul 08:00 WIB di Lapangan A. Jangan lupa membawa Bib Tag asli..."
                            class="input-pitch w-full text-xs"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            id="email_dispatch_chk"
                            v-model="advanceForm.dispatch_email"
                            type="checkbox"
                            class="rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                        />
                        <label for="email_dispatch_chk" class="text-xs font-semibold text-slate-700 select-none">
                            Kirim email notifikasi otomatis ke akun orang tua atlet
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        class="py-2 text-xs font-bold rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50"
                        @click="actionModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="py-2 text-xs font-bold rounded-xl bg-orange-600 hover:bg-orange-700 text-white shadow-sm"
                        :disabled="advanceForm.processing"
                        @click="submitAdvance"
                    >
                        <span v-if="advanceForm.processing">Memproses...</span>
                        <span v-else>Terapkan Keputusan</span>
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
