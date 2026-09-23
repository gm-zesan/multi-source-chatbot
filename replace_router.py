import re

with open('/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php', 'r') as f:
    content = f.read()

# We want to keep checkPendingActionState and extractEntities.
# Wait, let's just write the new file from scratch and inject the old checkPendingActionState.

match = re.search(r'(private function checkPendingActionState.*?return null;\n    })', content, re.DOTALL)
check_pending_code = match.group(1) if match else ""

match = re.search(r'(private function extractEntities.*?return \$entities;\n    })', content, re.DOTALL)
extract_entities_code = match.group(1) if match else """
    private function extractEntities(string $text): array
    {
        $entities = [];
        if (preg_match('/(?:order|invoice|bill)\s*(?:id|no|#)?\s*(#?\d{3,10})/ui', $text, $matches) || preg_match('/#(\d{3,10})/', $text, $matches)) {
            $entities['order_id'] = str_replace('#', '', $matches[1]);
        }
        if (preg_match('/ticket\s*(?:id|no|#)?\s*(#?\d{3,10})/ui', $text, $matches)) {
            $entities['ticket_id'] = str_replace('#', '', $matches[1]);
        }
        return $entities;
    }
"""

new_content = f"""<?php

declare(strict_types=1);

namespace App\AI\Routing;

use App\Models\Conversation;
use App\AI\LLM\LLMClient;
use App\AI\LLM\LLMRequest;
use Illuminate\Support\Facades\Log;

class HybridRouter
{{
    public const DEFAULT_CONFIDENCE_THRESHOLD = 0.70;
    private LLMClient \$llmClient;

    public function __construct(
        private readonly float \$confidenceThreshold = self::DEFAULT_CONFIDENCE_THRESHOLD,
        ?LLMClient \$llmClient = null
    ) {{
        \$this->llmClient = \$llmClient ?? app(LLMClient::class);
    }}

    /**
     * Route an incoming user query to the appropriate capability using a Fast LLM Semantic Router.
     */
    public function route(
        string \$query,
        ?Conversation \$conversation = null,
        ?int \$workspaceId = null,
    ): RoutingResult {{
        \$t_start = microtime(true);
        \$cleanQuery = trim(\$query);

        if (\$cleanQuery === '') {{
            \$latency = round((microtime(true) - \$t_start) * 1000, 2);
            return new RoutingResult(
                route: RouteType::CHAT,
                confidence: 1.0,
                intent: 'empty_query',
                signals: [
                    'layer'             => 'layer0_empty',
                    'normalized_query'  => '',
                    'router_latency_ms' => \$latency,
                ],
                routerLatencyMs: \$latency,
            );
        }}

        \$normalized = \$this->normalizeText(\$cleanQuery);

        // ── 0. Layer 0: Multi-Turn Pending Action State First ────────────────
        \$pendingActionResult = \$this->checkPendingActionState(\$cleanQuery, \$normalized, \$conversation);
        if (\$pendingActionResult !== null) {{
            \$latency = round((microtime(true) - \$t_start) * 1000, 2);
            return new RoutingResult(
                route: \$pendingActionResult['route'],
                confidence: \$pendingActionResult['confidence'],
                intent: \$pendingActionResult['intent'],
                signals: array_merge([
                    'layer'             => 'layer0_pending_action',
                    'normalized_query'  => \$normalized,
                    'router_latency_ms' => \$latency,
                ], \$pendingActionResult['signals']),
                entities: \$pendingActionResult['entities'],
                routerLatencyMs: \$latency,
            );
        }}

        // ── 1. Fast LLM Semantic Router ──────────────────────────────────────
        \$systemPrompt = <<<PROMPT
You are a fast, highly accurate semantic router for a multi-tenant business chatbot.
Your strictly single purpose is to classify the user's intent into exactly ONE of the following Route Types.

[ROUTE TYPES]
- CHAT: Pure conversational chitchat, greetings, gratitude, pleasantries, or generic capabilities questions (e.g. "hi", "how are you", "what can you do").
- KNOWLEDGE: Questions about company policies, pricing, guides, FAQ, or general information seeking (e.g. "how do I cancel?", "what is the refund policy?", "shipping charge koto?").
- ACTION: Explicit imperative commands to mutate state, like modifying an order, tracking a specific order/shipment with an ID, creating a ticket (e.g. "cancel my order #123", "track shipment").
- ANALYTICS: Queries asking for business metrics, performance, cash-in, sales, dues, or leaderboard data (e.g. "ajke koto sale holo?", "top 3 buyers dao", "Rahim er taka koto?").
- UNCERTAIN: Vague, highly ambiguous queries, or single keywords lacking context (e.g. "cancel" (without saying what), "bill", "change").
- OOD: Out of domain queries completely unrelated to e-commerce, customer support or business metrics (e.g. weather, politics, recipes, code generation).

[STRICT RULES]
1. You MUST NOT generate SQL.
2. You MUST NOT execute tools.
3. You MUST NOT answer the user's question.
4. ONLY return a JSON object with exactly three keys: 'route', 'confidence', 'reason'.
5. The 'route' MUST be one of: "CHAT", "KNOWLEDGE", "ACTION", "ANALYTICS", "UNCERTAIN", "OOD".
6. The 'confidence' MUST be a float between 0.0 and 1.0.
7. The 'reason' MUST be a short string explaining why you chose this route.
PROMPT;

        \$request = LLMRequest::fromPrompt(
            prompt: \$cleanQuery,
            systemPrompt: \$systemPrompt,
            model: config('ai.default_model', 'deepseek-flash'),
            temperature: 0.0,
            maxTokens: 100,
        );
        \$request->responseFormat = ['type' => 'json_object'];

        try {{
            \$response = \$this->llmClient->generate(\$request);
            \$content = \$response->content;
            
            // Extract JSON
            \$jsonStart = strpos(\$content, '{{');
            \$jsonEnd = strrpos(\$content, '}}');
            if (\$jsonStart !== false && \$jsonEnd !== false) {{
                \$content = substr(\$content, \$jsonStart, \$jsonEnd - \$jsonStart + 1);
            }}
            
            \$result = json_decode(\$content, true) ?? [];
            
            \$routeStr = strtoupper(\$result['route'] ?? 'KNOWLEDGE');
            \$confidence = (float) (\$result['confidence'] ?? 0.85);
            \$reason = \$result['reason'] ?? 'LLM Default Fallback';
            
            \$route = match(\$routeStr) {{
                'CHAT' => RouteType::CHAT,
                'KNOWLEDGE' => RouteType::KNOWLEDGE,
                'ACTION' => RouteType::ACTION,
                'ANALYTICS' => RouteType::ANALYTICS,
                'UNCERTAIN' => RouteType::UNCERTAIN,
                'OOD' => RouteType::OOD,
                default => RouteType::KNOWLEDGE,
            }};

            // Safety Gate: If confidence is below threshold and candidate is ACTION, demote to UNCERTAIN
            if (\$confidence < \$this->confidenceThreshold && \$route === RouteType::ACTION) {{
                \$route = RouteType::UNCERTAIN;
            }}

            \$latency = round((microtime(true) - \$t_start) * 1000, 2);

            return new RoutingResult(
                route: \$route,
                confidence: \$confidence,
                intent: 'llm_semantic_route',
                signals: [
                    'layer'             => 'layer1_llm_router',
                    'normalized_query'  => \$normalized,
                    'router_latency_ms' => \$latency,
                    'llm_reason'        => \$reason,
                    'llm_model'         => \$response->model ?? 'unknown',
                    'provider'          => \$response->provider ?? 'unknown',
                ],
                entities: \$this->extractEntities(\$cleanQuery),
                routerLatencyMs: \$latency,
            );

        }} catch (\Throwable \$e) {{
            Log::error("[HybridRouter] LLM routing failed: " . \$e->getMessage());
            
            \$latency = round((microtime(true) - \$t_start) * 1000, 2);
            return new RoutingResult(
                route: RouteType::KNOWLEDGE, // Safe fallback
                confidence: 0.5,
                intent: 'llm_routing_error_fallback',
                signals: [
                    'layer'             => 'layer1_llm_router_error',
                    'normalized_query'  => \$normalized,
                    'router_latency_ms' => \$latency,
                    'error'             => \$e->getMessage(),
                ],
                entities: \$this->extractEntities(\$cleanQuery),
                routerLatencyMs: \$latency,
            );
        }}
    }}

    /**
     * Normalize text for basic preprocessing.
     */
    private function normalizeText(string \$query): string
    {{
        \$text = mb_strtolower(trim(\$query));
        \$text = preg_replace('/[^\p{{L}}\p{{M}}\p{{N}}\s\?#]/u', ' ', \$text);
        \$text = preg_replace('/\s+/', ' ', (string) \$text);
        return trim((string) \$text);
    }}

{check_pending_code}

{extract_entities_code}
}}
"""

with open('/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php', 'w') as f:
    f.write(new_content)

print("Updated HybridRouter.php")
