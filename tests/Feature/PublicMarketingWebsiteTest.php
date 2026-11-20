<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContactForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ['pricing', 'Simple, Scalable Plans for High-Growth Commerce'],
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
}
