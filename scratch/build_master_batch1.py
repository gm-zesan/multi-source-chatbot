import os, json

BASE_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai"

# Load live inspection data
with open("scratch/live_inspected_screens.json", "r", encoding="utf-8") as f:
    live_screens = json.load(f)

# --------------------------------------------------------------------------
# 1. 01_PRODUCT_AND_NAVIGATION_MAP.md
# --------------------------------------------------------------------------
nav_map_content = """# Central AI: Product & Verified Navigation Map

- **Target System**: Central AI (`https://centralai.daffodil-bd.com`)
- **Inspection Date**: October 2026 (Live Authenticated Crawl Pass)
- **Account Identity**: Authenticated Workspace (`zesan`, Company: `ABC`)
- **Audit Methodology**: Live authenticated DOM inspection across 54 discovered platform routes [OBSERVED_LIVE] cross-verified with official documentation [DOCUMENTED].

---

## 1. Global Navigation Hierarchy

The Central AI web console is structured into 7 core functional zones accessible from the persistent sidebar and top navigation:

```mermaid
graph TD
    ROOT["Central AI Platform Console"]

    subgraph NavigationZones ["Verified Functional Zones"]
        ZONE_AI["1. AI Agent Core & Playground"]
        ZONE_KB["2. Knowledge Hub & Documents"]
        ZONE_COMM["3. AI Sales & Offerings Catalog"]
        ZONE_LEADS["4. Lead Capture & CRM Pipelines"]
        ZONE_SUPPORT["5. Support Tickets & Helpdesk"]
        ZONE_CHANNELS["6. Channels & Public Embed"]
        ZONE_ACCOUNTS["7. Company, Team & Security Settings"]
    end

    ROOT --> ZONE_AI
    ROOT --> ZONE_KB
    ROOT --> ZONE_COMM
    ROOT --> ZONE_LEADS
    ROOT --> ZONE_SUPPORT
    ROOT --> ZONE_CHANNELS
    ROOT --> ZONE_ACCOUNTS

    ZONE_AI --> AI_DASH["Dashboard Overview (/chat/dashboard/)"]
    ZONE_AI --> AI_PLAY["Interactive Playground (/chat/chatbot/)"]
    ZONE_AI --> AI_SETUP["Agents & Tools Settings (/chat/chatbot/settings/)"]
    ZONE_AI --> AI_GUIDE["Chatbot Prompt Guidelines (/chat/chatbot/guidelines/)"]

    ZONE_KB --> KB_HOME["Knowledge Base (/knowledge/)"]
    ZONE_KB --> KB_UPLOAD["Upload Document (/knowledge/upload/)"]
    ZONE_KB --> KB_FAQS["Knowledge Management / FAQs (/chat/faqs/)"]
    ZONE_KB --> KB_FAQ_ADD["Add FAQ (/chat/faqs/add/)"]
    ZONE_KB --> KB_EXPORT["Export Knowledge (/knowledge/export/)"]
    ZONE_KB --> KB_WEB["Web Training Crawler (/knowledge/web-training/ - Gated)"]

    ZONE_COMM --> COMM_SETUP["AI Sales Agent Setup (/sales/)"]
    ZONE_COMM --> COMM_OFFERINGS["Offerings Catalog (/sales/offerings/)"]
    ZONE_COMM --> COMM_OFFERING_NEW["New Offering Form (/sales/offerings/new/)"]
    ZONE_COMM --> COMM_OFFERING_IMP["Import Offerings CSV (/sales/offerings/import/)"]
    ZONE_COMM --> COMM_SUGG["Suggestions Queue / Find What I Sell (/sales/suggestions/)"]

    ZONE_LEADS --> LEADS_CAPTURE["Auto Lead Capture Dashboard (/lead-capture/dashboard/)"]
    ZONE_LEADS --> LEADS_ENGAGE["AI Lead Engagement Settings (/lead-engagement/settings/)"]
    ZONE_LEADS --> LEADS_LIST["Leads List (/leads/ - Gated on Test Drive)"]
    ZONE_LEADS --> LEADS_INTEG["Lead Integrations / Odoo Sync (/leads/integrations/)"]
    ZONE_LEADS --> LEADS_CONV["Conversions Upload (/leads/conversions/upload/ - Gated)"]

    ZONE_SUPPORT --> SUPP_INBOX["Support Tickets Inbox (/support/tickets/)"]
    ZONE_SUPPORT --> SUPP_CREATE["Create Support Ticket (/support/tickets/create/)"]

    ZONE_CHANNELS --> CHAN_WIDGET["Website Chat Widget (/chat/widget/)"]
    ZONE_CHANNELS --> CHAN_META["Social Channels Meta Hub (/integrations/meta/)"]
    ZONE_CHANNELS --> CHAN_LOGS["Chat Logs & Transcripts (/chat/chat-logs/)"]

    ZONE_ACCOUNTS --> ACC_CO["Update Company (/accounts/company/update/)"]
    ZONE_ACCOUNTS --> ACC_MAIL["Mailboxes Management (/accounts/company/mailboxes/)"]
    ZONE_ACCOUNTS --> ACC_TEAM["Team Management (/accounts/team/)"]
    ZONE_ACCOUNTS --> ACC_KEYS["API Keys (/accounts/security/api-keys/)"]
    ZONE_ACCOUNTS --> ACC_NOTIF["Notifications (/accounts/notifications/)"]
    ZONE_ACCOUNTS --> ACC_FEED["Submit Feedback (/accounts/feedback/)"]
    ZONE_ACCOUNTS --> ACC_USAGE["Usage Dashboard (/billing/usage/)"]
    ZONE_ACCOUNTS --> ACC_INV["Invoices (/billing/invoices/)"]
    ZONE_ACCOUNTS --> ACC_API_DOCS["In-App API Reference (/chat/api/docs/)"]
```

---

## 2. Exhaustive Route Map & Access Gating Matrix

| Route Path | Screen Title | Functional Zone | Observed HTTP Status | Plan Access Gating |
| :--- | :--- | :--- | :---: | :--- |
| `/chat/dashboard/` | Dashboard | 1. Agent Core | 200 OK | All Plans [OBSERVED_LIVE] |
| `/chat/chatbot/` | Playground - Central AI | 1. Agent Core | 200 OK | All Plans [OBSERVED_LIVE] |
| `/chat/chatbot/settings/` | Agents & Tools - Central AI | 1. Agent Core | 200 OK | All Plans [OBSERVED_LIVE] |
| `/chat/chatbot/guidelines/` | Chatbot Prompt Guidelines | 1. Agent Core | 200 OK | All Plans [OBSERVED_LIVE] |
| `/knowledge/` | Knowledge Base | 2. Knowledge Hub | 200 OK | All Plans [OBSERVED_LIVE] |
| `/knowledge/upload/` | Upload Document - Central AI | 2. Knowledge Hub | 200 OK | All Plans [OBSERVED_LIVE] |
| `/knowledge/export/` | Export Knowledge | 2. Knowledge Hub | 200 OK | All Plans [OBSERVED_LIVE] |
| `/knowledge/docs/` | Documentation Hub - Central AI | 2. Knowledge Hub | 200 OK | All Plans [OBSERVED_LIVE] |
| `/chat/faqs/` | Knowledge Management | 2. Knowledge Hub | 200 OK | All Plans [OBSERVED_LIVE] |
| `/chat/faqs/add/` | Add FAQ | 2. Knowledge Hub | 200 OK | All Plans [OBSERVED_LIVE] |
| `/knowledge/web-training/`| Pricing - Central AI | 2. Knowledge Hub | 302 -> /accounts/pricing/ | **Gated: Starter+** [OBSERVED_LIVE] |
| `/sales/` | AI Sales Agent \| Central AI | 3. Offerings & Commerce | 200 OK | All Plans [OBSERVED_LIVE] |
| `/sales/offerings/` | Offerings \| AI Sales Agent | 3. Offerings & Commerce | 200 OK | All Plans [OBSERVED_LIVE] |
| `/sales/offerings/new/` | New offering \| AI Sales Agent | 3. Offerings & Commerce | 200 OK | All Plans [OBSERVED_LIVE] |
| `/sales/offerings/import/` | Import offerings \| AI Sales Agent | 3. Offerings & Commerce | 200 OK | All Plans [OBSERVED_LIVE] |
| `/sales/suggestions/` | Needs your review \| AI Sales Agent | 3. Offerings & Commerce | 200 OK | All Plans [OBSERVED_LIVE] |
| `/lead-capture/dashboard/`| Auto Lead Capture \| ABC | 4. Leads & CRM | 200 OK | All Plans [OBSERVED_LIVE] |
| `/lead-engagement/settings/`| AI Lead Engagement Agent | 4. Leads & CRM | 200 OK | All Plans [OBSERVED_LIVE] |
| `/leads/` | Pricing - Central AI | 4. Leads & CRM | 302 -> /accounts/pricing/ | **Gated: Starter+** [OBSERVED_LIVE] |
| `/leads/integrations/` | Lead integrations \| Central AI | 4. Leads & CRM | 200 OK | All Plans [OBSERVED_LIVE] |
| `/leads/conversions/upload/`| Pricing - Central AI | 4. Leads & CRM | 302 -> /accounts/pricing/ | **Gated: Growth+** [OBSERVED_LIVE] |
| `/leads/email-settings/` | Pricing - Central AI | 4. Leads & CRM | 302 -> /accounts/pricing/ | **Gated: Growth+** [OBSERVED_LIVE] |
| `/support/tickets/` | Support Tickets | 5. Support Desk | 200 OK | All Plans [OBSERVED_LIVE] |
| `/support/tickets/create/` | Create Support Ticket | 5. Support Desk | 200 OK | All Plans [OBSERVED_LIVE] |
| `/chat/widget/` | Widget Integration \| ABC | 6. Channels & Embed | 200 OK | All Plans [OBSERVED_LIVE] |
| `/integrations/meta/` | Social Channels - Central AI | 6. Channels & Embed | 200 OK | All Plans [OBSERVED_LIVE] |
| `/chat/chat-logs/` | Chat Logs - Central AI | 6. Channels & Embed | 200 OK | All Plans [OBSERVED_LIVE] |
| `/accounts/company/update/`| Update Company \| Central AI | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |
| `/accounts/company/mailboxes/`| Mailboxes \| Central AI | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |
| `/accounts/team/` | Team Management | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |
| `/accounts/security/api-keys/`| API Keys - Central AI | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |
| `/accounts/notifications/`| Notifications \| Central AI | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |
| `/accounts/feedback/` | Submit Feedback - Central AI | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |
| `/billing/usage/` | Usage Dashboard | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |
| `/billing/invoices/` | Invoices - Central AI | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |
| `/chat/api/docs/` | API Reference - Central AI | 7. Account & System | 200 OK | All Plans [OBSERVED_LIVE] |

---

## 3. Discovered Legacy / Inactive Paths (Corrected 404s)
- **`/security/`**: Returns HTTP 404. Real security route is `/accounts/security/api-keys/` [OBSERVED_LIVE].
- **`/accounts/mailboxes/`**: Returns HTTP 404. Real mailboxes route is `/accounts/company/mailboxes/` [OBSERVED_LIVE].
- **`/knowledge/api/docs/`**: Redirects to `/accounts/pricing/`. Real API documentation hub is at `/chat/api/docs/` [OBSERVED_LIVE].
"""

with open(os.path.join(BASE_DIR, "01_PRODUCT_AND_NAVIGATION_MAP.md"), "w", encoding="utf-8") as f:
    f.write(nav_map_content.strip() + "\n")
print("Written: 01_PRODUCT_AND_NAVIGATION_MAP.md")

# --------------------------------------------------------------------------
# 2. 02_COMPLETE_MODULE_FEATURE_INVENTORY.md
# --------------------------------------------------------------------------
module_inventory_content = """# Central AI: Complete Module & Feature Inventory

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
"""

with open(os.path.join(BASE_DIR, "02_COMPLETE_MODULE_FEATURE_INVENTORY.md"), "w", encoding="utf-8") as f:
    f.write(module_inventory_content.strip() + "\n")
print("Written: 02_COMPLETE_MODULE_FEATURE_INVENTORY.md")

# --------------------------------------------------------------------------
# 3. 03_DASHBOARD_SCREEN_CATALOG.md
# --------------------------------------------------------------------------
screen_catalog_content = """# Central AI: Live Dashboard Screen Catalog

- **Inspection Date**: October 2026
- **Methodology**: Direct authenticated crawl of live platform screens [OBSERVED_LIVE].
- **Account State**: Active Test Drive Workspace (`zesan`, Company: `ABC`).

---

## 1. `/chat/dashboard/` — Main Platform Dashboard
- **Observed Title**: `Dashboard` [OBSERVED_LIVE]
- **Key Headings**: `Dashboard`
- **Data Grids & Tables**:
  - `Recent Leads Table`: Columns `Name/Email`, `Status`, `Source`, `Date`. Empty state: *"No recent leads found."*
  - `Recent Chats Table`: Columns `Contact`, `AI Score`, `Action`. Empty state: *"No recent chats found."*
- **Visual Analytics**: Interactive chart container (`height: 300px`) rendering conversational volume over time.

---

## 2. `/chat/chatbot/` — Interactive Agent Playground
- **Observed Title**: `Playground - Central AI` [OBSERVED_LIVE]
- **Purpose**: Real-time simulated chat dialogue for testing prompt responses, RAG retrieval citations, and offering cards before publishing live.
- **Observed Actions**: Reset session, inspect citation passages, view token count.

---

## 3. `/chat/chatbot/settings/` — Agents & Tools Configuration
- **Observed Title**: `Agents & Tools - Central AI` [OBSERVED_LIVE]
- **Key Headings**: `Agents & Tools`, `Chat Agent`, `Support Ticket Agent`, `Smart Lead Agent`
- **Live Form Controls (`POST /chat/chatbot/settings/`)**:
  - **Chat Agent**:
    - `prompt`: Multi-line textarea for system instructions and business persona.
  - **Support Ticket Agent**:
    - `support_ticket_enabled`: Checkbox toggle.
    - `support_ticket_auto_detect`: Checkbox toggle for automated frustration/issue detection.
    - `support_ticket_instructions`: Textarea guidance for ticket creation.
    - `support_ticket_mode`: Dropdown selector (2 modes).
    - `support_ticket_priority`: Dropdown selector (`low`, `medium`, `high`, `urgent`).
  - **Smart Lead Agent**:
    - `lead_agent_enabled`: Checkbox toggle.
    - `lead_agent_run_time`: Time input (default `03:00`) governing daily batch execution.
    - `lead_agent_allowed_categories`: Text input for comma-separated category taxonomy (up to 50 categories).
    - `lead_agent_timezone`: Dropdown selector with 433 global timezones (default `Asia/Dhaka`).

---

## 4. `/sales/` — AI Sales Agent Master Setup
- **Observed Title**: `AI Sales Agent | Central AI` [OBSERVED_LIVE]
- **Key Headings**: `Company type`, `What you offer`, `How customers complete`, `Connection to your system Advanced`, `Where the agent sells`, `Rules and payment`, `Publish`, `Usage this month`
- **Live Form Controls (`POST /sales/`)**:
  - **Step 1: Company Type**: Dropdown with 9 business classifications.
  - **Step 2: Offering Kinds**: Checkboxes and custom labels for 7 kinds:
    - `product` (`label_product`), `service` (`label_service`), `course_intake` (`label_course_intake`), `appointment` (`label_appointment`), `event_date` (`label_event_date`), `plan` (`label_plan`), `custom` (`label_custom`).
  - **Step 3: Completion Modes**: Radio selectors for each kind:
    - *Handled in Central AI* (in-chat order)
    - *Live checkout on your site* (`checkout_link_template` input: `{items}`, `{ref}`)
    - *Created in our system* (Commerce API connection: `profile_url`, `api_key`)
  - **Step 4: Rules & Payment**:
    - Checkboxes for payment methods: `cash_on_delivery`, `bkash_manual`, `nagad_manual`, `bank_transfer`, `payment_link`.
    - Multi-line text notes for each payment method (`payment_note_*`).
    - Custom policies: `tone`, `languages`, `currency`, `never_promise`, `handoff_triggers`, policy URLs (`refund_policy_url`, `cancellation_policy_url`, `delivery_policy_url`).

---

## 5. `/sales/offerings/new/` — Offering Editor
- **Observed Title**: `New offering | AI Sales Agent` [OBSERVED_LIVE]
- **Key Headings**: `What it is`, `Kind, price and availability`, `What the customer chooses`, `Options`, `Quantity per order`, `Intakes and seats`, `Slot rules`, `Party size`, `Opening hours`, `What to collect`
- **Live Form Fields (`POST /sales/offerings/new/`)**:
  - `name`: Text (Title)
  - `short_description`, `description`, `tags`: Text / Textarea
  - `sku`: Stock Keeping Unit
  - `external_ref`: Identifier on external merchant system
  - `image_url`: Thumbnail image URL
  - `external_url`: Direct page link
  - `price`: Numeric base price
  - `currency`: Currency code (BDT, USD, EUR, GBP)
  - `offering_kind`: Dropdown with 7 kinds
  - `availability_mode`: Dropdown with 5 modes
  - `fulfilment_kind`: Dropdown with 5 fulfillment options
  - `stock_quantity`, `track_inventory`: Inventory controls
  - `quote_required`: Checkbox
  - `is_active`: Checkbox active toggle

---

## 6. `/sales/suggestions/` — Needs Your Review ("Find What I Sell")
- **Observed Title**: `Needs your review | AI Sales Agent` [OBSERVED_LIVE]
- **Purpose**: AI suggestion review queue that extracts offerings from uploaded knowledge files.
- **Observed Controls**: Scan trigger button, suggested offering review table, edit price/kind modal, one-click approve action.

---

## 7. `/lead-capture/dashboard/` — Auto Lead Capture
- **Observed Title**: `Auto Lead Capture | ABC` [OBSERVED_LIVE]
- **Purpose**: Anonymous website visitor tracking dashboard. Visualizes interest scores, page view durations, and converted leads identified via widget interactions.

---

## 8. `/lead-engagement/settings/` — AI Lead Engagement Agent
- **Observed Title**: `AI Lead Engagement Agent` [OBSERVED_LIVE]
- **Purpose**: Autonomous email follow-up configuration.
- **Observed Controls**: Mailbox assignment selector, agent identity fields, follow-up cadence rules, operating mode toggle (`off`, `draft_only`, `autonomous`), and suppression list.

---

## 9. `/support/tickets/` — Support Tickets Desk
- **Observed Title**: `Support Tickets` [OBSERVED_LIVE]
- **Purpose**: In-app ticketing desk for issues escalated from live chats.
- **Columns**: `Ticket ID`, `Customer Email`, `Subject`, `Priority` (`low`, `medium`, `high`, `urgent`), `Status` (`open`, `in_progress`, `resolved`, `closed`), `Created Date`.
- **Observed Actions**: Create Ticket (`/support/tickets/create/`), Filter by Priority, Reply via Mailbox.

---

## 10. `/chat/widget/` — Website Chat Widget
- **Observed Title**: `Widget Integration | ABC` [OBSERVED_LIVE]
- **Live Controls**:
  - Snippet copy container (`<script src=".../widget/embed.js" data-key="ca_..."></script>`).
  - Allowed domains textarea (origin restriction whitelist).
  - Theme hex color picker, avatar logo upload, custom greeting title, and position toggle (bottom-right / bottom-left).

---

## 11. `/integrations/meta/` — Social Channels Meta Hub
- **Observed Title**: `Social Channels - Central AI` [OBSERVED_LIVE]
- **Observed Component Cards**:
  - **Facebook Messenger Card**: Page ID, Meta OAuth connect button.
  - **Instagram Direct Card**: Instagram Professional account linking status.
  - **WhatsApp Cloud API Card**: WABA ID, Phone Number ID, Permanent System User Token.

---

## 12. `/leads/integrations/` — Lead Integrations & Odoo CRM
- **Observed Title**: `Lead integrations | Central AI` [OBSERVED_LIVE]
- **Live Form Controls**:
  - `odoo_host`: URL of Odoo ERP instance.
  - `odoo_db`: Target Odoo database name.
  - `odoo_username`: API user email.
  - `odoo_api_key`: Secret API key or password.
  - Field mapping dropdowns to Odoo `crm.lead` model.

---

## 13. `/accounts/team/` — Team Management
- **Observed Title**: `Team Management` [OBSERVED_LIVE]
- **Data Table**: Columns `User`, `Email`, `Role`, `Joined`, `Actions`. Active user: `zesan @zesan` (Role: `Admin`).
- **Invite Form**: Email input, Role dropdown (`Admin`, `Operator`), Granular access scopes checkboxes.

---

## 14. `/accounts/security/api-keys/` — Security & API Keys
- **Observed Title**: `API Keys - Central AI` [OBSERVED_LIVE]
- **Purpose**: Key generation and domain whitelist management.
- **Controls**: Widget public key display (`ca_...`), External API secret key display (`cax_...`), Regenerate key button, Allowed domain addition form.

---

## 15. `/billing/usage/` — Usage Dashboard
- **Observed Title**: `Usage Dashboard` [OBSERVED_LIVE]
- **Active Plan Badge**: `Test Drive`
- **Observed Metered Units**:
  - Monthly message consumption progress bar.
  - Knowledge records storage meter (300 record limit).
  - Dashboard users quota (1 user limit).
  - Upgrade CTA linking to `/billing/checkout/starter/`, `/growth/`, `/scale/`.

---

## 16. `/chat/api/docs/` — Tenant-Facing API Reference
- **Observed Title**: `API Reference - Central AI` [OBSERVED_LIVE]
- **Live Content**: Contains 19 reference specification tables covering Chat API (`/chat/api/v1/`), Knowledge Sync API (`/knowledge/api/v1/sync/`), Lead Conversion API (`/api/v1/leads/conversions/`), UCP Commerce API (`catalog.search`, `quote.calculate`, `commit.create`), RFC 9421 signature verification, and idempotency error codes.
"""

with open(os.path.join(BASE_DIR, "03_DASHBOARD_SCREEN_CATALOG.md"), "w", encoding="utf-8") as f:
    f.write(screen_catalog_content.strip() + "\n")
print("Written: 03_DASHBOARD_SCREEN_CATALOG.md")
