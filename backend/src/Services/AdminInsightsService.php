<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;
use AtomGlobal\Mail\MailQueue;

final class AdminInsightsService
{
    public function __construct(
        private Database $db,
        private MailQueue $mailQueue,
        private array $config,
    ) {}

    public function dashboard(): array
    {
        $daily = [];
        for ($offset = 13; $offset >= 0; $offset--) {
            $day = date('Y-m-d', strtotime('-' . $offset . ' days'));
            $daily[$day] = [
                'date' => $day,
                'started' => 0,
                'completed' => 0,
                'revenueMinor' => 0,
            ];
        }

        foreach ($this->db->fetchAll(
            'SELECT DATE(created_at) day, COUNT(*) value FROM survey_sessions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at)'
        ) as $row) {
            if (isset($daily[$row['day']])) $daily[$row['day']]['started'] = (int) $row['value'];
        }

        foreach ($this->db->fetchAll(
            'SELECT DATE(completed_at) day, COUNT(*) value FROM survey_sessions WHERE completed_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(completed_at)'
        ) as $row) {
            if (isset($daily[$row['day']])) $daily[$row['day']]['completed'] = (int) $row['value'];
        }

        foreach ($this->db->fetchAll(
            'SELECT DATE(COALESCE(paid_at, created_at)) day, COALESCE(SUM(amount), 0) value FROM payments WHERE status = ? AND COALESCE(paid_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(COALESCE(paid_at, created_at))',
            ['paid']
        ) as $row) {
            if (isset($daily[$row['day']])) $daily[$row['day']]['revenueMinor'] = (int) $row['value'];
        }

        $funnel = $this->db->fetch(
            'SELECT COUNT(*) started, SUM(completion_percentage > 0) engaged, SUM(status = ?) completed, (SELECT COUNT(DISTINCT survey_session_id) FROM payments WHERE status = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) paid FROM survey_sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ['completed', 'paid']
        ) ?: [];

        $trackProgress = $this->db->fetchAll(
            'SELECT t.track_key trackKey, t.name track, COUNT(s.id) started, COALESCE(SUM(s.status = ?), 0) completed, COALESCE(ROUND(AVG(s.completion_percentage), 1), 0) averageProgress, COALESCE(SUM(CASE WHEN pay.status = ? THEN pay.amount ELSE 0 END), 0) revenueMinor FROM assessment_tracks t LEFT JOIN survey_sessions s ON s.track_id = t.id AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) LEFT JOIN payments pay ON pay.id = (SELECT MAX(p2.id) FROM payments p2 WHERE p2.survey_session_id = s.id) GROUP BY t.id ORDER BY t.display_order',
            ['completed', 'paid']
        );

        $email = $this->db->fetch(
            'SELECT SUM(status IN (?, ?)) pending, SUM(status = ?) sent, SUM(status = ?) failed FROM email_queue WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ['queued', 'retry', 'sent', 'failed']
        ) ?: [];

        return [
            'daily' => array_values($daily),
            'funnel' => [
                ['label' => 'Started', 'value' => (int) ($funnel['started'] ?? 0)],
                ['label' => 'Engaged', 'value' => (int) ($funnel['engaged'] ?? 0)],
                ['label' => 'Completed', 'value' => (int) ($funnel['completed'] ?? 0)],
                ['label' => 'Paid', 'value' => (int) ($funnel['paid'] ?? 0)],
            ],
            'trackProgress' => array_map(static fn(array $row): array => [
                'trackKey' => $row['trackKey'],
                'track' => $row['track'],
                'started' => (int) $row['started'],
                'completed' => (int) $row['completed'],
                'averageProgress' => (float) $row['averageProgress'],
                'revenueMinor' => (int) $row['revenueMinor'],
            ], $trackProgress),
            'emailSummary' => [
                'pending' => (int) ($email['pending'] ?? 0),
                'sent' => (int) ($email['sent'] ?? 0),
                'failed' => (int) ($email['failed'] ?? 0),
            ],
        ];
    }

    public function search(string $term, array $permissions): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) return [];
        $like = '%' . $term . '%';
        $all = in_array('*', $permissions, true);
        $allowed = static fn(string $permission): bool => $all || in_array($permission, $permissions, true);
        $items = [];

        if ($allowed('participants.view')) {
            foreach ($this->db->fetchAll(
                'SELECT p.id, p.name, p.email, s.status, s.completion_percentage progress, t.name track, s.last_activity_at activity FROM participants p LEFT JOIN survey_sessions s ON s.id = (SELECT MAX(s2.id) FROM survey_sessions s2 WHERE s2.participant_id = p.id) LEFT JOIN assessment_tracks t ON t.id = s.track_id WHERE p.anonymised_at IS NULL AND (p.name LIKE ? OR p.email LIKE ?) ORDER BY s.last_activity_at DESC LIMIT 8',
                [$like, $like]
            ) as $row) {
                $items[] = [
                    'type' => 'participant',
                    'module' => 'Participants',
                    'id' => (int) $row['id'],
                    'title' => $row['name'],
                    'subtitle' => $row['email'],
                    'meta' => trim(($row['track'] ?: 'No assessment') . ' · ' . ($row['status'] ?: 'No session') . ' · ' . (int) ($row['progress'] ?? 0) . '%'),
                    'query' => $row['email'],
                    'sortDate' => $row['activity'] ?? '',
                ];
            }
        }

        if ($allowed('reports.manage')) {
            foreach ($this->db->fetchAll(
                'SELECT gr.id, p.name, p.email, t.name track, gr.is_unlocked, gr.revoked_at, gr.created_at FROM generated_reports gr JOIN survey_sessions s ON s.id = gr.survey_session_id JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id WHERE p.name LIKE ? OR p.email LIKE ? OR t.name LIKE ? ORDER BY gr.created_at DESC LIMIT 6',
                [$like, $like, $like]
            ) as $row) {
                $status = $row['revoked_at'] ? 'Revoked' : ((int) $row['is_unlocked'] === 1 ? 'Full report' : 'Lite report');
                $items[] = [
                    'type' => 'report',
                    'module' => 'Reports',
                    'id' => (int) $row['id'],
                    'title' => $row['name'],
                    'subtitle' => $row['email'],
                    'meta' => $row['track'] . ' · ' . $status,
                    'query' => $row['email'],
                    'sortDate' => $row['created_at'],
                ];
            }
        }

        if ($allowed('payments.manage')) {
            foreach ($this->db->fetchAll(
                'SELECT pay.id, pay.status, pay.amount, pay.currency, pay.created_at, p.name, p.email, t.name track FROM payments pay JOIN survey_sessions s ON s.id = pay.survey_session_id JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id WHERE p.name LIKE ? OR p.email LIKE ? OR pay.status LIKE ? OR pay.stripe_checkout_session_id LIKE ? OR pay.stripe_payment_intent_id LIKE ? ORDER BY pay.created_at DESC LIMIT 6',
                [$like, $like, $like, $like, $like]
            ) as $row) {
                $amount = $row['amount'] === null ? 'No amount' : strtoupper($row['currency']) . ' ' . number_format((int) $row['amount'] / 100, 2);
                $items[] = [
                    'type' => 'payment',
                    'module' => 'Payments',
                    'id' => (int) $row['id'],
                    'title' => $row['name'],
                    'subtitle' => $row['email'],
                    'meta' => $row['track'] . ' · ' . $row['status'] . ' · ' . $amount,
                    'query' => $row['email'],
                    'sortDate' => $row['created_at'],
                ];
            }
        }

        if ($allowed('email.manage')) {
            foreach ($this->db->fetchAll(
                'SELECT id, recipient_email, template_key, status, created_at FROM email_queue WHERE recipient_email LIKE ? OR template_key LIKE ? OR status LIKE ? ORDER BY created_at DESC LIMIT 6',
                [$like, $like, $like]
            ) as $row) {
                $items[] = [
                    'type' => 'email',
                    'module' => 'Email',
                    'id' => (int) $row['id'],
                    'title' => $row['recipient_email'],
                    'subtitle' => $row['template_key'],
                    'meta' => ucfirst($row['status']),
                    'query' => $row['recipient_email'],
                    'sortDate' => $row['created_at'],
                ];
            }
        }

        if ($allowed('affiliates.manage')) {
            foreach ($this->db->fetchAll(
                'SELECT id, affiliate_code, name, contact_email, is_active, created_at FROM affiliates WHERE affiliate_code LIKE ? OR name LIKE ? OR contact_email LIKE ? ORDER BY created_at DESC LIMIT 6',
                [$like, $like, $like]
            ) as $row) {
                $items[] = [
                    'type' => 'affiliate',
                    'module' => 'Affiliates',
                    'id' => (int) $row['id'],
                    'title' => $row['affiliate_code'] . ' · ' . $row['name'],
                    'subtitle' => $row['contact_email'] ?: 'No contact email',
                    'meta' => (int) $row['is_active'] === 1 ? 'Active' : 'Inactive',
                    'query' => $row['affiliate_code'],
                    'sortDate' => $row['created_at'],
                ];
            }
        }

        usort($items, static fn(array $left, array $right): int => strcmp((string) $right['sortDate'], (string) $left['sortDate']));
        return array_slice(array_map(static function (array $item): array {
            unset($item['sortDate']);
            return $item;
        }, $items), 0, 24);
    }

    public function queueTemplateTest(string $templateKey, string $recipient, array $variables, int $adminId): array
    {
        $recipient = strtolower(trim($recipient));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid test email recipient is required.');
        }

        $template = $this->db->fetch('SELECT template_key, template_name, subject, html_body, text_body, is_active FROM email_templates WHERE template_key = ? LIMIT 1', [$templateKey]);
        if (!$template) throw new \RuntimeException('Email template not found.', 404);
        if (!(bool) $template['is_active']) throw new \InvalidArgumentException('Activate the template before sending a test.');

        $sample = array_merge($this->sampleVariables(), $variables);
        $queueId = $this->mailQueue->enqueue($templateKey, $recipient, $sample);
        $this->db->execute(
            'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$adminId, 'email_template.test_queued', 'email_template', $templateKey, json_encode(['queueId' => $queueId, 'recipient' => $recipient])]
        );

        return [
            'queueId' => $queueId,
            'templateKey' => $templateKey,
            'templateName' => $template['template_name'],
            'recipient' => $recipient,
            'variables' => $sample,
        ];
    }

    private function sampleVariables(): array
    {
        $base = rtrim((string) ($this->config['url'] ?? 'https://head-heart.atomglobal.com'), '/');
        return [
            'participantName' => 'Sample Participant',
            'adminName' => 'Sample Administrator',
            'trackName' => 'Manager',
            'resumeUrl' => $base . '/?resume=sample-test-link',
            'reportUrl' => $base . '/report/sample-test-link',
            'freeReportUrl' => $base . '/report/sample-test-link',
            'paidReportUrl' => $base . '/report/sample-test-link',
            'paymentUrl' => $base . '/payment/sample-test-link',
            'resetUrl' => $base . '/admin/reset-password?token=sample-test-link',
            'completionPercentage' => '60',
            'reminderNumber' => '1',
            'amount' => '49.00',
            'currency' => 'USD',
            'affiliateCode' => 'SAMPLE',
            'message' => 'This is a safe test of the selected email template.',
            'subject' => 'Growth Alignment template test',
            'expiresMinutes' => '60',
        ];
    }
}
