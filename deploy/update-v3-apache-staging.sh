#!/usr/bin/env bash
set -Eeuo pipefail

SOURCE_DIR="${SOURCE_DIR:-/srv/head-heart.atomglobal.com/staging-source}"
APP_ROOT="${APP_ROOT:-/var/www/head-heart-staging.atomglobal.com}"
ENV_FILE="${ENV_FILE:-/etc/head-heart-alignment/staging.env}"
EXPECTED_STORAGE_PATH="${EXPECTED_STORAGE_PATH:-/var/lib/head-heart-alignment-staging}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/head-heart-alignment-staging}"
CRON_FILE="${CRON_FILE:-/etc/cron.d/head-heart-v3-staging}"
DOMAIN="${DOMAIN:-head-heart-staging.atomglobal.com}"
BRANCH="${BRANCH:-sunil-v3-clean-40q-cms}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
EXPECTED_APP_ENV="${EXPECTED_APP_ENV:-staging}"
CMS_APPLY_SCRIPT="${CMS_APPLY_SCRIPT-bin/apply-v3-public-cms.php}"
PREVIOUS_RELEASE=""
SWITCHED=0

if [[ -x /opt/node-v22/bin/node ]]; then
  export PATH="/opt/node-v22/bin:$PATH"
fi

fail() { echo "ERROR: $*" >&2; exit 1; }

load_env_file() {
  local line key value
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line%$'\r'}"
    [[ -z "$line" || "$line" =~ ^[[:space:]]*# ]] && continue
    [[ "$line" == *=* ]] || continue
    key="${line%%=*}"
    value="${line#*=}"
    key="${key//[[:space:]]/}"
    [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || fail "Invalid environment key: $key"
    if [[ "$value" == \"*\" && "$value" == *\" ]]; then value="${value:1:${#value}-2}"; fi
    if [[ "$value" == \'*\' && "$value" == *\' ]]; then value="${value:1:${#value}-2}"; fi
    export "$key=$value"
  done < "$ENV_FILE"
}

rollback() {
  if [[ "$SWITCHED" -eq 1 && -n "$PREVIOUS_RELEASE" && -d "$PREVIOUS_RELEASE" ]]; then
    echo "Health/deploy failure after switch; restoring previous staging release." >&2
    ln -sfn "$PREVIOUS_RELEASE" "$APP_ROOT/current.rollback"
    mv -Tf "$APP_ROOT/current.rollback" "$APP_ROOT/current"
    systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || true
    systemctl reload apache2 2>/dev/null || true
  fi
}
trap rollback ERR

[[ "${EUID}" -eq 0 ]] || fail "Run as root."
[[ -d "$SOURCE_DIR/.git" ]] || fail "Missing staging source: $SOURCE_DIR"
[[ -r "$ENV_FILE" ]] || fail "Missing/read-protected staging env: $ENV_FILE"

for command in git php composer mysql mysqldump gzip npm node rsync curl apache2ctl runuser; do
  command -v "$command" >/dev/null 2>&1 || fail "Missing required command: $command"
done
node -e 'const [major]=process.versions.node.split(".").map(Number); process.exit(major >= 22 ? 0 : 1)' \
  || fail "Node 22 or newer is required. Checked node: $(command -v node) ($(node --version 2>/dev/null || true))"
PHP_BIN="$(command -v php)"

cd "$SOURCE_DIR"
git fetch origin
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"
COMMIT="$(git rev-parse HEAD)"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
RELEASE_ID="$(date -u +%Y%m%d%H%M%S)-${COMMIT:0:12}"
RELEASE_DIR="$APP_ROOT/releases/$RELEASE_ID"
TEMP_DIR="$APP_ROOT/releases/.$RELEASE_ID.tmp"
PREVIOUS_RELEASE="$(readlink -f "$APP_ROOT/current" 2>/dev/null || true)"

load_env_file
for variable in APP_ENV APP_URL APP_KEY DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD STORAGE_PATH; do
  [[ -n "${!variable:-}" ]] || fail "$variable is not configured in $ENV_FILE"
done

[[ "$APP_ENV" == "$EXPECTED_APP_ENV" ]] || fail "APP_ENV must be $EXPECTED_APP_ENV, got: $APP_ENV"
[[ "$APP_URL" == "https://$DOMAIN" ]] || fail "APP_URL must be https://$DOMAIN, got: $APP_URL"
[[ "$STORAGE_PATH" == "$EXPECTED_STORAGE_PATH" ]] || fail "STORAGE_PATH must be $EXPECTED_STORAGE_PATH, got: $STORAGE_PATH"

install -d -m 0755 "$APP_ROOT/releases"
install -d -m 0750 "$BACKUP_DIR" "$STORAGE_PATH" "$STORAGE_PATH/media" "$STORAGE_PATH/reports" "$STORAGE_PATH/tmp"
chown -R www-data:www-data "$STORAGE_PATH"
chown root:www-data "$ENV_FILE"
chmod 0640 "$ENV_FILE"

MYSQL_PWD="$DB_PASSWORD" mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --database="$DB_DATABASE" --execute='SELECT 1;' >/dev/null
MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --routines --triggers --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" "$DB_DATABASE" | gzip -9 > "$BACKUP_DIR/${DB_DATABASE}-${STAMP}-${COMMIT:0:12}.sql.gz"

ln -sfn "$ENV_FILE" "$SOURCE_DIR/backend/.env"
(
  cd "$SOURCE_DIR/backend"
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
  composer lint
  php bin/migrate.php
  if [[ "$CMS_APPLY_SCRIPT" == "bin/apply-v3-public-cms.php" ]]; then
    php bin/seed.php
    php bin/apply-v3-public-cms.php
  else
    php bin/seed.php
    if [[ -n "$CMS_APPLY_SCRIPT" ]]; then php "$CMS_APPLY_SCRIPT"; fi
  fi
  php ../tests/php/run.php
)
(
  cd "$SOURCE_DIR"
  npm ci --no-audit --no-fund
  npm test
  VITE_API_MODE=production VITE_API_BASE_URL=/api VITE_ENABLE_SW=true npm run build
  test -s dist/index.html
)

rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR/frontend" "$TEMP_DIR/backend"
rsync -a "$SOURCE_DIR/dist/" "$TEMP_DIR/frontend/"
rsync -a --exclude='.env' "$SOURCE_DIR/backend/" "$TEMP_DIR/backend/"
ln -sfn "$ENV_FILE" "$TEMP_DIR/backend/.env"
find "$TEMP_DIR" -type d -exec chmod 0755 {} \;
find "$TEMP_DIR" -type f -exec chmod 0644 {} \;
find "$TEMP_DIR/backend/bin" -type f -exec chmod 0755 {} \;
mv "$TEMP_DIR" "$RELEASE_DIR"

apache2ctl configtest
ln -sfn "$RELEASE_DIR" "$APP_ROOT/current.new"
mv -Tf "$APP_ROOT/current.new" "$APP_ROOT/current"
SWITCHED=1
printf '%s\n' "$COMMIT" > "$APP_ROOT/deployed-commit.txt"
systemctl reload "$PHP_FPM_SERVICE"
systemctl reload apache2

cat > "$CRON_FILE.tmp" <<EOF
MAILTO=""
*/5 * * * * www-data $PHP_BIN $APP_ROOT/current/backend/bin/cron.php >> $STORAGE_PATH/cron.log 2>&1
EOF
install -o root -g root -m 0644 "$CRON_FILE.tmp" "$CRON_FILE"
rm -f "$CRON_FILE.tmp"
systemctl reload cron 2>/dev/null || systemctl restart cron
runuser -u www-data -- "$PHP_BIN" "$APP_ROOT/current/backend/bin/cron.php"

HEALTH="$(curl --fail --silent --show-error --max-time 20 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/api/health")"
grep -q '"status":"ok"' <<<"$HEALTH" || fail "Health check failed: $HEALTH"
grep -q '"cron":true' <<<"$HEALTH" || fail "Staging background processing did not become healthy: $HEALTH"
curl --fail --silent --show-error --max-time 20 --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/" >/dev/null

SWITCHED=0
find "$APP_ROOT/releases" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -nr | tail -n +11 | cut -d' ' -f2- | xargs -r rm -rf
find "$BACKUP_DIR" -type f -name '*.sql.gz' -mtime +30 -delete

echo "Apache release updated successfully."
echo "Commit: $COMMIT"
echo "URL: https://$DOMAIN/"
echo "Health: $HEALTH"
echo "Background processing: scheduled every 5 minutes and verified healthy."
trap - ERR
