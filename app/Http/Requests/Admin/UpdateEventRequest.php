<?php

namespace App\Http\Requests\Admin;

use App\Models\Event;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends StoreEventRequest
{
    public function authorize(): bool
    {
        /** @var Event|null $event */
        $event = $this->route('event');

        return $event && ($this->user()?->can('update', $event) ?? false);
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Event|null $event */
                $event = $this->route('event');
                if (! $event) {
                    return;
                }

                $participantType = $this->input('participant_type', $event->participant_type ?? 'team');
                if ($this->input('quota_type', 'fixed') === 'fixed' && $participantType === 'team') {
                    $occupied = $event->teams()->occupyingQuotaAt(now())->count();
                    $requestedMax = (int) $this->input('max_teams', 0);

                    if ($requestedMax > 0 && $requestedMax < $occupied) {
                        $validator->errors()->add(
                            'max_teams',
                            "Kuota maksimal tidak boleh dikurangi di bawah jumlah tim yang saat ini sedang memegang slot ({$occupied} tim)."
                        );
                    }
                } elseif ($this->input('quota_type', 'fixed') === 'fixed' && $participantType === 'individual') {
                    $occupied = $event->auditionParticipants()->whereIn('status', ['registered', 'verified', 'passed', 'failed'])->count();
                    $requestedMax = (int) $this->input('max_participants', 0);

                    if ($requestedMax > 0 && $requestedMax < $occupied) {
                        $validator->errors()->add(
                            'max_participants',
                            "Kuota maksimal atlet tidak boleh dikurangi di bawah jumlah peserta yang saat ini terdaftar ({$occupied} atlet)."
                        );
                    }
                }
            },
        ];
    }
}

