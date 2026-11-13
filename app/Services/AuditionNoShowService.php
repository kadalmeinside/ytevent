<?php

namespace App\Services;

use App\Models\AuditionAttendance;
use App\Models\AuditionStage;
use App\Models\User;

class AuditionNoShowService
{
    public function markEndedStages(): int
    {
        $systemUserId = User::query()->where('is_active', true)->orderBy('id')->value('id');
        if (! $systemUserId) {
            return 0;
        }

        $marked = 0;
        AuditionStage::query()
            ->whereHas('event', fn ($query) => $query
                ->where('participant_type', 'individual')
                ->where('event_type', 'audition'))
            ->get()
            ->each(function (AuditionStage $stage) use ($systemUserId, &$marked): void {
                if (! $this->hasEnded($stage)) {
                    return;
                }

                $eligibleStatus = match ((int) $stage->stage_number) {
                    1 => 'registered',
                    2 => 'day_1_passed',
                    default => 'day_2_passed',
                };

                $stage->event->auditionParticipants()
                    ->where('status', 'active')
                    ->where('screening_status', 'passed')
                    ->whereHas('payments', fn ($query) => $query->where('status', 'paid'))
                    ->where('stage_status', $eligibleStatus)
                    ->whereDoesntHave('attendances', fn ($query) => $query->where('audition_stage_id', $stage->id))
                    ->get()
                    ->each(function ($participant) use ($stage, $systemUserId, &$marked): void {
                        $attendance = AuditionAttendance::firstOrCreate(
                            [
                                'audition_participant_id' => $participant->id,
                                'audition_stage_id' => $stage->id,
                            ],
                            [
                                'scanned_by' => $systemUserId,
                                'check_in_at' => now(),
                                'status' => 'no_show',
                                'notes' => 'Ditandai otomatis setelah tahap berakhir.',
                            ]
                        );
                        if ($attendance->wasRecentlyCreated) {
                            $marked++;
                        }
                    });
            });

        return $marked;
    }

    private function hasEnded(AuditionStage $stage): bool
    {
        $end = $stage->stage_date?->copy()->endOfDay();
        if ($stage->end_time) {
            $end = $stage->stage_date?->copy()->setTimeFromTimeString($stage->end_time);
        }

        return $end && now()->greaterThan($end);
    }
}
