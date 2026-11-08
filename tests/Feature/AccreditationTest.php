<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccreditationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);
        Storage::fake('private');
        Storage::fake('public');
    }

    protected function createAccreditationEnvironment(int $minPlayers = 3): array
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $manager = User::factory()->create();
        $manager->assignRole('team_manager');

        $event = Event::create([
            'name' => 'Persija Young Tiger Cup U-12',
            'slug' => 'young-tiger-cup-u-12',
            'venue_name' => 'Stadion Soemantri Brodjonegoro',
            'city' => 'Jakarta Selatan',
            'format' => 'festival',
            'age_category' => 'U-12',
            'min_birth_date' => '2014-01-01',
            'max_birth_date' => '2015-12-31',
            'min_players_per_team' => $minPlayers,
            'max_players_per_team' => 18,
            'max_officials_per_team' => 4,
            'required_documents' => ['birth_certificate'],
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'status' => 'open',
        ]);

        $team = Team::create([
            'event_id' => $event->id,
            'user_id' => $manager->id,
            'name' => 'Persija Muda Jakarta',
            'slug' => 'persija-muda-jakarta',
            'logo_path' => 'logos/persija-muda.png',
            'origin_association' => 'Askot PSSI Jakarta Selatan',
            'manager_name' => 'Bambang Pamungkas',
            'manager_whatsapp' => '081299998888',
            'manager_email' => $manager->email,
            'head_coach_name' => 'Coach Sudirman',
            'head_coach_license' => 'AFC B License',
            'terms_accepted_at' => now(),
            'status' => 'active',
            'screening_status' => 'in_review',
        ]);

        return compact('superadmin', 'manager', 'event', 'team');
    }

    protected function createPlayer(Team $team, int $number, string $pos = 'DF', string $status = 'passed'): Player
    {
        $photo = UploadedFile::fake()->image("player_{$number}.jpg", 300, 400);
        $path = $photo->store("players/{$team->id}", 'private');

        return Player::create([
            'team_id' => $team->id,
            'event_id' => $team->event_id,
            'full_name' => "Pemain {$number}",
            'jersey_name' => "PEMAIN {$number}",
            'jersey_number' => $number,
            'position' => $pos,
            'dominant_foot' => 'Right',
            'birth_place' => 'Jakarta',
            'birth_date' => '2014-06-15',
            'nik' => sprintf('317501150614%04d', $number),
            'nisn' => sprintf('0014%06d', $number),
            'photo_path' => $path,
            'screening_status' => $status,
            'is_locked' => $status === 'passed',
            'accreditation_code' => $status === 'passed' ? "YT-U12-PL{$number}" : null,
            'verified_at' => $status === 'passed' ? now() : null,
        ]);
    }

    protected function createOfficial(Team $team, string $role = 'Head Coach'): TeamOfficial
    {
        $photo = UploadedFile::fake()->image('official.jpg', 300, 400);
        $path = $photo->store("officials/{$team->id}", 'private');

        return TeamOfficial::create([
            'team_id' => $team->id,
            'event_id' => $team->event_id,
            'full_name' => 'Coach Sudirman',
            'role' => $role,
            'license_number' => 'AFC-B-12345',
            'photo_path' => $path,
            'screening_status' => 'passed',
        ]);
    }

    public function test_manager_can_view_accreditation_page_and_readiness_checklist(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createOfficial($env['team']);

        $response = $this->actingAs($env['manager'])
            ->get(route('manager.accreditation.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Manager/Accreditation/Index')
            ->has('team')
            ->has('readiness')
            ->where('readiness.passed_players_count', 1)
            ->where('readiness.min_players_required', 3)
            ->where('readiness.is_ready', false)
            ->where('readiness.is_locked', false)
        );
    }

    public function test_manager_cannot_lock_roster_if_minimum_players_count_not_met(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        // Only 2 players passed (need 3)
        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createPlayer($env['team'], 20, 'GK', 'passed');
        $this->createOfficial($env['team']);

        $response = $this->actingAs($env['manager'])
            ->post(route('manager.teams.lock-roster', ['team' => $env['team']->id]));

        $response->assertSessionHasErrors(['roster']);
        $this->assertFalse($env['team']->fresh()->isRosterLocked());
    }

    public function test_manager_cannot_lock_roster_if_minimum_2_gk_not_met(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        // 3 players passed, but only 1 GK
        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createPlayer($env['team'], 4, 'DF', 'passed');
        $this->createPlayer($env['team'], 7, 'FW', 'passed');
        $this->createOfficial($env['team']);

        $response = $this->actingAs($env['manager'])
            ->post(route('manager.teams.lock-roster', ['team' => $env['team']->id]));

        $response->assertSessionHasErrors(['roster']);
        $this->assertFalse($env['team']->fresh()->isRosterLocked());
    }

    public function test_manager_cannot_lock_roster_if_no_officials_registered(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        // 3 players passed (including 2 GK), but 0 officials
        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createPlayer($env['team'], 20, 'GK', 'passed');
        $this->createPlayer($env['team'], 10, 'FW', 'passed');

        $response = $this->actingAs($env['manager'])
            ->post(route('manager.teams.lock-roster', ['team' => $env['team']->id]));

        $response->assertSessionHasErrors(['roster']);
        $this->assertFalse($env['team']->fresh()->isRosterLocked());
    }

    public function test_manager_cannot_lock_roster_if_submitted_players_still_in_review(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createPlayer($env['team'], 20, 'GK', 'passed');
        $this->createPlayer($env['team'], 10, 'FW', 'passed');
        $this->createPlayer($env['team'], 9, 'FW', 'submitted'); // Still pending
        $this->createOfficial($env['team']);

        $response = $this->actingAs($env['manager'])
            ->post(route('manager.teams.lock-roster', ['team' => $env['team']->id]));

        $response->assertSessionHasErrors(['roster']);
        $this->assertFalse($env['team']->fresh()->isRosterLocked());
    }

    public function test_manager_can_lock_roster_when_all_conditions_satisfied(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createPlayer($env['team'], 20, 'GK', 'passed');
        $this->createPlayer($env['team'], 10, 'FW', 'passed');
        $this->createOfficial($env['team']);

        $response = $this->actingAs($env['manager'])
            ->post(route('manager.teams.lock-roster', ['team' => $env['team']->id]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $freshTeam = $env['team']->fresh();
        $this->assertTrue($freshTeam->isRosterLocked());
        $this->assertEquals('locked_roster', $freshTeam->status);
        $this->assertEquals('all_passed', $freshTeam->screening_status);
        $this->assertNotNull($freshTeam->roster_locked_at);
        $this->assertFalse($freshTeam->canManageRoster());
    }

    public function test_manager_cannot_modify_roster_once_team_is_locked(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        $p1 = $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createPlayer($env['team'], 20, 'GK', 'passed');
        $this->createPlayer($env['team'], 10, 'FW', 'passed');
        $this->createOfficial($env['team']);

        // Lock team
        $env['team']->update([
            'status' => 'locked_roster',
            'roster_locked_at' => now(),
        ]);

        // Attempt to create new player
        $response = $this->actingAs($env['manager'])
            ->post(route('manager.players.store', ['team' => $env['team']->id]), [
                'full_name' => 'Pemain Baru',
                'jersey_number' => 99,
                'position' => 'MF',
                'birth_date' => '2014-05-10',
                'nik' => '3175011005140099',
            ]);

        $response->assertStatus(403);
    }

    public function test_manager_can_download_roster_sheet_and_id_cards_when_locked(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createPlayer($env['team'], 20, 'GK', 'passed');
        $this->createPlayer($env['team'], 10, 'FW', 'passed');
        $this->createOfficial($env['team']);

        $env['team']->update([
            'status' => 'locked_roster',
            'roster_locked_at' => now(),
        ]);

        // Roster Sheet PDF
        $rosterResp = $this->actingAs($env['manager'])
            ->get(route('manager.accreditation.roster-sheet', ['team' => $env['team']->id]));

        $rosterResp->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $rosterResp->headers->get('Content-Type'));

        // ID Cards PDF
        $cardsResp = $this->actingAs($env['manager'])
            ->get(route('manager.accreditation.id-cards', ['team' => $env['team']->id]));

        $cardsResp->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $cardsResp->headers->get('Content-Type'));
    }

    public function test_manager_cannot_download_pdf_if_roster_not_locked(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        $this->createPlayer($env['team'], 1, 'GK', 'passed');

        $response = $this->actingAs($env['manager'])
            ->get(route('manager.accreditation.roster-sheet', ['team' => $env['team']->id]));

        $response->assertStatus(403);
    }

    public function test_superadmin_can_download_roster_sheet_anytime(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createOfficial($env['team']);

        // Even if not locked, admin can download
        $response = $this->actingAs($env['superadmin'])
            ->get(route('admin.teams.accreditation.roster-sheet', ['team' => $env['team']->id]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_public_can_verify_accredited_player_via_qr_code(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        $player = $this->createPlayer($env['team'], 10, 'FW', 'passed');

        // Public GET without login
        $response = $this->get(route('public.player.verify', ['code' => $player->accreditation_code]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/PlayerVerification')
            ->where('isVerified', true)
            ->where('code', $player->accreditation_code)
            ->where('player.full_name', $player->full_name)
            ->where('player.jersey_number', 10)
            ->where('team.name', $env['team']->name)
        );

        // Photo stream
        $photoResp = $this->get(route('public.player.verify.photo', ['code' => $player->accreditation_code]));
        $photoResp->assertStatus(200);
        $this->assertStringContainsString('image/', $photoResp->headers->get('Content-Type'));
    }

    public function test_public_verification_returns_unverified_for_non_existent_or_draft_player(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 3);

        // 1. Non-existent code
        $respInvalid = $this->get(route('public.player.verify', ['code' => 'INVALID-CODE-999']));
        $respInvalid->assertStatus(200);
        $respInvalid->assertInertia(fn ($page) => $page
            ->component('Public/PlayerVerification')
            ->where('isVerified', false)
            ->where('code', 'INVALID-CODE-999')
        );

        // 2. Player still draft
        $draftPlayer = $this->createPlayer($env['team'], 11, 'FW', 'draft');
        $draftPlayer->update(['accreditation_code' => 'DRAFT-CODE-11']);

        $respDraft = $this->get(route('public.player.verify', ['code' => 'DRAFT-CODE-11']));
        $respDraft->assertStatus(200);
        $respDraft->assertInertia(fn ($page) => $page
            ->component('Public/PlayerVerification')
            ->where('isVerified', false)
        );
    }

    public function test_id_cards_layout_is_a4_divided_by_4_precisely_and_roster_sheet_is_3_players_per_row(): void
    {
        $env = $this->createAccreditationEnvironment(minPlayers: 4);

        $this->createPlayer($env['team'], 1, 'GK', 'passed');
        $this->createPlayer($env['team'], 20, 'GK', 'passed');
        $this->createPlayer($env['team'], 7, 'MF', 'passed');
        $this->createPlayer($env['team'], 10, 'FW', 'passed');
        $this->createOfficial($env['team']);

        $rosterHtml = view('pdf.team_roster_sheet', [
            'team' => $env['team'],
            'event' => $env['event'],
            'players' => [
                ['full_name' => 'P1', 'jersey_number' => 1, 'position' => 'GK', 'birth_date' => '01/01/2014', 'birth_place' => 'JKT', 'nik' => '3171010101140001', 'accreditation_code' => 'C1', 'photo_base64' => null, 'qr_code_svg' => null],
                ['full_name' => 'P2', 'jersey_number' => 20, 'position' => 'GK', 'birth_date' => '01/01/2014', 'birth_place' => 'JKT', 'nik' => '3171010101140002', 'accreditation_code' => 'C2', 'photo_base64' => null, 'qr_code_svg' => null],
                ['full_name' => 'P3', 'jersey_number' => 7, 'position' => 'MF', 'birth_date' => '01/01/2014', 'birth_place' => 'JKT', 'nik' => '3171010101140003', 'accreditation_code' => 'C3', 'photo_base64' => null, 'qr_code_svg' => null],
                ['full_name' => 'P4', 'jersey_number' => 10, 'position' => 'FW', 'birth_date' => '01/01/2014', 'birth_place' => 'JKT', 'nik' => '3171010101140004', 'accreditation_code' => 'C4', 'photo_base64' => null, 'qr_code_svg' => null],
            ],
            'officials' => [],
            'team_logo_base64' => null,
            'generated_at' => '08 Nov 2026',
            'locked_at' => '08 Nov 2026',
        ])->render();

        // Roster sheet has 33.3333% column width and 6 player-card-td cells for 4 players (2 rows of 3)
        $this->assertStringContainsString('width: 33.3333%;', $rosterHtml);
        $this->assertSame(6, substr_count($rosterHtml, 'class="player-card-td"'));

        $cardsHtml = view('pdf.id_cards', [
            'team' => $env['team'],
            'event' => $env['event'],
            'cards' => [
                ['type' => 'player', 'name' => 'P1', 'jersey_number' => 1, 'role' => 'GK', 'role_label' => 'PEMAIN', 'accreditation_code' => 'C1', 'photo_base64' => null, 'qr_code_svg' => null],
                ['type' => 'player', 'name' => 'P2', 'jersey_number' => 20, 'role' => 'GK', 'role_label' => 'PEMAIN', 'accreditation_code' => 'C2', 'photo_base64' => null, 'qr_code_svg' => null],
                ['type' => 'player', 'name' => 'P3', 'jersey_number' => 7, 'role' => 'MF', 'role_label' => 'PEMAIN', 'accreditation_code' => 'C3', 'photo_base64' => null, 'qr_code_svg' => null],
                ['type' => 'player', 'name' => 'P4', 'jersey_number' => 10, 'role' => 'FW', 'role_label' => 'PEMAIN', 'accreditation_code' => 'C4', 'photo_base64' => null, 'qr_code_svg' => null],
                ['type' => 'official', 'name' => 'O1', 'jersey_number' => null, 'role' => 'Head Coach', 'role_label' => 'OFISIAL / HEAD COACH', 'license_number' => 'LIS-1', 'accreditation_code' => 'OFF-0001', 'photo_base64' => null, 'qr_code_svg' => null],
            ],
            'team_logo_base64' => null,
            'generated_at' => '08 Nov 2026',
        ])->render();

        // A4 exact quadrant dimensions: 105mm x 148.5mm (210mm x 297mm divided by 4)
        $this->assertStringContainsString('width: 105mm;', $cardsHtml);
        $this->assertStringContainsString('height: 148.5mm;', $cardsHtml);
        // 5 cards produce 2 pages x 4 quadrants = 8 <td class="quadrant-cell ..."> cells
        $this->assertSame(8, substr_count($cardsHtml, '<td class="quadrant-cell'));
    }
}

