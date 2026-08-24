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
$scalar = static function (mixed $value, string $fallback = ""): string {
  return is_string($value) || is_numeric($value) ? (string) $value : $fallback;
};
$keys = [
  "email.provider", "email.smtp_host", "email.smtp_port", "email.smtp_username",
  "email.smtp_password", "email.smtp_encryption", "email.smtp2go_api_key",
  "stripe.secret_key", "stripe.webhook_secret",
  "stripe.price_personal", "stripe.price_newjoiner", "stripe.price_manager", "stripe.price_executive"
];
$fallbacks = [
  "email.provider" => (string) ($_ENV["MAIL_PROVIDER"] ?? ""),
  "email.smtp_host" => (string) ($_ENV["SMTP_HOST"] ?? ""),
  "email.smtp_port" => (string) ($_ENV["SMTP_PORT"] ?? ""),
  "email.smtp_username" => (string) ($_ENV["SMTP_USERNAME"] ?? ""),
  "email.smtp_password" => (string) ($_ENV["SMTP_PASSWORD"] ?? ""),
  "email.smtp_encryption" => (string) ($_ENV["SMTP_ENCRYPTION"] ?? ""),
  "email.smtp2go_api_key" => (string) ($_ENV["SMTP2GO_API_KEY"] ?? ""),
  "stripe.secret_key" => (string) ($_ENV["STRIPE_SECRET_KEY"] ?? ""),
  "stripe.webhook_secret" => (string) ($_ENV["STRIPE_WEBHOOK_SECRET"] ?? ""),
  "stripe.price_personal" => (string) ($_ENV["STRIPE_PRICE_PERSONAL"] ?? ""),
  "stripe.price_newjoiner" => (string) ($_ENV["STRIPE_PRICE_NEWJOINER"] ?? ""),
  "stripe.price_manager" => (string) ($_ENV["STRIPE_PRICE_MANAGER"] ?? ""),
  "stripe.price_executive" => (string) ($_ENV["STRIPE_PRICE_EXECUTIVE"] ?? ""),
];
$out = [];
foreach ($keys as $key) {
  $fallback = $fallbacks[$key] ?? "";
  $out[$key] = $scalar($s->get($key, $fallback), $fallback);
}
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
$stripeReady = str_starts_with($stripe, "sk_test_") && str_starts_with($webhook, "whsec_");
foreach (["personal", "newjoiner", "manager", "executive"] as $track) {
  if (!str_starts_with(trim($out["stripe.price_" . $track]), "price_")) {
    $stripeReady = false;
  }
}
$out["_stripe_test_ready"] = $stripeReady ? "1" : "0";
if (!$stripeReady) {
  foreach (array_keys($out) as $key) {
    if (str_starts_with($key, "stripe.")) unset($out[$key]);
  }
  fwrite(STDERR, "WARNING: V2 does not contain a complete Stripe TEST configuration.\n");
  fwrite(STDERR, "WARNING: Email will be copied, but live Stripe credentials will never be copied to staging.\n");
}
file_put_contents($argv[1], json_encode($out, JSON_THROW_ON_ERROR));
echo "V2 email integration validated for guarded transfer.\n";
' "$TMP"

cd "$V3_BACKEND"
php -r '
$data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$c = require "src/bootstrap.php";
$s = $c["settings"];
$stripeReady = ($data["_stripe_test_ready"] ?? "0") === "1";
unset($data["_stripe_test_ready"]);
$encrypted = ["email.smtp_password", "email.smtp2go_api_key", "stripe.secret_key", "stripe.webhook_secret"];
foreach ($data as $key => $value) {
  $s->set((string) $key, (string) $value, in_array($key, $encrypted, true));
}
$stripeKeys = [
  "stripe.secret_key", "stripe.webhook_secret", "stripe.price_personal",
  "stripe.price_newjoiner", "stripe.price_manager", "stripe.price_executive"
];
if (!$stripeReady) {
  foreach ($stripeKeys as $key) $s->set($key, "", in_array($key, $encrypted, true));
}
$s->set("email.public_base_url", "https://head-heart-staging.atomglobal.com", false);
$s->set("payments.cash_on_delivery_enabled", "true", false);
$s->set("system.cash_on_delivery_enabled", "true", false);
echo "V3 staging email provider and settings copied securely from V2.\n";
echo $stripeReady
  ? "V3 staging Stripe TEST settings copied securely.\n"
  : "V3 staging Stripe remains disabled; use the authorised no-payment UAT route until test credentials are supplied.\n";
echo "No production/live Stripe credential was accepted.\n";
' "$TMP"

rm -f "$TMP"
trap - EXIT

php bin/email-settings-audit.php
echo "Integration transfer complete. Run deploy/burn-in-v3-staging.sh next."
