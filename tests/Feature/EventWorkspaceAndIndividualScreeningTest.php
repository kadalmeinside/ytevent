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

    public function test_split_view_workstation_paginates_queue_and_hydrates_only_active_participant(): void
    {
        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('audition');
        $user = User::factory()->create();

        $participants = [];
        for ($i = 1; $i <= 24; $i++) {
            $participants[] = $this->makeParticipant($event, $user, [
                'full_name' => sprintf('Atlet Uji %02d', $i),
                'screening_status' => $i <= 22 ? 'submitted' : 'passed',
                'bib_number' => $i > 22 ? $i : null,
            ]);
        }

        // Requesting participant #21 (page 2) auto-lands on page 2 and hydrates only participant #21
        $target = $participants[20];
        $this->actingAs($admin)
            ->get(route('admin.screening.auditions.review', ['event' => $event->id, 'participant_id' => $target->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Screening/AuditionReview')
                ->where('activeParticipant.id', $target->id)
                ->has('activeParticipant.documents', 2)
                ->has('participants', 4)
                ->where('pagination.current_page', 2)
                ->where('pagination.last_page', 2)
                ->where('pagination.total', 24)
                ->where('navigation.active_position', 21)
                ->where('navigation.prev_id', $participants[19]->id)
                ->where('navigation.next_id', $participants[21]->id)
                ->where('counts.total', 24)
                ->where('counts.submitted', 22)
                ->where('counts.passed', 2)
                ->missing('participants.1.documents')
            );

        // Status filter returns only passed participants
        $this->actingAs($admin)
            ->get(route('admin.screening.auditions.review', ['event' => $event->id, 'status' => 'passed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pagination.total', 2)
                ->has('participants', 2)
                ->where('activeParticipant.id', $participants[22]->id)
            );
    }

    public function test_event_workspace_paginates_and_filters_individual_participants_server_side(): void
    {
        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('audition');
        $user = User::factory()->create();

        for ($i = 1; $i <= 28; $i++) {
            $this->makeParticipant($event, $user, [
                'full_name' => $i === 28 ? 'Garuda Spesial' : sprintf('Peserta Event %02d', $i),
                'screening_status' => $i <= 20 ? 'submitted' : 'passed',
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.events.show', ['event' => $event->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 28)
                ->where('summary.submitted', 20)
                ->where('summary.passed', 8)
                ->has('participants', 25)
                ->where('participantsPagination.total', 28)
                ->where('participantsPagination.last_page', 2)
            );

        $this->actingAs($admin)
            ->get(route('admin.events.show', ['event' => $event->id, 'search' => 'Garuda Spesial']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.total', 28)
                ->has('participants', 1)
                ->where('participants.0.full_name', 'Garuda Spesial')
            );
    }

    public function test_individual_event_supports_open_or_single_sided_age_limits_and_stage_sync(): void
    {
        $admin = $this->makeSuperadmin();

        // 1. Open age limit (both null) + auto-generated stages from match_start_date..match_end_date (3 days)
        $this->actingAs($admin)
            ->post(route('admin.events.store'), [
                'name' => 'Coaching Clinic Terbuka',
                'venue_name' => 'Lapangan Senayan',
                'city' => 'Jakarta',
                'event_type' => 'coaching_clinic',
                'participant_type' => 'individual',
                'quota_type' => 'unlimited',
                'format' => 'coaching_clinic',
                'age_category' => 'Semua Usia',
                'min_birth_date' => null,
                'max_birth_date' => null,
                'required_documents' => ['birth_certificate'],
                'registration_fee' => 0,
                'lock_roster_until_paid' => true,
                'payment_deadline_hours' => 24,
                'reupload_grace_hours' => 6,
                'match_start_date' => '2026-12-01',
                'match_end_date' => '2026-12-03',
                'status' => 'open',
            ])
            ->assertRedirect(route('admin.events.index'));

        $clinic = Event::where('name', 'Coaching Clinic Terbuka')->firstOrFail();
        $this->assertTrue($clinic->isBirthDateEligible('1995-05-10'));
        $this->assertTrue($clinic->isBirthDateEligible('2016-01-01'));
        $this->assertSame(3, $clinic->auditionStages()->count());
        $this->assertSame('2026-12-01', $clinic->auditionStages()->orderBy('stage_number')->first()->stage_date->toDateString());

        // 2. Single-sided minimum age (only max_birth_date set, min_birth_date null) + custom stages on create
        $this->actingAs($admin)
            ->post(route('admin.events.store'), [
                'name' => 'Audisi Minimal Usia',
                'venue_name' => 'Stadion Madya',
                'city' => 'Jakarta',
                'event_type' => 'audition',
                'participant_type' => 'individual',
                'quota_type' => 'fixed',
                'max_participants' => 80,
                'format' => 'audition',
                'age_category' => 'Min. 12 Tahun',
                'min_birth_date' => null,
                'max_birth_date' => '2014-12-31',
                'required_documents' => ['birth_certificate'],
                'registration_fee' => 150000,
                'lock_roster_until_paid' => true,
                'payment_deadline_hours' => 24,
                'reupload_grace_hours' => 6,
                'match_start_date' => '2026-12-10',
                'match_end_date' => '2026-12-11',
                'stages' => [
                    [
                        'stage_name' => 'Screening Fisik & Teknik',
                        'stage_date' => '2026-12-10',
                        'start_time' => '08:00',
                        'end_time' => '12:00',
                        'venue_name' => 'Lapangan A',
                        'target_quota' => 80,
                        'is_active' => true,
                    ],
                ],
                'status' => 'open',
            ])
            ->assertRedirect(route('admin.events.index'));

        $audition = Event::where('name', 'Audisi Minimal Usia')->firstOrFail();
        $this->assertTrue($audition->isBirthDateEligible('2005-03-01'));
        $this->assertFalse($audition->isBirthDateEligible('2015-01-01'));
        $this->assertSame(1, $audition->auditionStages()->count());
        $this->assertSame('Screening Fisik & Teknik', $audition->auditionStages()->first()->stage_name);
    }

    public function test_digital_pass_e_ticket_scouting_and_qualification_work_without_bib_number(): void
    {
        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('audition');
        $stage = \App\Models\AuditionStage::create([
            'event_id' => $event->id,
            'stage_number' => 1,
            'stage_name' => 'Hari 1',
            'stage_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $participant = $this->makeParticipant($event, $user, [
            'registration_number' => 'AUD-2026-PASS01',
            'bib_number' => null,
            'screening_status' => 'passed',
            'screening_code' => 'SCR-AUD-DIGITALPASS1',
            'stage_status' => 'registered',
            'status' => 'active',
        ]);

        \App\Models\Payment::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'audition_participant_id' => $participant->id,
            'invoice_number' => 'INV-TEST-PASS01',
            'amount' => 150000,
            'base_amount' => 150000,
            'total_amount' => 150000,
            'status' => 'paid',
            'method' => 'midtrans_qris',
            'paid_at' => now(),
        ]);

        $this->assertTrue($participant->fresh()->canDownloadPass());

        // Simpan dummy banner image pada storage public untuk menguji Header Overlay Banner pada PDF
        \Illuminate\Support\Facades\Storage::fake('public');
        $dummyImg = imagecreatetruecolor(600, 200);
        imagefill($dummyImg, 0, 0, imagecolorallocate($dummyImg, 234, 88, 12));
        ob_start();
        imagepng($dummyImg);
        $pngBytes = ob_get_clean();
        imagedestroy($dummyImg);
        \Illuminate\Support\Facades\Storage::disk('public')->put('events/banners/test-banner.png', $pngBytes);
        $event->update(['banner_path' => 'events/banners/test-banner.png']);

        // 1. E-Tiket PDF dapat diunduh tanpa bib_number & dengan banner overlay + HMAC AUTH-SIG
        $pdfResponse = $this->actingAs($admin)->get(route('audition.bib.download', ['participant' => $participant->id]));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));

        // Verifikasi QR endpoint mengembalikan auth_signature HMAC & invoice_number yang identik
        $expectedSig = \App\Services\AuditionBibPdfService::computeAuthSignature($participant->fresh());
        $this->get(route('public.audition.verify', ['code' => 'SCR-AUD-DIGITALPASS1']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isVerified', true)
                ->where('participant.auth_signature', $expectedSig)
                ->where('participant.invoice_number', 'INV-TEST-PASS01')
            );

        // 2. Summary digital_pass_active menghitung peserta meski bib_number null
        $this->actingAs($admin)
            ->get(route('admin.events.show', ['event' => $event->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.digital_pass_active', 1)
            );

        // 3. Check-in scanner menggunakan ID Digital Pass (registration_number)
        $this->actingAs($admin)
            ->postJson(route('admin.auditions.scanner.check-in'), [
                'code' => 'AUD-2026-PASS01',
                'stage_id' => $stage->id,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'gatekeeper_status' => 'CHECK_IN_SUCCESS',
            ]);

        // 4. Panel Scouting & Panel Kelolosan tetap menampilkan peserta tanpa bib_number
        $this->actingAs($admin)
            ->get(route('scout.events.show', ['event' => $event->id, 'stage_id' => $stage->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('participants', 1)
                ->where('participants.0.registration_number', 'AUD-2026-PASS01')
            );

        $this->actingAs($admin)
            ->get(route('admin.events.auditions.qualifications', ['event' => $event->id, 'stage_id' => $stage->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('participants', 1)
                ->where('participants.0.registration_number', 'AUD-2026-PASS01')
            );
    }

    public function test_digital_pass_strictly_follows_event_form_presets_and_custom_fields(): void
    {
        // 1. Event Audisi tanpa Jersey, Foto, Kaki Dominan, Antropometri, dan Asal SSB
        $eventNoExtras = $this->makeIndividualEvent('audition');
        $eventNoExtras->update([
            'form_presets' => [
                'photo' => false,
                'position' => true,
                'foot_dominance' => false,
                'team_origin' => false,
                'body_measurements' => false,
                'experience' => false,
                'jersey_size' => false,
            ],
        ]);

        $user1 = User::factory()->create();
        $p1 = $this->makeParticipant($eventNoExtras, $user1, [
            'registration_number' => 'AUD-2026-PRESET1',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-PRESET-01',
            'preferred_position' => 'ST',
            'dominant_foot' => 'right',
            'jersey_size' => null,
            'photo_path' => null,
            'current_club_ssb' => null,
            'height_cm' => 170,
            'weight_kg' => 60,
        ]);

        $htmlNoExtras = view('pdf.audition_bib_tag', [
            'participant' => $p1,
            'event' => $eventNoExtras->fresh(),
            'session' => null,
            'form_presets' => $eventNoExtras->fresh()->normalizedFormPresets(),
            'custom_field_items' => [],
            'qr_code_svg' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
            'photo_base64' => null,
            'banner_overlay_base64' => null,
            'auth_signature' => 'ABCD-1234-EF56-7890',
            'invoice_number' => 'INV-PRESET-01',
            'verified_by_name' => 'Panitia',
            'verified_at_label' => '13 Nov 2026',
            'valid_dates_label' => '14 Nov 2026',
            'generated_at' => '13 November 2026',
        ])->render();

        $this->assertStringContainsString('POSISI: ST', $htmlNoExtras);
        $this->assertStringNotContainsString('JERSEY:', $htmlNoExtras);
        $this->assertStringNotContainsString('KAKI:', $htmlNoExtras);
        $this->assertStringNotContainsString('PAS FOTO RESMI', $htmlNoExtras);
        $this->assertStringNotContainsString('Cocokkan Wajah Atlet', $htmlNoExtras);
        $this->assertStringNotContainsString('Asal SSB / Klub', $htmlNoExtras);
        $this->assertStringNotContainsString('Tinggi / Berat', $htmlNoExtras);

        // 2. Event Clinic tanpa jersey_size juga tidak boleh memunculkan 'ALL SIZE' / 'Ukuran Jersey / Kit'
        $clinicNoJersey = $this->makeIndividualEvent('coaching_clinic');
        $clinicNoJersey->update([
            'form_presets' => [
                'photo' => false,
                'position' => false,
                'foot_dominance' => false,
                'team_origin' => false,
                'body_measurements' => false,
                'experience' => false,
                'jersey_size' => false,
            ],
        ]);
        $pClinic = $this->makeParticipant($clinicNoJersey, User::factory()->create(), [
            'registration_number' => 'CLN-2026-001',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-CLN-01',
            'jersey_size' => null,
        ]);

        $htmlClinic = view('pdf.audition_bib_tag', [
            'participant' => $pClinic,
            'event' => $clinicNoJersey->fresh(),
            'session' => null,
            'form_presets' => $clinicNoJersey->fresh()->normalizedFormPresets(),
            'custom_field_items' => [],
            'qr_code_svg' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
            'photo_base64' => null,
            'banner_overlay_base64' => null,
            'auth_signature' => 'ABCD-1234-EF56-7890',
            'invoice_number' => 'INV-CLN-01',
            'verified_by_name' => 'Panitia',
            'verified_at_label' => '13 Nov 2026',
            'valid_dates_label' => '14 Nov 2026',
            'generated_at' => '13 November 2026',
        ])->render();

        $this->assertStringNotContainsString('Ukuran Jersey / Kit', $htmlClinic);
        $this->assertStringNotContainsString('ALL SIZE', $htmlClinic);

        // 3. Event dengan jersey_size, body_measurements, dan custom_fields_schema aktif
        $eventFull = $eventNoExtras;
        $eventFull->update([
            'form_presets' => [
                'photo' => false,
                'position' => true,
                'foot_dominance' => true,
                'team_origin' => true,
                'body_measurements' => true,
                'experience' => false,
                'jersey_size' => true,
            ],
            'custom_fields_schema' => [
                [
                    'key' => 'golongan_darah',
                    'label' => 'Golongan Darah',
                    'type' => 'text',
                    'required' => false,
                ],
            ],
        ]);

        $userFull = User::factory()->create();
        $pFull = $this->makeParticipant($eventFull, $userFull, [
            'registration_number' => 'AUD-2026-FULL1',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-FULL-01',
            'preferred_position' => 'GK',
            'dominant_foot' => 'left',
            'jersey_size' => 'xl',
            'height_cm' => 165,
            'weight_kg' => 55,
            'current_club_ssb' => 'SSB Garuda Muda',
            'custom_answers' => [
                'golongan_darah' => 'O+',
            ],
        ]);

        \App\Models\Payment::create([
            'event_id' => $eventFull->id,
            'user_id' => $userFull->id,
            'audition_participant_id' => $pFull->id,
            'invoice_number' => 'INV-FULL-01',
            'amount' => 250000,
            'base_amount' => 250000,
            'total_amount' => 250000,
            'status' => 'paid',
            'method' => 'midtrans_qris',
            'paid_at' => now(),
        ]);

        $customItems = \App\Services\AuditionBibPdfService::extractCustomFieldItems($eventFull->fresh(), $pFull);
        $htmlFull = view('pdf.audition_bib_tag', [
            'participant' => $pFull,
            'event' => $eventFull->fresh(),
            'session' => null,
            'form_presets' => $eventFull->fresh()->normalizedFormPresets(),
            'custom_field_items' => $customItems,
            'qr_code_svg' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
            'photo_base64' => null,
            'banner_overlay_base64' => null,
            'auth_signature' => 'ABCD-1234-EF56-7890',
            'invoice_number' => 'INV-FULL-01',
            'verified_by_name' => 'Panitia',
            'verified_at_label' => '13 Nov 2026',
            'valid_dates_label' => '14 Nov 2026',
            'generated_at' => '13 November 2026',
        ])->render();

        $this->assertStringContainsString('JERSEY: XL', $htmlFull);
        $this->assertStringContainsString('KAKI: LEFT', $htmlFull);
        $this->assertStringContainsString('SSB Garuda Muda', $htmlFull);
        $this->assertStringContainsString('165 cm / 55 kg', $htmlFull);
        $this->assertStringContainsString('Golongan Darah:', $htmlFull);
        $this->assertStringContainsString('O+', $htmlFull);

        // Pastikan DomPDF merender 1 halaman A4 Portrait dengan bersih
        $pdfBytes = app(\App\Services\AuditionBibPdfService::class)->generateBibPdf($pFull->fresh())->output();
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdfBytes));
        $this->assertStringContainsString('595.280 841.890', $pdfBytes);

        // Portal verifikasi QR juga menyertakan form_presets dan custom_field_items
        $this->get(route('public.audition.verify', ['code' => 'SCR-FULL-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isVerified', true)
                ->where('event.form_presets.jersey_size', true)
                ->where('participant.jersey_size', 'xl')
                ->where('participant.custom_field_items.0.label', 'Golongan Darah')
                ->where('participant.custom_field_items.0.value', 'O+')
            );
    }

    public function test_event_update_normalizes_form_data_preset_booleans_and_hides_disabled_fields(): void
    {
        $admin = $this->makeSuperadmin();
        $event = $this->makeIndividualEvent('audition');

        // Simulasikan kondisi legacy di DB yang menyimpan string "1"/"0" dari FormData
        $event->forceFill([
            'form_presets' => [
                'photo' => '1',
                'position' => '1',
                'foot_dominance' => '1',
                'team_origin' => '1',
                'body_measurements' => '1',
                'experience' => '1',
                'jersey_size' => '0',
            ],
        ])->saveQuietly();

        // Edit page harus menormalisasi prop form_presets ke strict boolean true/false
        $this->actingAs($admin)
            ->get(route('admin.events.edit', ['event' => $event->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.form_presets.photo', true)
                ->where('event.form_presets.jersey_size', false)
            );

        // Simulasikan submit FormData dimana boolean dikirim sebagai string '0'/'1'
        $this->actingAs($admin)
            ->post(route('admin.events.update', ['event' => $event->id]), [
                'name' => $event->name,
                'venue_name' => $event->venue_name,
                'city' => $event->city,
                'event_type' => 'audition',
                'participant_type' => 'individual',
                'quota_type' => 'fixed',
                'max_participants' => 100,
                'format' => 'audition',
                'age_category' => 'U-14',
                'required_documents' => ['birth_certificate', 'family_card'],
                'registration_fee' => 250000,
                'payment_deadline_hours' => 24,
                'reupload_grace_hours' => 6,
                'status' => 'open',
                'form_presets' => [
                    'photo' => '0',
                    'position' => '1',
                    'foot_dominance' => '0',
                    'team_origin' => '0',
                    'body_measurements' => '0',
                    'experience' => '0',
                    'jersey_size' => '0',
                ],
            ])
            ->assertRedirect();

        $freshEvent = $event->fresh();
        $this->assertSame(false, $freshEvent->form_presets['photo']);
        $this->assertSame(true, $freshEvent->form_presets['position']);
        $this->assertSame(false, $freshEvent->form_presets['jersey_size']);
        $this->assertFalse($freshEvent->normalizedFormPresets()['jersey_size']);

        // Peserta yang sudah terlanjur memiliki jersey_size = 'M' tetap tidak menampilkan JERSEY pada E-Tiket
        $participant = $this->makeParticipant($freshEvent, User::factory()->create(), [
            'registration_number' => 'AUD-2026-NORM1',
            'screening_status' => 'passed',
            'screening_code' => 'SCR-NORM-01',
            'jersey_size' => 'M',
            'preferred_position' => 'CMF',
        ]);

        $renderedHtml = view('pdf.audition_bib_tag', [
            'participant' => $participant,
            'event' => $freshEvent,
            'session' => null,
            'form_presets' => $freshEvent->normalizedFormPresets(),
            'custom_field_items' => [],
            'qr_code_svg' => 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=',
            'photo_base64' => null,
            'banner_overlay_base64' => null,
            'auth_signature' => 'ABCD-1234-EF56-7890',
            'invoice_number' => 'INV-NORM-01',
            'verified_by_name' => 'Panitia',
            'verified_at_label' => '13 Nov 2026',
            'valid_dates_label' => '14 Nov 2026',
            'generated_at' => '13 November 2026',
        ])->render();

        $this->assertStringContainsString('POSISI: CMF', $renderedHtml);
        $this->assertStringNotContainsString('JERSEY:', $renderedHtml);
    }
}
