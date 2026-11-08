<?php

namespace Tests\Feature;

use App\Models\EmailNotificationLog;
use App\Models\User;
use App\Services\EmailNotificationService;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
    }

    public function test_simulation_persona_switch_is_blocked_when_module_disabled(): void
    {
        config(['simulation.master_enabled' => false]);

        $this->assertGuest();
        $this->post('/simulation/switch-persona', ['role' => 'superadmin'])->assertForbidden();
        $this->assertGuest();
        $this->get('/admin/dashboard')->assertRedirect();
    }

    public function test_simulation_module_defaults_to_disabled_in_config_file(): void
    {
        $config = require config_path('simulation.php');

        // phpunit.xml menyalakan modul untuk test simulasi; default kode (tanpa env) harus mati.
        $source = file_get_contents(config_path('simulation.php'));
        $this->assertStringContainsString("env('SIMULATION_MODULE_ENABLED', false)", $source);
        $this->assertStringContainsString("env('APP_ENV') !== 'production'", $source);
        $this->assertIsArray($config);
    }

    public function test_security_headers_are_present_on_public_pages(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=(self)', $response->headers->get('Permissions-Policy'));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=_-]+'/", $csp);
    }

    public function test_hsts_header_is_sent_over_https(): void
    {
        $response = $this->get('https://ytevent.test/');
        $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('Strict-Transport-Security'));
    }

    public function test_html_sanitizer_strips_xss_vectors(): void
    {
        $dirty = '<p onclick="alert(1)" style="background:url(javascript:alert(1))">Halo</p>'
            . '<script>alert(1)</script>'
            . '<img src=x onerror=alert(1)>'
            . '<a href="javascript:alert(1)">klik</a>'
            . '<a href="jav&#x09;ascript:alert(1)">klik2</a>'
            . '<a href="https://persija.id" target="_blank" onmouseover="x()">aman</a>'
            . '<iframe src="https://evil.test"></iframe>'
            . '<svg onload=alert(1)><circle/></svg>'
            . '<div style="text-align:center">tengah</div>';

        $clean = (string) HtmlSanitizer::clean($dirty);

        $this->assertStringNotContainsStringIgnoringCase('<script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onmouseover', $clean);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<svg', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<img', $clean);
        $this->assertStringContainsString('Halo', $clean);
        $this->assertStringContainsString('href="https://persija.id"', $clean);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $clean);
        $this->assertStringContainsString('text-align:center', $clean);
    }

    public function test_html_sanitizer_handles_empty_input(): void
    {
        $this->assertNull(HtmlSanitizer::clean(null));
        $this->assertNull(HtmlSanitizer::clean('   '));
    }

    public function test_public_verify_endpoint_is_rate_limited(): void
    {
        RateLimiter::clear('public-verify');

        $last = null;
        for ($i = 0; $i < 35; $i++) {
            $last = $this->get('/verify/player/TIDAK-ADA-' . $i);
        }

        $last->assertStatus(429);
    }

    public function test_password_reset_request_is_rate_limited(): void
    {
        $last = null;
        for ($i = 0; $i < 7; $i++) {
            $last = $this->post('/forgot-password', ['email' => 'spam@example.com']);
        }

        $last->assertStatus(429);
    }

    public function test_finance_and_screening_cannot_mutate_events_or_tournament(): void
    {
        $event = \App\Models\Event::create([
            'name' => 'Event Uji Otorisasi',
            'slug' => 'event-uji-otorisasi',
            'age_category' => 'U-12',
            'format' => 'cup',
            'match_start_date' => now()->addDays(5)->format('Y-m-d'),
            'match_end_date' => now()->addDays(7)->format('Y-m-d'),
            'registration_open_at' => now()->subDay(),
            'registration_close_at' => now()->addDays(2),
            'registration_fee' => 0,
            'max_teams' => 8,
            'payment_deadline_hours' => 24,
            'min_birth_date' => '2012-01-01',
            'max_birth_date' => '2014-12-31',
            'min_players_per_team' => 2,
            'max_players_per_team' => 18,
            'min_gk_per_team' => 1,
            'max_officials_per_team' => 3,
            'venue_name' => 'Stadion Uji',
            'city' => 'Jakarta',
            'status' => 'open',
            'required_documents' => [],
        ]);

        foreach (['finance', 'screening_officer'] as $role) {
            $user = User::factory()->create(['is_active' => true]);
            $user->assignRole($role);

            $this->actingAs($user)->post("/admin/events/{$event->id}/groups/toggle-lock")->assertForbidden();
            $this->actingAs($user)->post("/admin/events/{$event->id}/certificates/toggle-release")->assertForbidden();
            $this->actingAs($user)->post('/admin/events', [])->assertForbidden();
            $this->actingAs($user)->post("/admin/events/{$event->id}/update", [])->assertForbidden();
        }
    }

    public function test_tampered_email_payload_is_rejected_and_never_unserialized(): void
    {
        $signed = EmailNotificationService::signPayload(serialize(['x' => 1]));
        $this->assertSame(serialize(['x' => 1]), EmailNotificationService::verifyPayload($signed));

        // Payload dimanipulasi (mis. object injection) tanpa tanda tangan valid.
        $this->assertNull(EmailNotificationService::verifyPayload('deadbeef.' . serialize(['x' => 2])));
        $this->assertNull(EmailNotificationService::verifyPayload(serialize(['x' => 3])));
        $this->assertNull(EmailNotificationService::verifyPayload(null));
        $this->assertTrue(class_exists(EmailNotificationLog::class));
    }

    public function test_seeder_does_not_use_known_default_passwords_in_production(): void
    {
        $this->app['env'] = 'production';

        User::withTrashed()->forceDelete();
        (new \Database\Seeders\RolePermissionSeeder())->run();

        $admin = User::where('email', 'superadmin@persijadevelopment.id')->first();
        $this->assertNotNull($admin);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('Admin12345', $admin->password));
    }

    public function test_custom_404_error_page_renders_branded_view(): void
    {
        $response = $this->get('/halaman-acak-yang-pasti-tidak-ada-12345');
        $response->assertNotFound();
        $response->assertSee('404 NOT FOUND');
        $response->assertSee('Halaman Tidak Ditemukan');
        $response->assertSee('Kembali ke Beranda');
    }
}
