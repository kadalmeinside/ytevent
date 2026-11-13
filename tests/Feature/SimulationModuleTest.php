<?php

namespace Tests\Feature;

use App\Models\AuditionAttendance;
use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\ScoutingEvaluation;
use App\Models\Team;
use App\Models\User;
use App\Modules\Simulation\SimulationService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimulationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Event $event;
    protected Event $auditionEvent;
    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        Storage::fake('private');

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);

        $this->superadmin = User::create([
            'name' => 'Superadmin Sim',
            'email' => 'superadmin-sim@persija.id',
            'password' => Hash::make('Admin12345'),
            'is_active' => true,
        ]);
        $this->superadmin->assignRole('superadmin');

        $this->event = Event::create([
            'name' => 'Young Tiger Simulation Cup',
            'slug' => 'young-tiger-sim-cup',
            'venue_name' => 'Stadion Madya GBK',
            'city' => 'Jakarta Pusat',
            'format' => 'cup',
            'age_category' => 'U-12',
            'min_birth_date' => '2014-01-01',
            'max_birth_date' => '2015-12-31',
            'min_players_per_team' => 4,
            'max_players_per_team' => 18,
            'min_gk_per_team' => 2,
            'max_officials_per_team' => 4,
            'show_coach_license' => true,
            'require_coach_license' => true,
            'required_documents' => ['birth_certificate', 'family_card'],
            'max_teams' => 8,
            'registration_fee' => 2000000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'status' => 'open',
        ]);

        $this->auditionEvent = Event::create([
            'name' => 'Persija Development Talent Audition 2026',
            'slug' => 'persija-audition-2026',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'venue_name' => 'Persija Training Ground, Bojongsari',
            'city' => 'Depok',
            'format' => 'audition',
            'age_category' => 'U-12',
            'registration_fee' => 150000,
            'max_teams' => 50,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
            'audition_stages' => [
                ['stage' => 1, 'name' => 'Screening Fisik & Tes Keterampilan', 'date' => '2026-11-20'],
                ['stage' => 2, 'name' => 'Game Internal 11v11', 'date' => '2026-11-21'],
                ['stage' => 3, 'name' => 'Final Selection Day', 'date' => '2026-11-22'],
            ],
        ]);
    }

    public function test_simulation_module_can_be_toggled_on_and_off(): void
    {
        $this->assertTrue(SimulationService::isEnabled());

        $this->post(route('simulation.toggle'), ['enabled' => false])
            ->assertRedirect();

        $this->assertFalse(SimulationService::isEnabled());

        // When disabled, simulation endpoints are blocked with 403
        $this->post(route('simulation.register-team'), ['event_id' => $this->event->id])
            ->assertForbidden();

        // Turn back on
        $this->post(route('simulation.toggle'), ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue(SimulationService::isEnabled());
    }

    public function test_simulation_step_by_step_register_pay_fill_roster_and_admin_verify(): void
    {
        // 1. Register team via simulation
        $this->post(route('simulation.register-team'), [
            'event_id' => $this->event->id,
            'login_as_manager' => true,
        ])->assertRedirect(route('manager.payments'));

        $team = Team::query()->latest('id')->first();
        $this->assertNotNull($team);
        $this->assertSame('pending_payment', $team->status);

        // 2. Upload manual payment proof via simulation
        $this->post(route('simulation.pay-team'), [
            'team_id' => $team->id,
            'instant_settle' => false,
        ])->assertRedirect();

        $this->assertSame('waiting_verification', $team->fresh()->latestPayment->status);

        // 3. Admin approves payment via simulation
        $this->actingAs($this->superadmin)
            ->post(route('simulation.admin-approve-payments'), [
                'team_id' => $team->id,
            ])
            ->assertRedirect();

        $this->assertSame('paid', $team->fresh()->latestPayment->status);
        $this->assertSame('active', $team->fresh()->status);

        // 4. Fill roster + documents + officials via simulation
        $this->post(route('simulation.fill-roster'), [
            'team_id' => $team->id,
            'auto_submit' => true,
        ])->assertRedirect();

        $team->refresh();
        $this->assertGreaterThanOrEqual(4, $team->players()->count());
        $this->assertGreaterThanOrEqual(2, $team->players()->where('position', 'GK')->count());
        $this->assertSame('in_review', $team->screening_status);

        // 5. Admin approves all screening players & officials via simulation
        $this->actingAs($this->superadmin)
            ->post(route('simulation.admin-approve-screening'), [
                'team_id' => $team->id,
            ])
            ->assertRedirect();

        $team->refresh();
        $this->assertSame('all_passed', $team->screening_status);
        $this->assertSame(0, $team->players()->where('screening_status', '!=', 'passed')->count());
    }

    public function test_simulation_one_click_fast_forward_to_locked_roster(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('simulation.fast-forward-team'), [
                'event_id' => $this->event->id,
            ])
            ->assertRedirect();

        $team = Team::query()->latest('id')->first();
        $this->assertNotNull($team);
        $this->assertSame('locked_roster', $team->status);
        $this->assertSame('all_passed', $team->screening_status);
        $this->assertSame('paid', $team->latestPayment->status);
        $this->assertGreaterThanOrEqual(4, $team->players()->where('screening_status', 'passed')->count());
    }

    public function test_simulation_fill_remaining_slots_for_event(): void
    {
        // Event has max_teams = 8. Let's set it to 3 for quick test execution
        $this->event->update(['max_teams' => 3]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.fill-event-slots'), [
                'event_id' => $this->event->id,
            ]);

        $response->assertRedirect();

        // 3 teams created, each fully populated with players, jersey colors and paid
        $this->assertEquals(3, $this->event->teams()->count());
        foreach ($this->event->teams as $team) {
            $this->assertEquals('active', $team->status);
            $this->assertNotEmpty($team->home_jersey_color);
            $this->assertNotEmpty($team->away_jersey_color);
            $this->assertGreaterThanOrEqual(4, $team->players()->count());
            $this->assertEquals('paid', $team->latestPayment->status);
        }
    }

    public function test_simulation_bulk_approve_and_lock_all_teams_in_event(): void
    {
        $this->event->update(['max_teams' => 2]);

        // Inject 2 teams
        $this->actingAs($this->superadmin)
            ->post(route('simulation.fill-event-slots'), [
                'event_id' => $this->event->id,
            ]);

        // Now bulk approve and lock all
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-approve-lock-all'), [
                'event_id' => $this->event->id,
            ]);

        $response->assertRedirect();

        // Verify both teams are locked_roster and all players passed
        $this->assertEquals(2, $this->event->teams()->where('status', 'locked_roster')->count());
        foreach ($this->event->teams as $team) {
            $this->assertEquals('all_passed', $team->screening_status);
            $this->assertEquals(0, $team->players()->where('screening_status', '!=', 'passed')->count());
            foreach ($team->players as $player) {
                $this->assertNotEmpty($player->accreditation_code);
                $this->assertTrue($player->is_locked);
            }
        }
    }

    public function test_simulation_delete_team_purges_team_payments_and_restores_quota(): void
    {
        $service = app(SimulationService::class);

        // 1. Register team
        $team = $service->simulateRegisterTeam($this->event);
        $this->assertEquals(1, Team::count());
        $this->assertEquals(1, $this->event->fresh()->occupiedSlotsCount());

        // 2. Pay team (creates payment and proof file)
        $payment = $service->simulateManagerPayment($team, instantSettle: true);
        $this->assertEquals(1, Payment::count());
        $this->assertEquals('paid', $payment->status);

        // 3. Fill roster (creates players and documents)
        $service->simulateFillRosterAndDocuments($team, autoSubmit: true);
        $this->assertGreaterThan(0, Player::count());
        $this->assertGreaterThan(0, PlayerDocument::count());

        // 4. Delete team via simulation endpoint
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.delete-team'), [
                'team_id' => $team->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify team and all related payments, players, documents are permanently purged
        $this->assertEquals(0, Team::withTrashed()->count());
        $this->assertEquals(0, Payment::withTrashed()->count());
        $this->assertEquals(0, Player::withTrashed()->count());
        $this->assertEquals(0, PlayerDocument::count());

        // Verify event quota was restored
        $this->assertEquals(0, $this->event->fresh()->occupiedSlotsCount());
    }

    public function test_simulation_delete_all_teams_in_event_purges_all_and_resets_quota(): void
    {
        $this->event->update(['max_teams' => 3]);

        // Inject 3 teams
        $this->actingAs($this->superadmin)
            ->post(route('simulation.fill-event-slots'), [
                'event_id' => $this->event->id,
            ]);

        $this->assertEquals(3, Team::where('event_id', $this->event->id)->count());
        $this->assertEquals(3, Payment::where('event_id', $this->event->id)->count());
        $this->assertEquals(3, $this->event->fresh()->occupiedSlotsCount());

        // Now delete all teams in this event
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.delete-all-teams'), [
                'event_id' => $this->event->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify all teams and payments in this event are purged
        $this->assertEquals(0, Team::where('event_id', $this->event->id)->withTrashed()->count());
        $this->assertEquals(0, Payment::where('event_id', $this->event->id)->withTrashed()->count());
        $this->assertEquals(0, $this->event->fresh()->occupiedSlotsCount());
    }

    public function test_simulation_register_individual_participant(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.register-individual'), [
                'event_id' => $this->auditionEvent->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $participant = AuditionParticipant::where('event_id', $this->auditionEvent->id)->first();
        $this->assertNotNull($participant);
        $this->assertSame('passed', $participant->screening_status);
        $this->assertNotEmpty($participant->registration_number);
        $this->assertNotEmpty($participant->bib_number);
        $this->assertSame('paid', $participant->latestPayment?->status);
        $this->assertTrue($participant->isPaid());
        $this->assertTrue($participant->canDownloadBib());
    }

    public function test_simulation_bulk_inject_individual_participants(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-inject-individuals'), [
                'event_id' => $this->auditionEvent->id,
                'count' => 6,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(6, AuditionParticipant::where('event_id', $this->auditionEvent->id)->count());
        $this->assertEquals(6, AuditionParticipant::where('event_id', $this->auditionEvent->id)->where('screening_status', 'passed')->count());
    }

    public function test_simulation_bulk_check_in_attendance(): void
    {
        // First inject 4 participants
        $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-inject-individuals'), [
                'event_id' => $this->auditionEvent->id,
                'count' => 4,
            ]);

        // Bulk check-in for Stage 1
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-check-in'), [
                'event_id' => $this->auditionEvent->id,
                'stage_number' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(4, AuditionAttendance::whereHas('participant', fn ($q) => $q->where('event_id', $this->auditionEvent->id))->where('status', 'present')->count());
    }

    public function test_simulation_bulk_scout_grade(): void
    {
        // First inject 3 participants
        $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-inject-individuals'), [
                'event_id' => $this->auditionEvent->id,
                'count' => 3,
            ]);

        // Bulk scout grade for Stage 1
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-scout-grade'), [
                'event_id' => $this->auditionEvent->id,
                'stage_number' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(3, ScoutingEvaluation::whereHas('participant', fn ($q) => $q->where('event_id', $this->auditionEvent->id))->count());
    }

    public function test_simulation_bulk_advance_stage(): void
    {
        // Inject 4 participants
        $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-inject-individuals'), [
                'event_id' => $this->auditionEvent->id,
                'count' => 4,
            ]);

        // Advance to day_1_passed
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-advance-stage'), [
                'event_id' => $this->auditionEvent->id,
                'target_status' => 'day_1_passed',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertGreaterThan(0, AuditionParticipant::where('event_id', $this->auditionEvent->id)->where('stage_status', 'day_1_passed')->count());
    }

    public function test_simulation_delete_all_individuals_in_event(): void
    {
        // Inject 4 participants
        $this->actingAs($this->superadmin)
            ->post(route('simulation.bulk-inject-individuals'), [
                'event_id' => $this->auditionEvent->id,
                'count' => 4,
            ]);

        $this->assertEquals(4, AuditionParticipant::where('event_id', $this->auditionEvent->id)->count());

        // Now purge
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.delete-all-individuals'), [
                'event_id' => $this->auditionEvent->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(0, AuditionParticipant::where('event_id', $this->auditionEvent->id)->count());
    }

    public function test_simulation_switch_persona_scout_and_parent(): void
    {
        // Switch to scout
        $response = $this->post(route('simulation.switch-persona'), [
            'role' => 'scout',
        ]);
        $response->assertRedirect();
        $this->assertTrue(auth()->user()->hasRole('talent_scout'));

        // Switch to participant_parent
        $response = $this->post(route('simulation.switch-persona'), [
            'role' => 'participant_parent',
        ]);
        $response->assertRedirect();
        $this->assertTrue(auth()->user()->hasRole('participant_parent'));
    }

    public function test_simulation_can_create_audition_event(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.create-audition-event'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $event = Event::where('event_type', 'audition')->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('individual', $event->participant_type);
        $this->assertSame('audition', $event->format);
        $this->assertEquals(2, $event->auditionStages()->count());
        $this->assertEquals(2, $event->eventSessions()->count());
        $this->assertNotEmpty($event->position_quota_caps);
        $this->assertSame(16, $event->position_quota_caps['GK']);
    }

    public function test_simulation_can_create_coaching_clinic_event(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.create-coaching-clinic-event'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $event = Event::where('event_type', 'coaching_clinic')->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('individual', $event->participant_type);
        $this->assertSame('coaching_clinic', $event->format);
        $this->assertEquals(0, $event->auditionStages()->count());
        $this->assertEquals(2, $event->eventSessions()->count());
        $this->assertNull($event->position_quota_caps);
    }

    public function test_simulation_can_register_audition_participant(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.register-audition-participant'), [
                'event_id' => $this->auditionEvent->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $participant = AuditionParticipant::where('event_id', $this->auditionEvent->id)->latest('id')->first();
        $this->assertNotNull($participant);
        $this->assertStringStartsWith('AUD-', $participant->registration_number);
        $this->assertNotNull($participant->bib_number);
        $this->assertSame('passed', $participant->screening_status);
        $this->assertSame('active', $participant->status);
    }

    public function test_simulation_can_register_coaching_clinic_participant(): void
    {
        $clinic = app(SimulationService::class)->simulateCreateCoachingClinicEvent();

        $response = $this->actingAs($this->superadmin)
            ->post(route('simulation.register-coaching-clinic-participant'), [
                'event_id' => $clinic->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $participant = AuditionParticipant::where('event_id', $clinic->id)->latest('id')->first();
        $this->assertNotNull($participant);
        $this->assertStringStartsWith('CLN-', $participant->registration_number);
        $this->assertSame('passed', $participant->screening_status);
        $this->assertSame('active', $participant->status);
    }
}

