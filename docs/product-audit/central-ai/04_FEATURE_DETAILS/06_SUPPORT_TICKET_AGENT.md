# Feature Detail: Support Ticket Agent & Helpdesk Desk

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
