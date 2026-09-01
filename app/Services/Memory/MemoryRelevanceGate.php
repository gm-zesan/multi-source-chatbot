<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Models\Conversation;

class MemoryRelevanceGate
{
    /**
     * Phrases that should bypass memory retrieval (pure pleasantries / static greetings).
     */
    private const CHIT_CHAT_EXACT = [
        'hi', 'hello', 'hey', 'good morning', 'good evening', 'good night',
        'kemon achen', 'valon ni', 'thanks', 'thank you', 'dhonnobad',
        'ok', 'okay', 'accha', 'thik ache', 'bye', 'tata',
    ];

    /**
     * Commercial & personal intent keywords that should always trigger memory retrieval.
     */
    private const COMMERCIAL_INTENTS = [
        'size', 'সাইজ', 'মাপ', 'color', 'colour', 'রং', 'কালার',
        'payment', 'পেমেন্ট', 'টাকা', 'bkash', 'বিকাশ', 'nagad', 'নগদ', 'card', 'কার্ড',
        'order', 'অর্ডার', 'parcel', 'পার্সেল', 'delivery', 'ডেলিভারি', 'status', 'track',
        'issue', 'problem', 'সমস্যা', 'ভাঙ্গা', 'নষ্ট', 'wrong', 'ভুল', 'delay', 'দেরি',
        'about me', 'my info', 'আমার তথ্য', 'remember', 'মনে আছে', 'আমার প্রেফারেন্স',
    ];

    /**
     * Generic FAQ and policy phrases that should bypass memory retrieval unless personal intent is present.
     */
    private const GENERIC_FAQ_KEYWORDS = [
        'policy', 'পলিসি', 'terms', 'শর্ত', 'how to', 'কীভাবে', 'কিভাবে',
        'office address', 'contact us', 'opening hours', 'সময়সূচী', 'হেল্পলাইন',
        'faq', 'রুলস', 'rules', 'refund policy', 'return policy',
    ];

    /**
     * Determine if Conversation Graph Memory should be searched and injected.
     */
    public function shouldRetrieve(string $query, ?Conversation $conversation = null): bool
    {
        $trimmed = trim($query);
        if (mb_strlen($trimmed) < 3) {
            return false;
        }

        $lower = mb_strtolower($trimmed);

        // 1. If conversation has no customer/contact ID or doesn't exist, skip memory
        if ($conversation === null) {
            return false;
        }

        // 2. Pure chit-chat greeting without any other question
        if (in_array($lower, self::CHIT_CHAT_EXACT, true)) {
            return false;
        }

        // 3. Generic policy/FAQ query without personal intent should bypass memory
        $hasPersonalRef = (bool) preg_match('/\b(my|i|me|mine|amar|amr)\b|আমি|আমার|আমাকে/u', $lower);
        foreach (self::GENERIC_FAQ_KEYWORDS as $faqKw) {
            if (str_contains($lower, $faqKw) && !$hasPersonalRef) {
                return false;
            }
        }

        // 4. Commercial intent, personal preference, or order enquiry
        foreach (self::COMMERCIAL_INTENTS as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        // 5. Order number pattern (#1234 or order 1234)
        if (preg_match('/#\d{3,10}|\b\d{4,8}\b/', $lower) === 1) {
            return true;
        }

        // 6. Default: If query is moderately complex and conversational, allow retrieval
        // (The Python service relevance gate will filter if nothing matches anyway)
        return mb_strlen($trimmed) >= 15;
    }
}
