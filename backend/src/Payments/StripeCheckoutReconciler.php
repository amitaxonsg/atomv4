<?php
declare(strict_types=1);

namespace AtomGlobal\Payments;

use AtomGlobal\Database;
use AtomGlobal\Services\ReportService;
use AtomGlobal\Services\SettingsService;
use Stripe\StripeClient;

final class StripeCheckoutReconciler
{
    public function __construct(
        private Database $db,
        private SettingsService $settings,
        private ReportService $reports,
        private array $config,
    ) {}

    public function reconcile(string $checkoutId): void
    {
        if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $checkoutId)) {
            throw new \InvalidArgumentException('Checkout reference is invalid.');
        }

        $payment = $this->db->fetch(
            'SELECT * FROM payments WHERE provider = ? AND stripe_checkout_session_id = ? LIMIT 1',
            ['stripe', $checkoutId]
        );
        if (!$payment) throw new \RuntimeException('Stripe checkout payment record was not found.', 404);

        if ($payment['status'] === 'paid') {
            $this->db->transaction(fn() => $this->ensureFulfilment((int) $payment['id']));
            return;
        }
        if (!in_array($payment['status'], ['checkout_started'], true)) return;

        $secret = trim((string) $this->settings->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? ''));
        if ($secret === '') throw new \RuntimeException('Stripe credentials are not configured.');

        $stripe = new StripeClient($secret);
        $checkout = $stripe->checkout->sessions->retrieve($checkoutId, []);
        if ((string) ($checkout->id ?? '') !== $checkoutId) {
            throw new \RuntimeException('Stripe checkout verification returned an unexpected reference.');
        }
        if ((string) ($checkout->payment_status ?? '') !== 'paid') return;

        $sessionId = (int) ($checkout->metadata->survey_session_id ?? 0);
        if ($sessionId < 1 || $sessionId !== (int) $payment['survey_session_id']) {
            throw new \RuntimeException('Stripe checkout metadata does not match the assessment payment record.');
        }
        if ((string) ($checkout->metadata->payment_purpose ?? 'full_report') !== 'full_report') return;

        $this->db->transaction(function () use ($payment, $checkout): void {
            $current = $this->db->fetch('SELECT * FROM payments WHERE id = ? FOR UPDATE', [(int) $payment['id']]);
            if (!$current) throw new \RuntimeException('Payment disappeared during reconciliation.');

            if ($current['status'] !== 'paid') {
                $amount = (int) ($checkout->amount_total ?? 0);
                $currency = strtoupper((string) ($checkout->currency ?? 'USD'));
                $this->db->execute(
                    'UPDATE payments SET status = ?, stripe_payment_intent_id = ?, stripe_customer_id = ?, amount = ?, currency = ?, paid_at = COALESCE(paid_at, NOW()), updated_at = NOW() WHERE id = ?',
                    ['paid', $checkout->payment_intent ?? null, $checkout->customer ?? null, $amount, $currency, (int) $current['id']]
                );
            }

            $this->ensureFulfilment((int) $current['id']);
        });
    }

    private function ensureFulfilment(int $paymentId): void
    {
        $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if (!$payment || $payment['status'] !== 'paid') return;

        $sessionId = (int) $payment['survey_session_id'];
        $metadata = json_decode((string) ($payment['metadata_json'] ?? '{}'), true) ?: [];
        $reportUrl = trim((string) ($metadata['reportUrl'] ?? ''));
        $reportId = (int) ($metadata['reportId'] ?? 0);

        if ($reportUrl === '' || $reportId < 1) {
            $this->reports->unlockBySession($sessionId, 'stripe_reconciliation');
            $access = $this->rotateReportAccess($sessionId);
            $reportUrl = $access['reportUrl'];
            $reportId = $access['reportId'];
            $metadata['reportUrl'] = $reportUrl;
            $metadata['reportId'] = $reportId;
            $metadata['reportReadyAt'] = gmdate(DATE_ATOM);
            $metadata['reconciled'] = true;
            $this->db->execute(
                'UPDATE payments SET metadata_json = ?, updated_at = NOW() WHERE id = ?',
                [json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $paymentId]
            );
        } else {
            $this->reports->unlockBySession($sessionId, 'stripe_webhook');
        }

        $this->db->execute(
            'UPDATE affiliate_attributions SET conversion_at = COALESCE(conversion_at, NOW()) WHERE survey_session_id = ?',
            [$sessionId]
        );

        if (!empty($payment['affiliate_id'])) {
            $affiliate = $this->db->fetch('SELECT * FROM affiliates WHERE id = ?', [(int) $payment['affiliate_id']]);
            if ($affiliate) {
                $amount = (int) ($payment['amount'] ?? 0);
                $currency = strtoupper((string) ($payment['currency'] ?? 'USD'));
                $commission = $affiliate['commission_type'] === 'fixed'
                    ? (int) round((float) $affiliate['commission_value'] * 100)
                    : (int) round($amount * ((float) $affiliate['commission_value'] / 100));
                $this->db->execute(
                    'INSERT INTO affiliate_commissions (affiliate_id, payment_id, survey_session_id, amount_minor, currency, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE amount_minor = VALUES(amount_minor), currency = VALUES(currency), updated_at = NOW()',
                    [(int) $affiliate['id'], $paymentId, $sessionId, max(0, $commission), $currency, 'pending']
                );
            }
        }

        $participant = $this->db->fetch(
            'SELECT p.name, p.email, t.name track_name FROM survey_sessions s JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id WHERE s.id = ?',
            [$sessionId]
        );
        if ($participant) {
            $variables = [
                'participantName' => $participant['name'],
                'trackName' => $participant['track_name'],
                'amount' => number_format(((int) ($payment['amount'] ?? 0)) / 100, 2),
                'currency' => strtoupper((string) ($payment['currency'] ?? 'USD')),
                'reportUrl' => $reportUrl,
                'paidReportUrl' => $reportUrl,
                'reportId' => $reportId,
            ];
            $this->enqueueOnce('payment_successful', (string) $participant['email'], $variables);
            $this->enqueueOnce('paid_report_ready', (string) $participant['email'], $variables);
        }

        $existingNotice = $this->db->fetch(
            'SELECT id FROM notification_events WHERE event_key = ? AND entity_type = ? AND entity_id = ? LIMIT 1',
            ['payment_paid', 'payment', (string) $paymentId]
        );
        if (!$existingNotice) {
            $this->db->execute(
                'INSERT INTO notification_events (event_key, severity, entity_type, entity_id, title, message, payload_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    'payment_paid',
                    'info',
                    'payment',
                    (string) $paymentId,
                    'Stripe payment received',
                    'A Full Development Report payment was verified and the report was unlocked.',
                    json_encode(['surveySessionId' => $sessionId, 'reportId' => $reportId]),
                ]
            );
        }
    }

    private function rotateReportAccess(int $sessionId): array
    {
        $report = $this->db->fetch('SELECT id FROM generated_reports WHERE survey_session_id = ? FOR UPDATE', [$sessionId]);
        if (!$report) throw new \RuntimeException('Generated report was not found after payment.');

        $token = bin2hex(random_bytes(32));
        $days = max(1, (int) ($this->config['report_token_days'] ?? 30));
        $this->db->execute('UPDATE secure_report_tokens SET revoked_at = NOW() WHERE generated_report_id = ? AND revoked_at IS NULL', [$report['id']]);
        $this->db->execute(
            'INSERT INTO secure_report_tokens (generated_report_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())',
            [$report['id'], hash('sha256', $token), $days]
        );
        $this->db->execute(
            'UPDATE generated_reports SET secure_token_hash = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY), revoked_at = NULL, updated_at = NOW() WHERE id = ?',
            [hash('sha256', $token), $days, $report['id']]
        );
        return [
            'reportId' => (int) $report['id'],
            'reportUrl' => rtrim((string) $this->config['url'], '/') . '/report/' . rawurlencode($token),
        ];
    }

    private function enqueueOnce(string $template, string $recipient, array $variables): int
    {
        $recipient = strtolower(trim($recipient));
        $reportId = (int) ($variables['reportId'] ?? 0);
        if ($reportId > 0) {
            $existing = $this->db->fetch(
                "SELECT id FROM email_queue WHERE template_key = ? AND recipient_email = ? AND JSON_UNQUOTE(JSON_EXTRACT(variables_json, '$.reportId')) = ? ORDER BY id DESC LIMIT 1",
                [$template, $recipient, (string) $reportId]
            );
            if ($existing) return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO email_queue (template_key, recipient_email, variables_json, status, attempts, scheduled_at, created_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())',
            [$template, $recipient, json_encode($variables), 'queued']
        );
    }
}
