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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase4OperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $finance;
    protected User $manager;
    protected Event $event;
    protected Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);

        $this->superadmin = User::create([
            'name' => 'Superadmin Phase 4',
            'email' => 'superadmin-p4@persija.id',
            'password' => Hash::make('Admin12345'),
            'is_active' => true,
        ]);
        $this->superadmin->assignRole('superadmin');

        $this->finance = User::create([
            'name' => 'Finance Phase 4',
            'email' => 'finance-p4@persija.id',
            'password' => Hash::make('Finance12345'),
            'is_active' => true,
        ]);
        $this->finance->assignRole('finance');

        $this->manager = User::create([
            'name' => 'Manager Phase 4',
            'email' => 'manager-p4@ssb.id',
            'phone' => '081299990000',
            'password' => Hash::make('Manager12345'),
            'is_active' => true,
        ]);
        $this->manager->assignRole('team_manager');

        $this->event = Event::create([
            'name' => 'Young Tiger Cup Phase 4',
            'slug' => 'young-tiger-cup-phase-4',
            'venue_name' => 'Stadion Madya GBK',
            'city' => 'Jakarta Pusat',
            'format' => 'cup',
            'age_category' => 'U-14',
            'min_birth_date' => '2012-01-01',
            'max_birth_date' => '2013-12-31',
            'min_players_per_team' => 2,
            'max_players_per_team' => 18,
            'min_gk_per_team' => 1,
            'max_officials_per_team' => 4,
            'show_coach_license' => true,
            'require_coach_license' => false,
            'required_documents' => ['birth_certificate'],
            'max_teams' => 2,
            'registration_fee' => 1500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'status' => 'open',
        ]);

        $this->team = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $this->manager->id,
            'name' => 'SSB Macan Kemayoran Muda',
            'slug' => 'ssb-macan-kemayoran-muda',
            'logo_path' => 'teams/logos/dummy.png',
            'origin_association' => 'Askot PSSI Jakarta Pusat',
            'manager_name' => $this->manager->name,
            'manager_whatsapp' => '081299990000',
            'manager_email' => $this->manager->email,
            'head_coach_name' => 'Coach Bambang',
            'head_coach_license' => 'AFC C',
            'terms_accepted_at' => now(),
            'status' => 'active',
            'screening_status' => 'not_started',
        ]);
    }

    public function test_superadmin_can_save_notes_force_lock_unlock_disqualify_and_reactivate_team(): void
    {
        // Save internal admin notes
        $this->actingAs($this->superadmin)
            ->put(route('admin.teams.update-notes', ['team' => $this->team->id]), [
                'admin_notes' => 'Berkas fisik dibawa saat TM.',
            ])
            ->assertRedirect();

        $this->assertSame('Berkas fisik dibawa saat TM.', $this->team->fresh()->admin_notes);

        // Prepare team to be ready for lock (2 passed players incl 1 GK + 1 official)
        TeamOfficial::create([
            'team_id' => $this->team->id,
            'event_id' => $this->event->id,
            'full_name' => 'Coach Bambang',
            'role' => 'Head Coach',
            'license_number' => 'AFC C',
        ]);

        for ($i = 1; $i <= 2; $i++) {
            Player::create([
                'team_id' => $this->team->id,
                'event_id' => $this->event->id,
                'full_name' => "Pemain Sah {$i}",
                'jersey_number' => $i,
                'position' => $i === 1 ? 'GK' : 'FW',
                'dominant_foot' => 'Right',
                'birth_date' => '2012-06-15',
                'nik' => str_pad((string) (3171000000000000 + $i), 16, '0', STR_PAD_LEFT),
                'photo_path' => 'players/photos/dummy.jpg',
                'screening_status' => 'passed',
                'is_locked' => true,
                'accreditation_code' => 'YTC-P4-00' . $i,
            ]);
        }

        // Force Lock Roster by Admin
        $this->actingAs($this->superadmin)
            ->post(route('admin.teams.force-lock-roster', ['team' => $this->team->id]))
            ->assertRedirect();

        $this->assertSame('locked_roster', $this->team->fresh()->status);
        $this->assertStringContainsString('FORCE LOCK ROSTER', (string) $this->team->fresh()->admin_notes);

        // Unlock Roster by Admin
        $this->actingAs($this->superadmin)
            ->post(route('admin.teams.unlock-roster', ['team' => $this->team->id]), [
                'reason' => 'Pergantian kiper cedera dengan surat dokter rumah sakit.',
            ])
            ->assertRedirect();

        $this->assertSame('active', $this->team->fresh()->status);
        $this->assertNull($this->team->fresh()->roster_locked_at);
        $this->assertStringContainsString('UNLOCK ROSTER', (string) $this->team->fresh()->admin_notes);

        // Disqualify Team
        $this->actingAs($this->superadmin)
            ->post(route('admin.teams.update-status', ['team' => $this->team->id]), [
                'status' => 'disqualified',
                'reason' => 'Melanggar regulasi sportivitas turnamen.',
            ])
            ->assertRedirect();

        $this->assertSame('disqualified', $this->team->fresh()->status);

        // Reactivate Team
        $this->actingAs($this->superadmin)
            ->post(route('admin.teams.update-status', ['team' => $this->team->id]), [
                'status' => 'active',
                'reason' => 'Banding diterima oleh komisi disiplin turnamen.',
            ])
            ->assertRedirect();

        $this->assertSame('active', $this->team->fresh()->status);
    }

    public function test_manager_can_bulk_submit_all_ready_draft_players_in_one_click(): void
    {
        $readyPlayer1 = Player::create([
            'team_id' => $this->team->id,
            'event_id' => $this->event->id,
            'full_name' => 'Pemain Siap Satu',
            'jersey_number' => 10,
            'position' => 'FW',
            'dominant_foot' => 'Right',
            'birth_date' => '2012-03-10',
            'nik' => '3171000000000101',
            'photo_path' => 'players/photos/p1.jpg',
            'screening_status' => 'draft',
        ]);
        PlayerDocument::create([
            'player_id' => $readyPlayer1->id,
            'document_type' => 'birth_certificate',
            'file_path' => 'players/documents/akta1.jpg',
            'original_name' => 'akta1.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'validation_status' => 'pending',
        ]);

        $incompletePlayer = Player::create([
            'team_id' => $this->team->id,
            'event_id' => $this->event->id,
            'full_name' => 'Pemain Belum Lengkap',
            'jersey_number' => 11,
            'position' => 'MF',
            'dominant_foot' => 'Left',
            'birth_date' => '2012-04-10',
            'nik' => '3171000000000102',
            'photo_path' => 'players/photos/p2.jpg',
            'screening_status' => 'draft',
        ]);

        $this->actingAs($this->manager)
            ->withSession(['active_manager_team_id' => $this->team->id])
            ->post(route('manager.players.submit-all'))
            ->assertRedirect();

        $this->assertSame('submitted', $readyPlayer1->fresh()->screening_status);
        $this->assertSame('draft', $incompletePlayer->fresh()->screening_status);
        $this->assertSame('in_review', $this->team->fresh()->screening_status);
    }

    public function test_finance_can_mark_refund_required_payment_as_refunded_and_dispatch_email(): void
    {
        $payment = Payment::create([
            'invoice_number' => 'INV-2026-REFUND-001',
            'team_id' => $this->team->id,
            'event_id' => $this->event->id,
            'amount' => 1500000,
            'gateway_fee' => 0,
            'total_amount' => 1500000,
            'method' => 'midtrans',
            'payment_channel' => 'bca_va',
            'status' => 'refund_required',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->finance)
            ->post(route('admin.payments.mark-refunded', ['payment' => $payment->id]), [
                'refund_notes' => 'Refund telah ditransfer via BCA Ref #TRF-881920 pada 07/11/2026',
            ])
            ->assertRedirect();

        $freshPayment = $payment->fresh();
        $this->assertSame('refunded', $freshPayment->status);
        $this->assertStringContainsString('REFUND SELESAI:', (string) $freshPayment->rejection_reason);

        $this->assertDatabaseHas('email_notification_logs', [
            'team_id' => $this->team->id,
            'mailable_type' => 'PaymentRefundedMail',
            'recipient_email' => $this->team->manager_email,
        ]);
    }

    public function test_admin_can_export_transactions_teams_and_players_csv(): void
    {
        Payment::create([
            'invoice_number' => 'INV-2026-EXP-001',
            'team_id' => $this->team->id,
            'event_id' => $this->event->id,
            'amount' => 1500000,
            'gateway_fee' => 0,
            'total_amount' => 1500000,
            'method' => 'manual',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Player::create([
            'team_id' => $this->team->id,
            'event_id' => $this->event->id,
            'full_name' => 'Rizky Ridho Muda',
            'jersey_number' => 5,
            'position' => 'DF',
            'dominant_foot' => 'Right',
            'birth_date' => '2012-05-20',
            'nik' => '3171000000000555',
            'photo_path' => 'players/photos/p5.jpg',
            'screening_status' => 'passed',
            'accreditation_code' => 'YTC-EXP-005',
        ]);

        $txCsv = $this->actingAs($this->finance)
            ->get(route('admin.payments.transactions.export'));
        $txCsv->assertOk();
        $this->assertStringContainsString('INV-2026-EXP-001', $txCsv->streamedContent());

        $teamsCsv = $this->actingAs($this->superadmin)
            ->get(route('admin.teams.export'));
        $teamsCsv->assertOk();
        $this->assertStringContainsString('SSB Macan Kemayoran Muda', $teamsCsv->streamedContent());

        $playersCsv = $this->actingAs($this->superadmin)
            ->get(route('admin.teams.export-players'));
        $playersCsv->assertOk();
        $this->assertStringContainsString('Rizky Ridho Muda', $playersCsv->streamedContent());
        $this->assertStringContainsString('YTC-EXP-005', $playersCsv->streamedContent());
    }

    public function test_manager_can_change_password_and_use_forgot_password_flow(): void
    {
        // 1. Self-service password change in Team Profile
        $this->actingAs($this->manager)
            ->put(route('manager.password.update'), [
                'current_password' => 'Manager12345',
                'password' => 'NewPass9876',
                'password_confirmation' => 'NewPass9876',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('NewPass9876', $this->manager->fresh()->password));

        // 2. Forgot password request + Reset password flow
        $this->app['auth']->logout();

        $forgotPage = $this->get(route('password.request'));
        $forgotPage->assertOk();
        $forgotPage->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');

        $this->post(route('password.email'), [
            'email' => $this->manager->email,
        ])->assertRedirect();

        $this->assertDatabaseHas('email_notification_logs', [
            'recipient_email' => $this->manager->email,
            'mailable_type' => 'ResetPasswordMail',
        ]);

        // Set known token in password_reset_tokens to verify reset endpoint
        $plainToken = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $this->manager->email],
            ['token' => Hash::make($plainToken), 'created_at' => now()]
        );

        $this->post(route('password.store'), [
            'token' => $plainToken,
            'email' => $this->manager->email,
            'password' => 'Recovered1234',
            'password_confirmation' => 'Recovered1234',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Recovered1234', $this->manager->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->manager->email,
        ]);
    }
}
