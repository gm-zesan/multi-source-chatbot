<#
.SYNOPSIS
    Multi-Source Chatbot & AI Platform - Unified Service Launcher (PowerShell / Windows)
.DESCRIPTION
    Starts Typesense/Neo4j (Docker), Python AI Service (8001), Python Memory Service (8002),
    Laravel Queue Worker, and Laravel Web Server (8000).
#>

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Resolve-Path "$ScriptDir\.."
$ParentRoot = Resolve-Path "$ProjectRoot\.."

Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host " Starting Multi-Source Chatbot Platform (All Microservices)     " -ForegroundColor Cyan
Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host "Project Root : $ProjectRoot"
Write-Host "Parent Root  : $ParentRoot"
Write-Host ""

# 1. Start Typesense and Neo4j via Docker if available
$typesenseDir = Join-Path $ParentRoot "typesense"
if ((Test-Path "$typesenseDir\docker-compose.yml") -and (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host ">> [1/5] Starting Typesense (8108) & Neo4j (7474/7687) via Docker..." -ForegroundColor Yellow
    Push-Location $typesenseDir
    docker compose up -d
    Pop-Location
} else {
    Write-Host ">> [1/5] Skipping Typesense/Neo4j Docker auto-start. Ensure ports 8108 and 7687 are active." -ForegroundColor Gray
}

# 2. Start Python AI Retrieval Service (Port 8001)
$pythonAiDir = Join-Path $ParentRoot "python-ai-service"
if (Test-Path $pythonAiDir) {
    Write-Host ">> [2/5] Starting Python AI Retrieval Service on port 8001..." -ForegroundColor Yellow
    $aiCmd = "cd '$pythonAiDir'; if (!(Test-Path 'venv')) { python -m venv venv }; .\venv\Scripts\Activate.ps1; pip install -r requirements.txt; uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload"
    Start-Process powershell -ArgumentList "-NoExit", "-Command", $aiCmd -WindowStyle Normal
} else {
    Write-Host ">> [2/5] WARNING: Directory '$pythonAiDir' not found!" -ForegroundColor Red
}

# 3. Start Python Graph Memory Service (Port 8002)
$pythonMemDir = Join-Path $ParentRoot "python-memory-service"
if (Test-Path $pythonMemDir) {
    Write-Host ">> [3/5] Starting Python Graph Memory Service on port 8002..." -ForegroundColor Yellow
    $memCmd = "cd '$pythonMemDir'; if (!(Test-Path 'venv')) { python -m venv venv }; .\venv\Scripts\Activate.ps1; pip install -r requirements.txt; uvicorn app.main:app --host 127.0.0.1 --port 8002 --reload"
    Start-Process powershell -ArgumentList "-NoExit", "-Command", $memCmd -WindowStyle Normal
} else {
    Write-Host ">> [3/5] WARNING: Directory '$pythonMemDir' not found!" -ForegroundColor Red
}

# 4. Start Laravel Background Queue Worker
Write-Host ">> [4/5] Starting Laravel Queue Worker..." -ForegroundColor Yellow
$queueCmd = "cd '$ProjectRoot'; php artisan queue:work --queue=faq,default,documents,memory,telemetry --tries=3 --timeout=90"
Start-Process powershell -ArgumentList "-NoExit", "-Command", $queueCmd -WindowStyle Normal

# 5. Display Information & Start Laravel Web Server
Write-Host ""
Write-Host "=================================================================" -ForegroundColor Green
Write-Host " PLATFORM SERVICES & URLS:" -ForegroundColor Green
Write-Host "   - Web Application : http://127.0.0.1:8000"
Write-Host "   - Superadmin Login: http://127.0.0.1:8000/login (zesan@gmail.com / password)"
Write-Host "   - Workspaces Grid : http://127.0.0.1:8000/dashboard/workspaces"
Write-Host "   - SaaS Commercial : http://127.0.0.1:8000/dashboard/commercial"
Write-Host "   - Chat Widget UI  : http://127.0.0.1:8000/dashboard/widget"
Write-Host "   - Widget Demo     : http://127.0.0.1:8000/widget/demo"
Write-Host "   - Python AI Docs  : http://127.0.0.1:8001/docs"
Write-Host "   - Python Mem Docs : http://127.0.0.1:8002/docs"
Write-Host "   - Typesense API   : http://127.0.0.1:8108/health"
Write-Host "   - Neo4j Browser   : http://127.0.0.1:7474"
Write-Host "=================================================================" -ForegroundColor Green
Write-Host "Press Ctrl+C in this terminal to stop the main web server."
Write-Host "Close individual spawned terminal windows when finished."
Write-Host ""

Set-Location $ProjectRoot
php artisan serve --host=127.0.0.1 --port=8000
