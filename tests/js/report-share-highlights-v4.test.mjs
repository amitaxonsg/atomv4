import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const reportView = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");

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
  assert.match(builder, /publicShareUrl\(\)/);

  assert.doesNotMatch(builder, /paidContent|report\?\.paid|content\?|token|\/api\/reports\//);
  assert.doesNotMatch(builder, /writtenReflections|methodology|roadmap|commitment|private link|pdf/i);

  assert.match(reportView, /https:\/\/www\.facebook\.com\/sharer\/sharer\.php/);
  assert.match(reportView, /https:\/\/www\.linkedin\.com\/sharing\/share-offsite\//);
  assert.match(reportView, /https:\/\/www\.instagram\.com\//);
  assert.match(reportView, /navigator\.share\(payload\)/);
  assert.match(reportView, /navigator\.clipboard\?\.writeText/);
  assert.match(reportView, />Facebook<\/button>/);
  assert.match(reportView, />LinkedIn<\/button>/);
  assert.match(reportView, />Instagram<\/button>/);
  assert.match(reportView, />More apps<\/button>/);
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

  assert.doesNotMatch(reportView, /function fullReportText|Copy as text|Report copied as text/);
  assert.match(reportView, /<h3>Save your full report<\/h3>/);
  assert.match(reportView, /Email PDF to self/);
});
