<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    protected Event $event;

    protected User $manager;

    protected Team $team;

    protected Payment $payment;

    protected User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');

        $this->event = Event::create([
            'name' => 'Piala Utama 2026',
            'slug' => 'piala-utama-2026',
            'venue_name' => 'Stadion Gelora',
            'city' => 'Jakarta',
            'format' => 'cup',
            'age_category' => 'U-15',
            'min_birth_date' => '2011-01-01',
            'max_birth_date' => '2012-12-31',
            'min_players_per_team' => 14,
            'max_players_per_team' => 20,
            'max_officials_per_team' => 5,
            'required_documents' => ['birth_certificate', 'family_card'],
            'max_teams' => 4,
            'registration_fee' => 1500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Manajer Klub',
            'email' => 'manager@klub.id',
            'phone' => '081234567890',
        ]);
        $this->manager->assignRole('team_manager');

        $this->team = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $this->manager->id,
            'name' => 'Persada FC',
            'slug' => 'persada-fc',
            'logo_path' => 'teams/logos/sample.png',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => $this->manager->name,
            'manager_whatsapp' => '081234567890',
            'manager_email' => $this->manager->email,
            'head_coach_name' => 'Coach Anton',
            'terms_accepted_at' => now(),
            'status' => 'pending_payment',
            'slot_expires_at' => now()->addHours(24),
        ]);

        $this->payment = Payment::create([
            'invoice_number' => 'INV-2026-EVT01-0001',
            'team_id' => $this->team->id,
            'event_id' => $this->event->id,
            'amount' => 1500000,
            'gateway_fee' => 0,
            'total_amount' => 1500000,
            'expires_at' => now()->addHours(24),
            'status' => 'unpaid',
        ]);

        $this->financeUser = User::factory()->create([
            'name' => 'Finance Staff',
            'email' => 'finance-test@ytevent.id',
        ]);
        $this->financeUser->assignRole('finance');
    }

    public function test_manager_can_upload_manual_transfer_proof(): void
    {
        $proof = UploadedFile::fake()->image('bukti_transfer.jpg', 600, 800);

        $response = $this->actingAs($this->manager)->post(
            route('manager.payments.manual-proof', ['payment' => $this->payment->id]),
            [
                'sender_bank_name' => 'Bank BCA',
                'sender_account_holder' => 'Bambang Sudirman',
                'sender_account_number' => '1234567890',
                'transfer_date' => now()->toDateString(),
                'proof_file' => $proof,
            ]
        );

        $response->assertSessionHas('success');

        $this->payment->refresh();
        $this->assertEquals('waiting_verification', $this->payment->status);
        $this->assertEquals('Bank BCA', $this->payment->sender_bank_name);
        $this->assertNotEmpty($this->payment->proof_file_path);
        Storage::disk('private')->assertExists($this->payment->proof_file_path);
    }

    public function test_finance_can_approve_manual_payment(): void
    {
        $this->payment->update([
            'status' => 'waiting_verification',
            'proof_file_path' => 'payments/proofs/test.jpg',
            'sender_bank_name' => 'Bank Mandiri',
            'sender_account_holder' => 'Bambang',
            'transfer_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->financeUser)->post(
            route('admin.payments.verify', ['payment' => $this->payment->id]),
            [
                'action' => 'approve',
            ]
        );

        $response->assertSessionHas('success');

        $this->payment->refresh();
        $this->team->refresh();

        $this->assertEquals('paid', $this->payment->status);
        $this->assertEquals($this->financeUser->id, $this->payment->verified_by);
        $this->assertNotNull($this->payment->paid_at);
        $this->assertEquals('active', $this->team->status);
        $this->assertTrue($this->team->canManageRoster());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Payment::class,
            'auditable_id' => $this->payment->id,
            'action' => 'payment.manual.approved',
            'from_status' => 'waiting_verification',
            'to_status' => 'paid',
            'actor_id' => $this->financeUser->id,
        ]);
    }

    public function test_finance_can_reject_manual_payment_with_reason_and_grace_period(): void
    {
        $this->payment->update([
            'status' => 'waiting_verification',
            'proof_file_path' => 'payments/proofs/test.jpg',
            'sender_bank_name' => 'Bank Mandiri',
            'sender_account_holder' => 'Bambang',
            'transfer_date' => now()->toDateString(),
            'expires_at' => now()->addHour(), // tinggal 1 jam
        ]);

        $response = $this->actingAs($this->financeUser)->post(
            route('admin.payments.verify', ['payment' => $this->payment->id]),
            [
                'action' => 'reject',
                'rejection_reason' => 'Nominal transfer tidak sesuai dengan tagihan resmi.',
            ]
        );

        $response->assertSessionHas('warning');

        $this->payment->refresh();
        $this->assertEquals('rejected', $this->payment->status);
        $this->assertEquals('Nominal transfer tidak sesuai dengan tagihan resmi.', $this->payment->rejection_reason);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Payment::class,
            'auditable_id' => $this->payment->id,
            'action' => 'payment.manual.rejected',
            'from_status' => 'waiting_verification',
            'to_status' => 'rejected',
            'actor_id' => $this->financeUser->id,
        ]);
        // Memastikan perpanjangan waktu grace period 6 jam
        $this->assertTrue($this->payment->expires_at->gt(now()->addHours(5)));
    }

    public function test_midtrans_webhook_processes_valid_signature_and_activates_team(): void
    {
        config(['payment.midtrans.server_key' => 'test-server-key']);

        $orderId = $this->payment->invoice_number;
        $statusCode = '200';
        $grossAmount = number_format((float) $this->payment->total_amount, 2, '.', '');
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key');

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'bca_va',
            'transaction_id' => 'MID-TEST-9988',
        ];

        $response = $this->postJson(route('webhooks.midtrans'), $payload);

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'invoice_number' => $orderId,
            'payment_status' => 'paid',
        ]);

        $this->assertEquals('paid', $this->payment->fresh()->status);
        $this->assertEquals('active', $this->team->fresh()->status);
        $this->assertDatabaseHas('webhook_logs', [
            'provider' => 'midtrans',
            'invoice_number' => $orderId,
            'status' => 'processed',
            'http_status' => 200,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Payment::class,
            'auditable_id' => $this->payment->id,
            'action' => 'payment.gateway.settled',
            'from_status' => 'unpaid',
            'to_status' => 'paid',
            'actor_id' => null,
        ]);
    }

    public function test_midtrans_webhook_rejects_invalid_signature(): void
    {
        config(['payment.midtrans.server_key' => 'test-server-key']);

        $payload = [
            'order_id' => $this->payment->invoice_number,
            'status_code' => '200',
            'gross_amount' => '1500000.00',
            'signature_key' => 'forged-fake-signature',
            'transaction_status' => 'settlement',
        ];

        $response = $this->postJson(route('webhooks.midtrans'), $payload);

        $response->assertForbidden();
        $this->assertDatabaseHas('webhook_logs', [
            'provider' => 'midtrans',
            'invoice_number' => $this->payment->invoice_number,
            'status' => 'failed',
        ]);
        $this->assertDatabaseMissing('webhook_logs', [
            'payload->signature_key' => 'forged-fake-signature',
        ]);
    }

    public function test_midtrans_webhook_rejects_callback_with_mismatched_amount(): void
    {
        config(['payment.midtrans.server_key' => 'test-server-key']);

        $orderId = $this->payment->invoice_number;
        $statusCode = '200';
        $grossAmount = '1.00';
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key');

        $response = $this->postJson(route('webhooks.midtrans'), [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'MID-MISMATCH',
            'signature_key' => $signature,
        ]);

        $response->assertForbidden();
        $this->assertSame('unpaid', $this->payment->fresh()->status);
        $this->assertSame('pending_payment', $this->team->fresh()->status);
    }

    public function test_late_webhook_settlement_on_expired_team_marks_refund_required_if_quota_is_full(): void
    {
        config(['payment.midtrans.server_key' => 'test-server-key']);

        // Set event hanya kuota 1 tim
        $this->event->update(['max_teams' => 1]);

        // Tim ini sudah kedaluwarsa
        $this->team->update(['status' => 'expired']);
        $this->payment->update(['status' => 'expired']);

        // Namun tim lain sudah mengambil slot terakhir kuota 1 tersebut
        Team::create([
            'event_id' => $this->event->id,
            'user_id' => User::factory()->create()->id,
            'name' => 'Tim Pengambil Slot',
            'slug' => 'tim-pengambil-slot',
            'logo_path' => 'teams/logos/sample.png',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Budi',
            'manager_whatsapp' => '081234567890',
            'manager_email' => 'budi2@example.com',
            'head_coach_name' => 'Coach Budi',
            'terms_accepted_at' => now(),
            'status' => 'active',
        ]);

        $this->assertEquals(1, $this->event->fresh()->occupiedSlotsCount());

        // Webhook pelunasan terlambat tiba untuk tim yang sudah expired
        $orderId = $this->payment->invoice_number;
        $statusCode = '200';
        $grossAmount = number_format((float) $this->payment->total_amount, 2, '.', '');
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.'test-server-key');

        $payload = [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'MID-LATE-1234',
        ];

        $response = $this->postJson(route('webhooks.midtrans'), $payload);
        $response->assertOk();

        // Kuota tidak boleh jebol, pembayaran ditandai refund_required
        $this->payment->refresh();
        $this->team->refresh();

        $this->assertEquals('refund_required', $this->payment->status);
        $this->assertEquals('expired', $this->team->status);
        $this->assertEquals(1, $this->event->fresh()->occupiedSlotsCount());
    }

    public function test_admin_can_view_teams_and_transactions_with_payment_proof_metadata(): void
    {
        // Manager uploads proof
        $file = UploadedFile::fake()->create('bukti_transfer.jpg', 500, 'image/jpeg');
        $this->actingAs($this->manager)
            ->post(route('manager.payments.manual-proof', ['payment' => $this->payment->id]), [
                'sender_bank_name' => 'BCA',
                'sender_account_holder' => 'Manajer Klub',
                'sender_account_number' => '1234567890',
                'transfer_date' => now()->toDateString(),
                'proof_file' => $file,
            ]);

        // Finance user visits admin teams page
        $resTeams = $this->actingAs($this->financeUser)
            ->get(route('admin.teams.index'));
        $resTeams->assertOk();
        $resTeams->assertInertia(fn ($page) => $page
            ->component('Admin/Teams/Index')
            ->has('teams.0.latest_payment.proof_url')
        );

        // Finance user visits admin transactions page
        $resTransactions = $this->actingAs($this->financeUser)
            ->get(route('admin.payments.transactions'));
        $resTransactions->assertOk();
        $resTransactions->assertInertia(fn ($page) => $page
            ->component('Admin/Payments/Transactions')
            ->has('transactions.0.proof_url')
        );
    }

    public function test_payment_mode_is_enforced_strictly_on_backend(): void
    {
        // 1. Saat mode MANUAL aktif, gateway call harus ditolak
        SystemSetting::set('payment_method_mode', 'manual', 'text', 'payment');

        $resGateway = $this->actingAs($this->manager)
            ->post(route('manager.payments.gateway', ['payment' => $this->payment->id]), [
                'provider' => 'midtrans',
                'payment_channel' => 'bca_va',
            ]);
        $resGateway->assertSessionHasErrors('payment');

        // 2. Saat mode MIDTRANS aktif, upload bukti manual harus ditolak
        SystemSetting::set('payment_method_mode', 'midtrans', 'text', 'payment');

        $file = UploadedFile::fake()->create('bukti.jpg', 500, 'image/jpeg');
        $resManual = $this->actingAs($this->manager)
            ->post(route('manager.payments.manual-proof', ['payment' => $this->payment->id]), [
                'sender_bank_name' => 'BCA',
                'sender_account_holder' => 'Manajer Klub',
                'sender_account_number' => '1234567890',
                'transfer_date' => now()->toDateString(),
                'proof_file' => $file,
            ]);
        $resManual->assertSessionHasErrors('payment');
    }

    public function test_sandbox_simulator_is_forbidden_when_disabled(): void
    {
        config(['payment.sandbox_simulator' => false]);

        $res = $this->actingAs($this->manager)
            ->post(route('manager.payments.simulate', ['payment' => $this->payment->id]));

        $res->assertForbidden();
    }

    public function test_screening_officer_can_access_admin_portal(): void
    {
        $screeningUser = User::factory()->create();
        $screeningUser->assignRole('screening_officer');

        $res = $this->actingAs($screeningUser)
            ->get(route('admin.dashboard'));

        $res->assertOk();
    }

    public function test_admin_can_view_manual_verification_queue_and_empty_state_counters(): void
    {
        $res = $this->actingAs($this->financeUser)
            ->get(route('admin.payments.manual-verification'));

        $res->assertOk();
        $res->assertInertia(fn ($page) => $page
            ->component('Admin/Payments/ManualVerification')
            ->has('totalManualEver')
            ->has('totalVerified')
        );
    }
}
