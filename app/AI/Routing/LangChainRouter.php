<?php

declare(strict_types=1);

namespace App\AI\Routing;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LangChainRouter
{
    private string $baseUrl;
    
    public function __construct()
    {
        // For local development with Python LangChain Service on 8003
        $this->baseUrl = 'http://127.0.0.1:8003';
    }

    public function route(
        string $query,
        ?Conversation $conversation = null,
        ?int $workspaceId = null,
        ?string $customerId = null
    ): RoutingResult {
        $startTime = microtime(true);
        try {
            $messages = [];
            
            // Build conversation history context
            if ($conversation) {
                $messages = $conversation->messages()
                    ->latest('id')
                    ->take(3) // take last 3 for context
                    ->get()
                    ->reverse()
                    ->map(fn($m) => [
                        'role' => $m->direction === 'inbound' ? 'user' : 'assistant',
                        'body' => (string) $m->body
                    ])
                    ->values()
                    ->toArray();
            }
            
            // Always append the current query
            $messages[] = [
                'role' => 'user',
                'body' => $query
            ];

            // Make HTTP call to python-langchain-service
            $response = Http::withOptions(['proxy' => ''])->timeout(10)->post("{$this->baseUrl}/route", [
                'messages' => $messages,
                'workspace_id' => $workspaceId ?? 1,
                'customer_id' => $customerId ?? 'guest'
            ]);

            $latency = (microtime(true) - $startTime) * 1000;

            if ($response->successful()) {
                $data = $response->json();
                
                $routeType = RouteType::tryFrom(strtolower($data['route'] ?? 'uncertain')) ?? RouteType::UNCERTAIN;
                $confidence = (float) ($data['confidence'] ?? 0.0);
                
                $routingResult = new RoutingResult(
                    route: $routeType,
                    confidence: $confidence,
                    securityStatus: $data['security_status'] ?? 'allowed',
                    ambiguityType: $data['ambiguity_type'] ?? null,
                    routerLatencyMs: $latency,
                    agentExecution: [
                        'final_answer' => $data['final_answer'] ?? null,
                        'tool_calls' => $data['tool_calls'] ?? [],
                        'iterations' => $data['iterations'] ?? 0,
                    ]
                );
                
                return $routingResult;
            }

            Log::warning('[LangChainRouter] Python service returned error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

        } catch (\Throwable $e) {
            Log::error('[LangChainRouter] Exception calling Python service', [
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to uncertain
        return new RoutingResult(
            route: RouteType::UNCERTAIN,
            confidence: 0.0
        );
    }
}
