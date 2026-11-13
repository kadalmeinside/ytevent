<?php

namespace Tests\Feature;

use App\Models\AuditionParticipant;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditionDocumentReuploadService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventWorkspaceAndIndividualScreeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('private');
    }

    private function makeSuperadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function makeIndividualEvent(string $eventType = 'audition'): Event
    {
        return Event::create([
            'name' => $eventType === 'coaching_clinic' ? 'Coaching Clinic U-12 2026' : 'Audisi EPA U-14 2026',
            'slug' => $eventType === 'coaching_clinic' ? 'coaching-clinic-u-12-2026' : 'audisi-epa-u-14-2026',
            'venue_name' => 'Persija Training Ground',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-14',
            'event_type' => $eventType,
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 250000,
            'status' => 'open',
            'min_birth_date' => '2012-01-01',
            'max_birth_date' => '2013-12-31',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);
    }

    private function makeParticipant(Event $event, ?User $guardian = null, array $overrides = []): AuditionParticipant
    {
        $guardian ??= User::factory()->create();
        $guardian->assignRole('participant_parent');

        Storage::disk('private')->put('auditions/docs/akta_sample.pdf', '%PDF-1.4 mock');
        Storage::disk('private')->put('auditions/docs/kk_sample.pdf', '%PDF-1.4 mock');

        return AuditionParticipant::create(array_merge([
            'event_id' => $event->id,
            'user_id' => $guardian->id,
            'registration_number' => 'AUD-' . strtoupper(uniqid()),
            'full_name' => 'Rizky Ridho Junior',
            'nickname' => 'Rizky',
            'nik' => '3171012345678902',
            'birth_date' => '2012-05-10',
            'gender' => 'male',
            'preferred_position' => 'CB',
            'dominant_foot' => 'right',
            'current_club_ssb' => 'SSB Persija Muda',
            'emergency_contact_name' => 'Budi Santoso',
            'emergency_contact_phone' => '081234567890',
            'status' => 'active',
            'screening_status' => 'submitted',
            'birth_certificate_path' => 'auditions/docs/akta_sample.pdf',
            'family_card_path' => 'auditions/docs/kk_sample.pdf',
        ], $overrides));
    }

    public function test_admin_event_workspace_renders_for_individual_and_team_events(): void
    {
        $admin = $this->makeSuperadmin();
        $individualEvent = $this->makeIndividualEvent('audition');
        $session = EventSession::create([
            'event_id' => $individualEvent->id,
            'session_name' => 'Sesi Pagi',
            'quota' => 50,
            'occupied_slots' => 1,
        ]);
        $this->makeParticipant($individualEvent, null, [
            'event_session_id' => $session->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.events.show', ['event' => $individualEvent->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Events/Show')
                ->where('event.id', $individualEvent->id)
                ->has('summary')
                ->has('sessions', 1)
                ->has('participants', 1)
            );

        $teamEvent = Event::create([
            'name' => 'Turnamen Tim U-12',
            'slug' => 'turnamen-tim-u-12',
            'venue_name' => 'Stadion ABC',
            'city' => 'Jakarta',
            'format' => 'group_knockout',
            'age_category' => 'U-12',
            'event_type' => 'competition',
            'participant_type' => 'team',
            'quota_type' => 'fixed',
            'max_participants' => 16,
            'registration_fee' => 1500000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
        ]);

        $manager = User::factory()->create();
        $manager->assignRole('team_manager');
        Team::create([
            'event_id' => $teamEvent->id,
            'user_id' => $manager->id,
            'name' => 'Persija Academy U-12',
            'slug' => 'persija-academy-u-12',
            'logo_path' => 'logos/persija.png',
            'origin_association' => 'Askot PSSI Jakarta Selatan',
            'manager_name' => 'Manajer Tim',
            'manager_whatsapp' => '081234567890',
            'manager_email' => 'manager@persija.id',
            'head_coach_name' => 'Coach Persija',
            'terms_accepted_at' => now(),
            'status' => 'active',
            'screening_status' => 'in_review',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.events.show', ['event' => $teamEvent->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Events/Show')
                ->where('event.id', $teamEvent->id)
                ->has('summary')
                ->has('teams', 1)
            );
    }

    public function test_individual_screening_split_view_workstation_renders_with_structured_documents(): void
    {
        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('audition');
        $participant = $this->makeParticipant($event);
        AuditLog::record($participant, 'audition.screening.rejected', $admin, reason: 'Foto blur');

        $this->actingAs($admin)
            ->get(route('admin.screening.auditions.review', ['event' => $event->id, 'participant_id' => $participant->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Screening/AuditionReview')
                ->where('event.id', $event->id)
                ->has('participants', 1)
                ->where('participants.0.id', $participant->id)
                ->where('participants.0.is_age_valid', true)
                ->has('participants.0.documents', 2)
                ->where('participants.0.documents.0.document_type', 'birth_certificate')
                ->where('participants.0.documents.1.document_type', 'family_card')
                ->has('participants.0.logs', 1)
                ->where('participants.0.logs.0.reason', 'Foto blur')
            );
    }

    public function test_per_document_verification_persists_and_blocks_after_participant_passed(): void
    {
        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('audition');
        $participant = $this->makeParticipant($event);

        $this->actingAs($admin)
            ->postJson(route('admin.screening.auditions.document.verify', ['participant' => $participant->id]), [
                'document_type' => 'birth_certificate',
                'validation_status' => 'valid',
                'notes' => 'Akta jelas terbaca.',
            ])
            ->assertOk()
            ->assertJsonPath('document_statuses.birth_certificate.validation_status', 'valid');

        $participant->refresh();
        $this->assertSame('valid', $participant->document_statuses['birth_certificate']['validation_status']);

        $participant->update(['screening_status' => 'passed']);

        $this->actingAs($admin)
            ->postJson(route('admin.screening.auditions.document.verify', ['participant' => $participant->id]), [
                'document_type' => 'family_card',
                'validation_status' => 'invalid',
                'notes' => 'Buram',
            ])
            ->assertStatus(422);
    }

    public function test_emergency_unlock_requires_permission_and_logs_audit(): void
    {
        $officerRole = Role::findByName('screening_officer', 'web');
        $officerRole->revokePermissionTo('screening.unlock_player');
        $officer = User::factory()->create();
        $officer->assignRole($officerRole);

        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('coaching_clinic');
        $participant = $this->makeParticipant($event, null, [
            'screening_status' => 'passed',
            'screening_notes' => 'Awalnya disahkan',
        ]);

        $this->actingAs($officer)
            ->post(route('admin.screening.auditions.unlock', ['participant' => $participant->id]), [
                'reason' => 'Perlu perbaikan berkas',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('admin.screening.auditions.unlock', ['participant' => $participant->id]), [
                'reason' => 'Ditemukan ketidaksesuaian tanggal lahir pada KK.',
            ])
            ->assertRedirect();

        $participant->refresh();
        $this->assertSame('submitted', $participant->screening_status);
        $this->assertNull($participant->screening_notes);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'audition.screening.unlocked',
            'auditable_type' => AuditionParticipant::class,
            'auditable_id' => $participant->id,
            'reason' => 'Ditemukan ketidaksesuaian tanggal lahir pada KK.',
        ]);
    }

    public function test_reuploading_document_resets_its_document_status_to_pending(): void
    {
        $event = $this->makeIndividualEvent('audition');
        $participant = $this->makeParticipant($event, null, [
            'screening_status' => 'rejected',
            'document_statuses' => [
                'birth_certificate' => [
                    'validation_status' => 'invalid',
                    'notes' => 'Scan terpotong',
                ],
                'family_card' => [
                    'validation_status' => 'valid',
                    'notes' => 'Sesuai',
                ],
            ],
        ]);

        $service = app(AuditionDocumentReuploadService::class);
        $newAkta = UploadedFile::fake()->create('akta_baru.pdf', 300, 'application/pdf');
        $service->replace($participant, ['birth_certificate' => $newAkta]);

        $participant->refresh();
        $this->assertSame('submitted', $participant->screening_status);
        $this->assertSame('pending', $participant->document_statuses['birth_certificate']['validation_status']);
        $this->assertNull($participant->document_statuses['birth_certificate']['notes']);
        $this->assertSame('valid', $participant->document_statuses['family_card']['validation_status']);
    }
}
