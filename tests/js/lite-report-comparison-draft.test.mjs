import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const reportView = readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const reportCss = readFileSync("src/report-flow.css", "utf8");

test("Lite result uses the Lite vs Full comparison while Full Report keeps its summary cards", () => {
  assert.match(reportView, /function LiteFullComparison/);
  assert.doesNotMatch(reportView, />Lite vs Full Report</);
  assert.match(reportView, /Key strength/);
  assert.match(reportView, /Development observation/);
  assert.match(reportView, /Your 3 strengths/);
  assert.match(reportView, /Your 3 development observations/);
  assert.match(reportView, /Complete report preview/);
  assert.match(reportView, /Print Lite Report/);
  assert.match(reportView, /unlocked && <div className="report-columns"/);
  assert.match(reportView, /<h2>Top three strengths<\/h2>/);
  assert.match(reportView, /<h2>Development observations<\/h2>/);
});

test("Lite comparison keeps the existing payment and UAT routes", () => {
  assert.match(reportView, /onCheckout=\{openCheckout\}/);
  assert.match(reportView, /onCashOnDelivery=\{openCashOnDelivery\}/);
  assert.match(reportView, /Full Report — \$\{price\}/);
  assert.match(reportView, /UAT Test — No Payment/);
});

test("Lite share payload is reduced to one strength and one development observation", () => {
  assert.match(reportView, /summary\.strengths\.slice\(0, 1\)/);
  assert.match(reportView, /summary\.watchouts\.slice\(0, 1\)/);
  assert.match(reportView, /one key strength and one development observation/);
});

test("Comparison has responsive and print treatments", () => {
  assert.match(reportCss, /GAA LITE REPORT DRAFT — 28 SEP 2026/);
  assert.match(reportCss, /\.v4-lite-full-comparison__grid/);
  assert.match(reportCss, /@media \(max-width: 760px\)/);
  assert.match(reportCss, /@media print/);
});


test("Personal coffee banner is scoped to Personal and sits before the Full Report CTA", () => {
  assert.match(reportView, /trackKey === "personal" && <div className="v4-personal-coffee-banner">/);
  assert.match(reportView, /For less than a cup of coffee, find out more about yourself! ✨/);
  const banner = reportView.indexOf('className="v4-personal-coffee-banner"');
  const fullCta = reportView.indexOf('Full Report — ${price}');
  assert.ok(banner >= 0 && fullCta > banner, "Personal coffee banner must appear before Full Report CTA");
});

test("Print Lite Report uses a dedicated compact Lite summary", () => {
  assert.match(reportView, /className="v4-lite-print-summary"/);
  assert.match(reportView, /<h3>Key strength<\/h3>/);
  assert.match(reportView, /<h3>Development observation<\/h3>/);
  assert.match(reportCss, /\.v4-lite-print-summary\s*\{\s*display:\s*none/s);
});
