<?php

namespace Tests\Feature;

use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

    public function test_midtrans_webhook_processes_valid_signature_with_suffixed_order_id_and_activates_team(): void
    {
        config(['payment.midtrans.server_key' => 'test-server-key']);

        $suffixedOrderId = $this->payment->invoice_number.'-REV1';
        $statusCode = '200';
        $grossAmount = number_format((float) $this->payment->total_amount, 2, '.', '');
        $signature = hash('sha512', $suffixedOrderId.$statusCode.$grossAmount.'test-server-key');

        $this->payment->update(['external_reference_id' => $suffixedOrderId]);

        $payload = [
            'order_id' => $suffixedOrderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signature,
            'transaction_status' => 'settlement',
            'payment_type' => 'echannel',
            'transaction_id' => 'MID-TEST-SUFF123',
        ];

        $response = $this->postJson(route('webhooks.midtrans'), $payload);

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'invoice_number' => $this->payment->invoice_number,
            'payment_status' => 'paid',
        ]);

        $this->assertEquals('paid', $this->payment->fresh()->status);
        $this->assertEquals('active', $this->team->fresh()->status);
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

    public function test_xendit_gateway_creates_invoice_and_persists_reference(): void
    {
        config([
            'payment.xendit.secret_key' => 'xnd_test_secret',
            'payment.xendit.invoice_url' => 'https://api.xendit.co/v2/invoices',
        ]);
        SystemSetting::set('payment_method_mode', 'midtrans', 'text', 'payment');
        SystemSetting::set('fee_borne_by', 'organizer', 'text', 'payment');

        Http::fake([
            'https://api.xendit.co/v2/invoices' => Http::response([
                'id' => 'xnd-invoice-id',
                'external_id' => $this->payment->invoice_number,
                'invoice_url' => 'https://checkout.xendit.co/xnd-invoice-id',
            ], 200),
        ]);

        $response = $this->actingAs($this->manager)->post(
            route('manager.payments.gateway', ['payment' => $this->payment->id]),
            ['provider' => 'xendit', 'payment_channel' => 'QRIS']
        );

        $response->assertSessionHas('success');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.xendit.co/v2/invoices'
            && $request['external_id'] === $this->payment->invoice_number
            && $request['payer_email'] === $this->team->manager_email
            && $request['amount'] === 1500000);

        $this->payment->refresh();
        $this->assertSame('xendit', $this->payment->method);
        $this->assertSame('xnd-invoice-id', $this->payment->external_reference_id);
        $this->assertSame('https://checkout.xendit.co/xnd-invoice-id', $this->payment->payment_url);
        $this->assertSame('QRIS', $this->payment->payment_channel);
    }

    public function test_xendit_sandbox_simulator_uses_amount_callback_field(): void
    {
        config([
            'payment.sandbox_simulator' => true,
            'payment.xendit.callback_token' => 'xnd_callback_token',
        ]);
        SystemSetting::set('payment_method_mode', 'midtrans', 'text', 'payment');
        $this->payment->update([
            'method' => 'xendit',
            'external_reference_id' => 'xnd-invoice-id',
            'total_amount' => 1500000,
        ]);

        $response = $this->actingAs($this->manager)->post(
            route('manager.payments.simulate', ['payment' => $this->payment->id])
        );

        $response->assertSessionHas('success');
        $this->assertSame('paid', $this->payment->fresh()->status);
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

    public function test_clicking_change_payment_method_syncs_with_midtrans_and_settles_if_already_paid(): void
    {
        config(['payment.sandbox_simulator' => false]);
        config(['payment.midtrans.server_key' => 'test-server-key']);
        SystemSetting::set('payment_method_mode', 'midtrans', 'text', 'payment');

        $this->payment->update([
            'snap_token' => 'SNAP-OLD-TOKEN-123',
            'external_reference_id' => $this->payment->invoice_number,
        ]);

        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'status_code' => '200',
                'transaction_status' => 'settlement',
                'payment_type' => 'bca_va',
                'transaction_id' => 'MID-SETTLED-888',
                'gross_amount' => number_format((float) $this->payment->total_amount, 2, '.', ''),
            ], 200),
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'SHOULD-NOT-BE-CALLED',
            ], 200),
        ]);

        $response = $this->actingAs($this->manager)
            ->post(route('manager.payments.gateway', ['payment' => $this->payment->id]), [
                'provider' => 'midtrans',
                'force_regenerate' => true,
            ]);

        $response->assertRedirect(route('manager.payments'));
        $response->assertSessionHas('success', 'Pembayaran sebelumnya telah terverifikasi lunas di Midtrans! Status tim Anda kini telah aktif.');

        $this->assertEquals('paid', $this->payment->fresh()->status);
        $this->assertEquals('active', $this->team->fresh()->status);

        // Snap create API should NOT have been called because status was already settled!
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/snap/v1/transactions');
        });
    }

    public function test_manager_payments_check_status_syncs_with_midtrans(): void
    {
        config(['payment.sandbox_simulator' => false]);
        config(['payment.midtrans.server_key' => 'test-server-key']);
        SystemSetting::set('payment_method_mode', 'midtrans', 'text', 'payment');

        $this->payment->update([
            'snap_token' => 'SNAP-OLD-TOKEN-123',
            'external_reference_id' => $this->payment->invoice_number,
        ]);

        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response([
                'status_code' => '200',
                'transaction_status' => 'settlement',
                'payment_type' => 'qris',
                'transaction_id' => 'MID-QRIS-SETTLED',
                'gross_amount' => number_format((float) $this->payment->total_amount, 2, '.', ''),
            ], 200),
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('manager.payments', ['check_status' => 1]));

        $response->assertOk();
        $response->assertSessionHas('success', 'Pembayaran sebelumnya telah terverifikasi lunas di Midtrans! Status tim Anda kini telah aktif.');
        $this->assertEquals('paid', $this->payment->fresh()->status);
        $this->assertEquals('active', $this->team->fresh()->status);
    }

    public function test_gateway_provider_separation_rejects_unconfigured_or_disabled_provider(): void
    {
        config([
            'payment.sandbox_simulator' => false,
            'payment.midtrans.server_key' => 'test-midtrans-key',
            'payment.xendit.secret_key' => '',
        ]);
        SystemSetting::set('payment_method_mode', 'midtrans', 'text', 'payment');
        SystemSetting::set('payment_gateway_default', 'midtrans', 'text', 'payment');

        $res = $this->actingAs($this->manager)->post(
            route('manager.payments.gateway', ['payment' => $this->payment->id]),
            ['provider' => 'xendit', 'payment_channel' => 'QRIS']
        );
        $res->assertSessionHasErrors('payment');
        $this->assertNull($this->payment->fresh()->method);

        // Saat mode khusus xendit aktif, midtrans ditolak dan xendit yang terkonfigurasi diterima
        config(['payment.xendit.secret_key' => 'xnd_live_secret']);
        SystemSetting::set('payment_method_mode', 'xendit', 'text', 'payment');
        SystemSetting::set('payment_gateway_default', 'xendit', 'text', 'payment');

        $resMidtrans = $this->actingAs($this->manager)->post(
            route('manager.payments.gateway', ['payment' => $this->payment->id]),
            ['provider' => 'midtrans', 'payment_channel' => 'bca_va']
        );
        $resMidtrans->assertSessionHasErrors('payment');
    }

    public function test_arbitrary_payment_channel_is_rejected_while_official_channel_passes(): void
    {
        config([
            'payment.sandbox_simulator' => false,
            'payment.midtrans.server_key' => 'test-server-key',
        ]);
        SystemSetting::set('payment_method_mode', 'midtrans', 'text', 'payment');
        SystemSetting::set('fee_borne_by', 'team', 'text', 'payment');
        SystemSetting::set('midtrans_va_fee', 4000, 'integer', 'payment');

        $resInvalid = $this->actingAs($this->manager)->post(
            route('manager.payments.gateway', ['payment' => $this->payment->id]),
            ['provider' => 'midtrans', 'payment_channel' => 'custom_hacked_channel']
        );
        $resInvalid->assertSessionHasErrors('payment_channel');

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'SNAP-VA-OK',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/SNAP-VA-OK',
            ], 200),
        ]);

        $resValid = $this->actingAs($this->manager)->post(
            route('manager.payments.gateway', ['payment' => $this->payment->id]),
            ['provider' => 'midtrans', 'payment_channel' => 'bca_va']
        );
        $resValid->assertSessionHas('success');

        $fresh = $this->payment->fresh();
        $this->assertSame('bca_va', $fresh->payment_channel);
        $this->assertEquals(4000.0, (float) $fresh->gateway_fee);
        $this->assertEquals(1504000.0, (float) $fresh->total_amount);
    }

    public function test_midtrans_does_not_silently_fallback_to_all_channels_when_selected_channel_fails(): void
    {
        config([
            'payment.sandbox_simulator' => false,
            'payment.midtrans.server_key' => 'test-server-key',
        ]);
        SystemSetting::set('payment_method_mode', 'midtrans', 'text', 'payment');

        Http::fake([
            'https://api.sandbox.midtrans.com/v2/*/status' => Http::response(['status_code' => '404'], 404),
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'error_messages' => ['payment channel not activated on merchant'],
            ], 400),
        ]);

        $res = $this->actingAs($this->manager)->post(
            route('manager.payments.gateway', ['payment' => $this->payment->id]),
            ['provider' => 'midtrans', 'payment_channel' => 'QRIS']
        );

        $res->assertSessionHasErrors('payment');
        $snapRequests = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/snap/v1/transactions'));
        $this->assertCount(1, $snapRequests);
        $this->assertSame(['gopay', 'qris', 'shopeepay'], $snapRequests->first()[0]['enabled_payments']);
        $this->assertNull($this->payment->fresh()->snap_token);
    }

    public function test_xendit_webhook_handles_token_validation_duplicate_reinitiation_and_provider_mismatch(): void
    {
        config([
            'payment.xendit.callback_token' => 'xnd_secret_cb_token',
            'payment.midtrans.server_key' => 'midtrans_secret_key',
        ]);

        $this->payment->update([
            'method' => 'xendit',
            'external_reference_id' => 'xnd-inv-reinit-2',
            'webhook_payload' => [
                'attempted_order_ids' => [
                    $this->payment->invoice_number.'-11111AAA',
                    'xnd-inv-reinit-2',
                    $this->payment->invoice_number,
                ],
            ],
        ]);

        // 1. Invalid token ditolak
        $this->postJson(route('webhooks.xendit'), [
            'external_id' => $this->payment->invoice_number,
            'status' => 'PAID',
            'amount' => 1500000,
        ], ['x-callback-token' => 'wrong-token'])->assertForbidden();

        // 2. Provider mismatch: invoice xendit menerima callback Midtrans ditolak
        $orderId = $this->payment->invoice_number;
        $statusCode = '200';
        $grossAmount = '1500000.00';
        $sig = hash('sha512', $orderId.$statusCode.$grossAmount.'midtrans_secret_key');
        $this->postJson(route('webhooks.midtrans'), [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $sig,
            'transaction_status' => 'settlement',
        ])->assertForbidden();

        // 3. Valid token pada invoice hasil re-initiation berhasil melunaskan
        $resOk = $this->postJson(route('webhooks.xendit'), [
            'id' => 'xnd-inv-reinit-2',
            'external_id' => $this->payment->invoice_number.'-11111AAA',
            'status' => 'PAID',
            'amount' => 1500000,
            'payment_channel' => 'QRIS',
        ], ['x-callback-token' => 'xnd_secret_cb_token']);

        $resOk->assertOk();
        $this->assertSame('paid', $this->payment->fresh()->status);
        $this->assertSame('active', $this->team->fresh()->status);

        // 4. Duplicate callback idempotent
        $resDup = $this->postJson(route('webhooks.xendit'), [
            'id' => 'xnd-inv-reinit-2',
            'external_id' => $this->payment->invoice_number.'-11111AAA',
            'status' => 'PAID',
            'amount' => 1500000,
            'payment_channel' => 'QRIS',
        ], ['x-callback-token' => 'xnd_secret_cb_token']);

        $resDup->assertOk();
        $this->assertSame('paid', $this->payment->fresh()->status);
    }

    public function test_webhook_rejects_fee_underpayment_and_flags_overpayment_as_refund_required(): void
    {
        config([
            'payment.midtrans.server_key' => 'test-server-key',
            'payment.xendit.callback_token' => 'xnd_cb_token',
        ]);

        // Tagihan dengan MDR fee 4000 (total 1504000)
        $this->payment->update([
            'method' => 'midtrans',
            'amount' => 1500000,
            'gateway_fee' => 4000,
            'total_amount' => 1504000,
        ]);

        // 1. Callback yang hanya membayar pokok tanpa gateway_fee wajib ditolak 403
        $orderId = $this->payment->invoice_number;
        $statusCode = '200';
        $underAmount = '1500000.00';
        $sigUnder = hash('sha512', $orderId.$statusCode.$underAmount.'test-server-key');

        $this->postJson(route('webhooks.midtrans'), [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $underAmount,
            'signature_key' => $sigUnder,
            'transaction_status' => 'settlement',
        ])->assertForbidden();
        $this->assertSame('unpaid', $this->payment->fresh()->status);

        // 2. Callback settled dengan overpayment besar ditandai refund_required dan tidak mengaktifkan tim
        $overAmount = '1600000.00';
        $sigOver = hash('sha512', $orderId.$statusCode.$overAmount.'test-server-key');

        $this->postJson(route('webhooks.midtrans'), [
            'order_id' => $orderId,
            'status_code' => $statusCode,
            'gross_amount' => $overAmount,
            'signature_key' => $sigOver,
            'transaction_status' => 'settlement',
        ])->assertOk();

        $freshPayment = $this->payment->fresh();
        $this->assertSame('refund_required', $freshPayment->status);
        $this->assertStringContainsString('Overpayment terdeteksi', (string) $freshPayment->rejection_reason);
        $this->assertSame('pending_payment', $this->team->fresh()->status);
    }

    public function test_expired_webhook_for_individual_audition_participant_does_not_fail_on_null_team(): void
    {
        config(['payment.xendit.callback_token' => 'xnd_cb_token']);

        $indEvent = Event::create([
            'name' => 'Audisi Seleksi Nasional',
            'slug' => 'audisi-seleksi-nasional',
            'participant_type' => 'individual',
            'venue_name' => 'Lapangan A',
            'city' => 'Jakarta',
            'format' => 'league',
            'age_category' => 'U-17',
            'min_birth_date' => '2009-01-01',
            'max_birth_date' => '2010-12-31',
            'min_players_per_team' => 1,
            'max_players_per_team' => 1,
            'max_officials_per_team' => 0,
            'required_documents' => [],
            'max_participants' => 50,
            'registration_fee' => 250000,
            'payment_deadline_hours' => 24,
            'status' => 'open',
        ]);

        $participantUser = User::factory()->create();
        $participant = AuditionParticipant::create([
            'event_id' => $indEvent->id,
            'user_id' => $participantUser->id,
            'registration_number' => 'AUD-2026-001-0001',
            'full_name' => 'Peserta Audisi Satu',
            'nik' => '3171000000000001',
            'birth_date' => '2009-05-10',
            'gender' => 'L',
            'emergency_contact_name' => 'Orang Tua',
            'emergency_contact_phone' => '081234567890',
            'status' => 'pending_payment',
            'slot_expires_at' => now()->addHours(24),
        ]);

        $indPayment = Payment::create([
            'invoice_number' => 'INV-2026-AUD2-0001',
            'audition_participant_id' => $participant->id,
            'event_id' => $indEvent->id,
            'amount' => 250000,
            'gateway_fee' => 0,
            'total_amount' => 250000,
            'method' => 'xendit',
            'expires_at' => now()->addHours(24),
            'status' => 'unpaid',
        ]);

        $res = $this->postJson(route('webhooks.xendit'), [
            'id' => 'xnd-exp-1',
            'external_id' => $indPayment->invoice_number,
            'status' => 'EXPIRED',
            'amount' => 250000,
        ], ['x-callback-token' => 'xnd_cb_token']);

        $res->assertOk();
        $this->assertSame('expired', $indPayment->fresh()->status);
        $this->assertSame('cancelled', $participant->fresh()->status);
    }

    public function test_approve_manual_payment_transitions_to_refund_required_when_quota_already_full(): void
    {
        $this->event->update(['max_teams' => 1]);

        // Tim lain sudah aktif menempati satu-satunya slot
        Team::create([
            'event_id' => $this->event->id,
            'user_id' => User::factory()->create()->id,
            'name' => 'Slot Terisi FC',
            'slug' => 'slot-terisi-fc',
            'logo_path' => 'teams/logos/sample.png',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Budi',
            'manager_whatsapp' => '081234567890',
            'manager_email' => 'budi-slot@example.com',
            'head_coach_name' => 'Coach Budi',
            'terms_accepted_at' => now(),
            'status' => 'active',
        ]);

        $this->payment->update([
            'method' => 'manual',
            'status' => 'waiting_verification',
            'proof_file_path' => 'payments/proofs/test.jpg',
        ]);

        $res = $this->actingAs($this->financeUser)->post(
            route('admin.payments.verify', ['payment' => $this->payment->id]),
            ['action' => 'approve']
        );

        $res->assertSessionHas('warning');
        $this->assertSame('refund_required', $this->payment->fresh()->status);
        $this->assertSame('expired', $this->team->fresh()->status);
        $this->assertEquals(1, $this->event->fresh()->occupiedSlotsCount());
    }

    public function test_mark_refunded_persists_structured_refund_columns(): void
    {
        $this->payment->update([
            'status' => 'refund_required',
            'method' => 'midtrans',
            'total_amount' => 1504000,
        ]);

        $res = $this->actingAs($this->financeUser)->post(
            route('admin.payments.mark-refunded', ['payment' => $this->payment->id]),
            [
                'refund_notes' => 'Transfer balik dana pendaftaran via BCA',
                'refund_amount' => 1504000,
                'refund_reference' => 'BCA-REF-20261113',
                'refund_method' => 'bank_transfer',
            ]
        );

        $res->assertRedirect();
        $fresh = $this->payment->fresh();
        $this->assertSame('refunded', $fresh->status);
        $this->assertEquals(1504000.0, (float) $fresh->refund_amount);
        $this->assertSame('BCA-REF-20261113', $fresh->refund_reference);
        $this->assertSame('bank_transfer', $fresh->refund_method);
        $this->assertSame('Transfer balik dana pendaftaran via BCA', $fresh->refund_notes);
        $this->assertNotNull($fresh->refunded_at);
    }
}
