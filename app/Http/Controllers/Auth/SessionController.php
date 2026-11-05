<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function showManagerLogin(): Response
    {
        return Inertia::render('Auth/ManagerLogin');
    }

    public function managerLogin(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate(requireAdminPortal: false);
        $request->session()->regenerate();

        if ($user->hasAnyRole(['superadmin', 'finance'])) {
            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->intended(route('manager.dashboard'));
    }

    public function adminEntry(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if ($user->hasAnyRole(['superadmin', 'finance'])) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('manager.dashboard')
            ->with('warning', 'Akun Manajer Tim tidak memiliki akses ke portal internal panitia.');
    }

    public function showAdminLogin(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            if ($user->hasAnyRole(['superadmin', 'finance'])) {
                return redirect()->route('admin.dashboard');
            }

            return redirect()->route('manager.dashboard');
        }

        return Inertia::render('Auth/AdminLogin');
    }

    public function adminLogin(LoginRequest $request): RedirectResponse
    {
        $request->authenticate(requireAdminPortal: true);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function adminLogout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

