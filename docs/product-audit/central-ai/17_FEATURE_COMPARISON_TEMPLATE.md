# SaaS Feature Comparison Matrix: Central AI vs. Competing Platform

- **Document ID**: `COMP-17`
- **Scope**: Reusable, Neutral Capability Comparison Framework
- **Baseline Subject**: Central AI SaaS (`https://centralai.daffodil-bd.com`)
- **Evaluation Target**: In-House Multi-Source Conversational SaaS (`multi-source-chatbot`)
- **Assessment Date**: October 2026
- **Auditor Note**: All entries for the competing SaaS are intentionally marked `NOT ASSESSED` to maintain strict neutrality until formal empirical benchmarking is conducted.

---

## 1. Evaluation Framework & Standard Taxonomy

To ensure objective parity evaluation without bias or unverified claims, this template applies the following standardized classifications:

### A. Implementation Status
- `not started`: Capability has not been initiated in the codebase.
- `planned`: Architectural design or backlog ticket exists; no code written.
- `in progress`: Active branch or PR under development.
- `implemented`: Feature code is present in repository but unverified by tests.
- `tested`: Comprehensive unit, feature, and integration tests pass locally.
- `production-verified`: Deployed and validated in production staging with live traffic.
- `NOT ASSESSED`: Baseline default prior to comparative audit.

### B. Functional Parity
- `missing`: Competing SaaS lacks this capability entirely.
- `partial`: Sub-features or edge cases exist, but core capabilities are incomplete.
- `equivalent`: Competing SaaS matches Central AI in functionality and user experience.
- `exceeds baseline`: Competing SaaS provides superior functionality, performance, or accuracy.
- `not applicable`: Feature is outside the strategic scope of the competing platform.
- `NOT ASSESSED`: Baseline default prior to comparative audit.

---

## 2. High-Level Architectural Comparison Matrix

| # | Functional Domain | Central AI Capability & Evidence | Competing SaaS Capability | Implementation Status | Functional Parity | Integration & Infrastructure Dependencies | Security & Isolation Considerations | Usage Limits & Operating Costs | Next Validation Action |
| :-: | :--- | :--- | :--- | :---: | :---: | :--- | :--- | :--- | :--- |
| **01** | **Core Conversational RAG** | Answers from knowledge base using 8 retrieved passages per query. `[DOCUMENTED, EVID-DOC-01, 07]` | `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | LLM Inference API, Dense Vector DB | Tenant-scoped vector namespace filtering | Metered message quota (60 to 50k msgs/mo) | Benchmark latency & retrieval recall on test dataset |
| **02** | **Document Parsing & Chunking** | Parses PDF/DOCX/TXT (10MB limit); ~400 char chunks with 80 overlap. `[OBSERVED_LIVE, EVID-OBS-04]` | `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | File parser library, async worker queue | Secure file upload validation & malware scanning | Document storage records (300 to 50k records) | Test complex table & scanned PDF parsing accuracy |
| **03** | **Website Crawling** | Sitemaps/URLs crawled (1-3 hops); truncates at ~4,000 chars/page. `[GATED_PLAN, EVID-DOC-08]` | `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Headless browser / HTTP crawler, scheduler | SSRF protection on crawler destination URLs | Crawl job execution runtime & IP rate limiting | Evaluate SPA JavaScript rendering capabilities |
| **04** | **Tabular Data Processing** | Key-value normalization of CSVs (`col: val`); cannot run SQL joins. `[OBSERVED_LIVE, EVID-OBS-06]` | `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Relational SQL engine or embedding store | Table-level authorization per tenant | Database storage & query execution memory | Verify mathematical calculation precision on CSV |
| **05** | **AI Sales Agent** | 7 offering kinds, assisted selling, 3 completion modes. `[OBSERVED_LIVE, EVID-OBS-08, 09]` | `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Structured catalog database, cart state store | CSRF & session tampering protection on cart | Catalog item quotas (5 to unlimited items) | Inspect session basket concurrency handling |
| **06** | **Live Decoupled Checkout** | Redirects to external cart (`{items}`, `{ref}`); signed webhook. `[OBSERVED_LIVE, EVID-OBS-08, DOC-12]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | External merchant shopping cart, Webhook gateway | HMAC SHA-256 signature verification (`Central-AI-Signature`)| Webhook network compute & ingress bandwidth | Deploy mock merchant store to verify order webhook |
| **07** | **Headless UCP Commerce** | Universal Commerce Protocol v1.3 with RFC 9421 & idempotency. `[OBSERVED_LIVE, EVID-OBS-25]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Merchant-hosted UCP endpoint (`/.well-known/ucp`)| RFC 9421 HTTP Message Signatures & nonce tracking | Server-to-server HTTP API latency overhead | Test `idempotency_conflict` HTTP 409 error flows |
| **08** | **Find What I Sell** | AI scanner extracting sellable offerings verbatim from documents. `[OBSERVED_LIVE, EVID-OBS-10]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | LLM entity extraction pipeline, review queue | Tenant isolation of uploaded brochures | Token consumption during document scan pass | Run scan on multi-page service catalog PDF |
| **09** | **Smart Lead Daily Batch** | Asynchronous daily batch job (03:00) categorizing into 50 buckets. `[OBSERVED_LIVE, EVID-OBS-03]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Cron scheduler, transcript query pipeline | Access control on historical chat transcripts | 1 Lead Agent credit per chat (3k to 9k credits)| Inspect category classification accuracy |
| **10** | **Auto Lead Capture** | Anonymous web visitor journey dwell time & interest scoring. `[OBSERVED_LIVE, EVID-OBS-11]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | High-throughput event ingestion API, cookie store| GDPR / cookie consent compliance & origin guard | Lead capture quota (500 to 2,000 leads/mo) | Test client-side event tracking script latency |
| **11** | **AI Lead Engagement** | Autonomous email follow-up sequences via corporate mailboxes. `[OBSERVED_LIVE, EVID-OBS-12, 20]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | SMTP/IMAP or OAuth email server connectivity | Encryption of stored mailbox credentials | Email add-on quota (1,500 credits/mo) | Verify bounce handling & unsubscribe link compliance |
| **12** | **Support Ticket Agent** | In-chat NLP frustration detection, ticket creation, email sync. `[OBSERVED_LIVE, EVID-OBS-03, 13]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Sentiment analysis engine, ticket desk database | Helpdesk ticket visibility restricted by role | Ticket storage & 3-minute email polling worker | Test sentiment detection threshold on test phrases |
| **13** | **Web Chat Widget** | Branded floating embed snippet authenticated via `ca_...` key. `[OBSERVED_LIVE, EVID-OBS-14]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | CDN script host, SSE streaming gateway | Mandatory Allowed Domains whitelist origin guard | Client-side bundle size & SSE connection limits | Validate XSS sanitization in markdown render |
| **14** | **Meta Social Channels** | Unified hub for Facebook Messenger, Instagram Direct, WhatsApp. `[OBSERVED_LIVE, EVID-OBS-15]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Meta Graph API v19+, Webhook receiver | Meta app secret verification (`X-Hub-Signature-256`)| Meta Cloud API conversation charges & tiers | Test WhatsApp 24-hour service window handling |
| **15** | **Lead Integrations (Odoo)**| XML-RPC automated sync to Odoo `crm.lead` model. `[OBSERVED_LIVE, EVID-OBS-17]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | External XML-RPC client library | Stored Odoo API key encryption at rest | Network egress to external Odoo instances | Validate sync error recovery on network drops |
| **16** | **Partner Lead Sharing** | Routing of qualified leads to external affiliate partners (1-5). `[DOCUMENTED, EVID-DOC-14]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Partner distribution router, webhook/email alert| Cross-tenant data privacy & partner authorization | Partner seat quotas (1 on Growth, 5 on Scale) | Document partner acceptance UI workflow |
| **17** | **Chat Logs & Audits** | Full transcript viewer with ratings, channels, and RAG links. `[OBSERVED_LIVE, EVID-OBS-18]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Database transcript store, search index | Operator vs Admin transcript access segregation | Long-term log storage & retention compliance | Measure query performance across 100k+ sessions |
| **18** | **Corporate Mailboxes** | SMTP/OAuth business mailbox connection with 3-min sync poll. `[OBSERVED_LIVE, EVID-OBS-20]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Background mail worker, IMAP connection pool | TLS/SSL enforcement & secret key storage | Mail server connection limits & rate limits | Test OAuth token refresh lifecycle |
| **19** | **Team Management** | Multi-seat administration with Admin and Operator roles. `[OBSERVED_LIVE, EVID-OBS-21]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | RBAC middleware, invitation token system | Granular module permission scope enforcement | Dashboard seats (1 to 20 seats) | Test permission escalation boundary tests |
| **20** | **Two-Tier API Keys** | Segregated `ca_...` public widget key vs `cax_...` secret server key. `[OBSERVED_LIVE, EVID-OBS-22]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | API Gateway authentication middleware | Immediate key revocation upon regeneration | API rate limiting per tenant key | Verify legacy key rejection (`HTTP 401`) |
| **21** | **Usage Metering** | Real-time progress bars for messages, records, seats, credits. `[OBSERVED_LIVE, EVID-OBS-23]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Redis / Database atomic counter meters | Tamper-proof tenant usage accounting | Redis memory overhead for real-time counters | Verify quota enforcement at 100% boundary |
| **22** | **Invoicing & Billing** | Itemized invoice records, PDF receipts, plan upgrade checkouts. `[OBSERVED_LIVE, EVID-OBS-24]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Payment gateway integration (Stripe / Local MFS)| PCI-DSS compliance & payment token isolation | Gateway transaction processing fees | Test failed renewal grace period behavior |
| **23** | **Company Profile** | Organization metadata, logo upload, multi-currency defaults. `[OBSERVED_LIVE, EVID-OBS-19]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Tenant settings store, asset CDN storage | Multi-tenant profile isolation | Asset storage bandwidth for tenant logos | Test currency inheritance in offering cards |
| **24** | **Knowledge Sync API** | Asynchronous server API for external document pushing. `[OBSERVED_LIVE, EVID-OBS-25]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | Async task queue (Celery/Redis or RabbitMQ) | Secret key authentication via `X-API-Key` | Worker compute capacity during large ingestions | Test batch payload limits and job status polling |
| **25** | **Server Chat API** | Headless conversational API (`POST /chat/api/v1/`). `[OBSERVED_LIVE, EVID-OBS-25]`| `NOT ASSESSED` | `NOT ASSESSED` | `NOT ASSESSED` | REST API gateway, session state manager | Origin validation & server secret key validation | Server compute & LLM token consumption | Benchmark concurrent API session throughput |

---

## 3. Deep Dive Comparison Worksheet Template

For conducting module-by-module technical evaluations against a target capability, complete the following standardized evaluation sheet:

```markdown
### Capability Deep Dive: [Module Name / Capability]

#### 1. Capability & Business Value
- **User Problem Solved**:
- **Target Persona**:
- **Business Impact**:

#### 2. Central AI Baseline Specification
- **Observed Behavior**:
- **Configuration Fields**:
- **Documented Quotas & Plan Limits**:
- **Exact Evidence Reference**:

#### 3. Competing SaaS Specification
- **Current Architecture**:
- **Technical Implementation**:
- **Exact Code / File References**:

#### 4. Parity Assessment
- **Implementation Status**: [not started | planned | in progress | implemented | tested | production-verified]
- **Functional Parity**: [missing | partial | equivalent | exceeds baseline | not applicable]
- **Key Differentiators**:

#### 5. Technical Dependencies & Constraints
- **Infrastructure Dependencies**:
- **Security & Multi-Tenant Isolation**:
- **Operating Cost & Quota Impact**:

#### 6. Action Plan & Validation Gap
- **Identified Gap**:
- **Engineering Action Required**:
- **Verification Test Protocol**:
```

---

## 4. Benchmark Execution Guidelines

When populating the Competing SaaS columns:
1. **Never Assume Feature Parity**: Inspect the active codebase, database schemas, and automated test suite.
2. **Require Concrete Evidence**: Reference specific filenames, controller classes, and test cases (e.g. `tests/Feature/...`).
3. **Validate Architectural Truths**: Verify whether tabular processing performs SQL queries or raw text chunking; verify whether checkouts occur in-chat or via external redirects.
4. **Document Honest Gaps**: If an integration (e.g. Meta WhatsApp Cloud API or UCP Commerce Protocol) is not implemented, record it transparently as `missing` without mitigation spin.
