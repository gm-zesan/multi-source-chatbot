import os

BASE_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai"
FEAT_DIR = os.path.join(BASE_DIR, "04_FEATURE_DETAILS")

def write_feat(filename, content):
    path = os.path.join(FEAT_DIR, filename)
    with open(path, "w", encoding="utf-8") as f:
        f.write(content.strip() + "\n")
    print(f"Written: {filename} ({len(content)} bytes)")

# --------------------------------------------------------------------------
# 04_FEATURE_DETAILS/02_AI_SALES_AGENT.md
# --------------------------------------------------------------------------
sales_agent_content = """# Feature Detail: AI Sales Agent & In-Chat Commerce

- **Feature ID**: `MOD-02`
- **Module Name**: AI Sales Agent
- **Audit Status**: Verified Active `[OBSERVED_LIVE]`
- **Primary Routes**: `/sales/` (Configuration), `/sales/offerings/` (Catalog), `/chat/chatbot/` (Playground)
- **Plan Availability**: All Plans (Catalog Navigation); Starter+ (Live In-Chat Commerce & Checkout)

---

## 1. Overview & Business Purpose
The AI Sales Agent transforms standard customer support conversations into active sales consultations. Instead of simply generating textual answers, it parses purchase intent, queries structured offerings, presents visual interactive cards with prices and images, and manages shopping baskets directly inside the conversation.

---

## 2. Live Observed Settings Architecture (`/sales/`)

Live inspection of `/sales/` revealed a comprehensive 8-step administrative workflow:

### Step 1: Company Type (`company_type`)
Selects the core industry profile from 9 available business types:
- Retail & Physical Products, Professional Services, Education & Courses, Hospitality & Venues, Healthcare & Clinics, Software & SaaS, Salon & Beauty, Event Ticketing, Bespoke Consulting.

### Step 2: What You Offer (`offering_kinds`)
Enables and custom-labels up to 7 distinct offering models:
1. `Product` (`label_product`): Physical merchandise with inventory tracking.
2. `Service` (`label_service`): Professional or hourly services.
3. `Course Intake` (`label_course_intake`): Educational courses with semester intakes and seat quotas.
4. `Appointment` (`label_appointment`): Time-slot reservations.
5. `Event Date` (`label_event_date`): Fixed-date event admissions.
6. `Plan` (`label_plan`): Recurring subscription tiers.
7. `Custom` (`label_custom`): User-defined schemas.

### Step 3: How Customers Complete (Completion Modes)
Configured independently for each offering kind:
- **`Handled in Central AI`**: The bot collects customer details directly in chat, calculates totals, and takes the order.
- **`Live checkout on your site`**: The bot passes the cart to the merchant's external checkout URL template (`checkout_link_template`: `https://yourshop.com/cart/add?items={items}&ref={ref}`).
- **`Created in our system`**: Headless Commerce API mode connecting to external ERPs (`profile_url`, `api_key`).

### Step 4: Rules & Payment Settings
- **Supported Payment Methods**:
  - `cash_on_delivery`: Cash on delivery with custom policy note (`payment_note_cash_on_delivery`).
  - `bkash_manual`: Manual bKash transfer with merchant phone number note (`payment_note_bkash_manual`).
  - `nagad_manual`: Manual Nagad transfer with merchant phone number note (`payment_note_nagad_manual`).
  - `bank_transfer`: Direct bank settlement instructions (`payment_note_bank_transfer`).
  - `payment_link`: Dynamic payment gateway redirect URL (`payment_link_url`).
- **Policy Enforcement**:
  - `tone`: Custom sales persona tone guidance.
  - `languages`: Permitted conversational languages.
  - `currency`: Default currency code (BDT, USD, EUR, GBP).
  - `never_promise`: Guardrail directives detailing forbidden commitments.
  - `handoff_triggers`: Keywords triggering human operator escalation.
  - `refund_policy_url`, `cancellation_policy_url`, `delivery_policy_url`: Policy reference links.

---

## 3. Step-by-Step Functional Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Shopper as Customer in Chat
    participant Agent as AI Sales Agent Runtime
    participant Catalog as Offerings Database
    participant Cart as Session Basket
    participant Checkout as Live Checkout Gateway

    Shopper->>Agent: "What web development packages do you offer?"
    Agent->>Catalog: Filter active offerings (Kind = Course / Service)
    Catalog-->>Agent: Return matching Offerings (SKU: WEB-101, Price: $150)
    Agent-->>Shopper: Render visual Offering Card with [Add to Cart] / [Checkout]
    Shopper->>Agent: Clicks [Add to Cart]
    Agent->>Cart: Append item (WEB-101, Qty: 1)
    Agent-->>Shopper: "Added! Basket total: $150. Ready to complete?"
    Shopper->>Agent: "Yes, checkout please"
    alt Completion Mode = Live Checkout on Site
        Agent-->>Shopper: Provide Link: https://yourshop.com/cart/add?items=WEB-101:1&ref=cai_...
        Shopper->>Checkout: Opens merchant site in new tab & pays
    else Completion Mode = Handled in Central AI
        Agent-->>Shopper: Collects Name, Phone, Address & displays bKash instructions
    end
```

---

## 4. Verification Status & Evidence
- **Observed Live**: Screen `/sales/` inspected with all form fields, dropdowns, and checkboxes [OBSERVED_LIVE].
- **Official Documentation**: Verified in `/docs/sales-agent/` [DOCUMENTED].
"""
write_feat("02_AI_SALES_AGENT.md", sales_agent_content)

# --------------------------------------------------------------------------
# 04_FEATURE_DETAILS/04_AUTO_LEAD_CAPTURE.md
# --------------------------------------------------------------------------
lead_capture_content = """# Feature Detail: Auto Lead Capture & Visitor Tracking

- **Feature ID**: `MOD-04`
- **Module Name**: Auto Lead Capture
- **Audit Status**: Verified Active `[OBSERVED_LIVE]`
- **Primary Routes**: `/lead-capture/dashboard/` (Dashboard), Client-Side Tracker API
- **Plan Availability**: Growth (500 leads/mo), Scale (2,000 leads/mo), Enterprise

---

## 1. Overview & Business Purpose
Auto Lead Capture bridges anonymous web browsing with identified CRM lead records. Rather than relying solely on active form submissions, it silently monitors visitor journey signals (pages visited, scroll depth, dwell time, form field focus) to calculate a live "Interest Score." When the visitor identifies themselves (e.g. typing an email in chat or submitting a form), the tracker binds their historical browsing journey to the new lead profile.

---

## 2. Live Dashboard Screen (`/lead-capture/dashboard/`)
- **Observed Title**: `Auto Lead Capture | ABC` [OBSERVED_LIVE]
- **Key Metrics**:
  - Active anonymous visitor count.
  - Converted leads counter.
  - High-intent visitor alert queue.
- **Journey Timeline**: Displays page-by-page dwell time for converted leads prior to identification.

---

## 3. Client-Side Visitor Tracker API

Inspected directly from `/chat/api/docs/` [OBSERVED_LIVE]:

| Method | Path | Parameter / Header | Description |
| :--- | :--- | :--- | :--- |
| `GET` | `/lead-capture/vid/` | `X-Site-Id: ca_...` | Generates a persistent visitor ID cookie. |
| `POST`| `/lead-capture/events/` | `X-Site-Id`, Event JSON | Tracks pageviews, scroll events, clicks. |
| `POST`| `/lead-capture/consent/`| `X-Site-Id`, Consent status | Records GDPR / local cookie consent state. |
| `POST`| `/lead-capture/identify/`| `X-Site-Id`, Email/Phone | Binds anonymous browsing history to identity. |

---

## 4. Verification Status & Evidence
- **Observed Live**: Screen `/lead-capture/dashboard/` inspected in authenticated session [OBSERVED_LIVE].
- **Live API Reference**: Tracker endpoints and error codes (`AUTH_REQUIRED`, `ORIGIN_NOT_ALLOWED`) verified in `/chat/api/docs/` [OBSERVED_LIVE].
- **Official Documentation**: Verified in `/docs/lead-capture/` [DOCUMENTED].
"""
write_feat("04_AUTO_LEAD_CAPTURE.md", lead_capture_content)

# --------------------------------------------------------------------------
# 04_FEATURE_DETAILS/06_SUPPORT_TICKET_AGENT.md
# --------------------------------------------------------------------------
support_ticket_content = """# Feature Detail: Support Ticket Agent & Helpdesk Desk

- **Feature ID**: `MOD-06`
- **Module Name**: Support Ticket Agent
- **Audit Status**: Verified Active `[OBSERVED_LIVE]`
- **Primary Routes**: `/support/tickets/` (Inbox), `/support/tickets/create/` (Manual Ticket), `/chat/chatbot/settings/` (Agent Settings)
- **Plan Availability**: Growth+, Scale

---

## 1. Overview & Business Purpose
The Support Ticket Agent identifies customer frustration, technical complaints, or billing issues during conversations and automatically raises structured support tickets. It ensures customers needing human intervention receive rapid email follow-up without manual transcription by chat operators.

---

## 2. In-App Support Desk (`/support/tickets/`)
- **Observed Title**: `Support Tickets` [OBSERVED_LIVE]
- **Data Table Columns**: `Ticket ID`, `Customer Email`, `Subject`, `Priority`, `Status`, `Created Date`.
- **Priority Tiers**: `low`, `medium`, `high`, `urgent`.
- **Status Lifecycle**: `open` -> `in_progress` -> `resolved` -> `closed`.

---

## 3. Agent Configuration (`/chat/chatbot/settings/`)
- `support_ticket_enabled`: Master switch toggle.
- `support_ticket_auto_detect`: NLP detection trigger recognizing complaint sentiment and escalation keywords.
- `support_ticket_instructions`: Custom directives guiding the agent on what customer issues warrant ticketing.
- `support_ticket_priority`: Default priority assignment.

---

## 4. Server API Integration (`/chat/api/docs/`)
- **List Tickets**: `GET /api/v1/support/tickets/` (`status`, `priority`, `since`, `limit`, `offset`).
- **Create Ticket**: `POST /api/v1/support/tickets/` (`email`, `subject`, `description`, `priority`).
- **Update Ticket**: `POST /api/v1/support/tickets/{ticket_id}/` (`status`, `priority`, `reply_message`).

---

## 5. Verification Status & Evidence
- **Observed Live**: Screens `/support/tickets/` and `/support/tickets/create/` inspected [OBSERVED_LIVE].
- **Live API Reference**: Endpoints and parameters verified in `/chat/api/docs/` [OBSERVED_LIVE].
"""
write_feat("06_SUPPORT_TICKET_AGENT.md", support_ticket_content)

# --------------------------------------------------------------------------
# 04_FEATURE_DETAILS/12_SERVER_APIS_AND_UCP.md
# --------------------------------------------------------------------------
server_apis_content = """# Feature Detail: Server APIs & Universal Commerce Protocol (UCP)

- **Feature ID**: `MOD-10`, `MOD-15`, `MOD-26`
- **Module Name**: Server-to-Server APIs & Headless Commerce Contract v1.3
- **Audit Status**: Verified Active `[OBSERVED_LIVE]`
- **Primary Route**: `/chat/api/docs/` (Tenant-Facing API Reference)
- **Plan Availability**: Scale, Enterprise

---

## 1. Overview & Architecture
Central AI exposes comprehensive server-to-server APIs for headless conversational AI, automated knowledge ingestion, lead conversion attribution, and bidirectional headless e-commerce.

---

## 2. Authentication & Key Architecture
- **Header**: `X-API-Key: cax_live_...` (Secret external API key).
- **Public Key Sunset**: Requests presenting the public widget key (`ca_...`) receive `HTTP 401 LEGACY_API_KEY_RETIRED`.

---

## 3. Server-to-Server Endpoints Specification

| Method | Endpoint Path | Credential | Origin Check | Functionality |
| :--- | :--- | :--- | :---: | :--- |
| `POST` | `/chat/api/v1/` | `X-API-Key` | Yes | Chat API: `send_message`, `get_history`, `get_sessions`, `new_session`. |
| `POST` | `/knowledge/api/v1/sync/` | `X-API-Key` | No | Knowledge Sync: Asynchronous ingestion of documents and offerings. |
| `POST` | `/api/v1/leads/conversions/` | `X-API-Key` | No | Lead Conversion API: Reports won revenue and conversion events. |
| `GET` | `/api/v1/leads/lookup/` | `X-API-Key` | No | Lead Lookup: Retrieves lead records by `lead_id`, `email`, or `phone`. |
| `GET` | `/api/v1/support/tickets/` | `X-API-Key` | No | Support Tickets API: Queries open/closed tickets. |

---

## 4. Universal Commerce Protocol (UCP) Contract v1.3

Central AI implements an inverted headless commerce contract where the **merchant's system hosts the operations** and Central AI invokes them:

```mermaid
sequenceDiagram
    autonumber
    participant CentralAI as Central AI Sales Engine
    participant MerchantServer as Merchant System (UCP Endpoint)

    CentralAI->>MerchantServer: GET /.well-known/ucp (Discovery Profile & Public Key)
    CentralAI->>MerchantServer: POST /catalog/search (Search offerings)
    CentralAI->>MerchantServer: POST /availability/check (Check stock/seats)
    CentralAI->>MerchantServer: POST /quotes (Calculate live totals with taxes)
    CentralAI->>MerchantServer: POST /orders (commit.create with Idempotency-Key)
    MerchantServer-->>CentralAI: HTTP 200 Order Created (continue_url for payment)
    MerchantServer->>CentralAI: POST /webhooks/commerce/ (Signed Status Event RFC 9421)
```

### Idempotency Error Handling
- `idempotency_conflict` (HTTP 409): Non-retryable write rejection (same key, different body).
- `idempotency_in_flight` (HTTP 409): Retryable (first request still running, send `Retry-After`).
- `storage_unavailable` (HTTP 503): Fail closed.

---

## 5. Verification Status & Evidence
- **Observed Live**: Complete 19-table specification verified at `/chat/api/docs/` [OBSERVED_LIVE].
- **Official Documentation**: Verified in `/docs/server-apis/` and `/docs/commerce-api/` [DOCUMENTED].
"""
write_feat("12_SERVER_APIS_AND_UCP.md", server_apis_content)

# --------------------------------------------------------------------------
# 04_FEATURE_DETAILS/13_MAILBOXES_AND_EMAIL_SETTINGS.md
# --------------------------------------------------------------------------
mailboxes_content = """# Feature Detail: Mailboxes Engine & Corporate Email Integration

- **Feature ID**: `MOD-20`
- **Module Name**: Corporate Mailboxes Engine
- **Audit Status**: Verified Active `[OBSERVED_LIVE]`
- **Primary Route**: `/accounts/company/mailboxes/`
- **Plan Availability**: Growth+, Scale

---

## 1. Overview & Business Purpose
The Mailboxes Engine manages bidirectional SMTP/IMAP email connectivity for the tenant. It powers two distinct automated systems:
1. **AI Lead Engagement**: Sends outbound follow-up email sequences and ingests incoming replies.
2. **Support Ticket Agent**: Dispatches ticket updates from company email addresses and converts customer email replies into ticket comments (checked every 3 minutes).

---

## 2. Configuration & Protocol Fields (`/accounts/company/mailboxes/`)
- **Connection Type**: Standard SMTP/IMAP or OAuth-connected business mailbox.
- **Sender Address**: Primary sending address (e.g. `support@yourdomain.com`).
- **Assignment**: Dedicated assignment to `Lead Engagement` or `Support Tickets`.
- **Verification Status**: Validates SMTP credentials and MX records prior to allowing `autonomous` mode.

---

## 3. Verification Status & Evidence
- **Observed Live**: Screen `/accounts/company/mailboxes/` inspected directly [OBSERVED_LIVE]. (Corrects prior 404 path `/accounts/mailboxes/`).
- **Official Documentation**: Verified in `/docs/lead-engagement/` and `/docs/support-agent/` [DOCUMENTED].
"""
write_feat("13_MAILBOXES_AND_EMAIL_SETTINGS.md", mailboxes_content)

# --------------------------------------------------------------------------
# 04_FEATURE_DETAILS/14_TEAM_AND_ACCESS_MANAGEMENT.md
# --------------------------------------------------------------------------
team_content = """# Feature Detail: Team & Access Management

- **Feature ID**: `MOD-21`
- **Module Name**: Team Management & RBAC
- **Audit Status**: Verified Active `[OBSERVED_LIVE]`
- **Primary Route**: `/accounts/team/`
- **Plan Availability**: Starter (4 seats), Growth (10 seats), Scale (20 seats)

---

## 1. Overview & Business Purpose
Team Management governs administrative access to the Central AI tenant console. Organizations can invite teammates, assign functional roles, and restrict access to sensitive business data (such as API keys or billing).

---

## 2. Live Observed Screen Architecture (`/accounts/team/`)
- **Observed Title**: `Team Management` [OBSERVED_LIVE]
- **Team Table**: Columns `User`, `Email`, `Role`, `Joined`, `Actions`.
- **Role Hierarchy**:
  - `Admin`: Full access across agents, knowledge, offerings, billing, and API keys.
  - `Operator`: Operational access to chat logs, support tickets, and leads.
- **Granular Access Scopes**:
  - Checkboxes governing specific module permissions (Agent Configuration, Knowledge Base, Offerings, Billing, API Keys).

---

## 3. Verification Status & Evidence
- **Observed Live**: Screen `/accounts/team/` inspected directly [OBSERVED_LIVE].
- **Pricing Verification**: Seat quotas verified in live `/pricing/` DOM [OBSERVED_LIVE].
"""
write_feat("14_TEAM_AND_ACCESS_MANAGEMENT.md", team_content)

# --------------------------------------------------------------------------
# 04_FEATURE_DETAILS/15_API_KEYS_AND_SECURITY_SETTINGS.md
# --------------------------------------------------------------------------
api_keys_content = """# Feature Detail: API Keys & Security Architecture

- **Feature ID**: `MOD-22`
- **Module Name**: Security & API Keys
- **Audit Status**: Verified Active `[OBSERVED_LIVE]`
- **Primary Route**: `/accounts/security/api-keys/`
- **Plan Availability**: All Plans

---

## 1. Overview & Business Purpose
Central AI enforces strict privilege segregation through a two-key credential scheme. The Security and API Keys console allows administrators to view and regenerate credentials and manage the mandatory Domain Origin Whitelist.

---

## 2. Live Observed Screen Architecture (`/accounts/security/api-keys/`)
- **Observed Title**: `API Keys - Central AI` [OBSERVED_LIVE] (Corrects prior 404 path `/security/`).
- **Widget Public Key (`ca_...`)**: Public client-side token embedded in web pages. Protected exclusively by the Domain Whitelist.
- **External API Secret Key (`cax_...`)**: Confidential server-to-server credential. Required for Chat API, Knowledge Sync, and Lead Conversion APIs.
- **Allowed Domains Manager**: Form accepting approved fully-qualified domain names. Empty list allows nothing [DOCUMENTED].
- **Regenerate Controls**: One-click regeneration button with immediate invalidation of legacy tokens.

---

## 3. Verification Status & Evidence
- **Observed Live**: Screen `/accounts/security/api-keys/` inspected directly [OBSERVED_LIVE].
- **Official Documentation**: Verified in `/docs/security/` [DOCUMENTED].
"""
write_feat("15_API_KEYS_AND_SECURITY_SETTINGS.md", api_keys_content)

print("Batch 2 complete.")
