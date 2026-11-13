<?php

namespace App\Console\Commands;

use App\Services\AuditionNoShowService;
use Illuminate\Console\Command;

class MarkAuditionNoShowsCommand extends Command
{
    protected $signature = 'app:mark-audition-no-shows';
    protected $description = 'Menandai peserta audisi yang tidak hadir setelah tahap berakhir.';

    public function handle(AuditionNoShowService $service): int
    {
        $this->info('No-show ditandai: '.$service->markEndedStages());

        return self::SUCCESS;
    }
}
