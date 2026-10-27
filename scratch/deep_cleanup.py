import os, re

BASE_DIR = "/Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/product-audit/central-ai"

def update_file(filename, modifier_func):
    path = os.path.join(BASE_DIR, filename)
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()
    new_content = modifier_func(content)
    with open(path, "w", encoding="utf-8") as f:
        f.write(new_content)
    print(f"Cleaned: {filename} ({len(new_content)} bytes)")

# -----------------------------------------------------------------
# 1. 00_EXECUTIVE_SUMMARY.md
# -----------------------------------------------------------------
def clean_00(c):
    c = c.replace(
        'PAYMENTS["Payment Verification<br/>(bKash / Nagad TrxID / SSLCommerz)"]',
        'PAYMENTS["External Merchant Checkout & Signed Webhook<br/>(Central-AI-Signature)"]'
    )
    c = c.replace(
        "3. **Smart Lead Agent** (Contextual Qualification & Scoring)",
        "3. **Smart Lead Agent** (Daily Batch Conversation Summarization & Categorization)"
    )
    c = c.replace(
        "5. **AI Lead Engagement** (Automated Multi-turn Re-engagement)",
        "5. **AI Lead Engagement** (Automated Mailbox Email Follow-ups)"
    )
    c = c.replace(
        "combining automated order taking with local payment options (**bKash, Nagad, Bank transfer**) and international payment gateway redirection (**SSLCommerz, Stripe**).",
        "redirecting buyers to the merchant's external website cart ({items}, {ref}) and listening for HMAC-signed order webhooks (Central-AI-Signature)."
    )
    c = c.replace(
        "- `[OBSERVED]`: Confirmed via authenticated dashboard HTTP inspection or live DOM scrape.",
        "- `[OBSERVED]`: Claimed in prior inspection logs; pending independent live re-verification in restricted browser sandbox.\n  - `[DOCUMENTED]`: Confirmed via official vendor documentation (/docs/) or live pricing page DOM."
    )
    return c

update_file("00_EXECUTIVE_SUMMARY.md", clean_00)

# -----------------------------------------------------------------
# 2. 03_DASHBOARD_SCREEN_CATALOG.md
# -----------------------------------------------------------------
def clean_03(c):
    banner = """> [!WARNING]
> **Re-Verification Status Notice**:
> The 15 administrative screens described below were documented in prior inspection logs (`centralai_inspection.json`). During the October 2026 independent re-verification audit, automated browser initialization was blocked by environment network isolation. These screens are classified as **CLAIMED_PRIOR / UNVERIFIED_LIVE** pending manual operator execution of the inspection protocol in `15_LIVE_REVERIFICATION_REPORT.md`.

"""
    if "Re-Verification Status Notice" not in c:
        c = c.replace(
            "# Central AI: Dashboard Screen Catalog\n\nThis catalog documents the 15 administrative screens inspected directly during the authenticated exploration session (`centralai_inspection.json`).",
            "# Central AI: Dashboard Screen Catalog\n\n" + banner + "This catalog documents the 15 administrative screens originally cataloged during exploration sessions. See the status notice above regarding independent re-verification."
        )
    return c

update_file("03_DASHBOARD_SCREEN_CATALOG.md", clean_03)

# -----------------------------------------------------------------
# 3. 04_FEATURE_DETAILS/07_KNOWLEDGE_BASE.md
# -----------------------------------------------------------------
def clean_04_07(c):
    c = c.replace("- **Starter**: 1,000 knowledge records.", "- **Starter**: 3,000 knowledge records [DOCUMENTED].")
    c = c.replace("- **Growth**: 5,000 knowledge records.", "- **Growth**: 10,000 knowledge records [DOCUMENTED].")
    c = c.replace("- **Scale**: 20,000 knowledge records.", "- **Scale**: 50,000 knowledge records [DOCUMENTED].")
    return c

update_file("04_FEATURE_DETAILS/07_KNOWLEDGE_BASE.md", clean_04_07)

# -----------------------------------------------------------------
# 4. 05_END_TO_END_WORKFLOWS.md
# -----------------------------------------------------------------
def clean_05(c):
    c = c.replace(
        'Bot-->>Buyer: "Send 1,800 BDT to 017XXXXXXXX & reply with TrxID"\n    Buyer->>Payment: Completes transfer via bKash App\n    Buyer->>Bot: Submits TrxID: "8X92M01LK"\n    Bot->>OrderSys: Update Order status to PAYMENT_VERIFYING\n    OrderSys-->>Admin: Alert: New order requires manual verification\n    Admin->>OrderSys: Clicks [Verify & Confirm]\n    OrderSys-->>Bot: Order Status = CONFIRMED\n    Bot-->>Buyer: "Payment verified! Your order #ORD-1044 is active."',
        'Bot-->>Buyer: Interactive Offering Card with [Checkout on yoursite.com]\n    Buyer->>Payment: Clicks checkout link -> Completes payment on merchant website\n    Payment->>OrderSys: Merchant server sends signed webhook (Central-AI-Signature)\n    OrderSys-->>Bot: Webhook confirms Order #ORD-1044 (Status: Paid)\n    Bot-->>Buyer: Chat bubble automatically updates: "Payment received for #ORD-1044!"'
    )
    return c

update_file("05_END_TO_END_WORKFLOWS.md", clean_05)

# -----------------------------------------------------------------
# 5. 07_AI_AGENTS_AND_KNOWLEDGE_FLOWS.md
# -----------------------------------------------------------------
def clean_07(c):
    c = c.replace(
        'KB_STORE[("Knowledge Vector Index<br/>(Top-K Similarity > 0.72)")]',
        'KB_STORE[("Knowledge Vector Index<br/>(8 Most Relevant Passages)")]'
    )
    c = c.replace(
        "VectorDB-->>Router: Return top K=4 chunks (Similarity score: 0.88, 0.81, 0.76, 0.73)",
        "VectorDB-->>Router: Return 8 most relevant passages"
    )
    c = c.replace(
        "- **Similarity Threshold**: ~0.72 minimum cosine similarity cutoff.",
        "- **Similarity Threshold**: Internal vendor metric; exact cutoff unverified in public documentation."
    )
    c = c.replace(
        "bKash/Nagad TrxID recording, HMAC API",
        "decoupled Live Checkout link, signed order webhooks, HMAC API"
    )
    return c

update_file("07_AI_AGENTS_AND_KNOWLEDGE_FLOWS.md", clean_07)

# -----------------------------------------------------------------
# 6. 08_LEADS_TICKETS_AND_HUMAN_HANDOFF.md
# -----------------------------------------------------------------
def clean_08(c):
    bant_section = """## 2. Lead Qualification Heuristics & Daily Batch Summarization

> [!IMPORTANT]
> **Audit Re-Verification Notice**:
> The previous audit described a real-time conversational BANT qualification interview. Official documentation (`/docs/lead-agent/`) confirms that the Smart Lead Agent **does not conduct live in-chat qualification interviews**. 
> Instead, it operates as an **asynchronous daily batch summarization job** (default 03:00 Asia/Dhaka) that reviews completed chat transcripts, generates concise summaries, and categorizes leads into up to 50 user-defined business categories `[DOCUMENTED]`.

### Batch Summarization Parameters (`/chat/chatbot/settings/`)
- **Execution Schedule**: Once daily after specified local time (e.g. `03:00`, `Asia/Dhaka`).
- **Category Taxonomy**: Comma-separated list of up to 50 custom categories (e.g. *"Wholesale"*, *"Course Enrollment"*, *"General"*) `[DOCUMENTED]`.
- **Credits Metering**: Consumes 1 Lead Agent credit per summarized conversation. Failed summaries are refunded and retried on next cycle."""

    # Replace section 2
    c = re.sub(
        r"## 2\. Lead Qualification Heuristics & Scoring Algorithm.*?(?=## 3\. Odoo CRM)",
        bant_section + "\n\n---\n\n",
        c,
        flags=re.DOTALL
    )
    c = c.replace(
        "- **Documented**: BANT scoring framework and partner limits documented in enterprise feature matrix `[DOCUMENTED]`.",
        "- **Documented**: Daily batch lead summarization, 50-category taxonomy, and partner limits documented in official product guides `[DOCUMENTED]`."
    )
    return c

update_file("08_LEADS_TICKETS_AND_HUMAN_HANDOFF.md", clean_08)

# -----------------------------------------------------------------
# 7. 09_DATA_ENTITIES_AND_DEPENDENCIES.md
# -----------------------------------------------------------------
def clean_09(c):
    c = c.replace(
        "- **`PaymentReceipt`**: MFS transaction logs. Attributes: `id`, `order_id`, `transaction_id` (TrxID), `verified_by_user_id`, `verified_at`.",
        "- **`OrderWebhookEvent`**: External order updates. Attributes: `id`, `event_id`, `occurred_at`, `ref`, `order_reference`, `payment_status`, `total_amount`, `currency`, `signature` [DOCUMENTED]."
    )
    c = c.replace(
        "| **Domestic Payments**| bKash / Nagad / Rocket | Manual TrxID / API | Settlement of South Asian customer orders |",
        "| **Merchant Checkout**| Merchant Website | HTTPS / Redirect | External cart completion via Live Checkout URL |"
    )
    return c

update_file("09_DATA_ENTITIES_AND_DEPENDENCIES.md", clean_09)

# -----------------------------------------------------------------
# 8. 11_BILLING_USAGE_AND_PLAN_LIMITS.md
# -----------------------------------------------------------------
def clean_11(c):
    c = c.replace(
        "3. **Lead Qualification Credits**: Deducted strictly when the Smart Lead Agent executes a full qualification interview scoring run on an active prospect `[DOCUMENTED]`.",
        "3. **Lead Agent Credits**: Deducted strictly when the Smart Lead Agent executes a daily batch summarization on an unsummarized chat conversation (1 credit per conversation) `[DOCUMENTED]`."
    )
    return c

update_file("11_BILLING_USAGE_AND_PLAN_LIMITS.md", clean_11)

# -----------------------------------------------------------------
# 9. 14_AUDIT_PROGRESS.md
# -----------------------------------------------------------------
def clean_14(c):
    c = c.replace(
        "| 7 | `04_FEATURE_DETAILS/03_SMART_LEAD_AGENT.md` | Multi-turn BANT Qualification | Completed | `[DOCUMENTED]`, `[OBSERVED]` |",
        "| 7 | `04_FEATURE_DETAILS/03_SMART_LEAD_AGENT.md` | Daily Batch Summarization & Categorization | Completed | `[DOCUMENTED]` |"
    )
    c = c.replace(
        "- Incorporate structured offering entities and in-chat MFS (bKash/Nagad TrxID) verification to support South Asian transactional workflows.",
        "- Incorporate structured offering entities and decoupled Live Checkout links with signed webhooks to support external transactional workflows."
    )
    return c

update_file("14_AUDIT_PROGRESS.md", clean_14)

print("Deep cleanup complete.")
