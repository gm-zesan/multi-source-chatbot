<?php

declare(strict_types=1);

namespace App\AI\Routing;

class RoutingResult
{
    /**
     * @param array<string, mixed> $signals
     * @param array<string, mixed> $entities
     */
    public function __construct(
        public readonly RouteType $route,
        public readonly float $confidence,
        public readonly ?string $intent = null,
        public readonly array $signals = [],
        public readonly array $entities = [],
        public readonly float $routerLatencyMs = 0.0,
        public readonly bool $isFallback = false,
    ) {}

    public function isKnowledge(): bool
    {
        return $this->route === RouteType::KNOWLEDGE;
    }

    public function isChat(): bool
    {
        return $this->route === RouteType::CHAT;
    }

    public function isAction(): bool
    {
        return $this->route === RouteType::ACTION;
    }

    public function isOod(): bool
    {
        return $this->route === RouteType::OOD;
    }

    public function isUncertain(): bool
    {
        return $this->route === RouteType::UNCERTAIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'route'             => $this->route->value,
            'confidence'        => $this->confidence,
            'intent'            => $this->intent,
            'signals'           => $this->signals,
            'entities'          => $this->entities,
            'router_latency_ms' => $this->routerLatencyMs,
            'is_fallback'       => $this->isFallback,
        ];
    }
}
