<?php
declare(strict_types=1);

use AtomGlobal\Http\Request;
use AtomGlobal\Http\Response;
use AtomGlobal\Http\Router;
use AtomGlobal\Payments\CashOnDeliveryService;
use AtomGlobal\Payments\StripeService;
use AtomGlobal\Security\Auth;
use AtomGlobal\Security\Csrf;
use AtomGlobal\Security\RateLimiter;

$container = require dirname(__DIR__) . '/src/bootstrap.php';
$config = $container['config'];
ini_set('display_errors', $config['debug'] ? '1' : '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

session_name($config['session_cookie']);
session_set_cookie_params([
    'lifetime' => $config['session_lifetime'],
    'path' => '/',
    'secure' => $config['session_secure'],
    'httponly' => true,
    'samesite' => $config['session_same_site'],
]);
session_start();

$request = Request::capture();
$router = new Router();
$db = $container['db'];
$auth = new Auth($db);
$admin = $container['admin'];

$csrf = static function (Request $request): void {
    if (!Csrf::verify($request->header('x-csrf-token'))) {
        throw new \RuntimeException('Invalid CSRF token.', 419);
    }
};

$auditLogin = static function (?array $user, string $action) use ($db): void {
    $db->execute(
        'INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, ip_hash, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
        [$user['id'] ?? null, $action, 'admin_user', isset($user['id']) ? (string) $user['id'] : null, hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '')]
    );
};

// Public system and participant routes.
$router->add('GET', '/api/health', fn() => Response::json($container['health']->check()));
$router->add('GET', '/api/csrf', fn() => Response::json(['token' => Csrf::token()]));
$router->add('GET', '/api/public/configuration', fn() => Response::json($admin->publicConfiguration()));
$router->add('POST', '/api/survey-sessions', fn(Request $request) => Response::json($container['surveys']->create($request->body), 201));
$router->add('GET', '/api/survey-sessions/resume/{token}', fn(Request $request, array $params) => Response::json($container['surveys']->resume($params['token'])));
$router->add('PATCH', '/api/survey-sessions/{id}', fn(Request $request, array $params) => Response::json($container['surveys']->save((int) $params['id'], $request->body)));
$router->add('POST', '/api/survey-sessions/{id}/complete', fn(Request $request, array $params) => Response::json($container['surveys']->complete((int) $params['id'], $request->body)));
$router->add('GET', '/api/reports/{token}', function (Request $request, array $params) use ($container) {
    $report = $container['reports']->byToken($params['token']);
    return $report ? Response::json($report) : Response::error('Report not found or expired.', 404);
});
$router->add('POST', '/api/payments/checkout', function (Request $request) use ($container, $config) {
    $stripe = new StripeService($container['db'], $container['settings'], $container['reports'], $config);
    return Response::json($stripe->checkout((int) ($request->body['sessionId'] ?? 0), (string) ($request->body['track'] ?? ''), $request->body['affiliateCode'] ?? null));
});
$router->add('POST', '/api/payments/cash-on-delivery', function (Request $request) use ($container, $config) {
    $cash = new CashOnDeliveryService($container['db'], $container['settings'], $container['reports'], $config);
    $result = $cash->checkout((int) ($request->body['sessionId'] ?? 0), (string) ($request->body['track'] ?? ''));
    $delivery = $container['mailQueueProcessor']->processIds($result['emailQueueIds'] ?? []);
    unset($result['emailQueueIds']);
    $allSent = count($delivery) === 2 && count(array_filter($delivery, static fn(array $item): bool => $item['status'] === 'sent')) === 2;
    $result['emailDelivery'] = $allSent ? 'sent' : 'retrying';
    $result['successUrl'] .= '&email=' . rawurlencode($result['emailDelivery']);
    return Response::json($result);
});
$router->add('POST', '/api/stripe/webhook', function (Request $request) use ($container, $config) {
    $stripe = new StripeService($container['db'], $container['settings'], $container['reports'], $config);
    $stripe->webhook($request->rawBody, $request->header('stripe-signature') ?? '');
    return Response::json(['received' => true]);
});

// Secure administration session.
$router->add('POST', '/api/admin/login', function (Request $request) use ($db, $auth, $auditLogin) {
    if (!(new RateLimiter($db))->hit('admin-login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 10, 900)) {
        return Response::error('Too many login attempts.', 429);
    }
    $user = $auth->attempt((string) ($request->body['email'] ?? ''), (string) ($request->body['password'] ?? ''));
    if (!$user) {
        $auditLogin(null, 'admin.login_failed');
        return Response::error('Invalid credentials.', 422);
    }
    $auditLogin($user, 'admin.login');
    return Response::json(['user' => $user, 'csrfToken' => Csrf::token()]);
});
$router->add('GET', '/api/admin/session', function () use ($auth) {
    $user = $auth->current();
    return $user ? Response::json(['user' => $user, 'csrfToken' => Csrf::token()]) : Response::error('Unauthorised', 401);
});
$router->add('POST', '/api/admin/logout', function (Request $request) use ($auth, $csrf, $auditLogin) {
    $user = $auth->requireUser();
    $csrf($request);
    $auditLogin($user, 'admin.logout');
    $auth->logout();
    return Response::json(['loggedOut' => true]);
});

// Dashboard and participant records.
$router->add('GET', '/api/admin/dashboard', function () use ($auth, $admin) { $auth->requirePermission('dashboard.view'); return Response::json($admin->dashboard()); });
$router->add('GET', '/api/admin/participants', function (Request $request) use ($auth, $admin) { $auth->requirePermission('participants.view'); return Response::json($admin->participants($request->query)); });
$router->add('GET', '/api/admin/participants/{id}', function (Request $request, array $params) use ($auth, $admin) { $auth->requirePermission('participants.view'); return Response::json($admin->participant((int) $params['id'])); });
$router->add('GET', '/api/admin/participants/{id}/export', function (Request $request, array $params) use ($auth, $container) { $auth->requirePermission('participants.export'); return Response::json($container['privacy']->export((int) $params['id'])); });
$router->add('DELETE', '/api/admin/participants/{id}', function (Request $request, array $params) use ($auth, $container, $csrf) { $user = $auth->requirePermission('participants.delete'); $csrf($request); $container['privacy']->anonymise((int) $params['id'], (int) $user['id']); return Response::json(['anonymised' => true]); });

// Assessment, content, media and branding CMS.
$router->add('GET', '/api/admin/assessments', function () use ($auth, $admin) { $auth->requirePermission('assessments.manage'); return Response::json(['items' => $admin->assessments()]); });
$router->add('GET', '/api/admin/assessment-versions/{id}', function (Request $request, array $params) use ($auth, $admin) { $auth->requirePermission('assessments.manage'); return Response::json($admin->assessmentVersion((int) $params['id'])); });
$router->add('POST', '/api/admin/assessment-versions/{id}/clone', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('assessments.manage'); $csrf($request); $id = $admin->cloneAssessment((int) $params['id'], (int) $user['id'], (string) ($request->body['versionNumber'] ?? ''), (string) ($request->body['changeSummary'] ?? '')); return Response::json(['versionId' => $id], 201); });
$router->add('POST', '/api/admin/assessment-versions/{id}/publish', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('assessments.manage'); $csrf($request); $admin->publishAssessment((int) $params['id'], (int) $user['id']); return Response::json(['published' => true]); });
$router->add('GET', '/api/admin/content-stages', function () use ($auth, $admin) { $auth->requirePermission('content.manage'); return Response::json(['items' => $admin->publicConfiguration()['stages']]); });
$router->add('PATCH', '/api/admin/content-stages/{key}', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('content.manage'); $csrf($request); return Response::json($admin->saveStage($params['key'], $request->body, (int) $user['id'])); });
$router->add('GET', '/api/admin/media', function () use ($auth, $container) { $auth->requirePermission('content.manage'); return Response::json(['items' => $container['media']->all()]); });
$router->add('POST', '/api/admin/media', function (Request $request) use ($auth, $container, $csrf) { $user = $auth->requirePermission('content.manage'); $csrf($request); return Response::json($container['media']->upload($request->file('file') ?? [], $request->body, (int) $user['id']), 201); });
$router->add('GET', '/api/admin/branding', function () use ($auth, $admin) { $auth->requirePermission('branding.manage'); return Response::json($admin->branding()); });
$router->add('PUT', '/api/admin/branding/draft', function (Request $request) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('branding.manage'); $csrf($request); return Response::json(['draftId' => $admin->saveBrandingDraft($request->body, (int) $user['id'])]); });
$router->add('POST', '/api/admin/branding/{id}/publish', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('branding.manage'); $csrf($request); $admin->publishBranding((int) $params['id'], (int) $user['id']); return Response::json(['published' => true]); });

// Reports and payments.
$router->add('GET', '/api/admin/reports', function () use ($auth, $admin) { $auth->requirePermission('reports.manage'); return Response::json(['items' => $admin->reports()]); });
$router->add('POST', '/api/admin/reports/{id}/unlock', function (Request $request, array $params) use ($auth, $container, $csrf) { $user = $auth->requirePermission('reports.manage'); $csrf($request); $container['reportAdmin']->unlock((int) $params['id'], (int) $user['id']); return Response::json(['unlocked' => true]); });
$router->add('POST', '/api/admin/reports/{id}/lock', function (Request $request, array $params) use ($auth, $container, $csrf) { $user = $auth->requirePermission('reports.manage'); $csrf($request); $container['reportAdmin']->lock((int) $params['id'], (int) $user['id']); return Response::json(['unlocked' => false]); });
$router->add('POST', '/api/admin/reports/{id}/revoke', function (Request $request, array $params) use ($auth, $container, $csrf) { $user = $auth->requirePermission('reports.manage'); $csrf($request); $container['reportAdmin']->revoke((int) $params['id'], (int) $user['id']); return Response::json(['revoked' => true]); });
$router->add('POST', '/api/admin/reports/{id}/resend', function (Request $request, array $params) use ($auth, $container, $csrf) { $user = $auth->requirePermission('reports.manage'); $csrf($request); return Response::json($container['reportAdmin']->resend((int) $params['id'], (int) $user['id'])); });
$router->add('GET', '/api/admin/payments', function () use ($auth, $admin) { $auth->requirePermission('payments.manage'); return Response::json(['items' => $admin->payments()]); });

// Email, alerts and integrations.
$router->add('GET', '/api/admin/email-templates', function () use ($auth, $admin) { $auth->requirePermission('email.manage'); return Response::json(['items' => $admin->emailTemplates()]); });
$router->add('PUT', '/api/admin/email-templates/{key}', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('email.manage'); $csrf($request); $admin->saveEmailTemplate($params['key'], $request->body, (int) $user['id']); return Response::json(['saved' => true]); });
$router->add('GET', '/api/admin/email-queue', function (Request $request) use ($auth, $admin) { $auth->requirePermission('email.manage'); return Response::json(['items' => $admin->emailQueue($request->query)]); });
$router->add('POST', '/api/admin/email-queue/{id}/retry', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('email.manage'); $csrf($request); $admin->retryEmail((int) $params['id'], (int) $user['id']); return Response::json(['queued' => true]); });
$router->add('POST', '/api/admin/email/test', function (Request $request) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('email.manage'); $csrf($request); return Response::json(['queueId' => $admin->testEmail((string) ($request->body['recipient'] ?? ''), (int) $user['id'])]); });
$router->add('GET', '/api/admin/alert-recipients', function () use ($auth, $admin) { $auth->requirePermission('settings.manage'); return Response::json(['items' => $admin->alertRecipients()]); });
$router->add('POST', '/api/admin/alert-recipients', function (Request $request) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('settings.manage'); $csrf($request); return Response::json(['id' => $admin->saveAlertRecipient(null, $request->body, (int) $user['id'])], 201); });
$router->add('PUT', '/api/admin/alert-recipients/{id}', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('settings.manage'); $csrf($request); return Response::json(['id' => $admin->saveAlertRecipient((int) $params['id'], $request->body, (int) $user['id'])]); });

// Affiliates, SEO, settings and audit.
$router->add('GET', '/api/admin/affiliates', function () use ($auth, $admin) { $auth->requirePermission('affiliates.manage'); return Response::json(['items' => $admin->affiliates()]); });
$router->add('POST', '/api/admin/affiliates', function (Request $request) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('affiliates.manage'); $csrf($request); return Response::json(['id' => $admin->saveAffiliate(null, $request->body, (int) $user['id'])], 201); });
$router->add('PUT', '/api/admin/affiliates/{id}', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('affiliates.manage'); $csrf($request); return Response::json(['id' => $admin->saveAffiliate((int) $params['id'], $request->body, (int) $user['id'])]); });
$router->add('GET', '/api/admin/seo-pages', function () use ($auth, $admin) { $auth->requirePermission('seo.manage'); return Response::json(['items' => $admin->seoPages()]); });
$router->add('PUT', '/api/admin/seo-pages/{key}', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('seo.manage'); $csrf($request); $admin->saveSeo($params['key'], $request->body, (int) $user['id']); return Response::json(['saved' => true]); });
$router->add('GET', '/api/admin/settings', function (Request $request) use ($auth, $admin) { $auth->requirePermission('settings.manage'); $groups = isset($request->query['groups']) ? array_filter(explode(',', (string) $request->query['groups'])) : []; return Response::json($admin->settings($groups)); });
$router->add('PUT', '/api/admin/settings/{group}', function (Request $request, array $params) use ($auth, $admin, $csrf) { $user = $auth->requirePermission('settings.manage'); $csrf($request); $admin->saveSettings($params['group'], $request->body, (int) $user['id']); return Response::json(['saved' => true]); });
$router->add('GET', '/api/admin/audit-logs', function (Request $request) use ($auth, $admin) { $auth->requirePermission('audit.view'); return Response::json(['items' => $admin->auditLogs($request->query)]); });

// Additional production routes: PDF, question/report editors, users, alerts, analytics, attribution and password recovery.
require dirname(__DIR__) . '/src/route-bundle.php';

try {
    $router->dispatch($request);
} catch (\InvalidArgumentException $error) {
    Response::error($error->getMessage(), 422);
} catch (\RuntimeException $error) {
    $code = in_array($error->getCode(), [401, 403, 404, 419, 429], true) ? $error->getCode() : 500;
    error_log((string) $error);
    Response::error($code === 500 && !$config['debug'] ? 'An unexpected error occurred.' : $error->getMessage(), $code);
} catch (\Throwable $error) {
    error_log((string) $error);
    Response::error($config['debug'] ? $error->getMessage() : 'An unexpected error occurred.', 500);
}
