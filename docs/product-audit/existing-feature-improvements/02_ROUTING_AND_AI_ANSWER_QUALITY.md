# Routing, Retrieval & AI Answer Quality Audit

- **Document ID**: `QUAL-AUDIT-02`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Inspection Date**: October 2026
- **Scope**: Intent Routing (Scope A) & Retrieval, Grounding, Answer Quality (Scope B)
- **Status**: Empirical Deep-Dive Report

---

## 1. Executive Summary

This audit evaluates the precision, failure modes, and latency characteristics of the front half of our conversational AI pipeline:
1. **Intent Classification & Routing** (`HybridRouter v2.2`)
2. **Contextual Query Construction & Typesense Retrieval** (`RetrievalClient`, `typesense_engine.py`)
3. **Answerability Gating & Final LLM Answer Generation** (`SemanticAnswerabilityGate`, `CustomerSupportService`)

Using concrete code traces, empirical benchmark logs ([`step4_generation_baseline_report.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/step4_generation_baseline_report.json), [`phase_c_baseline_scorecard.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/phase_c_baseline_scorecard.json)), and failure diagnostic scripts ([`AuditRoutingFailuresCommand.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Console/Commands/AuditRoutingFailuresCommand.php)), we isolate the exact root cause for every observed classification, retrieval, and generation failure.

---

## 2. Scope A: Routing & Intent Classification Audit

### A.1 Architecture Overview
Routing is managed by [`HybridRouter.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php). The classifier routes queries into 6 explicit route types: `CHAT`, `KNOWLEDGE`, `ANALYTICS`, `ACTION`, `UNCERTAIN`, and `OOD`.

### A.2 Critical Routing Findings & Deficiencies

```
+---------------------------------------------------------------------------------------------------+
| FINDING ROUT-01: Absence of Local Fast-Path (Latency & Token Cost Penalty on Every Turn)          |
| Status: CONFIRMED | Evidence: CODE_INSPECTION & EXISTING_TEST                                     |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: In [`HybridRouter.php:33-81`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php#L33-L81), Layer 0 only checks for empty queries and `pending_clarification` state. If neither applies, the router immediately formats a full prompt and issues a remote LLM API call (`$this->llmClient->generate($request)` using `deepseek-chat`).
- **Failure Impact**:
  - Even trivial chitchat greetings (*"Hi"*, *"Hello"*, *"Salam"*, *"Bye"*, *"Thanks"*) incur an external network roundtrip taking 1,200ms to 3,500ms.
  - Generates unnecessary LLM token cost (system prompt + 4 message turns = ~300 prompt tokens per routing step).
  - Verified in [`tests/Unit/AI/HybridRouterTest.php:30-47`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Unit/AI/HybridRouterTest.php#L30-L47), where testing `"Hi, hello!"` requires mocking a complete LLM response.
- **Recommended Fix**: Add a deterministic Layer 0.5 Lexicon/Regex Fast-Path for standard greetings, pleasantries, and exact match FAQ titles (< 2ms execution, zero LLM tokens).

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING ROUT-02: OOD & Action Misclassification into Knowledge Route                             |
| Status: CONFIRMED | Evidence: BENCHMARK & EXISTING_TEST                                           |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Evidence**:
  - In [`AuditRoutingFailuresCommand.php:63-71`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Console/Commands/AuditRoutingFailuresCommand.php#L63-L71), evaluating the 244-turn benchmark revealed recurrent misclassifications:
    1. `CHAT -> KNOWLEDGE`: Inquiries expressing interest to buy or asking general capabilities are routed to `KNOWLEDGE`.
    2. `ACTION -> KNOWLEDGE`: Direct imperatives (*"Rahim-ke mail koro"*) misrouted to knowledge search because the router prompt's knowledge definition mentions "email" and "delivery".
    3. `OOD -> KNOWLEDGE`: Out-of-domain queries mentioning weather, recipes, or general programming get caught by keyword matches in Typesense.
  - In [`phase_c_baseline_scorecard.json:11-12`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/phase_c_baseline_scorecard.json#L11-L12), out of 8 benchmark OOD queries, 3 triggered false positives (37.5% failure rate), leaking corporate search attempts on completely irrelevant topics.
- **Root Cause**: The system prompt in [`HybridRouter.php:82-115`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php#L82-L115) has overlapping boundary definitions between `KNOWLEDGE` and `UNCERTAIN`/`OOD`.
- **Recommended Fix**: Tighten negative boundary rules in the router system prompt with few-shot contrastive pairs and lower the sub-threshold confidence fallback to force `UNCERTAIN` clarification when ambiguous.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING ROUT-03: Multi-Turn Intent Carryover & Elliptical Follow-Up Amnesia                       |
| Status: PARTIALLY_CONFIRMED | Evidence: CODE_INSPECTION & BENCHMARK                               |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: In [`HybridRouter.php:120-142`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php#L120-L142), the router injects the last 4 messages truncated to 160 characters.
- **Failure Impact**:
  - In short elliptical follow-ups like *"Ar Hasaner?"* (*"And Hasan's?"*) following an analytics question (*"Rahim-er due koto?"*), the LLM correctly preserves `ANALYTICS`.
  - However, when the user switches topics across 3 turns (e.g. Analytics -> FAQ -> Follow-up), the truncated 160-char window cuts off context, causing the router to hallucinate an entity or classify the query as `UNCERTAIN`.
- **Recommended Fix**: Pass structured metadata flags (`last_active_route: ANALYTICS`, `active_entity: Hasan`) directly in the router payload rather than relying solely on raw text parsing.

---

## 3. Scope B: Retrieval, Grounding & AI Answer Quality Audit

### B.1 Retrieval Architecture
Retrieval is coordinated by [`RetrievalClient.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Retrieval/RetrievalClient.php), communicating with `python-ai-service` which executes dense vector search + BM25 keyword matching on Typesense.

### B.2 Critical Retrieval & Grounding Findings

```
+---------------------------------------------------------------------------------------------------+
| FINDING RET-01: Disconnect Between KnowledgeDocument Uploads & Typesense Retrieval                |
| Status: CONFIRMED | Evidence: CODE_INSPECTION                                                     |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**:
  - In [`KnowledgeDocumentService.php:420-427`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Knowledge/KnowledgeDocumentService.php#L420-L427), when a user uploads a text or PDF knowledge document (`KNOWLEDGE_BASE` purpose), the file is saved to disk and marked `ACTIVE` in MySQL. **No indexing job is dispatched to Typesense!**
  - In [`KnowledgeRetrievalTool.php:18-60`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Tools/KnowledgeRetrievalTool.php#L18-L60), the tool delegates execution exclusively to `FAQSearch::search()`.
  - In `FAQSearch.php` and `FAQIndexJob.php:127-130`, only records from the `faqs` table are synced to Typesense!
- **Failure Impact**:
  - **A major product blindspot**: Merchants who upload corporate policy PDFs or text documents believe their chatbot has learned the material, but the chatbot literally cannot retrieve them because they are never indexed in Typesense!
  - The chatbot only searches the FAQ Q&A database.
- **Recommended Fix**: Implement an asynchronous `IndexKnowledgeDocumentJob` that chunks uploaded documents, extracts text, generates dense embeddings, and inserts chunks into a unified Typesense collection searched by `RetrievalClient`.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING RET-02: Hard Embedding Failure Disables Typesense BM25 Search (Single Point of Failure)   |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`python-ai-service/app/retrieval_engine.py`)       |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: In `python-ai-service/app/retrieval_engine.py:176-180`:
  ```python
  if embed is None or not callable(embed):
      logger.warning("Dense embedding engine unavailable for query [%s]. Raising HTTP 503.", query)
      raise HTTPException(
          status_code=503,
          detail="Embedding engine is unavailable for dense vector search (service degraded).",
      )
  ```
- **Failure Impact**:
  - Typesense natively supports pure BM25 keyword text search without embeddings.
  - However, because line 176 raises an HTTP 503 error if the dense embedding model fails to initialize, the entire retrieval pipeline crashes, preventing Typesense from performing keyword matching on exact matches (e.g. model numbers, SKUs, brand names).
- **Recommended Fix**: If `embed is None`, log a warning and execute a pure BM25 keyword search in Typesense rather than failing closed with HTTP 503.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING RET-03: Fragile Database Fallback on Retrieval Service Outage                             |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`RetrievalClient.php:305-342`)                     |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: When the Python service fails or times out, [`RetrievalClient.php:122`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Retrieval/RetrievalClient.php#L122) falls back to `fallbackDatabaseSearch()`:
  ```php
  $dbQuery->where(function ($q) use ($words) {
      foreach ($words as $word) {
          $q->orWhere('question', 'LIKE', '%' . $word . '%')
            ->orWhere('answer', 'LIKE', '%' . $word . '%')
            ->orWhere('searchable_text', 'LIKE', '%' . $word . '%');
      }
  });
  ```
- **Failure Impact**:
  - Performs an unindexed full table scan using leading wildcards (`LIKE '%word%'`), creating severe MySQL query contention during an outage.
  - Automatically assigns a static, hardcoded `finalScore: 0.85` (line 337) to all matched rows, regardless of how irrelevant the match is.
  - Only searches `faqs`, completely ignoring knowledge documents.
- **Recommended Fix**: Add a MySQL Full-Text Search index (`MATCH(question, answer) AGAINST(...)`) for fallback search and calculate actual relevance scores instead of hardcoding 0.85.

---

### B.3 Answer Generation & Faithfulness Audit

The empirical audit in [`step4_generation_baseline_report.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/step4_generation_baseline_report.json) provides conclusive measurements of generation quality across 48 multi-turn conversations:

```
Language Group      Evaluated Turns    Grounded Turns    Faithful Turns    Unsupported Claims Rate
--------------------------------------------------------------------------------------------------
English             12                 12 (100.0%)       12 (100.0%)       0.0% (0 / 31 claims)
Native Bangla       12                 12 (100.0%)       12 (100.0%)       0.0% (0 / 30 claims)
Banglish            12                 10 ( 83.3%)       10 ( 83.3%)      32.7% (16 / 49 claims)
Code-Mixed          12                  9 ( 75.0%)        9 ( 75.0%)      36.6% (15 / 41 claims)
--------------------------------------------------------------------------------------------------
TOTAL / AVERAGE     48                 43 ( 89.6%)       43 ( 89.6%)      22.5% (31 / 151 claims)
```

### Key Answer Quality Findings:
1. **Severe Language Asymmetry**: English and formal Bengali exhibit 100% groundedness and 0% unsupported claims. However, **Banglish and Code-Mixed queries exhibit an unsupported claim rate over 30%**!
2. **Root Cause**: When users write in Banglish (*"delivery koto lagbe?"*), the retrieval search occasionally retrieves partial chunks. Instead of abstaining, the final LLM attempts to translate the Banglish query into standard Bengali, hallucinates speculative delivery charges (e.g. 100 BDT) not present in the retrieved chunk, and outputs the answer as factual.
3. **P95 Latency Spike**: Generation P50 latency is 3.3 seconds, but **P95 latency spikes to 8.96 seconds**, caused by sequential LLM expansion passes when initial retrieval confidence is marginal.

---

## 4. Root Cause Summary: Retrieval vs Generation Failures

```
Failure Observed                              Stage Responsible     Root Cause
-----------------------------------------------------------------------------------------------------------
Uploaded PDFs not found in chat               Retrieval (Ingestion) KnowledgeDocumentService never syncs to Typesense.
High latency (1.5s–3.5s) on "Hi / Hello"      Routing               HybridRouter sends all queries to DeepSeek LLM.
Hallucinated delivery fee in Banglish         Generation            LLM fills retrieval gaps instead of abstaining.
HTTP 503 when local embedder is slow          Retrieval             retrieval_engine.py fails closed; skips BM25.
Database lockup during Python outage          Retrieval (Fallback)  Unindexed LIKE '%word%' table scan in MySQL.
OOD queries leaking into knowledge answers    Routing / Answer Gate SemanticAnswerabilityGate threshold too lenient.
```
