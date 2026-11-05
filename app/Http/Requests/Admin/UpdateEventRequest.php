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

                $occupied = $event->teams()->occupyingQuotaAt(now())->count();
                $requestedMax = (int) $this->input('max_teams', 0);

                if ($requestedMax > 0 && $requestedMax < $occupied) {
                    $validator->errors()->add(
                        'max_teams',
                        "Kuota maksimal tidak boleh dikurangi di bawah jumlah tim yang saat ini sedang memegang slot ({$occupied} tim)."
                    );
                }
            },
        ];
    }
}

