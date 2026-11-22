<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class BilingualPublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the default locale for the public website is Bangla ('bn').
     */
    public function test_default_locale_is_bangla(): void
    {
        $response = $this->get(route('home'));

        $response->assertStatus(200);
        $this->assertEquals('bn', App::getLocale());
        $response->assertSee('৩ জন বিশেষায়িত এআই কর্মী', false);
        $response->assertSee('lang="bn"', false);
    }

    /**
     * Test language switch route sets session, cookie, and redirects.
     */
    public function test_switching_locale_to_english_sets_session_and_cookie(): void
    {
        $response = $this->get(route('locale.switch', ['lang' => 'en']));

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'en');
        $response->assertCookie('app_locale', 'en');
    }

    /**
     * Test that switching to English renders the English marketing copy.
     */
    public function test_visiting_home_in_english_renders_english_content(): void
    {
        $response = $this->withSession(['locale' => 'en'])->get(route('home'));

        $response->assertStatus(200);
        $this->assertEquals('en', App::getLocale());
        $response->assertSee('Power Your Business With', false);
        $response->assertSee('3 Specialized AI Employees', false);
        $response->assertSee('Customer Sales Agent', false);
        $response->assertSee('lang="en"', false);
    }

    /**
     * Test switching back from English to Bangla.
     */
    public function test_switching_back_to_bangla(): void
    {
        $response = $this->withSession(['locale' => 'en'])
            ->get(route('locale.switch', ['lang' => 'bn']));

        $response->assertRedirect();
        $response->assertSessionHas('locale', 'bn');
        $response->assertCookie('app_locale', 'bn');

        $homeResponse = $this->withSession(['locale' => 'bn'])->get(route('home'));
        $homeResponse->assertStatus(200);
        $this->assertEquals('bn', App::getLocale());
        $homeResponse->assertSee('৩ জন সার্বক্ষণিক এআই কর্মী', false);
    }

    /**
     * Test solutions page is bilingual.
     */
    public function test_solutions_page_renders_in_both_locales(): void
    {
        // Default Bangla
        $bnResponse = $this->get(route('solutions'));
        $bnResponse->assertStatus(200);
        $bnResponse->assertSee('৩ জন এআই কর্মী — আপনার ব্যবসার<br>তিনটি প্রধান বিভাগের দায়িত্বে।', false);

        // Switch to English
        $enResponse = $this->withSession(['locale' => 'en'])->get(route('solutions'));
        $enResponse->assertStatus(200);
        $enResponse->assertSee('3 Specialized AI Employees —<br>Dedicated to Your Core Departments.', false);
    }

    /**
     * Test pricing page is bilingual.
     */
    public function test_pricing_page_renders_in_both_locales(): void
    {
        // Default Bangla
        $bnResponse = $this->get(route('pricing'));
        $bnResponse->assertStatus(200);
        $bnResponse->assertSee('৩ জন এআই কর্মী • স্বচ্ছ ও সহজ মূল্যতালিকা', false);
        $bnResponse->assertSee('সহজ ও স্বচ্ছ মূল্যতালিকা', false);

        // Switch to English
        $enResponse = $this->withSession(['locale' => 'en'])->get(route('pricing'));
        $enResponse->assertStatus(200);
        $enResponse->assertSee('Simple, Fair Pricing', false);
        $enResponse->assertSee('Recharge Estimate Calculator', false);
        $enResponse->assertSee('Pricing &amp; Billing FAQ', false);
    }

    /**
     * Test header language toggle pill alternates correctly.
     */
    public function test_header_language_toggle_pill_alternates(): void
    {
        // When in bn, pill offers switch to English
        $bnResponse = $this->get(route('home'));
        $bnResponse->assertStatus(200);
        $bnResponse->assertSee(route('locale.switch', ['lang' => 'en']), false);

        // When in en, pill offers switch to Bangla
        $enResponse = $this->withSession(['locale' => 'en'])->get(route('home'));
        $enResponse->assertStatus(200);
        $enResponse->assertSee(route('locale.switch', ['lang' => 'bn']), false);
    }

    /**
     * Test all remaining public pages render in both Bangla and English locales.
     */
    public function test_all_public_pages_render_in_both_locales(): void
    {
        $pages = [
            'platform' => [
                'bn' => 'কাস্টমার অপারেশনস ও বিজনেস ডেটার জন্য<br>একটি সমন্বিত আর্কিটেকচার',
                'en' => 'One Unified Architecture for<br>Customer Operations & Business Data.',
            ],
            'knowledge-engine' => [
                'bn' => 'বাস্তব কমার্স বিজনেসের জন্য<br>এন্টারপ্রাইজ নলেজ গ্রাউন্ডিং',
                'en' => 'Enterprise Knowledge Grounding<br>for Complex Commerce.',
            ],
            'unified-inbox' => [
                'bn' => 'সব কাস্টমার সংলাপের জন্য<br>একটি মাত্র সমন্বিত ইনবক্স',
                'en' => 'One Unified Inbox for<br>All Customer Dialogues.',
            ],
            'business-intelligence' => [
                'bn' => 'ব্যবসায়িক সিদ্ধান্তের কার্যকর অ্যানালিটিক্স,<br>নিছক চ্যাট হিস্ট্রি নয়',
                'en' => 'Actionable Business Intelligence,<br>Not Just Chat Logs.',
            ],
            'automation' => [
                'bn' => 'স্মার্ট অপারেশনস।<br>স্বয়ংক্রিয় বিজনেস ওয়ার্কফ্লো।',
                'en' => 'Smarter Operations.<br>Automated Workflows.',
            ],
            'integrations' => [
                'bn' => 'আপনার কমার্স আর্কিটেকচারের সাথে<br>সম্পূর্ণ সমন্বিত',
                'en' => 'Integrated With<br>Your Commerce Stack.',
            ],
            'about' => [
                'bn' => 'ইন্টেলিজেন্ট কমার্সের<br>অপারেটিং সিস্টেম তৈরি করছি',
                'en' => 'Building the Operating System for<br>Intelligent Commerce.',
            ],
            'contact' => [
                'bn' => 'আসুন আপনার ব্যবসার<br>অপারেশন নিয়ে আলোচনা করি',
                'en' => "Let's Discuss<br>Your Business Operations.",
            ],
            'resources' => [
                'bn' => 'রিসোর্স, গাইড ও<br>টেকনিক্যাল রেফারেন্স',
                'en' => 'Resources, Guides &<br>Technical References.',
            ],
            'faq' => [
                'bn' => 'সচরাচর জিজ্ঞাসিত',
                'en' => 'Frequently Asked',
            ],
            'privacy' => [
                'bn' => 'এন্টারপ্রাইজ ডেটা গভর্ন্যান্স ও<br>প্রাইভেসি পলিসি',
                'en' => 'Enterprise Data Governance &<br>Privacy Policy',
            ],
            'terms' => [
                'bn' => 'মাস্টার সাবস্ক্রিপশন চুক্তি ও<br>সেবার শর্তাবলী (Terms of Service)',
                'en' => 'Master Subscription Agreement &<br>Terms of Service',
            ],
            'cookies' => [
                'bn' => 'ব্রাউজার স্টোরেজ প্রকাশনা ও<br>কুকি পলিসি',
                'en' => 'Browser Storage Disclosure &<br>Cookie Policy',
            ],
        ];

        foreach ($pages as $route => $expected) {
            // Bangla (default)
            $this->flushSession();
            $bnResponse = $this->withSession(['locale' => 'bn'])->get(route($route));
            $bnResponse->assertStatus(200);
            $bnResponse->assertSee($expected['bn'], false);

            // English
            $enResponse = $this->withSession(['locale' => 'en'])->get(route($route));
            $enResponse->assertStatus(200);
            $enResponse->assertSee($expected['en'], false);
        }
    }

    /**
     * Test invalid locale parameter defaults safely without errors.
     */
    public function test_invalid_locale_switch_defaults_safely(): void
    {
        $response = $this->get('/locale/unsupported_locale');

        $response->assertRedirect();
        // Should not crash and session should not be set to invalid locale
        $this->assertNotEquals('unsupported_locale', session('locale'));
    }
}
