# Data Sources & Architectural Invariants

This document outlines the strict invariants and data ownership boundaries of the system.

---

## 1. Database Table Naming vs. Routing Independence
- Tables such as `analytics_products`, `analytics_orders`, `analytics_customers`, and `analytics_salespersons` are prefixed with `analytics_` for historical and schema grouping reasons.
- **Invariant**: Eloquent models and schema registries reference `analytics_*` statically. A table prefix must **never** influence or determine whether a query is routed to `ANALYTICS` or `KNOWLEDGE`.
- Example: Asking *"Laptop Pro 15 এর দাম কত?"* routes to `KNOWLEDGE`, and the system accesses `AnalyticsProduct` as the authoritative product data source.

---

## 2. Conversational Purchase Intent Invariant
- When a user says *"এই Laptop Pro 15 টা নিতে চাই"* or *"আমি এটা কিনতে চাই"*, the intent is conversational interest.
- **Invariant**: The system **must NOT** create an order record, cart mutation, payment transaction, or inventory decrement in the database.
- The `ConversationalSupportAgent` conversationally collects `Name`, `Phone`, and `Address`, provides a confirmation summary, and ends the turn.
- The existing `EntityExtractor` / `CRMService` observes the conversation history and updates `CRMContact`.

---

## 3. Action Capability Restriction
- **Invariant**: The `ACTION` capability is strictly limited to `SEND_SELLER_EMAIL`.
- The system must not be converted into an unconstrained general-purpose workflow executor.
- All actions require **Two-Phase Confirmation**:
  1. Turn N: Agent generates a proposal with a deterministic SHA-256 fingerprint stored in `conversation.metadata['pending_action']`.
  2. Turn N+1: Server validates tenant authorization, checks expiration, verifies the fingerprint, and dispatches `SellerNotificationMail` only upon explicit user confirmation.

---

## 4. Conversation Memory (KGM) vs. Company Knowledge
- **Graph Memory (KGM / Neo4j)**: Customer-specific preferences, recent mentioned entities, and personal purchase history.
- **Company Knowledge (Typesense / MySQL)**: Official company policies, delivery charges, return rules, and product catalogs.
- **Invariant**: Generic policy questions strictly bypass Neo4j graph memory to prevent personal data leaking into generic answers.
