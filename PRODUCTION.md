# Production Operations Guide

## 1. Queue Workers (Supervisor)

Three dedicated queue workers must be running at all times.

### Supervisor Config Files

Located in `supervisor/`:

- `chatbot-messenger.conf` — 2 processes, `messenger` queue
- `chatbot-crm.conf` — 1 process, `crm` queue
- `chatbot-faq.conf` — 2 processes, `faq` queue

### Deploy to Supervisor

```bash
sudo cp supervisor/*.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### Check Worker Status

```bash
sudo supervisorctl status
```

### Restart Workers After Deploy (automated via deploy.sh)

```bash
sudo supervisorctl restart chatbot-messenger:*
sudo supervisorctl restart chatbot-crm:*
sudo supervisorctl restart chatbot-faq:*
```

### Logs

```
storage/logs/supervisor-messenger.log
storage/logs/supervisor-crm.log
storage/logs/supervisor-faq.log
```

---

## 2. Health Check Endpoint

```
GET /health
```

Returns JSON with status of all critical services:

```json
{
    "status": "healthy",
    "checks": {
        "database": { "healthy": true, "latency_ms": 1.23 },
        "queue": { "healthy": true, "pending_jobs": 0, "failed_jobs": 0 },
        "embedding": { "healthy": true, "model": "...", "latency_ms": 5.67 },
        "cache": { "healthy": true }
    }
}
```

Use with uptime monitors (Pingdom, UptimeRobot, Grafana) pointed at `https://your-domain.com/health`.

Returns `503 Service Unavailable` if any check fails.

---

## 3. Queue Failure Alerting

### Failed Jobs Table

All failed queue jobs are stored in the `failed_jobs` table.

### Monitor

```bash
# Check for recent failures
php artisan queue:failed

# Retry all failed
php artisan queue:retry all

# Clear all failed
php artisan queue:flush
```

### Slack Alerting (Critical Errors)

For real-time Slack alerts on critical failures:

```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

This sends `critical`-level log messages (including queue failures caught by Sentry) to the configured Slack channel.

---

## 4. SSL/TLS (Server-Level)

The webhook endpoint (`POST /webhook`) **must** be served over HTTPS. Facebook/Meta requires a valid TLS certificate for webhook verification.

### Requirements

| Requirement          | Detail                                      |
| -------------------- | ------------------------------------------- |
| TLS 1.2+             | Required                                    |
| Valid certificate    | Let's Encrypt, Cloudflare, or commercial CA |
| No self-signed certs | Meta will reject the webhook                |

### Recommended Setup

- **Option A**: Cloudflare proxied DNS (automatic HTTPS + DDoS protection)
- **Option B**: Nginx + Certbot (Let's Encrypt)
- **Option C**: Load balancer (AWS ALB, Google LB) terminating TLS

### Nginx + Certbot Example

```nginx
server {
    listen 443 ssl;
    server_name chat.yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/chat.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/chat.yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 5. Queue Metrics (Grafana / Prometheus)

To monitor queue depth and latency, query the `jobs` and `failed_jobs` tables:

```sql
-- Pending jobs by queue
SELECT queue, COUNT(*) as pending FROM jobs GROUP BY queue;

-- Failed jobs (last 24h)
SELECT COUNT(*) FROM failed_jobs WHERE failed_at > NOW() - INTERVAL 24 HOUR;
```

---

## 6. Daily Operations

| Task                | Command                                         |
| ------------------- | ----------------------------------------------- |
| Deploy              | `bash deploy.sh`                                |
| Check workers       | `sudo supervisorctl status`                     |
| View messenger logs | `tail -f storage/logs/supervisor-messenger.log` |
| View CRM logs       | `tail -f storage/logs/supervisor-crm.log`       |
| View FAQ logs       | `tail -f storage/logs/supervisor-faq.log`       |
| Check failed jobs   | `php artisan queue:failed`                      |
| Health check        | `curl https://chat.yourdomain.com/health`       |
| View Sentry         | https://sentry.io                               |
