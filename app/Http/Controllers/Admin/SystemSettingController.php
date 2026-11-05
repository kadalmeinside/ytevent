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
            'payment_gateway_default' => ['required', 'in:midtrans,xendit'],
            'midtrans_server_key' => ['nullable', 'string', 'max:255'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'midtrans_is_production' => ['required', 'boolean'],
            'xendit_secret_key' => ['nullable', 'string', 'max:255'],
            'xendit_public_key' => ['nullable', 'string', 'max:255'],
            'xendit_is_production' => ['required', 'boolean'],
            'fee_borne_by' => ['required', 'in:team,organizer'],
            'fee_scheme' => ['required', 'in:fixed,percentage,both'],
            'fee_fixed_amount' => ['required', 'numeric', 'min:0'],
            'fee_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'payment_gateway_default.required' => 'Pilih gateway pembayaran utama.',
            'fee_borne_by.required' => 'Pilih penanggung biaya MDR / administrasi.',
        ]);

        foreach ($validated as $key => $val) {
            $type = is_bool($val) ? 'boolean' : (is_numeric($val) ? (str_contains((string) $val, '.') ? 'float' : 'integer') : 'text');
            SystemSetting::set($key, $val, $type, 'payment');
        }

        return back()->with('success', 'Pengaturan kredensial gateway dan aturan biaya MDR berhasil disimpan.');
    }
}
