<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Event::withTrashed()
            ->whereNotNull('form_presets')
            ->get()
            ->each(function (Event $event): void {
                $normalized = Event::normalizeFormPresetsArray($event->form_presets);
                if ($event->form_presets !== $normalized) {
                    $event->forceFill(['form_presets' => $normalized])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Idempotent data normalization; no rollback needed.
    }
};

