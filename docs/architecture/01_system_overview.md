# System Architecture Overview

This document provides a concise map of the Enterprise E-Commerce/F-Commerce AI Chatbot Orchestrator.

## Architectural Layers

```
[Inbound Channel / Webhook / Simulator]
                    │
                    ▼
  [App\Http\Controllers\ChatSimulatorController / WebhookController]
                    │
                    ▼
     [App\Services\AI\CustomerSupportService] (Orchestrator)
                    │
   ┌────────────────┼────────────────┬────────────────┐
   ▼                ▼                ▼                ▼
[HybridRouter] [AI Agents]     [Domain Services] [Safety / Policy]
- Fast LLM      - Knowledge     - CRM / Leads     - ActionSafety
  Router v2.2     Agent         - FAQ / Search    - SemanticGate
- Layer 0 State - Conversational - Graph Memory   - FollowUpPolicy
  Precedence      Agent         - Business SOT
   │                │                │                │
   └────────────────┴────────────────┴────────────────┘
                    │
                    ▼
      [Infrastructure & Transport]
- App\AI\LLM\LLMClient (GenericProvider -> DeepSeek / OpenRouter / OpenAI)
- App\Services\Retrieval\RetrievalClient (Typesense / FastAPI)
- App\Services\Analytics\AnalyticsClient (Text-to-SQL FastAPI)
- App\Services\Memory\ConversationMemoryClient (FastAPI / Neo4j KGM)
```

## Namespace Conventions

- **`app/AI/`**: AI Engine primitives and LLM internals:
  - `app/AI/Routing/`: Semantic intent classification (`HybridRouter`, `RouteType`, `RoutingResult`).
  - `app/AI/Agents/`: Domain LLM agents (`KnowledgeSupportAgent`, `ConversationalSupportAgent`).
  - `app/AI/LLM/`: Provider abstractions (`LLMClient`, `GenericProvider`, `LLMRequest`, `LLMResponse`).
  - `app/AI/Tools/`: Agent executable tools (`KnowledgeRetrievalTool`, `BusinessAnalyticsTool`, `ExcelAnalyticsTool`).

- **`app/Services/AI/`**: Application-level AI orchestration, security policies, and gating:
  - `CustomerSupportService`: Main dialogue lifecycle coordinator.
  - `ActionSafetyService`: Two-phase confirmation safety and parameter fingerprinting.
  - `SemanticAnswerabilityGate`: Confidence & similarity evaluation on retrieved knowledge.
  - `FollowUpPolicyManager`: Context-sensitive CTA follow-up rules.
  - `SellerEmailService`: Execution of verified seller email action.

- **`app/Services/Business/`**:
  - `BusinessSourceOfTruthService`: Authoritative Layer 3 structured data provider (Products, Orders, Customers) for live grounding.
