<?php

declare(strict_types=1);

namespace App\AI\Agents;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

class KnowledgeSupportAgent implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;

    public function __construct(
        public readonly ?Conversation $conversation = null,
        public readonly ?Collection $retrievedKnowledge = null,
        public readonly ?string $memoryContext = null,
        public readonly ?string $businessContext = null,
        public readonly mixed $retrievalTool = null,
    ) {}

    public function instructions(): Stringable|string
    {
        $businessSection = "";
        if (!empty($this->businessContext)) {
            $businessSection = "\n\n[Layer 3: Live Business Data / Authoritative Source of Truth]\n" . $this->businessContext . "\n";
        }

        $contextSection = "[Knowledge Base]\nNo documents retrieved.";
        if ($this->retrievedKnowledge && $this->retrievedKnowledge->isNotEmpty()) {
            $docs = [];
            $topHits = $this->retrievedKnowledge->take(3);
            foreach ($topHits as $idx => $hit) {
                $n = $idx + 1;
                $q = trim((string) ($hit->faq?->question ?? 'N/A'));
                $a = trim((string) ($hit->faq?->answer ?? 'N/A'));
                $docs[] = "[Doc {$n}]\nQ: {$q}\nA: {$a}";
            }
            $contextSection = "[Layer 1: Official Knowledge Base Documents]\nRetrieved Knowledge Base Documents:\n" . implode("\n\n", $docs);
        }

        $memorySection = "";
        if (!empty($this->memoryContext)) {
            $memorySection = "<MEMORY_CONTEXT>\n[Layer 2: Customer Conversation Graph Memory (Historical Preferences)]\nCustomer Preferences:\n" . $this->memoryContext . "\n</MEMORY_CONTEXT>\n";
        }

        return <<<PROMPT
<ROLE>
You are a professional Enterprise Customer Support AI Assistant. Your goal is to assist customers accurately, politely, and concisely based ONLY on the provided context.
</ROLE>

<CONTEXT_HIERARCHY>
Context Hierarchy & Conflict Resolution:
1. Live Business Data (Layer 3): Absolute source of truth for live order status, shipment tracking, and customer account records. Overrides past conversational memory.
2. Official Knowledge Base Documents: Highest authority for company policies, rules, and procedures.
3. Customer Conversation Graph Memory: Grounding for customer preferences without overriding live data.
</CONTEXT_HIERARCHY>

<RULES>
1. Grounding & Verification (Authority & Hierarchy):
   - Treat supplied Live Business Data (Layer 3) and Knowledge Base Documents (Layer 1) as the sole factual authority according to the context hierarchy.
   - Ground all company-specific and policy assertions strictly on relevant provided docs. Disregard irrelevant docs.
2. Negative Boundary Constraints:
   - NEVER invent, extrapolate, or assume branch/showroom availability, physical addresses, replacement windows, deadlines, fees, contact details (including phone numbers, hotlines, email addresses, support handles, or URLs), or other business-policy specifics absent from the supplied evidence.
   - Strictly distinguish an explicitly stated policy from an unknown or unverified policy.
3. Partial Grounding & Calibrated Uncertainty:
   - When a question has multiple parts or asks for specific details: answer the supported part clearly using the evidence, and explicitly acknowledge any unsupported sub-question by stating that the supplied information does not confirm it.
   - When showroom/branch exchange or physical visit availability is not supported by the retrieved evidence, clearly state that the policy does not confirm it.
   - For unsupported details, you may politely offer help from our support team or human specialist, but you must NEVER invent, fabricate, or append phone numbers, hotlines, or email addresses (refer to the 'support team' generally without contact details unless such details are explicitly present in the supplied evidence).
   - Avoid unnecessary abstention: when the evidence directly answers the question, answer confidently and concisely.
4. Live Orders: When present in Layer 3, provide accurate, reassuring status and tracking details.
5. Language & Tone:
   - Match the customer's language style naturally (Bangla, Banglish, English, or Code-Mixed).
   - Conversational fluency or informal phrasing must NEVER be treated as permission to invent or assume ungrounded facts. Maintain a warm, professional, and empathetic tone.
6. Conciseness & Completeness: Provide complete answers in 2-3 friendly sentences or bullet points.
7. Output Format: You MUST output a valid JSON object with the following schema:
{
  "answer": "Direct grounded answer to the customer query.",
  "proposed_follow_up": "Optional polite follow-up question (e.g. 'আপনি কি এটি নিতে চাচ্ছেন?') or null if no follow-up is appropriate.",
  "follow_up_type": "purchase_interest | product_variant | delivery | null"
}
Only propose a follow-up when relevant to purchase, variant selection, or delivery. For general FAQs (store hours, policies, return rules, complaints), set proposed_follow_up and follow_up_type to null. Do NOT include any text, reasoning, or formatting outside this JSON structure.
</RULES>

<EXAMPLES>
Example 1 (Product Price / Inquiry):
User: "Royal Silk Panjabiটার দাম কত?"
AI: {
  "answer": "আমাদের Royal Silk Panjabi ৩,৫০০ টাকা। বর্তমানে এটি স্টকে রয়েছে।",
  "proposed_follow_up": "আপনি কি এটি অর্ডার করতে চাচ্ছেন?",
  "follow_up_type": "purchase_interest"
}

Example 2 (General FAQ / Hours):
User: "দোকান কয়টায় বন্ধ হয়?"
AI: {
  "answer": "আমাদের শোরুম প্রতিদিন সকাল ১০টা থেকে রাত ৮টা পর্যন্ত খোলা থাকে।",
  "proposed_follow_up": null,
  "follow_up_type": null
}

Example 3 (Live Order Status):
User (Banglish): "order kobe pabo?"
AI: {
  "answer": "Apnar order ti process hocche, khub taratari peye jaben! Amra apnake track korar jonno update janiye dibo.",
  "proposed_follow_up": null,
  "follow_up_type": null
}

Example 4 (Partial Grounding / Missing Showroom in Banglish):
Evidence: Doc mentions online size exchange is allowed if product is unused and tags intact, but says nothing about a Banani showroom.
User (Banglish): "size na mille ki direct Banani showroom e giye exchange kora jabe?"
AI: {
  "answer": "Product intact thakle size er jonno online e exchange request kora jabe. Tobe Banani showroom e direct exchange kora jabe kina ta amader policy te ullekh nei; ei bishoye nishchit hote amader support team er shathe jogajog korte paren.",
  "proposed_follow_up": null,
  "follow_up_type": null
}

Example 5 (Partial Grounding / Missing Replacement Window in Code-Mixed):
Evidence: Doc mentions item return/exchange criteria, but does not state a replacement window or number of days.
User (Code-Mixed): "Size swap er replacement window koto din?"
AI: {
  "answer": "Sizing issue thakle item exchange kora jabe. Tobe replacement window ba koto diner moddhe exchange korte hobe ta amader tothye ullekh nei; apnake help korte ami ki support agent er sathe connect kore dibo?",
  "proposed_follow_up": null,
  "follow_up_type": null
}

Example 6 (Unsupported Physical Location in Bangla):
Evidence: Doc describes return processing via delivery courier, but does not mention outlet return.
User (Bangla): "আমি কি সরাসরি উত্তরা আউটলেটে গিয়ে প্রোডাক্ট রিটার্ন করতে পারব?"
AI: {
  "answer": "আমাদের রিটার্ন কুরিয়ার সার্ভিসের মাধ্যমে সম্পন্ন করা হয়। তবে উত্তরা আউটলেটে সরাসরি রিটার্ন গ্রহণ করা হয় কি না সে সম্পর্কিত তথ্য উল্লেখ নেই; বিস্তারিত জানতে আমাদের সাপোর্ট এজেন্টের সাথে যোগাযোগ করতে পারেন।",
  "proposed_follow_up": null,
  "follow_up_type": null
}
</EXAMPLES>

<BUSINESS_DATA>
{$businessSection}
</BUSINESS_DATA>

<KNOWLEDGE_BASE>
{$contextSection}
</KNOWLEDGE_BASE>

{$memorySection}
PROMPT;
    }

    public function maxTokens(): int
    {
        return (int) config('ai.max_tokens', 320);
    }

    public function providerOptions(Lab|string $provider): array
    {
        return [
            'max_tokens' => $this->maxTokens(),
        ];
    }

    public function temperature(): float
    {
        return 0.2;
    }

    public function messages(): iterable
    {
        if ($this->conversation === null) {
            return [];
        }

        $limit = (int) config('ai.memory.max_messages', 10);
        $maxChars = (int) config('ai.memory.max_message_chars', 1000);

        $rawMessages = $this->conversation->messages()
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        // If the last message is inbound (the query currently being prompted), omit it from history
        // because Laravel Ai Promptable automatically appends the current query as a UserMessage.
        if ($rawMessages->isNotEmpty() && $rawMessages->last()->direction === 'inbound') {
            $rawMessages->pop();
        }

        $aiMessages = [];
        foreach ($rawMessages as $msg) {
            $body = trim((string) $msg->body);
            if ($body === '') {
                continue;
            }

            if ($maxChars > 0 && mb_strlen($body) > $maxChars) {
                $body = mb_substr($body, 0, $maxChars) . '... [truncated]';
            }

            if ($msg->direction === 'inbound') {
                $aiMessages[] = new UserMessage($body);
            } else {
                $aiMessages[] = new AssistantMessage($body);
            }
        }

        return $aiMessages;
    }

    public function tools(): iterable
    {
        if ($this->retrievalTool !== null) {
            return [$this->retrievalTool];
        }

        return [];
    }

    public const BN_TO_EN_DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    /**
     * Deterministically sanitize unsupported contact details (phones, hotlines, emails)
     * fabricated by the LLM, while preserving legitimate evidence-backed contacts,
     * prices, dates, durations, percentages, and order numbers.
     */
    public static function sanitizeUnsupportedContactDetails(
        string $content,
        ?iterable $retrievedKnowledge = null,
        ?string $businessContext = null,
    ): string {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return $content;
        }

        // Handle JSON responses (schema format)
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded) && isset($decoded['answer'])) {
            $decoded['answer'] = self::sanitizeText((string) $decoded['answer'], $retrievedKnowledge, $businessContext);
            if (!empty($decoded['proposed_follow_up'])) {
                $sanitizedFollowUp = self::sanitizeText((string) $decoded['proposed_follow_up'], $retrievedKnowledge, $businessContext);
                $cleanedFollowUp = trim((string) preg_replace('/[[:punct:]\s\p{P}]+/u', '', $sanitizedFollowUp));
                if ($cleanedFollowUp === '') {
                    $decoded['proposed_follow_up'] = null;
                    if (array_key_exists('follow_up_type', $decoded)) {
                        $decoded['follow_up_type'] = null;
                    }
                } else {
                    $decoded['proposed_follow_up'] = $sanitizedFollowUp;
                }
            }
            return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return self::sanitizeText($content, $retrievedKnowledge, $businessContext);
    }

    /**
     * Canonicalize Bangladesh and international phone numbers into a consistent digit representation.
     * Maps Bengali digits to ASCII, strips formatting, and normalizes national/international BD formats.
     */
    private static function canonicalizePhone(string $rawPhone): ?string
    {
        $norm = strtr($rawPhone, self::BN_TO_EN_DIGITS);
        $digits = preg_replace('/[^\d]/', '', $norm);

        if (strlen($digits) < 7) {
            return null; // Ignore short numbers like prices, dates, or item codes
        }

        // Bangladesh standard mobile (013..019) or IPTSP / hotline (096):
        // 13 digits starting with 880: e.g. 8801712345678 or 8809612345678
        if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
            $sub = substr($digits, 3);
            if (preg_match('/^(?:1[3-9]|96)\d{8}$/', $sub)) {
                return '0' . $sub;
            }
        }

        // 11 digits starting with 01[3-9] or 096:
        if (strlen($digits) === 11 && preg_match('/^0(?:1[3-9]|96)\d{8}$/', $digits)) {
            return $digits;
        }

        // 10 digits starting with 1[3-9] or 96 (leading zero omitted):
        if (strlen($digits) === 10 && preg_match('/^(?:1[3-9]|96)\d{8}$/', $digits)) {
            return '0' . $digits;
        }

        // Other Bangladesh numbers with 880 prefix (e.g. landline +88029876543):
        if (str_starts_with($digits, '880') && strlen($digits) >= 10) {
            return '0' . substr($digits, 3);
        }

        return $digits;
    }

    /**
     * Remove contact clause from text, preserving adjacent parenthetical content.
     */
    private static function removeContactClause(string $text, string $clausePattern): string
    {
        $parenPattern = '/(?<open>[\(\[])(?<before>[^\(\)\[\]]*?)' . $clausePattern . '(?<after>[^\(\)\[\]]*?)(?<close>[\)\]])/ui';

        $maxIterations = 10;
        while ($maxIterations-- > 0 && preg_match($parenPattern, $text, $matches)) {
            $fullMatch = $matches[0];
            $open = $matches['open'];
            $before = $matches['before'];
            $after = $matches['after'];
            $close = $matches['close'];

            $trimmedBefore = trim($before);
            $trimmedAfter = trim($after, " \t\n\r\0\x0B.,!?।");

            if ($trimmedBefore === '' && $trimmedAfter === '') {
                // Contact is sole content of parenthetical: remove entire parenthetical and its leading whitespace
                $trailingPunct = '';
                if (preg_match('/([.,!?।]+)\s*$/u', $after, $punctMatch)) {
                    $trailingPunct = $punctMatch[1];
                }
                $cleanParenPattern = '/\s*' . preg_quote($fullMatch, '/') . '/u';
                $text = (string) preg_replace($cleanParenPattern, $trailingPunct, $text, 1);
            } else {
                // Adjacent content exists: preserve parenthetical and clean surrounding delimiters
                $cleanBefore = preg_replace('/[,\s;\-–—|এবংand]+$/u', '', $before);
                $cleanAfter = preg_replace('/^[,\s;\-–—|এবংand]+/u', '', $after);

                if ($cleanBefore !== '' && $cleanAfter !== '') {
                    $replacement = $open . $cleanBefore . ', ' . $cleanAfter . $close;
                } elseif ($cleanBefore !== '') {
                    $replacement = $open . $cleanBefore . $close;
                } elseif ($cleanAfter !== '') {
                    $replacement = $open . $cleanAfter . $close;
                } else {
                    $replacement = '';
                }
                $cleanParenPattern = '/' . preg_quote($fullMatch, '/') . '/u';
                $text = (string) preg_replace($cleanParenPattern, $replacement, $text, 1);
            }
        }

        // Remove standalone occurrences not inside parentheses/brackets
        $standalonePattern = '/\s*[:\s\-]*\s*' . $clausePattern . '/ui';
        $text = (string) preg_replace($standalonePattern, '', $text);

        return $text;
    }

    /**
     * Sanitize plain text string against provided evidence.
     */
    private static function sanitizeText(
        string $text,
        ?iterable $retrievedKnowledge = null,
        ?string $businessContext = null,
    ): string {
        $evidenceText = (string) $businessContext;
        if ($retrievedKnowledge !== null) {
            foreach ($retrievedKnowledge as $hit) {
                if (is_object($hit)) {
                    $q = $hit->faq?->question ?? $hit->question ?? '';
                    $a = $hit->faq?->answer ?? $hit->answer ?? '';
                    $evidenceText .= ' ' . $q . ' ' . $a;
                } elseif (is_array($hit)) {
                    $evidenceText .= ' ' . ($hit['question'] ?? '') . ' ' . ($hit['answer'] ?? '');
                }
            }
        }

        $normEvidence = strtr($evidenceText, self::BN_TO_EN_DIGITS);

        // Match phone numbers / hotlines: BD mobile (013..019), hotline/IPTSP (096.. or +880-96..), standard intl numbers
        $phoneRegex = '/(?<![\p{L}\p{N}])(?<![\p{L}\p{N}][\-_])(?:\+?880[\s\-]?)?(?:0?1[3-9]|0?96)(?:[\s\-]?\d){8}(?![\-_]?\d)|(?<![\p{L}\p{N}])(?<![\p{L}\p{N}][\-_])\+\d{1,3}(?:[\s\-]?\d){7,12}(?![\-_]?\d)/u';

        // Extract discrete supported phone numbers from evidence
        preg_match_all($phoneRegex, $normEvidence, $evidencePhoneMatches);
        $supportedCanonicalPhones = [];
        foreach ($evidencePhoneMatches[0] ?? [] as $evPhone) {
            $canon = self::canonicalizePhone($evPhone);
            if ($canon !== null) {
                $supportedCanonicalPhones[$canon] = true;
            }
        }

        $normText = strtr($text, self::BN_TO_EN_DIGITS);
        preg_match_all($phoneRegex, $normText, $phoneMatches);

        $unsupportedPhones = [];
        foreach ($phoneMatches[0] ?? [] as $rawPhone) {
            $canon = self::canonicalizePhone($rawPhone);
            if ($canon === null) {
                continue; // Preserve numbers shorter than 7 digits
            }
            if (!isset($supportedCanonicalPhones[$canon])) {
                $unsupportedPhones[] = $rawPhone;
            }
        }

        // Match emails
        $emailRegex = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/u';
        preg_match_all($emailRegex, $text, $emailMatches);
        $unsupportedEmails = [];
        foreach ($emailMatches[0] ?? [] as $rawEmail) {
            if (!str_contains(mb_strtolower($evidenceText), mb_strtolower($rawEmail))) {
                $unsupportedEmails[] = $rawEmail;
            }
        }

        if (empty($unsupportedPhones) && empty($unsupportedEmails)) {
            return $text;
        }

        $result = $text;

        $phoneLabels = '(?:hotline|helpline|phone|mobile|telephone|tel|call(?:\s*us)?|contact(?:\s*us)?|number|no|হটলাইন|হেল্পলাইন|ফোন\s*করুন|ফোন|মোবাইল|যোগাযোগ\s*করুন|যোগাযোগের\s*নম্বর|যোগাযোগ|কল\s*করুন|কল\s*দিন|কল|নম্বর|নাম্বার)';
        foreach ($unsupportedPhones as $phone) {
            $digitPattern = '';
            for ($i = 0; $i < strlen($phone); $i++) {
                $ch = $phone[$i];
                if (is_numeric($ch)) {
                    $bnChar = array_search($ch, self::BN_TO_EN_DIGITS, true);
                    $digitPattern .= '[' . $ch . ($bnChar !== false ? $bnChar : '') . ']';
                } elseif (in_array($ch, ['+', '-', ' '])) {
                    $digitPattern .= '[\s\-\+]?';
                } else {
                    $digitPattern .= preg_quote($ch, '/');
                }
            }

            $clausePattern = '(?:(?<![\p{L}\p{N}])' . $phoneLabels . '[:\s\-]*)?(?<![\p{L}\p{N}])(?<![\p{L}\p{N}][\-_])' . $digitPattern . '(?![\-_]?\d)(?:\s*(?:নম্বরে|নাম্বারে|number|no))?';
            $result = self::removeContactClause($result, $clausePattern);
        }

        $emailLabels = '(?:email|mail|e-mail|ইমেইল|মেইল)';
        foreach ($unsupportedEmails as $email) {
            $emailPattern = preg_quote($email, '/');
            $clausePattern = '(?:(?<![\p{L}\p{N}])' . $emailLabels . '[:\s\-]*)?' . $emailPattern;
            $result = self::removeContactClause($result, $clausePattern);
        }

        // Clean up empty parentheses/brackets that may have been left behind
        $result = preg_replace('/\s*[\(\[]\s*[,\s;\-]*\s*[\)\]]/u', '', $result);

        // Clean up punctuation spacing and redundant punctuation
        $result = preg_replace('/[,;\-–—]\s*([.,!?।\)\]])/u', '$1', $result);
        $result = preg_replace('/\s+([.,!?।])/u', '$1', $result);
        $result = preg_replace('/([.,!?।])\s*([.,!?।])/u', '$1', $result);
        $result = preg_replace('/[ \t]{2,}/u', ' ', $result);
        $result = preg_replace('/[,;\-–—]\s*$/u', '', $result);

        return trim($result);
    }
}
