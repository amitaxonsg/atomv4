<?php
declare(strict_types=1);

// Run on the server after the V4 database and environment file exist.
// Secrets are decrypted only inside each application process, transferred in
// memory and re-encrypted with the V4 APP_KEY by SettingsService::set().

$v3Bootstrap = $argv[1] ?? '/srv/head-heart.atomglobal.com/staging-source/backend/src/bootstrap.php';
$v4Bootstrap = $argv[2] ?? '/srv/v4.atomglobal.com/source/backend/src/bootstrap.php';
$allowLive = in_array('--allow-live-stripe', $argv, true);

if (!is_file($v3Bootstrap) || !is_file($v4Bootstrap)) {
    fwrite(STDERR, "Both V3 and V4 bootstrap files are required.\n");
    exit(1);
}

$v3 = require $v3Bootstrap;
$v4 = require $v4Bootstrap;
$source = $v3['settings'];
$target = $v4['settings'];

$emailKeys = [
    'email.provider', 'email.smtp_host', 'email.smtp_port', 'email.smtp_username',
    'email.smtp_password', 'email.smtp_encryption', 'email.smtp2go_api_key',
    'email.from_name', 'email.from_address', 'email.reply_to',
];
$stripeKeys = ['stripe.secret_key', 'stripe.webhook_secret'];
$sensitive = ['email.smtp_password', 'email.smtp2go_api_key', 'stripe.secret_key', 'stripe.webhook_secret'];

$secret = trim((string) $source->get('stripe.secret_key', ''));
if (str_starts_with($secret, 'sk_live_') && !$allowLive) {
    fwrite(STDERR, "Live Stripe transfer requires --allow-live-stripe.\n");
    exit(2);
}

foreach (array_merge($emailKeys, $stripeKeys) as $key) {
    $value = $source->get($key, '');
    if (!is_scalar($value) || trim((string) $value) === '') continue;
    $target->set($key, (string) $value, in_array($key, $sensitive, true));
}

$target->set('email.public_base_url', 'https://v4.atomglobal.com');
$target->set('payments.cash_on_delivery_enabled', 'true');

echo "V3 SMTP2GO/email settings and Stripe live credentials copied securely into V4.\n";
echo "V4 Full Report and retest Price IDs remain separate database settings and are not copied from V3.\n";

