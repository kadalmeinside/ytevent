<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\EmailNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminAndEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);
    }

    public function test_superadmin_can_manage_users_and_assign_roles(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        // 1. Superadmin can see user list
        $response = $this->actingAs($superadmin)->get(route('admin.users.index'));
        $response->assertOk();

        // 2. Create new finance officer
        $createResponse = $this->actingAs($superadmin)->post(route('admin.users.store'), [
            'name' => 'Staf Finance Panitia',
            'email' => 'finance.baru@organizer.id',
            'phone' => '081234567890',
            'role' => 'finance',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'is_active' => true,
        ]);

        $createResponse->assertRedirect(route('admin.users.index'));
        $newUser = User::where('email', 'finance.baru@organizer.id')->firstOrFail();
        $this->assertTrue($newUser->hasRole('finance'));
        $this->assertTrue($newUser->is_active);

        // 3. Update user role to screening_officer
        $updateResponse = $this->actingAs($superadmin)->post(route('admin.users.update', ['user' => $newUser->id]), [
            'name' => 'Staf Screening Panitia',
            'email' => 'finance.baru@organizer.id',
            'phone' => '081234567890',
            'role' => 'screening_officer',
            'is_active' => true,
        ]);

        $updateResponse->assertRedirect(route('admin.users.index'));
        $this->assertTrue($newUser->fresh()->hasRole('screening_officer'));

        // 4. Reset password via PUT request (Inertia method spoofing / PUT)
        $putResponse = $this->actingAs($superadmin)->put(route('admin.users.update', ['user' => $newUser->id]), [
            'name' => 'Staf Screening Panitia Updated',
            'email' => 'finance.baru@organizer.id',
            'phone' => '081234567899',
            'role' => 'screening_officer',
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
            'is_active' => true,
        ]);

        $putResponse->assertRedirect(route('admin.users.index'));
        $newUser->refresh();
        $this->assertSame('Staf Screening Panitia Updated', $newUser->name);
        $this->assertTrue(Hash::check('NewSecretPassword123!', $newUser->password));

        // 5. Reset password via POST request
        $postResponse = $this->actingAs($superadmin)->post(route('admin.users.update', ['user' => $newUser->id]), [
            'name' => 'Staf Screening Panitia Final',
            'email' => 'finance.baru@organizer.id',
            'phone' => '081234567899',
            'role' => 'screening_officer',
            'password' => 'AnotherPassword999!',
            'password_confirmation' => 'AnotherPassword999!',
            'is_active' => true,
        ]);

        $postResponse->assertRedirect(route('admin.users.index'));
        $newUser->refresh();
        $this->assertSame('Staf Screening Panitia Final', $newUser->name);
        $this->assertTrue(Hash::check('AnotherPassword999!', $newUser->password));
    }

    public function test_authorized_admin_can_view_audit_logs_and_unauthorized_user_cannot(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $response = $this->actingAs($superadmin)->get(route('admin.audit-logs.index'));
        $response->assertOk();

        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $this->actingAs($finance)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_authorized_admin_can_export_filtered_audit_logs(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        AuditLog::record(
            entity: $superadmin,
            action: 'payment.manual.approved',
            actor: $superadmin,
            fromStatus: 'waiting_verification',
            toStatus: 'paid',
            reason: 'Bukti transfer sesuai.'
        );

        $response = $this->actingAs($superadmin)->get(route('admin.audit-logs.export', [
            'action' => 'payment.manual.approved',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('payment.manual.approved', $csv);
        $this->assertStringContainsString('Bukti transfer sesuai.', $csv);
    }

    public function test_authorized_admin_can_view_integration_monitor(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        WebhookLog::create([
            'provider' => 'midtrans',
            'invoice_number' => 'INV-MONITOR-001',
            'status' => 'failed',
            'http_status' => 403,
            'error_message' => 'Signature tidak valid.',
            'payload' => ['order_id' => 'INV-MONITOR-001'],
        ]);

        $this->actingAs($superadmin)
            ->get(route('admin.integration-monitor.index'))
            ->assertOk();

        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $this->actingAs($finance)
            ->get(route('admin.integration-monitor.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_update_role_permissions(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $financeRole = Role::findByName('finance');

        $response = $this->actingAs($superadmin)->post(route('admin.roles.update', ['role' => $financeRole->id]), [
            'permissions' => ['dashboard.admin.view', 'payments.view_all', 'payments.verify_manual'],
        ]);

        $response->assertSessionHas('success');
        $this->assertTrue($financeRole->fresh()->hasPermissionTo('payments.verify_manual'));
    }

    public function test_superadmin_can_update_branding_and_payment_fee_settings(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        // 1. Update branding
        $brandResponse = $this->actingAs($superadmin)->post(route('admin.settings.branding'), [
            'app_name' => 'Liga Grassroots Persija Muda 2026',
            'app_tagline' => 'Festival Sepak Bola Usia Dini Terbesar',
            'header_logo_style' => 'logo_only',
            'footer_copyright' => 'Hak Cipta 2026 Persija Muda',
            'contact_email' => 'panitia@persijamuda.id',
            'contact_whatsapp' => '08111222333',
        ]);

        $brandResponse->assertSessionHas('success');
        $this->assertSame('Liga Grassroots Persija Muda 2026', SystemSetting::get('app_name'));
        $this->assertSame('logo_only', SystemSetting::get('header_logo_style'));

        // 2. Update gateway fee MDR rule with payment_method_mode
        $feeResponse = $this->actingAs($superadmin)->post(route('admin.settings.payment-gateway'), [
            'payment_method_mode' => 'midtrans',
            'payment_gateway_default' => 'midtrans',
            'midtrans_server_key' => 'SB-Mid-server-test123',
            'midtrans_client_key' => 'SB-Mid-client-test123',
            'midtrans_is_production' => false,
            'fee_borne_by' => 'organizer',
            'midtrans_va_fee' => 4000,
            'midtrans_qris_fee_percent' => 0.7,
            'midtrans_ewallet_fee_percent' => 1.5,
            'manual_bank_accounts' => [
                [
                    'bank_name' => 'BCA',
                    'account_number' => '1234567890',
                    'account_holder' => 'Persija Muda Official',
                    'notes' => 'KCU Sudirman',
                ],
            ],
        ]);

        $feeResponse->assertSessionHas('success');
        $this->assertSame('midtrans', SystemSetting::get('payment_method_mode'));
        $this->assertSame('organizer', SystemSetting::get('fee_borne_by'));
        $this->assertSame(4000, SystemSetting::get('midtrans_va_fee'));
    }

    public function test_non_superadmin_is_forbidden_from_admin_settings_and_users(): void
    {
        $financeUser = User::factory()->create();
        $financeUser->assignRole('finance');

        $this->actingAs($financeUser)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($financeUser)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_email_notification_service_dispatches_mail_and_records_log(): void
    {
        Mail::fake();

        $event = Event::create([
            'name' => 'Festival U-12',
            'slug' => 'festival-u-12',
            'venue_name' => 'Stadion Patriot',
            'city' => 'Bekasi',
            'format' => 'festival',
            'age_category' => 'U-12',
            'min_players_per_team' => 12,
            'max_players_per_team' => 18,
            'max_officials_per_team' => 4,
            'required_documents' => ['birth_certificate'],
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'status' => 'open',
        ]);

        $manager = User::factory()->create(['email' => 'manager.tim@gmail.com']);
        $team = Team::create([
            'event_id' => $event->id,
            'user_id' => $manager->id,
            'name' => 'Persija Junior',
            'slug' => 'persija-junior',
            'logo_path' => 'logos/persija.png',
            'origin_association' => 'Jakarta Pusat',
            'manager_name' => 'Bambang',
            'manager_whatsapp' => '08123456789',
            'manager_email' => $manager->email,
            'head_coach_name' => 'Coach Dedi',
            'terms_accepted_at' => now(),
            'slot_reserved_at' => now(),
            'status' => 'pending_payment',
        ]);

        $payment = Payment::create([
            'invoice_number' => 'INV-2026-TEST-0001',
            'team_id' => $team->id,
            'event_id' => $event->id,
            'amount' => 1500000,
            'gateway_fee' => 0,
            'total_amount' => 1500000,
            'status' => 'unpaid',
            'expires_at' => now()->addDay(),
        ]);

        $service = app(EmailNotificationService::class);
        $log = $service->sendTeamRegistered($team, $payment);

        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertSame('TeamRegisteredMail', $log->mailable_type);
        $this->assertSame('manager.tim@gmail.com', $log->recipient_email);
        $this->assertDatabaseHas('email_notification_logs', [
            'team_id' => $team->id,
            'mailable_type' => 'TeamRegisteredMail',
            'status' => 'sent',
        ]);
    }
}
