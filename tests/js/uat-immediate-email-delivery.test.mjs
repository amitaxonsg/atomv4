import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const cash = fs.readFileSync("backend/src/Payments/CashOnDeliveryService.php", "utf8");
const processor = fs.readFileSync("backend/src/Mail/MailQueueProcessor.php", "utf8");
const worker = fs.readFileSync("backend/bin/process-email-queue.php", "utf8");
const routes = fs.readFileSync("backend/public/index.php", "utf8");
const paymentPage = fs.readFileSync("src/components/AssessmentAppProduction.jsx", "utf8");
const burnTest = fs.readFileSync("backend/bin/production-report-flow-smoke-test.php", "utf8");

test("UAT checkout returns both queue ids and attempts delivery immediately", () => {
  assert.match(cash, /'emailQueueIds' => \$emailQueueIds/);
  assert.match(cash, /private function enqueue\([^)]*\): int/);
  assert.match(routes, /mailQueueProcessor'\]->processIds/);
  assert.match(routes, /emailDelivery.*sent.*retrying/s);
});

test("shared queue processor attaches the unlocked professional PDF", () => {
  assert.match(processor, /paid_report_ready/);
  assert.match(processor, /Growth-Alignment-Full-Development-Report\.pdf/);
  assert.match(processor, /pdf->generate\(\$reportId\)/);
  assert.match(processor, /Paid report email is missing its Full Development Report id/);
});

test("immediate and cron delivery share retry-safe queue processing", () => {
  assert.match(processor, /scheduled_at = DATE_ADD\(NOW\(\), INTERVAL 5 MINUTE\)/);
  assert.match(processor, /status.*failed.*retry/s);
  assert.match(worker, /mailQueueProcessor'\]->processDue/);
});

test("UAT thank-you page reports provider acceptance honestly", () => {
  assert.match(paymentPage, /emailDelivery === "sent"/);
  assert.match(paymentPage, /accepted by the email provider/);
  assert.match(paymentPage, /email delivery is retrying in the background/);
});

test("production burn test requires real provider acceptance for the PDF email", () => {
  assert.match(burnTest, /send-email/);
  assert.match(burnTest, /mailQueueProcessor'\]->processIds/);
  assert.match(burnTest, /paid_report_ready/);
  assert.match(burnTest, /provider message id/);
});
