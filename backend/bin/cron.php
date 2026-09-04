#!/usr/bin/env php
<?php
declare(strict_types=1);

$container = require dirname(__DIR__) . '/src/bootstrap.php';
$storage = rtrim((string) $container['config']['storage'], '/');
if (!is_dir($storage) && !mkdir($storage, 0750, true) && !is_dir($storage)) {
    fwrite(STDERR, "Storage is unavailable.\n");
    exit(1);
}

$lock = fopen($storage . '/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);

try {
    $commands = [
        PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/process-abandoned-surveys.php'),
        PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/reconcile-stripe-checkouts.php') . ' 20',
        PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/generate-report-pdfs.php') . ' 25',
        PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/process-admin-alerts.php'),
        PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/process-email-queue.php') . ' 50',
    ];
    foreach ($commands as $command) {
        passthru($command, $status);
        if ($status !== 0) throw new RuntimeException('Cron command failed: ' . $command);
    }
    $container['settings']->set('system.cron_last_run', gmdate(DATE_ATOM));
} catch (Throwable $error) {
    $container['db']->execute('INSERT INTO notification_events (event_key, severity, entity_type, title, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())', ['cron_failed', 'critical', 'system', 'Background processing failed', mb_substr($error->getMessage(), 0, 2000)]);
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
