<?php

declare(strict_types=1);

namespace App\Services\Memory;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConversationMemoryClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.memory_service.url', 'http://127.0.0.1:8002'), '/');
        $this->timeout = $timeout ?? (int) config('services.memory_service.timeout', 3);
    }

    /**
     * Check if the Python Memory Service is healthy and connected to Neo4j.
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout($this->timeout)->get("{$this->baseUrl}/health");
            if ($response->successful()) {
                $data = $response->json();
                return ($data['status'] ?? '') === 'ok' && ($data['neo4j']['status'] ?? '') === 'connected';
            }
            return false;
        } catch (\Throwable $e) {
            Log::warning('[ConversationMemoryClient] Health check failed', [
                'error' => $e->getMessage(),
                'url'   => "{$this->baseUrl}/health",
            ]);
            return false;
        }
    }

    /**
     * Ingest messages to extract entities, preferences, and temporal relationships.
     */
    public function ingest(
        int $workspaceId,
        string $customerId,
        string $conversationId,
        string $channel,
        array $messages
    ): array {
        try {
            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/memory/ingest", [
                'workspace_id'    => $workspaceId,
                'customer_id'     => $customerId,
                'conversation_id' => $conversationId,
                'channel'         => $channel,
                'messages'        => $messages,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('[ConversationMemoryClient] Ingest returned error response', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return ['success' => false, 'error' => $response->body()];
        } catch (\Throwable $e) {
            Log::warning('[ConversationMemoryClient] Ingest request failed', [
                'error'           => $e->getMessage(),
                'conversation_id' => $conversationId,
                'customer_id'     => $customerId,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Search customer memories for relevant subgraph context.
     */
    public function search(
        int $workspaceId,
        string $customerId,
        string $query,
        int $limit = 5
    ): array {
        try {
            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/memory/search", [
                'workspace_id' => $workspaceId,
                'customer_id'  => $customerId,
                'query'        => $query,
                'limit'        => $limit,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('[ConversationMemoryClient] Search returned error response', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [
                'success'                  => false,
                'has_memories'             => false,
                'memories_count'           => 0,
                'memories'                 => [],
                'formatted_memory_context' => '',
            ];
        } catch (\Throwable $e) {
            Log::warning('[ConversationMemoryClient] Search request failed', [
                'error'       => $e->getMessage(),
                'customer_id' => $customerId,
            ]);
            return [
                'success'                  => false,
                'has_memories'             => false,
                'memories_count'           => 0,
                'memories'                 => [],
                'formatted_memory_context' => '',
            ];
        }
    }

    /**
     * Purge all memories for a customer (GDPR Right to Be Forgotten).
     */
    public function deleteCustomer(int $workspaceId, string $customerId): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['X-Workspace-Id' => (string) $workspaceId])
                ->delete("{$this->baseUrl}/memory/customer/{$customerId}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[ConversationMemoryClient] Delete customer failed', [
                'error'       => $e->getMessage(),
                'customer_id' => $customerId,
            ]);
            return false;
        }
    }

    /**
     * Purge memories from a specific conversation session.
     */
    public function deleteConversation(int $workspaceId, string $conversationId): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['X-Workspace-Id' => (string) $workspaceId])
                ->delete("{$this->baseUrl}/memory/conversation/{$conversationId}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[ConversationMemoryClient] Delete conversation failed', [
                'error'           => $e->getMessage(),
                'conversation_id' => $conversationId,
            ]);
            return false;
        }
    }
}
