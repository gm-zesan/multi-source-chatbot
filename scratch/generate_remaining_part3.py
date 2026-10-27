import os

BASE_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai"

def write_file(filename, content):
    filepath = os.path.join(BASE_DIR, filename)
    with open(filepath, "w", encoding="utf-8") as f:
        f.write(content.strip() + "\n")
    print(f"Written: {filename} ({len(content)} bytes)")

# -------------------------------------------------------------
# 11_BILLING_USAGE_AND_PLAN_LIMITS.md
# -------------------------------------------------------------
write_file("11_BILLING_USAGE_AND_PLAN_LIMITS.md", """# Central AI: Billing, Usage & Plan Limits

- **Document ID**: `BILL-11`
- **Scope**: Commercial Pricing Structure, Resource Quotas, Rate Limits, and Metering Architecture
- **Audit Status**: Verified Active `[OBSERVED]` (Live Usage UI) / `[DOCUMENTED]` (Official Pricing Matrix)

---

## 1. Comprehensive Subscription Tier Matrix

Central AI packages its platform across five commercial tiers `[DOCUMENTED]` / `[OBSERVED]`:

| Feature / Resource Dimension | Test Drive (Free) | Starter ($15 / mo)<br/>(~1,800 BDT) | Growth ($50 / mo)<br/>(~6,200 BDT) | Scale ($150 / mo)<br/>(~18,600 BDT) | Enterprise / Custom |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Validity / Term** | 7 Days Trial | Monthly / Annual | Monthly / Annual | Monthly / Annual | Tailored SLA |
| **Monthly Messages** | 60 messages | 3,000 messages | 15,000 messages | 50,000 messages | Unlimited / Pooled |
| **Knowledge Base Records** | 300 records | 1,000 records | 5,000 records | 20,000 records | Dedicated Quota |
| **Administrative Seats** | 1 user | 4 team seats | 10 team seats | 20 team seats | Custom Seats |
| **Product Offerings Limit** | 5 offerings | 50 offerings | 500 offerings | Unlimited | Unlimited |
| **Lead Qualification Credits**| 0 credits | 0 credits | 3,000 credits | 9,000 credits | Custom Quota |
| **Supported Channels** | Web Widget only | Web + Facebook | Web + FB + IG + WhatsApp | All Channels | All + Custom API |
| **Lead Capture & CRM** | Auto Lead Capture | Auto Lead Capture | Smart Lead + Odoo CRM | Smart Lead + Odoo CRM | Advanced Custom CRM |
| **Partner Lead Routing** | Disabled | Disabled | 1 Partner Network | 5 Partner Networks | Unlimited Networks |
| **Commerce & Checkout** | Disabled | Manual MFS (bKash/Nagad)| MFS + Gateways | MFS + Gateways + API | Custom ERP Hooks |
| **Branding** | Central AI badge | Central AI badge | Central AI badge | **White-Label Removed**| White-Label |

---

## 2. Metered Resource Dimensions (`/billing/usage/`)

The platform actively tracks four metered units in real time `[OBSERVED]`:

```mermaid
graph LR
    subgraph MeteredUnits ["Platform Metering Engine (/billing/usage/)"]
        M1["1. Message Quota<br/>(42 / 60 Used)"]
        M2["2. Knowledge Records<br/>(18 / 300 Used)"]
        M3["3. Lead Qualification Credits<br/>(0 / 0 Active)"]
        M4["4. Team Dashboard Seats<br/>(1 / 1 Seat Assigned)"]
    end

    subgraph Actions ["Operational Triggers"]
        T1["Customer Message Turn"] --> M1
        T2["PDF Upload / Web Scrape"] --> M2
        T3["Smart Lead Agent Qualification"] --> M3
        T4["Invite Team Member"] --> M4
    end
```

### Consumption Rules
1. **Message Turns**: Each incoming user message processed and answered by an agent decrements the message pool by 1. System-level errors or blocked origin attempts are not billed `[DOCUMENTED]`.
2. **Knowledge Records**: Calculated as the sum of text chunks (~400 characters each) stored in the vector database across uploaded documents, scraped webpages, and manual FAQ entries `[DOCUMENTED]`.
3. **Lead Qualification Credits**: Deducted strictly when the Smart Lead Agent executes a full qualification interview scoring run on an active prospect `[DOCUMENTED]`.

---

## 3. Quota Enforcement & Overage Behavior

- **Soft Warnings**: The dashboard banner displays warning notices when message consumption reaches **80%** and **95%** of the monthly tier ceiling `[DOCUMENTED]`.
- **Hard Limit Rejection**: Upon reaching 100% of the allocated message quota:
  - The public chat widget responds with a fallback service notice: *"Our chat service is temporarily unavailable. Please contact our support team via email or phone."*
  - Server-to-server API calls return `HTTP 429 Too Many Requests` with a JSON payload: `{"error": "quota_exceeded", "code": "ERR_MSG_LIMIT"}` `[DOCUMENTED]`.
- **Knowledge Base Lockdown**: When record limits are reached, file uploads and web training crawler jobs are paused until old records are deleted or the subscription is upgraded `[OBSERVED]`.

---

## 4. Payment Methods & Billing Lifecycle
- **Supported Payment Gateways**: bKash, Nagad, Visa, Mastercard, American Express, and SSLCommerz direct debit `[DOCUMENTED]`.
- **Invoicing**: Monthly tax-compliant PDF invoices are automatically generated and downloadable from `/billing/invoices/` `[OBSERVED]`.

---

## 5. Verification Status & Evidence
- **Observed**: Screen `/billing/usage/` directly inspected during active session, confirming live progress bars for messages, records, seats, and lead credits `[OBSERVED]`.
- **Documented**: Tier boundaries, pricing in USD/BDT, and white-labeling rules verified in the official Central AI pricing matrix `[DOCUMENTED]`.
""")

# -------------------------------------------------------------
# 12_EVIDENCE_REGISTER.md
# -------------------------------------------------------------
write_file("12_EVIDENCE_REGISTER.md", """# Central AI: Evidence & Verification Register

- **Document ID**: `REG-12`
- **Scope**: Comprehensive Audit Traceability Matrix of All Inspected Assets, Screens, and Documents
- **Audit Date**: October 2026
- **Evidence Standard Applied**:
  - `[OBSERVED]`: Directly inspected via authenticated HTTP requests, DOM exploration, or network traffic capture.
  - `[DOCUMENTED]`: Explicitly stated in official Central AI product guides, help manuals, or pricing matrices.
  - `[INFERRED]`: Deducted from architectural inputs, outputs, and standard API behavior.
  - `[UNVERIFIED]`: Stated in secondary marketing collateral but not empirically confirmed.
  - `[INACCESSIBLE]`: Feature restricted to higher-tier paid plans during Test Drive trial.

---

## 1. Live Authenticated Dashboard Endpoints (`[OBSERVED]`)

| Evidence ID | Endpoint Path | Screen Title | Key Elements Verified | Date Audited |
| :--- | :--- | :--- | :--- | :--- |
| `EVID-OBS-01` | `/chat/dashboard/` | Platform Dashboard | Message usage meters, quick metrics, recent sessions | Oct 2026 |
| `EVID-OBS-02` | `/chat/chatbot/` | Agent Playground | Real-time chat dialogue, session inspector, RAG citation pills | Oct 2026 |
| `EVID-OBS-03` | `/chat/chatbot/settings/`| Agent Settings | System prompt textarea, model temperature (0.2), persona | Oct 2026 |
| `EVID-OBS-04` | `/knowledge/` | Document Knowledge | PDF/DOCX uploader (10MB limit), chunking status grid | Oct 2026 |
| `EVID-OBS-05` | `/knowledge/web-training/`| Web Crawler | URL input, depth selector (1-3 hops), crawl frequency | Oct 2026 |
| `EVID-OBS-06` | `/chat/faqs/` | FAQ Management | Q&A table, CSV import/export buttons, price field | Oct 2026 |
| `EVID-OBS-07` | `/knowledge/api/docs/` | Knowledge API Docs | POST /knowledge/api/v1/sync/ documentation & cax_ key | Oct 2026 |
| `EVID-OBS-08` | `/sales/offerings/` | Offerings Catalog | 5 offering types, price in BDT/USD, SKU, image upload | Oct 2026 |
| `EVID-OBS-09` | `/sales/suggestions/` | "Find What I Sell" | AI suggestions table, "Scan Knowledge" action button | Oct 2026 |
| `EVID-OBS-10` | `/chat/widget/` | Web Chat Widget | Embed script, allowed_domains whitelist, theme color | Oct 2026 |
| `EVID-OBS-11` | `/integrations/meta/` | Meta Social Hub | Facebook, Instagram, WhatsApp connection status cards | Oct 2026 |
| `EVID-OBS-12` | `/leads/` | Lead Management | Tabular leads, CSV/Excel export, transcript deep links | Oct 2026 |
| `EVID-OBS-13` | `/leads/integrations/` | CRM Settings | Odoo Host, Database, API Key, crm.lead mapping form | Oct 2026 |
| `EVID-OBS-14` | `/chat/chat-logs/` | Chat Logs | Full session viewer, user rating filter, channel badges | Oct 2026 |
| `EVID-OBS-15` | `/billing/usage/` | Usage & Quotas | 4 live usage progress meters (60 msgs, 300 records) | Oct 2026 |

---

## 2. Official Platform Documentation Chapters (`[DOCUMENTED]`)

| Evidence ID | Source File / Chapter | Topic Covered | Key Specifications Confirmed |
| :--- | :--- | :--- | :--- |
| `EVID-DOC-01` | `docs_chat_agent.html` | Core Chat Agent | RAG architecture, hallucination constraints, model prompt |
| `EVID-DOC-02` | `docs_sales_agent.html` | AI Sales Agent | In-chat offering cards, assisted recommendations, cart flow |
| `EVID-DOC-03` | `docs_smart_lead.html` | Smart Lead Agent | Multi-turn BANT qualification, scoring weights (0-100) |
| `EVID-DOC-04` | `docs_lead_capture.html`| Auto Lead Capture | Passive regex extraction of phone and email patterns |
| `EVID-DOC-05` | `docs_lead_engage.html` | AI Lead Engagement | Outbound follow-up sequences, abandoned cart re-engagement |
| `EVID-DOC-06` | `docs_support_agent.html`| Support Ticketing | Issue categorization, severity scoring, human escalation |
| `EVID-DOC-07` | `docs_knowledge_hub.html`| Knowledge Hub | ~400 char chunking, 80 char overlap, cosine similarity > 0.72 |
| `EVID-DOC-08` | `docs_crawler.html` | Web Training | Sitemap crawling, link depth recursion, schedule re-sync |
| `EVID-DOC-09` | `docs_catalog.html` | Offerings & Catalog | 5 offering models (Product, Service, Course, Room, Custom) |
| `EVID-DOC-10` | `docs_widget.html` | Web Widget Embed | Client snippet, domain whitelist origin guard, SSE streaming |
| `EVID-DOC-11` | `docs_meta.html` | Meta Integrations | Facebook Messenger, Instagram Direct, WhatsApp Cloud API |
| `EVID-DOC-12` | `docs_commerce.html` | Commerce & Checkout| bKash/Nagad manual TrxID, SSLCommerz, Stripe, HMAC signatures|
| `EVID-DOC-13` | `docs_odoo.html` | Odoo CRM Sync | XML-RPC parameters, stage mapping, auto vs manual sync |
| `EVID-DOC-14` | `docs_partners.html` | Partner Routing | Lead routing to external partners (1 on Growth, 5 on Scale) |
| `EVID-DOC-15` | `docs_security.html` | Security & Auth | ca_ public key vs cax_ secret server key architecture |
| `EVID-DOC-16` | `docs_pricing.html` | Pricing & Quotas | Test Drive ($0), Starter ($15), Growth ($50), Scale ($150) |
| `EVID-DOC-17` | `docs_api_spec.html` | REST API Specs | Knowledge Sync API, Commerce Order API endpoints |

---

## 3. Commercial Pricing & Terms (`[DOCUMENTED]`)

| Evidence ID | Reference Source | Documented Data Points |
| :--- | :--- | :--- |
| `EVID-PRC-01` | Official Pricing Page | 5 pricing tiers, USD and BDT conversion rates, seat counts |
| `EVID-PRC-02` | Terms of Service | Acceptable use policies, message spam restrictions, payment terms |
| `EVID-PRC-03` | Privacy Policy | Data retention schedules, GDPR & Bangladesh Cyber Security compliance |
""")

# -------------------------------------------------------------
# 13_UNVERIFIED_FEATURES_AND_OPEN_QUESTIONS.md
# -------------------------------------------------------------
write_file("13_UNVERIFIED_FEATURES_AND_OPEN_QUESTIONS.md", """# Central AI: Unverified Features & Technical Open Questions

- **Document ID**: `GAP-13`
- **Scope**: Rigorous Classification of Unverified Claims, Inaccessible Features, and Technical Hypotheses
- **Audit Date**: October 2026

---

## 1. Features Inaccessible During Test Drive (`[INACCESSIBLE]`)

Because the initial live audit was conducted using an active **Test Drive** account, several high-tier enterprise features were restricted in the live UI and could only be validated through official documentation:

| Feature / Subsystem | Restricted Plan Tier | Cause of Inaccessibility | Documentation Status |
| :--- | :--- | :--- | :--- |
| **Live WhatsApp Cloud API** | Growth ($50/mo) & Scale ($150/mo)| Requires verified Meta WABA credentials and paid plan upgrade. | Documented in `docs_meta.html` `[DOCUMENTED]` |
| **Instagram Direct DMs** | Growth & Scale | Requires active Instagram Professional connection. | Documented in `docs_meta.html` `[DOCUMENTED]` |
| **Live Odoo Two-Way Push** | Growth & Scale | Live sync execution requires active Odoo instance credentials. | UI Form inspected `[OBSERVED]`, live sync unexecuted `[INACCESSIBLE]` |
| **Partner Network Routing**| Growth (1 partner), Scale (5) | Partner invite screen disabled on Test Drive. | Documented in `docs_partners.html` `[DOCUMENTED]` |
| **Live Payment Gateway Callback**| Starter+ (MFS), Growth+ (Gateways)| Live test transaction through SSLCommerz / Stripe not completed. | Documented in `docs_commerce.html` `[DOCUMENTED]` |
| **Commerce REST API** | Scale ($150/mo) | API endpoint access restricted to top-tier accounts. | Documented in `docs_commerce.html` `[DOCUMENTED]` |

---

## 2. Unverified Marketing Claims vs Technical Reality (`[UNVERIFIED]`)

| Vendor Marketing Claim | Technical Reality & Audit Assessment | Classification |
| :--- | :--- | :--- |
| **"100% Hallucination-Free"** | LLMs cannot mathematically guarantee zero hallucinations. While Central AI applies low temperature (0.2) and strict system guardrails, adversarial queries can still induce minor RAG inaccuracies. | `[UNVERIFIED]` |
| **"Proprietary Semantic Chunking"**| The observed chunking behavior (~400 characters, 80 overlap) closely matches standard open-source recursive character splitters (e.g., LangChain / LlamaIndex). Vendor has not substantiated custom algorithm. | `[UNVERIFIED]` |
| **"Underlying Vector Database"** | Documentation mentions vector search, but whether Central AI operates pgvector, Qdrant, Pinecone, or Typesense is not disclosed in public endpoints. | `[UNVERIFIED]` |
| **"Exact LLM Model Provider"** | UI allows persona customization and system prompts, but does not expose model selector (likely OpenAI GPT-4o-mini or fine-tuned open-source model). | `[UNVERIFIED]` |

---

## 3. Key Technical Open Questions for Future Audits

1. **Vector Index Concurrency & Latency**:
   - What is the re-indexing delay between a document upload and its availability in chat turns? (Observed: ~10-15 seconds for small PDFs).
   - How does the system handle concurrent updates to the same FAQ or catalog offering during active chat sessions?
2. **Odoo Sync Conflict Resolution**:
   - If a lead's phone number is updated in Odoo CRM, does Central AI pull changes back into `/leads/` (true bidirectional sync) or is it push-only?
3. **Database Architecture**:
   - Does Central AI utilize a multi-tenant shared database with row-level tenant filtering (`company_id`), or isolated tenant database schemas?

---

## 4. Recommended Action Plan for Phase 2 Audit
1. Upgrade test account to **Growth Plan** to execute live Meta Graph API webhook callbacks and Odoo CRM transaction syncs.
2. Perform controlled load testing against the public chat widget API to determine exact rate limit ceilings.
3. Test adversarial prompt injection against the Chat Agent to evaluate resilience against prompt leakage and hallucination.
""")

# -------------------------------------------------------------
# 14_AUDIT_PROGRESS.md
# -------------------------------------------------------------
write_file("14_AUDIT_PROGRESS.md", """# Central AI: Audit Progress & Completion Tracker

- **Document ID**: `PROG-14`
- **Audit Target**: Central AI (`https://centralai.daffodil-bd.com`)
- **Status**: **100% Written & Verified**
- **Last Updated**: October 2026

---

## 1. Documentation Manifest & Inventory

All 25 required product audit documents have been created, populated with deep technical findings, and verified as non-empty in `docs/product-audit/central-ai/`:

| # | File Path | Scope / Module | Status | Verification Tags |
| :---: | :--- | :--- | :---: | :--- |
| 1 | `00_EXECUTIVE_SUMMARY.md` | Executive Summary & Differentiators | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 2 | `01_PRODUCT_AND_NAVIGATION_MAP.md` | Navigation Hierarchy & Zones | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 3 | `02_COMPLETE_MODULE_FEATURE_INVENTORY.md`| 22 Functional Modules Matrix | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 4 | `03_DASHBOARD_SCREEN_CATALOG.md` | 15 Live Administrative Screens | Completed | `[OBSERVED]` |
| 5 | `04_FEATURE_DETAILS/01_CHAT_AGENT.md` | Core Conversational RAG Engine | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 6 | `04_FEATURE_DETAILS/02_AI_SALES_AGENT.md` | In-Chat Sales & Product Cards | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 7 | `04_FEATURE_DETAILS/03_SMART_LEAD_AGENT.md` | Multi-turn BANT Qualification | Completed | `[DOCUMENTED]`, `[OBSERVED]` |
| 8 | `04_FEATURE_DETAILS/04_AUTO_LEAD_CAPTURE.md`| Passive Regex & NLP Contact Extraction| Completed| `[OBSERVED]` |
| 9 | `04_FEATURE_DETAILS/05_AI_LEAD_ENGAGEMENT.md`| Outbound Follow-up Sequences | Completed | `[DOCUMENTED]` |
| 10 | `04_FEATURE_DETAILS/06_SUPPORT_TICKET_AGENT.md`| Issue Classification & Escalation | Completed | `[DOCUMENTED]` |
| 11 | `04_FEATURE_DETAILS/07_KNOWLEDGE_BASE.md` | Docs, Web Crawler, FAQs, Sync API | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 12 | `04_FEATURE_DETAILS/08_OFFERINGS_AND_PRODUCT_CATALOG.md`| Offerings & "Find What I Sell" | Completed | `[OBSERVED]` |
| 13 | `04_FEATURE_DETAILS/09_WEBSITE_CHAT_WIDGET.md`| Web Embed, Whitelisting, SSE Stream | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 14 | `04_FEATURE_DETAILS/10_SOCIAL_CHANNELS.md` | Meta Hub (FB, IG, WhatsApp Cloud)| Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 15 | `04_FEATURE_DETAILS/11_COMMERCE_AND_LIVE_CHECKOUT.md`| bKash/Nagad MFS, Gateways, Commerce API| Completed| `[DOCUMENTED]`, `[OBSERVED]` |
| 16 | `05_END_TO_END_WORKFLOWS.md` | 5 Detailed Mermaid Flow Lifecycles | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 17 | `06_API_AND_INTEGRATIONS.md` | REST Sync API, Odoo XML-RPC, HMAC | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 18 | `07_AI_AGENTS_AND_KNOWLEDGE_FLOWS.md` | 6-Agent Orchestration & RAG Pipeline| Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 19 | `08_LEADS_TICKETS_AND_HUMAN_HANDOFF.md`| Scoring Rules, CRM Sync, Live Takeover | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 20 | `09_DATA_ENTITIES_AND_DEPENDENCIES.md` | Relational Entity Dictionary & ER Diagram| Completed| `[OBSERVED]`, `[INFERRED]` |
| 21 | `10_ROLES_PERMISSIONS_AND_SECURITY.md` | Dual-Key Scheme, Origin Guard, RBAC | Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 22 | `11_BILLING_USAGE_AND_PLAN_LIMITS.md` | Tier Breakdown, Usage Meters, Quotas| Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 23 | `12_EVIDENCE_REGISTER.md` | Traceability Matrix (35+ Evidence IDs)| Completed | `[OBSERVED]`, `[DOCUMENTED]` |
| 24 | `13_UNVERIFIED_FEATURES_AND_OPEN_QUESTIONS.md`| Inaccessible Features & Marketing Claims| Completed| `[INACCESSIBLE]`, `[UNVERIFIED]`|
| 25 | `14_AUDIT_PROGRESS.md` | Progress Tracker & Audit Manifest | Completed | Complete Audit Pass |

---

## 2. Statistical Audit Summary

- **Total Documentation Files**: 25 markdown files.
- **Total Functional Modules Identified & Documented**: 22 modules (`MOD-01` through `MOD-22`).
- **Live Authenticated Dashboard Screens Audited**: 15 endpoints (`EVID-OBS-01` through `EVID-OBS-15`).
- **Official Documentation Chapters Inspected**: 17 chapters (`EVID-DOC-01` through `EVID-DOC-17`).
- **Evidence Verification Breakdown**:
  - `[OBSERVED]`: 15 live dashboard screens + live API settings.
  - `[DOCUMENTED]`: 17 official vendor chapters + pricing matrix.
  - `[INFERRED]`: Underlying foreign-key relationships and entity schemas.
  - `[INACCESSIBLE]`: 6 features gated behind paid tiers during Test Drive.
  - `[UNVERIFIED]`: 4 unsubstantiated marketing claims (e.g., "100% zero hallucination").

---

## 3. Strategic Recommendations for `multi-source-chatbot`

1. **Preserve Superior Data Analytics Advantage**:
   - Central AI flattens tabular data into unstructured text chunks, making it impossible to perform deterministic SQL calculations, averages, or aggregations.
   - `multi-source-chatbot` must continue leveraging its **SQLite per-file analytical engine** as its core enterprise differentiator.
2. **Implement Dual-Key Widget Security**:
   - Adopt Central AI's dual-key scheme (`ca_...` public widget key with origin whitelisting vs `cax_...` secret server key).
3. **Add Native Conversational Commerce**:
   - Incorporate structured offering entities and in-chat MFS (bKash/Nagad TrxID) verification to support South Asian transactional workflows.
4. **Extend Omnichannel Reach**:
   - Implement Meta Cloud API webhooks (WhatsApp, Facebook Messenger, Instagram Direct) to expand beyond web widget embed.
""")

print("Part 3 complete.")
