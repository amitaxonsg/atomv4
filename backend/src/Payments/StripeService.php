<?php
declare(strict_types=1);

namespace AtomGlobal\Payments;

use AtomGlobal\Database;
use AtomGlobal\Services\ReportService;
use AtomGlobal\Services\SettingsService;
use Stripe\StripeClient;
use Stripe\Webhook;

final class StripeService
{
    private const RETAKE_MARKER = '__RETAKE__';
    private const RETAKE_QUESTIONS_PER_SECTION = 4;
    private const RETAKE_DEFAULTS = ['personal' => 299, 'newjoiner' => 995, 'manager' => 2995, 'executive' => 4995];

    public function __construct(private Database $db, private SettingsService $settings, private ReportService $reports, private array $config) {}

    public function checkout(int $sessionId, string $trackKey, ?string $affiliateCode): array
    {
        if ($affiliateCode === self::RETAKE_MARKER) {
            return $this->retakeCheckout($sessionId, $trackKey);
        }

        $secret = $this->settings->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? '');
        $environmentKey = 'STRIPE_PRICE_' . strtoupper($trackKey);
        $price = $this->settings->get('stripe.price_' . $trackKey, $_ENV[$environmentKey] ?? '');
        if (!$secret || !$price) throw new \RuntimeException('Stripe test or live credentials and track price IDs are not configured.');

        $survey = $this->db->fetch('SELECT s.id, s.status, p.email, t.track_key FROM survey_sessions s JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id WHERE s.id = ? AND t.track_key = ?', [$sessionId, $trackKey]);
        if (!$survey || $survey['status'] !== 'completed') throw new \InvalidArgumentException('A completed assessment is required before checkout.');
        $report = $this->db->fetch('SELECT id FROM generated_reports WHERE survey_session_id = ? AND revoked_at IS NULL', [$sessionId]);
        if (!$report) throw new \InvalidArgumentException('The report is not available for checkout.');

        $affiliate = null;
        if ($affiliateCode) $affiliate = $this->db->fetch('SELECT id, affiliate_code FROM affiliates WHERE affiliate_code = ? AND is_active = 1', [strtoupper(trim($affiliateCode))]);
        $stripe = new StripeClient($secret);
        $checkout = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $survey['email'],
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'allow_promotion_codes' => true,
            'success_url' => $this->config['url'] . '/payment/success?checkout={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->config['url'] . '/payment/cancelled?session=' . $sessionId,
            'metadata' => [
                'survey_session_id' => (string) $sessionId,
                'generated_report_id' => (string) $report['id'],
                'track_key' => $trackKey,
                'affiliate_code' => $affiliate['affiliate_code'] ?? '',
                'payment_purpose' => 'full_report',
            ],
        ]);
        $this->db->execute(
            'INSERT INTO payments (survey_session_id, affiliate_id, provider, status, stripe_checkout_session_id, currency, metadata_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$sessionId, $affiliate['id'] ?? null, 'stripe', 'checkout_started', $checkout->id, strtoupper((string) ($checkout->currency ?? 'USD')), json_encode($checkout->metadata)]
        );
        return ['url' => $checkout->url, 'checkoutSessionId' => $checkout->id];
    }

    public function webhook(string $payload, string $signature): void
    {
        $secret = $this->settings->get('stripe.webhook_secret', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '');
        if (!$secret) throw new \RuntimeException('Stripe webhook secret is not configured.');
        $event = Webhook::constructEvent($payload, $signature, $secret);

        $this->db->transaction(function () use ($event) {
            $exists = $this->db->fetch('SELECT id, status FROM stripe_webhook_events WHERE stripe_event_id = ? FOR UPDATE', [$event->id]);
            if ($exists && $exists['status'] === 'processed') return;
            $eventId = $exists ? (int) $exists['id'] : $this->db->insert(
                'INSERT INTO stripe_webhook_events (stripe_event_id, event_type, status, payload_json, received_at) VALUES (?, ?, ?, ?, NOW())',
                [$event->id, $event->type, 'processing', json_encode($event)]
            );

            try {
                match ($event->type) {
                    'checkout.session.completed' => $this->completed($event->data->object),
                    'checkout.session.async_payment_failed' => $this->failed($event->data->object, 'failed'),
                    'checkout.session.expired' => $this->failed($event->data->object, 'abandoned'),
                    'charge.refunded' => $this->refunded($event->data->object),
                    default => null,
                };
                $this->db->execute('UPDATE stripe_webhook_events SET status = ?, processed_at = NOW(), failure_reason = NULL WHERE id = ?', ['processed', $eventId]);
            } catch (\Throwable $error) {
                $this->db->execute('UPDATE stripe_webhook_events SET status = ?, failure_reason = ? WHERE id = ?', ['failed', mb_substr($error->getMessage(), 0, 1000), $eventId]);
                $this->db->execute('INSERT INTO notification_events (event_key, severity, entity_type, entity_id, title, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', ['webhook_failed', 'critical', 'stripe_webhook_event', (string) $eventId, 'Stripe webhook processing failed', mb_substr($error->getMessage(), 0, 2000)]);
                throw $error;
            }
        });
    }

    private function retakeCheckout(int $sessionId, string $trackKey): array
    {
        $secret = trim((string) $this->settings->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? ''));
        $webhook = trim((string) $this->settings->get('stripe.webhook_secret', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? ''));
        $retestPriceId = trim((string) $this->settings->get('stripe.retest_price_' . $trackKey, ''));
        $retestAmountMinor = max(0, (int) $this->settings->get('retest.price_' . $trackKey . '_minor', self::RETAKE_DEFAULTS[$trackKey] ?? 299));
        $waitDays = max(1, min(365, (int) $this->settings->get('retest.wait_days', 90)));
        if ($secret === '' || $webhook === '' || $retestPriceId === '') throw new \RuntimeException('Stripe credentials and the track retest Price ID are not configured.');

        $survey = $this->db->fetch(
            'SELECT s.id, s.status, s.completed_at, p.email, t.track_key, t.name track_name, gr.id report_id, gr.is_unlocked FROM survey_sessions s JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id JOIN generated_reports gr ON gr.survey_session_id = s.id AND gr.revoked_at IS NULL WHERE s.id = ? AND t.track_key = ? LIMIT 1',
            [$sessionId, $trackKey]
        );
        if (!$survey || $survey['status'] !== 'completed' || !(bool) $survey['is_unlocked']) {
            throw new \InvalidArgumentException('A retest is available only after a completed Full Development Report.');
        }
        if (!$this->hasPaidAssessment($sessionId)) {
            throw new \InvalidArgumentException('A retest is available only to participants who previously completed a paid Full Development Report assessment.');
        }
        $eligibleAt = (new \DateTimeImmutable((string) $survey['completed_at']))->modify('+' . $waitDays . ' days');
        if ($eligibleAt > new \DateTimeImmutable('now')) {
            throw new \InvalidArgumentException('The retest becomes available 90 days after the original assessment was completed.');
        }

        $stripe = new StripeClient($secret);
        $checkout = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $survey['email'],
            'line_items' => [['price' => $retestPriceId, 'quantity' => 1]],
            'success_url' => $this->config['url'] . '/payment/success?retake=1&checkout={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->config['url'] . '/payment/cancelled?retake=1&session=' . $sessionId,
            'metadata' => [
                'survey_session_id' => (string) $sessionId,
                'source_survey_session_id' => (string) $sessionId,
                'generated_report_id' => (string) $survey['report_id'],
                'track_key' => $trackKey,
                'payment_purpose' => 'retake',
            ],
        ]);
        $this->db->execute(
            'INSERT INTO payments (survey_session_id, affiliate_id, provider, status, stripe_checkout_session_id, amount, currency, metadata_json, created_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$sessionId, 'stripe', 'checkout_started', $checkout->id, $retestAmountMinor, 'USD', json_encode($checkout->metadata)]
        );
        return ['url' => $checkout->url, 'checkoutSessionId' => $checkout->id, 'retake' => true, 'amountMinor' => $retestAmountMinor, 'currency' => 'USD'];
    }

    private function completed(object $checkout): void
    {
        if (($checkout->payment_status ?? '') !== 'paid') return;
        $purpose = (string) ($checkout->metadata->payment_purpose ?? 'full_report');
        if ($purpose === 'retake') {
            $this->completedRetake($checkout);
            return;
        }

        $sessionId = (int) ($checkout->metadata->survey_session_id ?? 0);
        if ($sessionId < 1) throw new \RuntimeException('Stripe metadata does not contain a survey session.');
        $payment = $this->db->fetch('SELECT * FROM payments WHERE stripe_checkout_session_id = ? FOR UPDATE', [$checkout->id]);
        if (!$payment) throw new \RuntimeException('Stripe checkout payment record was not found.');
        if ($payment['status'] === 'paid') return;

        $amount = (int) ($checkout->amount_total ?? 0);
        $currency = strtoupper((string) ($checkout->currency ?? 'USD'));
        $this->db->execute('UPDATE payments SET status = ?, stripe_payment_intent_id = ?, stripe_customer_id = ?, amount = ?, currency = ?, paid_at = NOW(), updated_at = NOW() WHERE id = ?', ['paid', $checkout->payment_intent ?? null, $checkout->customer ?? null, $amount, $currency, $payment['id']]);
        $this->reports->unlockBySession($sessionId, 'stripe_webhook');
        $reportAccess = $this->rotateReportAccess($sessionId);
        $metadata = json_decode((string) ($payment['metadata_json'] ?? '{}'), true) ?: [];
        $metadata['reportUrl'] = $reportAccess['reportUrl'];
        $metadata['reportId'] = $reportAccess['reportId'];
        $metadata['reportReadyAt'] = gmdate(DATE_ATOM);
        $this->db->execute('UPDATE payments SET metadata_json = ?, updated_at = NOW() WHERE id = ?', [json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $payment['id']]);
        $this->db->execute('UPDATE affiliate_attributions SET conversion_at = COALESCE(conversion_at, NOW()) WHERE survey_session_id = ?', [$sessionId]);

        if ($payment['affiliate_id']) {
            $affiliate = $this->db->fetch('SELECT * FROM affiliates WHERE id = ?', [$payment['affiliate_id']]);
            if ($affiliate) {
                $commission = $affiliate['commission_type'] === 'fixed'
                    ? (int) round((float) $affiliate['commission_value'] * 100)
                    : (int) round($amount * ((float) $affiliate['commission_value'] / 100));
                $this->db->execute('INSERT INTO affiliate_commissions (affiliate_id, payment_id, survey_session_id, amount_minor, currency, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE amount_minor = VALUES(amount_minor), currency = VALUES(currency), updated_at = NOW()', [$affiliate['id'], $payment['id'], $sessionId, max(0, $commission), $currency, 'pending']);
            }
        }

        $participant = $this->db->fetch('SELECT p.name, p.email, t.name track_name FROM survey_sessions s JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id WHERE s.id = ?', [$sessionId]);
        if ($participant) {
            $variables = [
                'participantName' => $participant['name'],
                'trackName' => $participant['track_name'],
                'amount' => number_format($amount / 100, 2),
                'currency' => $currency,
                'reportUrl' => $reportAccess['reportUrl'],
                'paidReportUrl' => $reportAccess['reportUrl'],
                'reportId' => $reportAccess['reportId'],
            ];
            $this->enqueue('payment_successful', $participant['email'], $variables);
            $this->enqueue('paid_report_ready', $participant['email'], $variables);
        }
    }

    private function completedRetake(object $checkout): void
    {
        $sourceSessionId = (int) ($checkout->metadata->source_survey_session_id ?? $checkout->metadata->survey_session_id ?? 0);
        if ($sourceSessionId < 1) throw new \RuntimeException('Retake checkout does not contain a source survey session.');
        $payment = $this->db->fetch('SELECT * FROM payments WHERE stripe_checkout_session_id = ? FOR UPDATE', [$checkout->id]);
        if (!$payment) throw new \RuntimeException('Retake payment record was not found.');
        if ($payment['status'] === 'paid') return;

        $source = $this->db->fetch(
            'SELECT s.*, p.name participant_name, p.email participant_email, t.name track_name, t.track_key FROM survey_sessions s JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id WHERE s.id = ? AND s.status = ? LIMIT 1',
            [$sourceSessionId, 'completed']
        );
        if (!$source) throw new \RuntimeException('The source assessment for this retake is unavailable.');
        $sourceReport = $this->db->fetch('SELECT id, is_unlocked FROM generated_reports WHERE survey_session_id = ? AND revoked_at IS NULL LIMIT 1', [$sourceSessionId]);
        if (!$sourceReport || !(bool) $sourceReport['is_unlocked']) throw new \RuntimeException('The source Full Development Report is not unlocked.');
        if (!$this->hasPaidAssessment($sourceSessionId)) throw new \RuntimeException('The source assessment does not have a verified paid Full Development Report.');

        $snapshot = $this->normaliseRetakeSnapshot(json_decode((string) $source['question_snapshot_json'], true, 512, JSON_THROW_ON_ERROR));
        $resumeToken = bin2hex(random_bytes(32));
        $hours = max(1, (int) ($this->config['resume_token_hours'] ?? 168));
        $context = json_decode((string) ($source['participant_context_json'] ?? '{}'), true) ?: [];
        $attribution = json_decode((string) ($source['attribution_snapshot_json'] ?? '{}'), true) ?: [];
        $attribution['retakeOfSessionId'] = $sourceSessionId;
        $attribution['retakeSourceReportId'] = (int) $sourceReport['id'];
        $attribution['retakePaidAt'] = gmdate(DATE_ATOM);
        $attribution['retakePriceMinor'] = (int) ($payment['amount'] ?? 0);
        $attribution['retakeCurrency'] = 'USD';

        $newSessionId = $this->db->insert(
            'INSERT INTO survey_sessions (participant_id, track_id, assessment_version_id, affiliate_id, first_click_id, last_click_id, status, resume_token_hash, resume_expires_at, question_snapshot_json, participant_context_json, attribution_snapshot_json, last_activity_at, created_at, updated_at) VALUES (?, ?, ?, NULL, NULL, NULL, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), ?, ?, ?, NOW(), NOW(), NOW())',
            [(int) $source['participant_id'], (int) $source['track_id'], (int) $source['assessment_version_id'], 'in_progress', hash('sha256', $resumeToken), $hours, json_encode($snapshot), json_encode($context), json_encode($attribution)]
        );

        $amount = (int) ($checkout->amount_total ?? $payment['amount'] ?? 0);
        $currency = strtoupper((string) ($checkout->currency ?? 'USD'));
        $this->db->execute(
            'UPDATE payments SET survey_session_id = ?, status = ?, stripe_payment_intent_id = ?, stripe_customer_id = ?, amount = ?, currency = ?, paid_at = NOW(), updated_at = NOW() WHERE id = ?',
            [$newSessionId, 'paid', $checkout->payment_intent ?? null, $checkout->customer ?? null, $amount, $currency, $payment['id']]
        );
        $this->db->execute(
            'INSERT INTO analytics_events (survey_session_id, event_name, event_properties_json, consent_state, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$newSessionId, 'retake_started', json_encode(['sourceSessionId' => $sourceSessionId, 'amountMinor' => $amount, 'currency' => $currency]), 'essential']
        );

        $resumeUrl = rtrim((string) $this->config['url'], '/') . '/?resume=' . rawurlencode($resumeToken);
        $this->enqueue('survey_resume_link', (string) $source['participant_email'], [
            'participantName' => $source['participant_name'],
            'trackName' => $source['track_name'] . ' Retake',
            'resumeUrl' => $resumeUrl,
            'amount' => number_format($amount / 100, 2),
            'currency' => $currency,
        ]);
    }

    private function hasPaidAssessment(int $sessionId): bool
    {
        $paid = $this->db->fetch(
            'SELECT id FROM payments WHERE survey_session_id = ? AND provider = ? AND status = ? ORDER BY paid_at DESC, id DESC LIMIT 1',
            [$sessionId, 'stripe', 'paid']
        );
        return (bool) $paid;
    }

    private function normaliseRetakeSnapshot(array $snapshot): array
    {
        $questions = is_array($snapshot['questions'] ?? null) ? $snapshot['questions'] : [];
        $sections = [];
        foreach ($questions as $question) {
            $code = (string) ($question['subscale_code'] ?? '');
            if ($code === '') continue;
            $sections[$code] ??= [];
            $sections[$code][] = $question;
        }
        uasort($sections, static function (array $left, array $right): int {
            return ((int) ($left[0]['section_order'] ?? 0)) <=> ((int) ($right[0]['section_order'] ?? 0));
        });
        $runtime = [];
        foreach ($sections as $sectionQuestions) {
            usort($sectionQuestions, static fn(array $a, array $b): int => ((int) ($a['position'] ?? 0)) <=> ((int) ($b['position'] ?? 0)));
            foreach (array_slice($sectionQuestions, 0, self::RETAKE_QUESTIONS_PER_SECTION) as $question) {
                $question['position'] = count($runtime) + 1;
                $runtime[] = $question;
            }
        }
        if (count($runtime) !== 40) throw new \RuntimeException('Retake source could not be normalized to the required 40 questions.');
        $snapshot['questions'] = $runtime;
        $snapshot['scoring']['question_count'] = 40;
        return $snapshot;
    }

    private function rotateReportAccess(int $sessionId): array
    {
        $report = $this->db->fetch('SELECT id FROM generated_reports WHERE survey_session_id = ? FOR UPDATE', [$sessionId]);
        if (!$report) throw new \RuntimeException('Generated report was not found after payment.');
        $token = bin2hex(random_bytes(32));
        $days = max(1, (int) ($this->config['report_token_days'] ?? 30));
        $this->db->execute('UPDATE secure_report_tokens SET revoked_at = NOW() WHERE generated_report_id = ? AND revoked_at IS NULL', [$report['id']]);
        $this->db->execute('INSERT INTO secure_report_tokens (generated_report_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? DAY), NOW())', [$report['id'], hash('sha256', $token), $days]);
        $this->db->execute('UPDATE generated_reports SET secure_token_hash = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY), revoked_at = NULL, updated_at = NOW() WHERE id = ?', [hash('sha256', $token), $days, $report['id']]);
        return [
            'reportId' => (int) $report['id'],
            'reportUrl' => rtrim((string) $this->config['url'], '/') . '/report/' . rawurlencode($token),
        ];
    }

    private function failed(object $checkout, string $status): void
    {
        $this->db->execute('UPDATE payments SET status = ?, updated_at = NOW() WHERE stripe_checkout_session_id = ?', [$status, $checkout->id]);
        $this->db->execute('INSERT INTO notification_events (event_key, severity, entity_type, entity_id, title, message, payload_json, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())', ['payment_' . $status, $status === 'failed' ? 'warning' : 'info', 'payment', (string) $checkout->id, 'Stripe payment ' . $status, 'A checkout was marked ' . $status . '.', json_encode(['checkout' => $checkout->id])]);
    }

    private function refunded(object $charge): void
    {
        $paymentIntent = (string) ($charge->payment_intent ?? '');
        if ($paymentIntent === '') return;
        $payment = $this->db->fetch('SELECT * FROM payments WHERE stripe_payment_intent_id = ? FOR UPDATE', [$paymentIntent]);
        if (!$payment) return;
        $report = $this->db->fetch('SELECT id, pdf_path FROM generated_reports WHERE survey_session_id = ? FOR UPDATE', [$payment['survey_session_id']]);
        $this->db->execute('UPDATE payments SET status = ?, refunded_at = NOW(), updated_at = NOW() WHERE id = ?', ['refunded', $payment['id']]);
        if ($report) {
            $this->db->execute('UPDATE generated_reports SET is_unlocked = 0, unlock_reason = ?, unlocked_at = NULL, pdf_path = NULL, pdf_generated_at = NULL, updated_at = NOW() WHERE survey_session_id = ?', ['stripe_refund', $payment['survey_session_id']]);
            $this->db->execute('UPDATE affiliate_commissions SET status = ?, adjustment_note = ?, updated_at = NOW() WHERE payment_id = ?', ['void', 'Payment refunded', $payment['id']]);
            if (!empty($report['pdf_path']) && is_file($report['pdf_path'])) @unlink($report['pdf_path']);
        }
    }

    private function enqueue(string $template, string $recipient, array $variables): void
    {
        $this->db->execute('INSERT INTO email_queue (template_key, recipient_email, variables_json, status, attempts, scheduled_at, created_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())', [$template, strtolower($recipient), json_encode($variables), 'queued']);
    }
}
