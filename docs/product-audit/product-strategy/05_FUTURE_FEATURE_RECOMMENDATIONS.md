# Future Feature Recommendations & Functional Specifications

- **Document ID**: `STRAT-05`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Benchmark Target**: Central AI (`https://centralai.daffodil-bd.com/`)
- **Status**: Structured Recommendation Framework

---

## 1. Executive Summary

This document presents targeted, product-level feature recommendations grouped across 10 functional pillars. Rather than indiscriminate feature expansion, each proposal is anchored in:
1. Solving a verified merchant or end-user operational problem.
2. Closing a critical commercial gap identified in the Central AI comparison.
3. Amplifying our established technical differentiators (Analytics, Memory, Action Safety).

---

## 2. Recommendations Across 10 Functional Pillars

```
====================================================================================================
PILLAR A: Core Messaging and Customer Support
====================================================================================================
```

### Recommendation A.1: Streaming Response & Citation Indicator in Customer UI
- **Problem**: When LLMs generate responses, long generation times without visual feedback create user drop-off. Lack of clickable citations makes users doubt the authenticity of answers.
- **Expected Customer Value**: 40% reduction in perceived latency and increased trust in AI-provided policy answers.
- **Current State & Gap**: Backend supports streaming (`ChatSimulatorController.php`), but public client rendering is not packaged for production.
- **Proposed Behavior & Workflow**:
  1. As the AI generates tokens, they stream word-by-word into the chat bubble.
  2. If a response is derived from a knowledge base document, a subtle badge `[Source: Return Policy.pdf, p. 2]` appears beneath the message. Clicking the badge displays the exact passage in a preview modal.
- **Dependencies**: `FEAT-WGT-01` (Widget bundle).
- **Complexity**: `LOW`
- **Priority & Rationale**: `P1` — Core table-stakes UX for conversational AI.
- **Risks & Open Questions**: Verifying that streaming works seamlessly over mobile Safari and low-bandwidth connections.
- **Product Acceptance Criteria**:
  - Response starts rendering within 800ms of user submission.
  - Source citations display document name and page/section number.
  - Clicking citation displays the grounded source passage.

---

```
====================================================================================================
PILLAR B: AI Lead Intelligence and CRM
====================================================================================================
```

### Recommendation B.1: In-Conversation Contextual Lead Qualification (`FEAT-LEAD-01`)
- **Problem**: Support conversations frequently contain buying signals, but contact information (phone number, delivery location) is lost unless an agent manually copies it.
- **Expected Customer Value**: Automatic lead capture without annoying pre-chat barrier forms, increasing qualified lead capture by 35%.
- **Current State & Gap**: Drivers capture sender channel IDs, but no conversational entity extractor parses phone numbers or intent scores.
- **Proposed Behavior & Workflow**:
  1. When a user asks about pricing, delivery, or availability, the AI naturally inquires: *"I can check stock and reserve this for you. May I have your WhatsApp number or phone number?"*.
  2. When the user provides a phone number (e.g. `017XXXXXXXX`), the system extracts it, validates Bangladeshi number formats, creates a `Lead` record, and tags intent as `High`.
  3. The lead immediately appears in the merchant's dashboard lead queue.
- **Dependencies**: `FEAT-CHAT-01`.
- **Complexity**: `MEDIUM`
- **Priority & Rationale**: `P1` — Direct revenue driver for merchants.
- **Risks & Open Questions**: Balancing aggressive contact collection with helpful customer support.
- **Product Acceptance Criteria**:
  - Automatically identifies and normalizes valid Bangladeshi mobile numbers (regex: `+8801[3-9]\d{8}`).
  - Creates a lead record with conversation transcript snippet and intent score (Low/Medium/High).
  - Emits real-time notification to the merchant dashboard.

---

```
====================================================================================================
PILLAR C: Product Catalog and Sales Assistance
====================================================================================================
```

### Recommendation C.1: Lightweight Product Catalog & In-Chat Recommendation Cards (`FEAT-CAT-01`)
- **Problem**: Support bots cannot assist in closing sales because they lack product awareness, inventory states, and pricing variants.
- **Expected Customer Value**: Enables the chatbot to function as an 24/7 sales assistant, boosting conversion on product inquiries.
- **Current State & Gap**: No product catalog exists in our codebase.
- **Proposed Behavior & Workflow**:
  1. Merchants create products in a simple dashboard form: Title, Price (BDT), Category, Description, Images, and In-Stock toggle.
  2. Products are indexed into Typesense.
  3. When a buyer asks: *"Do you have any casual cotton shirts under 1,500 BDT?"*, the AI returns interactive carousel cards showing image, title, price, and a "Buy Now" button.
- **Dependencies**: Typesense Search Engine.
- **Complexity**: `MEDIUM`
- **Priority & Rationale**: `P2` — Transforms the platform from a cost-center (support) to a revenue-generator (sales).
- **Risks & Open Questions**: Need simple bulk CSV product import to avoid high onboarding friction.
- **Product Acceptance Criteria**:
  - Merchant can add, edit, and archive products with multiple images and variants.
  - Chat engine renders interactive product cards with image, price, and stock status.
  - Supports natural language budget and category filtering.

---

```
====================================================================================================
PILLAR D: Business Analytics and Reporting
====================================================================================================
```

### Recommendation D.1: Interactive Data Visualization for Spreadsheet Q&A (`FEAT-ANL-01`)
- **Problem**: Our virtual SQLite engine produces accurate numeric answers, but users digest insights much faster through visual charts.
- **Expected Customer Value**: Differentiates our SaaS as an executive decision-making tool, not just a customer chatbot.
- **Current State & Gap**: Ingestion and SQL execution are fully tested (`LiveSqlAnalyticsTest.php`), but output is purely textual Markdown tables.
- **Proposed Behavior & Workflow**:
  1. When a user asks: *"Show monthly sales breakdown by region for 2026"*, the SQL engine returns the aggregated dataset.
  2. The LLM determines the optimal visualization type (Bar Chart, Line Chart, Pie Chart).
  3. The chat UI renders a responsive, interactive Chart.js visualization with a toggle to view the raw data table or export to CSV.
- **Dependencies**: `python-ai-service` analytics engine.
- **Complexity**: `MEDIUM`
- **Priority & Rationale**: `P1` — Massively amplifies our core architectural strength.
- **Risks & Open Questions**: Ensuring generated charts handle zero or missing values gracefully.
- **Product Acceptance Criteria**:
  - Automatically renders bar, line, and pie charts for multi-row SQL results.
  - Allows exporting chart as PNG image and underlying data as CSV.
  - Mobile-responsive rendering in dashboard simulator and webchat.

---

```
====================================================================================================
PILLAR E: Knowledge Ingestion and Management
====================================================================================================
```

### Recommendation E.1: Automated Web Crawler & Sitemap Ingestion (`FEAT-KNOW-02`)
- **Problem**: Uploading PDFs and Word documents is tedious for merchants who already have complete FAQs, policies, and product guides on their websites.
- **Expected Customer Value**: Reduces onboarding time from days to minutes.
- **Current State & Gap**: Central AI offers web training (`/knowledge/web-training/`); our SaaS currently only supports file uploads.
- **Proposed Behavior & Workflow**:
  1. Merchant inputs their website URL (e.g. `https://mybrand.com/faq`).
  2. Background queue crawls up to 50 child pages, strips HTML navigation boilerplate, and extracts clean markdown text.
  3. Content is automatically chunked, embedded, and indexed into Typesense.
- **Dependencies**: `FEAT-KNOW-01` (Typesense pipeline).
- **Complexity**: `MEDIUM`
- **Priority & Rationale**: `P2` — Onboarding accelerator.
- **Risks & Open Questions**: Handling JavaScript-rendered websites (SPA) without high headless browser infrastructure costs.
- **Product Acceptance Criteria**:
  - Crawls public pages respecting `robots.txt` up to a configurable depth limit.
  - Deduplicates identical pages and strips header/footer boilerplate.
  - Automatically updates knowledge base when user requests a re-crawl.

---

```
====================================================================================================
PILLAR F: Social Channel Connections and Integrations
====================================================================================================
```

### Recommendation F.1: Self-Serve Meta Omnichannel Onboarding UI (`FEAT-SOC-01`)
- **Problem**: Our backend has robust drivers for Facebook, WhatsApp, and Instagram (`tests/Feature/ReceiveMessengerWebhookJobTest.php`), but merchants cannot connect their pages without developer intervention.
- **Expected Customer Value**: Eliminates manual provisioning, allowing self-serve tenant onboarding in under 3 minutes.
- **Current State & Gap**: Driver code exists; visual connection cards and OAuth token exchange flows are missing.
- **Proposed Behavior & Workflow**:
  1. Merchant navigates to `/channels` and clicks "Connect Facebook Page" or "Connect WhatsApp".
  2. A Facebook OAuth popup allows the merchant to select their business page and grant messaging permissions.
  3. The system exchanges the short-lived user token for a long-lived Page Access Token, encrypts it in MySQL (`ChannelAccount.php`), and auto-subscribes the page to our webhook endpoint.
- **Dependencies**: Meta Developer App Review, `OUR-SEC-03` (Encrypted Token Casting).
- **Complexity**: `MEDIUM`
- **Priority & Rationale**: `P1` — Essential for product-market fit in Bangladesh where social commerce is dominant.
- **Risks & Open Questions**: Meta App Review approval timelines and requirements.
- **Product Acceptance Criteria**:
  - 1-click OAuth connection for Facebook Pages.
  - WhatsApp Cloud API connection card allowing input of Phone Number ID and WABA System User Token.
  - Token refresh status indicator showing token health and expiration dates.

---

```
====================================================================================================
PILLAR G: Widget and Public API
====================================================================================================
```

### Recommendation G.1: Client-Side Embeddable Web Chat Widget Bundle (`FEAT-WGT-01`)
- **Problem**: Our platform currently operates solely inside an authenticated backend dashboard (`/simulator`). Merchants cannot embed the chatbot on their storefronts.
- **Expected Customer Value**: Unlocks the core delivery channel for website customer support.
- **Current State & Gap**: Total commercial delivery blocker.
- **Proposed Behavior & Workflow**:
  1. Merchant configures widget branding in `/settings/widget` (primary color, bot name, avatar image, welcome message).
  2. The dashboard generates a 2-line HTML embed snippet:
     ```html
     <script src="https://cdn.ourplatform.com/widget.js" data-key="pk_live_..." async></script>
     ```
  3. The script loads a floating launcher icon on the merchant's site, opens a responsive glassmorphic chat window, and establishes a secure WebSocket/HTTP streaming connection.
- **Dependencies**: `FEAT-SEC-01` (Dual API Keys & Origin Whitelisting).
- **Complexity**: `MEDIUM`
- **Priority & Rationale**: `P0` — **Immediate top priority blocker**.
- **Risks & Open Questions**: Ensuring zero CSS conflicts with host websites.
- **Product Acceptance Criteria**:
  - Lightweight script bundle (< 60 KB gzipped) with zero host CSS leakage.
  - Fully mobile-responsive modal drawer on screens < 640px.
  - Rejects connections if host origin does not match tenant's Allowed Domains list.

---

```
====================================================================================================
PILLAR H: Human Handoff and Support Ticketing
====================================================================================================
```

### Recommendation H.1: Dedicated Support Ticket Queue & SLA Timer (`FEAT-TKT-01`)
- **Problem**: When a conversation requires human intervention, standard chat threads get buried in history without clear ownership or resolution deadlines.
- **Expected Customer Value**: Brings accountability to support operations, ensuring customer issues are resolved within defined SLAs.
- **Current State & Gap**: Conversation status tagging exists, but formal ticket entities and SLA timers are absent.
- **Proposed Behavior & Workflow**:
  1. When AI triggers handoff (or customer clicks "Talk to Human"), a `Ticket` record is spawned with priority (`Urgent`, `Normal`, `Low`).
  2. The ticket appears in the `/support/tickets` queue.
  3. Agents can assign tickets to themselves, leave internal notes, and track time-to-first-response and resolution SLA timers.
- **Dependencies**: `FEAT-CHAT-01`.
- **Complexity**: `MEDIUM`
- **Priority & Rationale**: `P1` — Essential for operational customer support teams.
- **Risks & Open Questions**: Seamlessly transitioning back to AI after human marks ticket resolved.
- **Product Acceptance Criteria**:
  - Filterable ticket list: Status (Open, Pending, Resolved), Priority, and Assignee.
  - Internal staff notes that are visible to agents but hidden from the customer.
  - SLA tracking measuring first response time and resolution duration.

---

```
====================================================================================================
PILLAR I: SaaS Administration, Usage Metering, and Billing
====================================================================================================
```

### Recommendation I.1: Tenant-Level Usage Quotas & Self-Serve Subscription Billing (`FEAT-BILL-01`, `FEAT-MET-01`)
- **Problem**: Currently, any tenant can generate unlimited LLM tokens, creating financial risk of runaway API costs. We cannot charge customers automatically.
- **Expected Customer Value**: Protects platform profitability and enables automated recurring SaaS revenue.
- **Current State & Gap**: Token telemetry exists per turn, but no monthly roll-ups, plan limits, or payment gateways are built.
- **Proposed Behavior & Workflow**:
  1. Define 3 subscription plans: Starter ($19/mo, 3k messages), Growth ($49/mo, 15k messages), Business ($99/mo, 50k messages).
  2. Middleware checks current month token/message consumption before dispatching LLM calls.
  3. When quota reaches 90%, send warning email. When quota reaches 100%, bot switches to human-only fallback until upgraded.
  4. Integrate payment gateway (Stripe / LemonSqueezy for global, bKash / SSLCommerz for local) for instant 1-click tier upgrades.
- **Dependencies**: Payment Gateway Merchant Account.
- **Complexity**: `HIGH`
- **Priority & Rationale**: `P1` — Essential commercial readiness milestone.
- **Risks & Open Questions**: Selecting the right local payment gateway for automated recurring subscription rebilling in Bangladesh.
- **Product Acceptance Criteria**:
  - Real-time monthly usage progress bar in dashboard.
  - Automated quota enforcement blocking LLM execution upon limit exhaustion.
  - Automated PDF invoice generation upon successful billing renewal.

---

```
====================================================================================================
PILLAR J: Security, Privacy, and Operational Reliability
====================================================================================================
```

### Recommendation J.1: Comprehensive Tenant Security Hardening (`OUR-SEC-01`, `02`, `03`, `05`, `06`)
- **Problem**: As validated in [`06_CRITICAL_FINDINGS_VALIDATION.md`](../our-saas/06_CRITICAL_FINDINGS_VALIDATION.md), critical vulnerabilities exist: unauthenticated microservice endpoints, plaintext channel tokens, unaudited administrative tenant switching, and unbounded spreadsheet ingestion.
- **Expected Customer Value**: Guarantees multi-tenant confidentiality, compliance, and platform stability.
- **Current State & Gap**: Validated with concrete code traces; remediation steps defined.
- **Proposed Behavior & Workflow**:
  1. Attach `verify_api_key` dependency and HMAC request signing to `python-ai-service/app/main.py:141`.
  2. Cast `access_token` and `refresh_token` to `'encrypted'` in `ChannelAccount.php`.
  3. Add structured audit logging to `ChatSimulatorController.php:418-425` when superadmin switches workspace.
  4. Enforce `MAX_SHEETS = 10` and `MAX_ROWS_PER_SHEET = 100_000` in `excel_engine.py`.
- **Dependencies**: Core application codebases.
- **Complexity**: `MEDIUM`
- **Priority & Rationale**: `P0` — **Absolute non-negotiable security prerequisite**.
- **Risks & Open Questions**: Ensuring existing tests pass after applying encryption cast.
- **Product Acceptance Criteria**:
  - Microservice rejects calls without valid HMAC signature.
  - MySQL database stores encrypted tokens (`eyJpdiI6...`).
  - Administrative workspace switching creates immutable audit record with actor ID, target workspace ID, IP, and timestamp.
  - Python worker terminates spreadsheet processing exceeding 10 sheets or 100,000 rows.
