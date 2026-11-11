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
            'match_format' => '5_vs_5',
            'starting_players_count' => 1,
            'certificates_released' => true,
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
        // 1. Guard check: when registration is OPEN and slots not full, drawing is blocked
        $blocked = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.groups.generate', ['event' => $this->event->id]), [
                'group_count' => 1,
            ]);
        $blocked->assertRedirect();
        $blocked->assertSessionHasErrors(['group']);

        // 2. When slots are full / Technical Meeting session: drawing succeeds
        $this->event->update(['max_teams' => 2]);

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

        // Group must be locked before generating matches
        $this->event->update(['is_group_locked' => true]);

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

    public function test_admin_can_toggle_group_lock_and_locked_group_rejects_redraw(): void
    {
        // First draw groups
        $service = app(StandingsCalculatorService::class);
        $service->generateGroupsAndDraw($this->event, 1, false);

        $this->assertFalse((bool) $this->event->fresh()->is_group_locked);

        // Toggle lock to true
        $res = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.groups.toggle-lock', $this->event->id));
        $res->assertRedirect();
        $this->assertTrue((bool) $this->event->fresh()->is_group_locked);

        // Attempting to generate/re-draw groups while locked should be blocked
        $drawRes = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.groups.generate', $this->event->id), [
                'group_count' => 2,
            ]);
        $drawRes->assertRedirect();
        $drawRes->assertSessionHasErrors(['group']);

        // Toggle unlock
        $unlockRes = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.groups.toggle-lock', $this->event->id));
        $unlockRes->assertRedirect();
        $this->assertFalse((bool) $this->event->fresh()->is_group_locked);
    }

    public function test_admin_can_move_team_to_different_group(): void
    {
        $groupA = EventGroup::create([
            'event_id' => $this->event->id,
            'name' => 'Grup A',
            'sort_order' => 1,
        ]);
        $groupB = EventGroup::create([
            'event_id' => $this->event->id,
            'name' => 'Grup B',
            'sort_order' => 2,
        ]);

        \App\Models\EventGroupTeam::create([
            'event_group_id' => $groupA->id,
            'team_id' => $this->teamA->id,
        ]);

        $this->assertDatabaseHas('event_group_teams', [
            'event_group_id' => $groupA->id,
            'team_id' => $this->teamA->id,
        ]);

        // Move teamA to groupB
        $res = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.groups.move-team', $this->event->id), [
                'team_id' => $this->teamA->id,
                'target_group_id' => $groupB->id,
            ]);

        $res->assertRedirect();
        $this->assertDatabaseMissing('event_group_teams', [
            'event_group_id' => $groupA->id,
            'team_id' => $this->teamA->id,
        ]);
        $this->assertDatabaseHas('event_group_teams', [
            'event_group_id' => $groupB->id,
            'team_id' => $this->teamA->id,
        ]);
    }

    public function test_admin_can_reschedule_match(): void
    {
        $match = TournamentMatch::create([
            'event_id' => $this->event->id,
            'home_team_id' => $this->teamA->id,
            'away_team_id' => $this->teamB->id,
            'stage' => 'group_stage',
            'match_number' => 1,
            'kickoff_at' => Carbon::now()->addDays(5)->setTime(8, 0),
            'pitch_name' => 'Lapangan 1',
            'status' => 'scheduled',
        ]);

        $newKickoff = Carbon::now()->addDays(5)->setTime(14, 30)->format('Y-m-d H:i:s');

        $res = $this->actingAs($this->superAdmin)
            ->post(route('admin.matches.reschedule', $match->id), [
                'kickoff_at' => $newKickoff,
                'pitch_name' => 'Lapangan Utama Stadion',
                'referee_name' => 'Wasit Thoriq Alkatiri',
                'status' => 'scheduled',
                'match_notes' => 'Penyesuaian jadwal siaran',
            ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('tournament_matches', [
            'id' => $match->id,
            'pitch_name' => 'Lapangan Utama Stadion',
            'referee_name' => 'Wasit Thoriq Alkatiri',
            'match_notes' => 'Penyesuaian jadwal siaran',
        ]);
    }

    public function test_admin_can_generate_knockout_stage_matches(): void
    {
        $groupA = EventGroup::create(['event_id' => $this->event->id, 'name' => 'Grup A', 'sort_order' => 1]);
        $groupB = EventGroup::create(['event_id' => $this->event->id, 'name' => 'Grup B', 'sort_order' => 2]);

        $teamC = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $this->managerUser->id,
            'name' => 'Persikopa Pariaman',
            'slug' => 'persikopa-pariaman',
            'logo_path' => 'logos/default.png',
            'origin_association' => 'Askot Bekasi',
            'manager_name' => 'Manager C',
            'manager_email' => 'c@test.com',
            'manager_whatsapp' => '0811111111',
            'head_coach_name' => 'Coach C',
            'terms_accepted_at' => now(),
            'status' => 'locked_roster',
        ]);

        $teamD = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $this->managerUser->id,
            'name' => 'Bina Sentra Cirebon',
            'slug' => 'bina-sentra-cirebon',
            'logo_path' => 'logos/default.png',
            'origin_association' => 'Askot Bekasi',
            'manager_name' => 'Manager D',
            'manager_email' => 'd@test.com',
            'manager_whatsapp' => '0822222222',
            'head_coach_name' => 'Coach D',
            'terms_accepted_at' => now(),
            'status' => 'locked_roster',
        ]);

        \App\Models\EventGroupTeam::create(['event_group_id' => $groupA->id, 'team_id' => $this->teamA->id]);
        \App\Models\EventGroupTeam::create(['event_group_id' => $groupA->id, 'team_id' => $teamC->id]);
        \App\Models\EventGroupTeam::create(['event_group_id' => $groupB->id, 'team_id' => $this->teamB->id]);
        \App\Models\EventGroupTeam::create(['event_group_id' => $groupB->id, 'team_id' => $teamD->id]);

        $this->event->update(['is_group_locked' => true]);

        $res = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.matches.generate-knockout', $this->event->id), [
                'stage' => 'semi_final',
                'start_time' => Carbon::now()->addDays(6)->setTime(9, 0)->format('Y-m-d H:i:s'),
                'interval_minutes' => 60,
                'pitches' => 'Lapangan 1',
            ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('tournament_matches', [
            'event_id' => $this->event->id,
            'stage' => 'semi_final',
        ]);
    }

    public function test_dsp_lineup_enforces_starter_count_and_auto_lock(): void
    {
        // Event format requires 2 starters
        $this->event->update(['starting_players_count' => 2]);

        $match = TournamentMatch::create([
            'event_id' => $this->event->id,
            'home_team_id' => $this->teamA->id,
            'away_team_id' => $this->teamB->id,
            'stage' => 'group_stage',
            'match_number' => 2,
            'kickoff_at' => Carbon::now()->addDays(5)->setTime(10, 0),
            'pitch_name' => 'Lapangan 1',
            'status' => 'scheduled',
        ]);

        // Submit only 1 starter when 2 are required -> validation error on starting_player_ids
        $invalidRes = $this->actingAs($this->managerUser)
            ->post(route('manager.matches.lineup.save', $match->id), [
                'starting_player_ids' => [$this->playerA1->id],
                'substitute_player_ids' => [$this->playerA2->id],
            ]);

        $invalidRes->assertRedirect();
        $invalidRes->assertSessionHasErrors(['starting_player_ids']);

        // Lock condition: match is ongoing -> rejected
        $match->update(['status' => 'ongoing']);
        $ongoingRes = $this->actingAs($this->managerUser)
            ->post(route('manager.matches.lineup.save', $match->id), [
                'starting_player_ids' => [$this->playerA1->id, $this->playerA2->id],
                'substitute_player_ids' => [],
            ]);

        $ongoingRes->assertRedirect();
        $ongoingRes->assertSessionHasErrors(['lineup']);
    }

    public function test_player_certificate_download_is_gated_by_admin_release(): void
    {
        $this->event->update([
            'certificates_released' => false,
            'status' => 'open',
        ]);

        // Manager attempt when unreleased -> 403
        $blockedRes = $this->actingAs($this->managerUser)
            ->get(route('manager.players.certificate', $this->playerA1->id));
        $blockedRes->assertStatus(403);

        // Admin toggles release
        $toggleRes = $this->actingAs($this->superAdmin)
            ->post(route('admin.events.certificates.toggle-release', $this->event->id));
        $toggleRes->assertRedirect();
        $this->assertTrue((bool) $this->event->fresh()->certificates_released);

        // Manager attempt now -> 200 PDF
        $allowedRes = $this->actingAs($this->managerUser)
            ->get(route('manager.players.certificate', $this->playerA1->id));
        $allowedRes->assertOk();
        $this->assertEquals('application/pdf', $allowedRes->headers->get('content-type'));
    }
}
