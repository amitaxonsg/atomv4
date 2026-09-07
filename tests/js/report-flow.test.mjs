import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const reportView = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const reportCss = fs.readFileSync("src/report-flow.css", "utf8");
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

test("Lite and Full reports expose privacy-safe highlight sharing only", () => {
  assert.match(reportView, /Share your Lite Report highlights/);
  assert.match(reportView, /Share your Full Report highlights/);
  assert.match(reportView, /Share on LinkedIn/);
  assert.match(reportView, /Share on Facebook/);
  assert.match(reportView, /Copy highlights/);
  assert.match(reportView, /Only your profile name, top three strengths and the public assessment link are included/);
  assert.match(reportView, /Your Lite Report, Full Report, PDF, scores, answers, notes, email address and private report link are never shared/);

  const start = reportView.indexOf("function safeShareHighlights");
  const end = reportView.indexOf("function ScaleBar");
  const shareSource = reportView.slice(start, end);
  assert.ok(start >= 0 && end > start);
  assert.match(shareSource, /summary\?\.profile/);
  assert.match(shareSource, /summary\?\.strengths/);
  assert.doesNotMatch(shareSource, /summary\?\.total|summary\?\.watchouts|participantName|writtenReflections|private report token|paidContent/);
});

test("Full Report ends with the approved thank-you and sharing call to action", () => {
  assert.match(reportView, /Thank you for taking the assessment\./);
  assert.match(reportView, /If this is helpful, please share it with someone who will benefit from taking it!/);
  assert.match(reportView, /Save your private report/);
  assert.match(reportCss, /\.v4-thank-you-banner/);
  assert.match(reportCss, /\.v4-share-highlights/);
});

test("database audit and smoke test cover locked unlock and PDF lifecycle", () => {
  assert.match(reportAudit, /REPORT CONTENT: READY/);
  assert.match(reportAudit, /PAID CHECKOUT: PENDING CONFIGURATION/);
  assert.match(reportSmoke, /Authorised unlock changes the report to Full/);
  assert.match(reportSmoke, /Unlocked API reveals complete Full Report content/);
  assert.match(reportSmoke, /Unlocked Full Report PDF was generated/);
  assert.match(reportSmoke, /DATABASE LEFT CLEAN AFTER REPORT TEST/);
});
