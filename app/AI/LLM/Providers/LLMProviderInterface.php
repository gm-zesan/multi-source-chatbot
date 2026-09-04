<?php

declare(strict_types=1);

namespace App\AI\LLM\Providers;

use App\AI\LLM\LLMRequest;
use App\AI\LLM\LLMResponse;
use App\AI\LLM\ProviderCapabilities;

interface LLMProviderInterface
{
    /**
     * Send a standardized LLM request and return a standardized response.
     */
    public function send(LLMRequest $request): LLMResponse;

    /**
     * Get the capabilities descriptor of this provider.
     */
    public function capabilities(): ProviderCapabilities;

    /**
     * Get the provider's identifier name (e.g. 'deepseek', 'openrouter', 'openai').
     */
    public function getName(): string;
}
