#!/usr/bin/env php
<?php
declare(strict_types=1);

const CONFIRMATION = 'RUN-PRODUCTION-REPORT-SMOKE';
const TRACKS = ['personal', 'newjoiner', 'manager', 'executive'];
const EXPECTED_QUESTION_COUNT = 40;

$options = getopt('', ['recipient:', 'track::', 'send-email', 'confirm:']);
$recipient = strtolower(trim((string) ($options['recipient'] ?? '')));
$trackKey = strtolower(trim((string) ($options['track'] ?? 'personal')));
$sendEmail = array_key_exists('send-email', $options);
$confirmation = (string) ($options['confirm'] ?? '');

if ($confirmation !== CONFIRMATION) {
    fwrite(STDERR, "Refusing to run report smoke test. Re-run with --confirm=" . CONFIRMATION . "\n");
    exit(64);
}
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "A valid --recipient is required.\n");
    exit(64);
}
if (!in_array($trackKey, TRACKS, true)) {
    fwrite(STDERR, "Unknown --track.\n");
    exit(64);
}

$container = require dirname(__DIR__) . '/src/bootstrap.php';
$db = $container['db'];
$surveys = $container['surveys'];
$reports = $container['reports'];
$pdf = $container['pdf'];
$sessionId = null;
$participantId = null;
$pdfPath = null;
$failure = null;

function passReport(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

function marksReport(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

function paidContentReady(mixed $paid): bool
{
    if (!is_array($paid)) return false;
    $rich = 0;
    foreach (['developmentAreas', 'relationships', 'work', 'workingStyleTips', 'handlingDifficulty', 'growth'] as $key) {
        $value = $paid[$key] ?? null;
        if ((is_string($value) && trim($value) !== '') || (is_array($value) && $value)) $rich++;
    }
    return trim((string) ($paid['summary'] ?? '')) !== ''
        && is_array($paid['strengths'] ?? null) && (bool) $paid['strengths']
        && is_array($paid['watchouts'] ?? null) && (bool) $paid['watchouts']
        && is_array($paid['subscaleReads'] ?? null) && count($paid['subscaleReads']) === 10
        && is_array($paid['upgradeReasons'] ?? null) && (bool) $paid['upgradeReasons']
        && $rich >= 4;
}

function cleanupReportSmoke(object $db, ?int $sessionId, ?int $participantId, string $recipient, ?string $pdfPath): void
{
    $queueRows = $db->fetchAll('SELECT id FROM email_queue WHERE recipient_email = ?', [$recipient]);
    $queueIds = array_map(static fn(array $row): int => (int) $row['id'], $queueRows);
    $db->transaction(function () use ($db, $sessionId, $participantId, $queueIds): void {
        if ($queueIds) {
            $placeholders = marksReport($queueIds);
            $db->execute("DELETE FROM notification_events WHERE entity_type = 'email_queue' AND entity_id IN ({$placeholders})", array_map('strval', $queueIds));
            $db->execute("DELETE FROM email_logs WHERE email_queue_id IN ({$placeholders})", $queueIds);
            $db->execute("DELETE FROM email_queue WHERE id IN ({$placeholders})", $queueIds);
        }
        if ($sessionId) {
            $db->execute('DELETE rdl FROM report_delivery_log rdl JOIN generated_reports gr ON gr.id = rdl.generated_report_id WHERE gr.survey_session_id = ?', [$sessionId]);
            $db->execute('DELETE srt FROM secure_report_tokens srt JOIN generated_reports gr ON gr.id = srt.generated_report_id WHERE gr.survey_session_id = ?', [$sessionId]);
            foreach (['affiliate_commissions', 'generated_reports', 'score_snapshots', 'payments', 'affiliate_attributions', 'abandoned_survey_events', 'survey_answers', 'analytics_events', 'consent_logs'] as $table) {
                $db->execute("DELETE FROM {$table} WHERE survey_session_id = ?", [$sessionId]);
            }
            $db->execute('DELETE FROM survey_sessions WHERE id = ?', [$sessionId]);
            $db->execute("DELETE FROM audit_logs WHERE entity_type = 'survey_session' AND entity_id = ?", [(string) $sessionId]);
        }
        if ($participantId) {
            $db->execute('DELETE FROM consent_logs WHERE participant_id = ?', [$participantId]);
            $db->execute('DELETE FROM affiliate_attributions WHERE participant_id = ?', [$participantId]);
            $db->execute('DELETE FROM participants WHERE id = ? AND NOT EXISTS (SELECT 1 FROM survey_sessions s WHERE s.participant_id = participants.id)', [$participantId]);
            $db->execute("DELETE FROM audit_logs WHERE entity_type = 'participant' AND entity_id = ?", [(string) $participantId]);
        }
    });
    if ($pdfPath && is_file($pdfPath)) @unlink($pdfPath);
}

$existing = $db->fetch('SELECT id FROM participants WHERE email_normalized = ? AND anonymised_at IS NULL LIMIT 1', [$recipient]);
if ($existing) {
    fwrite(STDERR, "Recipient already exists in participants.\n");
    exit(65);
}

try {
    echo "==================================================\n";
    echo " HEAD–HEART LITE / FULL REPORT SMOKE TEST\n";
    echo "==================================================\n";

    $participant = [
        'name' => 'Report Audit ' . gmdate('Ymd-His'),
        'email' => $recipient,
        'ageRange' => '35–44',
        'gender' => 'Prefer not to say',
        'role' => $trackKey === 'personal' ? 'Individual / personal reflection' : 'Individual Contributor',
        'industry' => 'Technology',
        'region' => 'Singapore',
        'purpose' => 'Professional development',
        'tenure' => '1–2 years',
        'department' => '',
        'level' => '',
        'privacyConsent' => true,
        'transactionalConsent' => true,
        'marketingConsent' => false,
    ];
    $created = $surveys->create(['trackKey' => $trackKey, 'participant' => $participant, 'section' => 0]);
    $sessionId = (int) ($created['id'] ?? 0);
    $session = $db->fetch('SELECT participant_id FROM survey_sessions WHERE id = ?', [$sessionId]);
    $participantId = (int) ($session['participant_id'] ?? 0);
    passReport($sessionId > 0 && $participantId > 0, 'Temporary report test session was created');
    passReport(count($created['assessment']['questions'] ?? []) === EXPECTED_QUESTION_COUNT, 'V3 report test session contains 40 questions');

    $answers = [];
    for ($index = 0; $index < EXPECTED_QUESTION_COUNT; $index++) $answers[] = ['value' => ($index % 5) + 1, 'note' => ''];
    $completed = $surveys->complete($sessionId, [
        'resumeToken' => (string) $created['resumeToken'],
        'participant' => $participant,
        'answers' => $answers,
        'section' => 9,
    ]);
    $token = (string) ($completed['reportToken'] ?? '');
    passReport((bool) preg_match('/^[a-f0-9]{64}$/', $token), 'Completion returned a private report token');

    $locked = $reports->byToken($token);
    passReport((bool) $locked, 'Private report route returns the generated report');
    passReport(empty($locked['is_unlocked']), 'Full Report starts locked');
    passReport(empty($locked['paid_report_json']), 'Locked API does not expose paid report content');
    $free = json_decode((string) ($locked['free_report_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    passReport(trim((string) ($free['summary']['summary'] ?? '')) !== '', 'Lite Report summary is available immediately');
    passReport(is_array($free['summary']['strengths'] ?? null) && (bool) $free['summary']['strengths'], 'Lite Report strengths are available immediately');
    passReport(is_array($free['summary']['watchouts'] ?? null) && (bool) $free['summary']['watchouts'], 'Lite Report observations are available immediately');
    passReport(is_array($free['upgradePreview'] ?? null) && (bool) $free['upgradePreview'], 'Locked report contains the approved CMS upgrade preview');
    passReport(array_key_exists('checkoutAvailable', $locked), 'Report API exposes redacted checkout readiness');

    $stored = $db->fetch('SELECT paid_report_json FROM generated_reports WHERE survey_session_id = ?', [$sessionId]);
    $storedPaid = json_decode((string) ($stored['paid_report_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    passReport(paidContentReady($storedPaid['content'] ?? null), 'Immutable Full Report snapshot contains the approved rich CMS content');

    $reports->unlockBySession($sessionId, 'production_report_smoke');
    $unlocked = $reports->byToken($token);
    passReport(!empty($unlocked['is_unlocked']), 'Authorised unlock changes the report to Full');
    $paid = json_decode((string) ($unlocked['paid_report_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    passReport(paidContentReady($paid['content'] ?? null), 'Unlocked API reveals complete Full Report content');

    $pdfPath = $pdf->generate((int) ($completed['reportId'] ?? 0));
    passReport(is_file($pdfPath) && filesize($pdfPath) > 1000, 'Unlocked Full Report PDF was generated');

    if ($sendEmail) {
        $cash = new \AtomGlobal\Payments\CashOnDeliveryService(
            $db,
            $container['settings'],
            $reports,
            $container['config']
        );
        $checkout = $cash->checkout($sessionId, $trackKey);
        $delivery = $container['mailQueueProcessor']->processIds($checkout['emailQueueIds'] ?? []);
        passReport(count($delivery) === 2, 'UAT checkout processed the payment and Full Report email queue items immediately');
        passReport(
            count(array_filter($delivery, static fn(array $item): bool => $item['status'] === 'sent')) === 2,
            'SMTP2GO accepted the UAT payment confirmation and professional PDF report email'
        );
        $paidDelivery = array_values(array_filter(
            $delivery,
            static fn(array $item): bool => $item['templateKey'] === 'paid_report_ready' && $item['status'] === 'sent'
        ));
        passReport(
            count($paidDelivery) === 1 && trim((string) ($paidDelivery[0]['messageId'] ?? '')) !== '',
            'Professional PDF report email recorded its provider message id'
        );
    }

    echo "REPORT FLOW SMOKE TEST PASSED. Temporary records will now be removed.\n";
} catch (Throwable $error) {
    $failure = $error;
    fwrite(STDERR, "REPORT FLOW SMOKE TEST FAILED: {$error->getMessage()}\n");
} finally {
    try {
        cleanupReportSmoke($db, $sessionId, $participantId, $recipient, $pdfPath);
        echo "PASS: Temporary report participant, data, email queues and PDF were removed.\n";
    } catch (Throwable $cleanupError) {
        fwrite(STDERR, "REPORT CLEANUP FAILED: {$cleanupError->getMessage()}\n");
        if (!$failure) $failure = $cleanupError;
    }
}

if ($failure) exit(2);
echo "==================================================\n";
echo " LITE / FULL REPORT CHAIN: VERIFIED\n";
echo " DATABASE LEFT CLEAN AFTER REPORT TEST\n";
echo "==================================================\n";
