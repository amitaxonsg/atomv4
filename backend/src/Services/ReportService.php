<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;

final class ReportService
{
    private const RETAKE_DEFAULTS = [
        'personal' => 299,
        'newjoiner' => 995,
        'manager' => 2995,
        'executive' => 4995,
    ];

    public function __construct(
        private Database $db,
        private SettingsService $settings,
        private array $config,
    ) {}

    public function generate(int $sessionId, int $scoreId, array $score, array $snapshot): array
    {
        $token = bin2hex(random_bytes(32));
        $profile = $score['profile'];
        $paidContent = json_decode((string) $profile['paid_content_json'], true, 512, JSON_THROW_ON_ERROR);
        $paidContent = V3ReportEnhancer::enrich($this->db, $sessionId, $score, $snapshot, $paidContent, $profile);
        $comparison = $this->retakeComparison($sessionId, $score);
        $isRetake = $comparison !== null;
        if ($comparison) $paidContent['retakeComparison'] = $comparison;
        $upgradePreview = $this->normaliseUpgradePreview($paidContent['upgradeReasons'] ?? []);

        $free = [
            'profile' => $profile['profile_name'],
            'total' => $score['total'],
            'summary' => json_decode((string) $profile['free_content_json'], true, 512, JSON_THROW_ON_ERROR),
            'subscales' => $score['subscales'],
            'upgradePreview' => $upgradePreview,
        ];
        $paid = [
            'profile' => $profile['profile_name'],
            'total' => $score['total'],
            'content' => $paidContent,
            'subscales' => $score['subscales'],
        ];
        $id = $this->db->insert(
            'INSERT INTO generated_reports (survey_session_id, score_snapshot_id, secure_token_hash, token_expires_at, is_unlocked, free_report_json, paid_report_json, created_at, updated_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), 0, ?, ?, NOW(), NOW())',
            [$sessionId, $scoreId, hash('sha256', $token), $this->config['report_token_days'], json_encode($free), json_encode($paid)]
        );
        if ($isRetake) {
            $this->db->execute(
                'UPDATE generated_reports SET is_unlocked = 1, unlocked_at = NOW(), unlock_reason = ?, updated_at = NOW() WHERE id = ?',
                ['retake_payment', $id]
            );
        }
        $this->db->execute(
            'INSERT INTO secure_report_tokens (generated_report_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())',
            [$id, hash('sha256', $token), $this->config['report_token_days']]
        );
        return ['id' => $id, 'token' => $token, 'isRetake' => $isRetake];
    }

    public function byToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $row = $this->db->fetch(
            'SELECT gr.id, gr.survey_session_id sessionId, gr.is_unlocked, gr.free_report_json, IF(gr.is_unlocked = 1, gr.paid_report_json, NULL) paid_report_json, gr.token_expires_at, (gr.is_unlocked = 1 AND gr.pdf_path IS NOT NULL) pdf_available, gr.pdf_generated_at, gr.view_count, s.completed_at completedAt, p.name participantName, p.email participantEmail, t.track_key trackKey, t.name trackName, t.price_minor priceMinor, t.currency FROM generated_reports gr JOIN survey_sessions s ON s.id = gr.survey_session_id JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id WHERE gr.secure_token_hash = ? AND gr.revoked_at IS NULL AND gr.token_expires_at > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
        if ($row) {
            $row['id'] = (int) $row['id'];
            $row['sessionId'] = (int) $row['sessionId'];
            $row['is_unlocked'] = (bool) $row['is_unlocked'];
            $row['pdf_available'] = (bool) $row['pdf_available'];
            $row['priceMinor'] = (int) $row['priceMinor'];
            $row['view_count'] = (int) $row['view_count'];
            $row['checkoutAvailable'] = $this->checkoutAvailable((string) $row['trackKey']);
            $row['checkoutStatus'] = $row['checkoutAvailable'] ? 'available' : 'not_configured';
            $row['cashOnDeliveryAvailable'] = $this->cashOnDeliveryAvailable();
            $row['retakePriceMinor'] = $this->retakePriceMinor((string) $row['trackKey']);
            $row['retakeCurrency'] = 'USD';
            $row['retakeCheckoutAvailable'] = $row['is_unlocked'] && $this->retakeCheckoutAvailable((int) $row['sessionId'], (string) $row['trackKey']);
            $row['retakeRecommendedAt'] = $this->recommendedRetakeAt((string) ($row['completedAt'] ?? ''));
            $row['commitment'] = $this->commitment((int) $row['id']);
            $row['reportExperience'] = $this->reportExperience();
            $this->db->execute('UPDATE generated_reports SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = ?', [$row['id']]);
        }
        return $row;
    }

    public function pdfByToken(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $row = $this->db->fetch(
            'SELECT pdf_path FROM generated_reports WHERE secure_token_hash = ? AND revoked_at IS NULL AND token_expires_at > NOW() AND is_unlocked = 1 AND pdf_path IS NOT NULL',
            [hash('sha256', $token)]
        );
        $path = $row['pdf_path'] ?? null;
        return $path && is_file($path) ? $path : null;
    }

    public function unlockBySession(int $sessionId, string $reason): void
    {
        $this->db->execute(
            'UPDATE generated_reports SET is_unlocked = 1, unlocked_at = NOW(), unlock_reason = ?, updated_at = NOW() WHERE survey_session_id = ?',
            [$reason, $sessionId]
        );
    }

    private function checkoutAvailable(string $trackKey): bool
    {
        $secret = trim((string) $this->settings->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? ''));
        $webhook = trim((string) $this->settings->get('stripe.webhook_secret', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''));
        $environmentKey = 'STRIPE_PRICE_' . strtoupper($trackKey);
        $price = trim((string) $this->settings->get('stripe.price_' . $trackKey, $_ENV[$environmentKey] ?? ''));
        return $secret !== '' && $webhook !== '' && $price !== '';
    }

    private function retakeCheckoutAvailable(int $sessionId, string $trackKey): bool
    {
        $secret = trim((string) $this->settings->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? ''));
        $webhook = trim((string) $this->settings->get('stripe.webhook_secret', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''));
        $price = trim((string) $this->settings->get('stripe.retest_price_' . $trackKey, ''));
        if ($secret === '' || $webhook === '' || $price === '') return false;
        $paid = $this->db->fetch(
            'SELECT pay.id FROM payments pay JOIN survey_sessions s ON s.id = pay.survey_session_id WHERE pay.survey_session_id = ? AND pay.provider = ? AND pay.status = ? AND s.completed_at <= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY pay.paid_at DESC, pay.id DESC LIMIT 1',
            [$sessionId, 'stripe', 'paid', $this->retakeWaitDays()]
        );
        return (bool) $paid;
    }

    public function saveCommitment(string $token, string $text): array
    {
        $report = $this->byToken($token);
        if (!$report || empty($report['is_unlocked'])) {
            throw new \InvalidArgumentException('A valid unlocked Full Development Report is required.');
        }
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) < 10 || mb_strlen($text) > 2000) {
            throw new \InvalidArgumentException('Write a commitment between 10 and 2,000 characters.');
        }
        $date = (new \DateTimeImmutable('today'))->modify('+' . $this->retakeWaitDays() . ' days')->format('Y-m-d');
        $this->db->execute(
            'INSERT INTO report_commitments (generated_report_id, commitment_text, check_in_date, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE commitment_text = VALUES(commitment_text), check_in_date = VALUES(check_in_date), updated_at = NOW()',
            [(int) $report['id'], $text, $date]
        );
        return ['text' => $text, 'checkInDate' => $date];
    }

    private function commitment(int $reportId): ?array
    {
        $row = $this->db->fetch('SELECT commitment_text text, check_in_date checkInDate, updated_at updatedAt FROM report_commitments WHERE generated_report_id = ? LIMIT 1', [$reportId]);
        return $row ?: null;
    }

    private function reportExperience(): array
    {
        return [
            'commitmentHeading' => (string) $this->settings->get('reports.commitment_heading', 'My 90-day development commitment'),
            'commitmentPrompt' => (string) $this->settings->get('reports.commitment_prompt', 'Choose one or two development areas and write down the action you will practise consistently.'),
            'coachHeading' => (string) $this->settings->get('reports.coach_heading', 'Talk to a Coach'),
            'coachBody' => (string) $this->settings->get('reports.coach_body', 'Turn your report into a focused development plan with an Atom Global coach.'),
            'coachPrimaryName' => (string) $this->settings->get('reports.coach_primary_name', 'Reeta Nathwani'),
            'coachPrimaryEmail' => (string) $this->settings->get('reports.coach_primary_email', 'reeta.nathwani@atomglobal.com'),
            'coachSecondaryName' => (string) $this->settings->get('reports.coach_secondary_name', 'Sunil Setpaul'),
            'coachSecondaryEmail' => (string) $this->settings->get('reports.coach_secondary_email', 'sunil.setpaul@atomglobal.com'),
            'paymentWording' => (string) $this->settings->get('reports.payment_wording', 'Secure payment unlocks your private Full Development Report.'),
            'paymentWordingLocation' => (string) $this->settings->get('reports.payment_wording_location', 'lite'),
        ];
    }

    private function retakeWaitDays(): int
    {
        return max(1, min(365, (int) $this->settings->get('retest.wait_days', 90)));
    }

    private function retakePriceMinor(string $trackKey): int
    {
        $default = self::RETAKE_DEFAULTS[$trackKey] ?? self::RETAKE_DEFAULTS['personal'];
        return max(0, (int) $this->settings->get('retest.price_' . $trackKey . '_minor', $default));
    }

    private function cashOnDeliveryAvailable(): bool
    {
        $override = $this->settings->get('system.cash_on_delivery_enabled', null);
        $value = $override === null
            ? $this->settings->get('payments.cash_on_delivery_enabled', false)
            : $override;

        if (is_bool($value)) return $value;
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function recommendedRetakeAt(string $completedAt): ?string
    {
        if ($completedAt === '') return null;
        try {
            return (new \DateTimeImmutable($completedAt))->modify('+' . $this->retakeWaitDays() . ' days')->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function retakeComparison(int $sessionId, array $score): ?array
    {
        $session = $this->db->fetch('SELECT attribution_snapshot_json FROM survey_sessions WHERE id = ? LIMIT 1', [$sessionId]);
        if (!$session) return null;
        $attribution = json_decode((string) ($session['attribution_snapshot_json'] ?? '{}'), true) ?: [];
        $sourceSessionId = (int) ($attribution['retakeOfSessionId'] ?? 0);
        if ($sourceSessionId < 1) return null;

        $previousReport = $this->db->fetch('SELECT free_report_json FROM generated_reports WHERE survey_session_id = ? ORDER BY id DESC LIMIT 1', [$sourceSessionId]);
        if (!$previousReport) return null;
        $previous = json_decode((string) $previousReport['free_report_json'], true) ?: [];
        $previousSubscales = is_array($previous['subscales'] ?? null) ? $previous['subscales'] : [];
        $currentSubscales = is_array($score['subscales'] ?? null) ? $score['subscales'] : [];
        $areas = [];
        foreach ($currentSubscales as $code => $current) {
            if (!array_key_exists($code, $previousSubscales)) continue;
            $before = (int) $previousSubscales[$code];
            $after = (int) $current;
            $areas[] = ['code' => (string) $code, 'previous' => $before, 'current' => $after, 'change' => $after - $before];
        }
        $previousTotal = (int) ($previous['total'] ?? 0);
        $currentTotal = (int) ($score['total'] ?? 0);
        return [
            'sourceSessionId' => $sourceSessionId,
            'previousTotal' => $previousTotal,
            'currentTotal' => $currentTotal,
            'totalChange' => $currentTotal - $previousTotal,
            'areas' => $areas,
            'guidance' => 'Use the comparison to notice meaningful movement, stable strengths, and patterns that return under pressure. A small change is still useful when it reflects a deliberate habit practised consistently.',
        ];
    }

    private function normaliseUpgradePreview(mixed $items): array
    {
        if (!is_array($items)) return [];
        $preview = [];
        foreach ($items as $item) {
            if (is_string($item) && trim($item) !== '') {
                $preview[] = ['title' => trim($item), 'detail' => ''];
                continue;
            }
            if (!is_array($item)) continue;
            $title = trim((string) ($item['title'] ?? $item['area'] ?? ''));
            $detail = trim((string) ($item['detail'] ?? $item['summary'] ?? $item['insight'] ?? ''));
            if ($title !== '' || $detail !== '') $preview[] = ['title' => $title, 'detail' => $detail];
        }
        return array_slice($preview, 0, 8);
    }
}
