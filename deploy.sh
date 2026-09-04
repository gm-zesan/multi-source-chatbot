#!/bin/bash
set -e

APP_DIR="/home/entrepre/chat.entrepreneursautomation.com"

if [ -d "$APP_DIR" ]; then
    cd "$APP_DIR"
fi

echo "========================================="
echo "🚀 Deployment Started: $(date)"
echo "========================================="

# 1. Pull latest code
echo "📦 1/6 Pulling latest changes from Git..."
git pull origin main

# 2. Locate Composer & Install PHP Dependencies
echo "📦 2/6 Installing Composer dependencies..."
if [ -f "/home/entrepre/bin/composer" ]; then
    COMPOSER_BIN="/home/entrepre/bin/composer"
elif command -v composer &> /dev/null; then
    COMPOSER_BIN="composer"
elif [ -f "composer.phar" ]; then
    COMPOSER_BIN="php composer.phar"
else
    COMPOSER_BIN="composer"
fi

$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

# 3. Build Front-end Assets (if Node/NPM available)
echo "🎨 3/6 Building front-end assets..."
if command -v npm &> /dev/null; then
    npm ci --prefer-offline 2>/dev/null || npm install --no-audit --prefer-offline
    npm run build
else
    echo "⚠️ npm not found on PATH, skipping asset compilation."
fi

# 4. Database Migrations
echo "🗄️ 4/6 Running database migrations..."
php artisan migrate --force --no-interaction

# 5. Clear and Cache Laravel Configurations
echo "⚡ 5/6 Caching configuration & views..."
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan event:cache
php artisan route:cache || echo "⚠️ Route caching skipped"

# 6. Restart Queue Workers & Services
echo "🔄 6/6 Restarting Queue Workers..."
php artisan queue:restart || true

if command -v supervisorctl &> /dev/null; then
    if sudo -n true 2>/dev/null; then
        sudo supervisorctl reread || true
        sudo supervisorctl update || true
        sudo supervisorctl restart chatbot-messenger:* || true
        sudo supervisorctl restart chatbot-crm:* || true
        sudo supervisorctl restart chatbot-faq:* || true
    else
        supervisorctl reread 2>/dev/null || true
        supervisorctl update 2>/dev/null || true
        supervisorctl restart chatbot-messenger:* 2>/dev/null || true
        supervisorctl restart chatbot-crm:* 2>/dev/null || true
        supervisorctl restart chatbot-faq:* 2>/dev/null || true
    fi
fi

echo "========================================="
echo "✅ Deployment Finished Successfully: $(date)"
echo "========================================="
