import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const app = fs.readFileSync("src/components/AssessmentAppProduction.jsx", "utf8");
const report = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const stripe = fs.readFileSync("backend/src/Payments/StripeService.php", "utf8");
const routes = fs.readFileSync("backend/public/index.php", "utf8");
const reportRoutes = fs.readFileSync("backend/src/extra-routes.php", "utf8");

test("card checkout opens separately and returns to the unlocked private report", () => {
  assert.match(report, /window\.open\("about:blank", "_blank"\)/);
  assert.match(stripe, /'reportUrl'\] = \$reportAccess\['reportUrl'\]/);
  assert.match(stripe, /checkoutStatus/);
  assert.match(routes, /\/api\/payments\/checkout-status/);
  assert.match(app, /api\.checkoutStatus\(checkoutId\)/);
  assert.match(app, /window\.location\.replace\(result\.reportUrl\)/);
});

test("UAT opens the final report directly in a new window", () => {
  assert.match(report, /const reportWindow = window\.open/);
  assert.match(report, /reportWindow\.location\.replace\(result\.reportUrl \|\| result\.successUrl\)/);
});

test("verified card payment and self-email both attempt immediate PDF delivery", () => {
  assert.match(stripe, /emailQueueIds\[\] = \$this->enqueue\('paid_report_ready'/);
  assert.match(routes, /mailQueueProcessor'\]->processIds\(\$emailQueueIds\)/);
  assert.match(reportRoutes, /mailQueueProcessor'\]->processIds\(\[\$queueId\]\)/);
  assert.match(report, /Email me the PDF/);
  assert.match(report, /Download PDF/);
});
