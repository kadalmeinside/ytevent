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
            ['key' => 'payment_gateway_default', 'value' => 'midtrans', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_server_key', 'value' => 'SB-Mid-server-demo', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_client_key', 'value' => 'SB-Mid-client-demo', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'midtrans_is_production', 'value' => '0', 'type' => 'boolean', 'group' => 'payment'],
            ['key' => 'xendit_secret_key', 'value' => 'xnd_development_demo', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'xendit_public_key', 'value' => 'xnd_public_demo', 'type' => 'text', 'group' => 'payment'],
            ['key' => 'xendit_is_production', 'value' => '0', 'type' => 'boolean', 'group' => 'payment'],

            // Fee Admin & MDR Rules
            ['key' => 'fee_borne_by', 'value' => 'team', 'type' => 'text', 'group' => 'payment'], // 'team' atau 'organizer'
            ['key' => 'fee_scheme', 'value' => 'fixed', 'type' => 'text', 'group' => 'payment'], // 'fixed', 'percentage', 'both'
            ['key' => 'fee_fixed_amount', 'value' => '4500', 'type' => 'integer', 'group' => 'payment'],
            ['key' => 'fee_percentage', 'value' => '1.5', 'type' => 'float', 'group' => 'payment'],

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
