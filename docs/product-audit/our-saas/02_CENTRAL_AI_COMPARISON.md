# Feature-by-Feature Parity Audit: Our SaaS vs. Central AI Baseline

- **Document ID**: `OUR-COMP-02`
- **Baseline Subject**: Central AI 25 Functional Modules (`MOD-01` to `MOD-25`)
- **Evaluation Subject**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Audit Date**: October 2026
- **Evidence Standard Applied**:
  - Central AI Statuses: `OBSERVED_LIVE`, `TESTED_LIVE`, `DOCUMENTED`, `GATED_PLAN`, `UNTESTED_BEHAVIOR`, `INFERRED`, `UNVERIFIED`.
  - Our SaaS Implementation Statuses: `CODE_PRESENT`, `TESTED`, `INTEGRATION_TESTED`, `PRODUCTION_VERIFIED`, `PARTIAL`, `MISSING`, `UNVERIFIED`.
  - Parity Classifications: `missing`, `partial`, `equivalent`, `exceeds baseline`, `not applicable`.

---

## Complete 25-Module Parity Audit

```
================================================================================
MODULE 01: Chat Agent (Core Conversational AI Runtime)
================================================================================
```
- **Central AI Capability & Evidence**: RAG conversational engine retrieving the 8 most relevant passages across documents, FAQs, and web crawls; system prompt and tone customization. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-03, EVID-DOC-01, 07]`.
- **Our SaaS Implementation**: Complete multi-source conversational runtime orchestrated by `CustomerSupportService`. Uses `HybridRouter v2.2` to classify intent (`KNOWLEDGE`, `ANALYTICS`, `CONVERSATIONAL`, `ACTION`, `UNCERTAIN`, `OOD`). Routes knowledge turns to `KnowledgeSupportAgent` via Typesense Hybrid Search (dense 768-d vectors + sparse BM25) with `SemanticAnswerabilityGate` anti-hallucination verification.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Services/AI/CustomerSupportService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/CustomerSupportService.php), [`app/AI/Routing/HybridRouter.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php), [`app/Services/AI/SemanticAnswerabilityGate.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/SemanticAnswerabilityGate.php).
  - Routes: `GET /dashboard/simulator`, `POST /dashboard/simulator/send`.
  - Tests: `tests/Feature/M5ProductionRuntimeOrchestrationTest.php`, `tests/Feature/StaticKnowledge200BenchmarkTest.php`.
- **Functional Differences**: Our platform provides strict hybrid search (BM25 + vectors) and a pre-generation answerability gate that rejects ungrounded queries; Central AI retrieves 8 passages and relies purely on LLM prompt instructions.
- **Tenant Isolation & Security**: Both enforce tenant isolation. Our system scopes queries strictly via `workspace_id` in Typesense filter collections and database models.
- **Dependencies**: Typesense (Port 8108), `python-ai-service` (Port 8001), LLM provider (OpenAI / DeepSeek / Claude).
- **Implementation & Verification Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Functional Parity**: `equivalent`.
- **Recommended Action**: Retain current architecture; add tenant-editable system prompt settings in web UI.

```
================================================================================
MODULE 02: AI Sales Agent (In-Chat Commerce & Assisted Selling)
================================================================================
```
- **Central AI Capability & Evidence**: Assisted selling across 7 offering kinds (`Product`, `Service`, `Course Intake`, `Appointment`, `Event Date`, `Plan`, `Custom`), 3 completion modes (`Handled in Central AI`, `Live checkout on your site`, `Created in our system`), and payment method notes (bKash, Nagad, Bank Transfer, COD). `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-08, EVID-DOC-02]`.
- **Our SaaS Implementation**: **No conversational sales or commerce runtime exists**. Our system has `analytics_products` and `analytics_orders` tables, but these are purely historical read models for analytical queries (`ExcelAnalyticsTool`), not an interactive sales catalog.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Models/AnalyticsProduct.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/AnalyticsProduct.php) (Analytical read model only).
  - Routes: None.
  - Tests: None for conversational shopping.
- **Functional Differences**: Central AI builds shopping baskets in chat, presents rich visual product cards, and routes customers to external checkout links. Our system can only answer analytical questions about past sales or general knowledge questions about policies.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Relational catalog database, cart session cache.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: **High-priority roadmap item**. Implement an `Offerings` catalog and in-chat recommendation card presenter tailored for Bangladeshi F-Commerce sellers.

```
================================================================================
MODULE 03: Smart Lead Agent (Daily Batch Summarizer & Categorizer)
================================================================================
```
- **Central AI Capability & Evidence**: Asynchronous daily background cron job (default 03:00) that parses past completed chats, synthesizes customer intent summaries, and classifies them into up to 50 user-defined business categories on `/leads/`. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-03, EVID-DOC-03]`.
- **Our SaaS Implementation**: Partial. Our platform features real-time passive entity extraction via `ExtractCRMEntitiesListener` and `EntityExtractor.php` (extracting names, phones, emails, NIDs, and addresses into `crm_contacts`), but we do **NOT** have an overnight batch conversation summarizer or a 50-category classification dashboard.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Services/CRM/EntityExtractor.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/CRM/EntityExtractor.php), [`app/Listeners/ExtractCRMEntitiesListener.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Listeners/ExtractCRMEntitiesListener.php).
  - Routes: None for batch lead categorization.
  - Tests: `tests/Unit/Services/EntityExtractorTest.php`.
- **Functional Differences**: Central AI summarizes what the customer wanted and tags category buckets for human sales review. Our system captures raw contact details into database records but leaves conversation summaries ungenerated.
- **Tenant Isolation & Security**: Both scope data to tenant workspace.
- **Dependencies**: Cron scheduler, LLM summarization pipeline.
- **Implementation & Verification Status**: `PARTIAL`.
- **Functional Parity**: `partial`.
- **Recommended Action**: **High-priority quick win**. Implement an artisan command / scheduled job (`app:summarize-daily-leads`) that runs overnight, summarizes unhandled chats, and populates a categorization table.

```
================================================================================
MODULE 04: Auto Lead Capture (Anonymous Visitor Tracking & Scoring)
================================================================================
```
- **Central AI Capability & Evidence**: Client-side tracker script measuring dwell time, scroll depth, and pageviews to compute an "Interest Score", retroactively binding history upon identity capture. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-11, EVID-DOC-04]`.
- **Our SaaS Implementation**: **No web visitor journey tracking script exists**. Our lead extraction operates exclusively on messages sent inside active chat sessions.
- **Supporting Files, Routes & Tests**: None.
- **Functional Differences**: Central AI tracks pre-chat browsing journeys on the merchant's website. Our system only observes interactions after a user sends a message.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Client tracking JavaScript bundle, high-throughput event ingestion API.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Defer. Core conversational commerce and chat widget deployment take precedence over client-side web analytics.

```
================================================================================
MODULE 05: AI Lead Engagement (Mailbox Email Follow-Up Add-On)
================================================================================
```
- **Central AI Capability & Evidence**: Automated multi-stage email follow-up sequences sent from connected corporate mailboxes (`/accounts/company/mailboxes/`) with `draft_only` and `autonomous` operating modes, daily sending caps, and suppression lists. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-12, EVID-DOC-05]`.
- **Our SaaS Implementation**: Partial. We have `SellerEmailService` which dispatches `SellerNotificationMail` via Laravel's global `Mail::to()` configuration when human intervention is warranted. However, we do **NOT** have multi-turn email follow-up sequences to prospects, no per-tenant corporate mailbox configuration, and no staged email draft queue.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Services/AI/SellerEmailService.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/AI/SellerEmailService.php), [`app/Mail/SellerNotificationMail.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Mail/SellerNotificationMail.php).
  - Tests: `tests/Unit/AI/SellerEmailServiceTest.php`, `tests/Feature/Integration/SellerEmailActionIntegrationTest.php`.
- **Functional Differences**: Central AI performs outbound nurturing to prospects across days (Days 1, 3, 7). Our system only sends immediate internal notifications to assigned salespeople.
- **Tenant Isolation & Security**: Our seller email requires resolving the salesperson inside the authenticated `workspace_id`.
- **Dependencies**: Per-tenant SMTP/IMAP credentials, queue worker.
- **Implementation & Verification Status**: `PARTIAL`.
- **Functional Parity**: `partial`.
- **Recommended Action**: Medium priority. Add per-workspace SMTP mailbox settings to allow automated follow-up emails.

```
================================================================================
MODULE 06: Support Ticket Agent (In-Chat Detection & Helpdesk Desk)
================================================================================
```
- **Central AI Capability & Evidence**: Automated frustration sentiment detection in chat, customer email collection, structured ticket generation with 4 priorities (`low`, `medium`, `high`, `urgent`), 4 statuses (`open`, `in_progress`, `resolved`, `closed`), and an in-app ticketing desk at `/support/tickets/`. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-13, EVID-DOC-06]`.
- **Our SaaS Implementation**: **No dedicated ticketing desk exists**. Our `HybridRouter` extracts ticket keywords and ticket numbers (e.g. `ticket #501`), and human operators can view transcripts and send replies in `/dashboard/conversations/{id}`, but we have no `Ticket` entity, no priority tiers, and no resolution workflow.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/AI/Routing/HybridRouter.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Routing/HybridRouter.php#L367), [`app/Http/Controllers/ConversationController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ConversationController.php).
  - Routes: `GET /dashboard/conversations/{conversation}`, `POST /dashboard/conversations/{conversation}/reply`.
- **Functional Differences**: Central AI provides a formal helpdesk desk with ticket assignment, email sync, and status changes. Our system provides a conversational inbox where human operators can manually take over chat sessions.
- **Tenant Isolation & Security**: Conversation controller is tenant-isolated via `workspace_id`.
- **Dependencies**: Database ticketing tables, mailbox integration.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Medium priority. Create a lightweight `SupportTicket` model and `/dashboard/tickets` view linking escalated conversations.

```
================================================================================
MODULE 07: Document Knowledge (Unstructured Knowledge Ingestion & Analytics)
================================================================================
```
- **Central AI Capability & Evidence**: Ingests PDF, DOCX, TXT files up to 10MB; extracts text chunks (~400 chars, 80 overlap); stores vector embeddings for RAG retrieval. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-04, EVID-DOC-07]`.
- **Our SaaS Implementation**: **Substantially exceeds Central AI baseline**. In addition to ingesting unstructured documents (PDF, DOCX, TXT) with content hashing and lifecycle states (`active`, `syncing`, `sync_failed`, `draft`), our platform ingests structured spreadsheets (`.xlsx`, `.xls`, `.csv`), performs sheet discovery, column type inference, and powers deterministic SQL analytics via `ExcelAnalyticsTool`!
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Models/KnowledgeDocument.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/KnowledgeDocument.php), [`app/Http/Controllers/KnowledgeDocumentController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/KnowledgeDocumentController.php), [`app/AI/Tools/ExcelAnalyticsTool.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/AI/Tools/ExcelAnalyticsTool.php).
  - Routes: `GET /dashboard/documents`, `POST /dashboard/documents`, `GET /dashboard/documents/{document}/preview`.
  - Tests: `tests/Feature/KnowledgeDocumentManagementTest.php`, `tests/Feature/KnowledgeDocumentPreviewTest.php`, `tests/Feature/ChatSimulatorExcelFeatureTest.php`.
- **Functional Differences**: Central AI treats all documents as raw text. Our platform differentiates between general text knowledge (`purpose=knowledge`) and analytical spreadsheets (`purpose=data_analytics`), spinning up virtual SQLite databases for multi-sheet SQL queries.
- **Tenant Isolation & Security**: Strict tenant isolation enforced by `KnowledgeDocumentTenantIsolationTest`.
- **Dependencies**: MySQL, Typesense, `python-ai-service`.
- **Implementation & Verification Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Functional Parity**: `exceeds baseline`.
- **Recommended Action**: Maintain architecture; preserve existing preview and lifecycle capabilities.

```
================================================================================
MODULE 08: Web Training (Automated Website & Sitemap Crawler)
================================================================================
```
- **Central AI Capability & Evidence**: Automated recursive website scraper accepting root domains or sitemaps (1-3 hops depth), re-crawl scheduling, and ~4,000 char per-page truncation. `[GATED_PLAN, DOCUMENTED; EVID-OBS-05, EVID-DOC-08]`.
- **Our SaaS Implementation**: **No automated web scraper or sitemap crawler exists**. All knowledge must be uploaded as files or entered as FAQs.
- **Supporting Files, Routes & Tests**: None.
- **Functional Differences**: Central AI can periodically re-sync a live company website without file uploads. Our system requires manual document uploads.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Web scraper worker, SSRF protection proxy.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Low-to-medium priority. Integrate a scheduled crawler job using Guzzle / CrawlQueue with SSRF protection.

```
================================================================================
MODULE 09: FAQ Database (Structured Q&A Management)
================================================================================
```
- **Central AI Capability & Evidence**: Tabular FAQ management screen with Q&A pairs, title, URL, description, price, CSV bulk import/export, and instant active toggles. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-06, EVID-DOC-07]`.
- **Our SaaS Implementation**: Fully implemented FAQ management subsystem integrated into the Unified Knowledge Base Hub. Supports Question, Answer, Categories, active status toggling, soft deletes, bulk-deletion, and one-click synchronous Typesense reindexing (`POST /dashboard/knowledge-base/reindex`).
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Models/FAQ.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/FAQ.php), [`app/Http/Controllers/FAQController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/FAQController.php), [`app/Services/FAQ/FAQIndexer.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Services/FAQ/FAQIndexer.php).
  - Routes: `GET /dashboard/faqs`, `POST /dashboard/faqs`, `POST /dashboard/faqs/{faq}/toggle-active`, `POST /dashboard/faqs/bulk-delete`.
  - Tests: `tests/Feature/FAQTenantAndManagementTest.php`, `tests/Feature/FAQLifecyclePipelineTest.php`, `tests/Feature/FAQIndexingPipelineTest.php`.
- **Functional Differences**: Functional parity achieved. Our system integrates directly with Typesense hybrid vector search; Central AI links FAQs into its internal vector store.
- **Tenant Isolation & Security**: Tenant-isolated via `workspace_id` in database and Typesense index filters.
- **Dependencies**: MySQL, Typesense, `python-ai-service`.
- **Implementation & Verification Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Functional Parity**: `equivalent`.
- **Recommended Action**: Add CSV import/export template buttons in `resources/views/admin/faqs/`.

```
================================================================================
MODULE 10: Knowledge Sync API (Programmatic Ingestion)
================================================================================
```
- **Central AI Capability & Evidence**: Asynchronous REST endpoint `POST /knowledge/api/v1/sync/` authenticated with secret server key `cax_...` returning HTTP 202 Accepted. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-25, EVID-DOC-17]`.
- **Our SaaS Implementation**: **No external tenant-facing knowledge API exists**. Knowledge ingestion occurs exclusively through authenticated web dashboard routes.
- **Supporting Files, Routes & Tests**: None in `routes/api.php`.
- **Functional Differences**: Central AI allows external CMS/ERP systems to push knowledge articles programmatically. Our platform requires manual UI interaction.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Laravel Sanctum / API Key guard.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Expose an authenticated API endpoint (`POST /api/v1/knowledge/sync`) for programmatic document upload.

```
================================================================================
MODULE 11: Offerings Catalog (Transactional Commerce Inventory)
================================================================================
```
- **Central AI Capability & Evidence**: Dedicated commerce catalog supporting 7 offering kinds (`Product`, `Service`, `Course Intake`, `Appointment`, `Event Date`, `Plan`, `Custom`), SKU, prices in BDT/USD, 5 availability modes, and inventory tracking. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-08, 09, EVID-DOC-09]`.
- **Our SaaS Implementation**: **No transactional catalog editor exists**. We have `analytics_products` which stores historical product data extracted from spreadsheets, but no user-facing UI to create, edit, or manage active sellable offerings.
- **Supporting Files, Routes & Tests**: None for catalog management.
- **Functional Differences**: Central AI allows merchants to define sellable goods with direct purchase rules. Our system only queries past product sales for analytics.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Relational schema, currency configuration.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: **High-priority roadmap item**. Scaffold an `OfferingsController` and `/dashboard/offerings` views to support product and service catalogs.

```
================================================================================
MODULE 12: Find What I Sell (Knowledge AI Offering Scanner)
================================================================================
```
- **Central AI Capability & Evidence**: AI scanner parsing uploaded documents, extracting sellable entities word-for-word, and populating an administrative approval queue at `/sales/suggestions/`. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-10, EVID-DOC-18]`.
- **Our SaaS Implementation**: **No automated offering extraction scanner exists**.
- **Supporting Files, Routes & Tests**: None.
- **Functional Differences**: Central AI automates catalog creation from uploaded brochures. Our system requires users to either upload spreadsheets or manually enter FAQs.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: LLM extraction prompt, offerings review queue.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Defer until Module 11 (Offerings Catalog) is established.

```
================================================================================
MODULE 13: Web Chat Widget (Embeddable Floating Customer Interface)
================================================================================
```
- **Central AI Capability & Evidence**: Lightweight embeddable JavaScript snippet (`ca_...` key), floating launcher bubble, Allowed Domains origin whitelist, theme styling, and real-time SSE token streaming. `[OBSERVED_LIVE, TESTED_LIVE, DOCUMENTED; EVID-OBS-14, EVID-DOC-10]`.
- **Our SaaS Implementation**: Partial. We have an internal developer Chat Simulator (`/dashboard/simulator`) and a basic customer chat view (`resources/views/chat.blade.php`), but **no standalone embeddable client-side JavaScript widget bundle** that external websites can paste before their `</body>` tag.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Http/Controllers/ChatSimulatorController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ChatSimulatorController.php), `resources/views/admin/simulator.blade.php`.
  - Routes: `GET /dashboard/simulator`, `POST /dashboard/simulator/send`.
  - Tests: `tests/Feature/ChatSimulatorTest.php`.
- **Functional Differences**: Central AI can be embedded on any external website with a single `<script>` line protected by domain whitelisting. Our chat interface is accessible only as an authenticated dashboard page.
- **Tenant Isolation & Security**: Simulator uses session auth and workspace selection.
- **Dependencies**: Public embed script bundle, CORS origin validation middleware.
- **Implementation & Verification Status**: `PARTIAL`.
- **Functional Parity**: `partial`.
- **Recommended Action**: **High-priority quick win**. Compile a lightweight vanilla JavaScript widget bundle (`public/widget/embed.js`) that renders a floating bubble and talks to a public API endpoint.

```
================================================================================
MODULE 14: Meta Social Hub (Facebook, Instagram & WhatsApp)
================================================================================
```
- **Central AI Capability & Evidence**: Unified Meta hub connecting Facebook Pages, Instagram Professional accounts, and WhatsApp Cloud API (WABA) numbers via OAuth and system user tokens. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-15, EVID-DOC-11]`.
- **Our SaaS Implementation**: Partial. Our backend architecture contains dedicated channel drivers (`app/Channels/`): Facebook (`FacebookDriver`), Instagram (`InstagramDriver`), WhatsApp (`WhatsAppDriver`), and Telegram (`TelegramDriver`). The Facebook Messenger webhook pipeline is implemented and tested (`ReceiveMessengerWebhookJob`). However, we have **no user-facing dashboard screen to connect OAuth tokens or configure channel cards**.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Http/Controllers/WebhookController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/WebhookController.php), [`app/Jobs/ReceiveMessengerWebhookJob.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Jobs/ReceiveMessengerWebhookJob.php), `app/Channels/Facebook/FacebookDriver.php`.
  - Routes: `GET|POST /webhook`.
  - Tests: `tests/Feature/ReceiveMessengerWebhookJobTest.php`, `tests/Feature/ProductionMessagingPipelineTest.php`.
- **Functional Differences**: Backend logic for Meta channels exists in our codebase, but channel credentials must be configured manually via database/env rather than through a self-serve settings UI.
- **Tenant Isolation & Security**: Webhook resolves tenant via `channel_accounts.workspace_id`.
- **Dependencies**: Meta App Credentials, Meta Graph API v19+.
- **Implementation & Verification Status**: `PARTIAL`.
- **Functional Parity**: `partial`.
- **Recommended Action**: **High-priority roadmap item**. Build `/dashboard/channels` UI allowing tenants to input their Facebook Page Token, WhatsApp Phone Number ID, and WABA credentials.

```
================================================================================
MODULE 15: Commerce API / Universal Commerce Protocol (Headless UCP v1.3)
================================================================================
```
- **Central AI Capability & Evidence**: Inverted headless commerce contract where Central AI invokes the merchant server's endpoints (`/.well-known/ucp`, `/catalog/search`, `/quotes`, `/orders`) with RFC 9421 signatures and idempotency guarantees. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-25, EVID-DOC-12]`.
- **Our SaaS Implementation**: **No headless commerce protocol or external quote calculation engine exists**.
- **Supporting Files, Routes & Tests**: None.
- **Functional Differences**: Central AI can execute live quotes against an enterprise merchant's custom ERP during conversation. Our platform has no commerce API client.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: RFC 9421 HTTP signature validator, idempotency store.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Defer. UCP v1.3 is an enterprise-specific contract; standard e-commerce carts and webhooks offer higher immediate value in Bangladesh.

```
================================================================================
MODULE 16: Live Checkout (Decoupled Cart Redirect & Order Webhook)
================================================================================
```
- **Central AI Capability & Evidence**: Generates parameterized cart redirect URLs (`https://yourshop.com/cart/add?items={items}&ref={ref}`) passing shoppers to merchant checkouts; receives HMAC SHA-256 signed order webhooks (`POST /webhooks/orders/ow_.../`) to update live chat bubbles. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-08, EVID-DOC-12]`.
- **Our SaaS Implementation**: **No decoupled checkout link generator or order webhook endpoint exists**.
- **Supporting Files, Routes & Tests**: None.
- **Functional Differences**: Central AI allows merchants to complete real sales transactions via external website redirection and webhook callbacks. Our system has no transaction handling.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Webhook route, HMAC secret generator.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: **High-priority roadmap item**. Implement cart redirect link formatting and an order webhook receiver (`POST /api/v1/webhooks/orders`).

```
================================================================================
MODULE 17: Lead Integrations (Odoo CRM XML-RPC Synchronization)
================================================================================
```
- **Central AI Capability & Evidence**: Native XML-RPC integration connecting to Odoo ERP, mapping fields, and pushing qualified leads into Odoo `crm.lead` pipeline. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-17, EVID-DOC-13]`.
- **Our SaaS Implementation**: **No external CRM integration exists**. Leads are stored in internal `crm_contacts` tables.
- **Supporting Files, Routes & Tests**: None for Odoo.
- **Functional Differences**: Central AI synchronizes leads to third-party Odoo instances. Our system retains leads internally.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: PHP XML-RPC client.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Defer. Webhook-based lead export (Zapier/Make) is more flexible than hardcoded Odoo XML-RPC.

```
================================================================================
MODULE 18: Partner Lead Sharing (Multi-Partner Distribution Network)
================================================================================
```
- **Central AI Capability & Evidence**: Automated routing of qualified leads to external partner organizations (1 partner on Growth, 5 on Scale). `[DOCUMENTED; EVID-DOC-14]`.
- **Our SaaS Implementation**: **No multi-partner routing network exists**.
- **Supporting Files, Routes & Tests**: None.
- **Functional Differences**: Central AI distributes leads to affiliate partners. Our system scopes leads strictly to the single owning workspace.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Partner distribution router.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Defer. Niche requirement not immediately needed for standard merchants.

```
================================================================================
MODULE 19: Chat Logs & Audits (Transcript Inspector & Telemetry)
================================================================================
```
- **Central AI Capability & Evidence**: Transcript viewer at `/chat/chat-logs/` with channel badges, user ratings, turn history, and citation deep links. `[OBSERVED_LIVE; EVID-OBS-18]`.
- **Our SaaS Implementation**: **Substantially exceeds Central AI baseline**. We provide:
  1. Full conversational transcript management at `/dashboard/conversations` with turn-by-turn messages, manual operator replies, and AI-assisted drafts (`ConversationController`).
  2. Dedicated **Observability & Telemetry Dashboard** (`/dashboard/observability`) tracking prompt tokens, completion tokens, p50/p95 latencies, tool execution times, and routing distribution (`ObservabilityController`).
  3. Multi-format export engine supporting PDF, CSV, and Excel downloads (`ExportController`).
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Http/Controllers/ConversationController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ConversationController.php), [`app/Http/Controllers/ObservabilityController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ObservabilityController.php), [`app/Http/Controllers/ExportController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ExportController.php).
  - Routes: `GET /dashboard/conversations`, `GET /dashboard/observability`, `GET /dashboard/export/conversation/{conversation}/{format}`.
  - Tests: `tests/Feature/ObservabilityTelemetryTest.php`, `tests/Feature/AdminConversationTest.php`, `tests/Feature/Integration/ExportIntegrationTest.php`.
- **Functional Differences**: Our observability telemetry and multi-format exports provide significantly deeper operational intelligence than Central AI's basic chat log list.
- **Tenant Isolation & Security**: Strict tenant isolation enforced by `ConversationControllerTenantTest` and `ConversationExportTenantTest`.
- **Dependencies**: MySQL, Redis / Cache.
- **Implementation & Verification Status**: `INTEGRATION_TESTED` / `TESTED`.
- **Functional Parity**: `exceeds baseline`.
- **Recommended Action**: Maintain architecture; add customer thumbs-up/down feedback widgets in the UI.

```
================================================================================
MODULE 20: Mailboxes Engine (Corporate SMTP/OAuth Email Connectivity)
================================================================================
```
- **Central AI Capability & Evidence**: Corporate mailbox management hub at `/accounts/company/mailboxes/` connecting SMTP/IMAP and OAuth mailboxes with 3-minute inbox sync polling. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-20, EVID-DOC-05]`.
- **Our SaaS Implementation**: **No per-tenant corporate mailbox configuration exists**. Mail is handled globally via standard Laravel `.env` mail configuration (`MAIL_MAILER=smtp`).
- **Supporting Files, Routes & Tests**: None for per-workspace mailbox credentials.
- **Functional Differences**: Central AI allows each tenant to connect their own corporate email server (`support@tenant.com`). Our system sends all emails through the platform's global SMTP server.
- **Tenant Isolation & Security**: N/A (Not implemented per tenant).
- **Dependencies**: Encryption for stored tenant SMTP passwords, dynamic mailer switching.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Medium priority. Add encrypted SMTP settings table (`workspace_mailboxes`) and dynamic mailer configuration.

```
================================================================================
MODULE 21: Team Management (Multi-Seat Access & Scope Privileges)
================================================================================
```
- **Central AI Capability & Evidence**: Team management console at `/accounts/team/` supporting Admin and Operator roles, granular module checkboxes, and plan-based seat limits (1 to 20 seats). `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-21, EVID-PRC-01]`.
- **Our SaaS Implementation**: Partial. Our application has a robust, enterprise-grade role and permission subsystem powered by `spatie/laravel-permission` (`/dashboard/users`, `/dashboard/roles`, `/dashboard/assign-roles`). We support arbitrary custom roles, role creation, and granular permission enums. However, we have **no team invitation link workflow** and **no enforcement of plan seat quotas**.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Http/Controllers/UserController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/UserController.php), [`app/Http/Controllers/RoleController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/RoleController.php), [`app/Http/Controllers/AssignRoleController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/AssignRoleController.php).
  - Routes: `GET /dashboard/users`, `GET /dashboard/roles`, `GET /dashboard/assign-roles`.
  - Tests: `tests/Feature/Auth/RegistrationTest.php`.
- **Functional Differences**: Our RBAC permission system is more granular than Central AI's binary Admin/Operator model, but we lack self-serve teammate email invitations and seat enforcement.
- **Tenant Isolation & Security**: User models are scoped to `workspace_id`.
- **Dependencies**: Spatie permissions, MySQL.
- **Implementation & Verification Status**: `TESTED` / `PARTIAL`.
- **Functional Parity**: `partial`.
- **Recommended Action**: Add teammate invitation by email (`TeamInvite` model) and enforce seat quotas.

```
================================================================================
MODULE 22: API Keys & Security (Credential Segregation & Origin Guard)
================================================================================
```
- **Central AI Capability & Evidence**: Dedicated security console at `/accounts/security/api-keys/` featuring two-key segregation (`ca_...` public widget key vs `cax_...` secret server key), one-click key regeneration, and mandatory Allowed Domains origin whitelisting. `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-22, EVID-DOC-15]`.
- **Our SaaS Implementation**: **No tenant-facing API key management UI or two-tier key model exists**. All web dashboard routes use session authentication (`auth` middleware). Internal service-to-service calls use global environment variables (`config('retrieval.api_key')`).
- **Supporting Files, Routes & Tests**: None for tenant API key generation.
- **Functional Differences**: Central AI allows tenants to generate, rotate, and restrict API keys with domain whitelisting. Our system has no tenant API key infrastructure.
- **Tenant Isolation & Security**: Critical security gap if deploying public chat widgets.
- **Dependencies**: API Key hashing, CORS / Origin validation middleware.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: **High-priority security requirement**. Implement `api_keys` table with public/secret key separation and origin validation middleware before launching a public web widget.

```
================================================================================
MODULE 23: Usage Dashboard (Metered Resource Consumption & Quotas)
================================================================================
```
- **Central AI Capability & Evidence**: Real-time consumption dashboard at `/billing/usage/` with progress bars for monthly messages, knowledge records (300 to 50k), dashboard seats (1 to 20), and agent credits. `[OBSERVED_LIVE; EVID-OBS-23]`.
- **Our SaaS Implementation**: **No metered quota tracking dashboard exists**. Our system tracks operational telemetry in `/dashboard/observability` (latency, tokens), but does not enforce or visualize commercial subscription quotas.
- **Supporting Files, Routes & Tests**: None for quota enforcement.
- **Functional Differences**: Central AI enforces commercial limits and displays usage progress bars. Our platform runs unthrottled without commercial quota checks.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Redis atomic meters, subscription quota tables.
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Medium priority. Add quota tracking columns to `workspaces` table and display a usage widget on the main dashboard.

```
================================================================================
MODULE 24: Invoicing System (Subscription Invoices & Payment Gateway)
================================================================================
```
- **Central AI Capability & Evidence**: Billing invoices table at `/billing/invoices/`, PDF receipt downloads, and subscription checkout routes (`/billing/checkout/starter/`, `/growth/`, `/scale/`) supporting monthly and annual billing (15% discount). `[OBSERVED_LIVE, DOCUMENTED; EVID-OBS-24, EVID-PRC-01]`.
- **Our SaaS Implementation**: **No commercial billing, invoicing, or payment gateway integration exists**.
- **Supporting Files, Routes & Tests**: None.
- **Functional Differences**: Central AI is a commercial SaaS that bills customers and collects payments. Our application currently operates without a subscription billing layer.
- **Tenant Isolation & Security**: N/A (Not implemented).
- **Dependencies**: Payment gateway (SSLCommerz / bKash / Stripe).
- **Implementation & Verification Status**: `MISSING`.
- **Functional Parity**: `missing`.
- **Recommended Action**: Medium priority. Integrate Laravel Cashier or domestic payment gateway (SSLCommerz/bKash) to monetize tenant workspaces.

```
================================================================================
MODULE 25: Company Settings (Tenant Identity & Multi-Currency)
================================================================================
```
- **Central AI Capability & Evidence**: Tenant settings at `/accounts/company/update/` allowing editing of company name, logo upload, website, address, and base operating currency (BDT, USD, EUR, GBP). `[OBSERVED_LIVE; EVID-OBS-19]`.
- **Our SaaS Implementation**: Partial. Our application has a `Workspace` model (`app/Models/Workspace.php`) with `name`, `slug`, and `is_active`, and a user profile editor (`/dashboard/profile`). However, we have **no dedicated Company Settings screen** to upload company logos, set business addresses, or configure default operating currencies.
- **Supporting Files, Routes & Tests**:
  - Files: [`app/Models/Workspace.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/Workspace.php), [`app/Http/Controllers/ProfileController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ProfileController.php).
  - Routes: `GET /dashboard/profile`, `PATCH /dashboard/profile`.
- **Functional Differences**: Central AI allows customizing organizational metadata inherited by the AI Sales Agent and invoices. Our workspace settings are limited to database attributes.
- **Tenant Isolation & Security**: Scoped to workspace.
- **Dependencies**: File storage for logos, currency configuration.
- **Implementation & Verification Status**: `PARTIAL`.
- **Functional Parity**: `partial`.
- **Recommended Action**: Add `/dashboard/settings/company` screen allowing admins to set business name, logo, and currency.
