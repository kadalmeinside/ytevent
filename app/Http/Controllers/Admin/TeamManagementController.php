<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Services\AccreditationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

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
            'team' => $this->formatTeamForManager($team),
            'readiness' => $this->accreditationService->checkReadiness($team),
            'players' => $players,
            'officials' => $officials,
            'payments' => $payments,
        ]);
    }
}
