<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use PHPUnit\Framework\TestCase;

class HybridRouterAnalyticsTest extends TestCase
{
    private HybridRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new HybridRouter();
    }

    /**
     * Test Bangla Business Analytics Queries.
     */
    public function test_bangla_analytics_routing(): void
    {
        $cases = [
            'আজকে মোট কত টাকার বিক্রি হয়েছে?'       => RouteType::ANALYTICS,
            'আজকে কত ক্যাশ কালেকশন হয়েছে?'          => RouteType::ANALYTICS,
            'সবচেয়ে বেশি বকেয়া কোন কাস্টমারের?'     => RouteType::ANALYTICS,
            'সবচেয়ে বেশি বিক্রি হওয়া প্রোডাক্ট কোনটি?' => RouteType::ANALYTICS,
            'এই মাসে মোট ক্যাশ কালেকশন কত?'         => RouteType::ANALYTICS,
            'বকেয়া আদায়ের দায়িত্ব কার?'             => RouteType::ANALYTICS,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Bangla analytics routing failed for: '{$query}'");
            $this->assertGreaterThanOrEqual(0.75, $result->confidence);
        }
    }

    /**
     * Test English Business Analytics Queries.
     */
    public function test_english_analytics_routing(): void
    {
        $cases = [
            "What is today's total sales?"             => RouteType::ANALYTICS,
            "What is today's total cash collection?"    => RouteType::ANALYTICS,
            "Which customers have outstanding due?"     => RouteType::ANALYTICS,
            "Show me the top 3 selling products"       => RouteType::ANALYTICS,
            "Customer due list"                        => RouteType::ANALYTICS,
            "Who is assigned for due collection?"      => RouteType::ANALYTICS,
            "Total order volume this month"            => RouteType::ANALYTICS,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "English analytics routing failed for: '{$query}'");
            $this->assertGreaterThanOrEqual(0.75, $result->confidence);
        }
    }

    /**
     * Test Banglish Business Analytics Queries.
     */
    public function test_banglish_analytics_routing(): void
    {
        $cases = [
            'Aj total koto sales hoise?'              => RouteType::ANALYTICS,
            'Aj koto cashin hoise?'                   => RouteType::ANALYTICS,
            'Kar sobcheye beshi due baki ase?'        => RouteType::ANALYTICS,
            'Customer due list dekhte chai'           => RouteType::ANALYTICS,
            'Top selling product konta?'              => RouteType::ANALYTICS,
            'Ei mashe total collection koto?'         => RouteType::ANALYTICS,
            'Due collection er assignment kar?'       => RouteType::ANALYTICS,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Banglish analytics routing failed for: '{$query}'");
            $this->assertGreaterThanOrEqual(0.75, $result->confidence);
        }
    }

    /**
     * Test Dynamic Named Queries WITHOUT Hardcoded Benchmark Names.
     * Must work for benchmark names AND arbitrary unseeded names via generic linguistic patterns.
     */
    public function test_named_salesperson_and_customer_analytics_without_hardcoding(): void
    {
        $cases = [
            // Seeded benchmark names
            'How much payment did Rakib collect?'     => RouteType::ANALYTICS,
            'How much did Hasan sell?'                => RouteType::ANALYTICS,
            'What is Karim\'s outstanding due?'       => RouteType::ANALYTICS,
            'Hasan er total sales koto?'              => RouteType::ANALYTICS,
            'Rakib er cash collection koto?'          => RouteType::ANALYTICS,
            'Rahim er due koto?'                      => RouteType::ANALYTICS,
            // Arbitrary unseeded names (proving zero hardcoding)
            'How much did Sophia sell?'               => RouteType::ANALYTICS,
            'How much payment did David collect?'     => RouteType::ANALYTICS,
            'Alex er collection koto?'                => RouteType::ANALYTICS,
            'Tanvir er sales report dekhte chai'      => RouteType::ANALYTICS,
            'Karim এর মোট বকেয়া কত?'                 => RouteType::ANALYTICS,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Named analytics routing failed for: '{$query}'");
            $this->assertGreaterThanOrEqual(0.75, $result->confidence);
        }
    }

    /**
     * Regression Guard: SaaS Platform Setup, Billing Settings & FAQs MUST Remain KNOWLEDGE.
     * Even though they contain words like "payment", "method", "account", "invoice".
     */
    public function test_knowledge_protection_against_analytics_false_positives(): void
    {
        $cases = [
            'How do I update my payment method?'          => RouteType::KNOWLEDGE,
            'How can I change my password?'               => RouteType::KNOWLEDGE,
            'How do I connect WhatsApp?'                  => RouteType::KNOWLEDGE,
            'How do I connect Telegram?'                  => RouteType::KNOWLEDGE,
            'Can I change my plan?'                       => RouteType::KNOWLEDGE,
            'How do I view my subscription invoices?'     => RouteType::KNOWLEDGE,
            'Why is my chatbot not responding?'           => RouteType::KNOWLEDGE,
            'What should I do if I encounter an error?'   => RouteType::KNOWLEDGE,
            'How do I create an account?'                 => RouteType::KNOWLEDGE,
            'What is your refund policy?'                 => RouteType::KNOWLEDGE,
            // Bangla & Banglish FAQs
            'আমি কীভাবে আমার পেমেন্ট মেথড পরিবর্তন করব?'    => RouteType::KNOWLEDGE,
            'হোয়াটসঅ্যাপ কীভাবে কানেক্ট করব?'            => RouteType::KNOWLEDGE,
            'payment method kivabe change korbo?'         => RouteType::KNOWLEDGE,
            'kivabe notun account create korbo?'          => RouteType::KNOWLEDGE,
            'password reset korbo kemne?'                 => RouteType::KNOWLEDGE,
            'chatbot response korche na keno?'            => RouteType::KNOWLEDGE,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Knowledge false-positive regression on: '{$query}'");
        }
    }

    /**
     * Regression Guard: Existing CHAT, ACTION, and OOD routes must be 100% preserved.
     */
    public function test_existing_chat_action_ood_preservation(): void
    {
        // CHAT
        $chatQueries = [
            'Hello',
            'Good morning',
            'Hi, kemon achen?',
            'কেমন আছেন?',
            'Who are you?',
            'Thank you so much',
            'ধন্যবাদ',
        ];
        foreach ($chatQueries as $q) {
            $this->assertSame(RouteType::CHAT, $this->router->route($q)->route, "Chat preservation failed for: '{$q}'");
        }

        // ACTION
        $actionQueries = [
            'Please cancel my order #1024',
            'track order #502',
            'আমার অর্ডার #1024 বাতিল করুন',
        ];
        foreach ($actionQueries as $q) {
            $this->assertSame(RouteType::ACTION, $this->router->route($q)->route, "Action preservation failed for: '{$q}'");
        }

        // OOD
        $oodQueries = [
            'Who will win the football match tomorrow?',
            'What is the weather forecast in Dhaka?',
            'How do I bake a chocolate cake?',
        ];
        foreach ($oodQueries as $q) {
            $this->assertSame(RouteType::OOD, $this->router->route($q)->route, "OOD preservation failed for: '{$q}'");
        }
    }
}
