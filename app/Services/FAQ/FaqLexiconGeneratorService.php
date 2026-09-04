<?php

declare(strict_types=1);

namespace App\Services\FAQ;

use App\Models\FAQ;
use App\Models\FaqLexicon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaqLexiconGeneratorService
{
    public function __construct(
        private readonly FaqLexiconValidator $validator = new FaqLexiconValidator(),
    ) {}

    /**
     * Generate, validate, and persist domain lexicon for an FAQ.
     */
    public function generateAndStore(FAQ $faq): ?FaqLexicon
    {
        $rawOutput = $this->callLlmForLexicon($faq);
        $validated = $this->validator->validateAndSanitize($rawOutput);

        if (!$validated['is_valid']) {
            Log::warning('[FaqLexiconGeneratorService] Lexicon validation yielded no valid terms', [
                'faq_id' => $faq->id,
                'question' => $faq->question,
            ]);
            return null;
        }

        /** @var FaqLexicon $lexicon */
        $lexicon = FaqLexicon::updateOrCreate(
            ['faq_id' => $faq->id],
            [
                'workspace_id'    => $faq->workspace_id,
                'domain'          => $validated['domain'],
                'intent'          => $validated['intent'],
                'canonical_terms' => $validated['canonical_terms'],
                'bangla_terms'    => $validated['bangla_terms'],
                'commerce_terms'  => $validated['commerce_terms'],
                'generated_by'    => config('ai.default', 'deepseek'),
                'is_validated'    => true,
            ]
        );

        Log::info('[FaqLexiconGeneratorService] Successfully generated domain lexicon', [
            'faq_id'        => $faq->id,
            'domain'        => $lexicon->domain,
            'intent'        => $lexicon->intent,
            'total_terms'   => count($lexicon->allTerms()),
        ]);

        return $lexicon;
    }

    /**
     * Call LLM to extract commerce vocabulary and variations.
     *
     * @return array<string, mixed>
     */
    private function callLlmForLexicon(FAQ $faq): array
    {
        $provider = config('ai.default', 'deepseek');
        $apiKey = config("ai.providers.{$provider}.api_key", config('ai.providers.deepseek.api_key'));
        $baseUrl = rtrim((string) config("ai.providers.{$provider}.base_url", 'https://api.deepseek.com/v1'), '/');
        $model = config('ai.default_model', 'deepseek-chat');

        $domainsList = implode(', ', CommerceOntology::ALL_DOMAINS);

        $systemPrompt = <<<PROMPT
You are an expert E-Commerce & F-Commerce Search Lexicon Generator.
Your task is to analyze a support FAQ and extract canonical search keywords, colloquial Bengali/Banglish phrases, and social commerce terms for retrieval expansion.

Allowed Domain Categories:
[{$domainsList}]

Strict Rules:
1. Return ONLY a valid JSON object matching the schema below.
2. Terms MUST be short search keywords/aliases (1 to 5 words max).
3. Do NOT include full sentences, policies, or explanations.
4. "bangla_terms" MUST include authentic Bangla script phrases and common Banglish/transliterated variations used by online shoppers.
5. "commerce_terms" MUST include relevant E-commerce/F-commerce terms (e.g., parcel, courier, COD, bKash, delivery, checkout, cart, Messenger order).

JSON Schema:
{
  "domain": "<One of the Allowed Domains>",
  "intent": "<snake_case_intent_identifier>",
  "canonical_terms": ["term 1", "term 2", "term 3"],
  "bangla_terms": ["বাংলা ১", "বাংলা ২", "banglish phrase 1"],
  "commerce_terms": ["commerce keyword 1", "commerce keyword 2"]
}
PROMPT;

        $userPrompt = "FAQ Question: {$faq->question}\nFAQ Answer: {$faq->answer}";

        try {
            $response = Http::timeout(10)
                ->withToken($apiKey)
                ->post("{$baseUrl}/chat/completions", [
                    'model'       => $model,
                    'messages'    => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.1,
                    'max_tokens'  => 350,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content') ?? '';
                $cleanJson = $this->extractJsonString($content);
                $decoded = json_decode($cleanJson, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            Log::warning('[FaqLexiconGeneratorService] LLM response parsing failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[FaqLexiconGeneratorService] LLM call failed, generating deterministic fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        // Deterministic fallback if remote LLM call fails
        return $this->generateDeterministicFallback($faq);
    }

    /**
     * Clean JSON markdown fences from LLM output.
     */
    private function extractJsonString(string $content): string
    {
        $trimmed = trim($content);
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $trimmed, $matches)) {
            return $matches[1];
        }
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            return $trimmed;
        }

        return $trimmed;
    }

    /**
     * Generate rich concept-based deterministic fallback lexicon when LLM is offline.
     *
     * @return array<string, mixed>
     */
    private function generateDeterministicFallback(FAQ $faq): array
    {
        $domain = CommerceOntology::normalizeDomain($faq->question);
        $qLower = mb_strtolower($faq->question . ' ' . $faq->answer);

        $canonical = [];
        $bangla = [];
        $commerce = [];

        // 1. Account & Security
        if (str_contains($qLower, 'account') || str_contains($qLower, 'sign up') || str_contains($qLower, 'register')) {
            $canonical[] = 'create account';
            $canonical[] = 'sign up registration';
            $bangla[] = 'নতুন অ্যাকাউন্ট তৈরি';
            $bangla[] = 'notun akaunt khulbo';
            $commerce[] = 'customer registration';
        }
        if (str_contains($qLower, 'two-factor') || str_contains($qLower, '2fa') || str_contains($qLower, 'twofactor') || str_contains($qLower, 'authenticat')) {
            $canonical[] = 'two factor authentication';
            $canonical[] = '2FA security setup';
            $bangla[] = 'টু-স্টেপ ভেরিফিকেশন';
            $bangla[] = 'ভেরিফিকেশন অন';
            $bangla[] = '2-step verification';
            $commerce[] = 'account security code';
        }
        if (str_contains($qLower, 'password') || str_contains($qLower, 'reset')) {
            $canonical[] = 'reset password';
            $canonical[] = 'forgot password recovery';
            $bangla[] = 'পাসওয়ার্ড রিসেট';
            $bangla[] = 'password vule gechi';
            $commerce[] = 'account access recovery';
        }

        // 2. Billing & Invoices
        if (str_contains($qLower, 'payment') || str_contains($qLower, 'card') || str_contains($qLower, 'paypal')) {
            $canonical[] = 'update payment method';
            $canonical[] = 'credit card update';
            $bangla[] = 'পেমেন্ট মেথড পরিবর্তন';
            $bangla[] = 'card change korbo';
            $commerce[] = 'checkout payment details';
        }
        if (str_contains($qLower, 'invoice') || str_contains($qLower, 'receipt') || str_contains($qLower, 'billing')) {
            $canonical[] = 'view download invoices';
            $canonical[] = 'billing receipt history';
            $bangla[] = 'ইনভয়েস ডাউনলোড';
            $bangla[] = 'purono invoice roshid';
            $commerce[] = 'purchase tax invoice';
        }

        // 3. Plans, Pricing & Discounts
        if (str_contains($qLower, 'plan') || str_contains($qLower, 'upgrade') || str_contains($qLower, 'subscription')) {
            $canonical[] = 'change subscription plan';
            $canonical[] = 'plan upgrade downgrade';
            $bangla[] = 'প্ল্যান পরিবর্তন';
            $bangla[] = 'plan switch upgrade';
            $commerce[] = 'annual subscription pricing';
        }
        if (str_contains($qLower, 'discount') || str_contains($qLower, 'non-profit') || str_contains($qLower, 'charity')) {
            $canonical[] = 'non-profit discount pricing';
            $bangla[] = 'ডিসকাউন্ট অফার';
            $bangla[] = 'discount offer';
            $commerce[] = 'special promotion discount';
        }
        if (str_contains($qLower, 'trial') || str_contains($qLower, 'free')) {
            $canonical[] = 'free trial period';
            $bangla[] = 'ফ্রি ট্রায়াল';
            $bangla[] = 'free trial pro plan';
            $commerce[] = '14-day trial no card';
        }

        // 4. Channels & Integrations
        if (str_contains($qLower, 'whatsapp') || str_contains($qLower, 'telegram') || str_contains($qLower, 'channel')) {
            $canonical[] = 'connect messaging channels';
            $canonical[] = 'multi-channel integration';
            $bangla[] = 'হোয়াটসঅ্যাপ কানেক্ট';
            $bangla[] = 'whatsapp telegram connect';
            $commerce[] = 'F-Commerce social messaging';
        }
        if (str_contains($qLower, 'api') || str_contains($qLower, 'webhook') || str_contains($qLower, 'token')) {
            $canonical[] = 'API key token authentication';
            $canonical[] = 'developer rate limits';
            $commerce[] = 'REST API integration';
        }

        // 5. Troubleshooting & Security
        if (str_contains($qLower, 'chatbot') || str_contains($qLower, 'responding') || str_contains($qLower, 'error') || str_contains($qLower, 'delivered')) {
            $canonical[] = 'chatbot not responding';
            $canonical[] = 'message delivery troubleshooting';
            $bangla[] = 'মেসেজ যাচ্ছে না';
            $bangla[] = 'bot silent reply dicche na';
            $commerce[] = 'automated agent issue';
        }
        if (str_contains($qLower, 'encrypt') || str_contains($qLower, 'gdpr') || str_contains($qLower, 'security')) {
            $canonical[] = 'data encryption security';
            $canonical[] = 'GDPR privacy compliance';
            $bangla[] = 'ডেটা নিরাপত্তা এনক্রিপশন';
            $bangla[] = 'ডেটা এনক্রিপ্ট';
            $commerce[] = 'AES-256 TLS protection';
        }

        // Default words if empty
        if (empty($canonical)) {
            $cleanWords = array_filter(
                explode(' ', mb_strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $faq->question) ?? '')),
                fn ($w) => mb_strlen($w) >= 4
            );
            $canonical = array_values(array_slice($cleanWords, 0, 4));
        }

        return [
            'domain'          => $domain,
            'intent'          => 'support_faq_inquiry',
            'canonical_terms' => $canonical,
            'bangla_terms'    => $bangla,
            'commerce_terms'  => !empty($commerce) ? $commerce : [$domain],
        ];
    }
}
