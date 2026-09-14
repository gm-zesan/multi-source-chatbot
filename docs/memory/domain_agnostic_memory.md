# Domain-Agnostic Memory Service

This document outlines the architectural changes made to transform the `python-memory-service` from an e-commerce-specific system into a fully generic, domain-agnostic memory graph. 

## 1. What Changed?

### Removed E-Commerce Hardcoding
All specific e-commerce schemas, regex patterns, and Neo4j queries were removed:
- Removed `SIZE_PATTERNS`, `COLOR_MAP`, `PAYMENT_METHODS`, `DELIVERY_PATTERNS`, `ISSUE_KEYWORDS`.
- Removed `set_size_preference`, `set_payment_preference`, `set_color_preference`.
- Removed keyword intent domains from `memory_retriever.py` (e.g., `payment`, `size`, `color`).

### Generic Graph Nodes & Relationships
The graph schema in `neo4j_client.py` has been generalized to three core categories:
1. **Preferences**: `(Customer)-[HAS_PREFERENCE]->(Preference {category, value})`
2. **Interests**: `(Customer)-[INTERESTED_IN]->(Entity {type, name})`
3. **Issues**: `(Customer)-[REPORTED_ISSUE]->(Issue {category, description})`

### Generic LLM Extraction Prompt
The `memory_extractor.py` was updated to instruct the LLM to output a generic semantic schema rather than looking for sizes and colors:
```json
{
  "preferences": [{"category": "string", "value": "string"}],
  "interests": [{"entity_type": "string", "entity_name": "string"}],
  "issues": [{"category": "string", "description": "string"}]
}
```

### Generic Semantic Retrieval
`memory_retriever.py` was refactored to score relevance dynamically. If the user's query tokens match any tokens in the `category` or `object` strings in the graph, they are retrieved dynamically, regardless of domain.

---

## 2. Testing the Generic Capabilities

To verify that the service is domain-agnostic, we tested it with a **Healthcare / Hospital Scenario** instead of an e-commerce store.

### Ingestion Scenario
**Conversation:**
- **Customer**: "I want to book an appointment."
- **AI**: "Sure, which specialist do you want to see?"
- **Customer**: "A cardiologist. Also, please note I have a severe peanut allergy."

**Results in Neo4j Graph:**
- Created `Preference` node: `{category: "medical specialist", value: "cardiologist"}`
- Created `Issue` node: `{category: "allergy", description: "severe peanut allergy"}`

### Retrieval Scenario
**Query**: "allergy"
**Retrieval Output:**
```text
Customer Historical Preferences & Context:
- Previous Issue (allergy): severe peanut allergy
- Preferred medical specialist: cardiologist
```

> [!SUCCESS]
> The healthcare scenario demonstrates that the generalized schema can represent and retrieve non-e-commerce memory without domain-specific code changes. This provides evidence of domain-agnostic behavior, but does not constitute exhaustive proof across all domains.
