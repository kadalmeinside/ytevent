<?php

namespace Tests\Feature;

use App\Models\AuditionAttendance;
use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Payment;
use App\Models\ScoutingEvaluation;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsAndEventLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('private');
    }

    private function makeSuperadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function makeIndividualEvent(string $name = 'Audisi EPA U-14 2026'): Event
    {
        return Event::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString() . '-' . uniqid(),
            'venue_name' => 'Persija Training Ground',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 250000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);
    }

    public function test_admin_analytics_page_renders_funnels_trends_and_demographics(): void
    {
        $admin = $this->makeSuperadmin();

        $teamEvent = Event::create([
            'name' => 'Liga Muda U-12 2026',
            'slug' => 'liga-muda-u-12-2026',
            'venue_name' => 'Stadion Utama',
            'city' => 'Jakarta',
            'format' => 'group_knockout',
            'age_category' => 'U-12',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'quota_type' => 'fixed',
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'status' => 'open',
            'required_documents' => ['birth_certificate'],
        ]);

        $manager = User::factory()->create();
        $manager->assignRole('team_manager');
        $team = Team::create([
            'event_id' => $teamEvent->id,
            'user_id' => $manager->id,
            'name' => 'SSB Garuda Jaya',
            'slug' => 'ssb-garuda-jaya',
            'logo_path' => 'logos/garuda.png',
            'origin_association' => 'Askot Jakarta Selatan',
            'manager_name' => 'Budi',
            'manager_email' => $manager->email,
            'manager_whatsapp' => '081234567890',
            'head_coach_name' => 'Coach Budi',
            'terms_accepted_at' => now(),
            'status' => 'locked_roster',
            'screening_status' => 'locked_roster',
        ]);

        Payment::create([
            'event_id' => $teamEvent->id,
            'team_id' => $team->id,
            'invoice_number' => 'INV-TEAM-001',
            'amount' => 1500000,
            'gateway_fee' => 0,
            'total_amount' => 1500000,
            'method' => 'manual',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $auditionEvent = $this->makeIndividualEvent('Audisi Talenta Nasional');
        $stage = AuditionStage::create([
            'event_id' => $auditionEvent->id,
            'stage_number' => 1,
            'stage_name' => 'Hari 1 Seleksi Fisik',
            'stage_date' => now()->toDateString(),
            'venue_name' => 'Lapangan 1',
            'target_quota' => 100,
            'is_active' => true,
        ]);

        $parent = User::factory()->create();
        $parent->assignRole('participant_parent');
        $participant = AuditionParticipant::create([
            'event_id' => $auditionEvent->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-0001',
            'bib_number' => 12,
            'full_name' => 'Andi Pratama',
            'nik' => '3171012345670001',
            'birth_date' => '2012-06-15',
            'gender' => 'male',
            'preferred_position' => 'PENYERANG',
            'dominant_foot' => 'right',
            'current_club_ssb' => 'SSB Garuda Jaya',
            'emergency_contact_name' => 'Ayah Andi',
            'emergency_contact_phone' => '081299990000',
            'screening_status' => 'passed',
            'stage_status' => 'day_1_passed',
            'status' => 'active',
        ]);

        Payment::create([
            'event_id' => $auditionEvent->id,
            'audition_participant_id' => $participant->id,
            'invoice_number' => 'INV-AUD-001',
            'amount' => 250000,
            'gateway_fee' => 0,
            'total_amount' => 250000,
            'method' => 'midtrans',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        AuditionAttendance::create([
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage->id,
            'scanned_by' => $admin->id,
            'check_in_at' => now(),
            'status' => 'present',
        ]);

        ScoutingEvaluation::create([
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage->id,
            'scout_id' => $admin->id,
            'quick_grade' => 'A',
            'rating_stars' => 5,
            'tags' => ['#KecepatanTinggi'],
            'coach_notes' => 'Sangat tajam di kotak penalti.',
            'is_locked' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Index')
                ->where('kpi.total_revenue_paid', 1750000)
                ->where('teamFunnel.locked_roster', 1)
                ->where('individualFunnel.qualified', 1)
                ->where('demographics.scouting.grade_a', 1)
                ->has('dailyTrends', 14)
                ->has('eventComparison', 2)
            );

        $this->actingAs($admin)
            ->get(route('admin.analytics.index', ['event_id' => $auditionEvent->id, 'days' => 7]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Index')
                ->where('filters.event_id', $auditionEvent->id)
                ->where('filters.days', 7)
                ->where('kpi.total_revenue_paid', 250000)
                ->has('dailyTrends', 7)
            );
    }

    public function test_duplicate_event_clones_configuration_stages_and_sessions_as_draft(): void
    {
        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('Seleksi Akademi Utama');

        AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Tahap Awal',
            'stage_date' => '2026-08-01',
            'venue_name' => 'Lapangan A',
            'target_quota' => 100,
            'is_active' => true,
            'is_completed' => true,
            'instructions' => 'Datang 30 menit lebih awal',
        ]);

        EventSession::create([
            'event_id' => $event->id,
            'session_name' => 'Gelombang Pagi',
            'session_date' => '2026-08-01',
            'start_time' => '08:00',
            'end_time' => '11:00',
            'quota' => 50,
            'occupied_slots' => 15,
            'notes' => 'Bawa sepatu bola',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.events.duplicate', ['event' => $event->id]));

        $cloned = Event::query()->where('id', '!=', $event->id)->first();
        $this->assertNotNull($cloned);
        $response->assertRedirect(route('admin.events.edit', ['event' => $cloned->id]));

        $this->assertSame('Seleksi Akademi Utama (Salinan)', $cloned->name);
        $this->assertSame('draft', $cloned->status);
        $this->assertFalse((bool) $cloned->certificates_released);

        $clonedStage = $cloned->auditionStages()->first();
        $this->assertNotNull($clonedStage);
        $this->assertSame('Tahap Awal', $clonedStage->stage_name);
        $this->assertFalse((bool) $clonedStage->is_completed);

        $clonedSession = $cloned->eventSessions()->first();
        $this->assertNotNull($clonedSession);
        $this->assertSame('Gelombang Pagi', $clonedSession->session_name);
        $this->assertSame(0, (int) $clonedSession->occupied_slots);
    }

    public function test_destroy_event_soft_deletes_empty_draft_and_blocks_event_with_registrants(): void
    {
        $admin = $this->makeSuperadmin();
        $emptyEvent = $this->makeIndividualEvent('Draft Event Kosong');
        $emptyEvent->update(['status' => 'draft']);

        $this->actingAs($admin)
            ->delete(route('admin.events.destroy', ['event' => $emptyEvent->id]))
            ->assertRedirect(route('admin.events.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('events', ['id' => $emptyEvent->id]);

        $occupiedEvent = $this->makeIndividualEvent('Event Sudah Ada Peserta');
        $parent = User::factory()->create();
        AuditionParticipant::create([
            'event_id' => $occupiedEvent->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-2026-9999',
            'full_name' => 'Peserta Aktif',
            'nik' => '3171012345679999',
            'birth_date' => '2012-01-02',
            'gender' => 'male',
            'preferred_position' => 'GK',
            'dominant_foot' => 'right',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081200001111',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.events.destroy', ['event' => $occupiedEvent->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', [
            'id' => $occupiedEvent->id,
            'deleted_at' => null,
        ]);
    }

    public function test_export_participants_and_scouting_csv_stream_valid_utf8_csv(): void
    {
        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('Audisi Export CSV');
        $stage = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Hari 1 Lapangan',
            'stage_date' => now()->toDateString(),
            'venue_name' => 'Lapangan Utama',
            'is_active' => true,
        ]);

        $parent = User::factory()->create();
        $participant = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-CSV-001',
            'bib_number' => 45,
            'full_name' => 'Bagas Adi Nugroho',
            'nik' => '3171012345670045',
            'birth_date' => '2012-03-11',
            'gender' => 'male',
            'preferred_position' => 'BEK',
            'dominant_foot' => 'left',
            'current_club_ssb' => 'SSB Persija Muda',
            'emergency_contact_name' => 'Orang Tua',
            'emergency_contact_phone' => '081211112222',
            'screening_status' => 'passed',
            'stage_status' => 'day_1_passed',
            'status' => 'active',
        ]);

        ScoutingEvaluation::create([
            'audition_participant_id' => $participant->id,
            'audition_stage_id' => $stage->id,
            'scout_id' => $admin->id,
            'quick_grade' => 'A',
            'rating_stars' => 4,
            'tags' => ['#PassingAkurat', '#Leadership'],
            'coach_notes' => 'Disiplin menjaga lini belakang.',
            'is_locked' => true,
        ]);

        $participantsCsv = $this->actingAs($admin)
            ->get(route('admin.events.export-participants', ['event' => $event->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('AUD-CSV-001', $participantsCsv);
        $this->assertStringContainsString('Bagas Adi Nugroho', $participantsCsv);

        $scoutingCsv = $this->actingAs($admin)
            ->get(route('admin.events.export-scouting', ['event' => $event->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Quick Grade', $scoutingCsv);
        $this->assertStringContainsString('Bagas Adi Nugroho', $scoutingCsv);
        $this->assertStringContainsString('Disiplin menjaga lini belakang.', $scoutingCsv);
    }

    public function test_scout_portal_index_lists_individual_events_for_scout_and_superadmin(): void
    {
        $scout = User::factory()->create();
        $scout->assignRole('talent_scout');

        $individualEvent = $this->makeIndividualEvent('Audisi Terbuka 2026');
        Event::create([
            'name' => 'Turnamen Tim Internal',
            'slug' => 'turnamen-tim-internal',
            'venue_name' => 'Stadion ABC',
            'city' => 'Jakarta',
            'format' => 'cup',
            'age_category' => 'U-15',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'quota_type' => 'fixed',
            'max_teams' => 8,
            'registration_fee' => 1000000,
            'status' => 'open',
            'required_documents' => ['birth_certificate'],
        ]);

        $this->actingAs($scout)
            ->get(route('scout.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scout/Index')
                ->has('events', 1)
                ->where('events.0.id', $individualEvent->id)
            );
    }

    public function test_public_analytics_settings_inject_scripts_only_on_public_routes(): void
    {
        $admin = $this->makeSuperadmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.branding'), [
                'app_name' => 'Persija Development Portal',
                'ga4_measurement_id' => 'g-test123456',
                'plausible_domain' => 'kompetisi.persija.id',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'ga4_measurement_id',
            'value' => 'G-TEST123456',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'plausible_domain',
            'value' => 'kompetisi.persija.id',
        ]);

        $publicResponse = $this->get(route('home'))->assertOk();
        $publicHtml = $publicResponse->getContent();
        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-TEST123456', $publicHtml);
        $this->assertStringContainsString('data-domain="kompetisi.persija.id"', $publicHtml);

        $adminDashResponse = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $adminDashHtml = $adminDashResponse->getContent();
        $this->assertStringNotContainsString('googletagmanager.com', $adminDashHtml);
        $this->assertStringNotContainsString('plausible.io/js/script.js', $adminDashHtml);

        auth()->logout();
        $adminLoginResponse = $this->get(route('admin.login'))->assertOk();
        $adminHtml = $adminLoginResponse->getContent();
        $this->assertStringNotContainsString('googletagmanager.com', $adminHtml);
        $this->assertStringNotContainsString('plausible.io/js/script.js', $adminHtml);
    }

    public function test_scouting_panel_heals_legacy_stage_1_status_and_displays_stage_1_and_stage_2_participants_with_diagnostics(): void
    {
        $scout = User::factory()->create();
        $scout->assignRole('talent_scout');

        $parent = User::factory()->create();
        $parent->assignRole('participant_parent');

        $event = Event::create([
            'name' => 'Persija EPA Future Stars 2026 Test',
            'slug' => 'persija-epa-future-stars-2026',
            'venue_name' => 'Persija Training Ground',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-14',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 250000,
            'status' => 'ongoing',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $stage1 = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Tahap 1: Screening Fisik',
            'stage_date' => now()->toDateString(),
            'venue_name' => 'Lapangan A',
            'target_quota' => 100,
            'is_active' => true,
        ]);

        $stage2 = AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 2,
            'stage_name' => 'Tahap 2: Game Internal',
            'stage_date' => now()->addDay()->toDateString(),
            'venue_name' => 'Lapangan Utama',
            'target_quota' => 50,
            'is_active' => true,
        ]);

        // 1. Peserta dengan status legacy 'stage_1' yang sudah check-in di Tahap 1
        $legacyStage1 = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-ESP-0001',
            'bib_number' => 101,
            'full_name' => 'Rizky Legacy Stage One',
            'nik' => '3171012345671001',
            'birth_date' => '2012-02-02',
            'gender' => 'male',
            'preferred_position' => 'GELANDANG',
            'dominant_foot' => 'right',
            'emergency_contact_name' => 'Wali Rizky',
            'emergency_contact_phone' => '081200001001',
            'screening_status' => 'passed',
            'stage_status' => 'stage_1',
            'status' => 'active',
        ]);

        // 2. Peserta yang sudah dipromosikan ke 'day_1_passed' (harus tetap tampil di Tahap 1 & Tahap 2)
        $promoted = AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-ESP-0002',
            'bib_number' => 102,
            'full_name' => 'Rayhan Lolos Hari Satu',
            'nik' => '3171012345671002',
            'birth_date' => '2012-03-03',
            'gender' => 'male',
            'preferred_position' => 'PENYERANG',
            'dominant_foot' => 'left',
            'emergency_contact_name' => 'Wali Rayhan',
            'emergency_contact_phone' => '081200001002',
            'screening_status' => 'passed',
            'stage_status' => 'day_1_passed',
            'status' => 'active',
        ]);

        // 3. Peserta aktif & lolos screening namun belum check-in di gerbang
        AuditionParticipant::create([
            'event_id' => $event->id,
            'user_id' => $parent->id,
            'registration_number' => 'AUD-ESP-0003',
            'bib_number' => 103,
            'full_name' => 'Budi Belum Checkin',
            'nik' => '3171012345671003',
            'birth_date' => '2012-04-04',
            'gender' => 'male',
            'preferred_position' => 'BEK',
            'dominant_foot' => 'right',
            'emergency_contact_name' => 'Wali Budi',
            'emergency_contact_phone' => '081200001003',
            'screening_status' => 'passed',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);

        AuditionAttendance::create([
            'audition_participant_id' => $legacyStage1->id,
            'audition_stage_id' => $stage1->id,
            'scanned_by' => $scout->id,
            'check_in_at' => now(),
            'status' => 'present',
        ]);

        AuditionAttendance::create([
            'audition_participant_id' => $promoted->id,
            'audition_stage_id' => $stage1->id,
            'scanned_by' => $scout->id,
            'check_in_at' => now(),
            'status' => 'present',
        ]);

        // Tahap 1: auto-heal 'stage_1' -> 'registered' & tampilkan kedua peserta yang check-in
        $this->actingAs($scout)
            ->get(route('scout.events.show', ['event' => $event->id, 'stage_id' => $stage1->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scout/ScoutingPanel')
                ->has('participants', 2)
                ->where('participants.0.full_name', 'Rizky Legacy Stage One')
                ->where('participants.0.stage_status', 'registered')
                ->where('participants.1.full_name', 'Rayhan Lolos Hari Satu')
                ->where('stageDiagnostics.total_registered', 3)
                ->where('stageDiagnostics.active_paid', 3)
                ->where('stageDiagnostics.screening_passed', 3)
                ->where('stageDiagnostics.eligible_for_stage', 3)
                ->where('stageDiagnostics.checked_in_stage', 2)
            );

        $this->assertSame('registered', $legacyStage1->fresh()->stage_status);

        // Tahap 2: event demo Persija EPA otomatis melengkapi presensi Tahap 2 bagi peserta 'day_1_passed'
        $this->actingAs($scout)
            ->get(route('scout.events.show', ['event' => $event->id, 'stage_id' => $stage2->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Scout/ScoutingPanel')
                ->has('participants', 1)
                ->where('participants.0.full_name', 'Rayhan Lolos Hari Satu')
                ->where('stageDiagnostics.eligible_for_stage', 1)
                ->where('stageDiagnostics.checked_in_stage', 1)
            );

        // Nilai atlet eks-'stage_1' (yang sudah ternormalisasi) pada Tahap 1 harus berhasil
        $this->actingAs($scout)
            ->postJson(route('scout.events.grade', ['event' => $event->id]), [
                'audition_participant_id' => $legacyStage1->id,
                'audition_stage_id' => $stage1->id,
                'quick_grade' => 'B',
                'rating_stars' => 4,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
