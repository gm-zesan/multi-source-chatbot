<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Agents\KnowledgeSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use App\Models\FAQ;
use App\Services\AI\CustomerSupportService;
use App\Services\AI\Evaluation\GenerationFaithfulnessEvaluator;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeSupportAgentTest extends TestCase
{
    use RefreshDatabase;

    private Conversation $conversation;
    private KnowledgeRetrievalTool $retrievalTool;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::create(['name' => 'Support Org', 'slug' => 'support-org']);
        $channel = Channel::create(['slug' => 'web', 'name' => 'Web Chat', 'is_active' => true]);
        $account = ChannelAccount::create([
            'channel_id' => $channel->id,
            'workspace_id' => $workspace->id,
            'name' => 'Main Web Chat',
            'external_id' => 'web_001',
            'access_token' => 'token',
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id' => 'cust_777',
            'customer_name' => 'Bob',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        $faqSearchMock = $this->createMock(FAQSearch::class);
        $this->retrievalTool = new KnowledgeRetrievalTool($faqSearchMock);
    }

    public function test_agent_instructions_contain_core_rules(): void
    {
        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Enterprise Customer Support AI Assistant', $instructions);
        $this->assertStringContainsString('Grounding & Verification', $instructions);
    }

    public function test_agent_loads_messages_from_conversation(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hello',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'Hi, how can I help you today?',
        ]);

        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $messages = iterator_to_array($agent->messages());

        $this->assertCount(2, $messages);
        $this->assertSame('Hello', $messages[0]->content);
        $this->assertSame('Hi, how can I help you today?', $messages[1]->content);
    }

    public function test_agent_tools_returns_configured_tools(): void
    {
        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $tools = iterator_to_array($agent->tools());

        $this->assertCount(1, $tools);
        $this->assertSame($this->retrievalTool, $tools[0]);
    }

    public function test_agent_prompt_executes_with_faked_llm_response(): void
    {
        // Fake the Laravel AI SDK Agent response
        KnowledgeSupportAgent::fake([
            'You can return your item within 30 days of receipt as long as it is in original packaging.',
        ]);

        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $response = $agent->prompt("What's your return policy?");

        $this->assertStringContainsString('30 days of receipt', (string) $response);
        KnowledgeSupportAgent::assertPrompted("What's your return policy?");
    }

    public function test_agent_instructions_contain_explicit_negative_boundary_constraints(): void
    {
        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $instructions = (string) $agent->instructions();

        // 1. Context authority & hierarchy
        $this->assertStringContainsString('Live Business Data (Layer 3) and Knowledge Base Documents (Layer 1) as the sole factual authority', $instructions);

        // 2. Explicit negative boundary constraints
        $this->assertStringContainsString('NEVER invent, extrapolate, or assume branch/showroom availability', $instructions);
        $this->assertStringContainsString('replacement windows, deadlines, fees, contact details', $instructions);
        $this->assertStringContainsString('Strictly distinguish an explicitly stated policy from an unknown or unverified policy', $instructions);

        // 3. Partial grounding & calibrated uncertainty
        $this->assertStringContainsString('answer the supported part clearly using the evidence, and explicitly acknowledge any unsupported sub-question', $instructions);
        $this->assertStringContainsString('Avoid unnecessary abstention: when the evidence directly answers the question', $instructions);

        // 4. Language style without hallucination
        $this->assertStringContainsString('Conversational fluency or informal phrasing must NEVER be treated as permission to invent or assume ungrounded facts', $instructions);
    }

    public function test_agent_instructions_contain_targeted_calibrated_uncertainty_examples(): void
    {
        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $instructions = (string) $agent->instructions();

        // Banglish Banani showroom missing scenario
        $this->assertStringContainsString('direct Banani showroom e giye exchange kora jabe?', $instructions);
        $this->assertStringContainsString('Banani showroom e direct exchange kora jabe kina ta amader policy te ullekh nei', $instructions);

        // Code-mixed replacement window missing scenario
        $this->assertStringContainsString('Size swap er replacement window koto din?', $instructions);
        $this->assertStringContainsString('replacement window ba koto diner moddhe exchange korte hobe ta amader tothye ullekh nei', $instructions);

        // Bangla physical location missing scenario
        $this->assertStringContainsString('আমি কি সরাসরি উত্তরা আউটলেটে গিয়ে প্রোডাক্ট রিটার্ন করতে পারব?', $instructions);
        $this->assertStringContainsString('উত্তরা আউটলেটে সরাসরি রিটার্ন গ্রহণ করা হয় কি না সে সম্পর্কিত তথ্য উল্লেখ নেই', $instructions);
    }

    public function test_agent_prompt_simulates_grounded_partial_answer_with_json_schema(): void
    {
        $rawOutput = json_encode([
            'answer' => 'Product intact thakle size er jonno online e exchange request kora jabe. Tobe Banani showroom e direct exchange kora jabe kina ta amader policy te ullekh nei; ei bishoye nishchit hote amader support team er shathe jogajog korte paren.',
            'proposed_follow_up' => null,
            'follow_up_type' => null,
        ], JSON_UNESCAPED_UNICODE);

        KnowledgeSupportAgent::fake([$rawOutput]);

        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $response = $agent->prompt('size na mille ki direct Banani showroom e giye exchange kora jabe?');

        $supportService = app(CustomerSupportService::class);
        $parsed = $supportService->parseAgentOutput((string) $response);

        $this->assertStringContainsString('online e exchange request kora jabe', $parsed['answer']);
        $this->assertStringContainsString('Banani showroom e direct exchange kora jabe kina ta amader policy te ullekh nei', $parsed['answer']);
        $this->assertNull($parsed['proposed_follow_up']);
        $this->assertNull($parsed['follow_up_type']);
    }

    public function test_agent_prompt_correct_answer_when_requested_facts_are_present(): void
    {
        $rawOutput = json_encode([
            'answer' => 'আমাদের পণ্য ক্রয়ের ৭ কার্যদিবসের মধ্যে অব্যবহৃত অবস্থায় সম্পূর্ণ বিনামূল্যে এক্সচেঞ্জ করা যাবে।',
            'proposed_follow_up' => 'আপনি কি অন্য কোনো সাইজ দেখতে চান?',
            'follow_up_type' => 'product_variant',
        ], JSON_UNESCAPED_UNICODE);

        KnowledgeSupportAgent::fake([$rawOutput]);

        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $response = $agent->prompt('এক্সচেঞ্জ করার নিয়ম কি?');

        $supportService = app(CustomerSupportService::class);
        $parsed = $supportService->parseAgentOutput((string) $response);

        $this->assertSame('আমাদের পণ্য ক্রয়ের ৭ কার্যদিবসের মধ্যে অব্যবহৃত অবস্থায় সম্পূর্ণ বিনামূল্যে এক্সচেঞ্জ করা যাবে।', $parsed['answer']);
        $this->assertSame('আপনি কি অন্য কোনো সাইজ দেখতে চান?', $parsed['proposed_follow_up']);
        $this->assertSame('product_variant', $parsed['follow_up_type']);
    }

    public function test_evaluator_distinguishes_unsupported_showroom_claim_from_calibrated_acknowledgment(): void
    {
        $evaluator = new GenerationFaithfulnessEvaluator();

        $faq = new FAQ();
        $faq->question = 'সাইজ না মিললে কি এক্সচেঞ্জ করা যাবে?';
        $faq->answer = 'হ্যাঁ, পণ্য গ্রহণের পর সাইজ না মিললে আপনি অনলাইন পোর্টালের মাধ্যমে ৭ কার্যদিবসের মধ্যে সাইজ এক্সচেঞ্জ করতে পারবেন। পণ্য অবশ্যই অব্যবহৃত ও ট্যাগসহ থাকতে হবে।';
        $faq->document_type = 'exchange_policy';

        $hit = new FAQSearchResult(faq: $faq, finalScore: 0.90);
        $hits = new EloquentCollection([$hit]);

        // Case A: Hallucinated Banani showroom claim (Fails grounding)
        $hallucinatedReply = "হ্যাঁ, আপনি আমাদের বনানী শোরুমে এসে সরাসরি প্রোডাক্ট পরিবর্তন করতে পারবেন। বনানী ব্রাঞ্চে কোনো ডেলিভারি ফি ছাড়া তাৎক্ষণিক রিপ্লেসমেন্ট দেওয়া হয়। আপনি সকাল ১০টা থেকে রাত ৮টার মধ্যে বনানী শোরুমে আসতে পারেন।";
        $evalA = $evaluator->evaluateTurn(
            query: 'size na mille ki direct Banani showroom e giye exchange kora jabe?',
            generatedReply: $hallucinatedReply,
            groundedHits: $hits,
            intentType: 'knowledge',
        );
        $this->assertTrue($evalA['has_unsupported_claims']);
        $this->assertFalse($evalA['is_grounded']);

        // Case B: Calibrated partial grounding (Passes grounding)
        $groundedPartialReply = "পণ্য অব্যবহৃত থাকলে এবং ট্যাগ অক্ষত থাকলে আপনি ৭ কার্যদিবসের মধ্যে সাইজ এক্সচেঞ্জ করতে পারবেন। তবে বনানী শোরুমে সরাসরি এক্সচেঞ্জ সম্ভব কি না তা পলিসিতে উল্লেখ নেই; বিস্তারিত তথ্যের জন্য আমাদের সাপোর্ট সেন্টারে যোগাযোগ করতে পারেন।";
        $evalB = $evaluator->evaluateTurn(
            query: 'size na mille ki direct Banani showroom e giye exchange kora jabe?',
            generatedReply: $groundedPartialReply,
            groundedHits: $hits,
            intentType: 'knowledge',
        );
        $this->assertFalse($evalB['has_unsupported_claims']);
        $this->assertTrue($evalB['is_grounded']);
        $this->assertTrue($evalB['is_faithful']);
    }

    public function test_evaluator_validates_bangla_banglish_and_code_mixed_grounding(): void
    {
        $evaluator = new GenerationFaithfulnessEvaluator();

        $faq = new FAQ();
        $faq->question = 'What is the return and refund policy?';
        $faq->answer = 'Products can be returned within 7 business days if unworn with tags intact. Refund will be processed to the original payment method.';
        $faq->document_type = 'refund_policy';

        $hit = new FAQSearchResult(faq: $faq, finalScore: 0.88);
        $hits = new EloquentCollection([$hit]);

        // 1. Native Bangla Grounded
        $bnReply = "পণ্য অব্যবহৃত এবং ট্যাগ অক্ষত থাকলে ৭ কার্যদিবসের মধ্যে রিটার্ন করতে পারবেন এবং রিফান্ড পেয়ে যাবেন।";
        $evalBn = $evaluator->evaluateTurn('রিটার্ন পলিসি কি?', $bnReply, $hits, null, 'knowledge');
        $this->assertTrue($evalBn['is_grounded']);
        $this->assertFalse($evalBn['has_unsupported_claims']);

        // 2. Banglish Grounded
        $banglishReply = "Product unused ebong tags intact thakle 7 business days er moddhe return ebong refund pawa jabe.";
        $evalBanglish = $evaluator->evaluateTurn('return policy ki?', $banglishReply, $hits, null, 'knowledge');
        $this->assertTrue($evalBanglish['is_grounded']);
        $this->assertFalse($evalBanglish['has_unsupported_claims']);

        // 3. Code-Mixed Grounded
        $codeMixedReply = "Size ba fitting problem hole 7 business days er moddhe return kore refund nite parben.";
        $evalCodeMixed = $evaluator->evaluateTurn('Item return kore refund kora jabe ki?', $codeMixedReply, $hits, null, 'knowledge');
        $this->assertTrue($evalCodeMixed['is_grounded']);
        $this->assertFalse($evalCodeMixed['has_unsupported_claims']);

        // 4. English Grounded
        $enReply = "You can return products within 7 business days if unworn with tags intact for a full refund.";
        $evalEn = $evaluator->evaluateTurn('What is the return policy?', $enReply, $hits, null, 'knowledge');
        $this->assertTrue($evalEn['is_grounded']);
        $this->assertFalse($evalEn['has_unsupported_claims']);
    }

    public function test_agent_instructions_contain_explicit_contact_details_and_showroom_negative_constraints(): void
    {
        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('contact details (including phone numbers, hotlines, email addresses, support handles, or URLs)', $instructions);
        $this->assertStringContainsString('When showroom/branch exchange or physical visit availability is not supported by the retrieved evidence, clearly state that the policy does not confirm it', $instructions);
        $this->assertStringContainsString('you must NEVER invent, fabricate, or append phone numbers, hotlines, or email addresses', $instructions);
    }

    public function test_sanitizer_removes_unsupported_hotline_from_banani_showroom_banglish_answer(): void
    {
        $faq = new FAQ();
        $faq->question = 'size na mille ki exchange kora jabe?';
        $faq->answer = 'Delivery er 5 diner moddhe size exchange request kora jabe jodi product unused thake.';
        $faq->document_type = 'exchange_policy';

        $hit = new FAQSearchResult(faq: $faq, finalScore: 0.92);
        $hits = new EloquentCollection([$hit]);

        $rawReply = 'Online e exchange request korle delivery er 5 diner moddhe size ba color swap kora jabe. Tobe Banani showroom e direct giye exchange kora jabe kina ta amader policy te ullekh nei; ei bishoye nishchit hote amader support team er shathe jogajog korte paren (hotline: +880-9612-345678).';

        $sanitized = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($rawReply, $hits);

        $this->assertStringNotContainsString('+880-9612-345678', $sanitized);
        $this->assertStringNotContainsString('hotline', strtolower($sanitized));
        $this->assertStringContainsString('5 diner moddhe', $sanitized);
        $this->assertStringContainsString('Banani showroom e direct giye exchange kora jabe kina ta amader policy te ullekh nei', $sanitized);
        $this->assertStringContainsString('amader support team er shathe jogajog korte paren.', $sanitized);
    }

    public function test_sanitizer_preserves_legitimate_evidence_backed_contact_information(): void
    {
        $faq = new FAQ();
        $faq->question = 'How can I contact customer support?';
        $faq->answer = 'You can reach our official support team via hotline: +880-9612-345678 or email support@entrepreneurs.com from 10 AM to 8 PM.';
        $faq->document_type = 'support_contact';

        $hit = new FAQSearchResult(faq: $faq, finalScore: 0.95);
        $hits = new EloquentCollection([$hit]);

        $reply = 'Amader support team er shathe jogajog korte paren (hotline: +880-9612-345678, email: support@entrepreneurs.com).';

        $sanitized = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply, $hits);

        $this->assertStringContainsString('+880-9612-345678', $sanitized);
        $this->assertStringContainsString('support@entrepreneurs.com', $sanitized);
    }

    public function test_sanitizer_removes_unsupported_bengali_phone_numbers_while_preserving_prices_and_dates(): void
    {
        $faq = new FAQ();
        $faq->question = 'পাঞ্জাবির দাম ও ডেলিভারি তথ্য কি?';
        $faq->answer = 'আমাদের রয়্যাল সিল্ক পাঞ্জাবির মূল্য ৩,৫০০ টাকা এবং ডেলিভারি চার্জ ৭০ টাকা। ঢাকা সিটিতে ৫ কার্যদিবসের মধ্যে ডেলিভারি সম্পন্ন হয়।';
        $faq->document_type = 'product_info';

        $hit = new FAQSearchResult(faq: $faq, finalScore: 0.90);
        $hits = new EloquentCollection([$hit]);

        $reply = 'আমাদের রয়্যাল সিল্ক পাঞ্জাবির মূল্য ৩,৫০০ টাকা এবং ডেলিভারি চার্জ ৭০ টাকা। ৫ কার্যদিবসের মধ্যে পাবেন। বিস্তারিত জানতে আমাদের সাপোর্ট সেন্টারে যোগাযোগ করতে পারেন (হটলাইন: ০১৭১২৩৪৫৬৭৮)।';

        $sanitized = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply, $hits);

        $this->assertStringNotContainsString('০১৭১২৩৪৫৬৭৮', $sanitized);
        $this->assertStringNotContainsString('01712345678', $sanitized);
        $this->assertStringNotContainsString('হটলাইন', $sanitized);
        $this->assertStringContainsString('৩,৫০০ টাকা', $sanitized);
        $this->assertStringContainsString('৭০ টাকা', $sanitized);
        $this->assertStringContainsString('৫ কার্যদিবসের মধ্যে পাবেন', $sanitized);
        $this->assertStringContainsString('বিস্তারিত জানতে আমাদের সাপোর্ট সেন্টারে যোগাযোগ করতে পারেন।', $sanitized);
    }

    public function test_sanitizer_handles_json_structured_agent_payload(): void
    {
        $faq = new FAQ();
        $faq->question = 'এক্সচেঞ্জ পলিসি কি?';
        $faq->answer = 'অনলাইন এক্সচেঞ্জ ৫ দিনের মধ্যে করা যাবে।';
        $faq->document_type = 'exchange_policy';

        $hit = new FAQSearchResult(faq: $faq, finalScore: 0.90);
        $hits = new EloquentCollection([$hit]);

        $jsonPayload = json_encode([
            'answer' => 'Tobe Banani showroom e direct exchange kora jabe kina ta policy te nei (hotline: +880-9612-345678).',
            'proposed_follow_up' => null,
            'follow_up_type' => null,
        ], JSON_UNESCAPED_UNICODE);

        $sanitizedJson = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($jsonPayload, $hits);

        $this->assertJson($sanitizedJson);
        $decoded = json_decode($sanitizedJson, true);
        $this->assertStringNotContainsString('+880-9612-345678', $decoded['answer']);
        $this->assertSame('Tobe Banani showroom e direct exchange kora jabe kina ta policy te nei.', $decoded['answer']);
    }

    public function test_sanitizer_preserves_contact_across_local_and_international_formats(): void
    {
        // 1. Evidence has local format 01712345678, reply has international +8801712345678
        $faq1 = new FAQ();
        $faq1->question = 'Official support contact?';
        $faq1->answer = 'Our support line is 01712345678.';
        $faq1->document_type = 'support_contact';
        $hits1 = new EloquentCollection([new FAQSearchResult(faq: $faq1, finalScore: 0.95)]);

        $reply1 = 'You can reach our team at +8801712345678 for assistance.';
        $sanitized1 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply1, $hits1);
        $this->assertStringContainsString('+8801712345678', $sanitized1);
        $this->assertSame($reply1, $sanitized1);

        // 2. Evidence has international format +8801712345678, reply has local format 01712345678
        $faq2 = new FAQ();
        $faq2->question = 'Official support contact?';
        $faq2->answer = 'Our support line is +8801712345678.';
        $faq2->document_type = 'support_contact';
        $hits2 = new EloquentCollection([new FAQSearchResult(faq: $faq2, finalScore: 0.95)]);

        $reply2 = 'You can reach our team at 01712345678 for assistance.';
        $sanitized2 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply2, $hits2);
        $this->assertStringContainsString('01712345678', $sanitized2);
        $this->assertSame($reply2, $sanitized2);

        // 3. Evidence has Bengali digits, reply has formatted ASCII number
        $faq3 = new FAQ();
        $faq3->question = 'যোগাযোগ নম্বর কি?';
        $faq3->answer = 'আমাদের সাথে ০১৭১২-৩৪৫৬৭৮ নম্বরে যোগাযোগ করতে পারেন।';
        $faq3->document_type = 'support_contact';
        $hits3 = new EloquentCollection([new FAQSearchResult(faq: $faq3, finalScore: 0.95)]);

        $reply3 = 'Amader support team er shathe +880-1712-345678 e jogajog korte paren.';
        $sanitized3 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply3, $hits3);
        $this->assertStringContainsString('+880-1712-345678', $sanitized3);
        $this->assertSame($reply3, $sanitized3);

        // 4. Evidence has hotline +880-9612-345678, reply has 09612345678
        $faq4 = new FAQ();
        $faq4->question = 'Hotline number?';
        $faq4->answer = 'Call us at +880-9612-345678.';
        $faq4->document_type = 'support_contact';
        $hits4 = new EloquentCollection([new FAQSearchResult(faq: $faq4, finalScore: 0.95)]);

        $reply4 = 'Our official hotline is 09612345678.';
        $sanitized4 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply4, $hits4);
        $this->assertStringContainsString('09612345678', $sanitized4);
        $this->assertSame($reply4, $sanitized4);
    }

    public function test_sanitizer_prevents_false_acceptance_from_concatenated_evidence_numbers(): void
    {
        // Evidence contains 880, 9612, 345678 as separate unrelated numbers (price, model, batch)
        $faq = new FAQ();
        $faq->question = 'What is the price and model?';
        $faq->answer = 'The regular price is 880 BDT for model 9612, batch serial 345678. No official phone contact is published.';
        $faq->document_type = 'product_details';
        $hits = new EloquentCollection([new FAQSearchResult(faq: $faq, finalScore: 0.90)]);

        // Reply invents hotline +880-9612-345678 which matches concatenated digits but is unsupported
        $reply = 'Amader support team er shathe jogajog korte paren (hotline: +880-9612-345678).';

        $sanitized = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply, $hits);

        $this->assertStringNotContainsString('+880-9612-345678', $sanitized);
        $this->assertStringNotContainsString('hotline', strtolower($sanitized));
        $this->assertSame('Amader support team er shathe jogajog korte paren.', $sanitized);
    }

    public function test_sanitizer_preserves_adjacent_parenthetical_content_while_removing_unsupported_contact(): void
    {
        $faq = new FAQ();
        $faq->question = 'Showroom visit rules?';
        $faq->answer = 'Online exchange is accepted within 7 days. Showroom exchange policy is currently unconfirmed.';
        $faq->document_type = 'exchange_policy';
        $hits = new EloquentCollection([new FAQSearchResult(faq: $faq, finalScore: 0.90)]);

        // Case 1: Trailing unsupported contact inside parenthetical (operating hours before contact)
        $reply1 = 'সরাসরি এক্সচেঞ্জ পলিসিতে নেই (সকাল ১০টা - রাত ৮টা, হটলাইন: ০১৭১২৩৪৫৬৭৮)।';
        $sanitized1 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply1, $hits);
        $this->assertStringNotContainsString('০১৭১২৩৪৫৬৭৮', $sanitized1);
        $this->assertStringNotContainsString('হটলাইন', $sanitized1);
        $this->assertSame('সরাসরি এক্সচেঞ্জ পলিসিতে নেই (সকাল ১০টা - রাত ৮টা)।', $sanitized1);

        // Case 2: Leading unsupported contact inside parenthetical (operating hours after contact)
        $reply2 = 'সরাসরি এক্সচেঞ্জ পলিসিতে নেই (হটলাইন: ০১৭১২৩৪৫৬৭৮, সকাল ১০টা - রাত ৮টা)।';
        $sanitized2 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply2, $hits);
        $this->assertStringNotContainsString('০১৭১২৩৪৫৬৭৮', $sanitized2);
        $this->assertStringNotContainsString('হটলাইন', $sanitized2);
        $this->assertSame('সরাসরি এক্সচেঞ্জ পলিসিতে নেই (সকাল ১০টা - রাত ৮টা)।', $sanitized2);

        // Case 3: Supported email and unsupported phone inside same parenthetical
        $faqWithEmail = new FAQ();
        $faqWithEmail->question = 'Contact?';
        $faqWithEmail->answer = 'Please email us at support@entrepreneurs.com.';
        $faqWithEmail->document_type = 'support_contact';
        $hitsWithEmail = new EloquentCollection([new FAQSearchResult(faq: $faqWithEmail, finalScore: 0.95)]);

        $reply3 = 'Amader shathe jogajog korte paren (hotline: +880-9612-345678, email: support@entrepreneurs.com).';
        $sanitized3 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply3, $hitsWithEmail);
        $this->assertStringNotContainsString('+880-9612-345678', $sanitized3);
        $this->assertStringContainsString('support@entrepreneurs.com', $sanitized3);
        $this->assertSame('Amader shathe jogajog korte paren (email: support@entrepreneurs.com).', $sanitized3);

        // Case 4: Sole content inside parenthetical removes parenthetical completely
        $reply4 = 'অনলাইন রিটার্ন সম্ভব (হটলাইন: ০১৭১২৩৪৫৬৭৮)।';
        $sanitized4 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply4, $hits);
        $this->assertStringNotContainsString('০১৭১২৩৪৫৬৭৮', $sanitized4);
        $this->assertStringNotContainsString('(', $sanitized4);
        $this->assertStringNotContainsString(')', $sanitized4);
        $this->assertSame('অনলাইন রিটার্ন সম্ভব।', $sanitized4);
    }

    public function test_sanitizer_preserves_bengali_ascii_dates_prices_and_order_ids_in_json_and_plain_text(): void
    {
        $faq = new FAQ();
        $faq->question = 'Order status and prices?';
        $faq->answer = 'অর্ডার #ORD-98765 এর মূল্য ৩,৫০০ টাকা। ৩০ দিনের মধ্যে ডেলিভারি হবে। তারিখ ২০২৬-১০-২৮।';
        $faq->document_type = 'order_info';
        $hits = new EloquentCollection([new FAQSearchResult(faq: $faq, finalScore: 0.90)]);

        // 1. Plain text with mixed entities
        $reply = 'আপনার অর্ডার #ORD-98765 এর মূল্য ৩,৫০০ টাকা। ৩০ দিনের মধ্যে ডেলিভারি পাবেন। তারিখ ২০২৬-১০-২৮ (হটলাইন: ০১৭১২৩৪৫৬৭৮)।';
        $sanitized = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply, $hits);

        $this->assertStringNotContainsString('০১৭১২৩৪৫৬৭৮', $sanitized);
        $this->assertStringNotContainsString('হটলাইন', $sanitized);
        $this->assertStringContainsString('#ORD-98765', $sanitized);
        $this->assertStringContainsString('৩,৫০০ টাকা', $sanitized);
        $this->assertStringContainsString('৩০ দিনের মধ্যে', $sanitized);
        $this->assertStringContainsString('২০২৬-১০-২৮', $sanitized);
        $this->assertSame('আপনার অর্ডার #ORD-98765 এর মূল্য ৩,৫০০ টাকা। ৩০ দিনের মধ্যে ডেলিভারি পাবেন। তারিখ ২০২৬-১০-২৮।', $sanitized);

        // 2. Structured JSON payload
        $jsonPayload = json_encode([
            'answer' => 'অর্ডার #ORD-98765 এর মূল্য ৩,৫০০ টাকা (হটলাইন: ০১৭১২৩৪৫৬৭৮)।',
            'proposed_follow_up' => 'আপনি কি ২০২৬-১০-২৮ তারিখের চালান দেখতে চান?',
            'follow_up_type' => 'invoice',
        ], JSON_UNESCAPED_UNICODE);

        $sanitizedJson = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($jsonPayload, $hits);
        $this->assertJson($sanitizedJson);
        $decoded = json_decode($sanitizedJson, true);
        $this->assertStringNotContainsString('০১৭১২৩৪৫৬৭৮', $decoded['answer']);
        $this->assertSame('অর্ডার #ORD-98765 এর মূল্য ৩,৫০০ টাকা।', $decoded['answer']);
        $this->assertSame('আপনি কি ২০২৬-১০-২৮ তারিখের চালান দেখতে চান?', $decoded['proposed_follow_up']);
        $this->assertSame('invoice', $decoded['follow_up_type']);
    }

    public function test_sanitizer_preserves_identifiers_and_tracking_codes_without_fragment_slicing(): void
    {
        // 1. Alphanumeric order ID with hyphen prefix
        $reply1 = 'Your order code is ORD-01712345678.';
        $sanitized1 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply1);
        $this->assertSame('Your order code is ORD-01712345678.', $sanitized1);

        // 2. Compound tracking identifier with hyphen and trailing digits
        $reply2 = 'Your tracking code is 01712345678-1234.';
        $sanitized2 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply2);
        $this->assertSame('Your tracking code is 01712345678-1234.', $sanitized2);

        // 3. Identifier with underscore
        $reply3 = 'Batch reference is BATCH_01712345678.';
        $sanitized3 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply3);
        $this->assertSame('Batch reference is BATCH_01712345678.', $sanitized3);

        // 4. Identifier and unsupported contact co-occurring in the same response
        $reply4 = 'আপনার অর্ডার ORD-01712345678 কনফার্ম। যোগাযোগ করতে পারেন (কল করুন: ০১৭১২৩৪৫৬৭৮)।';
        $sanitized4 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply4);
        $this->assertSame('আপনার অর্ডার ORD-01712345678 কনফার্ম। যোগাযোগ করতে পারেন।', $sanitized4);
    }

    public function test_sanitizer_removes_bengali_contact_labels_cleanly_without_orphaning_verbal_prefixes(): void
    {
        // 1. 'কল করুন:' in parentheses
        $reply1 = 'আমাদের সাথে যোগাযোগ করতে পারেন (কল করুন: ০১৭১২৩৪৫৬৭৮)।';
        $sanitized1 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply1);
        $this->assertSame('আমাদের সাথে যোগাযোগ করতে পারেন।', $sanitized1);

        // 2. 'কল করুন ... নম্বরে' in parentheses
        $reply2 = 'আমাদের সাথে যোগাযোগ করতে পারেন (কল করুন ০১৭১২৩৪৫৬৭৮ নম্বরে)।';
        $sanitized2 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply2);
        $this->assertSame('আমাদের সাথে যোগাযোগ করতে পারেন।', $sanitized2);

        // 3. 'যোগাযোগ করুন:' in parentheses
        $reply3 = 'আমাদের সাথে যোগাযোগ করতে পারেন (যোগাযোগ করুন: ০১৭১২৩৪৫৬৭৮)।';
        $sanitized3 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply3);
        $this->assertSame('আমাদের সাথে যোগাযোগ করতে পারেন।', $sanitized3);

        // 4. 'কল:' in parentheses
        $reply4 = 'আমাদের সাথে যোগাযোগ করতে পারেন (কল: ০১৭১২৩৪৫৬৭৮)।';
        $sanitized4 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply4);
        $this->assertSame('আমাদের সাথে যোগাযোগ করতে পারেন।', $sanitized4);

        // 5. Standalone sentence with 'কল করুন:'
        $reply5 = 'বিস্তারিত জানতে কল করুন: ০১৭১২৩৪৫৬৭৮।';
        $sanitized5 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply5);
        $this->assertSame('বিস্তারিত জানতে।', $sanitized5);
    }

    public function test_sanitizer_handles_nested_parentheticals_and_brackets_without_punctuation_corruption(): void
    {
        // 1. Nested parenthetical with condition and hours
        $reply1 = 'আমাদের সাথে যোগাযোগ করতে পারেন (সকাল ১০টা (শুক্রবার বাদে), হটলাইন: ০১৭১২৩৪৫৬৭৮)।';
        $sanitized1 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply1);
        $this->assertSame('আমাদের সাথে যোগাযোগ করতে পারেন (সকাল ১০টা (শুক্রবার বাদে))।', $sanitized1);

        // 2. Square brackets with operating hours
        $reply2 = 'আমাদের সাথে যোগাযোগ করতে পারেন [সকাল ১০টা - রাত ৮টা, হটলাইন: ০১৭১২৩৪৫৬৭৮]।';
        $sanitized2 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply2);
        $this->assertSame('আমাদের সাথে যোগাযোগ করতে পারেন [সকাল ১০টা - রাত ৮টা]।', $sanitized2);

        // 3. Nested parentheses preserving supported email and removing unsupported hotline
        $faq = new FAQ();
        $faq->question = 'Contact';
        $faq->answer = 'Our email is support@entrepreneurs.com.';
        $faq->document_type = 'support_contact';
        $hits = new EloquentCollection([new FAQSearchResult(faq: $faq, finalScore: 0.95)]);

        $reply3 = 'যোগাযোগ করতে পারেন (সকাল ১০টা (শুক্রবার বাদে), email: support@entrepreneurs.com, hotline: +880-9612-345678)।';
        $sanitized3 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($reply3, $hits);
        $this->assertSame('যোগাযোগ করতে পারেন (সকাল ১০টা (শুক্রবার বাদে), email: support@entrepreneurs.com)।', $sanitized3);
    }

    public function test_sanitizer_normalizes_empty_or_punctuation_only_json_follow_up_to_null(): void
    {
        // 1. proposed_follow_up contains only unsupported hotline -> normalized to null alongside follow_up_type
        $jsonPayload1 = json_encode([
            'answer' => 'Exchange request accepts online (hotline: +880-9612-345678).',
            'proposed_follow_up' => 'Call +880-9612-345678?',
            'follow_up_type' => 'support',
        ], JSON_UNESCAPED_UNICODE);

        $sanitizedJson1 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($jsonPayload1);
        $this->assertJson($sanitizedJson1);
        $decoded1 = json_decode($sanitizedJson1, true);
        $this->assertSame('Exchange request accepts online.', $decoded1['answer']);
        $this->assertNull($decoded1['proposed_follow_up']);
        $this->assertNull($decoded1['follow_up_type']);

        // 2. proposed_follow_up contains meaningful business text -> preserved intact
        $jsonPayload2 = json_encode([
            'answer' => 'Exchange request accepts online.',
            'proposed_follow_up' => 'আপনি কি অন্য কোনো সাইজ দেখতে চান?',
            'follow_up_type' => 'product_variant',
        ], JSON_UNESCAPED_UNICODE);

        $sanitizedJson2 = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($jsonPayload2);
        $this->assertJson($sanitizedJson2);
        $decoded2 = json_decode($sanitizedJson2, true);
        $this->assertSame('Exchange request accepts online.', $decoded2['answer']);
        $this->assertSame('আপনি কি অন্য কোনো সাইজ দেখতে চান?', $decoded2['proposed_follow_up']);
        $this->assertSame('product_variant', $decoded2['follow_up_type']);
    }

    public function test_sanitizer_and_customer_support_service_pipeline_handles_follow_up_nullification(): void
    {
        $service = app(CustomerSupportService::class);
        $policyManager = app(\App\Services\AI\FollowUpPolicyManager::class);

        // Case A: Hallucinated hotline in proposed_follow_up -> nullified and suppressed as no_proposal downstream
        $jsonPayload = json_encode([
            'answer' => 'আমাদের ডেলিভারি চার্জ ঢাকার ভেতরে ৭০ টাকা।',
            'proposed_follow_up' => 'Call +880-9612-345678?',
            'follow_up_type' => 'support',
        ], JSON_UNESCAPED_UNICODE);

        $sanitizedJson = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($jsonPayload);
        $parsed = $service->parseAgentOutput($sanitizedJson);

        $this->assertSame('আমাদের ডেলিভারি চার্জ ঢাকার ভেতরে ৭০ টাকা।', $parsed['answer']);
        $this->assertNull($parsed['proposed_follow_up']);
        $this->assertNull($parsed['follow_up_type']);

        $decision = $policyManager->evaluate(
            rawAnswer: $parsed['answer'],
            proposedFollowUp: $parsed['proposed_follow_up'],
            followUpType: $parsed['follow_up_type'],
            route: 'knowledge',
            userQuery: 'delivery charge koto?',
        );

        $this->assertFalse($decision->allowed);
        $this->assertSame('no_proposal', $decision->suppressionReason);
        $this->assertSame('আমাদের ডেলিভারি চার্জ ঢাকার ভেতরে ৭০ টাকা।', $decision->finalReply());

        // Case B: Legitimate product variant follow-up -> allowed and formatted downstream
        $validPayload = json_encode([
            'answer' => 'আমাদের ডেলিভারি চার্জ ঢাকার ভেতরে ৭০ টাকা।',
            'proposed_follow_up' => 'আপনি কি অন্য কোনো সাইজ দেখতে চান?',
            'follow_up_type' => 'product_variant',
        ], JSON_UNESCAPED_UNICODE);

        $sanitizedValid = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($validPayload);
        $parsedValid = $service->parseAgentOutput($sanitizedValid);

        $this->assertSame('আমাদের ডেলিভারি চার্জ ঢাকার ভেতরে ৭০ টাকা।', $parsedValid['answer']);
        $this->assertSame('আপনি কি অন্য কোনো সাইজ দেখতে চান?', $parsedValid['proposed_follow_up']);
        $this->assertSame('product_variant', $parsedValid['follow_up_type']);

        $decisionValid = $policyManager->evaluate(
            rawAnswer: $parsedValid['answer'],
            proposedFollowUp: $parsedValid['proposed_follow_up'],
            followUpType: $parsedValid['follow_up_type'],
            route: 'knowledge',
            userQuery: 'delivery charge koto?',
        );

        $this->assertTrue($decisionValid->allowed);
        $this->assertNull($decisionValid->suppressionReason);
        $this->assertStringContainsString('আমাদের ডেলিভারি চার্জ ঢাকার ভেতরে ৭০ টাকা।', $decisionValid->finalReply());
        $this->assertStringContainsString('আপনি কি অন্য কোনো সাইজ দেখতে চান?', $decisionValid->finalReply());
    }
}
