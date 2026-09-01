<?php
declare(strict_types=1);

use AtomGlobal\Http\Request;
use AtomGlobal\Http\Response;
use AtomGlobal\Security\RateLimiter;
use Stripe\StripeClient;

// This file is required by public/index.php after the core routes are registered.

$router->add('GET', '/api/reports/{token}/pdf', function (Request $request, array $params) use ($container) {
    $report = $container['reports']->byToken($params['token']);
    if (!$report || empty($report['is_unlocked'])) return Response::error('PDF is not available for this report.', 404);
    $path = $container['reports']->pdfByToken($params['token']);
    if (!$path) $path = $container['pdf']->generate((int) $report['id']);
    if (!$path || !is_file($path)) return Response::error('PDF generation failed.', 500);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="growth-alignment-full-development-report.pdf"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
});

$router->add('POST', '/api/reports/{token}/email', function (Request $request, array $params) use ($container, $db) {
    $token = (string) ($params['token'] ?? '');
    $report = $container['reports']->byToken($token);
    if (!$report || empty($report['is_unlocked'])) return Response::error('Full Development Report is not available.', 404);
    $limiter = new RateLimiter($db);
    if (!$limiter->hit('report-email:' . hash('sha256', $token), 3, 3600)) {
        return Response::error('Please wait before emailing this report again.', 429);
    }
    $reportUrl = rtrim((string) $container['config']['url'], '/') . '/report/' . rawurlencode($token);
    $queueId = $container['mailQueue']->enqueue('paid_report_ready', (string) $report['participantEmail'], [
        'participantName' => (string) $report['participantName'],
        'trackName' => (string) $report['trackName'],
        'reportUrl' => $reportUrl,
        'paidReportUrl' => $reportUrl,
        'reportId' => (int) $report['id'],
    ]);
    $delivery = $container['mailQueueProcessor']->processIds([$queueId]);
    $sent = count($delivery) === 1 && ($delivery[0]['status'] ?? '') === 'sent';
    return Response::json(['queued' => true, 'queueId' => $queueId, 'delivery' => $sent ? 'sent' : 'retrying']);
});

$router->add('PUT', '/api/reports/{token}/commitment', function (Request $request, array $params) use ($container, $db) {
    $token = (string) $params['token'];
    $limiter = new RateLimiter($db);
    if (!$limiter->hit('report-commitment:' . hash('sha256', $token), 20, 3600)) {
        return Response::error('Please wait before saving again.', 429);
    }
    return Response::json($container['reports']->saveCommitment($token, (string) ($request->body['text'] ?? '')));
});

$router->add('POST', '/api/admin/reports/{id}/regenerate', function (Request $request, array $params) use ($auth, $container, $csrf) {
    $user = $auth->requirePermission('reports.manage');
    $csrf($request);
    $path = $container['reportAdmin']->regeneratePdf((int) $params['id'], (int) $user['id']);
    return Response::json(['generated' => true, 'fileName' => basename($path)]);
});

$router->add('POST', '/api/admin/reports/{id}/rotate-and-resend', function (Request $request, array $params) use ($auth, $container, $csrf) {
    $user = $auth->requirePermission('reports.manage');
    $csrf($request);
    return Response::json($container['reportAdmin']->resend((int) $params['id'], (int) $user['id']));
});

$router->add('PUT', '/api/admin/assessment-tracks/{id}/settings', function (Request $request, array $params) use ($auth, $db, $csrf) {
    $user = $auth->requirePermission('assessments.manage');
    $csrf($request);
    $trackId = (int) $params['id'];
    $payload = $request->body;
    $db->execute(
        'INSERT INTO assessment_track_settings (track_id, public_title, short_title, audience_label, estimated_minutes_min, estimated_minutes_max, average_seconds_per_question, average_seconds_per_participant_field, free_report_label, paid_report_label, free_report_read_minutes, paid_report_read_minutes, question_count, section_count, show_remaining_time, show_question_count, show_section_count, show_autosave, introductory_note, last_reviewed_date, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE public_title = VALUES(public_title), short_title = VALUES(short_title), audience_label = VALUES(audience_label), estimated_minutes_min = VALUES(estimated_minutes_min), estimated_minutes_max = VALUES(estimated_minutes_max), average_seconds_per_question = VALUES(average_seconds_per_question), average_seconds_per_participant_field = VALUES(average_seconds_per_participant_field), free_report_label = VALUES(free_report_label), paid_report_label = VALUES(paid_report_label), free_report_read_minutes = VALUES(free_report_read_minutes), paid_report_read_minutes = VALUES(paid_report_read_minutes), question_count = 40, section_count = 10, show_remaining_time = VALUES(show_remaining_time), show_question_count = VALUES(show_question_count), show_section_count = VALUES(show_section_count), show_autosave = VALUES(show_autosave), introductory_note = VALUES(introductory_note), last_reviewed_date = VALUES(last_reviewed_date), updated_at = NOW()',
        [$trackId, trim((string) ($payload['publicTitle'] ?? '')), trim((string) ($payload['shortTitle'] ?? '')), trim((string) ($payload['audienceLabel'] ?? '')), max(1, (int) ($payload['durationMin'] ?? 15)), max(1, (int) ($payload['durationMax'] ?? 15)), max(5, (int) ($payload['averageSecondsPerQuestion'] ?? 18)), max(5, (int) ($payload['averageSecondsPerParticipantField'] ?? 12)), trim((string) ($payload['freeReportLabel'] ?? 'Lite Report Free')), trim((string) ($payload['paidReportLabel'] ?? 'Full Report')), max(1, (int) ($payload['freeReportReadMinutes'] ?? 3)), max(1, (int) ($payload['paidReportReadMinutes'] ?? 12)), 40, 10, !empty($payload['showRemainingTime']) ? 1 : 0, !empty($payload['showQuestionCount']) ? 1 : 0, !empty($payload['showSectionCount']) ? 1 : 0, !empty($payload['showAutosave']) ? 1 : 0, $payload['introductoryNote'] ?? null, $payload['lastReviewedDate'] ?? null]
    );
    $db->execute('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [$user['id'], 'assessment_track.settings_saved', 'assessment_track', (string) $trackId, json_encode(array_merge($payload, ['questionCount' => 40, 'sectionCount' => 10]))]);
    return Response::json(['saved' => true, 'questionCount' => 40, 'sectionCount' => 10]);
});

$router->add('PUT', '/api/admin/questions/{id}', function (Request $request, array $params) use ($auth, $db, $csrf) {
    $user = $auth->requirePermission('assessments.manage');
    $csrf($request);
    $id = (int) $params['id'];
    $question = $db->fetch('SELECT q.*, v.status version_status FROM questions q JOIN assessment_versions v ON v.id = q.assessment_version_id WHERE q.id = ?', [$id]);
    if (!$question) return Response::error('Question not found.', 404);
    if ($question['version_status'] !== 'draft') return Response::error('Published and archived questions are immutable. Clone the version before correcting text.', 422);
    $payload = $request->body;
    foreach (['scoringDirection', 'position', 'required', 'active', 'sectionId', 'stableKey'] as $field) {
        if (array_key_exists($field, $payload)) {
            return Response::error('Only question text may be corrected. Scoring, position, section, identity and active state are locked.', 422);
        }
    }
    if (($payload['confirmMeaningUnchanged'] ?? false) !== true) {
        return Response::error('Confirm that the correction does not change the question meaning or scoring intent.', 422);
    }
    $text = trim((string) ($payload['questionText'] ?? ''));
    if (mb_strlen($text) < 10 || mb_strlen($text) > 2000) return Response::error('Question text must contain between 10 and 2,000 characters.', 422);
    if ($text === trim((string) $question['question_text'])) return Response::json(['saved' => false, 'unchanged' => true]);
    $db->execute('UPDATE questions SET question_text = ? WHERE id = ?', [$text, $id]);
    $db->execute('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, before_json, after_json, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [$user['id'], 'question.text_corrected', 'question', (string) $id, json_encode(['questionText' => $question['question_text'], 'stableKey' => $question['stable_key'], 'scoringDirection' => $question['scoring_direction'], 'position' => (int) $question['position']]), json_encode(['questionText' => $text, 'meaningUnchangedConfirmed' => true, 'stableKey' => $question['stable_key'], 'scoringDirection' => $question['scoring_direction'], 'position' => (int) $question['position']])]);
    return Response::json(['saved' => true, 'policy' => 'text_correction_only']);
});

$router->add('PUT', '/api/admin/report-templates/{id}', function (Request $request, array $params) use ($auth, $db, $csrf) {
    $user = $auth->requirePermission('assessments.manage');
    $csrf($request);
    $id = (int) $params['id'];
    $template = $db->fetch('SELECT rt.*, v.status version_status FROM report_templates rt JOIN assessment_versions v ON v.id = rt.assessment_version_id WHERE rt.id = ?', [$id]);
    if (!$template) return Response::error('Report template not found.', 404);
    if ($template['version_status'] !== 'draft') return Response::error('Only draft report templates may be edited.', 422);
    $payload = $request->body;
    $free = $payload['freeContent'] ?? json_decode($template['free_content_json'], true);
    $paid = $payload['paidContent'] ?? json_decode($template['paid_content_json'], true);
    $db->execute('UPDATE report_templates SET profile_name = ?, min_score = ?, max_score = ?, free_content_json = ?, paid_content_json = ?, updated_at = NOW() WHERE id = ?', [trim((string) ($payload['profileName'] ?? $template['profile_name'])), max(0, (int) ($payload['minScore'] ?? $template['min_score'])), min(250, (int) ($payload['maxScore'] ?? $template['max_score'])), json_encode($free), json_encode($paid), $id]);
    $db->execute('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, before_json, after_json, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [$user['id'], 'report_template.saved', 'report_template', (string) $id, json_encode($template), json_encode($payload)]);
    return Response::json(['saved' => true]);
});

$router->add('GET', '/api/admin/users', function () use ($auth, $db) {
    $auth->requirePermission('settings.manage');
    return Response::json(['items' => $db->fetchAll('SELECT u.id, u.email, u.display_name displayName, u.is_active active, u.two_factor_enabled twoFactorEnabled, u.last_login_at lastLoginAt, u.created_at createdAt, r.role_key roleKey, r.role_name roleName FROM admin_users u JOIN roles r ON r.id = u.role_id ORDER BY u.display_name')]);
});

$router->add('POST', '/api/admin/users', function (Request $request) use ($auth, $db, $csrf) {
    $user = $auth->requirePermission('settings.manage');
    $csrf($request);
    $payload = $request->body;
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $password = (string) ($payload['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) return Response::error('Valid email and a password of at least 12 characters are required.', 422);
    $role = $db->fetch('SELECT id FROM roles WHERE role_key = ?', [$payload['roleKey'] ?? 'viewer']);
    if (!$role) return Response::error('Role not found.', 422);
    $id = $db->insert('INSERT INTO admin_users (role_id, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())', [$role['id'], $email, trim((string) ($payload['displayName'] ?? $email)), password_hash($password, PASSWORD_ARGON2ID)]);
    $db->execute('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, created_at) VALUES (?, ?, ?, ?, NOW())', [$user['id'], 'admin_user.created', 'admin_user', (string) $id]);
    return Response::json(['id' => $id], 201);
});

$router->add('PUT', '/api/admin/users/{id}', function (Request $request, array $params) use ($auth, $db, $csrf) {
    $user = $auth->requirePermission('settings.manage');
    $csrf($request);
    $id = (int) $params['id'];
    $payload = $request->body;
    $role = $db->fetch('SELECT id FROM roles WHERE role_key = ?', [$payload['roleKey'] ?? 'viewer']);
    if (!$role) return Response::error('Role not found.', 422);
    $db->execute('UPDATE admin_users SET role_id = ?, display_name = ?, is_active = ?, session_version = session_version + 1, updated_at = NOW() WHERE id = ?', [$role['id'], trim((string) ($payload['displayName'] ?? '')), !empty($payload['active']) ? 1 : 0, $id]);
    if (!empty($payload['password'])) {
        if (strlen((string) $payload['password']) < 12) return Response::error('Password must be at least 12 characters.', 422);
        $db->execute('UPDATE admin_users SET password_hash = ?, session_version = session_version + 1, updated_at = NOW() WHERE id = ?', [password_hash((string) $payload['password'], PASSWORD_ARGON2ID), $id]);
    }
    $db->execute('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, after_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [$user['id'], 'admin_user.updated', 'admin_user', (string) $id, json_encode(['roleKey' => $payload['roleKey'] ?? 'viewer', 'active' => !empty($payload['active'])])]);
    return Response::json(['saved' => true]);
});

$router->add('GET', '/api/admin/notifications', function () use ($auth, $db) {
    $auth->requirePermission('dashboard.view');
    return Response::json(['items' => $db->fetchAll('SELECT * FROM notification_events ORDER BY acknowledged_at IS NULL DESC, created_at DESC LIMIT 250')]);
});

$router->add('POST', '/api/admin/notifications/{id}/acknowledge', function (Request $request, array $params) use ($auth, $db, $csrf) {
    $user = $auth->requirePermission('dashboard.view');
    $csrf($request);
    $db->execute('UPDATE notification_events SET acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ?', [$user['id'], (int) $params['id']]);
    return Response::json(['acknowledged' => true]);
});

$router->add('GET', '/api/admin/analytics/funnel', function () use ($auth, $db) {
    $auth->requirePermission('dashboard.view');
    $rows = $db->fetchAll('SELECT event_name, COUNT(*) eventCount, DATE(created_at) eventDate FROM analytics_events WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY event_name, DATE(created_at) ORDER BY eventDate, event_name');
    $dropoff = $db->fetchAll('SELECT current_section section, COUNT(*) sessions FROM survey_sessions WHERE status = ? AND last_activity_at < DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY current_section ORDER BY current_section', ['in_progress']);
    return Response::json(['events' => $rows, 'dropoff' => $dropoff]);
});

$router->add('POST', '/api/admin/integrations/{provider}/test', function (Request $request, array $params) use ($auth, $container, $db, $csrf) {
    $user = $auth->requirePermission('settings.manage');
    $csrf($request);
    $provider = strtolower($params['provider']);
    try {
        if ($provider === 'email') {
            $recipient = (string) ($request->body['recipient'] ?? $user['email']);
            $id = $container['admin']->testEmail($recipient, (int) $user['id']);
            $message = 'Test email queued as ' . $id;
        } elseif ($provider === 'stripe') {
            $secret = $container['settings']->get('stripe.secret_key', $_ENV['STRIPE_SECRET_KEY'] ?? '');
            if (!$secret) throw new RuntimeException('Stripe secret key is not configured.');
            $account = (new StripeClient($secret))->accounts->retrieve();
            $message = 'Connected to Stripe account ' . ($account->id ?? 'unknown');
        } else {
            throw new InvalidArgumentException('Unsupported integration provider.');
        }
        $db->execute('INSERT INTO api_connection_tests (provider_key, status, message, tested_by, tested_at) VALUES (?, ?, ?, ?, NOW())', [$provider, 'success', $message, $user['id']]);
        return Response::json(['status' => 'success', 'message' => $message]);
    } catch (Throwable $error) {
        $db->execute('INSERT INTO api_connection_tests (provider_key, status, message, tested_by, tested_at) VALUES (?, ?, ?, ?, NOW())', [$provider, 'failed', mb_substr($error->getMessage(), 0, 500), $user['id']]);
        return Response::error($error->getMessage(), 422);
    }
});
