<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamRegistrationAndQuotaTest extends TestCase
{
    protected Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');

        $this->event = Event::create([
            'name' => 'Turnamen U-15 Mini Cup',
            'slug' => 'turnamen-u15-mini-cup',
            'venue_name' => 'Stadion Mini',
            'city' => 'Jakarta Selatan',
            'format' => 'cup',
            'age_category' => 'U-15',
            'min_birth_date' => '2011-01-01',
            'max_birth_date' => '2012-12-31',
            'min_players_per_team' => 14,
            'max_players_per_team' => 20,
            'max_officials_per_team' => 5,
            'required_documents' => ['birth_certificate', 'family_card'],
            'max_teams' => 2,
            'registration_fee' => 1000000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
        ]);
    }

    public function test_guest_can_register_team_and_create_manager_account(): void
    {
        $logo = UploadedFile::fake()->image('team_logo.png', 200, 200);

        $response = $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'SSB Rajawali Emas',
            'origin_association' => 'Askot PSSI Jakarta Selatan',
            'manager_name' => 'Bambang Sutrisno',
            'email' => 'bambang@rajawali.id',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081234567890',
            'head_coach_name' => 'Coach Indra',
            'terms_accepted' => '1',
            'logo' => $logo,
        ]);

        $response->assertRedirect(route('manager.payments'));

        $this->assertDatabaseHas('users', [
            'email' => 'bambang@rajawali.id',
            'name' => 'Bambang Sutrisno',
        ]);

        $team = Team::where('name', 'SSB Rajawali Emas')->firstOrFail();
        $this->assertDatabaseHas('teams', [
            'name' => 'SSB Rajawali Emas',
            'event_id' => $this->event->id,
            'status' => 'pending_payment',
        ]);
        $this->assertNotNull($team->logo_path);
        $this->assertNotNull($team->logo_optimized_path);
        Storage::disk('public')->assertExists($team->logo_path);
        Storage::disk('public')->assertExists($team->logo_optimized_path);

        $this->assertDatabaseHas('payments', [
            'event_id' => $this->event->id,
            'amount' => 1000000,
            'status' => 'unpaid',
        ]);

        $this->assertEquals(1, $this->event->fresh()->occupiedSlotsCount());
        $this->assertEquals('open', $this->event->fresh()->status);
    }

    public function test_event_transitions_to_quota_full_when_last_slot_is_taken(): void
    {
        // Pendaftar 1
        $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'Tim Pertama',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Manajer Satu',
            'email' => 'satu@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081211112222',
            'head_coach_name' => 'Coach Satu',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo1.png'),
        ]);

        Auth::logout();
        $this->flushSession();

        // Pendaftar 2 (slot terakhir dari kuota 2)
        $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'Tim Kedua',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Manajer Dua',
            'email' => 'dua@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081233334444',
            'head_coach_name' => 'Coach Dua',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo2.png'),
        ]);

        $this->assertEquals(2, $this->event->fresh()->occupiedSlotsCount());
        $this->assertEquals('quota_full', $this->event->fresh()->status);

        Auth::logout();
        $this->flushSession();

        // Pendaftar 3 mencoba mendaftar saat kuota penuh
        $response = $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'Tim Ketiga',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Manajer Tiga',
            'email' => 'tiga@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081255556666',
            'head_coach_name' => 'Coach Tiga',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo3.png'),
        ]);

        $response->assertSessionHasErrors(['event_quota']);
        $this->assertDatabaseMissing('teams', ['name' => 'Tim Ketiga']);
    }

    public function test_lazy_expiration_frees_slot_during_subsequent_registration(): void
    {
        // Tim 1 mengisi slot 1
        $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'Tim Satu',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Manajer Satu',
            'email' => 'satu@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081211112222',
            'head_coach_name' => 'Coach Satu',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo1.png'),
        ]);

        Auth::logout();
        $this->flushSession();

        // Tim 2 mengisi slot 2 (kuota penuh)
        $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'Tim Dua',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Manajer Dua',
            'email' => 'dua@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081233334444',
            'head_coach_name' => 'Coach Dua',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo2.png'),
        ]);

        $this->assertEquals('quota_full', $this->event->fresh()->status);

        Auth::logout();
        $this->flushSession();

        // Simulasikan waktu berlalu melebihi expires_at untuk Tim 2
        $team2 = Team::where('name', 'Tim Dua')->first();
        $team2->update(['slot_expires_at' => now()->subMinutes(10)]);
        $team2->payments()->update(['expires_at' => now()->subMinutes(10)]);

        // Tim 3 mendaftar -> Lazy expiration menyapu Tim 2 menjadi expired sehingga slot terbuka untuk Tim 3
        $response = $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'Tim Tiga',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Manajer Tiga',
            'email' => 'tiga@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081255556666',
            'head_coach_name' => 'Coach Tiga',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo3.png'),
        ]);

        $response->assertRedirect(route('manager.payments'));

        $this->assertEquals('expired', $team2->fresh()->status);
        $this->assertDatabaseHas('teams', ['name' => 'Tim Tiga', 'status' => 'pending_payment']);
        $this->assertEquals(2, $this->event->fresh()->occupiedSlotsCount());
    }

    public function test_scheduled_command_expires_unpaid_and_restores_open_status(): void
    {
        // Tim 1 mengisi slot 1
        $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'Tim Satu',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Manajer Satu',
            'email' => 'satu@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081211112222',
            'head_coach_name' => 'Coach Satu',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo1.png'),
        ]);

        Auth::logout();
        $this->flushSession();

        // Tim 2 mengisi slot 2
        $this->post(route('events.register.store', ['event' => $this->event->slug]), [
            'team_name' => 'Tim Dua',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Manajer Dua',
            'email' => 'dua@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081233334444',
            'head_coach_name' => 'Coach Dua',
            'terms_accepted' => '1',
            'logo' => UploadedFile::fake()->image('logo2.png'),
        ]);

        $this->assertEquals('quota_full', $this->event->fresh()->status);

        Auth::logout();
        $this->flushSession();

        $team2 = Team::where('name', 'Tim Dua')->first();
        $team2->update(['slot_expires_at' => now()->subHour()]);
        $team2->payments()->update(['expires_at' => now()->subHour()]);

        // Jalankan scheduled command
        $this->artisan('app:expire-unpaid-registrations')->assertSuccessful();

        $this->assertEquals('expired', $team2->fresh()->status);
        $this->assertEquals('open', $this->event->fresh()->status);
        $this->assertEquals(1, $this->event->fresh()->occupiedSlotsCount());
    }

    public function test_manager_can_reclaim_expired_slot(): void
    {
        $team = Team::create([
            'event_id' => $this->event->id,
            'user_id' => User::factory()->create()->id,
            'name' => 'Tim Kadaluwarsa',
            'slug' => 'tim-kadaluwarsa',
            'logo_path' => 'teams/logos/sample.png',
            'origin_association' => 'Askot Jakarta',
            'manager_name' => 'Budi',
            'manager_whatsapp' => '081234567890',
            'manager_email' => 'budi@example.com',
            'head_coach_name' => 'Coach Budi',
            'terms_accepted_at' => now(),
            'status' => 'expired',
            'slot_expires_at' => now()->subHours(2),
        ]);

        $manager = $team->manager;
        $manager->assignRole('team_manager');

        $response = $this->actingAs($manager)->post(route('manager.payments.reclaim-slot', ['team' => $team->id]));

        $response->assertSessionHas('success');
        $this->assertEquals('pending_payment', $team->fresh()->status);
        $this->assertTrue($team->fresh()->slot_expires_at->gt(now()));
        $this->assertDatabaseHas('payments', [
            'team_id' => $team->id,
            'status' => 'unpaid',
        ]);
    }

    public function test_event_can_store_and_display_venue_maps_url_and_rich_description(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $this->event->update([
            'short_description' => '<p><strong>Panduan Resmi:</strong> Syarat pendaftaran wajib ditaati.</p>',
            'venue_maps_url' => 'https://maps.google.com/?q=Persija+Training+Ground',
        ]);

        $response = $this->get(route('events.show', ['event' => $this->event->slug]));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/EventDetail')
            ->where('event.venue_maps_url', 'https://maps.google.com/?q=Persija+Training+Ground')
        );
    }

    public function test_admin_can_resync_all_slots_and_specific_event_slots(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $manager = User::factory()->create();
        $manager->assignRole('team_manager');

        $team = Team::create([
            'event_id' => $this->event->id,
            'user_id' => $manager->id,
            'name' => 'Persikabo Muda',
            'slug' => 'persikabo-muda',
            'logo_path' => 'logos/dummy.png',
            'origin_association' => 'PSSI',
            'manager_name' => 'Manager A',
            'manager_email' => 'manager.a@test.id',
            'manager_whatsapp' => '081234567899',
            'head_coach_name' => 'Coach Dedi',
            'terms_accepted_at' => now(),
            'status' => 'pending_payment',
            'slot_expires_at' => now()->subMinute(),
        ]);

        Payment::create([
            'team_id' => $team->id,
            'event_id' => $this->event->id,
            'invoice_number' => 'INV-TEST-RESYNC-01',
            'amount' => 1000000,
            'fee_amount' => 0,
            'total_amount' => 1000000,
            'status' => 'unpaid',
            'expires_at' => now()->subMinute(),
        ]);

        // Resync specific event
        $resEvent = $this->actingAs($admin)->post(route('admin.events.resync-event-slots', ['event' => $this->event->id]));
        $resEvent->assertSessionHas('success');
        $this->assertEquals('expired', $team->fresh()->status);

        // Resync all events
        $resAll = $this->actingAs($admin)->post(route('admin.events.resync-slots'));
        $resAll->assertSessionHas('info');
    }
}
