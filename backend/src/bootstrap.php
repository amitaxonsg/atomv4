<?php
declare(strict_types=1);

use AtomGlobal\Database;
use AtomGlobal\Env;
use AtomGlobal\Mail\MailDeliveryService;
use AtomGlobal\Mail\MailQueue;
use AtomGlobal\Mail\MailQueueProcessor;
use AtomGlobal\Security\Crypto;
use AtomGlobal\Services\AdminInsightsService;
use AtomGlobal\Services\AdminService;
use AtomGlobal\Services\AssessmentExperienceService;
use AtomGlobal\Services\AttributionService;
use AtomGlobal\Services\FeedbackService;
use AtomGlobal\Services\HealthService;
use AtomGlobal\Services\MediaService;
use AtomGlobal\Services\PasswordResetService;
use AtomGlobal\Services\PdfService;
use AtomGlobal\Services\PrivacyService;
use AtomGlobal\Services\ReportAdminService;
use AtomGlobal\Services\ReportService;
use AtomGlobal\Services\ScoringService;
use AtomGlobal\Services\SettingsService;
use AtomGlobal\Services\SurveyService;

require dirname(__DIR__) . '/vendor/autoload.php';
Env::load(dirname(__DIR__) . '/.env');
$config = require dirname(__DIR__) . '/config/app.php';
$db = new Database(require dirname(__DIR__) . '/config/database.php');
$crypto = new Crypto($config['key']);
$settings = new SettingsService($db, $crypto);
$reports = new ReportService($db, $settings, $config);
$mailQueue = new MailQueue($db);
$pdf = new PdfService($db, $settings, $config);
$mailDelivery = new MailDeliveryService($db, $settings);
$mailQueueProcessor = new MailQueueProcessor($db, $mailQueue, $mailDelivery, $pdf);

return [
    'config' => $config,
    'db' => $db,
    'crypto' => $crypto,
    'settings' => $settings,
    'reports' => $reports,
    'privacy' => new PrivacyService($db),
    'surveys' => new SurveyService($db, new ScoringService(), $reports, $mailQueue, $settings, $config),
    'health' => new HealthService($db, $config, $settings),
    'mailQueue' => $mailQueue,
    'mailDelivery' => $mailDelivery,
    'mailQueueProcessor' => $mailQueueProcessor,
    'passwordReset' => new PasswordResetService($db, $mailQueue, $config),
    'pdf' => $pdf,
    'reportAdmin' => new ReportAdminService($db, $reports, $pdf, $mailQueue, $config),
    'media' => new MediaService($db, $config),
    'attribution' => new AttributionService($db, $config),
    'admin' => new AdminService($db, $settings, $mailQueue),
    'adminInsights' => new AdminInsightsService($db, $mailQueue, $config),
    'feedback' => new FeedbackService($db, $settings, $mailQueue, $config),
    'assessmentExperience' => new AssessmentExperienceService($db, $settings),
];
