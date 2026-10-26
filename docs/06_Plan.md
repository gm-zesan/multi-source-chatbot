# 06. Implementation Inventory & Roadmap (Plan)

> **Status**: CURRENTLY_VERIFIED  
> **Source of Truth**: Reverse-engineered from codebase, passing test suites, and verified git commit history.  
> **Last Updated**: October 2026  

---

## 1. Implementation Status Overview

| Phase | Milestone Description | Status | Evidence / Verification Test |
| :--- | :--- | :--- | :--- |
| **Phase 1** | **Multi-Tenant Foundation & RBAC** | `COMPLETED` | `UserPermission`, `RolePermission`, `WorkspaceSeeder`, Spatie migrations. |
| **Phase 2** | **Unified Knowledge Base Hub & Lifecycle** | `COMPLETED` | `KnowledgeBaseHubTest` (22 passed), `KnowledgeDocumentTenantIsolationTest` (5 passed), `FAQJobRaceConditionTest` (19 passed). |
| **Phase 3** | **Semantic Intent Routing Engine** | `COMPLETED` | `HybridRouter.php` (v2.2), `ContextualQueryBuilderTest`, `AiAgentRuntimeFlowTest`. |
| **Phase 4** | **Hybrid Retrieval & Vector Search** | `COMPLETED` | `python-ai-service` (768-d embeddings, Typesense 29.0), `FAQIndexingPipelineTest`, `StaticKnowledge200BenchmarkTest`. |
| **Phase 5** | **Business Analytics & Spreadsheet Query** | `COMPLETED` | `AnalyticsClient.php`, `python-ai-service/app/analytics`, `ChatSimulatorExcelFeatureTest`. |
| **Phase 6** | **Conversation Graph Memory** | `COMPLETED` | `python-memory-service` (Port 8002, Neo4j 5), `ConversationMemoryPipelineTest`, `M1PersistentMemoryCharacterizationTest`. |
| **Phase 7** | **Chat Simulator & Developer Observability** | `COMPLETED` | `ChatSimulatorControllerTest`, `ObservabilityTelemetryTest`, `ExportController`. |

---

## 2. Dependency-Ordered Architectural Inventory

### 2.1 Completed Milestones

- [x] **M1: Multi-Tenant Workspace & Security Scoping**
  - Workspace resolution via `resolveAuthorizedWorkspaceId`.
  - Zero-implicit-tenant invariant (reject missing/invalid workspace on creation).
  - Spatie permission enforcement on routes and DataTables dispatchers (`403 Forbidden` on unauthorized access).

- [x] **M2: Unified Knowledge Base Hub (KB Hub)**
  - Single canonical hub interface (`/dashboard/knowledge-base`) with 4 distinct tabs: `faqs`, `policies`, `business_info`, `data_sources`.
  - Deprecated separate standalone index pages via smart redirects to Hub tab.
  - In-place UI reindexing (`POST /dashboard/knowledge-base/reindex`) directly syncing active FAQs to Typesense.
  - Race condition prevention with per-record locking and lifecycle status tracking (`active`, `syncing`, `sync_failed`).
  - Document ingestion and modal previews for CSV, XLSX, XLS spreadsheets.

- [x] **M3: Fast LLM Semantic Router (v2.2)**
  - 6 distinct routing capabilities: `KNOWLEDGE`, `ANALYTICS`, `CONVERSATIONAL`, `ACTION`, `UNCERTAIN`, `OOD`.
  - DeepSeek and OpenRouter LLM driver integrations with strict JSON schema parsing.
  - Two-phase action safety mechanism (SHA-256 fingerprinting on seller notifications).
  - Clarification chip generator for ambiguous customer queries.

- [x] **M4: Hybrid Vector & Relational Retrieval Engine**
  - Dense embedding generation (768-d via SentenceTransformers/BGE models).
  - Typesense 29.0 hybrid vector + BM25 keyword search.
  - Resilient relational MySQL fallback (`BusinessSourceOfTruthService`) when search cluster is degraded.

- [x] **M5: Text-to-SQL & Spreadsheet Analytics Engine**
  - Natural language query to AST compiler.
  - Read-only safe execution against `analytics_*` tables and uploaded spreadsheets.
  - Cross-source disambiguation and currency handling.

- [x] **M6: Persistent Conversation Graph Memory**
  - Neo4j 5-community integration via Bolt protocol.
  - Entity, order, and preference extraction from chat history.
  - Memory Relevance Gate validating relevance before context window injection.

- [x] **M7: Telemetry, Observability & Exporting**
  - Observability dashboard showing token consumption, cache hits, and latency breakdowns.
  - Conversation transcript and analytics report export to PDF (DomPDF), CSV, and Excel (Maatwebsite).

---

### 2.2 Deprecated & Decommissioned Artifacts

- [x] **`python-ingestion-worker`**:
  - *Reason*: Redundant microservice.
  - *Action Taken*: Decommissioned and physically deleted from disk. Spreadsheet processing unified into `python-ai-service`.
- [x] **`python-langchain-service`**:
  - *Reason*: Phase L1/L2 LangGraph research completed. Native PHP `HybridRouter` demonstrated 3x lower latency and identical routing accuracy without Python orchestration overhead.
  - *Action Taken*: Decommissioned and physically deleted from disk. `LangChainRouter.php` removed from Laravel codebase.
- [x] **Standalone `/dashboard/faqs` and `/dashboard/documents` Screens**:
  - *Reason*: Fragmented user experience.
  - *Action Taken*: Replaced by the Unified Knowledge Base Hub. Legacy URLs issue permanent redirects to the appropriate Hub tab.

---

### 2.3 Pending & Future Roadmap Items

- [ ] **R1: Asynchronous Batch Reindexing for Enterprise Tenants**
  - *Scope*: Add queue-backed chunked reindexing for tenants exceeding 10,000 FAQ items to prevent web request timeouts during full catalog re-embeddings.
  - *Priority*: Medium (Current UI reindexing handles existing tenant sizes <2,000 items in <1.5s).

- [ ] **R2: Omni-Channel Webhook Dead-Letter Queue Dashboard**
  - *Scope*: Admin UI panel to inspect failed Facebook Messenger webhook deliveries, view error payloads, and trigger manual retries.
  - *Priority*: Low.

- [ ] **R3: Dedicated Multi-Cluster Typesense Isolation**
  - *Scope*: Support physical cluster separation for high-tier enterprise tenants requiring dedicated isolated vector database instances.
  - *Priority*: Low.
