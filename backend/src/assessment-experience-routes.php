<?php
declare(strict_types=1);

use AtomGlobal\Http\Request;
use AtomGlobal\Http\Response;

$router->add('GET', '/api/public/assessment-experience', function () use ($container) {
    header('Cache-Control: no-store, max-age=0');
    return Response::json($container['assessmentExperience']->publicConfiguration());
});

$router->add('GET', '/api/admin/assessment-experience', function () use ($auth, $container) {
    $auth->requirePermission('assessments.manage');
    return Response::json($container['assessmentExperience']->administrationConfiguration());
});

$router->add('PUT', '/api/admin/assessment-experience/landing', function (Request $request) use ($auth, $container, $csrf) {
    $user = $auth->requirePermission('assessments.manage');
    $csrf($request);
    return Response::json($container['assessmentExperience']->saveLanding($request->body, (int) $user['id']));
});

$router->add('PUT', '/api/admin/assessment-experience/live-track', function (Request $request) use ($auth, $container, $csrf) {
    $user = $auth->requirePermission('assessments.manage');
    $csrf($request);
    return Response::json($container['assessmentExperience']->saveLiveTrack((string) ($request->body['trackKey'] ?? ''), (int) $user['id']));
});

$router->add('GET', '/api/admin/assessment-tracks/{id}/experience', function (Request $request, array $params) use ($auth, $container) {
    $auth->requirePermission('assessments.manage');
    return Response::json($container['assessmentExperience']->byTrackId((int) $params['id']));
});

$router->add('PUT', '/api/admin/assessment-tracks/{id}/experience', function (Request $request, array $params) use ($auth, $container, $csrf) {
    $user = $auth->requirePermission('assessments.manage');
    $csrf($request);
    return Response::json($container['assessmentExperience']->save((int) $params['id'], $request->body, (int) $user['id']));
});
