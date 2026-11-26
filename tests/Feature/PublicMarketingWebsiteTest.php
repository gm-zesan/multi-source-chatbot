<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permissions\MessagePermission;
use App\Models\ContactForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PublicMarketingWebsiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test all public marketing routes return 200 OK and render correctly.
     *
     * @dataProvider publicRoutesProvider
     */
    public function test_public_routes_render_successfully(string $routeName, string $expectedContent): void
    {
        $response = $this->get(route($routeName));

        $response->assertStatus(200);
        $response->assertSee($expectedContent, false);
        $response->assertSee('Entrepreneurs Automation', false);
        $response->assertSee('data-theme', false);
    }

    public static function publicRoutesProvider(): array
    {
        return [
            ['home', 'Your Business. Connected. Intelligent. Automated.'],
            ['platform', 'Platform Overview'],
            ['knowledge-engine', 'Enterprise Knowledge Grounding for Complex Commerce'],
            ['unified-inbox', 'One Unified Inbox for All Customer Dialogues'],
            ['business-intelligence', 'Actionable Business Intelligence, Not Just Chat Logs'],
            ['automation', 'Smarter Operations. Automated Workflows'],
            ['integrations', 'Integrated With Your Commerce Stack'],
            ['solutions', 'Tailored for High-Velocity Modern Commerce'],
            ['pricing', 'Simple, Fair Pricing'],
            ['about', 'Building the Operating System for Intelligent Commerce'],
            ['contact', "Let's Discuss Your Business Operations"],
            ['resources', 'Resources, Guides'],
            ['faq', 'Frequently Asked Questions'],
            ['privacy', 'Enterprise Data Governance'],
            ['terms', 'Master Subscription Agreement'],
            ['cookies', 'Browser Storage Disclosure'],
        ];
    }

    /**
     * Test the contact form submission endpoint persists to the database.
     */
    public function test_public_contact_form_submission_persists_to_database(): void
    {
        $payload = [
            'name' => 'Sarah Connor',
            'email' => 'sarah@cyberdyne.org',
            'phone' => '+15551234567',
            'subject' => 'Enterprise Architecture Consultation',
            'message' => 'We are looking to ground 50,000 product rows into an omni-channel customer support workflow.',
        ];

        $response = $this->post(route('contact.store'), $payload);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('contact_forms', [
            'name' => 'Sarah Connor',
            'email' => 'sarah@cyberdyne.org',
            'phone' => '+15551234567',
            'subject' => 'Enterprise Architecture Consultation',
            'read_status' => false,
            'important_status' => false,
        ]);
    }

    /**
     * Test contact form validation failure when required fields are missing.
     */
    public function test_public_contact_form_requires_mandatory_fields(): void
    {
        $response = $this->post(route('contact.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
        $this->assertEquals(0, ContactForm::count());
    }

    /**
     * Test contact form validation failure when email format is invalid.
     */
    public function test_public_contact_form_rejects_invalid_email_format(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => 'John Doe',
            'email' => 'invalid-email-address',
            'message' => 'Test inquiry message',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertEquals(0, ContactForm::count());
    }

    /**
     * Test contact form AJAX JSON submission returns JSON payload.
     */
    public function test_public_contact_form_ajax_submission_returns_json(): void
    {
        $payload = [
            'name' => 'Michael Scott',
            'email' => 'michael@dundermifflin.com',
            'phone' => '+15559876543',
            'subject' => 'Paper and Chatbot Automation',
            'message' => 'Can we automate inquiry triage for branch orders?',
        ];

        $response = $this->postJson(route('contact.store'), $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure(['success', 'message', 'id']);

        $this->assertDatabaseHas('contact_forms', [
            'name' => 'Michael Scott',
            'email' => 'michael@dundermifflin.com',
            'read_status' => false,
        ]);
    }

    /**
     * Test that public contact submissions appear in the existing Admin Panel and can be managed.
     */
    public function test_public_contact_form_submission_appears_in_admin_panel_and_can_be_managed(): void
    {
        Permission::firstOrCreate([
            'name' => MessagePermission::VIEW->value,
            'guard_name' => 'web',
        ], [
            'display_name' => 'View Messages',
            'module' => 'Message',
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@entrepreneurs.app',
        ]);
        $admin->givePermissionTo(MessagePermission::VIEW->value);

        // 1. Visitor submits contact form
        $this->post(route('contact.store'), [
            'name' => 'Enterprise Prospect',
            'email' => 'prospect@enterprise.com',
            'subject' => 'High Volume Deployment',
            'message' => 'We need 50,000 monthly inquiries handled across Messenger and Web Widget.',
        ]);

        $messageRecord = ContactForm::where('email', 'prospect@enterprise.com')->firstOrFail();
        $this->assertEquals(0, $messageRecord->read_status);
        $this->assertEquals(0, $messageRecord->important_status);

        // 2. Admin views messages index view
        $viewResponse = $this->actingAs($admin)->get(route('message'));
        $viewResponse->assertOk();
        $viewResponse->assertViewIs('admin.contact-messages.index');

        // 3. Admin fetches DataTables AJAX feed
        $ajaxResponse = $this->actingAs($admin)->getJson(route('message'), [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
        $ajaxResponse->assertOk();
        $ajaxResponse->assertJsonFragment([
            'email' => 'prospect@enterprise.com',
        ]);

        // 4. Admin marks message as read
        $readResponse = $this->actingAs($admin)->getJson(route('message.read', ['id' => $messageRecord->id]));
        $readResponse->assertOk();
        $this->assertEquals(1, $messageRecord->fresh()->read_status);

        // 5. Admin marks message as important
        $importantResponse = $this->actingAs($admin)->getJson(route('message.important', [
            'id' => $messageRecord->id,
            'important_status' => 1,
        ]));
        $importantResponse->assertOk();
        $this->assertEquals(1, $messageRecord->fresh()->important_status);
    }

    /**
     * Test sitemap.xml route returns 200 OK and valid XML.
     */
    public function test_public_sitemap_returns_valid_xml(): void
    {
        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertSee('<urlset', false);
        $response->assertSee('https://entrepreneurs.app/platform', false);
        $response->assertSee('https://entrepreneurs.app/contact', false);
    }

    /**
     * Test public pages include valid SEO metadata, canonical links, and OpenGraph tags.
     */
    public function test_public_pages_include_seo_and_opengraph_metadata(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('<title>Entrepreneurs Automation', false);
        $response->assertSee('<meta name="description"', false);
        $response->assertSee('<link rel="canonical"', false);
        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('<meta property="og:description"', false);
        $response->assertSee('<meta name="twitter:card"', false);
    }

    public function test_public_analytics_scripts_stay_disabled_in_non_production_by_default(): void
    {
        config([
            'services.analytics.enabled' => null,
            'services.analytics.ga4_id' => 'G-S3VWSH4268',
            'services.analytics.meta_pixel_id' => '2340393403466648',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('G-S3VWSH4268', false);
        $response->assertDontSee('2340393403466648', false);
    }

    public function test_public_analytics_scripts_render_when_enabled_and_lead_fires_on_contact_success(): void
    {
        config([
            'services.analytics.enabled' => true,
            'services.analytics.ga4_id' => 'G-S3VWSH4268',
            'services.analytics.meta_pixel_id' => '2340393403466648',
        ]);

        $homeResponse = $this->get(route('home'));
        $homeResponse->assertOk();
        $homeResponse->assertSee('https://www.googletagmanager.com/gtag/js?id=G-S3VWSH4268', false);
        $homeResponse->assertSee('gtag(\'config\', "G-S3VWSH4268")', false);
        $homeResponse->assertSee('fbq(\'init\', "2340393403466648")', false);
        $homeResponse->assertSee("fbq('track', 'PageView')", false);

        $contactResponse = $this->withSession(['success' => 'Submitted'])->get(route('contact'));
        $contactResponse->assertOk();
        $contactResponse->assertSee("gtag('event', 'generate_lead')", false);
        $contactResponse->assertSee("fbq('track', 'Lead')", false);
    }
}
