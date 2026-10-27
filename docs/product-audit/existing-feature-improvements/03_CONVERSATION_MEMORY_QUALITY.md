# Conversation Memory Quality & Reliability Audit

- **Document ID**: `QUAL-AUDIT-03`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Inspection Date**: October 2026
- **Scope**: Scope C — Episodic Graph Memory, Relevance Gating & Lifecycle Management
- **Status**: Empirical Deep-Dive Report

---

## 1. Executive Summary

Our platform implements an advanced long-term memory architecture combining **Neo4j + Graphiti** (`python-memory-service`) with a Laravel orchestration client ([`ConversationMemoryService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Memory/ConversationMemoryService.php)) and a pre-retrieval relevance gate (`MemoryRelevanceGate.php`).

While the memory relevance gate demonstrates high effectiveness—achieving **95.33% noise reduction** on static FAQ benchmarks ([`phase_c_baseline_scorecard.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/phase_c_baseline_scorecard.json))—inspection of the write lifecycle and background queuing reveals **critical race conditions, potential fact duplication, and memory isolation vulnerabilities**.

---

## 2. Memory Lifecycle Trace: Read vs Write Path

```
READ PATH (Synchronous Pre-Turn)
User Query
   │
   ▼
[MemoryRelevanceGate::shouldRetrieve]
   ├── Checks if query contains pronouns, self-referential terms, or preferences.
   ├── If static FAQ/policy query: RETURNS FALSE (0ms network, 0 tokens wasted).
   └── If personal/contextual query: RETURNS TRUE
         │
         ▼
[ConversationMemoryService::retrieveStructuredMemory]
   ├── Resolves Customer ID (external_user_id -> contact_id -> cust_conv_X)
   ├── Calls python-memory-service: POST /api/v1/memory/search (Port 8002)
   ├── Resolves Conflict Precedence: Latest updated_at wins per relation
   └── Formats clean markdown block: "Customer Historical Preferences: ..."
```

```
WRITE PATH (Asynchronous Post-Turn)
Outbound Message Generated
   │
   ▼
[IngestConversationMemoryJob::dispatch] (Queue: 'memory')
   ├── Takes LAST 6 MESSAGES from conversation
   ├── Calls python-memory-service: POST /api/v1/memory/ingest
   ├── Python calls Graphiti (LLM entity/relation extraction into Neo4j)
   └── Records consumed memory tokens in conversation metadata
```

---

## 3. Deep-Dive Quality Findings & Deficiencies

```
+---------------------------------------------------------------------------------------------------+
| FINDING MEM-01: Overlapping Turn Ingestion Race Condition in Background Queue                     |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`IngestConversationMemoryJob.php:64-98`)           |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: In [`app/Jobs/IngestConversationMemoryJob.php:64-68`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php#L64-L68):
  ```php
  $messages = $this->conversation->messages()
      ->latest('id')
      ->take(6)
      ->get()
      ->reverse();
  ```
- **Failure Mechanism**:
  - When a user sends multiple messages rapidly (e.g. Turn 1: *"Amar nam Zesan"*, Turn 2: *"Ami Dhaka thaki"*, Turn 3: *"Amar size XL"*), each turn dispatches an `IngestConversationMemoryJob` to the queue.
  - Because each job takes the last 6 messages and sends them to Graphiti for LLM entity extraction, multiple jobs process overlapping message windows concurrently.
  - Graphiti creates duplicate episode nodes or parallel relationship edges in Neo4j (e.g. `(Customer)-[:LIVES_IN]->(Dhaka)` created 3 times with slightly different timestamps).
- **Impact**: Bloats Neo4j graph storage, increases memory search latency, and creates conflicting timestamps.
- **Recommended Fix**: Implement queue job deduplication using Laravel's `ShouldBeUnique` interface with `uniqueId = conversation_id`, or track an `ingested_until_message_id` watermark so jobs only ingest unseen messages.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING MEM-02: Customer ID Collision on Multi-Channel Fallbacks                                  |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`ConversationMemoryService.php:26-38`)             |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: In [`ConversationMemoryService.php:26-38`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Memory/ConversationMemoryService.php#L26-L38):
  ```php
  public function resolveCustomerId(?Conversation $conversation): ?string
  {
      $id = $conversation->external_user_id ?? $conversation->contact_id;
      if (!empty($id)) {
          return (string) $id;
      }
      return $conversation->id ? "cust_conv_{$conversation->id}" : null;
  }
  ```
- **Failure Mechanism**:
  - `external_user_id` represents channel-specific IDs (e.g. Facebook PSID `1029384756` vs Telegram ID `1029384756`).
  - If a Facebook user and a Telegram user happen to have identical numerical IDs, their memory graphs collide in Neo4j unless namespaced by channel!
  - When `external_user_id` is missing, it falls back to `"cust_conv_{$conversation->id}"`. If the user opens a second conversation, the new conversation receives `"cust_conv_Y"` and starts with **complete memory amnesia**!
- **Impact**: Accidental cross-customer data leakage if IDs collide; amnesia across conversations for non-authenticated web visitors.
- **Recommended Fix**: Prefix customer IDs with channel slug: `fb_PSID`, `tg_CHATID`, or permanent CRM `contact_UUID`.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING MEM-03: Temporal Fact Precedence Resolution Relies on Client Timestamps                   |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`ConversationMemoryService.php:159-173`)           |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: In [`ConversationMemoryService.php:159-173`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Memory/ConversationMemoryService.php#L159-L173):
  ```php
  $existingTs = $existing['updated_at'] ?? $existing['timestamp'] ?? null;
  $currentTs = $mem['updated_at'] ?? $mem['timestamp'] ?? null;

  if ($currentTs !== null && $existingTs !== null && $currentTs > $existingTs) {
      $superseded[] = $existing;
      $activeByAttribute[$relation] = $mem;
  } else {
      $superseded[] = $mem;
  }
  ```
- **Failure Mechanism**:
  - The conflict resolver determines that the newest fact wins precedence based on string comparison of timestamps (`$currentTs > $existingTs`).
  - If Graphiti returns an ISO8601 string with differing timezone offsets (e.g. `+06:00` vs `Z`), standard string comparison yields incorrect ordering!
  - If a user changes their preference (e.g. *"Actually, send it to Sylhet, not Dhaka"*), an incorrectly parsed timestamp will retain Dhaka and mark Sylhet as superseded.
- **Impact**: Incorrect customer details used during fulfillment or support.
- **Recommended Fix**: Parse timestamps into Unix epoch integers or Carbon instances before comparing: `Carbon::parse($currentTs)->gt(Carbon::parse($existingTs))`.

---

```
+---------------------------------------------------------------------------------------------------+
| FINDING MEM-04: Memory Token Ingestion Tracking Leaks into Outbound Message Usage                |
| Status: CONFIRMED | Evidence: CODE_INSPECTION (`IngestConversationMemoryJob.php:125-141`)         |
+---------------------------------------------------------------------------------------------------+
```
- **Observed Code Path**: In [`IngestConversationMemoryJob.php:139-140`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php#L139-L140):
  ```php
  $msgUsage['prompt_tokens'] = ((int) ($msgUsage['prompt_tokens'] ?? 0)) + $memPrompt;
  $msgUsage['completion_tokens'] = ((int) ($msgUsage['completion_tokens'] ?? 0)) + $memCompletion;
  ```
- **Failure Mechanism**:
  - Background memory ingestion token usage is retroactively added directly to the latest outbound customer message's `llm_usage` counters!
  - If a tenant inspects message token usage, a message that took 200 tokens to generate suddenly displays 1,200 tokens because the background memory extraction job ran 10 seconds later and mutated the message record!
- **Impact**: Corrupts per-turn message telemetry and distorts user-facing token analytics.
- **Recommended Fix**: Keep background memory ingestion tokens in a separate `memory_extraction_usage` ledger in conversation metadata rather than mutating individual message turn usage.

---

## 4. Empirical Evaluation: Memory Relevance Gating Benchmark

In [`tests/Datasets/phase_c_baseline_scorecard.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/phase_c_baseline_scorecard.json), the `MemoryRelevanceGate` was evaluated across 200 static and contextual queries:

```
Metric                                              Measured Score
------------------------------------------------------------------
Memory Relevance Gate Precision                     82.0%
Memory Token Reduction Rate                         95.33%
Unnecessary Memory Injections Eliminated            152 / 200 turns (76.0%)
Pure Static Policy Noise Leakage Rate               0.0%
Memory Retrieval Overhead Latency                   < 0.05 ms (Gated Turns)
```

### Verified Strength:
When users ask purely static questions (*"What is the return policy?"*, *"How much is delivery in Dhaka?"*), the gate has a **0.0% leakage rate**. It reliably intercepts and cancels graph memory calls, preventing memory hallucinations and saving 95.3% of prompt tokens.

### Weakness:
Precision is 82.0% on borderline queries (e.g. *"What should I order?"*). In 18% of borderline cases, the gate either failed to fetch available past order history or made a memory call when no personal facts existed.
