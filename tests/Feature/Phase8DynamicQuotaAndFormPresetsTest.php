<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\TeamRegistrationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Phase8DynamicQuotaAndFormPresetsTest extends TestCase
{
    protected User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');

        Role::firstOrCreate(['name' => 'superadmin']);
        Role::firstOrCreate(['name' => 'team_manager']);

        $this->superadmin = User::firstOrCreate(
            ['email' => 'superadmin.test@persijadevelopment.id'],
            [
                'name' => 'Super Admin Tester',
                'password' => bcrypt('Admin12345!'),
                'is_active' => true,
            ]
        );

        if (! $this->superadmin->hasRole('superadmin')) {
            $this->superadmin->assignRole('superadmin');
        }
    }

    public function test_superadmin_can_create_event_with_fixed_quota_and_form_presets(): void
    {
        $payload = [
            'name' => 'Persija Talent Audition U-12',
            'city' => 'Jakarta Timur',
            'venue_name' => 'Nirwana Park Sawangan',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 120,
            'max_teams' => null,
            'format' => 'festival',
            'age_category' => 'U-12',
            'min_players_per_team' => 10,
            'max_players_per_team' => 18,
            'max_officials_per_team' => 3,
            'required_documents' => ['birth_certificate', 'family_card'],
            'registration_fee' => 150000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
            'form_presets' => [
                'position' => true,
                'foot_dominance' => true,
                'team_origin' => true,
                'body_measurements' => true,
                'experience' => true,
                'jersey_size' => true,
            ],
            'custom_fields_schema' => [
                [
                    'key' => 'emergency_relation',
                    'label' => 'Hubungan Kontak Darurat',
                    'type' => 'text',
                    'required' => true,
                ],
            ],
        ];

        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.events.store'), $payload);

        $response->assertRedirect(route('admin.events.index'));

        $this->assertDatabaseHas('events', [
            'name' => 'Persija Talent Audition U-12',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 120,
        ]);

        $created = Event::where('name', 'Persija Talent Audition U-12')->firstOrFail();
        $this->assertTrue($created->isIndividual());
        $this->assertFalse($created->isUnlimitedQuota());
        $this->assertEquals(120, $created->availableSlotsCount());
        $this->assertTrue($created->form_presets['jersey_size'] ?? false);
    }

    public function test_superadmin_can_create_event_with_unlimited_quota(): void
    {
        $payload = [
            'name' => 'Persija Coaching Clinic 2026',
            'city' => 'Jakarta Selatan',
            'venue_name' => 'Gelora Bung Karno',
            'event_type' => 'coaching_clinic',
            'participant_type' => 'individual',
            'quota_type' => 'unlimited',
            'format' => 'festival',
            'age_category' => 'Senior / Coach',
            'min_players_per_team' => 5,
            'max_players_per_team' => 10,
            'max_officials_per_team' => 2,
            'required_documents' => ['national_id_ktp'],
            'registration_fee' => 500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 48,
            'reupload_grace_hours' => 12,
            'status' => 'open',
            'registration_open_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'registration_close_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
        ];

        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.events.store'), $payload);

        $response->assertRedirect(route('admin.events.index'));

        $this->assertDatabaseHas('events', [
            'name' => 'Persija Coaching Clinic 2026',
            'event_type' => 'coaching_clinic',
            'quota_type' => 'unlimited',
        ]);

        $event = Event::where('name', 'Persija Coaching Clinic 2026')->firstOrFail();
        $this->assertTrue($event->isUnlimitedQuota());
        $this->assertNull($event->availableSlotsCount());
        $this->assertTrue($event->canAcceptRegistration());
    }

    public function test_unlimited_quota_event_accepts_registration_beyond_arbitrary_limits(): void
    {
        $event = Event::create([
            'name' => 'Grassroots Open Festival 2026',
            'slug' => 'grassroots-open-festival-2026',
            'city' => 'Jakarta Barat',
            'venue_name' => 'Lapangan Cendrawasih',
            'format' => 'festival',
            'event_type' => 'festival',
            'participant_type' => 'team',
            'quota_type' => 'unlimited',
            'max_teams' => null,
            'age_category' => 'U-10',
            'min_players_per_team' => 7,
            'max_players_per_team' => 12,
            'max_officials_per_team' => 2,
            'required_documents' => ['birth_certificate'],
            'registration_fee' => 0,
            'lock_roster_until_paid' => false,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
            'registration_open_at' => now()->subDay(),
            'registration_close_at' => now()->addDays(7),
        ]);

        $service = app(TeamRegistrationService::class);

        for ($i = 1; $i <= 5; $i++) {
            $logo = UploadedFile::fake()->image("team_{$i}.png", 200, 200);
            $team = $service->registerNewTeam($event, [
                'team_name' => "SSB Garuda {$i}",
                'origin_association' => 'Askot PSSI Jakbar',
                'manager_name' => "Manager {$i}",
                'manager_whatsapp' => "08123456789{$i}",
                'email' => "manager{$i}@example.com",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'head_coach_name' => "Coach {$i}",
                'terms_accepted' => true,
            ], $logo);

            $this->assertNotNull($team->id, "Registrasi ke-{$i} gagal.");
        }

        $event->refresh();
        $this->assertEquals(5, $event->teams()->count());
        $this->assertEquals('open', $event->status);
        $this->assertTrue($event->canAcceptRegistration());
    }

    public function test_public_event_detail_provides_unlimited_quota_and_presets_metadata(): void
    {
        $event = Event::create([
            'name' => 'Jakarta Talent Showcase 2026',
            'slug' => 'jakarta-talent-showcase-2026',
            'city' => 'Jakarta Pusat',
            'venue_name' => 'Stadion Madya',
            'format' => 'cup',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'unlimited',
            'max_teams' => null,
            'age_category' => 'U-17',
            'min_players_per_team' => 11,
            'max_players_per_team' => 18,
            'max_officials_per_team' => 3,
            'required_documents' => ['birth_certificate', 'family_card'],
            'registration_fee' => 200000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
            'form_presets' => [
                'position' => true,
                'foot_dominance' => true,
                'team_origin' => true,
                'body_measurements' => true,
                'experience' => true,
                'jersey_size' => true,
            ],
        ]);

        $response = $this->get(route('events.show', ['event' => $event->slug]));
        $response->assertOk();

        $page = $response->viewData('page');
        $eventData = $page['props']['event'];

        $this->assertEquals('audition', $eventData['event_type']);
        $this->assertEquals('individual', $eventData['participant_type']);
        $this->assertTrue($eventData['is_unlimited_quota']);
        $this->assertNull($eventData['available_slots']);
        $this->assertTrue($eventData['form_presets']['jersey_size']);
    }

    public function test_superadmin_can_update_audition_event_with_audition_format(): void
    {
        $event = Event::create([
            'name' => 'Persija EPA Future Stars Scouting Fest 2026',
            'slug' => 'persija-epa-future-stars-scouting-fest-2026',
            'venue_name' => 'Persija Training Ground, Bojongsari',
            'city' => 'Kota Depok',
            'format' => 'audition',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 150,
            'max_teams' => 150,
            'age_category' => 'U-14',
            'required_documents' => ['birth_certificate', 'family_card'],
            'registration_fee' => 250000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
            'form_presets' => Event::DEFAULT_FORM_PRESETS,
        ]);

        $payload = [
            'name' => 'Persija EPA Future Stars Scouting Fest 2026 Updated',
            'city' => 'Kota Depok',
            'venue_name' => 'Persija Training Ground, Bojongsari',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 150,
            'max_teams' => 150,
            'format' => 'audition',
            'age_category' => 'U-14',
            'required_documents' => ['birth_certificate', 'family_card'],
            'registration_fee' => 250000,
            'status' => 'open',
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
        ];

        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.events.update', ['event' => $event->id]), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.events.index'));

        $this->assertEquals('audition', $event->fresh()->format);
        $this->assertEquals('Persija EPA Future Stars Scouting Fest 2026 Updated', $event->fresh()->name);
    }

    public function test_superadmin_can_upload_webp_banner_file(): void
    {
        $webpBanner = UploadedFile::fake()->create('event-banner.webp', 450, 'image/webp');

        $payload = [
            'name' => 'WebP Banner Event Test',
            'city' => 'Jakarta Selatan',
            'venue_name' => 'Stadion Madya GBK',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 80,
            'format' => 'audition',
            'age_category' => 'U-14',
            'required_documents' => ['birth_certificate', 'family_card'],
            'registration_fee' => 100000,
            'status' => 'open',
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'banner_file' => $webpBanner,
        ];

        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.events.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.events.index'));

        $created = Event::where('name', 'WebP Banner Event Test')->firstOrFail();
        $this->assertNotNull($created->banner_path);
        $this->assertStringEndsWith('.webp', $created->banner_path);
    }
}
