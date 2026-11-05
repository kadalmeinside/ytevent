<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function midtrans(Request $request, PaymentService $paymentService): JsonResponse
    {
        $payment = $paymentService->handleMidtransWebhook($request->all());

        return response()->json([
            'status' => 'ok',
            'invoice_number' => $payment->invoice_number,
            'payment_status' => $payment->status,
        ]);
    }

    public function xendit(Request $request, PaymentService $paymentService): JsonResponse
    {
        $payment = $paymentService->handleXenditWebhook(
            $request->all(),
            $request->header('x-callback-token')
        );

        return response()->json([
            'status' => 'ok',
            'invoice_number' => $payment->invoice_number,
            'payment_status' => $payment->status,
        ]);
    }
}

