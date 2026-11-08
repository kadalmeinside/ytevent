<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:webhooks')->group(function () {
    Route::post('/webhooks/midtrans', [WebhookController::class, 'midtrans'])->name('webhooks.midtrans');
    Route::post('/webhooks/xendit', [WebhookController::class, 'xendit'])->name('webhooks.xendit');
});
