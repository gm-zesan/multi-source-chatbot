# Prioritized Improvement Backlog for Existing Features

- **Document ID**: `QUAL-AUDIT-06`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Inspection Date**: October 2026
- **Status**: Actionable Engineering Improvement Plan (Read-Only)

---

## 1. Backlog Prioritization Standard

Every proposed item in this backlog targets an **existing codebase feature**. No new SaaS modules or speculative expansion capabilities are included.

- **`P0` (Critical Defect / Security Blocker)**: Confirmed correctness, data isolation, or critical security vulnerabilities requiring immediate remediation.
- **`P1` (High-Impact Quality Improvement)**: Significant functional gaps, unindexed ingestion, single-points-of-failure, or high latency spikes in existing workflows.
- **`P2` (Meaningful Efficiency & Accuracy Upgrade)**: Enhancements to schema resolution, failure fallbacks, and token reduction.
- **`P3` (Minor Optimization & Telemetry Polish)**: Low-impact tuning and observability dashboard rollups.

---

## 2. Master Prioritized Improvement Backlog

```
====================================================================================================
PRIORITY TIER P0: Critical Security, Tenant Isolation & Data Correctness Blockers
====================================================================================================
```

### `IMP-SEC-001`: Encrypt Channel Account Access & Refresh Tokens at Rest
- **Affected Current Feature**: Multi-tenant channel management ([`app/Models/ChannelAccount.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/ChannelAccount.php#L17-L26)).
- **Observed Problem**: Meta Page Tokens, WhatsApp System User Tokens, and refresh tokens are stored as raw plaintext UTF-8 strings in MySQL.
- **Evidence & Source Reference**: [`app/Models/ChannelAccount.php:23-26`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/ChannelAccount.php#L23-L26). `$casts` omits `'encrypted'`.
- **Reproduction Steps**:
  1. Inspect MySQL table `channel_accounts`.
  2. Query `SELECT access_token FROM channel_accounts WHERE id = 1;`.
  3. Observe raw string (e.g. `EAAG...`) instead of Laravel encrypted payload (`eyJpdiI6...`).
- **Expected User/Business Impact**: Eliminates catastrophic credential leakage risk in case of database backup compromise.
- **Recommended Improvement**: Add `'access_token' => 'encrypted'`, `'refresh_token' => 'encrypted'`, and `'settings' => 'encrypted:array'` to `$casts` in `ChannelAccount.php`.
- **Regression Tests Needed**: `tests/Feature/ReceiveMessengerWebhookJobTest.php`, `tests/Feature/ProductionMessagingPipelineTest.php`.
- **Complexity**: `LOW`
- **Priority**: `P0`
- **Dependencies & Risks**: Existing plaintext tokens must be migrated/re-encrypted if production records exist.
- **Validation Metric & Acceptance Criteria**: All tokens in MySQL match Laravel encrypted string format; decrypted correctly by Eloquent accessors.

---

### `IMP-SEC-002`: [RESOLVED & VERIFIED] Authenticate Python AI Analytics Endpoint & Bind Tenant Signatures
- **Status**: `RESOLVED & VERIFIED` (Phase 1)
- **Affected Current Feature**: Microservice analytics router (`python-ai-service/app/main.py:141`).
- **Observed Problem**: `/analytics/query` endpoint is mounted without `verify_api_key` dependency. Any caller can query arbitrary `workspace_id` SQLite databases.
- **Evidence & Source Reference**: `python-ai-service/app/main.py:141` compared against authenticated router inclusion on line 125.
- **Implemented Fix**: Added `dependencies=[Depends(verify_api_key)]` to `app.include_router(analytics_router)` in `python-ai-service/app/main.py`. Updated Laravel `AnalyticsClient.php` to pass configured API key headers (`X-API-Key`).
- **Verification Tests**: Verified with automated tests in `tests/Feature/Security/`. Unauthorized requests return HTTP 401; authenticated requests execute with tenant isolation.
- **Complexity**: `MEDIUM`
- **Priority**: `P0`

---

```
====================================================================================================
PRIORITY TIER P1: High-Impact Quality, Retrieval & Reliability Upgrades
====================================================================================================
```

### `IMP-ING-001`: [RESOLVED & VERIFIED] Knowledge Document Ingestion, Generation-Scoped Indexing & Typesense Search
- **Status**: `RESOLVED & VERIFIED` (Phase 2 & Phase 2B.1–2B.4)
- **Affected Current Feature**: Document Knowledge Base Ingestion ([`app/Services/Knowledge/KnowledgeDocumentService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Knowledge/KnowledgeDocumentService.php)).
- **Observed Problem**: Uploaded text and PDF knowledge documents were stored in MySQL/disk, but never indexed into Typesense or searched by the AI chatbot.
- **Implemented Fix**:
  1. Connected knowledge document ingestion pipeline (`processKnowledgeBaseIngestion`) upon store, restore, and purpose change.
  2. Implemented Blue-Green zero-downtime indexing using generation-scoped chunk IDs (`doc_{id}_{genId}_{idx}`). The active generation remains searchable while the replacement generation is indexing.
  3. Added database-level atomic Compare-And-Swap (CAS) publication fencing with version validation (`version = version + 1`) to eliminate race conditions.
  4. Added crash recovery, stale generation cleanup, and soft-delete fencing (`delete()` clears Typesense chunks and increments version).
  5. Connected retrieval pipeline so `KnowledgeRetrievalTool` queries document chunks alongside FAQs in Typesense.
- **Verification Tests**: Verified with `tests/Feature/KnowledgeDocumentRetrievalPipelineTest.php` (89 tests passing across knowledge & retrieval suites).
- **Complexity**: `HIGH`
- **Priority**: `P1`

---

### `IMP-ROUT-001`: Add Layer 0.5 Lexicon & Regex Fast-Path to HybridRouter
- **Affected Current Feature**: Semantic Query Intent Router ([`app/AI/Routing/HybridRouter.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php#L33-L81)).
- **Observed Problem**: Every single turn (including "hi", "hello", "salam", "bye", "thanks") issues an external LLM network call, adding 1.5s–3.5s latency and incurring token costs.
- **Evidence & Source Reference**: [`HybridRouter.php:33-81`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php#L33-L81).
- **Reproduction Steps**:
  1. Send `"hi"` to `/simulator`.
  2. Inspect router telemetry: `layer1_llm_router` executed with ~1,400ms latency.
- **Expected User/Business Impact**: Reduces chitchat response latency by 75% (< 50ms) and saves 15%–20% of monthly router LLM tokens.
- **Recommended Improvement**: Add a local regex/lexicon match in Layer 0.5 for greetings, pleasantries, gratitude, and exact-match FAQ titles before hitting the LLM.
- **Regression Tests Needed**: `tests/Unit/AI/HybridRouterTest.php`, [`tests/Feature/Live/LiveLlmRouterTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/Live/LiveLlmRouterTest.php).
- **Complexity**: `LOW`
- **Priority**: `P1`
- **Dependencies & Risks**: Must preserve priority of multi-intent queries (e.g. *"hi, price koto?"* must route to `KNOWLEDGE`, not pure `CHAT`).
- **Validation Metric & Acceptance Criteria**: Standard chitchat routes in < 5ms without issuing LLM requests.

---

### `IMP-RET-001`: Enable Typesense Pure BM25 Fallback on Dense Embedding Outage
- **Affected Current Feature**: Python Retrieval Engine (`python-ai-service/app/retrieval_engine.py:176-180`).
- **Observed Problem**: If dense embedding model fails to initialize or runs out of RAM, `search_knowledge_base` raises an HTTP 503 error, completely disabling Typesense BM25 search.
- **Evidence & Source Reference**: `python-ai-service/app/retrieval_engine.py:176-180`.
- **Reproduction Steps**:
  1. Simulate embedding failure (`embed = None`).
  2. Query `/api/v1/search`.
  3. Observe HTTP 503 Service Unavailable, despite Typesense service being fully operational.
- **Expected User/Business Impact**: Eliminates a single point of failure; maintains keyword search availability during local embedder downtime.
- **Recommended Improvement**: If `embed is None`, log a warning and fall back to pure Typesense BM25 text search instead of throwing 503.
- **Regression Tests Needed**: `tests/Feature/Live/LiveTypesenseRetrievalTest.php`.
- **Complexity**: `LOW`
- **Priority**: `P1`
- **Dependencies & Risks**: None.
- **Validation Metric & Acceptance Criteria**: Search returns exact keyword matches with 200 OK when embedding service is disabled.

---

### `IMP-MEM-001`: Deduplicate & Serialize Background Memory Ingestion Queue Jobs
- **Affected Current Feature**: Background Memory Extraction ([`app/Jobs/IngestConversationMemoryJob.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php#L64-L86)).
- **Observed Problem**: Rapid user turns dispatch multiple concurrent jobs that process overlapping 6-message windows, causing duplicate nodes in Neo4j.
- **Evidence & Source Reference**: [`IngestConversationMemoryJob.php:64-68`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php#L64-L68).
- **Reproduction Steps**: Send 3 messages within 2 seconds; observe 3 simultaneous jobs querying the same conversation message IDs and calling Graphiti.
- **Expected User/Business Impact**: Eliminates Neo4j entity bloat, reduces graph query latency, and prevents race conditions.
- **Recommended Improvement**: Implement `ShouldBeUnique` on `IngestConversationMemoryJob` scoped to `conversation_id`, or track an `ingested_until_id` pointer.
- **Regression Tests Needed**: `tests/Feature/ConversationMemoryPipelineTest.php`.
- **Complexity**: `MEDIUM`
- **Priority**: `P1`
- **Dependencies & Risks**: Redis or cache lock configuration.
- **Validation Metric & Acceptance Criteria**: Overlapping turns are serialized; duplicate relationship creation in Neo4j drops to 0.

---

```
====================================================================================================
PRIORITY TIER P2: Meaningful Efficiency, Accuracy & Schema Upgrades
====================================================================================================
```

### `IMP-ANL-001`: Enforce Ingestion Bounds & Query Timeouts on Spreadsheet Analytics
- **Affected Current Feature**: Excel Virtual SQLite Engine (`python-ai-service/app/analytics/excel_engine.py:104-140`).
- **Observed Problem**: Large multi-sheet files read unbounded rows into memory; SQLite queries execute without statement timeouts.
- **Evidence & Source Reference**: `excel_engine.py:104, 112-135`.
- **Expected User/Business Impact**: Protects Python analytics worker from OOM crashes and CPU exhaustion.
- **Recommended Improvement**: Enforce `MAX_SHEETS = 10`, `MAX_ROWS_PER_SHEET = 100_000`, and set a 15-second SQLite progress handler timeout.
- **Complexity**: `LOW`
- **Priority**: `P2`

---

### `IMP-RET-002`: Optimize MySQL Full-Text Fallback Search Index
- **Affected Current Feature**: Database Search Fallback ([`app/Services/Retrieval/RetrievalClient.php:305-342`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Retrieval/RetrievalClient.php#L305-L342)).
- **Observed Problem**: Fallback executes `LIKE '%word%'` table scans and hardcodes `finalScore = 0.85`.
- **Evidence & Source Reference**: [`RetrievalClient.php:324-337`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Retrieval/RetrievalClient.php#L324-L337).
- **Expected User/Business Impact**: Prevents MySQL database lockups during microservice outages and provides realistic fallback ranking scores.
- **Recommended Improvement**: Use `MATCH(...) AGAINST(...)` in MySQL and calculate normalized BM25 fallback scores.
- **Complexity**: `MEDIUM`
- **Priority**: `P2`

---

### `IMP-SAFE-001`: [RESOLVED & VERIFIED] Restrict Action Safety Confirmation Window & Clear Stale Actions
- **Status**: `RESOLVED & VERIFIED` (Phase 1)
- **Affected Current Feature**: Two-Phase Commit Action Protocol ([`app/Services/AI/ActionSafetyService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/ActionSafetyService.php)).
- **Observed Problem**: Action proposals stayed active for 15 minutes and could be accidentally confirmed by a generic *"ok"* to an unrelated question.
- **Implemented Fix**: Bound confirmation window and integrated `SemanticAnswerabilityGate` so pending actions are invalidated whenever conversational turns drift to non-confirmation intents or knowledge/analytics queries.
- **Verification Tests**: Verified with automated unit and feature tests.
- **Complexity**: `LOW`
- **Priority**: `P2`

---

```
====================================================================================================
PRIORITY TIER P3: Minor Telemetry Polish & Housekeeping
====================================================================================================
```

### `IMP-OBS-001`: Isolate Background Memory Ingestion Tokens from Outbound Message Usage
- **Affected Current Feature**: LLM Usage Telemetry ([`app/Jobs/IngestConversationMemoryJob.php:125-141`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php#L125-L141)).
- **Observed Problem**: Background memory extraction tokens are retroactively added to outbound message turn tokens, distorting message-level usage reporting.
- **Evidence & Source Reference**: [`IngestConversationMemoryJob.php:139-140`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php#L139-L140).
- **Recommended Improvement**: Store background memory tokens in a dedicated `memory_extraction_tokens` property on the conversation model.
- **Complexity**: `LOW`
- **Priority**: `P3`

---

### `IMP-OBS-002`: Daily & Monthly Tenant Token Aggregation Roll-Up Command
- **Affected Current Feature**: Observability Controller (`app/Http/Controllers/ObservabilityController.php`).
- **Observed Problem**: Telemetry is stored as raw JSON in `conversations.metadata`; no aggregated daily/monthly table exists for quick dashboard queries.
- **Recommended Improvement**: Create a scheduled daily command (`php artisan telemetry:aggregate-usage`) that rolls up prompt, completion, and total tokens per workspace into a summary table.
- **Complexity**: `LOW`
- **Priority**: `P3`
