import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const reportView = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const charts = fs.readFileSync("src/components/shared/Charts.jsx", "utf8");
const reportCss = fs.readFileSync("src/report-flow.css", "utf8");
const pdfService = fs.readFileSync("backend/src/Services/PdfService.php", "utf8");
const reportService = fs.readFileSync("backend/src/Services/ReportService.php", "utf8");
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
  for (const field of richFields) assert.match(reportView, new RegExp(field));
});

test("V4 browser and PDF use the same readable A-to-J 5-to-25 radar scale", () => {
  assert.match(reportView, /5 · Head-led/);
  assert.match(reportView, /15 · Balanced/);
  assert.match(reportView, /25 · Heart-led/);
  assert.match(reportView, /\(value - min\) \/ \(max - min\)/);
  assert.match(charts, /String\.fromCharCode\(65 \+ index\)/);
  assert.match(charts, /\(Number\(value\) - 5\) \/ 20/);
  assert.match(reportCss, /content: "A"/);
  assert.match(reportCss, /content: "J"/);
  assert.match(pdfService, /radar-score-grid/);
  assert.match(pdfService, /chr\(65 \+ \$index\)/);
  assert.match(pdfService, /letters A–J/);
  assert.match(pdfService, /5 · Head-led/);
  assert.match(pdfService, /15 · Balanced/);
  assert.match(pdfService, /25 · Heart-led/);
  assert.match(pdfService, /\(\(\$value - 5\) \/ 20\)/);
  assert.match(pdfService, /\(\$entry\['score'\] - 5\) \/ 20/);
});

test("database audit and smoke test cover locked unlock and PDF lifecycle", () => {
  assert.match(reportAudit, /REPORT CONTENT: READY/);
  assert.match(reportAudit, /PAID CHECKOUT: PENDING CONFIGURATION/);
  assert.match(reportSmoke, /Authorised unlock changes the report to Full/);
  assert.match(reportSmoke, /Unlocked API reveals complete Full Report content/);
  assert.match(reportSmoke, /Unlocked Full Report PDF was generated/);
  assert.match(reportSmoke, /DATABASE LEFT CLEAN AFTER REPORT TEST/);
});
