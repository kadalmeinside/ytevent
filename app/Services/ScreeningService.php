<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\ScreeningLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScreeningService
{
    /**
     * Memverifikasi pemain (Loloskan atau Tolak).
     */
    public function verifyPlayer(
        Player $player,
        User $officer,
        string $action,
        ?string $reason = null,
        array $documentUpdates = []
    ): Player {
        return DB::transaction(function () use ($player, $officer, $action, $reason, $documentUpdates) {
            $fromStatus = $player->screening_status;

            if ($action === 'passed') {
                $player->screening_status = 'passed';
                $player->is_locked = true;
                $player->rejection_reason = null;
                $player->verified_by = $officer->id;
                $player->verified_at = now();

                if (empty($player->accreditation_code)) {
                    $player->accreditation_code = $this->generateUniqueAccreditationCode($player);
                }
            } else {
                $player->screening_status = 'rejected';
                $player->is_locked = false;
                $player->rejection_reason = $reason;
                $player->verified_by = $officer->id;
                $player->verified_at = now();
            }

            // Perbarui status dokumen jika disertakan
            if (!empty($documentUpdates)) {
                foreach ($documentUpdates as $docData) {
                    if (empty($docData['id'])) continue;
                    $doc = $player->documents()->find($docData['id']);
                    if ($doc) {
                        $doc->update([
                            'validation_status' => $docData['validation_status'],
                            'notes' => $docData['notes'] ?? null,
                        ]);
                    }
                }
            } elseif ($action === 'passed') {
                // Jika lolos dan tidak ada pembaruan dokumen manual, set seluruh berkas pending menjadi valid
                $player->documents()->where('validation_status', 'pending')->update([
                    'validation_status' => 'valid',
                ]);
            }

            $player->save();

            // Catat log histori
            ScreeningLog::create([
                'player_id' => $player->id,
                'actor_id' => $officer->id,
                'from_status' => $fromStatus,
                'to_status' => $action,
                'reason' => $action === 'rejected' ? $reason : 'Pemain dinyatakan sah dan lolos verifikasi.',
            ]);

            // Sinkronkan status screening tim
            $this->syncTeamScreeningStatus($player->team);

            return $player->fresh(['documents', 'team', 'event']);
        });
    }

    /**
     * Memverifikasi keabsahan satu berkas dokumen pemain.
     */
    public function verifyDocument(
        PlayerDocument $document,
        User $officer,
        string $status,
        ?string $notes = null
    ): PlayerDocument {
        $document->update([
            'validation_status' => $status,
            'notes' => $notes,
        ]);

        return $document->fresh();
    }

    /**
     * Membuka kembali kunci pemain yang sudah lolos (Emergency Unlock).
     */
    public function unlockPlayer(
        Player $player,
        User $officer,
        ?string $reason = null
    ): Player {
        return DB::transaction(function () use ($player, $officer, $reason) {
            $fromStatus = $player->screening_status;
            $unlockReason = $reason ?: 'Kunci data pemain dibuka oleh panitia untuk perbaikan administrasi.';

            $player->update([
                'screening_status' => 'draft',
                'is_locked' => false,
                'rejection_reason' => $unlockReason,
            ]);

            ScreeningLog::create([
                'player_id' => $player->id,
                'actor_id' => $officer->id,
                'from_status' => $fromStatus,
                'to_status' => 'draft',
                'reason' => $unlockReason,
            ]);

            $this->syncTeamScreeningStatus($player->team);

            return $player->fresh(['documents', 'team', 'event']);
        });
    }

    /**
     * Sinkronisasi status screening keseluruhan tim.
     */
    public function syncTeamScreeningStatus($team): void
    {
        if (!$team) return;

        $players = $team->players()->get();
        if ($players->isEmpty()) {
            $team->update(['screening_status' => 'not_started']);
            return;
        }

        $hasRejected = $players->contains(fn ($p) => $p->screening_status === 'rejected');
        if ($hasRejected) {
            $team->update(['screening_status' => 'partially_rejected']);
            return;
        }

        $allPassed = $players->every(fn ($p) => $p->screening_status === 'passed');
        $minPlayers = $team->event?->min_players_per_team ?? 1;

        if ($allPassed && $players->count() >= $minPlayers) {
            $team->update(['screening_status' => 'all_passed']);
            return;
        }

        $hasSubmitted = $players->contains(fn ($p) => in_array($p->screening_status, ['submitted', 'passed']));
        if ($hasSubmitted) {
            $team->update(['screening_status' => 'in_review']);
            return;
        }

        $team->update(['screening_status' => 'not_started']);
    }

    /**
     * Menghasilkan kode akreditasi unik digital pemain.
     */
    protected function generateUniqueAccreditationCode(Player $player): string
    {
        do {
            $code = sprintf('YT-E%02d-PL%04d-%s', $player->event_id, $player->id, strtoupper(Str::random(5)));
        } while (Player::where('accreditation_code', $code)->exists());

        return $code;
    }
}
