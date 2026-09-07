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
        $pdfAccent = $trackKey === 'personal' ? $heart : $head;

        $summary = $free['summary']['summary'] ?? $free['summary'] ?? '';
        if (is_array($summary)) $summary = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $strengths = is_array($free['summary']['strengths'] ?? null) ? array_slice($free['summary']['strengths'], 0, 3) : [];
        $watchouts = is_array($free['summary']['watchouts'] ?? null) ? $free['summary']['watchouts'] : [];
        $scores = is_array($paid['subscales'] ?? null) ? $paid['subscales'] : (is_array($free['subscales'] ?? null) ? $free['subscales'] : []);
        $overallScore = max(0, min(250, (int) ($free['total'] ?? 0)));
        $overallWidth = max(0, min(100, (int) round(($overallScore / 250) * 100)));

        $brand = $logo
            ? '<img class="logo" src="' . $this->h($logo) . '" alt="Atom Global Consulting">'
            : '<div class="brand">ATOM GLOBAL CONSULTING</div>';

        $trackLabel = strtoupper((string) $row['track_name']);
        $participantName = trim((string) $row['participant_name']);
        $participantLead = $participantName !== ''
            ? $participantName . ', this result was calculated by the published assessment version from your saved responses.'
            : 'This result was calculated by the published assessment version from your saved responses.';
        $completed = trim((string) ($row['completed_at'] ?? ''));

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{margin:15mm 14mm 17mm}body{font-family:' . $this->css($body) . ';color:' . $this->css($ink) . ';font-size:9pt;line-height:1.48;background:' . $this->css($canvas) . ';margin:0}'
            . 'h1,h2,h3,h4{font-family:' . $this->css($heading) . ';page-break-after:avoid;color:#2B241D}h1{font-size:28pt;line-height:1.04;margin:1.5mm 0 2.5mm;color:#B54B3D}h2{font-size:16pt;margin:0 0 2.5mm}h3{font-size:13pt;margin:0 0 2mm}h4{font-size:10pt;margin:0 0 1.2mm}p{margin:0 0 2mm}ul,ol{padding-left:5mm;margin:1.5mm 0 0}li{margin-bottom:1.1mm}'
            . '.brand-row{width:100%;border-collapse:collapse;margin:0 0 3mm}.brand-row td{border:0;padding:0}.brand-cell{text-align:right;vertical-align:top}.logo{width:43mm;max-height:14mm;object-fit:contain}.brand{font-weight:bold;letter-spacing:.08em;color:' . $this->css($heart) . ';font-size:9pt;text-align:right}'
            . '.eyebrow{margin:0 0 1.5mm;color:#B54B3D;font-size:7.2pt;font-weight:bold;letter-spacing:.12em;text-transform:uppercase}.lead{margin:0 0 1mm;color:#4A4037;font-size:8.5pt;line-height:1.45}.completion-meta{margin:0 0 4mm;color:' . $this->css($muted) . ';font-size:7.2pt}'
            . '.hero{page-break-inside:avoid;background:#252832;color:#fff;padding:5mm;margin:4.5mm 0 4mm;border:1px solid #CAA34B;border-radius:6px}.hero-grid{width:100%;border-collapse:separate;border-spacing:4mm 0;table-layout:fixed}.hero-grid td{vertical-align:middle}.hero-score-cell{width:28%;height:36mm;padding:5mm;border:1px solid #D9B66A;background:#2A2B36;text-align:center;vertical-align:middle!important}.hero-copy{width:72%;padding:1mm 0 0 1mm;vertical-align:middle!important}.hero-copy h2{margin:0 0 2mm;color:#F2D78F;font-family:' . $this->css($body) . ';font-size:8.5pt;font-weight:bold;letter-spacing:.07em;text-transform:uppercase}.hero-copy p{margin:0;color:#fff;font-size:10.5pt;line-height:1.5}.score{font-family:' . $this->css($heading) . ';font-size:28pt;color:#fff;line-height:1;margin:0;text-align:center}.score span{display:block;margin-top:1.5mm;color:#F7EFE2;font-family:' . $this->css($body) . ';font-size:6.8pt;font-weight:bold;letter-spacing:.07em;text-align:center;text-transform:uppercase}.hero-meter-labels{width:100%;margin-top:4.5mm;border-collapse:collapse;color:#EEE7DC;font-size:5.8pt;font-weight:bold;line-height:1.2;text-transform:uppercase}.hero-meter-labels td{width:33.33%;padding:0;border:0}.hero-meter-labels td:first-child{text-align:left}.hero-meter-labels td:nth-child(2){text-align:center}.hero-meter-labels td:last-child{text-align:right}.hero-meter{height:3mm;margin-top:1.2mm;background:#62636B;border-radius:2mm;overflow:hidden}.hero-meter span{display:block;height:100%;background:#D8568C;background:linear-gradient(90deg,#5577FF 0%,#8E5DE7 48%,#EF4F6D 100%);border-radius:2mm}'
            . '.intro-grid,.edge-grid,.summary-grid,.score-grid,.feature-grid{width:100%;border-collapse:separate;border-spacing:2.5mm;table-layout:fixed}.intro-grid{margin:0 0 4mm}.intro-grid td{width:50%;vertical-align:top;padding:4mm;border:1px solid #E8DED2;border-radius:5px}.intro-strengths{border-top:3px solid #2F9E69!important;background:#F6FCF8}.intro-development{border-top:3px solid #B54B3D!important;background:#FFF7F5}.intro-grid h2{font-size:14.5pt}.intro-grid li{font-size:8.2pt}'
            . '.section-banner{page-break-inside:avoid;background:#27302F;color:#fff;padding:5mm 5.5mm;margin:5mm 0 3.5mm;border-radius:5px}.section-banner .block-eyebrow{color:#D6C6B5}.section-banner h2{color:#fff;margin:0;font-size:17pt}'
            . '.block-eyebrow{margin:0 0 1.2mm;color:#8A8178;font-size:6.7pt;font-weight:bold;letter-spacing:.09em;text-transform:uppercase}.report-block{page-break-inside:avoid;border:1px solid #E8DED2;border-left:3px solid ' . $this->css($pdfAccent) . ';border-radius:5px;padding:4.5mm;margin:3mm 0;background:#FFFDF9}.report-block p{color:#4A4037}.accent-blue{border-left-color:#3D82D8}.accent-pink{border-left-color:#D8568C}.accent-teal{border-left-color:#36A89B}.accent-orange{border-left-color:#D99035}.accent-purple{border-left-color:#7964D8}.accent-green{border-left-color:#2F9E69}.accent-gold{border-left-color:#CAA34B}'
            . '.executive-block{border-top:3px solid #CAA34B;background:#FFF9ED}.score-breakdown-block{border-top:3px solid #3D82D8}.edge-grid{margin:3mm 0}.edge-grid td{width:50%;vertical-align:top;border:1px solid #E8DED2;padding:4mm;background:#FFFDF9}.edge-grid td:first-child{border-top:3px solid #36A89B;background:#F7FCFB}.edge-grid td:last-child{border-top:3px solid #D99035;background:#FFFAF2}.summary-grid td{width:50%;vertical-align:top;border:1px solid #E8DED2;padding:3.5mm;background:#fff}.summary-grid td:first-child{border-top:3px solid #2F9E69;background:#F6FCF8}.summary-grid td:last-child{border-top:3px solid #B54B3D;background:#FFF7F5}'
            . '.subscale{page-break-inside:avoid;margin:2.2mm 0;padding:2.5mm 0;border-bottom:1px solid #EEE6DD}.subscale:last-child{border-bottom:0}.subscale-card{margin:2mm 0;padding:3mm;border:1px solid #E8DED2;border-left:3px solid #3D82D8;background:#fff;page-break-inside:avoid}.subscale-color-1{border-left-color:#3D82D8}.subscale-color-2{border-left-color:#D8568C}.subscale-color-3{border-left-color:#36A89B}.subscale-color-4{border-left-color:#D99035}.subscale-color-5{border-left-color:#7964D8}.comparison-row{border-bottom:1px solid #EEE6DD;padding:2mm 0}'
            . '.scale{height:3mm;background:#EEE7DE;border-radius:2mm;margin:1mm 0 2.2mm}.scale span{display:block;height:100%;background:' . $this->css($head) . ';border-radius:2mm}.scale-labels{width:100%;font-size:5.8pt;color:' . $this->css($muted) . ';line-height:1.2}.scale-labels td{border:0!important;padding:0!important;width:33.33%!important}.scale-labels td:nth-child(2){text-align:center}.scale-labels td:last-child{text-align:right}'
            . '.score-intro{font-size:7.8pt;color:' . $this->css($muted) . ';margin:1mm 0 3mm}.score-grid{border-spacing:2.2mm}.score-grid>tbody>tr>td{width:50%;vertical-align:top;padding:0;border:0}.score-item{border:1px solid #E8DED2;border-left:3px solid #3D82D8;background:#fff;padding:3mm;page-break-inside:avoid}.score-color-1{border-left-color:#3D82D8}.score-color-1 .scale span{background:#3D82D8}.score-color-2{border-left-color:#D8568C}.score-color-2 .scale span{background:#D8568C}.score-color-3{border-left-color:#36A89B}.score-color-3 .scale span{background:#36A89B}.score-color-4{border-left-color:#D99035}.score-color-4 .scale span{background:#D99035}.score-color-5{border-left-color:#7964D8}.score-color-5 .scale span{background:#7964D8}.score-item-head{width:100%;border-collapse:collapse;margin-bottom:1.5mm}.score-item-head td{border:0;padding:0;vertical-align:middle}.score-area{font-size:8pt;font-weight:bold;line-height:1.2}.score-value{text-align:right;font-size:7.5pt;font-weight:bold;white-space:nowrap}.score-legend{font-size:7.5pt;color:' . $this->css($muted) . ';background:#FFF8EE;padding:3mm;border:1px solid #EADCC7}'
            . '.roadmap-block{border-left-color:#CAA34B;background:#FFFCF5}.roadmap-block>h3{color:#8B6A1F}.roadmap-block .subscale-card{border-left-color:#CAA34B;background:#fff}.profile-block{border-left-color:#36A89B}.profile-block .subscale-card{border-left-color:#36A89B}.current-profile{border-left:4px solid #CAA34B!important;background:#FFF9EA!important}.reflection-block{border-left-color:#3D82D8}.reflection-block .subscale-card{border-left-color:#3D82D8}.methodology-block{border-left-color:#D99035}.methodology-block .subscale-card{border-left-color:#D99035}'
            . '.commitment-block{background:#27302F;color:#fff;border:0}.commitment-block .block-eyebrow{color:#DDD2C4}.commitment-block h3,.commitment-block p,.commitment-block strong{color:#fff}.retake-block{border-left-color:#7964D8;background:#F8F6FF}.coach-block{border-top:3px solid #CAA34B;border-left-color:#CAA34B;background:#FFF7DE}.upgrade-block{border-top:3px solid #CAA34B;background:#FFFDF9}.feature-grid td{width:50%;vertical-align:top;border:1px solid #E8DED2;padding:3mm;background:#fff}.feature-grid h4{margin-bottom:1mm}.final-note{font-size:7.3pt;color:' . $this->css($muted) . ';background:#FFFAF2;border:1px solid #E8DED2;padding:3mm;margin-top:3mm}'
            . '.footer{position:fixed;bottom:-10mm;left:0;right:0;color:' . $this->css($muted) . ';font-size:6.8pt;text-align:center}'
            . '</style></head><body>'
            . '<table class="brand-row"><tr><td></td><td class="brand-cell">' . $brand . '</td></tr></table>'
            . '<p class="eyebrow">GROWTH ALIGNMENT · ' . $this->h($trackLabel) . ' RESULT</p>'
            . '<h1>' . $this->h((string) ($free['profile'] ?? 'Growth Alignment Report')) . '</h1>'
            . '<p class="lead">' . $this->h($participantLead) . '</p>'
            . ($completed !== '' ? '<p class="completion-meta">Completed ' . $this->h($completed) . '</p>' : '<div style="height:2mm"></div>')
            . '<div class="hero"><table class="hero-grid"><tr><td class="hero-score-cell"><div class="score">' . $overallScore . '<span>Out of 250</span></div></td><td class="hero-copy"><h2>Your alignment pattern</h2><p>' . $this->h((string) $summary) . '</p><table class="hero-meter-labels"><tr><td>Head-led</td><td>' . $overallScore . '/250</td><td>Heart-led</td></tr></table><div class="hero-meter"><span style="width:' . $overallWidth . '%"></span></div></td></tr></table></div>'
            . $this->introCards($strengths, $watchouts)
            . '<div class="section-banner"><p class="block-eyebrow">Complete report</p><h2>Your full development report</h2></div>'
            . $this->renderRetakeComparison(is_array($content['retakeComparison'] ?? null) ? $content['retakeComparison'] : [], $trackKey)
            . $this->executiveSummary($scores, $trackKey)
            . $this->scoreBreakdownSection($scores, $trackKey, (string) ($content['radarLegend'] ?? ''))
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
            . $this->commitmentBlock((string) ($row['commitment_text'] ?? ''), (string) ($row['check_in_date'] ?? ''))
            . $this->retakePlan($trackKey)
            . $this->coachBlock()
            . $this->upgradeReasons($content['upgradeReasons'] ?? null)
            . '<div class="final-note">Your private Full Development Report reflects the same saved assessment result and development content shown on the website.</div>'
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

    private function introCards(array $strengths, array $watchouts): string
    {
        $strengthItems = implode('', array_map(fn($item) => '<li>' . $this->h((string) $item) . '</li>', $strengths));
        $watchItems = implode('', array_map(fn($item) => '<li>' . $this->h((string) $item) . '</li>', $watchouts));
        if ($strengthItems === '' && $watchItems === '') return '';
        return '<table class="intro-grid"><tr>'
            . '<td class="intro-strengths"><h2>Top three strengths</h2><ul>' . $strengthItems . '</ul></td>'
            . '<td class="intro-development"><h2>Development observations</h2><ul>' . $watchItems . '</ul></td>'
            . '</tr></table>';
    }

    private function textBlock(string $title, mixed $value): string
    {
        if (!is_scalar($value) || trim((string) $value) === '') return '';
        return '<div class="report-block ' . $this->accentClass($title) . '"><h3>' . $this->h($title) . '</h3><p>' . $this->h((string) $value) . '</p></div>';
    }

    private function listBlock(string $title, mixed $items, bool $ordered = false): string
    {
        if (!is_array($items) || !$items) return '';
        $values = array_values(array_filter(array_map(static fn($item): string => is_scalar($item) ? trim((string) $item) : '', $items)));
        if (!$values) return '';
        $tag = $ordered ? 'ol' : 'ul';
        return '<div class="report-block ' . $this->accentClass($title) . '"><h3>' . $this->h($title) . '</h3><' . $tag . '>' . implode('', array_map(fn($item) => '<li>' . $this->h($item) . '</li>', $values)) . '</' . $tag . '></div>';
    }

    private function mixedBlock(string $title, mixed $value, string $trackKey): string
    {
        if ($value === null || $value === '' || $value === []) return '';
        return '<div class="report-block ' . $this->accentClass($title) . '"><h3>' . $this->h($title) . '</h3>' . $this->renderValue($value, $trackKey) . '</div>';
    }

    private function scoreBreakdownSection(array $scores, string $trackKey, string $legend): string
    {
        if (!$scores) return '';
        $cards = [];
        $index = 0;
        foreach (array_slice($scores, 0, 10, true) as $code => $score) {
            $value = max(5, min(25, (int) $score));
            $width = max(0, min(100, (int) round((($value - 5) / 20) * 100)));
            $colourClass = 'score-color-' . (($index % 5) + 1);
            $cards[] = '<div class="score-item ' . $colourClass . '">'
                . '<table class="score-item-head"><tr><td class="score-area">' . $this->h($this->areaName($trackKey, (string) $code)) . '</td>'
                . '<td class="score-value">' . $value . '/25</td></tr></table>'
                . '<table class="scale-labels"><tr><td>5 · Head-led</td><td>15 · Balanced</td><td>25 · Heart-led</td></tr></table>'
                . '<div class="scale"><span style="width:' . $width . '%"></span></div></div>';
            $index++;
        }
        $rows = '';
        for ($i = 0; $i < count($cards); $i += 2) {
            $rows .= '<tr><td>' . $cards[$i] . '</td><td>' . ($cards[$i + 1] ?? '') . '</td></tr>';
        }
        return '<div class="report-block score-breakdown-block"><h3>Your 10-area score breakdown</h3>'
            . '<p class="score-intro">Compare all ten areas on the same scale: <strong>5 = more Head-led</strong>, <strong>15 = balanced</strong>, and <strong>25 = more Heart-led</strong>. The progress bars make the pattern easy to compare at a glance.</p>'
            . '<table class="score-grid"><tbody>' . $rows . '</tbody></table>'
            . ($legend !== '' ? '<p class="score-legend"><strong>How to read these scores:</strong> ' . $this->h($legend) . '</p>' : '') . '</div>';
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
                $value = max(5, min(25, (int) $item['score']));
                $width = max(0, min(100, (int) round((($value - 5) / 20) * 100)));
                $body .= '<div class="subscale"><strong>' . $this->h($this->areaName($trackKey, $item['code'])) . ' · ' . $value . '/25</strong>'
                    . '<table class="scale-labels"><tr><td>5 · Head-led</td><td>15 · Balanced</td><td>25 · Heart-led</td></tr></table>'
                    . '<div class="scale"><span style="width:' . $width . '%"></span></div></div>';
            }
            $cells .= '<td>' . $body . '</td>';
        }
        return '<div class="report-block executive-block"><p class="block-eyebrow">At a glance</p><h2>Executive Summary</h2><p>Your highest and lowest assessment areas show where your current pattern is strongest and where focused development may have the greatest value.</p><table class="summary-grid"><tr>' . $cells . '</tr></table></div>';
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
        $html = '<div class="report-block accent-blue"><h3>Your 10-area deep dive</h3>';
        $index = 0;
        foreach ($reads as $code => $value) {
            if (!is_scalar($value) || trim((string) $value) === '') continue;
            $colourClass = 'subscale-color-' . (($index % 5) + 1);
            $html .= '<div class="subscale-card ' . $colourClass . '"><h4>' . $this->h($this->areaName($trackKey, (string) $code)) . '</h4><p>' . $this->h((string) $value) . '</p></div>';
            $index++;
        }
        return $html . '</div>';
    }

    private function roadmap(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $html = '<div class="report-block roadmap-block"><h3>Development roadmap</h3><p>Choose two or three changes from this roadmap to practise consistently. The goal is not to change everything at once, but to build a small number of observable habits you can revisit.</p>';
        foreach (array_slice($items, 0, 5) as $index => $item) {
            if (!is_array($item)) continue;
            $title = (string) ($item['area'] ?? ('Development area ' . ($index + 1)));
            $detail = (string) ($item['insight'] ?? $item['summary'] ?? '');
            $html .= '<div class="subscale-card"><h4>' . $this->h($title) . '</h4>' . ($detail !== '' ? '<p>' . $this->h($detail) . '</p>' : '');
            if (is_array($item['steps'] ?? null)) $html .= '<ol>' . implode('', array_map(fn($step) => '<li>' . $this->h((string) $step) . '</li>', array_slice($item['steps'], 0, 3))) . '</ol>';
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private function profileSpectrum(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $html = '<div class="report-block profile-block"><h3>Understand the Head–Heart profile spectrum</h3><p>Your profile is one point on a four-profile spectrum. The highlighted definition is your current result; the others show the neighbouring patterns and score bands.</p>';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $class = !empty($item['current']) ? ' current-profile' : '';
            $html .= '<div class="subscale-card' . $class . '"><h4>' . (!empty($item['current']) ? 'Your profile — ' : '') . $this->h((string) ($item['name'] ?? 'Profile')) . ' · ' . (int) ($item['min'] ?? 0) . '–' . (int) ($item['max'] ?? 0) . '</h4>';
            if (!empty($item['summary'])) $html .= '<p>' . $this->h((string) $item['summary']) . '</p>';
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private function writtenReflections(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $html = '<div class="report-block reflection-block"><h3>Your written reflections</h3><p>These are the notes you chose to add while answering the assessment. They are included because your own context can be as important as the numerical pattern.</p>';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $html .= '<div class="subscale-card"><h4>Question ' . (int) ($item['questionPosition'] ?? 0) . '</h4>';
            if (!empty($item['question'])) $html .= '<p><strong>' . $this->h((string) $item['question']) . '</strong></p>';
            if (!empty($item['reflection'])) $html .= '<p>' . $this->h((string) $item['reflection']) . '</p>';
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private function methodology(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $html = '<div class="report-block methodology-block"><h3>Methodology and sourcing</h3>';
        foreach ($items as $title => $value) {
            if (!is_scalar($value) || trim((string) $value) === '') continue;
            $html .= '<div class="subscale-card"><h4>' . $this->h((string) $title) . '</h4><p>' . $this->h((string) $value) . '</p></div>';
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
        $html = '<div class="report-block accent-purple"><h3>Your progress since the previous assessment</h3><p><strong>Overall:</strong> ' . $previous . ' → ' . $current . ' (' . $this->h($signed) . ')</p>';
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
        $body = '<div class="report-block commitment-block"><p class="block-eyebrow">Make it actionable</p><h3>' . $this->h($heading) . '</h3><p>' . $this->h($prompt) . '</p>';
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
        return '<div class="report-block coach-block"><p class="block-eyebrow">Optional support</p><h3>' . $this->h($heading) . '</h3><p>' . $this->h($body) . '</p><p>' . $this->h($primary) . '<br>' . $this->h($secondary) . '</p></div>';
    }

    private function retakePlan(string $trackKey): string
    {
        $defaults = ['personal' => 299, 'newjoiner' => 995, 'manager' => 2995, 'executive' => 4995];
        $minor = max(0, (int) $this->settings->get('retest.price_' . $trackKey . '_minor', $defaults[$trackKey] ?? 299));
        $price = 'US$' . number_format($minor / 100, 2);
        return '<div class="report-block retake-block"><h3>3-month retake and progress check</h3><p>Commit to one or two development areas and work on them consistently. Retake the full 40-question assessment about three months after the original assessment so you can compare what shifted, what stayed stable, and where old patterns still show up under pressure.</p><p><strong>Retest price: ' . $this->h($price) . '.</strong></p></div>';
    }

    private function upgradeReasons(mixed $items): string
    {
        if (!is_array($items) || !$items) return '';
        $cards = [];
        foreach ($items as $index => $item) {
            if (is_scalar($item)) {
                $title = trim((string) $item);
                $detail = '';
            } elseif (is_array($item)) {
                $title = trim((string) ($item['title'] ?? $item['area'] ?? ('Full Report feature ' . ($index + 1))));
                $detail = trim((string) ($item['detail'] ?? $item['summary'] ?? $item['insight'] ?? ''));
            } else {
                continue;
            }
            if ($title === '') continue;
            $cards[] = '<div><h4>' . $this->h($title) . '</h4>' . ($detail !== '' ? '<p>' . $this->h($detail) . '</p>' : '') . '</div>';
        }
        if (!$cards) return '';
        $rows = '';
        for ($i = 0; $i < count($cards); $i += 2) {
            $rows .= '<tr><td>' . $cards[$i] . '</td><td>' . ($cards[$i + 1] ?? '') . '</td></tr>';
        }
        return '<div class="report-block upgrade-block"><h3>Use this report to</h3><table class="feature-grid"><tbody>' . $rows . '</tbody></table></div>';
    }

    private function accentClass(string $title): string
    {
        $value = strtolower($title);
        if (str_contains($value, 'strength')) return 'accent-green';
        if (str_contains($value, 'challenge')) return 'accent-pink';
        if (str_contains($value, 'development')) return 'accent-teal';
        if (str_contains($value, 'relationship')) return 'accent-blue';
        if (str_contains($value, 'working style') || str_contains($value, 'personal /')) return 'accent-orange';
        if (str_contains($value, 'working-style')) return 'accent-gold';
        if (str_contains($value, 'difficulty')) return 'accent-purple';
        if (str_contains($value, 'leadership')) return 'accent-blue';
        if (str_contains($value, 'culture')) return 'accent-teal';
        if (str_contains($value, 'everyday action')) return 'accent-green';
        return 'accent-blue';
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
