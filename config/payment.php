<?php

return [
    'sandbox_simulator' => (bool) env('PAYMENT_SANDBOX_SIMULATOR', true),

    'midtrans' => [
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'server_key' => env('MIDTRANS_SERVER_KEY', 'SB-Mid-server-ytevent-sandbox-key'),
        'client_key' => env('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-ytevent-sandbox-key'),
        'snap_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        'snap_js_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js',
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY', 'xnd_development_ytevent_sandbox_key'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN', 'ytevent-xendit-webhook-token-2026'),
        'invoice_url' => 'https://api.xendit.co/v2/invoices',
    ],

    'manual_bank' => [
        'bank_name' => env('MANUAL_BANK_NAME', 'Bank Mandiri'),
        'account_number' => env('MANUAL_BANK_ACCOUNT_NUMBER', '1240088991122'),
        'account_holder' => env('MANUAL_BANK_ACCOUNT_HOLDER', 'Panitia Kompetisi YTEvent Indonesia'),
    ],
];

