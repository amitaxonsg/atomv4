import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const reportView = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const heroCss = fs.readFileSync("src/report-full-hero-v4.css", "utf8");
const finalShareCss = fs.readFileSync("src/share-modal-final-v4.css", "utf8");
const mainEntry = fs.readFileSync("src/main.jsx", "utf8");
const appProduction = fs.readFileSync("src/components/AssessmentAppProduction.jsx", "utf8");
const apiClient = fs.readFileSync("src/api/client.js", "utf8");
const reportService = fs.readFileSync("backend/src/Services/ReportService.php", "utf8");
const backendRoutes = fs.readFileSync("backend/public/index.php", "utf8");

test("V4 shares only Lite-safe report highlights and keeps the Full Report private", () => {
  const start = reportView.indexOf("function highlightShareText(report, summary)");
  const end = reportView.indexOf("function highlightSharePayload", start);
  assert.ok(start >= 0 && end > start, "highlight-only share builder must exist");
  const builder = reportView.slice(start, end);

  assert.match(builder, /Profile: \$\{summary\.profile\}/);
  assert.match(builder, /Overall score: \$\{summary\.total\}\/250/);
  assert.match(builder, /textValue\(summary\.summary\)/);
  assert.match(builder, /summary\.strengths/);
  assert.match(builder, /summary\.watchouts/);
  assert.match(builder, /assessmentUrl\(\)/);

  assert.doesNotMatch(builder, /paidContent|report\?\.paid|content\?|token|\/api\/reports\//);
  assert.doesNotMatch(builder, /writtenReflections|methodology|roadmap|commitment|private link|pdf/i);

  assert.match(reportView, /https:\/\/www\.facebook\.com\/sharer\/sharer\.php/);
  assert.doesNotMatch(reportView, /facebook\.com\/sharer\/sharer\.php[^`]*quote=/);
  assert.match(reportView, /prepareFacebook/);
  assert.match(reportView, /facebookReady/);
  assert.match(reportView, /Highlights copied for Facebook/);
  assert.match(reportView, /Open Facebook/);
  assert.match(reportView, /What’s on your mind\?/);
  assert.match(reportView, /Ctrl\+V/);
  assert.match(reportView, /https:\/\/www\.linkedin\.com\/sharing\/share-offsite\//);
  assert.match(reportView, /prepareLinkedIn/);
  assert.match(reportView, /linkedinReady/);
  assert.match(reportView, /Highlights copied for LinkedIn/);
  assert.match(reportView, /Open LinkedIn/);
  assert.match(reportView, /LinkedIn cannot pre-fill the post text/);
  assert.match(reportView, /https:\/\/x\.com\/intent\/post/);
  assert.match(reportView, /function xCharacterWeight\(character\)/);
  assert.match(reportView, /function xWeightedLength\(text\)/);
  assert.match(reportView, /function xTakeWeighted\(text, maximumWeight\)/);
  assert.match(reportView, /function xShareText\(report, summary\)/);
  assert.match(reportView, /const xMaxLength = 280/);
  assert.match(reportView, /const xUrlLength = 23/);
  assert.match(reportView, /availableForSummary/);
  assert.match(reportView, /Take the Growth Alignment assessment:/);
  assert.match(reportView, /const xText = xShareText\(report, summary\)/);
  assert.match(reportView, /encodeURIComponent\(xText\)/);
  assert.match(reportView, /https:\/\/wa\.me\//);
  assert.match(reportView, /navigator\.clipboard\?\.writeText/);
  assert.match(reportView, /aria-label="Prepare Facebook share"/);
  assert.match(reportView, /aria-label="Share to X"/);
  assert.match(reportView, /aria-label="Share to WhatsApp"/);
  assert.match(reportView, /aria-label="Prepare LinkedIn share"/);
  assert.doesNotMatch(reportView, /Share to Instagram|More sharing options|>More apps<\/button>/);
  assert.match(reportView, /Share highlights/);
  assert.match(reportView, /Thank you for taking the assessment\./);
  assert.match(reportView, /If this is helpful, please share it with someone who will benefit from taking it!/);
  assert.match(reportView, /Your private Full Report, PDF, private link, reflections and detailed development content are not included\./);
  assert.match(reportView, /<ThankYouShare report=\{report\} summary=\{summary\} \/>/);

  const actionsStart = reportView.indexOf("const actions =");
  const actionsEnd = reportView.indexOf("const reportClass", actionsStart);
  const actions = reportView.slice(actionsStart, actionsEnd);
  assert.ok(actionsStart >= 0 && actionsEnd > actionsStart, "report action bar must exist");
  assert.doesNotMatch(actions, /ShareHighlightsButton/, "bottom action bar must not contain Share highlights");
  assert.match(actions, /Open PDF/);
  assert.match(actions, /Print report/);

  assert.match(reportView, /className="v4-share-modal__backdrop"/);
  assert.match(reportView, /role="dialog" aria-modal="true"/);
  assert.match(reportView, /aria-labelledby="v4-share-modal-title"/);
  assert.match(reportView, /aria-label="Close share dialog"/);
  assert.match(reportView, /Public Lite Report link/);
  assert.match(reportView, /Copy link/);
  assert.match(reportView, /function publicShareUrl\(report\)/);
  assert.match(reportView, /report\?\.publicLiteUrl/);
  assert.match(reportView, /value=\{publicShareUrl\(report\)\}/);
  assert.match(reportView, /Lite Report link copied/);
  assert.match(reportView, /sharedLite/);

  assert.match(appProduction, /function SharedLiteReport\(\{ token \}\)/);
  assert.match(appProduction, /path\.startsWith\("\/share\/lite\/"\)/);
  assert.match(apiClient, /getPublicLiteReport: token => request\(`\/public\/reports\/lite\//);

  assert.match(backendRoutes, /\/api\/public\/reports\/lite\/\{token\}/);
  assert.match(reportService, /public function byPublicLiteToken\(string \$token\)/);
  assert.match(reportService, /private function publicLiteSignature\(int \$reportId\)/);
  assert.match(reportService, /v4-public-lite-report:/);

  const publicLiteStart = reportService.indexOf("public function byPublicLiteToken");
  const publicLiteEnd = reportService.indexOf("public function pdfByToken", publicLiteStart);
  const publicLiteScope = reportService.slice(publicLiteStart, publicLiteEnd);
  assert.ok(publicLiteStart >= 0 && publicLiteEnd > publicLiteStart, "public Lite report service must exist");
  assert.doesNotMatch(publicLiteScope, /paid_report_json FROM|participantEmail|participantName|secure_token_hash/);
  assert.match(reportView, /event\.key === "Escape"/);
  assert.match(reportView, /document\.body\.style\.overflow = "hidden"/);
  assert.match(reportView, /previousFocusRef\.current\?\.focus\?\.\(\)/);
  assert.match(reportView, /event\.target === event\.currentTarget/);

  assert.match(heroCss, /\.v4-share-modal__backdrop\s*\{/);
  assert.match(heroCss, /position:\s*fixed/);
  assert.match(heroCss, /\.v4-share-modal\s*\{/);
  assert.match(heroCss, /\.v4-share-modal__close\s*\{/);
  assert.match(heroCss, /@media print[\s\S]*\.v4-share-modal__backdrop/);

  assert.match(mainEntry, /import "\.\/share-modal-final-v4\.css";/);
  assert.match(finalShareCss, /\.v4-share-modal__label::before\s*\{[\s\S]*content:\s*none\s*!important/);
  assert.match(finalShareCss, /grid-template-columns:\s*repeat\(4, minmax\(0, 1fr\)\)/);
  assert.match(finalShareCss, /> \.v4-social-facebook::before/);
  assert.match(finalShareCss, /> \.v4-social-x::before/);
  assert.match(finalShareCss, /> \.v4-social-whatsapp::before/);
  assert.match(finalShareCss, /> \.v4-social-linkedin::before/);
  assert.match(finalShareCss, /%2325D366/);
  assert.match(finalShareCss, /background-size:\s*56px 56px\s*!important/);
  assert.doesNotMatch(finalShareCss, /Instagram|More apps|content:\s*"More"/);

  assert.doesNotMatch(reportView, /function fullReportText|Copy as text|Report copied as text/);
  assert.match(reportView, /<h3>Save your full report<\/h3>/);
  assert.match(reportView, /Email PDF to self/);
});
