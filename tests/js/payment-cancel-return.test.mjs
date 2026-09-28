import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const stripe = readFileSync("backend/src/Payments/StripeService.php", "utf8");
const routes = readFileSync("backend/public/index.php", "utf8");
const report = readFileSync("src/components/assessment/ReportView.jsx", "utf8");

test("card checkout sends the current private report token to the backend", () => {
  assert.match(report, /createCheckout\(\{ sessionId: report\.sessionId, track: report\.trackKey, reportToken: token \}\)/);
  assert.match(routes, /\$request->body\['reportToken'\]/);
  assert.match(stripe, /checkout\(int \$sessionId, string \$trackKey, \?string \$affiliateCode, \?string \$reportToken = null\)/);
});

test("Stripe cancellation returns to the same verified result page", () => {
  assert.match(stripe, /verifiedReportReturnUrl/);
  assert.match(stripe, /secure_token_hash/);
  assert.match(stripe, /hash_equals\(\$storedHash, hash\('sha256', \$token\)\)/);
  assert.match(stripe, /token_expires_at/);
  assert.match(stripe, /\/report\/.*\?payment=cancelled/);
  assert.match(stripe, /'cancel_url' => \$cancelUrl/);
  assert.match(stripe, /A valid private report link is required before checkout/);
  assert.match(report, /get\("payment"\) === "cancelled"/);
  assert.match(report, /Payment not completed\.<\/strong> Nothing was charged/);
});

test("retest cancellation also returns to the originating Full Report", () => {
  assert.match(report, /affiliateCode: "__RETAKE__", reportToken: token/);
  assert.match(report, /<RetakePlan report=\{report\} token=\{token\} \/>/);
  assert.match(stripe, /retakeCheckout\(int \$sessionId, string \$trackKey, \?string \$reportToken = null\)/);
  assert.match(stripe, /A valid private report link is required before retest checkout/);
});
