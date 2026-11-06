<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'min:3', 'max:160'],
            'app_tagline' => ['nullable', 'string', 'max:255'],
            'footer_copyright' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'header_logo_style' => ['nullable', 'string', 'in:logo_only,logo_and_text'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:1024'],
        ], [
            'app_name.required' => 'Nama situs/aplikasi wajib diisi.',
            'logo.max' => 'Ukuran berkas logo maksimal 2 MB.',
            'favicon.max' => 'Ukuran berkas favicon maksimal 1 MB.',
        ]);

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

    public function updatePaymentGateway(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method_mode' => ['required', 'in:manual,midtrans'],
            'manual_bank_accounts' => ['nullable', 'array'],
            'manual_bank_accounts.*.bank_name' => ['required_if:payment_method_mode,manual', 'string', 'max:100'],
            'manual_bank_accounts.*.account_number' => ['required_if:payment_method_mode,manual', 'string', 'max:50'],
            'manual_bank_accounts.*.account_holder' => ['required_if:payment_method_mode,manual', 'string', 'max:150'],
            'manual_bank_accounts.*.notes' => ['nullable', 'string', 'max:150'],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'midtrans_is_production' => ['nullable', 'boolean'],
            'fee_borne_by' => ['nullable', 'in:team,organizer'],
            'midtrans_va_fee' => ['nullable', 'numeric', 'min:0'],
            'midtrans_qris_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'midtrans_ewallet_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'midtrans_cc_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'midtrans_cc_fee_fixed' => ['nullable', 'numeric', 'min:0'],
        ], [
            'payment_method_mode.required' => 'Pilih salah satu metode pembayaran aktif.',
            'manual_bank_accounts.*.bank_name.required_if' => 'Nama bank penerima wajib diisi.',
            'manual_bank_accounts.*.account_number.required_if' => 'Nomor rekening wajib diisi.',
            'manual_bank_accounts.*.account_holder.required_if' => 'Nama pemilik rekening wajib diisi.',
        ]);

        SystemSetting::set('payment_method_mode', $validated['payment_method_mode'], 'text', 'payment');

        if ($validated['payment_method_mode'] === 'manual') {
            $accounts = array_values($validated['manual_bank_accounts'] ?? []);
            SystemSetting::set('manual_bank_accounts', $accounts, 'json', 'payment');
        } else {
            SystemSetting::set('midtrans_server_key', $validated['midtrans_server_key'] ?? '', 'text', 'payment');
            SystemSetting::set('midtrans_client_key', $validated['midtrans_client_key'] ?? '', 'text', 'payment');
            SystemSetting::set('midtrans_is_production', (bool) ($validated['midtrans_is_production'] ?? false), 'boolean', 'payment');
            SystemSetting::set('fee_borne_by', $validated['fee_borne_by'] ?? 'team', 'text', 'payment');
            SystemSetting::set('midtrans_va_fee', (int) ($validated['midtrans_va_fee'] ?? 4000), 'integer', 'payment');
            SystemSetting::set('midtrans_qris_fee_percent', (float) ($validated['midtrans_qris_fee_percent'] ?? 0.7), 'float', 'payment');
            SystemSetting::set('midtrans_ewallet_fee_percent', (float) ($validated['midtrans_ewallet_fee_percent'] ?? 1.5), 'float', 'payment');
            SystemSetting::set('midtrans_cc_fee_percent', (float) ($validated['midtrans_cc_fee_percent'] ?? 2.9), 'float', 'payment');
            SystemSetting::set('midtrans_cc_fee_fixed', (int) ($validated['midtrans_cc_fee_fixed'] ?? 2000), 'integer', 'payment');
        }

        return back()->with('success', 'Pengaturan metode pembayaran dan nomor rekening / MDR berhasil disimpan.');
    }
}

