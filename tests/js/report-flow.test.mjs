import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const reportView = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const assessmentApp = fs.readFileSync("src/components/AssessmentAppProduction.jsx", "utf8");
const reportCss = fs.readFileSync("src/report-flow.css", "utf8");
const editorialCss = fs.readFileSync("src/report-editorial-v4.css", "utf8");
const main = fs.readFileSync("src/main.jsx", "utf8");
const pdfService = fs.readFileSync("backend/src/Services/PdfService.php", "utf8");
const reportService = fs.readFileSync("backend/src/Services/ReportService.php", "utf8");
const stripeService = fs.readFileSync("backend/src/Payments/StripeService.php", "utf8");
const routeBundle = fs.readFileSync("backend/src/route-bundle.php", "utf8");
const mailProcessor = fs.readFileSync("backend/src/Mail/MailQueueProcessor.php", "utf8");
const schema = fs.readFileSync("database/migrations/001_initial_schema.sql", "utf8");
const reportAudit = fs.readFileSync("backend/bin/report-flow-audit.php", "utf8");
const reportSmoke = fs.readFileSync("backend/bin/production-report-flow-smoke-test.php", "utf8");

const richFields = [
  "developmentAreas",
  "relationships",
  "workingStyleTips",
  "handlingDifficulty",
  "leadershipImpact",
  "cultureFitPrompt",
  "growth",
  "subscaleReads",
  "upgradeReasons",
];

test("locked report API exposes Lite content and preview but not Full content", () => {
  assert.match(reportService, /'upgradePreview' => \$upgradePreview/);
  assert.match(reportService, /IF\(gr\.is_unlocked = 1, gr\.paid_report_json, NULL\)/);
  assert.match(reportService, /checkoutAvailable/);
  assert.match(reportSmoke, /Locked API does not expose paid report content/);
  assert.match(reportSmoke, /Locked report contains the approved CMS upgrade preview/);
});

test("participant report shows safe Stripe readiness and full CMS schema", () => {
  assert.match(reportView, /This is the short version/);
  assert.match(reportView, /Full Report checkout coming soon/);
  assert.match(reportView, /checkoutAvailable/);
  assert.match(reportView, /UpgradeReasons/);
  assert.match(reportView, /Your alignment pattern/);
  assert.match(reportView, /Top three strengths/);
  assert.match(reportView, /Development observations/);
  assert.match(reportView, /Your Full Report goes deeper into the patterns behind this result/);
  assert.match(reportCss, /V4 Lite Report visual refresh/);
  assert.match(reportCss, /v4-report:has\(\.paid-report\.locked\)/);
  assert.match(reportCss, /report-hero[\s\S]*radial-gradient/);
  assert.match(reportCss, /report-card:first-child li:first-child[\s\S]*grid-column:\s*1 \/ -1/);
  assert.match(main, /report-editorial-v4\.css/);
  assert.match(editorialCss, /V4 Full Development Report editorial refresh/);
  assert.match(editorialCss, /v4-report:has\(\.paid-report\.unlocked\)/);
  assert.match(editorialCss, /--editorial-red/);
  assert.match(editorialCss, /paid-report\.unlocked[\s\S]*paid-heading/);
  assert.match(editorialCss, /v4-commitment[\s\S]*linear-gradient/);
  for (const field of richFields) assert.match(reportView, new RegExp(field));
});

test("V4 browser and PDF use the same 10-area progress-bar breakdown", () => {
  assert.match(reportView, /Your 10-area score breakdown/);
  assert.match(reportView, /v4-all-areas-grid/);
  assert.doesNotMatch(reportView, /RadarChart/);
  assert.match(reportView, /5 · Head-led/);
  assert.match(reportView, /15 · Balanced/);
  assert.match(reportView, /25 · Heart-led/);
  assert.match(reportView, /\(value - min\) \/ \(max - min\)/);
  assert.match(reportCss, /v4-score-breakdown[\s\S]*v4-all-areas-grid[\s\S]*v4-scale__track/);
  assert.match(reportCss, /v4-score-breakdown[\s\S]*display:\s*block\s*!important/);
  assert.match(reportCss, /v4-executive-summary[\s\S]*v4-scale__track/);
  assert.match(editorialCss, /v4-all-areas-grid article:nth-child\(5\)/);
  assert.match(editorialCss, /v4-score-breakdown \.v4-scale__track > span/);
  assert.match(pdfService, /scoreBreakdownSection/);
  assert.match(pdfService, /score-grid/);
  assert.match(pdfService, /Your 10-area score breakdown/);
  assert.doesNotMatch(pdfService, /radarSvg/);
  assert.doesNotMatch(pdfService, /letters A–J/);
  assert.match(pdfService, /5 · Head-led/);
  assert.match(pdfService, /15 · Balanced/);
  assert.match(pdfService, /25 · Heart-led/);
  assert.match(pdfService, /\(\(\$value - 5\) \/ 20\)/);
  assert.match(pdfService, /\$pdfAccent = \$trackKey === 'personal' \? \$heart : \$head/);
  assert.match(pdfService, /section-banner/);
  assert.match(pdfService, /score-color-5/);
  assert.match(pdfService, /commitment-block/);
  assert.match(pdfService, /coach-block/);
});

test("database audit and smoke test cover locked unlock and PDF lifecycle", () => {
  assert.match(reportAudit, /REPORT CONTENT: READY/);
  assert.match(reportAudit, /PAID CHECKOUT: PENDING CONFIGURATION/);
  assert.match(reportSmoke, /Authorised unlock changes the report to Full/);
  assert.match(reportSmoke, /Unlocked API reveals complete Full Report content/);
  assert.match(reportSmoke, /Unlocked Full Report PDF was generated/);
  assert.match(reportSmoke, /DATABASE LEFT CLEAN AFTER REPORT TEST/);

  assert.match(stripeService, /Webhook::constructEvent/);
  assert.match(stripeService, /checkout\.session\.completed/);
  assert.match(stripeService, /stripe_payment_intent_id/);
  assert.match(stripeService, /stripe_customer_id/);
  assert.match(stripeService, /amount = \?/);
  assert.match(stripeService, /paid_at = NOW\(\)/);
  assert.match(stripeService, /unlockBySession\(\$sessionId, 'stripe_webhook'\)/);
  assert.match(stripeService, /\$metadata\['reportUrl'\]/);
  assert.match(stripeService, /paid_report_ready/);
  assert.match(routeBundle, /GET', '\/api\/payments\/status/);
  assert.match(routeBundle, /stripe_checkout_session_id = \?/);
  assert.match(routeBundle, /reportReady/);
  assert.match(assessmentApp, /\/api\/payments\/status\?checkout=/);
  assert.match(assessmentApp, /Processing Full Report…/);
  assert.match(assessmentApp, /Please don’t close this page/);
  assert.match(assessmentApp, /role="progressbar"/);
  assert.match(assessmentApp, /Open Full Report/);
  assert.match(assessmentApp, /window\.location\.replace\(result\.reportUrl\)/);
  assert.match(assessmentApp, /resolvedReportUrl/);
  assert.match(mailProcessor, /addPaidReportAttachment/);
  assert.match(mailProcessor, /Growth-Alignment-Full-Development-Report\.pdf/);
  assert.match(schema, /CREATE TABLE payments/);
  assert.match(schema, /stripe_checkout_session_id VARCHAR\(255\)/);
  assert.match(schema, /stripe_payment_intent_id VARCHAR\(255\)/);
  assert.match(schema, /stripe_customer_id VARCHAR\(255\)/);
  assert.match(schema, /CREATE TABLE stripe_webhook_events/);
  assert.match(schema, /CREATE TABLE email_logs/);
});
