#!/usr/bin/env php
<?php
declare(strict_types=1);

$container = require dirname(__DIR__) . '/src/bootstrap.php';
$limit = max(1, min(100, (int) ($argv[1] ?? 25)));
$results = $container['mailQueueProcessor']->processDue($limit);
foreach ($results as $result) {
    $id = (int) $result['id'];
    if ($result['status'] === 'sent') echo "Sent queue item {$id}\n";
    else fwrite(STDERR, "Queue item {$id} {$result['status']}: {$result['failureReason']}\n");
}
echo 'Processed ' . count($results) . " email queue item(s).\n";
