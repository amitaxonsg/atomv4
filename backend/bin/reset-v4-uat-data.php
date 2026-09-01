#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Remove pre-UAT transactional/test activity from the isolated V4 database.
 *
 * Preserved: administrators, roles/permissions, assessment definitions,
 * questions, report templates, CMS/content/media, branding, global settings,
 * email templates, alert recipients, retention policies and affiliate records.
 *
 * Usage:
 *   php backend/bin/reset-v4-uat-data.php --confirm=RESET-V4-UAT-DATA
 */

const CONFIRMATION = 'RESET-V4-UAT-DATA';
const EXPECTED_DATABASE = 'growth_alignment_v4';
const EXPECTED_URL = 'https://v4.atomglobal.com';

$options = getopt('', ['confirm:']);
if ((string) ($options['confirm'] ?? '') !== CONFIRMATION) {
    fwrite(STDERR, "Refusing to remove data. Re-run with --confirm=" . CONFIRMATION . "\n");
    exit(64);
}

$container = require dirname(__DIR__) . '/src/bootstrap.php';
$db = $container['db'];
$config = $container['config'];
$database = (string) (($db->fetch('SELECT DATABASE() database_name')['database_name'] ?? ''));
$appUrl = rtrim((string) ($config['url'] ?? ''), '/');

if ($database !== EXPECTED_DATABASE) {
    fwrite(STDERR, "Refusing to remove data from unexpected database: {$database}\n");
    exit(65);
}
if ($appUrl !== EXPECTED_URL) {
    fwrite(STDERR, "Refusing to remove data for unexpected APP_URL: {$appUrl}\n");
    exit(65);
}

$tables = [
    'participants',
    'survey_sessions',
    'survey_answers',
    'score_snapshots',
    'generated_reports',
    'secure_report_tokens',
    'report_commitments',
    'report_delivery_log',
    'payments',
    'stripe_webhook_events',
    'affiliate_clicks',
    'affiliate_attributions',
    'affiliate_commissions',
    'consent_logs',
    'analytics_events',
    'abandoned_survey_events',
    'email_queue',
    'email_logs',
    'notification_events',
    'background_jobs',
    'api_connection_tests',
    'password_reset_tokens',
    'client_feedback',
    'client_feedback_updates',
    'audit_logs',
];

function tableCounts(object $db, array $tables): array
{
    $counts = [];
    foreach ($tables as $table) {
        $row = $db->fetch("SELECT COUNT(*) row_count FROM `{$table}`");
        $counts[$table] = (int) ($row['row_count'] ?? 0);
    }
    return $counts;
}

function printCounts(string $heading, array $counts): void
{
    echo "\n{$heading}\n";
    foreach ($counts as $table => $count) echo str_pad($table, 30) . " {$count}\n";
}

$storage = rtrim((string) ($config['storage'] ?? ''), '/');
$reportsDirectory = $storage . '/reports';
$resolvedReportsDirectory = realpath($reportsDirectory);
$pdfRows = $db->fetchAll("SELECT pdf_path FROM generated_reports WHERE pdf_path IS NOT NULL AND pdf_path <> ''");
$pdfPaths = [];

foreach ($pdfRows as $row) {
    $path = (string) ($row['pdf_path'] ?? '');
    if ($path === '' || !is_file($path)) continue;
    $resolvedPath = realpath($path);
    if ($resolvedReportsDirectory === false || $resolvedPath === false || !str_starts_with($resolvedPath, $resolvedReportsDirectory . DIRECTORY_SEPARATOR)) {
        fwrite(STDERR, "Refusing to remove an unexpected report file: {$path}\n");
        exit(65);
    }
    $pdfPaths[] = $resolvedPath;
}

$before = tableCounts($db, $tables);
printCounts('PRE-UAT ACTIVITY BEFORE RESET', $before);

$db->transaction(function (object $db): void {
    // Dependent rows must be removed before their parents. All statements are
    // deliberately limited to V4 transactional/operational activity tables.
    foreach ([
        'client_feedback_updates',
        'client_feedback',
        'report_delivery_log',
        'report_commitments',
        'secure_report_tokens',
        'affiliate_commissions',
        'generated_reports',
        'score_snapshots',
        'payments',
        'affiliate_attributions',
        'abandoned_survey_events',
        'survey_answers',
        'consent_logs',
        'analytics_events',
        'survey_sessions',
        'participants',
        'affiliate_clicks',
        'stripe_webhook_events',
        'email_logs',
        'email_queue',
        'notification_events',
        'background_jobs',
        'api_connection_tests',
        'password_reset_tokens',
        'audit_logs',
    ] as $table) {
        $db->execute("DELETE FROM `{$table}`");
    }

    $db->execute(
        'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (NULL, ?, ?, NULL, ?, NOW())',
        ['system.uat_data_reset', 'system', json_encode(['database' => EXPECTED_DATABASE, 'preservedAffiliateDefinitions' => true])]
    );
});

$removedFiles = 0;
foreach ($pdfPaths as $path) {
    if (is_file($path) && unlink($path)) $removedFiles++;
}

$after = tableCounts($db, $tables);
printCounts('PRE-UAT ACTIVITY AFTER RESET', $after);

$expectedAfter = array_fill_keys($tables, 0);
$expectedAfter['audit_logs'] = 1;
foreach ($expectedAfter as $table => $expected) {
    if (($after[$table] ?? -1) !== $expected) {
        fwrite(STDERR, "Reset verification failed for {$table}: expected {$expected}, found " . ($after[$table] ?? -1) . "\n");
        exit(70);
    }
}

$preserved = [
    'admin_users', 'assessment_tracks', 'assessment_versions', 'questions',
    'report_templates', 'content_stages', 'media_library', 'global_settings',
    'email_templates', 'affiliates',
];
$preservedCounts = tableCounts($db, $preserved);
printCounts('PRESERVED V4 CONFIGURATION', $preservedCounts);

if (($preservedCounts['admin_users'] ?? 0) < 1) {
    fwrite(STDERR, "Reset completed, but no V4 administrator account exists.\n");
    exit(70);
}
if (($preservedCounts['assessment_tracks'] ?? 0) < 4 || ($preservedCounts['questions'] ?? 0) < 160) {
    fwrite(STDERR, "Reset completed, but the V4 assessment definition is incomplete.\n");
    exit(70);
}

echo "\nV4 PRE-UAT DATA RESET COMPLETED SUCCESSFULLY\n";
echo "Database: {$database}\n";
echo "Application: {$appUrl}\n";
echo "Generated PDF files removed: {$removedFiles}\n";
echo "Affiliate definitions and all CMS/configuration records were preserved.\n";
