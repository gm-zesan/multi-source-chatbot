<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Models\Conversation;
use App\Services\AI\DTOs\ContextualResolutionResult;
use App\Services\Memory\DTOs\MemoryGateDecision;

/**
 * Memory Relevance Gate (Phase M3)
 *
 * Evaluates whether Conversation Graph Memory (Neo4j / Python Memory Service)
 * should be retrieved or bypassed for a given turn.
 *
 * Strict Architectural Rules:
 * 1. M3 NEVER overwrites M2's resolvedQuery.
 * 2. Generic policies and self-contained questions BYPASS memory (0ms latency, zero token waste).
 * 3. Explicit personal cues (e.g. "আমার আগের অর্ডার", "আমার আগের সাইজ") always RETRIEVE.
 * 4. When local turns already satisfy entity resolution, KGM retrieval is BYPASSED (Local priority).
 * 5. Out-of-Domain (OOD) queries strictly BYPASS memory (no forced personal context).
 * 6. Memory relevance confidence != Antecedent resolution confidence.
 */
class MemoryRelevanceGate
{
    /**
     * Pure conversational pleasantries that should always bypass memory.
     */
    private const CHIT_CHAT_EXACT = [
        'hi', 'hello', 'hey', 'good morning', 'good evening', 'good night',
        'kemon achen', 'valon ni', 'thanks', 'thank you', 'dhonnobad',
        'ok', 'okay', 'accha', 'thik ache', 'bye', 'tata',
    ];

    /**
     * Out-of-Domain indicators that should strictly bypass memory.
     */
    private const OOD_PATTERNS = [
        '/(আজকের\s*আবহাওয়া|weather|temperature|বৃষ্টি\s*হবে|weather\s*forecast)/ui',
        '/(world\s*cup|football\s*score|cricket\s*score|খেলা\s*কবে|score\s*koto)/ui',
        '/(tell\s*me\s*a\s*joke|কৌতুক|funny\s*story|sing\s*a\s*song)/ui',
        '/(capital\s+of|president\s+of|prime\s+minister|who\s+won|ইতিহাস\s+বলো)/ui',
        '/(write\s+(a\s+)?python|write\s+(a\s+)?code|script|program|coding|কোড\s+লিখে\s+দাও)/ui',
    ];

    /**
     * Evaluate the structured memory retrieval decision.
     */
    public function evaluate(
        string $query,
        ?Conversation $conversation = null,
        ?ContextualResolutionResult $contextResult = null
    ): MemoryGateDecision {
        $trimmed = trim($query);
        if (mb_strlen($trimmed) < 3) {
            return new MemoryGateDecision('BYPASS', 'query_too_short', 0.0);
        }

        // 1. If conversation has no customer/contact ID or doesn't exist, bypass memory
        if ($conversation === null) {
            return new MemoryGateDecision('BYPASS', 'null_conversation', 0.0);
        }

        $lower = mb_strtolower($trimmed);

        // 2. Pure chit-chat greeting
        if (in_array($lower, self::CHIT_CHAT_EXACT, true)) {
            return new MemoryGateDecision('BYPASS', 'pure_chitchat', 0.0);
        }

        // 3. Out-of-Domain (OOD) Gate: Personal memory must NOT force an answer to OOD inquiries
        foreach (self::OOD_PATTERNS as $pattern) {
            if (preg_match($pattern, $lower)) {
                return new MemoryGateDecision('BYPASS', 'ood_query', 0.0);
            }
        }

        // 4. Explicit Order Number or Consignment Code (#1042, order 1234, অর্ডার 1234)
        if (preg_match('/#\d{3,10}|\border\s+#?\d{3,8}\b|\bঅর্ডার\s+#?\d{3,8}\b/u', $lower) === 1) {
            return new MemoryGateDecision('RETRIEVE', 'explicit_order_id', 1.0);
        }

        // 5. Explicit Personal Preference, Order Recall, or Identity Inquiries
        // e.g. "আমার আগের অর্ডারটা কোথায়?", "আমার আগের সাইজটা কী ছিল?", "আমার পছন্দের পেমেন্ট"
        $hasPersonalRecallCue = $this->hasExplicitPersonalRecallCue($lower);
        if ($hasPersonalRecallCue) {
            return new MemoryGateDecision('RETRIEVE', 'personal_recall_cue', 0.95);
        }

        // 6. Active Reported Issue on Customer Goods (Broken item, missing parts, delivery delay)
        if ($this->hasPersonalDamagedOrIssueReport($lower)) {
            return new MemoryGateDecision('RETRIEVE', 'personal_reported_issue', 0.90);
        }

        // 7. Context-Result Aware Evaluations (M2 Coupling):
        if ($contextResult !== null) {
            // A. Self-contained query without personal recall cues strictly BYPASSES memory
            // e.g. "রিটার্ন পলিসি কি?", "ডেলিভারি চার্জ কত?"
            if ($contextResult->isSelfContained()) {
                return new MemoryGateDecision('BYPASS', 'self_contained_policy', 0.05);
            }

            // B. Local Context Priority: If M2 resolved entity from recent turns, KGM is bypassed
            // e.g. "User: iPhone 15 এর দাম কত? -> এটার দাম কত?" -> local turns already satisfied it
            if ($contextResult->source === 'local_turns' && $contextResult->isResolved()) {
                return new MemoryGateDecision('BYPASS', 'local_context_satisfied', 0.20, [
                    'resolved_entity' => $contextResult->resolvedEntity,
                ]);
            }

            // C. Anaphora / Ellipsis needing KGM Antecedents
            // e.g. "এটার দাম কত?" (no local entity found) or KGM candidate resolution
            if ($contextResult->source === 'kgm' || $this->hasAnaphoricDanglingPronoun($lower)) {
                return new MemoryGateDecision('RETRIEVE', 'anaphora_needing_memory', 0.85);
            }
        }

        // 8. Generic Policy, FAQ, or Store Info Gate (Without ContextResult)
        if ($this->isGenericPolicyOrStoreInfo($lower)) {
            return new MemoryGateDecision('BYPASS', 'generic_faq_policy', 0.05);
        }

        // 9. Conversational Anaphora or Continuity Keywords
        if ($this->hasConversationalAnaphoraKeywords($lower)) {
            return new MemoryGateDecision('RETRIEVE', 'conversational_anaphora', 0.80);
        }

        // 10. Specific Commercial Variant / Sizing Inquiries (e.g. "Do you have size XL in black?", "সাইজ কত পাওয়া যাবে?")
        if ($this->isSpecificVariantInquiry($lower)) {
            return new MemoryGateDecision('RETRIEVE', 'commercial_inquiry_with_potential_preference', 0.75);
        }

        // Default: General commercial inquiries without personal hooks bypass memory
        return new MemoryGateDecision('BYPASS', 'general_inquiry_no_memory_needed', 0.15);
    }

    /**
     * Backward-compatible boolean gate helper.
     */
    public function shouldRetrieve(
        string $query,
        ?Conversation $conversation = null,
        ?ContextualResolutionResult $contextResult = null
    ): bool {
        return $this->evaluate($query, $conversation, $contextResult)->shouldRetrieve();
    }

    /**
     * Detect explicit personal preference or memory recall cues in Bangla, Banglish, or English.
     */
    private function hasExplicitPersonalRecallCue(string $lower): bool
    {
        return (bool) preg_match(
            '/\b(i\s+(always\s+)?(prefer|like|wear|bought|ordered|chose))\b|' .
            '\b(my\s+(favorite|preferred|preference|size|order|payment|choice|info|color|wardrobe|chest|method))\b|' .
            '\b(what\s+(is|was)\s+my|remember\s+my|do\s+you\s+remember|keep\s+my)\b|' .
            '\b(amar\s+(favorite|preferred|preference|size|ordar|order|parcel|payment|choice|color|chest|wardrobe|default))\b|' .
            '\b(amar\s+(ki\s+)?(mone\s+ache|ager|previous))\b|' .
            '\b(mone\s+rakhte\s+parben|mone\s+ache|mone\s+rakhben|save\s+thakbe|mathay\s+rakhben)\b|' .
            '\b(ami.*(prefer|size|choice|bikash|bkash|korte\s+chai))\b|' .
            '\b(pochonder\s+size|default\s+payment|purchase\s+suggestion|pathao\s+consignment)\b|' .
            'আমার\s+(আগের|পছন্দের|পছন্দ|প্রিয়|বিকাশ|পেমেন্ট|অর্ডার|পার্সেল|তথ্য|কালার|চেস্ট|বুকের|সাইজ|size)|' .
            'আগের\s+(অর্ডার|সাইজ|পণ্য|কেনা|বার)|' .
            'আমি.*(পছন্দ\s+করি|পরি|কিনেছি|অর্ডার\s+দিয়েছি)|' .
            'পাঞ্জাবির\s+পছন্দের\s+সাইজ|চেস্ট\s+সাইজ|বুকের\s+মাপ|' .
            'মনে\s+রাখতে\s+পারবেন|মনে\s+রাখবেন|মনে\s+আছে|কী\s+ছিল/ui',
            $lower
        );
    }

    /**
     * Detect personal complaints or issues reported on received parcels.
     */
    private function hasPersonalDamagedOrIssueReport(string $lower): bool
    {
        return (bool) preg_match(
            '/(my\s+parcel|my\s+item|i\s+opened|i\s+received|amar\s+parcel|parcel\s+khule|delivered\s+parcel|আমার\s+(পাওয়া\s+)?পার্সেল|আমার\s+জামা).*(damaged|broken|defect|venge|ভাঙা|ছেঁড়া|নষ্ট|ভুল|wrong)/ui',
            $lower
        );
    }

    /**
     * Detect general policy, FAQ, pricing, and office inquiries.
     */
    private function isGenericPolicyOrStoreInfo(string $lower): bool
    {
        $patterns = [
            '/(policy|পলিসি|terms|শর্ত|গোপনীয়তা\s+নীতি|rules|রুলস)/ui',
            '/(refund\s+policy|return\s+policy|exchange\s+policy|রিটার্ন\s+পলিসি|রিফান্ড\s+পলিসি|এক্সচেঞ্জ\s+পলিসি)/ui',
            '/(delivery\s+charge|delivery\s+fee|shipping\s+charge|ডেলিভারি\s+চার্জ|ডেলিভারি\s+খরচ|ডেলিভারি\s+ফি|চার্জ\s+কত)/ui',
            '/(cash\s+on\s+delivery|cod|ক্যাশ\s+অন\s+ডেলিভারি)/ui',
            '/(office\s+address|office\s+thikana|store\s+address|showroom|আউটলেট|শোরুম|দোকান|অফিস\s+ঠিকানা|অফিস\s+কোথায়)/ui',
            '/(store\s+opening\s+hours|working\s+hours|সময়সূচী|কখন\s+খোলা|help\s*line|হেল্পলাইন)/ui',
            '/(about\s+us|about\s+company|company\s+story|কোম্পানি\s+সম্পর্কে)/ui',
            '/(how\s+to\s+reset|how\s+to\s+register|কীভাবে\s+অ্যাকাউন্ট)/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect conversational anaphora / follow-up keywords.
     */
    private function hasConversationalAnaphoraKeywords(string $lower): bool
    {
        return $this->hasAnaphoricDanglingPronoun($lower) || (bool) preg_match(
            '/\b(previous|earlier|last\s+(time|one|week|order)|that\s+one|same\s+as\s+before)\b|' .
            '\b(delivering\s+it|tracking\s+it|about\s+it|which\s+courier)\b|' .
            '\b(ager|ager\s+ta|sheta|oita|ager\s+order|eita\s+kon\s+courier)\b|' .
            'আগেরটা|আগের\s+অর্ডার|আগের\s+পণ্য|আগের\s+কেনা|আমার\s+আগের|সেটা|ঐটা|আগের\s+বারের|কোন\s+কুরিয়ারে|এটি\s+কোন/ui',
            $lower
        );
    }

    /**
     * Detect dangling pronouns ("এটার", "ওটার", "সেটা", "it", "that", "this").
     */
    private function hasAnaphoricDanglingPronoun(string $lower): bool
    {
        return (bool) preg_match(
            '/\b(it|this|that|them|they|these|those|its|their|' .
            'eta|ota|sheta|eita|oita|sheita|etar|otar|shetar|eitar|oitar)\b|' .
            '(^|[^\p{L}\p{N}])(এটা|ওটা|সেটা|এইটা|ওইটা|সেইটা|এটার|ওটার|সেটার|এগুলোর|ওগুলোর|এগুলো|ওগুলো)($|[^\p{L}\p{N}])/u',
            $lower
        );
    }

    /**
     * Detect specific variant/sizing inquiry without generic policy framing.
     */
    private function isSpecificVariantInquiry(string $lower): bool
    {
        $isVariant = (bool) preg_match('/\b(size\s+(xl|xxl|m|l|s|small|medium|large))\b|\b(in\s+(black|blue|navy|white|red))\b|সাইজ\s+কত/ui', $lower);
        $isPolicy = (bool) preg_match('/(বদলা|চেঞ্জ|change|না\s+মিললে|exchange|policy|পলিসি|চার্ট|chart|গাইড|guide|হলে\s+কি)/ui', $lower);

        return $isVariant && ! $isPolicy;
    }
}
