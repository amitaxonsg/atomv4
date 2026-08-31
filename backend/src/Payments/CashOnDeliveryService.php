<?php
declare(strict_types=1);

namespace AtomGlobal\Payments;

use AtomGlobal\Database;
use AtomGlobal\Services\ReportService;
use AtomGlobal\Services\SettingsService;

final class CashOnDeliveryService
{
    public function __construct(
        private Database $db,
        private SettingsService $settings,
        private ReportService $reports,
        private array $config,
    ) {}

    public function enabled(): bool
    {
        // The system-level value is the administrator's explicit UAT override.
        // If it has never been saved, fall back to the migration-level payments default.
        $override = $this->settings->get('system.cash_on_delivery_enabled', null);
        $value = $override === null
            ? $this->settings->get('payments.cash_on_delivery_enabled', false)
            : $override;
        if (is_bool($value)) return $value;
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function checkout(int $sessionId, string $trackKey): array
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Cash on Delivery is not currently enabled.', 403);
        }

        $survey = $this->db->fetch(
            'SELECT s.id, s.status, s.affiliate_id, p.name, p.email, t.track_key, t.name track_name, t.price_minor, t.currency '
            . 'FROM survey_sessions s JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id '
            . 'WHERE s.id = ? AND t.track_key = ?',
            [$sessionId, $trackKey]
        );
        if (!$survey || $survey['status'] !== 'completed') {
            throw new \InvalidArgumentException('A completed assessment is required before checkout.');
        }

        $report = $this->db->fetch(
            'SELECT id, is_unlocked FROM generated_reports WHERE survey_session_id = ? AND revoked_at IS NULL',
            [$sessionId]
        );
        if (!$report) throw new \InvalidArgumentException('The report is not available for checkout.');

        $result = $this->db->transaction(function () use ($survey, $report, $sessionId) {
            $existing = $this->db->fetch(
                'SELECT id FROM payments WHERE survey_session_id = ? AND provider = ? AND status = ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$sessionId, 'cash_on_delivery', 'manual']
            );

            if (!$existing) {
                $paymentId = $this->db->insert(
                    'INSERT INTO payments (survey_session_id, affiliate_id, provider, status, amount, currency, metadata_json, paid_at, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
                    [
                        $sessionId,
                        $survey['affiliate_id'] ?: null,
                        'cash_on_delivery',
                        'manual',
                        (int) $survey['price_minor'],
                        strtoupper((string) $survey['currency']),
                        json_encode(['uat' => true, 'method' => 'cash_on_delivery']),
                    ]
                );
            } else {
                $paymentId = (int) $existing['id'];
            }

            $this->reports->unlockBySession($sessionId, 'cash_on_delivery_manual');
            $access = $this->rotateReportAccess((int) $report['id']);

            $variables = [
                'participantName' => $survey['name'],
                'trackName' => $survey['track_name'],
                'amount' => number_format(((int) $survey['price_minor']) / 100, 2),
                'currency' => strtoupper((string) $survey['currency']),
                'reportUrl' => $access['reportUrl'],
                'paidReportUrl' => $access['reportUrl'],
                'paymentMethod' => 'Cash on Delivery',
                'reportId' => $access['reportId'],
            ];
            $emailQueueIds = [
                $this->enqueue('payment_successful', (string) $survey['email'], $variables),
                $this->enqueue('paid_report_ready', (string) $survey['email'], $variables),
            ];

            $this->db->execute(
                'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (NULL, ?, ?, ?, ?, NOW())',
                ['payment.cash_on_delivery_uat', 'payment', (string) $paymentId, json_encode(['surveySessionId' => $sessionId])]
            );

            return ['paymentId' => $paymentId, 'emailQueueIds' => $emailQueueIds, ...$access];
        });

        return [
            ...$result,
            'method' => 'cash_on_delivery',
            'successUrl' => rtrim((string) $this->config['url'], '/') . '/payment/success?method=cash-on-delivery&report=' . rawurlencode($result['reportUrl']),
        ];
    }

    private function rotateReportAccess(int $reportId): array
    {
        $token = bin2hex(random_bytes(32));
        $days = max(1, (int) ($this->config['report_token_days'] ?? 30));
        $this->db->execute('UPDATE secure_report_tokens SET revoked_at = NOW() WHERE generated_report_id = ? AND revoked_at IS NULL', [$reportId]);
        $this->db->execute('INSERT INTO secure_report_tokens (generated_report_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())', [$reportId, hash('sha256', $token), $days]);
        $this->db->execute('UPDATE generated_reports SET secure_token_hash = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY), revoked_at = NULL, updated_at = NOW() WHERE id = ?', [hash('sha256', $token), $days, $reportId]);
        return [
            'reportId' => $reportId,
            'reportUrl' => rtrim((string) $this->config['url'], '/') . '/report/' . rawurlencode($token),
        ];
    }

    private function enqueue(string $template, string $recipient, array $variables): int
    {
        return $this->db->insert(
            'INSERT INTO email_queue (template_key, recipient_email, variables_json, status, attempts, scheduled_at, created_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())',
            [$template, strtolower($recipient), json_encode($variables), 'queued']
        );
    }
}
