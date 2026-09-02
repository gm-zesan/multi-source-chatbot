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

        // 0. Chat / Conversational & Personal Memory Evaluation
        if ($intentType === 'chat' && empty($expectedDocTypes)) {
            $hasValidReply = !empty($replyClean) && mb_strlen($replyClean) >= 5;
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

        // 5. Contradiction & External Entity Detection
        $contradictions = $this->detectPolicyContradictions($replyClean, $documentText);
        $externalEntities = $this->detectHallucinatedExternalEntities($replyClean, $documentText);

        // 6. Evidence-Supported Claim Ratio
        $claimStats = $this->computeClaimSupportRatio($replyClean, $documentText, $isOod, $isAmbiguous);

        $hasUnsupported = !empty($unsupportedNumbers) || !empty($externalEntities) || ($claimStats['supported_ratio'] < 0.50 && !$isOod && !$isAmbiguous);
        $hasContradiction = !empty($contradictions);

        $isGrounded = !$hasUnsupported && ($isOod ? $isSafeAbstained : true);
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
        $clarificationPatterns = [
            '/\?/u',
            '/কোন\s+(বিষয়ে|ব্যাপারে|চার্জ|ফি)/u',
            '/ডেলিভারি\s*চার্জ/u',
            '/রিটার্ন\s*চার্জ/u',
            '/which\s+(fee|charge|service)/i',
            '/delivery\s+fee/i',
            '/return\s+fee/i',
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
        ];

        foreach ($clarificationPatterns as $pattern) {
            if (preg_match($pattern, $reply)) {
                return true;
            }
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

        // Find standalone integers or decimals
        preg_match_all('/\b\d+(\.\d+)?\b/u', $normalizedReply, $replyMatches);
        $replyNumbers = array_unique($replyMatches[0] ?? []);

        preg_match_all('/\b\d+(\.\d+)?\b/u', $normalizedDoc . ' ' . $normalizedQuery, $validMatches);
        $validNumbers = array_unique($validMatches[0] ?? []);

        // Common structural/formatting numerals to ignore (e.g. 1., 2., 3. or 24/7)
        $ignored = ['1', '2', '3', '4', '5', '24', '7'];

        $unsupported = [];
        foreach ($replyNumbers as $num) {
            if (in_array($num, $ignored, true)) {
                continue;
            }
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

        // If doc explicitly says "non-refundable" or "ফেরতযোগ্য নয়", check if reply says "is refundable" without conditions
        if (preg_match('/(non-refundable|ফেরতযোগ্য\s*নয়|ফেরতযোগ্য\s*নয়|not\s+refundable)/ui', $documentText)) {
            if (preg_match('/(সম্পূর্ণ\s*ফেরতযোগ্য|fully\s+refundable|can\s+be\s+refunded\s+without)/ui', $reply)) {
                $contradictions[] = 'Contradiction: Document specifies non-refundable conditions, but answer claims fully refundable';
            }
        }

        return $contradictions;
    }

    /**
     * Detect hallucinated external links, emails, or phone numbers not in policy.
     *
     * @return array<string>
     */
    private function detectHallucinatedExternalEntities(string $reply, string $documentText): array
    {
        $hallucinations = [];

        // Check for phone numbers
        preg_match_all('/(\+?8801[3-9]\d{8}|01[3-9]\d{8})/u', $reply, $phoneMatches);
        foreach ($phoneMatches[0] ?? [] as $phone) {
            if (!str_contains($documentText, $phone)) {
                $hallucinations[] = "Hallucinated phone number: {$phone}";
            }
        }

        // Check for email addresses
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u', $reply, $emailMatches);
        foreach ($emailMatches[0] ?? [] as $email) {
            if (!str_contains($documentText, $email) && !str_contains($email, 'entrepreneurs.com')) {
                $hallucinations[] = "Hallucinated email address: {$email}";
            }
        }

        return $hallucinations;
    }

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
    ];

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

        $factualSentences = [];
        foreach ($sentences as $s) {
            $trimmed = trim($s);
            if (mb_strlen($trimmed) < 15) {
                continue;
            }
            // Skip pure conversational pleasantries, transitions, and closing offers
            if (preg_match($scaffoldingPattern, $trimmed) && mb_strlen($trimmed) < 40) {
                continue;
            }
            if (preg_match($scaffoldingEndPattern, $trimmed)) {
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

        foreach ($factualSentences as $sentence) {
            $tokens = preg_split('/[\s\p{P}]+/u', mb_strtolower($sentence), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $meaningfulTokens = array_filter($tokens, fn ($t) => mb_strlen($t) >= 3);

            if (empty($meaningfulTokens)) {
                $supportedCount++;
                continue;
            }

            $matched = 0;
            foreach ($meaningfulTokens as $t) {
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

            // A substantive claim is supported if at least 20% of its concepts match the source document
            if (($matched / count($meaningfulTokens)) >= 0.20) {
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

    /**
     * Convert Bengali digits to ASCII for comparison.
     */
    private function normalizeNumbers(string $text): string
    {
        return strtr($text, self::BN_TO_EN_DIGITS);
    }
}
