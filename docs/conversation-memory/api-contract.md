# Conversation Graph Memory: API Contract Specification

**Component**: `conversation-memory/api-contract`  
**Version**: 1.0.0-draft  
**Status**: Specification Phase  
**Base Protocol**: REST JSON / HTTP  
**Service Target**: `python-memory-service` (Port `8002`)  
**Client**: `multi-source-chatbot` (Laravel `RetrievalClient` / `ConversationMemoryService`)

---

## 1. Health & Readiness

### `GET /health`
Verifies memory service status and Neo4j connectivity.

#### Response (200 OK):
```json
{
  "status": "ok",
  "service": "python-memory-service",
  "version": "1.0.0",
  "neo4j": {
    "status": "connected",
    "latency_ms": 2.4
  }
}
```

---

## 2. Ingestion Endpoint (Asynchronous Background Pipeline)

### `POST /memory/ingest`
Invoked asynchronously by Laravel's `IngestConversationMemoryJob` when a customer conversation completes or updates.

#### Headers:
- `Content-Type: application/json`
- `X-Workspace-Id: 1`

#### Request Payload:
```json
{
  "workspace_id": 1,
  "customer_id": "cust_98234",
  "conversation_id": "conv_89127391-7291-4912",
  "channel": "facebook",
  "messages": [
    {
      "direction": "inbound",
      "body": "Hi, I want to order a black panjabi in XL size.",
      "timestamp": "2026-09-01T12:00:00Z"
    },
    {
      "direction": "outbound",
      "body": "Sure! We have Navy Blue and Black Cotton Panjabis in XL. Would you like to pay via bKash or Cash on Delivery?",
      "timestamp": "2026-09-01T12:00:05Z"
    },
    {
      "direction": "inbound",
      "body": "I prefer bKash payment.",
      "timestamp": "2026-09-01T12:00:15Z"
    }
  ]
}
```

#### Response (202 Accepted):
```json
{
  "success": true,
  "status": "queued_for_extraction",
  "job_id": "mem_job_198273918"
}
```

---

## 3. Retrieval Endpoint (Synchronous Fast-Path Subgraph Search)

### `POST /memory/search`
Invoked synchronously by Laravel's `ConversationMemoryService` ONLY when `MemoryRelevanceGate` evaluates `true`.

#### Request Payload:
```json
{
  "workspace_id": 1,
  "customer_id": "cust_98234",
  "conversation_id": "conv_current_session",
  "query": "Show me another like the one I bought last time in the same size.",
  "limit": 5,
  "min_relevance": 0.60
}
```

#### Response (200 OK):
```json
{
  "success": true,
  "customer_id": "cust_98234",
  "has_memories": true,
  "memories_count": 2,
  "memories": [
    {
      "type": "preference",
      "subject": "Customer",
      "relation": "PREFERS_SIZE",
      "object": "XL",
      "status": "current",
      "confidence": 0.95
    },
    {
      "type": "historical_action",
      "subject": "Customer",
      "relation": "PURCHASED",
      "object": "Black Cotton Panjabi",
      "attributes": {
        "color": "Black",
        "size": "XL",
        "category": "Panjabi"
      },
      "status": "past",
      "confidence": 0.92
    }
  ],
  "formatted_memory_context": "Customer historical preferences & purchases:\n- Prefers size: XL\n- Previously purchased: Black Cotton Panjabi (Size: XL, Color: Black)",
  "latency_ms": 18.5
}
```

---

## 4. Deletion & Privacy Endpoints

### `DELETE /memory/customer/{customer_id}`
Permanently deletes all graph memory associated with a specific customer within a workspace.

#### Request:
- Path parameter: `customer_id` (string)
- Header: `X-Workspace-Id: 1`

#### Response (200 OK):
```json
{
  "success": true,
  "deleted_customer_id": "cust_98234",
  "detached_edges_count": 7,
  "purged_nodes_count": 3
}
```

### `DELETE /memory/conversation/{conversation_id}`
Removes memory assertions created in a specific conversation session.

#### Response (200 OK):
```json
{
  "success": true,
  "deleted_conversation_id": "conv_89127391-7291-4912"
}
```
