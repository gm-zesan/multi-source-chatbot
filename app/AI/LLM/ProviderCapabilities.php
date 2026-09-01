<?php

declare(strict_types=1);

namespace App\AI\LLM;

class ProviderCapabilities
{
    public function __construct(
        public bool $supportsToolCalling = true,
        public bool $supportsJsonMode = true,
        public bool $supportsSystemPrompt = true,
        public int $maxContextWindow = 64000,
    ) {}
}
