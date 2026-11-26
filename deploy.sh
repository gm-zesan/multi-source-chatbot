#!/bin/bash
set -euo pipefail

# ==============================================================================
# Entrepreneurs Automation — Main Domain Deployment Script (public_html)
#
# Supports two modes automatically:
#   1. Local Mac/PC mode (default when run locally):
#      Builds Vite assets, packages clean release tarball (without overwriting
#      server .env or storage), uploads via SCP/SSH to /home/entrepre/public_html,
#      installs Composer --no-dev dependencies, migrates, and caches Laravel.
#   2. Server-side mode (if run directly on the cPanel server inside public_html):
#      Runs git pull, composer install --no-dev, migrate, and cache warmup.
# ==============================================================================

SSH_USER="${DEPLOY_SSH_USER:-entrepre}"
SSH_HOST="${DEPLOY_SSH_HOST:-entrepreneursautomation.com}"
SSH_PORT="${DEPLOY_SSH_PORT:-22}"
REMOTE_DIR="${DEPLOY_REMOTE_DIR:-/home/entrepre/public_html}"
GIT_BRANCH="${DEPLOY_GIT_BRANCH:-main}"

echo "========================================="
echo "🚀 Entrepreneurs Automation Deploy"
echo "   Started: $(date)"
echo "========================================="

run_remote_laravel_tasks() {
    echo "🗄️  Running Composer, migrations, and cache warmup on server..."
    ssh -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" bash -s <<EOF
set -euo pipefail
cd "$REMOTE_DIR"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ -f "/home/entrepre/composer.phar" ]; then
    COMPOSER_CMD="php /home/entrepre/composer.phar"
elif command -v composer >/dev/null 2>&1; then
    COMPOSER_CMD="composer"
else
    echo "❌ Composer not found on server." >&2
    exit 1
fi

\$COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart || true
EOF
}

if [ -d "$REMOTE_DIR" ] && [ "$(whoami)" = "$SSH_USER" ]; then
    # ── Server-side deployment mode ──
    echo "🖥️  Detected cPanel server environment ($REMOTE_DIR)"
    cd "$REMOTE_DIR"

    echo "📦 1/3 Pulling latest code from origin/${GIT_BRANCH}..."
    git pull origin "$GIT_BRANCH"

    echo "🎨 2/3 Checking frontend assets..."
    for node_dir in /opt/cpanel/ea-nodejs22/bin /opt/cpanel/ea-nodejs20/bin /opt/cpanel/ea-nodejs18/bin /usr/local/bin; do
        if [ -d "$node_dir" ] && [ -x "$node_dir/npm" ]; then
            export PATH="$node_dir:$PATH"
            break
        fi
    done
    if command -v npm >/dev/null 2>&1; then
        npm ci --prefer-offline 2>/dev/null || npm install --no-audit --prefer-offline
        npm run build
    else
        echo "ℹ️  npm not installed on server; keeping pre-built public/build assets."
    fi

    echo "⚙️  3/3 Updating dependencies, migrations, and caches..."
    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    chmod -R 775 storage bootstrap/cache

    if [ -f "/home/entrepre/composer.phar" ]; then
        php /home/entrepre/composer.phar install --no-dev --optimize-autoloader --no-interaction
    else
        composer install --no-dev --optimize-autoloader --no-interaction
    fi

    php artisan migrate --force --no-interaction
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan queue:restart || true
else
    # ── Local-to-Server upload deployment mode ──
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    cd "$SCRIPT_DIR"

    echo "💻 1/4 Building production frontend assets locally..."
    if command -v npm >/dev/null 2>&1; then
        npm run build
    else
        echo "⚠️  npm not found locally; uploading existing public/build."
    fi

    ARCHIVE_PATH="/tmp/ea_deploy_$(date +%Y%m%d_%H%M%S).tar.gz"
    REMOTE_ARCHIVE="~/$(basename "$ARCHIVE_PATH")"

    echo "📦 2/4 Packaging clean release archive..."
    COPYFILE_DISABLE=1 tar -czf "$ARCHIVE_PATH" \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='.env' \
        --exclude='.env.backup' \
        --exclude='.env.production' \
        --exclude='storage/logs/*.log' \
        --exclude='storage/framework/cache/data/*' \
        --exclude='storage/framework/sessions/*' \
        --exclude='storage/framework/views/*' \
        --exclude='tests' \
        --exclude='audit_evidence_bo2' \
        --exclude='scratch' \
        --exclude='.phpunit*' \
        --exclude='.DS_Store' \
        --exclude='*/.DS_Store' \
        --exclude='public/hot' \
        --exclude='public/storage' \
        .

    trap 'rm -f "$ARCHIVE_PATH"' EXIT

    echo "☁️  3/4 Uploading release to ${SSH_USER}@${SSH_HOST}:${REMOTE_DIR}..."
    scp -P "$SSH_PORT" "$ARCHIVE_PATH" "${SSH_USER}@${SSH_HOST}:${REMOTE_ARCHIVE}"

    ssh -p "$SSH_PORT" "${SSH_USER}@${SSH_HOST}" "mkdir -p '${REMOTE_DIR}' && tar -xzf ${REMOTE_ARCHIVE} -C '${REMOTE_DIR}' && rm -f ${REMOTE_ARCHIVE}"

    echo "⚙️  4/4 Finalizing Laravel on remote server..."
    run_remote_laravel_tasks
fi

echo "========================================="
echo "✅ Deployment Complete: https://${SSH_HOST}"
echo "========================================="
