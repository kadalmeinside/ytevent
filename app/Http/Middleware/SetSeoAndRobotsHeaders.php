<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSeoAndRobotsHeaders
{
    /**
     * Sembunyikan area admin, portal manajer, media privat, dan halaman verifikasi QR dari indeks mesin pencari.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldNoIndex($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        } else {
            $response->headers->set('X-Robots-Tag', 'index, follow, max-image-preview:large, max-snippet:-1');
        }

        return $response;
    }

    protected function shouldNoIndex(Request $request): bool
    {
        return $request->is('admin')
            || $request->is('admin/*')
            || $request->is('manager')
            || $request->is('manager/*')
            || $request->is('private-media/*')
            || $request->is('verify/player/*')
            || $request->is('forgot-password')
            || $request->is('reset-password*');
    }
}

