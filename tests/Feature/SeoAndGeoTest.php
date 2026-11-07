<?php

namespace Tests\Feature;

use App\Models\Event;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoAndGeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemSettingSeeder::class);
    }

    protected function seedPublishedAndDraftEvents(): array
    {
        $published = Event::create([
            'name' => 'Persija Young Tiger Cup U-12',
            'slug' => 'persija-young-tiger-cup-u12',
            'short_description' => 'Turnamen resmi sepak bola usia dini di Jakarta.',
            'venue_name' => 'Stadion Soemantri Brodjonegoro',
            'city' => 'Jakarta Selatan',
            'format' => 'festival',
            'age_category' => 'U-12',
            'min_birth_date' => '2014-01-01',
            'max_birth_date' => '2015-12-31',
            'min_players_per_team' => 11,
            'max_players_per_team' => 18,
            'min_gk_per_team' => 2,
            'max_officials_per_team' => 4,
            'show_coach_license' => true,
            'require_coach_license' => false,
            'required_documents' => ['birth_certificate'],
            'max_teams' => 16,
            'registration_fee' => 1500000,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'status' => 'open',
        ]);

        $draft = Event::create([
            'name' => 'Turnamen Internal Rahasia Draft',
            'slug' => 'turnamen-internal-rahasia-draft',
            'venue_name' => 'Lapangan Latihan',
            'city' => 'Depok',
            'format' => 'cup',
            'age_category' => 'U-15',
            'min_players_per_team' => 11,
            'max_players_per_team' => 18,
            'max_officials_per_team' => 4,
            'required_documents' => ['birth_certificate'],
            'max_teams' => 8,
            'registration_fee' => 0,
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'status' => 'draft',
        ]);

        return compact('published', 'draft');
    }

    public function test_admin_login_and_admin_routes_are_hidden_from_search_engines_via_headers_and_meta(): void
    {
        $loginResponse = $this->get(route('admin.login'));

        $loginResponse->assertOk();
        $loginResponse->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
        $loginResponse->assertSee('noindex, nofollow, noarchive, nosnippet', false);

        $entryResponse = $this->get(route('admin.entry'));
        $entryResponse->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
    }

    public function test_public_home_and_event_detail_are_indexable_and_include_json_ld_structured_data(): void
    {
        $events = $this->seedPublishedAndDraftEvents();
        /** @var Event $published */
        $published = $events['published'];

        $homeResponse = $this->get(route('home'));
        $homeResponse->assertOk();
        $homeResponse->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large, max-snippet:-1');
        $homeResponse->assertSee('application/ld+json', false);
        $homeResponse->assertSee('SportsOrganization', false);
        // Ensure admin login link is not exposed on public layout footer
        $homeResponse->assertDontSee(route('admin.login'), false);

        $detailResponse = $this->get(route('events.show', ['event' => $published->slug]));
        $detailResponse->assertOk();
        $detailResponse->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large, max-snippet:-1');
        $detailResponse->assertSee('rel="canonical"', false);
    }

    public function test_robots_txt_disallows_admin_and_allows_public_and_ai_crawlers(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /admin/login', false);
        $response->assertSee('Disallow: /manager', false);
        $response->assertSee('Disallow: /private-media/', false);
        $response->assertSee('Allow: /llms.txt', false);
        $response->assertSee('User-agent: GPTBot', false);
        $response->assertSee('User-agent: PerplexityBot', false);
        $response->assertSee('Sitemap: ' . url('/sitemap.xml'), false);
    }

    public function test_sitemap_xml_includes_public_events_and_excludes_admin_and_draft_events(): void
    {
        $events = $this->seedPublishedAndDraftEvents();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(route('events.show', ['event' => $events['published']->slug]), false);
        $response->assertDontSee(route('events.show', ['event' => $events['draft']->slug]), false);
        $response->assertDontSee('/admin', false);
    }

    public function test_llms_txt_and_llms_full_txt_provide_structured_ai_generative_engine_context(): void
    {
        $events = $this->seedPublishedAndDraftEvents();

        $llmsResponse = $this->get('/llms.txt');
        $llmsResponse->assertOk();
        $llmsResponse->assertSee($events['published']->name, false);
        $llmsResponse->assertDontSee($events['draft']->name, false);
        $llmsResponse->assertDontSee('/admin', false);

        $llmsFullResponse = $this->get('/llms-full.txt');
        $llmsFullResponse->assertOk();
        $llmsFullResponse->assertSee($events['published']->name, false);
        $llmsFullResponse->assertSee('Stadion Soemantri Brodjonegoro', false);
        $llmsFullResponse->assertSee('Wajib minimal 2 Penjaga Gawang / GK', false);
        $llmsFullResponse->assertDontSee($events['draft']->name, false);
    }
}
