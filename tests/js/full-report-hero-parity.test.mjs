import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const main = fs.readFileSync("src/main.jsx", "utf8");
const heroCss = fs.readFileSync("src/report-full-hero-v4.css", "utf8");
const commitmentCss = fs.readFileSync("src/report-commitment-contrast-v4.css", "utf8");
const reportView = fs.readFileSync("src/components/assessment/ReportView.jsx", "utf8");
const pdf = fs.readFileSync("backend/src/Services/PdfService.php", "utf8");

test("V4 Lite, Full and PDF follow the approved reference result-card composition", () => {
  assert.match(main, /report-full-hero-v4\.css/);
  assert.match(heroCss, /Lite \/ Full overall-result reference parity/);
  assert.match(heroCss, /:has\(\.paid-report\.locked\)/);
  assert.match(heroCss, /:has\(\.paid-report\.unlocked\)/);
  assert.match(heroCss, /background-color:\s*#252832\s*!important/);
  assert.match(heroCss, /background-image:[\s\S]*linear-gradient\(118deg, #1f2331/);
  assert.match(heroCss, /background-color:\s*#2a2b36\s*!important/);
  assert.match(heroCss, /> \.report-hero > \.gauge[\s\S]*align-items:\s*center/);
  assert.match(heroCss, /> \.report-hero > \.gauge[\s\S]*justify-content:\s*center/);
  assert.match(heroCss, /> \.report-hero > \.gauge strong[\s\S]*color:\s*#ffffff\s*!important/);
  assert.match(heroCss, /> \.report-hero > \.gauge strong[\s\S]*text-align:\s*center/);
  assert.match(heroCss, /> \.report-hero > \.gauge > span[\s\S]*color:\s*#f7efe2\s*!important/);
  assert.match(heroCss, /> \.report-hero > \.gauge > span[\s\S]*text-align:\s*center/);
  assert.match(heroCss, /> \.report-hero > div:not\(\.gauge\)[\s\S]*flex-direction:\s*column/);
  assert.match(heroCss, /> \.report-hero h2[\s\S]*color:\s*#f2d78f\s*!important/);
  assert.match(heroCss, /> \.report-hero p[\s\S]*color:\s*#ffffff\s*!important/);
  assert.match(heroCss, /> \.report-hero \.v4-meter[\s\S]*width:\s*100%/);
  assert.match(heroCss, /\.v4-meter__labels[\s\S]*color:\s*#eee7dc\s*!important/);
  assert.match(heroCss, /\.v4-meter__track[\s\S]*background-color:\s*#62636b\s*!important/);
  assert.match(reportView, /<section className="report-hero"><AlignmentGauge score=\{summary\.total\} \/><div><h2>Your alignment pattern<\/h2><p>\{summary\.summary\}<\/p><AlignmentMeter score=\{summary\.total\} \/><\/div><\/section>/);
  assert.match(reportView, /<section className=\{`paid-report \$\{unlocked \? "unlocked" : "locked"\}`\}/);

  assert.match(pdf, /\$overallScore = max\(0, min\(250/);
  assert.match(pdf, /\$overallWidth = max\(0, min\(100/);
  assert.match(pdf, /\.hero-score-cell\{[^}]*text-align:center/);
  assert.match(pdf, /\.score\{[^}]*text-align:center/);
  assert.match(pdf, /\.score span\{[^}]*text-align:center/);
  assert.match(pdf, /class=\"hero-copy\"[\s\S]*<h2>Your alignment pattern<\/h2>[\s\S]*class=\"hero-meter-labels\"[\s\S]*class=\"hero-meter\"/);
  assert.match(pdf, /Head-led<\/td><td>' \. \$overallScore \. '\/250<\/td><td>Heart-led/);

  assert.match(main, /report-full-hero-v4\.css";\nimport "\.\/report-commitment-contrast-v4\.css";/);
  assert.match(commitmentCss, /\.v4-report \.v4-commitment > h3,[\s\S]*color:\s*#ffffff\s*!important/);
  assert.match(commitmentCss, /\.v4-report \.v4-commitment > p,[\s\S]*color:\s*#ffffff\s*!important/);
  assert.match(commitmentCss, /\.v4-report \.v4-commitment > \.preview-note,[\s\S]*color:\s*#ffffff\s*!important/);
  assert.match(commitmentCss, /textarea \{[\s\S]*background:\s*#ffffff\s*!important;[\s\S]*color:\s*#2b241d\s*!important/);
  assert.match(pdf, /\.commitment-block\{[^}]*color:#fff/);
  assert.match(pdf, /\.commitment-block h3,\.commitment-block p\{color:#fff\}/);
});
