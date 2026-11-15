#!/bin/bash
set -e

APP_DIR="${1:-$(pwd)}"
CRON_USER="${2:-$(whoami)}"

echo "========================================="
echo "⏰ Setting Up Production Crontab for: $CRON_USER"
echo "📂 Target Directory: $APP_DIR"
echo "========================================="

CRON_JOB_SCHEDULE="* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1"
CRON_JOB_BACKUP="0 2 * * * cd $APP_DIR && php artisan db:backup --compress --retention=30 >> /var/log/chatbot-db-backup.log 2>&1"

# Check if jobs already exist in crontab
CURRENT_CRON=$(crontab -l 2>/dev/null || true)

NEW_CRON="$CURRENT_CRON"

if ! echo "$CURRENT_CRON" | grep -Fq "schedule:run"; then
    NEW_CRON=$(echo -e "${NEW_CRON}\n# Laravel Scheduled Task Runner\n${CRON_JOB_SCHEDULE}")
    echo "✅ Added Laravel schedule:run cron."
else
    echo "ℹ️ Laravel schedule:run already configured in crontab."
fi

if ! echo "$CURRENT_CRON" | grep -Fq "db:backup"; then
    NEW_CRON=$(echo -e "${NEW_CRON}\n# Automated Point-in-Time Database Backup with 30-Day Retention\n${CRON_JOB_BACKUP}")
    echo "✅ Added automated db:backup cron (daily at 02:00 AM)."
else
    echo "ℹ️ Automated db:backup already configured in crontab."
fi

echo "$NEW_CRON" | crontab -

echo "========================================="
echo "🎉 Crontab configuration complete!"
echo "========================================="
crontab -l | grep -E "schedule:run|db:backup"
