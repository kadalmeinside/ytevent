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
use Illuminate\Http\UploadedFile;
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
            'photo_path' => 'player_photos/rizky.jpg',
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

        // Repeat approve call (idempotent guard): should not create duplicate screening log
        $this->actingAs($env['screeningOfficer'])->post(route('admin.screening.player.verify', [
            'player' => $player->id,
        ]), [
            'action' => 'passed',
        ]);

        $this->assertEquals(1, \App\Models\ScreeningLog::where('player_id', $player->id)->where('to_status', 'passed')->count());
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

    public function test_manager_can_edit_and_reupload_documents_for_rejected_player(): void
    {
        $env = $this->createScreeningEnvironment();
        $manager = $env['manager'];
        $player = $env['player'];
        $doc = $env['doc'];

        // 1. Mark player as rejected with reason and invalid doc note
        $player->update([
            'screening_status' => 'rejected',
            'rejection_reason' => 'Scan akta buram dan nomor punggung ganda.',
            'is_locked' => false,
        ]);
        $doc->update([
            'validation_status' => 'invalid',
            'notes' => 'Teks akta tidak terbaca.',
        ]);

        // 2. Manager edits player data
        $updateResponse = $this->actingAs($manager)->post(route('manager.players.update', ['player' => $player->id]), [
            'full_name' => 'Muhammad Rizky Pratama',
            'jersey_name' => 'RIZKY P',
            'jersey_number' => 11,
            'position' => 'FW',
            'dominant_foot' => 'Right',
            'birth_place' => 'Jakarta Timur',
            'birth_date' => '2015-06-15',
            'nik' => '3171011506150001',
        ]);

        $updateResponse->assertSessionHas('success');
        $player->refresh();
        $this->assertSame('Muhammad Rizky Pratama', $player->full_name);
        $this->assertSame(11, $player->jersey_number);

        // 3. Manager re-uploads replacement document
        $newFile = UploadedFile::fake()->create('akta_perbaikan.pdf', 800, 'application/pdf');
        $uploadResponse = $this->actingAs($manager)->post(route('manager.players.documents.store', ['player' => $player->id]), [
            'document_type' => 'birth_certificate',
            'document_number' => 'AKTA-NEW-12345',
            'file' => $newFile,
        ]);

        $uploadResponse->assertSessionHas('success');
        $doc->refresh();
        $this->assertSame('pending', $doc->validation_status);
        $this->assertNull($doc->notes);
        $this->assertSame('AKTA-NEW-12345', $doc->document_number);

        // 4. Manager resubmits player for screening
        $submitResponse = $this->actingAs($manager)->post(route('manager.players.submit', ['player' => $player->id]));
        $submitResponse->assertSessionHas('success');

        $player->refresh();
        $this->assertSame('submitted', $player->screening_status);
        $this->assertNull($player->rejection_reason);

        // Verify team screening status is in_review
        $env['team']->refresh();
        $this->assertSame('in_review', $env['team']->screening_status);

        // Verify audit log has from_status: rejected -> to_status: submitted
        $this->assertDatabaseHas('screening_logs', [
            'player_id' => $player->id,
            'actor_id' => $manager->id,
            'from_status' => 'rejected',
            'to_status' => 'submitted',
        ]);
    }

    public function test_manager_cannot_modify_passed_or_locked_player(): void
    {
        $env = $this->createScreeningEnvironment();
        $manager = $env['manager'];
        $player = $env['player'];

        $player->update([
            'screening_status' => 'passed',
            'is_locked' => true,
        ]);

        // Attempt to update
        $updateResponse = $this->actingAs($manager)->post(route('manager.players.update', ['player' => $player->id]), [
            'full_name' => 'Hacker Name',
            'jersey_number' => 99,
            'position' => 'FW',
            'dominant_foot' => 'Right',
            'birth_date' => '2015-06-15',
            'nik' => '3171011506150001',
        ]);

        $updateResponse->assertForbidden();

        // Attempt to upload doc
        $fakeFile = UploadedFile::fake()->create('hacked.pdf', 100, 'application/pdf');
        $uploadResponse = $this->actingAs($manager)->post(route('manager.players.documents.store', ['player' => $player->id]), [
            'document_type' => 'birth_certificate',
            'file' => $fakeFile,
        ]);

        $uploadResponse->assertForbidden();
    }

    public function test_marking_single_document_as_invalid_automatically_unlocks_revision_without_manual_recall(): void
    {
        $env = $this->createScreeningEnvironment();
        $manager = $env['manager'];
        $player = $env['player'];
        $doc = $env['doc'];

        // 1. Officer marks the single document as invalid ("Butuh Revisi") without calling verifyPlayer separately
        $verifyDocResponse = $this->actingAs($env['screeningOfficer'])->post(
            route('admin.screening.document.verify', ['document' => $doc->id]),
            [
                'validation_status' => 'invalid',
                'notes' => 'Foto akta kelahiran terpotong bagian bawah.',
            ]
        );

        $verifyDocResponse->assertSessionHas('success');
        $doc->refresh();
        $player->refresh();
        $env['team']->refresh();

        $this->assertSame('invalid', $doc->validation_status);
        $this->assertSame('rejected', $player->screening_status);
        $this->assertFalse($player->is_locked);
        $this->assertStringContainsString('Foto akta kelahiran terpotong bagian bawah.', (string) $player->rejection_reason);
        $this->assertSame('partially_rejected', $env['team']->screening_status);

        // 2. Manager cannot resubmit yet while the invalid document has not been replaced
        $prematureSubmit = $this->actingAs($manager)->post(route('manager.players.submit', ['player' => $player->id]));
        $prematureSubmit->assertSessionHasErrors('submission');

        // 3. Manager immediately re-uploads the rejected document AND triggers 1-click resubmit (without manual unlock/recall)
        $replacementFile = UploadedFile::fake()->create('akta_revisi_utuh.pdf', 500, 'application/pdf');
        $reuploadResponse = $this->actingAs($manager)->post(
            route('manager.players.documents.store', ['player' => $player->id]),
            [
                'document_type' => 'birth_certificate',
                'document_number' => 'AKTA-REVISI-999',
                'file' => $replacementFile,
                'resubmit' => true,
            ]
        );

        $reuploadResponse->assertSessionHas('success');
        $doc->refresh();
        $player->refresh();

        $this->assertSame('pending', $doc->validation_status);
        $this->assertNull($doc->notes);
        $this->assertSame('submitted', $player->screening_status);
        $this->assertNull($player->rejection_reason);
    }
}
