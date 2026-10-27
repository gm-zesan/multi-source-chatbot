# Feature Detail: API Keys & Security Architecture

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
