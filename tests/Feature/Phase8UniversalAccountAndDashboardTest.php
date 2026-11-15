<?php

namespace Tests\Feature;

use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase8UniversalAccountAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_public_header_shows_masuk_button_for_guest(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Home')
        );
    }

    public function test_login_page_renders_with_universal_account_context(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/ManagerLogin')
        );
    }

    public function test_user_with_participant_parent_role_can_access_dashboard(): void
    {
        $parent = User::factory()->create([
            'is_active' => true,
        ]);
        $parent->assignRole('participant_parent');

        $response = $this->actingAs($parent)->get(route('manager.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manager/Dashboard')
            ->has('auditionParticipants')
        );
    }

    public function test_unified_dashboard_displays_both_team_and_audition_participant(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $user->assignRole('team_manager');

        // Create Tournament Event & Team
        $tournament = Event::create([
            'name' => 'Persija Youth Cup 2026',
            'slug' => 'persija-youth-cup-2026',
            'venue_name' => 'Stadion Patriot Candrabhaga',
            'city' => 'Bekasi',
            'format' => 'cup',
            'age_category' => 'U-12',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'status' => 'open',
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'lock_roster_until_paid' => true,
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);
        $team = Team::create([
            'event_id' => $tournament->id,
            'user_id' => $user->id,
            'name' => 'SSB Macan Muda',
            'slug' => 'ssb-macan-muda',
            'logo_path' => 'teams/logos/macan.png',
            'origin_association' => 'PSSI Askot Jakarta Selatan',
            'manager_name' => 'Bambang Pamungkas',
            'manager_email' => $user->email,
            'manager_whatsapp' => '081234567890',
            'head_coach_name' => 'Sudirman',
            'terms_accepted_at' => now(),
            'status' => 'active',
        ]);

        // Create Audition Event & Participant
        $audition = Event::create([
            'name' => 'Audisi Akademi Persija 2026',
            'slug' => 'audisi-akademi-persija-2026',
            'venue_name' => 'Persija Training Ground Bojongsari',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'open',
            'max_participants' => 100,
            'registration_fee' => 150000,
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);
        $participant = AuditionParticipant::create([
            'event_id' => $audition->id,
            'user_id' => $user->id,
            'registration_number' => 'AUD-2026-0001',
            'bib_number' => 10,
            'full_name' => 'Bambang Junior',
            'nickname' => 'Bambang',
            'nik' => '3171012345678901',
            'birth_date' => '2014-05-10',
            'gender' => 'male',
            'preferred_position' => 'CF',
            'dominant_foot' => 'right',
            'height_cm' => 148,
            'weight_kg' => 40,
            'current_club_ssb' => 'SSB Menteng',
            'emergency_contact_name' => 'Bambang Pamungkas',
            'emergency_contact_phone' => '081234567890',
            'screening_status' => 'passed',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);

        // Payment for Audition
        Payment::create([
            'invoice_number' => 'INV-2026-AUD001',
            'audition_participant_id' => $participant->id,
            'event_id' => $audition->id,
            'amount' => 150000,
            'gateway_fee' => 0,
            'total_amount' => 150000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('manager.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manager/Dashboard')
            ->has('team')
            ->where('team.name', 'SSB Macan Muda')
            ->has('auditionParticipants', 1)
            ->where('auditionParticipants.0.full_name', 'Bambang Junior')
            ->where('auditionParticipants.0.bib_number', 10)
            ->where('auditionParticipants.0.is_paid', true)
            ->where('auditionParticipants.0.can_download_bib', true)
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('manager.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_prioritizes_active_events_over_completed_events_for_multi_registration_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('team_manager');
        $user->assignRole('participant_parent');

        $activeTournament = Event::create([
            'name' => 'Active Cup 2026',
            'slug' => 'active-cup-2026',
            'venue_name' => 'Stadion Madya',
            'city' => 'Jakarta',
            'format' => 'cup',
            'age_category' => 'U-12',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'status' => 'open',
            'max_teams' => 16,
            'registration_fee' => 1000000,
            'required_documents' => ['birth_certificate'],
        ]);

        $pastTournament = Event::create([
            'name' => 'Past Cup 2025',
            'slug' => 'past-cup-2025',
            'venue_name' => 'Lapangan C',
            'city' => 'Jakarta',
            'format' => 'cup',
            'age_category' => 'U-11',
            'event_type' => 'tournament',
            'participant_type' => 'team',
            'status' => 'completed',
            'max_teams' => 16,
            'registration_fee' => 1000000,
            'required_documents' => ['birth_certificate'],
        ]);

        $activeTeam = Team::create([
            'event_id' => $activeTournament->id,
            'user_id' => $user->id,
            'name' => 'Tim Aktif 2026',
            'slug' => 'tim-aktif-2026',
            'logo_path' => 'teams/logos/aktif.png',
            'origin_association' => 'PSSI Jakarta',
            'manager_name' => $user->name,
            'manager_whatsapp' => '081234567890',
            'manager_email' => $user->email,
            'head_coach_name' => 'Coach Aktif',
            'terms_accepted_at' => now(),
            'status' => 'active',
        ]);

        // Created AFTER activeTeam to verify completed event does not hijack default selection
        Team::create([
            'event_id' => $pastTournament->id,
            'user_id' => $user->id,
            'name' => 'Tim Selesai 2025',
            'slug' => 'tim-selesai-2025',
            'logo_path' => 'teams/logos/selesai.png',
            'origin_association' => 'PSSI Jakarta',
            'manager_name' => $user->name,
            'manager_whatsapp' => '081234567890',
            'manager_email' => $user->email,
            'head_coach_name' => 'Coach Selesai',
            'terms_accepted_at' => now(),
            'status' => 'locked_roster',
        ]);

        $activeAuditionEvent = Event::create([
            'name' => 'Audisi Aktif 2026',
            'slug' => 'audisi-aktif-2026',
            'venue_name' => 'Bojongsari',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'open',
            'max_participants' => 100,
            'registration_fee' => 150000,
            'required_documents' => ['birth_certificate'],
        ]);

        $pastAuditionEvent = Event::create([
            'name' => 'Audisi Selesai 2025',
            'slug' => 'audisi-selesai-2025',
            'venue_name' => 'Bojongsari',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-11',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'status' => 'completed',
            'certificates_released' => true,
            'max_participants' => 100,
            'registration_fee' => 150000,
            'required_documents' => ['birth_certificate'],
        ]);

        AuditionParticipant::create([
            'event_id' => $activeAuditionEvent->id,
            'user_id' => $user->id,
            'registration_number' => 'AUD-2026-ACT',
            'full_name' => 'Anak Aktif',
            'nik' => '3171000000000011',
            'birth_date' => '2014-01-01',
            'gender' => 'male',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081234567890',
            'screening_status' => 'passed',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);

        AuditionParticipant::create([
            'event_id' => $pastAuditionEvent->id,
            'user_id' => $user->id,
            'registration_number' => 'AUD-2025-OLD',
            'full_name' => 'Anak Selesai',
            'nik' => '3171000000000012',
            'birth_date' => '2015-01-01',
            'gender' => 'male',
            'emergency_contact_name' => 'Wali',
            'emergency_contact_phone' => '081234567890',
            'screening_status' => 'passed',
            'stage_status' => 'final_selected',
            'status' => 'passed',
        ]);

        $response = $this->actingAs($user)->get(route('manager.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manager/Dashboard')
            ->where('team.id', $activeTeam->id)
            ->where('team.event.is_completed', false)
            ->has('teamsList', 2)
            ->where('teamsList.0.name', 'Tim Aktif 2026')
            ->where('teamsList.0.is_event_completed', false)
            ->where('teamsList.1.name', 'Tim Selesai 2025')
            ->where('teamsList.1.is_event_completed', true)
            ->has('auditionParticipants', 2)
            ->where('auditionParticipants.0.full_name', 'Anak Aktif')
            ->where('auditionParticipants.0.event.is_completed', false)
            ->where('auditionParticipants.1.full_name', 'Anak Selesai')
            ->where('auditionParticipants.1.event.is_completed', true)
        );
    }
}
