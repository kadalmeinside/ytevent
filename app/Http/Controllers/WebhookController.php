<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use App\Services\OperationalAlertService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class WebhookController extends Controller
{
    public function midtrans(Request $request, PaymentService $paymentService): JsonResponse
    {
        return $this->process('midtrans', $request, fn () => $paymentService->handleMidtransWebhook($request->all()));
    }

    public function xendit(Request $request, PaymentService $paymentService): JsonResponse
    {
        return $this->process(
            'xendit',
            $request,
            fn () => $paymentService->handleXenditWebhook($request->all(), $request->header('x-callback-token'))
        );
    }

    protected function process(string $provider, Request $request, \Closure $handler): JsonResponse
    {
        $payload = $this->safePayload($provider, $request->all());

        try {
            $payment = $handler();
            $log = WebhookLog::create([
                'provider' => $provider,
                'invoice_number' => $payment->invoice_number,
                'external_reference' => $payment->external_reference_id,
                'event_name' => $payload['status'] ?? $payload['transaction_status'] ?? null,
                'status' => 'processed',
                'http_status' => 200,
                'payload' => $payload,
            ]);

            return response()->json([
                'status' => 'ok',
                'invoice_number' => $payment->invoice_number,
                'payment_status' => $payment->status,
            ]);
        } catch (Throwable $exception) {
            $log = WebhookLog::create([
                'provider' => $provider,
                'invoice_number' => $payload['external_id'] ?? $payload['order_id'] ?? null,
                'external_reference' => $payload['id'] ?? $payload['transaction_id'] ?? null,
                'event_name' => $payload['status'] ?? $payload['transaction_status'] ?? null,
                'status' => 'failed',
                'http_status' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500,
                'error_message' => $exception->getMessage(),
                'payload' => $payload,
            ]);
            app(OperationalAlertService::class)->webhookFailed($log);

            throw $exception;
        }
    }

    protected function safePayload(string $provider, array $payload): array
    {
        $allowed = $provider === 'midtrans'
            ? ['order_id', 'status_code', 'gross_amount', 'transaction_status', 'fraud_status', 'payment_type', 'transaction_id']
            : ['external_id', 'amount', 'status', 'payment_channel', 'id'];

        return collect($payload)
            ->only($allowed)
            ->filter(fn ($value) => is_scalar($value) || $value === null)
            ->all();
    }
}
