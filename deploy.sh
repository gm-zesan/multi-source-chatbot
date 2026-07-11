#!/bin/bash
set -e

cd /home/entrepre/chat.entrepreneursautomation.com

echo "🚀 Deploy Started: $(date)"

# 1. Pull latest code
git pull origin main

# 2. Install PHP deps (no dev on prod)
/home/entrepre/bin/composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Clear & rebuild cache
php artisan optimize:clear
php artisan view:cache
php artisan config:cache
php artisan event:cache

# 5. Restart queue workers
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart chatbot-messenger:*
sudo supervisorctl restart chatbot-crm:*
sudo supervisorctl restart chatbot-faq:*

echo "✅ Deploy Finished: $(date)"
