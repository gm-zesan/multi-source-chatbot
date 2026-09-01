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
        'payment', 'পেমেন্ট', 'টাকা', 'bkash', 'bikash', 'বিকাশ', 'nagad', 'নগদ', 'card', 'কার্ড',
        'order', 'ordar', 'অর্ডার', 'parcel', 'পার্সেল', 'delivery', 'ডেলিভারি', 'status', 'track',
        'issue', 'problem', 'সমস্যা', 'ভাঙ্গা', 'নষ্ট', 'wrong', 'ভুল', 'delay', 'দেরি',
        'about me', 'my info', 'আমার তথ্য', 'remember', 'মনে আছে', 'আমার প্রেফারেন্স',
    ];

    /**
     * Generic FAQ and policy phrases that should bypass memory retrieval unless personal intent is present.
     */
    private const GENERIC_FAQ_KEYWORDS = [
        'policy', 'পলিসি', 'terms', 'শর্ত', 'how to', 'কীভাবে', 'কিভাবে',
        'office address', 'contact us', 'opening hours', 'সময়সূচী', 'হেল্পলাইন',
        'faq', 'রুলস', 'rules', 'refund policy', 'return policy', 'exchange policy',
        'delivery policy', 'shipping policy', 'payment policy', 'cancellation policy',
        'warranty policy', 'privacy policy', 'about us', 'about company', 'customer support',
        'রিফান্ড পলিসি', 'রিটার্ন পলিসি', 'এক্সচেঞ্জ পলিসি', 'ডেলিভারি পলিসি', 'পেমেন্ট পলিসি',
        'ওয়ারেন্টি পলিসি', 'বাতিল পলিসি', 'যোগাযোগ', 'অফিস ঠিকানা', 'গোপনীয়তা নীতি',
        'delivery charge', 'delivery fee', 'shipping charge', 'shipping fee', 'ডেলিভারি চার্জ', 'ডেলিভারি খরচ', 'ডেলিভারি ফি',
        'delivery time', 'ডেলিভারি সময়', 'delivery koto', 'charge koto', 'shipping rate',
        'payment method', 'payment option', 'cash on delivery', 'cod', 'পেমেন্ট মাধ্যম', 'পেমেন্ট নিয়ম',
        'office thikana', 'office address', 'store address', 'showroom', 'আউটলেট', 'শোরুম', 'দোকান',
        'how long', 'koto din', 'কত দিন', 'কতদিন', 'turnaround',
        'company story', 'company info', 'কোম্পানি সম্পর্কে', 'somporke',
        'warranty period', 'guarantee', 'ওয়ারেন্টি', 'গ্যারান্টি',
        'size chart', 'size guide', 'সাইজ চার্ট', 'fabric care', 'wash care',
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
        // Directives like "tell me", "inform me", "help me" do not constitute personal ownership/preference
        $cleanedDirective = preg_replace('/\b(tell|give|show|inform|help)\s+me\b/i', '', $lower);
        $hasPersonalRef = (bool) preg_match('/\b(my|mine|amar|amr|ami)\b|\b(i\s+(want|need|ordered|bought|prefer|have|wear|am|pay|like)|can\s+i)\b|আমার|আমাকে|আমি/u', $cleanedDirective);
        foreach (self::GENERIC_FAQ_KEYWORDS as $faqKw) {
            if (str_contains($lower, $faqKw) && !$hasPersonalRef) {
                return false;
            }
        }

        // 4. Explicit order number pattern (#1234 or order 1234)
        if (preg_match('/#\d{3,10}|\border\s+\d{3,8}\b|\bঅর্ডার\s+\d{3,8}\b/u', $lower) === 1) {
            return true;
        }

        // 5. Personal pronoun, preference, or conversational anaphora
        $hasConversationalContext = (bool) preg_match(
            '/\b(previous|earlier|last\s+(time|one|week|order)|that\s+one|remember|prefer)\b|' .
            '\b(ager|ager\s+ta|sheta|oita|mone\s+ache)\b|' .
            'আগের|আগেরটা|সেটা|ঐটা|মনে\s+আছে/u',
            $lower
        );

        if ($hasPersonalRef || $hasConversationalContext) {
            // Check if paired with any commercial entity or question
            foreach (self::COMMERCIAL_INTENTS as $kw) {
                if (str_contains($lower, $kw)) {
                    return true;
                }
            }
            // Even if general, personal context query should check memory
            return true;
        }

        // 6. Direct sizing or variant inquiry (e.g. "Do you have size XL in black?", "সাইজ কত পাওয়া যাবে?")
        if (preg_match('/\b(size|xl|xxl|medium|large|small|সাইজ|মাপ)\b/u', $lower) === 1) {
            return true;
        }

        // 7. Reported issue, damaged goods, or problem inquiry
        if (preg_match('/\b(damaged|broken|defect|wrong|ভাঙ্গা|নষ্ট|ভুল)\b/u', $lower) === 1) {
            return true;
        }

        // 8. Default: Generic informational or corporate inquiries do NOT need memory
        return false;
    }
}
