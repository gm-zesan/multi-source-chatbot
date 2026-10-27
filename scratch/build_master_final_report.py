import os

target_path = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai/15_FINAL_PRODUCT_ANALYSIS_REPORT.md"

content = """# Central AI SaaS: Master Reverse Engineering & Functional Analysis Report

- **Document ID**: `MASTER-ANALYSIS-15`
- **Target Platform**: Central AI (`https://centralai.daffodil-bd.com`)
- **Inspection Date**: October 2026
- **Auditor**: Independent Pair Programming Assistant (Antigravity)
- **Status**: Comprehensive Master Deliverable (Post Live Authenticated Crawl)

---

## 1. Executive Summary & Architectural Overview

Central AI is an enterprise-grade agentic conversational AI platform developed by Daffodil Computers Ltd (Daffodil Family, Dhaka, Bangladesh). It unifies autonomous multi-agent task execution, omnichannel messaging, and conversational commerce into a single Django-based SaaS suite.

Unlike traditional single-turn chatbots that simply perform RAG over FAQs, Central AI divides conversational responsibilities into **6 specialized AI agents**:
1. **Chat Agent**: Answers customer questions from uploaded documents, FAQs, and web crawls using **8 retrieved passages per query**.
2. **AI Sales Agent**: Interactive in-chat assisted selling across **7 offering kinds**, calculating totals and offering **3 distinct checkout completion modes**.
3. **Smart Lead Agent**: Asynchronous **daily batch summarization engine** (runs once a day, default 03:00) categorizing conversations into up to 50 user-defined buckets without interrupting customer chat.
4. **Auto Lead Capture**: Anonymous website visitor tracking engine monitoring dwell time and interest scores, binding browsing history upon identification.
5. **AI Lead Engagement**: Automated **email-only nurturing add-on** working via connected company mailboxes with `draft_only` and `autonomous` modes.
6. **Support Ticket Agent**: In-chat issue and frustration detector that collects customer email, assigns priority (`low`, `medium`, `high`, `urgent`), and creates helpdesk tickets.

---

## 2. Quantitative Inspection Audit Metrics

| Metric Dimension | Reconciled Value | Source / Verification Method |
| :--- | :---: | :--- |
| **Total Functional Modules Discovered** | **25 Modules** | Identified across live navigation, settings, and API specs (`MOD-01` to `MOD-25`). |
| **Total Platform Routes Inspected** | **54 Routes** | Crawled via authenticated session [OBSERVED_LIVE] and public HTTP requests. |
| **Live Accessible Administrative Screens** | **21 Screens** | Directly observed in authenticated Test Drive workspace (`zesan`, Company: `ABC`). |
| **Subscription Plan-Gated Routes** | **5 Routes** | Confirmed redirecting to `/accounts/pricing/` (Web training, API sync, Leads list). |
| **Total End-to-End Workflows Reconstructed**| **6 Workflows** | Detailed with Mermaid sequence diagrams and status lifecycles. |
| **Server & Client APIs Documented** | **8 Interfaces** | Inspected from live in-app specification at `/chat/api/docs/` (19 tables). |
| **Total Documentation Files Produced** | **30 Files** | All organized under `docs/product-audit/central-ai/`. |

---

## 3. High-Level System Architecture Diagram

```mermaid
graph TB
    subgraph InboundChannels ["Omnichannel Ingress [OBSERVED_LIVE]"]
        WIDGET["Website Chat Widget<br/>(Snippet key: ca_pub_...)"]
        TRACKER["Visitor Journey Tracker<br/>(Site ID: ca_pub_...)"]
        META_FB["Facebook Messenger<br/>(Meta Graph OAuth)"]
        META_IG["Instagram Direct<br/>(Professional DMs)"]
        META_WA["WhatsApp Cloud API<br/>(WABA Cloud Phone)"]
        REST_API["Server-to-Server API<br/>(Secret key: cax_live_...)"]
    end

    subgraph SecurityGateway ["Security & Origin Guard [OBSERVED_LIVE]"]
        ORIGIN["Allowed Domains Guard<br/>(Empty allows nothing)"]
        AUTH_KEY["Two-Key Authenticator<br/>(ca_ public vs cax_ secret)"]
        RATE["Token Bucket Limiter"]
    end

    subgraph AgentCore ["Specialized 6-Agent Runtime [OBSERVED_LIVE]"]
        ROUTER{"Agent Intent Router"}
        AGENT_CHAT["1. Chat Agent<br/>(8-Passage RAG)"]
        AGENT_SALES["2. AI Sales Agent<br/>(7 Offering Kinds)"]
        AGENT_CAPTURE["4. Auto Lead Capture<br/>(Visitor Scoring)"]
        AGENT_SUPPORT["6. Support Ticket Agent<br/>(Ticket Dispatch)"]
    end

    subgraph BatchAndAsync ["Asynchronous & Outbound Subsystems [OBSERVED_LIVE]"]
        CRON_LEAD["3. Smart Lead Agent<br/>(Daily Batch Job @ 03:00)"]
        MAILBOX_ENGAGE["5. AI Lead Engagement<br/>(Email Mailbox Sequences)"]
    end

    subgraph CommerceEngine ["Commerce & Checkout Gateway [OBSERVED_LIVE]"]
        CHECKOUT_SITE["Mode 1: Live Checkout URL<br/>https://yourshop.com/cart?items={items}&ref={ref}"]
        CHECKOUT_CHAT["Mode 2: In-Chat Order<br/>(COD, bKash, Nagad, Bank Transfer)"]
        CHECKOUT_UCP["Mode 3: UCP Commerce API v1.3<br/>(quote.calculate / commit.create)"]
        WEBHOOK_ORDER["Order Webhook Receiver<br/>(POST /webhooks/orders/ with RFC 9421)"]
    end

    subgraph StorageLayer ["Data & State Stores [DOCUMENTED]"]
        KB_STORE[("Knowledge Vector Index<br/>(Company-scoped Namespace)")]
        OFFERING_DB[("Offerings Database<br/>(7 Offering Types)")]
        LEAD_DB[("Leads & CRM DB<br/>(Odoo XML-RPC Sync)")]
        TICKET_DB[("Support Tickets Desk<br/>(4 Priorities / 4 Statuses)")]
    end

    WIDGET --> ORIGIN --> RATE --> ROUTER
    TRACKER --> ORIGIN --> AGENT_CAPTURE
    META_FB --> ROUTER
    META_IG --> ROUTER
    META_WA --> ROUTER
    REST_API --> AUTH_KEY --> ROUTER

    ROUTER --> AGENT_CHAT <--> KB_STORE
    ROUTER --> AGENT_SALES <--> OFFERING_DB
    ROUTER --> AGENT_SUPPORT --> TICKET_DB

    AGENT_CHAT -.-> CRON_LEAD --> LEAD_DB
    LEAD_DB -.-> MAILBOX_ENGAGE

    AGENT_SALES --> CHECKOUT_SITE
    AGENT_SALES --> CHECKOUT_CHAT
    AGENT_SALES --> CHECKOUT_UCP
    CHECKOUT_SITE -.-> WEBHOOK_ORDER --> AGENT_CHAT
```

---

## 4. Discovered Modules Breakdown & Primary Findings

### Module 1: Chat Agent (`/chat/chatbot/settings/`)
- **Verified Behavior**: Core conversational RAG runtime. Lookups select the **8 most relevant passages** across documents, CSV rows, FAQs, and scraped web pages [DOCUMENTED].
- **Observed Settings**: Live system prompt textarea, tone customization, model persona [OBSERVED_LIVE].

### Module 2: AI Sales Agent (`/sales/`)
- **Verified Behavior**: Master commerce controller. Configures 9 business industry profiles, 7 offering types (`Product`, `Service`, `Course Intake`, `Appointment`, `Event Date`, `Plan`, `Custom`), and 3 completion modes [OBSERVED_LIVE].
- **Payment Settings**: Live form supports payment notes for `cash_on_delivery`, `bkash_manual`, `nagad_manual`, `bank_transfer`, and `payment_link` [OBSERVED_LIVE].

### Module 3: Smart Lead Agent (`/chat/chatbot/settings/`)
- **Verified Behavior**: Overturned previous audit claim of live in-chat BANT interviews. Confirmed to be an **asynchronous daily batch summarization job** running once per day (default `03:00`, `Asia/Dhaka`), categorizing conversations into up to 50 custom categories [OBSERVED_LIVE].

### Module 4: Auto Lead Capture (`/lead-capture/dashboard/`)
- **Verified Behavior**: Anonymous web visitor tracker. Generates persistent visitor IDs (`GET /lead-capture/vid/`), ingests behavioral events (`POST /lead-capture/events/`), and binds browsing history upon customer identification [OBSERVED_LIVE]. Gated to Growth+ (500/mo) and Scale (2,000/mo).

### Module 5: AI Lead Engagement (`/lead-engagement/settings/`)
- **Verified Behavior**: Overturned previous audit claim of WhatsApp marketing. Confirmed to be an **automated corporate email follow-up engine** operating via connected company mailboxes (`/accounts/company/mailboxes/`) with `draft_only` and `autonomous` modes [OBSERVED_LIVE].

### Module 6: Support Ticket Agent (`/support/tickets/`)
- **Verified Behavior**: Helpdesk ticketing agent. Ingests issues from chat, collects email, assigns priority (`low`, `medium`, `high`, `urgent`), and tracks statuses (`open`, `in_progress`, `resolved`, `closed`). Replies are sent via company mailbox and customer replies return to ticket [OBSERVED_LIVE].

### Module 7: Offerings Catalog & "Find What I Sell" (`/sales/offerings/new/` & `/sales/suggestions/`)
- **Verified Behavior**: Complete structured catalog management supporting SKU, external reference, pricing in BDT/USD, inventory tracking, and fulfillment kinds. The "Find What I Sell" scanner reads uploaded knowledge base files and extracts offerings word-for-word into a review queue [OBSERVED_LIVE].

### Module 8: Web Chat Widget & Meta Hub (`/chat/widget/` & `/integrations/meta/`)
- **Verified Behavior**: Embedded JavaScript snippet authenticated via public `ca_...` key. Protected by the Domain Origin Whitelist. Integrates natively with Facebook Messenger, Instagram Direct, and WhatsApp Cloud API [OBSERVED_LIVE].

### Module 9: Server APIs & Universal Commerce Protocol (`/chat/api/docs/`)
- **Verified Behavior**: 19 reference specification tables covering server-to-server endpoints (`X-API-Key: cax_...`), Lead Conversion API, and UCP Commerce Contract v1.3 with RFC 9421 signature verification and idempotency handling [OBSERVED_LIVE].

---

## 5. End-to-End Workflows Summary

1. **Knowledge Ingestion Workflow**: Documents (PDF/DOCX/TXT up to 10MB) -> Text extraction -> ~400 char chunking (80 overlap) -> Vector embedding -> Company namespace index.
2. **Omnichannel Customer Inquiry Workflow**: User turn (Web/Meta) -> Origin & signature verification -> Supervisor router -> 8-passage vector search -> Low-temperature LLM generation with citations -> Token streaming.
3. **Decoupled Live Checkout Workflow**: Product discovery in chat -> Cart basket assembly -> Customer clicks checkout link (`https://yourshop.com/cart/add?items={items}&ref={ref}`) -> Payment on merchant gateway -> Merchant posts signed webhook (`POST /webhooks/orders/`) -> Live chat bubble updates to "Payment Received".
4. **Smart Lead Daily Batch Workflow**: Daily scheduler triggers at 03:00 -> Queries unsummarized chats -> Generates concise summaries -> Assigns category from 50-item taxonomy -> Updates `/leads/` table -> Deducts 1 Lead Agent credit.
5. **Support Ticket Escalation Workflow**: Customer expresses frustration -> Agent requests email -> Ticket created with urgency rating -> Human support notified via email -> Live chat replies paused for takeover.
6. **Headless Commerce Contract v1.3 (UCP)**: Central AI calls merchant UCP discovery (`/.well-known/ucp`) -> Checks availability -> Calculates live quotes -> Commits order with `Idempotency-Key` -> Merchant issues payment URL -> Signed status webhook.

---

## 6. Commercial Packaging & Plan Limits Reconciled

Independently verified against live `/pricing/` DOM attributes:

| Plan | Price (Monthly / Annual) | Messages / Mo | Knowledge Records | Dashboard Seats | Key Features & Quotas |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Test Drive** | Free (7 Days) | 60 | 300 | 1 | Web widget, basic FAQ/Docs, playground. Social & CRM disabled. [OBSERVED_LIVE] |
| **Starter** | $15 / mo ($12.75 billed yearly)| 3,000 | 3,000 | 4 | Web widget, Facebook Messenger, Web training, Live checkout. |
| **Growth** | $50 / mo ($42.50 billed yearly)| 15,000 | 10,000 | 10 | WhatsApp, Instagram, Smart Lead (3k credits), Auto Lead Capture (500 leads), Odoo CRM, 1 Partner. |
| **Scale** | $150 / mo ($127.50 billed yearly)| 50,000 | 50,000 | 20 | All Growth features + 9k lead credits, 2k auto leads, 5 Partners, White-label branding, Commerce API. |
| **Custom** | Tailored SLA | Unlimited / Custom| Dedicated | Custom | Multi-tenant setup, custom CRM/SSO, dedicated onboarding. |

---

## 7. Comparative Assessment vs. `multi-source-chatbot`

| Evaluation Criteria | Central AI Platform | Our System (`multi-source-chatbot`) |
| :--- | :--- | :--- |
| **Tabular Data Analytics** | Flattens CSVs into key-value text pairs (`col: val`). Cannot perform dynamic math/SQL queries [DOCUMENTED]. | **Deterministic SQLite Engine**: SQLite per file, automated join discovery, SQL synthesis, accurate math [OBSERVED_LIVE]. |
| **E-Commerce & Transactions** | Full conversational commerce: Offerings catalog, Live Checkout link, UCP Commerce API v1.3, signed order webhooks [OBSERVED_LIVE]. | Informational RAG only (no transactional cart or checkout gateway). |
| **Omnichannel Messaging** | Native Meta Cloud Hub: Facebook Messenger, Instagram Direct, WhatsApp Cloud API [OBSERVED_LIVE]. | Web embed widget & playground only. |
| **Security Architecture** | Two-tier key scheme (`ca_...` public widget, `cax_...` secret server API), Origin whitelisting [OBSERVED_LIVE]. | Granular Spatie RBAC (`faq.view`, `knowledge_document.view`, Superadmin workspace switching). |
| **Lead Nurturing** | Dual engine: Daily batch lead summarizer + Automated mailbox email sequences [OBSERVED_LIVE]. | Generic webhook triggers and database records. |

---

## 8. Remaining Unknowns & Manual Follow-Up Tests

1. **Underlying Infrastructure Internals**:
   - The specific embedding model provider (e.g. OpenAI `text-embedding-3-small` vs Cohere vs local) and vector database engine (pgvector vs Qdrant vs Typesense) are abstracted behind backend Django views and remain undisclosed.
2. **Live High-Tier Integrations**:
   - End-to-end execution of live WhatsApp Cloud API messaging and live Odoo two-way XML-RPC sync requires upgrading the Test Drive account to Growth/Scale tier with valid external Meta and Odoo credentials.
"""

with open(target_path, "w", encoding="utf-8") as f:
    f.write(content.strip() + "\n")

print(f"Created {target_path} ({len(content)} bytes)")
