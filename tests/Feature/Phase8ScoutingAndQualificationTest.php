<?php

namespace Tests\Feature;

use App\Mail\AuditionStageResultMail;
use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use App\Models\Payment;
use App\Models\ScoutingEvaluation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase8ScoutingAndQualificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('private');
    }

    private function createAuditionSetup(): array
    {
        $event = Event::create([
            'name' => 'Audisi Elit Persija U-16 2026',
            'slug' => 'audisi-elit-persija-u-16-2026',
            'venue_name' => 'Nirwana Sawangan',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-16',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 60,
            'registration_fee' => 200000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $stage1 = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Uji Fisik & Speed (Hari 1)',
            'stage_date' => now()->toDateString(),
            'target_quota' => 60,
            'is_active' => true,
        ]);

        $stage2 = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 2,
            'stage_name' => 'Game Situasional 11v11 (Hari 2)',
            'stage_date' => now()->addDay()->toDateString(),
            'target_quota' => 30,
            'is_active' => true,
        ]);

        $parent = User::create([
            'name' => 'Orang Tua Fajar',
            'email' => 'fajar.parent@persijadevelopment.id',
            'password' => bcrypt('Secret123!'),
            'phone' => '081233334444',
            'is_active' => true,
        ]);
        $parent->assignRole('participant_parent');

        $participant = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-002-0007',
            'bib_number' => 7,
            'full_name' => 'Fajar Alamsyah',
            'nickname' => 'Fajar',
            'nik' => '3171012345678907',
            'birth_date' => '2010-05-20',
            'gender' => 'male',
            'preferred_position' => 'PENYERANG',
            'dominant_foot' => 'right',
            'current_club_ssb' => 'Akademi Ragunan',
            'emergency_contact_name' => 'Hendra Alamsyah',
            'emergency_contact_phone' => '081233334444',
            'emergency_contact_relation' => 'Ayah Kandung',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-AUD-2026-0007',
            'screening_verified_at' => now(),
            'stage_status' => 'registered',
            'status' => 'active',
            'liability_accepted_at' => now(),
        ]);

        Payment::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'team_id' => null,
            'audition_participant_id' => $participant->id,
            'invoice_number' => 'INV-AUD-2026-0007',
            'amount' => 200000,
            'base_amount' => 200000,
            'total_amount' => 200000,
            'status' => 'paid',
            'method' => 'midtrans_qris',
            'paid_at' => now(),
        ]);

        $scout = User::where('email', 'scout@persijadevelopment.id')->first();

        return [$event, $stage1, $stage2, $parent, $participant, $scout];
    }

    public function test_talent_scout_can_access_panel_and_grade_athlete(): void
    {
        /** @var User $scout */
        [$event, $stage1, $stage2, $parent, $participant, $scout] = $this->createAuditionSetup();

        // 1. Scout accesses mobile scouting web panel
        $response = $this->actingAs($scout)->get(route('scout.events.show', [
            'event' => $event->id,
            'stage_id' => $stage1->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Scout/ScoutingPanel')
            ->where('event.id', $event->id)
            ->has('participants', 1)
        );

        // 2. Scout grades athlete with A, 5 stars, tags, and notes
        $gradeResponse = $this->actingAs($scout)->postJson(route('scout.events.grade', ['event' => $event->id]), [
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage1->id,
            'quick_grade' => 'A',
            'rating_stars' => 5,
            'tags' => ['#KecepatanTinggi', '#Skill1on1'],
            'coach_notes' => 'Akselerasi luar biasa di flank kanan, finishing tenang.',
        ]);

        $gradeResponse->assertOk();
        $gradeResponse->assertJson([
            'success' => true,
            'evaluation' => [
                'quick_grade' => 'A',
                'rating_stars' => 5,
            ],
        ]);

        $this->assertDatabaseHas('scouting_evaluations', [
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage1->id,
            'scout_id' => $scout->id,
            'quick_grade' => 'A',
            'rating_stars' => 5,
        ]);

        // 3. Scout locks session
        $lockResponse = $this->actingAs($scout)->postJson(route('scout.events.lock-session', ['event' => $event->id]), [
            'audition_stage_id' => $stage1->id,
        ]);
        $lockResponse->assertOk();

        // 4. Edits after locking are forbidden
        $forbiddenEdit = $this->actingAs($scout)->postJson(route('scout.events.grade', ['event' => $event->id]), [
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage1->id,
            'quick_grade' => 'B',
            'rating_stars' => 3,
        ]);
        $forbiddenEdit->assertStatus(403);
    }

    public function test_admin_can_advance_participants_and_trigger_emails(): void
    {
        Mail::fake();

        [$event, $stage1, $stage2, $parent, $participant, $scout] = $this->createAuditionSetup();

        // Scout previously graded
        ScoutingEvaluation::create([
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage1->id,
            'scout_id' => $scout->id,
            'quick_grade' => 'A',
            'rating_stars' => 5,
            'tags' => ['#KecepatanTinggi'],
            'coach_notes' => 'Potensi tinggi.',
        ]);

        $superadmin = User::where('email', 'superadmin@persijadevelopment.id')->first();

        // 1. Admin views qualifications panel
        $response = $this->actingAs($superadmin)->get(route('admin.events.auditions.qualifications', ['event' => $event->id]));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Auditions/Qualifications')
            ->where('event.id', $event->id)
            ->where('participants.0.consensus.grade_a_count', 1)
        );

        // 2. Admin advances participant to day_1_passed
        $advanceResponse = $this->actingAs($superadmin)->post(route('admin.events.auditions.advance', ['event' => $event->id]), [
            'participant_ids' => [$participant->id],
            'target_stage_status' => 'day_1_passed',
            'dispatch_email' => true,
            'notes' => 'Hadir besok pukul 08:00 WIB untuk game 11v11.',
        ]);

        $advanceResponse->assertRedirect();
        $this->assertEquals('day_1_passed', $participant->fresh()->stage_status);

        // 3. Mail sent
        Mail::assertSent(AuditionStageResultMail::class, function ($mail) use ($parent) {
            return $mail->hasTo($parent->email) && $mail->resultStatus === 'passed';
        });
    }

    public function test_participant_can_download_audition_certificate_pdf_and_public_verify(): void
    {
        [$event, $stage1, $stage2, $parent, $participant, $scout] = $this->createAuditionSetup();
        $participant->update(['stage_status' => 'final_selected']);

        // 1. Parent downloads certificate
        $pdfResponse = $this->actingAs($parent)->get(route('audition.certificate.download', ['participant' => $participant->id]));
        $pdfResponse->assertOk();
        $this->assertEquals('application/pdf', $pdfResponse->headers->get('content-type'));

        // 2. Public verification of certificate
        $year = now()->format('Y');
        $certCode = sprintf('CERT-AUD-%s-%03d-%04d', $year, $event->id, $participant->id);

        $verifyResponse = $this->get(route('public.certificate.verify', ['code' => $certCode]));
        $verifyResponse->assertOk();
        $verifyResponse->assertInertia(fn ($page) => $page
            ->component('Public/CertificateVerification')
            ->where('isVerified', true)
            ->where('certificate.recipient_name', 'Fajar Alamsyah')
            ->where('certificate.predicate', 'ATLET TERPILIH - SKUAD UTAMA / AKADEMI')
        );

        // 3. Invalid certificate code returns unverified
        $invalidResponse = $this->get(route('public.certificate.verify', ['code' => 'CERT-AUD-2026-999-9999']));
        $invalidResponse->assertOk();
        $invalidResponse->assertInertia(fn ($page) => $page
            ->component('Public/CertificateVerification')
            ->where('isVerified', false)
        );
    }
}

