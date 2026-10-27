# Current System Capability Baseline & Request Flow

- **Document ID**: `QUAL-AUDIT-01`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Inspection Date**: October 2026
- **Status**: Technical Architecture Baseline (Read-Only)

---

## 1. High-Level System Architecture & Component Mapping

Our platform consists of three core runtime tiers communicating via HTTP/REST:

```
+---------------------------------------------------------------------------------------------------+
| TIER 1: LARAVEL 12 ORCHESTRATION ENGINE (PHP 8.2+ / MySQL / Redis)                                 |
| - Chat Simulator & Conversation Management (ChatSimulatorController.php, Conversation.php)         |
| - Semantic Intent Router v2.2 (HybridRouter.php)                                                  |
| - Action Safety & Two-Phase Commit (ActionSafetyService.php)                                      |
| - Typesense Client & FAQ Synchronization (RetrievalClient.php, FAQIndexJob.php)                   |
| - Knowledge Document Management (KnowledgeDocumentService.php)                                    |
| - Memory Client & Relevance Gate (ConversationMemoryService.php, MemoryRelevanceGate.php)         |
+---------------------------------------------------------------------------------------------------+
                               |                                    |
        HTTP/JSON (Port 8001)  |             HTTP/JSON (Port 8002)  |
                               v                                    v
+---------------------------------------------+    +---------------------------------------------+
| TIER 2: PYTHON AI RETRIEVAL & ANALYTICS     |    | TIER 3: PYTHON CONVERSATION MEMORY SERVICE  |
| (FastAPI / Typesense / SQLite / HuggingFace)|    | (FastAPI / Neo4j / Graphiti / AsyncIO)      |
| - Hybrid Typesense Vector & BM25 Search     |    | - Episodic Subgraph Extraction              |
| - LLM Query Expansion (Tier 3)              |    | - Temporal Fact Precedence Resolution       |
| - Virtual SQLite Dynamic Engine (Excel)     |    | - Memory Relevance Subgraph Filtering       |
| - Relational Semantic Query Validator (v2)  |    | - Asynchronous Turn Ingestion               |
| - Dataset Classifier & SQL Compiler         |    |                                             |
+---------------------------------------------+    +---------------------------------------------+
```

---

## 2. End-to-End Request & Data Flow (The Real Execution Pipeline)

When a customer or test sends a message, it traverses the following sequence:

```
User Query (e.g. "Laptop Pro 15 er dam koto?")
   │
   ▼
[1. ChatSimulatorController / Webhook]
   │ Resolves Workspace ID (Fails closed if missing)
   │ Resolves / creates Conversation model & Message record
   ▼
[2. HybridRouter::route]
   │ Step 2.0: Check Layer 0 Pending Clarification State
   │ Step 2.1: Fetch last 4 conversation messages for context
   │ Step 2.2: Invoke Fast LLM Semantic Router (DeepSeek-chat, Temp 0.0)
   │           Returns JSON: { route, confidence, security_status, ambiguity_type }
   │ Step 2.3: Enforce confidence threshold (>= 0.70); fallback to UNCERTAIN if low
   ▼
┌──────────────────┬───────────────────┬───────────────────┬───────────────────┬───────────────────┐
│ CHAT             │ KNOWLEDGE         │ ANALYTICS         │ ACTION            │ UNCERTAIN / OOD   │
│ Direct LLM Reply │ FAQ / Policy RAG  │ SQLite SQL Engine │ Seller Email Prop │ Clarify / Abstain │
└────────┬─────────┴─────────┬─────────┴─────────┬─────────┴─────────┬─────────┴─────────┬─────────┘
         │                   │                   │                   │                   │
         │                   ▼                   ▼                   │                   │
         │       [3. MemoryRelevanceGate]  [5. ExcelAnalyticsTool]   │                   │
         │         Checks if query needs     Directs query to        │                   │
         │         historical memory.        python-ai-service       │                   │
         │         (Skipped on static FAQs)  Generates virtual SQLite│                   │
         │                   │               Executes compiled SQL   │                   │
         │                   ▼                                       │                   │
         │       [4. KnowledgeRetrievalTool]                         │                   │
         │         Queries Typesense via                             │                   │
         │         RetrievalClient (Port 8001)                       │                   │
         │         Dense Vector + BM25 Fusion                        │                   │
         │                   │                                       │                   │
         └───────────────────┼───────────────────────────────────────┘                   │
                             ▼                                                           │
                   [6. CustomerSupportService] ◄─────────────────────────────────────────┘
                     Context Assembly:
                     - System Persona & Guidelines
                     - Filtered Neo4j Memory (if relevant)
                     - Grounded Typesense Chunks (Top-3)
                     - Conversation History (Last turns)
                     Final LLM Generation (DeepSeek-chat)
                     Telemetry & Token Usage Recorded
                             │
                             ▼
                   [7. Background Ingestion]
                     Dispatches IngestConversationMemoryJob (Queue: 'memory')
                     Extracts facts to Neo4j graph asynchronously
```

---

## 3. Comprehensive Inventory of Implemented Components

| Component ID | Subsystem | File / Class | Implementation Status | Evidence Level | Verified Test Reference |
| :--- | :--- | :--- | :---: | :---: | :--- |
| **`COMP-ROUT-01`** | Routing | [`app/AI/Routing/HybridRouter.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php) | Implemented | `INTEGRATION_TESTED` | [`tests/Feature/Live/LiveLlmRouterTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/Live/LiveLlmRouterTest.php) |
| **`COMP-RET-01`** | Retrieval | [`app/Services/Retrieval/RetrievalClient.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Retrieval/RetrievalClient.php) | Implemented | `INTEGRATION_TESTED` | [`tests/Feature/Live/LiveTypesenseRetrievalTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/Live/LiveTypesenseRetrievalTest.php) |
| **`COMP-RET-02`** | Retrieval Engine | `python-ai-service/app/retrieval_engine.py` | Implemented | `TESTED` | `tests/Feature/Live/LiveFastApiHttpTest.php` |
| **`COMP-MEM-01`** | Conversation Memory | [`app/Services/Memory/ConversationMemoryService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Memory/ConversationMemoryService.php) | Implemented | `INTEGRATION_TESTED` | [`tests/Feature/ConversationMemoryServiceTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/ConversationMemoryServiceTest.php) |
| **`COMP-MEM-02`** | Memory Relevance Gate | `app/Services/Memory/MemoryRelevanceGate.php` | Implemented | `TESTED` | `tests/Unit/Services/MemoryRelevanceGateTest.php` |
| **`COMP-MEM-03`** | Memory Ingestion Job | [`app/Jobs/IngestConversationMemoryJob.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php) | Implemented | `TESTED` | `tests/Feature/ConversationMemoryPipelineTest.php` |
| **`COMP-ANL-01`** | Analytics Tool | [`app/AI/Tools/ExcelAnalyticsTool.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Tools/ExcelAnalyticsTool.php) | Implemented | `INTEGRATION_TESTED` | [`tests/Feature/Live/LiveSqlAnalyticsTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/Live/LiveSqlAnalyticsTest.php) |
| **`COMP-ANL-02`** | Python Excel Engine | `python-ai-service/app/analytics/excel_engine.py` | Implemented | `TESTED` | `tests/Feature/ChatSimulatorExcelFeatureTest.php` |
| **`COMP-ANL-03`** | Semantic Validator | `python-ai-service/app/analytics/validator.py` | Implemented | `TESTED` | `tests/Unit/Services/AnalyticsClientTest.php` |
| **`COMP-ING-01`** | Document Ingestion | [`app/Services/Knowledge/KnowledgeDocumentService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Knowledge/KnowledgeDocumentService.php) | Implemented | `TESTED` | [`tests/Feature/KnowledgeDocumentTenantIsolationTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/KnowledgeDocumentTenantIsolationTest.php) |
| **`COMP-FAQ-01`** | FAQ Lifecycle & Sync | `app/Services/FAQ/FAQService.php` | Implemented | `TESTED` | [`tests/Feature/FAQTenantAndManagementTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/FAQTenantAndManagementTest.php) |
| **`COMP-SAFE-01`** | Action Safety | [`app/Services/AI/ActionSafetyService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/ActionSafetyService.php) | Implemented | `TESTED` | [`tests/Unit/AI/ActionSafetyServiceTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Unit/AI/ActionSafetyServiceTest.php) |
| **`COMP-OBS-01`** | Observability Telemetry | `app/Http/Controllers/ObservabilityController.php` | Implemented | `TESTED` | [`tests/Feature/ObservabilityTelemetryTest.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Feature/ObservabilityTelemetryTest.php) |

---

## 4. Benchmark Scorecard Evidence Register

The repository contains two rigorous empirical benchmark reports:

### Benchmark Report 1: Static Knowledge 200 Scorecard
- **Source**: [`tests/Datasets/phase_c_baseline_scorecard.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/phase_c_baseline_scorecard.json) (200 real-world queries)
- **Top-1 Retrieval Accuracy**: 73.0%
- **Top-3 Retrieval Accuracy**: 88.0%
- **Mean Reciprocal Rank (MRR)**: 0.7975
- **Semantic Answerability Accuracy**: 79.5%
- **OOD False Positives**: 3 out of 8 (37.5% error rate on out-of-domain rejection)
- **Memory Noise Elimination Rate**: 95.33% (152 unnecessary memory injections blocked)
- **Retrieval P50 Latency**: 60.29 ms
- **Retrieval P95 Latency**: 920.19 ms

### Benchmark Report 2: E2E Generation Faithfulness Audit
- **Source**: [`tests/Datasets/step4_generation_baseline_report.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/step4_generation_baseline_report.json) (48 multi-turn conversations)
- **Overall Groundedness Rate**: 89.6%
- **Overall Faithfulness Rate**: 89.6%
- **Claim Support Ratio**: 77.5%
- **Unsupported Claim Rate**: 10.4%
- **P50 Generation Latency**: 3,297.46 ms (~3.3 seconds)
- **P95 Generation Latency**: 8,961.27 ms (~9.0 seconds)
- **Language-Specific Unsupported Claim Rates**:
  - English: 0 unsupported turns out of 12 (100% grounded)
  - Native Bangla: 0 unsupported turns out of 12 (100% grounded)
  - **Banglish: 2 unsupported turns out of 12 (16.7% ungrounded rate)**
  - **Code-Mixed: 3 unsupported turns out of 12 (25.0% ungrounded rate)**

---

## 5. Architectural & Technical Limitations Identified

1. **No Local Fast-Path in Router**: Every single turn incurs a full remote LLM roundtrip, adding an unavoidable 1.5s–3.5s latency penalty to simple chitchat.
2. **Knowledge Document Indexing Disconnect**: Non-spreadsheet documents (PDFs, DOCX, TXT) uploaded under `KnowledgeDocumentService` are saved to storage, but **never indexed into Typesense or searched by the AI agent**!
3. **Hard Embedding Dependency in Python**: If dense embedding fails, Python `retrieval_engine.py` raises HTTP 503 instead of falling back to Typesense BM25 search.
4. **Unindexed Database Fallback**: When the Python service is offline, fallback search executes `LIKE '%word%'` queries only against `faqs` in MySQL, skipping all documents.
5. **Concurrent Memory Ingestion Race**: Dispatched jobs send overlapping turn windows (last 6 messages) into Neo4j without deduplication locking.
