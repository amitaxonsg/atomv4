#!/usr/bin/env bash
set -Eeuo pipefail

DOMAIN="${DOMAIN:-v4.atomglobal.com}"
APP_ROOT="${APP_ROOT:-/var/www/v4.atomglobal.com}"
SOURCE_DIR="${SOURCE_DIR:-/srv/v4.atomglobal.com/source}"
ENV_FILE="${ENV_FILE:-/etc/growth-alignment/v4.env}"
STORAGE_PATH="${STORAGE_PATH:-/var/lib/growth-alignment-v4}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/growth-alignment-v4}"
ISSUE_CERT=0
[[ "${1:-}" == "--issue-cert" ]] && ISSUE_CERT=1

[[ $EUID -eq 0 ]] || { echo 'Run as root.' >&2; exit 1; }
[[ -d "$SOURCE_DIR/.git" ]] || { echo "Missing Git checkout: $SOURCE_DIR" >&2; exit 1; }

install -d -m 0755 "$APP_ROOT/releases" "$SOURCE_DIR"
install -d -m 0750 -o www-data -g www-data "$STORAGE_PATH" "$STORAGE_PATH/media" "$STORAGE_PATH/reports" "$STORAGE_PATH/tmp"
install -d -m 0750 -o root -g root "$BACKUP_DIR"
install -d -m 0750 /etc/growth-alignment

if [[ ! -e "$ENV_FILE" ]]; then
  umask 027
  cat > "$ENV_FILE" <<EOF
APP_ENV=production
APP_URL=https://$DOMAIN
APP_KEY=REPLACE_WITH_A_NEW_RANDOM_V4_APP_KEY
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=growth_alignment_v4
DB_USERNAME=growth_alignment_v4
DB_PASSWORD=REPLACE_WITH_A_STRONG_DATABASE_PASSWORD
STORAGE_PATH=$STORAGE_PATH
EOF
  chown root:www-data "$ENV_FILE"
  chmod 0640 "$ENV_FILE"
  echo "Created $ENV_FILE. Fill in APP_KEY and database details before deployment."
fi

SITE_FILE="/etc/apache2/sites-available/$DOMAIN.conf"
cat > "$SITE_FILE" <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $APP_ROOT/current/frontend
    RewriteEngine On
    RewriteRule ^ https://$DOMAIN%{REQUEST_URI} [R=301,L]
    ErrorLog \${APACHE_LOG_DIR}/$DOMAIN-error.log
    CustomLog \${APACHE_LOG_DIR}/$DOMAIN-access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName $DOMAIN
    DocumentRoot $APP_ROOT/current/frontend

    <Directory $APP_ROOT/current/frontend>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        RewriteEngine On
        RewriteCond %{REQUEST_URI} !^/api/
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ /index.html [L]
    </Directory>

    Alias /api $APP_ROOT/current/backend/public
    <Directory $APP_ROOT/current/backend/public>
        Options -Indexes
        AllowOverride None
        Require all granted
        DirectoryIndex index.php
        AcceptPathInfo On
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]

        <FilesMatch "\\.php$">
            SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost/"
        </FilesMatch>
    </Directory>

    SetEnvIf Request_URI "^/(admin|report|payment|assessment)" NOINDEX=1
    Header always set X-Robots-Tag "noindex, nofollow" env=NOINDEX
    ErrorLog \${APACHE_LOG_DIR}/$DOMAIN-error.log
    CustomLog \${APACHE_LOG_DIR}/$DOMAIN-access.log combined
</VirtualHost>
EOF

a2enmod rewrite headers ssl
a2ensite "$DOMAIN.conf"
apache2ctl configtest
systemctl reload apache2

if [[ $ISSUE_CERT -eq 1 ]]; then
  command -v certbot >/dev/null || { echo 'Install certbot and python3-certbot-apache first.' >&2; exit 1; }
  certbot --apache -d "$DOMAIN" --redirect --non-interactive --agree-tos --email "${CERTBOT_EMAIL:?Set CERTBOT_EMAIL before using --issue-cert}"
fi

echo "V4 Apache vhost enabled: $DOMAIN"
echo "Next: configure $ENV_FILE, create its database, then run deploy/update-v4-apache.sh"
