import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const stripeService = fs.readFileSync("backend/src/Payments/StripeService.php", "utf8");
const reconciler = fs.readFileSync("backend/src/Payments/StripeCheckoutReconciler.php", "utf8");
const reconcileCron = fs.readFileSync("backend/bin/reconcile-stripe-checkouts.php", "utf8");
const routes = fs.readFileSync("backend/src/route-bundle.php", "utf8");
const app = fs.readFileSync("src/components/AssessmentAppProduction.jsx", "utf8");
const mailProcessor = fs.readFileSync("backend/src/Mail/MailQueueProcessor.php", "utf8");
const cron = fs.readFileSync("backend/bin/cron.php", "utf8");
const pdfGenerator = fs.readFileSync("backend/bin/generate-report-pdfs.php", "utf8");
const adminAlerts = fs.readFileSync("backend/bin/process-admin-alerts.php", "utf8");

test("V4 card payment burn-in keeps Stripe verification and a safe reconciliation fallback", () => {
  assert.match(stripeService, /Webhook::constructEvent/);
  assert.match(stripeService, /checkout\.session\.completed/);
  assert.match(stripeService, /payment_status/);
  assert.match(stripeService, /stripe_payment_intent_id/);
  assert.match(stripeService, /stripe_customer_id/);
  assert.match(stripeService, /paid_at = NOW\(\)/);

  assert.match(reconciler, /StripeClient/);
  assert.match(reconciler, /checkout->sessions->retrieve/);
  assert.match(reconciler, /payment_status/);
  assert.match(reconciler, /survey_session_id/);
  assert.match(reconciler, /payment_purpose/);
  assert.match(reconciler, /!== 'full_report'/);
  assert.match(reconciler, /UPDATE payments SET status = \?/);
  assert.match(reconciler, /stripe_payment_intent_id = \?/);
  assert.match(reconciler, /stripe_customer_id = \?/);
  assert.match(reconciler, /unlockBySession/);
  assert.match(reconciler, /secure_report_tokens/);
  assert.match(reconciler, /reportReadyAt/);
});

test("V4 paid fulfilment burn-in keeps PDF email and administrator notification idempotent", () => {
  assert.match(reconciler, /enqueueOnce\('payment_successful'/);
  assert.match(reconciler, /enqueueOnce\('paid_report_ready'/);
  assert.match(reconciler, /JSON_UNQUOTE\(JSON_EXTRACT\(variables_json, '\$\.reportId'\)\)/);
  assert.match(reconciler, /payment_paid/);
  assert.match(reconciler, /notification_events/);
  assert.match(mailProcessor, /template_key.*paid_report_ready/s);
  assert.match(mailProcessor, /addPaidReportAttachment/);
  assert.match(mailProcessor, /Growth-Alignment-Full-Development-Report\.pdf/);
  assert.match(pdfGenerator, /is_unlocked = 1 AND pdf_path IS NULL/);
  assert.match(adminAlerts, /admin_alert/);
  assert.match(cron, /generate-report-pdfs\.php/);
  assert.match(cron, /process-admin-alerts\.php/);
  assert.match(cron, /process-email-queue\.php/);
});

test("V4 missed webhooks are recovered by the scheduled Stripe reconciliation gate", () => {
  assert.match(reconcileCron, /StripeCheckoutReconciler/);
  assert.match(reconcileCron, /checkout_started/);
  assert.match(reconcileCron, /payment_purpose/);
  assert.match(reconcileCron, /full_report/);
  assert.match(reconcileCron, /payment_paid/);
  assert.match(reconcileCron, /CAST\(n\.entity_id AS UNSIGNED\) = p\.id/);
  assert.doesNotMatch(reconcileCron, /n\.entity_id = CAST\(p\.id AS CHAR\)/);
  assert.match(reconcileCron, /reconcile\(\$checkoutId\)/);
  assert.match(cron, /reconcile-stripe-checkouts\.php/);
  assert.match(cron, /reconcile-stripe-checkouts\.php[\s\S]*generate-report-pdfs\.php[\s\S]*process-admin-alerts\.php[\s\S]*process-email-queue\.php/);
});

test("V4 payment success screen shows progress and opens the Full Report instead of restarting", () => {
  assert.match(routes, /StripeCheckoutReconciler/);
  assert.match(routes, /'progress' => \$progress/);
  assert.match(routes, /'stage' => \$stage/);
  assert.match(routes, /'pdfEmailStatus' => \$emailStatus/);
  assert.match(routes, /'adminNotified' => \$adminNotified/);
  assert.match(app, /Please don’t close this page/);
  assert.match(app, /role="progressbar"/);
  assert.match(app, /Full Report processing progress/);
  assert.match(app, /window\.location\.replace\(result\.reportUrl\)/);
  assert.match(app, /Opening Full Report…/);
  assert.match(app, /Check payment status again/);
  assert.match(app, /resolvedReportUrl[\s\S]*Open Full Report/);
});
