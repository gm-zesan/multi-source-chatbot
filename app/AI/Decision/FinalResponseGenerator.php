<?php

declare(strict_types=1);

namespace App\AI\Decision;

use App\AI\Agents\KnowledgeSupportAgent;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ExecutionResponse;
use App\AI\DTOs\ToolResult;
use App\AI\LLM\LLMClient;
use App\AI\Orchestration\CustomerSupportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Final Response Generator.
 *
 * Synthesizes grounded natural-language responses based strictly on actual tool results.
 * Standardizes the response shape to satisfy all application and controller contracts.
 */
class FinalResponseGenerator
{
    public function __construct(
        private readonly ?LLMClient $llmClient = null,
    ) {}

    /**
     * Generate user-facing reply.
     *
     * @param DecisionRequest $request
     * @param DecisionResult $decision
     * @param ExecutionResponse $execution
     * @return array<string, mixed>
     */
    public function generate(
        DecisionRequest $request,
        DecisionResult $decision,
        ExecutionResponse $execution,
    ): array {
        // ── 1. Direct Response Optimization (Zero 2nd LLM Call) ─────────────
        if ($decision->isDirectResponse() && !empty($decision->directResponse)) {
            return $this->defaultContract([
                'reply'       => $decision->directResponse,
                'route'       => 'chat',
                'confidence'  => 1.0,
                'answered'    => true,
                'routing_telemetry' => [
                    'route'             => 'chat',
                    'confidence'        => 1.0,
                    'intent'            => 'direct_chat',
                    'router_latency_ms' => 0.0,
                    'total_e2e_ms'      => 0.0,
                    'signals'           => [
                        'contextual_signal' => 'COMMERCE_GENERAL',
                    ],
                ],
            ]);
        }

        // ── 2. Clarification Required Optimization (Zero 2nd LLM Call) ──────
        if ($decision->isClarificationRequired() && !empty($decision->clarification)) {
            $question = (string) ($decision->clarification['question'] ?? 'আপনার প্রশ্নটি আরও বিস্তারিত বলুন।');
            $options = is_array($decision->clarification['options'] ?? null)
                ? $decision->clarification['options']
                : [];

            return $this->defaultContract([
                'reply'       => $question,
                'route'       => 'uncertain',
                'confidence'  => 0.5,
                'answered'    => false,
                'suggestions' => $options,
                'routing_telemetry' => [
                    'route'             => 'uncertain',
                    'confidence'        => 0.5,
                    'intent'            => 'clarification_required',
                    'router_latency_ms' => 0.0,
                    'total_e2e_ms'      => 0.0,
                    'signals'           => [
                        'contextual_signal' => 'COMMERCE_GENERAL',
                    ],
                ],
            ]);
        }

        // ── 3. Safe Refusal Optimization (Zero 2nd LLM Call) ────────────────
        if ($decision->isSafeRefusal()) {
            return $this->defaultContract([
                'reply'       => $decision->directResponse ?? CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE,
                'route'       => 'analytics_denied',
                'confidence'  => 1.0,
                'answered'    => true,
                'routing_telemetry' => [
                    'route'             => 'analytics_denied',
                    'confidence'        => 1.0,
                    'intent'            => 'customer_analytics_blocked',
                    'router_latency_ms' => 0.0,
                    'total_e2e_ms'      => 0.0,
                    'signals'           => [
                        'contextual_signal' => 'COMMERCE_GENERAL',
                    ],
                ],
            ]);
        }

        // ── 4. Out of Domain Optimization (Zero 2nd LLM Call) ───────────────
        if ($decision->isOod()) {
            return $this->defaultContract([
                'reply'       => $decision->directResponse ?? 'দুঃখিত, এই বিষয়টি আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়। আমি শুধুমাত্র আমাদের পণ্য ও সেবা সংক্রান্ত তথ্যে সাহায্য করতে পারি।',
                'route'       => 'ood',
                'confidence'  => 1.0,
                'answered'    => true,
                'routing_telemetry' => [
                    'route'             => 'ood',
                    'confidence'        => 1.0,
                    'intent'            => 'out_of_domain',
                    'router_latency_ms' => 0.0,
                    'total_e2e_ms'      => 0.0,
                    'signals'           => [
                        'contextual_signal' => 'COMMERCE_GENERAL',
                    ],
                ],
            ]);
        }

        // ── 5. Tool Execution Results Processing ────────────────────────────
        if (!$execution->success || empty($execution->toolResults)) {
            $errorMessage = $execution->errorMessage ?? 'ডেটা লোড করতে সাময়িক সমস্যা হয়েছে।';
            return $this->defaultContract([
                'reply'       => "ব্যবসায়িক অ্যানালিটিক্স সিস্টেম: আপনার কোয়েরি প্রসেস করতে সাময়িক সমস্যা হয়েছে (" . $errorMessage . ")।",
                'route'       => $decision->toRouteType()->value,
                'confidence'  => 0.5,
                'answered'    => false,
            ]);
        }

        // Gather tool results & inspect knowledge hits
        $sources = [];
        $reports = [];
        $retrievalHits = new Collection();
        $topHit = null;

        foreach ($execution->toolResults as $item) {
            $toolName = (string) ($item['tool'] ?? '');
            $result = $item['result'];

            if ($result instanceof ToolResult) {
                if ($result->isSuccess()) {
                    if (isset($result->metadata['retrieval_hits']) && $result->metadata['retrieval_hits'] instanceof \Illuminate\Support\Collection) {
                        $retrievalHits = $result->metadata['retrieval_hits'];
                    }
                    if (isset($result->metadata['top_hit'])) {
                        $topHit = $result->metadata['top_hit'];
                    }

                    if ($toolName === 'knowledge_intelligence') {
                        $agentHits = $retrievalHits instanceof Collection ? $retrievalHits : collect($retrievalHits);

                        if ($agentHits->isEmpty()) {
                            $reports[] = 'দুঃখিত, এই বিষয়টি আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়। আমি শুধুমাত্র আমাদের পণ্য ও সেবা সংক্রান্ত তথ্যে সাহায্য করতে পারি।';
                        } else {
                            try {
                                $agent = new KnowledgeSupportAgent(
                                    conversation: $request->conversation,
                                    retrievedKnowledge: $agentHits,
                                    memoryContext: $request->compactContext,
                                    businessContext: null,
                                );

                                $rawReply = (string) $agent->prompt($request->query);
                                $cleanReply = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails(
                                    $rawReply,
                                    $agentHits,
                                    null
                                );

                                $reports[] = $cleanReply;
                            } catch (Throwable $agentError) {
                                Log::warning('[FinalResponseGenerator] KnowledgeSupportAgent generation failed: ' . $agentError->getMessage());
                                $topAnswer = $topHit?->faq?->answer
                                    ?? $topHit?->answer
                                    ?? ($result->data['evidence_items'][0]['content'] ?? null);
                                $reports[] = $topAnswer ?? 'আমি আপনার প্রশ্নের উত্তর আমাদের নলেজ বেসে খুঁজে পাইনি।';
                            }
                        }
                    } else {
                        $reports[] = (string) $result;
                    }

                    if (!empty($result->metadata['sources'])) {
                        $sources = array_merge($sources, (array) $result->metadata['sources']);
                    }
                } else {
                    $reports[] = "টুল '{$toolName}': " . $result->error;
                }
            } elseif (is_string($result)) {
                $reports[] = $result;
            }
        }

        $combinedReport = implode("\n\n", array_filter($reports));
        $routeValue = $decision->toRouteType()->value;

        if (!empty($combinedReport)) {
            $answerabilityDecision = [
                'status'           => $decision->isAnalytics() ? 'ANSWERABLE_ANALYTICS' : 'ANSWERABLE_GROUNDED',
                'confidence_score' => 1.0,
                'grounded_count'   => $retrievalHits->count(),
                'reasons'          => ['Grounded in knowledge base retrieval and tool execution'],
            ];

            $extraMeta = [];
            foreach ($execution->toolResults as $item) {
                $res = $item['result'] ?? null;
                if ($res instanceof ToolResult) {
                    if (isset($res->metadata['relationships_active'])) {
                        $extraMeta['relationships_active'] = $res->metadata['relationships_active'];
                    }
                    if (isset($res->metadata['contract_version'])) {
                        $extraMeta['contract_version'] = $res->metadata['contract_version'];
                    }
                    if (isset($res->metadata['query_fingerprint'])) {
                        $extraMeta['query_fingerprint'] = $res->metadata['query_fingerprint'];
                    }
                }
            }

            $isOwnerPipeline = !$request->isCustomer;

            return $this->defaultContract(array_merge([
                'reply'                  => $combinedReport,
                'route'                  => $routeValue,
                'confidence'             => 1.0,
                'answered'               => true,
                'is_owner_pipeline'      => $isOwnerPipeline,
                'sources'                => array_values(array_unique($sources)),
                'retrieval_hits'         => $retrievalHits,
                'top_hit'                => $topHit,
                'answerability_decision' => $answerabilityDecision,
                'routing_telemetry'      => [
                    'route'             => $routeValue,
                    'confidence'        => 1.0,
                    'intent'            => $decision->intent,
                    'router_latency_ms' => 0.0,
                    'total_e2e_ms'      => 0.0,
                    'signals'           => [
                        'contextual_signal' => 'COMMERCE_GENERAL',
                    ],
                ],
            ], $extraMeta));
        }

        return $this->defaultContract([
            'reply'       => 'আপনার অনুরোধকৃত তথ্যের কোনো রেকর্ড পাওয়া যায়নি।',
            'route'       => $routeValue,
            'confidence'  => 0.8,
            'answered'    => true,
            'sources'     => [],
        ]);
    }

    /**
     * Map a legacy/owner assistant result to the standardized response contract.
     *
     * @param array<string, mixed> $legacyResult
     * @param DecisionRequest $request
     * @param DecisionResult $decision
     * @param float $tStart
     * @return array<string, mixed>
     */
    public function fromLegacyResult(
        array $legacyResult,
        DecisionRequest $request,
        DecisionResult $decision,
        float $tStart,
    ): array {
        $elapsed = round((microtime(true) - $tStart) * 1000, 2);

        $retrievalHits = $legacyResult['retrieval_hits'] ?? null;
        if (!($retrievalHits instanceof Collection)) {
            $retrievalHits = $retrievalHits instanceof \Illuminate\Support\Collection
                ? (new Collection($retrievalHits->all()))
                : new Collection();
        }

        return $this->defaultContract([
            'reply'                  => (string) ($legacyResult['reply'] ?? ''),
            'route'                  => (string) ($legacyResult['route'] ?? 'analytics'),
            'confidence'             => (float) ($legacyResult['confidence'] ?? 1.0),
            'answered'               => (bool) ($legacyResult['answered'] ?? true),
            'suggestions'            => (array) ($legacyResult['suggestions'] ?? []),
            'sources'                => (array) ($legacyResult['sources'] ?? []),
            'is_handoff'             => (bool) ($legacyResult['is_handoff'] ?? false),
            'memory_context'         => $legacyResult['memory_context'] ?? null,
            'business_context'       => $legacyResult['business_context'] ?? null,
            'retrieval_hits'         => $retrievalHits,
            'top_hit'                => $legacyResult['top_hit'] ?? null,
            'answerability_decision' => is_array($legacyResult['answerability_decision'] ?? null)
                ? $legacyResult['answerability_decision']
                : (is_string($legacyResult['answerability_decision'] ?? null)
                    ? ['status' => $legacyResult['answerability_decision'], 'confidence_score' => 1.0]
                    : null),
            'follow_up_decision'     => $legacyResult['follow_up_decision'] ?? null,
            'raw_llm_response'       => is_array($legacyResult['raw_llm_response'] ?? null)
                ? $legacyResult['raw_llm_response']
                : [
                    'provider'                 => config('ai.default', 'deepseek'),
                    'model'                    => config('ai.default_model', 'deepseek-chat'),
                    'prompt_tokens'            => 0,
                    'completion_tokens'        => 0,
                    'total_tokens'             => 0,
                    'router_tokens'            => [],
                    'agent_tokens'             => [],
                    'grounded_documents_count' => 0,
                    'grounded_faq_questions'   => [],
                ],
            'routing_telemetry'      => array_merge([
                'route'             => (string) ($legacyResult['route'] ?? 'analytics'),
                'confidence'        => (float) ($legacyResult['confidence'] ?? 1.0),
                'intent'            => 'business_analytics',
                'router_latency_ms' => 0.0,
                'total_e2e_ms'      => $elapsed,
                'signals'           => [
                    'contextual_signal' => 'COMMERCE_GENERAL',
                ],
            ], (array) ($legacyResult['routing_telemetry'] ?? [])),
            'lexicon_telemetry'      => (array) ($legacyResult['lexicon_telemetry'] ?? [
                'canonical_concepts'  => [],
                'expansion_triggered' => false,
                'expanded_query'      => null,
                'reranker_applied'    => false,
                'reranker_reason'     => null,
                'first_pass_score'    => null,
                'tier_executed'       => null,
            ]),
            'latency_breakdown'      => array_merge([
                'router_ms'              => 0.0,
                'context_resolution_ms'  => 0.0,
                'clarification_ms'       => 0.0,
                'memory_gate_ms'         => 0.0,
                'memory_retrieval_ms'    => 0.0,
                'business_context_ms'    => 0.0,
                'knowledge_retrieval_ms' => 0.0,
                'retrieval_ms'           => 0.0,
                'answerability_ms'       => 0.0,
                'llm_generation_ms'      => 0.0,
                'llm_ms'                 => 0.0,
                'total_ms'               => $elapsed,
                'total_e2e_ms'           => $elapsed,
            ], (array) ($legacyResult['latency_breakdown'] ?? [])),
        ]);
    }

    /**
     * Provide baseline default array contract matching all legacy and modern consumer expectations.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function defaultContract(array $overrides = []): array
    {
        $base = [
            'reply'                  => '',
            'route'                  => 'chat',
            'confidence'             => 1.0,
            'answered'               => true,
            'is_owner_pipeline'      => false,
            'suggestions'            => [],
            'sources'                => [],
            'is_handoff'             => false,
            'memory_context'         => null,
            'business_context'       => null,
            'retrieval_hits'         => new Collection(),
            'top_hit'                => null,
            'answerability_decision' => [
                'status'           => 'ANSWERABLE_GROUNDED',
                'confidence_score' => 1.0,
                'grounded_count'   => 0,
                'reasons'          => [],
            ],
            'follow_up_decision'     => null,
            'raw_llm_response'       => [
                'provider'                 => config('ai.default', 'deepseek'),
                'model'                    => config('ai.default_model', 'deepseek-chat'),
                'prompt_tokens'            => 0,
                'completion_tokens'        => 0,
                'total_tokens'             => 0,
                'router_tokens'            => [],
                'agent_tokens'             => [],
                'grounded_documents_count' => 0,
                'grounded_faq_questions'   => [],
            ],
            'routing_telemetry'      => [
                'route'             => 'chat',
                'confidence'        => 1.0,
                'intent'            => 'direct_chat',
                'router_latency_ms' => 0.0,
                'total_e2e_ms'      => 0.0,
                'signals'           => [
                    'contextual_signal' => 'COMMERCE_GENERAL',
                ],
            ],
            'lexicon_telemetry'      => [
                'canonical_concepts'  => [],
                'expansion_triggered' => false,
                'expanded_query'      => null,
                'reranker_applied'    => false,
                'reranker_reason'     => null,
                'first_pass_score'    => null,
                'tier_executed'       => null,
            ],
            'latency_breakdown'      => [
                'router_ms'              => 0.0,
                'context_resolution_ms'  => 0.0,
                'clarification_ms'       => 0.0,
                'memory_gate_ms'         => 0.0,
                'memory_retrieval_ms'    => 0.0,
                'business_context_ms'    => 0.0,
                'knowledge_retrieval_ms' => 0.0,
                'retrieval_ms'           => 0.0,
                'answerability_ms'       => 0.0,
                'llm_generation_ms'      => 0.0,
                'llm_ms'                 => 0.0,
                'total_ms'               => 0.0,
                'total_e2e_ms'           => 0.0,
            ],
        ];

        return array_merge($base, $overrides);
    }
}
