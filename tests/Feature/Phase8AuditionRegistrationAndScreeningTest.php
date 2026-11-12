<?php

namespace Tests\Feature;

use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $response->assertRedirect(route('manager.dashboard'));
        $this->assertAuthenticated();

        // Verifikasi User dibuat dengan role participant_parent
        $user = User::where('email', 'bambang.parent@persijadevelopment.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('participant_parent'));

        // Verifikasi AuditionParticipant tersimpan
        $participant = AuditionParticipant::where('nik', '3171012345678901')->first();
        $this->assertNotNull($participant);
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
        $this->assertSame(1, $participant->bib_number); // Nomor dada otomatis terbit mulai dari #1
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
}

