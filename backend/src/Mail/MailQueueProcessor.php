<?php
declare(strict_types=1);

namespace AtomGlobal\Mail;

use AtomGlobal\Database;
use AtomGlobal\Services\PdfService;

final class MailQueueProcessor
{
    public function __construct(
        private Database $db,
        private MailQueue $queue,
        private MailDeliveryService $delivery,
        private PdfService $pdf,
    ) {}

    public function processDue(int $limit = 25): array
    {
        return $this->process($this->queue->due($limit));
    }

    public function processIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if (!$ids) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $items = $this->db->fetchAll(
            "SELECT * FROM email_queue WHERE id IN ({$placeholders}) AND status IN ('queued', 'retry') "
            . 'AND scheduled_at <= NOW() AND attempts < max_attempts ORDER BY id',
            $ids
        );
        return $this->process($items);
    }

    private function process(array $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $result = $this->processItem($item);
            if ($result !== null) $results[] = $result;
        }
        return $results;
    }

    private function processItem(array $item): ?array
    {
        $id = (int) $item['id'];
        $claimed = $this->db->execute(
            'UPDATE email_queue SET status = ?, attempts = attempts + 1, scheduled_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE) '
            . 'WHERE id = ? AND status IN (?, ?) AND scheduled_at <= NOW() AND attempts < max_attempts',
            ['retry', $id, 'queued', 'retry']
        );
        if ($claimed !== 1) return null;

        try {
            $item = $this->addPaidReportAttachment($item);
            $messageId = $this->delivery->deliver($item);
            $this->db->transaction(function () use ($item, $id, $messageId): void {
                $this->db->execute(
                    'UPDATE email_queue SET status = ?, sent_at = NOW(), provider_message_id = ?, failure_reason = NULL WHERE id = ?',
                    ['sent', $messageId, $id]
                );
                $this->db->execute(
                    'INSERT INTO email_logs (email_queue_id, recipient_email, template_key, status, provider_message_id, sent_at, created_at) '
                    . 'VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                    [$id, $item['recipient_email'], $item['template_key'], 'sent', $messageId]
                );
            });
            return ['id' => $id, 'templateKey' => $item['template_key'], 'status' => 'sent', 'messageId' => $messageId];
        } catch (\Throwable $error) {
            $attempts = (int) $item['attempts'] + 1;
            $final = $attempts >= (int) $item['max_attempts'];
            $status = $final ? 'failed' : 'retry';
            $delayMinutes = min(720, 5 * (2 ** min(7, $attempts)));
            $failure = mb_substr($error->getMessage(), 0, 2000);
            $this->db->transaction(function () use ($item, $id, $status, $delayMinutes, $failure): void {
                $this->db->execute(
                    'UPDATE email_queue SET status = ?, scheduled_at = DATE_ADD(NOW(), INTERVAL ? MINUTE), failure_reason = ? WHERE id = ?',
                    [$status, $delayMinutes, $failure, $id]
                );
                $this->db->execute(
                    'INSERT INTO email_logs (email_queue_id, recipient_email, template_key, status, failure_reason, created_at) '
                    . 'VALUES (?, ?, ?, ?, ?, NOW())',
                    [$id, $item['recipient_email'], $item['template_key'], $status, $failure]
                );
                if ($status === 'failed') {
                    $this->db->execute(
                        'INSERT INTO notification_events (event_key, severity, entity_type, entity_id, title, message, created_at) '
                        . 'VALUES (?, ?, ?, ?, ?, ?, NOW())',
                        ['email_failed', 'critical', 'email_queue', (string) $id, 'Email delivery failed', $failure]
                    );
                }
            });
            return ['id' => $id, 'templateKey' => $item['template_key'], 'status' => $status, 'failureReason' => $failure];
        }
    }

    private function addPaidReportAttachment(array $item): array
    {
        if (($item['template_key'] ?? '') !== 'paid_report_ready') return $item;

        $variables = json_decode((string) ($item['variables_json'] ?? '{}'), true) ?: [];
        $reportId = (int) ($variables['reportId'] ?? 0);
        if ($reportId <= 0) {
            throw new \RuntimeException('Paid report email is missing its Full Development Report id.');
        }

        $report = $this->db->fetch(
            'SELECT id, is_unlocked, pdf_path FROM generated_reports WHERE id = ? AND revoked_at IS NULL LIMIT 1',
            [$reportId]
        );
        if (!$report || !(bool) $report['is_unlocked']) {
            throw new \RuntimeException('Paid report email cannot attach a locked or unavailable Full Development Report.');
        }

        $pdfPath = (string) ($report['pdf_path'] ?? '');
        if ($pdfPath === '' || !is_file($pdfPath)) $pdfPath = $this->pdf->generate($reportId);
        $variables['_attachments'] = [[
            'path' => $pdfPath,
            'filename' => 'Growth-Alignment-Full-Development-Report.pdf',
            'mimetype' => 'application/pdf',
        ]];
        $item['variables_json'] = json_encode($variables, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $item;
    }
}
