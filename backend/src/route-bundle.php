<?php
declare(strict_types=1);

use AtomGlobal\Http\Request;
use AtomGlobal\Http\Response;
use AtomGlobal\Payments\StripeCheckoutReconciler;
use AtomGlobal\Security\RateLimiter;

require __DIR__ . '/question-text-policy-routes.php';
require __DIR__ . '/extra-routes.php';
require __DIR__ . '/attribution-routes.php';
require __DIR__ . '/feedback-routes.php';
require __DIR__ . '/assessment-experience-routes.php';

$router->add('GET', '/api/payments/status', function (Request $request) use ($db, $container, $config) {
    $checkout = trim((string) ($request->query['checkout'] ?? ''));
    if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $checkout)) {
        return Response::error('Checkout reference is invalid.', 422);
    }

    $key = 'payment-status:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ':' . hash('sha256', $checkout);
    if (!(new RateLimiter($db))->hit($key, 120, 300)) {
        return Response::error('Please wait before checking payment status again.', 429);
    }

    $payment = $db->fetch(
        'SELECT id, status, paid_at, metadata_json FROM payments WHERE provider = ? AND stripe_checkout_session_id = ? LIMIT 1',
        ['stripe', $checkout]
    );
    if (!$payment) return Response::error('Checkout reference was not found.', 404);

    if (in_array($payment['status'], ['checkout_started', 'paid'], true)) {
        try {
            $reconciler = new StripeCheckoutReconciler($db, $container['settings'], $container['reports'], $config);
            $reconciler->reconcile($checkout);
        } catch (\Throwable $error) {
            error_log('V4 Stripe checkout reconciliation: ' . $error->getMessage());
        }

        $payment = $db->fetch(
            'SELECT id, status, paid_at, metadata_json FROM payments WHERE provider = ? AND stripe_checkout_session_id = ? LIMIT 1',
            ['stripe', $checkout]
        ) ?: $payment;
    }

    $metadata = json_decode((string) ($payment['metadata_json'] ?? '{}'), true) ?: [];
    $paid = $payment['status'] === 'paid';
    $reportUrl = $paid ? trim((string) ($metadata['reportUrl'] ?? '')) : '';
    $reportId = (int) ($metadata['reportId'] ?? 0);
    $reportReady = $paid && $reportUrl !== '' && $reportId > 0;
    $emailStatus = null;
    if ($reportId > 0) {
        $email = $db->fetch(
            "SELECT status FROM email_queue WHERE template_key = ? AND JSON_UNQUOTE(JSON_EXTRACT(variables_json, '$.reportId')) = ? ORDER BY id DESC LIMIT 1",
            ['paid_report_ready', (string) $reportId]
        );
        $emailStatus = $email['status'] ?? null;
    }
    $adminNotified = (bool) $db->fetch(
        'SELECT id FROM notification_events WHERE event_key = ? AND entity_type = ? AND entity_id = ? LIMIT 1',
        ['payment_paid', 'payment', (string) $payment['id']]
    );

    $progress = $reportReady ? 100 : ($paid ? 85 : 55);
    $stage = $reportReady ? 'Full Report ready' : ($paid ? 'Unlocking Full Report' : 'Verifying payment');

    return Response::json([
        'status' => (string) $payment['status'],
        'paid' => $paid,
        'reportReady' => $reportReady,
        'reportUrl' => $reportReady ? $reportUrl : null,
        'reportId' => $reportId > 0 ? $reportId : null,
        'paidAt' => $paid ? ($payment['paid_at'] ?? null) : null,
        'progress' => $progress,
        'stage' => $stage,
        'pdfEmailStatus' => $emailStatus,
        'adminNotified' => $adminNotified,
    ]);
});

$router->add('POST', '/api/admin/password-reset/request', function (Request $request) use ($container, $db) {
    $email = strtolower(trim((string) ($request->body['email'] ?? '')));
    $key = 'admin-password-reset:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ':' . hash('sha256', $email);
    if (!(new RateLimiter($db))->hit($key, 5, 3600)) {
        return Response::json(['accepted' => true]);
    }
    $container['passwordReset']->request($email);
    return Response::json(['accepted' => true]);
});

$router->add('POST', '/api/admin/password-reset/confirm', function (Request $request) use ($container, $db) {
    $key = 'admin-password-reset-confirm:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!(new RateLimiter($db))->hit($key, 10, 3600)) {
        return Response::error('Too many reset attempts.', 429);
    }
    $container['passwordReset']->confirm((string) ($request->body['token'] ?? ''), (string) ($request->body['password'] ?? ''));
    return Response::json(['reset' => true]);
});

$router->add('GET', '/api/admin/insights', function () use ($auth, $container) {
    $auth->requirePermission('dashboard.view');
    return Response::json($container['adminInsights']->dashboard());
});

$router->add('GET', '/api/admin/search', function (Request $request) use ($auth, $container) {
    $user = $auth->requirePermission('dashboard.view');
    $term = trim((string) ($request->query['q'] ?? ''));
    $items = $container['adminInsights']->search($term, $user['permissions'] ?? []);
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    if (mb_strlen($term) >= 2 && (in_array('*', $permissions, true) || in_array('feedback.submit', $permissions, true))) {
        $feedback = $container['feedback']->list(['search' => $term, 'limit' => 6]);
        foreach ($feedback['items'] ?? [] as $item) {
            $items[] = [
                'type' => 'feedback',
                'module' => 'Feedback',
                'id' => (int) $item['id'],
                'title' => $item['title'],
                'subtitle' => $item['submitterEmail'],
                'meta' => $item['moduleName'] . ' · ' . ucwords(str_replace('_', ' ', $item['status'])) . ' · ' . ucfirst($item['priority']),
                'query' => $item['title'],
            ];
        }
    }
    return Response::json(['items' => array_slice($items, 0, 24)]);
});

$router->add('POST', '/api/admin/email-templates/{key}/test', function (Request $request, array $params) use ($auth, $container, $csrf) {
    $user = $auth->requirePermission('email.manage');
    $csrf($request);
    $variables = is_array($request->body['variables'] ?? null) ? $request->body['variables'] : [];
    return Response::json($container['adminInsights']->queueTemplateTest(
        (string) $params['key'],
        (string) ($request->body['recipient'] ?? ''),
        $variables,
        (int) $user['id']
    ), 201);
});

$router->add('GET', '/api/public/pages/{key}', function (Request $request, array $params) use ($db) {
    $page = $db->fetch('SELECT page_key pageKey, path, page_title pageTitle, meta_description metaDescription, canonical_url canonicalUrl, robots_setting robotsSetting, og_title ogTitle, og_description ogDescription, heading, introductory_content introductoryContent, faq_json faqJson, structured_data_json structuredDataJson, include_in_sitemap includeInSitemap, updated_at updatedAt FROM seo_pages WHERE page_key = ? LIMIT 1', [$params['key']]);
    if (!$page) return Response::error('Page not found.', 404);
    $page['faq'] = json_decode($page['faqJson'] ?: '[]', true) ?: [];
    $page['structuredData'] = json_decode($page['structuredDataJson'] ?: '{}', true) ?: [];
    unset($page['faqJson'], $page['structuredDataJson']);
    $page['includeInSitemap'] = (bool) $page['includeInSitemap'];
    return Response::json($page);
});
