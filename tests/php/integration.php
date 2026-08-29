#!/usr/bin/env php
<?php
declare(strict_types=1);

session_name('hhaa_test_admin');
session_start();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'HeadHeartIntegrationTest/1.0';

$container = require dirname(__DIR__, 2) . '/backend/src/bootstrap.php';
if (($container['config']['env'] ?? '') !== 'testing') {
    fwrite(STDERR, "Integration tests may run only with APP_ENV=testing.\n");
    exit(1);
}
$db = $container['db'];

function expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$ownerRole = $db->fetch('SELECT id FROM roles WHERE role_key = ?', ['owner']);
expect((bool) $ownerRole, 'Owner role is missing.');
$email = 'owner-integration@example.test';
$password = 'Integration-Test-Password-2026!';
$user = $db->fetch('SELECT id FROM admin_users WHERE email = ?', [$email]);
if (!$user) {
    $userId = $db->insert(
        'INSERT INTO admin_users (role_id, email, display_name, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
        [$ownerRole['id'], $email, 'Integration Owner', password_hash($password, PASSWORD_ARGON2ID)]
    );
} else {
    $userId = (int) $user['id'];
}

$auth = new AtomGlobal\Security\Auth($db);
$adminUser = $auth->attempt($email, $password);
expect((bool) $adminUser, 'Admin authentication failed.');
expect($adminUser['roleKey'] === 'owner', 'Owner role was not returned.');
expect(in_array('branding.manage', $adminUser['permissions'], true), 'Owner permissions are incomplete.');
expect(in_array('feedback.manage', $adminUser['permissions'], true), 'Owner feedback permission is missing.');
expect((int) $auth->requirePermission('settings.manage')['id'] === $userId, 'Owner permission shortcut failed.');

$configuration = $container['admin']->publicConfiguration();
expect(($configuration['branding']['logoUrl'] ?? '') !== '', 'Published/default logo is missing.');
expect(count($configuration['tracks']) === 4, 'Four assessment tracks were not configured.');
expect(count($configuration['stages']) >= 7, 'Stage content was not seeded.');

$participant = [
    'name' => 'Integration Participant',
    'email' => 'participant-integration@example.test',
    'role' => 'Manager',
    'industry' => 'Professional services',
    'privacyConsent' => true,
    'transactionalConsent' => true,
    'marketingConsent' => false,
];
$created = $container['surveys']->create([
    'trackKey' => 'personal',
    'participant' => $participant,
    'affiliateCode' => '',
    'attribution' => ['landingPage' => '/?utm_source=integration', 'utmSource' => 'integration'],
]);
expect((int) $created['id'] > 0, 'Survey session was not created.');
expect((bool) preg_match('/^[a-f0-9]{64}$/', $created['resumeToken']), 'Resume token is not 256-bit hexadecimal.');
expect(count($created['assessment']['questions'] ?? []) === 40, 'V3 published 40 questions were not returned to the participant frontend.');
expect(count($created['assessment']['sections'] ?? []) === 10, 'Published sections were not returned to the participant frontend.');
expect(count($created['assessment']['answerChoices'] ?? []) === 5, 'Published answer choices were not returned to the participant frontend.');
expect(($created['assessment']['questions'][0]['text'] ?? '') !== '', 'Published question text is empty.');

$answers = array_fill(0, 40, ['value' => 3, 'note' => '']);
$answers[0] = ['value' => 5, 'note' => 'Integration note'];
$saved = $container['surveys']->save((int) $created['id'], [
    'resumeToken' => $created['resumeToken'],
    'answers' => $answers,
    'section' => 9,
]);
expect((int) $saved['completionPercentage'] === 100, 'Autosave completion percentage is incorrect.');

$resumed = $container['surveys']->resume($created['resumeToken']);
expect((int) $resumed['answers'][0]['value'] === 5, 'Saved answer was not restored.');
expect($resumed['participant']['email'] === $participant['email'], 'Participant was not restored.');
expect(count($resumed['assessment']['questions'] ?? []) === 40, 'Resume did not restore the V3 40-question snapshot.');

$completed = $container['surveys']->complete((int) $created['id'], [
    'resumeToken' => $created['resumeToken'],
    'answers' => $answers,
    'section' => 9,
]);
expect($completed['status'] === 'completed', 'Survey did not complete.');
expect((bool) preg_match('/^[a-f0-9]{64}$/', $completed['reportToken']), 'Report token is invalid.');

$report = $container['reports']->byToken($completed['reportToken']);
expect((bool) $report, 'Secure report could not be loaded.');
expect($report['is_unlocked'] === false, 'New report should begin as Lite/locked.');
expect($report['paid_report_json'] === null, 'Paid report content leaked before unlock.');
expect($report['participantEmail'] === $participant['email'], 'Report participant metadata is missing.');
expect($report['trackKey'] === 'personal', 'Report track metadata is missing.');

$lockedPdfBlocked = false;
try {
    $container['pdf']->generate((int) $completed['reportId']);
} catch (RuntimeException $error) {
    $lockedPdfBlocked = $error->getCode() === 403 || str_contains($error->getMessage(), 'locked');
}
expect($lockedPdfBlocked, 'Locked report allowed paid PDF generation.');
expect($container['reports']->pdfByToken($completed['reportToken']) === null, 'Locked report exposed a paid PDF.');

$container['reports']->unlockBySession((int) $created['id'], 'integration_test');
$unlocked = $container['reports']->byToken($completed['reportToken']);
expect($unlocked['is_unlocked'] === true, 'Report did not unlock.');
expect($unlocked['paid_report_json'] !== null, 'Unlocked paid report content is missing.');
$pdfPath = $container['pdf']->generate((int) $completed['reportId']);
expect(is_file($pdfPath) && filesize($pdfPath) > 1000, 'PDF generation failed after unlock.');
expect($container['reports']->pdfByToken($completed['reportToken']) === $pdfPath, 'Unlocked secure PDF lookup failed.');

$draftBranding = $configuration['branding'];
$draftBranding['cta'] = '#C9A15A';
$draftId = $container['admin']->saveBrandingDraft($draftBranding, $userId);
expect($draftId > 0, 'Branding draft was not saved.');
$container['admin']->publishBranding($draftId, $userId);
expect($container['settings']->get('branding.cta') === '#C9A15A', 'Published branding did not update settings.');

$queued = $db->fetch('SELECT COUNT(*) count FROM email_queue WHERE recipient_email = ?', [$participant['email']]);
expect((int) ($queued['count'] ?? 0) >= 4, 'Registration, resume, completion and report emails were not queued.');

$templateTest = $container['adminInsights']->queueTemplateTest('participant_registration', 'template-test@example.test', ['participantName' => 'Template Test'], $userId);
expect((int) $templateTest['queueId'] > 0, 'Selected email template test was not queued.');
$testQueue = $db->fetch('SELECT template_key, recipient_email FROM email_queue WHERE id = ?', [$templateTest['queueId']]);
expect($testQueue['template_key'] === 'participant_registration', 'Template test queued the wrong template.');
expect($testQueue['recipient_email'] === 'template-test@example.test', 'Template test queued the wrong recipient.');

$feedback = $container['feedback']->create([
    'submitterName' => 'Sunil Integration',
    'submitterEmail' => 'sunil-feedback@example.test',
    'feedbackType' => 'improvement',
    'moduleName' => 'Dashboard',
    'priority' => 'normal',
    'title' => 'Improve progress graph labels',
    'details' => 'The graph should explain the date range and the meaning of each series.',
    'expectedOutcome' => 'A clear graph legend and date-range label.',
    'pageUrl' => 'https://head-heart.atomglobal.com/admin',
], $adminUser);
expect((int) $feedback['id'] > 0, 'Client feedback was not created.');
expect($feedback['status'] === 'new', 'New feedback status is incorrect.');
expect(in_array($feedback['githubSyncStatus'], ['not_configured', 'synced'], true), 'Feedback GitHub status is invalid.');
expect(count($feedback['updates'] ?? []) >= 1, 'Feedback change history was not created.');

$feedbackDone = $container['feedback']->update((int) $feedback['id'], [
    'status' => 'done',
    'priority' => 'normal',
    'message' => 'The graph labels were reviewed.',
    'resolution' => 'Added a date-range label and a clear series legend.',
], $adminUser);
expect($feedbackDone['status'] === 'done', 'Feedback could not be marked done.');
expect($feedbackDone['resolution'] !== '', 'Feedback completion note is missing.');
$feedbackEmails = $db->fetch('SELECT COUNT(*) count FROM email_queue WHERE recipient_email = ? AND template_key IN (?, ?)', ['sunil-feedback@example.test', 'feedback_received', 'feedback_completed']);
expect((int) ($feedbackEmails['count'] ?? 0) >= 2, 'Feedback acknowledgement and completion emails were not queued.');

$feedbackList = $container['feedback']->list(['search' => 'progress graph']);
expect(count($feedbackList['items'] ?? []) >= 1, 'Feedback search did not return the submitted item.');

$insights = $container['adminInsights']->dashboard();
expect(count($insights['daily'] ?? []) === 14, 'Dashboard trend does not contain fourteen days.');
expect(count($insights['funnel'] ?? []) === 4, 'Dashboard funnel is incomplete.');
expect(count($insights['trackProgress'] ?? []) === 4, 'Dashboard track progress is incomplete.');

$search = $container['adminInsights']->search('participant-integration', $adminUser['permissions']);
expect(count($search) >= 1, 'Global admin search did not find the participant.');
expect($search[0]['module'] === 'Participants', 'Global admin search returned the wrong module first.');

$logs = $db->fetch('SELECT COUNT(*) count FROM audit_logs WHERE admin_user_id = ?', [$userId]);
expect((int) ($logs['count'] ?? 0) >= 4, 'Audit log did not record administration and feedback actions.');

$auth->logout();
echo "Production integration tests passed.\n";
