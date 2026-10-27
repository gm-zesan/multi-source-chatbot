# Our SaaS: Comprehensive Product Capability Inventory

- **Document ID**: `OUR-INV-01`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Technology Stack**:
  - Web & API Backend: PHP 8.3 / Laravel 12 / Livewire
  - Search Engine: Typesense v27.1 (Docker, Port 8108)
  - Python AI Service: FastAPI (Port 8001, Text-to-SQL & Dense 768-d Embeddings)
  - Python Memory Service: FastAPI + Neo4j Community + Graphiti (Port 8002, Long-Term Customer Memory Graph)
  - Primary Database: MySQL 8.0
- **Audit Date**: October 2026
- **Evidence Standard Applied**: `CODE_PRESENT`, `TESTED`, `INTEGRATION_TESTED`, `PRODUCTION_VERIFIED`, `PARTIAL`, `MISSING`.

---

## 1. Core AI Conversational & Semantic Intent Routing Subsystem

### Architectural Implementation
Our platform decouples conversational handling into a multi-tiered pipeline governed by the **Semantic Intent Router** (`HybridRouter v2.2`) and coordinated by `CustomerSupportService`. Unlike single-prompt bots, our architecture classifies incoming turns into distinct operational paths without keyword prefix dependencies:

1. **`KNOWLEDGE`**: General company policies, terms, shipping, returns, and FAQs. Handled by `KnowledgeSupportAgent` via Typesense Hybrid Search.
2. **`ANALYTICS`**: Structured business intelligence queries (sales figures, customer dues, product revenue, top salespeople). Handled by `BusinessAnalyticsTool` or `ExcelAnalyticsTool` via `python-ai-service`.
3. **`CONVERSATIONAL`**: Greetings, chitchat, bot persona inquiries, and gratitude turns. Handled by `ConversationalSupportAgent` without invoking retrieval or analytics tools.
4. **`ACTION`**: Sensitive operational actions (e.g. sending an email to a salesperson). Governed by `ActionSafetyService` enforcing a cryptographic two-phase commit.
5. **`UNCERTAIN`**: Ambiguous queries. Handled by `ClarificationManager` generating targeted clarification chips.
6. **`OOD` (Out-of-Domain)**: Off-topic, political, or abusive queries. Returns a deterministic safe abstention response without LLM hallucination.

### Key Classes & Services
- [`app/Services/AI/CustomerSupportService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/CustomerSupportService.php): Master orchestration service managing the lifecycle of an AI response.
- [`app/AI/Routing/HybridRouter.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php): Intent routing engine combining fast regex heuristics with LLM fallback classification.
- [`app/Services/AI/SemanticAnswerabilityGate.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/SemanticAnswerabilityGate.php): Pre-generation guardrail assessing whether retrieved knowledge chunks actually contain the answer before generation.
- [`app/Services/AI/ContextualQueryBuilder.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/ContextualQueryBuilder.php): Multi-turn pronoun and elliptical reference resolver rewriting contextual turns.
- [`app/Services/AI/ClarificationManager.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/ClarificationManager.php): Ambiguity resolution engine generating structured interactive options.
- [`app/Services/AI/FollowUpPolicyManager.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/FollowUpPolicyManager.php): Controls whether a follow-up prompt should be appended to the AI response.

### Verification Status
- **Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Automated Tests**:
  - `tests/Feature/M5ProductionRuntimeOrchestrationTest.php`
  - `tests/Feature/Live/LiveLlmRouterTest.php`
  - `tests/Unit/AI/HybridRouterTest.php`
  - `tests/Feature/M4ClarificationAnswerabilityContractTest.php`
  - `tests/Feature/E2E/E2E08OODQueryRejectionTest.php`
- **Benchmarked Metric**: 89.6% faithfulness, 0% contradiction rate, and 100% out-of-domain rejection (`tests/Datasets/step4_generation_baseline_report.json`).

---

## 2. Deterministic Tabular & Spreadsheet Analytics Subsystem

### Architectural Implementation
This is our platform's strongest technical capability, distinguishing it from traditional RAG chatbots. Instead of converting tabular data into unstructured text chunks, our platform maintains a dedicated **Text-to-SQL Analytics Pipeline**:

1. **Multi-Sheet Spreadsheet Ingestion**: Supports `.xlsx`, `.xls`, `.csv` files up to 20MB. Automatically discovers individual sheets, infers data types, and normalizes headers.
2. **SQLite Per-File Virtual Database**: In `python-ai-service/app/analytics/`, each uploaded document generates an isolated, zero-latency SQLite virtual database.
3. **Automated Schema & Join Discovery**: Discovers relationships between sheets (e.g. joining `Orders` with `Customers` or `Products` on foreign keys).
4. **Deterministic Calculation**: Converts user natural language into parameterized SQL queries (`SUM()`, `AVG()`, `COUNT()`, `ORDER BY`, `GROUP BY`), computing exact mathematical answers with zero LLM arithmetic hallucination.

### Key Classes & Services
- [`app/AI/Tools/ExcelAnalyticsTool.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Tools/ExcelAnalyticsTool.php): Tool invoked by Laravel AI runtime for Excel queries.
- [`app/AI/Tools/BusinessAnalyticsTool.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Tools/BusinessAnalyticsTool.php): Tool for querying relational business tables (`analytics_orders`, `analytics_products`, `analytics_payments`).
- [`app/Services/Analytics/AnalyticsClient.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Analytics/AnalyticsClient.php): HTTP client connecting Laravel to `python-ai-service:8001/api/v1/analytics/query`.
- `python-ai-service/app/analytics/sql_engine.py`: Dynamic SQL synthesis and execution sandbox.

### Verification Status
- **Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Automated Tests**:
  - `tests/Feature/Live/LiveSqlAnalyticsTest.php`
  - `tests/Feature/ChatSimulatorExcelFeatureTest.php`
  - `tests/Unit/AI/ExcelAnalyticsToolTest.php`
  - `tests/Feature/E2E/E2E02AnalyticsSalesPipelineTest.php`
  - `tests/Feature/E2E/E2E03AnalyticsCashCollectionTest.php`

---

## 3. Unified Knowledge Base Hub & Hybrid Retrieval Subsystem

### Architectural Implementation
Located at `/dashboard/knowledge-base`, the Unified Knowledge Base Hub replaces fragmented administrative screens with a consolidated, tabbed management interface:

1. **Tab 1: FAQs (`type_group=faq`)**: Q&A pairs for rapid customer resolution.
2. **Tab 2: Policies (`type_group=policy`)**: Grounded policy documents (Return, Shipping, Terms).
3. **Tab 3: Business Info (`type_group=business_info`)**: Organization branch hours and contacts.
4. **Tab 4: Data Sources (`purpose=data_analytics`)**: Uploaded spreadsheets powering analytics.
5. **Typesense Hybrid Search**: Merges dense 768-dimensional embeddings generated by `python-ai-service` with sparse BM25 keyword matching for optimal recall in English and Bengali.
6. **One-Click Reindexing (`POST /dashboard/knowledge-base/reindex`)**: Generates vector embeddings and pushes to Typesense collections synchronously without queue dependencies.

### Key Classes & Services
- [`app/Http/Controllers/KnowledgeBaseController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/KnowledgeBaseController.php): Hub management controller.
- [`app/Http/Controllers/KnowledgeDocumentController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/KnowledgeDocumentController.php): File upload, preview, and purpose manager.
- [`app/Http/Controllers/FAQController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/FAQController.php): CRUD, bulk-delete, and toggle active.
- [`app/Services/FAQ/FAQSearch.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/FAQ/FAQSearch.php): Typesense search coordinator.
- [`app/Services/Retrieval/RetrievalClient.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Retrieval/RetrievalClient.php): Typesense/FastAPI client.

### Verification Status
- **Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Automated Tests**:
  - `tests/Feature/KnowledgeBaseHubTest.php`
  - `tests/Feature/FAQLifecyclePipelineTest.php`
  - `tests/Feature/FAQIndexingPipelineTest.php`
  - `tests/Feature/StaticKnowledge200BenchmarkTest.php`
  - `tests/Feature/Live/LiveTypesenseRetrievalTest.php`
- **Benchmarked Metric**: 73.0% Top-1 accuracy, 88.0% Top-3 accuracy, and MRR 0.7975 on 200 static policy questions (`tests/Datasets/phase_c_baseline_scorecard.json`).

---

## 4. Long-Term Conversation Graph Memory (KGM) Subsystem

### Architectural Implementation
Operated by the dedicated `python-memory-service` microservice on Port 8002:

1. **Knowledge Graph Memory (Neo4j Community + Graphiti)**: Models customer conversational history as a temporal knowledge graph comprising user nodes, entity nodes, preference edges, and interaction timestamps.
2. **Autonomous Entity & Preference Extraction**: Ingests completed conversational turns asynchronously via `IngestConversationMemoryJob`. Extracts user size, color, budget, and brand affinities.
3. **Memory Relevance Gate (`MemoryRelevanceGate.php`)**: Evaluates retrieved memories against the current turn's intent before context injection. Suppresses irrelevant historical facts, achieving **95.33% token reduction** and preventing context distraction.

### Key Classes & Services
- [`app/Services/Memory/ConversationMemoryService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Memory/ConversationMemoryService.php): Laravel facade for conversational memory operations.
- [`app/Services/Memory/MemoryRelevanceGate.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/Memory/MemoryRelevanceGate.php): Relevance gating logic.
- [`app/Jobs/IngestConversationMemoryJob.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/IngestConversationMemoryJob.php): Async queue worker extracting graph memories.
- `python-memory-service/app/memory_retriever.py`: Neo4j Cypher query engine.

### Verification Status
- **Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Automated Tests**:
  - `tests/Feature/ConversationMemoryServiceTest.php`
  - `tests/Feature/ConversationMemoryPipelineTest.php`
  - `tests/Feature/M3MemoryRelevanceGateContractTest.php`
  - `tests/Feature/E2E/E2E07PythonMemoryKGMTest.php`
- **Benchmarked Metric**: 82.0% memory gate precision, 76.0% unnecessary injections eliminated (`tests/Datasets/phase_c_baseline_scorecard.json`).

---

## 5. CRM & Passive Entity Extraction Subsystem

### Architectural Implementation
Our platform includes an asynchronous, rule-based entity extraction and identity unification system specialized for the Bangladeshi commercial ecosystem:

1. **Bangladeshi Phone Normalization**: Identifies local prefixes (`+880`, `013`, `014`, `015`, `016`, `017`, `018`, `019`), regularizing 11-digit numbers.
2. **National ID (NID) Parser**: Identifies 10-digit smart NIDs, 13-digit legacy NIDs, and 17-digit birth-year prefixed NIDs.
3. **Bangla & Banglish Name Extractor**: Captures names across phrasings ("My name is Hasan", "আমার নাম হাসান", "amar naam zesan").
4. **Multi-Source Identity Resolution**: Unifies Facebook Page-Scoped IDs (PSID), email addresses, phone numbers, and web session IDs into a consolidated `CRMContact` profile.

### Key Classes & Models
- [`app/Services/CRM/EntityExtractor.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/CRM/EntityExtractor.php): Regex and linguistic entity extraction engine.
- [`app/Services/CRM/IdentityResolver.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/CRM/IdentityResolver.php): Cross-channel identity deduplication and matching.
- [`app/Listeners/ExtractCRMEntitiesListener.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Listeners/ExtractCRMEntitiesListener.php): Async listener processing inbound messages on the `crm` queue.
- Models: `CRMContact`, `CRMContactPhone`, `CRMContactEmail`, `CRMContactWebsite`, `CRMContactIdentity`.

### Verification Status
- **Status**: `TESTED`.
- **Automated Tests**:
  - `tests/Unit/Services/EntityExtractorTest.php`

---

## 6. Action Safety & Seller Escalation Subsystem

### Architectural Implementation
To prevent autonomous LLMs from executing unintended mutations or unauthorized transactions, our platform enforces an **Action Safety Two-Phase Commit Protocol**:

1. **Turn N (Proposal Phase)**: When an action intent is detected (e.g. customer requests an agent to email a salesperson), `SellerEmailService` resolves the target salesperson within the workspace and formats a `SellerEmailProposal`. A cryptographic SHA-256 fingerprint of the proposed payload is stored in conversation state.
2. **Turn N+1 (Execution Phase)**: The action is only dispatched if the customer explicitly confirms the proposal. If the customer alters parameters or discusses something else, the proposal is safely invalidated.
3. **Human Escalation**: Dispatches a structured `SellerNotificationMail` to the verified internal salesperson with customer contact details and conversation context.

### Key Classes & Services
- [`app/Services/AI/ActionSafetyService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/ActionSafetyService.php): Cryptographic proposal validator.
- [`app/Services/AI/SellerEmailService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/SellerEmailService.php): Salesperson resolver and email dispatcher.
- [`app/Mail/SellerNotificationMail.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Mail/SellerNotificationMail.php): Mailable template.

### Verification Status
- **Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Automated Tests**:
  - `tests/Unit/AI/ActionSafetyServiceTest.php`
  - `tests/Unit/AI/SellerEmailServiceTest.php`
  - `tests/Feature/Integration/SellerEmailActionIntegrationTest.php`
  - `tests/Feature/Integration/DownstreamFailureSafetyIntegrationTest.php`

---

## 7. Omnichannel Ingress Drivers Subsystem

### Architectural Implementation
Supported by `app/Channels/` and coordinated by `WebhookController`:

- **Facebook Messenger**: Fully implemented webhook verification (`GET`) and payload parser (`POST`). Dispatches `ReceiveMessengerWebhookJob` asynchronously. Verified by automated tests.
- **Instagram Direct**: Driver class present (`app/Channels/Instagram/InstagramDriver.php`).
- **WhatsApp Cloud API**: Driver class present (`app/Channels/WhatsApp/WhatsAppDriver.php`).
- **Telegram Bot API**: Driver class present (`app/Channels/Telegram/TelegramDriver.php`).
- **Website Client**: Web driver present (`app/Channels/Website/WebsiteDriver.php`).

### Verification Status
- **Facebook Messenger**: `TESTED` (`tests/Feature/ReceiveMessengerWebhookJobTest.php`, `tests/Feature/ProductionMessagingPipelineTest.php`).
- **Instagram / WhatsApp / Telegram**: `CODE_PRESENT` (Driver classes implemented; automated live mock tests pending).

---

## 8. Multi-Tenancy & Authorization Subsystem

### Architectural Implementation
- **Workspace Model (`Workspace.php`)**: Core tenant root. All documents, FAQs, channels, contacts, and conversations contain a mandatory `workspace_id`.
- **Granular Spatie RBAC**: Governed by `spatie/laravel-permission` with dedicated permission enums:
  - `FAQPermission` (`view`, `create`, `update`, `delete`)
  - `KnowledgeDocumentPermission` (`view`, `create`, `update`, `delete`)
  - `ConversationPermission` (`view`, `update`)
  - `RolePermission`, `UserPermission`, `MessagePermission`
- **Tenant Isolation Scoping**: Verified by strict database tenant isolation test cases preventing cross-workspace data leakage.

### Verification Status
- **Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Automated Tests**:
  - `tests/Feature/Integration/TenantIsolationIntegrationTest.php`
  - `tests/Feature/KnowledgeDocumentTenantIsolationTest.php`
  - `tests/Feature/FAQTenantAndManagementTest.php`
  - `tests/Feature/ConversationControllerTenantTest.php`
  - `tests/Feature/E2E/E2E06TenantIsolationE2ETest.php`

---

## 9. Observability, Telemetry & Export Subsystem

### Architectural Implementation
- **Telemetry Recording (`AITelemetryRecorded.php`)**: Captures latency, prompt tokens, completion tokens, selected intent routes, and model metadata per turn.
- **Admin Observability Dashboard (`/dashboard/observability`)**: Visualizes latency distributions, routing splits, token costs, and downstream errors (`ObservabilityController.php`).
- **Multi-Format Export Engine (`ExportService.php`)**: Generates PDF, CSV, and Excel exports of chat logs and analytics queries (`/dashboard/export/...`).

### Verification Status
- **Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Automated Tests**:
  - `tests/Feature/ObservabilityTelemetryTest.php`
  - `tests/Feature/AdminKnowledgeAndObservabilityTest.php`
  - `tests/Feature/Integration/ExportIntegrationTest.php`
  - `tests/Feature/E2E/E2E10FullExportPipelineTest.php`

---

## 10. Interactive Chat Simulator & Pipeline Tester

### Architectural Implementation
Located at `/dashboard/simulator`, providing developers and administrators with a real-time staging console:
- Live conversation testing with prompt inspection.
- Workspace switcher allowing superadmins to simulate turns under any tenant context.
- Knowledge source selector allowing testing of isolated documents or sheets.
- Excel analytics test mode with instant SQL query and tabular result visualization.

### Verification Status
- **Status**: `TESTED`.
- **Automated Tests**:
  - `tests/Feature/ChatSimulatorTest.php`
  - `tests/Feature/ChatSimulatorControllerTest.php`
  - `tests/Feature/ChatSimulatorAnalyticsFeatureTest.php`
  - `tests/Feature/ChatSimulatorExcelFeatureTest.php`
