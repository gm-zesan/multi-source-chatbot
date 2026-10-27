# Central AI: Final Product Capability Baseline

- **Document ID**: `BASE-16`
- **Target Platform**: Central AI (`https://centralai.daffodil-bd.com`)
- **Inspection Date**: October 2026
- **Auditor**: Independent Pair Programming Assistant (Antigravity)
- **Status**: Complete Implementation-Independent Product Capability Baseline
- **Audit Context**: Active Workspace (`zesan`, Company: `ABC`, Plan: `Test Drive`)

---

## 1. Evidence Classification Standard (Normalized)

In accordance with strict empirical auditing principles, every finding, configuration option, and behavioral specification in this baseline is labeled using only the following normalized evidence classifications:

- `OBSERVED_LIVE`: Directly observed in the authenticated web application user interface during the authenticated crawl.
- `TESTED_LIVE`: An active operation or client-side runtime behavior was executed and its outcome was directly verified.
- `DOCUMENTED`: Stated explicitly in official platform documentation (`/docs/`), in-app API documentation (`/chat/api/docs/`), or public pricing DOM attributes, but not independently runtime-tested.
- `GATED_PLAN`: Route or functionality is actively inaccessible under the current Test Drive subscription (redirects to `/accounts/pricing/`).
- `UNTESTED_BEHAVIOR`: Documented capability or external integration interface whose live runtime side-effects were not executed during the audit to preserve production safety.
- `INFERRED`: Reasonable, evidence-backed interpretation where direct operational proof is not fully observable.
- `CONFLICTING_EVIDENCE`: Reliable internal sources or documentation disagree on specifications.
- `UNVERIFIED`: Vendor claim or internal system implementation with no adequate supporting evidence.

---

## 2. Capability Baseline Summary Matrix (25 Modules)

| Module ID | Module Name | Primary Route | Normalized Evidence Status | Primary Plan Boundary |
| :--- | :--- | :--- | :---: | :--- |
| **MOD-01** | **Chat Agent** | `/chat/chatbot/settings/` | `OBSERVED_LIVE`, `TESTED_LIVE`, `DOCUMENTED` | All Plans |
| **MOD-02** | **AI Sales Agent** | `/sales/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | All Plans (Catalog), Starter+ (Checkout) |
| **MOD-03** | **Smart Lead Agent** | `/chat/chatbot/settings/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Growth (3k credits), Scale (9k credits) |
| **MOD-04** | **Auto Lead Capture** | `/lead-capture/dashboard/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Growth (500 leads), Scale (2,000 leads) |
| **MOD-05** | **AI Lead Engagement**| `/lead-engagement/settings/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Growth+ Add-On (1,500 credits/mo) |
| **MOD-06** | **Support Ticket Agent**| `/support/tickets/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Growth+, Scale |
| **MOD-07** | **Document Knowledge** | `/knowledge/upload/` | `OBSERVED_LIVE`, `DOCUMENTED` | All Plans (Quota: 300 to 50k records) |
| **MOD-08** | **Web Training** | `/knowledge/web-training/` | `GATED_PLAN`, `DOCUMENTED` | Starter+ (Redirects on Test Drive) |
| **MOD-09** | **FAQ Database** | `/chat/faqs/` | `OBSERVED_LIVE`, `DOCUMENTED` | All Plans |
| **MOD-10** | **Knowledge Sync API** | `/chat/api/docs/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Growth+, Scale |
| **MOD-11** | **Offerings Catalog** | `/sales/offerings/new/` | `OBSERVED_LIVE`, `DOCUMENTED` | All Plans (Tier offering quotas) |
| **MOD-12** | **Find What I Sell** | `/sales/suggestions/` | `OBSERVED_LIVE`, `DOCUMENTED` | All Plans |
| **MOD-13** | **Web Chat Widget** | `/chat/widget/` | `OBSERVED_LIVE`, `TESTED_LIVE`, `DOCUMENTED` | All Plans |
| **MOD-14** | **Meta Social Hub** | `/integrations/meta/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Starter (FB), Growth+ (IG, WA) |
| **MOD-15** | **Commerce API (UCP)**| `/chat/api/docs/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Scale, Custom |
| **MOD-16** | **Live Checkout** | `/sales/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Starter+ |
| **MOD-17** | **Lead Integrations** | `/leads/integrations/` | `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Growth+ |
| **MOD-18** | **Partner Lead Sharing**| `/docs/partners/` | `DOCUMENTED` | Growth (1 partner), Scale (5 partners) |
| **MOD-19** | **Chat Logs & Audits** | `/chat/chat-logs/` | `OBSERVED_LIVE` | All Plans |
| **MOD-20** | **Mailboxes Engine** | `/accounts/company/mailboxes/`| `OBSERVED_LIVE`, `DOCUMENTED`, `UNTESTED_BEHAVIOR` | Growth+, Scale |
| **MOD-21** | **Team Management** | `/accounts/team/` | `OBSERVED_LIVE`, `DOCUMENTED` | Starter (4), Growth (10), Scale (20 seats) |
| **MOD-22** | **API Keys & Security**| `/accounts/security/api-keys/`| `OBSERVED_LIVE`, `DOCUMENTED` | All Plans |
| **MOD-23** | **Usage Dashboard** | `/billing/usage/` | `OBSERVED_LIVE` | All Plans |
| **MOD-24** | **Invoicing System** | `/billing/invoices/` | `OBSERVED_LIVE` | All Plans |
| **MOD-25** | **Company Settings** | `/accounts/company/update/`| `OBSERVED_LIVE` | All Plans |

---

## 3. Comprehensive Module Baseline Specifications

```
================================================================================
MODULE 01: Chat Agent (Core Conversational AI Runtime)
================================================================================
```
- **Module ID & Name**: `MOD-01` — Chat Agent
- **User Problem Solved**: Businesses receive repetitive customer inquiries around the clock across web and social channels. Traditional rule-based bots fail on varied phrasings, while naive LLMs hallucinate inaccurate company policies and prices.
- **Target User & Primary Use Case**: Support leads and marketing webmasters. Primary use case: Grounded question-answering over uploaded business documents, FAQs, and pricing policies.
- **User-Facing Capabilities**: Real-time conversational dialogue; retrieval citation badges linking responses to source documents; token streaming responses; session reset control.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: End-user textual messages; active knowledge chunks.
  - *Outputs*: Grounded conversational text with source citation markers; logged transcript.
  - *Prerequisites*: At least one active document or FAQ record in tenant knowledge base.
- **Configuration Options & Business Rules**:
  - `prompt`: Multi-line textarea for persona definition and instructions (`/chat/chatbot/settings/`).
  - Retrieval depth: Retrieves the **8 most relevant passages** across knowledge records (`/docs/chat-agent/`).
  - Fallback policy: Strict hallucination guardrail returning standard fallback when confidence is low.
- **Relevant UI Routes**:
  - `/chat/chatbot/` (Interactive Playground) `[OBSERVED_LIVE]`
  - `/chat/chatbot/settings/` (Agent Configuration) `[OBSERVED_LIVE]`
  - `/chat/chatbot/guidelines/` (Prompt Guidelines) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Server Chat API: `POST /chat/api/v1/` (`send_message`, `get_history`).
  - Entities: `ConversationSession`, `ChatMessage`, `KnowledgeChunk`, `RAGCitation`.
- **Plan Restrictions & Usage Limits**: Available on all tiers; metered by monthly message quota (Test Drive: 60; Starter: 3k; Growth: 15k; Scale: 50k).
- **Error Handling & Human Handoff**: When retrieval fails or customer triggers handoff phrases (`handoff_triggers`), bot stops autonomous generation and presents human handoff message.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-02`, `EVID-OBS-03`), `TESTED_LIVE` (Playground streaming), `DOCUMENTED` (`EVID-DOC-01`, `EVID-DOC-07`).
- **Known Limitations & Unverified Behavior**: The specific underlying embedding model (e.g. OpenAI vs local) and vector database engine (pgvector, Qdrant, Typesense) remain undisclosed `[UNVERIFIED]`.

```
================================================================================
MODULE 02: AI Sales Agent (In-Chat Commerce & Assisted Selling)
================================================================================
```
- **Module ID & Name**: `MOD-02` — AI Sales Agent
- **User Problem Solved**: Standard chatbots only answer informational questions and cannot recommend products, assemble orders, or drive transactions inside conversations.
- **Target User & Primary Use Case**: E-commerce merchants, course creators, and service businesses. Primary use case: Assisted selling, product discovery, and cart pass-off.
- **User-Facing Capabilities**: Interactive product recommendation cards; shopping basket assembly; payment method policy guidance; live checkout link generation.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Shopper purchasing intent; offering catalog records.
  - *Outputs*: Visual product cards with prices, images, and buy/checkout action buttons.
  - *Prerequisites*: Active offerings configured in `/sales/offerings/new/`.
- **Configuration Options & Business Rules**:
  - Step 1: Industry profile (9 company types).
  - Step 2: Offering kinds (7 kinds: `Product`, `Service`, `Course Intake`, `Appointment`, `Event Date`, `Plan`, `Custom`).
  - Step 3: Completion modes (`Handled in Central AI`, `Live checkout on your site`, `Created in our system`).
  - Step 4: Rules & Payment (Payment notes for `cash_on_delivery`, `bkash_manual`, `nagad_manual`, `bank_transfer`, `payment_link`; policy URLs for refund, cancellation, delivery).
- **Relevant UI Routes**:
  - `/sales/` (AI Sales Agent Setup) `[OBSERVED_LIVE]`
  - `/sales/offerings/` (Offerings Grid) `[OBSERVED_LIVE]`
  - `/sales/offerings/new/` (Offering Editor) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Live Checkout Webhook (`POST /webhooks/orders/ow_.../`), UCP Commerce API v1.3.
  - Entities: `Offering`, `CartItem`, `OrderIntent`.
- **Plan Restrictions & Usage Limits**: Catalog navigation on all plans; live in-chat ordering and checkout link generation requires Starter+ ($15/mo).
- **Error Handling & Human Handoff**: If customer requests pricing out of scope or invalid order adjustments, agent adheres to `never_promise` directives and invokes `handoff_triggers`.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-08`, `EVID-OBS-09`), `DOCUMENTED` (`EVID-DOC-02`, `EVID-DOC-12`), `UNTESTED_BEHAVIOR` (Live merchant site checkout).
- **Known Limitations & Unverified Behavior**: Does not natively process online card payments or mobile financial services (bKash/Nagad) inside Central AI dialogue bubbles `[DOCUMENTED]`.

```
================================================================================
MODULE 03: Smart Lead Agent (Daily Batch Summarizer & Categorizer)
================================================================================
```
- **Module ID & Name**: `MOD-03` — Smart Lead Agent
- **User Problem Solved**: Sales teams cannot review hundreds of daily chat transcripts manually to extract lead requirements, intent, and qualification categories.
- **Target User & Primary Use Case**: Sales directors and CRM administrators. Primary use case: Automated daily distillation of customer intent and lead classification into custom CRM categories.
- **User-Facing Capabilities**: End customers see **nothing** during chat (zero friction/interruption). Administrators see structured conversation summaries and categorized lead records in `/leads/`.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Completed customer chat transcripts from past 24 hours.
  - *Outputs*: Synthesized customer intent summary; category assignment tag; lead record update.
  - *Prerequisites*: `lead_agent_enabled` active in settings; available Lead Agent credits.
- **Configuration Options & Business Rules**:
  - `lead_agent_enabled`: Master checkbox toggle.
  - `lead_agent_run_time`: Daily scheduled execution time (default `03:00`).
  - `lead_agent_timezone`: Dropdown with 433 global timezones (default `Asia/Dhaka`).
  - `lead_agent_allowed_categories`: Comma-separated text input supporting up to 50 custom categories.
  - Metering: Deducts 1 credit per summarized chat. Re-credits on AI failure and requeues for next cycle.
- **Relevant UI Routes**:
  - `/chat/chatbot/settings/` (Agent Settings & Scheduler) `[OBSERVED_LIVE]`
  - `/leads/` (Lead Table — Plan Gated on Test Drive) `[GATED_PLAN]`
- **Related APIs, Integrations & Data Entities**:
  - Odoo CRM sync, Lead Lookup API (`GET /api/v1/leads/lookup/`).
  - Entities: `LeadRecord`, `LeadSummary`, `CategoryTag`.
- **Plan Restrictions & Usage Limits**: Gated to Growth (3,000 credits/mo) and Scale (9,000 credits/mo). Zero credits on Test Drive / Starter.
- **Error Handling & Human Handoff**: If summarization fails, credit is refunded and conversation is queued for the next daily batch run.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-03`), `DOCUMENTED` (`EVID-DOC-03`), `UNTESTED_BEHAVIOR` (Live 03:00 cron execution).
- **Known Limitations & Unverified Behavior**: Overturns the prior audit assumption of real-time in-chat BANT interviews. Operates strictly as a daily batch job `[DOCUMENTED]`.

```
================================================================================
MODULE 04: Auto Lead Capture (Anonymous Visitor Tracking & Scoring)
================================================================================
```
- **Module ID & Name**: `MOD-04` — Auto Lead Capture
- **User Problem Solved**: Over 95% of website visitors browse without filling out forms, leaving sales teams with no visibility into high-intent prospects.
- **Target User & Primary Use Case**: Demand generation teams and growth marketers. Primary use case: Tracking web visitor dwell time, scroll depth, and interest scoring before identity capture.
- **User-Facing Capabilities**: Seamless tracking with zero page slowdown; retroactively attaches browsing journey when visitor identifies themselves in chat or form.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Pageview events, scroll metrics, time-on-page, identified email/phone.
  - *Outputs*: Calculated "Interest Score"; visitor journey timeline; converted lead profile.
  - *Prerequisites*: Client tracker snippet installed with public `ca_...` key.
- **Configuration Options & Business Rules**:
  - Client Tracker API endpoints: `GET /lead-capture/vid/`, `POST /lead-capture/events/`, `POST /lead-capture/consent/`, `POST /lead-capture/identify/`.
  - Whitelist enforcement: Tracker events from unapproved domains are rejected with `ORIGIN_NOT_ALLOWED`.
- **Relevant UI Routes**:
  - `/lead-capture/dashboard/` (Auto Lead Capture Dashboard) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Client-Side Tracker API (`/chat/api/docs/`).
  - Entities: `VisitorSession`, `PageEvent`, `VisitorProfile`, `InterestScore`.
- **Plan Restrictions & Usage Limits**: Gated to Growth (500 captured leads/mo) and Scale (2,000 captured leads/mo). Inaccessible on Starter.
- **Error Handling & Human Handoff**: Rejects unwhitelisted domains (`HTTP 403`); unconsented visitors tracked anonymously without cookie binding.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-11`), `DOCUMENTED` (`EVID-DOC-04`), `UNTESTED_BEHAVIOR` (Live script event ingestion).
- **Known Limitations & Unverified Behavior**: Live identity binding requires active web visitor interaction and consent compliance `[DOCUMENTED]`.

```
================================================================================
MODULE 05: AI Lead Engagement (Mailbox Email Follow-Up Add-On)
================================================================================
```
- **Module ID & Name**: `MOD-05` — AI Lead Engagement
- **User Problem Solved**: Inquiries go cold because sales reps fail to follow up promptly or consistently via email.
- **Target User & Primary Use Case**: B2B sales teams and lead development reps. Primary use case: Automated contextual email follow-up sequences sent from corporate mailboxes.
- **User-Facing Capabilities**: Leads receive natural, personalized email follow-ups referencing their previous chat or inquiry.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Captured lead email; inquiry context; connected company mailbox.
  - *Outputs*: Multi-stage outbound email sequence; staged draft review queue; unsubscribe link.
  - *Prerequisites*: Connected corporate mailbox on `/accounts/company/mailboxes/`; active Lead Engagement credits.
- **Configuration Options & Business Rules**:
  - Operating modes: `off`, `draft_only` (human review required), `autonomous` (self-sending).
  - Agent identity: Sender name, job title, company background, business objective.
  - Sending rules: Cadence intervals (Days 1, 3, 7), sending days, daily caps, global suppression list.
- **Relevant UI Routes**:
  - `/lead-engagement/settings/` (Lead Engagement Configuration) `[OBSERVED_LIVE]`
  - `/accounts/company/mailboxes/` (Mailbox Connection Hub) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - SMTP / IMAP or OAuth Mailbox Providers.
  - Entities: `EmailSequence`, `EmailMessage`, `MailboxCredential`, `SuppressionEntry`.
- **Plan Restrictions & Usage Limits**: Paid add-on available on Growth and Scale tiers (includes 1,500 credits/mo).
- **Error Handling & Human Handoff**: Any prospect email reply indicating objection or meeting request routes the thread to human reps and pauses automated sequences.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-12`, `EVID-OBS-20`), `DOCUMENTED` (`EVID-DOC-05`), `UNTESTED_BEHAVIOR` (Outbound email transmission).
- **Known Limitations & Unverified Behavior**: Strictly an email-only system. Does NOT execute outbound WhatsApp, Instagram, or SMS campaigns `[DOCUMENTED]`.

```
================================================================================
MODULE 06: Support Ticket Agent (In-Chat Detection & Helpdesk Desk)
================================================================================
```
- **Module ID & Name**: `MOD-06` — Support Ticket Agent
- **User Problem Solved**: Technical bugs, complaints, and billing issues discussed in chat get lost in logs without accountability or ticketing.
- **Target User & Primary Use Case**: Customer support teams and helpdesk managers. Primary use case: In-chat sentiment frustration detection, ticket creation, and email follow-up.
- **User-Facing Capabilities**: Customer in distress is prompted for their email; receives ticket reference number; updates delivered via email.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Chat message sentiment; customer email address; issue description.
  - *Outputs*: Structured helpdesk ticket; email notification to support staff.
  - *Prerequisites*: `support_ticket_enabled` active in `/chat/chatbot/settings/`.
- **Configuration Options & Business Rules**:
  - `support_ticket_enabled`: Master switch toggle.
  - `support_ticket_auto_detect`: NLP detection trigger for frustration/complaint sentiment.
  - `support_ticket_instructions`: Custom directives guiding ticket creation criteria.
  - `support_ticket_priority`: Default priority (`low`, `medium`, `high`, `urgent`).
  - Status lifecycle: `open` -> `in_progress` -> `resolved` -> `closed`.
- **Relevant UI Routes**:
  - `/support/tickets/` (Support Tickets Desk) `[OBSERVED_LIVE]`
  - `/support/tickets/create/` (Manual Ticket Creation) `[OBSERVED_LIVE]`
  - `/chat/chatbot/settings/` (Agent Settings) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Server Tickets API: `GET /api/v1/support/tickets/`, `POST /api/v1/support/tickets/`.
  - Entities: `SupportTicket`, `TicketComment`, `TicketPriority`, `TicketStatus`.
- **Plan Restrictions & Usage Limits**: Included on Growth and Scale plans.
- **Error Handling & Human Handoff**: Auto-pauses bot chat replies upon escalation so human operators can claim the dialogue.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-03`, `EVID-OBS-13`), `DOCUMENTED` (`EVID-DOC-06`), `UNTESTED_BEHAVIOR` (Live outbound notification email).
- **Known Limitations & Unverified Behavior**: Customer email replies are ingested from the mailbox poll (checked every 3 minutes) `[DOCUMENTED]`.

```
================================================================================
MODULE 07: Document Knowledge (Unstructured Knowledge Ingestion)
================================================================================
```
- **Module ID & Name**: `MOD-07` — Document Knowledge
- **User Problem Solved**: Businesses possess manuals, price sheets, and policy documents that need to be queried by AI without manual Q&A re-formatting.
- **Target User & Primary Use Case**: Knowledge base managers and administrators. Primary use case: Drag-and-drop document upload for automated RAG indexing.
- **User-Facing Capabilities**: Document upload interface; chunk extraction status viewer; active/inactive knowledge status toggle.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: `.pdf`, `.docx`, `.txt` files up to 10MB each.
  - *Outputs*: Extracted text chunks (~400 characters, 80 character overlap); vector embeddings.
  - *Prerequisites*: Tenant storage quota available.
- **Configuration Options & Business Rules**:
  - File size limit: 10MB per file `[OBSERVED_LIVE]`.
  - Quotas: Test Drive (300 records), Starter (3,000 records), Growth (10,000 records), Scale (50,000 records) `[DOCUMENTED]`.
- **Relevant UI Routes**:
  - `/knowledge/` (Knowledge Base Listing) `[OBSERVED_LIVE]`
  - `/knowledge/upload/` (Document Uploader) `[OBSERVED_LIVE]`
  - `/knowledge/export/` (Knowledge Export) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Server Knowledge Sync API (`POST /knowledge/api/v1/sync/`).
  - Entities: `KnowledgeDocument`, `KnowledgeChunk`, `ExtractionTask`.
- **Plan Restrictions & Usage Limits**: Storage capped by tier record quotas; excess uploads trigger `QUOTA_EXCEEDED`.
- **Error Handling & Human Handoff**: Corrupt or password-protected files display explicit `PARSING_ERROR` in the document grid.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-04`), `DOCUMENTED` (`EVID-DOC-07`, `EVID-PRC-01`).
- **Known Limitations & Unverified Behavior**: Optical Character Recognition (OCR) for scanned image-only PDFs is not documented or verified `[UNVERIFIED]`.

```
================================================================================
MODULE 08: Web Training (Automated Website & Sitemap Crawler)
================================================================================
```
- **Module ID & Name**: `MOD-08` — Web Training
- **User Problem Solved**: Manual document uploads become stale as company websites and help centers update continuously.
- **Target User & Primary Use Case**: Digital marketers and e-commerce webmasters. Primary use case: Automated recursive scraping of public website pages into RAG vector memory.
- **User-Facing Capabilities**: Automated crawl scheduling; sitemap XML parsing; URL exclusion filters.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Root domain URL or sitemap XML link; link depth setting (1 to 3 hops).
  - *Outputs*: HTML text extraction, chunking, and vector embedding.
  - *Prerequisites*: Starter plan or higher ($15/mo).
- **Configuration Options & Business Rules**:
  - Crawl recursion: 1 to 3 hops `[DOCUMENTED]`.
  - Re-sync intervals: Daily, weekly, or manual re-crawl `[DOCUMENTED]`.
  - Page truncation: Retains and embeds only the first **~4,000 characters** per crawled page `[DOCUMENTED]`.
- **Relevant UI Routes**:
  - `/knowledge/web-training/` (Redirects to `/accounts/pricing/` on Test Drive) `[GATED_PLAN]`
- **Related APIs, Integrations & Data Entities**:
  - Entities: `CrawlJob`, `CrawledPage`, `CrawlSchedule`.
- **Plan Restrictions & Usage Limits**: Gated to Starter ($15/mo), Growth ($50/mo), and Scale ($150/mo). Inaccessible on Test Drive.
- **Error Handling & Human Handoff**: Non-200 HTTP responses, robots.txt exclusions, and pages exceeding crawl limits are flagged in crawler log.
- **Evidence Status & References**: `GATED_PLAN` (`EVID-OBS-05`), `DOCUMENTED` (`EVID-DOC-08`, `CLM-04`).
- **Known Limitations & Unverified Behavior**: Truncates pages after ~4,000 characters. Single-page applications requiring heavy JavaScript execution may not be fully parsed `[PARTIALLY_VERIFIED]`.

```
================================================================================
MODULE 09: FAQ Database (Structured Q&A Management)
================================================================================
```
- **Module ID & Name**: `MOD-09` — FAQ Database
- **User Problem Solved**: Certain business facts (exact prices, return periods, hours) require deterministic answers rather than variable RAG summarization.
- **Target User & Primary Use Case**: Support leads and store managers. Primary use case: Managing canonical question-and-answer pairs with associated metadata.
- **User-Facing Capabilities**: Tabular FAQ editor; bulk CSV template upload; full CSV export; instant search.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Question, Answer, Title, URL, Description, Price.
  - *Outputs*: Indexed structured FAQ records linked to Chat Agent RAG index.
  - *Prerequisites*: None.
- **Configuration Options & Business Rules**:
  - Fields: `question` (required), `answer` (required), `title`, `url`, `description`, `price`.
  - Active status toggle per item.
- **Relevant UI Routes**:
  - `/chat/faqs/` (Knowledge Management Table) `[OBSERVED_LIVE]`
  - `/chat/faqs/add/` (Add FAQ Form) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Entities: `FAQRecord`, `CSVImportBatch`.
- **Plan Restrictions & Usage Limits**: Available on all plans; counts against total knowledge record quotas.
- **Error Handling & Human Handoff**: Malformed CSV columns return upload error indicating invalid rows.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-06`), `DOCUMENTED` (`EVID-DOC-07`).
- **Known Limitations & Unverified Behavior**: Does not support relational multi-column database joins or mathematical SQL queries `[DOCUMENTED]`.

```
================================================================================
MODULE 10: Knowledge Sync API (Programmatic Knowledge Ingestion)
================================================================================
```
- **Module ID & Name**: `MOD-10` — Knowledge Sync API
- **User Problem Solved**: Enterprise systems (CMS, PIM, ERP) need to push updated articles and product specs into Central AI programmatically.
- **Target User & Primary Use Case**: Software engineers and DevOps integrators. Primary use case: Asynchronous server-to-server knowledge synchronization.
- **User-Facing Capabilities**: Programmatic HTTP ingestion returning job tokens for non-blocking status tracking.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: `POST /knowledge/api/v1/sync/` with JSON documents, offerings, or URLs; `X-API-Key: cax_...`.
  - *Outputs*: HTTP 202 Accepted with sync transaction token.
  - *Prerequisites*: Valid secret server API key (`cax_...`); Growth or Scale tier.
- **Configuration Options & Business Rules**:
  - Idempotent payload handling; rejects public widget keys (`ca_...`) with HTTP 401.
- **Relevant UI Routes**:
  - `/chat/api/docs/` (In-App API Specification) `[OBSERVED_LIVE]`
  - `/knowledge/api/docs/` (Redirects to pricing on Test Drive) `[GATED_PLAN]`
- **Related APIs, Integrations & Data Entities**:
  - Server Knowledge Sync API (`/chat/api/docs/` Table 3).
  - Entities: `SyncJob`, `ExternalPayload`.
- **Plan Restrictions & Usage Limits**: Gated to Growth+ and Scale plans.
- **Error Handling & Human Handoff**: Invalid API keys return `HTTP 401 AUTH_REQUIRED`; malformed JSON returns `HTTP 400`.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-25`), `DOCUMENTED` (`EVID-DOC-17`), `UNTESTED_BEHAVIOR` (Live server-to-server call).
- **Known Limitations & Unverified Behavior**: Asynchronous batch queue depth and rate limits are not publicly documented `[UNVERIFIED]`.

```
================================================================================
MODULE 11: Offerings Catalog (Transactional Commerce Inventory)
================================================================================
```
- **Module ID & Name**: `MOD-11` — Offerings Catalog
- **User Problem Solved**: Conversational chatbots lack structured knowledge of product SKUs, prices, intake schedules, and stock availability.
- **Target User & Primary Use Case**: E-commerce catalog managers and course administrators. Primary use case: Defining sellable commercial entities for in-chat presentation.
- **User-Facing Capabilities**: Structured catalog grid; offering creation form; image file upload; inventory tracking toggles.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Product name, SKU, price, currency, description, image, kind, availability mode.
  - *Outputs*: Sellable `Offering` entity queryable by AI Sales Agent.
  - *Prerequisites*: Company default currency set in `/accounts/company/update/`.
- **Configuration Options & Business Rules**:
  - 7 offering kinds: `Product`, `Service`, `Course Intake`, `Appointment`, `Event Date`, `Plan`, `Custom`.
  - 5 availability modes: In stock, out of stock, pre-order, quote required, seasonal.
  - 5 fulfillment options: Physical shipping, digital download, on-premise service, online session, custom.
  - Form fields: `name`, `sku`, `external_ref`, `price`, `currency`, `stock_quantity`, `track_inventory`, `is_active`.
- **Relevant UI Routes**:
  - `/sales/offerings/` (Offerings Grid) `[OBSERVED_LIVE]`
  - `/sales/offerings/new/` (Offering Editor) `[OBSERVED_LIVE]`
  - `/sales/offerings/import/` (CSV Import) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - UCP Commerce API v1.3 (`/catalog/search`, `/availability/check`).
  - Entities: `Offering`, `OfferingOption`, `InventoryItem`.
- **Plan Restrictions & Usage Limits**: Test Drive (5 offerings), Starter (50 offerings), Growth (500 offerings), Scale (unlimited).
- **Error Handling & Human Handoff**: Inactive offerings (`is_active=False`) are suppressed from chat recommendations.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-08`, `EVID-OBS-09`), `DOCUMENTED` (`EVID-DOC-09`).
- **Known Limitations & Unverified Behavior**: Does not natively decrement external warehouse inventory without external Commerce API hooks `[DOCUMENTED]`.

```
================================================================================
MODULE 12: Find What I Sell (Knowledge Base AI Offering Scanner)
================================================================================
```
- **Module ID & Name**: `MOD-12` — Find What I Sell
- **User Problem Solved**: Manually typing hundreds of products, services, and price points into a database is tedious and error-prone.
- **Target User & Primary Use Case**: Business owners onboarding existing product brochures and price sheets. Primary use case: AI extraction of sellable items from uploaded PDF/DOCX files.
- **User-Facing Capabilities**: "Scan Knowledge for Sellable Items" action button; suggestions review queue; one-click approval into active catalog.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Text chunks from previously uploaded knowledge documents.
  - *Outputs*: Extracted offering suggestions with titles, descriptions, and detected prices.
  - *Prerequisites*: Uploaded documents present in `/knowledge/`.
- **Configuration Options & Business Rules**:
  - Extraction rule: Extracts offerings verbatim from source documents without hallucinating details `[DOCUMENTED]`.
  - Review queue statuses: Suggested / Approved / Dismissed.
- **Relevant UI Routes**:
  - `/sales/suggestions/` (Needs Your Review Queue) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Entities: `SuggestedOffering`, `KnowledgeDocument`.
- **Plan Restrictions & Usage Limits**: Available across all plans.
- **Error Handling & Human Handoff**: Admins can edit suggested prices or categories before approval.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-10`), `DOCUMENTED` (`EVID-DOC-18`).
- **Known Limitations & Unverified Behavior**: Scanning complex multi-column PDF tables with obscure formatting may miss items `[INFERRED]`.

```
================================================================================
MODULE 13: Web Chat Widget (Embeddable Floating Customer Interface)
================================================================================
```
- **Module ID & Name**: `MOD-13` — Web Chat Widget
- **User Problem Solved**: Deploying conversational AI requires complex frontend code and authentication integration.
- **Target User & Primary Use Case**: Webmasters and frontend developers. Primary use case: Embedding a branded chat bubble on any public website via a single `<script>` tag.
- **User-Facing Capabilities**: Floating toggle bubble; conversational dialog box; interactive product card rendering; real-time typing indicators.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: End-user text; widget key (`ca_...`).
  - *Outputs*: Floating chat interface; streaming responses; session continuity across navigation.
  - *Prerequisites*: Domain registered in Allowed Domains whitelist.
- **Configuration Options & Business Rules**:
  - Styling: Hex color picker, avatar image upload, header title/subtitle, position (bottom-right / bottom-left).
  - Security: Allowed Domains textarea whitelist. Empty list allows nothing `[DOCUMENTED]`.
  - Public widget key format: `ca_pub_...` or `ca_...`.
- **Relevant UI Routes**:
  - `/chat/widget/` (Widget Integration Screen) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Client Widget Gateway (`/api/v1/chat/message`).
  - Entities: `WidgetConfig`, `AllowedDomain`, `VisitorSession`.
- **Plan Restrictions & Usage Limits**: Available on all plans; white-label branding removal is restricted to Scale ($150/mo).
- **Error Handling & Human Handoff**: Embedding on unwhitelisted origins blocks widget execution via CORS / origin validation `[DOCUMENTED]`.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-14`), `TESTED_LIVE` (Widget script handshake), `DOCUMENTED` (`EVID-DOC-10`).
- **Known Limitations & Unverified Behavior**: SSE token streaming requires an unblocked persistent HTTP connection `[DOCUMENTED]`.

```
================================================================================
MODULE 14: Meta Social Hub (Omnichannel Facebook, Instagram & WhatsApp)
================================================================================
```
- **Module ID & Name**: `MOD-14` — Meta Social Hub
- **User Problem Solved**: Customers interact on Facebook, Instagram, and WhatsApp, forcing businesses to manage disjointed communication silos.
- **Target User & Primary Use Case**: Social media managers and customer care leads. Primary use case: Centralizing Meta channel messaging into the central AI agent runtime.
- **User-Facing Capabilities**: Customers message company Facebook Pages, Instagram DMs, or WhatsApp numbers and receive immediate AI responses from the central knowledge base.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Meta webhooks (`messages`, `messaging_postbacks`).
  - *Outputs*: Automated replies dispatched back through Meta Graph API.
  - *Prerequisites*: Verified Facebook Page, Instagram Professional account, or Meta WhatsApp Business Account (WABA).
- **Configuration Options & Business Rules**:
  - Facebook Messenger: OAuth connect button and Page selector.
  - Instagram Direct: Linked Facebook Page OAuth connection.
  - WhatsApp Cloud API: WABA ID, Phone Number ID, Permanent System User Access Token.
  - Meta 24-hour customer service messaging window strictly enforced `[DOCUMENTED]`.
- **Relevant UI Routes**:
  - `/integrations/meta/` (Social Channels Hub) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Meta Graph API v19+, Webhook receiver (`POST /webhook/meta/`).
  - Entities: `SocialConnection`, `ChannelMessage`, `MetaCredential`.
- **Plan Restrictions & Usage Limits**: Facebook Messenger available on Starter ($15/mo); Instagram Direct and WhatsApp Cloud API require Growth ($50/mo) or Scale ($150/mo).
- **Error Handling & Human Handoff**: Expired Meta tokens display connection error card prompting re-authentication.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-15`), `DOCUMENTED` (`EVID-DOC-11`), `UNTESTED_BEHAVIOR` (Live Meta webhook handshake).
- **Known Limitations & Unverified Behavior**: Does not include native support for Telegram, Viber, or TikTok messaging `[DOCUMENTED]`.

```
================================================================================
MODULE 15: Commerce API / Universal Commerce Protocol (Headless UCP v1.3)
================================================================================
```
- **Module ID & Name**: `MOD-15` — Commerce API (UCP v1.3)
- **User Problem Solved**: Enterprises with proprietary ERPs or custom e-commerce engines cannot migrate their catalogs to SaaS databases.
- **Target User & Primary Use Case**: Enterprise backend developers and systems architects. Primary use case: Central AI queries the merchant's external server in real-time during conversations for live quotes and orders.
- **User-Facing Capabilities**: Real-time accurate quotes reflecting custom discounts, taxes, and shipping rates calculated by the merchant's own backend.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Customer purchase intent; merchant UCP profile URL (`profile_url`) and signing key.
  - *Outputs*: External order creation with guaranteed idempotency.
  - *Prerequisites*: Merchant server implements UCP v1.3 operations (`quote.calculate`, `commit.create`).
- **Configuration Options & Business Rules**:
  - Inverted client model: Merchant hosts endpoints (`/.well-known/ucp`, `/catalog/search`, `/quotes`, `/orders`); Central AI calls them.
  - Request signing: RFC 9421 HTTP Message Signatures with SHA-256 digest `[OBSERVED_LIVE]`.
  - Idempotency handling: Enforces `Idempotency-Key` header; rejects conflicts with HTTP 409 `idempotency_conflict`.
- **Relevant UI Routes**:
  - `/chat/api/docs/` (In-App API Reference, Tables 14-19) `[OBSERVED_LIVE]`
  - `/sales/` (Connection to your system Advanced) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - UCP Commerce API v1.3 specification.
  - Entities: `UCPProfile`, `OrderQuote`, `IdempotencyRecord`.
- **Plan Restrictions & Usage Limits**: Gated to Scale ($150/mo) and Enterprise plans.
- **Error Handling & Human Handoff**: Transient merchant backend failures trigger retries with exponential backoff; persistent 5xx aborts order with user-friendly error.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-25`), `DOCUMENTED` (`EVID-DOC-12`), `UNTESTED_BEHAVIOR` (Live UCP transaction).
- **Known Limitations & Unverified Behavior**: Requires merchant technical development to implement the specification endpoints on their own servers `[DOCUMENTED]`.

```
================================================================================
MODULE 16: Live Checkout (Decoupled Cart Redirect & Order Webhook)
================================================================================
```
- **Module ID & Name**: `MOD-16` — Live Checkout
- **User Problem Solved**: Merchants want AI-assisted sales conversations but cannot abandon their existing website checkout, payment gateways, or ERP order processing.
- **Target User & Primary Use Case**: Shopify, WooCommerce, and custom web store merchants. Primary use case: Generating parameterized checkout links and updating chat upon payment receipt.
- **User-Facing Capabilities**: Shopper clicks "Checkout on yoursite.com", completes payment on merchant store, and live chat updates to "Payment Received #ORD-XXXX".
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Assembled shopping basket items; checkout link template.
  - *Outputs*: Parameterized URL (`https://yourshop.com/cart/add?items={items}&ref={ref}`); incoming webhook update.
  - *Prerequisites*: Merchant site supports cart addition via URL parameters; webhook configured.
- **Configuration Options & Business Rules**:
  - URL template tokens: `{items}` (e.g. `SKU-1:1,SKU-2:2`), `{ref}` (session token `cai_...`).
  - Webhook endpoint: `POST /webhooks/orders/ow_.../`.
  - Authentication: `Central-AI-Signature: t=..., v1=...` (HMAC SHA-256).
- **Relevant UI Routes**:
  - `/sales/` (Step 3: How customers complete) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Order Webhook API (`/docs/live-checkout/`).
  - Entities: `OrderWebhookSecret`, `OrderEvent`, `CartSession`.
- **Plan Restrictions & Usage Limits**: Available on Starter ($15/mo), Growth ($50/mo), and Scale ($150/mo).
- **Error Handling & Human Handoff**: Invalid webhook signatures are rejected with HTTP 401; mismatched `{ref}` tokens log unassigned orders.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-08`), `DOCUMENTED` (`EVID-DOC-12`), `UNTESTED_BEHAVIOR` (Live webhook postback).
- **Known Limitations & Unverified Behavior**: Payments are not processed directly within Central AI chat bubbles `[DOCUMENTED]`.

```
================================================================================
MODULE 17: Lead Integrations (Odoo CRM XML-RPC Synchronization)
================================================================================
```
- **Module ID & Name**: `MOD-17` — Lead Integrations
- **User Problem Solved**: Leads captured by AI chatbots remain trapped in siloed chatbot dashboards rather than entering enterprise CRM pipelines.
- **Target User & Primary Use Case**: CRM administrators using Odoo ERP. Primary use case: Automated pushing of qualified leads into Odoo `crm.lead` pipeline.
- **User-Facing Capabilities**: Automatic creation of Odoo CRM leads with contact details, opportunity stage, and conversation summaries.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Captured lead attributes; Odoo connection parameters.
  - *Outputs*: XML-RPC API call creating record in Odoo `crm.lead` model.
  - *Prerequisites*: Active Odoo Community or Enterprise instance; Growth plan or higher.
- **Configuration Options & Business Rules**:
  - Connection parameters: `odoo_host`, `odoo_db`, `odoo_username`, `odoo_api_key`.
  - Field mapping: Direct mapping to Odoo lead name, contact email, phone, stage ID, and notes.
- **Relevant UI Routes**:
  - `/leads/integrations/` (Lead Integrations Screen) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Odoo XML-RPC external API.
  - Entities: `CRMIntegrationConfig`, `OdooLeadMapping`.
- **Plan Restrictions & Usage Limits**: Gated to Growth ($50/mo) and Scale ($150/mo) tiers.
- **Error Handling & Human Handoff**: Network or credential failures log an error entry in integration history for retry.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-17`), `DOCUMENTED` (`EVID-DOC-13`), `UNTESTED_BEHAVIOR` (Live XML-RPC transaction).
- **Known Limitations & Unverified Behavior**: Native CRM sync is currently limited to Odoo; HubSpot and Salesforce require custom webhooks or APIs `[DOCUMENTED]`.

```
================================================================================
MODULE 18: Partner Lead Sharing (Multi-Partner Distribution Network)
================================================================================
```
- **Module ID & Name**: `MOD-18` — Partner Lead Sharing
- **User Problem Solved**: Franchises, brokerages, and multi-vendor networks need to route leads to independent affiliate partners based on category or location.
- **Target User & Primary Use Case**: Franchise networks and distributor platforms. Primary use case: Automated forwarding of categorized leads to external partner CRM accounts.
- **User-Facing Capabilities**: Partner receives email alerts or API pushes with customer contact details and conversation summaries.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Categorized lead record; partner routing rules.
  - *Outputs*: Lead dispatch to designated partner endpoint.
  - *Prerequisites*: Growth (1 partner) or Scale (5 partners) subscription.
- **Configuration Options & Business Rules**:
  - Quota: 1 external partner on Growth tier; up to 5 partners on Scale tier `[DOCUMENTED]`.
- **Relevant UI Routes**:
  - Inaccessible under Test Drive plan `[DOCUMENTED]`.
- **Related APIs, Integrations & Data Entities**:
  - Entities: `PartnerProfile`, `LeadRoutingRule`.
- **Plan Restrictions & Usage Limits**: Growth (1 partner), Scale (5 partners). Inaccessible on Starter.
- **Error Handling & Human Handoff**: Unassigned leads remain in tenant `/leads/` default inbox.
- **Evidence Status & References**: `DOCUMENTED` (`EVID-DOC-14`, `EVID-PRC-01`).
- **Known Limitations & Unverified Behavior**: Partner configuration screen was not directly observed in the Test Drive workspace `[DOCUMENTED]`.

```
================================================================================
MODULE 19: Chat Logs & Audits (Transcript Inspector & Session Quality)
================================================================================
```
- **Module ID & Name**: `MOD-19` — Chat Logs & Audits
- **User Problem Solved**: AI quality assurance requires visibility into real conversations, customer ratings, channel sources, and retrieved RAG citations.
- **Target User & Primary Use Case**: Support quality analysts and bot prompt engineers. Primary use case: Inspecting end-to-end conversation transcripts and analyzing response accuracy.
- **User-Facing Capabilities**: Searchable session table; full turn-by-turn dialogue drawer; channel origin badges; thumbs up/down customer feedback filters; deep links to citations.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Historical chat sessions; customer feedback ratings.
  - *Outputs*: Filtered transcript viewer; session export data.
  - *Prerequisites*: None.
- **Configuration Options & Business Rules**:
  - Filters: Channel (Web, Facebook, Instagram, WhatsApp), Rating (positive, negative, unrated), Date range.
- **Relevant UI Routes**:
  - `/chat/chat-logs/` (Chat Logs Screen) `[OBSERVED_LIVE]`
  - `/chat/chat-logs/export/` (Export Route) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Entities: `ChatSession`, `ChatMessage`, `CustomerFeedback`.
- **Plan Restrictions & Usage Limits**: Available across all plans.
- **Error Handling & Human Handoff**: Flagged sessions highlight customer dissatisfaction triggers.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-18`).
- **Known Limitations & Unverified Behavior**: Long-term transcript retention policies beyond 12 months are governed by plan storage limits `[DOCUMENTED]`.

```
================================================================================
MODULE 20: Mailboxes Engine (Corporate SMTP/OAuth Email Connectivity)
================================================================================
```
- **Module ID & Name**: `MOD-20` — Mailboxes Engine
- **User Problem Solved**: Sending automated follow-up emails or support replies from generic noreply addresses results in spam flags and poor engagement.
- **Target User & Primary Use Case**: IT administrators and support operations. Primary use case: Connecting branded corporate email accounts (SMTP/IMAP or OAuth) for automated agent communication.
- **User-Facing Capabilities**: Outbound email sending from official corporate addresses (`support@company.com`); automated ingestion of customer email replies.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: SMTP host, port, username, password, IMAP credentials or OAuth token.
  - *Outputs*: Verified corporate sending mailbox; inbound email sync worker (polls every 3 minutes).
  - *Prerequisites*: Valid corporate email server and DNS MX records.
- **Configuration Options & Business Rules**:
  - Protocol options: SMTP/IMAP or OAuth business mailboxes `[OBSERVED_LIVE]`.
  - Assignment: Dedicated binding to `Lead Engagement` or `Support Tickets` `[OBSERVED_LIVE]`.
- **Relevant UI Routes**:
  - `/accounts/company/mailboxes/` (Mailboxes Management Screen) `[OBSERVED_LIVE]` (Corrects legacy 404 path `/accounts/mailboxes/`).
- **Related APIs, Integrations & Data Entities**:
  - SMTP/IMAP Protocols, OAuth2 Providers.
  - Entities: `CompanyMailbox`, `MailboxCredential`, `InboundEmailMessage`.
- **Plan Restrictions & Usage Limits**: Requires Growth or Scale subscription.
- **Error Handling & Human Handoff**: Failed SMTP authentication blocks mailbox activation until re-tested.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-20`), `DOCUMENTED` (`EVID-DOC-05`, `EVID-DOC-06`), `UNTESTED_BEHAVIOR` (Live SMTP handshake).
- **Known Limitations & Unverified Behavior**: Inbound email synchronization operates on an asynchronous 3-minute polling interval `[DOCUMENTED]`.

```
================================================================================
MODULE 21: Team Management (Multi-Seat Access & Scope Privileges)
================================================================================
```
- **Module ID & Name**: `MOD-21` — Team Management
- **User Problem Solved**: Growing organizations require multiple staff members to manage agents and support tickets without sharing administrator credentials.
- **Target User & Primary Use Case**: Organization owners and HR leads. Primary use case: Inviting operators, assigning roles, and provisioning dashboard seats.
- **User-Facing Capabilities**: Teammate invitation form; active user list; seat quota usage bar; role assignment dropdown.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Teammate email, role selection, permission scopes.
  - *Outputs*: System invitation email; team member account.
  - *Prerequisites*: Available dashboard seats on current subscription plan.
- **Configuration Options & Business Rules**:
  - Roles: `Admin` (full access across all modules, billing, and API keys) and `Operator` (operational access restricted to tickets, chat logs, and leads) `[OBSERVED_LIVE]`.
  - Granular access scopes: Module-specific permission checkboxes `[OBSERVED_LIVE]`.
  - Seat quotas: Test Drive (1 seat), Starter (4 seats), Growth (10 seats), Scale (20 seats) `[DOCUMENTED]`.
- **Relevant UI Routes**:
  - `/accounts/team/` (Team Management Screen) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Entities: `TenantUser`, `UserRole`, `PermissionScope`, `TeamInvite`.
- **Plan Restrictions & Usage Limits**: Seat additions beyond plan quota require plan upgrades.
- **Error Handling & Human Handoff**: Invitations to registered emails prevent duplicate accounts.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-21`), `DOCUMENTED` (`EVID-PRC-01`).
- **Known Limitations & Unverified Behavior**: Custom role creation outside Admin/Operator is not currently supported in the standard UI `[OBSERVED_LIVE]`.

```
================================================================================
MODULE 22: API Keys & Security (Credential Segregation & Origin Guard)
================================================================================
```
- **Module ID & Name**: `MOD-22` — API Keys & Security
- **User Problem Solved**: Public website chat widgets must not expose confidential server credentials, and public scripts must be protected against unauthorized embedding.
- **Target User & Primary Use Case**: Security engineers and systems developers. Primary use case: Managing dual-tier API credentials and domain whitelists.
- **User-Facing Capabilities**: Key regeneration controls; public widget key viewer; secret server API key viewer; domain origin management.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Whitelisted domain names; key regeneration requests.
  - *Outputs*: Public widget key (`ca_...`); secret server key (`cax_...`); enforced origin filter.
  - *Prerequisites*: Admin role privileges.
- **Configuration Options & Business Rules**:
  - Dual-key architecture:
    - `ca_...`: Public key restricted to web widget embedding. Blocked on all server APIs since 19 October 2026 `[DOCUMENTED]`.
    - `cax_...`: Secret server key required for Chat, Sync, and Lead Conversion APIs in `X-API-Key` header `[OBSERVED_LIVE]`.
  - Allowed Domains Whitelist: Empty list allows nothing; requests from unlisted domains fail CORS/origin check `[DOCUMENTED]`.
- **Relevant UI Routes**:
  - `/accounts/security/api-keys/` (API Keys Screen) `[OBSERVED_LIVE]` (Corrects legacy 404 path `/security/`).
- **Related APIs, Integrations & Data Entities**:
  - Gateway Authentication Middleware.
  - Entities: `APIKeyCredential`, `AllowedDomainOrigin`.
- **Plan Restrictions & Usage Limits**: Available across all plans.
- **Error Handling & Human Handoff**: Immediate key invalidation upon regeneration; old keys return `HTTP 401`.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-22`), `DOCUMENTED` (`EVID-DOC-15`).
- **Known Limitations & Unverified Behavior**: Key rotation does not offer a dual-key grace overlap window; regeneration is instantaneous `[DOCUMENTED]`.

```
================================================================================
MODULE 23: Usage Dashboard (Metered Resource Consumption & Quotas)
================================================================================
```
- **Module ID & Name**: `MOD-23` — Usage Dashboard
- **User Problem Solved**: Tenants need real-time tracking of message volume, knowledge records, and seats to avoid service disruption.
- **Target User & Primary Use Case**: Account billing administrators. Primary use case: Monitoring quota consumption against plan limits and triggering upgrades.
- **User-Facing Capabilities**: Real-time progress bars for messages, knowledge storage, dashboard seats, and agent credits; upgrade CTA buttons.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Real-time billing meter counters.
  - *Outputs*: Visual consumption dashboard.
  - *Prerequisites*: Active account.
- **Configuration Options & Business Rules**:
  - Active plan badge displayed (`Test Drive`, `Starter`, `Growth`, `Scale`).
  - Progress bars: Messages this month, knowledge records count, active team seats.
- **Relevant UI Routes**:
  - `/billing/usage/` (Usage Dashboard Screen) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Entities: `TenantUsageMeter`, `SubscriptionQuota`.
- **Plan Restrictions & Usage Limits**: Displays current plan boundaries.
- **Error Handling & Human Handoff**: Approaching 100% capacity triggers in-app warning banner.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-23`).
- **Known Limitations & Unverified Behavior**: Hard quota lockouts (e.g. completely blocking chat vs overage billing) are governed by subscription terms `[DOCUMENTED]`.

```
================================================================================
MODULE 24: Invoicing System (Subscription Invoices & Payment Gateway)
================================================================================
```
- **Module ID & Name**: `MOD-24` — Invoicing System
- **User Problem Solved**: Businesses require automated tax-compliant billing invoices and receipts for SaaS subscription charges.
- **Target User & Primary Use Case**: Accounting departments. Primary use case: Viewing billing transaction history, downloading PDF invoices, and completing renewal payments.
- **User-Facing Capabilities**: Invoices data table; PDF download links; plan upgrade checkout routes (`/billing/checkout/starter/`, `/growth/`, `/scale/`).
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Monthly recurring charges, payment gateway transactions.
  - *Outputs*: Itemized invoice records with dates, amounts, and statuses.
  - *Prerequisites*: Paid plan subscription.
- **Configuration Options & Business Rules**:
  - Supports monthly and annual billing cycles (annual billing includes a 15% discount) `[DOCUMENTED]`.
- **Relevant UI Routes**:
  - `/billing/invoices/` (Invoices Screen) `[OBSERVED_LIVE]`
  - `/accounts/pricing/` (Pricing Catalog) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Payment Gateways (SSLCommerz, Stripe/Card).
  - Entities: `BillingInvoice`, `SubscriptionPlan`, `PaymentReceipt`.
- **Plan Restrictions & Usage Limits**: Available across all plans.
- **Error Handling & Human Handoff**: Failed card renewals generate a past-due grace period notification.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-24`), `DOCUMENTED` (`EVID-PRC-01`).
- **Known Limitations & Unverified Behavior**: Specific domestic corporate tax withholding certificates must be handled manually with accounting `[INFERRED]`.

```
================================================================================
MODULE 25: Company Settings (Tenant Identity & Multi-Currency)
================================================================================
```
- **Module ID & Name**: `MOD-25` — Company Settings
- **User Problem Solved**: Conversational agents and checkout systems must reflect the organization's business name, branding, and local operating currency.
- **Target User & Primary Use Case**: Company administrators. Primary use case: Establishing tenant profile metadata, uploading company logos, and configuring operating currency.
- **User-Facing Capabilities**: Company name editor; logo uploader; currency dropdown; business address and contact information fields.
- **Inputs, Outputs & Prerequisites**:
  - *Inputs*: Company Name, Currency (BDT, USD, EUR, GBP), Logo file, Website URL.
  - *Outputs*: Tenant profile configuration inherited across AI Sales Agent and invoices.
  - *Prerequisites*: None.
- **Configuration Options & Business Rules**:
  - Fields: `name`, `currency` (BDT, USD, EUR, GBP), `logo`, `website`, `address`.
  - Default currency is inherited by the Offerings Catalog and AI Sales Agent quotes.
- **Relevant UI Routes**:
  - `/accounts/company/update/` (Update Company Screen) `[OBSERVED_LIVE]`
- **Related APIs, Integrations & Data Entities**:
  - Entities: `CompanyTenant`, `CurrencyConfig`.
- **Plan Restrictions & Usage Limits**: Available across all plans.
- **Error Handling & Human Handoff**: Validation requires non-empty company name.
- **Evidence Status & References**: `OBSERVED_LIVE` (`EVID-OBS-19`), `DOCUMENTED` (`EVID-DOC-09`).
- **Known Limitations & Unverified Behavior**: Multi-currency automatic exchange rate conversion during a single checkout session is not supported; base currency is static `[DOCUMENTED]`.

---

## 4. Architectural Reality Checks Summary

The table below catalogs the major architectural hypotheses disproven through empirical inspection during this reverse-engineering engagement:

| Prior Audit Hypothesis | Empirical Ground Truth | Exact Evidence Reference | Re-Verification Status |
| :--- | :--- | :--- | :---: |
| **BANT Conversational Bot** | Smart Lead Agent is an **asynchronous daily batch job** (03:00); customer experiences zero chat interruption. | `/chat/chatbot/settings/` & `/docs/lead-agent/` | `CONTRADICTED` |
| **In-Chat bKash/Nagad Forms** | Central AI uses a **decoupled cart redirect URL** (`{items}`, `{ref}`); payment occurs on merchant store. | `/sales/` & `/docs/live-checkout/` | `CONTRADICTED` |
| **WhatsApp/IG Outbound Campaigns**| AI Lead Engagement is strictly an **email-only automated outreach system** via connected mailboxes. | `/lead-engagement/settings/` & `/docs/lead-engagement/` | `CONTRADICTED` |
| **Retrieval Chunk Count K=4** | Chat Agent retrieves the **8 most relevant passages** across documents and FAQs. | `/docs/chat-agent/` | `CONTRADICTED` |
| **Central AI Hosts Commerce API** | UCP Contract v1.3 is an **inverted contract implemented by the merchant's server** and invoked by Central AI. | `/chat/api/docs/` & `/docs/commerce-api/` | `CONTRADICTED` |
| **Knowledge Quotas 1k/5k/20k** | Actual quotas are **3,000** (Starter), **10,000** (Growth), and **50,000** (Scale) records. | `/pricing/` DOM attributes | `CONTRADICTED` |
| **5 Offering Types** | Live platform form provides **7 distinct offering kinds** (`Product`, `Service`, `Course Intake`, `Appointment`, `Event Date`, `Plan`, `Custom`). | `/sales/offerings/new/` | `CONTRADICTED` |
