<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcilePendingPaymentsCommand extends Command
{
    protected $signature = 'app:reconcile-pending-payments {--hours=1 : Minimum age of an unchanged gateway payment}';

    protected $description = 'Memeriksa ulang status pembayaran gateway yang masih tertunda.';

    public function handle(PaymentService $paymentService): int
    {
        $cutoff = now()->subHours(max(1, (int) $this->option('hours')));
        $payments = Payment::query()
            ->where('method', 'midtrans')
            ->whereIn('status', ['unpaid', 'rejected'])
            ->whereNotNull('external_reference_id')
            ->where('updated_at', '<=', $cutoff)
            ->cursor();

        $checked = 0;
        $settled = 0;

        foreach ($payments as $payment) {
            $checked++;
            $before = $payment->status;
            $paymentService->syncMidtransTransactionStatus($payment);
            $after = $payment->fresh()->status;

            if ($before !== $after && in_array($after, ['paid', 'refund_required', 'refunded'], true)) {
                $settled++;
            }
        }

        $this->info("Rekonsiliasi selesai: {$checked} payment diperiksa, {$settled} status diselesaikan.");

        return self::SUCCESS;
    }
}
