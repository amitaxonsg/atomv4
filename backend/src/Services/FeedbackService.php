<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;
use AtomGlobal\Mail\MailQueue;

final class FeedbackService
{
    private const STATUSES = [
        'new', 'clarification_requested', 'accepted', 'in_progress',
        'ready_for_review', 'done', 'declined',
    ];

    private const TYPES = ['bug', 'improvement', 'content', 'question', 'other'];
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        private Database $db,
        private SettingsService $settings,
        private MailQueue $mailQueue,
        private array $config,
    ) {}

    public function list(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $status = trim((string) ($query['status'] ?? ''));
        $priority = trim((string) ($query['priority'] ?? ''));
        $limit = max(25, min(500, (int) ($query['limit'] ?? 250)));
        $where = [];
        $params = [];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(f.title LIKE ? OR f.details LIKE ? OR f.submitter_name LIKE ? OR f.submitter_email LIKE ? OR f.module_name LIKE ? OR CAST(f.github_issue_number AS CHAR) LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'f.status = ?';
            $params[] = $status;
        }
        if (in_array($priority, self::PRIORITIES, true)) {
            $where[] = 'f.priority = ?';
            $params[] = $priority;
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $items = $this->db->fetchAll(
            'SELECT f.id, f.public_uuid publicUuid, f.submitter_name submitterName, f.submitter_email submitterEmail, '
            . 'f.feedback_type feedbackType, f.module_name moduleName, f.priority, f.title, f.details, '
            . 'f.expected_outcome expectedOutcome, f.page_url pageUrl, f.attachment_url attachmentUrl, '
            . 'f.status, f.resolution, f.github_issue_number githubIssueNumber, f.github_issue_url githubIssueUrl, '
            . 'f.github_sync_status githubSyncStatus, f.github_last_error githubLastError, f.completed_at completedAt, '
            . 'f.created_at createdAt, f.updated_at updatedAt, u.display_name submittedByName '
            . 'FROM client_feedback f LEFT JOIN admin_users u ON u.id = f.submitted_by_admin_id'
            . $sqlWhere . ' ORDER BY FIELD(f.status, ?, ?, ?, ?, ?, ?, ?), f.updated_at DESC LIMIT ' . $limit,
            [...$params, 'new', 'clarification_requested', 'accepted', 'in_progress', 'ready_for_review', 'done', 'declined']
        );

        $summary = $this->db->fetch(
            'SELECT COUNT(*) total, SUM(status = ?) newCount, SUM(status = ?) clarificationCount, '
            . 'SUM(status IN (?, ?)) activeCount, SUM(status = ?) reviewCount, SUM(status = ?) doneCount '
            . 'FROM client_feedback',
            ['new', 'clarification_requested', 'accepted', 'in_progress', 'ready_for_review', 'done']
        ) ?: [];

        return [
            'items' => $items,
            'summary' => [
                'total' => (int) ($summary['total'] ?? 0),
                'new' => (int) ($summary['newCount'] ?? 0),
                'clarification' => (int) ($summary['clarificationCount'] ?? 0),
                'active' => (int) ($summary['activeCount'] ?? 0),
                'review' => (int) ($summary['reviewCount'] ?? 0),
                'done' => (int) ($summary['doneCount'] ?? 0),
            ],
        ];
    }

    public function detail(int $id): array
    {
        $item = $this->find($id);
        $item['updates'] = $this->db->fetchAll(
            'SELECT fu.id, fu.update_type updateType, fu.status, fu.message, fu.emailed_at emailedAt, '
            . 'fu.github_synced_at githubSyncedAt, fu.created_at createdAt, u.display_name adminName, u.email adminEmail '
            . 'FROM client_feedback_updates fu LEFT JOIN admin_users u ON u.id = fu.admin_user_id '
            . 'WHERE fu.feedback_id = ? ORDER BY fu.created_at DESC, fu.id DESC',
            [$id]
        );
        return $item;
    }

    public function create(array $payload, array $user): array
    {
        $title = mb_substr(trim((string) ($payload['title'] ?? '')), 0, 250);
        $details = mb_substr(trim((string) ($payload['details'] ?? '')), 0, 20000);
        if (mb_strlen($title) < 5) throw new \InvalidArgumentException('A clear feedback title is required.');
        if (mb_strlen($details) < 10) throw new \InvalidArgumentException('Please describe the feedback in more detail.');

        $type = in_array($payload['feedbackType'] ?? '', self::TYPES, true) ? $payload['feedbackType'] : 'improvement';
        $priority = in_array($payload['priority'] ?? '', self::PRIORITIES, true) ? $payload['priority'] : 'normal';
        $name = mb_substr(trim((string) ($payload['submitterName'] ?? $user['displayName'] ?? 'Client administrator')), 0, 200);
        $email = strtolower(trim((string) ($payload['submitterEmail'] ?? $user['email'] ?? $this->clientEmail())));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = $this->clientEmail();

        $id = $this->db->insert(
            'INSERT INTO client_feedback '
            . '(submitted_by_admin_id, submitter_name, submitter_email, feedback_type, module_name, priority, title, details, expected_outcome, page_url, attachment_url, status, github_sync_status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                (int) ($user['id'] ?? 0) ?: null,
                $name ?: 'Client administrator',
                $email,
                $type,
                mb_substr(trim((string) ($payload['moduleName'] ?? 'General')) ?: 'General', 0, 100),
                $priority,
                $title,
                $details,
                mb_substr(trim((string) ($payload['expectedOutcome'] ?? '')), 0, 10000) ?: null,
                $this->safePageUrl((string) ($payload['pageUrl'] ?? '')),
                $this->safeAttachmentUrl((string) ($payload['attachmentUrl'] ?? '')),
                'new',
                'pending',
            ]
        );

        $adminId = (int) ($user['id'] ?? 0) ?: null;
        $this->addUpdate($id, $adminId, 'submitted', 'new', 'Feedback submitted through the administration portal.');
        $this->audit($adminId, 'feedback.submitted', $id, ['title' => $title, 'priority' => $priority]);

        $this->syncGitHub($id, $adminId, false);
        $item = $this->find($id);
        $variables = $this->variables($item);
        $this->mailQueue->enqueue('feedback_received', $email, $variables);
        $this->addUpdate($id, $adminId, 'note', 'new', 'Acknowledgement email queued to the client update address.', true);

        $internal = $this->internalEmail();
        if ($internal && strtolower($internal) !== $email) {
            $this->mailQueue->enqueue('feedback_internal_notice', $internal, $variables);
            $this->addUpdate($id, $adminId, 'note', 'new', 'Internal feedback notification queued.', true);
        }

        return $this->detail($id);
    }

    public function update(int $id, array $payload, array $user): array
    {
        $before = $this->find($id);
        $status = (string) ($payload['status'] ?? $before['status']);
        if (!in_array($status, self::STATUSES, true)) throw new \InvalidArgumentException('Unknown feedback status.');

        $resolution = mb_substr(trim((string) ($payload['resolution'] ?? $before['resolution'] ?? '')), 0, 20000);
        $message = mb_substr(trim((string) ($payload['message'] ?? '')), 0, 10000);
        if ($status === 'clarification_requested' && $message === '') {
            throw new \InvalidArgumentException('Add the clarification required before changing this status.');
        }
        if ($status === 'done' && $resolution === '') {
            throw new \InvalidArgumentException('Add a completion note before marking feedback done.');
        }

        $priority = in_array($payload['priority'] ?? '', self::PRIORITIES, true) ? $payload['priority'] : $before['priority'];
        $this->db->execute(
            'UPDATE client_feedback SET status = ?, priority = ?, resolution = ?, completed_at = IF(? = ?, COALESCE(completed_at, NOW()), NULL), updated_at = NOW() WHERE id = ?',
            [$status, $priority, $resolution ?: null, $status, 'done', $id]
        );

        $adminId = (int) ($user['id'] ?? 0) ?: null;
        $updateType = $status === 'clarification_requested' ? 'clarification' : ($status === 'done' ? 'resolution' : 'status');
        $updateMessage = $message ?: ($resolution ?: 'Status changed to ' . $this->humanStatus($status) . '.');
        $this->addUpdate($id, $adminId, $updateType, $status, $updateMessage);
        $this->audit($adminId, 'feedback.updated', $id, ['status' => $status, 'priority' => $priority, 'message' => $message, 'resolution' => $resolution]);

        $item = $this->find($id);
        $variables = $this->variables($item, ['clarificationMessage' => $message, 'resolution' => $resolution]);
        if ($status === 'clarification_requested') {
            $this->mailQueue->enqueue('feedback_clarification', $item['submitterEmail'], $variables);
            $this->addUpdate($id, $adminId, 'note', $status, 'Clarification email queued to the client update address.', true);
        } elseif ($status === 'done') {
            $this->mailQueue->enqueue('feedback_completed', $item['submitterEmail'], $variables);
            $this->addUpdate($id, $adminId, 'note', $status, 'Completion email queued to the client update address.', true);
        }

        $this->syncGitHub($id, $adminId, true, $message ?: $resolution);
        return $this->detail($id);
    }

    public function retryGitHub(int $id, array $user): array
    {
        $this->syncGitHub($id, (int) ($user['id'] ?? 0) ?: null, true, 'GitHub synchronisation retried from the administration portal.');
        return $this->detail($id);
    }

    public function summary(): array
    {
        $row = $this->db->fetch(
            'SELECT SUM(status = ?) newCount, SUM(status = ?) clarificationCount, SUM(status IN (?, ?)) activeCount, SUM(status = ?) reviewCount '
            . 'FROM client_feedback',
            ['new', 'clarification_requested', 'accepted', 'in_progress', 'ready_for_review']
        ) ?: [];
        return [
            'new' => (int) ($row['newCount'] ?? 0),
            'clarification' => (int) ($row['clarificationCount'] ?? 0),
            'active' => (int) ($row['activeCount'] ?? 0),
            'review' => (int) ($row['reviewCount'] ?? 0),
        ];
    }

    private function syncGitHub(int $id, ?int $adminId, bool $statusUpdate, string $note = ''): void
    {
        $item = $this->find($id);
        $token = (string) $this->settings->get('feedback.github_token', $_ENV['GITHUB_FEEDBACK_TOKEN'] ?? '');
        $repository = trim((string) $this->settings->get('feedback.github_repository', $_ENV['GITHUB_FEEDBACK_REPOSITORY'] ?? 'amitaxonsg/atomglobal-hhaa-v2'));
        if ($token === '' || !preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository)) {
            $this->db->execute(
                'UPDATE client_feedback SET github_sync_status = ?, github_last_error = ?, updated_at = NOW() WHERE id = ?',
                ['not_configured', 'GitHub feedback token is not configured.', $id]
            );
            $this->addUpdate($id, $adminId, 'github_sync', $item['status'], 'Saved locally. GitHub integration is not configured.');
            return;
        }

        try {
            if (!$item['githubIssueNumber']) {
                $prefix = trim((string) $this->settings->get('feedback.issue_prefix', 'Client feedback')) ?: 'Client feedback';
                $response = $this->githubRequest(
                    'POST',
                    'https://api.github.com/repos/' . $repository . '/issues',
                    $token,
                    [
                        'title' => '[' . $prefix . ' #' . $id . '] ' . $item['title'],
                        'body' => $this->issueBody($item),
                    ]
                );
                $number = (int) ($response['number'] ?? 0);
                $url = (string) ($response['html_url'] ?? '');
                if ($number < 1 || $url === '') throw new \RuntimeException('GitHub did not return a valid issue reference.');
                $this->db->execute(
                    'UPDATE client_feedback SET github_issue_number = ?, github_issue_url = ?, github_sync_status = ?, github_last_error = NULL, updated_at = NOW() WHERE id = ?',
                    [$number, $url, 'synced', $id]
                );
                $this->addUpdate($id, $adminId, 'github_sync', $item['status'], 'Created GitHub issue #' . $number, false, true);
                return;
            }

            if ($statusUpdate) {
                $number = (int) $item['githubIssueNumber'];
                $comment = '**Admin status update:** ' . $this->humanStatus($item['status']);
                if ($note !== '') $comment .= "\n\n" . $note;
                $this->githubRequest('POST', 'https://api.github.com/repos/' . $repository . '/issues/' . $number . '/comments', $token, ['body' => $comment]);
                $state = in_array($item['status'], ['done', 'declined'], true) ? 'closed' : 'open';
                $this->githubRequest('PATCH', 'https://api.github.com/repos/' . $repository . '/issues/' . $number, $token, ['state' => $state]);
                $this->db->execute('UPDATE client_feedback SET github_sync_status = ?, github_last_error = NULL, updated_at = NOW() WHERE id = ?', ['synced', $id]);
                $this->addUpdate($id, $adminId, 'github_sync', $item['status'], 'Updated GitHub issue #' . $number, false, true);
            }
        } catch (\Throwable $error) {
            $message = mb_substr($error->getMessage(), 0, 1800);
            $this->db->execute('UPDATE client_feedback SET github_sync_status = ?, github_last_error = ?, updated_at = NOW() WHERE id = ?', ['failed', $message, $id]);
            $this->addUpdate($id, $adminId, 'github_sync', $item['status'], 'GitHub sync failed: ' . $message);
        }
    }

    private function githubRequest(string $method, string $url, string $token, array $payload): array
    {
        $curl = curl_init($url);
        if ($curl === false) throw new \RuntimeException('Could not initialise the GitHub connection.');
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'User-Agent: AtomGlobal-HeadHeart-Feedback',
                'X-GitHub-Api-Version: 2022-11-28',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($response === false || $status < 200 || $status >= 300) {
            $decoded = json_decode((string) $response, true) ?: [];
            throw new \RuntimeException('GitHub API request failed (' . $status . '): ' . ($decoded['message'] ?? $error ?: 'Unknown error'));
        }
        return json_decode((string) $response, true) ?: [];
    }

    private function issueBody(array $item): string
    {
        $lines = [
            '## Client feedback',
            '',
            '- **Portal reference:** #' . $item['id'],
            '- **Submitted by:** ' . $item['submitterName'],
            '- **Type:** ' . ucfirst($item['feedbackType']),
            '- **Module:** ' . $item['moduleName'],
            '- **Priority:** ' . ucfirst($item['priority']),
            '- **Status:** ' . $this->humanStatus($item['status']),
            '- **Page:** ' . ($item['pageUrl'] ?: 'Not supplied'),
            '- **Attachment:** ' . ($item['attachmentUrl'] ? 'Available in the secure administration portal' : 'Not supplied'),
            '',
            '### Details',
            mb_substr($item['details'], 0, 12000),
        ];
        if ($item['expectedOutcome']) {
            $lines[] = '';
            $lines[] = '### Expected outcome';
            $lines[] = mb_substr($item['expectedOutcome'], 0, 8000);
        }
        $lines[] = '';
        $lines[] = '_The submitter email and attachment URL remain private in the administration portal._';
        $lines[] = '_Created from the secure Growth Alignment feedback workflow._';
        return implode("\n", $lines);
    }

    private function variables(array $item, array $extra = []): array
    {
        return array_merge([
            'feedbackId' => (string) $item['id'],
            'feedbackTitle' => $item['title'],
            'feedbackStatus' => $this->humanStatus($item['status']),
            'feedbackModule' => $item['moduleName'],
            'feedbackPriority' => ucfirst($item['priority']),
            'feedbackDetails' => $item['details'],
            'submitterName' => $item['submitterName'],
            'submitterEmail' => $item['submitterEmail'],
            'githubReference' => $item['githubIssueUrl'] ?: ucfirst(str_replace('_', ' ', $item['githubSyncStatus'])),
            'supportEmail' => $this->supportEmail(),
            'clarificationMessage' => '',
            'resolution' => $item['resolution'] ?: 'The requested change has been completed.',
        ], $extra);
    }

    private function find(int $id): array
    {
        $row = $this->db->fetch(
            'SELECT f.id, f.public_uuid publicUuid, f.submitter_name submitterName, f.submitter_email submitterEmail, '
            . 'f.feedback_type feedbackType, f.module_name moduleName, f.priority, f.title, f.details, '
            . 'f.expected_outcome expectedOutcome, f.page_url pageUrl, f.attachment_url attachmentUrl, '
            . 'f.status, f.resolution, f.github_issue_number githubIssueNumber, f.github_issue_url githubIssueUrl, '
            . 'f.github_sync_status githubSyncStatus, f.github_last_error githubLastError, f.completed_at completedAt, '
            . 'f.created_at createdAt, f.updated_at updatedAt, u.display_name submittedByName '
            . 'FROM client_feedback f LEFT JOIN admin_users u ON u.id = f.submitted_by_admin_id WHERE f.id = ?',
            [$id]
        );
        if (!$row) throw new \RuntimeException('Feedback record not found.', 404);
        return $row;
    }

    private function addUpdate(
        int $feedbackId,
        ?int $adminId,
        string $type,
        ?string $status,
        string $message,
        bool $emailed = false,
        bool $githubSynced = false,
    ): void {
        $this->db->execute(
            'INSERT INTO client_feedback_updates (feedback_id, admin_user_id, update_type, status, message, emailed_at, github_synced_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $feedbackId,
                $adminId,
                $type,
                $status,
                mb_substr($message, 0, 10000),
                $emailed ? date('Y-m-d H:i:s') : null,
                $githubSynced ? date('Y-m-d H:i:s') : null,
            ]
        );
    }

    private function audit(?int $adminId, string $action, int $id, array $after): void
    {
        $this->db->execute(
            'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$adminId, $action, 'client_feedback', (string) $id, json_encode($after)]
        );
    }

    private function safePageUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) return null;
        $parts = parse_url($value);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) return null;
        $url = strtolower((string) $parts['scheme']) . '://' . $parts['host'];
        if (isset($parts['port'])) $url .= ':' . (int) $parts['port'];
        $url .= $parts['path'] ?? '/';
        return mb_substr($url, 0, 1000);
    }

    private function safeAttachmentUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || !filter_var($value, FILTER_VALIDATE_URL)) return null;
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? mb_substr($value, 0, 1000) : null;
    }

    private function humanStatus(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    private function clientEmail(): string
    {
        $value = strtolower(trim((string) $this->settings->get('feedback.client_email', 'sunil.setpaul@atomglobal.com')));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : 'sunil.setpaul@atomglobal.com';
    }

    private function internalEmail(): string
    {
        $value = strtolower(trim((string) $this->settings->get('feedback.internal_email', 'amit@axon.com.sg')));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    private function supportEmail(): string
    {
        $value = strtolower(trim((string) $this->settings->get('feedback.support_email', 'amit@axon.com.sg')));
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : 'amit@axon.com.sg';
    }
}
