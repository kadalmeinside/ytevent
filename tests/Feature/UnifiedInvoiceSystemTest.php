<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditionRegistrationService;
use App\Services\InvoiceService;
use App\Services\TeamRegistrationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UnifiedInvoiceSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('private');
        Notification::fake();
    }

    public function test_unified_invoice_numbering_and_immutable_snapshot_across_all_event_types(): void
    {
        $tournamentEvent = Event::create([
            'name' => 'Persija Cup Tournament',
            'slug' => 'persija-cup-tournament',
            'venue_name' => 'Stadion ABC',
            'city' => 'Jakarta',
            'format' => 'group_knockout',
            'age_category' => 'U-15',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'quota_type' => 'fixed',
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'status' => 'open',
            'required_documents' => ['birth_certificate'],
        ]);

        $auditionEvent = Event::create([
            'name' => 'Audisi Talenta Persija',
            'slug' => 'audisi-talenta-persija',
            'venue_name' => 'Sawangan',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 250000,
            'status' => 'open',
            'required_documents' => ['birth_certificate'],
        ]);

        $clinicEvent = Event::create([
            'name' => 'Coaching Clinic Elite',
            'slug' => 'coaching-clinic-elite',
            'venue_name' => 'Jakarta',
            'city' => 'Jakarta',
            'format' => 'coaching_clinic',
            'age_category' => 'U-11',
            'event_type' => 'coaching_clinic',
            'participant_type' => 'individual',
            'quota_type' => 'unlimited',
            'registration_fee' => 0,
            'status' => 'open',
            'required_documents' => [],
        ]);

        $festivalIndividualEvent = Event::create([
            'name' => 'Fun Festival Individual',
            'slug' => 'fun-festival-individual',
            'venue_name' => 'Lapangan Banteng',
            'city' => 'Jakarta',
            'format' => 'festival',
            'age_category' => 'U-10',
            'event_type' => 'festival',
            'participant_type' => 'individual',
            'quota_type' => 'unlimited',
            'registration_fee' => 100000,
            'status' => 'open',
            'required_documents' => [],
        ]);

        /** @var TeamRegistrationService $teamReg */
        $teamReg = app(TeamRegistrationService::class);
        $team = $teamReg->registerNewTeam(
            event: $tournamentEvent,
            validated: [
                'team_name' => 'SSB Macan Kemayoran',
                'origin_association' => 'Askot Jakarta Pusat',
                'manager_name' => 'Budi Santoso',
                'manager_whatsapp' => '081234567890',
                'head_coach_name' => 'Coach Ahmad',
                'email' => 'budi@macan.id',
                'password' => 'Secret123!',
                'terms_accepted' => true,
            ],
            logoFile: UploadedFile::fake()->image('logo.png', 200, 200)
        );
        /** @var Payment $teamPayment */
        $teamPayment = $team->latestPayment;

        /** @var AuditionRegistrationService $audReg */
        $audReg = app(AuditionRegistrationService::class);
        $audRes = $audReg->registerAuditionParticipant(
            event: $auditionEvent,
            validated: [
                'full_name' => 'Raka Pratama',
                'nik' => '3171010101010001',
                'birth_date' => '2014-05-10',
                'gender' => 'male',
                'preferred_position' => 'MF',
                'emergency_contact_name' => 'Pak Hendra',
                'emergency_contact_phone' => '081299988877',
                'guardian_name' => 'Pak Hendra',
                'guardian_phone' => '081299988877',
                'guardian_email' => 'hendra@ortu.id',
                'guardian_password' => 'Secret123!',
                'terms_accepted' => true,
            ],
            photoFile: UploadedFile::fake()->image('raka.jpg', 300, 400),
            birthCertFile: UploadedFile::fake()->image('akta.jpg', 300, 400),
        );
        /** @var Payment $audPayment */
        $audPayment = $audRes['payment'];

        // Free Coaching Clinic must ALSO issue a Rp 0 paid Receipt/Invoice consistently
        $clinicRes = $audReg->registerAuditionParticipant(
            event: $clinicEvent,
            validated: [
                'full_name' => 'Dafa Kusuma',
                'nik' => '3171010101010002',
                'birth_date' => '2015-03-14',
                'gender' => 'male',
                'emergency_contact_name' => 'Ibu Sari',
                'emergency_contact_phone' => '081277766655',
                'guardian_name' => 'Ibu Sari',
                'guardian_phone' => '081277766655',
                'guardian_email' => 'sari@ortu.id',
                'guardian_password' => 'Secret123!',
                'terms_accepted' => true,
            ],
            photoFile: UploadedFile::fake()->image('dafa.jpg', 300, 400),
        );
        /** @var Payment $clinicPayment */
        $clinicPayment = $clinicRes['payment'];

        $festRes = $audReg->registerAuditionParticipant(
            event: $festivalIndividualEvent,
            validated: [
                'full_name' => 'Zidan Putra',
                'nik' => '3171010101010003',
                'birth_date' => '2016-07-20',
                'gender' => 'male',
                'emergency_contact_name' => 'Pak Adi',
                'emergency_contact_phone' => '081255544433',
                'guardian_name' => 'Pak Adi',
                'guardian_phone' => '081255544433',
                'guardian_email' => 'adi@ortu.id',
                'guardian_password' => 'Secret123!',
                'terms_accepted' => true,
            ],
            photoFile: UploadedFile::fake()->image('zidan.jpg', 300, 400),
        );
        /** @var Payment $festPayment */
        $festPayment = $festRes['payment'];

        $year = now()->format('Y');
        $this->assertStringStartsWith("INV-{$year}-TRN" . str_pad((string) $tournamentEvent->id, 2, '0', STR_PAD_LEFT) . '-', $teamPayment->invoice_number);
        $this->assertStringStartsWith("INV-{$year}-AUD" . str_pad((string) $auditionEvent->id, 2, '0', STR_PAD_LEFT) . '-', $audPayment->invoice_number);
        $this->assertStringStartsWith("INV-{$year}-CLN" . str_pad((string) $clinicEvent->id, 2, '0', STR_PAD_LEFT) . '-', $clinicPayment->invoice_number);
        $this->assertStringStartsWith("INV-{$year}-IND" . str_pad((string) $festivalIndividualEvent->id, 2, '0', STR_PAD_LEFT) . '-', $festPayment->invoice_number);

        $this->assertNotNull($teamPayment->issued_at);
        $this->assertNotNull($audPayment->issued_at);
        $this->assertEquals('paid', $clinicPayment->status);
        $this->assertEquals(0.0, (float) $clinicPayment->total_amount);
        $this->assertEquals('Pendaftaran Gratis', $clinicPayment->payment_channel);

        // Snapshot immutability check: alter Event title & fee AFTER registration
        $auditionEvent->update([
            'name' => 'Judul Event Berubah Total',
            'registration_fee' => 999999,
        ]);

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $resolvedAud = $invoiceService->resolveInvoiceData($audPayment->fresh());

        $this->assertEquals('Audisi Talenta Persija', $resolvedAud['event']['name']);
        $this->assertEquals('Raka Pratama', $resolvedAud['bill_to']['name']);
        $this->assertEquals('individual', $resolvedAud['bill_to']['subject_type']);
        $this->assertEquals(250000.0, (float) $resolvedAud['total_amount']);
        $this->assertEquals('INVOICE', $resolvedAud['document_type']);

        $resolvedClinic = $invoiceService->resolveInvoiceData($clinicPayment->fresh());
        $this->assertEquals('RECEIPT', $resolvedClinic['document_type']);
        $this->assertEquals('KWITANSI PEMBAYARAN LUNAS', $resolvedClinic['document_title']);
    }

    public function test_invoice_pdf_download_authorization_and_admin_finance_unified_views(): void
    {
        $teamEvent = Event::create([
            'name' => 'Liga Akademi U-14',
            'slug' => 'liga-akademi-u14',
            'venue_name' => 'Stadion Utama',
            'city' => 'Jakarta',
            'format' => 'league',
            'age_category' => 'U-14',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'quota_type' => 'fixed',
            'max_teams' => 12,
            'registration_fee' => 1000000,
            'status' => 'open',
            'required_documents' => [],
        ]);

        $audEvent = Event::create([
            'name' => 'Seleksi Akademi U-13',
            'slug' => 'seleksi-akademi-u13',
            'venue_name' => 'Sawangan',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-13',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 50,
            'registration_fee' => 200000,
            'status' => 'open',
            'required_documents' => [],
        ]);

        /** @var TeamRegistrationService $teamReg */
        $teamReg = app(TeamRegistrationService::class);
        $team = $teamReg->registerNewTeam(
            event: $teamEvent,
            validated: [
                'team_name' => 'Garuda Muda FC',
                'origin_association' => 'Jakarta Selatan',
                'manager_name' => 'Andi Wijaya',
                'manager_whatsapp' => '081111111111',
                'head_coach_name' => 'Coach Rony',
                'email' => 'andi@garuda.id',
                'password' => 'Secret123!',
                'terms_accepted' => true,
            ],
            logoFile: UploadedFile::fake()->image('garuda.png', 200, 200)
        );
        /** @var Payment $teamPayment */
        $teamPayment = $team->latestPayment;

        /** @var AuditionRegistrationService $audReg */
        $audReg = app(AuditionRegistrationService::class);
        $audRes = $audReg->registerAuditionParticipant(
            event: $audEvent,
            validated: [
                'full_name' => 'Bima Sakti Junior',
                'nik' => '3171020202020009',
                'birth_date' => '2013-02-11',
                'gender' => 'male',
                'emergency_contact_name' => 'Ibu Rina',
                'emergency_contact_phone' => '081222222222',
                'guardian_name' => 'Ibu Rina',
                'guardian_phone' => '081222222222',
                'guardian_email' => 'rina@ortu.id',
                'guardian_password' => 'Secret123!',
                'terms_accepted' => true,
            ],
            photoFile: UploadedFile::fake()->image('bima.jpg', 300, 400),
        );
        /** @var Payment $audPayment */
        $audPayment = $audRes['payment'];
        /** @var User $parentUser */
        $parentUser = $audRes['user'];

        // Put individual participant payment into waiting_verification so Finance sees it in manual queue
        $audPayment->update([
            'method' => 'manual',
            'payment_channel' => 'Transfer Bank Manual',
            'sender_bank_name' => 'BCA',
            'sender_account_holder' => 'Ibu Rina',
            'proof_file_path' => 'payments/proofs/test.jpg',
            'status' => 'waiting_verification',
        ]);

        $teamManager = User::findOrFail($team->user_id);

        // 1. Owner Team Manager can download Team Invoice PDF, blocked from Parent Invoice PDF
        $this->actingAs($teamManager)
            ->get(route('manager.payments.invoice', ['payment' => $teamPayment->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($teamManager)
            ->get(route('manager.payments.invoice', ['payment' => $audPayment->id]))
            ->assertForbidden();

        // 2. Owner Parent can download Participant Invoice PDF, blocked from Team Invoice PDF
        $this->actingAs($parentUser)
            ->get(route('manager.payments.invoice', ['payment' => $audPayment->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($parentUser)
            ->get(route('manager.payments.invoice', ['payment' => $teamPayment->id]))
            ->assertForbidden();

        // 3. Finance Admin can access manual queue, transactions search, CSV export, and Invoice PDF for both
        $financeUser = User::factory()->create(['is_active' => true]);
        $financeUser->assignRole('finance');

        $this->actingAs($financeUser)
            ->get(route('admin.payments.invoice', ['payment' => $audPayment->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($financeUser)
            ->get(route('admin.payments.manual-verification'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Payments/ManualVerification')
                ->where('queue.0.invoice_number', $audPayment->invoice_number)
                ->where('queue.0.payer.subject_type', 'individual')
                ->where('queue.0.payer.name', 'Bima Sakti Junior')
            );

        $this->actingAs($financeUser)
            ->get(route('admin.payments.transactions', ['search' => 'Bima Sakti']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Payments/Transactions')
                ->has('transactions', 1)
                ->where('transactions.0.payer.name', 'Bima Sakti Junior')
                ->where('transactions.0.payer.subject_type', 'individual')
            );

        $csvResponse = $this->actingAs($financeUser)
            ->get(route('admin.payments.transactions.export'));
        $csvResponse->assertOk();
        $csvBody = $csvResponse->streamedContent();
        $this->assertStringContainsString('Tipe Peserta', $csvBody);
        $this->assertStringContainsString('Peserta Perorangan', $csvBody);
        $this->assertStringContainsString('Bima Sakti Junior', $csvBody);
        $this->assertStringContainsString('Garuda Muda FC', $csvBody);
    }
}

