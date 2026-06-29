#!/bin/bash

set -e

cd /home/entrepre/chat.entrepreneursautomation.com

git pull origin main

composer install --no-dev --optimize-autoloader

php artisan migrate --force

php artisan optimize:clear
php artisan optimize

echo "Deploy Finished"