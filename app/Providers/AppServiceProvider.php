<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('observability.slow_queries.enabled')) {
            DB::listen(function ($query): void {
                if ($query->time >= config('observability.slow_queries.threshold_ms')) {
                    Log::channel(config('observability.slow_queries.channel', 'stack'))->warning('Slow database query detected.', [
                        'duration_ms' => $query->time,
                        'sql' => $query->sql,
                        'bindings_count' => count($query->bindings),
                    ]);
                }
            });
        }

        if ($this->app->isProduction()) {
            // Guard rail konfigurasi: tolak boot bila konfigurasi berbahaya aktif di production.
            if (config('app.debug')) {
                throw new \RuntimeException('APP_DEBUG harus false di production.');
            }

            if (config('simulation.master_enabled')) {
                throw new \RuntimeException('Modul simulasi tidak boleh aktif di production.');
            }

            URL::forceScheme('https');
        }

        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });

        RateLimiter::for('team-registration', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Anti-enumerasi kode QR akreditasi publik.
        RateLimiter::for('public-verify', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Anti email-bombing & brute force token reset kata sandi.
        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(10)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            ];
        });

        // Webhook payment gateway (signature tetap divalidasi di controller).
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Batas umum untuk endpoint scanner QR panitia.
        RateLimiter::for('qr-lookup', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
