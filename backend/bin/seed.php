#!/usr/bin/env php
<?php
declare(strict_types=1);

use AtomGlobal\Database;

const REFERENCE_MARKER_KEY = 'questionnaire.reference_version';
const REFERENCE_SOURCE_HASH_KEY = 'questionnaire.reference_source_sha256';
const REFERENCE_AGGREGATE_HASH_KEY = 'questionnaire.reference_sha256';

function exportReference(string $root): array
{
    $script = $root . '/scripts/export-assessment-reference.mjs';
    if (!is_file($script)) throw new RuntimeException('Assessment reference exporter is missing.');
    if (!function_exists('exec')) throw new RuntimeException('The PHP CLI exec function is required to export the assessment reference.');

    $temporary = tempnam(sys_get_temp_dir(), 'head-heart-reference-');
    if ($temporary === false) throw new RuntimeException('Unable to create a temporary assessment reference file.');

    $node = trim((string) (getenv('NODE_BINARY') ?: 'node')) ?: 'node';
    $command = escapeshellcmd($node) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($temporary) . ' 2>&1';
    $output = [];
    $status = 0;

    try {
        exec($command, $output, $status);
        if ($status !== 0) {
            throw new RuntimeException("Assessment reference export failed:\n" . implode("\n", $output));
        }
        $json = file_get_contents($temporary);
        if ($json === false || trim($json) === '') throw new RuntimeException('Assessment reference export was empty.');
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } finally {
        @unlink($temporary);
    }
}

function reportPayload(array $track, array $profile): array
{
    $free = [
        'summary' => $profile['summary'],
        'strengths' => array_slice($profile['strengths'] ?? [], 0, 3),
        'watchouts' => array_slice($profile['watchouts'] ?? [], 0, 3),
    ];
    $paid = $profile;
    $paid['subscaleReads'] = $track['subscaleReads'];
    $paid['upgradeReasons'] = $track['upgradeReasons'];
    $paid['hasLeadershipImpact'] = $track['hasLeadershipImpact'];
    $paid['hasCultureFit'] = $track['hasCultureFit'];
    $paid['leadershipImpactLabel'] = $track['leadershipImpactLabel'];
    $paid['cultureFitLabel'] = $track['cultureFitLabel'];
    return [$free, $paid];
}

function assertReferenceVersion(Database $db, int $versionId, array $track): void
{
    $sections = $db->fetchAll(
        'SELECT id, code, name, description, display_order FROM assessment_sections WHERE assessment_version_id = ? AND is_active = 1 ORDER BY display_order',
        [$versionId]
    );
    if (count($sections) !== 10 || count($track['subscales']) !== 10) {
        throw new RuntimeException($track['key'] . ' reference must contain exactly 10 active sections.');
    }

    $sectionIds = [];
    foreach ($track['subscales'] as $index => $expected) {
        $actual = $sections[$index] ?? null;
        if (!$actual
            || $actual['code'] !== $expected['code']
            || $actual['name'] !== $expected['name']
            || (string) ($actual['description'] ?? '') !== (string) ($expected['blurb'] ?? '')
            || (int) $actual['display_order'] !== $index + 1
        ) {
            throw new RuntimeException($track['key'] . ' section ' . ($index + 1) . ' differs from the attached index.html reference.');
        }
        $sectionIds[$expected['code']] = (int) $actual['id'];
    }

    $questions = $db->fetchAll(
        'SELECT q.stable_key, q.question_text, q.scoring_direction, q.position, s.code section_code FROM questions q JOIN assessment_sections s ON s.id = q.section_id WHERE q.assessment_version_id = ? AND q.is_active = 1 ORDER BY q.position',
        [$versionId]
    );
    if (count($questions) !== 50) throw new RuntimeException($track['key'] . ' reference must contain exactly 50 active questions.');

    $position = 1;
    foreach ($track['subscales'] as $section) {
        foreach ($section['items'] as $itemIndex => $item) {
            $actual = $questions[$position - 1] ?? null;
            $stableKey = sprintf('%s-%02d', $section['code'], $itemIndex + 1);
            if (!$actual
                || $actual['stable_key'] !== $stableKey
                || $actual['question_text'] !== $item['t']
                || $actual['scoring_direction'] !== $item['d']
                || $actual['section_code'] !== $section['code']
                || (int) $actual['position'] !== $position
            ) {
                throw new RuntimeException($track['key'] . ' question ' . $position . ' differs from the attached index.html reference.');
            }
            $position++;
        }
    }

    $options = $db->fetchAll('SELECT option_value, label, display_order FROM answer_options WHERE assessment_version_id = ? ORDER BY display_order', [$versionId]);
    if (count($options) !== 5) throw new RuntimeException($track['key'] . ' reference must contain exactly five scored choices.');
    foreach ($track['answerChoices'] as $index => $label) {
        $actual = $options[$index] ?? null;
        if (!$actual || (int) $actual['option_value'] !== $index + 1 || $actual['label'] !== $label || (int) $actual['display_order'] !== $index + 1) {
            throw new RuntimeException($track['key'] . ' answer choices differ from the attached index.html reference.');
        }
    }

    $reports = $db->fetchAll('SELECT profile_key, profile_name, min_score, max_score, free_content_json, paid_content_json FROM report_templates WHERE assessment_version_id = ? ORDER BY min_score DESC', [$versionId]);
    if (count($reports) !== count($track['profiles'])) throw new RuntimeException($track['key'] . ' report profiles are incomplete.');
    foreach ($track['profiles'] as $index => $profile) {
        $actual = $reports[$index] ?? null;
        [$free, $paid] = reportPayload($track, $profile);
        $profileKey = (string) ($profile['key'] ?? strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $profile['name']), '-')));
        if (!$actual
            || $actual['profile_key'] !== $profileKey
            || $actual['profile_name'] !== $profile['name']
            || (int) $actual['min_score'] !== (int) $profile['range'][0]
            || (int) $actual['max_score'] !== (int) $profile['range'][1]
            || json_decode((string) $actual['free_content_json'], true) != $free
            || json_decode((string) $actual['paid_content_json'], true) != $paid
        ) {
            throw new RuntimeException($track['key'] . ' report profile ' . ($index + 1) . ' differs from the attached index.html reference.');
        }
    }
}

$root = dirname(__DIR__, 2);
$container = require dirname(__DIR__) . '/src/bootstrap.php';
$reference = exportReference($root);
$metadata = $reference['reference'] ?? [];
$versionNumber = trim((string) ($metadata['version'] ?? ''));
if ($versionNumber === '') throw new RuntimeException('Assessment reference version is missing.');

$currentMarker = (string) $container['settings']->get(REFERENCE_MARKER_KEY, '');
if (hash_equals($versionNumber, $currentMarker)) {
    echo "Assessment reference {$versionNumber} already imported; CMS content left unchanged.\n";
    exit(0);
}

$trackOrder = ['personal', 'newjoiner', 'manager', 'executive'];
$tracks = [];
foreach ($reference['tracks'] ?? [] as $track) $tracks[$track['key']] = $track;
foreach ($trackOrder as $key) {
    if (!isset($tracks[$key])) throw new RuntimeException("Assessment reference track {$key} is missing.");
}

$container['db']->transaction(function () use ($container, $reference, $metadata, $versionNumber, $trackOrder, $tracks): void {
    foreach ($trackOrder as $displayIndex => $key) {
        $track = $tracks[$key];
        $existingTrack = $container['db']->fetch('SELECT id FROM assessment_tracks WHERE track_key = ? FOR UPDATE', [$key]);
        if ($existingTrack) {
            $trackId = (int) $existingTrack['id'];
            $container['db']->execute(
                'UPDATE assessment_tracks SET name = ?, description = ?, price_minor = ?, currency = ?, is_active = 1, display_order = ?, updated_at = NOW() WHERE id = ?',
                [$track['label'], $track['tagline'], $track['priceMinor'], $track['currency'], $displayIndex + 1, $trackId]
            );
        } else {
            $trackId = $container['db']->insert(
                'INSERT INTO assessment_tracks (track_key, name, description, price_minor, currency, is_active, display_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW())',
                [$key, $track['label'], $track['tagline'], $track['priceMinor'], $track['currency'], $displayIndex + 1]
            );
        }

        $container['db']->execute(
            'INSERT INTO assessment_track_settings '
            . '(track_id, public_title, short_title, audience_label, estimated_minutes_min, estimated_minutes_max, average_seconds_per_question, average_seconds_per_participant_field, free_report_label, paid_report_label, free_report_read_minutes, paid_report_read_minutes, question_count, section_count, show_remaining_time, show_question_count, show_section_count, show_autosave, introductory_note, last_reviewed_date, intro_headline, intro_body, intro_offer, heart_label, heart_description, head_label, head_description, intake_configuration_json, allow_not_applicable, allow_answer_notes, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, 18, 12, ?, ?, 3, 12, 40, 10, 1, 1, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE public_title = VALUES(public_title), short_title = VALUES(short_title), audience_label = VALUES(audience_label), estimated_minutes_min = VALUES(estimated_minutes_min), estimated_minutes_max = VALUES(estimated_minutes_max), free_report_label = VALUES(free_report_label), paid_report_label = VALUES(paid_report_label), question_count = 40, section_count = 10, show_remaining_time = 1, show_question_count = 1, show_section_count = 1, show_autosave = 1, introductory_note = VALUES(introductory_note), last_reviewed_date = VALUES(last_reviewed_date), intro_headline = VALUES(intro_headline), intro_body = VALUES(intro_body), intro_offer = VALUES(intro_offer), heart_label = VALUES(heart_label), heart_description = VALUES(heart_description), head_label = VALUES(head_label), head_description = VALUES(head_description), intake_configuration_json = VALUES(intake_configuration_json), allow_not_applicable = VALUES(allow_not_applicable), allow_answer_notes = VALUES(allow_answer_notes), updated_at = NOW()',
            [
                $trackId,
                'Growth Alignment: ' . $track['label'],
                $track['label'],
                $track['audienceLabel'],
                $track['durationMin'],
                $track['durationMax'],
                'Lite Report Free',
                'Full Report',
                'Approved assessment process imported from the attached index.html reference.',
                $metadata['reviewedDate'],
                $track['introHeadline'],
                $track['introBody'],
                $track['introOffer'],
                $track['heartLabel'],
                $track['heartDescription'],
                $track['headLabel'],
                $track['headDescription'],
                json_encode($track['intake'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                !empty($track['allowNotApplicable']) ? 1 : 0,
                !empty($track['allowAnswerNotes']) ? 1 : 0,
            ]
        );

        $referenceVersion = $container['db']->fetch(
            'SELECT id, status FROM assessment_versions WHERE track_id = ? AND version_number = ? LIMIT 1 FOR UPDATE',
            [$trackId, $versionNumber]
        );

        if ($referenceVersion) {
            if ($referenceVersion['status'] !== 'published') {
                throw new RuntimeException("Reference version {$versionNumber} already exists for {$key} but is not published.");
            }
            $versionId = (int) $referenceVersion['id'];
        } else {
            $currentPublished = $container['db']->fetch('SELECT id FROM assessment_versions WHERE track_id = ? AND status = ? ORDER BY published_at DESC, id DESC LIMIT 1 FOR UPDATE', [$trackId, 'published']);
            $versionId = $container['db']->insert(
                'INSERT INTO assessment_versions (track_id, version_number, status, cloned_from_id, change_summary, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                [$trackId, $versionNumber, 'draft', $currentPublished['id'] ?? null, 'Exact questionnaire, flow and report content imported from attached index.html SHA-256 ' . $metadata['sourceFileSha256']]
            );

            foreach ($track['answerChoices'] as $index => $label) {
                $container['db']->execute('INSERT INTO answer_options (assessment_version_id, option_value, label, display_order) VALUES (?, ?, ?, ?)', [$versionId, $index + 1, $label, $index + 1]);
            }

            $position = 1;
            foreach ($track['subscales'] as $sectionIndex => $section) {
                $sectionId = $container['db']->insert(
                    'INSERT INTO assessment_sections (assessment_version_id, code, name, description, display_order, is_active) VALUES (?, ?, ?, ?, ?, 1)',
                    [$versionId, $section['code'], $section['name'], $section['blurb'] ?? '', $sectionIndex + 1]
                );
                foreach ($section['items'] as $itemIndex => $item) {
                    $container['db']->execute(
                        'INSERT INTO questions (assessment_version_id, section_id, stable_key, question_text, scoring_direction, position, is_required, is_active) VALUES (?, ?, ?, ?, ?, ?, 1, 1)',
                        [$versionId, $sectionId, sprintf('%s-%02d', $section['code'], $itemIndex + 1), $item['t'], $item['d'], $position++]
                    );
                }
            }

            foreach ($track['profiles'] as $profile) {
                [$free, $paid] = reportPayload($track, $profile);
                $profileKey = (string) ($profile['key'] ?? strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $profile['name']), '-')));
                $container['db']->execute(
                    'INSERT INTO report_templates (assessment_version_id, profile_key, profile_name, min_score, max_score, free_content_json, paid_content_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $versionId,
                        $profileKey,
                        $profile['name'],
                        $profile['range'][0],
                        $profile['range'][1],
                        json_encode($free, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        json_encode($paid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    ]
                );
            }

            assertReferenceVersion($container['db'], $versionId, $track);
            $container['db']->execute('UPDATE assessment_versions SET status = ?, archived_at = NOW(), updated_at = NOW() WHERE track_id = ? AND status = ? AND id <> ?', ['archived', $trackId, 'published', $versionId]);
            $container['db']->execute('UPDATE assessment_versions SET status = ?, published_at = NOW(), archived_at = NULL, updated_at = NOW() WHERE id = ?', ['published', $versionId]);
            $container['db']->execute(
                'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (NULL, ?, ?, ?, ?, NOW())',
                [
                    'assessment.reference_imported',
                    'assessment_version',
                    (string) $versionId,
                    json_encode([
                        'trackKey' => $key,
                        'version' => $versionNumber,
                        'sourceFileSha256' => $metadata['sourceFileSha256'],
                        'questionnaireSha256' => $track['questionnaireSha256'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]
            );
        }

        assertReferenceVersion($container['db'], $versionId, $track);
    }

    $container['settings']->set('questionnaire.landing', $reference['landing']);
    $container['settings']->set(REFERENCE_SOURCE_HASH_KEY, $metadata['sourceFileSha256']);
    $container['settings']->set(REFERENCE_AGGREGATE_HASH_KEY, $metadata['questionnaireSha256']);
    $container['settings']->set(REFERENCE_MARKER_KEY, $versionNumber);
});

echo "Assessment reference {$versionNumber} imported and published for Personal, New Joiner, Manager and Executive.\n";
