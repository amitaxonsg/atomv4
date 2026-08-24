#!/usr/bin/env bash
set -Eeuo pipefail

SOURCE="${SOURCE:-/srv/head-heart.atomglobal.com/staging-source}"
APP="${APP:-/var/www/head-heart-staging.atomglobal.com}"
STORAGE="${STORAGE:-/var/lib/head-heart-alignment-staging}"
ENV_DIR="${ENV_DIR:-/etc/head-heart-alignment}"
ENV_FILE="${ENV_FILE:-$ENV_DIR/staging.env}"
LIVE_APP="${LIVE_APP:-/var/www/head-heart.atomglobal.com}"
NGINX_CONF="${NGINX_CONF:-/etc/nginx/conf.d/head-heart-stage4-local.conf}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
PHP_SOCKET="${PHP_SOCKET:-/run/php/php8.3-fpm.sock}"
EXPECTED_COMMIT="${EXPECTED_COMMIT:-}"
PORT="${PORT:-18088}"
STAGE_HOST="${STAGE_HOST:-head-heart-staging.local}"

STAMP="$(date +%Y%m%d-%H%M%S)"
AUDIT="/root/head-heart-stage4-$STAMP"
TEMP_RELEASE="$APP/releases/.stage4-$STAMP.tmp"
NEW_RELEASE=""

mkdir -p "$AUDIT"
exec > >(tee -a "$AUDIT/stage4.log") 2>&1

LIVE_RELEASE_BEFORE="$(readlink -f "$LIVE_APP/current")"
LIVE_COMMIT_BEFORE="$(cat "$LIVE_APP/v2-deployed-commit.txt")"
PREVIOUS_STAGE_RELEASE="$(readlink -f "$APP/current" 2>/dev/null || true)"

if [[ -f "$NGINX_CONF" ]]; then
  cp -a "$NGINX_CONF" "$AUDIT/nginx.before"
  HAD_NGINX=1
else
  HAD_NGINX=0
fi

rollback() {
  local code="${1:-1}"
  local line="${2:-unknown}"
  local command="${3:-unknown}"
  trap - ERR

  echo
  echo "=================================================="
  echo " STAGE 4 FAILED — RESTORING STAGING"
  echo "=================================================="
  echo "Failed line: $line"
  echo "Failed command: $command"

  rm -rf "$TEMP_RELEASE"
  [[ -n "$NEW_RELEASE" ]] && rm -rf "$NEW_RELEASE"

  if [[ -n "$PREVIOUS_STAGE_RELEASE" && -d "$PREVIOUS_STAGE_RELEASE" ]]; then
    ln -sfn "$PREVIOUS_STAGE_RELEASE" "$APP/current.rollback"
    mv -Tf "$APP/current.rollback" "$APP/current"
  else
    rm -f "$APP/current"
  fi

  if [[ "$HAD_NGINX" -eq 1 ]]; then
    cp -a "$AUDIT/nginx.before" "$NGINX_CONF"
  else
    rm -f "$NGINX_CONF"
  fi

  nginx -t >/dev/null 2>&1 && systemctl reload nginx || true
  echo "Live production was not intentionally changed."
  echo "Audit directory: $AUDIT"
  exit "$code"
}
trap 'rollback "$?" "$LINENO" "$BASH_COMMAND"' ERR

log() { printf '\n===== %s =====\n' "$*"; }

printf '%s\n' \
  "==================================================" \
  " HEAD–HEART STAGE 4 — LOCAL PHP/MARIADB STAGING" \
  "=================================================="

log "1. KEEP AUTOMATIC DEPLOYMENT OFF"
systemctl disable --now head-heart-v2-sync.timer 2>/dev/null || true
systemctl is-enabled head-heart-v2-sync.timer 2>/dev/null || true
systemctl is-active head-heart-v2-sync.timer 2>/dev/null || true

log "2. VERIFY SOURCE"
[[ -d "$SOURCE/.git" ]] || { echo "ERROR: Staging Git source is missing."; exit 1; }
[[ -z "$(git -C "$SOURCE" status --porcelain)" ]] || {
  echo "ERROR: Staging source is not clean:"
  git -C "$SOURCE" status --short
  exit 1
}

COMMIT="$(git -C "$SOURCE" rev-parse HEAD)"
echo "Current staging commit: $COMMIT"
if [[ -n "$EXPECTED_COMMIT" && "$COMMIT" != "$EXPECTED_COMMIT" ]]; then
  echo "ERROR: Unexpected staging commit."
  echo "Expected: $EXPECTED_COMMIT"
  echo "Current:  $COMMIT"
  exit 1
fi

log "3. VERIFY PROTECTED ENVIRONMENT"
[[ -s "$ENV_FILE" ]] || { echo "ERROR: Missing $ENV_FILE"; exit 1; }
chown root:www-data "$ENV_DIR" "$ENV_FILE"
chmod 0750 "$ENV_DIR"
chmod 0640 "$ENV_FILE"
stat -c '%A %U:%G %n' "$ENV_DIR" "$ENV_FILE"

set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

[[ "${DB_DATABASE:-}" == "head_heart_staging" ]] || { echo "ERROR: Wrong database in staging.env."; exit 1; }
export PATH="/opt/node-v22/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
node -e 'const major=Number(process.versions.node.split(".")[0]); if(major<22) process.exit(1); console.log(`Node ${process.version}`);'
php -v | head -1
composer --version
systemctl is-active --quiet nginx
systemctl is-active --quiet "$PHP_FPM_SERVICE"
[[ -S "$PHP_SOCKET" ]] || { echo "ERROR: PHP-FPM socket missing: $PHP_SOCKET"; exit 1; }

log "4. VERIFY DATABASE"
MYSQL_PWD="$DB_PASSWORD" mysql \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --database="$DB_DATABASE" \
  --execute='SELECT DATABASE() database_name; SELECT COUNT(*) migrations FROM migrations;'

log "5. INSTALL, MIGRATE AND TEST BACKEND"
ln -sfn "$ENV_FILE" "$SOURCE/backend/.env"
(
  cd "$SOURCE/backend"
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
  php bin/migrate.php
  php bin/seed.php
  composer lint
  composer test
  test -s vendor/autoload.php
)

log "6. BUILD PRODUCTION-MODE FRONTEND"
(
  cd "$SOURCE"
  export VITE_API_MODE=production
  export VITE_API_BASE_URL=/api
  export VITE_ENABLE_SW=false
  npm ci --no-audit --no-fund
  npm test
  npm run build
  test -s dist/index.html
)

log "7. CREATE ISOLATED RELEASE"
RELEASE_NAME="$STAMP-${COMMIT:0:12}"
NEW_RELEASE="$APP/releases/$RELEASE_NAME"
install -d -m 0755 "$APP/releases"
rm -rf "$TEMP_RELEASE"
install -d -m 0755 "$TEMP_RELEASE/frontend" "$TEMP_RELEASE/backend"
rsync -a "$SOURCE/dist/" "$TEMP_RELEASE/frontend/"
rsync -a --exclude='.env' "$SOURCE/backend/" "$TEMP_RELEASE/backend/"
ln -sfn "$ENV_FILE" "$TEMP_RELEASE/backend/.env"

test -s "$TEMP_RELEASE/frontend/index.html"
test -s "$TEMP_RELEASE/backend/public/index.php"
test -s "$TEMP_RELEASE/backend/bin/create-admin.php"
test -s "$TEMP_RELEASE/backend/vendor/autoload.php"

find "$TEMP_RELEASE" -type d -exec chmod 0755 {} \;
find "$TEMP_RELEASE" -type f -exec chmod 0644 {} \;
find "$TEMP_RELEASE/backend/bin" -type f -exec chmod 0755 {} \;

mv "$TEMP_RELEASE" "$NEW_RELEASE"
ln -sfn "$NEW_RELEASE" "$APP/current.new"
mv -Tf "$APP/current.new" "$APP/current"
printf '%s\n' "$COMMIT" > "$APP/deployed-commit.txt"

echo "Release: $NEW_RELEASE"
REAL_INDEX="$(readlink -f "$APP/current/backend/public/index.php")"
[[ -f "$REAL_INDEX" ]] || { echo "ERROR: Backend front controller missing: $REAL_INDEX"; exit 1; }
runuser -u www-data -- test -r "$REAL_INDEX" || { echo "ERROR: www-data cannot read $REAL_INDEX"; exit 1; }

log "8. VERIFY STAGING STORAGE"
install -d -m 0750 "$STORAGE" "$STORAGE/media" "$STORAGE/reports" "$STORAGE/tmp"
chown -R www-data:www-data "$STORAGE"

log "9. VERIFY DEDICATED LOOPBACK PORT"
ss -ltnp > "$AUDIT/listeners.before.txt"
nginx -T > "$AUDIT/nginx.loaded.before.txt" 2>&1

if awk '{print $4}' "$AUDIT/listeners.before.txt" | grep -Fqx "127.0.0.1:${PORT}"; then
  echo "ERROR: Loopback staging port $PORT is already in use."
  grep -E ":${PORT}\\b" "$AUDIT/listeners.before.txt" || true
  exit 1
fi

if grep -Eq "listen[[:space:]]+127\\.0\\.0\\.1:${PORT}([[:space:];]|$)" "$AUDIT/nginx.loaded.before.txt"; then
  echo "ERROR: An Nginx configuration already claims port $PORT."
  grep -nE "listen[[:space:]]+127\\.0\\.0\\.1:${PORT}|server_name" "$AUDIT/nginx.loaded.before.txt" | tail -n 30 || true
  exit 1
fi

echo "Port $PORT is free and not claimed by another Nginx configuration."

log "10. CONFIGURE LOOPBACK-ONLY NGINX"
cat > "$NGINX_CONF" <<NGINX
server {
    listen 127.0.0.1:${PORT};
    server_name ${STAGE_HOST};

    root ${NEW_RELEASE}/frontend;
    index index.html;

    access_log /var/log/nginx/head-heart-staging-access.log;
    error_log /var/log/nginx/head-heart-staging-error.log;
    client_max_body_size 12m;

    add_header X-Head-Heart-Staging "1" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location = /__head-heart-stage4-marker {
        default_type text/plain;
        return 200 "head-heart-stage4\n";
    }

    location ^~ /api/ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME ${NEW_RELEASE}/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT ${NEW_RELEASE}/backend/public;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param DOCUMENT_URI /index.php;
        fastcgi_param REQUEST_URI \$request_uri;
        fastcgi_param PATH_INFO "";
        fastcgi_param HTTPS off;
        fastcgi_param HTTP_AUTHORIZATION \$http_authorization;
        fastcgi_pass unix:${PHP_SOCKET};
        fastcgi_read_timeout 60s;
    }

    location ^~ /media-uploads/ {
        alias ${STORAGE}/media/;
        access_log off;
        autoindex off;
        add_header X-Head-Heart-Staging "1" always;
        add_header X-Content-Type-Options nosniff always;
        add_header Cache-Control "no-store";
    }

    location = /index.html {
        add_header X-Head-Heart-Staging "1" always;
        add_header Cache-Control "no-store, no-cache, must-revalidate";
        try_files \$uri =404;
    }

    location = /sw.js {
        add_header X-Head-Heart-Staging "1" always;
        add_header Cache-Control "no-store, no-cache, must-revalidate";
        try_files \$uri =404;
    }

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location ~ /\\. { deny all; }
    location ~* \\.(?:env|ini|log|sql|bak|sh)$ { deny all; }
}
NGINX

if ! nginx -t; then
  echo "ERROR: Nginx rejected the Head–Heart staging configuration."
  exit 1
fi

if ! systemctl reload nginx; then
  echo "ERROR: Nginx reload failed."
  systemctl status nginx --no-pager || true
  journalctl -u nginx --since "10 minutes ago" --no-pager || true
  exit 1
fi

sleep 1
ss -ltnp > "$AUDIT/listeners.after.txt"
if ! awk '{print $4}' "$AUDIT/listeners.after.txt" | grep -Fqx "127.0.0.1:${PORT}"; then
  echo "ERROR: Nginx did not start the loopback listener on port $PORT."
  cat "$AUDIT/listeners.after.txt"
  nginx -T 2>&1 | grep -nE "${PORT}|${STAGE_HOST}" || true
  exit 1
fi

grep -E ":${PORT}\\b" "$AUDIT/listeners.after.txt" || true
if awk '{print $4}' "$AUDIT/listeners.after.txt" | grep -Eq "^(0\\.0\\.0\\.0|\\[::\\]):${PORT}$"; then
  echo "ERROR: Staging port is publicly exposed."
  exit 1
fi

log "11. VERIFY CORRECT STAGING VHOST"
MARKER_BODY="$AUDIT/marker.txt"
MARKER_HEADERS="$AUDIT/marker.headers"
MARKER_CODE="$(curl --noproxy '*' --silent --show-error --max-time 20 \
  --header "Host: ${STAGE_HOST}" \
  --dump-header "$MARKER_HEADERS" \
  --output "$MARKER_BODY" \
  --write-out '%{http_code}' \
  "http://127.0.0.1:${PORT}/__head-heart-stage4-marker")"

echo "Marker HTTP: $MARKER_CODE"
cat "$MARKER_BODY" || true

if [[ "$MARKER_CODE" != "200" ]] || ! grep -qx 'head-heart-stage4' "$MARKER_BODY" || ! grep -qi '^X-Head-Heart-Staging: 1' "$MARKER_HEADERS"; then
  echo "ERROR: Request did not reach the Head–Heart staging virtual host."
  cat "$MARKER_HEADERS" || true
  nginx -T 2>&1 | grep -nE "${PORT}|${STAGE_HOST}|head-heart-stage4-marker" || true
  exit 1
fi

log "12. VERIFY PHP/MARIADB HEALTH"
HEALTH_BODY="$AUDIT/health.json"
HEALTH_HEADERS="$AUDIT/health.headers"
HEALTH_CODE="$(curl --noproxy '*' --silent --show-error --max-time 20 \
  --header "Host: ${STAGE_HOST}" \
  --dump-header "$HEALTH_HEADERS" \
  --output "$HEALTH_BODY" \
  --write-out '%{http_code}' \
  "http://127.0.0.1:${PORT}/api/health")"

echo "Health HTTP: $HEALTH_CODE"
cat "$HEALTH_BODY" || true
echo

if [[ "$HEALTH_CODE" != "200" ]] || ! grep -q '"status":"ok"' "$HEALTH_BODY" || ! grep -qi '^X-Head-Heart-Staging: 1' "$HEALTH_HEADERS"; then
  echo "ERROR: Staging health verification failed."
  cat "$HEALTH_HEADERS" || true
  tail -n 40 /var/log/nginx/head-heart-staging-access.log 2>/dev/null || true
  tail -n 80 /var/log/nginx/head-heart-staging-error.log 2>/dev/null || true
  journalctl -u "$PHP_FPM_SERVICE" --since "10 minutes ago" --no-pager 2>/dev/null || true
  exit 1
fi

log "13. VERIFY ADMIN SESSION AND FRONTEND"
SESSION_CODE="$(curl --noproxy '*' --silent \
  --header "Host: ${STAGE_HOST}" \
  --output "$AUDIT/admin-session.json" \
  --write-out '%{http_code}' \
  "http://127.0.0.1:${PORT}/api/admin/session")"

echo "Admin session HTTP: $SESSION_CODE"
cat "$AUDIT/admin-session.json" || true
echo
[[ "$SESSION_CODE" == "401" ]] || { echo "ERROR: Expected HTTP 401 before login."; exit 1; }

curl --noproxy '*' --fail --silent --show-error --header "Host: ${STAGE_HOST}" "http://127.0.0.1:${PORT}/" >/dev/null
curl --noproxy '*' --fail --silent --show-error --header "Host: ${STAGE_HOST}" "http://127.0.0.1:${PORT}/admin" >/dev/null

log "14. CONFIRM LIVE PRODUCTION UNCHANGED"
LIVE_RELEASE_AFTER="$(readlink -f "$LIVE_APP/current")"
LIVE_COMMIT_AFTER="$(cat "$LIVE_APP/v2-deployed-commit.txt")"
echo "Before release: $LIVE_RELEASE_BEFORE"
echo "After release:  $LIVE_RELEASE_AFTER"
echo "Before commit:  $LIVE_COMMIT_BEFORE"
echo "After commit:   $LIVE_COMMIT_AFTER"
[[ "$LIVE_RELEASE_BEFORE" == "$LIVE_RELEASE_AFTER" ]]
[[ "$LIVE_COMMIT_BEFORE" == "$LIVE_COMMIT_AFTER" ]]
curl -sS -o /dev/null -w 'Live HTTP %{http_code} | SSL %{ssl_verify_result}\n' https://head-heart.atomglobal.com/

log "15. FINAL STATUS"
readlink -f "$APP/current"
cat "$APP/deployed-commit.txt"
ls -lah "$APP/current/backend/bin/create-admin.php"
systemctl is-enabled head-heart-v2-sync.timer 2>/dev/null || true
systemctl is-active head-heart-v2-sync.timer 2>/dev/null || true

printf '%s\n' \
  "==================================================" \
  " STAGE 4 LOCAL API AND FRONTEND READY" \
  " URL: http://127.0.0.1:${PORT}" \
  " HOST: ${STAGE_HOST}" \
  " LIVE PRODUCTION WAS NOT CHANGED" \
  "=================================================="

NEW_RELEASE=""
trap - ERR
