#!/usr/bin/env php
<?php
declare(strict_types=1);

$container = require dirname(__DIR__) . '/src/bootstrap.php';
$db = $container['db'];
$settings = $container['settings'];
$options = getopt('', ['checkout::']);
$checkout = trim((string) ($options['checkout'] ?? ''));

function burninLine(string $state, string $message): void
{
    echo sprintf("%-5s %s\n", $state . ':', $message);
}

function burninPass(bool $condition, string $success, string $failure, bool $required = true): bool
{
    if ($condition) {
        burninLine('PASS', $success);
        return true;
    }
    burninLine($required ? 'FAIL' : 'WARN', $failure);
    return !$required;
}

$failures = 0;
echo "============================================================\n";
echo " V4 STRIPE PAYMENT / FULL REPORT BURN-IN — READ ONLY\n";
echo "============================================================\n";

$secret = trim((string) $settings->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? ''));
$webhookSecret = trim((string) $settings->get('stripe.webhook_secret', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''));
if (!burninPass($secret !== '', 'Stripe secret key is configured.', 'Stripe secret key is missing.')) $failures++;
if (!burninPass($webhookSecret !== '', 'Stripe webhook secret is configured.', 'Stripe webhook secret is missing.')) $failures++;

$params = [];
$where = "provider = 'stripe'";
if ($checkout !== '') {
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $checkout)) {
        burninLine('FAIL', 'Provided checkout reference is invalid.');
        exit(2);
    }
    $where .= ' AND stripe_checkout_session_id = ?';
    $params[] = $checkout;
}
$payment = $db->fetch("SELECT * FROM payments WHERE {$where} ORDER BY id DESC LIMIT 1", $params);
if (!$payment) {
    burninLine('FAIL', 'No Stripe payment record was found.');
    exit(2);
}

burninLine('INFO', 'Payment ID: ' . $payment['id']);
burninLine('INFO', 'Checkout: ' . ($payment['stripe_checkout_session_id'] ?: '(missing)'));
burninLine('INFO', 'Status: ' . $payment['status']);
burninLine('INFO', 'Amount: ' . (($payment['amount'] ?? null) === null ? '(not recorded)' : number_format(((int) $payment['amount']) / 100, 2) . ' ' . strtoupper((string) $payment['currency'])));

if (!burninPass($payment['status'] === 'paid', 'Payment is recorded as paid.', 'Payment is not recorded as paid.')) $failures++;
if (!burninPass(trim((string) ($payment['stripe_payment_intent_id'] ?? '')) !== '', 'Stripe Payment Intent ID is recorded.', 'Stripe Payment Intent ID is missing.')) $failures++;
if (!burninPass(trim((string) ($payment['paid_at'] ?? '')) !== '', 'Payment paid_at timestamp is recorded.', 'Payment paid_at timestamp is missing.')) $failures++;
burninPass(trim((string) ($payment['stripe_customer_id'] ?? '')) !== '', 'Stripe Customer ID is recorded.', 'Stripe Customer ID is blank; Checkout can still be valid when Stripe does not create a reusable customer.', false);

$metadata = json_decode((string) ($payment['metadata_json'] ?? '{}'), true) ?: [];
$reportId = (int) ($metadata['reportId'] ?? 0);
$reportUrl = trim((string) ($metadata['reportUrl'] ?? ''));
if (!burninPass($reportId > 0, 'Payment metadata contains the generated Report ID.', 'Payment metadata is missing the generated Report ID.')) $failures++;
if (!burninPass($reportUrl !== '', 'Payment metadata contains the secure Full Report URL.', 'Payment metadata is missing the secure Full Report URL.')) $failures++;

$report = $reportId > 0 ? $db->fetch('SELECT id, survey_session_id, is_unlocked, unlock_reason, unlocked_at, pdf_path, pdf_generated_at, token_expires_at FROM generated_reports WHERE id = ? LIMIT 1', [$reportId]) : null;
if (!burninPass((bool) $report, 'Generated report record exists.', 'Generated report record was not found.')) $failures++;
if ($report) {
    if (!burninPass((bool) $report['is_unlocked'], 'Full Development Report is unlocked.', 'Full Development Report is still locked.')) $failures++;
    if (!burninPass(trim((string) ($report['unlocked_at'] ?? '')) !== '', 'Report unlock timestamp is recorded.', 'Report unlock timestamp is missing.')) $failures++;
    $token = $db->fetch('SELECT id, expires_at FROM secure_report_tokens WHERE generated_report_id = ? AND revoked_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1', [$reportId]);
    if (!burninPass((bool) $token, 'An active secure Full Report token exists.', 'No active secure Full Report token exists.')) $failures++;
    $pdfPath = trim((string) ($report['pdf_path'] ?? ''));
    burninPass($pdfPath !== '' && is_file($pdfPath), 'Full Report PDF exists on disk.', 'Full Report PDF is not generated yet; the five-minute background processor should generate it.', false);
}

$queue = $reportId > 0 ? $db->fetch(
    "SELECT id, status, attempts, sent_at, provider_message_id, failure_reason FROM email_queue WHERE template_key = 'paid_report_ready' AND JSON_UNQUOTE(JSON_EXTRACT(variables_json, '$.reportId')) = ? ORDER BY id DESC LIMIT 1",
    [(string) $reportId]
) : null;
if (!burninPass((bool) $queue, 'PDF Full Report email is present in the delivery queue.', 'PDF Full Report email was not queued.')) $failures++;
if ($queue) {
    burninLine('INFO', 'PDF email queue ID/status: ' . $queue['id'] . ' / ' . $queue['status']);
    if ($queue['status'] === 'sent') {
        burninPass(trim((string) ($queue['provider_message_id'] ?? '')) !== '', 'PDF email was sent and has a provider message ID.', 'PDF email says sent but provider message ID is missing.', false);
    } elseif (in_array($queue['status'], ['queued', 'retry'], true)) {
        burninLine('WARN', 'PDF email is queued/retrying. Background processing runs every five minutes.');
    } else {
        burninLine('FAIL', 'PDF email status is ' . $queue['status'] . ': ' . (string) ($queue['failure_reason'] ?? ''));
        $failures++;
    }
}

$checkoutId = (string) ($payment['stripe_checkout_session_id'] ?? '');
$webhook = $checkoutId !== '' ? $db->fetch(
    "SELECT id, stripe_event_id, event_type, status, failure_reason, received_at, processed_at FROM stripe_webhook_events WHERE payload_json LIKE ? ORDER BY id DESC LIMIT 1",
    ['%' . $checkoutId . '%']
) : null;
if ($webhook) {
    burninLine('INFO', 'Webhook event: ' . $webhook['event_type'] . ' / ' . $webhook['status']);
    if (!burninPass($webhook['status'] === 'processed', 'Stripe webhook event was processed.', 'Stripe webhook event exists but was not processed.')) $failures++;
} else {
    burninLine('WARN', 'No webhook event containing this Checkout ID was found. The new reconciliation fallback covers a delayed/missed webhook after deployment.');
}

$notice = $db->fetch(
    'SELECT id, created_at FROM notification_events WHERE event_key = ? AND entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
    ['payment_paid', 'payment', (string) $payment['id']]
);
burninPass((bool) $notice, 'Administrator payment notification record exists.', 'Administrator payment notification is not present on the current live baseline; the new fulfilment check will create it after deployment.', false);

$paymentEmail = $reportId > 0 ? $db->fetch(
    "SELECT id, status FROM email_queue WHERE template_key = 'payment_successful' AND JSON_UNQUOTE(JSON_EXTRACT(variables_json, '$.reportId')) = ? ORDER BY id DESC LIMIT 1",
    [(string) $reportId]
) : null;
if (!burninPass((bool) $paymentEmail, 'Customer payment confirmation email is queued/recorded.', 'Customer payment confirmation email was not queued.')) $failures++;

if ($failures > 0) {
    echo "============================================================\n";
    echo " BURN-IN RESULT: {$failures} REQUIRED CHECK(S) FAILED\n";
    echo " DO NOT DEPLOY/RETEST CARD PAYMENT UNTIL REVIEWED\n";
    echo "============================================================\n";
    exit(2);
}

echo "============================================================\n";
echo " BURN-IN RESULT: CORE PAYMENT / REPORT CHAIN VERIFIED\n";
echo " WARNINGS MAY REFLECT THE CURRENT PRE-FIX LIVE BASELINE\n";
echo "============================================================\n";
