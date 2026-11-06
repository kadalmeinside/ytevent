<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlayerManagementTest extends TestCase
{
    protected Event $event;
    protected User $managerA;
    protected Team $teamA;
    protected User $managerB;
    protected Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');

        $this->event = Event::create([
            'name' => 'Piala Pelajar U-15 2026',
            'slug' => 'piala-pelajar-u15-2026',
            'venue_name' => 'Stadion Siliwangi',
            'city' => 'Bandung',
            'format' => 'cup',
            'age_category' => 'U-15',
            'min_birth_date' => '2011-01-01',
            'max_birth_date' => '2012-12-31',
            'min_players_per_team' => 14,
            'max_players_per_team' => 20,
            'max_officials_per_team' => 5,
            'required_documents' => ['birth_certificate', 'family_card'],
            'max_teams' => 8,
            'registration_fee' => 1000000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
        ]);

        // Manajer & Tim A
        $this->managerA = User::factory()->create([
            'email' => 'managerA@tim-a.id',
            'phone' => '081211112222',
        ]);
        $this->managerA->assignRole('team_manager');

        $this->teamA = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $this->managerA->id,
            'name' => 'Akademi Elang Putih',
            'slug' => 'akademi-elang-putih',
            'logo_path' => 'teams/logos/teamA.png',
            'origin_association' => 'Askot Bandung',
            'manager_name' => $this->managerA->name,
            'manager_whatsapp' => '081211112222',
            'manager_email' => $this->managerA->email,
            'head_coach_name' => 'Coach Dedi',
            'terms_accepted_at' => now(),
            'status' => 'pending_payment',
        ]);

        Payment::create([
            'invoice_number' => 'INV-2026-EVT01-0001',
            'team_id' => $this->teamA->id,
            'event_id' => $this->event->id,
            'amount' => 1000000,
            'total_amount' => 1000000,
            'status' => 'unpaid',
        ]);

        // Manajer & Tim B (Sudah Lunas)
        $this->managerB = User::factory()->create([
            'email' => 'managerB@tim-b.id',
            'phone' => '081233334444',
        ]);
        $this->managerB->assignRole('team_manager');

        $this->teamB = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $this->managerB->id,
            'name' => 'Klub Macan Kemayoran Jr',
            'slug' => 'klub-macan-kemayoran-jr',
            'logo_path' => 'teams/logos/teamB.png',
            'origin_association' => 'Askot Bandung',
            'manager_name' => $this->managerB->name,
            'manager_whatsapp' => '081233334444',
            'manager_email' => $this->managerB->email,
            'head_coach_name' => 'Coach Bambang',
            'terms_accepted_at' => now(),
            'status' => 'active',
        ]);

        Payment::create([
            'invoice_number' => 'INV-2026-EVT01-0002',
            'team_id' => $this->teamB->id,
            'event_id' => $this->event->id,
            'amount' => 1000000,
            'total_amount' => 1000000,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function test_player_input_is_locked_when_team_is_unpaid(): void
    {
        $response = $this->actingAs($this->managerA)->post(
            route('manager.players.store', ['team' => $this->teamA->id]),
            [
                'full_name' => 'Ahmad Fauzi',
                'jersey_number' => 10,
                'position' => 'FW',
                'dominant_foot' => 'Right',
                'birth_date' => '2011-05-12',
                'nik' => '3201123456789001',
                'photo' => UploadedFile::fake()->image('fauzi.jpg'),
            ]
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('players', ['full_name' => 'Ahmad Fauzi']);
    }

    public function test_player_input_is_allowed_once_team_is_active(): void
    {
        $photo = UploadedFile::fake()->image('budi.jpg', 300, 400);

        $response = $this->actingAs($this->managerB)->post(
            route('manager.players.store', ['team' => $this->teamB->id]),
            [
                'full_name' => 'Budi Pratama',
                'jersey_name' => 'BUDI',
                'jersey_number' => 9,
                'position' => 'FW',
                'dominant_foot' => 'Right',
                'birth_place' => 'Bandung',
                'birth_date' => '2011-07-20',
                'nik' => '3201123456789010',
                'photo' => $photo,
            ]
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('players', [
            'team_id' => $this->teamB->id,
            'full_name' => 'Budi Pratama',
            'jersey_number' => 9,
            'position' => 'FW',
            'screening_status' => 'draft',
        ]);

        $player = Player::where('full_name', 'Budi Pratama')->first();
        $this->assertNotEmpty($player->photo_path);
        Storage::disk('private')->assertExists($player->photo_path);
    }

    public function test_age_cut_off_filter_rejects_ineligible_birth_dates(): void
    {
        // Event mewajibkan kelahiran 2011-01-01 s/d 2012-12-31.
        // Coba daftarkan pemain kelahiran 2009 (terlalu tua)
        $response = $this->actingAs($this->managerB)->post(
            route('manager.players.store', ['team' => $this->teamB->id]),
            [
                'full_name' => 'Pemain Terlalu Tua',
                'jersey_number' => 7,
                'position' => 'MF',
                'dominant_foot' => 'Right',
                'birth_date' => '2009-03-15',
                'nik' => '3201123456789099',
                'photo' => UploadedFile::fake()->image('foto.jpg'),
            ]
        );

        $response->assertSessionHasErrors(['birth_date']);
        $this->assertDatabaseMissing('players', ['full_name' => 'Pemain Terlalu Tua']);
    }

    public function test_nik_duplicate_lock_prevents_same_player_in_two_teams_in_same_event(): void
    {
        // Daftarkan pemain di Tim B
        Player::create([
            'team_id' => $this->teamB->id,
            'event_id' => $this->event->id,
            'full_name' => 'Deni Permana',
            'jersey_number' => 11,
            'position' => 'MF',
            'dominant_foot' => 'Left',
            'birth_date' => '2011-08-10',
            'nik' => '3201123456789888',
            'photo_path' => 'players/photos/deni.jpg',
            'screening_status' => 'draft',
        ]);

        // Aktifkan Tim A
        $this->teamA->update(['status' => 'active']);

        // Manajer A mencoba mendaftarkan pemain dengan NIK yang sama
        $response = $this->actingAs($this->managerA)->post(
            route('manager.players.store', ['team' => $this->teamA->id]),
            [
                'full_name' => 'Deni Kloning',
                'jersey_number' => 11,
                'position' => 'MF',
                'dominant_foot' => 'Right',
                'birth_date' => '2011-08-10',
                'nik' => '3201123456789888', // NIK yang sama persis
                'photo' => UploadedFile::fake()->image('deni_clone.jpg'),
            ]
        );

        $response->assertSessionHasErrors(['nik']);
        $this->assertDatabaseMissing('players', ['full_name' => 'Deni Kloning']);
    }

    public function test_player_documents_upload_and_submission_for_screening(): void
    {
        $player = Player::create([
            'team_id' => $this->teamB->id,
            'event_id' => $this->event->id,
            'full_name' => 'Rizky Alamsyah',
            'jersey_number' => 1,
            'position' => 'GK',
            'dominant_foot' => 'Right',
            'birth_date' => '2011-04-05',
            'nik' => '3201123456789777',
            'photo_path' => 'players/photos/rizky.jpg',
            'screening_status' => 'draft',
        ]);

        // Coba submit screening sebelum dokumen diunggah -> gagal
        $response = $this->actingAs($this->managerB)->post(
            route('manager.players.submit', ['player' => $player->id])
        );
        $response->assertSessionHasErrors(['submission']);
        $this->assertEquals('draft', $player->fresh()->screening_status);

        // Unggah Akta Kelahiran
        $akta = UploadedFile::fake()->create('akta_lahir.pdf', 500, 'application/pdf');
        $this->actingAs($this->managerB)->post(
            route('manager.players.documents.store', ['player' => $player->id]),
            [
                'document_type' => 'birth_certificate',
                'document_number' => 'AKTA-123456',
                'file' => $akta,
            ]
        )->assertSessionHas('success');

        // Unggah Kartu Keluarga
        $kk = UploadedFile::fake()->image('kartu_keluarga.jpg', 800, 600);
        $this->actingAs($this->managerB)->post(
            route('manager.players.documents.store', ['player' => $player->id]),
            [
                'document_type' => 'family_card',
                'document_number' => '3201000000000001',
                'file' => $kk,
            ]
        )->assertSessionHas('success');

        $this->assertEquals(2, $player->documents()->count());
        $this->assertTrue($player->fresh()->isCompleteForSubmission());

        // Update nomor dokumen yang sudah ada tanpa unggah ulang file
        $this->actingAs($this->managerB)->post(
            route('manager.players.documents.store', ['player' => $player->id]),
            [
                'document_type' => 'birth_certificate',
                'document_number' => 'AKTA-654321-REVISED',
            ]
        )->assertSessionHas('success');

        $this->assertDatabaseHas('player_documents', [
            'player_id' => $player->id,
            'document_type' => 'birth_certificate',
            'document_number' => 'AKTA-654321-REVISED',
        ]);

        // Submit kembali setelah lengkap
        $submitResponse = $this->actingAs($this->managerB)->post(
            route('manager.players.submit', ['player' => $player->id])
        );

        $submitResponse->assertSessionHas('success');
        $this->assertEquals('submitted', $player->fresh()->screening_status);
        $this->assertDatabaseHas('screening_logs', [
            'player_id' => $player->id,
            'to_status' => 'submitted',
        ]);

        // Saat status 'submitted', form terkunci dari direct update (403 Forbidden jika mencoba bypass UI)
        $aktaBaru = UploadedFile::fake()->create('akta_lahir_revisi.pdf', 600, 'application/pdf');
        $blockedResponse = $this->actingAs($this->managerB)->post(
            route('manager.players.documents.store', ['player' => $player->id]),
            [
                'document_type' => 'birth_certificate',
                'document_number' => 'AKTA-REVISI-FINAL',
                'file' => $aktaBaru,
            ]
        );
        $blockedResponse->assertForbidden();

        // Manajer menarik pengajuan pemain (Recall to Draft) terlebih dahulu
        $recallResponse = $this->actingAs($this->managerB)->post(
            route('manager.players.recall', ['player' => $player->id])
        );
        $recallResponse->assertSessionHas('success');
        $this->assertEquals('draft', $player->fresh()->screening_status);
        $this->assertDatabaseHas('screening_logs', [
            'player_id' => $player->id,
            'from_status' => 'submitted',
            'to_status' => 'draft',
        ]);

        // Setelah ditarik ke draft, berkas baru dapat diperbarui dengan sukses
        $replaceResponse = $this->actingAs($this->managerB)->post(
            route('manager.players.documents.store', ['player' => $player->id]),
            [
                'document_type' => 'birth_certificate',
                'document_number' => 'AKTA-REVISI-FINAL',
                'file' => $aktaBaru,
            ]
        );
        $replaceResponse->assertSessionHas('success');
        $this->assertDatabaseHas('player_documents', [
            'player_id' => $player->id,
            'document_type' => 'birth_certificate',
            'document_number' => 'AKTA-REVISI-FINAL',
            'original_name' => 'akta_lahir_revisi.pdf',
        ]);
    }

    public function test_manager_cannot_access_or_tamper_with_other_teams_players(): void
    {
        $playerB = Player::create([
            'team_id' => $this->teamB->id,
            'event_id' => $this->event->id,
            'full_name' => 'Pemain Milik Tim B',
            'jersey_number' => 8,
            'position' => 'MF',
            'dominant_foot' => 'Right',
            'birth_date' => '2011-09-09',
            'nik' => '3201123456789555',
            'photo_path' => 'players/photos/playerB.jpg',
            'screening_status' => 'draft',
        ]);

        Storage::disk('private')->put('players/photos/playerB.jpg', 'fake-photo-content');

        // Manajer A mencoba hapus pemain Tim B -> 403 Forbidden
        $response = $this->actingAs($this->managerA)->delete(
            route('manager.players.destroy', ['player' => $playerB->id])
        );
        $response->assertForbidden();

        // Manajer A mencoba akses foto privat pemain Tim B -> 403 Forbidden
        $photoResponse = $this->actingAs($this->managerA)->get(
            route('private.player-photo', ['player' => $playerB->id])
        );
        $photoResponse->assertForbidden();

        // Manajer B (pemilik sah) bisa mengakses fotonya
        $photoAllowed = $this->actingAs($this->managerB)->get(
            route('private.player-photo', ['player' => $playerB->id])
        );
        $photoAllowed->assertOk();
    }
}

