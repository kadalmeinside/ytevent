<?php

namespace App\Console\Commands;

use App\Services\QuotaExpirationService;
use Illuminate\Console\Command;

class ExpireUnpaidRegistrationsCommand extends Command
{
    protected $signature = 'app:expire-unpaid-registrations';

    protected $description = 'Melepas slot kuota tim yang melewati batas waktu pembayaran (expires_at) dan menyinkronkan status event.';

    public function handle(QuotaExpirationService $quotaExpiration): int
    {
        $expiredCount = $quotaExpiration->sweepAllEvents();

        $this->info("Selesai menyapu pendaftaran kedaluwarsa: {$expiredCount} slot dilepas.");

        return self::SUCCESS;
    }
}

