# Conversation Graph Memory: Architecture Specification

**Component**: `conversation-memory`  
**Version**: 1.0.0-draft  
**Status**: Specification Phase (No code changes to retrieval)  
**Core Technologies**: Graphiti, Neo4j, FastAPI (Python), Laravel (PHP)

---

## 1. Executive Summary & Core Paradigm

The **Conversation Graph Memory** system replaces traditional flat message-buffer history (`last 10 messages`) as the primary long-term conversational memory mechanism for multi-channel customer interactions.

> **Fundamental Architectural Separation**:  
> - **Graphiti + Neo4j** remembers conversational context, personal preferences, and historical interaction relationships.  
> - **Typesense** searches static business policies, FAQs, and product catalogs (< 40ms).  
> - **CRM & Order DB/APIs** provide authoritative real-time ground truth.  
> - **LLM Gateway** reasons, synthesizes, and generates natural responses.

---

## 2. High-Level Component Topology

```text
                           CUSTOMER INBOUND QUERY
                                     │
                                     ▼
                            ┌─────────────────┐
                            │     Laravel     │
                            │ Application/API │
                            └────────┬────────┘
                                     │
                                     ▼
                            ┌─────────────────┐
                            │  HybridRouter   │
                            └────────┬────────┘
                                     │
                 ┌───────────────────┼───────────────────┐
                 │                   │                   │
                 ▼                   ▼                   ▼
               CHAT              KNOWLEDGE              OOD
                 │                   │                   │
                 │                   ▼                   │
                 │           Retrieval Engine            │
                 │                   │                   │
                 │             ┌─────┴─────┐             │
                 │             ▼           ▼             │
                 │         Typesense    Live DB          │
                 │        (FAQ/Policy) (Catalog)         │
                 │                                       │
                 ▼                                       │
      ┌──────────────────────┐                           │
      │ Memory Relevance     │                           │
      │ Gate (Conditional)   │                           │
      └──────────┬───────────┘                           │
                 │                                       │
         ┌───────┴───────┐                               │
         ▼               ▼                               │
      [SKIP]         [RETRIEVE]                          │
   (Generic FAQ)   (Personal Context)                    │
                         │                               │
                         ▼                               │
               ┌───────────────────┐                     │
               │   Python Memory   │                     │
               │      Service      │                     │
               │ (Graphiti + Neo4j)│                     │
               └─────────┬─────────┘                     │
                         │                               │
                         ▼ (Compact Subgraph)            │
               ┌───────────────────┐                     │
               │  Memory Context   │                     │
               └─────────┬─────────┘                     │
                         │                               │
                         └───────────────┬───────────────┘
                                         ▼
                             ┌───────────────────────┐
                             │ Unified Context       │
                             │ Builder               │
                             └───────────┬───────────┘
                                         │
                                         ▼
                             ┌───────────────────────┐
                             │ Provider-Agnostic     │
                             │ LLM Gateway           │
                             │ (DeepSeek/OpenRouter) │
                             └───────────┬───────────┘
                                         │
                                         ▼
                              LIVE OUTBOUND RESPONSE
                                         │
                         (Async Event / Background Job)
                                         │
                                         ▼
                             ┌───────────────────────┐
                             │ Memory Ingestion      │
                             │ Worker (Graphiti)     │
                             └───────────┬───────────┘
                                         │
                                         ▼
                                   Neo4j Graph DB
```

---

## 3. The Three Operating Guarantees

### A. Non-Blocking Async Write Guarantee
- Extracting semantic nodes, temporal states, and edges from dialogue requires LLM entity extraction.
- **Rule**: Memory extraction and graph mutation **never run in the synchronous request path**.
- When an AI response is generated and delivered to the customer, an asynchronous `IngestConversationMemoryJob` is dispatched to the background queue.
- Chat latency remains strictly governed by the fast-path engine (< 50ms for KB hits).

### B. Conditional Read Guarantee (Memory Relevance Gate)
- Queries that are generic business questions (`"What is your refund policy?"`, `"Do you support bKash?"`) do **not** require historical graph memory.
- The `MemoryRelevanceGate` classifies if the query is referential (contains personal pronouns, entity anaphora, cross-session references like *"the same size as before"*).
- If negative, Graph Memory retrieval is **completely bypassed** (0ms latency overhead).

### C. Compact Subgraph Context Guarantee
- The entire customer graph is **never** dumped into the LLM prompt context.
- The retrieval API returns a compact summary of only top-3 to top-5 relevant edges (e.g. `[Customer prefers: size=XL, payment=bKash; previous issue: damaged shirt on Order #1042]`).
- Strict token cap: Maximum 150–250 tokens for memory context.

---

## 4. Multi-Tenancy & Workspace Scoping

Every graph operation strictly enforces tenant isolation:
- `workspace_id`: Tenant boundary (mandatory property on all nodes and edges).
- `customer_id`: Unique identifier of the human shopper.
- `conversation_id`: Identifier of the specific interaction thread.

Cross-workspace traversal is physically prevented by Cypher constraints and query filters (`WHERE n.workspace_id = $workspace_id`).
