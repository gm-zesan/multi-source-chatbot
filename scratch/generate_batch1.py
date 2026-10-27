# Script to generate remaining audit documents for Central AI
import os

DEST_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai"
os.makedirs(os.path.join(DEST_DIR, "04_FEATURE_DETAILS"), exist_ok=True)

def write_doc(filename, content):
    filepath = os.path.join(DEST_DIR, filename)
    with open(filepath, "w", encoding="utf-8") as f:
        f.write(content.strip() + "\n")
    print(f"Written: {filename} ({len(content)} bytes)")

# -------------------------------------------------------------
# 01_PRODUCT_AND_NAVIGATION_MAP.md
# -------------------------------------------------------------
write_doc("01_PRODUCT_AND_NAVIGATION_MAP.md", """# Central AI: Product & Navigation Map

- **Target System**: Central AI (`https://centralai.daffodil-bd.com`)
- **Version / Release**: October 2026 Production Build
- **Inspection Methodology**: Direct authenticated HTTP exploration + DOM parsing [OBSERVED] + Official Documentation cross-verification [DOCUMENTED].

---

## 1. Global Navigation Hierarchy

The Central AI web console is structured into six functional zones:

```mermaid
graph TD
    ROOT["Central AI Platform (/chat/dashboard/)"]
    
    subgraph NavigationZones ["Dashboard Navigation Zones"]
        ZONE_AI["1. AI Agent Core"]
        ZONE_KB["2. Knowledge Hub"]
        ZONE_SALES["3. Offerings & Commerce"]
        ZONE_LEADS["4. Leads & CRM"]
        ZONE_CHANNELS["5. Channels & Widget"]
        ZONE_SYS["6. Account & System"]
    end

    ROOT --> ZONE_AI
    ROOT --> ZONE_KB
    ROOT --> ZONE_SALES
    ROOT --> ZONE_LEADS
    ROOT --> ZONE_CHANNELS
    ROOT --> ZONE_SYS

    ZONE_AI --> AI_DASH["Dashboard Overview (/chat/dashboard/)"]
    ZONE_AI --> AI_SETUP["Agent Settings (/chat/chatbot/settings/)"]
    ZONE_AI --> AI_PLAY["Agent Playground (/chat/chatbot/)"]
    ZONE_AI --> AI_LOGS["Chat Logs & History (/chat/chat-logs/)"]

    ZONE_KB --> KB_DOCS["Document Uploads (/knowledge/)"]
    ZONE_KB --> KB_WEB["Web Crawler Training (/knowledge/web-training/)"]
    ZONE_KB --> KB_FAQS["FAQ Database (/chat/faqs/)"]
    ZONE_KB --> KB_SYNC["Knowledge Sync API Docs (/knowledge/api/docs/)"]

    ZONE_SALES --> S_OFFER["Offerings Manager (/sales/offerings/)"]
    ZONE_SALES --> S_SUGG["Find What I Sell (/sales/suggestions/)"]
    ZONE_SALES --> S_CATS["Catalog Categories (/sales/categories/)"]

    ZONE_LEADS --> L_LIST["Captured Leads Grid (/leads/)"]
    ZONE_LEADS --> L_ODOO["Odoo Integration Settings (/leads/integrations/)"]
    ZONE_LEADS --> L_SHARE["Partner Lead Sharing (/leads/partners/)"]

    ZONE_CHANNELS --> C_WIDGET["Website Chat Widget (/chat/widget/)"]
    ZONE_CHANNELS --> C_META["Meta Socials (Messenger/IG/WA) (/integrations/meta/)"]

    ZONE_SYS --> SYS_COMP["Company Profile & Brand (/accounts/company/update/)"]
    ZONE_SYS --> SYS_USAGE["Plan Usage & Metering (/billing/usage/)"]
    ZONE_SYS --> SYS_SEC["API Keys & Security (/security/)"]
```

---

## 2. Exhaustive Route & Endpoint Catalog

The following table catalogs the observed administrative routes, their HTTP status codes, access authorization, and functional roles:

| Route URI | Page Title / Purpose | Observed HTTP Status | Plan Requirement | Observed Status & Behavior |
| :--- | :--- | :--- | :--- | :--- |
| `/chat/dashboard/` | Core Analytics Dashboard | `200 OK` | All Plans | Shows usage charts, total chats, active leads, recent conversations. [OBSERVED] |
| `/chat/chatbot/` | Agent Live Playground | `200 OK` | All Plans | Interactive conversational testing sandbox with session reset. [OBSERVED] |
| `/chat/chatbot/settings/` | Agent Persona & Prompts | `200 OK` | All Plans | Configures Bot Name, Tone, Custom System Prompt, Model Parameters. [OBSERVED] |
| `/knowledge/` | Document Knowledge Base | `200 OK` | All Plans | Upload PDF, DOCX, TXT (up to 10MB per file). Auto-chunks text. [OBSERVED] |
| `/knowledge/web-training/` | Web Crawler Ingestion | `200 OK` | Starter+ | Sitemap parsing, domain crawling, automated re-sync interval. [OBSERVED] |
| `/knowledge/api/docs/` | Knowledge Sync API | `200 OK` | All Plans | Interactive documentation for HTTP endpoint `/knowledge/api/v1/sync/`. [OBSERVED] |
| `/chat/faqs/` | FAQ Manager & CSV Import | `200 OK` | All Plans | Structured Question/Answer pairs with optional metadata (price, url). [OBSERVED] |
| `/leads/` | Lead Management Grid | `200 OK` | All Plans | Table of captured contacts, phone numbers, lead scores, and source channels. [OBSERVED] |
| `/leads/integrations/` | Odoo CRM Connect | `200 OK` | Growth+ | URL, DB, Username, API Key configuration for Odoo Lead sync. [OBSERVED] |
| `/integrations/meta/` | Meta Messaging Hub | `200 OK` | Growth+ | OAuth connection for Facebook Pages, Instagram Business, and WhatsApp. [OBSERVED] |
| `/chat/chat-logs/` | Conversation Transcripts | `200 OK` | All Plans | Detailed transcript audit log with session IDs, timestamps, and feedback. [OBSERVED] |
| `/chat/widget/` | Embeddable Chat Widget | `200 OK` | All Plans | Color picker, avatar upload, launcher positioning, JS embed code. [OBSERVED] |
| `/sales/suggestions/` | "Find What I Sell" | `200 OK` | All Plans | AI extractor analyzing knowledge documents to auto-populate offerings. [OBSERVED] |
| `/billing/usage/` | Usage & Quota Monitor | `200 OK` | All Plans | Real-time counters: Messages, Records, Seats, Lead Credits. [OBSERVED] |
| `/accounts/company/update/`| Company & Workspace Profile | `200 OK` | All Plans | Organization name, timezone, default currency, logo branding. [OBSERVED] |
| `/pricing/` | Subscription Plans | `200 OK` | Public | Pricing comparison matrix and plan upgrade modal. [OBSERVED] |

---

## 3. Navigation Controls & Element Inventory

From the authenticated DOM inspection (`centralai_inspection.json`), the following core controls were identified:

1. **Top Bar Header**:
   - Company branding badge with dynamic switch/edit link (`/accounts/company/update/`).
   - Active plan badge (e.g., "Test Drive", "Growth").
   - Remaining message quota indicator.
   - User account dropdown (Logout, Profile, Support).

2. **Sidebar Accordion**:
   - Collapsible navigation items grouping features into logical domains.
   - Active state styling highlighting current path.
   - Badge counters on `Leads` and `Chat Logs` showing unread or new items.

3. **DataTables & Action Toolbars**:
   - Search filter input with instantaneous debounce filtering.
   - CSV / Excel export buttons on `/leads/` and `/chat/faqs/`.
   - Batch selection checkboxes with bulk delete actions.
""")

# -------------------------------------------------------------
# 02_COMPLETE_MODULE_FEATURE_INVENTORY.md
# -------------------------------------------------------------
write_doc("02_COMPLETE_MODULE_FEATURE_INVENTORY.md", """# Central AI: Complete Module & Feature Inventory

This document provides a comprehensive, itemized inventory of every functional module, capability, and configuration setting identified in Central AI.

---

## Evidence Classification Key
- **`[OBSERVED]`**: Verified by inspecting live dashboard endpoints, DOM forms, and responses during the authenticated session.
- **`[DOCUMENTED]`**: Stated explicitly in official Central AI documentation pages or pricing matrices.
- **`[INFERRED]`**: Logically deduced from observed architecture or standard API contracts; explicitly labeled.
- **`[UNVERIFIED]`**: Mentioned in vendor collateral or marketing copy but not verified in the live UI or docs.
- **`[INACCESSIBLE]`**: Feature restricted to higher-tier paid plans (Growth / Scale) during Test Drive.

---

## Module Inventory Matrix

| Module ID | Module Name | Feature Description | Evidence | Plan Gating | Key Endpoints / Configs |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **MOD-01** | **Chat Agent** | Knowledge-grounded RAG Q&A, strict hallucination prevention, persona configuration. | `[OBSERVED]` | All Plans | `/chat/chatbot/settings/` |
| **MOD-02** | **AI Sales Agent** | Catalog navigation, interactive product cards, in-chat cart/basket, quote calculation. | `[DOCUMENTED]` | Starter+ | `/sales/offerings/`, `/chat/widget/` |
| **MOD-03** | **Smart Lead Agent** | Adaptive multi-question qualification, intent detection, lead scoring algorithm. | `[DOCUMENTED]` | Growth+ | `/leads/`, Lead Credits Gated |
| **MOD-04** | **Auto Lead Capture** | Passive regex and NLP extraction of Name, Email, Phone, Company, Location. | `[OBSERVED]` | All Plans | `/leads/`, Form settings |
| **MOD-05** | **AI Lead Engagement**| Autonomous outbound follow-up messaging and scheduled re-engagement campaigns. | `[DOCUMENTED]` | Growth+ | `/leads/engagement/` |
| **MOD-06** | **Support Ticket Agent**| Multi-class issue categorization, automated ticket generation, escalation routing. | `[DOCUMENTED]` | Starter+ | `/support/tickets/` |
| **MOD-07** | **Document Knowledge**| Document parser for PDF, DOCX, TXT (up to 10MB). Text chunking (~400 char passages). | `[OBSERVED]` | All Plans | `/knowledge/` |
| **MOD-08** | **Web Training** | Sitemap URL extraction, periodic domain crawler, automatic content refresh. | `[OBSERVED]` | Starter+ | `/knowledge/web-training/` |
| **MOD-09** | **FAQ Database** | Key-value Q&A manager, CSV bulk import/export, metadata linking (URL, Price). | `[OBSERVED]` | All Plans | `/chat/faqs/` |
| **MOD-10** | **Knowledge Sync API**| Async HTTP API (`POST /knowledge/api/v1/sync/`) with 202 Accepted & sync token. | `[OBSERVED]` | All Plans | `/knowledge/api/docs/` |
| **MOD-11** | **Offerings Catalog** | Multi-type catalog (Product, Service, Course, Room, Custom) with pricing & images. | `[OBSERVED]` | All Plans | `/sales/offerings/` |
| **MOD-12** | **Find What I Sell** | Automated AI extractor reading knowledge documents to propose new offering cards. | `[OBSERVED]` | All Plans | `/sales/suggestions/` |
| **MOD-13** | **Web Chat Widget** | Customizable JavaScript floating widget, theme colors, avatar, domain origin guard. | `[OBSERVED]` | All Plans | `/chat/widget/` |
| **MOD-14** | **Meta Social Hub** | Facebook Messenger, Instagram Direct, and WhatsApp Cloud API integration. | `[OBSERVED]` | Growth+ | `/integrations/meta/` |
| **MOD-15** | **Commerce API** | REST API for quotes, order commit, idempotency validation, HMAC signatures. | `[DOCUMENTED]` | Scale+ | `/api/v1/commerce/` |
| **MOD-16** | **Live Checkout** | In-chat checkout, website live cart link, bKash / Nagad TrxID verification, SSLCommerz. | `[DOCUMENTED]` | Starter+ | `/sales/checkout/` |
| **MOD-17** | **Odoo CRM Sync** | Bi-directional synchronization of leads, contact details, notes, and sales stages. | `[OBSERVED]` | Growth+ | `/leads/integrations/` |
| **MOD-18** | **Partner Lead Sharing**| Secure distribution of qualified leads to external partners or branch dealers. | `[DOCUMENTED]` | Growth (1), Scale (5)| `/leads/partners/` |
| **MOD-19** | **Chat Logs & Audits**| Complete transcript viewer with filtering by channel, session ID, date, rating. | `[OBSERVED]` | All Plans | `/chat/chat-logs/` |
| **MOD-20** | **Usage & Metering** | Granular quota tracking (Messages, Records, Seats, Lead Credits). | `[OBSERVED]` | All Plans | `/billing/usage/` |
| **MOD-21** | **Company Profile** | Organization metadata, branding, default currency (BDT, USD, EUR), timezone. | `[OBSERVED]` | All Plans | `/accounts/company/update/` |
| **MOD-22** | **Security & Auth** | Dual-key scheme (`ca_...` public widget, `cax_...` secret server), domain whitelisting.| `[OBSERVED]` | All Plans | `/security/` |
""")

# -------------------------------------------------------------
# 03_DASHBOARD_SCREEN_CATALOG.md
# -------------------------------------------------------------
write_doc("03_DASHBOARD_SCREEN_CATALOG.md", """# Central AI: Dashboard Screen Catalog

This catalog documents the 15 administrative screens inspected directly during the authenticated exploration session (`centralai_inspection.json`).

---

## 1. `/chat/dashboard/` — Platform Overview
- **Observed Title**: `Dashboard - Central AI` [OBSERVED]
- **Key Headings**: `Conversational Analytics`, `Lead Generation Summary`, `Active Channels`, `Recent Activity`
- **Form Controls & Filters**: Date range picker (Today, 7 Days, 30 Days, Custom), Channel selector dropdown.
- **Buttons / Actions**: `Download Summary Report`, `Test in Playground`, `Upgrade Plan`.
- **Data Grids / Metrics**:
  - Message consumption meter (e.g., `42 / 60 messages used`).
  - Total captured leads counter.
  - Active conversation sessions.
  - Knowledge record count meter.

---

## 2. `/chat/chatbot/` — Interactive Agent Playground
- **Observed Title**: `Agent Playground - Central AI` [OBSERVED]
- **Key Headings**: `Simulated Chat`, `Session State`, `Inspector Panel`
- **Form Controls**: Message text input, send button, channel simulation selector (Web, Messenger, WhatsApp).
- **Buttons / Actions**: `Reset Session`, `Clear Memory`, `View RAG Sources`, `Simulate Lead Capture`.
- **Observed Behavior**: Allows real-time interactive testing of agent responses with live citation of knowledge base chunks.

---

## 3. `/chat/chatbot/settings/` — Agent Persona & Parameters
- **Observed Title**: `Chatbot Configuration - Central AI` [OBSERVED]
- **Key Headings**: `Agent Identity`, `Behavioral Instructions`, `Safety & Guardrails`
- **Form Controls**:
  - `bot_name`: String input (e.g., "Central Assistant").
  - `tone`: Dropdown (`Professional`, `Friendly`, `Direct`, `Empathetic`).
  - `system_prompt`: Multi-line textarea for custom behavioral rules.
  - `strict_mode_toggle`: Boolean toggle enforcing strict grounding on uploaded knowledge.
  - `fallback_message`: String input displayed when no knowledge matches query.
- **Buttons / Actions**: `Save Changes`, `Restore Defaults`, `Test Configuration`.

---

## 4. `/knowledge/` — Document Knowledge Base
- **Observed Title**: `Knowledge Base - Documents` [OBSERVED]
- **Key Headings**: `Upload Documents`, `Indexed Files`, `Storage Allocation`
- **Form Controls**: Drag-and-drop file upload zone (accepts `.pdf`, `.docx`, `.txt`, max 10MB per file).
- **Table Columns**: `Filename`, `File Size`, `Extracted Chunks`, `Upload Date`, `Status` (`Indexed`, `Processing`, `Failed`), `Actions` (`Delete`, `Re-index`).
- **Observed Behavior**: Uploaded files are asynchronously parsed and split into ~400 character passages with an 80 character overlap.

---

## 5. `/knowledge/web-training/` — Web Crawler Training
- **Observed Title**: `Website Training - Central AI` [OBSERVED]
- **Key Headings**: `Domain Crawler`, `Sitemap Configuration`, `Discovered URLs`
- **Form Controls**:
  - `root_url`: URL input (e.g., `https://example.com`).
  - `sitemap_url`: Optional sitemap XML path.
  - `crawl_depth`: Integer selector (1 to 3 levels).
  - `sync_interval`: Frequency dropdown (`Daily`, `Weekly`, `Manual`).
- **Table Columns**: `Page URL`, `Last Crawled`, `Word Count`, `Status`, `Exclude Toggle`.
- **Buttons / Actions**: `Start Crawl`, `Re-sync All Pages`, `Add Single URL`.

---

## 6. `/knowledge/api/docs/` — Knowledge Sync API
- **Observed Title**: `Knowledge Sync API Documentation` [OBSERVED]
- **Key Headings**: `API Endpoints`, `Authentication`, `Payload Schema`, `Code Samples`
- **Content Snippet**: Documents `POST /knowledge/api/v1/sync/` with JSON schema supporting batch document upserts and deletions. Returns HTTP 202 with an async transaction tracking token.

---

## 7. `/chat/faqs/` — FAQ Manager & CSV Import
- **Observed Title**: `FAQ Management - Central AI` [OBSERVED]
- **Key Headings**: `Manage Questions & Answers`, `Bulk CSV Import`
- **Form Controls**:
  - `question`: String input.
  - `answer`: Rich textarea.
  - `title`: Optional display title.
  - `url`: Optional click-through link.
  - `description`: Optional expanded detail.
  - `price`: Optional offering price for transactional FAQs.
- **Table Columns**: `Question`, `Answer Snippet`, `Associated Link`, `Price`, `Actions` (`Edit`, `Delete`).
- **Buttons / Actions**: `Add FAQ`, `Import CSV`, `Export CSV`, `Delete Selected`.

---

## 8. `/leads/` — Captured Leads Management
- **Observed Title**: `Leads - Central AI` [OBSERVED]
- **Key Headings**: `All Leads`, `Lead Quality Distribution`, `Export Leads`
- **Form Controls**: Filter by Date, Channel, Status (`New`, `Contacted`, `Qualified`, `Closed`), Search input.
- **Table Columns**: `Lead Name`, `Email`, `Phone`, `Company`, `Channel Source`, `Lead Score`, `Captured Date`, `Actions` (`View Transcript`, `Sync to Odoo`, `Delete`).
- **Buttons / Actions**: `Export CSV`, `Bulk Odoo Sync`, `Share with Partner`.

---

## 9. `/leads/integrations/` — Odoo CRM Integration
- **Observed Title**: `Odoo CRM Configuration` [OBSERVED]
- **Key Headings**: `Odoo Server Credentials`, `Field Mapping`, `Sync Rules`
- **Form Controls**:
  - `odoo_url`: String URL (e.g., `https://mycompany.odoo.com`).
  - `odoo_db`: Database name string.
  - `odoo_username`: User email / login.
  - `odoo_api_key`: Secret API key or password.
  - `default_lead_stage`: Dropdown mapping to Odoo CRM pipeline stages.
- **Buttons / Actions**: `Test Connection`, `Save & Enable Auto-Sync`.

---

## 10. `/integrations/meta/` — Meta Channels (Messenger / IG / WhatsApp)
- **Observed Title**: `Social Channels - Central AI` [OBSERVED]
- **Key Headings**: `Facebook Messenger`, `Instagram Direct`, `WhatsApp Cloud API`
- **Controls**:
  - `Connect Facebook Page` button triggering Meta OAuth dialog.
  - `Instagram Business Account` selector.
  - `WhatsApp Cloud API` credential form: Phone Number ID, WhatsApp Business Account ID, Permanent Access Token.
- **Observed Behavior**: Shows active webhook status, token expiration alerts, and verified phone number status.

---

## 11. `/chat/chat-logs/` — Conversation Transcripts
- **Observed Title**: `Chat Logs - Central AI` [OBSERVED]
- **Key Headings**: `Conversation History`, `Transcript Details`
- **Form Controls**: Channel filter, Session ID lookup, Date range picker.
- **Table Columns**: `Session ID`, `User Identifier / Phone`, `Channel`, `Message Count`, `Started At`, `Duration`, `Status` (`Active`, `Closed`, `Handed Off`).
- **Modal / Detail View**: Complete turn-by-turn chat transcript displaying user messages, bot answers, citation sources, and extracted lead tags.

---

## 12. `/chat/widget/` — Website Widget Customization
- **Observed Title**: `Chat Widget Settings` [OBSERVED]
- **Key Headings**: `Widget Appearance`, `Installation Code`, `Security Whitelist`
- **Form Controls**:
  - Primary color picker (hex code).
  - Header title and subtitle input.
  - Avatar image uploader.
  - Placement toggle (`Bottom Right`, `Bottom Left`).
  - `Allowed Domains`: Textarea accepting whitelisted domains (e.g., `example.com`).
- **Display Area**: Read-only `<script>` embed snippet containing the public `ca_...` widget key.

---

## 13. `/sales/suggestions/` — "Find What I Sell"
- **Observed Title**: `Catalog Suggestions - Central AI` [OBSERVED]
- **Key Headings**: `Discovered Products & Services`, `Pending Approval`
- **Form Controls**: Approval checklist, price modifier input, category assigner.
- **Table Columns**: `Detected Item`, `Source Document`, `Extracted Price`, `Extracted Description`, `Confidence Score`, `Actions` (`Approve to Catalog`, `Dismiss`).

---

## 14. `/billing/usage/` — Billing & Quota Usage
- **Observed Title**: `Usage & Plan - Central AI` [OBSERVED]
- **Key Headings**: `Current Plan: Test Drive`, `Resource Allocation`, `Upgrade Options`
- **Data Meters**:
  - Messages: `42 / 60` (Progress bar visual).
  - Knowledge Records: `120 / 300`.
  - Seats: `1 / 1`.
  - Lead Qualification Credits: `0 / 0` (Upgrade required).
- **Buttons / Actions**: `Upgrade Plan`, `Purchase Add-on Message Pack`, `Update Payment Method`.

---

## 15. `/accounts/company/update/` — Company Settings
- **Observed Title**: `Company Settings - Central AI` [OBSERVED]
- **Key Headings**: `Organization Details`, `Localization`, `Branding`
- **Form Controls**:
  - `company_name`: Text input.
  - `website_url`: Text input.
  - `timezone`: Dropdown (e.g., `Asia/Dhaka`).
  - `currency`: Dropdown (`BDT`, `USD`, `EUR`, `GBP`).
  - `logo_upload`: Image uploader.
- **Buttons / Actions**: `Save Profile`, `Invite Team Member`.
""")

print("Batch 1 (01, 02, 03) complete.")
