# Customer Workflows & Use Case Comparison

- **Document ID**: `STRAT-07`
- **Application**: Multi-Source Chatbot Platform (`multi-source-chatbot`)
- **Benchmark Target**: Central AI (`https://centralai.daffodil-bd.com/`)
- **Status**: Operational Workflow Analysis

---

## 1. Executive Summary

Feature lists and API specs fail to capture how real users experience a platform. This document compares **9 core end-to-end customer workflows** across both platforms, mapping:
1. **Central AI Verified Experience**: How Central AI executes the journey today.
2. **Our SaaS Current Posture**: How our platform currently behaves.
3. **Operational Gaps & Friction Points**: Where our journey breaks down.
4. **Recommended Future Experience**: The target user journey to implement.
5. **Success Metrics**: Quantitative targets to validate workflow quality.

---

## 2. Nine Detailed Customer Journey Comparisons

```
====================================================================================================
WORKFLOW 1: Customer Asks a Policy / Product Question on Webchat
====================================================================================================
```
- **Scenario**: A website visitor asks: *"What is your return policy for damaged goods, and who pays for return shipping?"*.
- **Central AI Behavior (Verified)**:
  - Visitor opens chat widget (`/chat/widget/`).
  - Bot retrieves passages from uploaded documents/FAQs (8-passage retrieval limit).
  - Streams response with citation markers; falls back to standard message if confidence is low.
- **Our SaaS Current Behavior (Verified)**:
  - Operates inside authenticated simulator only (`/simulator`); no public webchat widget exists.
  - Generates streaming response with Typesense hybrid search and session context.
- **Missing Steps / Differences**:
  - Our SaaS cannot be accessed by external website visitors without a public widget.
  - Our SaaS lacks clickable citation previews in the simulator.
- **Recommended Future Experience**:
  - Visitor clicks branded widget launcher on merchant's website.
  - Bot streams response in < 800ms with clickable source badge: `[Return Policy.pdf, p. 2]`. Clicking displays the exact policy snippet in an unobtrusive drawer.
- **Success Metrics**:
  - Time-to-First-Token (TTFT) < 800ms.
  - User satisfaction rating > 4.5 / 5 on answered questions.

---

```
====================================================================================================
WORKFLOW 2: Lead Qualification from Social Media Conversations
====================================================================================================
```
- **Scenario**: A prospective buyer sends a message on Facebook Messenger: *"Price koto? Delivery hobe Dhanmondi te?"*.
- **Central AI Behavior (Verified)**:
  - Smart Lead Agent identifies buyer intent (`/chat/chatbot/settings/`).
  - Prompts buyer for contact details, auto-extracts phone number and address, marks lead as `Qualified`, and saves to `/lead-capture/dashboard/`.
- **Our SaaS Current Behavior (Verified)**:
  - `ReceiveMessengerWebhookJob` processes incoming message and sends AI response via `FacebookDriver`.
  - Captures Facebook Sender ID, but does not extract phone numbers, score lead intent, or create a `Lead` record.
- **Missing Steps / Differences**:
  - Lack of automated lead extraction and scoring; contact details remain buried in text messages unless manually recorded by an agent.
- **Recommended Future Experience**:
  - AI answers price and delivery questions, then conversational entity extractor prompts for phone number.
  - Automatically parses `017XXXXXXXX`, creates a `Lead` record tagged `Dhanmondi / High Intent`, and alerts sales agents in real time.
- **Success Metrics**:
  - Extraction accuracy of valid Bangladeshi mobile numbers > 95%.
  - Lead capture conversion rate > 25% of commercial conversations.

---

```
====================================================================================================
WORKFLOW 3: Human Agent Handoff & Escalation
====================================================================================================
```
- **Scenario**: A frustrated customer types: *"Your bot is useless, I received the wrong item, let me speak with a real person right now"*.
- **Central AI Behavior (Verified)**:
  - Keyword trigger activates human handoff.
  - Bot halts autonomous generation and creates a ticket in `/support/tickets/` with status `Open`.
  - Notification sent to agent dashboard.
- **Our SaaS Current Behavior (Verified)**:
  - `ConversationController.php` allows assigning conversation to an agent and updating status to `open`.
  - Bot stops autonomous response when agent takes over thread.
- **Missing Steps / Differences**:
  - Our SaaS lacks real-time audio/browser notifications for agents and lacks a formal ticket entity with SLA timers.
- **Recommended Future Experience**:
  - Bot recognizes angry sentiment / explicit handoff trigger.
  - Bot responds empathetically: *"I apologize for the frustration. I am transferring you to our senior support team right now."*.
  - Dispatches WebSocket event triggering instant desktop notification to online agents; starts a 15-minute SLA timer.
- **Success Metrics**:
  - Handoff transfer latency < 2 seconds.
  - Agent First Response Time (FRT) < 5 minutes during operational hours.

---

```
====================================================================================================
WORKFLOW 4: Product Discovery & In-Chat Purchase Intent
====================================================================================================
```
- **Scenario**: A buyer asks: *"Show me formal black shoes under 3,000 BDT in size 42"*.
- **Central AI Behavior (Verified)**:
  - AI Sales Agent queries offering catalog (`/sales/`).
  - Displays interactive product recommendation cards with image, price, and "Buy Now" button.
  - Assembles cart and generates live checkout URL.
- **Our SaaS Current Behavior (Verified)**:
  - Responds that it does not have product catalog or inventory data; searches FAQ/documents only.
- **Missing Steps / Differences**:
  - Total capability gap: No product catalog, no recommendation cards, no checkout link generation.
- **Recommended Future Experience**:
  - Typesense queries product catalog collection filtering by price <= 3000, category = "shoes", variant = "42", color = "black".
  - Bot returns interactive carousel cards. Clicking "Buy Now" generates a one-click bKash/SSLCommerz checkout link pre-filled with the item.
- **Success Metrics**:
  - In-chat product click-through rate (CTR) > 20%.
  - In-chat checkout completion rate > 8%.

---

```
====================================================================================================
WORKFLOW 5: Spreadsheet & Business Analytics Q&A
====================================================================================================
```
- **Scenario**: A merchant uploads a multi-sheet `sales_2026.xlsx` and asks: *"Which product category had the highest return rate in Q2, and what was the total refund amount?"*.
- **Central AI Behavior (Verified)**:
  - Ingests file as text chunks into vector index.
  - Searches for "return rate" and "refund amount"; returns hallucinated estimate or states it cannot calculate numbers across rows.
- **Our SaaS Current Behavior (Verified)**:
  - Dynamically loads `sales_2026.xlsx` into an isolated virtual SQLite database (`ws_X_file.sqlite`).
  - Synthesizes exact SQL query: `SELECT category, SUM(refund_amount), AVG(is_returned) FROM sales ... GROUP BY category ORDER BY ...`.
  - Executes query deterministically and prints exact numeric results in chat (`LiveSqlAnalyticsTest.php`).
- **Missing Steps / Differences**:
  - Our SaaS executes the math perfectly, but outputs raw text/markdown rather than a visual chart.
- **Recommended Future Experience**:
  - In addition to the exact calculation, bot renders an interactive Bar Chart comparing refund amounts across categories, with an "Export to Excel" button.
- **Success Metrics**:
  - Calculation accuracy = 100% (zero mathematical hallucinations).
  - Query execution latency < 2.5 seconds on files up to 100,000 rows.

---

```
====================================================================================================
WORKFLOW 6: Knowledge Document Ingestion & Verification
====================================================================================================
```
- **Scenario**: Merchant uploads a 40-page employee handbook PDF and wants to verify it is ready for customer inquiries.
- **Central AI Behavior (Verified)**:
  - Uploads via `/knowledge/upload/`; progress bar displays upload status; chunks added to tenant record quota.
- **Our SaaS Current Behavior (Verified)**:
  - Validates file (max 20MB, ZIP bomb checks in `StoreKnowledgeDocumentRequest.php`).
  - Extracts text, generates embeddings, and indexes into Typesense with workspace scope (`KnowledgeDocumentService.php`).
- **Missing Steps / Differences**:
  - Both platforms successfully ingest documents. Central AI displays a record count badge; our SaaS displays chunk count in simulator metadata.
- **Recommended Future Experience**:
  - Add an in-dashboard "Test Knowledge" drawer allowing the merchant to immediately ask test questions and see which specific chunks are activated.
- **Success Metrics**:
  - Ingestion processing time < 30 seconds for 50-page PDF.
  - Retrieval precision (P@3) > 90% on benchmark FAQ questions.

---

```
====================================================================================================
WORKFLOW 7: Merchant Connects a Social Media Channel
====================================================================================================
```
- **Scenario**: A new merchant wants to connect their business Facebook Page and WhatsApp Business Account to the AI bot.
- **Central AI Behavior (Verified)**:
  - Navigates to `/integrations/meta/`; clicks "Connect Page"; completes Meta OAuth dialog; page automatically links.
- **Our SaaS Current Behavior (Verified)**:
  - Backend driver classes (`FacebookDriver`, `WhatsAppDriver`) are tested and ready.
  - Requires a developer to insert credentials into the `channel_accounts` database table manually.
- **Missing Steps / Differences**:
  - Absence of self-serve OAuth connection UI.
- **Recommended Future Experience**:
  - Merchant clicks "Connect Facebook" in dashboard.
  - Official Meta OAuth modal opens; merchant selects page; tokens are exchanged, encrypted at rest, and webhook auto-subscribed.
- **Success Metrics**:
  - Self-serve connection completion time < 3 minutes.
  - Onboarding success rate > 90% without customer support intervention.

---

```
====================================================================================================
WORKFLOW 8: Tenant Onboarding & Team Role Management
====================================================================================================
```
- **Scenario**: A company owner invites 3 customer support agents with restricted permissions (can answer chats, cannot delete documents or view billing).
- **Central AI Behavior (Verified)**:
  - Invites users by email via `/accounts/team/`; assigns coarse roles (Admin or Agent) constrained by tier seat limit (4 to 20 seats).
- **Our SaaS Current Behavior (Verified)**:
  - Spatie role-permission system (`RoleEnum`, `PermissionEnum`) with 28 granular permissions.
  - Requires command-line or database seeding to assign roles; no front-end invite form.
- **Missing Steps / Differences**:
  - Superior backend permission granularity; missing frontend user invitation UI.
- **Recommended Future Experience**:
  - Owner enters agent email and selects preset role (Support Agent, Knowledge Manager, Billing Admin).
  - Agent receives invitation email with magic sign-in link.
- **Success Metrics**:
  - Invitation acceptance rate > 85%.
  - Zero permission leakage across workspace boundaries.

---

```
====================================================================================================
WORKFLOW 9: Usage Tracking & Subscription Management
====================================================================================================
```
- **Scenario**: A tenant reaches 80% of their monthly message quota and wants to upgrade from Starter to Growth.
- **Central AI Behavior (Verified)**:
  - Dashboard shows real-time progress bar (`/billing/usage/`).
  - Merchant clicks "Upgrade", inputs payment details, upgrades immediately, and downloads PDF invoice (`/billing/invoices/`).
- **Our SaaS Current Behavior (Verified)**:
  - Measures raw tokens per turn in `conversations.metadata`; provides no aggregated monthly quota limit or billing upgrade UI.
- **Missing Steps / Differences**:
  - Total commercial billing gap.
- **Recommended Future Experience**:
  - Visual gauge on dashboard header: `12,450 / 15,000 Messages Used (83%)`.
  - When reaching 90%, banner prompts: "Upgrade to Growth for 50,000 messages".
  - One-click bKash / Card checkout charges subscription and resets limits instantly.
- **Success Metrics**:
  - Zero quota overages permitted without payment.
  - Subscription self-serve conversion rate > 12% among active trial users.
