<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;

final class HealthService
{
    public function __construct(private Database $db, private array $config, private SettingsService $settings) {}

    public function check(): array
    {
        $checks = [];
        try {
            $checks['database'] = (int) ($this->db->fetch('SELECT 1 ok')['ok'] ?? 0) === 1;
            $checks['migrations'] = (bool) $this->db->fetch('SELECT 1 ok FROM migrations WHERE migration = ? LIMIT 1', ['019_v4_remove_retired_head_heart_image.sql']);
        } catch (\Throwable) {
            $checks['database'] = false;
            $checks['migrations'] = false;
        }
        $checks['storage'] = is_dir($this->config['storage']) && is_writable($this->config['storage']);
        $checks['stripe'] = (bool) $this->settings->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? '');
        $checks['stripeWebhook'] = (bool) $this->settings->get('stripe.webhook_secret', $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '');
        $checks['email'] = (bool) (
            $this->settings->get('email.smtp2go_api_key', $_ENV['SMTP2GO_API_KEY'] ?? '')
            ?: $this->settings->get('email.smtp_host', $_ENV['SMTP_HOST'] ?? '')
        );
        $checks['feedbackGitHub'] = (bool) (
            $this->settings->get('feedback.github_token', $_ENV['GITHUB_FEEDBACK_TOKEN'] ?? '')
            && $this->settings->get('feedback.github_repository', $_ENV['GITHUB_FEEDBACK_REPOSITORY'] ?? '')
        );
        $cron = $this->settings->get('system.cron_last_run');
        $checks['cron'] = $cron && strtotime((string) $cron) > time() - 900;
        $essential = [$checks['database'], $checks['migrations'], $checks['storage']];
        return [
            'status' => !in_array(false, $essential, true) ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => gmdate(DATE_ATOM),
            'environment' => $this->config['env'],
        ];
    }
}
