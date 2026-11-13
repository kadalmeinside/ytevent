<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingSettingsRequest;
use App\Http\Requests\Admin\UpdatePaymentGatewaySettingsRequest;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    public function index(): Response
    {
        $settings = SystemSetting::all()->mapWithKeys(function (SystemSetting $s) {
            $val = SystemSetting::get($s->key);
            if ($s->type === 'file' && $val) {
                $val = Storage::disk('public')->url($val);
            }
            return [$s->key => $val];
        });

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
            'availableChannels' => \App\Services\PaymentService::MIDTRANS_AVAILABLE_CHANNELS,
        ]);
    }

    public function updateBranding(UpdateBrandingSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        SystemSetting::set('app_name', trim($validated['app_name']), 'text', 'branding');
        if (isset($validated['app_tagline'])) {
            SystemSetting::set('app_tagline', trim($validated['app_tagline']), 'text', 'branding');
        }
        if (isset($validated['footer_copyright'])) {
            SystemSetting::set('footer_copyright', trim($validated['footer_copyright']), 'text', 'branding');
        }
        if (isset($validated['contact_email'])) {
            SystemSetting::set('contact_email', trim($validated['contact_email']), 'text', 'general');
        }
        if (isset($validated['contact_whatsapp'])) {
            SystemSetting::set('contact_whatsapp', trim($validated['contact_whatsapp']), 'text', 'general');
        }

        $headerLogoStyle = $validated['header_logo_style'] ?? 'logo_only';
        SystemSetting::set('header_logo_style', $headerLogoStyle, 'text', 'branding');

        if ($request->hasFile('logo')) {
            $oldLogo = SystemSetting::where('key', 'app_logo')->value('value');
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }
            $logoPath = $request->file('logo')->store('branding', 'public');
            SystemSetting::set('app_logo', $logoPath, 'file', 'branding');
        }

        if ($request->hasFile('favicon')) {
            $oldFavicon = SystemSetting::where('key', 'app_favicon')->value('value');
            if ($oldFavicon && Storage::disk('public')->exists($oldFavicon)) {
                Storage::disk('public')->delete($oldFavicon);
            }
            $favPath = $request->file('favicon')->store('branding', 'public');
            SystemSetting::set('app_favicon', $favPath, 'file', 'branding');
        }

        return back()->with('success', 'Pengaturan identitas branding & kontak resmi berhasil diperbarui.');
    }

    public function updatePaymentGateway(UpdatePaymentGatewaySettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        SystemSetting::set('payment_method_mode', $validated['payment_method_mode'], 'text', 'payment');

        if ($validated['payment_method_mode'] === 'manual') {
            $accounts = array_values($validated['manual_bank_accounts'] ?? []);
            SystemSetting::set('manual_bank_accounts', $accounts, 'json', 'payment');
        } else {
            $defaultProvider = $validated['payment_gateway_default']
                ?? ($validated['payment_method_mode'] === 'xendit' ? 'xendit' : 'midtrans');
            SystemSetting::set('payment_gateway_default', $defaultProvider, 'text', 'payment');

            if (array_key_exists('payment_enabled_providers', $validated)) {
                SystemSetting::set('payment_enabled_providers', array_values($validated['payment_enabled_providers'] ?? []), 'json', 'payment');
            }

            SystemSetting::set('midtrans_server_key', $validated['midtrans_server_key'] ?? '', 'text', 'payment');
            SystemSetting::set('midtrans_client_key', $validated['midtrans_client_key'] ?? '', 'text', 'payment');
            SystemSetting::set('midtrans_is_production', (bool) ($validated['midtrans_is_production'] ?? false), 'boolean', 'payment');
            if (array_key_exists('xendit_secret_key', $validated)) {
                SystemSetting::set('xendit_secret_key', $validated['xendit_secret_key'] ?? '', 'text', 'payment');
            }
            if (array_key_exists('xendit_callback_token', $validated)) {
                SystemSetting::set('xendit_callback_token', $validated['xendit_callback_token'] ?? '', 'text', 'payment');
            }
            SystemSetting::set('fee_borne_by', $validated['fee_borne_by'] ?? 'team', 'text', 'payment');
            SystemSetting::set('midtrans_va_fee', (int) ($validated['midtrans_va_fee'] ?? 4000), 'integer', 'payment');
            SystemSetting::set('midtrans_qris_fee_percent', (float) ($validated['midtrans_qris_fee_percent'] ?? 0.7), 'float', 'payment');
            SystemSetting::set('midtrans_ewallet_fee_percent', (float) ($validated['midtrans_ewallet_fee_percent'] ?? 1.5), 'float', 'payment');
            SystemSetting::set('midtrans_cc_fee_percent', (float) ($validated['midtrans_cc_fee_percent'] ?? 2.9), 'float', 'payment');
            SystemSetting::set('midtrans_cc_fee_fixed', (int) ($validated['midtrans_cc_fee_fixed'] ?? 2000), 'integer', 'payment');
            SystemSetting::set('midtrans_channels_mode', $validated['midtrans_channels_mode'] ?? 'all', 'text', 'payment');
            SystemSetting::set('midtrans_active_channels', array_values($validated['midtrans_active_channels'] ?? []), 'json', 'payment');
        }

        return back()->with('success', 'Pengaturan metode pembayaran dan nomor rekening / MDR berhasil disimpan.');
    }
}

