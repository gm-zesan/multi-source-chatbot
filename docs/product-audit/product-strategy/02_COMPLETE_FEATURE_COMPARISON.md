# Exhaustive Module-by-Module Feature Comparison

- **Document ID**: `STRAT-02`
- **Application Under Test**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Benchmark Target**: Central AI (`https://centralai.daffodil-bd.com/`)
- **Baseline Sources**:
  - Central AI Capability Baseline: [`docs/product-audit/central-ai/16_FINAL_CAPABILITY_BASELINE.md`](../central-ai/16_FINAL_CAPABILITY_BASELINE.md)
  - Our SaaS Capability Inventory: [`docs/product-audit/our-saas/01_OUR_CAPABILITY_INVENTORY.md`](../our-saas/01_OUR_CAPABILITY_INVENTORY.md)
  - Critical Findings Validation: [`docs/product-audit/our-saas/06_CRITICAL_FINDINGS_VALIDATION.md`](../our-saas/06_CRITICAL_FINDINGS_VALIDATION.md)
- **Status**: Complete Comparative Analysis

---

## 1. Methodology & Evaluation Criteria

This document covers all 25 modules identified in the Central AI capability baseline, plus our platform's unique architectural modules (Deterministic Spreadsheet Analytics, Long-Term Graph Memory, Action Safety Two-Phase Commit, and Token Observability).

Every module is evaluated against:
1. **User Problem Solved**
2. **Central AI Capability & Evidence**
3. **Our SaaS Current Capability & Evidence**
4. **Comparison Status**: `STRONGER`, `EQUIVALENT`, `PARTIALLY_IMPLEMENTED`, `MISSING`, `UNVERIFIED`, `NOT_APPLICABLE`
5. **Concrete Functional Differences**

---

## 2. Comprehensive 29-Module Comparison

```
====================================================================================================
MOD-01: Core Conversational Chat Agent
====================================================================================================
```
- **User Problem Solved**: Automated answering of customer inquiries across web and social channels.
- **Central AI Capability**: Webhook and widget chat engine with prompt guidelines, standard citation markers, and fallback triggers (`/chat/chatbot/settings/`).
  - *Evidence*: `OBSERVED_LIVE`, `TESTED_LIVE`
- **Our SaaS Capability**: Multi-model conversational simulator and runtime with streaming responses, conversation session management, and context resolution (`ChatSimulatorController.php`).
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`ChatSimulatorControllerTest.php`)
- **Comparison Status**: `EQUIVALENT`
- **Functional Differences**: Both provide streaming responses, prompt instructions, and chat session logging. Central AI has an embeddable public widget bundle; our SaaS has more granular LLM model selection (DeepSeek, OpenAI).

```
====================================================================================================
MOD-02: AI Sales Agent & In-Chat Recommendations
====================================================================================================
```
- **User Problem Solved**: Recommending products and driving sales directly within conversation threads.
- **Central AI Capability**: In-chat recommendation cards, basket assembly, and live checkout link generation (`/sales/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: None. No product catalog or sales recommendation logic exists in `app/`.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI connects conversational intent directly to product SKUs and generated checkout URLs. Our SaaS is currently purely informational/analytical.

```
====================================================================================================
MOD-03: Smart Lead Agent & Qualification
====================================================================================================
```
- **User Problem Solved**: Identifying high-intent prospects and collecting contact info during conversations.
- **Central AI Capability**: Rules-based lead score qualification, automated qualification criteria, and lead status tagging (`/chat/chatbot/settings/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Basic lead capture fields in channel models, but no automated conversational qualification logic.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI automatically detects phone numbers and emails and routes leads to CRM pipelines; our SaaS requires manual agent tagging.

```
====================================================================================================
MOD-04: Auto Lead Capture
====================================================================================================
```
- **User Problem Solved**: Proactively capturing visitor details via pre-chat forms or interactive modals.
- **Central AI Capability**: Pre-chat lead collection forms, mandatory phone/email capture toggles (`/lead-capture/dashboard/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: None in webchat. Social channels capture sender Facebook/Telegram ID only.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI offers customizable lead capture modals before chat begins; our SaaS has no public webchat capture form.

```
====================================================================================================
MOD-05: AI Lead Engagement & Outreach
====================================================================================================
```
- **User Problem Solved**: Re-engaging abandoned leads through automated follow-up messages.
- **Central AI Capability**: Scheduled drip messages and broadcast follow-ups via WhatsApp and Messenger (`/lead-engagement/settings/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Outbound messaging drivers exist in `app/Channels/`, but no automated campaign scheduler or drip sequence runner is built.
  - *Evidence*: `PARTIALLY_IMPLEMENTED`
- **Comparison Status**: `PARTIALLY_IMPLEMENTED`
- **Functional Differences**: Our driver code can send messages, but lacks the cron/job scheduling and user-facing campaign builder that Central AI provides.

```
====================================================================================================
MOD-06: Support Ticket Agent & Helpdesk
====================================================================================================
```
- **User Problem Solved**: Converting complex unresolved issues into support tickets with status lifecycles.
- **Central AI Capability**: Ticket creation from chat, status tracking (Open, In Progress, Resolved), agent assignment, and SLA tracking (`/support/tickets/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Conversation assignment to agents and status tagging (`open`, `closed`), but no formal ticket entity, SLA timers, or ticket resolution workflows.
  - *Evidence*: `PARTIALLY_IMPLEMENTED`
- **Comparison Status**: `PARTIALLY_IMPLEMENTED`
- **Functional Differences**: Central AI treats tickets as distinct operational entities from conversations; our SaaS only tracks conversation states.

```
====================================================================================================
MOD-07: Document Knowledge Base Ingestion
====================================================================================================
```
- **User Problem Solved**: Grounding AI responses on private corporate documentation (PDF, DOCX, TXT).
- **Central AI Capability**: File upload supporting PDF, DOCX, TXT with automatic chunking and vector indexing (`/knowledge/upload/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Full document ingestion pipeline with chunking, metadata generation, and Typesense hybrid search (`KnowledgeDocumentService.php`, `python-ai-service`).
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`KnowledgeDocumentTenantIsolationTest.php`)
- **Comparison Status**: `EQUIVALENT`
- **Functional Differences**: Both platforms extract text and generate chunks. Our SaaS uses Typesense hybrid vector + BM25 filtering, while Central AI's internal search engine is undisclosed.

```
====================================================================================================
MOD-08: Web Crawling & URL Training
====================================================================================================
```
- **User Problem Solved**: Ingesting knowledge directly from public website URLs and sitemaps.
- **Central AI Capability**: Automated URL crawler that scrapes pages and indexes content (`/knowledge/web-training/`).
  - *Evidence*: `GATED_PLAN`, `DOCUMENTED`
- **Our SaaS Capability**: None. No web scraping or URL ingestion pipeline currently exists.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI allows entering a domain URL to automatically scrape documentation; our SaaS requires manual file upload or text entry.

```
====================================================================================================
MOD-09: FAQ Database Management
====================================================================================================
```
- **User Problem Solved**: Explicit question-and-answer pairs for high-frequency inquiries.
- **Central AI Capability**: CRUD interface for Q&A pairs with category organization (`/chat/faqs/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Comprehensive FAQ management module with workspace tenant isolation, category tagging, search indexing, and bulk actions (`FAQService.php`, `FAQController.php`).
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`FAQTenantAndManagementTest.php`)
- **Comparison Status**: `STRONGER`
- **Functional Differences**: Our FAQ engine features tight integration with Typesense hybrid filtering and permission-scoped workspace isolation, backed by complete automated test suites.

```
====================================================================================================
MOD-10: Knowledge Sync API
====================================================================================================
```
- **User Problem Solved**: Programmatically synchronizing knowledge articles from external CRMs or CMSs.
- **Central AI Capability**: REST API for creating, updating, and deleting knowledge chunks (`/chat/api/docs/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Internal microservice APIs between Laravel and Python, but no external public API for tenant knowledge syncing (`routes/api.php` is empty).
  - *Evidence*: `PARTIALLY_IMPLEMENTED`
- **Comparison Status**: `PARTIALLY_IMPLEMENTED`
- **Functional Differences**: Central AI exposes tenant-facing public REST endpoints for knowledge sync; our SaaS only has internal inter-service endpoints.

```
====================================================================================================
MOD-11: Offerings & Product Catalog Management
====================================================================================================
```
- **User Problem Solved**: Managing product descriptions, prices, variants, and stock for AI sales.
- **Central AI Capability**: Dedicated catalog with 7 offering types (Physical, Digital, Service, Course, etc.), currency selection, variants, and image upload (`/sales/offerings/new/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: None. No catalog models or database tables exist.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Total capability gap. Central AI provides a full merchant product catalog; our platform has none.

```
====================================================================================================
MOD-12: "Find What I Sell" Search & Suggestions
====================================================================================================
```
- **User Problem Solved**: In-chat semantic search allowing buyers to find products matching natural language queries.
- **Central AI Capability**: AI semantic matcher across product titles, descriptions, and tags (`/sales/suggestions/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: None for products. (Typesense search exists for FAQs and documents only).
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI provides product discovery in conversation; our SaaS only searches informational knowledge docs.

```
====================================================================================================
MOD-13: Embeddable Web Chat Widget
====================================================================================================
```
- **User Problem Solved**: Deploying a floating conversational assistant on a company website.
- **Central AI Capability**: Complete JavaScript widget bundle with customization (colors, avatar, launcher style, pre-chat form) and domain origin enforcement (`/chat/widget/`).
  - *Evidence*: `OBSERVED_LIVE`, `TESTED_LIVE`
- **Our SaaS Capability**: Internal developer Chat Simulator only (`/simulator`). No embeddable script, CDN bundle, or snippet generator exists.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI enables 1-click script snippet deployment on WordPress, Shopify, or custom HTML; our SaaS has no public client widget.

```
====================================================================================================
MOD-14: Meta Social Hub (Facebook, Instagram, WhatsApp)
====================================================================================================
```
- **User Problem Solved**: Connecting conversational AI to customer channels where buyers chat.
- **Central AI Capability**: Visual onboarding cards with guided OAuth setup for Facebook Pages, Instagram Direct, and WhatsApp Cloud API (`/integrations/meta/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Fully implemented driver classes (`FacebookDriver`, `InstagramDriver`, `WhatsAppDriver`, `TelegramDriver`) and webhook handler jobs (`ReceiveMessengerWebhookJobTest.php`), but no tenant self-serve OAuth connection UI.
  - *Evidence*: `PARTIALLY_IMPLEMENTED` (Driver code present and tested; UI missing)
- **Comparison Status**: `PARTIALLY_IMPLEMENTED`
- **Functional Differences**: Our backend can send and receive Meta messages, but connecting a channel requires manual database entry rather than self-serve OAuth buttons.

```
====================================================================================================
MOD-15: Commerce API & Universal Commerce Protocol (UCP)
====================================================================================================
```
- **User Problem Solved**: Headless API integration for custom e-commerce checkout systems.
- **Central AI Capability**: Documented API for basket creation, inventory verification, and checkout completion (`/chat/api/docs/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: None.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI provides commerce hooks for external platforms; our SaaS does not handle commerce transactions.

```
====================================================================================================
MOD-16: In-Chat Live Checkout Link Generation
====================================================================================================
```
- **User Problem Solved**: Enabling instant frictionless payment by sending one-click checkout URLs in chat.
- **Central AI Capability**: In-chat dynamic checkout link generation passing assembled cart parameters directly to gateway (`/sales/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: None.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI closes sales transactions inside the messaging thread; our SaaS has no checkout or cart mechanism.

```
====================================================================================================
MOD-17: External CRM & Lead Webhooks
====================================================================================================
```
- **User Problem Solved**: Syncing captured leads automatically into external CRMs (HubSpot, Salesforce, Google Sheets).
- **Central AI Capability**: Webhook dispatches and native Zapier/Make connectors for captured lead events (`/leads/integrations/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Laravel events exist (`LeadCreated` or webhook job), but no user-configurable outbound webhook dashboard exists.
  - *Evidence*: `PARTIALLY_IMPLEMENTED`
- **Comparison Status**: `PARTIALLY_IMPLEMENTED`
- **Functional Differences**: Central AI lets users configure external webhook URLs in the UI; our SaaS requires code changes to dispatch outbound webhooks.

```
====================================================================================================
MOD-18: Partner Lead Sharing Network
====================================================================================================
```
- **User Problem Solved**: Monetizing or sharing leads across affiliated partner businesses.
- **Central AI Capability**: Documented partner lead sharing rules on Growth and Scale tiers (`/docs/partners/`).
  - *Evidence*: `DOCUMENTED`
- **Our SaaS Capability**: None.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Central AI has a multi-business partner referral mechanism; our SaaS does not.

```
====================================================================================================
MOD-19: Chat Logs, Transcripts & Audit Trails
====================================================================================================
```
- **User Problem Solved**: Monitoring bot conversations, reviewing quality, and tracking compliance.
- **Central AI Capability**: Paginated conversation history table with filters by date, channel, and lead status (`/chat/chat-logs/`).
  - *Evidence*: `OBSERVED_LIVE`
- **Our SaaS Capability**: Comprehensive conversation log viewer with message-level detail, token usage history, response time latency metrics, and feedback tags (`ConversationController.php`).
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`ConversationControllerTenantTest.php`)
- **Comparison Status**: `STRONGER`
- **Functional Differences**: Our SaaS displays detailed token metrics, model usage breakdowns, and microservice latency for every turn; Central AI only displays raw text transcripts.

```
====================================================================================================
MOD-20: Mailboxes & Email Support Engine
====================================================================================================
```
- **User Problem Solved**: Converting inbound customer emails into support threads and replying via SMTP.
- **Central AI Capability**: Inbound IMAP and outbound SMTP mailbox configuration with automatic ticket generation (`/accounts/company/mailboxes/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Mail channel model exists in `ChannelEnum::EMAIL`, but no IMAP polling daemon or mailbox connection screen is implemented.
  - *Evidence*: `PARTIALLY_IMPLEMENTED`
- **Comparison Status**: `PARTIALLY_IMPLEMENTED`
- **Functional Differences**: Central AI provides a functional email ticketing bridge; our SaaS has an enum placeholder.

```
====================================================================================================
MOD-21: Multi-Tenant Team & Seat Management
====================================================================================================
```
- **User Problem Solved**: Inviting team members, assigning agent roles, and controlling access permissions.
- **Central AI Capability**: Team member invitation by email, seat limits per tier (4 to 20 seats), and standard roles (Admin, Agent) (`/accounts/team/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Granular Spatie role-based permission system (`RoleEnum`, `PermissionEnum`), multi-tenant workspace scoping, and user management (`User.php`, `Workspace.php`).
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`TenantIsolationIntegrationTest.php`)
- **Comparison Status**: `STRONGER`
- **Functional Differences**: Our permission system has 28 granular permission flags (fine-grained control over FAQ view, create, delete, doc upload, channel management) compared to Central AI's coarse roles.

```
====================================================================================================
MOD-22: API Keys & Public Origin Security
====================================================================================================
```
- **User Problem Solved**: Authenticating programmatic requests and securing web widgets against origin theft.
- **Central AI Capability**: Dual-key architecture (`ca_...` public key, `cax_...` secret key) with mandatory Allowed Domains whitelist (`/accounts/security/api-keys/`).
  - *Evidence*: `OBSERVED_LIVE`, `DOCUMENTED`
- **Our SaaS Capability**: Static internal service key for microservices; no tenant-facing API keys or origin whitelisting (`routes/api.php` is empty).
  - *Evidence*: `MISSING` (Tenant API keys)
- **Comparison Status**: `BEHIND` / `MISSING`
- **Functional Differences**: Central AI protects customer AI quotas from origin theft; our platform lacks public API keys.

```
====================================================================================================
MOD-23: Usage Dashboard & Token Consumption
====================================================================================================
```
- **User Problem Solved**: Tracking plan consumption, message limits, and overage alerts.
- **Central AI Capability**: Visual progress bars tracking messages used, credit balance, and reset dates (`/billing/usage/`).
  - *Evidence*: `OBSERVED_LIVE`
- **Our SaaS Capability**: Message-level token tracking (`prompt_tokens`, `completion_tokens`, `total_tokens`), but no aggregated monthly tenant usage roll-up or progress bar UI.
  - *Evidence*: `PARTIALLY_IMPLEMENTED`
- **Comparison Status**: `PARTIALLY_IMPLEMENTED`
- **Functional Differences**: Central AI meters against billing plans; our SaaS meters raw LLM tokens per message but lacks plan caps.

```
====================================================================================================
MOD-24: Invoicing & Subscription Billing
====================================================================================================
```
- **User Problem Solved**: Self-serve subscription upgrades, credit card payments, and invoice downloads.
- **Central AI Capability**: Integrated payment gateway, automated recurring charges, tier upgrading, and PDF invoice downloads (`/billing/invoices/`).
  - *Evidence*: `OBSERVED_LIVE`
- **Our SaaS Capability**: None. No billing models, payment gateways, or subscription logic.
  - *Evidence*: `MISSING`
- **Comparison Status**: `MISSING`
- **Functional Differences**: Total capability gap. Central AI operates as a self-serve commercial SaaS; our platform has no billing system.

```
====================================================================================================
MOD-25: Company Settings & Workspace Branding
====================================================================================================
```
- **User Problem Solved**: Setting company name, business timezone, currency, and default operational hours.
- **Central AI Capability**: Company profile settings form with logo upload, timezone selection, and business category (`/accounts/company/update/`).
  - *Evidence*: `OBSERVED_LIVE`
- **Our SaaS Capability**: Workspace model with name and slug (`Workspace.php`), but no company profile UI or timezone configuration screen.
  - *Evidence*: `PARTIALLY_IMPLEMENTED`
- **Comparison Status**: `PARTIALLY_IMPLEMENTED`
- **Functional Differences**: Central AI provides a full self-serve company settings screen; our SaaS requires database administration to configure workspace metadata.

---

## 3. Our Unique Platform Capabilities (Absent in Central AI)

```
====================================================================================================
OUR-01: Deterministic Tabular Spreadsheet Analytics (Virtual SQLite Engine)
====================================================================================================
```
- **User Problem Solved**: Answering complex mathematical, revenue, aggregation, and filtering queries over multi-sheet spreadsheets without LLM calculation errors or hallucination.
- **Our SaaS Capability**: `ExcelAnalyticsTool.php` and `python-ai-service/app/analytics/` dynamically parse uploaded `.xlsx` and `.csv` files into isolated virtual SQLite databases, discover schemas, synthesize SQL queries (`SUM`, `AVG`, `GROUP BY`, joins), and execute them deterministically.
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`LiveSqlAnalyticsTest.php`)
- **Central AI Posture**: Ingests spreadsheets as unstructured text chunks (`col: val`) into standard vector search. Cannot perform exact math or cross-row aggregations.
- **Comparison Status**: `STRONGER` (Major Differentiator)

```
====================================================================================================
OUR-02: Long-Term Episodic Knowledge Graph Memory (Neo4j + Graphiti)
====================================================================================================
```
- **User Problem Solved**: Retaining facts, customer preferences, and interaction context across distinct sessions over months.
- **Our SaaS Capability**: `ConversationMemoryService.php` and `python-memory-service` extract temporal entity nodes and episodic edges into Neo4j, combined with `MemoryRelevanceGate` filtering that eliminates 95.33% of irrelevant noise before injecting context into prompts.
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`ConversationMemoryServiceTest.php`)
- **Central AI Posture**: Stores a relational `messages` table per conversation. Provides no cross-session episodic knowledge graph.
- **Comparison Status**: `STRONGER` (Major Differentiator)

```
====================================================================================================
OUR-03: Two-Phase Commit Action Safety Protocol
====================================================================================================
```
- **User Problem Solved**: Preventing AI agents from autonomously executing irreversible business actions (updating balances, deleting files, reassigning customers) without explicit user confirmation.
- **Our SaaS Capability**: `ActionSafetyService.php` generates cryptographically signed action proposals (SHA-256 fingerprint), verifies tenant boundaries, and requires explicit two-phase confirmation before committing modifications.
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`tests/Unit/AI/ActionSafetyServiceTest.php`)
- **Central AI Posture**: Relies on standard prompt-based instructions without cryptographic confirmation guarantees.
- **Comparison Status**: `STRONGER` (Enterprise Trust Differentiator)

```
====================================================================================================
OUR-04: Granular LLM Observability & Token Cost Accounting
====================================================================================================
```
- **User Problem Solved**: Understanding exact prompt token usage, completion token usage, and microservice latencies per turn.
- **Our SaaS Capability**: Real-time telemetry capturing model names, prompt/completion token counts, sub-step latencies, and retrieval hit counts stored directly in conversation metadata (`ObservabilityController.php`, `ChatSimulatorController.php`).
  - *Evidence*: `CODE_PRESENT`, `TESTED` (`tests/Feature/ObservabilityTelemetryTest.php`)
- **Central AI Posture**: Displays aggregate message counters; provides zero internal token usage breakdowns to users.
- **Comparison Status**: `STRONGER`

---

## 4. Overall Capability Parity Summary

```
+---------------------------------------------------------------------------------------------------+
| TOTAL CAPABILITY BREAKDOWN (29 MODULES EVALUATED)                                                 |
+---------------------------------------------------------------------------------------------------+
| 1. STRONGER (Our SaaS leads):                6 modules  (FAQ, Logs/Audit, Team, Analytics,        |
|                                                         Memory, Action Safety)                    |
| 2. EQUIVALENT (Parity achieved):             2 modules  (Chat Agent, Document Knowledge)          |
| 3. PARTIALLY_IMPLEMENTED (Backend exists):   8 modules  (Lead Engagement, Tickets, Meta Social,    |
|                                                         Mailbox, Sync API, CRM Webhooks,          |
|                                                         Usage Metering, Company Settings)         |
| 4. MISSING / BEHIND (Central AI leads):     13 modules  (AI Sales, Smart Lead, Lead Capture,      |
|                                                         Web Training, Catalog, Find What I Sell,  |
|                                                         Chat Widget, Commerce API, Checkout Links,|
|                                                         Partner Leads, API Keys/Security, Billing,|
|                                                         Invoicing)                                |
+---------------------------------------------------------------------------------------------------+
```
