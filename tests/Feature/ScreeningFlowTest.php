<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);
    }

    protected function createScreeningEnvironment(): array
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $screeningOfficer = User::factory()->create();
        $screeningOfficer->assignRole('screening_officer');

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
            'min_players_per_team' => 2,
            'max_players_per_team' => 18,
            'max_officials_per_team' => 4,
            'required_documents' => [
                ['type' => 'birth_certificate', 'label' => 'Akta Kelahiran'],
                ['type' => 'family_card', 'label' => 'Kartu Keluarga'],
            ],
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'status' => 'open',
        ]);

        $team = Team::create([
            'event_id' => $event->id,
            'user_id' => $manager->id,
            'name' => 'Persija Muda FC',
            'slug' => 'persija-muda-fc',
            'logo_path' => 'logos/persija-muda.png',
            'origin_association' => 'Jakarta Timur',
            'manager_name' => 'Bambang Pamungkas',
            'manager_whatsapp' => '081299998888',
            'manager_email' => $manager->email,
            'head_coach_name' => 'Coach Sudirman',
            'terms_accepted_at' => now(),
            'slot_reserved_at' => now(),
            'status' => 'active',
            'screening_status' => 'in_review',
        ]);

        $player = Player::create([
            'team_id' => $team->id,
            'event_id' => $event->id,
            'full_name' => 'Muhammad Rizky Pratama',
            'jersey_name' => 'M. RIZKY',
            'jersey_number' => 10,
            'position' => 'FW',
            'dominant_foot' => 'Right',
            'birth_place' => 'Jakarta',
            'birth_date' => '2014-05-15',
            'nik' => '3171010101010001',
            'screening_status' => 'submitted',
            'is_locked' => false,
        ]);

        $doc = PlayerDocument::create([
            'player_id' => $player->id,
            'document_type' => 'birth_certificate',
            'document_number' => '12345/DISDUK/2014',
            'file_path' => 'player_documents/test.jpg',
            'original_name' => 'akta_kelahiran_rizky.jpg',
            'file_size' => 102400,
            'mime_type' => 'image/jpeg',
            'validation_status' => 'pending',
        ]);

        return compact('superadmin', 'screeningOfficer', 'manager', 'event', 'team', 'player', 'doc');
    }

    public function test_screening_officer_and_superadmin_can_access_screening_queue_and_team_review(): void
    {
        $env = $this->createScreeningEnvironment();

        // 1. Screening Officer can view screening index
        $response = $this->actingAs($env['screeningOfficer'])->get(route('admin.screening.index'));
        $response->assertOk();

        // 2. Screening Officer can view team review
        $reviewResponse = $this->actingAs($env['screeningOfficer'])->get(route('admin.screening.team', [
            'event' => $env['event']->id,
            'team' => $env['team']->id,
        ]));
        $reviewResponse->assertOk();

        // 3. Superadmin can also view
        $superadminResponse = $this->actingAs($env['superadmin'])->get(route('admin.screening.index'));
        $superadminResponse->assertOk();

        // 4. Team Manager is redirected away from admin screening portal
        $managerResponse = $this->actingAs($env['manager'])->get(route('admin.screening.index'));
        $managerResponse->assertRedirect(route('manager.dashboard'));
    }

    public function test_screening_officer_can_approve_player_and_lock_data(): void
    {
        $env = $this->createScreeningEnvironment();
        $player = $env['player'];

        $response = $this->actingAs($env['screeningOfficer'])->post(route('admin.screening.player.verify', ['player' => $player->id]), [
            'action' => 'passed',
        ]);

        $response->assertSessionHas('success');
        $player->refresh();

        $this->assertSame('passed', $player->screening_status);
        $this->assertTrue($player->is_locked);
        $this->assertNotNull($player->accreditation_code);
        $this->assertStringStartsWith('YT-', $player->accreditation_code);
        $this->assertSame($env['screeningOfficer']->id, $player->verified_by);
        $this->assertNotNull($player->verified_at);

        // Verify document status updated to valid
        $doc = $env['doc']->fresh();
        $this->assertSame('valid', $doc->validation_status);

        // Verify audit log created
        $this->assertDatabaseHas('screening_logs', [
            'player_id' => $player->id,
            'actor_id' => $env['screeningOfficer']->id,
            'from_status' => 'submitted',
            'to_status' => 'passed',
        ]);
    }

    public function test_screening_officer_can_reject_player_with_reason(): void
    {
        $env = $this->createScreeningEnvironment();
        $player = $env['player'];

        // Rejection without reason fails validation
        $failResponse = $this->actingAs($env['screeningOfficer'])->post(route('admin.screening.player.verify', ['player' => $player->id]), [
            'action' => 'rejected',
            'rejection_reason' => '',
        ]);
        $failResponse->assertSessionHasErrors('rejection_reason');

        // Rejection with valid reason succeeds
        $reason = 'Scan akta kelahiran buram pada bagian tanggal lahir. Mohon unggah ulang.';
        $response = $this->actingAs($env['screeningOfficer'])->post(route('admin.screening.player.verify', ['player' => $player->id]), [
            'action' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $response->assertSessionHas('success');
        $player->refresh();

        $this->assertSame('rejected', $player->screening_status);
        $this->assertFalse($player->is_locked);
        $this->assertSame($reason, $player->rejection_reason);

        // Team status transitions to partially_rejected
        $this->assertSame('partially_rejected', $env['team']->fresh()->screening_status);

        // Verify audit log created
        $this->assertDatabaseHas('screening_logs', [
            'player_id' => $player->id,
            'actor_id' => $env['screeningOfficer']->id,
            'from_status' => 'submitted',
            'to_status' => 'rejected',
            'reason' => $reason,
        ]);
    }

    public function test_screening_officer_can_verify_individual_document(): void
    {
        $env = $this->createScreeningEnvironment();
        $doc = $env['doc'];

        $response = $this->actingAs($env['screeningOfficer'])->post(route('admin.screening.document.verify', ['document' => $doc->id]), [
            'validation_status' => 'valid',
            'notes' => 'Nomor akta cocok dengan data kependudukan.',
        ]);

        $response->assertSessionHas('success');
        $doc->refresh();

        $this->assertSame('valid', $doc->validation_status);
        $this->assertSame('Nomor akta cocok dengan data kependudukan.', $doc->notes);
    }

    public function test_officer_can_unlock_player(): void
    {
        $env = $this->createScreeningEnvironment();
        $player = $env['player'];

        // 1. Pass player first
        $player->update([
            'screening_status' => 'passed',
            'is_locked' => true,
            'accreditation_code' => 'YT-E01-PL0001-TESTCODE',
        ]);

        // 2. Unlock player
        $response = $this->actingAs($env['screeningOfficer'])->post(route('admin.screening.player.unlock', ['player' => $player->id]), [
            'reason' => 'Perbaikan nomor punggung',
        ]);

        $response->assertSessionHas('success');
        $player->refresh();

        $this->assertSame('draft', $player->screening_status);
        $this->assertFalse($player->is_locked);

        // Verify audit log
        $this->assertDatabaseHas('screening_logs', [
            'player_id' => $player->id,
            'actor_id' => $env['screeningOfficer']->id,
            'from_status' => 'passed',
            'to_status' => 'draft',
            'reason' => 'Perbaikan nomor punggung',
        ]);
    }
}
