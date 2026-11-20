@echo off
chcp 65001 >nul
title Multi-Source Chatbot Platform - Unified Service Launcher (Windows)

echo =================================================================
echo  Starting Multi-Source Chatbot Platform (All Microservices)
echo =================================================================
set SCRIPT_DIR=%~dp0
cd /d "%SCRIPT_DIR%.."
set PROJECT_ROOT=%CD%
cd /d "%PROJECT_ROOT%\.."
set PARENT_ROOT=%CD%

echo Project Root : %PROJECT_ROOT%
echo Parent Root  : %PARENT_ROOT%
echo.

:: 1. Start Typesense (8108) and Neo4j (7474/7687) via Docker if available
if exist "%PARENT_ROOT%\typesense\docker-compose.yml" (
    echo [1/5] Checking Docker for Typesense & Neo4j...
    where docker >nul 2>&1
    if %ERRORLEVEL% equ 0 (
        echo       Starting Typesense (port 8108) & Neo4j (ports 7474, 7687)...
        cd /d "%PARENT_ROOT%\typesense"
        docker compose up -d
        cd /d "%PROJECT_ROOT%"
    ) else (
        echo       [NOTE] Docker not found in PATH. Please ensure Typesense and Neo4j are running natively or start Docker Desktop.
    )
) else (
    echo [1/5] typesense directory not found at "%PARENT_ROOT%\typesense". Assuming already running.
)

:: 2. Start Python AI Retrieval Service (Port 8001)
if exist "%PARENT_ROOT%\python-ai-service" (
    echo [2/5] Starting Python AI Retrieval Service on port 8001 in a new window...
    start "Python AI Retrieval Service (Port 8001)" cmd /k "cd /d ""%PARENT_ROOT%\python-ai-service"" && (if not exist venv python -m venv venv) && call venv\Scripts\activate.bat && if not exist venv\Scripts\uvicorn.exe pip install -r requirements.txt && uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload"
) else (
    echo [2/5] WARNING: Directory "%PARENT_ROOT%\python-ai-service" not found!
)

:: 3. Start Python Graph Memory Service (Port 8002)
if exist "%PARENT_ROOT%\python-memory-service" (
    echo [3/5] Starting Python Graph Memory Service on port 8002 in a new window...
    start "Python Graph Memory Service (Port 8002)" cmd /k "cd /d ""%PARENT_ROOT%\python-memory-service"" && (if not exist venv python -m venv venv) && call venv\Scripts\activate.bat && if not exist venv\Scripts\uvicorn.exe pip install -r requirements.txt && uvicorn app.main:app --host 127.0.0.1 --port 8002 --reload"
) else (
    echo [3/5] WARNING: Directory "%PARENT_ROOT%\python-memory-service" not found!
)

:: 4. Start Laravel Background Queue Worker
echo [4/5] Starting Laravel Queue Worker in a new window...
start "Laravel Background Queue Worker" cmd /k "cd /d ""%PROJECT_ROOT%"" && php artisan queue:work --queue=faq,default,documents,memory,telemetry --tries=3 --timeout=90"

:: 5. Display Dashboard Info & Start Laravel HTTP Server
echo [5/5] Launching Laravel Web Application on http://127.0.0.1:8000...
echo.
echo =================================================================
echo  PLATFORM SERVICES STATUS & URLS:
echo   - Web Application : http://127.0.0.1:8000
echo   - Superadmin Login: http://127.0.0.1:8000/login (zesan@gmail.com / password)
echo   - Workspaces Grid : http://127.0.0.1:8000/dashboard/workspaces
echo   - SaaS Commercial : http://127.0.0.1:8000/dashboard/commercial
echo   - Chat Widget UI  : http://127.0.0.1:8000/dashboard/widget
echo   - Widget Demo     : http://127.0.0.1:8000/widget/demo
echo   - Python AI Docs  : http://127.0.0.1:8001/docs
echo   - Python Mem Docs : http://127.0.0.1:8002/docs
echo   - Typesense API   : http://127.0.0.1:8108/health
echo   - Neo4j Browser   : http://127.0.0.1:7474
echo =================================================================
echo Press Ctrl+C to stop the main web server.
echo Close individual command windows when you are done.
echo.

cd /d "%PROJECT_ROOT%"
php artisan serve --host=127.0.0.1 --port=8000
