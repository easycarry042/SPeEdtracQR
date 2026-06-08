#!/usr/bin/env bash
# Create a MySQL database + user for SPeEdtracQR and update .env
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DB_NAME="${DB_NAME:-speedtraqr}"
DB_USER="${DB_USER:-speedtraqr}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

if [[ -z "${MYSQL_ROOT_PASSWORD:-}" ]]; then
  read -rsp "MySQL root password: " MYSQL_ROOT_PASSWORD
  echo
fi

DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)}"

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -h "$DB_HOST" -P "$DB_PORT" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# Update .env (backup first)
cp -n .env .env.bak.sqlite 2>/dev/null || cp .env .env.bak.sqlite

export ROOT DB_NAME DB_USER DB_PASS DB_HOST DB_PORT
php <<'PHP'
<?php
$root = getenv('ROOT') ?: getcwd();
$envFile = $root . '/.env';
$lines = file($envFile, FILE_IGNORE_NEW_LINES);
$updates = [
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('DB_PORT') ?: '3306',
    'DB_DATABASE' => getenv('DB_NAME') ?: 'speedtraqr',
    'DB_USERNAME' => getenv('DB_USER') ?: 'speedtraqr',
    'DB_PASSWORD' => getenv('DB_PASS') ?: '',
    'SQLITE_LEGACY_PATH' => $root . '/database/database.sqlite',
];
$seen = [];
$out = [];
foreach ($lines as $line) {
    $key = strtok($line, '=');
    if ($key !== false && isset($updates[$key])) {
        $out[] = $key . '=' . $updates[$key];
        $seen[$key] = true;
        continue;
    }
    if (str_starts_with($line, 'DB_DATABASE=') && ! isset($seen['DB_DATABASE'])) {
        continue; // drop old sqlite path line
    }
    $out[] = $line;
}
foreach ($updates as $key => $value) {
    if (! isset($seen[$key])) {
        $out[] = $key . '=' . $value;
    }
}
file_put_contents($envFile, implode(PHP_EOL, $out) . PHP_EOL);
PHP

echo ""
echo "MySQL database ready."
echo "  Database : ${DB_NAME}"
echo "  User     : ${DB_USER}"
echo "  Password : ${DB_PASS}"
echo "  .env     : updated (backup at .env.bak.sqlite)"
echo ""
echo "Next steps:"
echo "  php artisan config:clear"
echo "  php artisan db:move-to-mysql --fresh"
echo ""
