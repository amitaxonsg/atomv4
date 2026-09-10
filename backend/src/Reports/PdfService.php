<?php
declare(strict_types=1);

namespace AtomGlobal\Reports;

use AtomGlobal\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfService
{
    public function __construct(private Database $db, private string $storagePath) {}
    public function generate(int $reportId): string
    {
        $report = $this->db->fetch('SELECT r.*, p.name participant_name, t.name track_name FROM generated_reports r JOIN survey_sessions s ON s.id = r.survey_session_id JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id WHERE r.id = ? AND r.is_unlocked = 1', [$reportId]);
        if (!$report) throw new \RuntimeException('Unlocked report not found.');
        $content = json_decode($report['paid_report_json'], true, 512, JSON_THROW_ON_ERROR);
        $options = new Options(); $options->set('isRemoteEnabled', false); $dompdf = new Dompdf($options);
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;color:#2b251f;line-height:1.55}h1,h2{font-family:DejaVu Serif;color:#17313d}.score{font-size:42px;color:#c94d24}.section{page-break-inside:avoid;border-top:1px solid #ddd;padding-top:16px;margin-top:20px}</style></head><body><h1>Growth Alignment</h1><p>' . htmlspecialchars($report['participant_name']) . ' · ' . htmlspecialchars($report['track_name']) . '</p><p class="score">' . (int) $content['total'] . ' / 250</p><h2>' . htmlspecialchars($content['profile']) . '</h2><div class="section"><pre>' . htmlspecialchars(json_encode($content['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></div><p>This is a reflective self-assessment, not a clinical instrument.</p></body></html>';
        $dompdf->loadHtml($html); $dompdf->setPaper('A4'); $dompdf->render();
        $directory = rtrim($this->storagePath, '/') . '/reports'; if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new \RuntimeException('Unable to create report directory.');
        $path = $directory . '/report-' . $reportId . '-' . bin2hex(random_bytes(8)) . '.pdf'; file_put_contents($path, $dompdf->output(), LOCK_EX); chmod($path, 0640);
        $this->db->execute('UPDATE generated_reports SET pdf_path = ?, pdf_generated_at = NOW(), updated_at = NOW() WHERE id = ?', [$path, $reportId]); return $path;
    }
}
