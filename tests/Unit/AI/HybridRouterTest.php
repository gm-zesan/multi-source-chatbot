<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\Models\Conversation;
use PHPUnit\Framework\TestCase;

class HybridRouterTest extends TestCase
{
    private HybridRouter $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new HybridRouter();
    }

    public function test_category_1_pure_chat(): void
    {
        $cases = [
            'Hello'                          => RouteType::CHAT,
            'hi'                             => RouteType::CHAT,
            'Good morning'                   => RouteType::CHAT,
            'Assalamu Alaikum'               => RouteType::CHAT,
            'আসসালামু আলাইকুম'               => RouteType::CHAT,
            'হাই ভাই কেমন আছেন?'             => RouteType::CHAT,
            'kemon asen vai'                 => RouteType::CHAT,
            'hello vai'                      => RouteType::CHAT,
            'dhonnobad vai'                  => RouteType::CHAT,
            'ধন্যবাদ'                        => RouteType::CHAT,
            'Thank you so much'              => RouteType::CHAT,
            'who are you?'                   => RouteType::CHAT,
            'are you a bot or human?'        => RouteType::CHAT,
            'বিদায় ভালো থাকবেন'             => RouteType::CHAT,
            'allah hafez bro'                => RouteType::CHAT,
        ];

        foreach ($cases as $phrase => $expectedRoute) {
            $result = $this->router->route($phrase);
            $this->assertSame($expectedRoute, $result->route, "Category 1 Pure Chat failed for: '{$phrase}'");
            $this->assertGreaterThanOrEqual(0.90, $result->confidence);
        }
    }

    public function test_category_2_greeting_plus_knowledge(): void
    {
        $cases = [
            'hi, how do I change my payment method?'      => RouteType::KNOWLEDGE,
            'hello, what plans are available?'           => RouteType::KNOWLEDGE,
            'সালাম ভাই, রিফান্ড পলিসি কি?'                => RouteType::KNOWLEDGE,
            'hey there, how is my data encrypted?'       => RouteType::KNOWLEDGE,
            'vai payment method kivabe change korbo?'     => RouteType::KNOWLEDGE,
            'hello bro, where can I find my invoices?'   => RouteType::KNOWLEDGE,
            'Good morning! What is your return policy?'  => RouteType::KNOWLEDGE,
            'শুভ সকাল, আপনাদের ডেলিভারি চার্জ কত?'      => RouteType::KNOWLEDGE,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Category 2 Greeting+Knowledge failed for: '{$query}'");
            $this->assertSame('knowledge_inquiry', $result->intent);
        }
    }

    public function test_category_3_greeting_plus_action(): void
    {
        $cases = [
            'hello, please cancel my order #1024'        => ['route' => RouteType::ACTION, 'intent' => 'cancel_order', 'order_id' => 1024],
            'hi vai, order ta cancel kore den'           => ['route' => RouteType::ACTION, 'intent' => 'cancel_order', 'order_id' => null],
            'আসসালামু আলাইকুম, আমার অর্ডার #502 বাতিল করুন' => ['route' => RouteType::ACTION, 'intent' => 'cancel_order', 'order_id' => 502],
            'hey, track order #2048'                     => ['route' => RouteType::ACTION, 'intent' => 'get_order', 'order_id' => 2048],
            'hello, create a ticket for billing issue'   => ['route' => RouteType::ACTION, 'intent' => 'create_ticket', 'order_id' => null],
        ];

        foreach ($cases as $query => $exp) {
            $result = $this->router->route($query);
            $this->assertSame($exp['route'], $result->route, "Category 3 Greeting+Action failed for: '{$query}'");
            $this->assertSame($exp['intent'], $result->intent);
            if ($exp['order_id'] !== null) {
                $this->assertSame($exp['order_id'], $result->entities['order_id'] ?? null);
            }
        }
    }

    public function test_category_4_thanks_plus_knowledge(): void
    {
        $cases = [
            'thanks, but how do I view my invoices?'       => RouteType::KNOWLEDGE,
            'ধন্যবাদ, কিন্তু ডেলিভারি চার্জ কত?'            => RouteType::KNOWLEDGE,
            'thank you, can I use multiple channels?'      => RouteType::KNOWLEDGE,
            'dhonnobad, password reset korbo kivabe?'      => RouteType::KNOWLEDGE,
            'thanks a lot, where do I find the API key?'   => RouteType::KNOWLEDGE,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Category 4 Thanks+Knowledge failed for: '{$query}'");
        }
    }

    public function test_category_5_thanks_plus_action(): void
    {
        $cases = [
            'thanks, but cancel my order #1024'             => ['route' => RouteType::ACTION, 'intent' => 'cancel_order', 'order_id' => 1024],
            'ধন্যবাদ, কিন্তু অর্ডার #555 বাতিল করুন'           => ['route' => RouteType::ACTION, 'intent' => 'cancel_order', 'order_id' => 555],
            'thank you, please create a ticket for billing' => ['route' => RouteType::ACTION, 'intent' => 'create_ticket', 'order_id' => null],
            'onek dhonnobad, amar order #99 cancel kore den'=> ['route' => RouteType::ACTION, 'intent' => 'cancel_order', 'order_id' => 99],
        ];

        foreach ($cases as $query => $exp) {
            $result = $this->router->route($query);
            $this->assertSame($exp['route'], $result->route, "Category 5 Thanks+Action failed for: '{$query}'");
            $this->assertSame($exp['intent'], $result->intent);
            if ($exp['order_id'] !== null) {
                $this->assertSame($exp['order_id'], $result->entities['order_id'] ?? null);
            }
        }
    }

    public function test_category_6_question_with_mutation_keyword_is_knowledge(): void
    {
        $cases = [
            'Can you tell me if I can cancel my order?' => RouteType::KNOWLEDGE,
            'order cancel kora jabe ki?'                => RouteType::KNOWLEDGE,
            'অর্ডার বাতিল করার নিয়ম কি?'                 => RouteType::KNOWLEDGE,
            'how do I cancel my subscription?'          => RouteType::KNOWLEDGE,
            'is it possible to refund after 7 days?'    => RouteType::KNOWLEDGE,
            'cancel korbo naki?'                        => RouteType::KNOWLEDGE,
            'amar order cancel hoyeche?'                => RouteType::KNOWLEDGE,
            'payment method kivabe change korbo?'       => RouteType::KNOWLEDGE,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Category 6 Question+MutationKeyword failed for: '{$query}'");
            $this->assertSame('knowledge_inquiry', $result->intent);
        }
    }

    public function test_category_7_casual_mutation_is_action(): void
    {
        $cases = [
            'order ta cancel kore den'             => ['route' => RouteType::ACTION, 'intent' => 'cancel_order'],
            'amar order cancel korun'              => ['route' => RouteType::ACTION, 'intent' => 'cancel_order'],
            'আমার অর্ডারটি বাতিল করে দিন'           => ['route' => RouteType::ACTION, 'intent' => 'cancel_order'],
            'vai payment method change kore den'   => ['route' => RouteType::ACTION, 'intent' => 'update_payment_method'],
            'please cancel my order #777'          => ['route' => RouteType::ACTION, 'intent' => 'cancel_order'],
        ];

        foreach ($cases as $query => $exp) {
            $result = $this->router->route($query);
            $this->assertSame($exp['route'], $result->route, "Category 7 Casual Mutation failed for: '{$query}'");
            $this->assertSame($exp['intent'], $result->intent);
        }
    }

    public function test_category_8_code_switching(): void
    {
        $cases = [
            'hi vai, invoice ta kothay pabo?'                => RouteType::KNOWLEDGE,
            'vai amar order #1024 cancel kore den'           => RouteType::ACTION,
            'security encryption kivabe kaj kore bolben?'    => RouteType::KNOWLEDGE,
            'open a ticket for double billing ভাই'           => RouteType::ACTION,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Category 8 Code-Switching failed for: '{$query}'");
        }
    }

    public function test_category_9_banglish_typos_and_colloquial(): void
    {
        $cases = [
            'payment kivabe chng korbo?'         => RouteType::KNOWLEDGE,
            'order ta cancel kora jbe?'          => RouteType::KNOWLEDGE,
            'vai refund policy ki bolbn?'        => RouteType::KNOWLEDGE,
            'passwrd reset kivbe krbo?'          => RouteType::KNOWLEDGE,
            'plz amar order #1024 cncl kore den' => RouteType::ACTION,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Category 9 Typo/Colloquial failed for: '{$query}'");
        }
    }

    public function test_category_10_bare_keyword_is_uncertain(): void
    {
        $cases = [
            'cancel'        => RouteType::UNCERTAIN,
            'change'        => RouteType::UNCERTAIN,
            'order cancel'  => RouteType::UNCERTAIN,
            'refund'        => RouteType::UNCERTAIN,
            'hello cancel'  => RouteType::UNCERTAIN,
            'thanks cancel' => RouteType::UNCERTAIN,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Category 10 Bare Keyword failed for: '{$query}'");
        }
    }

    public function test_category_11_ambiguous_pronoun_is_uncertain(): void
    {
        $cases = [
            'cancel this'               => RouteType::UNCERTAIN,
            'eta cancel kore den'       => RouteType::UNCERTAIN,
            'please cancel it'          => RouteType::UNCERTAIN,
            'এটা পরিবর্তন করে দিন'      => RouteType::UNCERTAIN,
            'ota change korun'          => RouteType::UNCERTAIN,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Category 11 Pronoun without entity failed for: '{$query}'");
        }
    }

    public function test_category_12_contextual_confirmation_and_standalone(): void
    {
        $pendingConv = new Conversation([
            'id' => 999,
            'metadata' => [
                'pending_action' => [
                    'action'     => 'cancel_order',
                    'parameters' => ['order_id' => 1024],
                ],
            ],
        ]);

        // 1. Pending Context: Affirmation -> ACTION
        $resConfirm = $this->router->route('হ্যাঁ', $pendingConv);
        $this->assertSame(RouteType::ACTION, $resConfirm->route);
        $this->assertSame('action_confirmation', $resConfirm->intent);
        $this->assertSame(1024, $resConfirm->entities['order_id']);

        $resYes = $this->router->route('yes please', $pendingConv);
        $this->assertSame(RouteType::ACTION, $resYes->route);
        $this->assertSame('action_confirmation', $resYes->intent);

        // 2. Pending Context: Rejection / Goodbye -> CHAT (Rejection)
        $resReject = $this->router->route('না, দরকার নেই', $pendingConv);
        $this->assertSame(RouteType::CHAT, $resReject->route);
        $this->assertSame('action_rejection', $resReject->intent);

        $resBye = $this->router->route('bye', $pendingConv);
        $this->assertSame(RouteType::CHAT, $resBye->route);
        $this->assertSame('action_rejection', $resBye->intent);

        // 3. Standalone (No pending action): "yes" / "হ্যাঁ" / "no" / "না" -> CHAT
        $resStandaloneYes = $this->router->route('yes');
        $this->assertSame(RouteType::CHAT, $resStandaloneYes->route);
        $this->assertSame('affirmation', $resStandaloneYes->intent);

        $resStandaloneNo = $this->router->route('না');
        $this->assertSame(RouteType::CHAT, $resStandaloneNo->route);
        $this->assertSame('negation', $resStandaloneNo->intent);
    }

    public function test_category_13_ood_with_greeting(): void
    {
        $cases = [
            'hi, what is the weather in Dhaka today?'       => RouteType::OOD,
            'hello, recipe for chicken biryani'            => RouteType::OOD,
            'হ্যালো ভাই, আজকের আবহাওয়া কেমন?'              => RouteType::OOD,
            'hey bro, who won the football match?'         => RouteType::OOD,
            'good morning! Who is the president of BD?'   => RouteType::OOD,
            'asdf ghjk qwerty zxcvbnm'                     => RouteType::OOD,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame($expectedRoute, $result->route, "Category 13 OOD failed for: '{$query}'");
        }
    }

    public function test_adversarial_boundary_queries(): void
    {
        $cases = [
            'can I please cancel?'                => RouteType::KNOWLEDGE,
            'please tell me how to cancel'        => RouteType::KNOWLEDGE,
            'I want to know how to cancel'        => RouteType::KNOWLEDGE,
            'I want to cancel'                    => RouteType::UNCERTAIN,
            'I think I want to cancel'            => RouteType::UNCERTAIN,
            'maybe cancel this'                   => RouteType::UNCERTAIN,
            'cancel my order'                     => RouteType::ACTION,
            'please cancel my order'              => RouteType::ACTION,
        ];

        foreach ($cases as $query => $expectedRoute) {
            $result = $this->router->route($query);
            $this->assertSame(
                $expectedRoute,
                $result->route,
                "Adversarial test failed for: '{$query}'. Got: {$result->route->value} (intent: {$result->intent}, conf: {$result->confidence})"
            );
        }
    }
}
