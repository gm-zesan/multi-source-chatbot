# Feature Detail: Mailboxes Engine & Corporate Email Integration

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
