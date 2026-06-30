#!/bin/bash

set -e

cd /home/entrepre/chat.entrepreneursautomation.com

BEFORE=$(git rev-parse HEAD)

git pull origin main

# /home/entrepre/bin/composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan optimize:clear

echo "✅ Deploy Finished"
