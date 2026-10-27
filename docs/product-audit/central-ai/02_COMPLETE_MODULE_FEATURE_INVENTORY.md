# Central AI: Complete Module & Feature Inventory

- **Target System**: Central AI (`https://centralai.daffodil-bd.com`)
- **Audit Methodology**: Verified through authenticated screen inspection [OBSERVED_LIVE], live API reference parsing [OBSERVED_LIVE], and official documentation [DOCUMENTED].

---

## Evidence Classification Key
- **`[OBSERVED_LIVE]`**: Screen inspected and verified in the live authenticated web application.
- **`[DOCUMENTED]`**: Stated explicitly in official Central AI documentation pages or pricing specifications.
- **`[GATED_PLAN]`**: Route redirected to `/accounts/pricing/` due to subscription tier gating on the current account.
- **`[UNTESTED_BEHAVIOR]`**: Documented interface whose live runtime side-effects were not executed to preserve safety.

---

## Complete Module Matrix (25 Discovered Modules)

| Module ID | Module Name | Scope & Capabilities | Primary Route | Observed Status | Plan Availability |
| :--- | :--- | :--- | :--- | :---: | :--- |
| **MOD-01** | **Chat Agent** | Knowledge RAG assistant, 8-passage lookups, persona, prompt guidelines. | `/chat/chatbot/settings/` | `[OBSERVED_LIVE]` | All Plans |
| **MOD-02** | **AI Sales Agent** | 9 company types, 7 offering kinds, 4 completion modes, payment notes. | `/sales/` | `[OBSERVED_LIVE]` | All Plans (Catalog), Starter+ (Checkout) |
| **MOD-03** | **Smart Lead Agent** | Asynchronous daily batch summarization (default 03:00) into 50 categories. | `/chat/chatbot/settings/` | `[OBSERVED_LIVE]` | Growth (3k credits), Scale (9k credits) |
| **MOD-04** | **Auto Lead Capture** | Anonymous visitor web tracking, interest scoring, and identification. | `/lead-capture/dashboard/` | `[OBSERVED_LIVE]` | Growth (500/mo), Scale (2,000/mo) |
| **MOD-05** | **AI Lead Engagement**| Automated email follow-up via connected company mailboxes (draft/autonomous).| `/lead-engagement/settings/` | `[OBSERVED_LIVE]` | Growth+ Paid Add-on (1,500 credits/mo) |
| **MOD-06** | **Support Ticket Agent**| Detects customer issue in chat, collects email, assigns priority, creates ticket.| `/support/tickets/` | `[OBSERVED_LIVE]` | Growth+, Scale |
| **MOD-07** | **Document Knowledge** | Ingests PDF, DOCX, TXT (up to 10MB). Text chunking (~400 chars, 80 overlap). | `/knowledge/upload/` | `[OBSERVED_LIVE]` | All Plans (Tier record quotas) |
| **MOD-08** | **Web Training** | Sitemap URL discovery, page crawler (embeds first ~4,000 chars per page). | `/knowledge/web-training/` | `[GATED_PLAN]` | Starter+ (3k/10k/50k records) |
| **MOD-09** | **FAQ Database** | Q&A pair manager, CSV import/export, title, URL, description, price fields. | `/chat/faqs/` | `[OBSERVED_LIVE]` | All Plans |
| **MOD-10** | **Knowledge Sync API** | Asynchronous server API for external document and offering ingestion (`POST /sync/`).| `/chat/api/docs/` | `[OBSERVED_LIVE]` | Growth+, Scale |
| **MOD-11** | **Offerings Catalog** | Sellable catalog: Product, Service, Course Intake, Appointment, Event, Plan, Custom.| `/sales/offerings/new/` | `[OBSERVED_LIVE]` | All Plans (Item quotas apply) |
| **MOD-12** | **Find What I Sell** | AI document scanner that extracts offerings word-for-word from knowledge files.| `/sales/suggestions/` | `[OBSERVED_LIVE]` | All Plans |
| **MOD-13** | **Web Chat Widget** | Floating embed snippet (`ca_...` key), allowed domains origin guard, theme styling.| `/chat/widget/` | `[OBSERVED_LIVE]` | All Plans |
| **MOD-14** | **Meta Social Hub** | Facebook Messenger, Instagram Direct, and WhatsApp Cloud API integration cards.| `/integrations/meta/` | `[OBSERVED_LIVE]` | Starter (FB), Growth+ (IG, WA) |
| **MOD-15** | **Commerce API (UCP)**| Universal Commerce Protocol Contract v1.3: quote.calculate, commit.create. | `/chat/api/docs/` | `[OBSERVED_LIVE]` | Scale, Custom |
| **MOD-16** | **Live Checkout** | Decoupled cart redirect ({items}, {ref}) to merchant checkout, signed webhooks.| `/sales/` | `[OBSERVED_LIVE]` | Starter+ |
| **MOD-17** | **Lead Integrations** | Odoo CRM XML-RPC configuration (Host, DB, User, Key, `crm.lead` mapping). | `/leads/integrations/` | `[OBSERVED_LIVE]` | Growth+ |
| **MOD-18** | **Partner Lead Sharing**| Routing of qualified leads to external partner networks. | `/docs/social-channels/` | `[DOCUMENTED]` | Growth (1), Scale (5) |
| **MOD-19** | **Chat Logs & Audits** | Real-time session transcript viewer with ratings, channels, and citation links. | `/chat/chat-logs/` | `[OBSERVED_LIVE]` | All Plans |
| **MOD-20** | **Mailboxes Engine** | Corporate email mailbox connection for Lead Engagement and Support ticketing. | `/accounts/company/mailboxes/`| `[OBSERVED_LIVE]` | Growth+, Scale |
| **MOD-21** | **Team Management** | Team member invitation, role assignment (Admin, Operator), access scopes. | `/accounts/team/` | `[OBSERVED_LIVE]` | Starter (4), Growth (10), Scale (20) |
| **MOD-22** | **API Keys & Security**| Two-key management: `ca_...` widget key, `cax_...` secret server key, domains.| `/accounts/security/api-keys/`| `[OBSERVED_LIVE]` | All Plans |
| **MOD-23** | **Usage Dashboard** | Real-time tracking of message quota, knowledge records, seats, and credits. | `/billing/usage/` | `[OBSERVED_LIVE]` | All Plans |
| **MOD-24** | **Invoicing System** | Automated monthly billing invoices and payment gateway checkout. | `/billing/invoices/` | `[OBSERVED_LIVE]` | All Plans |
| **MOD-25** | **Company Settings** | Tenant organization metadata, logo, default currency, and address. | `/accounts/company/update/` | `[OBSERVED_LIVE]` | All Plans |
