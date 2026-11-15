# Enterprise AI Business Operating & Customer Support Platform
**Multi-Tenant SaaS for E-Commerce & F-Commerce**

[![Tests](https://img.shields.io/badge/PHPUnit-1153%20passed-brightgreen.svg)](#automated-testing)
[![PHP](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/License-Proprietary-yellow.svg)](#license)

An enterprise-grade, multi-tenant SaaS platform that unifies AI customer support, spreadsheet analytics, business intelligence (BI), long-term conversational memory, and commercial billing into a single operating system for modern e-commerce and F-commerce businesses.

---

## 🏛️ Platform Architecture

```
               ┌─────────────────────────────────────────────────────────────┐
               │                        Business Users                       │
               │         Superadmin · Workspace Owner · Agent · Customer      │
               └──────────────────────────────┬──────────────────────────────┘
                                              │
               ┌──────────────────────────────▼──────────────────────────────┐
               │                Unified Chat & Application Layer              │
               │   Omnichannel Webhooks · Web Chat Widget · Admin Simulator  │
               └──────────────────────────────┬──────────────────────────────┘
                                              │
               ┌──────────────────────────────▼──────────────────────────────┐
               │                  Unified AI Orchestration                   │
               │  Unified Decision Engine (UDE) · Policy & DLP Controller    │
               │      Tool Execution Controller · Multi-Turn Context Gate     │
               └──────────────┬───────────────────────────────┬──────────────┘
                              │                               │
        ┌─────────────────────▼────────┐             ┌────────▼────────────────────┐
        │    Knowledge Intelligence    │             │    Business Intelligence    │
        │ Typesense Hybrid Vector/BM25 │             │ Dual-Path Execution Engine  │
        │   MySQL Fulltext Fallback    │             │  Virtual Relational SQLite  │
        │ Long-Term Neo4j Graph Memory │             │ Analytical Tables & Aggs    │
        └──────────────────────────────┘             └─────────────────────────────┘
```

---

## 👥 Personas & Core Capabilities

| Persona | Core Capabilities & Workspaces |
| :--- | :--- |
| **Superadmin** | Global commercial governance, MRR tracking, tenant quota allocation, payment gateway reconciliation (bKash, SSLCommerz, Stripe), and system health probes (`/health`). |
| **Workspace Owner** | Business profile management (currency, operating hours, support contacts), multi-sheet spreadsheet ingestion, visual BI charts (`/analytics`), staff RBAC management, and zero cross-tenant leakage. |
| **Support Agent** | Unified omnichannel conversation queue, ticket self-assignment, status & priority lifecycles (`open`, `in_progress`, `resolved`), and manual staff messaging. |
| **Customer** | Omnichannel inbound (WhatsApp, Instagram, Web Widget), safe order fulfillment lookup (`CustomerOrderStatusTool` with zero internal cost leakage), FAQ Q&A, and protected by P0 prompt-injection defense. |

---

## 🛠️ Omnichannel Messaging Integrations

- **Meta WhatsApp Cloud API (`WhatsAppDriver`):** Webhook signature verification, phone ID routing, and media/text outbound delivery.
- **Meta Instagram Graph API (`InstagramDriver`):** Inbound event parsing, user profile hydration, and thread management.
- **Facebook Messenger:** Dual-layer webhook verification and high-concurrency queue processing.
- **Web Chat Widget (`WebsiteDriver`):** Standalone, embeddable JavaScript customer support chat widget located at [`public/widget/chat.js`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/public/widget/chat.js).

---

## 🔒 Security, Governance & DLP

1. **P0 Security Barrier:** Intercepts prompt injections, Developer Mode bypasses, system instructions extraction, and Bengali financial/workforce probing before tool execution.
2. **5-Level DLP Sanitization:** Restricts sensitive internal business data (purchase cost, gross margins, employee salaries) to authorized Owners and Managers only.
3. **HMAC Execution Grants:** Analytical queries against virtual spreadsheets require cryptographic SHA-256 HMAC signature verification with replay defense.
4. **Row-Level Tenant Isolation:** Database queries strictly enforce `workspace_id` boundaries. Unauthorized cross-tenant requests fail closed with `403 Forbidden`.

---

## 💾 Automated Database Backup & Disaster Recovery

The platform includes point-in-time database backup and restoration commands with automated retention purging and SHA-256 integrity verification:

```bash
# Create point-in-time backup with SHA-256 integrity checksum
php artisan db:backup --compress --retention=30

# Restore from backup archive with cryptographic integrity validation
php artisan db:restore prod_snapshot.sql.gz --force
```

Full operational runbook available at [`docs/operations/DISASTER_RECOVERY.md`](file:///Users/zesan/Desktop/Entrepreneurs_Automation/multi-source-chatbot/docs/operations/DISASTER_RECOVERY.md).

---

## 🧪 Automated Testing & Verification

The test suite provides comprehensive test coverage across unit, feature, security, concurrency, channel driver, and end-to-end multi-persona workflows:

```bash
# Run full automated test suite
php -d memory_limit=1024M vendor/bin/phpunit

# Run focused 4-Persona End-to-End staging validation
php vendor/bin/phpunit tests/Feature/E2E/FullPlatformMultiPersonaE2ETest.php

# Run database backup & recovery tests
php vendor/bin/phpunit tests/Feature/Console/DatabaseBackupCommandTest.php
```

**Quality Baseline:** 1,153 automated tests · 6,218 assertions · 0 failures · 0 errors (100% Pass Rate).

---

## ⚙️ Getting Started

### Prerequisites
- PHP 8.4+ with `pdo`, `mbstring`, `openssl`, `curl`, `redis` extensions
- Composer 2.x
- MySQL 8.0+ / SQLite 3
- Node.js & NPM (for frontend assets)

### Setup Instructions
```bash
# 1. Clone repository & install dependencies
composer install
npm install && npm run build

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Run database migrations & seeders
php artisan migrate
php artisan db:seed --class=RoleSeeder

# 4. Start background queue workers
php artisan queue:work --queue=messenger,spreadsheet,default

# 5. Verify system health
curl http://localhost/health
```

---

## 📄 License
Proprietary & Confidential. All rights reserved.
