# SPeEdtracQR — Deployment Guide

This app has **no automated CI/CD** yet. Deployment is: provision a Linux server, configure `.env`, run `./scripts/deploy.sh`, and keep three background pieces alive (web, queue worker, scheduler).

---

## What must run in production

| Component | Purpose |
|-----------|---------|
| **Nginx + PHP-FPM 8.3** | Web UI, scanning API, uploads |
| **MySQL 8+** | All app data (you already use MySQL locally) |
| **Queue worker** | `QUEUE_CONNECTION=database` — background jobs |
| **Cron scheduler** | Runs `documents:check-sla` hourly for SLA emails |
| **Reverb server** | `php artisan reverb:start` — WebSocket server for live document tracking (Pillar 1) |

Local dev uses `composer dev` (server + queue + Vite). Production uses **built assets** (`npm run build`), not Vite dev.

---

## Path A — You are here now (local machine)

You already have MySQL and migrated data. Before thinking about a public server:

```bash
# From repo root
composer test                    # 55 tests should pass
php artisan about                # confirms mysql connection
php artisan storage:link         # QR images under public/storage
```

Smoke-test the UI with [TESTING.md](TESTING.md).

To simulate “production mode” locally:

```bash
# In .env temporarily:
# APP_ENV=production
# APP_DEBUG=false

npm run build
php artisan config:cache
php artisan serve   # quick check only — real prod uses Nginx
```

---

## Path B — Deploy to a VPS (recommended for real use)

### Step 1 — Provision the server

- Ubuntu 22.04/24.04 (or similar)
- 1–2 GB RAM minimum for a small office
- Open ports **80** and **443**

Install stack:

```bash
sudo apt update
sudo apt install -y nginx mysql-server php8.3-fpm php8.3-cli \
  php8.3-mysql php8.3-gd php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath supervisor git unzip curl

# Node 20+ for building assets (can remove after build if you deploy pre-built assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Secure MySQL:

```bash
sudo mysql_secure_installation
```

### Step 2 — Create database on the server

Same as local — run from your **local** machine if MySQL is only on the server, SSH in first:

```bash
ssh user@YOUR_SERVER
cd /var/www
sudo git clone <your-repo-url> speedtraqr
sudo chown -R $USER:www-data speedtraqr
cd speedtraqr
```

Create DB (on server):

```bash
./scripts/setup-mysql.sh
# Or manually: CREATE DATABASE speedtraqr; CREATE USER ...
```

### Step 3 — Production `.env`

```bash
cp .env.example .env
nano .env
```

**Required production values:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=speedtraqr
DB_USERNAME=speedtraqr
DB_PASSWORD=<strong-password>

ADMIN_PASSWORD=<strong-admin-password>

MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@your-domain.example.com
MAIL_FROM_NAME="SPeED TraQR"

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
FILESYSTEM_DISK=local

# Live tracking (Laravel Reverb). Keep the REVERB_APP_* secrets that
# `install:broadcasting` generated — do NOT regenerate them on the server.
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<generated>
REVERB_APP_KEY=<generated>
REVERB_APP_SECRET=<generated>
# Server binds locally; nginx terminates TLS and proxies /app to it.
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
# What the browser connects to (your public domain over wss:443).
REVERB_HOST=your-domain.example.com
REVERB_PORT=443
REVERB_SCHEME=https
# Vite bakes these into the built JS — must be set BEFORE `npm run build`.
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Generate app key on first deploy (the script does this if missing):

```bash
php artisan key:generate
```

### Step 4 — First deploy

```bash
chmod +x scripts/deploy.sh
./scripts/deploy.sh --first-time
```

This runs: `composer install --no-dev`, `npm run build`, migrations, roles seeder, config/route/view cache.

**Import existing local data** (optional — if moving from your laptop):

```bash
# On local machine: copy SQLite or mysqldump
mysqldump -u root -p speedtraqr > speedtraqr-backup.sql
scp speedtraqr-backup.sql user@YOUR_SERVER:/var/www/speedtraqr/
# Also copy uploaded files:
tar czf storage-backup.tar.gz storage/app/document-attachments storage/app/public/qrcodes
scp storage-backup.tar.gz user@YOUR_SERVER:/var/www/speedtraqr/

# On server:
mysql -u speedtraqr -p speedtraqr < speedtraqr-backup.sql
tar xzf storage-backup.tar.gz
sudo chown -R www-data:www-data storage bootstrap/cache
```

Or fresh start on server (empty DB + seed only) — skip import.

### Step 5 — Nginx

```bash
sudo cp scripts/nginx-speedtraqr.conf.example /etc/nginx/sites-available/speedtraqr
sudo nano /etc/nginx/sites-available/speedtraqr   # set YOUR_DOMAIN and paths
sudo ln -s /etc/nginx/sites-available/speedtraqr /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

The example config includes a `location /app { … }` block that proxies the
WebSocket connection to the local Reverb server (`127.0.0.1:8080`) so live
tracking works over `wss://your-domain/app`. Keep it after Certbot rewrites
the file for TLS.

HTTPS with Certbot:

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.example.com
```

Fix permissions:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

### Step 6 — Queue worker + Reverb server (Supervisor)

The example file defines **two** programs: `speedtraqr-worker` (queue) and
`speedtraqr-reverb` (live-tracking WebSocket server).

```bash
sudo cp scripts/supervisor-speedtraqr-worker.conf.example /etc/supervisor/conf.d/speedtraqr.conf
sudo nano /etc/supervisor/conf.d/speedtraqr.conf   # fix paths
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start speedtraqr-worker speedtraqr-reverb
sudo supervisorctl status
```

`deploy.sh` restarts both programs automatically on routine updates.

### Step 7 — Scheduler (cron)

SLA emails **will not send** without this.

```bash
sudo crontab -u www-data -e
```

Add:

```cron
* * * * * cd /var/www/speedtraqr && php artisan schedule:run >> /dev/null 2>&1
```

Verify scheduled tasks:

```bash
php artisan schedule:list
# Should show documents:check-sla … hourly
```

### Step 7b — AI assistant model (Ollama, optional — Pillar 3)

The document assistant works without this (it falls back to a deterministic
rule-based answer). To enable the real self-hosted LLM:

```bash
# Install Ollama (https://ollama.com/download) and pull the model:
ollama pull llama3.2          # ~2 GB
ollama list                   # confirm llama3.2 is present
```

Then set in `.env` (see `.env.example`): `AI_PROVIDER=ollama`,
`OLLAMA_MODEL=llama3.2`, `OLLAMA_TIMEOUT=90`, `OLLAMA_KEEP_ALIVE=30m`.

> **Latency note:** CPU-only inference is slow — a cold model load is ~60–80s,
> warmed calls answer in a few seconds. `OLLAMA_KEEP_ALIVE` keeps the model
> resident so citizens don't hit cold starts. If the assistant runs behind
> nginx, raise `fastcgi_read_timeout` on the `/track/.../ask` path (or the
> whole site) above `OLLAMA_TIMEOUT`, or any slow generation 504s before the
> rule-based fallback can return. A GPU host removes the problem.

### Step 8 — Go-live checklist

Run through [TESTING.md](TESTING.md) on the **staging/production URL**:

- [ ] Create submission with multiple photos
- [ ] QR file exists under `storage/app/public/qrcodes/`
- [ ] Scan IN / OUT
- [ ] Public `/track/{number}` works (no login)
- [ ] Attachments require staff login
- [ ] Admin login with seeded password (then change it)
- [ ] `APP_DEBUG=false` — errors must not show stack traces
- [ ] Live tracking: open `/track/{number}`, scan the document elsewhere, page updates without refresh (● Live indicator on)
- [ ] AI assistant: ask a question on `/track/{number}`; if Ollama is enabled the answer is model-generated, otherwise the rule-based fallback responds (both are fine)

---

## Routine updates (after git pull)

```bash
cd /var/www/speedtraqr
git pull
./scripts/deploy.sh --maintenance   # optional: puts up maintenance page briefly
```

Or without maintenance:

```bash
./scripts/deploy.sh
```

---

## Backups (plan these — not automated in repo)

Daily at minimum:

```bash
# Database
mysqldump -u speedtraqr -p speedtraqr | gzip > /backups/speedtraqr-$(date +%F).sql.gz

# Uploaded files + QR codes (not in DB blobs)
tar czf /backups/speedtraqr-storage-$(date +%F).tar.gz \
  storage/app/document-attachments storage/app/public/qrcodes
```

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| 500 after deploy | `storage/logs/laravel.log`; check `storage` permissions |
| QR images 404 | `php artisan storage:link` |
| SLA emails never arrive | Cron + `php artisan schedule:list`; check `MAIL_*` in `.env` |
| Jobs stuck | `sudo supervisorctl status speedtraqr-worker` |
| Live tracking not updating | `sudo supervisorctl status speedtraqr-reverb`; check nginx `/app` proxy + `VITE_REVERB_*` were set before `npm run build` |
| `Mix/Vite manifest not found` | Run `npm run build` on server |
| Database locked | You are on SQLite — use MySQL (see `scripts/setup-mysql.sh`) |

---

## Quick reference

```bash
# First time on server
./scripts/deploy.sh --first-time

# Every update
git pull && ./scripts/deploy.sh

# Clear caches if .env changed
php artisan config:clear && php artisan config:cache

# Health
php artisan about
composer test
```
