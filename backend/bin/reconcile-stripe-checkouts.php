#!/usr/bin/env php
<?php
declare(strict_types=1);

use AtomGlobal\Payments\StripeCheckoutReconciler;

$container = require dirname(__DIR__) . '/src/bootstrap.php';
$db = $container['db'];
$limit = max(1, min(50, (int) ($argv[1] ?? 20)));

$rows = $db->fetchAll(
    "SELECT p.id, p.stripe_checkout_session_id checkoutId, p.status
     FROM payments p
     WHERE p.provider = 'stripe'
       AND p.stripe_checkout_session_id IS NOT NULL
       AND JSON_UNQUOTE(JSON_EXTRACT(p.metadata_json, '$.payment_purpose')) = 'full_report'
       AND (
            p.status = 'checkout_started'
            OR (
                p.status = 'paid'
                AND NOT EXISTS (
                    SELECT 1 FROM notification_events n
                    WHERE n.event_key = 'payment_paid'
                      AND n.entity_type = 'payment'
                      AND CAST(n.entity_id AS UNSIGNED) = p.id
                )
            )
       )
       AND p.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
     ORDER BY p.id ASC
     LIMIT {$limit}"
);

$reconciler = new StripeCheckoutReconciler(
    $db,
    $container['settings'],
    $container['reports'],
    $container['config']
);

$checked = 0;
$recovered = 0;
$failed = 0;

foreach ($rows as $row) {
    $checkoutId = trim((string) ($row['checkoutId'] ?? ''));
    if ($checkoutId === '') continue;
    $checked++;

    try {
        $before = (string) ($row['status'] ?? '');
        $reconciler->reconcile($checkoutId);
        $after = $db->fetch('SELECT status FROM payments WHERE id = ? LIMIT 1', [(int) $row['id']]);
        if ($before !== 'paid' && (($after['status'] ?? '') === 'paid')) $recovered++;
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, 'Stripe reconciliation failed for payment ' . (int) $row['id'] . ': ' . $error->getMessage() . "\n");
    }
}

echo 'Checked ' . $checked . ' Stripe checkout(s); recovered ' . $recovered . '; failures ' . $failed . ".\n";
