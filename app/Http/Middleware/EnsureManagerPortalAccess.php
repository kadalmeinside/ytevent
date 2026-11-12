<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureManagerPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Akun Anda saat ini dinonaktifkan oleh panitia.');
        }

        if (! $user->hasAnyRole(['team_manager', 'participant_parent']) && ! $user->can('manager.portal.access')) {
            if ($request->isMethodSafe() && $user->hasAnyRole(['superadmin', 'finance'])) {
                return redirect()->route('admin.dashboard');
            }

            abort(403, 'Akses ditolak. Halaman ini khusus untuk Akun Peserta & Manajer Tim.');
        }

        return $next($request);
    }
}

