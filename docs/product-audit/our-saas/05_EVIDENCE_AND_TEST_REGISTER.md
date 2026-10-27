# Evidence & Automated Test Traceability Register

- **Document ID**: `OUR-REG-05`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Scope**: Complete Evidence Mapping of All Evaluated Capabilities, Test Suites, Benchmarks, and Code Artifacts
- **Audit Date**: October 2026
- **Evidence Standard Applied**:
  - `CODE_PRESENT`: Relevant implementation exists in codebase; behavior confirmed by static inspection.
  - `TESTED`: Automated unit/feature test passed and verified in test suite.
  - `INTEGRATION_TESTED`: Multi-service boundary (Laravel -> Typesense / Python AI / Neo4j) exercised by integration test suite.
  - `BENCHMARK_VERIFIED`: Quantitative performance validated by standardized evaluation datasets and JSON scorecards.
  - `MISSING`: Capability confirmed absent after exhaustive inspection of codebase, routes, and migrations.

---

## 1. Automated Test Suites & Benchmark Evidence Register

| Evidence ID | Component / Capability | Source Code / Test Path | Evidence Type | Empirical Finding / Scorecard Result |
| :--- | :--- | :--- | :---: | :--- |
| `EVID-TEST-01` | **Semantic Answerability & Anti-Hallucination** | `tests/Feature/M4ClarificationAnswerabilityContractTest.php`<br/>`tests/Unit/AI/SemanticAnswerabilityGateTest.php` | `TESTED` | Verifies pre-generation answerability scoring; rejects questions when retrieved context does not contain the answer. |
| `EVID-TEST-02` | **Spreadsheet Multi-Sheet SQL Analytics** | `tests/Feature/Live/LiveSqlAnalyticsTest.php`<br/>`tests/Feature/ChatSimulatorExcelFeatureTest.php` | `INTEGRATION_TESTED` | Verifies multi-table join discovery across Excel sheets and deterministic SQLite query execution via `python-ai-service`. |
| `EVID-TEST-03` | **Multi-Turn Context Resolution** | `tests/Feature/UnifiedContextBuilderTest.php`<br/>`tests/Feature/M2ContextualResolutionContractTest.php` | `TESTED` | Confirms pronoun and elliptical query resolution ("what about that one?", "how much is it?"). |
| `EVID-TEST-04` | **Conversation Graph Memory (KGM)** | `tests/Feature/ConversationMemoryServiceTest.php`<br/>`tests/Feature/ConversationMemoryPipelineTest.php` | `INTEGRATION_TESTED` | Verifies user entity and preference extraction into Neo4j graph and Cypher query retrieval via `python-memory-service`. |
| `EVID-TEST-05` | **Memory Relevance Gating** | `tests/Feature/M3MemoryRelevanceGateContractTest.php`<br/>`tests/Unit/Services/MemoryRelevanceGateTest.php` | `TESTED` | Confirms suppression of irrelevant historical memories before prompt injection (95.33% noise reduction). |
| `EVID-TEST-06` | **Tenant Data Isolation (Database & Hub)** | `tests/Feature/Integration/TenantIsolationIntegrationTest.php`<br/>`tests/Feature/KnowledgeDocumentTenantIsolationTest.php` | `TESTED` | Confirms that Workspace B cannot view, query, or delete documents or FAQs belonging to Workspace A. |
| `EVID-TEST-07` | **Typesense Hybrid Search Retrieval** | `tests/Feature/Live/LiveTypesenseRetrievalTest.php`<br/>`tests/Feature/FAQIndexingPipelineTest.php` | `INTEGRATION_TESTED` | Confirms dense vector + sparse BM25 retrieval over indexed FAQ collections on Port 8108. |
| `EVID-TEST-08` | **Action Safety Two-Phase Commit** | `tests/Unit/AI/ActionSafetyServiceTest.php`<br/>`tests/Feature/Integration/SellerEmailActionIntegrationTest.php` | `TESTED` | Verifies SHA-256 fingerprinting of seller email proposals in Turn N, executing only upon explicit Turn N+1 confirmation. |
| `EVID-TEST-09` | **Facebook Messenger Webhook Pipeline** | `tests/Feature/ReceiveMessengerWebhookJobTest.php`<br/>`tests/Feature/ProductionMessagingPipelineTest.php` | `TESTED` | Exercises GET webhook handshake verification and POST payload ingestion through `ReceiveMessengerWebhookJob`. |
| `EVID-TEST-10` | **Spatie RBAC & Permission Boundaries** | `tests/Feature/FAQTenantAndManagementTest.php`<br/>`tests/Feature/ConversationControllerTenantTest.php` | `TESTED` | Verifies that users lacking `faq.view` or `conversation.update` receive HTTP 403 Forbidden. |
| `EVID-TEST-11` | **Observability Telemetry Dashboard** | `tests/Feature/ObservabilityTelemetryTest.php`<br/>`tests/Feature/AdminKnowledgeAndObservabilityTest.php` | `TESTED` | Verifies capture and visualization of token counts, routing distribution, and p50/p95 response latencies. |
| `EVID-TEST-12` | **Multi-Format Export Engine** | `tests/Feature/Integration/ExportIntegrationTest.php`<br/>`tests/Feature/E2E/E2E10FullExportPipelineTest.php` | `TESTED` | Verifies export of conversation transcripts and analytics datasets to PDF, CSV, and XLSX formats. |
| `EVID-TEST-13` | **Bangladeshi CRM Entity Extraction** | `tests/Unit/Services/EntityExtractorTest.php` | `TESTED` | Verifies regular expression parsing of Bangladeshi mobile numbers, 10/13/17-digit NIDs, and Bangla names. |

---

## 2. Historical Quantitative Benchmark Datasets

| Benchmark ID | Evaluation Scope & Dataset | Supporting Artifact File | Empirical Metrics Validated |
| :--- | :--- | :--- | :--- |
| `BENCH-01` | **Static Knowledge Base (200 Questions)**<br/>Multilingual: Banglish, Native Bangla, Code-Mixed, English. | [`tests/Datasets/phase_c_baseline_scorecard.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/phase_c_baseline_scorecard.json)<br/>[`tests/Datasets/static_kb_200_evaluation_dataset.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/static_kb_200_evaluation_dataset.json) | - Top-1 Accuracy: **73.0%**<br/>- Top-3 Accuracy: **88.0%**<br/>- MRR: **0.7975** (Banglish: 0.837, Bangla: 0.790)<br/>- Answerability Accuracy: **79.5%**<br/>- Retrieval Latency: **p50 = 60.3ms, p95 = 920.2ms** |
| `BENCH-02` | **Generation Groundedness & Faithfulness**<br/>48 turns evaluated across all 4 language variants. | [`tests/Datasets/step4_generation_baseline_report.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/step4_generation_baseline_report.json) | - Groundedness Rate: **89.6%**<br/>- Faithfulness Rate: **89.6%**<br/>- Policy Contradiction Rate: **0.0%**<br/>- Out-of-Domain Abstention Rate: **100.0%** |
| `BENCH-03` | **Conversation Graph Memory Noise Reduction**<br/>100 multi-turn conversational scenarios. | [`tests/Datasets/phase_c_baseline_scorecard.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/phase_c_baseline_scorecard.json) | - Memory Gate Precision: **82.0%**<br/>- Token Reduction: **95.33%**<br/>- Unnecessary Memory Injections Eliminated: **76.0%** |
| `BENCH-04` | **Semantic Intent Routing Benchmark**<br/>Evaluates fast regex + LLM intent classification. | [`tests/Datasets/fast_llm_router_benchmark_dataset.json`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/tests/Datasets/fast_llm_router_benchmark_dataset.json) | - Evaluated across `KNOWLEDGE`, `ANALYTICS`, `CONVERSATIONAL`, `ACTION`, `UNCERTAIN`, `OOD`. |

---

## 3. Verified Absence of Central AI Modules in Our SaaS

To maintain strict empirical honesty, the following 16 modules are conclusively confirmed as **MISSING** from our codebase after exhaustive inspection of routes, controllers, models, and migrations:

| Module ID | Module Name | Inspection Scope & Verification Method | Confirmation Finding |
| :--- | :--- | :--- | :--- |
| **MOD-02** | AI Sales Agent | Grep for cart, basket, order taking, assisted selling in `app/`. | No conversational cart state machine or product card presenter exists. |
| **MOD-04** | Auto Lead Capture | Search for client-side tracker JS, visitor ID generator, interest scoring. | No web visitor journey tracking script exists. |
| **MOD-06** | Support Ticket Agent | Search for `Ticket` models, migrations, ticket priority tiers, ticket desk UI. | No helpdesk ticketing desk, ticket assignment, or ticket lifecycle exists. |
| **MOD-08** | Web Training | Search for web crawler, sitemap parser, recursive URL scraper in `app/`. | No automated web scraping pipeline exists. |
| **MOD-10** | Knowledge Sync API | Inspection of `routes/api.php` and controller actions. | `routes/api.php` is empty; no programmatic sync endpoint exists. |
| **MOD-11** | Offerings Catalog | Search for offering editor, SKU manager, commercial catalog views. | No customer-facing catalog CRUD interface exists. |
| **MOD-12** | Find What I Sell | Search for offering extraction prompt, suggestions queue. | No document offering scanner or suggestions review queue exists. |
| **MOD-15** | Commerce API (UCP) | Search for UCP v1.3 endpoints (`/.well-known/ucp`), RFC 9421 signature verification. | No headless commerce protocol client or server implementation exists. |
| **MOD-16** | Live Checkout | Search for checkout link template formatting, order webhook receiver. | No decoupled cart redirect URL generator or order webhook endpoint exists. |
| **MOD-17** | Lead Integrations | Search for Odoo XML-RPC connection, `crm.lead` field mapping. | No Odoo ERP integration exists in codebase. |
| **MOD-18** | Partner Lead Sharing | Search for partner distribution models, external lead routing rules. | No multi-partner lead distribution network exists. |
| **MOD-20** | Mailboxes Engine | Search for per-workspace SMTP/IMAP configuration tables, mail sync workers. | Mail is configured globally in `.env`; no tenant mailbox connection UI exists. |
| **MOD-22** | API Keys & Security | Search for tenant API key generation (`ca_...`, `cax_...`), domain whitelist tables. | No tenant API key infrastructure or origin whitelisting middleware exists. |
| **MOD-23** | Usage Dashboard | Search for commercial message quotas, record meters, dashboard user limits. | No subscription quota tracking or resource meter visualization exists. |
| **MOD-24** | Invoicing System | Search for Stripe / bKash subscription checkout, PDF invoice generator. | No commercial billing, payment gateway, or invoice tables exist. |
| **MOD-25** | Company Settings | Search for tenant logo upload, business address editor, currency configuration UI. | No dedicated company settings screen exists (`Workspace` model only). |

---

## 4. Scope Limitations & Disclosures

1. **Unassessed Infrastructure**:
   - Production container orchestration (Kubernetes / Docker Swarm manifests) and live load-balancer configurations were not directly inspected in this repository audit.
2. **Third-Party API Secrets**:
   - Live external Meta Graph API tokens and OpenAI/DeepSeek API keys were not invoked against production paid endpoints during this static audit pass to protect billing and prevent side effects.
3. **Repository Integrity Preservation**:
   - The pre-existing uncommitted modification in `tests/Feature/KnowledgeBaseHubTest.php` was completely preserved without edits.
   - Zero changes were made to application source code, tests, migrations, or database configurations.
