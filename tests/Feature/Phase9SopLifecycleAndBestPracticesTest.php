<?php

namespace Tests\Feature;

use App\Mail\AuditionStageResultMail;
use App\Models\AuditionAttendance;
use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\ScoutingEvaluation;
use App\Models\Team;
use App\Models\TournamentMatch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class Phase9SopLifecycleAndBestPracticesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('private');
    }

    public function test_team_roster_mutation_and_individual_reupload_are_blocked_when_event_is_ongoing_or_completed(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole('team_manager');

        $event = Event::create([
            'name' => 'Turnamen SOP Test U-14',
            'slug' => 'turnamen-sop-test-u14',
            'format' => 'league',
            'age_category' => 'U-14',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'max_teams' => 8,
            'min_players_per_team' => 2,
            'max_players_per_team' => 18,
            'max_officials_per_team' => 4,
            'min_birth_date' => '2011-01-01',
            'max_birth_date' => '2013-12-31',
            'registration_fee' => 500000,
            'lock_roster_until_paid' => true,
            'reupload_grace_hours' => 24,
            'status' => 'ongoing',
            'venue_name' => 'Stadion Uji',
            'city' => 'Jakarta',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $team = Team::create([
            'event_id' => $event->id,
            'user_id' => $manager->id,
            'name' => 'SSB Garuda Jaya',
            'slug' => 'ssb-garuda-jaya',
            'logo_path' => 'logos/garuda.png',
            'origin_association' => 'Askot Jakarta Selatan',
            'manager_name' => $manager->name,
            'manager_email' => $manager->email,
            'manager_whatsapp' => '081234567890',
            'head_coach_name' => 'Coach Garuda',
            'terms_accepted_at' => now(),
            'status' => 'active',
        ]);

        $this->assertFalse($team->canManageRoster());

        $this->actingAs($manager)
            ->post(route('manager.players.store', ['team' => $team->id]), [
                'full_name' => 'Pemain Terlambat',
                'jersey_number' => 9,
                'position' => 'Penyerang',
                'dominant_foot' => 'right',
                'birth_place' => 'Jakarta',
                'birth_date' => '2012-05-05',
                'nik' => '3171010101120099',
                'photo' => UploadedFile::fake()->image('p.jpg'),
            ])
            ->assertForbidden();

        // Revisi dokumen peserta individu juga diblokir ketika event sudah ongoing
        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('participant_parent');

        $audEvent = Event::create([
            'name' => 'Audisi SOP Reupload Test',
            'slug' => 'audisi-sop-reupload-test',
            'format' => 'festival',
            'age_category' => 'U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'ongoing',
            'reupload_grace_hours' => 48,
            'venue_name' => 'Sawangan',
            'city' => 'Depok',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $participant = AuditionParticipant::create([
            'event_id' => $audEvent->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-SOP-0001',
            'full_name' => 'Budi Santoso',
            'nik' => '3276010101140001',
            'birth_date' => '2014-01-01',
            'gender' => 'male',
            'preferred_position' => 'GELANDANG',
            'emergency_contact_name' => 'Ayah Budi',
            'emergency_contact_phone' => '081211112222',
            'screening_status' => 'rejected',
            'screening_verified_at' => now()->subHour(),
            'status' => 'active',
        ]);

        $this->actingAs($parent)
            ->post(route('audition.documents.reupload', ['participant' => $participant->id]), [
                'birth_certificate' => UploadedFile::fake()->create('akta.pdf', 150, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_field_operations_checkin_scouting_qualify_and_dsp_require_closed_or_ongoing_event(): void
    {
        $admin = User::where('email', 'superadmin@persijadevelopment.id')->first();
        $scout = User::where('email', 'scout@persijadevelopment.id')->first();
        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole('team_manager');

        // 1. Individual event masih berstatus OPEN -> operasi lapangan ditolak (422)
        $audEvent = Event::create([
            'name' => 'Audisi Guard Lapangan',
            'slug' => 'audisi-guard-lapangan',
            'format' => 'festival',
            'age_category' => 'U-15',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'open',
            'venue_name' => 'Lapangan A',
            'city' => 'Jakarta',
            'required_documents' => ['birth_certificate'],
        ]);

        $stage = AuditionStage::create([
            'event_id' => $audEvent->id,
            'stage_number' => 1,
            'stage_name' => 'Hari 1',
            'stage_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $participant = AuditionParticipant::create([
            'event_id' => $audEvent->id,
            'user_id' => $admin->id,
            'registration_number' => 'AUD-GUARD-01',
            'full_name' => 'Andi Pratama',
            'nik' => '3171010101110001',
            'birth_date' => '2011-04-04',
            'gender' => 'male',
            'preferred_position' => 'BEK',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081200001111',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-GUARD-01',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.auditions.scanner.check-in'), [
                'code' => 'AUD-GUARD-01',
                'stage_id' => $stage->id,
            ])
            ->assertStatus(422)
            ->assertJson(['gatekeeper_status' => 'EVENT_UNAVAILABLE']);

        AuditionAttendance::create([
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage->id,
            'scanned_by' => $admin->id,
            'check_in_at' => now(),
            'status' => 'present',
        ]);

        $this->actingAs($scout)
            ->postJson(route('scout.events.grade', ['event' => $audEvent->id]), [
                'audition_participant_id' => $participant->id,
                'audition_stage_id' => $stage->id,
                'quick_grade' => 'A',
                'rating_stars' => 5,
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post(route('admin.events.auditions.advance', ['event' => $audEvent->id]), [
                'participant_ids' => [$participant->id],
                'target_stage_status' => 'final_selected',
            ])
            ->assertStatus(422);

        // 2. Event Turnamen berstatus DRAFT / OPEN / COMPLETED -> DSP & skor pertandingan ditolak
        $tourEvent = Event::create([
            'name' => 'Turnamen Guard Matchday',
            'slug' => 'turnamen-guard-matchday',
            'format' => 'league',
            'age_category' => 'U-12',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'max_teams' => 4,
            'starting_players_count' => 1,
            'status' => 'open',
            'venue_name' => 'Lapangan B',
            'city' => 'Jakarta',
            'required_documents' => ['birth_certificate'],
        ]);

        $teamA = Team::create([
            'event_id' => $tourEvent->id,
            'user_id' => $manager->id,
            'name' => 'Tim Alpha',
            'slug' => 'tim-alpha',
            'logo_path' => 'logos/alpha.png',
            'origin_association' => 'Askot A',
            'manager_name' => $manager->name,
            'manager_email' => $manager->email,
            'manager_whatsapp' => '081200001112',
            'head_coach_name' => 'Coach Alpha',
            'terms_accepted_at' => now(),
            'status' => 'locked_roster',
        ]);
        $teamB = Team::create([
            'event_id' => $tourEvent->id,
            'user_id' => $admin->id,
            'name' => 'Tim Beta',
            'slug' => 'tim-beta',
            'logo_path' => 'logos/beta.png',
            'origin_association' => 'Askot B',
            'manager_name' => $admin->name,
            'manager_email' => $admin->email,
            'manager_whatsapp' => '081200001113',
            'head_coach_name' => 'Coach Beta',
            'terms_accepted_at' => now(),
            'status' => 'locked_roster',
        ]);

        $player = Player::create([
            'team_id' => $teamA->id,
            'event_id' => $tourEvent->id,
            'full_name' => 'Pemain Alpha 1',
            'nik' => '3171010101140077',
            'birth_date' => '2014-01-01',
            'position' => 'Penyerang',
            'jersey_number' => 10,
            'screening_status' => 'passed',
        ]);

        $match = TournamentMatch::create([
            'event_id' => $tourEvent->id,
            'stage' => 'group',
            'match_number' => 1,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($manager)
            ->post(route('manager.matches.lineup.save', ['match' => $match->id]), [
                'starting_player_ids' => [$player->id],
            ])
            ->assertSessionHasErrors('lineup');

        $this->actingAs($admin)
            ->post(route('admin.matches.result', ['match' => $match->id]), [
                'status' => 'completed',
                'home_score' => 1,
                'away_score' => 0,
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_individual_screening_requires_payment_and_blocks_on_draft_or_completed_events(): void
    {
        $officer = User::where('email', 'screening@persijadevelopment.id')->first();
        $admin = User::where('email', 'superadmin@persijadevelopment.id')->first();

        $event = Event::create([
            'name' => 'Audisi Berbayar SOP',
            'slug' => 'audisi-berbayar-sop',
            'format' => 'festival',
            'age_category' => 'U-13',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'registration_fee' => 175000,
            'status' => 'open',
            'venue_name' => 'Stadion C',
            'city' => 'Bekasi',
            'required_documents' => ['birth_certificate'],
        ]);

        $participant = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'registration_number' => 'AUD-UNPAID-01',
            'full_name' => 'Dimas Belum Bayar',
            'nik' => '3275010101130088',
            'birth_date' => '2013-02-02',
            'gender' => 'male',
            'preferred_position' => 'KIPER',
            'emergency_contact_name' => 'Ayah Dimas',
            'emergency_contact_phone' => '081233339999',
            'screening_status' => 'submitted',
            'status' => 'pending_payment',
        ]);

        $payment = Payment::create([
            'event_id' => $event->id,
            'audition_participant_id' => $participant->id,
            'invoice_number' => 'INV-UNPAID-SOP-01',
            'amount' => 175000,
            'base_amount' => 175000,
            'total_amount' => 175000,
            'status' => 'unpaid',
            'method' => 'manual_transfer',
        ]);

        // Belum lunas -> ditolak lolos screening
        $this->actingAs($officer)
            ->post(route('admin.screening.auditions.verify', ['participant' => $participant->id]), [
                'status' => 'passed',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('submitted', $participant->fresh()->screening_status);

        // Setelah lunas -> berhasil lolos screening
        $payment->update(['status' => 'paid', 'paid_at' => now()]);
        $participant->update(['status' => 'active']);

        $this->actingAs($officer)
            ->post(route('admin.screening.auditions.verify', ['participant' => $participant->id]), [
                'status' => 'passed',
            ])
            ->assertSessionHas('success');

        $this->assertSame('passed', $participant->fresh()->screening_status);

        // Event Completed -> Emergency Unlock & Verify diblokir
        $event->update(['status' => 'completed']);

        $this->actingAs($admin)
            ->post(route('admin.screening.auditions.unlock', ['participant' => $participant->id]), [
                'reason' => 'Uji coba unlock saat event sudah selesai.',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_coaching_clinic_participant_can_download_certificate_upon_present_attendance_without_elimination_stage(): void
    {
        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('participant_parent');
        $admin = User::where('email', 'superadmin@persijadevelopment.id')->first();

        $clinic = Event::create([
            'name' => 'Coaching Clinic Kiper Muda 2026',
            'slug' => 'coaching-clinic-kiper-2026',
            'format' => 'coaching_clinic',
            'age_category' => 'U-12',
            'event_type' => 'coaching_clinic',
            'participant_type' => 'individual',
            'registration_fee' => 100000,
            'status' => 'completed',
            'certificates_released' => true,
            'venue_name' => 'PSF Pancoran',
            'city' => 'Jakarta',
            'required_documents' => ['birth_certificate'],
        ]);

        $stage = AuditionStage::create([
            'event_id' => $clinic->id,
            'stage_number' => 1,
            'stage_name' => 'Sesi Praktik & Teori',
            'stage_date' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $presentParticipant = AuditionParticipant::create([
            'event_id' => $clinic->id,
            'user_id' => $parent->id,
            'registration_number' => 'CC-2026-001',
            'full_name' => 'Raka Pratama',
            'nik' => '3171010101140101',
            'birth_date' => '2014-05-10',
            'gender' => 'male',
            'preferred_position' => 'KIPER',
            'emergency_contact_name' => 'Ibu Raka',
            'emergency_contact_phone' => '081211113333',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-CC-001',
            'stage_status' => 'registered', // Coaching clinic tetap registered (non-eliminasi)
            'status' => 'active',
        ]);

        Payment::create([
            'event_id' => $clinic->id,
            'user_id' => $parent->id,
            'audition_participant_id' => $presentParticipant->id,
            'invoice_number' => 'INV-CC-001',
            'amount' => 100000,
            'base_amount' => 100000,
            'total_amount' => 100000,
            'status' => 'paid',
            'paid_at' => now()->subDays(2),
        ]);

        AuditionAttendance::create([
            'audition_participant_id' => $presentParticipant->id,
            'audition_stage_id' => $stage->id,
            'scanned_by' => $admin->id,
            'check_in_at' => now()->subDay(),
            'status' => 'present',
        ]);

        // Hadir -> boleh download PDF sertifikat & verifikasi QR valid
        $this->assertTrue($presentParticipant->fresh()->canDownloadCertificate());

        $pdfResponse = $this->actingAs($parent)
            ->get(route('audition.certificate.download', ['participant' => $presentParticipant->id]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));

        $certCode = sprintf('CERT-AUD-%s-%03d-%04d', now()->format('Y'), $clinic->id, $presentParticipant->id);
        $this->get(route('public.certificate.verify', ['code' => $certCode]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isVerified', true)
                ->where('certificate.predicate', 'PARTISIPASI & PENGHARGAAN RESMI')
            );

        // Peserta yang tidak hadir -> tetap tidak boleh download
        $absentParticipant = AuditionParticipant::create([
            'event_id' => $clinic->id,
            'user_id' => $parent->id,
            'registration_number' => 'CC-2026-002',
            'full_name' => 'Doni Absen',
            'nik' => '3171010101140102',
            'birth_date' => '2014-05-12',
            'gender' => 'male',
            'preferred_position' => 'BEK',
            'emergency_contact_name' => 'Ayah Doni',
            'emergency_contact_phone' => '081211114444',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-CC-002',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);
        Payment::create([
            'event_id' => $clinic->id,
            'user_id' => $parent->id,
            'audition_participant_id' => $absentParticipant->id,
            'invoice_number' => 'INV-CC-002',
            'amount' => 100000,
            'base_amount' => 100000,
            'total_amount' => 100000,
            'status' => 'paid',
            'paid_at' => now()->subDays(2),
        ]);

        $this->assertFalse($absentParticipant->fresh()->canDownloadCertificate());
        $this->actingAs($parent)
            ->get(route('audition.certificate.download', ['participant' => $absentParticipant->id]))
            ->assertForbidden();
    }

    public function test_dynamic_stage_qualification_supports_single_stage_and_rolls_back_atomically_on_partial_failure(): void
    {
        Mail::fake();
        $admin = User::where('email', 'superadmin@persijadevelopment.id')->first();
        $scout = User::where('email', 'scout@persijadevelopment.id')->first();
        $parent = User::factory()->create(['is_active' => true]);
        $parent->assignRole('participant_parent');

        // Event audisi 1 hari / 1 tahap saja
        $event = Event::create([
            'name' => 'One-Day Audition 2026',
            'slug' => 'one-day-audition-2026',
            'format' => 'audition',
            'age_category' => 'U-15',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'ongoing',
            'venue_name' => 'Lapangan D',
            'city' => 'Jakarta',
            'required_documents' => ['birth_certificate'],
        ]);

        $stage = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Seleksi Utama (1 Hari)',
            'stage_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $p1 = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-1DAY-001',
            'full_name' => 'Peserta Valid',
            'nik' => '3171010101110201',
            'birth_date' => '2011-01-01',
            'gender' => 'male',
            'preferred_position' => 'PENYERANG',
            'emergency_contact_name' => 'Ayah 1',
            'emergency_contact_phone' => '081200002201',
            'screening_status' => 'passed',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);
        $p2 = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-1DAY-002',
            'full_name' => 'Peserta Belum Nilai',
            'nik' => '3171010101110202',
            'birth_date' => '2011-02-02',
            'gender' => 'male',
            'preferred_position' => 'GELANDANG',
            'emergency_contact_name' => 'Ayah 2',
            'emergency_contact_phone' => '081200002202',
            'screening_status' => 'passed',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);

        AuditionAttendance::create([
            'audition_participant_id' => $p1->id,
            'audition_stage_id' => $stage->id,
            'scanned_by' => $admin->id,
            'check_in_at' => now(),
            'status' => 'present',
        ]);
        ScoutingEvaluation::create([
            'audition_participant_id' => $p1->id,
            'audition_stage_id' => $stage->id,
            'scout_id' => $scout->id,
            'quick_grade' => 'A',
            'rating_stars' => 5,
        ]);

        // 1. Batch [p1, p2] gagal karena p2 belum hadir/dievaluasi -> p1 HARUS ikut rollback (atomic DB::transaction)
        $this->actingAs($admin)
            ->post(route('admin.events.auditions.advance', ['event' => $event->id]), [
                'participant_ids' => [$p1->id, $p2->id],
                'target_stage_status' => 'final_selected',
                'dispatch_email' => true,
            ])
            ->assertStatus(422);

        $this->assertSame('registered', $p1->fresh()->stage_status);
        Mail::assertNothingSent();

        // 2. Promosikan p1 saja langsung ke final_selected pada event 1-tahap -> SUKSES
        $this->actingAs($admin)
            ->post(route('admin.events.auditions.advance', ['event' => $event->id]), [
                'participant_ids' => [$p1->id],
                'target_stage_status' => 'final_selected',
                'dispatch_email' => true,
            ])
            ->assertRedirect();

        $this->assertSame('final_selected', $p1->fresh()->stage_status);
        Mail::assertSent(AuditionStageResultMail::class, 1);
    }

    public function test_scouting_session_lock_is_isolated_per_scout_and_requires_active_stage(): void
    {
        $scoutA = User::where('email', 'scout@persijadevelopment.id')->first();
        $scoutB = User::factory()->create(['is_active' => true]);
        $scoutB->assignRole('talent_scout');

        $event = Event::create([
            'name' => 'Audisi Multi Scout Isolation',
            'slug' => 'audisi-multi-scout-isolation',
            'format' => 'audition',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'ongoing',
            'venue_name' => 'Lapangan E',
            'city' => 'Jakarta',
            'required_documents' => ['birth_certificate'],
        ]);

        $stage = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Hari 1',
            'stage_date' => now()->toDateString(),
            'is_active' => true,
            'is_completed' => false,
        ]);

        $participant = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $scoutA->id,
            'registration_number' => 'AUD-SCOUT-001',
            'full_name' => 'Tomi Wijaya',
            'nik' => '3171010101120301',
            'birth_date' => '2012-06-01',
            'gender' => 'male',
            'preferred_position' => 'PENYERANG',
            'emergency_contact_name' => 'Wali Tomi',
            'emergency_contact_phone' => '081200003301',
            'screening_status' => 'passed',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);

        AuditionAttendance::create([
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage->id,
            'scanned_by' => $scoutA->id,
            'check_in_at' => now(),
            'status' => 'present',
        ]);

        // Scout B belum menilai siapapun -> lockSession harus ditolak (mencegah 0-row no-op)
        $this->actingAs($scoutB)
            ->postJson(route('scout.events.lock-session', ['event' => $event->id]), [
                'audition_stage_id' => $stage->id,
            ])
            ->assertStatus(422);

        // Scout A menilai lalu mengunci sesinya
        $this->actingAs($scoutA)
            ->postJson(route('scout.events.grade', ['event' => $event->id]), [
                'audition_participant_id' => $participant->id,
                'audition_stage_id' => $stage->id,
                'quick_grade' => 'A',
                'rating_stars' => 5,
            ])
            ->assertOk();

        $this->actingAs($scoutA)
            ->postJson(route('scout.events.lock-session', ['event' => $event->id]), [
                'audition_stage_id' => $stage->id,
            ])
            ->assertOk();

        // Scout A terkunci (403)
        $this->actingAs($scoutA)
            ->postJson(route('scout.events.grade', ['event' => $event->id]), [
                'audition_participant_id' => $participant->id,
                'audition_stage_id' => $stage->id,
                'quick_grade' => 'C',
                'rating_stars' => 2,
            ])
            ->assertStatus(403);

        // Scout B TIDAK ikut terkunci oleh Scout A
        $this->actingAs($scoutB)
            ->postJson(route('scout.events.grade', ['event' => $event->id]), [
                'audition_participant_id' => $participant->id,
                'audition_stage_id' => $stage->id,
                'quick_grade' => 'B',
                'rating_stars' => 4,
            ])
            ->assertOk();

        // Jika stage ditandai selesai (is_completed = true), Scout B juga tidak bisa mengubah nilai
        $stage->update(['is_completed' => true]);

        $this->actingAs($scoutB)
            ->postJson(route('scout.events.grade', ['event' => $event->id]), [
                'audition_participant_id' => $participant->id,
                'audition_stage_id' => $stage->id,
                'quick_grade' => 'A',
                'rating_stars' => 5,
            ])
            ->assertStatus(422);
    }

    public function test_gatekeeper_payload_does_not_crash_when_public_photo_file_is_missing_on_disk(): void
    {
        $officer = User::where('email', 'screening@persijadevelopment.id')->first();

        $event = Event::create([
            'name' => 'Audisi Missing Photo Regression',
            'slug' => 'audisi-missing-photo-regression',
            'format' => 'audition',
            'age_category' => 'U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'ongoing',
            'venue_name' => 'Lapangan F',
            'city' => 'Jakarta',
            'required_documents' => ['birth_certificate'],
        ]);

        $stage = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Hari 1',
            'stage_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $officer->id,
            'registration_number' => 'AUD-PHOTO-MISS',
            'full_name' => 'Bagas Foto Hilang',
            'nik' => '3171010101140401',
            'birth_date' => '2014-07-07',
            'gender' => 'male',
            'preferred_position' => 'KIPER',
            'emergency_contact_name' => 'Ayah Bagas',
            'emergency_contact_phone' => '081200004401',
            'photo_path' => 'auditions/photos/non-existent-file.jpg',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-PHOTO-MISS',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);

        $response = $this->actingAs($officer)
            ->postJson(route('admin.auditions.scanner.check-in'), [
                'code' => 'AUD-PHOTO-MISS',
                'stage_id' => $stage->id,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'gatekeeper_status' => 'CHECK_IN_SUCCESS',
            'participant' => [
                'photo_url' => route('public.audition.verify.photo', ['code' => 'SCR-PHOTO-MISS']),
            ],
        ]);
    }

    public function test_zero_inline_validate_calls_exist_in_http_controllers(): void
    {
        $controllerRoot = app_path('Http/Controllers');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllerRoot));
        $violations = [];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if ($contents !== false && str_contains($contents, '->validate(')) {
                $violations[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $violations, 'Ditemukan pemanggilan inline ->validate() di Controller yang melanggar GEMINI-2.md Poin 2.3: ' . implode(', ', $violations));
    }
}
