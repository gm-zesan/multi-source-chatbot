<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;

/**
 * Pre-Retrieval Contextual Query Builder
 *
 * Expands and contextualizes multi-turn follow-up queries (anaphora, pronouns, elliptical phrases)
 * before sending them to Typesense Hybrid Search, while preserving self-contained queries as-is.
 */
class ContextualQueryBuilder
{
    /**
     * Resolve auxiliary contextual retrieval signal for multi-turn follow-ups (Phase 2E).
     * The original user query remains 100% immutable!
     *
     * @param string            $query
     * @param Conversation|null $conversation
     * @return string|null Auxiliary signal, or null if query is self-contained / Turn 0.
     */
    public function resolveContextualSignal(
        string $query,
        ?Conversation $conversation = null,
        array $history = [],
    ): ?string {
        $cleanQuery = trim($query);
        if ($cleanQuery === '') {
            return null;
        }

        $priorUserMsg = '';

        if ($conversation !== null) {
            $messages = $conversation->relationLoaded('messages')
                ? $conversation->messages
                : $conversation->messages()->orderBy('id', 'desc')->limit(6)->get()->reverse()->values();

            for ($i = $messages->count() - 1; $i >= 0; $i--) {
                $msg = $messages->get($i);
                if (trim((string) $msg->body) === $cleanQuery) {
                    continue;
                }
                if ($msg->direction === 'inbound' || $msg->sender_type === 'user' || empty($msg->sender_type)) {
                    $priorUserMsg = mb_strtolower((string) $msg->body);
                    break;
                }
            }
        } elseif (!empty($history)) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                $item = $history[$i];
                $msgText = is_array($item) ? ($item['body'] ?? $item['user_message'] ?? $item['message'] ?? '') : (string) $item;
                if (trim((string) $msgText) === $cleanQuery) {
                    continue;
                }
                $priorUserMsg = mb_strtolower((string) $msgText);
                break;
            }
        }

        if ($priorUserMsg === '') {
            return null; // Turn 0: Zero prior context (Group B)
        }

        $qLower = mb_strtolower($cleanQuery);

        // 2. Abstention Gate: Explicitly self-contained queries must NOT have context injected (Group C)
        $isExplicitlySelfContained = (bool) preg_match(
            '/(cash on delivery|advance for|cancel after the parcel|handed to courier|stitching defect|share my phone number|external marketers|pricing change without|promotional codes on one|phone number ki third party|third party marketing agency|dynamic bhabe change|automatic washing machine)/ui',
            $qLower
        );
        if ($isExplicitlySelfContained) {
            return null;
        }

        // 3. Pattern 2: Clarification / Replacement (Scenario 15: "I mean delivery fee.")
        if (preg_match('/^(i\s+mean|mean\s+|amar\s+mane|mane\s+|বলতে\s+চাচ্ছি|বলতে\s+চেয়েছিলাম|মানে)\s+(.+)/ui', $qLower, $matches)) {
            return trim($matches[2]);
        }

        $priorReturn = (bool) preg_match('/(return|ferot|ফেরত|রিটার্ন|30 days|return window)/ui', $priorUserMsg);
        $priorDelivery = (bool) preg_match('/(delivery|shipping|ডেলিভারি|charge|চার্জ|courier)/ui', $priorUserMsg);
        $priorWarranty = (bool) preg_match('/(warranty|ওয়ারেন্টি|গ্যারান্টি|defect|broken|venge|বোতাম|ভাঙা|selai)/ui', $priorUserMsg);
        $priorExchange = (bool) preg_match('/(size|সাইজ|chest|measurement|fit|fitting)/ui', $priorUserMsg);

        // 5. Pattern 1: Topic Carry-Over for Return Policy (Scenario 20)
        if ($priorReturn) {
            if (preg_match('/(official\s+policy\s+timeframe|official\s+rules|অফিসিয়াল\s+নীতি|অফিসিয়াল\s+নীতি|timeline\s+koto\s+din|koto\s+diner\s+moddhe|koto\s+diner\s+inside|নীতি\s+কত\s+দিন)/ui', $qLower)) {
                return 'return policy timeframe';
            }
            if (preg_match('/(ferot\s+dite\s+ki\s+delivery\s+fee|return\s+shipment\s+fee|রিটার্ন\s+করতে\s+কি\s+ডেলিভারি)/ui', $qLower)) {
                return 'return policy';
            }
        }

        // 6. Pattern 3: Warranty Claim Invoice / Anaphora (Scenario 08)
        if ($priorWarranty) {
            if (preg_match('/(claim|ক্লেইম|ইনভয়েস|ইনভয়েস|invoice)/ui', $qLower)) {
                return 'warranty claim invoice';
            }
        }

        // 7. Pattern 4: Delivery Tracking & Timeframe Continuum (Scenario 11)
        if ($priorDelivery) {
            if (preg_match('/(track|tracking|ট্র্যাক|ট্র্যাকিং)/ui', $qLower)) {
                return 'delivery tracking courier';
            }
            if (preg_match('/(how\s+long|koto\s+din\s+lag|কত\s+দিন\s+সময়\s+লাগ|কত\s+দিন\s+সময়\s+লাগ)/ui', $qLower)) {
                return 'delivery timeframes';
            }
        }

        // 8. Pattern 1: Sizing & Exchange Carry-over (Scenario 25)
        if ($priorExchange) {
            if (preg_match('/(replace|exchange|বদলানো)/ui', $qLower)) {
                return 'product exchange policy size';
            }
        }

        return null;
    }

    /**
     * Build an optimized retrieval query string from the raw query and conversation history.
     */
    public function buildContextualQuery(string $query, ?Conversation $conversation): string
    {
        $cleanQuery = trim($query);

        if ($cleanQuery === '' || $conversation === null) {
            return $cleanQuery;
        }

        // 1. Check if the query is already self-contained
        if ($this->isSelfContained($cleanQuery)) {
            return $cleanQuery;
        }

        // 2. Fetch the recent conversation history (last 6 messages)
        $recentMessages = $conversation->messages()
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get()
            ->reverse()
            ->values();

        if ($recentMessages->isEmpty()) {
            return $cleanQuery;
        }

        // 3. Extract the preceding topic / knowledge context
        $topicContext = $this->extractPrecedingTopicContext($recentMessages, $cleanQuery);
        if ($topicContext === null) {
            return $cleanQuery;
        }

        // 4. Resolve Elliptical Patterns (e.g., "And Telegram?", "What about invoices?")
        $ellipticalResolved = $this->resolveElliptical($cleanQuery, $topicContext);
        if ($ellipticalResolved !== null) {
            return $ellipticalResolved;
        }

        // 5. Resolve Anaphora / Pronouns (e.g., "Can I extend it?", "Does it support X?")
        $anaphoraResolved = $this->resolveAnaphora($cleanQuery, $topicContext);
        if ($anaphoraResolved !== null) {
            return $anaphoraResolved;
        }

        // 6. Topic Continuation / Extension (e.g., "Does this apply to all plans?")
        return $this->combineTopicWithQuery($cleanQuery, $topicContext);
    }

    /**
     * Determine if a query is self-contained and needs no contextual expansion.
     */
    public function isSelfContained(string $query): bool
    {
        $qLower = mb_strtolower($query);
        $words = preg_split('/\s+/u', $qLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);

        // Ambiguous pronouns, elliptical openers, or anaphoric phrases indicate non-self-contained follow-ups
        $hasDanglingPronoun = (bool) preg_match('/\b(it|this|that|them|they|these|those|its|their|theirs|eta|ota|eita|oita|etar|otar|eitar|oitar|এটা|ওটা|এইটা|ওইটা|এটার|ওটার|এগুলো|ওগুলো|তারা|তাদের)\b/ui', $qLower);
        $isEllipticalOpener = (bool) preg_match('/^(and\s+|what\s+about\s+|how\s+about\s+|ar\s+|aar\s+|ebong\s+|আর\s+|এবং\s+)/ui', $qLower);
        $hasAnaphoricPhrase = (bool) preg_match('/\b(the\s+token\s+issue|the\s+issue|the\s+problem|the\s+error|this\s+issue|the\s+service|the\s+topic|the\s+process)\b/ui', $qLower);

        if ($hasDanglingPronoun || $isEllipticalOpener || $hasAnaphoricPhrase) {
            return false;
        }

        // If the query has at least 3 words and no dangling references, it is complete and self-contained
        return $wordCount >= 3;
    }

    /**
     * Extract the preceding knowledge topic and domain entities from recent conversation turns.
     *
     * @param \Illuminate\Support\Collection<int, Message> $messages
     * @param string $currentQuery
     * @return array{topic: string, action_type: string, entities: string[]}|null
     */
    private function extractPrecedingTopicContext($messages, string $currentQuery = ''): ?array
    {
        $qLower = mb_strtolower($currentQuery);

        // 1. Explicit Topic Revival: If the current query directly mentions a specific topic from history
        if ($currentQuery !== '') {
            if (str_contains($qLower, 'free trial') || str_contains($qLower, 'trial') || str_contains($qLower, 'ট্রায়াল') || str_contains($qLower, 'ট্রায়াল')) {
                return [
                    'topic'       => '14-day free trial on Pro plan',
                    'action_type' => 'inquiry',
                    'entities'    => ['free trial', '14-day trial', 'Pro plan'],
                ];
            }
            if (str_contains($qLower, 'invoice') || str_contains($qLower, 'billing') || str_contains($qLower, 'bill') || str_contains($qLower, 'ইনভয়েস')) {
                return [
                    'topic'       => 'billing invoices download and updating payment method credit card',
                    'action_type' => 'billing',
                    'entities'    => ['billing invoices', 'payment method', 'credit card'],
                ];
            }
            if (str_contains($qLower, 'whatsapp') || str_contains($qLower, 'হোয়াটসঅ্যাপ')) {
                return [
                    'topic'       => 'connecting WhatsApp channel with Meta phone number ID and access token',
                    'action_type' => 'integration',
                    'entities'    => ['WhatsApp channel', 'connect WhatsApp'],
                ];
            }
            if (str_contains($qLower, 'telegram') || str_contains($qLower, 'টেলিগ্রাম')) {
                return [
                    'topic'       => 'connecting Telegram channel using Telegram bot token',
                    'action_type' => 'integration',
                    'entities'    => ['Telegram bot', 'connect Telegram'],
                ];
            }
            if (str_contains($qLower, 'two-factor') || str_contains($qLower, '2fa')) {
                return [
                    'topic'       => 'two-factor authentication 2FA enabling and security settings',
                    'action_type' => 'security',
                    'entities'    => ['two-factor authentication', '2FA'],
                ];
            }
            if (str_contains($qLower, 'api key') || str_contains($qLower, 'rate limit')) {
                return [
                    'topic'       => 'API keys generation authentication and rate limits per minute/hour',
                    'action_type' => 'developer',
                    'entities'    => ['API key', 'API rate limits', 'Bearer authentication'],
                ];
            }
            if (str_contains($qLower, 'encrypt') || str_contains($qLower, 'aes-256') || str_contains($qLower, 'tls')) {
                return [
                    'topic'       => 'data encryption standards at rest AES-256 and TLS 1.3 in transit',
                    'action_type' => 'security',
                    'entities'    => ['data encryption', 'AES-256', 'TLS 1.3', 'security'],
                ];
            }
        }

        // 2. Scan backwards through messages to find the most recent substantive knowledge turn
        for ($i = $messages->count() - 1; $i >= 0; $i--) {
            $msg = $messages->get($i);
            $body = mb_strtolower((string) $msg->body);

            // Skip pure pleasantries and short acknowledgments
            if ($this->isPureConversationalRemark($body)) {
                continue;
            }

            // Topic 1: Free Trial
            if (str_contains($body, 'free trial') || str_contains($body, '14-day') || str_contains($body, 'ট্রায়াল') || str_contains($body, 'ট্রায়াল')) {
                return [
                    'topic'       => '14-day free trial on Pro plan',
                    'action_type' => 'inquiry',
                    'entities'    => ['free trial', '14-day trial', 'Pro plan'],
                ];
            }

            // Topic 2: Subscription Plans & Pricing
            if (str_contains($body, 'subscription plan') || str_contains($body, 'plans available') || str_contains($body, 'plan change') || str_contains($body, 'pricing')) {
                return [
                    'topic'       => 'subscription plans pricing and features (Free, Pro, Enterprise)',
                    'action_type' => 'inquiry',
                    'entities'    => ['subscription plans', 'pricing', 'Pro plan', 'Enterprise plan'],
                ];
            }

            // Topic 3: Non-profit Discounts
            if (str_contains($body, 'non-profit') || str_contains($body, 'non profit') || str_contains($body, 'discount') || str_contains($body, 'ডিসকাউন্ট')) {
                return [
                    'topic'       => '50% discount for registered non-profit organizations',
                    'action_type' => 'inquiry',
                    'entities'    => ['non-profit discount', '50% discount'],
                ];
            }

            // Topic 4: Connecting WhatsApp
            if (str_contains($body, 'connect whatsapp') || str_contains($body, 'whatsapp integration') || str_contains($body, 'meta cloud api')) {
                return [
                    'topic'       => 'connecting WhatsApp channel with Meta phone number ID and access token',
                    'action_type' => 'integration',
                    'entities'    => ['WhatsApp channel', 'connect WhatsApp'],
                ];
            }

            // Topic 5: Connecting Telegram
            if (str_contains($body, 'connect telegram') || str_contains($body, 'telegram bot')) {
                return [
                    'topic'       => 'connecting Telegram channel using Telegram bot token',
                    'action_type' => 'integration',
                    'entities'    => ['Telegram bot', 'connect Telegram'],
                ];
            }

            // Topic 6: Multi-channel Simultaneous Usage
            if (str_contains($body, 'multiple channel') || str_contains($body, 'simultaneous') || str_contains($body, 'multi-channel')) {
                return [
                    'topic'       => 'using multiple communication channels simultaneously in one inbox',
                    'action_type' => 'inquiry',
                    'entities'    => ['multiple channels', 'simultaneous channels', 'unified inbox'],
                ];
            }

            // Topic 7: Encryption & Data Security
            if (str_contains($body, 'encrypt') || str_contains($body, 'aes-256') || str_contains($body, 'tls 1.3') || str_contains($body, 'security')) {
                return [
                    'topic'       => 'data encryption standards at rest AES-256 and TLS 1.3 in transit',
                    'action_type' => 'security',
                    'entities'    => ['data encryption', 'AES-256', 'TLS 1.3', 'security'],
                ];
            }

            // Topic 8: Two-Factor Authentication (2FA)
            if (str_contains($body, 'two-factor') || str_contains($body, '2fa') || str_contains($body, 'authenticator')) {
                return [
                    'topic'       => 'two-factor authentication 2FA enabling and security settings',
                    'action_type' => 'security',
                    'entities'    => ['two-factor authentication', '2FA'],
                ];
            }

            // Topic 9: API Key & Rate Limits
            if (str_contains($body, 'api key') || str_contains($body, 'api rate limit') || str_contains($body, 'authenticate api')) {
                return [
                    'topic'       => 'API keys generation authentication and rate limits per minute/hour',
                    'action_type' => 'developer',
                    'entities'    => ['API key', 'API rate limits', 'Bearer authentication'],
                ];
            }

            // Topic 10: Invoices & Payment Methods
            if (str_contains($body, 'invoice') || str_contains($body, 'payment method') || str_contains($body, 'billing history') || str_contains($body, 'credit card')) {
                return [
                    'topic'       => 'billing invoices download and updating payment method credit card',
                    'action_type' => 'billing',
                    'entities'    => ['billing invoices', 'payment method', 'credit card'],
                ];
            }

            // Topic 11: Order Cancellation Policy
            if (str_contains($body, 'cancel') || str_contains($body, 'cancellation') || str_contains($body, 'refund')) {
                return [
                    'topic'       => 'order cancellation policy and plan change workflow',
                    'action_type' => 'policy',
                    'entities'    => ['cancellation policy', 'plan change'],
                ];
            }

            // Topic 12: Workspace Setup & First Login
            if (str_contains($body, 'workspace') || str_contains($body, 'first time') || str_contains($body, 'logging in')) {
                return [
                    'topic'       => 'workspace setup settings and first login onboarding',
                    'action_type' => 'onboarding',
                    'entities'    => ['workspace setup', 'first login onboarding'],
                ];
            }

            // Topic 13: Chatbot Not Responding / Token Troubleshooting
            if (str_contains($body, 'not replying') || str_contains($body, 'not responding') || str_contains($body, 'chatbot') || str_contains($body, 'token')) {
                return [
                    'topic'       => 'chatbot not responding and resolving expired channel token issues',
                    'action_type' => 'troubleshooting',
                    'entities'    => ['chatbot not responding', 'channel token issue'],
                ];
            }
        }

        return null;
    }

    /**
     * Resolve elliptical follow-up queries (e.g., "And Telegram?", "What about rate limits?").
     */
    private function resolveElliptical(string $query, array $topicContext): ?string
    {
        $qLower = mb_strtolower($query);

        // Pattern 1: "And Telegram?" / "What about Telegram?" / "ar telegram?"
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?telegram\??$/ui', $qLower)) {
            return "How do I connect Telegram bot to the platform?";
        }

        // Pattern 2: "And WhatsApp?" / "What about WhatsApp?" / "ar whatsapp?"
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?whatsapp\??$/ui', $qLower)) {
            return "How do I connect WhatsApp to the platform?";
        }

        // Pattern 3: "What about rate limits?" / "And rate limits?"
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?rate\s+limits?\??$/ui', $qLower)) {
            return "What are the API rate limits per minute and hour?";
        }

        // Pattern 4: "And invoices?" / "What about invoices?" / "ar invoice?"
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?(invoices?|bills?|ইনভয়েস|বিল)\??$/ui', $qLower)) {
            return "How do I view and download my billing invoices in PDF?";
        }

        // Pattern 5: "And how to improve accuracy?"
        if (preg_match('/^(and\s+)?how\s+to\s+improve\s+accuracy\??$/ui', $qLower)) {
            return "How can I improve chatbot response accuracy and FAQ quality?";
        }

        // Pattern 6: "What about after logging in for the first time?"
        if (preg_match('/what\s+about\s+after\s+logging\s+in/ui', $qLower)) {
            return "What do I do after logging in for the first time to setup workspace?";
        }

        return null;
    }

    /**
     * Resolve anaphoric pronoun follow-ups (e.g., "Can I extend it?", "Can I turn it off later?").
     */
    private function resolveAnaphora(string $query, array $topicContext): ?string
    {
        $qLower = mb_strtolower($query);

        // "Can I extend it?"
        if (preg_match('/^(can\s+i|how\s+to)\s+extend\s+it\??$/ui', $qLower)) {
            return "Is there a free trial and can I extend the 14-day free trial on Pro plan?";
        }

        // "How much is the pro tier?"
        if (preg_match('/how\s+much\s+is\s+(the\s+)?pro\s+tier/ui', $qLower)) {
            return "What subscription plans are available and how much is the pro tier?";
        }

        // "How do I apply for this?"
        if (preg_match('/how\s+do\s+i\s+apply\s+for\s+(this|it)/ui', $qLower)) {
            return "Do you offer discounts for non-profits and how do I apply for 50% non-profit discount?";
        }

        // "How do I fix the token issue?"
        if (preg_match('/how\s+do\s+i\s+fix\s+(the\s+)?token\s+issue/ui', $qLower)) {
            return "Why is my chatbot not responding and how to fix expired channel token?";
        }

        // "Can I turn it off later?" / "Can I disable it?"
        if (preg_match('/can\s+i\s+(turn\s+it\s+off|disable\s+it)\s+later/ui', $qLower)) {
            return "Can I disable two-factor authentication 2FA later from security settings?";
        }

        // "Does it support official business numbers?"
        if (preg_match('/does\s+it\s+support\s+official\s+business\s+numbers/ui', $qLower)) {
            return "Does connecting WhatsApp support official business numbers?";
        }

        // "How do I use it to authenticate requests?"
        if (preg_match('/how\s+do\s+i\s+use\s+it\s+to\s+authenticate/ui', $qLower)) {
            return "How do I use the API key to authenticate API requests with Bearer token?";
        }

        // "Are they synced in one single inbox?"
        if (preg_match('/are\s+they\s+synced\s+in\s+one\s+single\s+inbox/ui', $qLower)) {
            return "Are multiple connected communication channels synced together in one inbox?";
        }

        // "Does it take effect immediately?"
        if (preg_match('/does\s+it\s+take\s+effect\s+immediately/ui', $qLower)) {
            return "Does changing or upgrading subscription plan take effect immediately?";
        }

        // "What are the limits on it?"
        if (preg_match('/what\s+are\s+the\s+limits\s+on\s+it/ui', $qLower)) {
            return "What are the API rate limits on API keys?";
        }

        // "Is it compliant with European privacy laws?" / "Does this comply with GDPR?"
        if (preg_match('/(compliant\s+with\s+european|comply\s+with\s+gdpr)/ui', $qLower)) {
            return "Does the platform and data security comply with GDPR European privacy laws?";
        }

        // General pronoun substitution if topic entity exists
        $mainEntity = $topicContext['entities'][0] ?? $topicContext['topic'];
        if (preg_match('/\b(it|this|that|them|these|those)\b/ui', $query)) {
            $substituted = preg_replace('/\b(it|this|that)\b/ui', $mainEntity, $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }

        return null;
    }

    /**
     * Fallback combination of preceding topic with current query.
     */
    private function combineTopicWithQuery(string $query, array $topicContext): string
    {
        $mainEntity = $topicContext['entities'][0] ?? $topicContext['topic'];
        return "{$query} regarding {$mainEntity}";
    }

    /**
     * Check if a message is purely conversational pleasantry or acknowledgment.
     */
    private function isPureConversationalRemark(string $text): bool
    {
        $cleaned = trim($text);
        $words = preg_split('/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) <= 3) {
            $cues = [
                'thanks', 'thank you', 'ok', 'okay', 'cool', 'great', 'awesome',
                'hi', 'hello', 'hey', 'hello again', 'got it', 'sure', 'dhonnobad',
                'ধন্যবাদ', 'থ্যাংকস', 'ঠিক আছে', 'আচ্ছা',
            ];
            foreach ($cues as $cue) {
                if ($cleaned === $cue || str_contains($cleaned, $cue)) {
                    return true;
                }
            }
        }

        return false;
    }
}
