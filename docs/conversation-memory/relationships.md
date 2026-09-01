# Conversation Graph Memory: Relationships Specification

**Component**: `conversation-memory/relationships`  
**Version**: 1.0.0-draft  
**Status**: Specification Phase  

---

## 1. Core Graph Relationship Taxonomy

Relationships in Graphiti/Neo4j represent **semantic connections**, **temporal preferences**, and **historical actions** extracted from customer dialogues.

```text
┌──────────────┐                  [:PARTICIPATED_IN]                 ┌──────────────────┐
│              ├────────────────────────────────────────────────────►│   Conversation   │
│              │                                                     └──────────────────┘
│              │                  [:PREFERS]                         ┌──────────────────┐
│              ├────────────────────────────────────────────────────►│  PaymentMethod   │
│              │                                                     └──────────────────┘
│              │                  [:PREFERS_SIZE]                    ┌──────────────────┐
│              ├────────────────────────────────────────────────────►│ Preference (Size)│
│              │                                                     └──────────────────┘
│              │                  [:INTERESTED_IN]                   ┌──────────────────┐
│   Customer   ├────────────────────────────────────────────────────►│ Product/Category │
│              │                                                     └──────────────────┘
│              │                  [:PURCHASED]                       ┌──────────────────┐
│              ├────────────────────────────────────────────────────►│     Product      │
│              │                                                     └──────────────────┘
│              │                  [:DISCUSSED]                       ┌──────────────────┐
│              ├────────────────────────────────────────────────────►│      Order       │
│              │                                                     └──────────────────┘
│              │                  [:REPORTED]                        ┌──────────────────┐
│              ├────────────────────────────────────────────────────►│      Issue       │
└──────────────┘                                                     └──────────────────┘
```

---

## 2. Detailed Relationship Specifications

### 1. `(:Customer)-[:PARTICIPATED_IN]->(:Conversation)`
- **Purpose**: Establishes session lineage.
- **Properties**:
  - `occurred_at` (datetime): Timestamp of the conversation.
  - `channel` (string): `facebook`, `whatsapp`, `web`.

### 2. `(:Customer)-[:PREFERS]->(:PaymentMethod)`
- **Purpose**: Records customer's preferred transaction method.
- **Properties**:
  - `status` (string, required): `current` or `past`.
  - `valid_from` (datetime): Timestamp when this preference was asserted.
  - `valid_to` (datetime, nullable): Timestamp when replaced by a new preference.
  - `source_conversation_id` (string): Traceability back to conversation UUID.

### 3. `(:Customer)-[:PREFERS_SIZE]->(:Preference)`
- **Purpose**: Records sizing preference (e.g. `XL`, `42`, `Medium`).
- **Properties**:
  - `category_scope` (string, optional): e.g. `panjabi`, `shoes`, `general`.
  - `status` (string): `current` or `past`.
  - `confidence` (float): Extraction confidence score.

### 4. `(:Customer)-[:INTERESTED_IN]->(:Product | :Category)`
- **Purpose**: Captures active interest or browsing intent.
- **Properties**:
  - `intent_strength` (string): `casual_inquiry`, `ready_to_buy`, `price_checking`.
  - `last_expressed_at` (datetime): Most recent timestamp customer asked about this.
  - `frequency` (integer): How many times customer inquired.

### 5. `(:Customer)-[:PURCHASED]->(:Product)`
- **Purpose**: Records confirmed historical purchase discussed in conversation.
- **Properties**:
  - `order_id` (string, optional): Associated order reference.
  - `purchased_at` (datetime, optional): Transaction date.
  - `size` (string, optional): Specific size purchased.
  - `color` (string, optional): Specific color purchased.

### 6. `(:Customer)-[:DISCUSSED]->(:Order)`
- **Purpose**: Links customer to an order they asked about, tracked, or modified.
- **Properties**:
  - `last_discussed_at` (datetime): Timestamp of discussion.
  - `reason` (string): `tracking`, `cancellation`, `payment_query`, `address_change`.

### 7. `(:Customer)-[:REPORTED]->(:Issue)`
- **Purpose**: Records customer support grievances or problem history.
- **Properties**:
  - `reported_at` (datetime): When reported.
  - `resolution_state` (string): `pending`, `escalated`, `resolved`.

### 8. `(:Order)-[:CONTAINS]->(:Product)`
- **Purpose**: Connects discussed order to specific products.
- **Properties**:
  - `quantity` (integer): Number of units.

---

## 3. Temporal State Tracking

Graph relationships explicitly capture **temporal progression**. When a customer changes a preference, the old edge is NOT deleted; its state transitions to `past`:

```cypher
// Transitioning Payment Preference: bKash -> Visa Card
MATCH (c:Customer {id: $customer_id, workspace_id: $workspace_id})-[r:PREFERS {status: 'current'}]->(:PaymentMethod)
SET r.status = 'past', r.valid_to = datetime()
WITH c
MATCH (p:PaymentMethod {code: 'card_visa'})
CREATE (c)-[:PREFERS {status: 'current', valid_from: datetime(), source_conversation_id: $conv_id}]->(p)
```

This allows the AI assistant to accurately answer queries like:
- *"What did I use to pay last month?"* &rarr; bKash
- *"Use my preferred payment method."* &rarr; Visa Card (Current)
