import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const main = fs.readFileSync("src/main.jsx", "utf8");
const heroCss = fs.readFileSync("src/report-full-hero-v4.css", "utf8");
const reportView = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const pdf = fs.readFileSync("backend/src/Services/PdfService.php", "utf8");

test("V4 Lite and Full reports keep the overall score meter inside the same centered score panel", () => {
  assert.match(main, /report-full-hero-v4\.css/);
  assert.match(heroCss, /Lite \/ Full report overall-result hero parity/);
  assert.match(heroCss, /:has\(\.paid-report\)/);
  assert.match(heroCss, /report-hero::before/);
  assert.match(heroCss, /> \.report-hero \.v4-meter[\s\S]*grid-column:\s*1/);
  assert.match(heroCss, /justify-self:\s*center/);
  assert.match(heroCss, /grid-row:\s*2/);
  assert.match(heroCss, /grid-row:\s*1 \/ 3/);
  assert.match(reportView, /<AlignmentGauge score=\{summary\.total\}/);
  assert.match(reportView, /<AlignmentMeter score=\{summary\.total\}/);
  assert.match(reportView, /<section className=\{`paid-report \$\{unlocked \? "unlocked" : "locked"\}`\}/);

  assert.match(pdf, /\$overallScore = max\(0, min\(250/);
  assert.match(pdf, /\$overallWidth = max\(0, min\(100/);
  assert.match(pdf, /class=\"hero-score-cell\"/);
  assert.match(pdf, /class=\"hero-meter-labels\"/);
  assert.match(pdf, /class=\"hero-meter\"/);
  assert.match(pdf, /Head-led<\/td><td>' \. \$overallScore \. '\/250<\/td><td>Heart-led/);
  assert.match(pdf, /<h2>Your alignment pattern<\/h2>/);
});
