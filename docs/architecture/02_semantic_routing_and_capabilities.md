# Semantic Routing & Capabilities Guide

## Core Principle: Intent-Driven Routing

Routing decisions are determined strictly by the **Semantic Intent** of the user query, **NEVER by database table names or database prefixes**.

```
User Query ──► Semantic Intent ──► Route Capability ──► Appropriate Data Source
```

---

## The 6 Route Capabilities

| Capability | Intent & Scope | Primary Data Sources | Execution Behavior |
| :--- | :--- | :--- | :--- |
| **`KNOWLEDGE`** | Product specifications, prices, colors, sizes, stock, warranty, company policies, shipping rates, return FAQs. | Vector FAQ (Layer 2) + `AnalyticsProduct` relational catalog (Layer 3). | Generates grounded answer via `KnowledgeSupportAgent`. |
| **`ANALYTICS`** | Business metrics, sales aggregations, revenue, cash collection, dues, salesperson performance and rankings. | Python Analytics Text-to-SQL Service querying MySQL `analytics_*` tables. | Returns structured analytical tables and summaries. LLM never runs unconstrained raw SQL. |
| **`CHAT`** | Conversational chitchat, greetings, capability questions, and **conversational purchase intent** (e.g. "এই Laptop Pro 15 টা নিতে চাই"). | Conversation history. | `ConversationalSupportAgent` collects Name, Phone, Delivery Address and provides a summary. **No order mutation occurs**. CRM extractor asynchronously stores lead in `CRMContact`. |
| **`ACTION`** | Explicit imperative requests to notify a specific salesperson/seller (e.g. "Rahim-কে মেইল পাঠাও"). | `AnalyticsSalesperson` directory. | Two-phase lifecycle: Turn N proposes action with deterministic SHA-256 fingerprint; Turn N+1 executes on verified user confirmation. |
| **`UNCERTAIN`** | Ambiguous or vague queries requiring user clarification, or blocked mutation commands (e.g. "delete customer 5"). | Clarification options registry. | Emits interactive clarification chips or safe policy rejection. |
| **`OOD`** | Queries completely outside commercial or support domain (weather, politics, generic coding). | None. | Deterministic out-of-domain safe fallback. |

---

## Production vs. Research Routers

- **Production Control Router**: `App\AI\Routing\HybridRouter` (Fast LLM Semantic Router v2.2 with DeepSeek JSON output).
- **Frozen Research / Benchmark Adapter**: `App\AI\Routing\LangChainRouter` (Offline benchmark harness against LangGraph Python service).
