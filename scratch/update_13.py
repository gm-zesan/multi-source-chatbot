with open("docs/product-audit/central-ai/13_UNVERIFIED_FEATURES_AND_OPEN_QUESTIONS.md", "r") as f:
    text = f.read()

new_content = """# Central AI: Unverified Features, Gaps & Open Questions

- **Document ID**: `GAP-13`
- **Scope**: Rigorous Classification of Unverified Claims, Inaccessible Environments, and Technical Hypotheses
- **Audit Date**: October 2026
- **Status**: Updated Post Independent Re-Verification

---

## 1. Authenticated Dashboard Screens (`[INACCESSIBLE_SANDBOX / UNVERIFIED_LIVE]`)

Due to environment sandbox constraints blocking Playwright automated browser initialization (no external binary download access), the 15 administrative screens originally cataloged in `03_DASHBOARD_SCREEN_CATALOG.md` could not be verified live via browser automation in this run:

| Endpoint Path | Screen Name | Prior Observation Claim | Re-Verification Status |
| :--- | :--- | :--- | :--- |
| `/chat/dashboard/` | Platform Dashboard | Metrics meters, channel statuses | **UNVERIFIED_LIVE** (Requires manual login) |
| `/chat/chatbot/` | Agent Playground | Real-time chat dialogue & source inspector | **UNVERIFIED_LIVE** (Requires manual login) |
| `/chat/chatbot/settings/`| Agent Settings | Model temperature, persona, prompt | **UNVERIFIED_LIVE** (Requires manual login) |
| `/knowledge/` | Document Knowledge | PDF/DOCX uploader, chunking status | **UNVERIFIED_LIVE** (Requires manual login) |
| `/knowledge/web-training/`| Web Crawler | URL input, link depth selector | **UNVERIFIED_LIVE** (Requires manual login) |
| `/chat/faqs/` | FAQ Management | Q&A table, CSV import/export | **UNVERIFIED_LIVE** (Requires manual login) |
| `/knowledge/api/docs/` | Knowledge API Docs | In-dashboard API documentation | **UNVERIFIED_LIVE** (Requires manual login) |
| `/sales/offerings/` | Offerings Catalog | 5 offering types, price inputs | **UNVERIFIED_LIVE** (Requires manual login) |
| `/sales/suggestions/` | "Find What I Sell" | AI suggestions table, scan button | **UNVERIFIED_LIVE** (Requires manual login) |
| `/chat/widget/` | Web Chat Widget | Embed script, allowed domains | **UNVERIFIED_LIVE** (Requires manual login) |
| `/integrations/meta/` | Meta Social Hub | FB, IG, WhatsApp connection cards | **UNVERIFIED_LIVE** (Requires manual login) |
| `/leads/` | Lead Management | Tabular leads, export actions | **UNVERIFIED_LIVE** (Requires manual login) |
| `/leads/integrations/` | CRM Settings | Odoo XML-RPC configuration form | **UNVERIFIED_LIVE** (Requires manual login) |
| `/chat/chat-logs/` | Chat Logs | Transcript viewer, rating filter | **UNVERIFIED_LIVE** (Requires manual login) |
| `/billing/usage/` | Usage & Quotas | 4 live resource progress meters | **UNVERIFIED_LIVE** (Requires manual login) |

---

## 2. Documented Features with Untested Live Runtime Behavior (`[UNTESTED_BEHAVIOR]`)

The following features are conclusively **DOCUMENTED** in official documentation (`/docs/`), but their actual live runtime behavior was not executed in this audit because doing so would require live external accounts, payment transactions, or live customer communications:

1. **Live Order Webhook Processing**:
   - `POST /webhooks/orders/ow_.../` with `Central-AI-Signature` (HMAC SHA-256). Documented in `/docs/live-checkout/`, but live webhook dispatch to update a chat bubble was not executed.
2. **Headless Commerce API (v1.3)**:
   - Server-side execution of `quote.calculate` and `commit.create` with `Idempotency-Key`. Documented in `/docs/commerce-api/`, but no live mock merchant store was deployed to receive calls.
3. **Live Odoo CRM Synchronization**:
   - Bi-directional synchronization of leads to `crm.lead` via XML-RPC. Documented in `/docs/server-apis/`, but live sync against a staging Odoo database was not run.
4. **Autonomous Email Follow-up Dispatch**:
   - AI Lead Engagement mailbox sending via connected IMAP/SMTP. Documented in `/docs/lead-engagement/`, but live emails were not sent to avoid spamming real recipients.
5. **WhatsApp Cloud API Webhooks**:
   - Webhook reception and 24-hour service window enforcement. Documented in `/docs/social-channels/`, but requires verified Meta Business WABA credentials.

---

## 3. Unverified Marketing Claims vs Technical Reality (`[UNVERIFIED]`)

| Vendor Marketing Claim | Technical Reality & Audit Assessment | Classification |
| :--- | :--- | :--- |
| **"100% Zero Hallucination"** | LLMs cannot guarantee zero hallucinations. While Central AI applies low temperature and strict context guardrails, novel or adversarial queries remain subject to RAG retrieval misses. | `[UNVERIFIED]` |
| **"Exact Embedding Model"** | Documentation confirms semantic vector search, but whether Central AI uses OpenAI `text-embedding-3-small`, Cohere, or local sentence-transformers is unverified. | `[UNVERIFIED]` |
| **"Underlying Vector Database"** | Documentation mentions vector storage and tenant namespaces, but whether it runs pgvector, Qdrant, Pinecone, or Typesense is unverified in public specs. | `[UNVERIFIED]` |
| **"Exact Similarity Threshold"** | Prior audit claimed `0.72` cosine similarity cutoff. Official documentation confirms K=8 passages retrieved, but does not state a numeric threshold. | `[UNVERIFIED]` |

---

## 4. Required Manual Follow-Up Tests for Operators

To transition the 15 dashboard screens and 5 integration workflows from `UNVERIFIED_LIVE` to `VERIFIED_LIVE`, human operators should follow the step-by-step protocol in Section 5 of `15_LIVE_REVERIFICATION_REPORT.md`.
"""

with open("docs/product-audit/central-ai/13_UNVERIFIED_FEATURES_AND_OPEN_QUESTIONS.md", "w") as f:
    f.write(new_content)

print("Updated 13_UNVERIFIED_FEATURES_AND_OPEN_QUESTIONS.md successfully")
