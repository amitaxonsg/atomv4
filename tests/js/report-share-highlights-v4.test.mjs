import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const reportView = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const heroCss = fs.readFileSync("src/report-full-hero-v4.css", "utf8");

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
  assert.match(reportView, /aria-label="Share to Facebook"/);
  assert.match(reportView, /aria-label="Share to LinkedIn"/);
  assert.match(reportView, /aria-label="Share to Instagram"/);
  assert.match(reportView, /aria-label="More sharing options"/);
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

  assert.match(reportView, /className="v4-share-modal__backdrop"/);
  assert.match(reportView, /role="dialog" aria-modal="true"/);
  assert.match(reportView, /aria-labelledby="v4-share-modal-title"/);
  assert.match(reportView, /aria-label="Close share dialog"/);
  assert.match(reportView, /Public assessment link/);
  assert.match(reportView, /Copy highlights/);
  assert.match(reportView, /event\.key === "Escape"/);
  assert.match(reportView, /document\.body\.style\.overflow = "hidden"/);
  assert.match(reportView, /previousFocusRef\.current\?\.focus\?\.\(\)/);
  assert.match(reportView, /event\.target === event\.currentTarget/);

  assert.match(heroCss, /\.v4-share-modal__backdrop\s*\{/);
  assert.match(heroCss, /position:\s*fixed/);
  assert.match(heroCss, /\.v4-share-modal\s*\{/);
  assert.match(heroCss, /width:\s*min\(370px, calc\(100vw - 32px\)\)/);
  assert.match(heroCss, /border-radius:\s*12px/);
  assert.match(heroCss, /\.v4-share-modal__header \.eyebrow\s*\{[\s\S]*display:\s*none/);
  assert.match(heroCss, /\.v4-share-modal__label::before\s*\{[\s\S]*Share the link via/);
  assert.match(heroCss, /\.v4-share-modal__platforms\s*\{[\s\S]*order:\s*3/);
  assert.match(heroCss, /\.v4-share-modal__link\s*\{[\s\S]*order:\s*4/);
  assert.match(heroCss, /\.v4-share-modal__link > span::before\s*\{[\s\S]*Copy link/);
  assert.match(heroCss, /\.v4-share-modal__link > div\s*\{[\s\S]*box-shadow:/);
  assert.match(heroCss, /\.v4-share-modal__platforms > button:nth-child\(-n\+3\)[\s\S]*border-radius:\s*50%/);
  assert.match(heroCss, /%234267B2/);
  assert.match(heroCss, /%230A66C2/);
  assert.match(heroCss, /radialGradient/);
  assert.match(heroCss, /\.v4-share-modal__close\s*\{/);
  assert.match(heroCss, /@media print[\s\S]*\.v4-share-modal__backdrop/);

  assert.doesNotMatch(reportView, /function fullReportText|Copy as text|Report copied as text/);
  assert.match(reportView, /<h3>Save your full report<\/h3>/);
  assert.match(reportView, /Email PDF to self/);
});
