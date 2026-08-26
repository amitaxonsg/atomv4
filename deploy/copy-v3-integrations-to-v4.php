<?php
declare(strict_types=1);

// Transfers V3 integration values to V4 only on the server. V3 and V4 use
// independently generated Composer autoloaders, so the V3 export runs in a
// child process and its JSON travels through a private process pipe. Secrets
// are never written to disk, command arguments, Git or terminal output.

$emailKeys = [
    'email.provider', 'email.smtp_host', 'email.smtp_port', 'email.smtp_username',
    'email.smtp_password', 'email.smtp_encryption', 'email.smtp2go_api_key',
    'email.from_name', 'email.from_address', 'email.reply_to',
];
$stripeKeys = ['stripe.secret_key', 'stripe.webhook_secret'];
$sensitive = ['email.smtp_password', 'email.smtp2go_api_key', 'stripe.secret_key', 'stripe.webhook_secret'];

if (($argv[1] ?? '') === '--export-v3') {
    $bootstrap = $argv[2] ?? '';
    if (!is_file($bootstrap)) {
        fwrite(STDERR, "V3 bootstrap file is required.\\n");
        exit(1);
    }
    $container = require $bootstrap;
    $settings = $container['settings'];
    $values = [];
    foreach (array_merge($emailKeys, $stripeKeys) as $key) {
        $value = $settings->get($key, '');
        if (is_scalar($value) && trim((string) $value) !== '') $values[$key] = (string) $value;
    }
    echo json_encode($values, JSON_THROW_ON_ERROR);
    exit(0);
}

$v3Bootstrap = $argv[1] ?? '/srv/head-heart.atomglobal.com/source/backend/src/bootstrap.php';
$v4Bootstrap = $argv[2] ?? '/srv/v4.atomglobal.com/source/backend/src/bootstrap.php';
$allowLive = in_array('--allow-live-stripe', $argv, true);

if (!is_file($v3Bootstrap) || !is_file($v4Bootstrap)) {
    fwrite(STDERR, "Both V3 and V4 bootstrap files are required.\\n");
    exit(1);
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --export-v3 ' . escapeshellarg($v3Bootstrap);
$process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "Unable to open the private V3 export process.\\n");
    exit(1);
}
fclose($pipes[0]);
$json = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0) {
    fwrite(STDERR, "Unable to read V3 integration settings: " . trim($error) . "\\n");
    exit(1);
}

try {
    $values = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fwrite(STDERR, "V3 integration export was invalid.\\n");
    exit(1);
}

$secret = trim((string) ($values['stripe.secret_key'] ?? ''));
if (str_starts_with($secret, 'sk_live_') && !$allowLive) {
    fwrite(STDERR, "Live Stripe transfer requires --allow-live-stripe.\\n");
    exit(2);
}

$v4 = require $v4Bootstrap;
$target = $v4['settings'];
foreach ($values as $key => $value) {
    $target->set($key, $value, in_array($key, $sensitive, true));
}

$target->set('email.public_base_url', 'https://v4.atomglobal.com');
$target->set('payments.cash_on_delivery_enabled', 'true');

echo "V3 SMTP2GO/email settings and Stripe credentials copied securely into V4.\\n";
echo "V4 Full Report and retest Price IDs remain separate V4 settings.\\n";
