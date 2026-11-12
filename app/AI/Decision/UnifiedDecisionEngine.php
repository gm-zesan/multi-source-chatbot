<?php

declare(strict_types=1);

namespace App\AI\Decision;

use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ToolCall;
use App\AI\LLM\LLMClient;
use App\AI\LLM\LLMRequest;
use App\AI\Orchestration\CustomerSupportService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Unified AI Decision Engine.
 *
 * Consolidates intent classification and tool planning into a single
 * authoritative decision-making pipeline. Produces structured Schema v1.0 JSON.
 */
class UnifiedDecisionEngine implements DecisionEngineInterface
{
    public function __construct(
        private readonly LLMClient $llmClient,
        private readonly ToolRegistry $toolRegistry,
        private readonly ?CustomerSupportService $customerSupportService = null,
    ) {}

    public function decide(DecisionRequest $request): DecisionResult
    {
        $tStart = microtime(true);
        $cleanQuery = trim($request->query);
        $css = $this->customerSupportService ?? app(CustomerSupportService::class);

        // ── 0. Preflight Fast-Path: Customer Workforce & Financial Security Barrier ──
        if ($request->isCustomer && $css->hasInternalWorkforceOrFinancialIntent($cleanQuery)) {
            $latency = round((microtime(true) - $tStart) * 1000, 2);
            Log::info('[UnifiedDecisionEngine] Inbound customer workforce/financial query blocked pre-routing', [
                'workspace_id' => $request->workspaceId,
                'query'        => $cleanQuery,
            ]);

            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: DecisionResult::INTENT_ANALYTICS,
                responseMode: DecisionResult::MODE_SAFE_REFUSAL,
                toolCalls: [],
                clarification: null,
                directResponse: CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
                decisionMetadata: [
                    'layer'             => 'preflight_security_barrier',
                    'decision_ms'       => $latency,
                    'is_fallback'       => false,
                    'security_status'   => 'blocked_customer_financial',
                ]
            );
        }

        // ── 0.5 Preflight Fast-Path: Standalone Greetings & Pleasantries ──────────
        $greetingResult = $this->matchGreetingFastPath($cleanQuery);
        if ($greetingResult !== null) {
            $latency = round((microtime(true) - $tStart) * 1000, 2);
            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: DecisionResult::INTENT_CHAT,
                responseMode: DecisionResult::MODE_DIRECT_RESPONSE,
                toolCalls: [],
                clarification: null,
                directResponse: $greetingResult['response'],
                decisionMetadata: [
                    'layer'             => 'layer0_greeting_fastpath',
                    'decision_ms'       => $latency,
                    'is_fallback'       => false,
                    'security_status'   => 'allowed',
                ]
            );
        }

        // ── 0.6 Preflight Fast-Path: Pending Clarification Selection Resolution ──
        if ($request->conversation !== null && !empty($request->conversation->metadata['pending_clarification'])) {
            $pending = $request->conversation->metadata['pending_clarification'];
            $isExpired = isset($pending['expires_at']) && now()->toIso8601String() > $pending['expires_at'];

            if ($isExpired) {
                $metadata = $request->conversation->metadata;
                unset($metadata['pending_clarification']);
                $request->conversation->metadata = $metadata;
                if ($request->conversation->exists) {
                    $request->conversation->save();
                }
            } else {
                $options = $pending['options'] ?? [];
                $cleaned = mb_strtolower(preg_replace('/[^a-zA-Z0-9\p{Bengali}\s_]/u', '', $cleanQuery));

                foreach ($options as $index => $option) {
                    $numericChoice = (string) ($index + 1);
                    $optionIdLower = mb_strtolower($option['id'] ?? '');
                    $labelLower = mb_strtolower(preg_replace('/[^a-zA-Z0-9\p{Bengali}\s_]/u', '', $option['label'] ?? ''));

                    // Anti-tampering: match numeric choice, option ID, or label, never raw semantic_value
                    if ($cleaned === $numericChoice || $cleaned === $optionIdLower || $cleaned === $labelLower) {
                        $metadata = $request->conversation->metadata;
                        unset($metadata['pending_clarification']);
                        $request->conversation->metadata = $metadata;
                        if ($request->conversation->exists) {
                            $request->conversation->save();
                        }

                        $resolvedIntent = $option['semantic_value'] ?? DecisionResult::INTENT_ANALYTICS;
                        $latency = round((microtime(true) - $tStart) * 1000, 2);

                        return new DecisionResult(
                            schemaVersion: DecisionResult::SCHEMA_VERSION,
                            intent: DecisionResult::INTENT_ANALYTICS,
                            responseMode: DecisionResult::MODE_TOOL_REQUIRED,
                            toolCalls: [
                                new ToolCall(
                                    tool: 'sales_analytics',
                                    operation: 'query_sales',
                                    arguments: [
                                        'query'              => $pending['original_query'] ?? $cleanQuery,
                                        'intent'             => $resolvedIntent,
                                        'resolved_option_id' => $option['id'] ?? null,
                                    ]
                                )
                            ],
                            clarification: null,
                            directResponse: null,
                            decisionMetadata: [
                                'layer'                  => 'layer0_pending_clarification',
                                'decision_ms'            => $latency,
                                'is_fallback'            => false,
                                'security_status'        => 'allowed',
                                'clarification_resolved' => true,
                                'resolved_intent'        => $resolvedIntent,
                                'resolved_option_id'     => $option['id'] ?? null,
                            ]
                        );
                    }
                }

                $candidates = $pending['candidates'] ?? [];
                foreach ($candidates as $cand) {
                    $candName = mb_strtolower(trim($cand['name'] ?? ''));
                    $candNameClean = mb_strtolower(preg_replace('/[^a-zA-Z0-9\p{Bengali}\s_]/u', '', $candName));
                    if ($candNameClean !== '' && (str_contains($candNameClean, $cleaned) || str_contains($cleaned, $candNameClean) || $cleaned === $candNameClean)) {
                        $metadata = $request->conversation->metadata;
                        unset($metadata['pending_clarification']);
                        $request->conversation->metadata = $metadata;
                        if ($request->conversation->exists) {
                            $request->conversation->save();
                        }

                        $latency = round((microtime(true) - $tStart) * 1000, 2);

                        return new DecisionResult(
                            schemaVersion: DecisionResult::SCHEMA_VERSION,
                            intent: DecisionResult::INTENT_KNOWLEDGE,
                            responseMode: DecisionResult::MODE_TOOL_REQUIRED,
                            toolCalls: [
                                new ToolCall(
                                    tool: 'knowledge_intelligence',
                                    operation: 'search_knowledge',
                                    arguments: [
                                        'query' => $cand['name'],
                                    ]
                                )
                            ],
                            clarification: null,
                            directResponse: null,
                            decisionMetadata: [
                                'layer'                  => 'layer0_pending_clarification_candidate',
                                'decision_ms'            => $latency,
                                'is_fallback'            => false,
                                'security_status'        => 'allowed',
                                'clarification_resolved' => true,
                                'resolved_entity'        => $cand['name'],
                            ]
                        );
                    }
                }

                // If input did not match any clarification option or candidate, clear state because user entered a new query or invalid choice
                $metadata = $request->conversation->metadata;
                unset($metadata['pending_clarification']);
                $request->conversation->metadata = $metadata;
                if ($request->conversation->exists) {
                    $request->conversation->save();
                }
            }
        }

        // ── 1. Structured Semantic Decision Generation (Single LLM Call) ─────────
        $permittedToolNames = $this->toolRegistry->getPermittedToolNames(
            isCustomer: $request->isCustomer,
            user: $request->user,
            workspaceId: $request->workspaceId,
        );

        $systemPrompt = $this->buildSystemPrompt($permittedToolNames, $request->isCustomer);
        $userPrompt = $this->buildUserPrompt($request);

        $llmRequest = LLMRequest::fromPrompt(
            prompt: $userPrompt,
            systemPrompt: $systemPrompt,
            model: config('ai.default_model', 'deepseek-chat'),
            temperature: 0.0,
            maxTokens: 300,
        );
        $llmRequest->responseFormat = ['type' => 'json_object'];

        try {
            $response = $this->llmClient->generate($llmRequest);
            $parsed = $this->extractAndValidateJson($response->content);

            $latency = round((microtime(true) - $tStart) * 1000, 2);

            $toolCalls = [];
            foreach ($parsed['tool_calls'] ?? [] as $rawCall) {
                if (is_array($rawCall) && !empty($rawCall['tool'])) {
                    $toolName = (string) $rawCall['tool'];
                    // Only permit tools present in the caller's allowlist
                    if (in_array($toolName, $permittedToolNames, true)) {
                        $toolCalls[] = new ToolCall(
                            tool: $toolName,
                            operation: (string) ($rawCall['operation'] ?? 'default'),
                            arguments: is_array($rawCall['arguments'] ?? null) ? $rawCall['arguments'] : [],
                        );
                    }
                }
            }

            $normalizedIntent = strtoupper(trim((string) $parsed['intent']));
            $normalizedMode = strtoupper(trim((string) $parsed['response_mode']));

            // Customer Channel Post-Decision Hard Block: Zero Analytics Execution
            if ($request->isCustomer && $normalizedIntent === DecisionResult::INTENT_ANALYTICS) {
                Log::info('[UnifiedDecisionEngine] Customer request generated ANALYTICS intent; intercepted by Security Barrier', [
                    'workspace_id' => $request->workspaceId,
                    'query'        => $cleanQuery,
                ]);

                return new DecisionResult(
                    schemaVersion: DecisionResult::SCHEMA_VERSION,
                    intent: DecisionResult::INTENT_ANALYTICS,
                    responseMode: DecisionResult::MODE_SAFE_REFUSAL,
                    toolCalls: [],
                    clarification: null,
                    directResponse: CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
                    decisionMetadata: [
                        'layer'       => 'post_decision_security_barrier',
                        'decision_ms' => $latency,
                    ]
                );
            }

            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: $normalizedIntent,
                responseMode: $normalizedMode,
                toolCalls: $toolCalls,
                clarification: is_array($parsed['clarification'] ?? null) ? $parsed['clarification'] : null,
                directResponse: !empty($parsed['direct_response']) ? (string) $parsed['direct_response'] : null,
                decisionMetadata: [
                    'decision_ms' => $latency,
                    'provider'    => $response->provider ?? 'primary',
                    'model'       => $response->model ?? config('ai.default_model', 'deepseek-chat'),
                    'is_fallback' => false,
                ]
            );

        } catch (Throwable $e) {
            Log::warning('[UnifiedDecisionEngine] Semantic decision generation failed: ' . $e->getMessage(), [
                'query'        => $cleanQuery,
                'workspace_id' => $request->workspaceId,
            ]);

            return $this->resolveDeterministicFallback($cleanQuery, $request, $tStart);
        }
    }

    /**
     * Deterministic Fallback Strategy on LLM Provider Outage or Malformed Output.
     */
    protected function resolveDeterministicFallback(string $query, DecisionRequest $request, float $tStart): DecisionResult
    {
        $latency = round((microtime(true) - $tStart) * 1000, 2);
        $lower = mb_strtolower(trim($query));

        // Fallback Rule 1: Customer Channel attempting financial/workforce query is strictly blocked
        if ($request->isCustomer) {
            $css = $this->customerSupportService ?? app(CustomerSupportService::class);
            if ($css->hasInternalWorkforceOrFinancialIntent($query)) {
                return new DecisionResult(
                    schemaVersion: DecisionResult::SCHEMA_VERSION,
                    intent: DecisionResult::INTENT_ANALYTICS,
                    responseMode: DecisionResult::MODE_SAFE_REFUSAL,
                    toolCalls: [],
                    clarification: null,
                    directResponse: CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
                    decisionMetadata: ['layer' => 'fallback_customer_denial', 'decision_ms' => $latency, 'is_fallback' => true]
                );
            }
        }

        // Fallback Rule 1.5: Explicit spreadsheet dataset inquiries for authorized owner
        if (!$request->isCustomer && preg_match('/(?:spreadsheet|excel|স্প্রেডশিট|এক্সেল|csv|সিএসভি|xlsx|xls|dataset|ডেটাসেট|শীট|শিট|sheet|file|ফাইল)/ui', $lower)) {
            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: DecisionResult::INTENT_ANALYTICS,
                responseMode: DecisionResult::MODE_TOOL_REQUIRED,
                toolCalls: [
                    new ToolCall(
                        tool: 'legacy_excel_analytics',
                        operation: 'query_spreadsheet',
                        arguments: ['question' => $query]
                    ),
                ],
                clarification: null,
                directResponse: null,
                decisionMetadata: ['layer' => 'fallback_explicit_spreadsheet', 'decision_ms' => $latency, 'is_fallback' => true]
            );
        }

        // Fallback Rule 1.8: Multi-turn analytics continuation (e.g. pronoun references "ওটা কবে থেকে?", "আর Karim-এরটা?")
        $hasAnalyticsContext = (!empty($request->compactContext) && preg_match('/(?:বকেয়া|due|sales|বিক্রি|বিক্রয়|collection|কালেকশন|revenue|রেভিনিউ)/ui', $request->compactContext))
            || !empty($request->conversation?->metadata['active_file_id'])
            || !empty($request->conversation?->metadata['active_filename']);

        $hasExplicitProductOrPolicyTerm = preg_match('/(?:stock|স্টক|available|দাম|দর|মূল্য|price|dam\s*koto|dam\s*কত|delivery|ডেলিভারি|ডেলিভারী|shipping|courier|parcel|পার্সেল|পৌঁছাবে|পৌছাবে|warranty|ওয়ারেন্টি|গ্যারান্টি|guarantee|return|রিটার্ন|ফেরত|refund|discount|ডিসকাউন্ট|কুপন|coupon)/ui', $lower) === 1;

        if (!$request->isCustomer && $hasAnalyticsContext && !$hasExplicitProductOrPolicyTerm && preg_match('/(?:ওটা|সেটা|তার|এরটা|কার|আর\b|কবে|how\s+much|when|what\s+about|গতকাল|yesterday|গত\s*মাস|কত|কতো|ছিল)/ui', $lower)) {
            $hasSpreadsheet = \App\Models\SpreadsheetImportJob::where('workspace_id', $request->workspaceId)
                ->whereIn('status', ['completed', 'completed_with_errors'])
                ->exists();

            if ($hasSpreadsheet) {
                return new DecisionResult(
                    schemaVersion: DecisionResult::SCHEMA_VERSION,
                    intent: DecisionResult::INTENT_ANALYTICS,
                    responseMode: DecisionResult::MODE_TOOL_REQUIRED,
                    toolCalls: [
                        new ToolCall(
                            tool: 'legacy_excel_analytics',
                            operation: 'query_spreadsheet',
                            arguments: ['question' => $query]
                        ),
                    ],
                    clarification: null,
                    directResponse: null,
                    decisionMetadata: ['layer' => 'fallback_strict_spreadsheet_sales', 'decision_ms' => $latency, 'is_fallback' => true]
                );
            }

            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: DecisionResult::INTENT_ANALYTICS,
                responseMode: DecisionResult::MODE_TOOL_REQUIRED,
                toolCalls: [
                    new ToolCall(
                        tool: 'sales_analytics',
                        operation: 'get_sales_summary',
                        arguments: ['period' => 'today', 'query' => $query]
                    ),
                ],
                clarification: null,
                directResponse: null,
                decisionMetadata: ['layer' => 'fallback_multiturn_analytics', 'decision_ms' => $latency, 'is_fallback' => true]
            );
        }

        // Fallback Rule 2: Unambiguous, strict sales / revenue / salesperson patterns for authorized owner
        if (!$request->isCustomer && preg_match('/(?:আজকে\s+মোট|মোট\s+(?:বিক্রি|বিক্রয়|সেলস|sales)|total\s+sales?|today\'?s\s+(?:sales?|revenue|cash)|ajke\s+koto\s+sales?|ke\s+koto\s+taka|ke\s+koto|who\s+(?:sold|made)|kar\s+sales?|\b\w+\s+কত\s+sales?|(?:বিক্রি|বিক্রয়|সেলস|sales?)\s+কত|sales?\s+koto|(?:বিক্রি|বিক্রয়|সেলস|sales?)\s*(?:রিপোর্ট|হিসাব|পরিসংখ্যান|সামারি|সারসংক্ষেপ|summary|report)|cash\s*collection|cash\s*in|বকেয়া|due|baki|লাভ\s*বা\s*মার্জিন|revenue|salesperson|sales\s*rep|বেতন|salary|payroll)/ui', $lower)) {
            $hasSpreadsheet = \App\Models\SpreadsheetImportJob::where('workspace_id', $request->workspaceId)
                ->whereIn('status', ['completed', 'completed_with_errors'])
                ->exists();

            if ($hasSpreadsheet) {
                return new DecisionResult(
                    schemaVersion: DecisionResult::SCHEMA_VERSION,
                    intent: DecisionResult::INTENT_ANALYTICS,
                    responseMode: DecisionResult::MODE_TOOL_REQUIRED,
                    toolCalls: [
                        new ToolCall(
                            tool: 'legacy_excel_analytics',
                            operation: 'query_spreadsheet',
                            arguments: ['question' => $query]
                        ),
                    ],
                    clarification: null,
                    directResponse: null,
                    decisionMetadata: ['layer' => 'fallback_strict_spreadsheet_sales', 'decision_ms' => $latency, 'is_fallback' => true]
                );
            }

            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: DecisionResult::INTENT_ANALYTICS,
                responseMode: DecisionResult::MODE_TOOL_REQUIRED,
                toolCalls: [
                    new ToolCall(
                        tool: 'sales_analytics',
                        operation: 'get_sales_summary',
                        arguments: ['period' => 'today', 'query' => $query]
                    ),
                ],
                clarification: null,
                directResponse: null,
                decisionMetadata: ['layer' => 'fallback_strict_sales', 'decision_ms' => $latency, 'is_fallback' => true]
            );
        }

        // Fallback Rule 2.8: Personal customer preference & order memory inquiries -> CHAT
        if (preg_match('/(?:what\s+can\s+you\s+do|who\s+are\s+you|explain|what\s+is\s+an?\s+|ki\s+korte\s+paro|tumi\s+ke|apni\s+ke|thank\s+you|thanks|ধন্যবাদ|আমার\s*(?:সাইজ|size|আগের\s*অর্ডার|পছন্দ|ঠিকানা))/ui', $lower)) {
            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: DecisionResult::INTENT_CHAT,
                responseMode: DecisionResult::MODE_DIRECT_RESPONSE,
                toolCalls: [],
                clarification: null,
                directResponse: 'আমি আপনার শপের এআই অ্যাসিস্ট্যান্ট। পণ্য, অর্ডার, ডেলিভারি বা ব্যবসায়িক তথ্যে আপনাকে সাহায্য করতে পারি।',
                decisionMetadata: ['layer' => 'fallback_conversational_chat', 'decision_ms' => $latency, 'is_fallback' => true]
            );
        }

        // Fallback Rule 3: Completely Out-of-Domain (OOD) Requests
        if (preg_match('/\b(?:nuclear|reactor|fusion|space|rocket|astronomy|weather|climate|politics|president|election|recipe|cook|song|movie|aircraft|airplane|plane|flight|boeing|jet|world\s*cup|fifa|cricket|football|sports|python|coding|programming)\b/ui', $lower) || preg_match('/(?:আবহাওয়া|আবহাওয়া|রাজনীতি|নির্বাচন|ভোট|রেসিপি|গান|সিনেমা|মুভি|ফুটবল|ক্রিকেট|খেলাধুলা)/ui', $lower)) {
            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: DecisionResult::INTENT_OOD,
                responseMode: DecisionResult::MODE_OUT_OF_DOMAIN,
                toolCalls: [],
                clarification: null,
                directResponse: 'দুঃখিত, এই বিষয়টি আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়। আমি শুধুমাত্র আমাদের পণ্য ও সেবা সংক্রান্ত তথ্যে সাহায্য করতে পারি।',
                decisionMetadata: ['layer' => 'fallback_ood_refusal', 'decision_ms' => $latency, 'is_fallback' => true]
            );
        }

        // Fallback Rule 3.5: Product-specific availability or general FAQ inquiry -> KNOWLEDGE (Never analytics!)
        if (preg_match('/(?:stock|স্টক|available|দাম|দর|মূল্য|price|dam\s*koto|dam\s*কত|how\s*much|cost|কি\s*কি\s*প্রোডাক্ট|delivery|shipping|courier|parcel|পার্সেল|tracking|ট্র্যাকিং|kothay|কোথায়|ডেলিভারি|ডেলিভারী|কুরিয়ার|শিপিং|কত\s*দিন|কবে\s*পাব|পাবো|পৌঁছাবে|পৌছাবে|ফেরত|রিটার্ন|return|refund|policy|পলিসি|নিয়ম|সময়|how\s*long|when|charge|চার্জ|hours|store\s*hours|sla|guarantee|warranty|ওয়ারেন্টি|গ্যারান্টি|office|অফিস|ঠিকানা|address|location|where|branch|শাখা|password|reset|login|signup|signin|sign-in|account|forgot|auth|discount|ডিসকাউন্ট|কুপন|কোড|অফার|code|coupon|offer|promo|voucher|payment|pay\b|cod|পেমেন্ট|টাকা\s*পরিশোধ|deri|দেরি|delay|order|অর্ডার)/ui', $lower)) {
            return new DecisionResult(
                schemaVersion: DecisionResult::SCHEMA_VERSION,
                intent: DecisionResult::INTENT_KNOWLEDGE,
                responseMode: DecisionResult::MODE_TOOL_REQUIRED,
                toolCalls: [
                    new ToolCall(
                        tool: 'knowledge_intelligence',
                        operation: 'search_knowledge',
                        arguments: ['query' => $query]
                    ),
                ],
                clarification: null,
                directResponse: null,
                decisionMetadata: ['layer' => 'fallback_product_knowledge', 'decision_ms' => $latency, 'is_fallback' => true]
            );
        }


        // Fallback Rule 4: Ambiguous Banglish / Colloquial Queries -> CLARIFICATION_REQUIRED
        return new DecisionResult(
            schemaVersion: DecisionResult::SCHEMA_VERSION,
            intent: DecisionResult::INTENT_UNCERTAIN,
            responseMode: DecisionResult::MODE_CLARIFICATION_REQUIRED,
            toolCalls: [],
            clarification: [
                'question' => 'আপনার প্রশ্নটি আরও স্পষ্টভাবে বুঝতে অনুগ্রহ করে একটু বিস্তারিত বলুন।',
                'options'  => ['আজকের মোট বিক্রি', 'প্রোডাক্টের স্টক ও দাম', 'কাস্টমার সাপোর্ট'],
            ],
            directResponse: null,
            decisionMetadata: ['layer' => 'fallback_uncertain_clarification', 'decision_ms' => $latency, 'is_fallback' => true]
        );
    }

    /**
     * Standalone greeting fast-path matcher.
     *
     * @return array{response: string}|null
     */
    protected function matchGreetingFastPath(string $query): ?array
    {
        $normalized = mb_strtolower(trim(preg_replace('/[\?\!\.,]+/u', '', $query)));

        // Greetings must be short (<= 4 words)
        $tokens = array_filter(explode(' ', $normalized));
        if (count($tokens) > 4) {
            return null;
        }

        // Guard: Substantive business keywords must never be treated as greetings
        if (preg_match('/\b(?:order|delivery|price|cost|dam|taka|sales|sell|stock|due|balance|মেইল|অর্ডার|দাম|বিক্রি|স্টক)\b/ui', $normalized)) {
            return null;
        }

        $bengaliGreetings = [
            'সালাম', 'সালামু আলাইকুম', 'সালামুআলাইকুম', 'আসসালামু আলাইকুম', 'আসসালামুআলাইকুম', 'কেমন আছেন', 'কেমন আছো', 'শুভ সকাল', 'শুভ দুপুর', 'শুভ সন্ধ্যা',
        ];
        foreach ($bengaliGreetings as $bg) {
            if ($normalized === $bg || str_starts_with($normalized, $bg . ' ')) {
                return ['response' => 'ওয়ালাইকুম আসসালাম! আমি আপনাকে কীভাবে সাহায্য করতে পারি?'];
            }
        }

        $englishGreetings = [
            'hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'how are you',
        ];
        foreach ($englishGreetings as $eg) {
            if ($normalized === $eg || str_starts_with($normalized, $eg . ' ')) {
                return ['response' => 'Hello! How can I assist you with our store and services today?'];
            }
        }

        return null;
    }

    protected function buildSystemPrompt(array $permittedToolNames, bool $isCustomer): string
    {
        $toolsDescription = $this->toolRegistry->formatToolsForPrompt($permittedToolNames);

        return <<<PROMPT
<ROLE>
You are the central Unified Decision Engine for an enterprise e-commerce platform and chatbot.
Analyze the user's message in context and return ONE validated Schema v1.0 JSON decision.
</ROLE>

<CAPABILITIES>
- CHAT: Conversational greetings, pleasantries, chitchat, gratitude, general capability inquiries.
- KNOWLEDGE: Product availability, specifications, pricing, stock for customer purchase (e.g. "Laptop Pro কি stock e ache?", "Laptop Pro এর দাম কত?"), return policies, shipping charges, delivery times.
- ANALYTICS: Internal business metrics, aggregate sales, revenue summaries, employee sales rankings, low-stock inventory alerts, cash flow, debt/dues reports.
- UNCERTAIN: Vague, ambiguous queries lacking required context or entity names.
- OOD: Queries completely unrelated to business, e-commerce, or customer service (e.g. weather, coding, politics).
</CAPABILITIES>

<RESPONSE_MODES>
- DIRECT_RESPONSE: Use for pure conversational CHAT, greetings, or when no tool lookup is required. Supply the answer in 'direct_response'.
- TOOL_REQUIRED: Use when query requires KNOWLEDGE retrieval or ANALYTICS execution. Select the best tool and operation in 'tool_calls'.
- CLARIFICATION_REQUIRED: Use when query is UNCERTAIN or ambiguous. Provide a clarifying question in 'clarification'.
- SAFE_REFUSAL: Use when the user requests restricted internal data or unauthorized operations.
- OUT_OF_DOMAIN: Use for OOD queries.
</RESPONSE_MODES>

<AVAILABLE_TOOLS>
{$toolsDescription}
</AVAILABLE_TOOLS>

<CRITICAL_RULES>
1. PRODUCT STOCK COLLISION RULE: A question asking if a specific product is in stock (e.g. "Laptop Pro কি stock e ache?", "Is this item available?") is KNOWLEDGE (tool: knowledge_intelligence, operation: check_product_availability), NOT ANALYTICS.
2. AGGREGATE INVENTORY REPORT RULE: A question asking for overall inventory levels or low-stock reports across the store (e.g. "স্টক রিপোর্ট দাও", "low stock products") is ANALYTICS (tool: inventory_analytics, operation: get_stock_overview).
3. Do NOT invent raw SQL, database tables, or executable commands.
4. Server will inject trusted workspace_id and permissions; do NOT include workspace_id or auth credentials in arguments.
5. Strict Schema v1.0 output only.
</CRITICAL_RULES>

<OUTPUT_SCHEMA>
Return ONLY a valid JSON object matching this exact structure:
{
  "schema_version": "1.0",
  "intent": "CHAT" | "KNOWLEDGE" | "ANALYTICS" | "UNCERTAIN" | "OOD",
  "response_mode": "DIRECT_RESPONSE" | "TOOL_REQUIRED" | "CLARIFICATION_REQUIRED" | "SAFE_REFUSAL" | "OUT_OF_DOMAIN",
  "tool_calls": [
    {
      "tool": "string",
      "operation": "string",
      "arguments": {}
    }
  ],
  "clarification": null | { "question": "string", "options": ["string"] },
  "direct_response": null | "string"
}
</OUTPUT_SCHEMA>
PROMPT;
    }

    protected function buildUserPrompt(DecisionRequest $request): string
    {
        $contextBlock = '';
        if (!empty($request->compactContext)) {
            $contextBlock = "<CONVERSATION_CONTEXT>\n{$request->compactContext}\n</CONVERSATION_CONTEXT>\n\n";
        }

        return "{$contextBlock}User Message: {$request->query}";
    }

    /**
     * Extract and validate JSON from raw model output.
     *
     * @return array<string, mixed>
     */
    protected function extractAndValidateJson(string $rawContent): array
    {
        $content = trim($rawContent);

        // Strip markdown code fences if present
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
        }

        $jsonStart = strpos($content, '{');
        $jsonEnd = strrpos($content, '}');
        if ($jsonStart !== false && $jsonEnd !== false) {
            $content = substr($content, $jsonStart, $jsonEnd - $jsonStart + 1);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Malformed JSON output from model: '{$rawContent}'");
        }

        if (($decoded['schema_version'] ?? null) !== '1.0') {
            throw new \RuntimeException("Invalid or unsupported schema_version: " . ($decoded['schema_version'] ?? 'null'));
        }

        if (empty($decoded['intent']) || empty($decoded['response_mode'])) {
            throw new \RuntimeException("Missing required intent or response_mode in decision JSON.");
        }

        return $decoded;
    }
}
