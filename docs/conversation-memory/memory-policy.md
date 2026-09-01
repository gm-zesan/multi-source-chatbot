# Conversation Graph Memory: Policies & Governance

**Component**: `conversation-memory/memory-policy`  
**Version**: 1.0.0-draft  
**Status**: Specification Phase  

---

## 1. Data Retention & Expiration Policy

Conversational memory is categorized into three retention tiers:

| Memory Tier | Examples | Retention Period | Action on Expiry |
| :--- | :--- | :--- | :--- |
| **Tier 1: Enduring Traits & Preferences** | Preferred size (`XL`), preferred payment (`bKash`), language | **Indefinite** (until explicitly changed or customer requested deletion) | Retain as active preference |
| **Tier 2: Historical Entity Interactions** | Discussed Order (`#1042`), viewed category (`Panjabi`) | **180 Days** | Archive / prune inactive interaction edges |
| **Tier 3: Transient Issues & Conversations** | `Conversation` session node, resolved delivery complaint | **90 Days** | Soft-delete / prune session edges |

---

## 2. Right to Be Forgotten & Data Deletion Policy

In compliance with data privacy standards and enterprise requirements, the memory system provides three deterministic deletion endpoints:

### A. Delete Customer Memory (`DELETE /memory/customer/{customer_id}`)
- Permanently detaches and removes all `:Preference`, `:Issue`, and interaction edges associated with the `:Customer` node within the specified `workspace_id`.
- Cascade removes isolated transient nodes.

### B. Delete Conversation Memory (`DELETE /memory/conversation/{conversation_id}`)
- Removes all memory edges created during a specific conversation session.
- If an asserted preference originated solely from that deleted conversation, the preference is reverted to the prior state.

### C. Delete Workspace Memory (`DELETE /memory/workspace/{workspace_id}`)
- Full tenant purge. Removes all nodes and edges tagged with `workspace_id`.

---

## 3. Conflict Handling & Recency Resolution

When a customer provides conflicting statements across conversations (e.g. Conversation 1: *"I only use bKash"*, Conversation 5: *"Please charge my Visa card"*):

1. **Recency Priority**:
   - The most recent verified assertion supersedes previous assertions.
   - The prior edge transitions from `status: 'current'` to `status: 'past'`.
2. **Ambiguity / Contradiction Safeguard**:
   - If an extraction has low confidence (< 0.70) or contradicts an established preference without clear intent to change, the system marks the edge with `needs_confirmation: true`.
   - The LLM will politely confirm: *"You previously preferred bKash. Would you like to switch your default payment method to Visa Card?"*

---

## 4. Privacy & Security Guardrails (Anti-Leakage Policy)

The following data categories are **strictly prohibited** from entering the graph memory:
- ❌ **Sensitive Authentication Data**: Passwords, OTPs, PINs, 2FA backup codes.
- ❌ **Financial Credentials**: Full credit/debit card numbers, CVV/CVC codes.
- ❌ **Sensitive Personal Identifiers**: National ID numbers, passport scans.

### Ingestion Filter:
Before any node or edge is committed by Graphiti to Neo4j, an automated regex sanitizer strips card patterns (`\b\d{4}[ -]?\d{4}[ -]?\d{4}[ -]?\d{4}\b`), CVVs, and 6-digit OTP codes.
