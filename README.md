# Enterprise AI Business Operating & Customer Support Platform
**Multi-Tenant SaaS for E-Commerce & F-Commerce**

[![Tests](https://img.shields.io/badge/PHPUnit-1160%20passed-brightgreen.svg)](#automated-testing)
[![PHP](https://img.shields.io/badge/PHP-8.4+-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![FastAPI](https://img.shields.io/badge/FastAPI-0.110+-009688.svg)](https://fastapi.tiangolo.com)
[![Typesense](https://img.shields.io/badge/Typesense-29.0-D82C20.svg)](https://typesense.org)
[![Neo4j](https://img.shields.io/badge/Neo4j-5.x-008CC1.svg)](https://neo4j.com)
[![License](https://img.shields.io/badge/License-Proprietary-yellow.svg)](#license)

An enterprise-grade, multi-tenant SaaS platform that unifies AI customer support, spreadsheet analytics, business intelligence (BI), long-term conversational memory, and commercial billing into a single operating system for modern e-commerce and F-commerce businesses.

---

## 🏛️ System Architecture & Services Port Reference

The platform operates as a coordinated multi-service ecosystem. Below is the complete port and service map:

| Service | Technology | Port | Directory / Location | Purpose / Description |
| :--- | :--- | :--- | :--- | :--- |
| **Laravel Core Web App** | PHP 8.4 / Laravel 12 | `8000` | `multi-source-chatbot/` | Main application, REST APIs, Webhooks, Superadmin & Owner Dashboards |
| **Python AI Retrieval Service** | Python 3.10+ / FastAPI | `8001` | `../python-ai-service/` | Multilingual text embeddings, semantic FAQ search, LLM orchestration |
| **Python Graph Memory Service** | Python 3.10+ / FastAPI | `8002` | `../python-memory-service/` | Graphiti long-term conversational memory, Neo4j graph entity relationships |
| **Typesense Search Engine** | Typesense 29.0 (Docker) | `8108` | `../typesense/` | High-speed hybrid vector & BM25 search for FAQs and public catalog |
| **Neo4j Graph Database** | Neo4j 5 Community | `7474`, `7687` | `../typesense/` | Property graph for conversational memory (`7474` HTTP UI, `7687` Bolt) |
| **MySQL Database** | MySQL 8.x | `3306` | Native / Docker | Core relational database (`chatbot_core`), transactional and analytics data |
| **Redis Cache & Queue** | Redis 7.x | `6379` | Native / Docker | High-throughput message queuing, caching, and monotonic SLA rate limiting |
| **Queue Worker** | Laravel Artisan | N/A | `multi-source-chatbot/` | Asynchronous worker processing `faq,documents,memory,telemetry,default` |
| **Vite Frontend Assets** | Vite / TailwindCSS | `5173` | `multi-source-chatbot/` | Hot module reloading (HMR) for dashboard and UI assets |

---

## ⚡ 1-Click Startup Scripts (Fastest Way to Run Everything)

Ready-to-use launch scripts are provided for both macOS and Windows in the `scripts/` directory.

### macOS & Linux (Bash)
Open your Terminal and run:
```bash
cd multi-source-chatbot
bash scripts/start-all.sh
```
*This starts Typesense & Neo4j (Docker), Python AI Service (8001), Python Memory Service (8002), Laravel Queue Worker, and Laravel Web Server (8000). Press `Ctrl+C` to cleanly terminate all background services.*

### Windows (PowerShell)
Open PowerShell (as Administrator or with script execution allowed) and run:
```powershell
cd multi-source-chatbot
.\scripts\start-all.ps1
```
*If script execution is blocked on Windows, first run: `Set-ExecutionPolicy -Scope CurrentUser RemoteSigned`.*

### Windows (Command Prompt / Batch)
Double-click `scripts\start-all.bat` or run in CMD:
```cmd
cd multi-source-chatbot
scripts\start-all.bat
```
*This opens dedicated, labeled terminal windows for each microservice so you can monitor live logs and easily restart any service.*

---

## 📋 System Prerequisites

Ensure the following runtimes and tools are installed on your host system:

### For macOS
1. **Homebrew**: `/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"`
2. **PHP 8.4 & Extensions**:
   ```bash
   brew install php@8.4 composer
   brew link --force --overwrite php@8.4
   ```
3. **Node.js (v20+)**: `brew install node`
4. **Python 3.10+ / 3.12**: `brew install python@3.12`
5. **MySQL 8 & Redis**:
   ```bash
   brew install mysql redis
   brew services start mysql
   brew services start redis
   ```
6. **Docker Desktop**: Required to run Typesense and Neo4j via Docker Compose.

### For Windows
1. **PHP 8.4**: Install via [Laragon](https://laragon.org/) (recommended), [XAMPP](https://www.apachefriends.org/), or standalone PHP 8.4 binaries from [windows.php.net](https://windows.php.net/download/).
   *Ensure `php.exe` is added to your Windows System `PATH`.*
   *Enable necessary extensions in `php.ini`: `pdo_mysql`, `curl`, `mbstring`, `fileinfo`, `openssl`, `sodium`.*
2. **Composer**: Download and run the installer from [getcomposer.org](https://getcomposer.org/Composer-Setup.exe).
3. **Node.js (v20+)**: Download and run installer from [nodejs.org](https://nodejs.org/).
4. **Python 3.10+ / 3.12**: Download installer from [python.org](https://www.python.org/).
   *⚠️ CRITICAL: Check the box **"Add python.exe to PATH"** during installation.*
5. **MySQL 8.x**: Included in Laragon/XAMPP or installed via MySQL Community Installer on port `3306`.
6. **Redis**: Use [Redis for Windows (GitHub port)](https://github.com/zkteco-home/redis-windows), Memurai, WSL2 Redis, or Docker Redis on port `6379`.
7. **Docker Desktop for Windows**: Required for Typesense & Neo4j. Ensure WSL2 backend is enabled.

---

## 🛠️ Step-by-Step Manual Execution Guide

If you prefer starting each service independently in separate terminals, follow the exact steps below:

```
Directory Hierarchy Expected:
└── Entrepreneurs_Automation/
    ├── multi-source-chatbot/       <-- (Laravel Core Web App)
    ├── python-ai-service/          <-- (Python Semantic Search & Embeddings)
    ├── python-memory-service/      <-- (Python Neo4j Graph Memory)
    └── typesense/                  <-- (Docker Compose for Typesense & Neo4j)
```

---

### Step 1: Start Typesense (8108) & Neo4j (7474 / 7687)

Both Typesense and Neo4j are containerized under the `../typesense` folder.

#### macOS / Linux:
```bash
cd /path/to/Entrepreneurs_Automation/typesense
docker compose up -d
```

#### Windows (PowerShell or CMD):
```powershell
cd C:\path\to\Entrepreneurs_Automation\typesense
docker compose up -d
```

*Verification:*
- Typesense Health: Visit `http://127.0.0.1:8108/health` (should return `{"ok":true}`).
- Neo4j Browser UI: Visit `http://127.0.0.1:7474` (Credentials: `neo4j` / `secret1234`).

---

### Step 2: Start Python AI & Semantic Retrieval Service (Port 8001)

Handles multilingual vector embeddings (`paraphrase-multilingual-mpnet-base-v2`), candidate retrieval, and LLM reasoning.

#### macOS / Linux:
```bash
cd /path/to/Entrepreneurs_Automation/python-ai-service

# Create virtual environment if not already created
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Ensure .env is present
cp -n .env.example .env

# Run FastAPI with Uvicorn
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

#### Windows (PowerShell):
```powershell
cd C:\path\to\Entrepreneurs_Automation\python-ai-service

# Create virtual environment
python -m venv venv
.\venv\Scripts\Activate.ps1

# Install dependencies
pip install -r requirements.txt

# Ensure .env exists
if (!(Test-Path .env)) { Copy-Item .env.example .env }

# Run FastAPI with Uvicorn
uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

#### Windows (Command Prompt / CMD):
```cmd
cd C:\path\to\Entrepreneurs_Automation\python-ai-service

python -m venv venv
call venv\Scripts\activate.bat
pip install -r requirements.txt
if not exist .env copy .env.example .env

uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

*Verification:*
- Interactive API Docs: `http://127.0.0.1:8001/docs`
- Health check: `http://127.0.0.1:8001/health`

---

### Step 3: Start Python Graph Memory & Analytics Service (Port 8002)

Handles conversational history extraction and Neo4j graph entity tracking via Graphiti.

#### macOS / Linux:
```bash
cd /path/to/Entrepreneurs_Automation/python-memory-service

# Create virtual environment
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt

# Ensure .env exists
cp -n .env.example .env

# Run FastAPI with Uvicorn
uvicorn app.main:app --host 127.0.0.1 --port 8002 --reload
```

#### Windows (PowerShell):
```powershell
cd C:\path\to\Entrepreneurs_Automation\python-memory-service

python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
if (!(Test-Path .env)) { Copy-Item .env.example .env }

uvicorn app.main:app --host 127.0.0.1 --port 8002 --reload
```

#### Windows (Command Prompt / CMD):
```cmd
cd C:\path\to\Entrepreneurs_Automation\python-memory-service

python -m venv venv
call venv\Scripts\activate.bat
pip install -r requirements.txt
if not exist .env copy .env.example .env

uvicorn app.main:app --host 127.0.0.1 --port 8002 --reload
```

*Verification:*
- Interactive API Docs: `http://127.0.0.1:8002/docs`
- Health check: `http://127.0.0.1:8002/health`

---

### Step 4: Setup & Run Laravel Core Application (Port 8000)

#### macOS / Linux:
```bash
cd /path/to/Entrepreneurs_Automation/multi-source-chatbot

# 1. Install PHP dependencies
composer install

# 2. Environment configuration
cp -n .env.example .env
php artisan key:generate

# 3. Migrate and seed the database
php artisan migrate
php artisan db:seed

# 4. Start HTTP development server
php artisan serve --host=127.0.0.1 --port=8000
```

#### Windows (PowerShell or CMD):
```powershell
cd C:\path\to\Entrepreneurs_Automation\multi-source-chatbot

# 1. Install PHP dependencies
composer install

# 2. Environment configuration
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate

# 3. Migrate and seed database
php artisan migrate
php artisan db:seed

# 4. Start HTTP server
php artisan serve --host=127.0.0.1 --port=8000
```

*Verification:*
- Open `http://127.0.0.1:8000` in your web browser.

---

### Step 5: Start Laravel Background Queue Worker

Background workers are essential for processing incoming webhook messages, building customer graph memory, and syncing documents asynchronously without blocking web requests.

#### macOS / Linux:
```bash
cd /path/to/Entrepreneurs_Automation/multi-source-chatbot
php artisan queue:work --queue=faq,default,documents,memory,telemetry --tries=3 --timeout=90
```

#### Windows (PowerShell or CMD):
```powershell
cd C:\path\to\Entrepreneurs_Automation\multi-source-chatbot
php artisan queue:work --queue=faq,default,documents,memory,telemetry --tries=3 --timeout=90
```

---

### Step 6: Compile Frontend Assets (Vite)

#### macOS / Linux:
```bash
cd /path/to/Entrepreneurs_Automation/multi-source-chatbot
npm install

# For local development with live hot-reload:
npm run dev

# Or for production compiled assets:
npm run build
```

#### Windows (PowerShell or CMD):
```powershell
cd C:\path\to\Entrepreneurs_Automation\multi-source-chatbot
npm install
npm run dev
```

---

### Step 7: Index FAQs into Typesense Vector Engine

> [!IMPORTANT]
> **Why is this required?**
> If you ask the chatbot questions and it responds with:
> *"দুঃখিত, এই বিষয়টি আমাদের কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়।"*
> this means the FAQ database entries have not yet been synchronized into the Python AI Service / Typesense vector collection.

Once MySQL, Typesense (8108), and Python AI Service (8001) are active, run the following sync command:

#### macOS / Linux & Windows:
```bash
php artisan faq:reindex --force
```

Alternatively, from the Web UI:
1. Log in to the dashboard at `http://127.0.0.1:8000/dashboard`
2. Navigate to **Knowledge Base** (`/dashboard/faqs` or `/knowledge`)
3. Click the **"Sync to Vector Engine"** button.

---

## 🔑 Default Credentials & Interactive Navigation Directory

### Demo & Administrative Logins:
| Role | Email | Password | Scope & Privileges |
| :--- | :--- | :--- | :--- |
| **Superadmin** | `zesan@gmail.com` | `password` | Global access across all workspaces, commercial billing, tenant quotas, system health |
| **Workspace Owner** | `admin@gmail.com` | `password` | Workspace-level governance, staff management, spreadsheets, BI analytics |
| **Support Agent** | `agent@example.com` | `password` | Omnichannel chat inbox, ticket assignment, conversation status lifecycle |

### Platform Key URLs:
- **Web Application Portal:** [http://127.0.0.1:8000](http://127.0.0.1:8000)
- **Login Screen:** [http://127.0.0.1:8000/login](http://127.0.0.1:8000/login)
- **Superadmin Workspaces Live Grid & Table:** [http://127.0.0.1:8000/dashboard/workspaces](http://127.0.0.1:8000/dashboard/workspaces)
- **SaaS Commercial & Quota Management:** [http://127.0.0.1:8000/dashboard/commercial](http://127.0.0.1:8000/dashboard/commercial)
- **Business Intelligence & Analytics:** [http://127.0.0.1:8000/dashboard/analytics](http://127.0.0.1:8000/dashboard/analytics)
- **Web Chat Widget Studio & Embed Code:** [http://127.0.0.1:8000/dashboard/widget](http://127.0.0.1:8000/dashboard/widget)
- **Standalone Chat Widget Demo:** [http://127.0.0.1:8000/widget/demo](http://127.0.0.1:8000/widget/demo)
- **System Health Probes:** [http://127.0.0.1:8000/health](http://127.0.0.1:8000/health)
- **Python AI Swagger Docs:** [http://127.0.0.1:8001/docs](http://127.0.0.1:8001/docs)
- **Python Memory Swagger Docs:** [http://127.0.0.1:8002/docs](http://127.0.0.1:8002/docs)
- **Neo4j Graph Database Browser:** [http://127.0.0.1:7474](http://127.0.0.1:7474)

---

## 🛠️ Verification & Diagnostic Health Checks

To verify that all services and database connections are properly operating:

```bash
# Verify complete chatbot infrastructure connectivity
php artisan chatbot:health
```

Output should indicate passing status across:
- `Database`: MySQL `chatbot_core` read/write
- `Cache`: Redis read/write
- `Queue`: Redis queue availability
- `Storage`: Local filesystem writable
- `Python Retrieval Service`: Port 8001 healthy
- `AI Provider`: Configured model provider (e.g., DeepSeek / OpenAI)

---

## ❓ Common Troubleshooting & Windows / Mac FAQs

### 1. PowerShell Script Execution Error on Windows
- **Symptom:** `File ... start-all.ps1 cannot be loaded because running scripts is disabled on this system.`
- **Fix:** Open PowerShell as Administrator and run:
  ```powershell
  Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned
  ```

### 2. Python Virtual Environment Activation Syntax
- On **macOS / Linux**: `source venv/bin/activate`
- On **Windows PowerShell**: `.\venv\Scripts\Activate.ps1`
- On **Windows CMD**: `venv\Scripts\activate.bat`

### 3. Port Conflicts (e.g., 8000, 8001, 8002, 3306, 8108)
- If port `8000` is already in use by another app:
  ```bash
  php artisan serve --port=8080
  ```
- To check active ports:
  - macOS: `lsof -i :8000` or `lsof -i :8001`
  - Windows: `netstat -ano | findstr :8000`

### 4. Database Connection Refused (`SQLSTATE[HY000] [2002]`)
- Ensure your MySQL server is running.
- In `multi-source-chatbot/.env`, verify `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` match your local MySQL configuration.

### 5. Chatbot Widget Answers "Out of Scope" for All Inquiries
- Run `php artisan faq:reindex --force` while both Python AI Service (8001) and Typesense (8108) are running.
- Verify that the active workspace has FAQs stored in the database (`php artisan tinker` -> `\App\Models\FAQ::count()`).

---

## 🧪 Automated Testing

```bash
# Run complete test suite (1,160 tests)
php -d memory_limit=1024M vendor/bin/phpunit

# Run focused End-to-End multi-persona validation
php vendor/bin/phpunit tests/Feature/E2E/FullPlatformMultiPersonaE2ETest.php
```

---

## 📄 License
Proprietary & Confidential. All rights reserved.
