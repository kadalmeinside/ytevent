<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesActiveTeam;
use App\Http\Requests\Manager\StorePlayerRequest;
use App\Http\Requests\Manager\UpdatePlayerRequest;
use App\Http\Requests\Manager\UploadPlayerDocumentRequest;
use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\ScreeningLog;
use App\Models\Team;
use App\Services\ScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlayerController extends Controller
{
    use ResolvesActiveTeam;

    public function index(Request $request): Response
    {
        $ctx = $this->resolveManagerContext($request);
        /** @var Team|null $team */
        $team = $ctx['activeTeam'];

        $players = [];
        if ($team) {
            $team->loadMissing('event');
            $players = $team->players()
                ->with('documents')
                ->orderBy('jersey_number')
                ->get()
                ->map(function (Player $player) use ($team) {
                    $player->setRelation('event', $team->event);
                    $missing = collect($player->missingRequiredDocuments())
                        ->map(fn (string $type) => Event::documentLabel($type))
                        ->values();

                    return [
                        'id' => $player->id,
                        'full_name' => $player->full_name,
                        'jersey_name' => $player->jersey_name,
                        'jersey_number' => $player->jersey_number,
                        'position' => $player->position,
                        'dominant_foot' => $player->dominant_foot,
                        'birth_place' => $player->birth_place,
                        'birth_date' => $player->birth_date?->toDateString(),
                        'nik' => $player->nik,
                        'nisn' => $player->nisn,
                        'height_cm' => $player->height_cm,
                        'weight_kg' => $player->weight_kg,
                        'screening_status' => $player->screening_status,
                        'is_locked' => $player->is_locked,
                        'rejection_reason' => $player->rejection_reason,
                        'photo_url' => $player->photo_path
                            ? route('private.player-photo', ['player' => $player->id])
                            : null,
                        'documents' => $player->documents->mapWithKeys(fn (PlayerDocument $doc) => [
                            $doc->document_type => [
                                'id' => $doc->id,
                                'document_type' => $doc->document_type,
                                'label' => Event::documentLabel($doc->document_type),
                                'document_number' => $doc->document_number,
                                'original_name' => $doc->original_name,
                                'mime_type' => $doc->mime_type,
                                'file_size' => $doc->file_size,
                                'validation_status' => $doc->validation_status,
                                'notes' => $doc->notes,
                                'preview_url' => route('private.player-document', ['document' => $doc->id]),
                            ],
                        ]),
                        'missing_documents' => $missing,
                        'is_complete_for_submission' => $player->isCompleteForSubmission(),
                    ];
                })
                ->values();
        }

        return Inertia::render('Manager/Players', [
            'team' => $ctx['teamPayload'],
            'teamsList' => $ctx['teamsList'],
            'players' => $players,
            'positions' => Player::POSITIONS,
        ]);
    }

    public function store(StorePlayerRequest $request, Team $team): RedirectResponse
    {
        $team->loadMissing('event');
        $this->authorize('create', [Player::class, $team]);

        $validated = $request->validated();
        $photoPath = $request->file('photo')->store('players/photos', 'private');

        $player = DB::transaction(function () use ($team, $validated, $photoPath, $request) {
            $newPlayer = Player::create([
                'team_id' => $team->id,
                'event_id' => $team->event_id,
                'full_name' => trim($validated['full_name']),
                'jersey_name' => isset($validated['jersey_name']) ? trim($validated['jersey_name']) : null,
                'jersey_number' => (int) $validated['jersey_number'],
                'position' => $validated['position'],
                'dominant_foot' => $validated['dominant_foot'],
                'birth_place' => isset($validated['birth_place']) ? trim($validated['birth_place']) : null,
                'birth_date' => $validated['birth_date'],
                'nik' => trim($validated['nik']),
                'nisn' => isset($validated['nisn']) ? trim($validated['nisn']) : null,
                'height_cm' => $validated['height_cm'] ?? null,
                'weight_kg' => $validated['weight_kg'] ?? null,
                'photo_path' => $photoPath,
                'screening_status' => 'draft',
                'is_locked' => false,
            ]);

            $uploadedDocs = $request->file('documents', []);
            if (is_array($uploadedDocs)) {
                foreach ($uploadedDocs as $docType => $file) {
                    if ($file instanceof UploadedFile) {
                        $this->persistPlayerDocument($newPlayer, (string) $docType, $file);
                    }
                }
            }

            return $newPlayer;
        });

        return redirect()->back()
            ->with('success', "Pemain {$player->full_name} (#{$player->jersey_number}) berhasil ditambahkan.");
    }

    public function update(UpdatePlayerRequest $request, Player $player): RedirectResponse
    {
        $player->loadMissing('team.event');
        $this->authorize('update', $player);

        $validated = $request->validated();

        DB::transaction(function () use ($player, $validated, $request) {
            $updates = [
                'full_name' => trim($validated['full_name']),
                'jersey_name' => isset($validated['jersey_name']) ? trim($validated['jersey_name']) : null,
                'jersey_number' => (int) $validated['jersey_number'],
                'position' => $validated['position'],
                'dominant_foot' => $validated['dominant_foot'],
                'birth_place' => isset($validated['birth_place']) ? trim($validated['birth_place']) : null,
                'birth_date' => $validated['birth_date'],
                'nik' => trim($validated['nik']),
                'nisn' => isset($validated['nisn']) ? trim($validated['nisn']) : null,
                'height_cm' => $validated['height_cm'] ?? null,
                'weight_kg' => $validated['weight_kg'] ?? null,
            ];

            if ($request->hasFile('photo')) {
                $newPhoto = $request->file('photo')->store('players/photos', 'private');
                if ($player->photo_path) {
                    Storage::disk('private')->delete($player->photo_path);
                }
                $updates['photo_path'] = $newPhoto;
            }

            $player->update($updates);

            $uploadedDocs = $request->file('documents', []);
            if (is_array($uploadedDocs)) {
                foreach ($uploadedDocs as $docType => $file) {
                    if ($file instanceof UploadedFile) {
                        $this->persistPlayerDocument($player, (string) $docType, $file);
                    }
                }
            }
        });

        return redirect()->back()
            ->with('success', "Data pemain {$player->full_name} berhasil diperbarui.");
    }

    public function destroy(Player $player): RedirectResponse
    {
        $player->loadMissing(['team.event', 'documents']);
        $this->authorize('delete', $player);

        $team = $player->team;
        $name = $player->full_name;

        foreach ($player->documents as $doc) {
            if ($doc->file_path) {
                Storage::disk('private')->delete($doc->file_path);
            }
        }

        if ($player->photo_path) {
            Storage::disk('private')->delete($player->photo_path);
        }

        $player->forceDelete();

        if ($team) {
            app(ScreeningService::class)->syncTeamStatus($team);
        }

        return redirect()->back()->with('success', "Data pemain {$name} berhasil dihapus.");
    }

    public function uploadDocument(UploadPlayerDocumentRequest $request, Player $player): RedirectResponse
    {
        $player->loadMissing('team.event');
        $this->authorize('update', $player);

        $validated = $request->validated();
        $docType = $validated['document_type'];
        $docLabel = Event::documentLabel($docType);

        if ($request->hasFile('file')) {
            $this->persistPlayerDocument(
                player: $player,
                documentType: $docType,
                file: $request->file('file'),
                documentNumber: $validated['document_number'] ?? null
            );
        } elseif (array_key_exists('document_number', $validated)) {
            $player->documents()->where('document_type', $docType)->update([
                'document_number' => $validated['document_number'],
            ]);
        }

        return redirect()->back()
            ->with('success', "Berkas {$docLabel} untuk {$player->full_name} berhasil disimpan.");
    }

    public function submitPlayer(Request $request, Player $player): RedirectResponse
    {
        $player->loadMissing(['team.event', 'documents']);
        $this->authorize('update', $player);

        $missing = $player->missingRequiredDocuments();
        if (! empty($missing) || empty($player->photo_path)) {
            $missingLabels = collect($missing)->map(fn (string $t) => Event::documentLabel($t))->implode(', ');
            $detail = $missingLabels ?: 'Pas Foto Resmi';

            throw ValidationException::withMessages([
                'submission' => "Pemain {$player->full_name} belum dapat diajukan karena dokumen wajib belum lengkap: {$detail}.",
            ]);
        }

        $prevStatus = $player->screening_status;

        DB::transaction(function () use ($player, $request, $prevStatus) {
            $player->update([
                'screening_status' => 'submitted',
                'rejection_reason' => null,
            ]);

            ScreeningLog::create([
                'player_id' => $player->id,
                'actor_id' => $request->user()->id,
                'from_status' => $prevStatus,
                'to_status' => 'submitted',
                'reason' => $prevStatus === 'rejected'
                    ? 'Diajukan ulang oleh Manajer Tim setelah perbaikan berkas/data.'
                    : 'Diajukan oleh Manajer Tim untuk pemeriksaan screening.',
            ]);

            app(ScreeningService::class)->syncTeamStatus($player->team);
        });

        $message = $prevStatus === 'rejected'
            ? "Perbaikan berkas pemain {$player->full_name} berhasil diajukan ulang ke antrean screening panitia."
            : "Berkas pemain {$player->full_name} berhasil diajukan ke antrean screening panitia.";

        return redirect()->back()->with('success', $message);
    }

    public function recallPlayer(Request $request, Player $player): RedirectResponse
    {
        $player->loadMissing('team');
        $this->authorize('recall', $player);

        if ($player->screening_status !== 'submitted') {
            return redirect()->back()->with('error', "Pengajuan pemain {$player->full_name} tidak dapat ditarik karena statusnya bukan Menunggu Screening.");
        }

        if ($player->is_locked || $player->screening_status === 'passed') {
            return redirect()->back()->with('error', "Pemain {$player->full_name} sudah berstatus sah/terkunci dan tidak dapat ditarik.");
        }

        DB::transaction(function () use ($player, $request) {
            $player->update([
                'screening_status' => 'draft',
            ]);

            ScreeningLog::create([
                'player_id' => $player->id,
                'actor_id' => $request->user()->id,
                'from_status' => 'submitted',
                'to_status' => 'draft',
                'reason' => 'Pengajuan ditarik kembali ke draft oleh Manajer Tim untuk perbaikan data/berkas.',
            ]);

            app(ScreeningService::class)->syncTeamStatus($player->team);
        });

        return redirect()->back()->with('success', "Pengajuan screening pemain {$player->full_name} berhasil ditarik ke draft. Anda dapat mengoreksi data atau berkas sebelum diajukan kembali.");
    }

    protected function persistPlayerDocument(
        Player $player,
        string $documentType,
        UploadedFile $file,
        ?string $documentNumber = null
    ): PlayerDocument {
        $storedPath = $file->store('players/documents', 'private');

        $existing = PlayerDocument::query()
            ->where('player_id', $player->id)
            ->where('document_type', $documentType)
            ->first();

        if ($existing && $existing->file_path) {
            Storage::disk('private')->delete($existing->file_path);
        }

        return PlayerDocument::updateOrCreate(
            [
                'player_id' => $player->id,
                'document_type' => $documentType,
            ],
            [
                'document_number' => $documentNumber ?? $existing?->document_number,
                'file_path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize() ?: 0,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'validation_status' => 'pending',
                'notes' => null,
            ]
        );
    }
}

