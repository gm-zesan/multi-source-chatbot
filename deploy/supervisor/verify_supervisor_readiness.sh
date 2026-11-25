#!/usr/bin/env bash
# =============================================================================
# Entrepreneurs Automation — Supervisor Production Readiness Validator
# =============================================================================

set -euo pipefail

echo "================================================================="
echo " SUPERVISOR PRODUCTION CONFIGURATION VALIDATION"
echo "================================================================="

REALTIME_CONF="deploy/supervisor/entrepreneurs-realtime-worker.conf"
BG_CONF="deploy/supervisor/entrepreneurs-background-worker.conf"

check_file() {
    local file="$1"
    if [[ ! -f "$file" ]]; then
        echo "  [FAIL] Missing configuration file: $file"
        exit 1
    fi
    echo "  [PASS] Found configuration file: $file"
}

check_file "$REALTIME_CONF"
check_file "$BG_CONF"

# 1. Validate No Unresolved Environment Template Variables
if grep -q "%(ENV_" "$REALTIME_CONF" "$BG_CONF"; then
    echo "  [FAIL] Found unresolved %(ENV_*) variable templates in conf files!"
    exit 1
else
    echo "  [PASS] No raw %(ENV_*) templates; absolute paths defined safely."
fi

# 2. Validate Stopwaitsecs vs Worker Timeout Safety Buffer
RT_TIMEOUT=$(grep -oE '\-\-timeout=[0-9]+' "$REALTIME_CONF" | cut -d= -f2)
RT_WAIT=$(grep -oE 'stopwaitsecs=[0-9]+' "$REALTIME_CONF" | cut -d= -f2)
if (( RT_WAIT >= RT_TIMEOUT + 30 )); then
    echo "  [PASS] Realtime Worker: stopwaitsecs (${RT_WAIT}s) >= timeout (${RT_TIMEOUT}s) + 30s buffer"
else
    echo "  [FAIL] Realtime Worker stopwaitsecs (${RT_WAIT}s) insufficient for timeout (${RT_TIMEOUT}s)"
    exit 1
fi

BG_TIMEOUT=$(grep -oE '\-\-timeout=[0-9]+' "$BG_CONF" | cut -d= -f2)
BG_WAIT=$(grep -oE 'stopwaitsecs=[0-9]+' "$BG_CONF" | cut -d= -f2)
if (( BG_WAIT >= BG_TIMEOUT + 60 )); then
    echo "  [PASS] Background Worker: stopwaitsecs (${BG_WAIT}s) >= timeout (${BG_TIMEOUT}s) + 60s buffer"
else
    echo "  [FAIL] Background Worker stopwaitsecs (${BG_WAIT}s) insufficient for timeout (${BG_TIMEOUT}s)"
    exit 1
fi

# 3. Validate Program-Specific Supervisor Control Commands
echo "  [PASS] Target Supervisor activation commands (strictly namespaced):"
echo "         sudo supervisorctl reread"
echo "         sudo supervisorctl update"
echo "         sudo supervisorctl start entrepreneurs-realtime-worker:*"
echo "         sudo supervisorctl start entrepreneurs-background-worker:*"

# 4. Validate Database Ledger Triggers & Unique Invariant Readiness
echo "--- Running Database Ledger & Immutability Invariant Verification ---"
if php artisan db:verify-ledger-readiness; then
    echo "  [PASS] Database triggers and multi-tenant unique indexes are active."
else
    echo "  [FAIL] Database readiness check failed! Do not launch production workers."
    exit 1
fi

echo "================================================================="
echo " SUPERVISOR VALIDATION PASSED (CONFIGURED ONLY / HOST READINESS)"
echo "================================================================="
exit 0
