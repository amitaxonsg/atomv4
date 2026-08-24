#!/usr/bin/env bash
set -Eeuo pipefail

V2_BACKEND="/var/www/head-heart.atomglobal.com/current/backend"
V3_BACKEND="/srv/head-heart.atomglobal.com/staging-source/backend"
TMP="$(mktemp /root/head-heart-v2-uat.XXXXXX.json)"
trap 'rm -f "$TMP"' EXIT
chmod 0600 "$TMP"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || fail "Run as root."
[[ -f "$V2_BACKEND/src/bootstrap.php" ]] || fail "V2 backend not found: $V2_BACKEND"
[[ -f "$V3_BACKEND/src/bootstrap.php" ]] || fail "V3 staging backend not found: $V3_BACKEND"

cd "$V2_BACKEND"
php -r '
$c = require "src/bootstrap.php";
$s = $c["settings"];
$keys = [
  "email.provider", "email.smtp_host", "email.smtp_port", "email.smtp_username",
  "email.smtp_password", "email.smtp_encryption", "email.smtp2go_api_key",
  "stripe.secret_key", "stripe.webhook_secret",
  "stripe.price_personal", "stripe.price_newjoiner", "stripe.price_manager", "stripe.price_executive"
];
$out = [];
foreach ($keys as $key) $out[$key] = (string) $s->get($key, "");
$provider = strtolower(trim($out["email.provider"]));
$host = strtolower(trim($out["email.smtp_host"]));
$stripe = trim($out["stripe.secret_key"]);
$webhook = trim($out["stripe.webhook_secret"]);
if (!in_array($provider, ["smtp", "smtp2go"], true)) {
  fwrite(STDERR, "ERROR: V2 email provider is unsupported ({$provider}).\n");
  exit(2);
}
if ($provider === "smtp" && ($host === "" || trim($out["email.smtp_username"]) === "" || trim($out["email.smtp_password"]) === "")) {
  fwrite(STDERR, "ERROR: V2 SMTP credentials are incomplete.\n");
  exit(3);
}
if ($provider === "smtp2go" && trim($out["email.smtp2go_api_key"]) === "") {
  fwrite(STDERR, "ERROR: V2 SMTP2GO API key is unavailable.\n");
  exit(3);
}
if (!str_starts_with($stripe, "sk_test_")) {
  fwrite(STDERR, "ERROR: V2 Stripe key is not a test key; refusing to copy it into staging.\n");
  exit(4);
}
if (!str_starts_with($webhook, "whsec_")) {
  fwrite(STDERR, "ERROR: V2 Stripe webhook secret is unavailable or invalid.\n");
  exit(5);
}
foreach (["personal", "newjoiner", "manager", "executive"] as $track) {
  if (!str_starts_with(trim($out["stripe.price_" . $track]), "price_")) {
    fwrite(STDERR, "ERROR: V2 Stripe test price is missing for {$track}.\n");
    exit(6);
  }
}
file_put_contents($argv[1], json_encode($out, JSON_THROW_ON_ERROR));
echo "V2 UAT integrations validated for guarded transfer.\n";
' "$TMP"

cd "$V3_BACKEND"
php -r '
$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$c = require "src/bootstrap.php";
$s = $c["settings"];
$encrypted = ["email.smtp_password", "email.smtp2go_api_key", "stripe.secret_key", "stripe.webhook_secret"];
foreach ($data as $key => $value) {
  $s->set((string) $key, (string) $value, in_array($key, $encrypted, true));
}
$s->set("email.public_base_url", "https://head-heart-staging.atomglobal.com", false);
$s->set("payments.cash_on_delivery_enabled", "true", false);
$s->set("system.cash_on_delivery_enabled", "true", false);
echo "V3 staging email provider and settings copied securely from V2.\n";
echo "V3 staging Stripe TEST settings copied securely.\n";
echo "No production/live Stripe credential was accepted.\n";
' "$TMP"

rm -f "$TMP"
trap - EXIT

php bin/email-settings-audit.php
echo "Integration transfer complete. Run deploy/burn-in-v3-staging.sh next."
