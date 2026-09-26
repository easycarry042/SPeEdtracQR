#!/usr/bin/env bash
#
# Start the app and put it on the internet, in one command.
#
#   ./start-demo.sh
#
# Starts the web server and a Cloudflare tunnel, then prints the public
# address. Press Ctrl+C to stop both.
#
# The database (MySQL) starts by itself when the computer boots, so your data
# is always there — only these two need starting by hand.
#
set -u

PORT=8123
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLOUDFLARED="$HOME/bin/cloudflared"
LOG_DIR="$APP_DIR/storage/logs"
TUNNEL_LOG="$LOG_DIR/tunnel.log"
SERVE_LOG="$LOG_DIR/serve.log"

cd "$APP_DIR" || exit 1
mkdir -p "$LOG_DIR"

green() { printf '\033[0;32m%s\033[0m\n' "$1"; }
red()   { printf '\033[0;31m%s\033[0m\n' "$1"; }
info()  { printf '  %s\n' "$1"; }

cleanup() {
    echo
    info "Stopping…"
    [ -n "${SERVE_PID:-}" ] && kill "$SERVE_PID" 2>/dev/null
    [ -n "${TUNNEL_PID:-}" ] && kill "$TUNNEL_PID" 2>/dev/null
    green "Stopped. Your data is safe in the database."
    exit 0
}
trap cleanup INT TERM

echo
green "SPeED TraQR — starting"
echo

# 1. Database. It should already be running (it starts with the computer), so
#    this is a check rather than a step.
if systemctl is-active --quiet mysql; then
    info "✓ Database is running"
else
    red   "✗ Database is NOT running."
    info  "  Start it with:  sudo systemctl start mysql"
    exit 1
fi

# 2. Web server. Free the port first so a leftover process from a previous run
#    doesn't silently keep serving old code.
if lsof -ti:"$PORT" >/dev/null 2>&1; then
    info "· Port $PORT was busy — freeing it"
    lsof -ti:"$PORT" | xargs -r kill 2>/dev/null
    sleep 1
fi

php artisan serve --port="$PORT" > "$SERVE_LOG" 2>&1 &
SERVE_PID=$!
sleep 3

if ! curl -s -o /dev/null "http://127.0.0.1:$PORT/"; then
    red  "✗ The app did not start. Last few lines:"
    tail -5 "$SERVE_LOG"
    exit 1
fi
info "✓ App running at http://127.0.0.1:$PORT"

# 3. Public tunnel. Optional — without it the app still works on this computer.
if [ ! -x "$CLOUDFLARED" ]; then
    echo
    info "cloudflared not found at $CLOUDFLARED"
    info "The app works locally at http://127.0.0.1:$PORT"
    info "Press Ctrl+C to stop."
    wait "$SERVE_PID"
    exit 0
fi

: > "$TUNNEL_LOG"
"$CLOUDFLARED" tunnel --url "http://127.0.0.1:$PORT" --no-autoupdate > "$TUNNEL_LOG" 2>&1 &
TUNNEL_PID=$!

info "· Opening the public address…"
PUBLIC_URL=""
for _ in $(seq 1 30); do
    PUBLIC_URL=$(grep -oE "https://[a-z0-9-]+\.trycloudflare\.com" "$TUNNEL_LOG" 2>/dev/null | head -1)
    [ -n "$PUBLIC_URL" ] && break
    sleep 1
done

echo
if [ -n "$PUBLIC_URL" ]; then
    green "Open this on any phone or computer:"
    echo
    printf '    \033[1;36m%s\033[0m\n' "$PUBLIC_URL"
    echo
    # The address is different every run, so anything that bakes it in (a
    # printed QR code, a link in an email) stops working after a restart.
    info "Note: this address changes every time you start it."
    info "Don't print QR codes while using it — those links would stop working."
else
    red  "Could not get a public address. The app still works locally:"
    info "http://127.0.0.1:$PORT"
    info "Tunnel log: $TUNNEL_LOG"
fi

echo
info "Press Ctrl+C to stop everything."
wait
