# Production Operations & Disaster Recovery Runbook
**Platform:** Enterprise AI Business Operating & Customer Support Platform (Multi-Tenant SaaS)  
**Document Version:** 1.0.0-RELEASE  
**Audience:** Site Reliability Engineers, DevOps, Lead Software Architects, System Administrators  

---

## 1. Automated Database Backup Architecture

The platform provides native, zero-downtime, point-in-time database backup with cryptographic SHA-256 integrity verification.

### 1.1 Artisan Backup Command
```bash
# Create point-in-time backup with default 30-day retention
php artisan db:backup

# Create gzip-compressed backup with custom filename and 60-day retention
php artisan db:backup --compress --filename=prod_snapshot_$(date +%F) --retention=60
```

### 1.2 Storage Location & Artifact Sidecars
- **Backup Directory:** `storage/app/backups/`
- **Archive Format:** `.sql`, `.sql.gz` (MySQL) or `.sqlite` (SQLite)
- **Metadata Sidecar:** `<archive_filename>.meta.json`
  - Records timestamp, connection name, file size in bytes, duration in ms, and the authoritative SHA-256 hash.

### 1.3 Production Crontab Schedule
Add the following entry to `/etc/cron.d/chatbot-backup` or the server crontab:
```cron
# Run daily database backup at 02:00 AM UTC with 30-day automated retention purge
0 2 * * * cd /var/www/multi-source-chatbot && php artisan db:backup --compress --retention=30 >> /var/log/chatbot-db-backup.log 2>&1
```

---

## 2. Disaster Recovery & Restoration Runbook

### 2.1 Pre-Restoration Integrity Verification
Before executing any restoration, the system automatically checks the cryptographic SHA-256 hash against the `.meta.json` sidecar. If tampering or corruption is detected, restoration fails closed immediately.

### 2.2 Interactive Restoration
```bash
# Restore from archive (will prompt for interactive confirmation)
php artisan db:restore prod_snapshot_2026-11-15.sql.gz
```

### 2.3 Automated / Non-Interactive Disaster Restoration
```bash
# Force restoration in CI/CD or disaster recovery pipelines
php artisan db:restore /var/www/multi-source-chatbot/storage/app/backups/prod_snapshot_2026-11-15.sql.gz --force
```

---

## 3. High Availability Health Monitoring & Probes

The application provides an unauthenticated `/health` endpoint compliant with Kubernetes liveness/readiness probes, AWS ALB target groups, and Cloudflare uptime monitors.

### 3.1 Endpoint Specification
- **URL:** `GET /health`
- **Success Status:** `HTTP 200 OK` (All subsystems healthy)
- **Failure Status:** `HTTP 503 Service Unavailable` (One or more subsystems degraded)
- **Payload Schema:**
```json
{
  "status": "healthy",
  "timestamp": "2026-11-15T04:20:00+00:00",
  "app": {
    "env": "production",
    "debug": false,
    "version": "1.0.0"
  },
  "checks": {
    "database": { "healthy": true, "latency_ms": 1.2 },
    "queue": { "healthy": true, "pending_jobs": 0, "failed_jobs": 0 },
    "retrieval": { "healthy": true, "latency_ms": 4.1, "error": null },
    "analytics": { "healthy": true, "latency_ms": 3.8, "error": null },
    "memory": { "healthy": true, "latency_ms": 5.2, "error": null },
    "cache": { "healthy": true }
  }
}
```

---

## 4. Queue Worker & Background Pipeline Recovery

The platform processes webhooks, CRM sync, knowledge indexing, and spreadsheet ingestion asynchronously via Laravel Queues and Redis/Database drivers.

### 4.1 Supervisor Configuration (`/etc/supervisor/conf.d/chatbot-worker.conf`)
```ini
[program:chatbot-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/multi-source-chatbot/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --queue=messenger,spreadsheet,default
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/chatbot-worker.log
```

### 4.2 Handling Failed Jobs
```bash
# Check failed jobs count and stack traces
php artisan queue:failed

# Retry specific failed job or all failed jobs
php artisan queue:retry all

# Flush dead-letter jobs after post-mortem investigation
php artisan queue:flush
```

---

## 5. Security Incident Response & Tenant Isolation Auditing

If a security incident or tenant data leak suspicion arises:
1. **Immediately Revoke Governance Sessions:**
   ```bash
   # Revokes any active superadmin impersonation session
   php artisan tinker --execute="\App\Models\SuperAdminGovernanceSession::whereNull('revoked_at')->update(['revoked_at' => now()]);"
   ```
2. **Review Adversarial & Injection Logs:**
   - Filter logs for `[CustomerSupportService] Inbound customer workforce/financial inquiry blocked by P0 Security Barrier`.
3. **Verify Row-Level Tenant Isolation:**
   - Execute test suite security suite:
     ```bash
     php vendor/bin/phpunit tests/Feature/UserControllerTenantIsolationTest.php
     php vendor/bin/phpunit tests/Feature/Security/AdversarialJailbreakAndConcurrencyTest.php
     ```
