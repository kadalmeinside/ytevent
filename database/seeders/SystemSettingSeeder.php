<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Branding
            ['key' => 'app_name', 'value' => 'YTEvent Football Competition', 'type' => 'text', 'group' => 'branding'],
            ['key' => 'app_tagline', 'value' => 'Multi-Event Competition Portal', 'type' => 'text', 'group' => 'branding'],
            ['key' => 'app_logo', 'value' => null, 'type' => 'file', 'group' => 'branding'],
            ['key' => 'app_favicon', 'value' => null, 'type' => 'file', 'group' => 'branding'],
            ['key' => 'footer_copyright', 'value' => 'YTEvent Multi-Event Football Tournament & League Management System', 'type' => 'text', 'group' => 'branding'],

            // Payment Gateway & Fee Rules
            ['key' => 'payment_method_mode', 'value' => 'midtrans', 'type' => 'text', 'group' => 'payment'], // 'midtrans' atau 'manual'
            ['key' => 'payment_gateway_default', 'value' => 'midtrans', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_server_key', 'value' => 'SB-Mid-server-demo', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_client_key', 'value' => 'SB-Mid-client-demo', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_is_production', 'value' => '0', 'type' => 'boolean', 'group' => 'payment'],

            // Rekening Transfer Manual (Bisa Lebih Dari Satu)
            [
                'key' => 'manual_bank_accounts',
                'value' => json_encode([
                    [
                        'bank_name' => 'BCA',
                        'account_number' => '8720192837',
                        'account_holder' => 'PT Event Sepak Bola Indonesia',
                        'notes' => 'KCU Sudirman Jakarta',
                    ],
                    [
                        'bank_name' => 'Bank Mandiri',
                        'account_number' => '1370019283746',
                        'account_holder' => 'PT Event Sepak Bola Indonesia',
                        'notes' => 'Cabang Thamrin Jakarta',
                    ],
                ]),
                'type' => 'json',
                'group' => 'payment',
            ],

            // Midtrans MDR Fee Rules per Channel
            ['key' => 'fee_borne_by', 'value' => 'team', 'type' => 'text', 'group' => 'payment'], // 'team' atau 'organizer'
            ['key' => 'midtrans_va_fee', 'value' => '4000', 'type' => 'integer', 'group' => 'payment'],
            ['key' => 'midtrans_qris_fee_percent', 'value' => '0.7', 'type' => 'float', 'group' => 'payment'],
            ['key' => 'midtrans_ewallet_fee_percent', 'value' => '1.5', 'type' => 'float', 'group' => 'payment'],
            ['key' => 'midtrans_cc_fee_percent', 'value' => '2.9', 'type' => 'float', 'group' => 'payment'],
            ['key' => 'midtrans_cc_fee_fixed', 'value' => '2000', 'type' => 'integer', 'group' => 'payment'],

            // General & Kontak
            ['key' => 'contact_email', 'value' => 'sekretariat@ytevent.id', 'type' => 'text', 'group' => 'general'],
            ['key' => 'contact_whatsapp', 'value' => '081298765432', 'type' => 'text', 'group' => 'general'],
        ];

        foreach ($defaults as $row) {
            SystemSetting::updateOrCreate(
                ['key' => $row['key']],
                [
                    'value' => $row['value'],
                    'type' => $row['type'],
                    'group' => $row['group'],
                ]
            );
        }
    }
}

