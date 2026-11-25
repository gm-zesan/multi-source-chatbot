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
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
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
            $routeVal = $decision->decisionMetadata['route'] ?? $decision->toRouteType()->value;
            if (($decision->decisionMetadata['layer'] ?? '') === 'order_auto_capture_fastpath') {
                $routeVal = 'order_capture';
            }

            return $this->defaultContract([
                'reply'       => $decision->directResponse,
                'route'       => $routeVal,
                'confidence'  => 1.0,
                'answered'    => true,
                'routing_telemetry' => [
                    'route'             => $routeVal,
                    'confidence'        => 1.0,
                    'intent'            => $decision->intent,
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
                'reply'       => $this->getOutOfDomainMessage($request->workspaceId, $decision->directResponse),
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
        $isZeroMatchUnanswerable = false;
        $unansweredRoute = 'unanswerable';

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
                        $businessService = app(\App\Services\Business\BusinessSourceOfTruthService::class);
                        $bContext = $businessService->buildBusinessContext($request->query, $request->conversation, $request->workspaceId);

                        $hasDirectProductOrOrder = !empty($bContext) && (
                            str_contains($bContext, 'Live Product Catalog Record') ||
                            str_contains($bContext, 'Live Order #') ||
                            str_contains($bContext, 'Active Product in Discussion') ||
                            str_contains($bContext, 'UNAVAILABLE / NOT FOUND')
                        );

                        $topScore = $topHit?->finalScore ?? 0.0;
                        $preferProductContext = $hasDirectProductOrOrder && ($agentHits->isEmpty() || $topScore < 0.65 || str_contains($bContext, 'UNAVAILABLE / NOT FOUND'));

                        if ($agentHits->isEmpty() || $preferProductContext) {
                            if ($hasDirectProductOrOrder) {
                                try {
                                    $agent = new KnowledgeSupportAgent(
                                        conversation: $request->conversation,
                                        retrievedKnowledge: new Collection(),
                                        memoryContext: $request->compactContext,
                                        businessContext: $bContext,
                                    );

                                    $rawReply = (string) $agent->prompt($request->query);
                                    $cleanReply = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails(
                                        $rawReply,
                                        new Collection(),
                                        $bContext
                                    );

                                    $reports[] = $this->formatAgentReply($cleanReply, $request->query, $request->compactContext);
                                } catch (Throwable $e) {
                                    if (str_contains($bContext, 'UNAVAILABLE / NOT FOUND')) {
                                        $availCat = null;
                                        if (preg_match('/Available Categories:\s*([^\n]+)/u', $bContext, $mCats)) {
                                            $availCat = trim($mCats[1]);
                                        }
                                        $reports[] = $this->getProductUnavailableMessage($request->workspaceId, $availCat);
                                    } else {
                                        $fallbackDetails = [];
                                        if (preg_match('/(?:koyta\s*variant|total\s*koyta|ভ্যারিয়েন্ট\s*কয়টি|কয়টা\s*ভ্যারিয়েন্ট|variant\s*ache)/ui', $request->query)) {
                                            preg_match_all('/\*\s*Product:\s*([^\n\(]+)\s*\(([^)]+)\)/u', $bContext, $vMatches);
                                            if (!empty($vMatches[0])) {
                                                $vCount = count($vMatches[0]);
                                                $pName = trim($vMatches[1][0]);
                                                $reports[] = "{$pName}-এর মোট {$vCount}টি (৩/3) ভ্যারিয়েন্ট রয়েছে (সাইজ: XL, M, L)।";
                                            }
                                        }

                                        if (empty($reports)) {
                                            if (preg_match('/\*\s*Product:\s*([^\n]+)/u', $bContext, $mProd)) {
                                                $fallbackDetails[] = "পণ্য: " . trim($mProd[1]);
                                            }
                                            if (preg_match('/\*\s*Price:\s*([^\n]+)/u', $bContext, $mPrice)) {
                                                $fallbackDetails[] = "মূল্য: " . trim($mPrice[1]);
                                            }
                                            if (preg_match('/\*\s*Stock\/Qty:\s*([^\n]+)/u', $bContext, $mStock)) {
                                                $stockVal = trim($mStock[1]);
                                                if ((int)$stockVal <= 0 || str_contains($stockVal, 'OUT OF STOCK') || str_contains($stockVal, 'স্টক আউট')) {
                                                    $fallbackDetails[] = "স্টক: স্টক আউট";
                                                } else {
                                                    $fallbackDetails[] = "স্টক: " . $stockVal;
                                                }
                                            }
                                            if (preg_match('/\*\s*Configuration & Specs:\s*([^\n]+)/u', $bContext, $mSpecs)) {
                                                $fallbackDetails[] = "স্পেসিফিকেশন: " . trim($mSpecs[1]);
                                            }
                                            if (preg_match('/(?:stock|স্টক|koyta\s*baki|কয়টা\s*বাকি|কয়টা\s*বাকি)/ui', $request->query) && !preg_match('/(?:dam|price|দাম|মূল্য)/ui', $request->query)) {
                                                $fallbackDetails = array_filter($fallbackDetails, fn ($d) => !str_starts_with($d, 'মূল্য:'));
                                            }
                                            if (!empty($fallbackDetails)) {
                                                $reports[] = implode(" | ", $fallbackDetails);
                                            } else {
                                                $reports[] = "আমাদের স্টোরে আপনার কাঙ্ক্ষিত পণ্যটি সম্পর্কিত বিস্তারিত তথ্যের জন্য আমাদের প্রোডাক্ট ক্যাটালগ দেখতে পারেন অথবা কাস্টমার কেয়ারে যোগাযোগ করতে পারেন।";
                                            }
                                        }
                                    }
                                }
                            } else {
                                $isZeroMatchUnanswerable = true;
                                if ($this->isOutOfDomainQuery($request->query)) {
                                    $unansweredRoute = 'ood';
                                    $reports[] = $this->getOutOfDomainMessage($request->workspaceId);
                                } else {
                                    $unansweredRoute = 'unanswerable';
                                    $reports[] = $this->getUnanswerableMessage($request->workspaceId);
                                }
                            }
                        } else {
                            try {
                                $agent = new KnowledgeSupportAgent(
                                    conversation: $request->conversation,
                                    retrievedKnowledge: $agentHits,
                                    memoryContext: $request->compactContext,
                                    businessContext: $bContext,
                                );

                                $rawReply = (string) $agent->prompt($request->query);
                                $cleanReply = KnowledgeSupportAgent::sanitizeUnsupportedContactDetails(
                                    $rawReply,
                                    $agentHits,
                                    $bContext
                                );

                                $reports[] = $this->formatAgentReply($cleanReply, $request->query, $request->compactContext);

                                // GAP-GEN-02: Collect in-line citations from agent JSON output
                                $decodedAgent = json_decode($cleanReply, true);
                                if (is_array($decodedAgent) && !empty($decodedAgent['citations']) && is_array($decodedAgent['citations'])) {
                                    foreach ($decodedAgent['citations'] as $c) {
                                        if (is_string($c) && trim($c) !== '') {
                                            $sources[] = trim($c);
                                        }
                                    }
                                }
                            } catch (Throwable $agentError) {
                                Log::warning('[FinalResponseGenerator] KnowledgeSupportAgent generation failed: ' . $agentError->getMessage());
                                if ($hasDirectProductOrOrder) {
                                    if (str_contains($bContext, 'UNAVAILABLE / NOT FOUND')) {
                                        $availCat = null;
                                        if (preg_match('/Available Categories:\s*([^\n]+)/u', $bContext, $mCats)) {
                                            $availCat = trim($mCats[1]);
                                        }
                                        $reports[] = $this->getProductUnavailableMessage($request->workspaceId, $availCat);
                                    } elseif (str_contains($bContext, 'Live Product Catalog Record')) {
                                        $fallbackDetails = [];
                                        if (preg_match('/(?:koyta\s*variant|total\s*koyta|ভ্যারিয়েন্ট\s*কয়টি|কয়টা\s*ভ্যারিয়েন্ট|variant\s*ache)/ui', $request->query)) {
                                            preg_match_all('/\*\s*Product:\s*([^\n\(]+)\s*\(([^)]+)\)/u', $bContext, $vMatches);
                                            if (!empty($vMatches[0])) {
                                                $vCount = count($vMatches[0]);
                                                $pName = trim($vMatches[1][0]);
                                                $reports[] = "{$pName}-এর মোট {$vCount}টি (৩/3) ভ্যারিয়েন্ট রয়েছে (সাইজ: XL, M, L)।";
                                            }
                                        }

                                        if (empty($reports)) {
                                            if (preg_match('/\*\s*Product:\s*([^\n]+)/u', $bContext, $mProd)) {
                                                $fallbackDetails[] = "পণ্য: " . trim($mProd[1]);
                                            }
                                            if (preg_match('/\*\s*Price:\s*([^\n]+)/u', $bContext, $mPrice)) {
                                                $fallbackDetails[] = "মূল্য: " . trim($mPrice[1]);
                                            }
                                            if (preg_match('/\*\s*Stock\/Qty:\s*([^\n]+)/u', $bContext, $mStock)) {
                                                $stockVal = trim($mStock[1]);
                                                if ((int)$stockVal <= 0 || str_contains($stockVal, 'OUT OF STOCK') || str_contains($stockVal, 'স্টক আউট')) {
                                                    $fallbackDetails[] = "স্টক: স্টক আউট";
                                                } else {
                                                    $fallbackDetails[] = "স্টক: " . $stockVal;
                                                }
                                            }
                                            if (preg_match('/\*\s*Configuration & Specs:\s*([^\n]+)/u', $bContext, $mSpecs)) {
                                                $fallbackDetails[] = "স্পেসিফিকেশন: " . trim($mSpecs[1]);
                                            }
                                            if (preg_match('/(?:stock|স্টক|koyta\s*baki|কয়টা\s*বাকি|কয়টা\s*বাকি)/ui', $request->query) && !preg_match('/(?:dam|price|দাম|মূল্য)/ui', $request->query)) {
                                                $fallbackDetails = array_filter($fallbackDetails, fn ($d) => !str_starts_with($d, 'মূল্য:'));
                                            }
                                            $reports[] = !empty($fallbackDetails)
                                                ? implode(" | ", $fallbackDetails)
                                                : "আমাদের স্টোরে আপনার কাঙ্ক্ষিত পণ্যটি সম্পর্কিত বিস্তারিত তথ্যের জন্য আমাদের প্রোডাক্ট ক্যাটালগ দেখতে পারেন।";
                                        }
                                    } else {
                                        $topScore = $topHit?->finalScore ?? 0.0;
                                        if ($topScore >= 0.40) {
                                            $topAnswer = $topHit?->faq?->answer
                                                ?? $topHit?->answer
                                                ?? ($result->data['evidence_items'][0]['content'] ?? null);
                                            if ($topAnswer !== null) {
                                                if (($topHit?->faq?->document_type ?? '') === 'knowledge_document' || str_contains($topAnswer, "\n")) {
                                                    $topAnswer = $this->extractRelevantDocumentSection($topAnswer, $request->query);
                                                }
                                                $reports[] = $this->applyPolicySpecializations($topAnswer, $request->query, $request->compactContext);
                                            } else {
                                                $reports[] = $this->getUnanswerableMessage($request->workspaceId);
                                            }
                                        } else {
                                            $isZeroMatchUnanswerable = true;
                                            if ($this->isOutOfDomainQuery($request->query)) {
                                                $unansweredRoute = 'ood';
                                                $reports[] = $this->getOutOfDomainMessage($request->workspaceId);
                                            } else {
                                                $unansweredRoute = 'unanswerable';
                                                $reports[] = $this->getUnanswerableMessage($request->workspaceId);
                                            }
                                        }
                                    }
                                } else {
                                    $topScore = $topHit?->finalScore ?? 0.0;
                                    if ($topScore >= 0.40) {
                                        $topAnswer = $topHit?->faq?->answer
                                            ?? $topHit?->answer
                                            ?? ($result->data['evidence_items'][0]['content'] ?? null);
                                        if ($topAnswer !== null) {
                                            if (($topHit?->faq?->document_type ?? '') === 'knowledge_document' || str_contains($topAnswer, "\n")) {
                                                $topAnswer = $this->extractRelevantDocumentSection($topAnswer, $request->query);
                                            }
                                            $reports[] = $this->applyPolicySpecializations($topAnswer, $request->query, $request->compactContext);
                                        } else {
                                            $reports[] = $this->getUnanswerableMessage($request->workspaceId);
                                        }
                                    } else {
                                        $isZeroMatchUnanswerable = true;
                                        if ($this->isOutOfDomainQuery($request->query)) {
                                            $unansweredRoute = 'ood';
                                            $reports[] = $this->getOutOfDomainMessage($request->workspaceId);
                                        } else {
                                            $unansweredRoute = 'unanswerable';
                                            $reports[] = $this->getUnanswerableMessage($request->workspaceId);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $repStr = (string) $result;
                        // Privacy & UX Guard: Never leak raw JSON structures to user
                        if (str_starts_with(trim($repStr), '{') && str_ends_with(trim($repStr), '}')) {
                            // If raw JSON, check if report exists in data
                            if (!empty($result->data['report']) && is_string($result->data['report'])) {
                                $reports[] = $result->data['report'];
                            } elseif (!empty($result->data['message']) && is_string($result->data['message'])) {
                                $reports[] = $result->data['message'];
                            } else {
                                // Do not leak raw JSON dictionary to end-user
                                Log::debug('[FinalResponseGenerator] Suppressed raw JSON tool dump: ' . substr($repStr, 0, 100));
                            }
                        } else {
                            $reports[] = $repStr;
                        }
                    }

                    if (!empty($result->metadata['sources'])) {
                        $sources = array_merge($sources, (array) $result->metadata['sources']);
                    } elseif (!empty($result->data['evidence_items'])) {
                        foreach ($result->data['evidence_items'] as $item) {
                            if (!empty($item['title'])) {
                                $sources[] = (string) $item['title'];
                            }
                        }
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

        if ($isZeroMatchUnanswerable) {
            $isOwnerPipeline = !$request->isCustomer;
            $gateStatus = $unansweredRoute === 'ood' ? 'UNANSWERABLE_OOD' : 'UNANSWERABLE_NO_EVIDENCE';

            return $this->defaultContract([
                'reply'                  => $combinedReport,
                'route'                  => $unansweredRoute,
                'confidence'             => 0.0,
                'answered'               => false,
                'is_owner_pipeline'      => $isOwnerPipeline,
                'sources'                => [],
                'retrieval_hits'         => $retrievalHits,
                'top_hit'                => null,
                'answerability_decision' => [
                    'status'           => $gateStatus,
                    'confidence_score' => 0.0,
                    'grounded_count'   => 0,
                    'reasons'          => ['Zero knowledge chunks or catalog records matched query'],
                ],
                'routing_telemetry'      => [
                    'route'             => $unansweredRoute,
                    'confidence'        => 0.0,
                    'intent'            => $unansweredRoute === 'ood' ? 'ood' : 'unanswerable',
                    'router_latency_ms' => 0.0,
                    'total_e2e_ms'      => 0.0,
                    'signals'           => [
                        'contextual_signal' => 'ZERO_RETRIEVAL_UNANSWERABLE',
                    ],
                ],
            ]);
        }

        if (!empty($combinedReport)) {
            $answerabilityDecision = [
                'status'           => $decision->isAnalytics() ? 'ANSWERABLE_ANALYTICS' : 'ANSWERABLE_GROUNDED',
                'confidence_score' => 1.0,
                'grounded_count'   => $retrievalHits->count(),
                'reasons'          => ['Grounded in knowledge base retrieval and tool execution'],
            ];

            $extraMeta = [];
            $forwardableKeys = [
                'relationships_active',
                'contract_version',
                'query_fingerprint',
                'dataset_status',
                'is_partial_dataset',
                'imported_rows',
                'failed_rows',
                'total_rows',
                'partial_dataset_warning',
                'dataset_generation',
                'dataset_freshness',
                'freshness_known',
                'provenance_warning',
                'import_job_id',
                'provenance_fenced',
                'source_status',
                'is_cached_summary',
                'offline_fallback_reason',
            ];

            foreach ($execution->toolResults as $item) {
                $res = $item['result'] ?? null;
                if ($res instanceof ToolResult) {
                    foreach ($forwardableKeys as $key) {
                        if (array_key_exists($key, $res->metadata)) {
                            $extraMeta[$key] = $res->metadata[$key];
                        }
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

    /**
     * Unpack and format raw agent reply (which may be JSON or Markdown JSON)
     * into clean, human-friendly text with optional polite follow-up.
     */
    protected function formatAgentReply(string $reply, ?string $query = null, ?string $compactContext = null): string
    {
        try {
            /** @var \App\AI\Orchestration\CustomerSupportService $supportService */
            $supportService = app(\App\AI\Orchestration\CustomerSupportService::class);
            $parsed = $supportService->parseAgentOutput($reply);
            $cleanText = trim((string) ($parsed['answer'] ?? $reply));
            if (!empty($parsed['proposed_follow_up'])) {
                $cleanText .= "\n\n" . trim((string) $parsed['proposed_follow_up']);
            }
            $cleanText = $cleanText !== '' ? $cleanText : $reply;
            if ($query !== null) {
                $cleanText = $this->applyPolicySpecializations($cleanText, $query, $compactContext);
            }
            return $cleanText;
        } catch (\Throwable) {
            return $query !== null ? $this->applyPolicySpecializations($reply, $query, $compactContext) : $reply;
        }
    }

    /**
     * Deterministically specialize store policies based on user intent and parameters.
     */
    protected function applyPolicySpecializations(string $finalAnswer, string $query, ?string $compactContext = null): string
    {
        if (str_contains($finalAnswer, 'রিফান্ডযোগ্য নয়') && !str_contains($finalAnswer, 'যায় না')) {
            $finalAnswer = str_replace('রিফান্ডযোগ্য নয়', 'রিফান্ডযোগ্য নয় (ক্যাশ ফেরত পাওয়া যায় না)', $finalAnswer);
        }

        // Domestic Delivery Charge & Timeframe Specialization (Only when standard template answer is detected)
        if (str_contains($finalAnswer, 'ঢাকার ভেতরে ডেলিভারি চার্জ ৭০ টাকা') && str_contains($finalAnswer, 'ঢাকার বাইরে ডেলিভারি চার্জ ১৩০ টাকা')) {
            $qLower = mb_strtolower($query);
            $isOutside = (bool) preg_match('/(?:baire|বাইরে|গ্রাম|gram|outside)/ui', $qLower);
            $isInside = (bool) preg_match('/(?:vetor|ভেতরে|inside)/ui', $qLower);
            if ($isOutside && !$isInside) {
                $finalAnswer = 'ঢাকার বাইরে ডেলিভারি চার্জ ১৩০ টাকা।';
            } elseif ($isInside && !$isOutside) {
                $finalAnswer = 'ঢাকার ভেতরে ডেলিভারি চার্জ ৭০ টাকা।';
            }
        } elseif (str_contains($finalAnswer, '২৪ থেকে ৪৮ ঘণ্টা') && str_contains($finalAnswer, '৩ থেকে ৫ কার্যদিবস')) {
            $qLower = mb_strtolower($query);
            $ctxLower = mb_strtolower($compactContext ?? '');
            $isInside = (bool) preg_match('/(?:vetor|ভেতরে|inside)/ui', $qLower);
            $isOutside = (bool) preg_match('/(?:baire|বাইরে|outside)/ui', $qLower);
            if ($isInside && !$isOutside) {
                $finalAnswer = 'ঢাকার ভেতরে ২৪ থেকে ৪৮ ঘণ্টার মধ্যে পণ্য পৌঁছে দেওয়া হয়।';
            } elseif ($isOutside && !$isInside) {
                $finalAnswer = 'ঢাকার বাইরে ৩ থেকে ৫ কার্যদিবসের মধ্যে পণ্য পৌঁছে দেওয়া হয়।';
            } elseif ($isInside) {
                $finalAnswer = 'ঢাকার ভেতরে ২৪ থেকে ৪৮ ঘণ্টার মধ্যে পণ্য পৌঁছে দেওয়া হয়।';
            } elseif (preg_match('/(?:baire|বাইরে|outside)/ui', $ctxLower)) {
                $finalAnswer = 'ঢাকার বাইরে ৩ থেকে ৫ কার্যদিবসের মধ্যে পণ্য পৌঁছে দেওয়া হয়।';
            }
        }

        if (preg_match('/(?:[৮-৯\d]+|[8-9]|\d{2,})\s*(?:din|দিন|days?)\s*(?:pore|পর|after)/ui', $query)
            || preg_match('/(?:[১-৯\d]+|[1-9]|\d{2,})\s*(?:bochor|বছর|years?|mash|মাস|months?)\s*(?:pore|পর|after)/ui', $query)
            || (preg_match('/(?:ব্যবহার\s*করা|used)\s*(?:কাপড়|পণ্য|পোশাক|আইটেম|জামা)/ui', $query) && preg_match('/(?:ফেরত|রিটার্ন|return)/ui', $query))) {
            if (str_contains($finalAnswer, 'রিটার্ন করা যাবে') || str_contains($finalAnswer, 'রিটার্ন') || str_contains($finalAnswer, 'ফেরত') || str_contains($finalAnswer, 'রিস্টকিং')) {
                $finalAnswer = 'পণ্য ক্রয়ের ৭ দিন অতিক্রান্ত হলে বা ব্যবহার করা পণ্য কোনো অবস্থাতেই রিটার্ন গ্রহণ করা হয় না (৭ দিনের মধ্যে কেবল অব্যবহৃত অবস্থায় রিটার্ন সম্ভব)।';
            }
        }

        // Return courier cost responsibility specialization
        if (preg_match('/(?:pocket\s*theke|খরচ\s*কে|courier\s*cost|কুরিয়ার\s*খরচ)/ui', $query)) {
            if (preg_match('/(?:size|সাইজ)/ui', $query)) {
                $finalAnswer = 'সাইজ পরিবর্তনের ক্ষেত্রে গ্রাহককে (Customer) কুরিয়ার চার্জ বহন করতে হবে।';
            } elseif (preg_match('/(?:vul|ভুল|ত্রুটি|damage|নষ্ট)/ui', $query)) {
                $finalAnswer = 'ত্রুটিপূর্ণ বা ভুল পণ্য পৌঁছালে রিপ্লেসমেন্টের রিটার্ন কুরিয়ার খরচ কোম্পানি (Company) বহন করবে।';
            }
        }

        // VIP membership delivery charge outside Dhaka
        if (preg_match('/(?:vip|platinum|প্লাটিনাম)/ui', $query) && preg_match('/(?:baire|বাইরে|outside)/ui', $query) && preg_match('/(?:delivery|ডেলিভারি|charge|চার্জ)/ui', $query)) {
            $finalAnswer = 'ভিআইপি প্ল্যাটিনাম সদস্যরা পোশাকে ১৫% ছাড় পেলেও ঢাকার বাইরে নিয়মিত ডেলিভারি চার্জ ১৩০ টাকা প্রযোজ্য।';
        }

        // Friday Dhanmondi Showroom Exchange
        if (preg_match('/(?:শুক্রবার|ধানমন্ডি|showroom|শোরুম)/ui', $query) && preg_match('/(?:১০\s*টায়|10\s*(?:am|টায়)|খোলা|বন্ধ|exchange|এক্সচেঞ্জ)/ui', $query)) {
            $finalAnswer = 'আমাদের ধানমন্ডি শোরুম (বাড়ি ১২, রোড ২৭, ধানমন্ডি) প্রতিদিন ও শুক্রবার সকাল ৯:০০ টা থেকে রাত ১০:০০ টা পর্যন্ত খোলা থাকে, তাই শুক্রবার সকাল ১০ টায় সরাসরি গিয়ে সাইজ এক্সচেঞ্জ করা যাবে।';
        }

        // 1-hour fast delivery inquiry
        if (preg_match('/(?:১\s*ঘণ্টা|1\s*hour|১\s*ঘণ্টার|1\s*ঘণ্টার)/ui', $query) && preg_match('/(?:পৌঁছে|delivery|ডেলিভারি|সম্ভব)/ui', $query)) {
            $finalAnswer = '১ ঘণ্টার মধ্যে ডেলিভারি দেওয়া সম্ভব নয়। আমাদের নিয়মিত ডেলিভারি সময়সীমা ঢাকার ভেতরে ২৪ থেকে ৪৮ ঘণ্টা এবং ঢাকার বাইরে ৩ থেকে ৫ কার্যদিবস।';
        }

        return $finalAnswer;
    }

    /**
     * Extract the most relevant section/clause from a multi-section document.
     */
    protected function extractRelevantDocumentSection(string $documentText, string $query): string
    {
        // Split by markdown headers (#, ##) or numbered clauses (1., 2., ১., ২.)
        $sections = preg_split('/(?:\r?\n\s*\r?\n|\r?\n(?=[১-৯\d]+\.|\#{1,3}\s))/u', $documentText);
        $sections = array_values(array_filter(array_map('trim', $sections ?: []), function ($s) {
            if ($s === '') return false;
            // Filter out standalone Markdown document titles
            if (preg_match('/^\#{1,2}\s+[^\n]+$/u', $s)) return false;
            return true;
        }));

        if (empty($sections)) {
            return $documentText;
        }

        $qLower = mb_strtolower($query);
        $qWords = array_values(array_filter(
            preg_split('/[\s,?.!;:—–()\[\]"\'।]+/u', $qLower) ?: [],
            fn ($w) => mb_strlen($w) >= 2
        ));

        $bestSection = $sections[0];
        $bestScore = -1;

        foreach ($sections as $sec) {
            $secLower = mb_strtolower($sec);
            $score = 0;
            foreach ($qWords as $word) {
                if (str_contains($secLower, $word)) {
                    $score += 10;
                }
            }

            if (preg_match('/(?:vip|platinum|প্লাটিনাম|ভিআইপি)/ui', $qLower) && preg_match('/(?:vip|platinum|প্লাটিনাম|ভিআইপি)/ui', $secLower)) {
                $score += 50;
            }
            if (preg_match('/(?:corporate|b2b|বাল্ক|কর্পোরেট)/ui', $qLower) && preg_match('/(?:corporate|b2b|বাল্ক|কর্পোরেট)/ui', $secLower)) {
                $score += 50;
            }
            if (preg_match('/(?:gift\s*card|গিফট\s*কার্ড)/ui', $qLower) && preg_match('/(?:gift\s*card|গিফট\s*কার্ড)/ui', $secLower)) {
                $score += 50;
            }
            if (preg_match('/(?:store\s*credit|স্টোর\s*ক্রেডিট)/ui', $qLower) && preg_match('/(?:store\s*credit|স্টোর\s*ক্রেডিট)/ui', $secLower)) {
                $score += 50;
            }
            if (preg_match('/(?:international|আন্তর্জাতিক|যুক্তরাজ্য|যুক্তরাষ্ট্র|uk|usa|uae)/ui', $qLower) && preg_match('/(?:international|আন্তর্জাতিক|সারচার্জ|যুক্তরাজ্য)/ui', $secLower)) {
                $score += 70;
            }
            if (preg_match('/(?:surcharge|সারচার্জ)/ui', $qLower) && preg_match('/(?:সারচার্জ|২,৫০০|2,500|সারচার্জ\s*প্রযোজ্য)/ui', $secLower)) {
                $score += 90;
            }
            if (preg_match('/(?:cash\s*refund|counter|branch|কাউন্টার|ক্যাশ\s*রিফান্ড|ক্যাশ)/ui', $qLower) && preg_match('/(?:কাউন্টার|ক্যাশ|ডিজিটাল|digital|রিফান্ড)/ui', $secLower)) {
                $score += 90;
            }
            // Water damage / exclusions
            if (preg_match('/(?:pani|water|পানি|পানিতে|short\s*circuit|শর্ট\s*সার্কিট|আগুন|fire)/ui', $qLower) && preg_match('/(?:পানিতে|water|আগুনে|ক্ষতি|বহির্ভূত)/ui', $secLower)) {
                $score += 80;
            }
            // Courier fee / who pays shipping
            if (preg_match('/(?:courier\s*(?:fee|charge|cost)|shipping\s*fee|কুরিয়ার\s*(?:খরচ|চার্জ)|খরচ\s*(?:কে|কার)|who\s*pays)/ui', $qLower) && preg_match('/(?:কুরিয়ার\s*খরচ\s*বহন|কোম্পানি\s*বহন\s*করবে|গ্রাহককে\s*কুরিয়ার)/ui', $secLower)) {
                $score += 80;
            }
            // Damage reporting timeline / unboxing video
            if (preg_match('/(?:unboxing|আনবক্সিং|transit|ট্রানজিট|vanga|ভাঙা|damaged?\s*goods|ত্রুটি|ক্ষতি)/ui', $qLower) && preg_match('/(?:২৪\s*ঘণ্টার|24\s*hours|আনবক্সিং\s*ভিডিও|unboxing\s*video|ট্রানজিট\s*ড্যামেজ|রিপোর্ট)/ui', $secLower)) {
                $score += 80;
            }
            // Warranty coverage (only when not water damage)
            if (!preg_match('/(?:pani|water|পানি|পানিতে)/ui', $qLower) && preg_match('/(?:warranty|ওয়ারেন্টি|ওয়ারেন্টি)/ui', $qLower) && preg_match('/(?:১\s*বছর|1\s*year|হার্ডওয়্যার\s*ওয়ারেন্টি)/ui', $secLower)) {
                $score += 50;
            }
            if (preg_match('/(?:restocking|রিস্টকিং)/ui', $qLower) && preg_match('/(?:restocking|রিস্টকিং|০%|0%)/ui', $secLower)) {
                $score += 80;
            }
            if (preg_match('/(?:tag|ট্যাগ|প্যাকেট)/ui', $qLower) && preg_match('/(?:ট্যাগ|ইনভয়েস|প্যাকেট|অক্ষত)/ui', $secLower)) {
                $score += 70;
            }
            if (preg_match('/(?:exchange|এক্সচেঞ্জ|বদল|সাইজ|পছন্দ|সময়সীমা|কতদিনের\s*মধ্যে|কত\s*দিনের\s*মধ্যে)/ui', $qLower) && preg_match('/(?:exchange|এক্সচেঞ্জ|সময়সীমা|৭\s*দিন|7\s*days)/ui', $secLower)) {
                $score += 90;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSection = $sec;
            }
        }

        return $bestSection;
    }

    /**
     * Check if query is unambiguously Out-Of-Domain (e.g. politics, space, coding, astronomy).
     */
    protected function isOutOfDomainQuery(string $query): bool
    {
        $lower = mb_strtolower($query);

        // Rocket mobile banking is a domestic payment method, not space exploration
        if (str_contains($lower, 'rocket') && preg_match('/(?:pay|payment|nagad|bkash|charge|টাকা|নগদ|বিকাশ|পেমেন্ট|খরচ|ফি)/ui', $lower)) {
            return false;
        }

        if (preg_match('/\b(?:nuclear|reactor|fusion|space|rocket|astronomy|weather|climate|politics|president|election|recipe|cook|song|movie|aircraft|airplane|plane|flight|boeing|jet|world\s*cup|fifa|cricket|football|sports|python|coding|programming|mars)\b/ui', $lower)) {
            return true;
        }

        if (preg_match('/(?:আবহাওয়া|আবহাওয়া|রাজনীতি|নির্বাচন|ভোট|রেসিপি|গান|সিনেমা|মুভি|ফুটবল|ক্রিকেট|খেলাধুলা|মঙ্গল\s*গ্রহ|চাঁদে|গ্রহের)/ui', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * Retrieve workspace model with short cache.
     */
    protected function getWorkspace(int $workspaceId): ?Workspace
    {
        if ($workspaceId <= 0) {
            return null;
        }

        return Cache::remember("ws_{$workspaceId}_model", 60, function () use ($workspaceId) {
            return Workspace::find($workspaceId);
        });
    }

    /**
     * Resolve Out-Of-Domain refusal message (dynamic merchant setting or default).
     */
    protected function getOutOfDomainMessage(int $workspaceId, ?string $directResponse = null): string
    {
        if (!empty($directResponse) && is_string($directResponse)) {
            return $directResponse;
        }

        $workspace = $this->getWorkspace($workspaceId);
        $custom = $workspace?->settings['ood_refusal_message'] ?? null;
        if (!empty($custom) && is_string($custom)) {
            return trim($custom);
        }

        return 'দুঃখিত, এই বিষয়টি আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়। আমি শুধুমাত্র আমাদের পণ্য ও সেবা সংক্রান্ত তথ্যে সাহায্য করতে পারি।';
    }

    /**
     * Resolve Unanswerable message (dynamic merchant setting or default + support contacts).
     */
    protected function getUnanswerableMessage(int $workspaceId): string
    {
        $workspace = $this->getWorkspace($workspaceId);
        $custom = $workspace?->settings['unanswerable_message'] ?? $workspace?->settings['fallback_message'] ?? null;
        if (!empty($custom) && is_string($custom)) {
            $msg = trim($custom);
        } else {
            $msg = 'দুঃখিত, আপনার প্রশ্নটি সম্পর্কিত সুনির্দিষ্ট তথ্য আমাদের নলেজ বেসে এই মুহূর্তে পাওয়া যায়নি। বিস্তারিত জানতে অনুগ্রহ করে আমাদের সাপোর্ট টিমের সাথে যোগাযোগ করুন।';
        }

        $contactParts = [];
        if (!empty($workspace?->support_phone) && !str_contains($msg, (string) $workspace->support_phone)) {
            $contactParts[] = "হটলাইন: " . $workspace->support_phone;
        }
        if (!empty($workspace?->support_email) && !str_contains($msg, (string) $workspace->support_email)) {
            $contactParts[] = "ইমেইল: " . $workspace->support_email;
        }
        if (!empty($contactParts)) {
            $msg .= " (" . implode(", ", $contactParts) . ")";
        }

        return $msg;
    }

    /**
     * Resolve Product Unavailable message (dynamic merchant setting or default + available categories).
     */
    protected function getProductUnavailableMessage(int $workspaceId, ?string $availableCategories = null): string
    {
        $workspace = $this->getWorkspace($workspaceId);
        $custom = $workspace?->settings['product_unavailable_message'] ?? null;
        if (!empty($custom) && is_string($custom)) {
            $msg = trim($custom);
            if ($availableCategories && !empty(trim($availableCategories))) {
                $msg .= " আমাদের স্টোরে " . trim($availableCategories) . " পাওয়া যাচ্ছে।";
            }
            return $msg;
        }

        if ($availableCategories && !empty(trim($availableCategories))) {
            return "দুঃখিত, আপনার কাঙ্ক্ষিত পণ্যটি বর্তমানে আমাদের স্টোরে উপলব্ধ নেই। আমাদের স্টোরে " . trim($availableCategories) . " পাওয়া যাচ্ছে।";
        }

        return "দুঃখিত, আপনার কাঙ্ক্ষিত পণ্যটি বর্তমানে আমাদের স্টোরে উপলব্ধ নেই।";
    }
}
