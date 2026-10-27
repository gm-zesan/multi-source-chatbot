# Feature Detail: AI Lead Engagement (Email Nurturing Add-On)

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

## 3. Setup & Mailbox Integration (`/accounts/company/mailboxes/`)
- **Mailbox Connection**: Administrators connect an official corporate email mailbox (SMTP/IMAP or OAuth) on the Mailboxes screen.
- **Agent Identity**: Configured with a designated sender name, job title, company background description, and primary business objective (e.g., "Schedule a demo", "Confirm wholesale order").
- **Follow-up Cadence**: Configurable intervals (e.g., Email 1 on Day 1, Email 2 on Day 3, Email 3 on Day 7), sending days, hours, and daily sending caps.
- **Suppression List & Compliance**: Global suppression list prevents emailing opted-out individuals; every outgoing email carries a mandatory unsubscribe link `[DOCUMENTED]`.

---

## 4. Verification Status & Evidence
- **Documented**: Official documentation `/docs/lead-engagement/` explicitly defines mailbox requirements, email follow-up mechanics, and 1,500 monthly credit limits `[DOCUMENTED]`.
- **Re-Verification Finding**: Corrects previous audit assumption of WhatsApp/Messenger outbound campaigns.
