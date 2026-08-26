<?php
declare(strict_types=1);

namespace AtomGlobal\Mail;

use AtomGlobal\Database;
use AtomGlobal\Services\SettingsService;
use PHPMailer\PHPMailer\PHPMailer;

final class MailDeliveryService
{
    public function __construct(private Database $db, private SettingsService $settings) {}

    public function deliver(array $queue): string
    {
        $template = $this->db->fetch('SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1', [$queue['template_key']]);
        if (!$template && $queue['template_key'] === 'admin_test') {
            $variables = json_decode($queue['variables_json'], true) ?: [];
            $template = [
                'subject' => $variables['subject'] ?? 'Atom Global email test',
                'html_body' => '<p>' . htmlspecialchars((string) ($variables['message'] ?? 'Email configuration test.'), ENT_QUOTES, 'UTF-8') . '</p>',
                'text_body' => (string) ($variables['message'] ?? 'Email configuration test.'),
            ];
        }
        if (!$template) throw new \RuntimeException('Email template is unavailable: ' . $queue['template_key']);

        $variables = json_decode($queue['variables_json'], true) ?: [];
        $attachments = $this->normaliseAttachments($variables['_attachments'] ?? []);
        unset($variables['_attachments']);
        $subject = $this->render((string) $template['subject'], $variables);
        $content = $this->render((string) $template['html_body'], $variables);
        $html = $this->brandHtml($content, $subject);
        $text = $this->render((string) $template['text_body'], $variables);
        $provider = strtolower(trim((string) $this->settings->get('email.provider', '')));
        if (!in_array($provider, ['smtp2go', 'smtp'], true)) {
            throw new \RuntimeException('CMS email delivery provider is missing or invalid.');
        }

        return $provider === 'smtp2go'
            ? $this->sendSmtp2Go($queue['recipient_email'], $subject, $html, $text, $attachments)
            : $this->sendSmtp($queue['recipient_email'], $subject, $html, $text, $attachments);
    }

    private function sendSmtp2Go(string $recipient, string $subject, string $html, string $text, array $attachments = []): string
    {
        $apiKey = trim((string) $this->settings->get('email.smtp2go_api_key', ''));
        if ($apiKey === '') throw new \RuntimeException('CMS SMTP2GO API key is not configured.');
        $endpoint = $_ENV['SMTP2GO_API_URL'] ?? 'https://api.smtp2go.com/v3/email/send';
        $payload = [
            'api_key' => $apiKey,
            'sender' => $this->senderIdentity(),
            'to' => [$recipient],
            'subject' => $subject,
            'html_body' => $html,
            'text_body' => $text,
        ];
        if ($attachments) {
            $payload['attachments'] = array_map(static function (array $attachment): array {
                $data = file_get_contents($attachment['path']);
                if ($data === false) throw new \RuntimeException('Unable to read email attachment.');
                return [
                    'filename' => $attachment['filename'],
                    'fileblob' => base64_encode($data),
                    'mimetype' => $attachment['mimetype'],
                ];
            }, $attachments);
        }
        $replyTo = $this->replyTo();
        if ($replyTo !== '') $payload['custom_headers'] = [['header' => 'Reply-To', 'value' => $replyTo]];

        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($response === false || $status < 200 || $status >= 300) throw new \RuntimeException('SMTP2GO delivery failed: ' . ($error ?: (string) $response));
        $decoded = json_decode((string) $response, true) ?: [];
        return (string) ($decoded['data']['email_id'] ?? $decoded['request_id'] ?? 'smtp2go-' . bin2hex(random_bytes(8)));
    }

    private function sendSmtp(string $recipient, string $subject, string $html, string $text, array $attachments = []): string
    {
        $host = trim((string) $this->settings->get('email.smtp_host', ''));
        $username = trim((string) $this->settings->get('email.smtp_username', ''));
        $password = (string) $this->settings->get('email.smtp_password', '');
        if ($host === '' || $username === '' || $password === '') {
            throw new \RuntimeException('CMS SMTP host, username or password is missing.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) $this->settings->get('email.smtp_port', 587);
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $encryption = trim((string) $this->settings->get('email.smtp_encryption', 'tls'));
        if ($encryption !== '') $mail->SMTPSecure = $encryption;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($this->fromAddress(), $this->fromName());
        if ($this->replyTo() !== '') $mail->addReplyTo($this->replyTo());
        $mail->addAddress($recipient);
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment['path'], $attachment['filename'], PHPMailer::ENCODING_BASE64, $attachment['mimetype']);
        }
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
        return $mail->getLastMessageID() ?: 'smtp-' . bin2hex(random_bytes(8));
    }

    private function normaliseAttachments(mixed $items): array
    {
        if (!is_array($items)) return [];
        $result = [];
        foreach (array_slice($items, 0, 3) as $item) {
            if (!is_array($item)) continue;
            $path = realpath((string) ($item['path'] ?? ''));
            if ($path === false || !is_file($path) || !is_readable($path)) throw new \RuntimeException('Email attachment is missing or unreadable.');
            $size = filesize($path);
            if ($size === false || $size > 20 * 1024 * 1024) throw new \RuntimeException('Email attachment exceeds the 20 MB application limit.');
            $filename = basename((string) ($item['filename'] ?? basename($path)));
            if ($filename === '' || $filename === '.' || $filename === '..') $filename = 'attachment.pdf';
            $mimetype = trim((string) ($item['mimetype'] ?? 'application/pdf')) ?: 'application/pdf';
            $result[] = ['path' => $path, 'filename' => $filename, 'mimetype' => $mimetype];
        }
        return $result;
    }

    private function brandHtml(string $content, string $subject): string
    {
        if (stripos($content, '<html') !== false) return $content;

        $baseUrl = rtrim((string) $this->settings->get('email.public_base_url', 'https://head-heart.atomglobal.com'), '/');
        $logo = (string) $this->settings->get('email.logo_url', $this->settings->get('branding.email_logo_url', '/media/brand/atom-global-wordmark.png'));
        $logo = $this->absoluteUrl($logo, $baseUrl);
        $websiteUrl = $this->absoluteUrl((string) $this->settings->get('email.website_url', '/'), $baseUrl);
        $privacyUrl = $this->absoluteUrl((string) $this->settings->get('email.privacy_url', '/privacy'), $baseUrl);
        $termsUrl = $this->absoluteUrl((string) $this->settings->get('email.terms_url', '/terms'), $baseUrl);
        $footer = (string) $this->settings->get('email.footer_text', 'Growth Alignment by Atom Global Consulting');

        $colour = static fn(mixed $value, string $fallback): string => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value) ? strtoupper((string) $value) : $fallback;
        $font = static function (mixed $value, string $fallback): string {
            $clean = preg_replace('/[^A-Za-z0-9\s,"\'\.\-]/', '', (string) $value);
            return trim((string) $clean) !== '' ? trim((string) $clean) : $fallback;
        };
        $canvas = $colour($this->settings->get('branding.canvas', '#F7F4EF'), '#F7F4EF');
        $surface = $colour($this->settings->get('branding.surface', '#FFFFFF'), '#FFFFFF');
        $ink = $colour($this->settings->get('branding.text_primary', '#211C16'), '#211C16');
        $muted = $colour($this->settings->get('branding.text_muted', '#726A5B'), '#726A5B');
        $border = $colour($this->settings->get('branding.border', '#E4DDCF'), '#E4DDCF');
        $cta = $colour($this->settings->get('branding.cta', '#C9A15A'), '#C9A15A');
        $heart = $colour($this->settings->get('branding.heart', '#C1443F'), '#C1443F');
        $heading = $font($this->settings->get('branding.heading_font', 'Georgia, Times New Roman, serif'), 'Georgia, Times New Roman, serif');
        $body = $font($this->settings->get('branding.body_font', 'Arial, Helvetica, sans-serif'), 'Arial, Helvetica, sans-serif');
        $cardRadius = max(0, min(32, (int) $this->settings->get('branding.card_radius', 8)));
        $buttonRadius = max(0, min(32, (int) $this->settings->get('branding.button_radius', 8)));

        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeLogo = htmlspecialchars($logo, ENT_QUOTES, 'UTF-8');
        $safeWebsite = htmlspecialchars($websiteUrl, ENT_QUOTES, 'UTF-8');
        $safePrivacy = htmlspecialchars($privacyUrl, ENT_QUOTES, 'UTF-8');
        $safeTerms = htmlspecialchars($termsUrl, ENT_QUOTES, 'UTF-8');
        $safeFooter = htmlspecialchars($footer, ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $safeSubject . '</title><style>'
            . 'body{margin:0;background:' . $canvas . ';color:' . $ink . ';font-family:' . $body . '}'
            . '.ag-wrap{width:100%;padding:32px 14px;background:' . $canvas . '}'
            . '.ag-card{width:100%;max-width:620px;margin:0 auto;background:' . $surface . ';border:1px solid ' . $border . ';border-top:4px solid ' . $heart . ';border-radius:' . $cardRadius . 'px;overflow:hidden}'
            . '.ag-head{padding:32px 34px 18px;text-align:center}.ag-head img{display:block;width:190px;max-width:70%;height:auto;margin:0 auto 18px}'
            . '.ag-kicker{margin:0;color:' . $heart . ';font-size:11px;font-weight:700;letter-spacing:2px;text-transform:uppercase}'
            . '.ag-content{padding:10px 38px 34px;color:' . $ink . ';font-size:15px;line-height:1.7}'
            . '.ag-content p{margin:0 0 18px}.ag-content h1,.ag-content h2{font-family:' . $heading . ';font-weight:400;color:' . $ink . '}'
            . '.ag-content a{color:' . $ink . '!important;background:' . $cta . ';display:inline-block;padding:12px 20px;border-radius:' . $buttonRadius . 'px;text-decoration:none;font-weight:700}'
            . '.ag-foot{padding:22px 28px;background:' . $canvas . ';border-top:1px solid ' . $border . ';text-align:center;color:' . $muted . ';font-size:11px;line-height:1.6}'
            . '.ag-foot a{color:' . $muted . ';text-decoration:underline;margin:0 7px}'
            . '@media(max-width:640px){.ag-wrap{padding:14px 8px}.ag-head{padding:26px 20px 14px}.ag-content{padding:8px 22px 28px}}'
            . '</style></head><body><div class="ag-wrap"><div class="ag-card">'
            . '<div class="ag-head"><img src="' . $safeLogo . '" alt="Atom Global"><p class="ag-kicker">Growth Alignment</p></div>'
            . '<div class="ag-content">' . $content . '</div>'
            . '<div class="ag-foot"><strong>' . $safeFooter . '</strong><br>'
            . '<a href="' . $safeWebsite . '">Website</a><a href="' . $safePrivacy . '">Privacy</a><a href="' . $safeTerms . '">Terms</a><br>'
            . 'This message was sent because you started, completed or administer a Growth Alignment assessment.'
            . '</div></div></div></body></html>';
    }

    private function absoluteUrl(string $value, string $baseUrl): string
    {
        $value = trim($value);
        if ($value === '') return $baseUrl;
        if (preg_match('#^https?://#i', $value)) return $value;
        return $baseUrl . '/' . ltrim($value, '/');
    }

    private function render(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $content = str_replace(['{{' . $key . '}}', '{{ ' . $key . ' }}'], (string) $value, $content);
            }
        }
        return $content;
    }

    private function senderIdentity(): string
    {
        return $this->fromName() . ' <' . $this->fromAddress() . '>';
    }

    private function fromAddress(): string
    {
        $address = strtolower(trim((string) $this->settings->get('email.admin_from_address', '')));
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('CMS sender email is missing or invalid.');
        }
        return $address;
    }

    private function fromName(): string
    {
        $name = trim((string) $this->settings->get('email.admin_from_name', ''));
        if ($name === '') throw new \RuntimeException('CMS sender name is missing.');
        return $name;
    }

    private function replyTo(): string
    {
        $replyTo = strtolower(trim((string) $this->settings->get('email.reply_to', '')));
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('CMS reply-to email is invalid.');
        }
        return $replyTo;
    }
}
