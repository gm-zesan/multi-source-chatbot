<?php

declare(strict_types=1);

namespace App\Services\AI\Evaluation;

use App\Services\AI\DTOs\AnswerabilityDecision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Deterministic Evaluator for STEP 4: E2E Generation Baseline & Faithfulness Attribution.
 *
 * Objectively labels every generated turn against the STEP 4 Formal Taxonomy:
 * - Groundedness & Faithfulness
 * - Unsupported Claim Detection
 * - Policy Contradiction Detection
 * - OOD Safe Abstention
 * - Ambiguity Clarification
 * - Evidence-Supported Claim Ratio
 */
class GenerationFaithfulnessEvaluator
{
    /**
     * Map Bengali numerals to standard ASCII numerals.
     */
    private const BN_TO_EN_DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    /**
     * Cross-lingual concept mapping (Bengali/Banglish to English document terms).
     */
    private const CONCEPT_DICTIONARY = [
        'ফেরত'       => ['return', 'refund', 'ফেরত'],
        'রিটার্ন'     => ['return', 'রিটার্ন'],
        'রিফান্ড'     => ['refund', 'money back', 'রিফান্ড'],
        'ডেলিভারি'   => ['delivery', 'shipping', 'ডেলিভারি'],
        'শিপিং'      => ['shipping', 'delivery', 'শিপিং'],
        'কুরিয়ার'    => ['courier', 'delivery', 'কুরিয়ার', 'কুরিয়ার'],
        'অব্যবহৃত'   => ['unworn', 'unused', 'intact', 'original condition', 'অব্যবহৃত'],
        'ট্যাগ'      => ['tag', 'tags', 'label', 'ট্যাগ'],
        'প্যাকেজিং'  => ['packaging', 'box', 'package', 'প্যাকেজিং'],
        'দিন'        => ['day', 'days', 'business days', 'দিন'],
        'ঘণ্টা'      => ['hour', 'hours', 'ঘণ্টা'],
        'ওয়ারেন্টি'   => ['warranty', 'guarantee', 'ওয়ারেন্টি', 'ওয়ারেন্টি'],
        'গ্যারান্টি'   => ['guarantee', 'warranty', 'গ্যারান্টি'],
        'এক্সচেঞ্জ'   => ['exchange', 'replace', 'বদলানো', 'এক্সচেঞ্জ'],
        'বদলানো'     => ['exchange', 'replace', 'change', 'বদলানো'],
        'বাতিল'      => ['cancel', 'cancellation', 'বাতিল'],
        'চার্জ'      => ['charge', 'fee', 'cost', 'চার্জ'],
        'ফি'         => ['fee', 'charge', 'ফি'],
        'ফ্রি'       => ['free', 'complimentary', 'ফ্রি', 'বিনামূল্যে'],
        'বিনামূল্যে'  => ['free', 'without charge', 'no charge', 'বিনামূল্যে'],
        'পেমেন্ট'    => ['payment', 'pay', 'paid', 'পেমেন্ট'],
        'বিকাশ'      => ['bkash', 'বিকাশ'],
        'নগদ'        => ['nagad', 'cash', 'নগদ'],
        'কার্ড'      => ['card', 'visa', 'mastercard', 'কার্ড'],
        'ক্যাশ'      => ['cash', 'cod', 'ক্যাশ'],
        'অর্ডার'      => ['order', 'purchase', 'অর্ডার'],
        'পার্সেল'    => ['parcel', 'package', 'item', 'product', 'পার্সেল'],
        'ইনভয়েস'     => ['invoice', 'receipt', 'bill', 'ইনভয়েস', 'রসিদ'],
        'রসিদ'       => ['invoice', 'receipt', 'bill', 'রসিদ'],
        'গ্রহণযোগ্য'  => ['eligible', 'acceptable', 'allowed', 'valid', 'accepted'],
        'পণ্য'        => ['item', 'product', 'products', 'goods', 'package'],
        'পোশাক'      => ['apparel', 'clothing', 'item', 'garment', 'clothes'],
        'পরীক্ষা'    => ['inspect', 'inspection', 'check', 'verify', 'received'],
        'আসল'        => ['original', 'authentic'],
        'কার্যদিবস'  => ['business days', 'working days', 'days'],
        'মাধ্যম'      => ['method', 'gateway', 'channel', 'mode'],
        'স্টক'        => ['stock', 'availability', 'available'],
        'শোরুম'      => ['showroom', 'outlet', 'store', 'retail'],
        'সাইজ'       => ['size', 'sizing', 'dimensions'],
        'ফিটিং'      => ['fitting', 'fit'],
        'সমস্যা'     => ['issue', 'problem', 'defect'],
        'কালার'      => ['color', 'colour'],
        'ক্রেডিট'     => ['credit', 'store credit'],
        'ফোন'        => ['phone', 'contact', 'call', 'mobile', 'hotline', 'ফোন', 'মোবাইল', 'হটলাইন', 'যোগাযোগ'],
        'মোবাইল'     => ['mobile', 'phone', 'contact', 'call', 'ফোন', 'মোবাইল'],
        'হটলাইন'     => ['hotline', 'helpline', 'contact', 'phone', 'হটলাইন', 'হেল্পলাইন'],
        'যোগাযোগ'    => ['contact', 'reach', 'call', 'support', 'যোগাযোগ', 'ফোন'],
        'কল'         => ['call', 'contact', 'phone', 'কল', 'যোগাযোগ'],
        'contact'    => ['যোগাযোগ', 'contact', 'reach', 'call', 'support', 'phone'],
        'call'       => ['কল', 'call', 'contact', 'phone'],
        'phone'      => ['ফোন', 'phone', 'contact', 'mobile'],
    ];

    /**
     * Convert Bengali digits to ASCII for comparison.
     */
    public function normalizeNumbers(string $text): string
    {
        return strtr($text, self::BN_TO_EN_DIGITS);
    }

    /**
     * Canonicalize Bangladesh and international phone numbers into a consistent digit representation.
     */
    public function canonicalizePhone(string $rawPhone): ?string
    {
        $norm = $this->normalizeNumbers($rawPhone);
        $digits = preg_replace('/[^\d]/', '', $norm);

        if (strlen($digits) < 7) {
            return null;
        }

        // 13 digits starting with 880 (e.g. 8801712345678 or 8809612345678)
        if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
            $sub = substr($digits, 3);
            if (preg_match('/^(?:1[3-9]|96)\d{8}$/', $sub)) {
                return '0' . $sub;
            }
        }

        // 11 digits starting with 01[3-9] or 096
        if (strlen($digits) === 11 && preg_match('/^0(?:1[3-9]|96)\d{8}$/', $digits)) {
            return $digits;
        }

        // 10 digits starting with 1[3-9] or 96 (omitted leading zero)
        if (strlen($digits) === 10 && preg_match('/^(?:1[3-9]|96)\d{8}$/', $digits)) {
            return '0' . $digits;
        }

        // Other BD landlines with 880 prefix
        if (str_starts_with($digits, '880') && strlen($digits) >= 10) {
            return '0' . substr($digits, 3);
        }

        return $digits;
    }

    /**
     * Extract discrete phone candidates with boundary protection.
     *
     * @return array<string, string> Map of rawCandidate => canonicalDigitString
     */
    public function extractPhones(string $text): array
    {
        $normText = $this->normalizeNumbers($text);

        // Boundary-aware phone regex:
        // Excludes ORD-01712345678 or tracking numbers like 01712345678-1234
        $phoneRegex = '/(?<![\p{L}\p{N}])(?<![\p{L}\p{N}][\-_])(?:\+?880[\s\-]?)?(?:0?1[3-9]|0?96)(?:[\s\-]?\d){8}(?![\-_]?\d)|(?<![\p{L}\p{N}])(?<![\p{L}\p{N}][\-_])\+\d{1,3}(?:[\s\-]?\d){7,12}(?![\-_]?\d)/u';

        preg_match_all($phoneRegex, $normText, $matches);
        $result = [];
        foreach ($matches[0] ?? [] as $matchStr) {
            $canon = $this->canonicalizePhone($matchStr);
            if ($canon !== null) {
                $result[$matchStr] = $canon;
            }
        }

        return $result;
    }

    /**
     * Extract discrete email addresses from text.
     *
     * @return array<string>
     */
    public function extractEmails(string $text): array
    {
        $emailRegex = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u';
        preg_match_all($emailRegex, $text, $matches);
        return array_unique($matches[0] ?? []);
    }

    /**
     * Extract discrete URLs from text.
     *
     * @return array<string>
     */
    public function extractUrls(string $text): array
    {
        preg_match_all('/\bhttps?:\/\/[^\s<>"\'()]+/i', $text, $matches);
        return array_unique($matches[0] ?? []);
    }

    /**
     * Evaluate a generated turn against grounded evidence and scenario intent.
     *
     * @param string                $query User query
     * @param string                $generatedReply LLM response
     * @param Collection            $groundedHits Strictly authorized documents passed to prompt
     * @param AnswerabilityDecision $decision Answerability Gate decision
     * @param string                $intentType Ground truth scenario intent ('knowledge', 'ood', 'uncertain', etc.)
     * @param array<string>         $expectedDocTypes Expected policy types
     * @return array<string, mixed>
     */
    public function evaluateTurn(
        string $query,
        string $generatedReply,
        Collection $groundedHits,
        ?AnswerabilityDecision $decision = null,
        string $intentType = 'knowledge',
        array $expectedDocTypes = [],
    ): array {
        $replyClean = trim($generatedReply);

        if ($replyClean === '') {
            return [
                'is_grounded'              => false,
                'is_faithful'              => false,
                'has_unsupported_claims'   => true,
                'has_policy_contradiction' => false,
                'is_safe_abstained'        => false,
                'is_ambiguous_clarified'   => false,
                'supported_claim_ratio'    => 0.0,
                'total_claims_evaluated'   => 1,
                'supported_claims_count'   => 0,
                'unsupported_numbers'      => [],
                'external_entities'        => [],
                'contradictions'           => [],
            ];
        }

        // 0. Chat / Conversational & Personal Memory Evaluation
        if ($intentType === 'chat' && empty($expectedDocTypes)) {
            $hasValidReply = mb_strlen($replyClean) >= 5;
            return [
                'is_grounded'              => $hasValidReply,
                'is_faithful'              => $hasValidReply,
                'has_unsupported_claims'   => false,
                'has_policy_contradiction' => false,
                'is_safe_abstained'        => false,
                'is_ambiguous_clarified'   => false,
                'supported_claim_ratio'    => 1.0,
                'total_claims_evaluated'   => 1,
                'supported_claims_count'   => 1,
                'unsupported_numbers'      => [],
                'external_entities'        => [],
                'contradictions'           => [],
            ];
        }

        // 1. OOD Safe Abstention Evaluation
        $isOod = ($intentType === 'ood') || ($decision !== null && $decision->isUnanswerable());
        $isSafeAbstained = false;
        if ($isOod) {
            $isSafeAbstained = $this->checkSafeAbstention($replyClean);
        }

        // 2. Ambiguity Clarification Evaluation
        $isAmbiguous = ($intentType === 'uncertain') || ($decision !== null && $decision->isAmbiguous());
        $isAmbiguousClarified = false;
        if ($isAmbiguous) {
            $isAmbiguousClarified = $this->checkClarificationPhrasing($replyClean);
        }

        // 3. Grounded Evidence Extraction
        $documentText = '';
        foreach ($groundedHits as $hit) {
            $documentText .= ' ' . ($hit->faq?->question ?? '') . ' ' . ($hit->faq?->answer ?? '');
        }

        // 4. Numeric and Policy Constraint Extraction
        $unsupportedNumbers = [];
        if (!$isOod && !$isAmbiguous && $groundedHits->isNotEmpty()) {
            $unsupportedNumbers = $this->extractUnsupportedNumbers($query, $replyClean, $documentText);
        }

        // 5. Contradiction & External Entity Detection (Always runs!)
        $contradictions = $this->detectPolicyContradictions($replyClean, $documentText);
        $externalEntities = $this->detectHallucinatedExternalEntities($replyClean, $documentText, $query);

        // 6. Evidence-Supported Claim Ratio
        $claimStats = $this->computeClaimSupportRatio($replyClean, $documentText, $isOod, $isAmbiguous);

        $hasContradiction = !empty($contradictions);
        $hasUnsupported = !empty($unsupportedNumbers)
            || !empty($externalEntities)
            || $hasContradiction
            || ($claimStats['supported_ratio'] <= 0.50 && !$isOod && !$isAmbiguous);

        $isGrounded = !$hasUnsupported && ($isOod ? $isSafeAbstained : true) && ($isAmbiguous ? $isAmbiguousClarified : true);
        $isFaithful = $isGrounded && !$hasContradiction;

        return [
            'is_grounded'              => $isGrounded,
            'is_faithful'              => $isFaithful,
            'has_unsupported_claims'   => $hasUnsupported,
            'has_policy_contradiction' => $hasContradiction,
            'is_safe_abstained'        => $isSafeAbstained,
            'is_ambiguous_clarified'   => $isAmbiguousClarified,
            'supported_claim_ratio'    => $claimStats['supported_ratio'],
            'total_claims_evaluated'   => $claimStats['total_claims'],
            'supported_claims_count'   => $claimStats['supported_claims'],
            'unsupported_numbers'      => $unsupportedNumbers,
            'external_entities'        => $externalEntities,
            'contradictions'           => $contradictions,
        ];
    }

    /**
     * Check if reply safely abstains without fabricating proprietary policies.
     */
    private function checkSafeAbstention(string $reply): bool
    {
        $abstentionPatterns = [
            '/আওতাভুক্ত\s*নয়/u',
            '/আওতাভুক্ত\s*নয়/u',
            '/কাস্টমার\s*সাপোর্ট\s*নলেজ/u',
            '/সাপোর্ট\s*স্পেশালিস্ট/u',
            '/সাপোর্ট\s*টিমের/u',
            '/out\s+of\s+scope/i',
            '/specialist\s+will\s+assist/i',
            '/support\s+team/i',
            '/not\s+covered\s+in\s+our\s+knowledge/i',
            '/cannot\s+assist\s+with\s+this/i',
            '/দয়া\s*করে\s*আমাদের\s*সার্ভিস/u',
            '/daya\s*kore\s*amader\s*service/i',
            '/তথ্য\s*(?:নেই|উল্লেখ\s*নেই|জানা\s*নেই)/u',
            '/পলিসিতে\s*(?:নেই|উল্লেখ\s*নেই)/u',
            '/উত্তর\s*দিতে\s*পারছি\s*না/u',
            '/সহায়তা\s*করতে\s*পারছি\s*না|সহায়তা\s*করতে\s*পারছি\s*না/u',
            '/cannot\s+provide|unable\s+to\s+assist|don\'?t\s+have\s+information|no\s+information\s+available/i',
            '/not\s+(?:mentioned|available)\s+in\s+our\s+policy/i',
            '/not\s+supported/i',
        ];

        foreach ($abstentionPatterns as $pattern) {
            if (preg_match($pattern, $reply)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if reply asks for clarification rather than guessing.
     */
    private function checkClarificationPhrasing(string $reply): bool
    {
        // Remove trailing conversational closing pleasantries before checking clarification
        $cleaned = preg_replace('/(?:আর\s*কিছু\s*জানতে\s*চান|কোনো\s*প্রশ্ন\s*থাকলে|সাহায্য\s*লাগলে|anything\s+else|further\s+questions?|help\s+you\s+with\s+anything)[\?\।\.\s]*$/ui', '', trim($reply));

        $clarificationPatterns = [
            '/কোন\s+(বিষয়ে|ব্যাপারে|চার্জ|ফি|এলাকা|লোকেশন|পদ্ধতি)/u',
            '/(?:ডেলিভারি\s*চার্জ\s*(?:নাকি|বা|অথবা)\s*রিটার্ন\s*চার্জ|কোন\s*ডেলিভারি\s*চার্জ)/u',
            '/delivery\s+(?:fee|charge)\s+(?:or|versus|vs)\s+return/i',
            '/which\s+(fee|charge|service|area|location)/i',
            '/could\s+you\s+please\s+clarify/i',
            '/bolben\s+ki/i',
            '/konta\s+janben/i',
            '/জানাতে\s*পারেন/u',
            '/বলবেন\s*কি/u',
            '/স্পষ্টভাবে\s*বুঝতে\s*পারিনি/u',
            '/বিস্তারিত\s*বলুন/u',
            '/please\s+clarify/i',
            '/could\s+you\s+elaborate/i',
            '/could\s+not\s+understand/i',
            '/ঢাকার\s*(?:ভিতরে|বাইরে)/u',
            '/inside\s*or\s*outside\s*dhaka/i',
        ];

        foreach ($clarificationPatterns as $pattern) {
            if (preg_match($pattern, $cleaned)) {
                return true;
            }
        }

        // If there is an explicit clarifying question mark in the substantive body (not just closing pleasantry)
        if (str_contains($cleaned, '?') && preg_match('/(?:কি|কিনা|কোনটি|কোন|who|what|where|which|how|naki|ki)/ui', $cleaned)) {
            return true;
        }

        return false;
    }

    /**
     * Extract numeric values from reply and verify if they exist in source document or user query.
     *
     * @return array<string>
     */
    private function extractUnsupportedNumbers(string $query, string $reply, string $documentText): array
    {
        $normalizedQuery = $this->normalizeNumbers($query);
        $normalizedReply = $this->normalizeNumbers($reply);
        $normalizedDoc = $this->normalizeNumbers($documentText);

        // Mask extracted phones and emails from reply so phone digits are not treated as standalone policy numbers
        $maskedReply = $normalizedReply;
        $extractedPhones = $this->extractPhones($reply);
        foreach (array_keys($extractedPhones) as $p) {
            $normP = $this->normalizeNumbers($p);
            $maskedReply = str_replace($normP, ' ', $maskedReply);
        }
        $extractedEmails = $this->extractEmails($reply);
        foreach ($extractedEmails as $e) {
            $maskedReply = str_replace($e, ' ', $maskedReply);
        }

        // Mask list numbering bullets (e.g. "1. ", "2. ", "1) ", "(1) ")
        $listBulletPattern = '/(?:^|[\n\r]|\.\s+)\s*\(?(\d+)[\.\)]\s+/u';
        $maskedReply = preg_replace($listBulletPattern, ' ', $maskedReply);

        // Mask 24/7 or 24x7
        $maskedReply = preg_replace('/24\s*[\/xX]\s*7/u', ' ', $maskedReply);

        // Find standalone integers or decimals in masked reply
        preg_match_all('/\b\d+(\.\d+)?\b/u', $maskedReply, $replyMatches);
        $replyNumbers = array_unique($replyMatches[0] ?? []);

        preg_match_all('/\b\d+(\.\d+)?\b/u', $normalizedDoc . ' ' . $normalizedQuery, $validMatches);
        $validNumbers = array_unique($validMatches[0] ?? []);

        $unsupported = [];
        foreach ($replyNumbers as $num) {
            if (!in_array($num, $validNumbers, true)) {
                $unsupported[] = "Number {$num} mentioned in answer but absent in document";
            }
        }

        return $unsupported;
    }

    /**
     * Detect explicit contradictions between reply and official constraints.
     *
     * @return array<string>
     */
    private function detectPolicyContradictions(string $reply, string $documentText): array
    {
        $contradictions = [];

        // 1. Non-refundable vs fully refundable
        if (preg_match('/(non-refundable|ফেরতযোগ্য\s*নয়|ফেরতযোগ্য\s*নয়|not\s+refundable)/ui', $documentText)) {
            if (preg_match('/(সম্পূর্ণ\s*ফেরতযোগ্য|fully\s+refundable|can\s+be\s+refunded\s+without)/ui', $reply)) {
                $contradictions[] = 'Contradiction: Document specifies non-refundable conditions, but answer claims fully refundable';
            }
        }

        // 2. Showroom exchange contradiction:
        // Doc says showroom exchange is NOT available / not mentioned / online courier only
        $docDisallowsShowroom = (bool) preg_match('/(?:শোরুমে|আউটলেটে|showroom|outlet|store|experience\s+center).*(?:হবে\s*না|করা\s*যাবে\s*না|সুযোগ\s*নেই|সম্ভব\s*নয়|সম্ভব\s*নয়|পলিসিতে\s*উল্লেখ\s*নেই|not\s+allowed|no\s+exchange|kora\s*jabe\s*na|jabe\s*na|hobe\s*na|shombhob\s*na)/ui', $documentText)
            || (bool) preg_match('/(?:না|নেই|not|no).*(?:শোরুমে|showroom).*(?:এক্সচেঞ্জ|exchange)/ui', $documentText)
            || ((bool) preg_match('/(?:শুধুমাত্র\s*অনলাইনে|online\s*only|কুরিয়ারের\s*মাধ্যমে).*(?:এক্সচেঞ্জ|exchange)/ui', $documentText) && !preg_match('/(?:শোরুমে|showroom).*(?:হবে|সম্ভব|allowed)/ui', $documentText));

        if ($docDisallowsShowroom) {
            $replyClaimsShowroom = (bool) preg_match('/(?:সরাসরি\s*শোরুমে|বনানী\s*শোরুমে|শোরুমে\s*গিয়ে|শোরুমে\s*গিয়ে|শোরুমে\s*এসে|showroom\s*e\s*giye|visit\s*(?:our\s*)?showroom|directly\s*at\s*(?:the\s*)?showroom).*(?:এক্সচেঞ্জ\s*হবে|এক্সচেঞ্জ\s*করতে\s*পারবেন|বদলানো\s*যাবে|exchange\s*possible|can\s*exchange|রিপ্লেসমেন্ট\s*দেওয়া\s*হয়|korte\s*parben|kora\s*jabe|possible)/ui', $reply)
                || (bool) preg_match('/^(?:জি|হ্যাঁ|yes)\s*,\s*.*(?:শোরুমে|showroom).*(?:এক্সচেঞ্জ|রিপ্লেসমেন্ট|exchange)/ui', trim($reply));

            if ($replyClaimsShowroom) {
                $contradictions[] = 'Contradiction: Document does not support showroom exchange, but answer claims showroom exchange is available';
            }
        }

        // 3. Delivery fee contradiction
        $docRequiresFee = (bool) preg_match('/(?:ডেলিভারি\s*চার্জ|ডেলিভারি\s*ফি|shipping\s*fee|delivery\s*charge).*(?:পরিশোধ\s*করতে\s*হবে|দিতে\s*হবে|প্রযোজ্য|applies|applicable)/ui', $documentText);
        if ($docRequiresFee) {
            $replyClaimsFree = (bool) preg_match('/(?:সম্পূর্ণ\s*ফ্রি|বিনামূল্যে\s*ডেলিভারি|ডেলিভারি\s*(?:চার্জ|ফি)\s*(?:দিতে\s*হবে\s*না|নেই|ছাড়া)|free\s*delivery|no\s*delivery\s*(?:charge|fee))/ui', $reply);
            if ($replyClaimsFree) {
                $contradictions[] = 'Contradiction: Document specifies delivery fee applies, but answer claims free delivery';
            }
        }

        return $contradictions;
    }

    /**
     * Detect hallucinated external links, emails, or phone numbers not in policy.
     *
     * @return array<string>
     */
    private function detectHallucinatedExternalEntities(string $reply, string $documentText, string $query = ''): array
    {
        $hallucinations = [];
        $evidenceSource = $documentText . ' ' . $query;

        // Phones
        $replyPhones = $this->extractPhones($reply);
        $evidencePhones = $this->extractPhones($evidenceSource);
        $supportedCanons = array_flip(array_values($evidencePhones));

        foreach ($replyPhones as $rawPhone => $canon) {
            if (!isset($supportedCanons[$canon])) {
                $hallucinations[] = "Hallucinated phone number: {$rawPhone}";
            }
        }

        // Emails
        $replyEmails = $this->extractEmails($reply);
        $evidenceLower = mb_strtolower($evidenceSource);
        foreach ($replyEmails as $email) {
            if (!str_contains($evidenceLower, mb_strtolower($email))) {
                $hallucinations[] = "Hallucinated email address: {$email}";
            }
        }

        // URLs
        $replyUrls = $this->extractUrls($reply);
        foreach ($replyUrls as $url) {
            if (!str_contains($evidenceSource, $url)) {
                $hallucinations[] = "Hallucinated external URL: {$url}";
            }
        }

        return $hallucinations;
    }

    /**
     * Compute the ratio of substantive claims backed by the document.
     *
     * @return array{supported_ratio: float, total_claims: int, supported_claims: int}
     */
    private function computeClaimSupportRatio(string $reply, string $documentText, bool $isOod, bool $isAmbiguous): array
    {
        if ($isOod || $isAmbiguous || empty($documentText)) {
            return [
                'supported_ratio'  => 1.0,
                'total_claims'     => 1,
                'supported_claims' => 1,
            ];
        }

        // Split reply into sentences/propositions
        $sentences = preg_split('/[\.\n!?।]+/u', $reply, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $scaffoldingPattern = '/^(hello|hi|sure|certainly|please|note|here\s+are|let\s+me\s+know|if\s+you\s+have|feel\s+free|জি|হ্যাঁ|ধন্যবাদ|নমস্কার|মনে\s+রাখবেন|শর্তগুলো|নিচে|আরও\s+কিছু|সাহায্যের\s+প্রয়োজন|কোনো\s+প্রশ্ন\s+থাকলে)/ui';
        $scaffoldingEndPattern = '/(সাহায্যের\s+প্রয়োজন\?|help\s+you\?|further\s+questions\?|জানাবেন।?$|সহায়তা\s+লাগলে)/ui';
        $policyNouns = '/(?:এক্সচেঞ্জ|রিটার্ন|রিফান্ড|শোরুম|টাকা|চার্জ|ফি|ডেলিভারি|ওয়ারেন্টি|গ্যারান্টি|অর্ডার|কুরিয়ার|exchange|refund|return|showroom|delivery|fee|charge|warranty)/ui';

        $factualSentences = [];
        foreach ($sentences as $s) {
            $trimmed = trim($s);
            if (mb_strlen($trimmed) < 15) {
                continue;
            }
            // Skip pure conversational pleasantries, transitions, and closing offers ONLY if they lack substantive policy tokens
            if (preg_match($scaffoldingPattern, $trimmed) && mb_strlen($trimmed) < 40 && !preg_match($policyNouns, $trimmed)) {
                continue;
            }
            if (preg_match($scaffoldingEndPattern, $trimmed) && !preg_match($policyNouns, $trimmed)) {
                continue;
            }
            $factualSentences[] = $trimmed;
        }

        if (empty($factualSentences)) {
            return [
                'supported_ratio'  => 1.0,
                'total_claims'     => 1,
                'supported_claims' => 1,
            ];
        }

        $supportedCount = 0;
        $docLower = mb_strtolower($documentText);
        $docPhones = $this->extractPhones($documentText);
        $supportedCanons = array_flip(array_values($docPhones));

        foreach ($factualSentences as $sentence) {
            // Check if sentence expresses calibrated uncertainty (admitting missing facts from policy)
            $isCalibratedUncertainty = (bool) preg_match('/(?:পলিসিতে|তথ্য|নলেজে|ডকুমেন্টে|policy).*(?:উল্লেখ\s*নেই|নেই|জানা\s*নেই|বলা\s*নেই|not\s+(?:mentioned|specified|stated))/ui', $sentence)
                || (bool) preg_match('/(?:does\s*not\s*mention|no\s+mention\s+of)/ui', $sentence);

            if ($isCalibratedUncertainty) {
                $supportedCount++;
                continue;
            }

            $sentPhones = $this->extractPhones($sentence);
            $hasSupportedPhone = false;
            foreach ($sentPhones as $canon) {
                if (isset($supportedCanons[$canon])) {
                    $hasSupportedPhone = true;
                    break;
                }
            }

            $tokens = preg_split('/[\s\p{P}]+/u', mb_strtolower($sentence), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $meaningfulTokens = array_filter($tokens, fn ($t) => mb_strlen($t) >= 3);

            if (empty($meaningfulTokens)) {
                $supportedCount++;
                continue;
            }

            $matched = 0;
            foreach ($meaningfulTokens as $t) {
                // Supported phone match
                if ($hasSupportedPhone && (isset($sentPhones[$t]) || str_contains($sentence, $t))) {
                    $canonT = $this->canonicalizePhone($t);
                    if ($canonT !== null && isset($supportedCanons[$canonT])) {
                        $matched++;
                        continue;
                    }
                }

                // Direct literal match
                if (str_contains($docLower, $t)) {
                    $matched++;
                    continue;
                }

                // Cross-lingual concept match
                if (isset(self::CONCEPT_DICTIONARY[$t])) {
                    foreach (self::CONCEPT_DICTIONARY[$t] as $synonym) {
                        if (str_contains($docLower, $synonym)) {
                            $matched++;
                            break;
                        }
                    }
                }
            }

            // A substantive claim is supported if at least 20% of its concepts match the source document,
            // or if it conveys a verified supported phone number and at least 1 context token matches
            if (($matched / count($meaningfulTokens)) >= 0.20 || ($hasSupportedPhone && $matched >= 1)) {
                $supportedCount++;
            }
        }

        $total = count($factualSentences);
        $ratio = round($supportedCount / max(1, $total), 4);

        return [
            'supported_ratio'  => $ratio,
            'total_claims'     => $total,
            'supported_claims' => $supportedCount,
        ];
    }
}
