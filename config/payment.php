<?php

return [
    /*
    | Simulator pembayaran HANYA untuk pengembangan lokal.
    | Default WAJIB false agar server production yang lupa menulis variabel ini
    | tidak membuka celah aktivasi tim tanpa pembayaran.
    | Simulator juga otomatis dimatikan paksa saat APP_ENV=production.
    */
    'sandbox_simulator' => (bool) env('PAYMENT_SANDBOX_SIMULATOR', false)
        && env('APP_ENV') !== 'production',

    /*
    | Kredensial di bawah adalah fallback dari .env.
    | Nilai yang diisi Superadmin di /admin/settings (tabel system_settings)
    | selalu diprioritaskan oleh PaymentService.
    */
    'midtrans' => [
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        'server_key' => env('MIDTRANS_SERVER_KEY', ''),
        'client_key' => env('MIDTRANS_CLIENT_KEY', ''),
        'snap_url_production' => 'https://app.midtrans.com/snap/v1/transactions',
        'snap_url_sandbox' => 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        'snap_js_url_production' => 'https://app.midtrans.com/snap/snap.js',
        'snap_js_url_sandbox' => 'https://app.sandbox.midtrans.com/snap/snap.js',
        'snap_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions',
        'snap_js_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js',
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY', ''),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN', ''),
        'invoice_url' => 'https://api.xendit.co/v2/invoices',
    ],

    'manual_bank' => [
        'bank_name' => env('MANUAL_BANK_NAME', 'Bank Central Asia (BCA)'),
        'account_number' => env('MANUAL_BANK_ACCOUNT_NUMBER', '473-5499999'),
        'account_holder' => env('MANUAL_BANK_ACCOUNT_HOLDER', 'PT Persija Jakarta Hebat KCP Pamulang'),
    ],
];
