#!/usr/bin/env bash
# Deploy SPeEdtracQR to a server (first-time or update).
#
# Usage:
#   ./scripts/deploy.sh --first-time   # initial production setup
#   ./scripts/deploy.sh                # routine code update
#   ./scripts/deploy.sh --maintenance  # enable maintenance mode during deploy
#
# Run from the repo root on the server, as a user that can write to the app dir.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FIRST_TIME=false
MAINTENANCE=false

for arg in "$@"; do
  case "$arg" in
    --first-time) FIRST_TIME=true ;;
    --maintenance) MAINTENANCE=true ;;
    -h|--help)
      echo "Usage: $0 [--first-time] [--maintenance]"
      exit 0
      ;;
    *)
      echo "Unknown option: $arg" >&2
      exit 1
      ;;
  esac
done

if [[ ! -f artisan ]]; then
  echo "Run this script from the Laravel app root (where artisan lives)." >&2
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "Missing .env — copy .env.example to .env and configure it first." >&2
  echo "See DEPLOYMENT.md §2 Production environment." >&2
  exit 1
fi

echo "==> SPeEdtracQR deploy ($( $FIRST_TIME && echo 'first-time' || echo 'update' ))"

if $MAINTENANCE; then
  php artisan down --retry=60 || true
  trap 'php artisan up 2>/dev/null || true' EXIT
fi

echo "==> PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Frontend assets"
if command -v npm >/dev/null 2>&1; then
  npm ci --ignore-scripts
  npm run build
else
  echo "WARN: npm not found — skip asset build (ensure public/build exists)" >&2
fi

if $FIRST_TIME; then
  if grep -q '^APP_KEY=$' .env 2>/dev/null || grep -q '^APP_KEY=\s*$' .env 2>/dev/null; then
    echo "==> Application key"
    php artisan key:generate --force
  fi

  echo "==> Storage link (public QR codes)"
  php artisan storage:link --force 2>/dev/null || php artisan storage:link
fi

echo "==> Database migrations"
php artisan migrate --force

if $FIRST_TIME; then
  echo "==> Seed roles & admin user (skip if already seeded)"
  if ! grep -q '^ADMIN_PASSWORD=.\+' .env 2>/dev/null; then
    echo "WARN: Set ADMIN_PASSWORD in .env before seeding in production!" >&2
  fi
  php artisan db:seed --class=RolesAndPermissionsSeeder --force
fi

echo "==> Cache config/routes/views"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

if command -v supervisorctl >/dev/null 2>&1; then
  if supervisorctl status speedtraqr-worker >/dev/null 2>&1; then
    echo "==> Restart queue worker"
    sudo supervisorctl restart speedtraqr-worker
  else
    echo "NOTE: Supervisor program 'speedtraqr-worker' not found."
    echo "      Install scripts/supervisor-speedtraqr-worker.conf.example — see DEPLOYMENT.md §4."
  fi
  if supervisorctl status speedtraqr-reverb >/dev/null 2>&1; then
    echo "==> Restart Reverb server (live tracking)"
    sudo supervisorctl restart speedtraqr-reverb
  else
    echo "NOTE: Supervisor program 'speedtraqr-reverb' not found."
    echo "      Install scripts/supervisor-speedtraqr-worker.conf.example — see DEPLOYMENT.md §4."
  fi
else
  echo "NOTE: supervisorctl not found — ensure a queue worker (php artisan queue:work)"
  echo "      and the Reverb server (php artisan reverb:start) are running."
fi

if $MAINTENANCE; then
  php artisan up
  trap - EXIT
fi

echo ""
echo "Deploy finished."
echo "  Verify: php artisan about"
echo "  Smoke test: follow TESTING.md"
echo "  Scheduler: cron must run * * * * * php artisan schedule:run (see DEPLOYMENT.md §4)"
