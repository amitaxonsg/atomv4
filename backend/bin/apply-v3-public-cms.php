#!/usr/bin/env php
<?php
declare(strict_types=1);

$container = require dirname(__DIR__) . '/src/bootstrap.php';
$db = $container['db'];
$settings = $container['settings'];

const V3_PUBLIC_QUESTION_COUNT = 40;
const V3_PUBLIC_SECTION_COUNT = 10;
const V3_STAGE_IMAGE = '/media/stages/sunil-head-heart-v3.webp';
const V3_STAGE_ALT = 'A translucent blue head with a heart representing Growth Alignment';

$areaNames = [
    'personal' => [
        'DM' => 'Decision-Making',
        'RC' => 'Relationships & Connection',
        'EA' => 'Emotional Awareness',
        'CN' => 'Conflict Navigation',
        'TI' => 'Trust & Intuition',
        'EC' => 'Empathy & Compassion',
        'AE' => 'Authentic Self-Expression',
        'SP' => 'Stress & Pressure Response',
        'VP' => 'Values & Life Priorities',
        'CS' => 'Communication Style',
    ],
    'newjoiner' => [
        'DM' => 'Decision-Making as You Start Out',
        'RC' => 'Building Relationships at a New Job',
        'EA' => 'Emotional Awareness in a New Environment',
        'CN' => 'Handling Feedback & Early Conflict',
        'TI' => 'Trust & Intuition as a Newcomer',
        'EC' => 'Empathy for Your New Team',
        'AE' => 'Authentic Presence as the New Person',
        'SP' => 'Pressure & Imposter Moments',
        'VP' => 'What You’re Optimizing For Early On',
        'CS' => 'Communication as a New Team Member',
    ],
    'manager' => [
        'DM' => 'Decision-Making',
        'RC' => 'Team Relationships & Trust',
        'EA' => 'Emotional Awareness at Work',
        'CN' => 'Conflict & Difficult Conversations',
        'TI' => 'Trust & Intuition About People',
        'EC' => 'Empathy for Your Team',
        'AE' => 'Authentic Leadership',
        'SP' => 'Stress & Pressure at Work',
        'VP' => 'What You’re Optimizing For',
        'CS' => 'Communication as a Manager',
    ],
    'executive' => [
        'DM' => 'Strategic Decision-Making',
        'RC' => 'Executive Trust & Relationships',
        'EA' => 'Emotional Awareness in the C-Suite',
        'CN' => 'High-Stakes Conflict & Negotiation',
        'TI' => 'Trust & Intuition on Big Bets',
        'EC' => 'Empathy at Scale',
        'AE' => 'Authentic Executive Presence',
        'SP' => 'Pressure at the Top',
        'VP' => 'What You’re Building For',
        'CS' => 'Communication as an Executive',
    ],
];

$prices = [
    'personal' => 499,
    'newjoiner' => 2900,
    'manager' => 4900,
    'executive' => 9900,
];

$priceLabels = [
    'personal' => '$4.99',
    'newjoiner' => '$29',
    'manager' => '$49',
    'executive' => '$99',
];

$db->transaction(function () use ($db, $settings, $areaNames, $prices, $priceLabels): void {
    $tracks = $db->fetchAll('SELECT id, track_key FROM assessment_tracks WHERE track_key IN (?, ?, ?, ?)', ['personal', 'newjoiner', 'manager', 'executive']);
    foreach ($tracks as $track) {
        $trackId = (int) $track['id'];
        $trackKey = (string) $track['track_key'];

        $db->execute(
            'UPDATE assessment_tracks SET price_minor = ?, currency = ?, updated_at = NOW() WHERE id = ?',
            [(int) ($prices[$trackKey] ?? 0), 'USD', $trackId]
        );
        $db->execute(
            'UPDATE assessment_track_settings SET question_count = ?, section_count = ?, updated_at = NOW() WHERE track_id = ?',
            [V3_PUBLIC_QUESTION_COUNT, V3_PUBLIC_SECTION_COUNT, $trackId]
        );
        $db->execute(
            "UPDATE assessment_track_settings SET intro_offer = REPLACE(intro_offer, '50-question', '40-question'), updated_at = NOW() WHERE track_id = ? AND intro_offer LIKE '%50-question%'",
            [$trackId]
        );

        $settingsRow = $db->fetch('SELECT intro_offer FROM assessment_track_settings WHERE track_id = ? LIMIT 1', [$trackId]);
        $offer = (string) ($settingsRow['intro_offer'] ?? '');
        if ($offer !== '') {
            if ($trackKey === 'newjoiner') $offer = str_replace('$19', $priceLabels[$trackKey], $offer);
            if ($trackKey === 'manager') $offer = str_replace('$29', $priceLabels[$trackKey], $offer);
            $db->execute('UPDATE assessment_track_settings SET intro_offer = ?, updated_at = NOW() WHERE track_id = ?', [$offer, $trackId]);
        }

        $published = $db->fetch('SELECT id FROM assessment_versions WHERE track_id = ? AND status = ? ORDER BY published_at DESC, id DESC LIMIT 1', [$trackId, 'published']);
        if (!$published) continue;
        $versionId = (int) $published['id'];
        foreach ($areaNames[$trackKey] ?? [] as $code => $name) {
            $db->execute(
                'UPDATE assessment_sections SET name = ? WHERE assessment_version_id = ? AND code = ?',
                [$name, $versionId, $code]
            );
        }
    }

    $landing = $settings->get('questionnaire.landing', []);
    if (!is_array($landing)) $landing = [];
    $secondary = (string) ($landing['secondaryCopy'] ?? "You'll answer 40 statements across 10 areas of life, get an instant free result, and can unlock a full in-depth report. Choose the version that fits you:");
    $secondary = str_replace(['50 statements', '50-question'], ['40 statements', '40-question'], $secondary);
    $landing['secondaryCopy'] = $secondary;
    $landing['halfwayTitle'] = (string) ($landing['halfwayTitle'] ?? 'Halfway there — 20 of 40 complete.');
    $landing['halfwayBody'] = (string) ($landing['halfwayBody'] ?? 'Keep answering honestly; the value comes from the pattern, not any single response.');
    $landing['completeTitle'] = (string) ($landing['completeTitle'] ?? 'All 40 questions complete — well done.');
    $landing['completeBody'] = (string) ($landing['completeBody'] ?? 'Your responses are ready. You can review this section or continue to your result.');
    $landing['hideSectionTitles'] = true;
    $settings->set('questionnaire.landing', $landing);

    // content_stages stores media-library foreign keys, not raw image URL columns.
    // Register/reuse the approved repository-backed V3 visual in the CMS media table,
    // then point every public V3 stage at that CMS media record.
    $media = $db->fetch('SELECT id FROM media_library WHERE storage_path = ? ORDER BY id DESC LIMIT 1', [V3_STAGE_IMAGE]);
    if (!$media) {
        $assetPath = dirname(__DIR__, 2) . '/public' . V3_STAGE_IMAGE;
        if (!is_file($assetPath)) {
            throw new RuntimeException('Approved V3 stage image is missing: ' . $assetPath);
        }
        $dimensions = @getimagesize($assetPath) ?: [null, null];
        $mediaId = $db->insert(
            'INSERT INTO media_library (file_name, storage_path, mime_type, file_size, width, height, alt_text, focal_x, focal_y, variants_json, uploaded_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 50, 50, NULL, NULL, NOW(), NOW())',
            [basename(V3_STAGE_IMAGE), V3_STAGE_IMAGE, 'image/webp', filesize($assetPath), $dimensions[0] ?: null, $dimensions[1] ?: null, V3_STAGE_ALT]
        );
    } else {
        $mediaId = (int) $media['id'];
        $db->execute(
            'UPDATE media_library SET alt_text = ?, focal_x = 50, focal_y = 50, updated_at = NOW() WHERE id = ?',
            [V3_STAGE_ALT, $mediaId]
        );
    }

    $stageKeys = ['version', 'participant', 'personal', 'newjoiner', 'manager', 'executive', 'report'];
    foreach ($stageKeys as $stageKey) {
        $db->execute(
            'UPDATE content_stages SET desktop_media_id = ?, mobile_media_id = NULL, image_alt = ?, focal_x = 50, focal_y = 50, overlay_strength = 0, updated_at = NOW() WHERE stage_key = ?',
            [$mediaId, V3_STAGE_ALT, $stageKey]
        );
    }
    $db->execute(
        'UPDATE content_stages SET supporting_text = ?, updated_at = NOW() WHERE stage_key = ?',
        ['Align with what you feel and what you reason with.', 'version']
    );
});

echo "V3 public CMS normalised: Sunil 40-question scope, exact area names, approved prices, CMS stage media/opening copy and hidden topic titles applied.\n";
