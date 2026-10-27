with open("docs/product-audit/central-ai/05_END_TO_END_WORKFLOWS.md", "r") as f:
    text = f.read()

old_diagram = """```mermaid
sequenceDiagram
    autonumber
    actor Buyer as Customer
    participant Bot as AI Sales Agent
    participant Catalog as Offerings Database
    participant OrderSys as Order Management
    participant Payment as bKash / Nagad / SSLCommerz
    actor Admin as Store Manager

    Buyer->>Bot: "I want to purchase the Starter Plan"
    Bot->>Catalog: Lookup Offering SKU: STARTER-01
    Catalog-->>Bot: Return details (Price: $15 / 1,800 BDT)
    Bot-->>Buyer: Interactive Offering Card with [Checkout]
    Buyer->>Bot: Clicks [Checkout] / Selects bKash MFS
    Bot->>OrderSys: Generate Pending Order (#ORD-1044)
    Bot-->>Buyer: "Send 1,800 BDT to 017XXXXXXXX & reply with TrxID"
    Buyer->>Payment: Completes transfer via bKash App
    Buyer->>Bot: Completes payment on merchant checkout page
    Bot->>OrderSys: Sends signed order webhook (Central-AI-Signature)
    OrderSys-->>Admin: Alert: New order requires manual verification
    Admin->>OrderSys: Clicks [Verify & Confirm]
    OrderSys-->>Bot: Order Status = CONFIRMED
    Bot-->>Buyer: "Payment verified! Your order #ORD-1044 is active."
```"""

new_diagram = """```mermaid
sequenceDiagram
    autonumber
    actor Buyer as Customer in Chat
    participant Bot as AI Sales Agent
    participant Catalog as Offerings Database
    participant MerchantCart as Merchant Website Checkout
    participant MerchantServer as Merchant Backend Server
    participant CentralAI as Central AI Webhook Gateway

    Buyer->>Bot: "I want to purchase the Starter Plan"
    Bot->>Catalog: Lookup Offering SKU: STARTER-01
    Catalog-->>Bot: Return details (Price: $15 / 1,800 BDT)
    Bot-->>Buyer: Renders Offering Card with [Checkout on yoursite.com]
    Buyer->>MerchantCart: Clicks button -> Opens https://yourshop.com/cart/add?items=STARTER-01:1&ref=cai_...
    Buyer->>MerchantCart: Logs in / Pays via merchant's gateway (SSLCommerz / Card / COD)
    MerchantCart->>MerchantServer: Order created & paid (#ORD-1044)
    MerchantServer->>CentralAI: POST /webhooks/orders/ow_.../ with Central-AI-Signature
    CentralAI-->>Bot: Push order status event (Status: Paid)
    Bot-->>Buyer: Chat bubble automatically updates: "Order #ORD-1044 confirmed!"
```"""

if old_diagram in text:
    text = text.replace(old_diagram, new_diagram)
    with open("docs/product-audit/central-ai/05_END_TO_END_WORKFLOWS.md", "w") as f:
        f.write(text)
    print("Fixed Workflow 3 successfully")
else:
    print("Could not find exact old diagram in 05_END_TO_END_WORKFLOWS.md")
