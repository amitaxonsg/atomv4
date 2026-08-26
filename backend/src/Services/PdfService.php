<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PdfService
{
    public function __construct(private Database $db, private SettingsService $settings, private array $config) {}

    public function generate(int $reportId): string
    {
        $row = $this->db->fetch(
            'SELECT gr.*, p.name participant_name, p.email participant_email, t.name track_name, t.track_key, s.completed_at, rc.commitment_text, rc.check_in_date FROM generated_reports gr JOIN survey_sessions s ON s.id = gr.survey_session_id JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id LEFT JOIN report_commitments rc ON rc.generated_report_id = gr.id WHERE gr.id = ?',
            [$reportId]
        );
        if (!$row) throw new \RuntimeException('Report not found.', 404);
        if (!(bool) $row['is_unlocked']) throw new \RuntimeException('Full Development Report is locked.', 403);

        $free = json_decode((string) $row['free_report_json'], true, 512, JSON_THROW_ON_ERROR);
        $paid = json_decode((string) $row['paid_report_json'], true, 512, JSON_THROW_ON_ERROR);
        $content = is_array($paid['content'] ?? null) ? $paid['content'] : $paid;
        $trackKey = (string) $row['track_key'];

        $canvas = (string) $this->settings->get('branding.canvas', '#F7F4EF');
        $ink = (string) $this->settings->get('branding.text_primary', '#211C16');
        $muted = (string) $this->settings->get('branding.text_muted', '#726A5B');
        $heart = (string) $this->settings->get('branding.heart', '#C1443F');
        $head = (string) $this->settings->get('branding.head', '#6C8FAE');
        $gold = (string) $this->settings->get('branding.accent', '#C9A15A');
        $heading = (string) $this->settings->get('branding.heading_font', 'Georgia, Times New Roman, serif');
        $body = (string) $this->settings->get('branding.body_font', 'Arial, Helvetica, sans-serif');
        $logo = $this->logoDataUri((string) $this->settings->get('branding.report_logo_url', '/media/brand/atom-global-wordmark.png'));

        $summary = $free['summary']['summary'] ?? $free['summary'] ?? '';
        if (is_array($summary)) $summary = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $strengths = is_array($free['summary']['strengths'] ?? null) ? array_slice($free['summary']['strengths'], 0, 3) : [];
        $watchouts = is_array($free['summary']['watchouts'] ?? null) ? $free['summary']['watchouts'] : [];
        $scores = is_array($paid['subscales'] ?? null) ? $paid['subscales'] : (is_array($free['subscales'] ?? null) ? $free['subscales'] : []);

        $brand = $logo
            ? '<img class="logo" src="' . $this->h($logo) . '" alt="Atom Global Consulting">'
            : '<div class="brand">ATOM GLOBAL CONSULTING</div>';

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{margin:25mm 19mm 22mm}body{font-family:' . $this->css($body) . ';color:' . $this->css($ink) . ';font-size:10.5pt;line-height:1.55;background:#fff}'
            . 'h1,h2,h3,h4{font-family:' . $this->css($heading) . ';font-weight:normal;page-break-after:avoid}h1{font-size:28pt;margin:0 0 5mm}h2{font-size:18pt;margin:10mm 0 3mm;border-bottom:1px solid #ddd;padding-bottom:2mm}h3{font-size:14pt;margin:6mm 0 2mm}h4{font-size:11.5pt;margin:3mm 0 1mm}.logo{width:52mm;max-height:18mm;object-fit:contain}.brand{font-weight:bold;letter-spacing:.08em;color:' . $this->css($heart) . ';font-size:10pt}.meta{color:' . $this->css($muted) . ';font-size:8.8pt}.hero{background:' . $this->css($canvas) . ';padding:9mm;margin:7mm 0;border-left:3px solid ' . $this->css($gold) . '}.score{font-family:' . $this->css($heading) . ';font-size:27pt}.report-block{page-break-inside:avoid;border:1px solid #e4ddcf;border-radius:4px;padding:5mm;margin:4mm 0}.edge-grid,.summary-grid{width:100%;border-collapse:separate;border-spacing:4mm}.edge-grid td,.summary-grid td{width:50%;vertical-align:top;border:1px solid #e4ddcf;padding:5mm}.subscale{page-break-inside:avoid;margin:3mm 0}.comparison-row{border-bottom:1px solid #eee;padding:2mm 0}.scale{height:3mm;background:#eee;border-radius:2mm;margin:1mm 0 3mm}.scale span{display:block;height:100%;background:' . $this->css($head) . ';border-radius:2mm}.scale-labels{display:flex;justify-content:space-between;font-size:7pt;color:' . $this->css($muted) . '}.current-profile{border-left:3px solid ' . $this->css($gold) . ';padding-left:4mm}.radar{page-break-inside:avoid;text-align:center;margin:5mm 0}.radar-legend{font-size:9pt;color:' . $this->css($muted) . ';background:' . $this->css($canvas) . ';padding:4mm}.footer{position:fixed;bottom:-13mm;left:0;right:0;color:' . $this->css($muted) . ';font-size:8pt;text-align:center}ul,ol{padding-left:6mm;margin-top:2mm}li{margin-bottom:1.5mm}</style></head><body>'
            . $brand . '<p class="meta">GROWTH ALIGNMENT · ' . $this->h($row['track_name']) . '</p>'
            . '<h1>' . $this->h((string) ($free['profile'] ?? 'Growth Alignment Report')) . '</h1>'
            . '<p class="meta">Prepared for ' . $this->h((string) $row['participant_name']) . ' · Completed ' . $this->h((string) ($row['completed_at'] ?? '')) . '</p>'
            . '<div class="hero"><div class="score">' . (int) ($free['total'] ?? 0) . ' / 250</div><p>' . $this->h((string) $summary) . '</p></div>'
            . $this->section('Top three strengths', $strengths)
            . $this->section('Development observations', $watchouts)
            . '<h2>Full Development Report</h2>'
            . $this->executiveSummary($scores, $trackKey)
            . $this->radarSection($scores, $trackKey, (string) ($content['radarLegend'] ?? ''), $heart, $head)
            . $this->edgeSection($content, $trackKey)
            . $this->textBlock('Complete profile summary', $content['summary'] ?? '')
            . $this->listBlock('Full strengths list', $content['strengths'] ?? [])
            . $this->listBlock('Challenges and development areas', $content['watchouts'] ?? [])
            . $this->mixedBlock('Development areas', $content['developmentAreas'] ?? null, $trackKey)
            . $this->textBlock('Relationships / team', $content['relationships'] ?? '')
            . $this->textBlock('Personal / working style', $content['work'] ?? '')
            . $this->listBlock('Working-style actions', $content['workingStyleTips'] ?? [])
            . $this->textBlock('How you handle difficulty', $content['handlingDifficulty'] ?? '')
            . $this->textBlock((string) ($content['leadershipImpactLabel'] ?? 'Leadership impact'), $content['leadershipImpact'] ?? '')
            . $this->textBlock((string) ($content['cultureFitLabel'] ?? 'Culture fit reflection'), $content['cultureFitPrompt'] ?? '')
            . $this->listBlock('Five practical everyday actions', array_slice(is_array($content['growth'] ?? null) ? $content['growth'] : [], 0, 5), true)
            . $this->subscaleReads($content['subscaleReads'] ?? null, $trackKey)
            . $this->roadmap($content['roadmap'] ?? null)
            . $this->profileSpectrum($content['profileSpectrum'] ?? null)
            . $this->writtenReflections($content['writtenReflections'] ?? null)
            . $this->methodology($content['methodology'] ?? null)
            . $this->renderRetakeComparison(is_array($content['retakeComparison'] ?? null) ? $content['retakeComparison'] : [], $trackKey)
            . $this->commitmentBlock((string) ($row['commitment_text'] ?? ''), (string) ($row['check_in_date'] ?? ''))
            . $this->retakePlan($trackKey)
            . $this->coachBlock()
            . '<div class="footer">Growth Alignment by Atom Global Consulting · Private and confidential</div></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        $directory = rtrim((string) $this->config['storage'], '/') . '/reports';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new \RuntimeException('Report storage is unavailable.');
        $path = $directory . '/report-' . $reportId . '-' . bin2hex(random_bytes(8)) . '.pdf';
        file_put_contents($path, $dompdf->output(), LOCK_EX);
        chmod($path, 0640);
        $this->db->execute('UPDATE generated_reports SET pdf_path = ?, pdf_generated_at = NOW(), updated_at = NOW() WHERE id = ?', [$path, $reportId]);
        return $path;
    }

    private function logoDataUri(string $url): ?string
    {
        $path = null;
        if (str_starts_with($url, '/media-uploads/')) {
            $path = rtrim((string) $this->config['storage'], '/') . '/media/' . basename($url);
        } elseif (str_starts_with($url, '/')) {
            $path = dirname(__DIR__, 3) . '/frontend' . $url;
        }
        if (!$path || !is_file($path) || filesize($path) > 2 * 1024 * 1024) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'image/png';
        if (!str_starts_with($mime, 'image/')) return null;
        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    private function section(string $title, array $items, bool $ordered = false): string
    {
        if (!$items) return '';
        $tag = $ordered ? 'ol' : 'ul';
        return '<h2>' . $this->h($title) . '</h2><' . $tag . '>' . implode('', array_map(fn($item) => '<li>' . $this->h((string) $item) . '</li>', $items)) . '</' . $tag . '>';
    }

    private function textBlock(string $title, mixed $value): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') return '';
        return '<div class="report-block"><h3>' . $this->h($title) . '</h3><p>' . $this->h((string) $value) . '</p></div>';
    }

    private function listBlock(string $title, mixed $items, bool $ordered = false): string
    {
        if (!is_array($items) || !$items) return '';
        $values = array_values(array_filter(array_map(static fn($item): string => is_scalar($item) ? trim((string) $item) : '', $items)));
        if (!$values) return '';
        $tag = $ordered ? 'ol' : 'ul';
        return '<div class="report-block"><h3>' . $this->h($title) . '</h3><' . $tag . '>' . implode('', array_map(fn($item) => '<li>' . $this->h($item) . '</li>', $values)) . '</' . $tag . '></div>';
    }

    private function mixedBlock(string $title, mixed $value, string $trackKey): string
    {
        if ($value === null || $value === '' || $value === []) return '';
        return '<div class="report-block"><h3>' . $this->h($title) . '</h3>' . $this->renderValue($value, $trackKey) . '</div>';
    }

    private function radarSection(array $scores, string $trackKey, string $legend, string $heart, string $head): string
    {
        if (!$scores) return '';
        $chart = $this->radarSvg($scores, $trackKey, $heart, $head);
        $list = [];
        foreach ($scores as $code => $score) $list[] = $this->areaName($trackKey, (string) $code) . ': ' . (int) $score . ' / 25';
        return '<div class="report-block"><h3>Your 10-area radar and score breakdown</h3><div class="radar">' . $chart . '</div><ul>'
            . implode('', array_map(fn($item) => '<li>' . $this->h($item) . '</li>', $list)) . '</ul>'
            . ($legend !== '' ? '<p class="radar-legend"><strong>How to read the chart:</strong> ' . $this->h($legend) . '</p>' : '') . '</div>';
    }

    private function executiveSummary(array $scores, string $trackKey): string
    {
        if (count($scores) < 6) return '';
        $items = [];
        foreach ($scores as $code => $score) $items[] = ['code' => (string) $code, 'score' => (int) $score];
        usort($items, static fn(array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['code'], $b['code']));
        $groups = ['Highest 3' => array_slice($items, 0, 3), 'Lowest 3' => array_reverse(array_slice($items, -3))];
        $cells = '';
        foreach ($groups as $title => $group) {
            $body = '<h3>' . $this->h($title) . '</h3>';
            foreach ($group as $item) {
                $width = max(0, min(100, (int) round($item['score'] / 25 * 100)));
                $body .= '<div class="subscale"><strong>' . $this->h($this->areaName($trackKey, $item['code'])) . ' · ' . $item['score'] . '/25</strong><div class="scale-labels"><span>Low</span><span>Mid</span><span>High</span></div><div class="scale"><span style="width:' . $width . '%"></span></div></div>';
            }
            $cells .= '<td>' . $body . '</td>';
        }
        return '<div class="report-block"><h2>Executive Summary</h2><p>Your three highest and three lowest assessment areas show current strengths and focused development opportunities.</p><table class="summary-grid"><tr>' . $cells . '</tr></table></div>';
    }

    private function radarSvg(array $scores, string $trackKey, string $heart, string $head): string
    {
        $entries = array_slice(array_values(array_map(static fn($code, $score): array => ['code' => (string) $code, 'score' => (int) $score], array_keys($scores), array_values($scores))), 0, 10);
        if (count($entries) < 3) return '';
        $cx = 210.0; $cy = 205.0; $radius = 125.0; $count = count($entries);
        $rings = '';
        foreach ([0.2, 0.4, 0.6, 0.8, 1.0] as $factor) {
            $points = [];
            for ($i = 0; $i < $count; $i++) {
                $angle = -M_PI / 2 + (2 * M_PI * $i / $count);
                $points[] = round($cx + cos($angle) * $radius * $factor, 2) . ',' . round($cy + sin($angle) * $radius * $factor, 2);
            }
            $rings .= '<polygon points="' . implode(' ', $points) . '" fill="none" stroke="#D8D8D8" stroke-width="1" />';
        }
        $axes = ''; $labels = ''; $dataPoints = [];
        foreach ($entries as $i => $entry) {
            $angle = -M_PI / 2 + (2 * M_PI * $i / $count);
            $x = $cx + cos($angle) * $radius; $y = $cy + sin($angle) * $radius;
            $axes .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . round($x, 2) . '" y2="' . round($y, 2) . '" stroke="#D8D8D8" stroke-width="1" />';
            $labelX = $cx + cos($angle) * ($radius + 42); $labelY = $cy + sin($angle) * ($radius + 42);
            $short = $entry['code'];
            $labels .= '<text x="' . round($labelX, 2) . '" y="' . round($labelY, 2) . '" text-anchor="middle" font-size="9" fill="#555">' . $this->h($short) . '</text>';
            $normalised = max(0, min(1, ($entry['score'] - 5) / 20));
            $dataRadius = $radius * $normalised;
            $dataPoints[] = round($cx + cos($angle) * $dataRadius, 2) . ',' . round($cy + sin($angle) * $dataRadius, 2);
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="410" viewBox="0 0 420 410">'
            . $rings . $axes
            . '<polygon points="' . implode(' ', $dataPoints) . '" fill="' . $this->h($heart) . '" fill-opacity="0.18" stroke="' . $this->h($head) . '" stroke-width="2" />'
            . $labels . '</svg>';
    }

    private function edgeSection(array $content, string $trackKey): string
    {
        $cards = '';
        foreach ([['Sharpest Edge', $content['sharpestEdge'] ?? null], ['Growth Edge', $content['growthEdge'] ?? null]] as [$title, $edge]) {
            if (!is_array($edge) || empty($edge['code'])) continue;
            $cards .= '<td><h3>' . $this->h($title) . '</h3><h4>' . $this->h($this->areaName($trackKey, (string) $edge['code'])) . ' · ' . (int) ($edge['score'] ?? 0) . '/25</h4><p>' . $this->h((string) ($edge['meaning'] ?? '')) . '</p></td>';
        }
        return $cards ? '<table class="edge-grid"><tr>' . $cards . '</tr></table>' : '';
    }

    private function subscaleReads(mixed $reads, string $trackKey): string
    {
        if (!is_array($reads) || !$reads) return '';
        $html = '<div class="report-block"><h3>Your 10-area deep dive</h3>';
        foreach ($reads as $code => $value) {
            if (!is_scalar($value) || trim((string) $value) === '') continue;
            $html .= '<div class="subscale"><h4>' . $this->h($this->areaName($trackKey, (string) $code)) . '</h4><p>' . $this->h((string) $value) . '</p></div>';
        }
        return $html . '</div>';
    }

    private function roadmap(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $html = '<div class="report-block"><h3>Development roadmap</h3><p>Focus on three to five areas and practise a small number of observable steps consistently.</p>';
        foreach (array_slice($items, 0, 5) as $index => $item) {
            if (!is_array($item)) continue;
            $title = (string) ($item['area'] ?? ('Development area ' . ($index + 1)));
            $detail = (string) ($item['insight'] ?? $item['summary'] ?? '');
            $html .= '<div class="subscale"><h4>' . $this->h($title) . '</h4>' . ($detail !== '' ? '<p>' . $this->h($detail) . '</p>' : '');
            if (is_array($item['steps'] ?? null)) $html .= '<ol>' . implode('', array_map(fn($step) => '<li>' . $this->h((string) $step) . '</li>', array_slice($item['steps'], 0, 3))) . '</ol>';
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private function profileSpectrum(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $html = '<div class="report-block"><h3>Understand the Head–Heart profile spectrum</h3><p>Your current profile is highlighted below. The other definitions show the neighbouring patterns and score ranges.</p>';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $class = !empty($item['current']) ? ' current-profile' : '';
            $html .= '<div class="subscale' . $class . '"><h4>' . (!empty($item['current']) ? 'Your profile — ' : '') . $this->h((string) ($item['name'] ?? 'Profile')) . ' · ' . (int) ($item['min'] ?? 0) . '–' . (int) ($item['max'] ?? 0) . '</h4>';
            if (!empty($item['summary'])) $html .= '<p>' . $this->h((string) $item['summary']) . '</p>';
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private function writtenReflections(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $html = '<div class="report-block"><h3>Your written reflections</h3><p>Your own notes are included as context for the numerical pattern.</p>';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $html .= '<div class="subscale"><h4>Question ' . (int) ($item['questionPosition'] ?? 0) . '</h4>';
            if (!empty($item['question'])) $html .= '<p><strong>' . $this->h((string) $item['question']) . '</strong></p>';
            if (!empty($item['reflection'])) $html .= '<p>' . $this->h((string) $item['reflection']) . '</p>';
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private function methodology(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $html = '<div class="report-block"><h3>Methodology and sourcing</h3>';
        foreach ($items as $title => $value) {
            if (!is_scalar($value) || trim((string) $value) === '') continue;
            $html .= '<div class="subscale"><h4>' . $this->h((string) $title) . '</h4><p>' . $this->h((string) $value) . '</p></div>';
        }
        return $html . '</div>';
    }

    private function renderRetakeComparison(array $comparison, string $trackKey): string
    {
        if (!$comparison) return '';
        $previous = (int) ($comparison['previousTotal'] ?? 0);
        $current = (int) ($comparison['currentTotal'] ?? 0);
        $change = (int) ($comparison['totalChange'] ?? ($current - $previous));
        $signed = $change > 0 ? '+' . $change : (string) $change;
        $html = '<div class="report-block"><h3>Your progress since the previous assessment</h3><p><strong>Overall:</strong> ' . $previous . ' → ' . $current . ' (' . $this->h($signed) . ')</p>';
        foreach (($comparison['areas'] ?? []) as $area) {
            if (!is_array($area)) continue;
            $areaChange = (int) ($area['change'] ?? 0);
            $areaSigned = $areaChange > 0 ? '+' . $areaChange : (string) $areaChange;
            $html .= '<div class="comparison-row"><strong>' . $this->h($this->areaName($trackKey, (string) ($area['code'] ?? ''))) . '</strong>: ' . (int) ($area['previous'] ?? 0) . ' → ' . (int) ($area['current'] ?? 0) . ' (' . $this->h($areaSigned) . ')</div>';
        }
        if (!empty($comparison['guidance'])) $html .= '<p>' . $this->h((string) $comparison['guidance']) . '</p>';
        return $html . '</div>';
    }

    private function renderValue(mixed $value, string $trackKey): string
    {
        if (!is_array($value)) return '<p>' . $this->h((string) $value) . '</p>';
        if (array_is_list($value)) {
            $html = '<ul>';
            foreach ($value as $item) {
                if (is_scalar($item)) $html .= '<li>' . $this->h((string) $item) . '</li>';
            }
            return $html . '</ul>';
        }
        $html = '';
        foreach ($value as $key => $item) {
            $label = preg_match('/^[A-Z]{2}$/', (string) $key) ? $this->areaName($trackKey, (string) $key) : (string) $key;
            $html .= '<div class="subscale"><h4>' . $this->h($label) . '</h4>' . $this->renderValue($item, $trackKey) . '</div>';
        }
        return $html;
    }

    private function commitmentBlock(string $text, string $date): string
    {
        $heading = (string) $this->settings->get('reports.commitment_heading', 'My 90-day development commitment');
        $prompt = (string) $this->settings->get('reports.commitment_prompt', 'Choose one or two development areas and write down the action you will practise consistently.');
        $body = '<div class="report-block"><h3>' . $this->h($heading) . '</h3><p>' . $this->h($prompt) . '</p>';
        if ($text !== '') $body .= '<p><strong>' . $this->h($text) . '</strong></p>';
        if ($date !== '') $body .= '<p>Suggested check-in: ' . $this->h($date) . '</p>';
        return $body . '</div>';
    }

    private function coachBlock(): string
    {
        $heading = (string) $this->settings->get('reports.coach_heading', 'Talk to a Coach');
        $body = (string) $this->settings->get('reports.coach_body', 'Turn your report into a focused development plan with an Atom Global coach.');
        $primary = (string) $this->settings->get('reports.coach_primary_name', 'Reeta Nathwani') . ' — ' . (string) $this->settings->get('reports.coach_primary_email', 'reeta.nathwani@atomglobal.com');
        $secondary = (string) $this->settings->get('reports.coach_secondary_name', 'Sunil Setpaul') . ' — ' . (string) $this->settings->get('reports.coach_secondary_email', 'sunil.setpaul@atomglobal.com');
        return '<div class="report-block"><h3>' . $this->h($heading) . '</h3><p>' . $this->h($body) . '</p><p>' . $this->h($primary) . '<br>' . $this->h($secondary) . '</p></div>';
    }

    private function retakePlan(string $trackKey): string
    {
        $defaults = ['personal' => 299, 'newjoiner' => 995, 'manager' => 2995, 'executive' => 4995];
        $minor = max(0, (int) $this->settings->get('retest.price_' . $trackKey . '_minor', $defaults[$trackKey] ?? 299));
        $price = 'US$' . number_format($minor / 100, 2);
        return '<div class="report-block"><h3>90-day retest and progress check</h3><p>Commit to one or two development areas and practise them consistently. The retest becomes available 90 days after the original paid assessment and the new Full Development Report compares both results.</p><p><strong>Retest price: ' . $this->h($price) . '.</strong></p></div>';
    }

    private function areaName(string $trackKey, string $code): string
    {
        $areas = [
            'personal' => ['DM' => 'Decision-Making', 'RC' => 'Relationships & Connection', 'EA' => 'Emotional Awareness', 'CN' => 'Conflict Navigation', 'TI' => 'Trust & Intuition', 'EC' => 'Empathy & Compassion', 'AE' => 'Authentic Self-Expression', 'SP' => 'Stress & Pressure Response', 'VP' => 'Values & Life Priorities', 'CS' => 'Communication Style'],
            'newjoiner' => ['DM' => 'Decision-Making as You Start Out', 'RC' => 'Building Relationships at a New Job', 'EA' => 'Emotional Awareness in a New Environment', 'CN' => 'Handling Feedback & Early Conflict', 'TI' => 'Trust & Intuition as a Newcomer', 'EC' => 'Empathy for Your New Team', 'AE' => 'Authentic Presence as the New Person', 'SP' => 'Pressure & Imposter Moments', 'VP' => 'What You’re Optimizing For Early On', 'CS' => 'Communication as a New Team Member'],
            'manager' => ['DM' => 'Decision-Making', 'RC' => 'Team Relationships & Trust', 'EA' => 'Emotional Awareness at Work', 'CN' => 'Conflict & Difficult Conversations', 'TI' => 'Trust & Intuition About People', 'EC' => 'Empathy for Your Team', 'AE' => 'Authentic Leadership', 'SP' => 'Stress & Pressure at Work', 'VP' => 'What You’re Optimizing For', 'CS' => 'Communication as a Manager'],
            'executive' => ['DM' => 'Strategic Decision-Making', 'RC' => 'Executive Trust & Relationships', 'EA' => 'Emotional Awareness in the C-Suite', 'CN' => 'High-Stakes Conflict & Negotiation', 'TI' => 'Trust & Intuition on Big Bets', 'EC' => 'Empathy at Scale', 'AE' => 'Authentic Executive Presence', 'SP' => 'Pressure at the Top', 'VP' => 'What You’re Building For', 'CS' => 'Communication as an Executive'],
        ];
        return $areas[$trackKey][$code] ?? $code;
    }

    private function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private function css(string $value): string { return str_replace(['<', '>', '"', "'", '\\'], '', $value); }
}
