<?php

declare(strict_types=1);

namespace App\AI\Decision\Contracts;

use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;

/**
 * Unified Decision Engine Contract.
 *
 * Consolidates intent classification, capability detection, and tool planning
 * into a single authoritative decision-making interface.
 */
interface DecisionEngineInterface
{
    /**
     * Generate a structured, schema-validated decision for the incoming user request.
     *
     * @param DecisionRequest $request Preflight-evaluated request container
     * @return DecisionResult Schema-validated decision object
     */
    public function decide(DecisionRequest $request): DecisionResult;
}

