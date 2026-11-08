<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventGroup;
use App\Models\MatchEvent;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\StandingsCalculatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase5TournamentTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $managerUser;
    protected Event $event;
    protected Team $teamA;
    protected Team $teamB;
    protected Player $playerA1;
    protected Player $playerA2;
    protected Player $playerB1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);

        $this->superAdmin = User::factory()->create([
            'email' => 'admin@ytevent.test',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        $this->managerUser = User::factory()->create([
            'email' => 'manager@ytevent.test',
            'is_active' => true,
        ]);
        $this->managerUser->assignRole('team_manager');

        $this->event = Event::create([
            'name' => 'Piala Pelajar Nusantara U-12',
            'slug' => 'piala-pelajar-nusantara-u12',
            'age_category' => 'U-12',
            'format' => 'championship',
            'match_start_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'match_end_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
            'registration_open_at' => Carbon::now()->subDays(10),
            'registration_close_at' => Carbon::now()->addDays(2),
            'registration_fee' => 500000,
            'max_teams' => 16,
            'payment_deadline_hours' => 24,
            'min_birth_date' => '2012-01-01',
            'max_birth_date' => '2014-12-31',
            'min_players_per_team' => 2,
            'max_players_per_team' => 18,
            'min_gk_per_team' => 1,
            'max_officials_per_team' => 3,
            'venue_name' => 'Stadion Patriot Chandrabhaga',
            'city' => 'Kota Bekasi',
            'status' => 'open',
            'required_documents' => [
                ['type' => 'nisn', 'label' => 'NISN'],
            ],
        ]);

        $this->teamA = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $this->managerUser->id,
            'name' => 'Garuda Muda FC',
            'slug' => 'garuda-muda-fc',
            'logo_path' => 'logos/garuda.png',
            'origin_association' => 'Askot Bekasi',
            'manager_name' => $this->managerUser->name,
            'manager_email' => $this->managerUser->email,
            'manager_whatsapp' => '081234567890',
            'head_coach_name' => 'Coach Bima',
            'terms_accepted_at' => now(),
            'status' => 'locked_roster',
            'roster_locked_at' => Carbon::now(),
            'home_jersey_color' => 'Merah',
            'away_jersey_color' => 'Putih',
        ]);

        $otherManager = User::factory()->create(['is_active' => true]);
        $otherManager->assignRole('team_manager');

        $this->teamB = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $otherManager->id,
            'name' => 'Bintang Timur FC',
            'slug' => 'bintang-timur-fc',
            'logo_path' => 'logos/bintang.png',
            'origin_association' => 'Askab Bogor',
            'manager_name' => $otherManager->name,
            'manager_email' => $otherManager->email,
            'manager_whatsapp' => '081298765432',
            'head_coach_name' => 'Coach Fakhri',
            'terms_accepted_at' => now(),
            'status' => 'locked_roster',
            'roster_locked_at' => Carbon::now(),
            'home_jersey_color' => 'Biru',
            'away_jersey_color' => 'Kuning',
        ]);

        $this->playerA1 = Player::create([
            'team_id' => $this->teamA->id,
            'event_id' => $this->event->id,
            'full_name' => 'Rizky Pratama',
            'nik' => '3275010101120001',
            'birth_place' => 'Bekasi',
            'birth_date' => '2012-05-10',
            'position' => 'Penyerang',
            'jersey_number' => 10,
            'screening_status' => 'passed',
            'accreditation_code' => 'YT-E01-PL0001',
        ]);

        $this->playerA2 = Player::create([
            'team_id' => $this->teamA->id,
            'event_id' => $this->event->id,
            'full_name' => 'Bambang Kiper',
            'nik' => '3275010101120002',
            'birth_place' => 'Bekasi',
            'birth_date' => '2012-08-20',
            'position' => 'Penjaga Gawang',
            'jersey_number' => 1,
            'screening_status' => 'passed',
            'accreditation_code' => 'YT-E01-PL0002',
        ]);

        $this->playerB1 = Player::create([
            'team_id' => $this->teamB->id,
            'event_id' => $this->event->id,
            'full_name' => 'Eko Prasetyo',
            'nik' => '3275010101120003',
            'birth_place' => 'Bogor',
            'birth_date' => '2012-03-15',
            'position' => 'Gelandang',
            'jersey_number' => 8,
            'screening_status' => 'passed',
            'accreditation_code' => 'YT-E01-PL0003',
        ]);
    }

    public function test_admin_can_access_tournament_control_center(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('admin.events.tournament', ['event' => $this->event->id]))
            ->assertOk();
    }

    public function test_admin_can_generate_groups_and_draw_teams_automatically(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.groups.generate', ['event' => $this->event->id]), [
                'group_count' => 1,
                'only_locked_roster' => true,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('event_groups', 1);
        $group = EventGroup::where('event_id', $this->event->id)->first();
        $this->assertEquals('Grup A', $group->name);

        $this->assertDatabaseHas('event_group_teams', [
            'event_group_id' => $group->id,
            'team_id' => $this->teamA->id,
        ]);
        $this->assertDatabaseHas('event_group_teams', [
            'event_group_id' => $group->id,
            'team_id' => $this->teamB->id,
        ]);
    }

    public function test_admin_can_generate_group_stage_round_robin_matches(): void
    {
        // First generate group
        $service = app(StandingsCalculatorService::class);
        $service->generateGroupsAndDraw($this->event, 1, false);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.matches.generate', ['event' => $this->event->id]), [
                'start_time' => Carbon::now()->addDays(5)->setTime(8, 0)->format('Y-m-d\TH:i'),
                'interval_minutes' => 60,
                'pitches' => 'Lapangan Utama',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('tournament_matches', 1);
        $match = TournamentMatch::where('event_id', $this->event->id)->first();
        $this->assertEquals(1, $match->match_number);
        $this->assertEquals('group', $match->stage);
        $this->assertEquals('Lapangan Utama', $match->pitch_name);
        $this->assertEquals('scheduled', $match->status);
    }

    public function test_admin_can_update_match_score_events_and_recalculate_standings(): void
    {
        $service = app(StandingsCalculatorService::class);
        $service->generateGroupsAndDraw($this->event, 1, false);
        $group = EventGroup::where('event_id', $this->event->id)->first();

        $match = TournamentMatch::create([
            'event_id' => $this->event->id,
            'event_group_id' => $group->id,
            'stage' => 'group',
            'match_number' => 1,
            'home_team_id' => $this->teamA->id,
            'away_team_id' => $this->teamB->id,
            'status' => 'scheduled',
            'pitch_name' => 'Lapangan Utama',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.matches.result', ['match' => $match->id]), [
                'status' => 'completed',
                'home_score' => 2,
                'away_score' => 1,
                'pitch_name' => 'Lapangan Utama',
                'referee_name' => 'Thoriq Alkatiri',
                'events' => [
                    [
                        'team_id' => $this->teamA->id,
                        'player_id' => $this->playerA1->id,
                        'event_type' => 'goal',
                        'minute' => 14,
                    ],
                    [
                        'team_id' => $this->teamA->id,
                        'player_id' => $this->playerA1->id,
                        'event_type' => 'goal',
                        'minute' => 38,
                    ],
                    [
                        'team_id' => $this->teamB->id,
                        'player_id' => $this->playerB1->id,
                        'event_type' => 'goal',
                        'minute' => 52,
                    ],
                    [
                        'team_id' => $this->teamB->id,
                        'player_id' => $this->playerB1->id,
                        'event_type' => 'yellow_card',
                        'minute' => 55,
                    ],
                ],
            ]);

        $response->assertRedirect();

        // Verify match status
        $match->refresh();
        $this->assertEquals('completed', $match->status);
        $this->assertEquals(2, $match->home_score);
        $this->assertEquals(1, $match->away_score);
        $this->assertEquals('Thoriq Alkatiri', $match->referee_name);

        // Verify events recorded
        $this->assertDatabaseCount('match_events', 4);

        // Verify standings recalculation
        $this->assertDatabaseHas('event_group_teams', [
            'event_group_id' => $group->id,
            'team_id' => $this->teamA->id,
            'played' => 1,
            'won' => 1,
            'lost' => 0,
            'goals_for' => 2,
            'goals_against' => 1,
            'goal_difference' => 1,
            'points' => 3,
        ]);

        $this->assertDatabaseHas('event_group_teams', [
            'event_group_id' => $group->id,
            'team_id' => $this->teamB->id,
            'played' => 1,
            'won' => 0,
            'lost' => 1,
            'goals_for' => 1,
            'goals_against' => 2,
            'goal_difference' => -1,
            'points' => 0,
            'yellow_cards' => 1,
        ]);

        // Verify leaderboard
        $leaderboard = $service->getEventLeaderboard($this->event);
        $this->assertNotEmpty($leaderboard['top_scorers']);
        $this->assertEquals('Rizky Pratama', $leaderboard['top_scorers'][0]['player_name']);
        $this->assertEquals(2, $leaderboard['top_scorers'][0]['total_goals']);
    }

    public function test_manager_can_view_matches_and_submit_dsp_lineup(): void
    {
        $match = TournamentMatch::create([
            'event_id' => $this->event->id,
            'stage' => 'group_stage',
            'match_number' => 1,
            'home_team_id' => $this->teamA->id,
            'away_team_id' => $this->teamB->id,
            'status' => 'scheduled',
            'pitch_name' => 'Lapangan Utama',
        ]);

        $this->actingAs($this->managerUser)
            ->get(route('manager.matches.index'))
            ->assertOk();

        // Submit DSP for teamA
        $response = $this->actingAs($this->managerUser)
            ->post(route('manager.matches.lineup.save', ['match' => $match->id]), [
                'starting_player_ids' => [$this->playerA1->id],
                'substitute_player_ids' => [$this->playerA2->id],
                'captain_player_id' => $this->playerA1->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('match_lineups', [
            'match_id' => $match->id,
            'team_id' => $this->teamA->id,
            'player_id' => $this->playerA1->id,
            'is_starting' => true,
            'is_captain' => true,
        ]);

        $this->assertDatabaseHas('match_lineups', [
            'match_id' => $match->id,
            'team_id' => $this->teamA->id,
            'player_id' => $this->playerA2->id,
            'is_starting' => false,
            'is_captain' => false,
        ]);
    }

    public function test_manager_cannot_submit_suspended_player_in_dsp(): void
    {
        $match1 = TournamentMatch::create([
            'event_id' => $this->event->id,
            'stage' => 'group_stage',
            'match_number' => 1,
            'home_team_id' => $this->teamA->id,
            'away_team_id' => $this->teamB->id,
            'status' => 'completed',
            'home_score' => 1,
            'away_score' => 0,
        ]);

        // Player A1 receives a direct red card in match 1
        MatchEvent::create([
            'match_id' => $match1->id,
            'team_id' => $this->teamA->id,
            'player_id' => $this->playerA1->id,
            'event_type' => 'red_card',
            'minute' => 40,
        ]);

        // Next match
        $match2 = TournamentMatch::create([
            'event_id' => $this->event->id,
            'stage' => 'group_stage',
            'match_number' => 2,
            'home_team_id' => $this->teamA->id,
            'away_team_id' => $this->teamB->id,
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->managerUser)
            ->post(route('manager.matches.lineup.save', ['match' => $match2->id]), [
                'starting_player_ids' => [$this->playerA1->id],
                'captain_player_id' => $this->playerA1->id,
            ]);

        $response->assertSessionHasErrors('lineup');
    }

    public function test_manager_can_download_dsp_pdf_and_player_certificate_pdf(): void
    {
        $match = TournamentMatch::create([
            'event_id' => $this->event->id,
            'stage' => 'group_stage',
            'match_number' => 1,
            'home_team_id' => $this->teamA->id,
            'away_team_id' => $this->teamB->id,
            'status' => 'scheduled',
        ]);

        // Save lineup
        $this->actingAs($this->managerUser)
            ->post(route('manager.matches.lineup.save', ['match' => $match->id]), [
                'starting_player_ids' => [$this->playerA1->id],
                'substitute_player_ids' => [$this->playerA2->id],
                'captain_player_id' => $this->playerA1->id,
            ]);

        // Download DSP PDF
        $response = $this->actingAs($this->managerUser)
            ->get(route('manager.matches.lineup.pdf', ['match' => $match->id]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));

        // Download Player Certificate PDF
        $certResponse = $this->actingAs($this->managerUser)
            ->get(route('manager.players.certificate', ['player' => $this->playerA1->id]));

        $certResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $certResponse->headers->get('Content-Type'));
    }

    public function test_admin_can_create_announcements_and_assign_team_achievements(): void
    {
        // Announcement
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.announcements.store', ['event' => $this->event->id]), [
                'title' => 'Undangan Technical Meeting Resmi',
                'content' => 'TM akan dilaksanakan via Google Meet pada hari Jumat pukul 19.30 WIB.',
                'meeting_link' => 'https://meet.google.com/abc-defg-hij',
                'is_pinned' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('event_announcements', [
            'event_id' => $this->event->id,
            'title' => 'Undangan Technical Meeting Resmi',
            'is_pinned' => true,
        ]);

        // Team Achievement
        $achResponse = $this->actingAs($this->superAdmin)
            ->post(route('admin.teams.achievement', ['team' => $this->teamA->id]), [
                'achievement_title' => 'Juara 1 (Champions)',
            ]);

        $achResponse->assertRedirect();
        $this->assertDatabaseHas('teams', [
            'id' => $this->teamA->id,
            'achievement_title' => 'Juara 1 (Champions)',
        ]);
    }

    public function test_matchday_qr_scanner_lookup_endpoint(): void
    {
        // Lookup player
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.qr-scanner.lookup'), [
                'code' => $this->playerA1->accreditation_code,
            ]);

        $response->assertOk()
            ->assertJson([
                'found' => true,
                'entity_type' => 'player',
                'is_eligible' => true,
                'code' => $this->playerA1->accreditation_code,
                'full_name' => 'Rizky Pratama',
                'team_name' => 'Garuda Muda FC',
            ]);

        // Create official
        $official = TeamOfficial::create([
            'team_id' => $this->teamA->id,
            'event_id' => $this->event->id,
            'full_name' => 'Coach Indra',
            'role' => 'Pelatih Kepala',
            'license_number' => 'AFC-B-12345',
            'phone' => '0811223344',
            'validation_status' => 'valid',
        ]);

        // Lookup official
        $offResponse = $this->actingAs($this->superAdmin)
            ->postJson(route('admin.qr-scanner.lookup'), [
                'code' => 'OFF-' . str_pad($official->id, 4, '0', STR_PAD_LEFT),
            ]);

        $offResponse->assertOk()
            ->assertJson([
                'found' => true,
                'entity_type' => 'official',
                'is_eligible' => true,
                'full_name' => 'Coach Indra',
                'role' => 'Pelatih Kepala',
            ]);
    }
}
