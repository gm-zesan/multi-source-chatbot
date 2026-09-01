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

        // 3. Explicit order number pattern (#1234 or order 1234 or অর্ডার 1234)
        if (preg_match('/#\d{3,10}|\border\s+#?\d{3,8}\b|\bঅর্ডার\s+#?\d{3,8}\b/u', $lower) === 1) {
            return true;
        }

        // 4. Explicit personal preference disclosure or memory recall inquiry
        // Must be genuinely personal, e.g.:
        // "I prefer", "my favorite", "my size is", "what was my", "do you remember", "my chest"
        // "আমার পছন্দের", "আমার প্রিয়", "আমার সাইজ", "আমার পেমেন্ট", "মনে আছে", "কী ছিল"
        $hasPersonalPreferenceOrRecall = (bool) preg_match(
            '/\b(i\s+(always\s+)?(prefer|like|wear|bought|ordered|chose))\b|' .
            '\b(my\s+(favorite|preferred|preference|size|order|payment|choice|info|color|wardrobe|chest|method))\b|' .
            '\b(what\s+(is|was)\s+my|remember\s+my|do\s+you\s+remember|keep\s+my)\b|' .
            '\b(amar\s+(favorite|preferred|preference|size|ordar|order|payment|choice|color|chest|wardrobe|default))\b|' .
            '\b(amar\s+ki\s+mone\s+ache|mone\s+rakhte\s+parben|mone\s+ache|mone\s+rakhben|save\s+thakbe|mathay\s+rakhben)\b|' .
            '\b(ami\s+(shobshomoy|always|standard)?.*(prefer|size|choice))\b|' .
            '\b(pochonder\s+size|default\s+payment|purchase\s+suggestion|pathao\s+consignment)\b|' .
            'আমার\s+(পছন্দের|পছন্দ|প্রিয়|বিকাশ|পেমেন্ট|অর্ডার|তথ্য|কালার|চেস্ট|বুকের)|' .
            'আমি\s+(সবসময়|সাধারণত|আগে|স্ট্যান্ডার্ড).*(পছন্দ\s+করি|পরি|কিনেছি|অর্ডার\s+দিয়েছি)|' .
            'পাঞ্জাবির\s+পছন্দের\s+সাইজ|চেস্ট\s+সাইজ|বুকের\s+মাপ|' .
            'মনে\s+রাখতে\s+পারবেন|মনে\s+রাখবেন|মনে\s+আছে|কী\s+ছিল/u',
            $lower
        );

        // 5. Conversational anaphora / continuity / follow-up pronouns
        $hasConversationalAnaphora = (bool) preg_match(
            '/\b(previous|earlier|last\s+(time|one|week|order)|that\s+one|same\s+as\s+before)\b|' .
            '\b(delivering\s+it|tracking\s+it|about\s+it|which\s+courier)\b|' .
            '\b(ager|ager\s+ta|sheta|oita|ager\s+order|eita\s+kon\s+courier)\b|' .
            'আগেরটা|আগের\s+অর্ডার|আগের\s+পণ্য|আগের\s+কেনা|আমার\s+আগের|সেটা|ঐটা|আগের\s+বারের|কোন\s+কুরিয়ারে|এটি\s+কোন/u',
            $lower
        );

        // 6. Active reported issue on received customer goods with personal ownership
        // e.g. "আমার পাওয়া পার্সেলের বোতাম ভাঙা", "parcel khule dekhi button venge gese", "I opened my parcel and found a broken button"
        $hasPersonalDamagedIssue = (bool) preg_match(
            '/(my\s+parcel|my\s+item|i\s+opened|i\s+received|amar\s+parcel|parcel\s+khule|delivered\s+parcel|আমার\s+(পাওয়া\s+)?পার্সেল|আমার\s+জামা).*(damaged|broken|defect|venge|ভাঙা|ছেঁড়া|নষ্ট)/u',
            $lower
        );

        // 7. Specific product variant or sizing inquiry (e.g. "Do you have size XL in black?", "সাইজ কত পাওয়া যাবে?")
        // Excluding generic exchange/return policy framing
        $isSpecificVariant = (bool) preg_match('/\b(size\s+(xl|xxl|m|l|s|small|medium|large))\b|\b(in\s+(black|blue|navy|white|red))\b|সাইজ\s+কত/ui', $lower);
        $isPolicyFraming = (bool) preg_match('/(বদলা|চেঞ্জ|change|না\s+মিললে|exchange|policy|পলিসি|চার্ট|chart|গাইড|guide|হলে\s+কি)/ui', $lower);

        if ($hasPersonalPreferenceOrRecall || $hasConversationalAnaphora || $hasPersonalDamagedIssue || ($isSpecificVariant && !$isPolicyFraming)) {
            return true;
        }

        // 8. General commercial inquiries, general policy questions, sizing charts, warranty rules,
        // out-of-domain queries, and corporate questions strictly bypass memory
        return false;
    }
}
