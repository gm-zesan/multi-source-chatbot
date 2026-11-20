#!/usr/bin/env bash

# ==============================================================================
# Multi-Source Chatbot & AI Platform - Unified Local Service Starter (macOS/Linux)
# ==============================================================================

set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PARENT_DIR="$(cd "${PROJECT_ROOT}/.." && pwd)"

echo "================================================================="
echo " Starting Multi-Source Chatbot Platform (All Microservices)     "
echo "================================================================="
echo "Project Root : ${PROJECT_ROOT}"
echo "Parent Root  : ${PARENT_DIR}"
echo ""

# 1. Start Docker Containers for Typesense and Neo4j if available
if [ -d "${PARENT_DIR}/typesense" ] && command -v docker &> /dev/null; then
    echo ">> [1/5] Starting Typesense (8108) & Neo4j (7474/7687) via Docker..."
    cd "${PARENT_DIR}/typesense"
    docker compose up -d
    cd "${PROJECT_ROOT}"
else
    echo ">> [1/5] Skipping Typesense/Neo4j Docker startup (ensure running on ports 8108 & 7687)."
fi

# 2. Start Python AI Retrieval Service (Port 8001)
if [ -d "${PARENT_DIR}/python-ai-service" ]; then
    echo ">> [2/5] Starting Python AI Retrieval Service on port 8001..."
    cd "${PARENT_DIR}/python-ai-service"
    if [ ! -d "venv" ]; then
        python3 -m venv venv
        source venv/bin/activate
        pip install -r requirements.txt
    else
        source venv/bin/activate
    fi
    uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload > /tmp/python_ai.log 2>&1 &
    PYTHON_AI_PID=$!
    echo "   -> Python AI Service running in background (PID: ${PYTHON_AI_PID})"
    deactivate || true
    cd "${PROJECT_ROOT}"
fi

# 3. Start Python Graph Memory & Analytics Service (Port 8002)
if [ -d "${PARENT_DIR}/python-memory-service" ]; then
    echo ">> [3/5] Starting Python Graph Memory Service on port 8002..."
    cd "${PARENT_DIR}/python-memory-service"
    if [ ! -d "venv" ]; then
        python3 -m venv venv
        source venv/bin/activate
        pip install -r requirements.txt
    else
        source venv/bin/activate
    fi
    uvicorn app.main:app --host 127.0.0.1 --port 8002 --reload > /tmp/python_mem.log 2>&1 &
    PYTHON_MEM_PID=$!
    echo "   -> Python Memory Service running in background (PID: ${PYTHON_MEM_PID})"
    deactivate || true
    cd "${PROJECT_ROOT}"
fi

# 4. Start Laravel Background Queue Worker
echo ">> [4/5] Starting Laravel Queue Worker..."
cd "${PROJECT_ROOT}"
php artisan queue:work --queue=faq,default,documents,memory,telemetry --tries=3 --timeout=90 > /tmp/laravel_queue.log 2>&1 &
QUEUE_PID=$!
echo "   -> Queue Worker running in background (PID: ${QUEUE_PID})"

# 5. Start Laravel Web Server (Port 8000)
echo ">> [5/5] Starting Laravel Web Application on http://127.0.0.1:8000..."
echo ""
echo "================================================================="
echo " Platform URLs:"
echo "   - Web Application : http://127.0.0.1:8000"
echo "   - Superadmin Login: http://127.0.0.1:8000/login (zesan@gmail.com / password)"
echo "   - Workspaces Grid : http://127.0.0.1:8000/dashboard/workspaces"
echo "   - SaaS Commercial : http://127.0.0.1:8000/dashboard/commercial"
echo "   - Chat Widget     : http://127.0.0.1:8000/dashboard/widget"
echo "   - Widget Demo     : http://127.0.0.1:8000/widget/demo"
echo "   - Python AI Docs  : http://127.0.0.1:8001/docs"
echo "   - Python Mem Docs : http://127.0.0.1:8002/docs"
echo "================================================================="
echo "Press Ctrl+C to terminate all services."

cleanup() {
    echo ""
    echo "Shutting down background services..."
    [ -n "${PYTHON_AI_PID}" ] && kill "${PYTHON_AI_PID}" 2>/dev/null || true
    [ -n "${PYTHON_MEM_PID}" ] && kill "${PYTHON_MEM_PID}" 2>/dev/null || true
    [ -n "${QUEUE_PID}" ] && kill "${QUEUE_PID}" 2>/dev/null || true
    echo "All background processes stopped."
}
trap cleanup EXIT INT TERM

php artisan serve --host=127.0.0.1 --port=8000
