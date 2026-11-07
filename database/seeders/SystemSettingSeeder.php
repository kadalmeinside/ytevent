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
            ['key' => 'app_name', 'value' => 'Persija Development', 'type' => 'text', 'group' => 'branding'],
            ['key' => 'app_tagline', 'value' => 'Young Tiger Cup & Football Competition Portal', 'type' => 'text', 'group' => 'branding'],
            ['key' => 'app_logo', 'value' => null, 'type' => 'file', 'group' => 'branding'],
            ['key' => 'header_logo_style', 'value' => 'logo_only', 'type' => 'text', 'group' => 'branding'], // 'logo_only' (hanya logo kesamping) atau 'logo_and_text' (logo + teks)
            ['key' => 'app_favicon', 'value' => null, 'type' => 'file', 'group' => 'branding'],
            ['key' => 'footer_copyright', 'value' => '© ' . date('Y') . ' Persija Development - Young Tiger Football Tournament Management System', 'type' => 'text', 'group' => 'branding'],

            // Payment Gateway & Fee Rules (Default: Pembayaran Manual Transfer Bank)
            ['key' => 'payment_method_mode', 'value' => 'manual', 'type' => 'text', 'group' => 'payment'], // 'manual' atau 'midtrans'
            ['key' => 'payment_gateway_default', 'value' => 'midtrans', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_server_key', 'value' => 'SB-Mid-server-demo', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_client_key', 'value' => 'SB-Mid-client-demo', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_is_production', 'value' => '0', 'type' => 'boolean', 'group' => 'payment'],

            // Rekening Transfer Manual Resmi Persija Development
            [
                'key' => 'manual_bank_accounts',
                'value' => json_encode([
                    [
                        'bank_name' => 'Bank Central Asia (BCA)',
                        'account_number' => '473-5499999',
                        'account_holder' => 'PT Persija Jakarta Hebat KCP Pamulang',
                        'notes' => 'Rekening Resmi Pendaftaran Persija Development',
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
            ['key' => 'contact_email', 'value' => 'development@persija.id', 'type' => 'text', 'group' => 'general'],
            ['key' => 'contact_whatsapp', 'value' => '081128021928', 'type' => 'text', 'group' => 'general'],

            // Simulation Module Toggle (Dapat dimatikan saat full production)
            ['key' => 'simulation_mode_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'simulation'],
        ];

        \Illuminate\Support\Facades\Cache::flush();

        foreach ($defaults as $row) {
            SystemSetting::set(
                $row['key'],
                $row['type'] === 'json' ? json_decode($row['value'], true) : $row['value'],
                $row['type'],
                $row['group']
            );
        }
    }
}

