<?php

namespace Tests\Feature;

use App\Mail\RosterLockedMail;
use App\Mail\ScreeningResultMail;
use App\Models\EmailNotificationLog;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Models\User;
use App\Services\AccreditationService;
use App\Services\EmailNotificationService;
use App\Services\ScreeningService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailLogsAndQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);
        Storage::fake('private');
        Storage::fake('public');
    }

    protected function createEnvironment(): array
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $screeningOfficer = User::factory()->create();
        $screeningOfficer->assignRole('screening_officer');

        $manager = User::factory()->create();
        $manager->assignRole('team_manager');

        $event = Event::create([
            'name' => 'Persija Cup U-12',
            'slug' => 'persija-cup-u-12',
            'venue_name' => 'Stadion Soemantri',
            'city' => 'Jakarta Selatan',
            'format' => 'festival',
            'age_category' => 'U-12',
            'min_birth_date' => '2014-01-01',
            'max_birth_date' => '2015-12-31',
            'min_players_per_team' => 2,
            'max_players_per_team' => 18,
            'max_officials_per_team' => 4,
            'required_documents' => ['birth_certificate'],
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'status' => 'open',
        ]);

        $team = Team::create([
            'event_id' => $event->id,
            'user_id' => $manager->id,
            'name' => 'Persija Barat',
            'slug' => 'persija-barat',
            'logo_path' => 'logos/persija-barat.png',
            'origin_association' => 'Jakarta Barat',
            'manager_name' => 'Bambang',
            'manager_whatsapp' => '081234567890',
            'manager_email' => 'bambang@persijabarat.com',
            'head_coach_name' => 'Coach Dedi',
            'terms_accepted_at' => now(),
            'status' => 'active',
            'screening_status' => 'in_review',
        ]);

        return compact('superadmin', 'screeningOfficer', 'manager', 'event', 'team');
    }

    public function test_authorized_admin_can_view_email_logs_dashboard(): void
    {
        $env = $this->createEnvironment();

        EmailNotificationLog::create([
            'event_id' => $env['event']->id,
            'team_id' => $env['team']->id,
            'mailable_type' => 'TeamRegisteredMail',
            'recipient_email' => 'manager@persijabarat.com',
            'subject' => 'Pendaftaran Tim',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($env['superadmin'])
            ->get(route('admin.email-logs.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/EmailLogs/Index')
            ->has('logs.data', 1)
            ->where('metrics.total', 1)
            ->where('metrics.sent', 1)
        );
    }

    public function test_unauthorized_user_is_forbidden_from_email_logs(): void
    {
        $env = $this->createEnvironment();

        // Team Manager cannot access
        $this->actingAs($env['manager'])
            ->get(route('admin.email-logs.index'))
            ->assertRedirect(route('manager.dashboard'));

        // User without email_logs.manage permission is forbidden
        $staffWithoutPermission = User::factory()->create();
        $this->actingAs($staffWithoutPermission)
            ->get(route('admin.email-logs.index'))
            ->assertForbidden();
    }

    public function test_screening_completion_triggers_screening_result_email(): void
    {
        Mail::fake();
        $env = $this->createEnvironment();

        $player = Player::create([
            'team_id' => $env['team']->id,
            'event_id' => $env['event']->id,
            'full_name' => 'Andritany Ardhiyasa',
            'jersey_number' => 1,
            'position' => 'GK',
            'birth_date' => '2014-05-10',
            'nik' => '3175011005140001',
            'screening_status' => 'submitted',
        ]);

        $service = app(ScreeningService::class);
        $service->verifyPlayer($player, $env['screeningOfficer'], 'passed');

        // Check log recorded
        $this->assertDatabaseHas('email_notification_logs', [
            'team_id' => $env['team']->id,
            'mailable_type' => 'ScreeningResultMail',
            'recipient_email' => 'bambang@persijabarat.com',
            'status' => 'sent',
        ]);

        Mail::assertSent(ScreeningResultMail::class, function ($mail) use ($env) {
            return $mail->hasTo('bambang@persijabarat.com') && $mail->team->id === $env['team']->id;
        });
    }

    public function test_roster_lock_triggers_roster_locked_email(): void
    {
        Mail::fake();
        $env = $this->createEnvironment();

        Player::create([
            'team_id' => $env['team']->id,
            'event_id' => $env['event']->id,
            'full_name' => 'Pemain 1',
            'jersey_number' => 1,
            'position' => 'GK',
            'birth_date' => '2014-05-10',
            'nik' => '3175011005140001',
            'screening_status' => 'passed',
        ]);

        Player::create([
            'team_id' => $env['team']->id,
            'event_id' => $env['event']->id,
            'full_name' => 'Pemain 2',
            'jersey_number' => 20,
            'position' => 'GK',
            'birth_date' => '2014-06-10',
            'nik' => '3175011006140002',
            'screening_status' => 'passed',
        ]);

        TeamOfficial::create([
            'team_id' => $env['team']->id,
            'event_id' => $env['event']->id,
            'full_name' => 'Coach Dedi',
            'role' => 'Head Coach',
            'screening_status' => 'passed',
        ]);

        $accreditationService = app(AccreditationService::class);
        $accreditationService->lockRoster($env['team'], $env['manager']);

        $this->assertDatabaseHas('email_notification_logs', [
            'team_id' => $env['team']->id,
            'mailable_type' => 'RosterLockedMail',
            'recipient_email' => 'bambang@persijabarat.com',
            'status' => 'sent',
        ]);

        Mail::assertSent(RosterLockedMail::class, function ($mail) use ($env) {
            return $mail->hasTo('bambang@persijabarat.com') && $mail->team->id === $env['team']->id;
        });
    }

    public function test_admin_can_retry_failed_email_and_transition_to_sent(): void
    {
        Mail::fake();
        $env = $this->createEnvironment();

        $mailable = new RosterLockedMail($env['team']);

        $log = EmailNotificationLog::create([
            'event_id' => $env['event']->id,
            'team_id' => $env['team']->id,
            'mailable_type' => 'RosterLockedMail',
            'recipient_email' => 'bambang@persijabarat.com',
            'subject' => 'Test Skuad Terkunci',
            'payload' => serialize($mailable),
            'status' => 'failed',
            'attempts' => 1,
            'error_message' => 'Connection timeout to SMTP server',
        ]);

        $this->assertTrue($log->isRetryable());

        $response = $this->actingAs($env['superadmin'])
            ->post(route('admin.email-logs.retry', ['log' => $log->id]));

        $response->assertSessionHas('success');

        $freshLog = $log->fresh();
        $this->assertSame('sent', $freshLog->status);
        $this->assertSame(2, $freshLog->attempts);
        $this->assertNull($freshLog->error_message);
        $this->assertNotNull($freshLog->sent_at);

        Mail::assertSent(RosterLockedMail::class);
    }

    public function test_admin_can_bulk_retry_all_failed_emails(): void
    {
        Mail::fake();
        $env = $this->createEnvironment();

        $mailable = new RosterLockedMail($env['team']);
        $payload = serialize($mailable);

        $log1 = EmailNotificationLog::create([
            'event_id' => $env['event']->id,
            'team_id' => $env['team']->id,
            'mailable_type' => 'RosterLockedMail',
            'recipient_email' => 'penerima1@gmail.com',
            'payload' => $payload,
            'status' => 'failed',
            'attempts' => 1,
        ]);

        $log2 = EmailNotificationLog::create([
            'event_id' => $env['event']->id,
            'team_id' => $env['team']->id,
            'mailable_type' => 'RosterLockedMail',
            'recipient_email' => 'penerima2@gmail.com',
            'payload' => $payload,
            'status' => 'failed',
            'attempts' => 1,
        ]);

        $response = $this->actingAs($env['superadmin'])
            ->post(route('admin.email-logs.retry-failed'));

        $response->assertSessionHas('success');

        $this->assertSame('sent', $log1->fresh()->status);
        $this->assertSame('sent', $log2->fresh()->status);
        Mail::assertSent(RosterLockedMail::class, 2);
    }

    public function test_retry_rejects_already_sent_log(): void
    {
        $env = $this->createEnvironment();

        $log = EmailNotificationLog::create([
            'event_id' => $env['event']->id,
            'team_id' => $env['team']->id,
            'mailable_type' => 'TeamRegisteredMail',
            'recipient_email' => 'bambang@persijabarat.com',
            'payload' => 'payload',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->assertFalse($log->isRetryable());

        $response = $this->actingAs($env['superadmin'])
            ->post(route('admin.email-logs.retry', ['log' => $log->id]));

        $response->assertSessionHas('error');
    }
}

