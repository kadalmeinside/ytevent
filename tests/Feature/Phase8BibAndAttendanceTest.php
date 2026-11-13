<?php

namespace Tests\Feature;

use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Payment;
use App\Models\User;
use App\Services\AuditionNoShowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase8BibAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('private');
    }

    private function createAuditionParticipant(array $attributes = []): array
    {
        $event = Event::create([
            'name' => 'Persija Talent Audition U-14 2026',
            'slug' => 'persija-talent-audition-u-14-2026',
            'venue_name' => 'Lapangan PSF Pancoran',
            'city' => 'Jakarta Selatan',
            'format' => 'festival',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 80,
            'registration_fee' => 150000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $stage1 = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Fisik & Teknik Dasar (Hari 1)',
            'stage_date' => now()->toDateString(),
            'target_quota' => 80,
            'is_active' => true,
        ]);

        $stage2 = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 2,
            'stage_name' => 'Game Situasional (Hari 2)',
            'stage_date' => now()->addDay()->toDateString(),
            'target_quota' => 40,
            'is_active' => true,
        ]);

        $parent = User::create([
            'name' => 'Orang Tua Rizky',
            'email' => 'rizky.parent@persijadevelopment.id',
            'password' => bcrypt('Secret123!'),
            'phone' => '081299998888',
            'is_active' => true,
        ]);
        $parent->assignRole('participant_parent');

        $participant = AuditionParticipant::create(array_merge([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-001-0042',
            'bib_number' => 42,
            'full_name' => 'Rizky Ramadhan',
            'nickname' => 'Rizky',
            'nik' => '3171012345678902',
            'birth_date' => '2012-04-10',
            'gender' => 'male',
            'preferred_position' => 'GELANDANG',
            'dominant_foot' => 'right',
            'current_club_ssb' => 'SSB Menteng',
            'emergency_contact_name' => 'Bambang Pamungkas',
            'emergency_contact_phone' => '081234567890',
            'emergency_contact_relation' => 'Ayah Kandung',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-AUD-2026-0042',
            'screening_verified_at' => now(),
            'stage_status' => 'registered',
            'status' => 'active',
            'liability_accepted_at' => now(),
        ], $attributes));

        $payment = Payment::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'team_id' => null,
            'audition_participant_id' => $participant->id,
            'invoice_number' => 'INV-AUD-2026-0042',
            'amount' => 150000,
            'base_amount' => 150000,
            'total_amount' => 150000,
            'status' => 'paid',
            'method' => 'midtrans_qris',
            'paid_at' => now(),
        ]);

        return [$event, $stage1, $stage2, $parent, $participant, $payment];
    }

    public function test_participant_can_download_bib_tag_pdf_when_paid_and_screening_passed(): void
    {
        /** @var AuditionParticipant $participant */
        [$event, $stage1, $stage2, $parent, $participant, $payment] = $this->createAuditionParticipant();

        // 1. Owner parent can download
        $response = $this->actingAs($parent)->get(route('audition.bib.download', ['participant' => $participant->id]));
        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));

        // 2. Unrelated user is forbidden
        $stranger = User::create([
            'name' => 'Orang Asing',
            'email' => 'stranger@persijadevelopment.id',
            'password' => bcrypt('Secret123!'),
            'is_active' => true,
        ]);
        $stranger->assignRole('participant_parent');

        $this->actingAs($stranger)
            ->get(route('audition.bib.download', ['participant' => $participant->id]))
            ->assertForbidden();

        // 3. Superadmin can download
        $superadmin = User::where('email', 'superadmin@persijadevelopment.id')->first();
        $this->actingAs($superadmin)
            ->get(route('audition.bib.download', ['participant' => $participant->id]))
            ->assertOk();
    }

    public function test_bib_download_is_blocked_if_screening_is_not_passed_or_unpaid(): void
    {
        [$event, $stage1, $stage2, $parent, $participant, $payment] = $this->createAuditionParticipant([
            'screening_status' => 'draft',
            'bib_number' => null,
        ]);

        $this->actingAs($parent)
            ->get(route('audition.bib.download', ['participant' => $participant->id]))
            ->assertForbidden();
    }

    public function test_public_can_verify_audition_participant_via_qr_code(): void
    {
        [$event, $stage1, $stage2, $parent, $participant, $payment] = $this->createAuditionParticipant();

        $response = $this->get(route('public.audition.verify', ['code' => $participant->screening_code]));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/AuditionVerification')
            ->where('isVerified', true)
            ->where('participant.bib_number', 42)
            ->where('participant.full_name', 'Rizky Ramadhan')
        );

        // Non-existent code returns unverified
        $responseInvalid = $this->get(route('public.audition.verify', ['code' => 'INVALID-CODE-XYZ']));
        $responseInvalid->assertOk();
        $responseInvalid->assertInertia(fn ($page) => $page
            ->component('Public/AuditionVerification')
            ->where('isVerified', false)
        );
    }

    public function test_gatekeeper_attendance_scanner_checkin_and_rejection_guards(): void
    {
        [$event, $stage1, $stage2, $parent, $participant, $payment] = $this->createAuditionParticipant();
        $event->update(['status' => 'ongoing']);
        $stage2->update(['stage_date' => now()->toDateString()]);

        $screeningOfficer = User::where('email', 'screening@persijadevelopment.id')->first();

        // 1. Success check-in on Stage 1 (Hari 1)
        $response = $this->actingAs($screeningOfficer)->postJson(route('admin.auditions.scanner.check-in'), [
            'code' => '42', // lookup via Bib Number
            'stage_id' => $stage1->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'already_checked_in' => false,
            'gatekeeper_status' => 'CHECK_IN_SUCCESS',
        ]);

        $this->assertDatabaseHas('audition_attendances', [
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage1->id,
            'status' => 'present',
        ]);

        // 2. Second scan on same stage returns already_checked_in
        $responseDup = $this->actingAs($screeningOfficer)->postJson(route('admin.auditions.scanner.check-in'), [
            'code' => $participant->screening_code, // lookup via screening_code
            'stage_id' => $stage1->id,
        ]);

        $responseDup->assertOk();
        $responseDup->assertJson([
            'success' => true,
            'already_checked_in' => true,
            'gatekeeper_status' => 'ALREADY_PRESENT',
        ]);

        // 3. Stage 2 (Hari 2) Guard: if participant is day_1_eliminated, entry must be REJECTED
        $participant->update(['stage_status' => 'day_1_eliminated']);

        $responseStage2 = $this->actingAs($screeningOfficer)->postJson(route('admin.auditions.scanner.check-in'), [
            'code' => '42',
            'stage_id' => $stage2->id,
        ]);

        $responseStage2->assertStatus(422);
        $responseStage2->assertJson([
            'success' => false,
            'gatekeeper_status' => 'ELIMINATED',
        ]);

        // 4. If participant is promoted to day_1_passed, entry to Stage 2 must SUCCEED
        $participant->update(['stage_status' => 'day_1_passed']);

        $responseStage2Pass = $this->actingAs($screeningOfficer)->postJson(route('admin.auditions.scanner.check-in'), [
            'code' => '42',
            'stage_id' => $stage2->id,
        ]);

        $responseStage2Pass->assertOk();
        $responseStage2Pass->assertJson([
            'success' => true,
            'gatekeeper_status' => 'CHECK_IN_SUCCESS',
        ]);
    }

    public function test_ended_stage_marks_eligible_absent_participants_without_overwriting_present(): void
    {
        [$event, $stage1, $stage2, $parent, $participant] = $this->createAuditionParticipant();
        $stage1->update([
            'stage_date' => now()->subDay()->toDateString(),
            'end_time' => '12:00:00',
        ]);

        $marked = app(AuditionNoShowService::class)->markEndedStages();

        $this->assertSame(1, $marked);
        $this->assertDatabaseHas('audition_attendances', [
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage1->id,
            'status' => 'no_show',
        ]);

        $markedAgain = app(AuditionNoShowService::class)->markEndedStages();
        $this->assertSame(0, $markedAgain);
    }
}
