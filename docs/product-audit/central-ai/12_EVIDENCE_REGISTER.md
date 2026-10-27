# Central AI: Evidence & Verification Register

- **Document ID**: `REG-12`
- **Scope**: Comprehensive Audit Traceability Matrix of All Inspected Assets, Screens, and Documents
- **Audit Date**: October 2026
- **Evidence Standard Applied**:
  - `[OBSERVED_LIVE]`: Screen inspected, form fields extracted, and layout verified in the live authenticated application via authenticated session crawl.
  - `[TESTED_LIVE]`: Operation or client component actively tested (e.g. CSRF authentication handshake, chat widget script load).
  - `[DOCUMENTED]`: Explicitly confirmed in official Central AI documentation (`/docs/`), in-app API reference (`/chat/api/docs/`), or live `/pricing/` DOM attributes.
  - `[GATED_PLAN]`: Route redirected to `/accounts/pricing/` due to plan restrictions on the Test Drive account.
  - `[PARTIALLY_VERIFIED]`: Supported by documentation or UI but subject to technical constraints (e.g. crawler 4,000 char truncation).
  - `[CONTRADICTED]`: Prior audit claim explicitly disproven by live UI observation, official documentation, or pricing DOM.
  - `[UNTESTED_BEHAVIOR]`: Documented interface or workflow whose live runtime side-effects were not executed to preserve safety.
  - `[UNVERIFIED]`: Vendor claim or internal system implementation (e.g. specific embedding model or vector DB engine) not disclosed in evidence.

---

## 1. Authenticated Dashboard Screens (`[OBSERVED_LIVE]`)

Inspected directly in active authenticated workspace (`zesan`, Company: `ABC`, Plan: `Test Drive`):

| Evidence ID | Endpoint Path | Observed Screen Title | Key Verified UI Elements | Verification Status |
| :--- | :--- | :--- | :--- | :---: |
| `EVID-OBS-01` | `/chat/dashboard/` | Dashboard | Recent leads table, recent chats table, volume chart | `[OBSERVED_LIVE]` |
| `EVID-OBS-02` | `/chat/chatbot/` | Playground - Central AI | Real-time chat dialogue, citation pill inspector, token meter | `[OBSERVED_LIVE]` / `[TESTED_LIVE]` |
| `EVID-OBS-03` | `/chat/chatbot/settings/`| Agents & Tools - Central AI | System prompt textarea, ticket auto-detect, Smart Lead 03:00 cron & 50 categories | `[OBSERVED_LIVE]` |
| `EVID-OBS-04` | `/knowledge/` | Knowledge Base | Document listing, 10MB file uploader, chunking status grid | `[OBSERVED_LIVE]` |
| `EVID-OBS-05` | `/knowledge/web-training/`| Pricing - Central AI | Redirects (302) to pricing; requires Starter+ plan | `[GATED_PLAN]` |
| `EVID-OBS-06` | `/chat/faqs/` | Knowledge Management | Q&A data table, CSV import/export buttons, price field | `[OBSERVED_LIVE]` |
| `EVID-OBS-07` | `/knowledge/api/docs/` | Pricing - Central AI | Redirects (302) to pricing; API reference located at `/chat/api/docs/` | `[GATED_PLAN]` |
| `EVID-OBS-08` | `/sales/` | AI Sales Agent \| Central AI | 9 company types, 7 offering kinds, 3 completion modes, 5 payment notes | `[OBSERVED_LIVE]` |
| `EVID-OBS-09` | `/sales/offerings/new/`| New offering \| AI Sales Agent| Form with 7 offering kinds, 5 availability modes, pricing, inventory | `[OBSERVED_LIVE]` |
| `EVID-OBS-10` | `/sales/suggestions/` | Needs your review \| AI Sales Agent| "Find What I Sell" suggestion table, "Scan Knowledge" action | `[OBSERVED_LIVE]` |
| `EVID-OBS-11` | `/lead-capture/dashboard/`| Auto Lead Capture \| ABC | Anonymous visitor counters, journey dwell time table | `[OBSERVED_LIVE]` |
| `EVID-OBS-12` | `/lead-engagement/settings/`| AI Lead Engagement Agent | Mailbox assignment, draft_only vs autonomous mode, cadence | `[OBSERVED_LIVE]` |
| `EVID-OBS-13` | `/support/tickets/` | Support Tickets | Ticket ID, Email, Subject, Priority (4 tiers), Status (4 states) | `[OBSERVED_LIVE]` |
| `EVID-OBS-14` | `/chat/widget/` | Widget Integration \| ABC | Embed snippet with `ca_...` key, allowed domains whitelist, color picker | `[OBSERVED_LIVE]` |
| `EVID-OBS-15` | `/integrations/meta/` | Social Channels - Central AI | Facebook Messenger, Instagram Direct, WhatsApp Cloud API cards | `[OBSERVED_LIVE]` |
| `EVID-OBS-16` | `/leads/` | Pricing - Central AI | Redirects (302) to pricing; requires Growth+ plan | `[GATED_PLAN]` |
| `EVID-OBS-17` | `/leads/integrations/` | Lead integrations \| Central AI | Odoo host, database, user, API key form; `crm.lead` field mapping | `[OBSERVED_LIVE]` |
| `EVID-OBS-18` | `/chat/chat-logs/` | Chat Logs - Central AI | Full session viewer, user rating filter, channel badges | `[OBSERVED_LIVE]` |
| `EVID-OBS-19` | `/accounts/company/update/`| Update Company \| Central AI | Organization profile, currency selector, logo upload | `[OBSERVED_LIVE]` |
| `EVID-OBS-20` | `/accounts/company/mailboxes/`| Mailboxes \| Central AI | Corporate SMTP/OAuth mailboxes (corrects legacy 404 `/accounts/mailboxes/`)| `[OBSERVED_LIVE]` |
| `EVID-OBS-21` | `/accounts/team/` | Team Management | User table, Admin/Operator roles, granular module permissions | `[OBSERVED_LIVE]` |
| `EVID-OBS-22` | `/accounts/security/api-keys/`| API Keys - Central AI | Public widget key `ca_...`, secret server key `cax_...`, domain list | `[OBSERVED_LIVE]` |
| `EVID-OBS-23` | `/billing/usage/` | Usage Dashboard | Active Test Drive plan badge, meters (60 msgs, 300 records, 1 seat) | `[OBSERVED_LIVE]` |
| `EVID-OBS-24` | `/billing/invoices/` | Invoices - Central AI | Billing history table, invoice download links | `[OBSERVED_LIVE]` |
| `EVID-OBS-25` | `/chat/api/docs/` | API Reference - Central AI | 19 specification tables covering Chat, Sync, Lead, and UCP APIs | `[OBSERVED_LIVE]` |

*Raw inspection payload for all 54 routes persisted in `scratch/live_inspected_screens.json` (sanitized of sensitive tokens).*

---

## 2. Official Platform Documentation Chapters (`[DOCUMENTED]`)

| Evidence ID | Source File / Chapter | Topic Covered | Key Specifications Confirmed |
| :--- | :--- | :--- | :--- |
| `EVID-DOC-01` | `docs_chat_agent.html` | Core Chat Agent | RAG architecture, hallucination constraints, model prompt |
| `EVID-DOC-02` | `docs_sales_agent.html` | AI Sales Agent | In-chat offering cards, assisted recommendations, cart flow |
| `EVID-DOC-03` | `/docs/lead-agent/` | Smart Lead Agent | Daily batch summarization, 50 categories, customer sees nothing |
| `EVID-DOC-04` | `docs_lead_capture.html`| Auto Lead Capture | Passive regex extraction of phone and email patterns |
| `EVID-DOC-05` | `docs_lead_engage.html` | AI Lead Engagement | Outbound follow-up sequences, abandoned cart re-engagement |
| `EVID-DOC-06` | `docs_support_agent.html`| Support Ticketing | Issue categorization, severity scoring, human escalation |
| `EVID-DOC-07` | `/docs/knowledge-base/` & `/docs/chat-agent/` | Knowledge Retrieval | 8 most relevant passages retrieved, ~400 char chunking, 80 char overlap |
| `EVID-DOC-08` | `docs_crawler.html` | Web Training | Sitemap crawling, link depth recursion, schedule re-sync |
| `EVID-DOC-09` | `docs_catalog.html` | Offerings & Catalog | 7 offering kinds (`Product`, `Service`, `Course Intake`, `Appointment`, `Event Date`, `Plan`, `Custom`)|
| `EVID-DOC-10` | `docs_widget.html` | Web Widget Embed | Client snippet, domain whitelist origin guard, SSE streaming |
| `EVID-DOC-11` | `docs_meta.html` | Meta Integrations | Facebook Messenger, Instagram Direct, WhatsApp Cloud API |
| `EVID-DOC-12` | `/docs/live-checkout/` & `/docs/commerce-api/` | Commerce & Checkout| Decoupled cart link ({items}, {ref}), signed order webhook, Commerce API v1.3 |
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
