<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Agents\KnowledgeSupportAgent;
use App\Models\FAQ;
use App\Services\AI\DTOs\AnswerabilityDecision;
use App\Services\AI\Enums\AnswerabilityStatus;
use App\Services\AI\Evaluation\GenerationFaithfulnessEvaluator;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class GenerationFaithfulnessEvaluatorTest extends TestCase
{
    private GenerationFaithfulnessEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new GenerationFaithfulnessEvaluator();
    }

    private function makeHit(string $question, string $answer, string $docType = 'policy'): FAQSearchResult
    {
        $faq = new FAQ();
        $faq->question = $question;
        $faq->answer = $answer;
        $faq->document_type = $docType;

        return new FAQSearchResult(faq: $faq, finalScore: 0.90);
    }

    /**
     * 1. Fabricated 096 IPTSP hotline is flagged.
     */
    public function test_flags_fabricated_iptsp_096_hotline(): void
    {
        $doc = 'Customer support is available via website chat.';
        $hits = new EloquentCollection([$this->makeHit('Support', $doc)]);

        $reply = 'Please call our hotline 09612345678 for instant resolution.';
        $eval = $this->evaluator->evaluateTurn('hotline number?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['external_entities']);
        $this->assertStringContainsString('09612345678', $eval['external_entities'][0]);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
        $this->assertFalse($eval['is_faithful']);
    }

    /**
     * 1b. Supported 096 IPTSP hotline is unflagged.
     */
    public function test_supports_legitimate_iptsp_096_hotline(): void
    {
        $doc = 'Official hotline is +880-9612-345678 (available 9 AM to 10 PM daily).';
        $hits = new EloquentCollection([$this->makeHit('Support', $doc)]);

        $reply = 'You can reach us directly on our hotline 09612345678.';
        $eval = $this->evaluator->evaluateTurn('hotline number?', $reply, $hits, null, 'knowledge');

        $this->assertEmpty($eval['external_entities']);
        $this->assertFalse($eval['has_unsupported_claims']);
        $this->assertTrue($eval['is_grounded']);
        $this->assertTrue($eval['is_faithful']);
    }

    /**
     * 2. Fabricated hyphenated mobile number is flagged.
     */
    public function test_flags_fabricated_hyphenated_mobile_number(): void
    {
        $doc = 'We assist customers via Facebook messenger.';
        $hits = new EloquentCollection([$this->makeHit('Support', $doc)]);

        $reply = 'Call us on mobile at 01712-345678.';
        $eval = $this->evaluator->evaluateTurn('phone?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['external_entities']);
        $this->assertStringContainsString('01712-345678', $eval['external_entities'][0]);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 2b. Supported hyphenated mobile number across format variations.
     */
    public function test_supports_legitimate_hyphenated_mobile_number(): void
    {
        $doc = 'Official support mobile number is 01712-345678.';
        $hits = new EloquentCollection([$this->makeHit('Support', $doc)]);

        $reply = 'You can contact our support team at +8801712345678.';
        $eval = $this->evaluator->evaluateTurn('phone?', $reply, $hits, null, 'knowledge');

        $this->assertEmpty($eval['external_entities']);
        $this->assertFalse($eval['has_unsupported_claims']);
        $this->assertTrue($eval['is_grounded']);
    }

    /**
     * 3. Fabricated Bengali-digit phone number is flagged.
     */
    public function test_flags_fabricated_bengali_digit_phone(): void
    {
        $doc = 'আমাদের সাথে ফেসবুক ইনবক্সে যোগাযোগ করুন।';
        $hits = new EloquentCollection([$this->makeHit('যোগাযোগ', $doc)]);

        $reply = 'আমাদের হটলাইন ০১৬১২৩৪৫৬৭৮ নম্বরে সরাসরি কল দিন।';
        $eval = $this->evaluator->evaluateTurn('ফোন?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['external_entities']);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 3b. Supported Bengali-digit phone number across ASCII and Bengali representations.
     */
    public function test_supports_legitimate_bengali_digit_phone(): void
    {
        $doc = 'যোগাযোগের জন্য আমাদের অফিশিয়াল নম্বর ০১৬১২৩৪৫৬৭৮।';
        $hits = new EloquentCollection([$this->makeHit('যোগাযোগ', $doc)]);

        $reply = 'আমাদের সাপোর্ট টিমকে 01612345678 নম্বরে কল করতে পারেন।';
        $eval = $this->evaluator->evaluateTurn('ফোন?', $reply, $hits, null, 'knowledge');

        $this->assertEmpty($eval['external_entities']);
        $this->assertFalse($eval['has_unsupported_claims']);
        $this->assertTrue($eval['is_grounded']);
    }

    /**
     * 4. Unsupported @entrepreneurs.com email is flagged (no hardcoded whitelist exemption).
     */
    public function test_flags_unsupported_whitelisted_domain_email(): void
    {
        $doc = 'Support is provided only through our web portal.';
        $hits = new EloquentCollection([$this->makeHit('Help', $doc)]);

        $reply = 'For refund requests, email us at refund-priority@entrepreneurs.com.';
        $eval = $this->evaluator->evaluateTurn('email?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['external_entities']);
        $this->assertStringContainsString('refund-priority@entrepreneurs.com', $eval['external_entities'][0]);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 4b. Supported email in evidence passes cleanly.
     */
    public function test_supports_legitimate_evidence_backed_email(): void
    {
        $doc = 'Reach our team directly via email: support@entrepreneurs.com.';
        $hits = new EloquentCollection([$this->makeHit('Help', $doc)]);

        $reply = 'You can email our customer care team at support@entrepreneurs.com.';
        $eval = $this->evaluator->evaluateTurn('email?', $reply, $hits, null, 'knowledge');

        $this->assertEmpty($eval['external_entities']);
        $this->assertFalse($eval['has_unsupported_claims']);
        $this->assertTrue($eval['is_grounded']);
    }

    /**
     * 5. Symmetrical phone matching prevents false positives for local and international forms.
     */
    public function test_symmetric_phone_matching_prevents_false_positives_for_local_and_international(): void
    {
        $doc = 'Banani showroom contact number is 01712345678.';
        $hits = new EloquentCollection([$this->makeHit('Contact', $doc)]);

        $reply = 'You can reach the Banani showroom at +8801712345678.';
        $eval = $this->evaluator->evaluateTurn('Banani phone?', $reply, $hits, null, 'knowledge');

        $this->assertEmpty($eval['external_entities']);
        $this->assertEmpty($eval['unsupported_numbers']);
        $this->assertTrue($eval['is_grounded']);
        $this->assertTrue($eval['is_faithful']);
    }

    /**
     * 6. Order and tracking identifiers must not be parsed or flagged as phone numbers.
     */
    public function test_boundary_check_preserves_order_and_tracking_identifiers(): void
    {
        $doc = 'Track order status using your Order ID.';
        $hits = new EloquentCollection([$this->makeHit('Order', $doc)]);

        $reply = 'Your order reference is ORD-01712345678 and courier parcel code is 01712345678-1234.';
        $eval = $this->evaluator->evaluateTurn('my order?', $reply, $hits, null, 'knowledge');

        $this->assertEmpty($eval['external_entities'], 'Order and parcel tracking IDs must not be extracted as phone numbers');
    }

    /**
     * 7. Fabricated contact inside an OOD refusal is flagged.
     */
    public function test_flags_fabricated_contact_in_ood_refusal(): void
    {
        $decision = new AnswerabilityDecision(AnswerabilityStatus::UNANSWERABLE, new EloquentCollection(), 0.1, ['Out of domain']);
        $reply = 'এই বিষয়টি আমাদের আওতাভুক্ত নয়। বিস্তারিত তথ্যের জন্য আমাদের হটলাইন 09612345678 নম্বরে কল করুন।';
        $eval = $this->evaluator->evaluateTurn('Can you advise on income tax filing?', $reply, new EloquentCollection(), $decision, 'ood');

        $this->assertNotEmpty($eval['external_entities']);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded'], 'OOD refusal fabricating an ungrounded hotline must not be grounded');
    }

    /**
     * 8. Fabricated contact inside an ambiguous response is flagged.
     */
    public function test_flags_fabricated_contact_in_ambiguous_response(): void
    {
        $decision = new AnswerabilityDecision(AnswerabilityStatus::AMBIGUOUS, new EloquentCollection(), 0.5, ['Ambiguous fee intent']);
        $reply = 'আপনি কি ডেলিভারি চার্জ নাকি রিটার্ন চার্জ জানতে চাচ্ছেন? হটলাইন 01712-345678 নম্বরে কল করে জানতে পারেন।';
        $eval = $this->evaluator->evaluateTurn('charge koto?', $reply, new EloquentCollection(), $decision, 'uncertain');

        $this->assertNotEmpty($eval['external_entities']);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 9. Incorrect 1, 2, 3, 5, 7 policy days are flagged instead of suppressed by global ignore lists.
     */
    public function test_flags_hallucinated_policy_days_in_ignore_list(): void
    {
        $doc = 'পণ্য ডেলিভারি পাওয়ার ৩০ দিনের মধ্যে ম্যানুফ্যাকচারিং ডিফেক্ট ক্লেইম করতে পারবেন।';
        $hits = new EloquentCollection([$this->makeHit('Warranty', $doc)]);

        $reply = 'পণ্য ডেলিভারি পাওয়ার ৩ দিনের মধ্যে রিটার্ন ক্লেইম করতে হবে।';
        $eval = $this->evaluator->evaluateTurn('kobe return korte hobe?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['unsupported_numbers'], 'Day count 3 must be flagged as unsupported when not present in doc');
        $this->assertStringContainsString('3', $eval['unsupported_numbers'][0]);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 10. Legitimate list numbering and 24/7 hours are preserved without false positives.
     */
    public function test_preserves_legitimate_numeric_list_bullets_and_24_7_operational_hours(): void
    {
        $doc = 'Customer support is available 24/7. Step 1: Login to your account. Step 2: Select the item. Step 3: Confirm the order.';
        $hits = new EloquentCollection([$this->makeHit('Guide', $doc)]);

        $reply = "Our support team is available 24/7.\n1. Login to your account.\n2. Select the item.\n3. Confirm the order.";
        $eval = $this->evaluator->evaluateTurn('how to order?', $reply, $hits, null, 'knowledge');

        $this->assertEmpty($eval['unsupported_numbers'], 'List bullet markers (1., 2., 3.) and 24/7 must not be flagged as unsupported numbers');
        $this->assertTrue($eval['is_grounded']);
    }

    /**
     * 11. Short affirmative claim contradicting retrieved policy is flagged.
     */
    public function test_short_affirmative_claim_contradicting_policy_is_flagged(): void
    {
        $doc = 'পণ্য কুরিয়ারের মাধ্যমে পাঠাতে হবে। শোরুমে কোনো এক্সচেঞ্জ হবে না।';
        $hits = new EloquentCollection([$this->makeHit('Exchange', $doc)]);

        $reply = 'হ্যাঁ, শোরুমে এক্সচেঞ্জ হবে।';
        $eval = $this->evaluator->evaluateTurn('showroom e exchange hobe?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['contradictions']);
        $this->assertTrue($eval['has_policy_contradiction']);
        $this->assertFalse($eval['is_grounded']);
        $this->assertFalse($eval['is_faithful']);
    }

    /**
     * 12. Bengali negation contradiction: Delivery fee required vs free delivery claimed.
     */
    public function test_bengali_negation_contradiction_for_delivery_fee_is_flagged(): void
    {
        $doc = 'রিটার্নের ক্ষেত্রে কাস্টমারকে ১০০ টাকা ডেলিভারি চার্জ পরিশোধ করতে হবে।';
        $hits = new EloquentCollection([$this->makeHit('Fee', $doc)]);

        $reply = 'আমাদের এক্সচেঞ্জে ডেলিভারি সম্পূর্ণ ফ্রি, কোনো ডেলিভারি চার্জ দিতে হবে না।';
        $eval = $this->evaluator->evaluateTurn('delivery charge lagbe?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['contradictions']);
        $this->assertTrue($eval['has_policy_contradiction']);
        $this->assertFalse($eval['is_faithful']);
    }

    /**
     * 13. Banglish and code-mixed showroom exchange contradiction is flagged.
     */
    public function test_banglish_and_code_mixed_showroom_exchange_contradiction_is_flagged(): void
    {
        $doc = 'Exchange request online courier er maddhome korte hobe. Banani showroom e direct exchange kora jabe na.';
        $hits = new EloquentCollection([$this->makeHit('Exchange', $doc)]);

        $reply = 'Apni direct Banani showroom e giye product exchange korte parben.';
        $eval = $this->evaluator->evaluateTurn('Banani showroom exchange kora jabe?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['contradictions']);
        $this->assertTrue($eval['has_policy_contradiction']);
        $this->assertFalse($eval['is_faithful']);
    }

    /**
     * 14. Supported claims with paraphrasing and valid synonyms pass faithfully.
     */
    public function test_supported_claims_with_paraphrasing_pass_faithfully(): void
    {
        $doc = 'Products can be returned within 7 business days if unworn with original tags intact.';
        $hits = new EloquentCollection([$this->makeHit('Return', $doc)]);

        $reply = 'You can return your item within 7 business days as long as it remains unworn and the tags are intact.';
        $eval = $this->evaluator->evaluateTurn('can I return?', $reply, $hits, null, 'knowledge');

        $this->assertEmpty($eval['unsupported_numbers']);
        $this->assertEmpty($eval['contradictions']);
        $this->assertTrue($eval['is_grounded']);
        $this->assertTrue($eval['is_faithful']);
    }

    /**
     * 15. Generic closing question does not satisfy ambiguity clarification requirement.
     */
    public function test_generic_closing_question_does_not_satisfy_ambiguity_clarification(): void
    {
        $decision = new AnswerabilityDecision(AnswerabilityStatus::AMBIGUOUS, new EloquentCollection(), 0.5, ['Ambiguous']);
        $reply = 'আমাদের ডেলিভারি চার্জ ৮০ টাকা। আপনি কি আর কিছু জানতে চান?';
        $eval = $this->evaluator->evaluateTurn('delivery charge koto?', $reply, new EloquentCollection(), $decision, 'uncertain');

        $this->assertFalse($eval['is_ambiguous_clarified'], 'Generic pleasantry with question mark must not satisfy clarification');
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 15b. Substantive clarification question passes ambiguity check.
     */
    public function test_substantive_clarification_question_passes(): void
    {
        $decision = new AnswerabilityDecision(AnswerabilityStatus::AMBIGUOUS, new EloquentCollection(), 0.5, ['Ambiguous']);
        $reply = 'আপনি কি ঢাকার ভিতরের নাকি ঢাকার বাইরের ডেলিভারি চার্জ জানতে চাচ্ছেন?';
        $eval = $this->evaluator->evaluateTurn('delivery charge koto?', $reply, new EloquentCollection(), $decision, 'uncertain');

        $this->assertTrue($eval['is_ambiguous_clarified']);
        $this->assertTrue($eval['is_grounded']);
    }

    /**
     * 16. Legitimate safe abstention passes.
     */
    public function test_legitimate_safe_abstention_passes(): void
    {
        $decision = new AnswerabilityDecision(AnswerabilityStatus::UNANSWERABLE, new EloquentCollection(), 0.1, ['OOD']);
        $reply = 'দুঃখিত, এই বিষয়টি আমাদের কাস্টমার সাপোর্ট নলেজের আওতাভুক্ত নয়। আমাদের বিশেষজ্ঞ টিম আপনাকে সহায়তা করবে।';
        $eval = $this->evaluator->evaluateTurn('legal advice?', $reply, new EloquentCollection(), $decision, 'ood');

        $this->assertTrue($eval['is_safe_abstained']);
        $this->assertTrue($eval['is_grounded']);
        $this->assertTrue($eval['is_faithful']);
    }

    /**
     * 17. Sanitizer removes unsupported contact details while preserving valid surrounding content.
     */
    public function test_sanitizer_removes_unsupported_contact_while_preserving_surrounding_content(): void
    {
        $raw = 'Banani showroom e direct giye exchange kora jabe kina ta policy te nei (hotline: +880-9612-345678).';
        $sanitized = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($raw, []);

        $this->assertStringNotContainsString('+880-9612-345678', $sanitized);
        $this->assertSame('Banani showroom e direct giye exchange kora jabe kina ta policy te nei.', $sanitized);
    }

    /**
     * 18. Sanitizer nullifies JSON follow-up proposal when contact detail is removed.
     */
    public function test_sanitizer_nullifies_json_follow_up_when_contact_is_removed(): void
    {
        $raw = json_encode([
            'answer' => 'Exchange rules info.',
            'proposed_follow_up' => 'Call +880-9612-345678?',
            'follow_up_type' => 'call_support',
        ]);
        $sanitized = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails($raw, []);
        $decoded = json_decode($sanitized, true);

        $this->assertNull($decoded['proposed_follow_up']);
        $this->assertNull($decoded['follow_up_type']);
    }

    /**
     * 19. Multiple claims where one unsupported claim does not disappear inside aggregate ratio.
     */
    public function test_multiple_claims_with_one_unsupported_claim_fails_grounding(): void
    {
        $doc = 'পণ্য অব্যবহৃত থাকলে ৭ দিনের মধ্যে রিটার্ন করতে পারবেন এবং রিফান্ড পেয়ে যাবেন।';
        $hits = new EloquentCollection([$this->makeHit('Return', $doc)]);

        $reply = 'পণ্য অব্যবহৃত থাকলে ৭ দিনের মধ্যে রিটার্ন করতে পারবেন। আমাদের প্রতিনিধি নিজে এসে আপনার বাসা থেকে পণ্য সংগ্রহ করবেন।';
        $eval = $this->evaluator->evaluateTurn('return policy?', $reply, $hits, null, 'knowledge');

        $this->assertTrue($eval['has_unsupported_claims'], 'Turn with half its claims completely ungrounded must be marked unsupported');
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 20. Empty, malformed, and unusually formatted inputs fail safely without exceptions.
     */
    public function test_empty_and_malformed_inputs_fail_safely(): void
    {
        $evalEmpty = $this->evaluator->evaluateTurn('', '', new EloquentCollection(), null, 'knowledge');
        $this->assertIsArray($evalEmpty);
        $this->assertFalse($evalEmpty['is_grounded']);

        $evalSpaces = $this->evaluator->evaluateTurn('   ', "   \n\t  ", new EloquentCollection(), null, 'knowledge');
        $this->assertIsArray($evalSpaces);
        $this->assertFalse($evalSpaces['is_grounded']);
    }

    /**
     * 21. Fabricated hotline inside a calibrated uncertainty sentence still fails grounding.
     */
    public function test_calibrated_uncertainty_with_fabricated_hotline_still_fails_grounding(): void
    {
        $doc = 'অনলাইন পোর্টালের মাধ্যমে ৭ দিনের মধ্যে সাইজ এক্সচেঞ্জ করতে পারবেন।';
        $hits = new EloquentCollection([$this->makeHit('Exchange', $doc)]);

        $reply = 'পণ্য অব্যবহৃত থাকলে ৭ দিনের মধ্যে এক্সচেঞ্জ করা যাবে। তবে বনানী শোরুমে এক্সচেঞ্জ হবে কি না তা পলিসিতে উল্লেখ নেই (হটলাইন: 09612345678)।';
        $eval = $this->evaluator->evaluateTurn('size na mille ki showroom e jabe?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['external_entities']);
        $this->assertStringContainsString('09612345678', $eval['external_entities'][0]);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 22. Fabricated fee/numeric claim inside calibrated uncertainty still fails grounding.
     */
    public function test_calibrated_uncertainty_with_fabricated_fee_still_fails_grounding(): void
    {
        $doc = 'অনলাইন পোর্টালের মাধ্যমে ৭ দিনের মধ্যে সাইজ এক্সচেঞ্জ করতে পারবেন।';
        $hits = new EloquentCollection([$this->makeHit('Exchange', $doc)]);

        $reply = 'পণ্য অব্যবহৃত থাকলে ৭ দিনের মধ্যে এক্সচেঞ্জ করা যাবে। তবে এক্সচেঞ্জ ফি ৫০০ টাকা লাগবে কি না তা পলিসিতে উল্লেখ নেই।';
        $eval = $this->evaluator->evaluateTurn('fee lagbe?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['unsupported_numbers']);
        $this->assertStringContainsString('500', $eval['unsupported_numbers'][0]);
        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
    }

    /**
     * 23. Contradiction combined with calibrated uncertainty phrase still fails faithfulness.
     */
    public function test_calibrated_uncertainty_with_contradiction_still_fails_faithfulness(): void
    {
        $doc = 'শোরুমে কোনো এক্সচেঞ্জ হবে না। শুধুমাত্র অনলাইনে এক্সচেঞ্জ সম্ভব।';
        $hits = new EloquentCollection([$this->makeHit('Exchange', $doc)]);

        $reply = 'হ্যাঁ, শোরুমে এক্সচেঞ্জ হবে তবে বিস্তারিত পলিসিতে উল্লেখ নেই।';
        $eval = $this->evaluator->evaluateTurn('showroom e exchange hobe?', $reply, $hits, null, 'knowledge');

        $this->assertNotEmpty($eval['contradictions']);
        $this->assertTrue($eval['has_policy_contradiction']);
        $this->assertFalse($eval['is_grounded']);
        $this->assertFalse($eval['is_faithful']);
    }

    /**
     * 24. Completely fabricated policy claim paired with calibrated uncertainty fails grounding.
     */
    public function test_fabricated_policy_paired_with_calibrated_uncertainty_still_fails_grounding(): void
    {
        $doc = 'অনলাইন পোর্টালের মাধ্যমে ৭ দিনের মধ্যে সাইজ এক্সচেঞ্জ করতে পারবেন।';
        $hits = new EloquentCollection([$this->makeHit('Exchange', $doc)]);

        $reply = 'আমাদের প্রতিনিধি এসে আপনার বাসা থেকে পণ্য সংগ্রহ করবেন। তবে বনানী শোরুমের বিষয়ে পলিসিতে উল্লেখ নেই।';
        $eval = $this->evaluator->evaluateTurn('home pickup hobe?', $reply, $hits, null, 'knowledge');

        $this->assertTrue($eval['has_unsupported_claims']);
        $this->assertFalse($eval['is_grounded']);
    }
}
