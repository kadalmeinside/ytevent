<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'systemSettings' => fn () => [
                'app_name' => SystemSetting::get('app_name', config('app.name', 'YTEvent Football')),
                'app_tagline' => SystemSetting::get('app_tagline', 'Multi-Event Competition Portal'),
                'header_logo_style' => SystemSetting::get('header_logo_style', 'logo_only'),
                'app_logo_url' => SystemSetting::get('app_logo') ? Storage::disk('public')->url(SystemSetting::get('app_logo')) : null,
                'app_favicon_url' => SystemSetting::get('app_favicon') ? Storage::disk('public')->url(SystemSetting::get('app_favicon')) : null,
                'footer_copyright' => SystemSetting::get('footer_copyright', 'YTEvent Multi-Event Football Tournament & League Management System'),
                'contact_whatsapp' => SystemSetting::get('contact_whatsapp'),
                'payment_method_mode' => SystemSetting::get('payment_method_mode', 'midtrans'),
                'manual_bank_accounts' => SystemSetting::get('manual_bank_accounts', []),
                'fee_borne_by' => SystemSetting::get('fee_borne_by', 'team'),
                'fee_fixed_amount' => (int) SystemSetting::get('midtrans_va_fee', 4000),
            ],
        ];
    }
}
