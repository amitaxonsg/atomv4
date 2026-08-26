<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;

final class AssessmentExperienceService
{
    private const LANDING_KEY = 'questionnaire.landing';
    private const LIVE_TRACK_KEY = 'questionnaire.live_track';
    private const V3_PUBLIC_QUESTION_COUNT = 40;
    private const V3_PUBLIC_SECTION_COUNT = 10;

    public function __construct(private Database $db, private SettingsService $settings) {}

    public function publicConfiguration(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.id trackId, t.track_key trackKey, t.name trackName, t.description tagline, t.price_minor priceMinor, t.currency, '
            . 's.intro_headline introHeadline, s.intro_body introBody, s.intro_offer introOffer, s.heart_label heartLabel, '
            . 's.heart_description heartDescription, s.head_label headLabel, s.head_description headDescription, '
            . 's.intake_configuration_json intakeConfiguration, s.allow_not_applicable allowNotApplicable, '
            . 's.allow_answer_notes allowAnswerNotes, s.question_count questionCount, s.section_count sectionCount '
            . 'FROM assessment_tracks t LEFT JOIN assessment_track_settings s ON s.track_id = t.id '
            . 'WHERE t.is_active = 1 ORDER BY t.display_order'
        );

        $tracks = [];
        foreach ($rows as $row) {
            $tracks[$row['trackKey']] = $this->normalise($row);
        }
        return [
            'landing' => $this->landing(),
            'liveTrackKey' => $this->liveTrackKey(),
            'tracks' => $tracks,
        ];
    }

    public function administrationConfiguration(): array
    {
        return $this->publicConfiguration();
    }

    public function saveLanding(array $payload, int $adminId): array
    {
        $landing = [
            'title' => $this->text($payload['title'] ?? '', 200),
            'primaryCopy' => $this->text($payload['primaryCopy'] ?? '', 5000),
            'secondaryCopy' => $this->text($payload['secondaryCopy'] ?? '', 5000),
            'cardTitlePrefix' => $this->text($payload['cardTitlePrefix'] ?? 'Growth Alignment:', 100),
            'showBrandName' => !array_key_exists('showBrandName', $payload) || !empty($payload['showBrandName']),
            'hideSectionTitles' => !array_key_exists('hideSectionTitles', $payload) || !empty($payload['hideSectionTitles']),
            'halfwayTitle' => $this->text($payload['halfwayTitle'] ?? 'Halfway there — 20 of 40 complete.', 255),
            'halfwayBody' => $this->text($payload['halfwayBody'] ?? 'Keep answering honestly; the value comes from the pattern, not any single response.', 1000),
            'completeTitle' => $this->text($payload['completeTitle'] ?? 'All 40 questions complete — well done.', 255),
            'completeBody' => $this->text($payload['completeBody'] ?? 'Your responses are ready. You can review this section or continue to your result.', 1000),
        ];
        if ($landing['title'] === '' || $landing['primaryCopy'] === '' || $landing['secondaryCopy'] === '') {
            throw new \InvalidArgumentException('Landing title and both introduction paragraphs are required.');
        }
        $this->settings->set(self::LANDING_KEY, $landing);
        $this->db->execute(
            'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$adminId, 'questionnaire_landing.saved', 'global_setting', self::LANDING_KEY, json_encode($landing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );
        return $landing;
    }

    public function saveLiveTrack(string $trackKey, int $adminId): array
    {
        $trackKey = strtolower(trim($trackKey));
        $track = $this->db->fetch(
            'SELECT t.id, t.track_key trackKey, t.name trackName, v.id versionId, v.version_number versionNumber, '
            . 'COUNT(DISTINCT q.id) questionCount, COUNT(DISTINCT s.id) sectionCount '
            . 'FROM assessment_tracks t '
            . 'JOIN assessment_versions v ON v.track_id = t.id AND v.status = ? '
            . 'LEFT JOIN questions q ON q.assessment_version_id = v.id AND q.is_active = 1 '
            . 'LEFT JOIN assessment_sections s ON s.assessment_version_id = v.id AND s.is_active = 1 '
            . 'WHERE t.track_key = ? AND t.is_active = 1 GROUP BY t.id, v.id LIMIT 1',
            ['published', $trackKey]
        );
        if (!$track) throw new \InvalidArgumentException('Choose an active assessment with a published version.');
        if ((int) ($track['questionCount'] ?? 0) !== 50 || (int) ($track['sectionCount'] ?? 0) !== 10) {
            throw new \InvalidArgumentException('The source bank must contain exactly 50 active questions across 10 active sections before the V3 40-question public projection can be used.');
        }

        $before = $this->liveTrackKey();
        $this->settings->set(self::LIVE_TRACK_KEY, $trackKey);
        $this->db->execute(
            'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, before_json, after_json, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $adminId,
                'assessment.live_track_changed',
                'assessment_track',
                (string) $track['id'],
                json_encode(['liveTrackKey' => $before], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode([
                    'liveTrackKey' => $trackKey,
                    'trackName' => $track['trackName'],
                    'versionId' => (int) $track['versionId'],
                    'versionNumber' => $track['versionNumber'],
                    'sourceQuestionCount' => 50,
                    'publicQuestionCount' => self::V3_PUBLIC_QUESTION_COUNT,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );

        return [
            'liveTrackKey' => $trackKey,
            'trackName' => $track['trackName'],
            'versionId' => (int) $track['versionId'],
            'versionNumber' => $track['versionNumber'],
            'publicQuestionCount' => self::V3_PUBLIC_QUESTION_COUNT,
        ];
    }

    public function liveTrackKey(): string
    {
        $configured = strtolower(trim((string) $this->settings->get(self::LIVE_TRACK_KEY, 'personal')));
        $available = $this->db->fetch(
            'SELECT t.track_key FROM assessment_tracks t JOIN assessment_versions v ON v.track_id = t.id AND v.status = ? '
            . 'WHERE t.track_key = ? AND t.is_active = 1 LIMIT 1',
            ['published', $configured]
        );
        if ($available) return $configured;

        $fallback = $this->db->fetch(
            'SELECT t.track_key FROM assessment_tracks t JOIN assessment_versions v ON v.track_id = t.id AND v.status = ? '
            . 'WHERE t.is_active = 1 ORDER BY t.display_order LIMIT 1',
            ['published']
        );
        return (string) ($fallback['track_key'] ?? 'personal');
    }

    public function byTrackId(int $trackId): array
    {
        $row = $this->db->fetch(
            'SELECT t.id trackId, t.track_key trackKey, t.name trackName, t.description tagline, t.price_minor priceMinor, t.currency, '
            . 's.intro_headline introHeadline, s.intro_body introBody, s.intro_offer introOffer, s.heart_label heartLabel, '
            . 's.heart_description heartDescription, s.head_label headLabel, s.head_description headDescription, '
            . 's.intake_configuration_json intakeConfiguration, s.allow_not_applicable allowNotApplicable, '
            . 's.allow_answer_notes allowAnswerNotes, s.question_count questionCount, s.section_count sectionCount '
            . 'FROM assessment_tracks t LEFT JOIN assessment_track_settings s ON s.track_id = t.id WHERE t.id = ? LIMIT 1',
            [$trackId]
        );
        if (!$row) throw new \RuntimeException('Assessment track not found.', 404);
        return $this->normalise($row);
    }

    public function save(int $trackId, array $payload, int $adminId): array
    {
        $track = $this->db->fetch('SELECT id, track_key, name, description FROM assessment_tracks WHERE id = ?', [$trackId]);
        if (!$track) throw new \RuntimeException('Assessment track not found.', 404);

        $intake = $this->validateIntake($payload['intake'] ?? null);
        $values = [
            'tagline' => $this->text($payload['tagline'] ?? $track['description'] ?? '', 500),
            'introHeadline' => $this->text($payload['introHeadline'] ?? ('Growth Alignment: ' . $track['name']), 255),
            'introBody' => $this->text($payload['introBody'] ?? '', 5000),
            'introOffer' => $this->text($payload['introOffer'] ?? '', 5000),
            'heartLabel' => $this->text($payload['heartLabel'] ?? 'Heart', 100) ?: 'Heart',
            'heartDescription' => $this->text($payload['heartDescription'] ?? 'Feeling, intuition, connection, meaning', 255),
            'headLabel' => $this->text($payload['headLabel'] ?? 'Head', 100) ?: 'Head',
            'headDescription' => $this->text($payload['headDescription'] ?? 'Logic, analysis, control, proof', 255),
            'intake' => $intake,
            'allowNotApplicable' => !empty($payload['allowNotApplicable']),
            'allowAnswerNotes' => !empty($payload['allowAnswerNotes']),
        ];

        if ($values['tagline'] === '') throw new \InvalidArgumentException('Track card description is required.');
        $this->db->execute('UPDATE assessment_tracks SET description = ?, updated_at = NOW() WHERE id = ?', [$values['tagline'], $trackId]);
        $this->db->execute(
            'INSERT INTO assessment_track_settings '
            . '(track_id, public_title, short_title, estimated_minutes_min, estimated_minutes_max, free_report_label, paid_report_label, question_count, section_count, show_remaining_time, show_question_count, show_section_count, show_autosave, intro_headline, intro_body, intro_offer, heart_label, heart_description, head_label, head_description, intake_configuration_json, allow_not_applicable, allow_answer_notes, updated_at) '
            . 'VALUES (?, ?, ?, 15, 15, ?, ?, 40, 10, 1, 1, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE question_count = 40, section_count = 10, intro_headline = VALUES(intro_headline), intro_body = VALUES(intro_body), intro_offer = VALUES(intro_offer), '
            . 'heart_label = VALUES(heart_label), heart_description = VALUES(heart_description), head_label = VALUES(head_label), '
            . 'head_description = VALUES(head_description), intake_configuration_json = VALUES(intake_configuration_json), '
            . 'allow_not_applicable = VALUES(allow_not_applicable), allow_answer_notes = VALUES(allow_answer_notes), updated_at = NOW()',
            [
                $trackId,
                'Growth Alignment: ' . $track['name'],
                $track['name'],
                'Lite Report Free',
                'Full Report',
                $values['introHeadline'] ?: null,
                $values['introBody'] ?: null,
                str_replace('50-question', '40-question', $values['introOffer']) ?: null,
                $values['heartLabel'],
                $values['heartDescription'],
                $values['headLabel'],
                $values['headDescription'],
                json_encode($values['intake'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $values['allowNotApplicable'] ? 1 : 0,
                $values['allowAnswerNotes'] ? 1 : 0,
            ]
        );

        $this->db->execute(
            'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$adminId, 'assessment_experience.saved', 'assessment_track', (string) $trackId, json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]
        );

        return $this->byTrackId($trackId);
    }

    private function landing(): array
    {
        $defaults = [
            'title' => 'Growth Alignment',
            'primaryCopy' => 'Every choice you make is cast by two votes: what you feel and what you reason. This assessment maps which one you actually hand the deciding vote to — not which one you wish you did.',
            'secondaryCopy' => "You'll answer 40 statements across 10 areas of life, get an instant free result, and can unlock a full in-depth report. Choose the version that fits you:",
            'cardTitlePrefix' => 'Growth Alignment:',
            'showBrandName' => true,
            'hideSectionTitles' => true,
            'halfwayTitle' => 'Halfway there — 20 of 40 complete.',
            'halfwayBody' => 'Keep answering honestly; the value comes from the pattern, not any single response.',
            'completeTitle' => 'All 40 questions complete — well done.',
            'completeBody' => 'Your responses are ready. You can review this section or continue to your result.',
        ];
        $stored = $this->settings->get(self::LANDING_KEY, []);
        return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
    }

    private function normalise(array $row): array
    {
        $intake = json_decode((string) ($row['intakeConfiguration'] ?? ''), true);
        return [
            'trackId' => (int) $row['trackId'],
            'trackKey' => (string) $row['trackKey'],
            'trackName' => (string) $row['trackName'],
            'tagline' => (string) ($row['tagline'] ?? ''),
            'priceMinor' => (int) ($row['priceMinor'] ?? 0),
            'currency' => (string) ($row['currency'] ?? 'USD'),
            'questionCount' => (int) ($row['questionCount'] ?? self::V3_PUBLIC_QUESTION_COUNT),
            'sectionCount' => (int) ($row['sectionCount'] ?? self::V3_PUBLIC_SECTION_COUNT),
            'introHeadline' => (string) ($row['introHeadline'] ?? ''),
            'introBody' => (string) ($row['introBody'] ?? ''),
            'introOffer' => (string) ($row['introOffer'] ?? ''),
            'heartLabel' => (string) ($row['heartLabel'] ?? 'Heart'),
            'heartDescription' => (string) ($row['heartDescription'] ?? 'Feeling, intuition, connection, meaning'),
            'headLabel' => (string) ($row['headLabel'] ?? 'Head'),
            'headDescription' => (string) ($row['headDescription'] ?? 'Logic, analysis, control, proof'),
            'intake' => is_array($intake) ? $intake : null,
            'allowNotApplicable' => (bool) ($row['allowNotApplicable'] ?? true),
            'allowAnswerNotes' => (bool) ($row['allowAnswerNotes'] ?? true),
        ];
    }

    private function validateIntake(mixed $value): array
    {
        if (!is_array($value)) throw new \InvalidArgumentException('The participant intake configuration must be an object.');
        $result = [];
        foreach (['who', 'what', 'where', 'why', 'how'] as $prefix) {
            $labelKey = $prefix . 'Label';
            $optionsKey = $prefix . 'Options';
            $label = $this->text($value[$labelKey] ?? '', 255);
            $options = $this->options($value[$optionsKey] ?? null);
            if ($label === '' || count($options) < 2) {
                throw new \InvalidArgumentException('Each intake field requires a label and at least two options.');
            }
            $result[$labelKey] = $label;
            $result[$optionsKey] = $options;
        }

        $result['hasCompanyFields'] = !empty($value['hasCompanyFields']);
        if ($result['hasCompanyFields']) {
            foreach (['department', 'level'] as $prefix) {
                $labelKey = $prefix . 'Label';
                $optionsKey = $prefix . 'Options';
                $label = $this->text($value[$labelKey] ?? '', 255);
                $options = $this->options($value[$optionsKey] ?? null);
                if ($label === '' || count($options) < 2) {
                    throw new \InvalidArgumentException('Department and level fields require labels and at least two options.');
                }
                $result[$labelKey] = $label;
                $result[$optionsKey] = $options;
            }
            $triggers = $this->options($value['companyRoleTriggers'] ?? null, 20);
            if (!$triggers) throw new \InvalidArgumentException('At least one role must trigger the company fields.');
            $result['companyRoleTriggers'] = $triggers;
        }
        return $result;
    }

    private function options(mixed $value, int $limit = 300): array
    {
        $options = is_array($value) ? $value : [];
        return array_slice(array_values(array_filter(array_map(fn($item) => $this->text($item, 200), $options))), 0, $limit);
    }

    private function text(mixed $value, int $limit): string
    {
        return mb_substr(trim((string) $value), 0, $limit);
    }
}
