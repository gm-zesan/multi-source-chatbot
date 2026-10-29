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

### `IMP-ING-001`: [RESOLVED & VERIFIED] Knowledge Document Ingestion, Generation-Scoped Indexing, Deterministic Fallback & Score Mapping
- **Status**: `RESOLVED & VERIFIED` (Phase 2, Phase 2B.1–2B.4 & Phase 2C Regression Fixes)
- **Affected Current Feature**: Document Knowledge Base Ingestion & Retrieval ([`app/Services/Knowledge/KnowledgeDocumentService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Knowledge/KnowledgeDocumentService.php), [`app/Services/Retrieval/RetrievalClient.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Retrieval/RetrievalClient.php), [`app/Services/AI/CustomerSupportService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/CustomerSupportService.php)).
- **Observed Problem**: Uploaded text and PDF knowledge documents were initially unindexed; after indexing, Tier 3 deterministic fallback only inspected `faq.answer` (ignoring document chunk contents), and hybrid `semantic_score` defaulting caused false OOD refusals.
- **Implemented Fix**:
  1. Connected knowledge document ingestion pipeline (`processKnowledgeBaseIngestion`) upon store, restore, and purpose change.
  2. Implemented Blue-Green zero-downtime indexing using generation-scoped chunk IDs (`doc_{id}_{genId}_{idx}`). The active generation remains searchable while the replacement generation is indexing.
  3. Added database-level atomic Compare-And-Swap (CAS) publication fencing with version validation (`version = version + 1`) to eliminate race conditions.
  4. Added crash recovery, stale generation cleanup, and soft-delete fencing (`delete()` clears Typesense chunks and increments version).
  5. Connected retrieval pipeline so `KnowledgeRetrievalTool` queries document chunks alongside FAQs in Typesense.
  6. **Phase 2C Deterministic Fallback & Score Mapping Fix**: Updated `CustomerSupportService.php` to resolve document chunk contents (`$topHit->faq?->answer ?? $topHit->content ?? $topHit->document_content ?? $topHit->answer`). Refactored `RetrievalClient.php` and `FAQSearchResult.php` so missing hybrid subscores remain `null` (rather than faking `0.0` or `$score`), preventing false semantic mismatch triggers while preserving off-topic non-commerce protections.
- **Verification Tests**: Verified with `tests/Feature/KnowledgeDocumentRetrievalPipelineTest.php` (32 tests), `tests/Unit/Services/RetrievalClientTest.php` (18 tests), and `tests/Unit/Services/CustomerSupportServiceTest.php` (15 tests, 100% PASS).
- **Complexity**: `HIGH`
- **Priority**: `P1`

---

### `IMP-DOM-001`: [RESOLVED & VERIFIED] Contextual Query Building for Clarification & Multi-Turn Queries
- **Status**: `RESOLVED & VERIFIED`
- **Affected Current Feature**: Query Preprocessing & Multi-Turn Clarification ([`app/Services/AI/CustomerSupportService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/CustomerSupportService.php), [`app/AI/Routing/HybridRouter.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php)).
- **Observed Problem**: Isolated user responses to clarification options (e.g. *"Option 2"* or *"Track parcel"*) lacked conversational context, failing dense vector retrieval.
- **Implemented Fix**: Bound conversation context to user selection turns, propagating `contextual_intent` through the pipeline to ensure dense retrieval evaluates full semantic intent.
- **Verification Tests**: Verified with regression tests in `CustomerSupportServiceTest.php` and router characterization tests.
- **Complexity**: `MEDIUM`
- **Priority**: `P1`

---

### `IMP-DOM-002`: [RESOLVED & VERIFIED] Dynamic Multi-Tenant FAQ Lexicon Lifecycle
- **Status**: `RESOLVED & VERIFIED`
- **Affected Current Feature**: Dynamic Terminology & Lexicon Synchronization ([`app/Services/FAQ/LexiconLifecycleManager.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/FAQ/LexiconLifecycleManager.php)).
- **Observed Problem**: Domain-specific terminology and synonyms across diverse tenants were static or hardcoded, risking cross-tenant terminology contamination.
- **Implemented Fix**: Implemented `LexiconLifecycleManager` to dynamically extract, sync, and isolate workspace-scoped domain lexicons across healthcare, education, retail, and hospitality.
- **Verification Tests**: Verified with `tests/test_faq_sync_and_scoring.py` and `tests/test_adversarial_lexicon_fuzzing.py` in `python-ai-service`.
- **Complexity**: `MEDIUM`
- **Priority**: `P1`

---

### `IMP-DOM-003`: [RESOLVED & VERIFIED] Multi-Domain Retrieval Expansion Neutrality
- **Status**: `RESOLVED & VERIFIED`
- **Affected Current Feature**: Tier 3 LLM Query Expansion Engine (`python-ai-service/app/retrieval_engine.py:37-56`).
- **Observed Problem**: The LLM query expansion prompt hardcoded retail-only topics (`refund, cancellation, delivery charges, payment methods, warranty`) and single retail example (`order kobe pabo?`), biasing expansions towards e-commerce even for healthcare, education, SaaS, or hotel queries.
- **Implemented Fix**: Rewrote `expand_query_via_llm` system prompt to be strictly domain-neutral. Explicitly instructed LLM to preserve the exact subject domain of the query without injecting retail bias. Added diverse few-shot examples covering healthcare, education, retail, and hospitality.
- **Verification Tests**: Verified with `python-ai-service/tests/test_domain_neutral_expansion.py` (5 tests passing) and regression tests (26 tests passing).
- **Complexity**: `LOW`
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

### `IMP-RET-001`: [RESOLVED & VERIFIED] Typesense Pure BM25 Fallback on Dense Embedding Outage
- **Status**: `RESOLVED & VERIFIED`
- **Affected Current Feature**: Python Retrieval Engine (`python-ai-service/app/retrieval_engine.py:176-210`).
- **Observed Problem**: If dense embedding model failed to initialize or ran out of RAM, `search_knowledge_base` raised an HTTP 503 error, completely disabling Typesense BM25 search.
- **Implemented Fix**: Added degraded BM25 fallback in `retrieval_engine.py`. When `embed` is `None` or embedding generation throws an exception, the engine logs a warning and executes pure Typesense text match queries with BM25 score normalization, returning 200 OK.
- **Verification Tests**: Verified with `python-ai-service/tests/test_degraded_embedding_fallback.py` (12 tests passing) and Laravel `tests/Unit/Services/RetrievalClientTest.php`.
- **Complexity**: `LOW`
- **Priority**: `P1`

---

### `IMP-MEM-001`: [RESOLVED & VERIFIED] Deduplicate & Serialize Background Memory Ingestion Queue Jobs
- **Status**: `RESOLVED & VERIFIED`
- **Affected Current Feature**: Background Memory Extraction ([`app/Jobs/IngestConversationMemoryJob.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php)).
- **Observed Problem**: Rapid user turns dispatched multiple concurrent jobs that processed overlapping 6-message sliding windows, causing duplicate nodes in Neo4j, wasted LLM tokens, and lost metadata writes.
- **Implemented Fix**:
  1. Implemented `ShouldBeUniqueUntilProcessing` and `uniqueId()` on `IngestConversationMemoryJob` (with `$uniqueFor = 60`), deduplicating jobs queued before worker pickup while allowing subsequent turns arriving mid-flight to be cleanly queued.
  2. Implemented execution-level runtime mutex using `Cache::lock("memory_ingest:conv:{$id}", 60)`. If a worker attempts to process an already active conversation, it safely delays execution with `$this->release(3)`.
  3. Added ingestion watermark pointer `conversations.metadata['last_memory_ingested_message_id']`. Consecutive jobs only query `where('id', '>', $lastIngestedId)`. Overlap between successive runs dropped from $\ge 4$ to exactly $0$. If no new messages exist, the job exits immediately with 0 HTTP calls.
  4. Added failure recovery and retry semantics: the watermark is only advanced on verified successful ingestion (`success: true`). On transient failure or timeout, the watermark remains in place and `$this->release(10)` schedules retry up to `$tries = 2`.
- **Verification Tests**: Verified with `tests/Feature/MemoryJobConcurrencyTest.php` (6 tests, 25 assertions) and complete memory pipeline suite (41 tests, 100% PASS).
- **Complexity**: `MEDIUM`
- **Priority**: `P1`

---

```
====================================================================================================
PRIORITY TIER P2: Meaningful Efficiency, Accuracy & Schema Upgrades
====================================================================================================
```

### `IMP-ANL-001`: [RESOLVED & VERIFIED] Enforce Ingestion Bounds & Query Timeouts on Spreadsheet Analytics
- **Status**: `RESOLVED & VERIFIED`
- **Affected Current Feature**: Excel Virtual SQLite Engine (`python-ai-service/app/analytics/excel_engine.py`).
- **Observed Problem**: Large multi-sheet files read unbounded rows into memory; SQLite queries executed without statement execution timeouts, risking OOM crashes and CPU exhaustion.
- **Implemented Fix**:
  1. Configured resource limits in `app/config.py`: `ANALYTICS_MAX_SHEETS = 10`, `ANALYTICS_MAX_ROWS_PER_SHEET = 100_000`, `ANALYTICS_QUERY_TIMEOUT_SECONDS = 15.0`.
  2. Bounded workbook ingestion in `excel_engine.py`: rejects workbooks with `> 10` worksheets before reading data; enforces `100,000` data rows limit (excluding header) during streaming/reading without loading unbounded worksheets into memory.
  3. Added clean file cleanup on rejection to prevent orphaned SQLite files on disk.
  4. Implemented `execute_sql_query` with SQLite `set_progress_handler` checking query deadline every 1,000 VM instructions. Safely interrupts long-running queries, returns safe timeout notice without exposing internal paths or stack traces, and resets the progress handler in `finally`.
- **Verification Tests**: Verified with `python-ai-service/tests/test_analytics_resource_guards.py` (10 tests, 100% PASS) and complete analytics test battery (76 tests, 100% PASS).
- **Complexity**: `LOW`
- **Priority**: `P2`

---

### `IMP-RET-002`: [RESOLVED & VERIFIED] Optimize MySQL Full-Text Fallback Search Index & Calibrated Scoring
- **Status**: `RESOLVED & VERIFIED`
- **Affected Current Feature**: Database Search Fallback ([`app/Services/Retrieval/RetrievalClient.php:538-695`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Retrieval/RetrievalClient.php#L538-L695)).
- **Observed Problem**: Fallback executed unindexed `LIKE '%word%'` table scans, hardcoded an arbitrary uncalibrated `finalScore = 0.85` for every match regardless of relevance, and dumped arbitrary FAQs when queries contained no tokens $> 2$ characters.
- **Implemented Fix**:
  1. Maintained strict tenant isolation: missing or invalid `workspace_id` aborts search immediately and returns an empty collection.
  2. Empty queries or queries without searchable tokens ($\ge 2$ characters) return an empty collection immediately, preventing irrelevant FAQ dumping on chitchat or noise queries during microservice outages.
  3. Added MySQL Full-Text Boolean Search fast-path (`MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE)`) leveraging existing migration indexes on MySQL while safely failing over to parameterized token matching if FULLTEXT produces 0 hits or under SQLite in test environments.
  4. Implemented calibrated lexical relevance scoring in $[0.0, 0.95]$ (`calculateFallbackRelevance`): exact question match ($0.95$), contiguous phrase in question ($0.90$), title word coverage ($0.60 - 0.88$), and full document/searchable text coverage ($0.40 - 0.75$). Filters out non-matching candidates ($0.0$).
  5. Applied the same calibrated scoring and tenant isolation to active `KnowledgeDocument` records.
  6. Sorted hits descending by calibrated score with `matchType = 'keyword_fallback'`.
- **Verification Tests**: Verified with `tests/Unit/Services/DatabaseFallbackOptimizationTest.php` (8 tests, 42 assertions, 100% PASS) and complete retrieval regression suite (`RetrievalClientTest`, `SemanticAnswerabilityGateTest`, `CustomerSupportServiceTest`, `KnowledgeDocumentRetrievalPipelineTest` - 92 tests, 464 assertions, 0 failures).
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
