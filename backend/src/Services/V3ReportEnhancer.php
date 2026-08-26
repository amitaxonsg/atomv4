<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;

final class V3ReportEnhancer
{
    public static function enrich(Database $db, int $sessionId, array $score, array $snapshot, array $paidContent, array $profile): array
    {
        $session = $db->fetch('SELECT t.track_key FROM survey_sessions s JOIN assessment_tracks t ON t.id = s.track_id WHERE s.id = ? LIMIT 1', [$sessionId]);
        $trackKey = (string) ($session['track_key'] ?? 'personal');
        self::applyTrackSections($paidContent, $trackKey);
        $paidContent['sharpestEdge'] = self::edge($score['subscales'] ?? [], true);
        $paidContent['growthEdge'] = self::edge($score['subscales'] ?? [], false);
        $paidContent['radarLegend'] = 'Each spoke represents one of the 10 Head–Heart areas. Scores run from 5 to 25. A higher score means the Heart-oriented responses were more prominent in that area; a lower score means the Head-oriented responses were more prominent. Read the shape as a pattern across areas rather than treating any single score as good or bad.';
        $paidContent['profileSpectrum'] = self::profileSpectrum($snapshot['profiles'] ?? [], (string) ($profile['profile_key'] ?? ''));
        $paidContent['writtenReflections'] = self::writtenReflections($db, $sessionId, $snapshot['questions'] ?? []);
        $paidContent['methodology'] = [
            'Instrument' => 'This Full Development Report is generated from the published Atom Global Growth Alignment assessment version saved with the participant session. The participant experience uses 40 statements across 10 areas, with four scored statements per area.',
            'Scoring' => 'Each scored response uses a 1–5 scale. Items keyed toward the Head direction are reverse-scored using 6 minus the response. N/A responses are excluded rather than treated as a midpoint.',
            'Overall score' => 'The overall score is the mean of all scored responses multiplied by 50, producing a 50–250 range. The published profile band for that assessment version determines the participant profile.',
            'Area scores' => 'Each of the 10 area scores is the mean of its scored responses multiplied by 5, producing a 5–25 range. The radar and deep-dive sections show how the overall pattern changes by context.',
            'Interpretation' => 'The assessment is a developmental reflection tool, not a clinical, diagnostic or aptitude test. Results should be interpreted alongside context, lived experience and the participant’s own written reflections.',
            'Source and audit trail' => 'Question wording, scoring direction, answer choices, profile bands and report content are taken from the published assessment version and immutable question snapshot stored with this session, providing an auditable source for the generated result.',
        ];
        return $paidContent;
    }

    private static function applyTrackSections(array &$paidContent, string $trackKey): void
    {
        if ($trackKey === 'personal') {
            unset($paidContent['leadershipImpact'], $paidContent['cultureFitPrompt']);
            unset($paidContent['leadershipImpactLabel'], $paidContent['cultureFitLabel']);
            return;
        }
        if ($trackKey === 'newjoiner') {
            $paidContent['leadershipImpactLabel'] = 'How You’re Coming Across';
            $paidContent['cultureFitLabel'] = 'Culture fit reflection';
            return;
        }
        $paidContent['leadershipImpactLabel'] = 'Leadership impact';
        $paidContent['cultureFitLabel'] = 'Culture fit reflection';
    }

    private static function edge(array $subscales, bool $highest): ?array
    {
        if (!$subscales) return null;
        $values = array_filter($subscales, static fn($value): bool => is_numeric($value));
        if (!$values) return null;
        $target = $highest ? max($values) : min($values);
        $code = array_key_first(array_filter($values, static fn($value): bool => (int) $value === (int) $target));
        if ($code === null) return null;
        return [
            'code' => (string) $code,
            'score' => (int) $target,
            'meaning' => $highest
                ? 'This is the area where your Heart-oriented responses are most pronounced relative to your other areas. Treat it as a distinctive strength or default pattern to use deliberately.'
                : 'This is the area where your Head-oriented responses are most pronounced relative to your other areas. Treat it as a development edge to explore rather than a weakness label.',
        ];
    }

    private static function profileSpectrum(array $profiles, string $currentProfileKey): array
    {
        $result = [];
        foreach ($profiles as $item) {
            if (!is_array($item)) continue;
            $free = json_decode((string) ($item['free_content_json'] ?? '{}'), true) ?: [];
            $summary = trim((string) ($free['summary'] ?? ''));
            $key = (string) ($item['profile_key'] ?? '');
            $result[] = [
                'key' => $key,
                'name' => (string) ($item['profile_name'] ?? $key),
                'min' => (int) ($item['min_score'] ?? 0),
                'max' => (int) ($item['max_score'] ?? 0),
                'summary' => $summary,
                'current' => $key !== '' && $key === $currentProfileKey,
            ];
        }
        usort($result, static fn(array $a, array $b): int => $a['min'] <=> $b['min']);
        return $result;
    }

    private static function writtenReflections(Database $db, int $sessionId, array $questions): array
    {
        $questionMap = [];
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $position = (int) ($question['position'] ?? 0);
            if ($position > 0) $questionMap[$position] = (string) ($question['question_text'] ?? $question['text'] ?? '');
        }

        $rows = $db->fetchAll(
            'SELECT question_position, note FROM survey_answers WHERE survey_session_id = ? AND note IS NOT NULL AND TRIM(note) <> ? ORDER BY question_position',
            [$sessionId, '']
        );
        $result = [];
        foreach ($rows as $row) {
            $position = (int) ($row['question_position'] ?? 0);
            $note = trim((string) ($row['note'] ?? ''));
            if ($position < 1 || $note === '') continue;
            $result[] = [
                'questionPosition' => $position,
                'question' => $questionMap[$position] ?? ('Question ' . $position),
                'reflection' => $note,
            ];
        }
        return $result;
    }
}
