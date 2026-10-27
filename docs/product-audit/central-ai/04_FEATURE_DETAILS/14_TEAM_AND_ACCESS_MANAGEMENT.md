# Feature Detail: Team & Access Management

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
