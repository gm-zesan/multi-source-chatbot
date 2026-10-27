# Prioritized Product Engineering Roadmap: Closing Strategic Gaps

- **Document ID**: `OUR-ROAD-04`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Market Context**: Bangladesh E-Commerce & F-Commerce (Facebook/Instagram/WhatsApp) SaaS
- **Audit Date**: October 2026
- **Status**: Prioritized Strategic Roadmap

---

## 1. Roadmap Prioritization Framework

Not every feature observed in Central AI warrants implementation in our platform. Features are prioritized using the **RICE Scoring Framework** adapted for the South Asian e-commerce landscape:

$$\text{RICE Score} = \frac{\text{Reach (Tenants Affected)} \times \text{Impact (Revenue/Retention)} \times \text{Confidence}}{\text{Effort (Engineering Person-Weeks)}}$$

### Key Strategic Principles:
1. **Capitalize on Existing Architectural Superiority**: Maintain our lead in deterministic multi-sheet SQL analytics over spreadsheets, Neo4j customer memory, and zero-hallucination semantic answerability.
2. **Close High-Friction Commercial Gaps**: Enable merchants to showcase product catalogs in chat and pass buyers to decoupled checkout links.
3. **Avoid Over-Engineering Niche Protocols**: Prefer generic, flexible webhooks over proprietary protocols like UCP v1.3 or hardcoded Odoo XML-RPC.

---

## 2. Phase 1: Quick Wins (Timeline: 1–2 Weeks)

```
================================================================================
PROJECT QW-1: Standalone Embeddable Web Chat Widget & Origin Whitelisting
================================================================================
```
- **Target Modules**: `MOD-13` (Web Chat Widget), `MOD-22` (API Keys & Security)
- **Customer Problem**: Merchants currently cannot embed our chatbot onto their public websites. The system only provides an internal dashboard simulator (`/dashboard/simulator`).
- **Audit Evidence**: Central AI provides a single `<script src=".../widget/embed.js" data-key="ca_..."></script>` snippet protected by an Allowed Domains whitelist.
- **Proposed Implementation**:
  - Build a lightweight (under 30KB gzip), vanilla JavaScript widget bundle (`public/widget/embed.js`) that injects a floating button and modal.
  - Create `api_keys` and `allowed_domains` tables. Public keys prefixed with `pk_live_...`.
  - Implement a public streaming endpoint: `POST /api/v1/widget/chat` with CORS origin verification matching the tenant's allowed domain whitelist.
- **Dependencies**: Laravel Route, Vanilla JS/CSS. Zero external microservice dependencies.
- **Risks**: Cross-site scripting (XSS) in markdown chat bubbles. *Mitigation*: Sanitize rendered markdown with DOMPurify.
- **Acceptance Criteria**:
  - A tenant pastes `<script src="...embed.js" data-key="pk_live_..."></script>` on an approved domain.
  - The chat bubble renders, allows conversational turns, and streams responses.
  - Embedding on an unauthorized domain returns `HTTP 403 ORIGIN_NOT_ALLOWED`.

```
================================================================================
PROJECT QW-2: Overnight Daily Batch Conversation Summarizer & Categorizer
================================================================================
```
- **Target Modules**: `MOD-03` (Smart Lead Agent)
- **Customer Problem**: Store owners cannot read hundreds of chat transcripts every day to extract what customers wanted. Interactive survey bots annoy customers and cause 40%+ drop-off.
- **Audit Evidence**: Central AI's Smart Lead Agent runs once per day at 03:00, summarizing completed chats into 50 user-defined buckets without customer interruption.
- **Proposed Implementation**:
  - Create a scheduled artisan command `php artisan chat:summarize-daily-leads` scheduled in `routes/console.php` at `03:00 Asia/Dhaka`.
  - Command queries conversations from the past 24 hours lacking a summary.
  - Synthesizes customer intent, extracts requested items, and matches against a comma-separated category taxonomy stored in tenant workspace settings.
  - Populates a `/dashboard/leads` table.
- **Dependencies**: Existing `CustomerSupportService`, LLM prompt, MySQL scheduler.
- **Risks**: LLM API latency or timeout during large batch runs. *Mitigation*: Chunk into queued jobs of 20 conversations per batch.
- **Acceptance Criteria**:
  - At 03:00, all completed chats from the preceding 24 hours receive a 2-sentence summary and a category tag.
  - Customers experience zero interruption or survey questions during live chats.

```
================================================================================
PROJECT QW-3: Channel Settings UI for Facebook Messenger & WhatsApp
================================================================================
```
- **Target Modules**: `MOD-14` (Meta Social Hub)
- **Customer Problem**: While backend drivers exist for Facebook Messenger (`FacebookDriver`), tenants have no web interface to input their Page Access Tokens or WhatsApp Cloud API credentials.
- **Audit Evidence**: Central AI provides visual connection cards at `/integrations/meta/`.
- **Proposed Implementation**:
  - Build a `/dashboard/channels` management screen.
  - Provide input forms for Facebook Page ID, Page Access Token, and WhatsApp Phone Number ID / WABA token.
  - Encrypt credentials in database using `encrypted:array` casting.
- **Dependencies**: Existing `ChannelAccount` model and `app/Channels/` drivers.
- **Risks**: Invalid tokens provided by user. *Mitigation*: Include a "Test Connection" button validating the token against Meta Graph API before saving.
- **Acceptance Criteria**:
  - Tenant inputs Facebook Page Token; receives inbound messages from real Facebook users and dispatches automated replies.

---

## 3. Phase 2: Medium-Sized Core Projects (Timeline: 3–5 Weeks)

```
================================================================================
PROJECT MP-1: Conversational Offerings Catalog & Visual In-Chat Cards
================================================================================
```
- **Target Modules**: `MOD-02` (AI Sales Agent), `MOD-11` (Offerings Catalog)
- **Customer Problem**: Chatbot can only answer informational policies. When a customer asks "Do you have black hoodies in XL?", it cannot display a clickable product card with price and photo.
- **Audit Evidence**: Central AI supports 7 offering kinds (`Product`, `Service`, etc.) with image URLs, SKUs, and prices, rendering interactive cards in chat.
- **Proposed Implementation**:
  - Scaffold `OfferingsController` and `Offerings` database table (`workspace_id`, `name`, `sku`, `price`, `currency`, `image_url`, `description`, `is_active`).
  - Add `/dashboard/offerings` CRUD UI in the Unified Knowledge Base Hub.
  - In `CustomerSupportService`, when intent is `KNOWLEDGE` and matches a product SKU/name, return a structured rich payload alongside text tokens.
- **Dependencies**: MySQL migration, Blade/Livewire UI.
- **Risks**: Overlapping product names confusing vector retrieval. *Mitigation*: Match products using combined BM25 + SKU exact matching in Typesense.
- **Acceptance Criteria**:
  - Merchant creates product "Winter Hoodie" at 1,200 BDT with image.
  - In chat, customer asks about winter hoodies; bot renders an interactive visual card with title, image, price, and action button.

```
================================================================================
PROJECT MP-2: Decoupled Live Checkout Link Template & Signed Order Webhook
================================================================================
```
- **Target Modules**: `MOD-16` (Live Checkout)
- **Customer Problem**: In Bangladesh, merchants use diverse checkout flows (Shopify, WooCommerce, custom landing pages, or phone confirmation). A bot that tries to force full card payments in chat fails because merchants want orders in their main store.
- **Audit Evidence**: Central AI uses a decoupled URL template (`https://yourshop.com/cart/add?items={items}&ref={ref}`) and listens for an HMAC-signed order webhook.
- **Proposed Implementation**:
  - In workspace settings, allow merchants to enter a `checkout_link_template`.
  - When customer agrees to buy in chat, generate the parameterized link and render a `[Complete Order on Website]` button.
  - Implement `POST /api/v1/webhooks/orders` verifying `X-Signature-256` (HMAC SHA-256 with tenant secret).
  - Webhook arrival updates conversation state and pushes an order confirmation message to the chat session.
- **Dependencies**: Webhook route, session reference generator.
- **Risks**: Webhook spoofing without signatures. *Mitigation*: Mandatory HMAC validation; reject unsigned webhooks with HTTP 401.
- **Acceptance Criteria**:
  - Customer clicks checkout link; redirected to merchant cart with items pre-filled.
  - Merchant site fires webhook upon payment; customer's chat session immediately reflects order confirmation.

```
================================================================================
PROJECT MP-3: Lightweight Support Ticket Desk & Escalation Inbox
================================================================================
```
- **Target Modules**: `MOD-06` (Support Ticket Agent)
- **Customer Problem**: Unresolved customer issues get buried in conversation transcripts without ticket assignment, severity tracking, or status accountability.
- **Audit Evidence**: Central AI provides `/support/tickets/` with 4 priorities and 4 statuses.
- **Proposed Implementation**:
  - Create `support_tickets` table (`workspace_id`, `conversation_id`, `ticket_number`, `customer_email`, `subject`, `priority`, `status`, `assigned_to`).
  - In `CustomerSupportService`, when frustration sentiment or `handoff_triggers` are hit, generate a ticket and notify on-duty staff.
  - Build a `/dashboard/tickets` helpdesk view with status toggles (`open`, `in_progress`, `resolved`, `closed`).
- **Dependencies**: Blade views, email notification.
- **Acceptance Criteria**:
  - Frustrated customer conversation automatically spawns Ticket #TCK-XXXX with priority set to `high` or `urgent`.
  - Human agent can claim the ticket, filter by priority, and resolve the issue.

```
================================================================================
PROJECT MP-4: Per-Workspace Corporate Mailboxes (SMTP/IMAP)
================================================================================
```
- **Target Modules**: `MOD-05` (AI Lead Engagement), `MOD-20` (Mailboxes Engine)
- **Customer Problem**: Outbound notifications and replies are sent from the SaaS platform's generic email rather than the merchant's official branded corporate address (`support@merchant.com`).
- **Audit Evidence**: Central AI has `/accounts/company/mailboxes/` connecting tenant SMTP/IMAP.
- **Proposed Implementation**:
  - Create `workspace_mailboxes` table with encrypted credentials.
  - Implement a dynamic mail transport resolver configuring Symfony Mailer with tenant SMTP settings on-the-fly.
- **Dependencies**: OpenSSL encryption, Laravel dynamic mailer.
- **Acceptance Criteria**:
  - Outbound notification emails are sent directly through the merchant's own mail server with correct DKIM/SPF alignment.

---

## 4. Phase 3: Major Strategic Architectural Work (Timeline: 6–10 Weeks)

```
================================================================================
PROJECT MA-1: Multi-Tenant Commercial Subscription Billing & Metering Engine
================================================================================
```
- **Target Modules**: `MOD-23` (Usage Dashboard), `MOD-24` (Invoicing System)
- **Customer Problem**: The platform currently has no automated monetization, billing tiers, or usage meters.
- **Audit Evidence**: Central AI monetizes via Starter ($15), Growth ($50), and Scale ($150) tiers, with real-time usage meters for messages, knowledge records, and seats.
- **Proposed Implementation**:
  - Integrate domestic Bangladeshi payment gateways (bKash Checkout, Nagad, SSLCommerz) alongside Stripe for international cards.
  - Implement atomic Redis counters tracking monthly message consumption, document count, and active seats.
  - Build `/dashboard/billing` with plan upgrade CTAs and automated PDF invoice generation.
- **Dependencies**: Payment gateway APIs, PDF invoice renderer.
- **Risks**: Subscription renewal failures on local payment methods. *Mitigation*: Grace period handling with automated SMS/email reminders.
- **Acceptance Criteria**:
  - Tenant can upgrade from Free to Pro using bKash/Card; usage meters accurately throttle turns upon quota exhaustion.

```
================================================================================
PROJECT MA-2: Generic Inbound & Outbound Webhook Integration Hub
================================================================================
```
- **Target Modules**: `MOD-17` (Lead Integrations代替 Odoo), `MOD-10` (Knowledge Sync API)
- **Customer Problem**: Hardcoding individual ERP integrations (like Central AI's Odoo XML-RPC) is brittle and excludes 95% of businesses using WooCommerce, Shopify, Google Sheets, or custom CRMs.
- **Proposed Implementation**:
  - Instead of copying Central AI's narrow Odoo XML-RPC integration, build a **Generic Webhook Dispatcher**.
  - When a lead is captured or conversation completed, dispatch a signed webhook payload to an arbitrary URL configured by the tenant (compatible with Zapier, Make, or custom webhooks).
- **Dependencies**: Guzzle async client, webhook retry worker.
- **Acceptance Criteria**:
  - Lead captured in chat immediately posts a webhook to the merchant's Zapier or Google Sheets webhook URL.

```
================================================================================
PROJECT MA-3: Automated Website Crawler & Sitemap Parser
================================================================================
```
- **Target Modules**: `MOD-08` (Web Training)
- **Customer Problem**: Merchants frequently update their websites and do not want to re-upload PDFs every time a policy changes.
- **Proposed Implementation**:
  - Build an asynchronous crawler worker using Guzzle and Symfony DomCrawler.
  - Supports sitemap XML parsing, link extraction up to 2 hops, and automated weekly re-syncing.
  - Strict SSRF protection preventing crawler from accessing private/internal IP ranges (`127.0.0.1`, `10.0.0.0/8`, `192.168.0.0/16`).
- **Dependencies**: Queue worker, HTML parser.
- **Acceptance Criteria**:
  - Tenant enters `https://example.com/sitemap.xml`; system crawls approved pages, chunks content, and indexes into Typesense.

---

## 5. Deliberately Deferred / Non-Recommended Features

| Feature | Central AI Approach | Why We Recommend DEFERRING or REJECTING |
| :--- | :--- | :--- |
| **Native In-Chat Credit Card Processing** | Built-in card forms inside chat bubbles. | **Rejected**. High compliance burden (PCI-DSS), high customer skepticism in Bangladesh, and high drop-off compared to decoupled checkout links on the merchant's trusted domain. |
| **Headless Commerce Protocol UCP v1.3** | Custom inverted contract requiring merchant servers to expose `/.well-known/ucp`. | **Deferred**. Extreme developer friction. Less than 1% of merchants in Bangladesh have engineering teams capable of implementing custom UCP server specifications. |
| **Hardcoded Odoo XML-RPC Sync** | Tight XML-RPC coupling to Odoo `crm.lead`. | **Rejected in favor of Generic Webhooks**. Only a tiny fraction of target customers use Odoo; standard Zapier/Make webhooks serve 100x more businesses. |
| **Anonymous Web Visitor Tracker** | Passive tracking of dwell time and scroll depth prior to chat. | **Deferred**. High infrastructure event volume with low marginal conversion impact compared to high-intent conversational lead capture. |
