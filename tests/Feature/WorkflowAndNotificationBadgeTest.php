<?php

namespace Tests\Feature;

use App\Models\EmailNotificationLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkflowAndNotificationBadgeTest extends TestCase
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

    protected function createEnvironment(int $minPlayers = 3): array
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $manager = User::factory()->create();
        $manager->assignRole('team_manager');

        $event = Event::create([
            'name' => 'Persija Young Tiger Cup U-12',
            'slug' => 'young-tiger-cup-u-12',
            'venue_name' => 'Stadion Soemantri Brodjonegoro',
            'city' => 'Jakarta Selatan',
            'format' => 'festival',
            'age_category' => 'U-12',
            'min_birth_date' => '2014-01-01',
            'max_birth_date' => '2015-12-31',
            'min_players_per_team' => $minPlayers,
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
            'name' => 'Macan Kemayoran Muda',
            'slug' => 'macan-kemayoran-muda',
            'logo_path' => 'logos/macan-muda.png',
            'origin_association' => 'Askot PSSI Jakarta Pusat',
            'manager_name' => 'Ismed Sofyan',
            'manager_whatsapp' => '081288887777',
            'manager_email' => $manager->email,
            'head_coach_name' => 'Thomas Doll',
            'head_coach_license' => 'UEFA Pro',
            'terms_accepted_at' => now(),
            'status' => 'pending_payment',
            'screening_status' => 'not_started',
        ]);

        return compact('superadmin', 'manager', 'event', 'team');
    }

    protected function seedPlayer(Team $team, int $number, string $pos, string $status): Player
    {
        $player = Player::create([
            'team_id' => $team->id,
            'event_id' => $team->event_id,
            'full_name' => "Pemain {$number}",
            'jersey_name' => "PEMAIN {$number}",
            'jersey_number' => $number,
            'position' => $pos,
            'dominant_foot' => 'Right',
            'birth_place' => 'Jakarta',
            'birth_date' => '2014-05-10',
            'nik' => str_pad((string) (3171000000000000 + $number), 16, '0', STR_PAD_LEFT),
            'nisn' => str_pad((string) (100000000 + $number), 10, '0', STR_PAD_LEFT),
            'photo_path' => "players/{$team->id}/photo_{$number}.jpg",
            'screening_status' => $status,
            'is_locked' => $status === 'passed',
            'accreditation_code' => $status === 'passed' ? "YTE-2026-P{$number}" : null,
        ]);

        PlayerDocument::create([
            'player_id' => $player->id,
            'document_type' => 'birth_certificate',
            'file_path' => "players/{$team->id}/doc_{$number}.jpg",
            'original_name' => "akta_{$number}.jpg",
            'mime_type' => 'image/jpeg',
            'file_size' => 120000,
            'validation_status' => $status === 'passed' ? 'valid' : 'pending',
        ]);

        return $player;
    }

    public function test_admin_shared_notifications_provide_live_operational_queue_counts(): void
    {
        $env = $this->createEnvironment(3);
        /** @var Team $team */
        $team = $env['team'];
        $team->update(['status' => 'active', 'screening_status' => 'in_review']);

        Payment::create([
            'team_id' => $team->id,
            'event_id' => $env['event']->id,
            'invoice_number' => 'INV-TEST-001',
            'channel' => 'manual_transfer',
            'amount' => 1500000,
            'admin_fee' => 0,
            'total_amount' => 1500000,
            'status' => 'waiting_verification',
        ]);

        $this->seedPlayer($team, 1, 'GK', 'submitted');
        $this->seedPlayer($team, 2, 'DF', 'submitted');

        EmailNotificationLog::create([
            'team_id' => $team->id,
            'event_id' => $env['event']->id,
            'recipient_email' => 'manager@example.com',
            'mailable_type' => \App\Mail\TeamRegisteredMail::class,
            'subject' => 'Test Failed Email',
            'status' => 'failed',
            'attempts' => 3,
        ]);

        $response = $this->actingAs($env['superadmin'])->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('adminNotifications.pending_payments_count', 1)
            ->where('adminNotifications.pending_screening_players_count', 2)
            ->where('adminNotifications.pending_screening_teams_count', 1)
            ->where('adminNotifications.failed_emails_count', 1)
            ->where('adminNotifications.total_actionable', 4)
        );
    }

    public function test_manager_workflow_transitions_from_step_1_to_ready_to_lock_and_accredited(): void
    {
        $env = $this->createEnvironment(3);
        /** @var Team $team */
        $team = $env['team'];
        $manager = $env['manager'];

        // Step 1: Unpaid -> current_step = 1, payment_required banner with deadline
        $team->update(['slot_expires_at' => now()->addHours(24)]);
        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.workflow.current_step', 1)
                ->where('team.workflow.banner.type', 'payment_required')
                ->where('team.workflow.banner.deadline', fn ($val) => ! empty($val))
                ->where('team.workflow.badges.payment.text', 'WAJIB')
            );

        // Expired status -> slot_expired banner
        $team->update(['status' => 'expired']);
        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.workflow.banner.type', 'slot_expired')
                ->where('team.workflow.banner.cta_label', 'Ajukan Ulang Slot Pendaftaran')
            );

        // Mark team paid/active -> Step 2 (Roster incomplete)
        $team->update(['status' => 'active']);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.workflow.current_step', 2)
                ->where('team.workflow.banner.type', 'roster_incomplete')
                ->where('team.workflow.badges.payment.text', 'LUNAS')
            );

        // Add 3 submitted players (including 2 GK) -> Step 3 (Officials incomplete)
        $p1 = $this->seedPlayer($team, 1, 'GK', 'submitted');
        $p2 = $this->seedPlayer($team, 12, 'GK', 'submitted');
        $p3 = $this->seedPlayer($team, 10, 'FW', 'submitted');

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.workflow.current_step', 3)
                ->where('team.workflow.banner.type', 'officials_incomplete')
                ->where('team.workflow.badges.officials.text', 'WAJIB 1+')
            );

        // Add 1 Official -> Step 4 (Waiting screening by committee)
        TeamOfficial::create([
            'team_id' => $team->id,
            'event_id' => $team->event_id,
            'full_name' => 'Coach Thomas Doll',
            'role' => 'Head Coach',
            'license_number' => 'UEFA-PRO-01',
            'phone' => '081299990000',
        ]);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.workflow.current_step', 4)
                ->where('team.workflow.banner.type', 'waiting_screening')
                ->where('team.workflow.badges.players.text', '3 PROSES')
            );

        // Pass all 3 players -> Stage 4 Ready to Lock Accreditation Warning!
        Player::where('team_id', $team->id)->update([
            'screening_status' => 'passed',
            'is_locked' => true,
        ]);
        $team->update(['screening_status' => 'all_passed']);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.workflow.current_step', 4)
                ->where('team.workflow.is_ready_to_lock', true)
                ->where('team.workflow.banner.type', 'ready_to_lock')
                ->where('team.workflow.badges.accreditation.text', 'SIAP KUNCI')
                ->where('team.workflow.badges.players.text', '3 SAH')
            );

        // Lock Final Roster -> Step 5 Accredited & Players page stays accessible in read-only mode
        $this->actingAs($manager)
            ->post(route('manager.teams.lock-roster', ['team' => $team->id]))
            ->assertRedirect();

        $this->actingAs($manager)
            ->get(route('manager.players'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.status', 'locked_roster')
                ->where('team.workflow.current_step', 5)
                ->where('team.workflow.is_roster_locked', true)
                ->where('team.workflow.banner.type', 'accredited')
                ->where('team.workflow.badges.accreditation.text', 'FINAL')
                ->has('players', 3)
            );
    }

    public function test_quick_document_verification_endpoint_returns_json_for_async_wizard(): void
    {
        $env = $this->createEnvironment(3);
        $team = $env['team'];
        $team->update(['status' => 'active', 'screening_status' => 'in_review']);

        $player = $this->seedPlayer($team, 9, 'FW', 'submitted');
        $document = $player->documents()->first();

        $response = $this->actingAs($env['superadmin'])
            ->postJson(route('admin.screening.document.verify', ['document' => $document->id]), [
                'validation_status' => 'valid',
                'notes' => null,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'document' => [
                    'id' => $document->id,
                    'validation_status' => 'valid',
                ],
            ]);

        $this->assertDatabaseHas('player_documents', [
            'id' => $document->id,
            'validation_status' => 'valid',
        ]);
    }

    public function test_admin_can_view_full_team_dossier_page_with_players_officials_and_workflow(): void
    {
        $env = $this->createEnvironment(3);
        $team = $env['team'];
        $team->update(['status' => 'active', 'screening_status' => 'in_review']);

        $this->seedPlayer($team, 1, 'GK', 'passed');
        $this->seedPlayer($team, 10, 'FW', 'submitted');

        TeamOfficial::create([
            'team_id' => $team->id,
            'event_id' => $team->event_id,
            'full_name' => 'Coach Thomas Doll',
            'role' => 'Head Coach',
            'license_number' => 'UEFA-PRO-01',
            'phone' => '081299990000',
        ]);

        $this->actingAs($env['superadmin'])
            ->get(route('admin.teams.show', ['team' => $team->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Teams/Show')
                ->where('team.id', $team->id)
                ->where('team.workflow.counts.players_total', 2)
                ->where('team.workflow.counts.players_passed', 1)
                ->where('team.workflow.counts.players_submitted', 1)
                ->has('players', 2)
                ->has('officials', 1)
            );
    }

    public function test_event_with_min_1_gk_and_required_coach_license_enforces_rules_correctly(): void
    {
        $env = $this->createEnvironment(2);
        /** @var Event $event */
        $event = $env['event'];
        /** @var Team $team */
        $team = $env['team'];
        $manager = $env['manager'];

        // Configure event: min 1 GK, show_coach_license = true, require_coach_license = true
        $event->update([
            'min_gk_per_team' => 1,
            'show_coach_license' => true,
            'require_coach_license' => true,
        ]);

        // Clear head_coach_license on team to test mandatory coach license enforcement
        $team->update([
            'status' => 'active',
            'head_coach_license' => null,
        ]);

        // Add 2 passed players with only 1 GK (which meets min_gk_per_team = 1)
        $this->seedPlayer($team, 1, 'GK', 'passed');
        $this->seedPlayer($team, 9, 'FW', 'passed');

        // Add Head Coach without license_number
        $coach = TeamOfficial::create([
            'team_id' => $team->id,
            'event_id' => $team->event_id,
            'full_name' => 'Coach Tanpa Lisensi',
            'role' => 'Head Coach',
            'license_number' => null,
            'phone' => '081299990000',
        ]);

        // Because require_coach_license is true and license is empty, team is at Step 3 (LISENSI WAJIB) and NOT ready to lock
        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.workflow.counts.min_gk_required', 1)
                ->where('team.workflow.is_gk_met', true)
                ->where('team.workflow.is_gk_passed_met', true)
                ->where('team.workflow.is_coach_license_met', false)
                ->where('team.workflow.is_ready_to_lock', false)
                ->where('team.workflow.badges.officials.text', 'LISENSI WAJIB')
            );

        // Attempting to update Head Coach without license_number fails validation
        $this->actingAs($manager)
            ->post(route('manager.officials.update', ['team' => $team->id, 'official' => $coach->id]), [
                'full_name' => 'Coach Tanpa Lisensi',
                'role' => 'Head Coach',
                'license_number' => '',
                'phone' => '081299990000',
            ])
            ->assertSessionHasErrors(['license_number']);

        // Updating Head Coach WITH license_number succeeds and makes team ready to lock with just 1 GK!
        $this->actingAs($manager)
            ->post(route('manager.officials.update', ['team' => $team->id, 'official' => $coach->id]), [
                'full_name' => 'Coach Berlisensi Resmi',
                'role' => 'Head Coach',
                'license_number' => 'AFC-C-2026',
                'phone' => '081299990000',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('team.workflow.is_coach_license_met', true)
                ->where('team.workflow.is_ready_to_lock', true)
                ->where('team.workflow.badges.accreditation.text', 'SIAP KUNCI')
            );

        // Locking roster with 1 GK succeeds
        $this->actingAs($manager)
            ->post(route('manager.teams.lock-roster', ['team' => $team->id]))
            ->assertSessionHasNoErrors();

        $this->assertEquals('locked_roster', $team->fresh()->status);
    }
}
