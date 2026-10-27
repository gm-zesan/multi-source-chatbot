import os, re

BASE_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai"

def update_file(rel_path, fn):
    full_path = os.path.join(BASE_DIR, rel_path)
    with open(full_path, "r", encoding="utf-8") as f:
        content = f.read()
    new_content = fn(content)
    with open(full_path, "w", encoding="utf-8") as f:
        f.write(new_content)
    print(f"Updated: {rel_path} ({len(new_content)} bytes)")

# ----------------------------------------------------------------------
# 1. 00_EXECUTIVE_SUMMARY.md
# ----------------------------------------------------------------------
def fix_00(c):
    # Fix K=4 to 8 passages, fix quotas
    c = c.replace("3,000 | 1 | Web widget", "3,000 | 4 | Web widget")
    c = c.replace("3,000 | 4 | Web widget, Facebook Messenger, Auto Lead Capture, Web training.", "3,000 | 4 | Web widget, Facebook Messenger, Web training.")
    c = c.replace("10,000 | 10 | WhatsApp", "10,000 | 10 | WhatsApp")
    c = c.replace("50,000 | 20 | All Growth", "50,000 | 20 | All Growth")
    # Fix commerce description
    c = c.replace("bKash/Nagad TrxID recording, HMAC-signed Commerce API", "decoupled Live Checkout link ({items}, {ref}), signed order webhooks (Central-AI-Signature), HMAC Commerce API")
    c = c.replace("Total Documentation Files Written**: 25 markdown files", "Total Documentation Files Written**: 26 markdown files (including 15_LIVE_REVERIFICATION_REPORT.md)")
    return c

update_file("00_EXECUTIVE_SUMMARY.md", fix_00)

# ----------------------------------------------------------------------
# 2. 02_COMPLETE_MODULE_FEATURE_INVENTORY.md
# ----------------------------------------------------------------------
def fix_02(c):
    c = c.replace(
        "| **MOD-03** | **Smart Lead Agent** | Adaptive multi-question qualification, intent detection, lead scoring algorithm. | `[DOCUMENTED]` | Growth+ | `/leads/`, Lead Credits Gated |",
        "| **MOD-03** | **Smart Lead Agent** | Asynchronous daily batch summarization and categorization into up to 50 custom categories. | `[DOCUMENTED]` | Growth+ | `/chat/chatbot/settings/`, Daily 03:00 Run |"
    )
    c = c.replace(
        "| **MOD-04** | **Auto Lead Capture** | Passive regex and NLP extraction of Name, Email, Phone, Company, Location. | `[OBSERVED]` | All Plans | `/leads/`, Form settings |",
        "| **MOD-04** | **Auto Lead Capture** | Anonymous website visitor tracking and conversion upon identification. | `[DOCUMENTED]` | Growth (500/mo), Scale (2k/mo) | `/leads/`, Allowed Domains |"
    )
    c = c.replace(
        "| **MOD-05** | **AI Lead Engagement**| Autonomous outbound follow-up messaging and scheduled re-engagement campaigns. | `[DOCUMENTED]` | Growth+ | `/leads/engagement/` |",
        "| **MOD-05** | **AI Lead Engagement**| Automated email follow-up via connected company mailboxes (`draft_only` / `autonomous`). | `[DOCUMENTED]` | Growth+ Add-on | `/accounts/mailboxes/`, 1.5k credits/mo |"
    )
    c = c.replace(
        "| **MOD-06** | **Support Ticket Agent**| Multi-class issue categorization, automated ticket generation, escalation routing. | `[DOCUMENTED]` | Starter+ | `/support/tickets/` |",
        "| **MOD-06** | **Support Ticket Agent**| Notices customer issue in chat, collects email, creates ticket, email replies. | `[DOCUMENTED]` | Growth+ | `/support/tickets/`, Connected Mailbox |"
    )
    c = c.replace(
        "| **MOD-16** | **Live Checkout** | In-chat checkout, website live cart link, bKash / Nagad TrxID verification, SSLCommerz. | `[DOCUMENTED]` | Starter+ | `/sales/checkout/` |",
        "| **MOD-16** | **Live Checkout** | Decoupled cart redirect ({items}, {ref}) to merchant site; HMAC signed order webhook. | `[DOCUMENTED]` | Starter+ | Checkout URL Template, Webhook |"
    )
    return c

update_file("02_COMPLETE_MODULE_FEATURE_INVENTORY.md", fix_02)

# ----------------------------------------------------------------------
# 3. 04_FEATURE_DETAILS/01_CHAT_AGENT.md
# ----------------------------------------------------------------------
def fix_04_01(c):
    c = c.replace("Query embeddings (K=4, similarity > 0.72)", "Query embeddings (8 most relevant passages retrieved)")
    c = c.replace("Top K=4 chunks", "8 most relevant passages")
    return c

update_file("04_FEATURE_DETAILS/01_CHAT_AGENT.md", fix_04_01)

# ----------------------------------------------------------------------
# 4. 04_FEATURE_DETAILS/03_SMART_LEAD_AGENT.md
# ----------------------------------------------------------------------
def fix_04_03(c):
    new_doc = """# Feature Detail: Smart Lead Agent (Daily Batch Summarizer)

- **Feature ID**: `MOD-03`
- **Component**: Daily Batch Lead Categorization & Summarization Engine
- **Audit Status**: Verified Documented `[DOCUMENTED]`
- **Official Documentation Source**: `/docs/lead-agent/`
- **Plan Availability**: Growth ($50/mo — 3,000 credits/mo), Scale ($150/mo — 9,000 credits/mo)

---

## 1. Overview & Business Purpose
Contrary to conversational qualification chatbots that interrupt users with survey questions, the Central AI **Smart Lead Agent** operates as an **asynchronous daily batch summarization engine** `[DOCUMENTED]`.

It runs once per day in the background, reviewing all completed customer conversations from the past 24 hours that do not yet have a summary. It extracts what the customer wanted, categorizes the chat into up to 50 user-defined business categories (e.g., "Wholesale order", "Admission inquiry"), and logs the structured summary directly onto the `/leads/` dashboard.

Customers experience **zero friction** during live chats—they interact naturally with the Chat and Sales agents without being subjected to multi-turn qualification interviews `[DOCUMENTED]`.

---

## 2. Documented Execution Workflow

```mermaid
sequenceDiagram
    autonumber
    actor Customer as Website Visitor
    participant Chat as Chat Agent Runtime
    participant DB as Chat Logs Database
    participant Cron as Daily Batch Scheduler (Default: 03:00)
    participant LeadAgent as Smart Lead Agent
    participant LeadsTable as Leads Dashboard (/leads/)
    actor SalesRep as Human Sales Rep

    Customer->>Chat: Chats naturally about pricing, services, and requirements
    Chat->>DB: Persists conversation transcript
    Note over Cron,LeadAgent: Daily execution trigger (e.g. 03:00 Asia/Dhaka)
    Cron->>LeadAgent: Trigger daily batch lead summarization run
    LeadAgent->>DB: Query unsummarized chat transcripts
    LeadAgent->>LeadAgent: Synthesize conversation summary & assign category (1 of 50)
    LeadAgent->>LeadsTable: Insert/Update Lead with summary & category tags
    SalesRep->>LeadsTable: Reviews categorized leads and follows up
```

---

## 3. Configuration & Schedule Settings (`/chat/chatbot/settings/`)

| Configuration Parameter | Type | Default Value | Description |
| :--- | :--- | :--- | :--- |
| `run_time` | Time (HH:MM) | `03:00` | The daily scheduled execution time `[DOCUMENTED]`. |
| `timezone` | Timezone String | `Asia/Dhaka` | Organization timezone governing schedule `[DOCUMENTED]`. |
| `lead_categories` | Text (CSV) | None | Comma-separated list of up to 50 custom categories `[DOCUMENTED]`. |
| `is_active` | Boolean | `false` | Master toggle to enable/disable daily agent run `[DOCUMENTED]`. |

---

## 4. Quota Consumption & Credits
- **Metered Resource**: 1 Lead Agent Credit per summarized conversation `[DOCUMENTED]`.
- **Failure Resilience**: If AI summarization fails for a specific chat, the credit is refunded and the conversation is queued for re-processing in the next daily cycle `[DOCUMENTED]`.
- **Monthly Allocation**:
  - Test Drive: 0 credits.
  - Starter: 0 credits.
  - Growth: 3,000 credits / mo.
  - Scale: 9,000 credits / mo.

---

## 5. Verification Status & Evidence
- **Documented**: Official documentation topic `/docs/lead-agent/` explicitly details the once-daily execution model, 50-category limit, and credit accounting `[DOCUMENTED]`.
- **Re-Verification Finding**: Overturns prior audit claim of live in-chat BANT interviews.
"""
    return new_doc

update_file("04_FEATURE_DETAILS/03_SMART_LEAD_AGENT.md", fix_04_03)

# ----------------------------------------------------------------------
# 5. 04_FEATURE_DETAILS/04_AUTO_LEAD_CAPTURE.md
# ----------------------------------------------------------------------
def fix_04_04(c):
    c = c.replace("Plan Availability**: All Plans", "Plan Availability**: Growth (500 leads/mo), Scale (2,000 leads/mo), Enterprise [DOCUMENTED]")
    return c

update_file("04_FEATURE_DETAILS/04_AUTO_LEAD_CAPTURE.md", fix_04_04)

# ----------------------------------------------------------------------
# 6. 04_FEATURE_DETAILS/05_AI_LEAD_ENGAGEMENT.md
# ----------------------------------------------------------------------
def fix_04_05(c):
    new_doc = """# Feature Detail: AI Lead Engagement (Email Nurturing Add-On)

- **Feature ID**: `MOD-05`
- **Component**: Automated Email Lead Follow-up Engine
- **Audit Status**: Verified Documented `[DOCUMENTED]`
- **Official Documentation Source**: `/docs/lead-engagement/`
- **Plan Availability**: Growth, Scale, Enterprise (Separate paid add-on, includes 1,500 credits/mo)

---

## 1. Overview & Business Purpose
The **AI Lead Engagement Agent** is an autonomous email follow-up assistant designed to convert cold leads and inquiries into sales meetings or orders. 

Unlike chat-based bots or social messaging outreach, Lead Engagement operates exclusively through **connected business email mailboxes** `[DOCUMENTED]`. It sends scheduled initial follow-ups, crafts contextual subsequent emails based on lead responses and knowledge base documents, and automatically routes the conversation to human representatives when human intervention is warranted.

---

## 2. Core Operating Modes

Central AI provides three operating modes for email follow-up `[DOCUMENTED]`:
1. `off`: Engagement is completely disabled.
2. `draft_only`: The AI generates proposed email follow-ups and stages them in the dashboard review inbox. A human operator must inspect and approve each email before transmission.
3. `autonomous`: The agent autonomously dispatches emails through the connected company mailbox based on configured schedule rules, daily caps, and playbooks.

---

## 3. Setup & Mailbox Integration (`/accounts/mailboxes/`)
- **Mailbox Connection**: Administrators connect an official corporate email mailbox (SMTP/IMAP or OAuth) on the Mailboxes screen.
- **Agent Identity**: Configured with a designated sender name, job title, company background description, and primary business objective (e.g., "Schedule a demo", "Confirm wholesale order").
- **Follow-up Cadence**: Configurable intervals (e.g., Email 1 on Day 1, Email 2 on Day 3, Email 3 on Day 7), sending days, hours, and daily sending caps.
- **Suppression List & Compliance**: Global suppression list prevents emailing opted-out individuals; every outgoing email carries a mandatory unsubscribe link `[DOCUMENTED]`.

---

## 4. Verification Status & Evidence
- **Documented**: Official documentation `/docs/lead-engagement/` explicitly defines mailbox requirements, email follow-up mechanics, and 1,500 monthly credit limits `[DOCUMENTED]`.
- **Re-Verification Finding**: Corrects previous audit assumption of WhatsApp/Messenger outbound campaigns.
"""
    return new_doc

update_file("04_FEATURE_DETAILS/05_AI_LEAD_ENGAGEMENT.md", fix_04_05)

# ----------------------------------------------------------------------
# 7. 04_FEATURE_DETAILS/11_COMMERCE_AND_LIVE_CHECKOUT.md
# ----------------------------------------------------------------------
def fix_04_11(c):
    new_doc = """# Feature Detail: Commerce & Live Checkout Architecture

- **Feature ID**: `MOD-15`, `MOD-16`
- **Module Name**: Live Website Checkout & Commerce API (v1.3)
- **Audit Status**: Verified Documented `[DOCUMENTED]`
- **Official Documentation Sources**: `/docs/live-checkout/` and `/docs/commerce-api/`
- **Plan Availability**: Live Checkout (Starter+); Commerce API (Scale, Custom)

---

## 1. Overview & Business Purpose
Central AI's commerce framework connects conversational basket building with external transaction execution. Crucially, Central AI **does not store credit card numbers or process domestic MFS payments directly inside the chat engine**.

Instead, it offers two decoupled integration models:
1. **Live Checkout on Your Site**: The AI Sales Agent builds a basket in chat, then passes the shopper and cart reference to the merchant's *own website checkout page* via URL parameters. The merchant's site processes payment and updates Central AI via an HMAC-signed webhook.
2. **Headless Commerce API (Contract v1.3)**: For custom ERPs or course portals, the *merchant's server implements the API* (quote.calculate, commit.create, commit.lookup) and Central AI invokes it during the chat conversation.

---

## 2. Live Checkout URL Architecture

### The Checkout Link Template
In `/chat/chatbot/settings/`, the merchant configures a parameterized checkout URL:
```text
https://yourshop.com/cart/add?items={items}&ref={ref}
```
- `{items}`: Populated with offering codes and quantities (e.g., `WEB-101:1,UX-7:2`).
- `{ref}`: Unique Central AI chat session tracking token (e.g., `cai_Z3xH1...`).

### The Customer Journey
```mermaid
sequenceDiagram
    autonumber
    actor Shopper as Customer in Chat
    participant Agent as AI Sales Agent
    participant SiteCart as Merchant Website Cart
    participant Gateway as Merchant Payment Gateway
    participant CentralAI as Central AI Webhook Receiver

    Shopper->>Agent: "I want to enroll in the UX Masterclass"
    Agent-->>Shopper: Renders Offering Card with [Checkout on yoursite.com]
    Shopper->>SiteCart: Clicks button -> Opens https://yourshop.com/cart/add?items=UX-7:1&ref=cai_...
    SiteCart->>Gateway: Customer logs in on site & completes payment
    Gateway-->>SiteCart: Payment Approved
    SiteCart->>CentralAI: POST /webhooks/orders/ow_EXAMPLE/ (Central-AI-Signature)
    CentralAI-->>Shopper: Chat bubble automatically updates to "Payment Received #ORD-1092"
```

---

## 3. Order Webhook Specification (`/webhooks/orders/ow_.../`)

- **Method**: `POST`
- **Endpoint**: `http://centralai.daffodil-bd.com/webhooks/orders/ow_MERCHANT_KEY/`
- **Authentication**: `Central-AI-Signature: t=1791009400,v1=5f2c...` (HMAC SHA-256)

### Sample Payload
```json
{
  "event_id": "EVT-9021-paid",
  "occurred_at": "2026-10-26T10:12:04Z",
  "ref": "cai_Z3xH1...",
  "order": {
    "reference": "ORD-9021",
    "status": "confirmed",
    "payment_status": "paid",
    "total": {"amount": "1800.00", "currency": "BDT"},
    "payment_method": {"label": "SSLCommerz"}
  }
}
```

---

## 4. Headless Commerce API (Contract v1.3)
For advanced Scale tier accounts:
- **Architecture**: Inverted webhook model where the merchant's server implements endpoints and Central AI acts as the API client.
- **Operations**:
  - `quote.calculate`: Central AI asks merchant server for live pricing, shipping, and taxes.
  - `commit.create`: Central AI sends the confirmed order with an `Idempotency-Key`.
  - `commit.lookup`: Central AI checks order status.
- **SDK Support**: Reference packages available in plain PHP, Laravel, and Node.js.

---

## 5. Verification Status & Evidence
- **Documented**: Verified in `/docs/live-checkout/` and `/docs/commerce-api/` `[DOCUMENTED]`.
- **Re-Verification Finding**: Corrects previous audit assumptions of native in-chat bKash/Nagad TrxID entry forms inside Central AI.
"""
    return new_doc

update_file("04_FEATURE_DETAILS/11_COMMERCE_AND_LIVE_CHECKOUT.md", fix_04_11)

# ----------------------------------------------------------------------
# 8. 05_END_TO_END_WORKFLOWS.md
# ----------------------------------------------------------------------
def fix_05(c):
    # Update Workflow 2 passage count
    c = c.replace("Top K=4 chunks, Similarity > 0.72", "8 most relevant passages retrieved from knowledge base")
    # Update Workflow 3
    c = c.replace("Submits TrxID: \"8X92M01LK\"", "Completes payment on merchant checkout page")
    c = c.replace("Update Order status to PAYMENT_VERIFYING", "Sends signed order webhook (Central-AI-Signature)")
    # Update Workflow 4 to batch
    c = c.replace("Smart Lead Agent asks qualification questions", "Customer chats naturally; Smart Lead Agent processes daily at 03:00")
    return c

update_file("05_END_TO_END_WORKFLOWS.md", fix_05)

# ----------------------------------------------------------------------
# 9. 07_AI_AGENTS_AND_KNOWLEDGE_FLOWS.md
# ----------------------------------------------------------------------
def fix_07(c):
    c = c.replace("Top K=4 chunks", "8 most relevant passages")
    c = c.replace("Top-K Chunks**: 4 candidate passages", "Passages Retrieved**: 8 candidate passages (official specification)")
    c = c.replace("Multi-Turn Qualification", "Daily Batch Summarization")
    c = c.replace("AI Lead Engagement<br/>(Outbound Nurturing)", "AI Lead Engagement<br/>(Email Follow-Up Add-on)")
    return c

update_file("07_AI_AGENTS_AND_KNOWLEDGE_FLOWS.md", fix_07)

# ----------------------------------------------------------------------
# 10. 11_BILLING_USAGE_AND_PLAN_LIMITS.md
# ----------------------------------------------------------------------
def fix_11(c):
    c = c.replace("1,000 records", "3,000 records")
    c = c.replace("5,000 records", "10,000 records")
    c = c.replace("20,000 records", "50,000 records")
    c = c.replace("Auto Lead Capture | Smart Lead + Odoo CRM", "Disabled | Auto Lead (500/mo) + Smart Lead (3k) + Odoo")
    c = c.replace("Auto Lead Capture | Auto Lead Capture", "Lead capture | Lead management")
    return c

update_file("11_BILLING_USAGE_AND_PLAN_LIMITS.md", fix_11)

# ----------------------------------------------------------------------
# 11. 14_AUDIT_PROGRESS.md
# ----------------------------------------------------------------------
def fix_14(c):
    c = c.replace(
        "Total Documentation Files**: 25 markdown files.",
        "Total Documentation Files**: 26 markdown files (including `15_LIVE_REVERIFICATION_REPORT.md`)."
    )
    c = c.replace(
        "Status**: **100% Written & Verified**",
        "Status**: **Independently Re-Verified & Corrected (October 2026)**"
    )
    return c

update_file("14_AUDIT_PROGRESS.md", fix_14)

print("All corrections applied.")
