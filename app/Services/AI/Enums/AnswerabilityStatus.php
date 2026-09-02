<?php

declare(strict_types=1);

namespace App\Services\AI\Enums;

/**
 * Tri-state decision enum for the Semantic Answerability Gate (Phase 3).
 *
 * - CONFIDENT: Authoritative company knowledge is present and aligned; answering authorized.
 * - AMBIGUOUS: Query appears in-domain, but intent is underspecified or evidence is competing; clarification prompt required.
 * - UNANSWERABLE: Query is out-of-domain or evidence is insufficient; safe polite fallback enforced.
 */
enum AnswerabilityStatus: string
{
    case CONFIDENT = 'confident';
    case AMBIGUOUS = 'ambiguous';
    case UNANSWERABLE = 'unanswerable';
}
