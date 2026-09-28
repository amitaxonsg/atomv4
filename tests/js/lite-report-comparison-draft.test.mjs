import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const reportView = readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const reportCss = readFileSync("src/report-flow.css", "utf8");

test("Lite result uses the Lite vs Full comparison while Full Report keeps its summary cards", () => {
  assert.match(reportView, /function LiteFullComparison/);
  assert.match(reportView, /Lite vs Full Report/);
  assert.match(reportView, /Key strength/);
  assert.match(reportView, /Development observation/);
  assert.match(reportView, /3 strengths/);
  assert.match(reportView, /3 development observations/);
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
