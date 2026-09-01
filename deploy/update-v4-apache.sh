#!/usr/bin/env bash
set -Eeuo pipefail

# Deploy the isolated Growth Alignment V4 site. This only uses V4 paths and
# does not alter head-heart.atomglobal.com or the V3 staging site.
export SOURCE_DIR="${SOURCE_DIR:-/srv/v4.atomglobal.com/source}"
export APP_ROOT="${APP_ROOT:-/var/www/v4.atomglobal.com}"
export ENV_FILE="${ENV_FILE:-/etc/growth-alignment/v4.env}"
export EXPECTED_STORAGE_PATH="${EXPECTED_STORAGE_PATH:-/var/lib/growth-alignment-v4}"
export BACKUP_DIR="${BACKUP_DIR:-/var/backups/growth-alignment-v4}"
export CRON_FILE="${CRON_FILE:-/etc/cron.d/growth-alignment-v4}"
export DOMAIN="${DOMAIN:-v4.atomglobal.com}"
export BRANCH="${BRANCH:-sunil-v4-smooth-checkout-crm-blueprint}"
export PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
export EXPECTED_APP_ENV="${EXPECTED_APP_ENV:-production}"
export CMS_APPLY_SCRIPT="${CMS_APPLY_SCRIPT:-}"

exec /usr/bin/env bash "$(dirname "$0")/update-v3-apache-staging.sh"
