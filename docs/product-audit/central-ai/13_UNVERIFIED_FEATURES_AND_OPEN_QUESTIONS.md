# Central AI: Unverified Features, Plan Gates & Open Questions

- **Document ID**: `GAP-13`
- **Scope**: Rigorous Classification of Plan-Gated Screens, Untested Runtime Behaviors, and Open Technical Questions
- **Audit Date**: October 2026
- **Status**: Updated Post Live Authenticated Session Crawl Pass

---

## 1. Verified Live Screens vs. Subscription Plan Gates

During the authenticated session crawl, **21 administrative routes** were accessed and inspected directly `[OBSERVED_LIVE]` in the Test Drive workspace (`zesan`, Company: `ABC`).

However, **5 platform routes** are actively gated by subscription plan restrictions and redirect (HTTP 302) to `/accounts/pricing/`:

| Endpoint Path | Redirect Target | Required Subscription Tier | Documented Capabilities (From `/docs/`) | Access Status |
| :--- | :---: | :---: | :--- | :---: |
| `/knowledge/web-training/` | `/accounts/pricing/` | Starter+ ($15/mo) | Automated web crawler, sitemap XML ingestion, crawl depth (1-3 hops). | `[GATED_PLAN]` |
| `/knowledge/api/docs/` | `/accounts/pricing/` | Growth+ ($50/mo) | Knowledge Sync API documentation (also mirrored at `/chat/api/docs/`). | `[GATED_PLAN]` |
| `/leads/` | `/accounts/pricing/` | Starter+ ($15/mo) | Full tabular lead management table, CSV/Excel export, transcript viewer. | `[GATED_PLAN]` |
| `/leads/conversions/upload/`| `/accounts/pricing/`| Growth+ ($50/mo) | Offline conversion upload CSV tool for attributing won sales. | `[GATED_PLAN]` |
| `/leads/email-settings/` | `/accounts/pricing/` | Growth+ ($50/mo) | Inbound lead email notification rules and trigger thresholds. | `[GATED_PLAN]` |

---

## 2. Documented Features with Untested Live Runtime Behavior (`[UNTESTED_BEHAVIOR]`)

The following capabilities are conclusively **DOCUMENTED** in official documentation (`/docs/`) and specified in the in-app API reference (`/chat/api/docs/`), but their live runtime execution was not triggered to ensure production safety and avoid modifying external accounts:

1. **Live Decoupled Order Webhook Ingestion**:
   - `POST /webhooks/orders/ow_.../` with HMAC SHA-256 signature (`Central-AI-Signature: t=..., v1=...`).
   - *Untested Aspect*: Firing a live HTTP POST from an external merchant store to observe chat bubble real-time transition to "Payment Received".
2. **Headless Commerce Contract v1.3 (UCP)**:
   - Server-side execution of `quote.calculate` and `commit.create` with `Idempotency-Key` against merchant ERP.
   - *Untested Aspect*: Requires hosting a live external mock merchant server exposing `/.well-known/ucp`.
3. **Live Odoo CRM Synchronization**:
   - Push leads to Odoo `crm.lead` via XML-RPC.
   - *Untested Aspect*: Triggering actual XML-RPC push against an external Odoo database.
4. **Autonomous Outbound Email Dispatch**:
   - AI Lead Engagement mailbox sending via connected SMTP/OAuth.
   - *Untested Aspect*: Allowing the agent to send live automated emails to real recipient inboxes.
5. **Meta Cloud API Webhook Handshake**:
   - Live incoming WhatsApp/Instagram message handling and 24-hour service window enforcement.
   - *Untested Aspect*: Requires linking a live Meta Business WhatsApp Business Account (WABA).

---

## 3. Unverified Marketing Claims vs Technical Reality (`[UNVERIFIED]`)

| Vendor Marketing Claim | Technical Reality & Audit Assessment | Classification |
| :--- | :--- | :---: |
| **"100% Zero Hallucination"** | LLMs cannot theoretically guarantee zero hallucinations. While Central AI applies temperature 0.2 and strict context constraints, novel or unindexed queries remain subject to retrieval misses. | `[UNVERIFIED]` |
| **"Exact Embedding Model"** | Documentation confirms dense vector embeddings, but whether Central AI utilizes OpenAI `text-embedding-3-small`, Cohere, or local embeddings is undisclosed. | `[UNVERIFIED]` |
| **"Underlying Vector Database"** | Documentation mentions vector storage and company namespaces, but whether the engine runs pgvector, Qdrant, Pinecone, or Typesense is abstracted behind Django backend views. | `[UNVERIFIED]` |
| **"Similarity Cutoff Threshold"** | Prior audit claimed `0.72` cosine similarity cutoff. Official documentation confirms K=8 passages retrieved, but does not disclose a numeric cutoff score. | `[UNVERIFIED]` |

---

## 4. Recommended Next Steps for Complete Runtime Verification

To transition the remaining 5 plan-gated screens and 5 runtime integration workflows to `TESTED_LIVE`:
1. Upgrade the audit workspace (`ABC`) from `Test Drive` to `Growth` tier.
2. Deploy a lightweight mock merchant server exposing `/.well-known/ucp` and an order webhook receiver.
3. Connect a sandbox Odoo Community container to verify XML-RPC field mapping.
4. Connect a dedicated staging Gmail/Outlook mailbox to observe automated lead follow-up emails in `draft_only` and `autonomous` modes.
