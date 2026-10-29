<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\AI\DTOs\ContextualResolutionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deterministic regression tests to eliminate cross-domain query rewriting failures:
 * 1. Hotel guest asking "Can I extend it?" after discussing a booking/stay
 * 2. University student asking "ফি কত?" after discussing admission
 * 3. Clinic patient asking "দেরি হলে কী করব?" after discussing an appointment
 * 4. Existing retail questions about delivery, exchange, size, price, and product variants
 * 5. Subjectless follow-up with no reliable antecedent
 * 6. Genuinely ambiguous reference requesting clarification rather than inventing a topic
 */
class ContextualQueryDomainNeutralRegressionTest extends TestCase
{
    use RefreshDatabase;

    private ContextualQueryBuilder $builder;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new ContextualQueryBuilder();

        $workspace = Workspace::create(['name' => 'Regression WS', 'slug' => 'regression-ws']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Account',
            'external_id'  => 'acc_regression',
            'access_token' => 'tok_regression',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_reg_test',
            'status'             => 'active',
            'customer_name'      => 'Test User',
            'last_direction'     => 'inbound',
        ]);
    }

    /**
     * Requirement 1: Hotel guest asking "Can I extend it?" after discussing a booking or stay.
     * Must construct a grounded query with the hotel/booking antecedent.
     * MUST NOT rewrite into SaaS free trial, courier delivery, or exchange.
     */
    public function test_hotel_guest_can_i_extend_it_grounds_in_booking_antecedent_without_saas_hijacking(): void
    {
        $this->createTurn(
            'I have a booking for two nights at the Grand Hotel.',
            'outbound',
            'Your reservation for two nights at Grand Hotel is confirmed.'
        );

        $result = $this->builder->resolveContext('Can I extend it?', $this->conversation);

        $this->assertInstanceOf(ContextualResolutionResult::class, $result);
        $this->assertSame('resolved', $result->status);
        $this->assertSame('local_turns', $result->source);
        $this->assertGreaterThanOrEqual(0.70, $result->confidence);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('Grand Hotel', $result->resolvedEntity['name']);

        $this->assertNotNull($result->resolvedQuery);
        $this->assertSame('Can I extend Grand Hotel?', $result->resolvedQuery);

        // Explicit cross-domain anti-regression assertions
        $lowerResolved = mb_strtolower($result->resolvedQuery);
        $this->assertStringNotContainsString('free trial', $lowerResolved);
        $this->assertStringNotContainsString('pro plan', $lowerResolved);
        $this->assertStringNotContainsString('14-day', $lowerResolved);
        $this->assertStringNotContainsString('ডেলিভারি', $result->resolvedQuery);
        $this->assertStringNotContainsString('courier', $lowerResolved);
        $this->assertStringNotContainsString('parcel', $lowerResolved);
        $this->assertStringNotContainsString('রিটার্ন', $result->resolvedQuery);
    }

    /**
     * Requirement 1 Variant: Hotel guest discussing a stay.
     */
    public function test_hotel_guest_stay_discussion_resolves_without_cross_domain_hijacking(): void
    {
        $this->createTurn(
            'I would like to inquire about my stay at Sunset Resort.',
            'outbound',
            'Welcome! Your stay at Sunset Resort is scheduled for next week.'
        );

        $result = $this->builder->resolveContext('Can I extend it?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertSame('Sunset Resort', $result->resolvedEntity['name']);
        $this->assertSame('Can I extend Sunset Resort?', $result->resolvedQuery);
        $this->assertStringNotContainsString('free trial', mb_strtolower($result->resolvedQuery));
    }

    /**
     * Requirement 2: University student asking "ফি কত?" after discussing admission.
     * Must construct a grounded contextual query using the admission antecedent.
     * MUST NOT rewrite into courier shipping / delivery charge ("ডেলিভারি চার্জ").
     */
    public function test_university_student_admission_fee_query_grounds_in_admission_antecedent(): void
    {
        $this->createTurn(
            'আমি CSE Admission সম্পর্কে জানতে চাচ্ছি।',
            'outbound',
            'আমাদের CSE Admission এর আবেদন আগামী ১৫ তারিখ পর্যন্ত চলবে।'
        );

        $result = $this->builder->resolveContext('ফি কত?', $this->conversation);

        $this->assertInstanceOf(ContextualResolutionResult::class, $result);
        $this->assertSame('resolved', $result->status);
        $this->assertSame('antecedent_context', $result->source);
        $this->assertGreaterThanOrEqual(0.85, $result->confidence);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('CSE Admission', $result->resolvedEntity['name']);

        $this->assertNotNull($result->resolvedQuery);
        $this->assertSame('CSE Admission ফি ও চার্জ কত', $result->resolvedQuery);

        // Explicit cross-domain anti-regression assertions
        $this->assertStringNotContainsString('ডেলিভারি', $result->resolvedQuery);
        $this->assertStringNotContainsString('শিপিং', $result->resolvedQuery);
        $this->assertStringNotContainsString('কুরিয়ার', $result->resolvedQuery);
        $this->assertStringNotContainsString('পার্সেল', $result->resolvedQuery);
        $this->assertStringNotContainsString('free trial', mb_strtolower($result->resolvedQuery));
    }

    /**
     * Requirement 2 Variant: University admission discussed using Bengali noun phrase.
     */
    public function test_university_bengali_admission_noun_resolves_fee_correctly(): void
    {
        $this->createTurn(
            'ভর্তি সংক্রান্ত তথ্য জানতে চাই।',
            'outbound',
            'আমাদের নতুন সেশনের ভর্তি বিজ্ঞপ্তি প্রকাশ করা হয়েছে।'
        );

        $result = $this->builder->resolveContext('ফি কত?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertSame('ভর্তি', $result->resolvedEntity['name']);
        $this->assertSame('ভর্তি ফি ও চার্জ কত', $result->resolvedQuery);
        $this->assertStringNotContainsString('ডেলিভারি', $result->resolvedQuery);
    }

    /**
     * Requirement 3: Clinic patient asking "দেরি হলে কী করব?" after discussing an appointment.
     * Must construct a grounded query using the doctor / appointment antecedent.
     * MUST NOT rewrite into courier parcel delay ("পার্সেল বা ডেলিভারি পেতে দেরি হলে করণীয় কী").
     */
    public function test_clinic_patient_appointment_delay_query_grounds_in_appointment_antecedent(): void
    {
        $this->createTurn(
            'আমি Dr. Rahman এর সাথে appointment নিতে চাই।',
            'outbound',
            'Dr. Rahman এর চেম্বার আগামী সোমবার খোলা থাকবে।'
        );

        $result = $this->builder->resolveContext('দেরি হলে কী করব?', $this->conversation);

        $this->assertInstanceOf(ContextualResolutionResult::class, $result);
        $this->assertSame('resolved', $result->status);
        $this->assertSame('antecedent_context', $result->source);
        $this->assertGreaterThanOrEqual(0.85, $result->confidence);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('Dr. Rahman', $result->resolvedEntity['name']);

        $this->assertNotNull($result->resolvedQuery);
        $this->assertSame('Dr. Rahman দেরি হলে করণীয় কী', $result->resolvedQuery);

        // Explicit cross-domain anti-regression assertions
        $this->assertStringNotContainsString('পার্সেল', $result->resolvedQuery);
        $this->assertStringNotContainsString('ডেলিভারি', $result->resolvedQuery);
        $this->assertStringNotContainsString('কুরিয়ার', $result->resolvedQuery);
        $this->assertStringNotContainsString('free trial', mb_strtolower($result->resolvedQuery));
    }

    /**
     * Requirement 4: Existing retail questions about delivery, exchange, size, price, and product variants.
     * Confirms all retail-specific behavior remains strictly intact.
     */
    public function test_retail_delivery_duration_and_charge_queries_preserved(): void
    {
        $this->createTurn(
            'ডেলিভারি চার্জ কত?',
            'outbound',
            'ঢাকার ভেতরে ৭০ টাকা, বাইরে ১৩০ টাকা।'
        );

        // Delivery Duration
        $durationRes = $this->builder->resolveContext('কতদিন লাগবে?', $this->conversation);
        $this->assertSame('Delivery', $durationRes->activeTopic);
        $this->assertSame('resolved', $durationRes->status);
        $this->assertSame('ডেলিভারি হতে কতদিন সময় লাগবে ডেলিভারি সময়সীমা', $durationRes->resolvedQuery);

        // Delivery Fee
        $feeRes = $this->builder->resolveContext('চার্জ কত?', $this->conversation);
        $this->assertSame('Delivery', $feeRes->activeTopic);
        $this->assertSame('resolved', $feeRes->status);
        $this->assertSame('ডেলিভারি চার্জ কত এবং শিপিং ফি কত', $feeRes->resolvedQuery);
    }

    public function test_retail_return_exchange_charge_query_preserved(): void
    {
        $this->createTurn(
            'সাইজ না মিললে রিটার্ন বা এক্সচেঞ্জ করার নিয়ম কী?',
            'outbound',
            'পণ্য পাওয়ার ৭ দিনের মধ্যে এক্সচেঞ্জ ক্লেইম করা যাবে।'
        );

        $result = $this->builder->resolveContext('এটার চার্জ কত?', $this->conversation);

        $this->assertSame('Return_Exchange', $result->activeTopic);
        $this->assertSame('resolved', $result->status);
        $this->assertSame('পণ্য রিটার্ন বা এক্সচেঞ্জ করতে ডেলিভারি চার্জ কত', $result->resolvedQuery);
    }

    public function test_retail_product_price_size_and_variants_queries_preserved(): void
    {
        $this->createTurn(
            'Black Cotton Panjabi এর ফেব্রিক কেমন?',
            'outbound',
            'এটি প্রিমিয়াম কটন কাপড়ে তৈরি।'
        );

        // Price
        $priceRes = $this->builder->resolveContext('এটার দাম কত?', $this->conversation);
        $this->assertSame('resolved', $priceRes->status);
        $this->assertSame('Black Cotton Panjabi এর দাম কত?', $priceRes->resolvedQuery);

        // Size
        $sizeRes = $this->builder->resolveContext('ওটার সাইজ কি কি আছে?', $this->conversation);
        $this->assertSame('resolved', $sizeRes->status);
        $this->assertSame('Black Cotton Panjabi এর কি কি সাইজ পাওয়া যাবে?', $sizeRes->resolvedQuery);

        // Color variants
        $variantRes = $this->builder->resolveContext('কালার ভ্যারিয়েন্ট কি কি আছে?', $this->conversation);
        $this->assertSame('resolved', $variantRes->status);
        $this->assertSame('Black Cotton Panjabi এর কালার ভ্যারিয়েন্ট কি কি আছে?', $variantRes->resolvedQuery);
    }

    /**
     * Requirement 5: Subjectless follow-up with no reliable antecedent.
     * Must preserve ambiguity / mark unresolved (resolvedQuery = null) rather than guessing.
     */
    public function test_subjectless_follow_up_without_antecedent_returns_unresolved_without_guessing(): void
    {
        $emptyConv = Conversation::create([
            'channel_account_id' => $this->conversation->channel_account_id,
            'external_user_id'   => 'user_no_antecedent',
            'status'             => 'active',
            'last_direction'     => 'inbound',
        ]);

        // Standalone "Can I extend it?" without antecedent
        $resExtend = $this->builder->resolveContext('Can I extend it?', $emptyConv);
        $this->assertSame('unresolved', $resExtend->status);
        $this->assertNull($resExtend->resolvedQuery);
        $this->assertTrue($resExtend->needsClarification());
        $this->assertNotSame('Is there a free trial and can I extend the 14-day free trial on Pro plan?', $resExtend->resolvedQuery);

        // Standalone "ফি কত?" without antecedent
        $resFee = $this->builder->resolveContext('ফি কত?', $emptyConv);
        $this->assertSame('unresolved', $resFee->status);
        $this->assertNull($resFee->resolvedQuery);
        $this->assertTrue($resFee->needsClarification());

        // Standalone "দেরি হলে কী করব?" without antecedent
        $resDelay = $this->builder->resolveContext('দেরি হলে কী করব?', $emptyConv);
        $this->assertSame('unresolved', $resDelay->status);
        $this->assertNull($resDelay->resolvedQuery);
        $this->assertTrue($resDelay->needsClarification());

        // Standalone "কতদিন লাগবে?" without antecedent
        $resDuration = $this->builder->resolveContext('কতদিন লাগবে?', $emptyConv);
        $this->assertSame('unresolved', $resDuration->status);
        $this->assertNull($resDuration->resolvedQuery);
        $this->assertTrue($resDuration->needsClarification());
    }

    /**
     * Requirement 5 Variant: User had conversational pleasantry only (no substantive antecedent).
     */
    public function test_subjectless_follow_up_after_pure_pleasantry_returns_unresolved(): void
    {
        $this->createTurn('Hello', 'outbound', 'Hi! How can I help you?');

        $result = $this->builder->resolveContext('Can I extend it?', $this->conversation);

        $this->assertSame('unresolved', $result->status);
        $this->assertNull($result->resolvedQuery);
        $this->assertTrue($result->needsClarification());
    }

    /**
     * Requirement 6: Genuinely ambiguous reference requesting clarification rather than inventing a topic.
     * Multiple competing candidates within margin -> ambiguous, resolvedQuery = null (NO GUESSING).
     */
    public function test_ambiguous_reference_between_competing_hotel_bookings_requests_clarification(): void
    {
        $this->createTurn(
            'I have a booking at Grand Hotel and another reservation at Ocean View Hotel.',
            'outbound',
            'We see both of your reservations in our system.'
        );

        $result = $this->builder->resolveContext('Can I extend it?', $this->conversation);

        $this->assertSame('ambiguous', $result->status);
        $this->assertNull($result->resolvedQuery);
        $this->assertTrue($result->isAmbiguous());
        $this->assertTrue($result->needsClarification());
        $this->assertGreaterThanOrEqual(2, count($result->candidates));
    }

    public function test_ambiguous_reference_between_competing_university_programs_requests_clarification(): void
    {
        $this->createTurn(
            'আমি CSE Admission আর BBA Admission দুটির ব্যাপারেই জানতে চাচ্ছি।',
            'outbound',
            'দুটি প্রোগ্রামের জন্যই ফর্ম উন্মুক্ত আছে।'
        );

        $result = $this->builder->resolveContext('ওটার ফি কত?', $this->conversation);

        $this->assertSame('ambiguous', $result->status);
        $this->assertNull($result->resolvedQuery);
        $this->assertTrue($result->isAmbiguous());
        $this->assertTrue($result->needsClarification());
        $this->assertGreaterThanOrEqual(2, count($result->candidates));
    }

    /**
     * Adversarial Test: Hotel guest asking "When is it available?"
     * MUST NOT rewrite into courier delivery ("Grand Hotel এর ডেলিভারি কবে পাবো?").
     */
    public function test_hotel_guest_when_is_it_available_grounds_without_courier_delivery_hijacking(): void
    {
        $this->createTurn(
            'I have a booking for two nights at the Grand Hotel.',
            'outbound',
            'Your reservation for two nights at Grand Hotel is confirmed.'
        );

        $result = $this->builder->resolveContext('When is it available?', $this->conversation);

        $this->assertInstanceOf(ContextualResolutionResult::class, $result);
        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedQuery);
        $this->assertSame('When is Grand Hotel available?', $result->resolvedQuery);
        $this->assertStringNotContainsString('ডেলিভারি', $result->resolvedQuery);
        $this->assertStringNotContainsString('কুরিয়ার', $result->resolvedQuery);
        $this->assertStringNotContainsString('parcel', mb_strtolower($result->resolvedQuery));
    }

    /**
     * Adversarial Test: University student asking "এটা কবে শুরু হবে?"
     * MUST NOT rewrite into courier delivery arrival ("CSE Admission এর ডেলিভারি কবে পাবো?").
     */
    public function test_university_student_when_does_it_start_grounds_without_courier_delivery_hijacking(): void
    {
        $this->createTurn(
            'আমি CSE Admission সম্পর্কে জানতে চাচ্ছি।',
            'outbound',
            'আমাদের CSE Admission এর আবেদন আগামী ১৫ তারিখ পর্যন্ত চলবে।'
        );

        $result = $this->builder->resolveContext('এটা কবে শুরু হবে?', $this->conversation);

        $this->assertInstanceOf(ContextualResolutionResult::class, $result);
        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedQuery);
        $this->assertSame('CSE Admission কবে শুরু হবে?', $result->resolvedQuery);
        $this->assertStringNotContainsString('ডেলিভারি', $result->resolvedQuery);
        $this->assertStringNotContainsString('পার্সেল', $result->resolvedQuery);
    }

    /**
     * Adversarial Test: Subjectless fee query "ফি কত?" when multiple competing programs discussed.
     * MUST NOT arbitrarily guess CSE Admission over BBA Admission. Must trigger ambiguity.
     */
    public function test_subjectless_fee_query_with_competing_antecedents_requests_clarification(): void
    {
        $this->createTurn(
            'আমি CSE Admission আর BBA Admission দুটির ব্যাপারেই জানতে চাচ্ছি।',
            'outbound',
            'দুটি প্রোগ্রামের জন্যই ফর্ম উন্মুক্ত আছে।'
        );

        // Subjectless query WITHOUT pronoun
        $result = $this->builder->resolveContext('ফি কত?', $this->conversation);

        $this->assertSame('ambiguous', $result->status);
        $this->assertNull($result->resolvedQuery);
        $this->assertTrue($result->isAmbiguous());
        $this->assertTrue($result->needsClarification());
        $this->assertGreaterThanOrEqual(2, count($result->candidates));
    }

    /**
     * Adversarial Test: Bengali demonstrative "এইটা" / "এইটার" variations.
     */
    public function test_bengali_demonstrative_eita_variations_resolve_consistently(): void
    {
        $this->createTurn(
            'ভর্তি সংক্রান্ত তথ্য জানতে চাই।',
            'outbound',
            'আমাদের নতুন সেশনের ভর্তি বিজ্ঞপ্তি প্রকাশ করা হয়েছে।'
        );

        $res1 = $this->builder->resolveContext('এইটার ফি কত?', $this->conversation);
        $this->assertSame('resolved', $res1->status);
        $this->assertNotNull($res1->resolvedQuery);
        $this->assertSame('ভর্তি এর ফি কত?', $res1->resolvedQuery);

        $res2 = $this->builder->resolveContext('এইটা কবে শুরু হবে?', $this->conversation);
        $this->assertSame('resolved', $res2->status);
        $this->assertNotNull($res2->resolvedQuery);
        $this->assertSame('ভর্তি কবে শুরু হবে?', $res2->resolvedQuery);
    }

    /**
     * Adversarial Test: Passing array history formatted with canonical 'text' key.
     */
    public function test_history_array_with_canonical_text_key_resolves_correctly(): void
    {
        $history = [
            ['text' => 'I would like to inquire about my stay at Sunset Resort.', 'direction' => 'inbound'],
            ['text' => 'Welcome! Your stay at Sunset Resort is scheduled for next week.', 'direction' => 'outbound'],
        ];

        $result = $this->builder->resolveContext('Can I extend it?', null, $history);

        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedQuery);
        $this->assertSame('Can I extend Sunset Resort?', $result->resolvedQuery);
    }

    /**
     * Adversarial Test: "Where is it?" with no antecedent MUST NOT be hijacked into order delivery tracking.
     */
    public function test_subjectless_where_is_it_without_antecedent_returns_unresolved_without_order_hijacking(): void
    {
        $result = $this->builder->resolveContext('Where is it?');

        $this->assertSame('unresolved', $result->status);
        $this->assertNull($result->resolvedQuery);
        $this->assertTrue($result->needsClarification());
        $this->assertNull($result->activeTopic);

        $resultLocated = $this->builder->resolveContext('Where is it located?');
        $this->assertSame('unresolved', $resultLocated->status);
        $this->assertNull($resultLocated->resolvedQuery);
        $this->assertTrue($resultLocated->needsClarification());
    }

    /**
     * Adversarial Test: Standalone number without order context MUST NOT be rewritten into order parcel tracking.
     */
    public function test_bare_number_without_order_context_is_not_hijacked_into_order_tracking(): void
    {
        $result = $this->builder->resolveContext('500');

        $this->assertNotSame('Order_Tracking', $result->activeTopic);
        if ($result->resolvedQuery !== null) {
            $this->assertStringNotContainsString('ডেলিভারি', $result->resolvedQuery);
            $this->assertStringNotContainsString('পার্সেল', $result->resolvedQuery);
            $this->assertStringNotContainsString('অর্ডার', $result->resolvedQuery);
        }
    }

    /**
     * Adversarial Test: Sentence starting with modal "Can I" MUST NOT extract "Can I" as proper named entity.
     */
    public function test_hotel_booking_with_conversational_modal_starter_resolves_cleanly(): void
    {
        $this->createTurn(
            'Can I book a room for tonight?',
            'outbound',
            'Yes, we have deluxe rooms available for tonight.'
        );

        $result = $this->builder->resolveContext('Can I extend it?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('room', $result->resolvedEntity['name']);
        $this->assertSame('Can I extend room?', $result->resolvedQuery);
        $this->assertStringNotContainsString('Can I extend Can I', (string) $result->resolvedQuery);
    }

    /**
     * Adversarial Test: English dialogue with preposition phrase cleans candidate properly.
     */
    public function test_hotel_booking_with_stay_preposition_phrase_cleans_candidate(): void
    {
        $this->createTurn(
            'I have a booking for two nights.',
            'outbound',
            'Your booking for two nights is confirmed.'
        );

        $result = $this->builder->resolveContext('Can I extend it?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('booking', $result->resolvedEntity['name']);
        $this->assertSame('Can I extend booking?', $result->resolvedQuery);
        $this->assertStringNotContainsString('booking for two', (string) $result->resolvedQuery);
    }

    /**
     * Adversarial Test: Sentence starting with "I Need" MUST NOT extract "I Need" as proper named entity.
     */
    public function test_clinic_appointment_with_modal_starter_resolves_delay_cleanly(): void
    {
        $this->createTurn(
            'I Need an appointment for tomorrow.',
            'outbound',
            'We have scheduled your appointment for 4 PM.'
        );

        $result = $this->builder->resolveContext('দেরি হলে কী করব?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('appointment', $result->resolvedEntity['name']);
        $this->assertSame('appointment দেরি হলে করণীয় কী', $result->resolvedQuery);
        $this->assertStringNotContainsString('I Need', (string) $result->resolvedQuery);
    }

    /**
     * Adversarial Test: Bengali sentence with indefinite quantifier "একটা" strips quantifier from entity candidate.
     */
    public function test_bengali_booking_with_indefinite_article_cleans_candidate(): void
    {
        $this->createTurn(
            'আমার একটা বুকিং আছে।',
            'outbound',
            'আপনার বুকিং কনফার্ম করা হয়েছে।'
        );

        $result = $this->builder->resolveContext('এটা বাড়ানো যাবে?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('বুকিং', $result->resolvedEntity['name']);
        $this->assertSame('বুকিং বাড়ানো যাবে?', $result->resolvedQuery);
    }

    /**
     * Adversarial Test: English delay follow-up grounds in clinic appointment antecedent.
     */
    public function test_english_delay_query_grounds_in_appointment_antecedent(): void
    {
        $this->createTurn(
            'I have an appointment for tomorrow.',
            'outbound',
            'Your appointment is scheduled for tomorrow at 3 PM.'
        );

        $result = $this->builder->resolveContext('What should I do if late?', $this->conversation);

        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('appointment', $result->resolvedEntity['name']);
        $this->assertSame('appointment দেরি হলে করণীয় কী', $result->resolvedQuery);
    }

    /**
     * Adversarial Test: Substantive short turns containing substrings "ok" (booking) or "hi" (thinkpad, shipping)
     * MUST NOT be discarded as pure conversational pleasantries.
     */
    public function test_substantive_turns_with_substrings_ok_hi_are_not_discarded_as_pleasantry(): void
    {
        $this->createTurn(
            'I have a booking confirmed',
            'outbound',
            'Your booking is locked in our system.'
        );

        $res1 = $this->builder->resolveContext('Can I extend it?', $this->conversation);
        $this->assertSame('resolved', $res1->status);
        $this->assertNotNull($res1->resolvedEntity);
        $this->assertSame('booking', $res1->resolvedEntity['name']);
        $this->assertSame('Can I extend booking?', $res1->resolvedQuery);

        $conv2 = Conversation::create([
            'channel_account_id' => $this->conversation->channel_account_id,
            'external_user_id'   => 'user_thinkpad_test',
            'status'             => 'active',
            'last_direction'     => 'inbound',
        ]);
        Message::create([
            'conversation_id' => $conv2->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'ThinkPad X1 laptop',
        ]);
        Message::create([
            'conversation_id' => $conv2->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'ThinkPad X1 is in stock.',
        ]);

        $res2 = $this->builder->resolveContext('etar warranty koto din?', $conv2);
        $this->assertSame('resolved', $res2->status);
        $this->assertNotNull($res2->resolvedEntity);
        $this->assertSame('ThinkPad X1', $res2->resolvedEntity['name']);
        $this->assertSame('ThinkPad X1 এর warranty koto din?', $res2->resolvedQuery);
    }

    /**
     * Adversarial Test: Bengali loanwords ending in -টি (e.g. ইউনিভার্সিটি, ওয়ারেন্টি)
     * MUST NOT be mutilated into trailing virama fragments (e.g. ওয়ারেন্).
     */
    public function test_bengali_loanwords_ending_in_ti_are_not_mutilated_into_virama_fragments(): void
    {
        $this->createTurn(
            'আমাদের কি কোনো ওয়ারেন্টি আছে?',
            'outbound',
            'জি, ১ বছরের ওয়ারেন্টি রয়েছে।'
        );

        // Warranty question should NOT invent a product named "কি কোনো ওয়ারেন্"
        $result = $this->builder->resolveContext('Can I extend it?', $this->conversation);
        $this->assertSame('unresolved', $result->status);
        $this->assertNull($result->resolvedQuery);
        $this->assertTrue($result->needsClarification());
    }

    /**
     * Adversarial Test: Banglish and Bengali pronoun substitutions (eta, otar, sheta, এগুলো, ওগুলো).
     * Must substitute entity cleanly without leaving raw pronoun or adding fallback wrapper.
     */
    public function test_banglish_and_bengali_pronouns_substitute_correctly(): void
    {
        $this->createTurn(
            'ThinkPad X1 laptop available?',
            'outbound',
            'Yes, ThinkPad X1 is available.'
        );

        // eta substitution
        $res1 = $this->builder->resolveContext('eta kivabe kinbo?', $this->conversation);
        $this->assertSame('resolved', $res1->status);
        $this->assertSame('ThinkPad X1 kivabe kinbo?', $res1->resolvedQuery);

        // এগুলো substitution
        $res2 = $this->builder->resolveContext('এগুলো কিভাবে কিনব?', $this->conversation);
        $this->assertSame('resolved', $res2->status);
        $this->assertSame('ThinkPad X1 কিভাবে কিনব?', $res2->resolvedQuery);
    }

    /**
     * Adversarial Test: Subjectless bare extension queries ("Can I extend?", "বাড়ানো যাবে?")
     * Must ground in antecedent when present, and return unresolved when absent.
     */
    public function test_subjectless_bare_extension_query_without_pronoun_grounds_in_antecedent(): void
    {
        // 1. With antecedent (Grand Hotel)
        $this->createTurn(
            'I have a booking for two nights at the Grand Hotel.',
            'outbound',
            'Your reservation is confirmed.'
        );

        $resWithAntecedent = $this->builder->resolveContext('Can I extend?', $this->conversation);
        $this->assertSame('resolved', $resWithAntecedent->status);
        $this->assertSame('Can I extend Grand Hotel?', $resWithAntecedent->resolvedQuery);

        $resBengali = $this->builder->resolveContext('বাড়ানো যাবে?', $this->conversation);
        $this->assertSame('resolved', $resBengali->status);
        $this->assertSame('Grand Hotel বাড়ানো যাবে?', $resBengali->resolvedQuery);

        // 2. Standalone without antecedent
        $emptyConv = Conversation::create([
            'channel_account_id' => $this->conversation->channel_account_id,
            'external_user_id'   => 'user_bare_extend',
            'status'             => 'active',
            'last_direction'     => 'inbound',
        ]);

        $resStandalone = $this->builder->resolveContext('Can I extend?', $emptyConv);
        $this->assertSame('unresolved', $resStandalone->status);
        $this->assertNull($resStandalone->resolvedQuery);
        $this->assertTrue($resStandalone->needsClarification());

        $resStandaloneBn = $this->builder->resolveContext('বাড়ানো যাবে?', $emptyConv);
        $this->assertSame('unresolved', $resStandaloneBn->status);
        $this->assertNull($resStandaloneBn->resolvedQuery);
        $this->assertTrue($resStandaloneBn->needsClarification());
    }

    /**
     * Adversarial Test: Dangling pronoun after delivery discussion without an order ID
     * MUST NOT be rewritten into fabricated topic questions.
     */
    public function test_dangling_pronoun_after_delivery_discussion_without_order_id_returns_unresolved(): void
    {
        $this->createTurn(
            'ডেলিভারি চার্জ কত?',
            'outbound',
            'ঢাকার ভেতরে ৭০ টাকা।'
        );

        $resWhere = $this->builder->resolveContext('Where is it?', $this->conversation);
        $this->assertSame('unresolved', $resWhere->status);
        $this->assertNull($resWhere->resolvedQuery);
        $this->assertTrue($resWhere->needsClarification());

        $resExtend = $this->builder->resolveContext('Can I extend it?', $this->conversation);
        $this->assertSame('unresolved', $resExtend->status);
        $this->assertNull($resExtend->resolvedQuery);
        $this->assertTrue($resExtend->needsClarification());
    }

    /**
     * Adversarial Test: Bengali numeric quantifiers (১টি vs কোনো) must not create false ambiguity.
     */
    public function test_bengali_numeric_quantifiers_do_not_create_false_ambiguity(): void
    {
        $this->createTurn(
            'আমার কোনো বুকিং আছে?',
            'outbound',
            'আপনার ১টি বুকিং আছে।'
        );

        $result = $this->builder->resolveContext('এটা বাড়ানো যাবে?', $this->conversation);
        $this->assertSame('resolved', $result->status);
        $this->assertNotNull($result->resolvedEntity);
        $this->assertSame('বুকিং', $result->resolvedEntity['name']);
        $this->assertSame('বুকিং বাড়ানো যাবে?', $result->resolvedQuery);
    }

    /**
     * Helper to create mock conversation turn.
     */
    private function createTurn(string $userMsg, string $botDir, string $botMsg): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => $userMsg,
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => $botDir,
            'type'            => 'text',
            'body'            => $botMsg,
        ]);
    }
}
