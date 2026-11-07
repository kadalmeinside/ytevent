<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Services\AccreditationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamManagementController extends Controller
{
    use ResolvesActiveTeam;

    public function __construct(
        protected AccreditationService $accreditationService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Team::class);

        $query = Team::query()
            ->with(['event', 'latestPayment'])
            ->withCount([
                'players',
                'players as players_passed_count' => fn ($q) => $q->where('screening_status', 'passed'),
                'players as players_submitted_count' => fn ($q) => $q->where('screening_status', 'submitted'),
                'players as players_rejected_count' => fn ($q) => $q->where('screening_status', 'rejected'),
                'players as players_draft_count' => fn ($q) => $q->where('screening_status', 'draft'),
                'players as gk_passed_count' => fn ($q) => $q->where('position', 'GK')->where('screening_status', 'passed'),
                'officials',
            ])
            ->orderByDesc('created_at');

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($screeningStatus = $request->query('screening_status')) {
            $query->where('screening_status', $screeningStatus);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('origin_association', 'like', "%{$search}%")
                    ->orWhere('manager_name', 'like', "%{$search}%")
                    ->orWhere('manager_email', 'like', "%{$search}%")
                    ->orWhere('manager_whatsapp', 'like', "%{$search}%");
            });
        }

        $teams = $query->get()->map(fn (Team $team) => [
            'id' => $team->id,
            'name' => $team->name,
            'origin_association' => $team->origin_association,
            'manager_name' => $team->manager_name,
            'manager_whatsapp' => $team->manager_whatsapp,
            'manager_email' => $team->manager_email,
            'head_coach_name' => $team->head_coach_name,
            'status' => $team->status,
            'screening_status' => $team->screening_status,
            'players_count' => (int) $team->players_count,
            'players_passed_count' => (int) $team->players_passed_count,
            'players_submitted_count' => (int) $team->players_submitted_count,
            'players_rejected_count' => (int) $team->players_rejected_count,
            'players_draft_count' => (int) $team->players_draft_count,
            'gk_passed_count' => (int) $team->gk_passed_count,
            'officials_count' => (int) $team->officials_count,
            'roster_locked_at' => $team->roster_locked_at?->translatedFormat('d M Y H:i'),
            'slot_expires_at' => $team->slot_expires_at?->toIso8601String(),
            'created_at' => $team->created_at?->toIso8601String(),
            'logo_url' => $team->logo_path ? Storage::disk('public')->url($team->logo_path) : null,
            'event' => $team->event ? [
                'id' => $team->event->id,
                'name' => $team->event->name,
                'age_category' => $team->event->age_category,
                'min_players_per_team' => $team->event->min_players_per_team,
                'max_players_per_team' => $team->event->max_players_per_team,
            ] : null,
            'latest_payment' => $team->latestPayment ? [
                'id' => $team->latestPayment->id,
                'invoice_number' => $team->latestPayment->invoice_number,
                'status' => $team->latestPayment->status,
                'total_amount' => (float) $team->latestPayment->total_amount,
                'method' => $team->latestPayment->method,
                'payment_channel' => $team->latestPayment->payment_channel,
                'sender_bank_name' => $team->latestPayment->sender_bank_name,
                'sender_account_holder' => $team->latestPayment->sender_account_holder,
                'sender_account_number' => $team->latestPayment->sender_account_number,
                'transfer_date' => $team->latestPayment->transfer_date?->toDateString(),
                'rejection_reason' => $team->latestPayment->rejection_reason,
                'paid_at' => $team->latestPayment->paid_at?->toIso8601String(),
                'expires_at' => $team->latestPayment->expires_at?->toIso8601String(),
                'proof_url' => $team->latestPayment->proof_file_path
                    ? route('private.payment-proof', ['payment' => $team->latestPayment->id])
                    : null,
                'proof_is_pdf' => $team->latestPayment->proof_file_path
                    ? strtolower(pathinfo($team->latestPayment->proof_file_path, PATHINFO_EXTENSION)) === 'pdf'
                    : false,
                'team' => [
                    'name' => $team->name,
                ],
                'event' => $team->event ? [
                    'name' => $team->event->name,
                ] : null,
            ] : null,
        ]);

        $eventsFilter = Event::query()
            ->select(['id', 'name', 'age_category'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
            'eventsFilter' => $eventsFilter,
            'filters' => [
                'event_id' => (string) $request->query('event_id', ''),
                'status' => (string) $request->query('status', ''),
                'screening_status' => (string) $request->query('screening_status', ''),
                'search' => (string) $request->query('search', ''),
            ],
        ]);
    }

    /**
     * Menampilkan profil dossier 360 derajat tim untuk panitia (Status, Skuad Pemain, Berkas, Ofisial & Invoice).
     */
    public function show(Team $team): Response
    {
        $this->authorize('view', $team);

        $team->load([
            'event',
            'latestPayment',
            'payments.verifier',
            'players' => fn ($q) => $q->orderBy('jersey_number'),
            'players.documents',
            'players.verifier',
            'officials',
        ]);

        $event = $team->event;
        $requiredDocs = collect($event?->required_documents ?? []);

        $players = $team->players->map(function ($player) use ($requiredDocs) {
            $docsByType = $player->documents->keyBy('document_type');

            $documentsList = $requiredDocs->map(function (string $docType) use ($docsByType) {
                $doc = $docsByType->get($docType);

                return [
                    'id' => $doc?->id,
                    'type' => $docType,
                    'label' => Event::documentLabel($docType),
                    'uploaded' => (bool) $doc,
                    'original_name' => $doc?->original_name,
                    'mime_type' => $doc?->mime_type,
                    'is_pdf' => $doc ? strtolower(pathinfo($doc->file_path, PATHINFO_EXTENSION)) === 'pdf' : false,
                    'validation_status' => $doc?->validation_status ?? 'missing',
                    'notes' => $doc?->notes,
                    'url' => $doc ? route('private.player-document', ['document' => $doc->id]) : null,
                ];
            })->values();

            return [
                'id' => $player->id,
                'full_name' => $player->full_name,
                'jersey_name' => $player->jersey_name,
                'jersey_number' => $player->jersey_number,
                'position' => $player->position,
                'dominant_foot' => $player->dominant_foot,
                'birth_place' => $player->birth_place,
                'birth_date' => $player->birth_date?->toDateString(),
                'birth_date_formatted' => $player->birth_date?->format('d/m/Y'),
                'age' => $player->birth_date ? (int) $player->birth_date->diffInYears(now()) : null,
                'nik' => $player->nik,
                'nisn' => $player->nisn,
                'height_cm' => $player->height_cm,
                'weight_kg' => $player->weight_kg,
                'screening_status' => $player->screening_status,
                'rejection_reason' => $player->rejection_reason,
                'is_locked' => (bool) $player->is_locked,
                'accreditation_code' => $player->accreditation_code,
                'verified_at' => $player->verified_at?->translatedFormat('d M Y H:i'),
                'verifier_name' => $player->verifier?->name,
                'photo_url' => $player->photo_path ? route('private.player-photo', ['player' => $player->id]) : null,
                'documents' => $documentsList,
            ];
        })->values();

        $officials = $team->officials->map(fn (TeamOfficial $official) => [
            'id' => $official->id,
            'full_name' => $official->full_name,
            'role' => $official->role,
            'role_label' => TeamOfficial::ROLES[$official->role] ?? $official->role,
            'license_number' => $official->license_number,
            'phone' => $official->phone,
            'screening_status' => $official->screening_status,
            'verification_notes' => $official->verification_notes,
            'accreditation_code' => $official->accreditation_code,
            'verify_url' => route('public.official.verify', ['code' => $official->accreditation_code]),
            'photo_url' => $official->photo_path ? route('private.official-photo', ['official' => $official->id]) : null,
            'license_url' => $official->license_file_path ? route('private.official-license', ['official' => $official->id]) : null,
            'license_is_pdf' => $official->license_file_path
                ? strtolower(pathinfo($official->license_file_path, PATHINFO_EXTENSION)) === 'pdf'
                : false,
        ])->values();

        $payments = $team->payments->sortByDesc('created_at')->values()->map(fn ($payment) => [
            'id' => $payment->id,
            'invoice_number' => $payment->invoice_number,
            'status' => $payment->status,
            'amount' => (float) $payment->amount,
            'admin_fee' => (float) $payment->admin_fee,
            'total_amount' => (float) $payment->total_amount,
            'channel' => $payment->channel,
            'sender_bank_name' => $payment->sender_bank_name,
            'sender_account_holder' => $payment->sender_account_holder,
            'sender_account_number' => $payment->sender_account_number,
            'transfer_date' => $payment->transfer_date?->toDateString(),
            'rejection_reason' => $payment->rejection_reason,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'expires_at' => $payment->expires_at?->toIso8601String(),
            'verifier_name' => $payment->verifier?->name,
            'proof_url' => $payment->proof_file_path
                ? route('private.payment-proof', ['payment' => $payment->id])
                : null,
            'proof_is_pdf' => $payment->proof_file_path
                ? strtolower(pathinfo($payment->proof_file_path, PATHINFO_EXTENSION)) === 'pdf'
                : false,
            'team' => ['name' => $team->name],
            'event' => $event ? ['name' => $event->name] : null,
        ]);

        return Inertia::render('Admin/Teams/Show', [
            'team' => array_merge($this->formatTeamForManager($team), [
                'admin_notes' => $team->internal_notes,
            ]),
            'readiness' => $this->accreditationService->checkReadiness($team),
            'players' => $players,
            'officials' => $officials,
            'payments' => $payments,
            'canManageStatus' => (bool) request()->user()?->can('teams.manage_status'),
        ]);
    }

    /**
     * Membuka kembali kunci skuad final (locked_roster -> active) atas izin Admin.
     */
    public function unlockRoster(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan pembukaan kunci skuad wajib diisi.',
            'reason.min' => 'Alasan pembukaan kunci skuad minimal 5 karakter.',
        ]);

        if ($team->status !== 'locked_roster') {
            return redirect()->back()->with('error', 'Tim ini tidak sedang dalam status skuad terkunci.');
        }

        $auditLine = '[UNLOCK ROSTER - ' . now()->format('d/m/Y H:i') . ' oleh ' . $request->user()->name . ']: ' . trim($validated['reason']);
        $updatedNotes = trim(($team->admin_notes ? $team->admin_notes . "\n" : '') . $auditLine);

        $team->update([
            'status' => 'active',
            'roster_locked_at' => null,
            'admin_notes' => $updatedNotes,
        ]);

        return redirect()->back()
            ->with('success', "Kunci skuad final untuk tim {$team->name} berhasil dibuka kembali. Manajer tim kini dapat melakukan penyesuaian skuad.");
    }

    /**
     * Mengunci skuad final secara langsung oleh Admin (Force Lock Roster).
     */
    public function forceLockRoster(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('lockRoster', $team);

        $this->accreditationService->lockRoster($team, $request->user());

        $auditLine = '[FORCE LOCK ROSTER - ' . now()->format('d/m/Y H:i') . ' oleh ' . $request->user()->name . ']';
        $updatedNotes = trim(($team->admin_notes ? $team->admin_notes . "\n" : '') . $auditLine);
        $team->update(['admin_notes' => $updatedNotes]);

        return redirect()->back()
            ->with('success', "Skuad final tim {$team->name} berhasil dikunci resmi oleh Panitia. Dokumen akreditasi siap diunduh.");
    }

    /**
     * Mengubah status kepesertaan tim (Diskualifikasi atau Pemulihan ke Aktif).
     */
    public function updateStatus(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:disqualified,active'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'status.required' => 'Status tujuan wajib dipilih.',
            'reason.required' => 'Alasan perubahan status tim wajib diisi.',
            'reason.min' => 'Alasan perubahan status tim minimal 5 karakter.',
        ]);

        $targetStatus = $validated['status'];
        $reason = trim($validated['reason']);

        DB::transaction(function () use ($team, $targetStatus, $reason, $request) {
            $lockedEvent = Event::query()->whereKey($team->event_id)->lockForUpdate()->firstOrFail();
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            if ($targetStatus === 'disqualified') {
                $auditLine = '[DISKUALIFIKASI - ' . now()->format('d/m/Y H:i') . ' oleh ' . $request->user()->name . ']: ' . $reason;
                $updatedNotes = trim(($lockedTeam->admin_notes ? $lockedTeam->admin_notes . "\n" : '') . $auditLine);

                $lockedTeam->update([
                    'status' => 'disqualified',
                    'slot_expires_at' => null,
                    'admin_notes' => $updatedNotes,
                ]);

                $lockedEvent->syncQuotaStatus();

                return;
            }

            // Pemulihan ke active
            $occupied = $lockedEvent->teams()
                ->occupyingQuotaAt(now())
                ->where('id', '!=', $lockedTeam->id)
                ->count();

            if ($occupied >= $lockedEvent->max_teams) {
                throw ValidationException::withMessages([
                    'status' => 'Kuota event saat ini sudah penuh oleh tim lain sehingga tim tidak dapat diaktifkan kembali.',
                ]);
            }

            $auditLine = '[RE-AKTIVASI TIM - ' . now()->format('d/m/Y H:i') . ' oleh ' . $request->user()->name . ']: ' . $reason;
            $updatedNotes = trim(($lockedTeam->admin_notes ? $lockedTeam->admin_notes . "\n" : '') . $auditLine);

            $lockedTeam->update([
                'status' => 'active',
                'slot_expires_at' => null,
                'admin_notes' => $updatedNotes,
            ]);

            $lockedEvent->syncQuotaStatus($occupied + 1);
        });

        $msg = $targetStatus === 'disqualified'
            ? "Tim {$team->name} telah didiskualifikasi dan slot kuota kompetisi telah disesuaikan."
            : "Status tim {$team->name} berhasil dipulihkan menjadi AKTIF.";

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Menyimpan catatan internal panitia (admin_notes) pada tim.
     */
    public function updateNotes(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'admin_notes.max' => 'Catatan internal panitia maksimal 2000 karakter.',
        ]);

        $team->update([
            'admin_notes' => isset($validated['admin_notes']) ? trim($validated['admin_notes']) : null,
        ]);

        return redirect()->back()->with('success', 'Catatan internal panitia berhasil disimpan.');
    }

    /**
     * Memverifikasi atau meminta revisi berkas lisensi pelatih / ofisial oleh Admin.
     */
    public function verifyOfficial(Request $request, Team $team, TeamOfficial $official): RedirectResponse
    {
        $this->authorize('view', $team);

        if ((int) $official->team_id !== (int) $team->id) {
            abort(403);
        }

        $validated = $request->validate([
            'screening_status' => ['required', 'string', 'in:passed,rejected'],
            'verification_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'screening_status.required' => 'Keputusan verifikasi ofisial wajib dipilih.',
            'screening_status.in' => 'Status verifikasi ofisial tidak valid.',
        ]);

        if ($validated['screening_status'] === 'rejected' && empty(trim((string) ($validated['verification_notes'] ?? '')))) {
            throw ValidationException::withMessages([
                'verification_notes' => 'Catatan alasan revisi wajib diisi apabila berkas lisensi ofisial ditolak.',
            ]);
        }

        $official->update([
            'screening_status' => $validated['screening_status'],
            'verification_notes' => isset($validated['verification_notes']) ? trim($validated['verification_notes']) : null,
        ]);

        $statusLabel = $validated['screening_status'] === 'passed' ? 'disahkan (LOLOS)' : 'diminta revisi';

        return redirect()->back()->with('success', "Status lisensi/data ofisial {$official->full_name} berhasil {$statusLabel}.");
    }

    /**
     * Ekspor rekapitulasi daftar tim ke format CSV (UTF-8 BOM kompatibel Excel).
     */
    public function exportTeamsCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Team::class);

        $query = Team::query()
            ->with(['event', 'latestPayment'])
            ->withCount([
                'players',
                'players as players_passed_count' => fn ($q) => $q->where('screening_status', 'passed'),
                'players as gk_passed_count' => fn ($q) => $q->where('position', 'GK')->where('screening_status', 'passed'),
                'officials',
            ])
            ->orderBy('event_id')
            ->orderBy('name');

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($screeningStatus = $request->query('screening_status')) {
            $query->where('screening_status', $screeningStatus);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('origin_association', 'like', "%{$search}%")
                    ->orWhere('manager_name', 'like', "%{$search}%");
            });
        }

        $teams = $query->get();
        $filename = 'rekap-tim-ytevent-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($teams) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID Tim',
                'Kompetisi',
                'Kategori Usia',
                'Nama Tim',
                'Asosiasi / Askot / Askab',
                'Nama Manajer',
                'Email Manajer',
                'WhatsApp Manajer',
                'Pelatih Kepala',
                'Lisensi Pelatih',
                'Status Tim',
                'Status Pembayaran',
                'Status Screening',
                'Total Pemain',
                'Pemain Sah',
                'GK Sah',
                'Jumlah Ofisial',
                'Waktu Kunci Skuad',
            ]);

            foreach ($teams as $team) {
                fputcsv($out, [
                    $team->id,
                    $team->event?->name ?? '-',
                    $team->event?->age_category ?? '-',
                    $team->name,
                    $team->origin_association ?? '-',
                    $team->manager_name,
                    $team->manager_email,
                    $team->manager_whatsapp,
                    $team->head_coach_name ?? '-',
                    $team->head_coach_license ?? '-',
                    $team->status,
                    $team->latestPayment?->status ?? 'unpaid',
                    $team->screening_status,
                    (int) $team->players_count,
                    (int) $team->players_passed_count,
                    (int) $team->gk_passed_count,
                    (int) $team->officials_count,
                    $team->roster_locked_at?->format('Y-m-d H:i:s') ?? '-',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Ekspor database pemain lengkap ke format CSV (UTF-8 BOM kompatibel Excel).
     */
    public function exportPlayersCsv(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Team::class);

        $query = Player::query()
            ->with(['team', 'event'])
            ->orderBy('event_id')
            ->orderBy('team_id')
            ->orderBy('jersey_number');

        if ($eventId = $request->query('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->query('status')) {
            $query->whereHas('team', fn ($q) => $q->where('status', $status));
        }

        if ($screeningStatus = $request->query('screening_status')) {
            $query->whereHas('team', fn ($q) => $q->where('screening_status', $screeningStatus));
        }

        $players = $query->get();
        $filename = 'database-pemain-ytevent-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($players) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'ID Pemain',
                'Kompetisi',
                'Kategori Usia',
                'Nama Tim',
                'Asosiasi / Askot',
                'Status Tim',
                'No. Punggung',
                'Nama Lengkap Pemain',
                'Nama Punggung',
                'Posisi',
                'Kaki Dominan',
                'Tempat Lahir',
                'Tanggal Lahir',
                'NIK',
                'NISN',
                'Tinggi (cm)',
                'Berat (kg)',
                'Status Screening',
                'Kode Akreditasi QR',
            ]);

            foreach ($players as $player) {
                fputcsv($out, [
                    $player->id,
                    $player->event?->name ?? '-',
                    $player->event?->age_category ?? '-',
                    $player->team?->name ?? '-',
                    $player->team?->origin_association ?? '-',
                    $player->team?->status ?? '-',
                    $player->jersey_number,
                    $player->full_name,
                    $player->jersey_name ?? '-',
                    $player->position,
                    $player->dominant_foot,
                    $player->birth_place ?? '-',
                    $player->birth_date?->format('Y-m-d') ?? '-',
                    "'" . $player->nik,
                    $player->nisn ? "'" . $player->nisn : '-',
                    $player->height_cm ?? '-',
                    $player->weight_kg ?? '-',
                    $player->screening_status,
                    $player->accreditation_code ?? '-',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
