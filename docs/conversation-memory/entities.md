# Conversation Graph Memory: Entities Specification

**Component**: `conversation-memory/entities`  
**Version**: 1.0.0-draft  
**Status**: Specification Phase  

---

## 1. Entity Modeling Principles

1. **Meaningful Memories Only**: Raw conversational messages are **not** graph nodes. The graph only records distilled facts, entities, and states.
2. **Deterministic Typing**: Every entity node has a strict label and a set of required properties.
3. **Workspace Partitioning**: Every entity node **must** contain `workspace_id`.
4. **Idempotent Identity**: Entity identities must support deduplication (e.g. merging references to the same order number or phone number across conversations).

---

## 2. Core Entity Definitions (Phase 1–6)

### 1. `Customer`
Represents the human customer across multiple sessions and channels (Messenger, WhatsApp, Web).
- **Label**: `:Customer`
- **Properties**:
  - `id` (string, required): Global UUID or scoped customer identifier.
  - `workspace_id` (integer, required): Tenant workspace ID.
  - `external_ids` (list of strings): Channel-specific user IDs (e.g. `psid:123456`, `wa:8801700000000`).
  - `name` (string, optional): Detected customer name.
  - `phone` (string, optional): Normalized contact number.
  - `email` (string, optional): Normalized email address.
  - `first_seen_at` (datetime): Timestamp of first recorded dialogue.
  - `last_seen_at` (datetime): Timestamp of most recent interaction.

### 2. `Conversation`
Represents an individual interaction thread/session.
- **Label**: `:Conversation`
- **Properties**:
  - `id` (string, required): Conversation UUID.
  - `workspace_id` (integer, required): Tenant workspace ID.
  - `channel` (string, required): `facebook`, `whatsapp`, `web`, `telegram`.
  - `started_at` (datetime): Session start timestamp.
  - `ended_at` (datetime, optional): Session termination timestamp.
  - `turn_count` (integer): Total dialogue turns.

### 3. `Product`
A conversational reference to an item in the store catalog.
- **Label**: `:Product`
- **Properties**:
  - `id` (string, required): SKU or Product ID (if identified), or slug.
  - `workspace_id` (integer, required): Tenant workspace ID.
  - `title` (string, required): Canonical product name (e.g. `Navy Blue Slim-fit Panjabi`).
  - `color` (string, optional): Identified color variation.
  - `size` (string, optional): Identified size variation (e.g. `XL`, `42`).

### 4. `Category`
Product category or department.
- **Label**: `:Category`
- **Properties**:
  - `id` (string, required): Category slug or code (e.g. `panjabi`, `electronics`, `t-shirt`).
  - `workspace_id` (integer, required): Tenant workspace ID.
  - `name` (string, required): Human-readable name.

### 5. `Order`
Commercial transaction anchor discussed by the customer.
- **Label**: `:Order`
- **Properties**:
  - `id` (string, required): Order reference number (e.g. `#1042`, `ORD-9872`).
  - `workspace_id` (integer, required): Tenant workspace ID.
  - `discussed_at` (datetime): When the customer asked about this order.
  - *(Note: Live status, total, and items are NOT stored here permanently. Live status is resolved from Order DB).*

### 6. `PaymentMethod`
Supported payment channel or instrument.
- **Label**: `:PaymentMethod`
- **Properties**:
  - `code` (string, required): `bkash`, `nagad`, `rocket`, `card_visa`, `card_mastercard`, `cash_on_delivery`.
  - `name` (string, required): Display name.

### 7. `DeliveryMethod`
Courier or delivery mode.
- **Label**: `:DeliveryMethod`
- **Properties**:
  - `code` (string, required): `pathao`, `steadfast`, `redx`, `sundarban`, `inside_dhaka_regular`.
  - `name` (string, required): Display name.

### 8. `Issue`
A friction point, complaint, or problem reported by the customer.
- **Label**: `:Issue`
- **Properties**:
  - `id` (string, required): Unique issue identifier.
  - `workspace_id` (integer, required): Tenant workspace ID.
  - `category` (string, required): `damaged_item`, `delivery_delay`, `wrong_size`, `payment_failed`, `bot_misunderstanding`.
  - `description` (string, required): Concise summary of the issue.
  - `reported_at` (datetime): Timestamp when reported.
  - `status` (string): `active`, `escalated`, `resolved`.

### 9. `Preference`
A stable user preference or trait.
- **Label**: `:Preference`
- **Properties**:
  - `key` (string, required): `size`, `color`, `payment_method`, `delivery_time`, `language`.
  - `value` (string, required): `XL`, `black`, `bkash`, `evening`, `bn`.
  - `confidence` (float): Extraction confidence (0.0 to 1.0).

---

## 3. Future Roadmap Entities (Phase 11+)

The following entities are planned for future deep enterprise integrations:
- `:CRMProfile`: Deep link to external CRM record.
- `:Campaign`: Marketing campaign context customer responded to.
- `:Promotion`: Specific coupon or discount code discussed.
- `:Return` & `:Refund`: Formal return authorization records.
- `:SupportTicket`: Formal ticketing integration (Zendesk / Freshdesk).
- `:Address`: Customer's verified delivery destination.
