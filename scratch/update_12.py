with open("docs/product-audit/central-ai/12_EVIDENCE_REGISTER.md", "r") as f:
    text = f.read()

# Fix classification standard
old_std = """  - `[OBSERVED]`: Directly inspected via authenticated HTTP requests, DOM exploration, or network traffic capture.
  - `[DOCUMENTED]`: Explicitly stated in official Central AI product guides, help manuals, or pricing matrices.
  - `[INFERRED]`: Deducted from architectural inputs, outputs, and standard API behavior.
  - `[UNVERIFIED]`: Stated in secondary marketing collateral but not empirically confirmed.
  - `[INACCESSIBLE]`: Feature restricted to higher-tier paid plans during Test Drive trial."""

new_std = """  - `[DOCUMENTED]`: Explicitly confirmed in official Central AI documentation (/docs/) or live pricing page DOM.
  - `[PARTIALLY_VERIFIED]`: Supported by documentation but subject to technical constraints (e.g. crawler 4,000 char truncation).
  - `[CONTRADICTED]`: Prior audit claim explicitly disproven by official documentation or pricing specifications.
  - `[CLAIMED_PRIOR / UNVERIFIED_LIVE]`: Recorded in prior exploration logs but unable to be re-verified live in this run due to browser environment sandbox limitations.
  - `[UNTESTED_BEHAVIOR]`: Documented interface or workflow whose live runtime behavior has not been executed end-to-end."""

text = text.replace(old_std, new_std)

# Fix heading of Section 1
text = text.replace(
    "## 1. Live Authenticated Dashboard Endpoints (`[OBSERVED]`)",
    "## 1. Authenticated Dashboard Screens (`[CLAIMED_PRIOR / UNVERIFIED_LIVE in this run]`)"
)

# Replace table notes
text = text.replace(
    "| `EVID-DOC-03` | `docs_smart_lead.html` | Smart Lead Agent | Multi-turn BANT qualification, scoring weights (0-100) |",
    "| `EVID-DOC-03` | `/docs/lead-agent/` | Smart Lead Agent | Daily batch summarization, 50 categories, customer sees nothing |"
)
text = text.replace(
    "| `EVID-DOC-07` | `docs_knowledge_hub.html`| Knowledge Hub | ~400 char chunking, 80 char overlap, cosine similarity > 0.72 |",
    "| `EVID-DOC-07` | `/docs/knowledge-base/` & `/docs/chat-agent/` | Knowledge Retrieval | 8 most relevant passages retrieved, ~400 char chunking, 80 char overlap |"
)
text = text.replace(
    "| `EVID-DOC-12` | `docs_commerce.html` | Commerce & Checkout| bKash/Nagad manual TrxID, SSLCommerz, Stripe, HMAC signatures|",
    "| `EVID-DOC-12` | `/docs/live-checkout/` & `/docs/commerce-api/` | Commerce & Checkout| Decoupled cart link ({items}, {ref}), signed order webhook, Commerce API v1.3 |"
)

with open("docs/product-audit/central-ai/12_EVIDENCE_REGISTER.md", "w") as f:
    f.write(text)

print("Updated 12_EVIDENCE_REGISTER.md successfully")
