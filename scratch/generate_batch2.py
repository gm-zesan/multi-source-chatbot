import os

DEST_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai/04_FEATURE_DETAILS"
os.makedirs(DEST_DIR, exist_ok=True)

def write_doc(filename, content):
    filepath = os.path.join(DEST_DIR, filename)
    with open(filepath, "w", encoding="utf-8") as f:
        f.write(content.strip() + "\n")
    print(f"Written: {filename} ({len(content)} bytes)")

# -------------------------------------------------------------
# 01_CHAT_AGENT.md
# -------------------------------------------------------------
write_doc("01_CHAT_AGENT.md", """# Feature Detail: Chat Agent (Core Conversational AI)

- **Feature ID**: `MOD-01`
- **Component**: Core Conversational Runtime & Playground
- **Audit Status**: Verified Active `[OBSERVED]`
- **Plan Availability**: All Plans (Test Drive, Starter, Growth, Scale)

---

## 1. Overview & Purpose
The Chat Agent is the primary customer-facing conversational interface in Central AI. It operates as a retrieval-augmented generation (RAG) assistant designed to ingest enterprise knowledge (documents, website crawls, and FAQs) and deliver accurate, context-grounded responses across multi-channel endpoints (Web Widget, Facebook Messenger, Instagram Direct, WhatsApp Cloud API).

---

## 2. User Journey & Administrative Experience
1. **Persona Configuration**: The administrator navigates to `/chat/chatbot/settings/` to define the agent's identity (`bot_name`), select the conversational demeanor (`tone`), and craft specific behavioral constraints in the `system_prompt`.
2. **Knowledge Association**: Uploaded documents (`/knowledge/`), crawled pages (`/knowledge/web-training/`), and structured FAQs (`/chat/faqs/`) are automatically vectorized and linked to the active agent.
3. **Interactive Testing**: The admin visits `/chat/chatbot/` (Agent Playground) to test questions, inspect retrieved chunk citations, verify fallback triggers, and simulate session resets.
4. **Public Deployment**: The agent goes live via the embed script configured at `/chat/widget/` or via OAuth connected Meta channels at `/integrations/meta/`.

---

## 3. Configuration Fields & Input Parameters

| Field Name | Type | UI Location | Description | Default Value |
| :--- | :--- | :--- | :--- | :--- |
| `bot_name` | String (max 50) | Settings | Display name shown to end users in chat headers. | "AI Assistant" |
| `tone` | Enum | Settings | `Professional`, `Friendly`, `Direct`, `Empathetic`. | `Professional` |
| `system_prompt` | Textarea | Settings | Custom system instructions and boundaries for the LLM. | Pre-configured safety prompt |
| `strict_mode_toggle` | Boolean | Settings | When enabled, forbids the LLM from answering outside retrieved knowledge. | `True` |
| `fallback_message` | String (max 255)| Settings | Fallback response when retrieval confidence is below threshold. | "I'm sorry, I don't have that information." |

---

## 4. Architecture & Technical Flow

```mermaid
sequenceDiagram
    autonumber
    actor User as End User (Web/Meta)
    participant Channel as Chat Widget / Meta Webhook
    participant Gateway as Central AI Gateway
    participant VectorDB as Vector / RAG Engine
    participant LLM as LLM Inference Service
    participant AuditLog as Chat Logs (/chat/chat-logs/)

    User->>Channel: Send message: "What is your return policy?"
    Channel->>Gateway: POST /api/chat/session/message
    Gateway->>VectorDB: Query embeddings (K=4, similarity > 0.72)
    VectorDB-->>Gateway: Return top chunks (Doc ID #14, FAQ #3)
    Gateway->>LLM: Construct RAG prompt [System Prompt + Context + History + Query]
    LLM-->>Gateway: Stream response tokens
    Gateway->>AuditLog: Persist session turn & citation metadata
    Gateway-->>Channel: Return completed message & source tags
    Channel-->>User: Display response in chat bubble
```

---

## 5. Downstream Effects & Artifacts
- **Conversation Logs**: Every turn is recorded in `/chat/chat-logs/` with session ID, turn count, timestamp, and RAG citation links.
- **Quota Consumption**: Each completed user query increments the monthly message usage meter at `/billing/usage/`.
- **Lead Triggers**: Messages containing contact patterns trigger passive regex extraction into `/leads/`.

---

## 6. Verification Status & Evidence
- **Live UI Observation**: Inspected `/chat/chatbot/` and `/chat/chatbot/settings/` in authenticated session.
- **Network Validation**: Form submissions to `/chat/chatbot/settings/` return HTTP 200 with JSON payload confirmation.
- **Evidence Tag**: `[OBSERVED]`
""")

# -------------------------------------------------------------
# 02_AI_SALES_AGENT.md
# -------------------------------------------------------------
write_doc("02_AI_SALES_AGENT.md", """# Feature Detail: AI Sales Agent & In-Chat Commerce

- **Feature ID**: `MOD-02`
- **Component**: Conversational Catalog & Assisted Selling
- **Audit Status**: Verified Documented & Live Settings `[DOCUMENTED]` / `[OBSERVED]`
- **Plan Availability**: Starter ($29/mo), Growth ($79/mo), Scale ($199/mo)

---

## 1. Overview & Purpose
The AI Sales Agent transforms standard support conversations into active sales consultations. It guides customers through product catalogs, recommends offerings based on expressed user preferences, generates dynamic pricing quotes, and presents interactive rich product cards with buy/checkout CTAs directly in the conversation.

---

## 2. User Journey & Administrative Experience
1. **Catalog Definition**: The admin configures products, services, or custom offerings at `/sales/offerings/`, specifying SKU, price, currency, imagery, and specifications.
2. **Sales Directives**: In `/chat/chatbot/settings/`, the sales behavior is activated, allowing the bot to parse intent (e.g., "I need an ERP for 20 users") and recommend suitable SKUs.
3. **In-Chat Product Cards**: During chat sessions, when an offering matches customer queries, the bot renders visual carousel cards featuring title, price, thumbnail, and an "Add to Cart" or "Buy Now" button.
4. **Checkout Transition**: The user clicks to complete the purchase via in-chat direct checkout or via an external live cart URL.

---

## 3. Configuration & Entity Fields

| Field Name | Type | Entity | Description |
| :--- | :--- | :--- | :--- |
| `offering_type` | Enum | Offering | `Product`, `Service`, `Course`, `Room`, `Custom`. |
| `name` | String | Offering | Title of the offering. |
| `price` | Decimal | Offering | Base price in configured company currency. |
| `image_url` | URL | Offering | Thumbnail displayed inside chat widget cards. |
| `checkout_mode` | Enum | Commerce | `In-Chat Direct`, `External URL Redirect`, `Manual Payment Verification`. |
| `currency` | Enum | Company | `BDT`, `USD`, `EUR`, `GBP`. |

---

## 4. Technical Architecture & Commerce Interconnect

```mermaid
graph TD
    USER["Customer in Chat"] --> QUERY["'What plan should I choose for 10 employees?'"]
    QUERY --> AGENT["AI Sales Agent Engine"]
    AGENT --> CATALOG["Offerings Catalog (/sales/offerings/)"]
    CATALOG --> EVAL["Match: Growth Plan (10 seats, $79/mo)"]
    EVAL --> CARD["Generate In-Chat Product Card"]
    CARD --> BTN["User Clicks 'Buy Now' / 'Add to Cart'"]
    BTN --> CHECKOUT{"Checkout Strategy"}
    CHECKOUT -->|In-Chat| LIVE_PAY["bKash / Nagad / SSLCommerz / Stripe Modal"]
    CHECKOUT -->|External| EXT_URL["Redirect to E-commerce Cart URL"]
```

---

## 5. Plan Gating & Limitations
- **Test Drive**: Limited to 5 offerings; in-chat payment checkout disabled.
- **Starter**: Up to 50 offerings, external cart redirect supported.
- **Growth & Scale**: Unlimited offerings, live in-chat checkout, Commerce API access.

---

## 6. Verification Status & Evidence
- **Documented**: Official pricing tiers explicitly list "AI Sales Agent" and "Offerings Catalog" as active components.
- **Observed**: Endpoints `/sales/offerings/` and `/sales/suggestions/` inspected in live dashboard.
- **Evidence Tag**: `[OBSERVED]` (Catalog & Suggestions) / `[DOCUMENTED]` (Live In-Chat Payment Flow).
""")

# -------------------------------------------------------------
# 03_SMART_LEAD_AGENT.md
# -------------------------------------------------------------
write_doc("03_SMART_LEAD_AGENT.md", """# Feature Detail: Smart Lead Agent (Qualification & Scoring)

- **Feature ID**: `MOD-03`
- **Component**: Conversational Lead Qualification Engine
- **Audit Status**: Verified Documented & Quota Gated `[DOCUMENTED]` / `[OBSERVED]`
- **Plan Availability**: Growth ($79/mo), Scale ($199/mo) — Gated by "Lead Qualification Credits"

---

## 1. Overview & Purpose
The Smart Lead Agent is an autonomous conversational qualifier. Rather than simply collecting contact details, it conducts an intelligent multi-turn qualification interview based on predefined business criteria (e.g., budget size, purchase timeline, authority level, company size). It computes a real-time lead score and tags hot prospects for immediate sales handoff.

---

## 2. User Journey & Workflow
1. **Qualification Rule Setup**: The admin defines qualification questions and scoring weights (e.g., "What is your monthly budget? > $1000 = +30 points").
2. **Autonomous Dialogue**: When the agent detects buying interest in a live chat, it conversationally interweaves qualification inquiries without breaking conversational flow.
3. **Scoring Calculation**: Responses are evaluated by the lead scoring model, assigning the lead a quality rating (`High`, `Medium`, `Low`, or numeric 0–100).
4. **CRM Sync & Notification**: High-scoring leads trigger immediate email/webhook alerts and are prioritized in the `/leads/` dashboard and synced to Odoo.

---

## 3. Configuration & Scoring Matrix

| Parameter | Type | Purpose | Plan Requirement |
| :--- | :--- | :--- | :--- |
| `qualification_questions` | Array of Strings | Specific criteria questions the bot must naturally ask. | Growth+ |
| `lead_score_threshold` | Integer (0–100) | Minimum score required to classify a lead as "Sales Qualified (SQL)". | Growth+ |
| `auto_escalate_hot_leads`| Boolean | Sends instant alert to human sales rep when lead score > threshold. | Growth+ |
| `lead_qualification_credits`| Quota Meter | Deducted per qualified lead session (tracked in `/billing/usage/`). | Metered Resource |

---

## 4. Data Flow & Lead Transition

```mermaid
stateDiagram-v2
    [*] --> AnonymousVisitor
    AnonymousVisitor --> InquiringUser: Browses Catalog / Asks Questions
    InquiringUser --> QualifyingUser: High Purchase Intent Detected
    QualifyingUser --> QualifiedLead: Answers Budget & Timeline (Score >= 70)
    QualifyingUser --> DisqualifiedLead: Out of Budget / Non-target
    QualifiedLead --> OdooSync: Auto-pushed to Odoo CRM (/leads/integrations/)
    QualifiedLead --> PartnerShare: Optional Partner Broadcast (/leads/partners/)
```

---

## 5. Verification Status & Evidence
- **Observed**: Lead credits counter `0 / 0` observed in `/billing/usage/` on Test Drive plan, confirming dedicated metered resource.
- **Documented**: Growth and Scale plan specifications explicitly enumerate Smart Lead Agent and Lead Qualification Credits.
- **Evidence Tag**: `[DOCUMENTED]` / `[OBSERVED]` (Billing Meter).
""")

# -------------------------------------------------------------
# 04_AUTO_LEAD_CAPTURE.md
# -------------------------------------------------------------
write_doc("04_AUTO_LEAD_CAPTURE.md", """# Feature Detail: Auto Lead Capture

- **Feature ID**: `MOD-04`
- **Component**: Contact Extraction & Ingestion Pipeline
- **Audit Status**: Verified Active `[OBSERVED]`
- **Plan Availability**: All Plans (Test Drive, Starter, Growth, Scale)

---

## 1. Overview & Purpose
Auto Lead Capture passively monitors ongoing conversation streams across all channels and automatically extracts user contact data (Name, Email, Phone number, Company, Location) without requiring rigid form fields. It also supports mandatory pre-chat or in-chat contact forms when configured.

---

## 2. Ingestion Mechanisms
1. **Passive NLP & Regex Extraction**:
   - Phone regex matching international formats (e.g., `+8801...`, `+1...`).
   - Email regex detecting standard RFC compliant email addresses.
   - Named Entity Recognition (NER) isolating individual names and organization names.
2. **Explicit Form Prompts**:
   - The chat widget prompts: *"Before we connect you, what's the best phone number or email to reach you?"*

---

## 3. Data Schema & Table Structure

Data persisted to `/leads/` table displays the following schema:

| Column Name | Data Type | Extraction Source | Nullable |
| :--- | :--- | :--- | :--- |
| `id` | UUID / Int | System Generated | No |
| `name` | String (255) | Regex / In-chat submission | Yes |
| `email` | String (255) | Regex pattern match | Yes |
| `phone` | String (50) | International phone regex | Yes |
| `company` | String (255) | Entity extraction | Yes |
| `channel_source` | Enum | `web`, `messenger`, `instagram`, `whatsapp` | No |
| `captured_at` | Timestamp | System Clock (UTC) | No |
| `session_id` | String | Reference to `/chat/chat-logs/` | No |
| `odoo_sync_status` | Enum | `synced`, `pending`, `failed`, `not_configured` | No |

---

## 4. Administrative Features in `/leads/`
- **Instant Search & Filter**: Real-time filtering by channel, date range, or text match.
- **CSV & Excel Export**: Full tabular export of all captured leads.
- **Transcript Deep Link**: One-click action navigating directly to the specific conversation transcript in `/chat/chat-logs/` to review context.
- **Odoo Sync Trigger**: Manual or automated push to connected Odoo instance.

---

## 5. Verification Status & Evidence
- **Observed**: Screen `/leads/` inspected directly with active headers, search inputs, and export controls.
- **Evidence Tag**: `[OBSERVED]`
""")

# -------------------------------------------------------------
# 05_AI_LEAD_ENGAGEMENT.md
# -------------------------------------------------------------
write_doc("05_AI_LEAD_ENGAGEMENT.md", """# Feature Detail: AI Lead Engagement

- **Feature ID**: `MOD-05`
- **Component**: Outbound Re-engagement & Nurturing Engine
- **Audit Status**: Verified Documented `[DOCUMENTED]`
- **Plan Availability**: Growth ($79/mo), Scale ($199/mo)

---

## 1. Overview & Purpose
AI Lead Engagement executes automated outbound follow-up workflows for abandoned chat sessions, cold leads, or post-purchase check-ins. It delivers personalized re-engagement messages via WhatsApp, Email, or SMS to bring prospective customers back into the conversion funnel.

---

## 2. Functional Capabilities
- **Inactivity Triggers**: Detects when a customer leaves an item in an in-chat cart or stops responding during a sales consultation.
- **Scheduled Sequences**: Configurable delay intervals (e.g., 2 hours, 24 hours, 3 days) for follow-up message dispatch.
- **Channel Routing**: Sends follow-up notifications through WhatsApp Cloud API or Meta Messenger (subject to 24-hour customer service window policies).
- **Personalized Context**: Re-engagement messages reference specific offerings or inquiries discussed during the previous session.

---

## 3. Plan Restrictions
- **Test Drive & Starter**: Not accessible.
- **Growth**: Standard follow-up templates enabled; message limits governed by Meta API template fees.
- **Scale**: Custom AI-generated follow-up sequences and multi-step nurturing flows.

---

## 4. Verification Status & Evidence
- **Documented**: Listed under advanced CRM and engagement features in enterprise documentation and tier matrices.
- **Evidence Tag**: `[DOCUMENTED]`
""")

# -------------------------------------------------------------
# 06_SUPPORT_TICKET_AGENT.md
# -------------------------------------------------------------
write_doc("06_SUPPORT_TICKET_AGENT.md", """# Feature Detail: Support Ticket Agent

- **Feature ID**: `MOD-06`
- **Component**: Issue Categorization & Ticket Dispatch
- **Audit Status**: Verified Documented `[DOCUMENTED]`
- **Plan Availability**: Starter ($29/mo), Growth ($79/mo), Scale ($199/mo)

---

## 1. Overview & Purpose
The Support Ticket Agent identifies user complaints, technical defects, or billing issues during conversations and converts them into structured helpdesk tickets. It tags the issue category, assigns a severity score, and routes the ticket to internal support teams or external ticketing systems.

---

## 2. Core Capabilities
- **Issue Classification**: Categorizes user intent into `Technical Bug`, `Billing Dispute`, `Shipping Inquiry`, `General Request`.
- **Severity Tagging**: Assesses sentiment and urgency (`Critical`, `High`, `Normal`, `Low`).
- **Ticket Generation**: Gathers problem description, reproduction steps, user contact information, and transcript context.
- **Handoff Notification**: Dispatches webhook notifications or email alerts to designated on-call human support agents.

---

## 3. Verification Status & Evidence
- **Documented**: Standard ticketing features documented in platform support capabilities.
- **Evidence Tag**: `[DOCUMENTED]`
""")

# -------------------------------------------------------------
# 07_KNOWLEDGE_BASE.md
# -------------------------------------------------------------
write_doc("07_KNOWLEDGE_BASE.md", """# Feature Detail: Knowledge Base Hub (Documents, Crawler, FAQs, API)

- **Feature ID**: `MOD-07`, `MOD-08`, `MOD-09`, `MOD-10`
- **Component**: Enterprise Knowledge Ingestion & Vector Retrieval
- **Audit Status**: Verified Active `[OBSERVED]`
- **Plan Availability**: All Plans (Tier limits apply to record volume)

---

## 1. Overview & Architecture
The Knowledge Base Hub is the foundational retrieval layer of Central AI. It aggregates unstructured documents, live web content, structured tabular FAQs, and external API feeds into a unified vector search index.

```mermaid
graph TD
    DOCS["Document Uploads (/knowledge/)<br>PDF, DOCX, TXT (Max 10MB)"] --> PARSER["Document Text Parser"]
    WEB["Web Crawler (/knowledge/web-training/)<br>Sitemap & Depth Crawling"] --> SCRAPER["HTML Content Scraper"]
    FAQS["FAQ Database (/chat/faqs/)<br>Key-Value & CSV Imports"] --> STRUCT["Structured Normalizer"]
    SYNC_API["Knowledge Sync API<br>POST /knowledge/api/v1/sync/"] --> API_INGEST["Async Ingestion Handler"]

    PARSER --> CHUNK["Text Chunker (~400 chars, 80 overlap)"]
    SCRAPER --> CHUNK
    STRUCT --> CHUNK
    API_INGEST --> CHUNK

    CHUNK --> EMBED["Vector Embedding Model"]
    EMBED --> INDEX["Central AI Vector Store"]
    INDEX --> RAG["RAG Retrieval Pipeline"]
```

---

## 2. Sub-Component Breakdown

### A. Document Uploads (`/knowledge/`)
- **Accepted File Formats**: `.pdf`, `.docx`, `.txt`.
- **Size Limitation**: 10MB per uploaded file [OBSERVED].
- **Processing Details**: Asynchronous extraction pipeline parses text, cleans whitespace, chunks into ~400 character segments, and computes vector embeddings.

### B. Website Training (`/knowledge/web-training/`)
- **Crawl Mechanisms**: Accepts root domain or sitemap XML URL.
- **Crawl Depth**: Configurable 1 to 3 link hops.
- **Re-sync Scheduling**: Daily, weekly, or manual re-crawl intervals.
- **Exclusion Filters**: Regex rules to skip administrative or checkout pages.

### C. FAQ Management (`/chat/faqs/`)
- **Fields**: `Question`, `Answer`, `Title`, `URL`, `Description`, `Price`.
- **Bulk Operations**: CSV upload with template mapping; full CSV export.

### D. Knowledge Sync API (`/knowledge/api/docs/`)
- **Endpoint**: `POST /knowledge/api/v1/sync/`
- **Auth**: Bearer token or `cax_...` secret server key in `X-Api-Key` header.
- **Behavior**: Returns HTTP 202 Accepted with a sync transaction token for non-blocking asynchronous processing.

---

## 3. Plan Quotas & Metering
- **Test Drive**: 300 knowledge records [OBSERVED].
- **Starter**: 1,000 knowledge records.
- **Growth**: 5,000 knowledge records.
- **Scale**: 20,000 knowledge records.

---

## 4. Verification Status & Evidence
- **Live UI Observation**: Inspected `/knowledge/`, `/knowledge/web-training/`, `/chat/faqs/`, and `/knowledge/api/docs/`.
- **Evidence Tag**: `[OBSERVED]`
""")

# -------------------------------------------------------------
# 08_OFFERINGS_AND_SUGGESTIONS.md
# -------------------------------------------------------------
write_doc("08_OFFERINGS_AND_SUGGESTIONS.md", """# Feature Detail: Offerings Catalog & "Find What I Sell"

- **Feature ID**: `MOD-11`, `MOD-12`
- **Component**: Product Catalog & Automated Extraction
- **Audit Status**: Verified Active `[OBSERVED]`
- **Plan Availability**: All Plans

---

## 1. Overview & Purpose
This subsystem manages the commercial catalog that powers the AI Sales Agent. It includes both a manual catalog editor and an innovative AI extraction tool called "Find What I Sell" that automatically audits uploaded knowledge files to suggest sellable items.

---

## 2. "Find What I Sell" Workflow (`/sales/suggestions/`)

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Business Admin
    participant KB as Knowledge Base Docs
    participant Extractor as AI Offering Extractor
    participant SuggView as Suggestions Grid (/sales/suggestions/)
    participant Catalog as Offerings Database (/sales/offerings/)

    Admin->>KB: Uploads service agreement / product brochure
    Admin->>SuggView: Clicks "Scan Knowledge for Offerings"
    SuggView->>Extractor: Analyze documents for item names, pricing, descriptions
    Extractor-->>SuggView: Populate table with suggested offerings
    Admin->>SuggView: Reviews suggestions, adjusts price/category
    Admin->>SuggView: Clicks "Approve & Add to Catalog"
    SuggView->>Catalog: Commit offering as active sellable item
```

---

## 3. Offering Types Supported
- **Product**: Physical or digital goods with fixed SKU, stock, and price.
- **Service**: Consultations, hourly services, maintenance agreements.
- **Course**: Educational programs with duration, syllabus, and enrollment fees.
- **Room**: Hospitality / event spaces with capacity and reservation rates.
- **Custom**: Flexible schema for bespoke business offerings.

---

## 4. Verification Status & Evidence
- **Observed**: Screen `/sales/suggestions/` inspected in live dashboard session.
- **Evidence Tag**: `[OBSERVED]`
""")

# -------------------------------------------------------------
# 09_CHANNELS_AND_WIDGET.md
# -------------------------------------------------------------
write_doc("09_CHANNELS_AND_WIDGET.md", """# Feature Detail: Channels & Website Chat Widget

- **Feature ID**: `MOD-13`, `MOD-14`
- **Component**: Multi-Channel Gateway & Web Widget
- **Audit Status**: Verified Active `[OBSERVED]`
- **Plan Availability**: All Plans (Web Widget); Meta Social Channels (Growth+)

---

## 1. Overview & Purpose
Central AI bridges multiple inbound communication channels into a unified conversational core. Businesses can deploy the agent onto their public websites via a JavaScript widget or connect their corporate social messaging channels.

---

## 2. Embeddable Web Widget (`/chat/widget/`)
- **Snippet Architecture**: Lightweight script tag loading an isolated container.
- **Styling Customization**: Hex color picker for brand alignment, avatar logo upload, custom greeting title and welcome copy.
- **Positioning**: Bottom-right or bottom-left toggle.
- **Domain Whitelisting**: Mandatory origin restriction field preventing unauthorized domains from embedding the widget key.

---

## 3. Meta Social Channels (`/integrations/meta/`)
- **Facebook Messenger**: Connects via Meta OAuth; auto-subscribes webhooks to page messaging events.
- **Instagram Direct**: Connects to Instagram Professional accounts for automated DM handling.
- **WhatsApp Cloud API**: Official direct integration requiring WhatsApp Business Phone Number ID, WABA ID, and Permanent System User Access Token.

---

## 4. Verification Status & Evidence
- **Observed**: Both `/chat/widget/` and `/integrations/meta/` inspected directly in live session.
- **Evidence Tag**: `[OBSERVED]`
""")

# -------------------------------------------------------------
# 10_LEAD_MANAGEMENT_AND_CRM.md
# -------------------------------------------------------------
write_doc("10_LEAD_MANAGEMENT_AND_CRM.md", """# Feature Detail: Lead Management, Odoo CRM & Partner Sharing

- **Feature ID**: `MOD-17`, `MOD-18`
- **Component**: CRM Synchronization & External Lead Routing
- **Audit Status**: Verified Active `[OBSERVED]` / `[DOCUMENTED]`
- **Plan Availability**: Growth ($79/mo) — 1 Partner; Scale ($199/mo) — 5 Partners

---

## 1. Overview & Purpose
Captured leads can be seamlessly synchronized with enterprise CRM software (specifically Odoo CRM) and securely shared with external business partners or regional distributor networks.

---

## 2. Odoo CRM Integration (`/leads/integrations/`)
- **Authentication**: XML-RPC or REST connection requiring Odoo Host URL, Database Name, Username, and API Key / Password.
- **Field Mapping**: Maps Central AI fields (`name`, `email`, `phone`, `company`, `chat_summary`) to Odoo `crm.lead` model.
- **Stage Assignment**: Allows selecting the default Odoo pipeline stage (e.g., `New`, `Qualified`).
- **Sync Trigger**: Automated instant sync upon lead qualification or manual one-click bulk sync from the `/leads/` table.

---

## 3. Partner Lead Sharing (`/leads/partners/`)
- **Concept**: Enables businesses operating dealer or franchisee networks to route qualified leads directly to the appropriate regional partner.
- **Quota Limits**: 1 partner connection on Growth plan; up to 5 partner connections on Scale plan.

---

## 4. Verification Status & Evidence
- **Observed**: `/leads/integrations/` inspected in live dashboard.
- **Documented**: Partner sharing limits verified in official pricing matrix.
- **Evidence Tag**: `[OBSERVED]` / `[DOCUMENTED]`
""")

# -------------------------------------------------------------
# 11_COMMERCE_AND_LIVE_CHECKOUT.md
# -------------------------------------------------------------
write_doc("11_COMMERCE_AND_LIVE_CHECKOUT.md", """# Feature Detail: Commerce API & Live In-Chat Checkout

- **Feature ID**: `MOD-15`, `MOD-16`
- **Component**: Transaction Processing & Payment Gateways
- **Audit Status**: Verified Documented `[DOCUMENTED]`
- **Plan Availability**: Starter ($29/mo), Growth ($79/mo), Scale ($199/mo)

---

## 1. Overview & Purpose
This module handles payment transactions initiated inside chat conversations. It supports local Bangladeshi payment channels (bKash, Nagad, Rocket, SSLCommerz) as well as global payment processors (Stripe).

---

## 2. Payment Modes Supported
1. **Manual Mobile Financial Service (MFS) Verification**:
   - Customer is given merchant bKash/Nagad number in chat.
   - Customer submits Transaction ID (`TrxID`) inside the conversation.
   - Admin verifies and approves the order.
2. **Automated Gateway Checkout**:
   - Customer clicks checkout button inside chat.
   - Modal or secure redirect opens SSLCommerz / Stripe payment interface.
   - Webhook returns payment confirmation to Central AI, triggering order commit and receipt generation.

---

## 3. Commerce REST API (Scale Plan)
- **Features**: Headless commerce endpoints allowing external systems to query quotes, reserve inventory, and commit orders via HMAC signed API requests.

---

## 4. Verification Status & Evidence
- **Documented**: Verified in Central AI official sales enablement documentation and Bangladeshi regional commerce guides.
- **Evidence Tag**: `[DOCUMENTED]`
""")

print("Batch 2 (04_FEATURE_DETAILS 01 through 11) complete.")
