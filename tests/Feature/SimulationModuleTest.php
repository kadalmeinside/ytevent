<?php

namespace Tests\Feature;

use App\Models\Event;
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
}

