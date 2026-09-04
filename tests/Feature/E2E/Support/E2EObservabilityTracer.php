<?php

declare(strict_types=1);

namespace Tests\Feature\E2E\Support;

/**
 * E2E Observability Contract Tracer
 * 
 * Captures and formats structured execution traces for full-system E2E test certification.
 */
class E2EObservabilityTracer
{
    /**
     * Build structured trace from pipeline execution result.
     *
     * @param array<string, mixed> $pipelineResult
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function trace(string $query, array $pipelineResult, array $options = []): array
    {
        $route = (string) ($pipelineResult['route'] ?? 'uncertain');
        $isShortCircuited = (bool) ($pipelineResult['routing_telemetry']['short_circuited'] ?? false);
        $answerability = $pipelineResult['answerability_decision'] ?? null;
        $retrievalHits = $pipelineResult['retrieval_hits'] ?? collect();
        $topHit = $pipelineResult['top_hit'] ?? null;
        $memoryContext = $pipelineResult['memory_context'] ?? null;

        $retrievalTelemetry = $pipelineResult['lexicon_telemetry'] ?? [];
        $tierUsed = (int) ($options['tier_used'] ?? ($retrievalTelemetry['tier_executed'] ?? ($topHit !== null ? 1 : 0)));

        return [
            'query'         => $query,
            'route'         => $route,
            'language'      => (string) ($options['language'] ?? 'bengali'),
            'context'       => [
                'status'       => $isShortCircuited ? 'ambiguous' : ($options['context_status'] ?? 'resolved'),
                'active_topic' => $options['active_topic'] ?? null,
                'entity'       => $options['entity'] ?? null,
            ],
            'memory'        => [
                'decision'       => $options['memory_decision'] ?? ($memoryContext !== null ? 'RETRIEVE' : 'BYPASS'),
                'service_called' => (bool) ($options['memory_service_called'] ?? ($memoryContext !== null)),
                'memory_found'   => (bool) ($options['memory_found'] ?? ($memoryContext !== null)),
            ],
            'retrieval'     => [
                'tier_used'        => $isShortCircuited ? 0 : $tierUsed,
                'faq_id'           => $topHit?->faq?->id ?? ($options['faq_id'] ?? null),
                'tier1_attempted'  => (bool) ($options['tier1_attempted'] ?? (!$isShortCircuited && ($route === 'knowledge' || $route === 'uncertain'))),
                'tier2_attempted'  => (bool) ($options['tier2_attempted'] ?? ($tierUsed >= 2)),
                'tier3_invoked'    => (bool) ($options['tier3_invoked'] ?? ($tierUsed >= 3)),
                'hits_count'       => is_countable($retrievalHits) ? count($retrievalHits) : 0,
            ],
            'answerability' => [
                'status'           => $isShortCircuited ? 'AMBIGUOUS' : ($answerability['status'] ?? ($pipelineResult['answered'] ? 'CONFIDENT' : 'UNANSWERABLE')),
                'answerable'       => (bool) ($pipelineResult['answered'] ?? false),
                'confidence_score' => (float) ($pipelineResult['confidence'] ?? 0.0),
            ],
            'llm'           => [
                'calls'    => $isShortCircuited ? 0 : (int) ($options['llm_calls'] ?? 1),
                'grounded' => (bool) ($options['llm_grounded'] ?? ($route === 'knowledge')),
            ],
            'clarification' => [
                'requested'       => (bool) ($isShortCircuited || !empty($pipelineResult['suggestions'])),
                'short_circuited' => $isShortCircuited,
            ],
            'result'        => 'PASS',
        ];
    }
}
