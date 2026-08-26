<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;
use AtomGlobal\Mail\MailQueue;

final class AdminService
{
    private const BRAND_KEYS = [
        'canvas','surface','text_primary','text_muted','border','cta','cta_hover','heart','head','accent','navy',
        'questionnaire_copy','questionnaire_label','input_surface','input_text','option_surface','option_text',
        'selected_surface','selected_text','progress','heading_font','body_font','base_font_size','page_title_size',
        'body_text_size','question_text_size','option_text_size','field_label_size','field_text_size','meta_text_size',
        'visual_title_size','visual_body_size','content_max_width','intake_max_width','question_max_width','content_gutter',
        'card_radius','button_radius','logo_url','favicon_url','email_logo_url','report_logo_url','banner_url'
    ];

    private const SENSITIVE_KEYS = [
        'stripe.secret_key','stripe.webhook_secret','email.smtp_password','email.smtp2go_api_key',
        'turnstile.secret_key'
    ];

    public function __construct(
        private Database $db,
        private SettingsService $settings,
        private MailQueue $mailQueue,
    ) {}

    public function publicConfiguration(): array
    {
        $branding = [];
        foreach (self::BRAND_KEYS as $key) {
            $branding[$this->camel($key)] = $this->settings->get('branding.' . $key, $this->brandDefault($key));
        }

        $stageRows = $this->db->fetchAll(
            'SELECT cs.stage_key, cs.image_alt, cs.focal_x, cs.focal_y, cs.overlay_strength, cs.headline, cs.supporting_text, cs.is_active, cs.display_order, dm.storage_path desktop_image, mm.storage_path mobile_image FROM content_stages cs LEFT JOIN media_library dm ON dm.id = cs.desktop_media_id LEFT JOIN media_library mm ON mm.id = cs.mobile_media_id ORDER BY cs.display_order'
        );
        $stages = [];
        foreach ($stageRows as $row) {
            $stages[$row['stage_key']] = [
                'image' => $row['desktop_image'] ?: '/media/stages/reflection-portrait.png',
                'mobileImage' => $row['mobile_image'] ?: '',
                'alt' => $row['image_alt'] ?: '',
                'focalPoint' => rtrim(rtrim((string) $row['focal_x'], '0'), '.') . '% ' . rtrim(rtrim((string) $row['focal_y'], '0'), '.') . '%',
                'overlay' => (int) $row['overlay_strength'],
                'headline' => $row['headline'] ?: '',
                'supporting' => $row['supporting_text'] ?: '',
                'active' => (bool) $row['is_active'],
                'order' => (int) $row['display_order'],
            ];
        }

        $tracks = [];
        foreach ($this->db->fetchAll(
            'SELECT t.track_key, t.name, t.description, t.price_minor, t.currency, t.is_active, s.public_title, s.short_title, s.audience_label, s.estimated_minutes_min, s.estimated_minutes_max, s.average_seconds_per_question, s.average_seconds_per_participant_field, s.free_report_label, s.paid_report_label, s.free_report_read_minutes, s.paid_report_read_minutes, s.question_count, s.section_count, s.show_remaining_time, s.show_question_count, s.show_section_count, s.show_autosave, s.introductory_note, s.last_reviewed_date FROM assessment_tracks t LEFT JOIN assessment_track_settings s ON s.track_id = t.id ORDER BY t.display_order'
        ) as $row) {
            $tracks[$row['track_key']] = [
                'key' => $row['track_key'],
                'label' => $row['name'],
                'description' => $row['description'],
                'publicTitle' => $row['public_title'] ?: 'Growth Alignment: ' . $row['name'],
                'shortTitle' => $row['short_title'] ?: $row['name'],
                'audienceLabel' => $row['audience_label'],
                'durationMin' => (int) ($row['estimated_minutes_min'] ?: 15),
                'durationMax' => (int) ($row['estimated_minutes_max'] ?: 15),
                'averageSecondsPerQuestion' => (int) ($row['average_seconds_per_question'] ?: 18),
                'averageSecondsPerParticipantField' => (int) ($row['average_seconds_per_participant_field'] ?: 12),
                'freeReportLabel' => $row['free_report_label'] ?: 'Lite Report Free',
                'paidReportLabel' => $row['paid_report_label'] ?: 'Full Report',
                'freeReportReadMinutes' => (int) ($row['free_report_read_minutes'] ?: 3),
                'paidReportReadMinutes' => (int) ($row['paid_report_read_minutes'] ?: 12),
                'questionCount' => (int) ($row['question_count'] ?: 50),
                'sectionCount' => (int) ($row['section_count'] ?: 10),
                'showRemainingTime' => (bool) ($row['show_remaining_time'] ?? true),
                'showQuestionCount' => (bool) ($row['show_question_count'] ?? true),
                'showSectionCount' => (bool) ($row['show_section_count'] ?? true),
                'showAutosave' => (bool) ($row['show_autosave'] ?? true),
                'introductoryNote' => $row['introductory_note'],
                'lastReviewedDate' => $row['last_reviewed_date'],
                'priceMinor' => (int) $row['price_minor'],
                'currency' => $row['currency'],
                'active' => (bool) $row['is_active'],
            ];
        }

        return ['branding' => $branding, 'stages' => $stages, 'tracks' => $tracks];
    }

    public function dashboard(): array
    {
        $metrics = $this->db->fetch(
            'SELECT COUNT(*) surveysStarted, SUM(status = ?) completed, SUM(status = ? AND last_activity_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)) abandoned FROM survey_sessions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ['completed', 'in_progress']
        ) ?: [];
        $revenue = $this->db->fetch(
            'SELECT COALESCE(SUM(amount),0) revenueMinor, COUNT(*) paidReports FROM payments WHERE status = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            ['paid']
        ) ?: [];
        $participants = $this->db->fetchAll(
            'SELECT p.id, p.name, p.email, t.name track, s.completion_percentage progress, COALESCE(pay.status, ?) payment, s.last_activity_at activity FROM survey_sessions s JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id LEFT JOIN payments pay ON pay.id = (SELECT MAX(p2.id) FROM payments p2 WHERE p2.survey_session_id = s.id) ORDER BY s.last_activity_at DESC LIMIT 8',
            ['free']
        );
        $activity = $this->db->fetchAll(
            'SELECT action, entity_type entityType, entity_id entityId, created_at createdAt FROM audit_logs ORDER BY created_at DESC LIMIT 12'
        );
        $failures = $this->db->fetch(
            'SELECT (SELECT COUNT(*) FROM email_queue WHERE status = ?) failedEmails, (SELECT COUNT(*) FROM stripe_webhook_events WHERE status = ?) failedWebhooks, (SELECT COUNT(*) FROM notification_events WHERE acknowledged_at IS NULL AND severity = ?) criticalAlerts',
            ['failed','failed','critical']
        ) ?: [];

        return [
            'metrics' => [
                ['label' => 'Surveys started', 'value' => (int) ($metrics['surveysStarted'] ?? 0)],
                ['label' => 'Completed', 'value' => (int) ($metrics['completed'] ?? 0)],
                ['label' => 'Abandoned', 'value' => (int) ($metrics['abandoned'] ?? 0)],
                ['label' => 'Paid reports', 'value' => (int) ($revenue['paidReports'] ?? 0)],
                ['label' => 'Revenue', 'value' => (int) ($revenue['revenueMinor'] ?? 0)],
            ],
            'participants' => $participants,
            'activity' => $activity,
            'failures' => $failures,
        ];
    }

    public function participants(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = max(10, min(100, (int) ($query['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;
        $search = trim((string) ($query['search'] ?? ''));
        $status = trim((string) ($query['status'] ?? ''));
        $track = trim((string) ($query['track'] ?? ''));
        $where = ['p.anonymised_at IS NULL'];
        $params = [];
        if ($search !== '') { $where[] = '(p.name LIKE ? OR p.email LIKE ?)'; $params[] = '%' . $search . '%'; $params[] = '%' . $search . '%'; }
        if ($status !== '') { $where[] = 's.status = ?'; $params[] = $status; }
        if ($track !== '') { $where[] = 't.track_key = ?'; $params[] = $track; }
        $sqlWhere = implode(' AND ', $where);
        $count = $this->db->fetch('SELECT COUNT(DISTINCT p.id) count FROM participants p JOIN survey_sessions s ON s.participant_id = p.id JOIN assessment_tracks t ON t.id = s.track_id WHERE ' . $sqlWhere, $params);
        $rows = $this->db->fetchAll(
            'SELECT p.id, p.public_uuid publicUuid, p.name, p.email, p.marketing_consent marketingConsent, p.created_at createdAt, s.id sessionId, s.status, s.completion_percentage progress, s.last_activity_at lastActivityAt, s.completed_at completedAt, t.track_key trackKey, t.name track, gr.id reportId, gr.is_unlocked reportUnlocked, pay.status paymentStatus, pay.amount paymentAmount, pay.currency FROM participants p JOIN survey_sessions s ON s.id = (SELECT MAX(s2.id) FROM survey_sessions s2 WHERE s2.participant_id = p.id) JOIN assessment_tracks t ON t.id = s.track_id LEFT JOIN generated_reports gr ON gr.survey_session_id = s.id LEFT JOIN payments pay ON pay.id = (SELECT MAX(p2.id) FROM payments p2 WHERE p2.survey_session_id = s.id) WHERE ' . $sqlWhere . ' ORDER BY s.last_activity_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
        return ['items' => $rows, 'page' => $page, 'limit' => $limit, 'total' => (int) ($count['count'] ?? 0)];
    }

    public function participant(int $id): array
    {
        $participant = $this->db->fetch('SELECT * FROM participants WHERE id = ?', [$id]);
        if (!$participant) throw new \RuntimeException('Participant not found.', 404);
        return [
            'participant' => $participant,
            'sessions' => $this->db->fetchAll('SELECT s.*, t.name track, v.version_number version FROM survey_sessions s JOIN assessment_tracks t ON t.id = s.track_id JOIN assessment_versions v ON v.id = s.assessment_version_id WHERE s.participant_id = ? ORDER BY s.created_at DESC', [$id]),
            'answers' => $this->db->fetchAll('SELECT a.*, q.question_text questionText FROM survey_answers a JOIN survey_sessions s ON s.id = a.survey_session_id LEFT JOIN questions q ON q.assessment_version_id = s.assessment_version_id AND q.position = a.question_position WHERE s.participant_id = ? ORDER BY a.survey_session_id DESC, a.question_position', [$id]),
            'reports' => $this->db->fetchAll('SELECT gr.*, s.public_uuid sessionUuid FROM generated_reports gr JOIN survey_sessions s ON s.id = gr.survey_session_id WHERE s.participant_id = ? ORDER BY gr.created_at DESC', [$id]),
            'payments' => $this->db->fetchAll('SELECT pay.* FROM payments pay JOIN survey_sessions s ON s.id = pay.survey_session_id WHERE s.participant_id = ? ORDER BY pay.created_at DESC', [$id]),
            'emails' => $this->db->fetchAll('SELECT * FROM email_logs WHERE recipient_email = ? ORDER BY created_at DESC LIMIT 100', [$participant['email']]),
            'consents' => $this->db->fetchAll('SELECT * FROM consent_logs WHERE participant_id = ? ORDER BY created_at DESC', [$id]),
        ];
    }

    public function assessments(): array
    {
        return $this->db->fetchAll(
            'SELECT t.id trackId, t.track_key trackKey, t.name trackName, t.price_minor priceMinor, t.currency, t.is_active active, v.id versionId, v.version_number versionNumber, v.status, v.change_summary changeSummary, v.published_at publishedAt, COUNT(DISTINCT q.id) questionCount, COUNT(DISTINCT sec.id) sectionCount FROM assessment_tracks t LEFT JOIN assessment_versions v ON v.track_id = t.id LEFT JOIN questions q ON q.assessment_version_id = v.id AND q.is_active = 1 LEFT JOIN assessment_sections sec ON sec.assessment_version_id = v.id AND sec.is_active = 1 GROUP BY t.id, v.id ORDER BY t.display_order, v.created_at DESC'
        );
    }

    public function assessmentVersion(int $id): array
    {
        $version = $this->db->fetch('SELECT v.*, t.track_key trackKey, t.name trackName FROM assessment_versions v JOIN assessment_tracks t ON t.id = v.track_id WHERE v.id = ?', [$id]);
        if (!$version) throw new \RuntimeException('Assessment version not found.', 404);
        return [
            'version' => $version,
            'sections' => $this->db->fetchAll('SELECT * FROM assessment_sections WHERE assessment_version_id = ? ORDER BY display_order', [$id]),
            'questions' => $this->db->fetchAll('SELECT q.*, s.code sectionCode, s.name sectionName FROM questions q JOIN assessment_sections s ON s.id = q.section_id WHERE q.assessment_version_id = ? ORDER BY q.position', [$id]),
            'options' => $this->db->fetchAll('SELECT * FROM answer_options WHERE assessment_version_id = ? ORDER BY display_order', [$id]),
            'reports' => $this->db->fetchAll('SELECT * FROM report_templates WHERE assessment_version_id = ? ORDER BY min_score', [$id]),
        ];
    }

    public function cloneAssessment(int $id, int $adminId, string $newVersion, string $summary): int
    {
        return $this->db->transaction(function () use ($id, $adminId, $newVersion, $summary) {
            $source = $this->db->fetch('SELECT * FROM assessment_versions WHERE id = ?', [$id]);
            if (!$source) throw new \RuntimeException('Assessment version not found.', 404);
            $newId = $this->db->insert('INSERT INTO assessment_versions (track_id, version_number, status, cloned_from_id, change_summary, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())', [$source['track_id'], $newVersion, 'draft', $id, $summary, $adminId]);
            $sectionMap = [];
            foreach ($this->db->fetchAll('SELECT * FROM assessment_sections WHERE assessment_version_id = ? ORDER BY display_order', [$id]) as $section) {
                $sectionMap[$section['id']] = $this->db->insert('INSERT INTO assessment_sections (assessment_version_id, code, name, description, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?)', [$newId, $section['code'], $section['name'], $section['description'], $section['display_order'], $section['is_active']]);
            }
            foreach ($this->db->fetchAll('SELECT * FROM questions WHERE assessment_version_id = ? ORDER BY position', [$id]) as $question) {
                $this->db->execute('INSERT INTO questions (assessment_version_id, section_id, stable_key, question_text, scoring_direction, position, is_required, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$newId, $sectionMap[$question['section_id']], $question['stable_key'], $question['question_text'], $question['scoring_direction'], $question['position'], $question['is_required'], $question['is_active']]);
            }
            foreach ($this->db->fetchAll('SELECT * FROM answer_options WHERE assessment_version_id = ? ORDER BY display_order', [$id]) as $option) {
                $this->db->execute('INSERT INTO answer_options (assessment_version_id, option_value, label, display_order) VALUES (?, ?, ?, ?)', [$newId, $option['option_value'], $option['label'], $option['display_order']]);
            }
            foreach ($this->db->fetchAll('SELECT * FROM report_templates WHERE assessment_version_id = ?', [$id]) as $report) {
                $this->db->execute('INSERT INTO report_templates (assessment_version_id, profile_key, profile_name, min_score, max_score, free_content_json, paid_content_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', [$newId, $report['profile_key'], $report['profile_name'], $report['min_score'], $report['max_score'], $report['free_content_json'], $report['paid_content_json']]);
            }
            return $newId;
        });
    }

    public function publishAssessment(int $id, int $adminId): void
    {
        $version = $this->db->fetch('SELECT * FROM assessment_versions WHERE id = ?', [$id]);
        if (!$version) throw new \RuntimeException('Assessment version not found.', 404);
        $counts = $this->db->fetch('SELECT COUNT(*) questionCount, COUNT(DISTINCT section_id) sectionCount FROM questions WHERE assessment_version_id = ? AND is_active = 1', [$id]);
        if ((int) ($counts['questionCount'] ?? 0) !== 50 || (int) ($counts['sectionCount'] ?? 0) !== 10) {
            throw new \InvalidArgumentException('Publishing requires exactly 50 active questions across 10 sections.');
        }
        $this->db->transaction(function () use ($id, $adminId, $version) {
            $this->db->execute('UPDATE assessment_versions SET status = ?, archived_at = NOW(), updated_at = NOW() WHERE track_id = ? AND status = ? AND id <> ?', ['archived', $version['track_id'], 'published', $id]);
            $this->db->execute('UPDATE assessment_versions SET status = ?, published_at = NOW(), archived_at = NULL, updated_at = NOW() WHERE id = ?', ['published', $id]);
            $this->audit($adminId, 'assessment.published', 'assessment_version', (string) $id, null, ['version' => $version['version_number']]);
        });
    }

    public function saveStage(string $stageKey, array $payload, int $adminId): array
    {
        $desktopId = isset($payload['desktopMediaId']) ? (int) $payload['desktopMediaId'] : null;
        $mobileId = isset($payload['mobileMediaId']) ? (int) $payload['mobileMediaId'] : null;
        $before = $this->db->fetch('SELECT * FROM content_stages WHERE stage_key = ?', [$stageKey]);
        $this->db->execute(
            'INSERT INTO content_stages (stage_key, desktop_media_id, mobile_media_id, image_alt, focal_x, focal_y, overlay_strength, headline, supporting_text, is_active, display_order, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE desktop_media_id = VALUES(desktop_media_id), mobile_media_id = VALUES(mobile_media_id), image_alt = VALUES(image_alt), focal_x = VALUES(focal_x), focal_y = VALUES(focal_y), overlay_strength = VALUES(overlay_strength), headline = VALUES(headline), supporting_text = VALUES(supporting_text), is_active = VALUES(is_active), display_order = VALUES(display_order), updated_at = NOW()',
            [$stageKey, $desktopId, $mobileId, trim((string) ($payload['alt'] ?? '')), (float) ($payload['focalX'] ?? 50), (float) ($payload['focalY'] ?? 50), max(0, min(90, (int) ($payload['overlay'] ?? 40))), trim((string) ($payload['headline'] ?? '')), trim((string) ($payload['supporting'] ?? '')), !empty($payload['active']) ? 1 : 0, (int) ($payload['order'] ?? 0)]
        );
        $after = $this->db->fetch('SELECT * FROM content_stages WHERE stage_key = ?', [$stageKey]);
        $this->audit($adminId, 'content_stage.saved', 'content_stage', $stageKey, $before, $after);
        return $after ?: [];
    }

    public function branding(): array
    {
        $published = $this->db->fetch('SELECT * FROM branding_revisions WHERE status = ? ORDER BY published_at DESC LIMIT 1', ['published']);
        $draft = $this->db->fetch('SELECT * FROM branding_revisions WHERE status = ? ORDER BY updated_at DESC LIMIT 1', ['draft']);
        return [
            'published' => $published ? json_decode($published['settings_json'], true) : $this->publicConfiguration()['branding'],
            'draft' => $draft ? json_decode($draft['settings_json'], true) : null,
            'draftId' => $draft['id'] ?? null,
            'publishedAt' => $published['published_at'] ?? null,
        ];
    }

    public function saveBrandingDraft(array $payload, int $adminId): int
    {
        $settings = $this->validateBranding($payload);
        $existing = $this->db->fetch('SELECT id FROM branding_revisions WHERE status = ? ORDER BY updated_at DESC LIMIT 1', ['draft']);
        if ($existing) {
            $this->db->execute('UPDATE branding_revisions SET settings_json = ?, created_by = ?, updated_at = NOW() WHERE id = ?', [json_encode($settings), $adminId, $existing['id']]);
            $id = (int) $existing['id'];
        } else {
            $id = $this->db->insert('INSERT INTO branding_revisions (status, settings_json, created_by, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', ['draft', json_encode($settings), $adminId]);
        }
        $this->audit($adminId, 'branding.draft_saved', 'branding_revision', (string) $id, null, $settings);
        return $id;
    }

    public function publishBranding(int $draftId, int $adminId): void
    {
        $draft = $this->db->fetch('SELECT * FROM branding_revisions WHERE id = ? AND status = ?', [$draftId, 'draft']);
        if (!$draft) throw new \RuntimeException('Branding draft not found.', 404);
        $values = json_decode($draft['settings_json'], true, 512, JSON_THROW_ON_ERROR);
        $this->db->transaction(function () use ($draftId, $adminId, $values) {
            $this->db->execute('UPDATE branding_revisions SET status = ? WHERE status = ?', ['archived', 'published']);
            $this->db->execute('UPDATE branding_revisions SET status = ?, published_by = ?, published_at = NOW(), updated_at = NOW() WHERE id = ?', ['published', $adminId, $draftId]);
            foreach ($values as $key => $value) {
                $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
                if (in_array($snake, self::BRAND_KEYS, true)) $this->settings->set('branding.' . $snake, $value);
            }
            $this->audit($adminId, 'branding.published', 'branding_revision', (string) $draftId, null, $values);
        });
    }

    public function emailTemplates(): array
    {
        return $this->db->fetchAll('SELECT * FROM email_templates ORDER BY template_name');
    }

    public function saveEmailTemplate(string $key, array $payload, int $adminId): void
    {
        $before = $this->db->fetch('SELECT * FROM email_templates WHERE template_key = ?', [$key]);
        $this->db->execute('INSERT INTO email_templates (template_key, template_name, subject, html_body, text_body, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE template_name = VALUES(template_name), subject = VALUES(subject), html_body = VALUES(html_body), text_body = VALUES(text_body), is_active = VALUES(is_active), updated_at = NOW()', [$key, trim((string) ($payload['templateName'] ?? $key)), trim((string) ($payload['subject'] ?? '')), (string) ($payload['htmlBody'] ?? ''), (string) ($payload['textBody'] ?? ''), !empty($payload['active']) ? 1 : 0]);
        $after = $this->db->fetch('SELECT * FROM email_templates WHERE template_key = ?', [$key]);
        $this->audit($adminId, 'email_template.saved', 'email_template', $key, $before, $after);
    }

    public function emailQueue(array $query): array
    {
        $status = trim((string) ($query['status'] ?? ''));
        $params = [];
        $where = '';
        if ($status !== '') { $where = ' WHERE status = ?'; $params[] = $status; }
        return $this->db->fetchAll('SELECT * FROM email_queue' . $where . ' ORDER BY created_at DESC LIMIT 250', $params);
    }

    public function testEmail(string $recipient, int $adminId): int
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('A valid test email address is required.');
        $id = $this->mailQueue->enqueue('admin_test', $recipient, ['subject' => 'Atom Global email configuration test', 'message' => 'The Growth Alignment email queue is configured.']);
        $this->db->execute('INSERT INTO api_connection_tests (provider_key, status, message, tested_by, tested_at) VALUES (?, ?, ?, ?, NOW())', ['email', 'success', 'Test email queued as message ' . $id, $adminId]);
        return $id;
    }

    public function retryEmail(int $id, int $adminId): void
    {
        $this->db->execute('UPDATE email_queue SET status = ?, scheduled_at = NOW(), failure_reason = NULL WHERE id = ?', ['retry', $id]);
        $this->audit($adminId, 'email.retry', 'email_queue', (string) $id);
    }

    public function affiliates(): array
    {
        return $this->db->fetchAll('SELECT a.*, COUNT(DISTINCT c.id) clicks, COUNT(DISTINCT at.survey_session_id) conversions, COALESCE(SUM(CASE WHEN p.status = ? THEN p.amount ELSE 0 END),0) revenueMinor, COALESCE(SUM(ac.amount_minor),0) commissionMinor FROM affiliates a LEFT JOIN affiliate_clicks c ON c.affiliate_id = a.id LEFT JOIN affiliate_attributions at ON at.affiliate_id = a.id LEFT JOIN payments p ON p.affiliate_id = a.id LEFT JOIN affiliate_commissions ac ON ac.affiliate_id = a.id GROUP BY a.id ORDER BY a.created_at DESC', ['paid']);
    }

    public function saveAffiliate(?int $id, array $payload, int $adminId): int
    {
        $values = [strtoupper(trim((string) ($payload['code'] ?? ''))), trim((string) ($payload['name'] ?? '')), trim((string) ($payload['contactName'] ?? '')), strtolower(trim((string) ($payload['contactEmail'] ?? ''))), trim((string) ($payload['campaignName'] ?? '')), max(1, (int) ($payload['cookieDurationDays'] ?? 30)), in_array($payload['commissionType'] ?? '', ['percentage','fixed'], true) ? $payload['commissionType'] : 'percentage', max(0, (float) ($payload['commissionValue'] ?? 0)), json_encode($payload['tracks'] ?? []), trim((string) ($payload['notes'] ?? '')), !empty($payload['active']) ? 1 : 0];
        if ($values[0] === '' || $values[1] === '') throw new \InvalidArgumentException('Affiliate code and name are required.');
        if ($id) {
            $this->db->execute('UPDATE affiliates SET affiliate_code = ?, name = ?, contact_name = ?, contact_email = ?, campaign_name = ?, cookie_duration_days = ?, commission_type = ?, commission_value = ?, applicable_tracks_json = ?, notes = ?, is_active = ?, updated_at = NOW() WHERE id = ?', [...$values, $id]);
        } else {
            $id = $this->db->insert('INSERT INTO affiliates (affiliate_code, name, contact_name, contact_email, campaign_name, cookie_duration_days, commission_type, commission_value, applicable_tracks_json, notes, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', $values);
        }
        $this->audit($adminId, 'affiliate.saved', 'affiliate', (string) $id, null, $payload);
        return $id;
    }

    public function settings(array $groups = []): array
    {
        $rows = $this->db->fetchAll('SELECT setting_key, setting_value, is_encrypted, updated_at FROM global_settings ORDER BY setting_key');
        $result = [];
        foreach ($rows as $row) {
            [$group, $key] = array_pad(explode('.', $row['setting_key'], 2), 2, 'value');
            if ($groups && !in_array($group, $groups, true)) continue;
            $result[$group][$this->camel($key)] = $row['is_encrypted'] ? ['configured' => true, 'masked' => '••••••••'] : (json_decode($row['setting_value'], true) ?? $row['setting_value']);
        }
        $result['environment'] = [
            'stripeSecretConfigured' => (bool) ($_ENV['STRIPE_SECRET_KEY'] ?? false),
            'stripeWebhookConfigured' => (bool) ($_ENV['STRIPE_WEBHOOK_SECRET'] ?? false),
            'smtpConfigured' => (bool) ($_ENV['SMTP_HOST'] ?? false),
            'smtp2goConfigured' => (bool) ($_ENV['SMTP2GO_API_KEY'] ?? false),
            'turnstileConfigured' => (bool) ($_ENV['TURNSTILE_SECRET_KEY'] ?? false),
        ];
        return $result;
    }

    public function saveSettings(string $group, array $payload, int $adminId): void
    {
        $allowedGroups = ['email','stripe','alerts','security','privacy','turnstile','report','system'];
        if (!in_array($group, $allowedGroups, true)) throw new \InvalidArgumentException('Unknown settings group.');
        foreach ($payload as $key => $value) {
            if ($value === '' && in_array($group . '.' . $this->snake((string) $key), self::SENSITIVE_KEYS, true)) continue;
            $settingKey = $group . '.' . $this->snake((string) $key);
            $this->settings->set($settingKey, $value, in_array($settingKey, self::SENSITIVE_KEYS, true));
        }
        $this->audit($adminId, 'settings.saved', 'settings_group', $group, null, array_keys($payload));
    }

    public function seoPages(): array
    {
        return $this->db->fetchAll('SELECT * FROM seo_pages ORDER BY path');
    }

    public function saveSeo(string $pageKey, array $payload, int $adminId): void
    {
        $this->db->execute('INSERT INTO seo_pages (page_key, path, page_title, meta_description, canonical_url, robots_setting, og_title, og_description, og_media_id, twitter_json, heading, introductory_content, faq_json, structured_data_json, include_in_sitemap, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE path = VALUES(path), page_title = VALUES(page_title), meta_description = VALUES(meta_description), canonical_url = VALUES(canonical_url), robots_setting = VALUES(robots_setting), og_title = VALUES(og_title), og_description = VALUES(og_description), og_media_id = VALUES(og_media_id), twitter_json = VALUES(twitter_json), heading = VALUES(heading), introductory_content = VALUES(introductory_content), faq_json = VALUES(faq_json), structured_data_json = VALUES(structured_data_json), include_in_sitemap = VALUES(include_in_sitemap), updated_at = NOW()', [$pageKey, (string) ($payload['path'] ?? '/'), $payload['pageTitle'] ?? null, $payload['metaDescription'] ?? null, $payload['canonicalUrl'] ?? null, $payload['robotsSetting'] ?? 'index,follow', $payload['ogTitle'] ?? null, $payload['ogDescription'] ?? null, $payload['ogMediaId'] ?? null, json_encode($payload['twitter'] ?? null), $payload['heading'] ?? null, $payload['introductoryContent'] ?? null, json_encode($payload['faq'] ?? null), json_encode($payload['structuredData'] ?? null), !empty($payload['includeInSitemap']) ? 1 : 0]);
        $this->audit($adminId, 'seo.saved', 'seo_page', $pageKey, null, $payload);
    }

    public function payments(): array
    {
        return $this->db->fetchAll('SELECT pay.*, p.name participantName, p.email participantEmail, t.name track, a.affiliate_code affiliateCode FROM payments pay JOIN survey_sessions s ON s.id = pay.survey_session_id JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id LEFT JOIN affiliates a ON a.id = pay.affiliate_id ORDER BY pay.created_at DESC LIMIT 500');
    }

    public function reports(): array
    {
        return $this->db->fetchAll('SELECT gr.id, gr.survey_session_id sessionId, gr.is_unlocked unlocked, gr.unlock_reason unlockReason, gr.unlocked_at unlockedAt, gr.revoked_at revokedAt, gr.token_expires_at tokenExpiresAt, gr.pdf_path pdfPath, gr.pdf_generated_at pdfGeneratedAt, gr.view_count viewCount, gr.created_at createdAt, p.name participantName, p.email participantEmail, t.name track FROM generated_reports gr JOIN survey_sessions s ON s.id = gr.survey_session_id JOIN participants p ON p.id = s.participant_id JOIN assessment_tracks t ON t.id = s.track_id ORDER BY gr.created_at DESC LIMIT 500');
    }

    public function revokeReport(int $id, int $adminId): void
    {
        $this->db->execute('UPDATE generated_reports SET revoked_at = NOW(), updated_at = NOW() WHERE id = ?', [$id]);
        $this->db->execute('UPDATE secure_report_tokens SET revoked_at = NOW() WHERE generated_report_id = ?', [$id]);
        $this->audit($adminId, 'report.revoked', 'generated_report', (string) $id);
    }

    public function resendReport(int $id, int $adminId): int
    {
        $row = $this->db->fetch('SELECT gr.id, p.email, p.name FROM generated_reports gr JOIN survey_sessions s ON s.id = gr.survey_session_id JOIN participants p ON p.id = s.participant_id WHERE gr.id = ?', [$id]);
        if (!$row) throw new \RuntimeException('Report not found.', 404);
        $queueId = $this->mailQueue->enqueue('paid_report_ready', $row['email'], ['participantName' => $row['name'], 'reportId' => $id]);
        $this->db->execute('INSERT INTO report_delivery_log (generated_report_id, delivery_type, recipient_email, email_queue_id, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [$id, 'admin_resend', $row['email'], $queueId, 'queued', $adminId]);
        $this->audit($adminId, 'report.resent', 'generated_report', (string) $id);
        return $queueId;
    }

    public function auditLogs(array $query): array
    {
        $limit = max(25, min(500, (int) ($query['limit'] ?? 200)));
        return $this->db->fetchAll('SELECT a.*, u.display_name adminName, u.email adminEmail FROM audit_logs a LEFT JOIN admin_users u ON u.id = a.admin_user_id ORDER BY a.created_at DESC LIMIT ' . $limit);
    }

    public function alertRecipients(): array
    {
        return $this->db->fetchAll('SELECT * FROM admin_alert_recipients ORDER BY name');
    }

    public function saveAlertRecipient(?int $id, array $payload, int $adminId): int
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('A valid admin alert email is required.');
        $values = [trim((string) ($payload['name'] ?? $email)), $email, json_encode($payload['alertTypes'] ?? []), !empty($payload['active']) ? 1 : 0];
        if ($id) {
            $this->db->execute('UPDATE admin_alert_recipients SET name = ?, email = ?, alert_types_json = ?, is_active = ?, updated_at = NOW() WHERE id = ?', [...$values, $id]);
        } else {
            $id = $this->db->insert('INSERT INTO admin_alert_recipients (name, email, alert_types_json, is_active, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())', [...$values, $adminId]);
        }
        $this->audit($adminId, 'alert_recipient.saved', 'admin_alert_recipient', (string) $id, null, $payload);
        return $id;
    }

    private function validateBranding(array $payload): array
    {
        $defaults = $this->publicConfiguration()['branding'];
        $colourKeys = [
            'canvas','surface','textPrimary','textMuted','border','cta','ctaHover','heart','head','accent','navy',
            'questionnaireCopy','questionnaireLabel','inputSurface','inputText','optionSurface','optionText',
            'selectedSurface','selectedText','progress',
        ];
        $numericBounds = [
            'baseFontSize' => [12, 22], 'pageTitleSize' => [36, 88], 'bodyTextSize' => [12, 22],
            'questionTextSize' => [14, 26], 'optionTextSize' => [9, 18], 'fieldLabelSize' => [9, 18],
            'fieldTextSize' => [12, 22], 'metaTextSize' => [9, 18], 'visualTitleSize' => [36, 96],
            'visualBodySize' => [12, 30], 'contentMaxWidth' => [520, 1100], 'intakeMaxWidth' => [600, 1200],
            'questionMaxWidth' => [640, 1200], 'contentGutter' => [24, 120], 'cardRadius' => [0, 32],
            'buttonRadius' => [0, 32],
        ];
        $fontKeys = ['headingFont', 'bodyFont'];
        $assetKeys = ['logoUrl', 'faviconUrl', 'emailLogoUrl', 'reportLogoUrl', 'bannerUrl'];
        $result = [];

        foreach ($defaults as $key => $default) {
            $value = $payload[$key] ?? $default;
            if (in_array($key, $colourKeys, true)) {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $value)) {
                    throw new \InvalidArgumentException('Invalid colour value for ' . $key . '.');
                }
                $result[$key] = strtoupper((string) $value);
                continue;
            }
            if (isset($numericBounds[$key])) {
                if (!is_numeric($value)) throw new \InvalidArgumentException('Invalid numeric value for ' . $key . '.');
                $number = (int) round((float) $value);
                [$minimum, $maximum] = $numericBounds[$key];
                if ($number < $minimum || $number > $maximum) {
                    throw new \InvalidArgumentException($key . ' must be between ' . $minimum . ' and ' . $maximum . '.');
                }
                $result[$key] = $number;
                continue;
            }
            if (in_array($key, $fontKeys, true)) {
                $font = trim((string) $value);
                if ($font === '' || strlen($font) > 180 || !preg_match('/^[A-Za-z0-9\s,"\'\.\-]+$/', $font)) {
                    throw new \InvalidArgumentException('Invalid font stack for ' . $key . '.');
                }
                $result[$key] = $font;
                continue;
            }
            if (in_array($key, $assetKeys, true)) {
                $url = trim((string) $value);
                if ($url !== '' && !preg_match('#^(?:/|https://)#i', $url)) {
                    throw new \InvalidArgumentException('Brand assets must use a local path or HTTPS URL.');
                }
                $result[$key] = $url;
                continue;
            }
            $result[$key] = $value;
        }
        return $result;
    }

    private function brandDefault(string $key): mixed
    {
        return match ($key) {
            'canvas' => '#F7F4EF', 'surface' => '#FFFFFF', 'text_primary' => '#211C16', 'text_muted' => '#726A5B',
            'border' => '#E4DDCF', 'cta', 'accent', 'progress', 'selected_surface' => '#C9A15A',
            'cta_hover', 'questionnaire_label' => '#AF8540', 'heart' => '#C1443F', 'head' => '#6C8FAE',
            'navy', 'selected_text' => '#14141C', 'questionnaire_copy' => '#3A3428',
            'input_surface', 'option_surface' => '#FFFFFF', 'input_text' => '#211C16', 'option_text' => '#726A5B',
            'heading_font' => 'Georgia, "Times New Roman", serif', 'body_font' => 'Arial, Helvetica, sans-serif',
            'base_font_size' => 16, 'page_title_size' => 62, 'body_text_size' => 16, 'question_text_size' => 17,
            'option_text_size' => 11, 'field_label_size' => 12, 'field_text_size' => 14, 'meta_text_size' => 12,
            'visual_title_size' => 72, 'visual_body_size' => 22, 'content_max_width' => 720,
            'intake_max_width' => 840, 'question_max_width' => 880, 'content_gutter' => 72,
            'card_radius', 'button_radius' => 8,
            'logo_url' => '/media/brand/atom-global-wordmark-transparent.svg',
            'email_logo_url', 'report_logo_url' => '/media/brand/atom-global-wordmark.png',
            'favicon_url' => '/icon-192.png', 'banner_url' => '', default => '',
        };
    }

    private function audit(int $adminId, string $action, ?string $entityType = null, ?string $entityId = null, mixed $before = null, mixed $after = null): void
    {
        $this->db->execute('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, before_json, after_json, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [$adminId, $action, $entityType, $entityId, $before === null ? null : json_encode($before), $after === null ? null : json_encode($after)]);
    }

    private function camel(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $value))));
    }

    private function snake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
