# Prioritized Product Strategic Roadmap

- **Document ID**: `STRAT-06`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Benchmark Target**: Central AI (`https://centralai.daffodil-bd.com/`)
- **Status**: Strategic Recommendation Only (Not an Authorization to Build)

---

## 1. Roadmap Phasing Strategy

This roadmap organizes future product initiatives into 4 disciplined, value-maximizing phases designed to establish security, deploy public delivery channels, achieve commercial readiness, and expand our defensible differentiators:

```
+-------------------------------------------------------------------------------------------------------+
| PHASE 0: ASSUMPTION VALIDATION & ARCHITECTURAL HARDENING                                              |
| Focus: Resolving critical security vulnerabilities, tenant isolation, and customer discovery          |
+-------------------------------------------------------------------------------------------------------+
                                                   |
                                                   v
+-------------------------------------------------------------------------------------------------------+
| PHASE 1: CLIENT-FACING DELIVERY & VALUE AMPLIFICATION                                                 |
| Focus: Public embeddable web chat widget, streaming UX, citation UI, and spreadsheet visualization    |
+-------------------------------------------------------------------------------------------------------+
                                                   |
                                                   v
+-------------------------------------------------------------------------------------------------------+
| PHASE 2: COMMERCIAL READINESS & OMNICHANNEL EXPANSION                                                 |
| Focus: Meta self-serve onboarding, subscription billing, token quota guards, and support ticketing   |
+-------------------------------------------------------------------------------------------------------+
                                                   |
                                                   v
+-------------------------------------------------------------------------------------------------------+
| PHASE 3: COMMERCE EXPANSION & ADVANCED INTELLIGENCE                                                   |
| Focus: In-chat product catalog, dynamic checkout link generation, and web sitemap crawling            |
+-------------------------------------------------------------------------------------------------------+
```

---

## 2. Priority Level Definitions

- **`P0` (Critical Prerequisite)**: Verified security vulnerability, data leakage risk, or hard commercial blocker that prevents public launch. Must be completed before exposing the application to external traffic.
- **`P1` (High Customer Value / Near-Term)**: High-impact features that directly drive activation, retention, or core customer problem resolution.
- **`P2` (Dependent / Mid-Term)**: Significant value-add features that logically depend on Phase 1 or 2 infrastructure.
- **`P3` (Speculative / Optional / Long-Term)**: Lower-urgency opportunities requiring further customer validation before commitment.

---

## 3. Detailed Phased Roadmap Specifications

```
====================================================================================================
PHASE 0: Validation & Architectural Hardening (Immediate Prerequisite)
====================================================================================================
```

### Item 0.1: Secure Python Microservice Endpoints (`OUR-SEC-02`)
- **Priority**: `P0`
- **Why It Matters**: Port 8001 exposes `/analytics/query` without API-key or tenant signature verification, allowing unauthorized callers to query arbitrary tenant databases.
- **Expected Outcome**: Cryptographic tenant binding via HMAC-SHA256 request signing between Laravel and FastAPI microservices.
- **Dependencies**: None.
- **Complexity**: `MEDIUM`
- **Success Metric**: 100% of unsigned microservice requests rejected with `401 Unauthorized`.
- **Pre-Implementation Validation**: Verify internal network firewall rules and test signing latency (< 5ms).

### Item 0.2: Encrypt Channel Account Credentials at Rest (`OUR-SEC-03`)
- **Priority**: `P0`
- **Why It Matters**: Meta Page tokens and channel credentials in `channel_accounts` are currently stored as plaintext strings in MySQL.
- **Expected Outcome**: Apply Laravel `'encrypted'` and `'encrypted:array'` casting to `$casts` in [`ChannelAccount.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Models/ChannelAccount.php#L23-L26).
- **Dependencies**: None.
- **Complexity**: `LOW`
- **Success Metric**: All saved tokens stored in database in encrypted ciphertext format.
- **Pre-Implementation Validation**: Confirm that existing test suites pass with encrypted attributes.

### Item 0.3: Administrative Tenant Switching Audit Logging (`OUR-SEC-05`)
- **Priority**: `P1`
- **Why It Matters**: Superadmins can switch active workspaces in [`ChatSimulatorController.php`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/app/Http/Controllers/ChatSimulatorController.php#L418-L425) without creating an audit log.
- **Expected Outcome**: Structured audit logging recording actor user ID, target workspace ID, timestamp, and IP address.
- **Dependencies**: None.
- **Complexity**: `LOW`
- **Success Metric**: 100% of workspace context switches logged to security audit log table.
- **Pre-Implementation Validation**: Ensure logging does not impact simulator response latency.

### Item 0.4: Spreadsheet Ingestion Bounds & SQLite Timeouts (`OUR-SEC-06`)
- **Priority**: `P1`
- **Why It Matters**: Python worker lacks sheet count and row count limits, exposing worker to memory exhaustion.
- **Expected Outcome**: Enforce `MAX_SHEETS = 10`, `MAX_ROWS_PER_SHEET = 100_000`, and a 15-second SQLite execution timeout in [`excel_engine.py`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/python-ai-service/app/analytics/excel_engine.py).
- **Dependencies**: None.
- **Complexity**: `LOW`
- **Success Metric**: Ingestion cleanly terminates with user-friendly error when limits are exceeded.
- **Pre-Implementation Validation**: Test with a sample 50-sheet file in test environment.

---

```
====================================================================================================
PHASE 1: Client-Facing Delivery & Core Value Amplification
====================================================================================================
```

### Item 1.1: Public API Key Infrastructure & Origin Whitelisting (`FEAT-SEC-01`)
- **Priority**: `P0`
- **Why It Matters**: Hard prerequisite for deploying a public embeddable chat widget without risking quota theft.
- **Expected Outcome**: Database tables `api_keys` (`pk_live_...` / `sk_live_...`) and `allowed_domains` with CORS origin verification middleware.
- **Dependencies**: Phase 0 security hardening.
- **Complexity**: `MEDIUM`
- **Success Metric**: Requests from unauthorized host origins are blocked with `403 Forbidden`.
- **Pre-Implementation Validation**: Verify that wildcard subdomains (`*.brand.com`) are supported.

### Item 1.2: Client-Side Embeddable Web Chat Widget Bundle (`FEAT-WGT-01`)
- **Priority**: `P0`
- **Why It Matters**: We currently have no customer-facing delivery mechanism for website visitors.
- **Expected Outcome**: Standalone vanilla JavaScript widget bundle (< 60 KB) with customizable branding and mobile drawer.
- **Dependencies**: `FEAT-SEC-01`.
- **Complexity**: `MEDIUM`
- **Success Metric**: Successfully renders and streams chat responses on any standard HTML page within 1.2 seconds of initial page load.
- **Pre-Implementation Validation**: Test embed snippet across WordPress, Shopify, and plain HTML test pages.

### Item 1.3: Streaming Responses & Grounded Source Citation Badges (`FEAT-CHAT-01`)
- **Priority**: `P1`
- **Why It Matters**: Enhances user engagement and builds trust in AI-provided policy answers.
- **Expected Outcome**: Real-time token streaming with clickable citation badges displaying source document passages.
- **Dependencies**: `FEAT-WGT-01`.
- **Complexity**: `LOW`
- **Success Metric**: First token renders in < 800ms; citation click rate > 15% on policy queries.
- **Pre-Implementation Validation**: Verify SSE (Server-Sent Events) streaming stability across mobile Safari.

### Item 1.4: Interactive Data Visualization for Spreadsheet Q&A (`FEAT-ANL-01`)
- **Priority**: `P1`
- **Why It Matters**: Transforms our technical differentiator (SQLite analytics) into a visually stunning, executive-ready capability.
- **Expected Outcome**: Automatically renders responsive Chart.js visualizations (bar, line, pie) directly in chat responses for tabular queries.
- **Dependencies**: `python-ai-service` analytics engine.
- **Complexity**: `MEDIUM`
- **Success Metric**: 90% of aggregate query responses include an accurate interactive chart.
- **Pre-Implementation Validation**: Benchmark LLM chart-type inference accuracy on 30 sample financial queries.

---

```
====================================================================================================
PHASE 2: Commercial Readiness & Omnichannel Expansion
====================================================================================================
```

### Item 2.1: Self-Serve Meta Omnichannel Onboarding UI (`FEAT-SOC-01`)
- **Priority**: `P1`
- **Why It Matters**: Unlocks social commerce for Bangladeshi merchants where Facebook Messenger and WhatsApp represent > 75% of customer interactions.
- **Expected Outcome**: 1-click OAuth connection for Facebook Pages and guided WhatsApp Cloud API setup card.
- **Dependencies**: Meta App Review approval.
- **Complexity**: `MEDIUM`
- **Success Metric**: Merchant connects a live Facebook Page in under 3 minutes with zero manual support.
- **Pre-Implementation Validation**: Complete Meta Developer App Review for `pages_messaging` and `whatsapp_business_messaging`.

### Item 2.2: Tiered Subscription Billing & Token Quota Metering (`FEAT-BILL-01`, `FEAT-MET-01`)
- **Priority**: `P1`
- **Why It Matters**: Protects platform profitability, enforces tenant usage limits, and enables automated SaaS monetization.
- **Expected Outcome**: Subscription management (Starter, Growth, Scale), real-time usage progress bars, automated token limits, and PDF invoice generation.
- **Dependencies**: Payment gateway onboarding (bKash / SSLCommerz / Stripe).
- **Complexity**: `HIGH`
- **Success Metric**: Automated payment collection and 100% enforcement of message caps.
- **Pre-Implementation Validation**: Verify automated subscription rebilling behavior in gateway sandbox.

### Item 2.3: Support Helpdesk & Ticket Lifecycles (`FEAT-TKT-01`)
- **Priority**: `P1`
- **Why It Matters**: Essential for customer support teams to manage complex handoffs, track SLAs, and resolve escalations.
- **Expected Outcome**: Ticket queue interface (`/support/tickets`) with status tracking (Open, In Progress, Resolved), agent assignment, internal notes, and SLA timers.
- **Dependencies**: `FEAT-CHAT-01`.
- **Complexity**: `MEDIUM`
- **Success Metric**: Average First Response Time (FRT) tracked for 100% of escalated conversations.
- **Pre-Implementation Validation**: Conduct workflow reviews with 3 customer support managers.

---

```
====================================================================================================
PHASE 3: Commerce Expansion & Advanced Intelligence
====================================================================================================
```

### Item 3.1: Lightweight Product Catalog & In-Chat Recommendation Cards (`FEAT-CAT-01`, `FEAT-SALES-01`)
- **Priority**: `P2`
- **Why It Matters**: Allows the chatbot to act as an active sales assistant, recommending in-stock items directly in chat.
- **Expected Outcome**: Product CRUD interface in dashboard, Typesense product indexing, and interactive in-chat recommendation carousels.
- **Dependencies**: Typesense search engine.
- **Complexity**: `MEDIUM`
- **Success Metric**: In-chat product click-through rate > 20% on product inquiries.
- **Pre-Implementation Validation**: Verify that product search latency stays under 150ms.

### Item 3.2: In-Chat Live Checkout Link Generation (`FEAT-CHK-01`)
- **Priority**: `P2`
- **Why It Matters**: Closes the transaction directly in the conversation, eliminating shopping cart abandonment.
- **Expected Outcome**: One-click checkout link generator passing assembled cart and amount directly to payment gateways (bKash/Nagad/SSLCommerz).
- **Dependencies**: `FEAT-CAT-01`, Gateway Merchant Account.
- **Complexity**: `MEDIUM`
- **Success Metric**: End-to-end checkout completion in < 60 seconds from chat link click.
- **Pre-Implementation Validation**: Test gateway callback webhook verification and idempotency.

### Item 3.3: Automated Web Crawler & Sitemap Ingestion (`FEAT-KNOW-02`)
- **Priority**: `P2`
- **Why It Matters**: Speeds up customer onboarding by indexing existing website documentation automatically.
- **Expected Outcome**: Asynchronous background crawler that ingests up to 50 child pages from a domain URL.
- **Dependencies**: `FEAT-KNOW-01`.
- **Complexity**: `MEDIUM`
- **Success Metric**: Successfully crawls and indexes a 20-page website in < 3 minutes.
- **Pre-Implementation Validation**: Benchmark HTML boilerplate stripping to prevent indexing navigation headers.

---

## 4. Summary Matrix of Roadmap Initiatives

| Phase | Item ID | Feature Name | Priority | Complexity | Target Outcome | Dependencies |
| :---: | :---: | :--- | :---: | :---: | :--- | :--- |
| **0** | `0.1` | Secure Python Microservice Endpoints | `P0` | `MEDIUM` | HMAC signing on `/analytics/query` | None |
| **0** | `0.2` | Encrypt Channel Credentials at Rest | `P0` | `LOW` | `encrypted` cast on `ChannelAccount` | None |
| **0** | `0.3` | Administrative Workspace Audit Logging | `P1` | `LOW` | Structured audit log for workspace switches | None |
| **0** | `0.4` | Spreadsheet Ingestion Bounds & Timeouts | `P1` | `LOW` | Sheet/row limits and query timeout in Python | None |
| **1** | `1.1` | Public API Key & Allowed Domains Guard | `P0` | `MEDIUM` | `api_keys` table + CORS origin middleware | Phase 0 |
| **1** | `1.2` | Embeddable Web Chat Widget Bundle | `P0` | `MEDIUM` | Standalone `<script>` bundle (< 60 KB) | `1.1` |
| **1** | `1.3` | Streaming & Clickable Citation Badges | `P1` | `LOW` | Word-by-word streaming + source popup | `1.2` |
| **1** | `1.4` | Interactive Data Charting for Analytics | `P1` | `MEDIUM` | Chart.js rendering for spreadsheet SQL | None |
| **2** | `2.1` | Meta Self-Serve Onboarding UI | `P1` | `MEDIUM` | 1-click OAuth connection cards | Meta Review |
| **2** | `2.2` | Subscription Billing & Quota Metering | `P1` | `HIGH` | Tiered plans + bKash/Stripe billing | Gateway |
| **2** | `2.3` | Support Helpdesk & Ticket Lifecycles | `P1` | `MEDIUM` | Ticket queue + SLA tracking | `FEAT-CHAT-01` |
| **3** | `3.1` | Product Catalog & Recommendation Cards | `P2` | `MEDIUM` | Catalog CRUD + Typesense product search | None |
| **3** | `3.2` | In-Chat Live Checkout Link Generation | `P2` | `MEDIUM` | Direct payment link generation in chat | `3.1` |
| **3** | `3.3` | Automated Web Crawler & URL Training | `P2` | `MEDIUM` | 50-page sitemap background crawler | None |
