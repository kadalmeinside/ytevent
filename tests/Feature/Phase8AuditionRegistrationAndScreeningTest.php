<?php

namespace Tests\Feature;

use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase8AuditionRegistrationAndScreeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('private');
    }

    public function test_guest_can_register_child_in_audition_event(): void
    {
        $event = Event::create([
            'name' => 'Audisi Bakat Persija U-12 2026',
            'slug' => 'audisi-bakat-persija-u-12-2026',
            'venue_name' => 'Nirwana Park Sawangan',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 150000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
            'form_presets' => [
                'position' => true,
                'foot' => true,
                'team_origin' => true,
                'body_measurements' => true,
                'jersey' => true,
            ],
        ]);

        $session = EventSession::create([
            'event_id' => $event->id,
            'session_name' => 'Sesi Pagi (08.00 - 11.30 WIB)',
            'quota' => 50,
            'occupied_slots' => 0,
        ]);

        $photo = UploadedFile::fake()->image('pas_foto.jpg', 400, 600);
        $birthCert = UploadedFile::fake()->create('akta_lahir.pdf', 500, 'application/pdf');
        $familyCard = UploadedFile::fake()->create('kartu_keluarga.pdf', 500, 'application/pdf');

        $payload = [
            'guardian_name' => 'Bambang Pamungkas',
            'guardian_email' => 'bambang.parent@persijadevelopment.id',
            'guardian_phone' => '081234567890',
            'guardian_password' => 'Rahasia123!',
            'guardian_password_confirmation' => 'Rahasia123!',

            'full_name' => 'Bagas Pamungkas',
            'nickname' => 'Bagas',
            'nik' => '3171012345678901',
            'birth_date' => '2014-06-15',
            'gender' => 'male',
            'event_session_id' => $session->id,

            'preferred_position' => 'CF',
            'secondary_position' => 'RW',
            'dominant_foot' => 'right',
            'height_cm' => 148,
            'weight_kg' => 39,
            'current_club_ssb' => 'SSB Menteng Jaya',
            'jersey_size' => 'M',

            'emergency_contact_name' => 'Tribuana Tungga Dewi',
            'emergency_contact_phone' => '081987654321',
            'emergency_contact_relation' => 'Ibu Kandung',
            'medical_notes' => 'Tidak ada riwayat alergi.',
            'liability_waiver' => '1',

            'photo' => $photo,
            'birth_certificate' => $birthCert,
            'family_card' => $familyCard,
        ];

        $response = $this->post(route('events.register-audition.store', ['event' => $event->slug]), $payload);

        $this->assertAuthenticated();

        // Verifikasi User dibuat dengan role participant_parent
        $user = User::where('email', 'bambang.parent@persijadevelopment.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('participant_parent'));

        // Verifikasi AuditionParticipant tersimpan
        $participant = AuditionParticipant::where('nik', '3171012345678901')->first();
        $this->assertNotNull($participant);
        $response->assertRedirect(route('manager.payments', ['participant_id' => $participant->id]));
        $this->assertSame($user->id, $participant->user_id);
        $this->assertSame($event->id, $participant->event_id);
        $this->assertSame($session->id, $participant->event_session_id);
        $this->assertSame('submitted', $participant->screening_status);
        $this->assertSame('pending_payment', $participant->status);
        $this->assertNotNull($participant->liability_accepted_at);
        $this->assertStringStartsWith('AUD-', $participant->registration_number);

        // Verifikasi Invoice Payment terbit
        $payment = Payment::where('audition_participant_id', $participant->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(150000.0, (float) $payment->total_amount);
        $this->assertSame('unpaid', $payment->status);
        $this->assertStringStartsWith('INV-', $payment->invoice_number);

        // Verifikasi session increment
        $session->refresh();
        $this->assertSame(1, $session->occupied_slots);

        // Verifikasi halaman Manager/Payments memuat tagihan peserta (tidak muncul "Tidak Ada Tagihan Aktif")
        $this->actingAs($user)
            ->get(route('manager.payments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Manager/Payments')
                ->where('billingType', 'audition_participant')
                ->where('auditionParticipant.id', $participant->id)
                ->where('auditionParticipant.full_name', 'Bagas Pamungkas')
                ->where('payment.invoice_number', $payment->invoice_number)
                ->where('payment.status', 'unpaid')
            );
    }

    public function test_nik_must_be_unique_per_event(): void
    {
        $event = Event::create([
            'name' => 'Audisi Akademi Persija 2026',
            'slug' => 'audisi-akademi-persija-2026',
            'venue_name' => 'Bojongsari',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $parent = User::factory()->create();
        $parent->assignRole('participant_parent');

        AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-0001',
            'full_name' => 'Anak Pertama',
            'nik' => '3171012345678901',
            'birth_date' => '2014-05-10',
            'gender' => 'male',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081234567890',
            'screening_status' => 'submitted',
            'status' => 'active',
        ]);

        $photo = UploadedFile::fake()->image('photo.jpg');
        $birthCert = UploadedFile::fake()->create('akta.pdf', 100, 'application/pdf');
        $familyCard = UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf');

        $payload = [
            'full_name' => 'Anak Kedua Tapi NIK Sama',
            'nik' => '3171012345678901', // Sama!
            'birth_date' => '2014-05-10',
            'gender' => 'male',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081234567890',
            'liability_waiver' => '1',
            'photo' => $photo,
            'birth_certificate' => $birthCert,
            'family_card' => $familyCard,
        ];

        $response = $this->actingAs($parent)
            ->post(route('events.register-audition.store', ['event' => $event->slug]), $payload);

        $response->assertSessionHasErrors('nik');
    }

    public function test_screening_officer_can_view_auditions_and_approve_participant(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('screening_officer');

        $event = Event::create([
            'name' => 'Seleksi Elite Pro Academy 2026',
            'slug' => 'seleksi-epa-2026',
            'venue_name' => 'Training Ground',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $parent = User::factory()->create();
        $parent->assignRole('participant_parent');

        $participant = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-0042',
            'full_name' => 'Bintang Masa Depan',
            'nik' => '3276012345678901',
            'birth_date' => '2012-04-12',
            'gender' => 'male',
            'preferred_position' => 'MF',
            'emergency_contact_name' => 'Ayah',
            'emergency_contact_phone' => '081234567890',
            'screening_status' => 'submitted',
            'status' => 'active',
        ]);

        // Officer mengakses halaman antrean audisi
        $response = $this->actingAs($officer)->get(route('admin.screening.auditions'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Screening/Auditions')
            ->has('participants.data', 1)
        );

        // Officer menyetujui (Approve) berkas
        $approveResponse = $this->actingAs($officer)
            ->post(route('admin.screening.auditions.verify', ['participant' => $participant->id]), [
                'status' => 'passed',
            ]);

        $approveResponse->assertRedirect();
        $approveResponse->assertSessionHas('success');

        $participant->refresh();
        $this->assertSame('passed', $participant->screening_status);
        $this->assertNull($participant->bib_number); // Nomor bib/rompi bersifat opsional
        $this->assertNotNull($participant->screening_code);
        $this->assertSame($officer->id, $participant->screening_verified_by);
        $this->assertNotNull($participant->screening_verified_at);
    }

    public function test_screening_officer_can_reject_participant_with_reason(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('screening_officer');

        $event = Event::create([
            'name' => 'Audisi U-10',
            'slug' => 'audisi-u-10',
            'venue_name' => 'Sawangan',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-10',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $participant = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create()->id,
            'registration_number' => 'AUD-2026-0099',
            'full_name' => 'Calon Atlet',
            'nik' => '3175012345678901',
            'birth_date' => '2016-08-20',
            'gender' => 'male',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081234567890',
            'screening_status' => 'submitted',
            'status' => 'active',
        ]);

        $rejectResponse = $this->actingAs($officer)
            ->post(route('admin.screening.auditions.verify', ['participant' => $participant->id]), [
                'status' => 'rejected',
                'notes' => 'Scan Akta Kelahiran terpotong pada bagian tanggal lahir dan stempel Disdukcapil.',
            ]);

        $rejectResponse->assertRedirect();

        $participant->refresh();
        $this->assertSame('rejected', $participant->screening_status);
        $this->assertStringContainsString('terpotong', $participant->screening_notes);
        $this->assertNull($participant->bib_number);
    }

    public function test_private_document_access_control(): void
    {
        $officer = User::factory()->create();
        $officer->assignRole('screening_officer');

        $parent = User::factory()->create();
        $parent->assignRole('participant_parent');

        $stranger = User::factory()->create();
        $stranger->assignRole('participant_parent');

        $event = Event::create([
            'name' => 'Audisi U-12',
            'slug' => 'audisi-u-12',
            'venue_name' => 'Sawangan',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $dummyPath = 'auditions/documents/birth_cert/test_akta.pdf';
        Storage::disk('private')->put($dummyPath, 'DUMMY_PDF_CONTENT');

        $participant = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-0123',
            'full_name' => 'Anak Bambang',
            'nik' => '3171012345678901',
            'birth_date' => '2014-05-10',
            'gender' => 'male',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081234567890',
            'birth_certificate_path' => $dummyPath,
            'screening_status' => 'submitted',
            'status' => 'active',
        ]);

        // 1. Orang tua kandung dapat melihat
        $this->actingAs($parent)
            ->get(route('private.audition.birth-certificate', ['participant' => $participant->id]))
            ->assertStatus(200);

        // 2. Petugas screening dapat melihat
        $this->actingAs($officer)
            ->get(route('private.audition.birth-certificate', ['participant' => $participant->id]))
            ->assertStatus(200);

        // 3. Orang tua lain / pengguna asing ditolak (403 Forbidden)
        $this->actingAs($stranger)
            ->get(route('private.audition.birth-certificate', ['participant' => $participant->id]))
            ->assertStatus(403);
    }

    public function test_public_event_detail_renders_individual_audition_with_stages_and_participants(): void
    {
        $event = Event::create([
            'name' => 'Persija EPA Future Stars Scouting Fest 2026',
            'slug' => 'persija-epa-future-stars-2026',
            'venue_name' => 'Persija Training Ground, Bojongsari',
            'city' => 'Kota Depok',
            'format' => 'audition',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'age_category' => 'U-14',
            'max_participants' => 150,
            'registration_fee' => 250000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
            'min_birth_date' => '2012-01-01',
            'max_birth_date' => '2013-12-31',
        ]);

        AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Tahap 1: Screening Fisik & Tes Teknik',
            'stage_date' => '2026-11-10',
            'start_time' => '07:30:00',
            'end_time' => '12:00:00',
            'venue_name' => 'Persija Training Ground',
            'target_quota' => 150,
            'instructions' => 'Wajib memakai jersey latihan dan sepatu bola.',
        ]);

        AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 2,
            'stage_name' => 'Tahap 2: Game Internal & Taktikal',
            'stage_date' => '2026-11-11',
            'start_time' => '08:00:00',
            'end_time' => '13:00:00',
            'venue_name' => 'Persija Training Ground',
            'target_quota' => 75,
            'instructions' => 'Simulasi pertandingan 11 vs 11.',
        ]);

        $parent = User::factory()->create();
        $parent->assignRole('participant_parent');

        AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-ESP0101',
            'bib_number' => 101,
            'full_name' => 'Muhammad Rayhan Al-Fatih',
            'nickname' => 'Rayhan',
            'nik' => '3171021205120001',
            'birth_date' => '2012-05-12',
            'gender' => 'male',
            'emergency_contact_name' => 'Bapak Rayhan',
            'emergency_contact_phone' => '081234567890',
            'preferred_position' => 'Attacking Midfielder (AMF)',
            'current_club_ssb' => 'SSB Jayakarta Muda',
            'stage_status' => 'day_1_passed',
            'screening_status' => 'passed',
            'status' => 'active',
        ]);

        $response = $this->get(route('events.show', ['event' => $event->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/EventDetail')
            ->has('stages', 2)
            ->where('stages.0.stage_name', 'Tahap 1: Screening Fisik & Tes Teknik')
            ->has('registeredParticipants', 1)
            ->where('registeredParticipants.0.registration_number', 'AUD-2026-ESP0101')
            ->where('registeredParticipants.0.bib_number', 101)
            ->where('registeredParticipants.0.public_label', 'Pass AUD-2026-ESP0101')
            ->where('event.participant_type', 'individual')
            ->where('event.format', 'audition')
        );
    }

    public function test_superadmin_can_store_and_update_position_quota_caps(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $payload = [
            'name' => 'Audisi Scouting Mandiri 2026',
            'venue_name' => 'Persija Training Ground',
            'city' => 'Kota Depok',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
            'registration_open_at' => '2026-10-01 00:00:00',
            'registration_close_at' => '2026-11-19 23:59:59',
            'registration_fee' => 200000,
            'quota_type' => 'fixed',
            'max_participants' => 150,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 24,
            'min_birth_date' => '2012-01-01',
            'max_birth_date' => '2013-12-31',
            'age_category' => 'U-14',
            'participant_type' => 'individual',
            'event_type' => 'audition',
            'format' => 'audition',
            'status' => 'open',
            'position_quota_caps' => [
                'GK' => 16,
                'CB' => 30,
                'FB' => 30,
                'MF' => 44,
                'FW' => 30,
            ],
            'form_presets' => [
                'require_photo' => true,
                'require_position' => true,
            ],
            'required_documents' => ['birth_certificate', 'family_card'],
        ];

        $response = $this->actingAs($superadmin)->post(route('admin.events.store'), $payload);
        $response->assertRedirect(route('admin.events.index'));

        $event = Event::where('name', 'Audisi Scouting Mandiri 2026')->first();
        $this->assertNotNull($event);
        $this->assertSame(16, $event->position_quota_caps['GK']);
        $this->assertSame(30, $event->position_quota_caps['CB']);
        $this->assertSame(44, $event->position_quota_caps['MF']);

        // Update to reset / clear position caps
        $updatePayload = array_merge($payload, [
            'name' => 'Audisi Scouting Mandiri 2026 Updated',
            'position_quota_caps' => [
                'GK' => '',
                'CB' => null,
                'FB' => '',
                'MF' => 0,
                'FW' => '',
            ],
        ]);

        $updateResponse = $this->actingAs($superadmin)->post(route('admin.events.update', $event->id), $updatePayload);
        $updateResponse->assertRedirect(route('admin.events.index'));

        $event->refresh();
        $this->assertNull($event->position_quota_caps);
    }

    public function test_registration_validates_position_quota_caps(): void
    {
        Storage::fake('private');
        Notification::fake();

        $event = Event::create([
            'name' => 'Audisi Terbatas GK 2026',
            'slug' => 'audisi-terbatas-gk-2026',
            'venue_name' => 'Bojongsari',
            'city' => 'Kota Depok',
            'format' => 'audition',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'open',
            'quota_type' => 'fixed',
            'max_participants' => 10,
            'registration_fee' => 0,
            'position_quota_caps' => [
                'GK' => 1,
            ],
            'form_presets' => [
                'photo' => false,
                'position' => true,
                'foot_dominance' => false,
                'team_origin' => false,
                'body_measurements' => false,
                'jersey_size' => false,
                'experience' => false,
            ],
            'required_documents' => ['birth_certificate'],
        ]);

        $session = EventSession::create([
            'event_id' => $event->id,
            'session_name' => 'Sesi Pagi',
            'session_date' => '2026-11-20',
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
            'max_capacity' => 10,
            'occupied_slots' => 0,
            'is_active' => true,
        ]);

        $parent = User::factory()->create();
        $parent->assignRole('participant_parent');

        // Existing GK already registered
        AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-GK-01',
            'full_name' => 'Kiper Satu',
            'nik' => '3171012345678909',
            'birth_date' => '2012-05-10',
            'gender' => 'male',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081234567890',
            'preferred_position' => 'Goalkeeper (GK)',
            'screening_status' => 'passed',
            'status' => 'active',
        ]);

        // Attempt second GK registration should fail due to position quota
        $payload = [
            'guardian_name' => 'Orang Tua Kiper Dua',
            'guardian_email' => 'kiper2@persija.id',
            'guardian_phone' => '081299998888',
            'guardian_password' => 'Rahasia123!',
            'guardian_password_confirmation' => 'Rahasia123!',
            'full_name' => 'Kiper Dua',
            'nik' => '3171012345678999',
            'birth_date' => '2012-06-15',
            'gender' => 'male',
            'event_session_id' => $session->id,
            'preferred_position' => 'Goalkeeper (GK)',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081234567890',
            'emergency_contact_relation' => 'Ayah',
            'liability_waiver' => '1',
            'birth_certificate' => UploadedFile::fake()->create('birth.pdf', 200, 'application/pdf'),
        ];

        $response = $this->post(route('events.register-audition.store', ['event' => $event->slug]), $payload);

        $response->assertSessionHasErrors(['preferred_position']);
        $this->assertDatabaseMissing('audition_participants', [
            'nik' => '3171012345678999',
        ]);
    }

    public function test_paid_audition_registration_auto_initiates_midtrans_snap_and_redirects_to_checkout(): void
    {
        Notification::fake();

        SystemSetting::set('payment_method_mode', 'midtrans', 'payment');
        SystemSetting::set('payment_gateway_default', 'midtrans', 'payment');
        SystemSetting::set('midtrans_server_key', 'SB-Mid-server-test-key', 'payment');
        SystemSetting::set('midtrans_client_key', 'SB-Mid-client-test-key', 'payment');
        config(['payment.sandbox_simulator' => false]);

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-aud-999',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-aud-999',
            ], 201),
        ]);

        $event = Event::create([
            'name' => 'Coaching Clinic Persija 2026',
            'slug' => 'coaching-clinic-persija-2026',
            'venue_name' => 'Lapangan Bojongsari',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-12',
            'event_type' => 'coaching_clinic',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 50,
            'registration_fee' => 250000,
            'status' => 'open',
            'required_documents' => ['birth_certificate'],
            'form_presets' => [
                'photo' => false,
                'position' => true,
                'foot_dominance' => false,
                'team_origin' => false,
                'body_measurements' => false,
                'experience' => false,
                'jersey_size' => false,
            ],
        ]);

        $response = $this->post(route('events.register-audition.store', ['event' => $event->slug]), [
            'guardian_name' => 'Hendra Setiawan',
            'guardian_email' => 'hendra.clinic@persija.id',
            'guardian_phone' => '081234567890',
            'guardian_password' => 'Rahasia123',
            'guardian_password_confirmation' => 'Rahasia123',
            'full_name' => 'Raka Setiawan',
            'nik' => '3171022345678901',
            'birth_date' => '2014-04-10',
            'gender' => 'male',
            'preferred_position' => 'CMF',
            'emergency_contact_name' => 'Dian',
            'emergency_contact_phone' => '081234567891',
            'liability_waiver' => '1',
            'birth_certificate' => UploadedFile::fake()->create('akta.pdf', 150, 'application/pdf'),
        ]);

        $participant = AuditionParticipant::where('nik', '3171022345678901')->firstOrFail();
        $payment = Payment::where('audition_participant_id', $participant->id)->firstOrFail();

        $this->assertSame('snap-token-aud-999', $payment->snap_token);
        $response->assertRedirect(route('manager.payments', [
            'participant_id' => $participant->id,
            'auto_pay' => 1,
        ]));
        $response->assertSessionHas('auto_open_snap', true);
    }

    public function test_inline_email_check_and_inline_login_for_registration_forms(): void
    {
        $teamEvent = Event::create([
            'name' => 'Turnamen Persija Cup 2026',
            'slug' => 'turnamen-persija-cup-2026',
            'venue_name' => 'Stadion Cendrawasih',
            'city' => 'Jakarta Barat',
            'format' => 'league',
            'age_category' => 'U-12',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'quota_type' => 'fixed',
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $auditionEvent = Event::create([
            'name' => 'Audisi Talenta 2026',
            'slug' => 'audisi-talenta-2026',
            'venue_name' => 'Bojongsari',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 150000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $existingUser = User::factory()->create([
            'name' => 'Andi Pratama',
            'email' => 'andi.terdaftar@persija.id',
            'password' => bcrypt('Rahasia123'),
            'is_active' => true,
        ]);
        $existingUser->assignRole('participant_parent');

        // 1. Check unknown email
        $this->postJson(route('events.check-email'), [
            'email' => 'baru.belumdaftar@persija.id',
        ])->assertOk()->assertJson([
            'exists' => false,
        ]);

        // 2. Check existing email (case-insensitive)
        $this->postJson(route('events.check-email'), [
            'email' => 'ANDI.TERDAFTAR@persija.id',
        ])->assertOk()->assertJson([
            'exists' => true,
            'name' => 'Andi Pratama',
        ]);

        // 3. Inline login with wrong password fails on login_password
        $this->post(route('events.inline-login', ['event' => $auditionEvent->slug]), [
            'email' => 'andi.terdaftar@persija.id',
            'password' => 'SalahPassword',
            'redirect_to' => 'register-audition',
        ])->assertSessionHasErrors('login_password');
        $this->assertGuest();

        // 4. Guest submitting audition registration with already-registered email gets validation error
        $this->post(route('events.register-audition.store', ['event' => $auditionEvent->slug]), [
            'guardian_name' => 'Andi Pratama',
            'guardian_email' => 'andi.terdaftar@persija.id',
            'guardian_phone' => '081234567890',
            'guardian_password' => 'Rahasia123',
            'full_name' => 'Fajar Pratama',
            'nik' => '3171022345678888',
            'birth_date' => '2012-04-10',
            'gender' => 'male',
            'preferred_position' => 'CMF',
            'emergency_contact_name' => 'Ibu Fajar',
            'emergency_contact_phone' => '081234567891',
            'liability_waiver' => '1',
            'birth_certificate' => UploadedFile::fake()->create('akta.pdf', 150, 'application/pdf'),
            'family_card' => UploadedFile::fake()->create('kk.pdf', 150, 'application/pdf'),
        ])->assertSessionHasErrors('guardian_email');

        // 5. Inline login succeeds and redirects to audition registration page
        $this->post(route('events.inline-login', ['event' => $auditionEvent->slug]), [
            'email' => 'andi.terdaftar@persija.id',
            'password' => 'Rahasia123',
            'redirect_to' => 'register-audition',
        ])->assertRedirect(route('events.register-audition', ['event' => $auditionEvent->slug]));
        $this->assertAuthenticatedAs($existingUser);

        auth()->logout();

        // 6. Inline login for team event redirects to team registration page
        $this->post(route('events.inline-login', ['event' => $teamEvent->slug]), [
            'email' => 'andi.terdaftar@persija.id',
            'password' => 'Rahasia123',
            'redirect_to' => 'register',
        ])->assertRedirect(route('events.register', ['event' => $teamEvent->slug]));
        $this->assertAuthenticatedAs($existingUser);
    }

    public function test_logged_in_parent_sees_existing_children_and_can_register_eligible_child_without_reuploading_documents_while_already_registered_child_is_marked(): void
    {
        Notification::fake();

        $oldEvent = Event::create([
            'name' => 'Audisi Seri Sebelumnya',
            'slug' => 'audisi-seri-sebelumnya',
            'venue_name' => 'Sawangan',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'completed',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $targetEvent = Event::create([
            'name' => 'Audisi Seri Terbaru 2026',
            'slug' => 'audisi-seri-terbaru-2026',
            'venue_name' => 'Bojongsari',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 0,
            'min_birth_date' => '2012-01-01',
            'max_birth_date' => '2013-12-31',
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
            'form_presets' => [
                'photo' => true,
                'position' => true,
                'foot_dominance' => false,
                'team_origin' => false,
                'body_measurements' => false,
                'experience' => false,
                'jersey_size' => false,
            ],
        ]);

        $parent = User::factory()->create([
            'name' => 'Siti Orang Tua',
            'email' => 'siti.parent@persija.id',
            'is_active' => true,
        ]);
        $parent->assignRole('participant_parent');

        $storedPhotoPath = 'auditions/photos/existing_child_photo.jpg';
        $storedAktaPath = 'auditions/documents/birth_cert/existing_akta.pdf';
        $storedKkPath = 'auditions/documents/family_card/existing_kk.pdf';
        Storage::disk('public')->put($storedPhotoPath, 'PHOTO');
        Storage::disk('private')->put($storedAktaPath, 'AKTA');
        Storage::disk('private')->put($storedKkPath, 'KK');

        // Child 1: Eligible & not yet in targetEvent
        $eligibleChild = AuditionParticipant::create([
            'event_id' => $oldEvent->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2025-0001',
            'full_name' => 'Rafi Anak Pertama',
            'nik' => '3171011122330001',
            'birth_date' => '2012-07-15',
            'gender' => 'male',
            'preferred_position' => 'CMF',
            'emergency_contact_name' => 'Ayah Rafi',
            'emergency_contact_phone' => '081234567890',
            'photo_path' => $storedPhotoPath,
            'birth_certificate_path' => $storedAktaPath,
            'family_card_path' => $storedKkPath,
            'screening_status' => 'passed',
            'status' => 'active',
        ]);

        // Child 2: Too old (out of min_birth_date)
        AuditionParticipant::create([
            'event_id' => $oldEvent->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2025-0002',
            'full_name' => 'Bima Anak Sulung',
            'nik' => '3171011122330002',
            'birth_date' => '2010-03-20',
            'gender' => 'male',
            'preferred_position' => 'CF',
            'emergency_contact_name' => 'Ayah Rafi',
            'emergency_contact_phone' => '081234567890',
            'screening_status' => 'passed',
            'status' => 'active',
        ]);

        // Child 3: Already registered in targetEvent
        AuditionParticipant::create([
            'event_id' => $targetEvent->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-0099',
            'full_name' => 'Danu Anak Ketiga',
            'nik' => '3171011122330003',
            'birth_date' => '2013-02-11',
            'gender' => 'male',
            'preferred_position' => 'GK',
            'emergency_contact_name' => 'Ayah Rafi',
            'emergency_contact_phone' => '081234567890',
            'screening_status' => 'submitted',
            'status' => 'active',
        ]);

        // 1. Check Inertia props on register-audition page
        $this->actingAs($parent)
            ->get(route('events.register-audition', ['event' => $targetEvent->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/RegisterAudition')
                ->has('existingChildren', 3)
                ->where('existingChildren.0.nik', '3171011122330003')
                ->where('existingChildren.0.already_in_this_event', true)
                ->where('existingChildren.1.nik', '3171011122330002')
                ->where('existingChildren.1.already_in_this_event', false)
                ->where('existingChildren.1.age_eligible', false)
                ->where('existingChildren.2.nik', '3171011122330001')
                ->where('existingChildren.2.already_in_this_event', false)
                ->where('existingChildren.2.age_eligible', true)
                ->where('existingChildren.2.has_photo', true)
                ->where('existingChildren.2.has_birth_certificate', true)
                ->where('existingChildren.2.has_family_card', true)
            );

        // 2. Register eligibleChild using source_participant_id without re-uploading files
        $registerResponse = $this->actingAs($parent)
            ->post(route('events.register-audition.store', ['event' => $targetEvent->slug]), [
                'source_participant_id' => $eligibleChild->id,
                'full_name' => $eligibleChild->full_name,
                'nik' => $eligibleChild->nik,
                'birth_date' => '2012-07-15',
                'gender' => 'male',
                'preferred_position' => 'CMF',
                'emergency_contact_name' => 'Ayah Rafi',
                'emergency_contact_phone' => '081234567890',
                'liability_waiver' => '1',
            ]);

        $registerResponse->assertRedirect(route('manager.dashboard'));

        $newRegistration = AuditionParticipant::query()
            ->where('event_id', $targetEvent->id)
            ->where('nik', '3171011122330001')
            ->firstOrFail();

        $this->assertSame($storedPhotoPath, $newRegistration->photo_path);
        $this->assertSame($storedAktaPath, $newRegistration->birth_certificate_path);
        $this->assertSame($storedKkPath, $newRegistration->family_card_path);

        // 3. Re-registering already registered child in same event is blocked
        $this->actingAs($parent)
            ->post(route('events.register-audition.store', ['event' => $targetEvent->slug]), [
                'source_participant_id' => $eligibleChild->id,
                'full_name' => $eligibleChild->full_name,
                'nik' => $eligibleChild->nik,
                'birth_date' => '2012-07-15',
                'gender' => 'male',
                'preferred_position' => 'CMF',
                'emergency_contact_name' => 'Ayah Rafi',
                'emergency_contact_phone' => '081234567890',
                'liability_waiver' => '1',
            ])
            ->assertSessionHasErrors('nik');
    }
}
