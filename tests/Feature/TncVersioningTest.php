<?php

namespace Tests\Feature;

use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\Team;
use App\Models\TncVersion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TncVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('private');
    }

    public function test_admin_can_create_new_tnc_version_when_creating_and_updating_event(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $payload = [
            'name' => 'Audisi Medis Terukur 2026',
            'venue_name' => 'Persija Training Ground',
            'city' => 'Kota Depok',
            'registration_fee' => 150000,
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 24,
            'age_category' => 'U-15',
            'participant_type' => 'individual',
            'event_type' => 'audition',
            'format' => 'audition',
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
            'tnc_mode' => 'new',
            'tnc_code' => 'audisi-waiver-khusus',
            'tnc_title' => 'Surat Pernyataan Bebas Tanggung Jawab Medis Khusus',
            'tnc_summary' => 'Saya menyetujui surat pernyataan medis untuk {{event_name}}.',
            'tnc_content' => '<p>Peserta <strong>{{event_name}}</strong> dalam kondisi sehat.</p><script>alert("xss")</script>',
        ];

        $response = $this->actingAs($superadmin)->post(route('admin.events.store'), $payload);
        $response->assertRedirect(route('admin.events.index'));

        $event = Event::where('name', 'Audisi Medis Terukur 2026')->firstOrFail();
        $v1 = TncVersion::where('code', 'audisi-waiver-khusus')->where('version_number', 1)->firstOrFail();

        $this->assertSame($v1->id, $event->tnc_version_id);
        $this->assertSame('1.0', $v1->version);
        $this->assertStringNotContainsString('<script>', $v1->content);

        // Update event dengan revisi baru pada code yang sama -> otomatis menjadi v2.0
        $updatePayload = array_merge($payload, [
            'tnc_content' => '<p>Klausul medis revisi 2 untuk {{event_name}}.</p>',
        ]);

        $this->actingAs($superadmin)
            ->post(route('admin.events.update', ['event' => $event->id]), $updatePayload)
            ->assertRedirect(route('admin.events.index'));

        $event->refresh();
        $v2 = TncVersion::where('code', 'audisi-waiver-khusus')->where('version_number', 2)->firstOrFail();

        $this->assertSame('2.0', $v2->version);
        $this->assertSame($v2->id, $event->tnc_version_id);
        $this->assertDatabaseHas('tnc_versions', [
            'id' => $v1->id,
            'version' => '1.0',
        ]);
    }

    public function test_public_registration_pages_receive_resolved_tnc_with_interpolated_event_name(): void
    {
        $teamTnc = TncVersion::createNewVersion(
            title: 'Regulasi Liga Muda',
            content: '<p>Aturan resmi <strong>{{event_name}}</strong> batas {{payment_deadline_hours}} jam.</p>',
            scope: 'team',
            summary: 'Setuju aturan {{event_name}} ({{payment_deadline_hours}} jam).',
            code: 'liga-muda-team',
        );

        $teamEvent = Event::create([
            'name' => 'Persija Cup U-15',
            'slug' => 'persija-cup-u15',
            'venue_name' => 'Stadion Madya',
            'city' => 'Jakarta Pusat',
            'format' => 'group_knockout',
            'participant_type' => 'team',
            'event_type' => 'tournament',
            'age_category' => 'U-15',
            'min_players_per_team' => 11,
            'max_players_per_team' => 18,
            'min_gk_per_team' => 2,
            'max_officials_per_team' => 5,
            'required_documents' => ['birth_certificate', 'family_card'],
            'max_teams' => 16,
            'registration_fee' => 1000000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 12,
            'reupload_grace_hours' => 6,
            'status' => 'open',
            'tnc_version_id' => $teamTnc->id,
        ]);

        $this->get(route('events.register', ['event' => $teamEvent->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/RegisterTeam')
                ->where('event.tnc.id', $teamTnc->id)
                ->where('event.tnc.version', '1.0')
                ->where('event.tnc.summary', 'Setuju aturan Persija Cup U-15 (12 jam).')
                ->where('event.tnc.content_html', fn (string $html) => str_contains($html, 'Persija Cup U-15') && str_contains($html, '12 jam'))
            );

        $auditionTnc = TncVersion::createNewVersion(
            title: 'Surat Pernyataan Medis Audisi Khusus',
            content: '<p>Pernyataan medis resmi <strong>{{event_name}}</strong>.</p>',
            scope: 'audition',
            summary: 'Saya wali sah peserta {{event_name}} menyetujui ketentuan medis.',
            code: 'waiver-audisi-inertia',
        );

        $auditionEvent = Event::create([
            'name' => 'Audisi Muda Persija',
            'slug' => 'audisi-muda-persija',
            'venue_name' => 'Sawangan',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-13',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 100000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
            'tnc_version_id' => $auditionTnc->id,
        ]);

        $this->get(route('events.register-audition', ['event' => $auditionEvent->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/RegisterAudition')
                ->where('event.tnc.id', $auditionTnc->id)
                ->where('event.tnc.version', '1.0')
                ->where('event.tnc.summary', 'Saya wali sah peserta Audisi Muda Persija menyetujui ketentuan medis.')
                ->where('event.tnc.content_html', fn (string $html) => str_contains($html, 'Audisi Muda Persija'))
            );
    }

    public function test_team_registration_stores_tnc_snapshot_and_rejects_stale_version(): void
    {
        $v1 = TncVersion::createNewVersion(
            title: 'Regulasi Kompetisi',
            content: '<p>Ketentuan v1 untuk {{event_name}}</p>',
            scope: 'team',
            code: 'regulasi-kompetisi-test',
        );
        $v2 = TncVersion::createNewVersion(
            title: 'Regulasi Kompetisi',
            content: '<p>Ketentuan v2 untuk {{event_name}}</p>',
            scope: 'team',
            code: 'regulasi-kompetisi-test',
        );

        $event = Event::create([
            'name' => 'Jakarta Youth League',
            'slug' => 'jakarta-youth-league',
            'venue_name' => 'Lapangan ABC',
            'city' => 'Jakarta Selatan',
            'format' => 'group_knockout',
            'participant_type' => 'team',
            'event_type' => 'tournament',
            'age_category' => 'U-14',
            'min_players_per_team' => 11,
            'max_players_per_team' => 18,
            'min_gk_per_team' => 2,
            'max_officials_per_team' => 5,
            'required_documents' => ['birth_certificate', 'family_card'],
            'max_teams' => 16,
            'registration_fee' => 500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
            'tnc_version_id' => $v2->id,
        ]);

        // 1. Kirim tnc_version_id usang (v1) -> ditolak validasi
        $staleResponse = $this->post(route('events.register.store', ['event' => $event->slug]), [
            'team_name' => 'SSB Persada Lama',
            'origin_association' => 'Askot PSSI Jakarta Selatan',
            'manager_name' => 'Budi Santoso',
            'email' => 'budi@persada.id',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'manager_whatsapp' => '081234567890',
            'head_coach_name' => 'Coach Ahmad',
            'terms_accepted' => '1',
            'tnc_version_id' => $v1->id,
            'logo' => UploadedFile::fake()->image('logo1.png', 200, 200),
        ]);

        $staleResponse->assertSessionHasErrors('terms_accepted');

        // 2. Kirim tnc_version_id aktif (v2) -> berhasil & tersimpan jejak audit lengkap
        $validResponse = $this->withHeaders(['User-Agent' => 'PersijaTestBrowser/1.0'])
            ->post(route('events.register.store', ['event' => $event->slug]), [
                'team_name' => 'SSB Persada Baru',
                'origin_association' => 'Askot PSSI Jakarta Selatan',
                'manager_name' => 'Budi Santoso',
                'email' => 'budi.baru@persada.id',
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'manager_whatsapp' => '081234567891',
                'head_coach_name' => 'Coach Ahmad',
                'terms_accepted' => '1',
                'tnc_version_id' => $v2->id,
                'logo' => UploadedFile::fake()->image('logo2.png', 200, 200),
            ]);

        $validResponse->assertRedirect(route('manager.payments'));

        $team = Team::where('name', 'SSB Persada Baru')->firstOrFail();
        $this->assertSame($v2->id, $team->accepted_tnc_version_id);
        $this->assertSame('2.0', $team->accepted_tnc_version);
        $this->assertSame($v2->contentHashForEvent($event), $team->accepted_tnc_hash);
        $this->assertNotNull($team->accepted_ip);
        $this->assertSame('PersijaTestBrowser/1.0', $team->accepted_user_agent);
    }

    public function test_individual_audition_registration_stores_tnc_snapshot_and_rejects_stale_version(): void
    {
        $v1 = TncVersion::createNewVersion(
            title: 'Surat Pernyataan Medis Audisi',
            content: '<p>Medis v1 {{event_name}}</p>',
            scope: 'audition',
            code: 'waiver-audisi-test',
        );
        $v2 = TncVersion::createNewVersion(
            title: 'Surat Pernyataan Medis Audisi',
            content: '<p>Medis v2 {{event_name}}</p>',
            scope: 'audition',
            code: 'waiver-audisi-test',
        );

        $event = Event::create([
            'name' => 'Audisi Akbar U-13',
            'slug' => 'audisi-akbar-u13',
            'venue_name' => 'Sawangan',
            'city' => 'Depok',
            'format' => 'festival',
            'age_category' => 'U-13',
            'event_type' => 'audition',
            'participant_type' => 'individual',
            'quota_type' => 'fixed',
            'max_participants' => 100,
            'registration_fee' => 100000,
            'status' => 'open',
            'required_documents' => ['birth_certificate', 'family_card'],
            'tnc_version_id' => $v2->id,
        ]);

        $basePayload = [
            'guardian_name' => 'Andi Wijaya',
            'guardian_email' => 'andi.wali@persija.id',
            'guardian_phone' => '081234567899',
            'guardian_password' => 'Rahasia123',
            'guardian_password_confirmation' => 'Rahasia123',
            'full_name' => 'Rafi Wijaya',
            'nik' => '3171098765432101',
            'birth_date' => '2013-05-10',
            'gender' => 'male',
            'preferred_position' => 'CMF',
            'dominant_foot' => 'right',
            'height_cm' => 150,
            'weight_kg' => 42,
            'current_club_ssb' => 'SSB Menteng Jaya',
            'jersey_size' => 'M',
            'emergency_contact_name' => 'Siti Aminah',
            'emergency_contact_phone' => '081299988877',
            'liability_waiver' => '1',
            'birth_certificate' => UploadedFile::fake()->create('akta.pdf', 300, 'application/pdf'),
            'family_card' => UploadedFile::fake()->create('kk.pdf', 300, 'application/pdf'),
        ];

        $this->post(route('events.register-audition.store', ['event' => $event->slug]), array_merge($basePayload, [
            'tnc_version_id' => $v1->id,
        ]))->assertSessionHasErrors('liability_waiver');

        $response = $this->withHeaders(['User-Agent' => 'ParentSafari/17.0'])
            ->post(route('events.register-audition.store', ['event' => $event->slug]), array_merge($basePayload, [
                'tnc_version_id' => $v2->id,
            ]));

        $participant = AuditionParticipant::where('nik', '3171098765432101')->firstOrFail();
        $response->assertRedirect(route('manager.payments', ['participant_id' => $participant->id]));
        $this->assertSame($v2->id, $participant->accepted_tnc_version_id);
        $this->assertSame('2.0', $participant->accepted_tnc_version);
        $this->assertSame($v2->contentHashForEvent($event), $participant->accepted_tnc_hash);
        $this->assertNotNull($participant->accepted_ip);
        $this->assertSame('ParentSafari/17.0', $participant->accepted_user_agent);
    }

    public function test_superadmin_can_manage_bump_and_toggle_tnc_versions(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->get(route('admin.tnc-versions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/TncVersions/Index')
                ->where('stats.total_codes', 3)
                ->where('stats.total_versions', 3)
            );

        // Terbitkan dokumen baru v1.0
        $this->actingAs($superadmin)
            ->post(route('admin.tnc-versions.store'), [
                'title' => 'Syarat & Ketentuan Festival Nasional',
                'code' => 'festival-nasional',
                'scope' => 'team',
                'summary' => 'Setuju regulasi festival {{event_name}}.',
                'content' => '<p>Pasal 1 regulasi resmi {{event_name}}.</p>',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $v1 = TncVersion::where('code', 'festival-nasional')->where('version_number', 1)->firstOrFail();
        $this->assertTrue($v1->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TncVersion::class,
            'auditable_id' => $v1->id,
            'action' => 'tnc_version.created',
        ]);

        // Bump ke v2.0 pada kode yang sama
        $this->actingAs($superadmin)
            ->post(route('admin.tnc-versions.store'), [
                'title' => 'Syarat & Ketentuan Festival Nasional',
                'code' => 'festival-nasional',
                'scope' => 'team',
                'summary' => 'Setuju regulasi revisi v2 {{event_name}}.',
                'content' => '<p>Pasal 1 revisi kedua {{event_name}}.</p>',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $v2 = TncVersion::where('code', 'festival-nasional')->where('version_number', 2)->firstOrFail();
        $this->assertSame('2.0', $v2->version);

        // Toggle nonaktif v1
        $this->actingAs($superadmin)
            ->post(route('admin.tnc-versions.toggle-active', ['tncVersion' => $v1->id]))
            ->assertSessionHasNoErrors();

        $v1->refresh();
        $this->assertFalse($v1->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => TncVersion::class,
            'auditable_id' => $v1->id,
            'action' => 'tnc_version.status_toggled',
            'from_status' => 'active',
            'to_status' => 'inactive',
        ]);
    }

    public function test_non_superadmin_is_forbidden_from_superadmin_tnc_management(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $tnc = TncVersion::createNewVersion(
            title: 'Syarat & Ketentuan Internal',
            content: '<p>Isi ketentuan</p>',
            scope: 'all',
            code: 'internal-only',
        );

        $this->actingAs($finance)
            ->get(route('admin.tnc-versions.index'))
            ->assertForbidden();

        $this->actingAs($finance)
            ->post(route('admin.tnc-versions.store'), [
                'title' => 'Percobaan Ilegal',
                'scope' => 'all',
                'content' => '<p>Konten</p>',
            ])
            ->assertForbidden();

        $this->actingAs($finance)
            ->post(route('admin.tnc-versions.toggle-active', ['tncVersion' => $tnc->id]))
            ->assertForbidden();
    }

    public function test_tnc_preserves_rich_text_formatting_bold_italic_and_line_breaks(): void
    {
        $richHtml = '<div><b>Tebal B</b> dan <strong>Strong</strong></div><div><i>Miring I</i> dan <em>Em</em></div><div><u>Garis Bawah</u></div><div style="text-align: center"><span style="font-weight: bold; font-style: italic">Inline Style Aman</span></div><ul><li>Poin 1</li><li>Poin 2</li></ul>';

        $tnc = TncVersion::createNewVersion(
            title: 'Uji Format Rich Text',
            content: $richHtml,
            scope: 'all',
            code: 'rich-text-test',
        );

        $event = Event::create([
            'name' => 'Event Rich Text',
            'slug' => 'event-rich-text',
            'venue_name' => 'Jakarta',
            'city' => 'Jakarta',
            'format' => 'festival',
            'participant_type' => 'team',
            'event_type' => 'tournament',
            'age_category' => 'U-12',
            'min_players_per_team' => 11,
            'max_players_per_team' => 18,
            'min_gk_per_team' => 2,
            'max_officials_per_team' => 5,
            'required_documents' => ['birth_certificate'],
            'max_teams' => 8,
            'registration_fee' => 0,
            'lock_roster_until_paid' => false,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'status' => 'open',
            'tnc_version_id' => $tnc->id,
        ]);

        $rendered = $tnc->renderContentForEvent($event);

        $this->assertStringContainsString('<b>Tebal B</b>', $rendered);
        $this->assertStringContainsString('<strong>Strong</strong>', $rendered);
        $this->assertStringContainsString('<i>Miring I</i>', $rendered);
        $this->assertStringContainsString('<em>Em</em>', $rendered);
        $this->assertStringContainsString('<u>Garis Bawah</u>', $rendered);
        $this->assertStringContainsString('text-align:center', $rendered);
        $this->assertStringContainsString('font-weight:bold', $rendered);
        $this->assertStringContainsString('font-style:italic', $rendered);
        $this->assertStringContainsString('<ul><li>Poin 1</li><li>Poin 2</li></ul>', $rendered);
    }
}
