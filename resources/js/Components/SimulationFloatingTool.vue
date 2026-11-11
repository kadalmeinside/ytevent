<script setup>
import { computed, ref, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';

const props = defineProps({
    contextArea: {
        type: String,
        default: 'public', // 'public' | 'manager' | 'admin'
    },
    currentTeamId: {
        type: [Number, String],
        default: null,
    },
    currentEventId: {
        type: [Number, String],
        default: null,
    },
});

const page = usePage();
const simCtx = computed(() => page.props.simulationContext || { enabled: false, master_enabled: false });
const isOpen = ref(false);
const isBusy = ref(false);

const selectedEventId = ref(
    props.currentEventId ||
        page.props.event?.id ||
        simCtx.value?.events?.[0]?.id ||
        ''
);

const selectedTeamId = ref(
    props.currentTeamId ||
        page.props.team?.id ||
        simCtx.value?.active_team?.id ||
        simCtx.value?.teams?.[0]?.id ||
        ''
);

watch(
    () => [props.currentTeamId, page.props.team?.id, simCtx.value?.active_team?.id],
    ([propTeamId, pageTeamId, activeTeamId]) => {
        if (propTeamId) {
            selectedTeamId.value = propTeamId;
        } else if (pageTeamId) {
            selectedTeamId.value = pageTeamId;
        } else if (!selectedTeamId.value && activeTeamId) {
            selectedTeamId.value = activeTeamId;
        }
    },
    { immediate: true }
);

watch(
    () => [props.currentEventId, page.props.event?.id, simCtx.value?.events],
    ([propEvtId, pageEvtId, evts]) => {
        if (propEvtId) {
            selectedEventId.value = propEvtId;
        } else if (pageEvtId) {
            selectedEventId.value = pageEvtId;
        } else if (!selectedEventId.value && evts?.length > 0) {
            selectedEventId.value = evts[0].id;
        }
    },
    { immediate: true }
);

const runSimAction = (routeName, payload = {}) => {
    if (isBusy.value) return;
    isBusy.value = true;
    router.post(route(routeName), payload, {
        preserveScroll: true,
        onFinish: () => {
            isBusy.value = false;
        },
    });
};

const toggleSimulationMode = (enabledState) => {
    runSimAction('simulation.toggle', { enabled: enabledState });
};

const confirmDeleteTeam = () => {
    if (!selectedTeamId.value) return;
    const currentTeam = simCtx.value?.teams?.find((t) => t.id === Number(selectedTeamId.value));
    const teamName = currentTeam ? currentTeam.name : `#${selectedTeamId.value}`;
    if (
        window.confirm(
            `Yakin ingin menghapus permanen tim "${teamName}" beserta seluruh invoice, bukti pembayaran, pemain, dan berkasnya?\n\nSlot kuota pendaftaran kompetisi akan otomatis terbuka kembali.`
        )
    ) {
        runSimAction('simulation.delete-team', { team_id: selectedTeamId.value });
    }
};

const confirmDeleteAllTeams = () => {
    if (!selectedEventId.value) return;
    const currentEvent = simCtx.value?.events?.find((e) => e.id === Number(selectedEventId.value));
    const eventName = currentEvent ? currentEvent.name : `#${selectedEventId.value}`;
    if (
        window.confirm(
            `PERINGATAN: Yakin ingin menghapus SELURUH tim terdaftar pada kompetisi "${eventName}" beserta seluruh data pembayaran dan pemainnya?\n\nKuota kompetisi akan kembali kosong (0 tim).`
        )
    ) {
        runSimAction('simulation.delete-all-teams', { event_id: selectedEventId.value });
    }
};

/**
 * Jika pengguna berada di halaman form pendaftaran (/events/{slug}/register),
 * kita juga menyediakan pengisi form cepat di sisi klien selain 1-klik server-side.
 */
const fillCurrentDomFormIfPresent = () => {
    const rand = Math.floor(100 + Math.random() * 900);
    const setInputValue = (selector, val) => {
        const el = document.querySelector(selector);
        if (!el) return false;
        el.value = val;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    };

    setInputValue('input[placeholder*="Macan Muda"]', `SSB Macan Muda Kemayoran ${rand}`);
    setInputValue('input[placeholder*="Askot"]', 'Askot PSSI Jakarta Pusat');
    setInputValue('textarea[placeholder*="Alamat"]', 'Jl. H. R. Rasuna Said Kav. C-22, Jakarta Selatan');
    setInputValue('input[placeholder*="Bambang"]', `Coach Hendri Susilo ${rand}`);
    setInputValue('input[placeholder*="AFC"]', `AFC C - PSSI ${rand}`);
    setInputValue('input[type="email"]', `sim.manager.${rand}@persijadevelopment.id`);
    setInputValue('input[placeholder*="0812"]', `081288990${rand}`);
};
</script>

<template>
    <!-- Hanya tampil jika master_enabled aktif dan simulation_mode_enabled = true -->
    <div v-if="simCtx?.enabled" class="fixed bottom-5 right-5 z-[90] select-none font-sans">
        <!-- Drawer Panel ketika Dibuka -->
        <div
            v-if="isOpen"
            class="mb-3 w-80 sm:w-96 rounded-2xl bg-slate-900/95 backdrop-blur-xl text-white border border-orange-500/40 shadow-2xl overflow-hidden"
        >
            <!-- Header Panel -->
            <div class="px-4 py-3 bg-gradient-to-r from-orange-600 to-red-600 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider leading-none">Modul Simulasi &amp; Isi Paksa</h3>
                        <p class="text-[10px] text-orange-100 mt-0.5">Area Aktif: {{ contextArea.toUpperCase() }} (Take-Out Ready)</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="text-white/80 hover:text-white text-xs font-bold px-2 py-0.5 rounded bg-black/20 cursor-pointer"
                    @click="isOpen = false"
                >
                    Tutup
                </button>
            </div>

            <div class="p-4 space-y-4 max-h-[75vh] overflow-y-auto text-xs">
                <!-- Pemilih Konteks Kompetisi & Tim Target -->
                <div class="grid grid-cols-1 gap-2.5 p-3 rounded-xl bg-slate-800/90 border border-slate-700">
                    <div v-if="simCtx.events?.length">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                            Target Kompetisi (Untuk Daftar Tim Baru)
                        </label>
                        <select
                            v-model="selectedEventId"
                            class="w-full rounded-lg bg-slate-900 border border-slate-700 text-white text-xs px-2.5 py-1.5 focus:outline-none focus:border-orange-500"
                        >
                            <option v-for="evt in simCtx.events" :key="evt.id" :value="evt.id">
                                {{ evt.name }} ({{ evt.age_category }})
                            </option>
                        </select>
                    </div>

                    <div v-if="simCtx.teams?.length">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">
                            Target Tim (Untuk Isi Pemain / Bayar / Verifikasi)
                        </label>
                        <select
                            v-model="selectedTeamId"
                            class="w-full rounded-lg bg-slate-900 border border-slate-700 text-white text-xs px-2.5 py-1.5 focus:outline-none focus:border-orange-500"
                        >
                            <option v-for="t in simCtx.teams" :key="t.id" :value="t.id">
                                #{{ t.id }} {{ t.name }} [{{ t.status }} / {{ t.screening_status }}]
                            </option>
                        </select>
                    </div>
                </div>

                <!-- BAGIAN 1: PENDAFTARAN TIM & SIMULASI MANAJER -->
                <div class="space-y-1.5">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-orange-400">
                        1. Simulasi Pendaftaran, Pembayaran &amp; Skuad Pemain
                    </p>

                    <button
                        type="button"
                        class="w-full text-left px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 flex items-center justify-between gap-2 transition-colors cursor-pointer"
                        :disabled="isBusy"
                        @click="runSimAction('simulation.register-team', { event_id: selectedEventId, login_as_manager: contextArea !== 'admin' })"
                    >
                        <div>
                            <span class="font-bold text-white block">Daftar Tim Baru Paksa + Buat Akun</span>
                            <span class="text-[10px] text-slate-400">Otomatis buat tim, logo, pelatih &amp; invoice tagihan</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-orange-600 text-white shrink-0">1-KLIK</span>
                    </button>

                    <button
                        v-if="contextArea === 'public'"
                        type="button"
                        class="w-full text-left px-3 py-1.5 rounded-xl bg-slate-800/60 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-between gap-2 transition-colors cursor-pointer"
                        @click="fillCurrentDomFormIfPresent"
                    >
                        <span class="text-[11px] font-semibold text-slate-300">Isi Otomatis Kolom Form di Halaman Ini</span>
                        <span class="text-[10px] text-slate-400">DOM Fill</span>
                    </button>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="text-left p-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors cursor-pointer"
                            :disabled="isBusy || !selectedTeamId"
                            @click="runSimAction('simulation.pay-team', { team_id: selectedTeamId, instant_settle: false })"
                        >
                            <span class="font-bold text-amber-300 block">Unggah Bukti Bayar</span>
                            <span class="text-[10px] text-slate-400 block mt-0.5">Masuk antrean Finance</span>
                        </button>

                        <button
                            type="button"
                            class="text-left p-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors cursor-pointer"
                            :disabled="isBusy || !selectedTeamId"
                            @click="runSimAction('simulation.pay-team', { team_id: selectedTeamId, instant_settle: true })"
                        >
                            <span class="font-bold text-emerald-400 block">Lunasi Tagihan Paksa</span>
                            <span class="text-[10px] text-slate-400 block mt-0.5">Langsung PAID &amp; Aktif</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="w-full text-left px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 flex items-center justify-between gap-2 transition-colors cursor-pointer"
                        :disabled="isBusy"
                        @click="runSimAction('simulation.fill-roster', { team_id: selectedTeamId, auto_submit: true })"
                    >
                        <div>
                            <span class="font-bold text-white block">Isi Paksa Semua Pemain + 5 Dokumen + GK</span>
                            <span class="text-[10px] text-slate-400">Otomatis isi kuota pemain, foto, berkas &amp; ajukan screening</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-sky-600 text-white shrink-0">SKUAD</span>
                    </button>

                    <!-- INJECT SISAH KEKURANGAN TIM & PEMAIN EVENT -->
                    <button
                        type="button"
                        class="w-full text-left px-3 py-2.5 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white shadow-md flex items-center justify-between gap-2 transition-all cursor-pointer"
                        :disabled="isBusy || !selectedEventId"
                        @click="runSimAction('simulation.fill-event-slots', { event_id: selectedEventId })"
                    >
                        <div>
                            <span class="font-black text-xs block uppercase tracking-wide">Inject Penuhi Sisa Kuota Tim &amp; Pemain</span>
                            <span class="text-[10px] text-orange-100 block">Buat tim baru hingga kuota event penuh + lunas + pemain lengkap</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-black/25 shrink-0">AUTO KUOTA</span>
                    </button>
                </div>

                <!-- BAGIAN 2: SIMULASI TINDAKAN ADMIN & VERIFIKASI -->
                <div class="space-y-1.5 pt-2 border-t border-slate-800">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-400">
                        2. Simulasi Verifikasi Admin (Finance &amp; Screening)
                    </p>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="text-left p-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors cursor-pointer"
                            :disabled="isBusy"
                            @click="runSimAction('simulation.admin-approve-payments', { team_id: selectedTeamId })"
                        >
                            <span class="font-bold text-emerald-300 block">Setujui Pembayaran</span>
                            <span class="text-[10px] text-slate-400 block mt-0.5">Verifikasi Finance Tim Ini</span>
                        </button>

                        <button
                            type="button"
                            class="text-left p-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 transition-colors cursor-pointer"
                            :disabled="isBusy"
                            @click="runSimAction('simulation.admin-approve-screening', { team_id: selectedTeamId })"
                        >
                            <span class="font-bold text-emerald-300 block">Sahkan Semua Pemain</span>
                            <span class="text-[10px] text-slate-400 block mt-0.5">Loloskan berkas &amp; lisensi</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="w-full text-left px-3 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-md flex items-center justify-between gap-2 transition-all cursor-pointer"
                        :disabled="isBusy"
                        @click="runSimAction('simulation.fast-forward-team', { team_id: selectedTeamId, event_id: selectedEventId })"
                    >
                        <div>
                            <span class="font-black text-xs block uppercase tracking-wide">Simulasi Penuh Sampai Kunci Skuad Final (1 Tim)</span>
                            <span class="text-[10px] text-emerald-100 block">Bayar Lunas &rarr; Isi Skuad &rarr; Lolos Screening &rarr; Kunci Roster</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-black/25">FINISH</span>
                    </button>

                    <!-- SAHKAN SEMUA TIM DI EVENT SEKALIGUS -->
                    <button
                        type="button"
                        class="w-full text-left px-3 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white shadow-md flex items-center justify-between gap-2 transition-all cursor-pointer"
                        :disabled="isBusy || !selectedEventId"
                        @click="runSimAction('simulation.bulk-approve-lock-all', { event_id: selectedEventId })"
                    >
                        <div>
                            <span class="font-black text-xs block uppercase tracking-wide">Sahkan Seluruh Tim &amp; Kunci Skuad Turnamen</span>
                            <span class="text-[10px] text-indigo-100 block">Loloskan screening &amp; kunci roster SEMUA tim di event (Siap Drawing)</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-black/25 shrink-0">SAHKAN SEMUA</span>
                    </button>
                </div>

                <!-- BAGIAN 3: PINDAH PERAN INSTAN (PERSONA SWITCHER) -->
                <div class="space-y-1.5 pt-2 border-t border-slate-800">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                        3. Pindah Akun / Portal Instan (Tanpa Ketik Login)
                    </p>
                    <div class="grid grid-cols-2 gap-1.5">
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[11px] font-bold text-orange-300 cursor-pointer text-center"
                            :disabled="isBusy"
                            @click="runSimAction('simulation.switch-persona', { role: 'team_manager', team_id: selectedTeamId })"
                        >
                            Login Manajer Tim Ini
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[11px] font-bold text-white cursor-pointer text-center"
                            :disabled="isBusy"
                            @click="runSimAction('simulation.switch-persona', { role: 'superadmin' })"
                        >
                            Login Superadmin
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[11px] font-bold text-amber-300 cursor-pointer text-center"
                            :disabled="isBusy"
                            @click="runSimAction('simulation.switch-persona', { role: 'finance' })"
                        >
                            Login Finance
                        </button>
                        <button
                            type="button"
                            class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[11px] font-bold text-sky-300 cursor-pointer text-center"
                            :disabled="isBusy"
                            @click="runSimAction('simulation.switch-persona', { role: 'screening_officer' })"
                        >
                            Login Tim Screening
                        </button>
                    </div>
                </div>

                <!-- BAGIAN 4: BERSIHKAN / RESET DATA SIMULASI (DANGER ZONE) -->
                <div class="space-y-1.5 pt-2 border-t border-slate-800">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-rose-400">
                            4. Hapus / Reset Tim &amp; Pembayaran
                        </p>
                        <span class="text-[9px] text-rose-300/80 font-mono font-bold">DANGER ZONE</span>
                    </div>

                    <!-- HAPUS TIM TERPILIH BESERTA PEMBAYARAN -->
                    <button
                        type="button"
                        class="w-full text-left px-3 py-2 rounded-xl bg-rose-950/40 hover:bg-rose-900/60 border border-rose-800/60 text-white flex items-center justify-between gap-2 transition-all cursor-pointer group"
                        :disabled="isBusy || !selectedTeamId"
                        @click="confirmDeleteTeam"
                    >
                        <div>
                            <span class="font-bold text-rose-200 group-hover:text-white block">
                                Hapus Tim Terpilih &amp; Data Pembayarannya
                            </span>
                            <span class="text-[10px] text-rose-300/70 block mt-0.5">
                                Hapus permanen invoice, bukti bayar, pemain &amp; buka slot kuota
                            </span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-rose-600/90 text-white shrink-0">
                            HAPUS TIM
                        </span>
                    </button>

                    <!-- RESET SELURUH TIM DI EVENT -->
                    <button
                        v-if="simCtx.events?.length"
                        type="button"
                        class="w-full text-left px-3 py-2 rounded-xl bg-slate-800/80 hover:bg-rose-950/40 border border-slate-700 hover:border-rose-800/60 text-slate-300 hover:text-rose-200 flex items-center justify-between gap-2 transition-all cursor-pointer group"
                        :disabled="isBusy || !selectedEventId"
                        @click="confirmDeleteAllTeams"
                    >
                        <div>
                            <span class="font-bold text-slate-200 group-hover:text-rose-200 block">
                                Kosongkan Semua Tim di Event Ini
                            </span>
                            <span class="text-[10px] text-slate-400 group-hover:text-rose-300/70 block mt-0.5">
                                Hapus semua tim &amp; seluruh pembayaran event (Reset Kuota 0)
                            </span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-slate-700 group-hover:bg-rose-700 text-white shrink-0">
                            RESET ALL
                        </span>
                    </button>
                </div>

                <!-- BAGIAN 5: MATIKAN SEMENTARA FITUR SIMULASI -->
                <div class="pt-2 border-t border-slate-800 flex items-center justify-between gap-2 text-[11px]">
                    <span class="text-slate-400">Sembunyikan tombol simulasi?</span>
                    <button
                        type="button"
                        class="text-rose-400 hover:text-rose-300 font-bold underline cursor-pointer"
                        :disabled="isBusy"
                        @click="toggleSimulationMode(false)"
                    >
                        Matikan Sementara
                    </button>
                </div>
            </div>
        </div>

        <!-- Floating Trigger Button -->
        <div class="flex justify-end">
            <button
                type="button"
                class="group inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-gradient-to-r from-slate-900 via-slate-900 to-orange-950 text-white border-2 border-orange-500 shadow-xl hover:shadow-orange-500/25 hover:scale-[1.02] transition-all cursor-pointer"
                @click="isOpen = !isOpen"
            >
                <span class="w-6 h-6 rounded-full bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-white shadow-xs">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </span>
                <div class="text-left leading-tight">
                    <span class="block text-[11px] font-black uppercase tracking-wider text-orange-400">Simulasi Cepat</span>
                    <span class="block text-[10px] font-semibold text-slate-300">{{ isBusy ? 'Memproses Data...' : 'Isi Data Paksa' }}</span>
                </div>
            </button>
        </div>
    </div>
</template>

