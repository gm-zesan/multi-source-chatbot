# Feature Detail: Smart Lead Agent (Daily Batch Summarizer)

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
