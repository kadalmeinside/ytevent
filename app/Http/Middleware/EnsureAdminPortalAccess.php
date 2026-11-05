<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'Akun Anda saat ini dinonaktifkan oleh administrator.');
        }

        if (! $user->hasAnyRole(['superadmin', 'finance'])) {
            if ($request->isMethodSafe() && $user->hasRole('team_manager')) {
                return redirect()->route('manager.dashboard')
                    ->with('warning', 'Akses ke portal internal panitia ditolak. Anda dialihkan ke Portal Manajer Tim.');
            }

            abort(403, 'Akses ditolak. Halaman ini hanya untuk panitia internal.');
        }

        return $next($request);
    }
}

