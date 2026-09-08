import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const main = fs.readFileSync("src/main.jsx", "utf8");
const heroCss = fs.readFileSync("src/report-full-hero-v4.css", "utf8");
const commitmentCss = fs.readFileSync("src/report-commitment-contrast-v4.css", "utf8");
const printCss = fs.readFileSync("src/report-print-v4.css", "utf8");
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

  // Browser Print Report for locked Lite is a content-parity print view, not a screenshot of controls/layout.
  assert.match(main, /share-modal-final-v4\.css";\nimport "\.\/report-print-v4\.css";/);
  assert.match(printCss, /V4 browser Print Report — Lite Report print parity/);
  assert.match(printCss, /@media print/);
  assert.match(printCss, /@page[\s\S]*size:\s*A4 portrait/);
  assert.match(printCss, /:has\(\.v4-report \.paid-report\.locked\)/);
  assert.match(printCss, /> \.latest-visual-panel[\s\S]*display:\s*none\s*!important/);
  assert.match(printCss, /> \.paid-report\.locked,[\s\S]*> \.v4-thank-you-share,[\s\S]*\.latest-page-actions/);
  assert.match(printCss, /> \.report-hero[\s\S]*background-color:\s*#252832\s*!important/);
  assert.match(printCss, /> \.report-columns[\s\S]*grid-template-columns:\s*1fr 1fr\s*!important/);
  assert.match(printCss, /-webkit-print-color-adjust:\s*exact\s*!important/);
  assert.doesNotMatch(printCss, /paid-report\.unlocked[\s\S]*display:\s*none/);

  // PDF mirrors the website Full Report hierarchy and visual language.
  assert.match(pdf, /\$overallScore = max\(0, min\(250/);
  assert.match(pdf, /\$overallWidth = max\(0, min\(100/);
  assert.match(pdf, /class=\"brand-row\"/);
  assert.match(pdf, /GROWTH ALIGNMENT · .* RESULT/);
  assert.match(pdf, /this result was calculated by the published assessment version from your saved responses/);
  assert.match(pdf, /\.hero-score-cell\{[^}]*text-align:center/);
  assert.match(pdf, /\.score\{[^}]*text-align:center/);
  assert.match(pdf, /\.score span\{[^}]*text-align:center/);
  assert.match(pdf, /class=\"hero-copy\"[\s\S]*<h2>Your alignment pattern<\/h2>[\s\S]*class=\"hero-meter-labels\"[\s\S]*class=\"hero-meter\"/);
  assert.match(pdf, /Head-led<\/td><td>' \. \$overallScore \. '\/250<\/td><td>Heart-led/);
  assert.match(pdf, /hero-meter span\{[^}]*background:#D8568C/);
  assert.match(pdf, /style=\"width:' \. \$overallWidth \. '%;background-color:#D8568C\"/);
  assert.doesNotMatch(pdf, /hero-meter span\{[^}]*linear-gradient/);

  // Dompdf pagination is packed at safe row/card boundaries instead of moving whole sections.
  assert.match(pdf, /@page\{margin:8mm 9mm 10mm\}/);
  assert.match(pdf, /\.executive-block,\.score-breakdown-block,\.deep-dive-block,\.roadmap-block,\.profile-block,\.reflection-block,\.methodology-block,\.upgrade-block\{page-break-inside:auto\}/);
  assert.match(pdf, /\.summary-grid tr\{page-break-inside:avoid\}/);
  assert.match(pdf, /\.feature-grid tr\{page-break-inside:avoid\}/);
  assert.match(pdf, /<table class=\"summary-grid\"><thead><tr><th>Highest 3<\/th><th>Lowest 3<\/th><\/tr><\/thead><tbody>/);
  assert.match(pdf, /executiveSummaryItem\(\$highest\[\$i\] \?\? null, \$trackKey\)/);
  assert.match(pdf, /executiveSummaryItem\(\$lowest\[\$i\] \?\? null, \$trackKey\)/);
  assert.match(pdf, /if \(\$cards === ''\) return '';/);
  assert.match(pdf, /deep-dive-block/);

  assert.match(pdf, /introCards\(\$strengths, \$watchouts\)/);
  assert.match(pdf, /<p class=\"block-eyebrow\">Complete report<\/p><h2>Your full development report<\/h2>/);
  assert.match(pdf, /\.executive-block\{[^}]*#CAA34B/);
  assert.match(pdf, /\.score-breakdown-block\{[^}]*#3D82D8/);
  assert.match(pdf, /\.roadmap-block\{[^}]*#CAA34B/);
  assert.match(pdf, /\.retake-block\{[^}]*#7964D8/);
  assert.match(pdf, /\.coach-block\{[^}]*#CAA34B/);
  assert.match(pdf, /\.commitment-block\{[^}]*background:#27302F[^}]*color:#fff/);
  assert.match(pdf, /<p class=\"block-eyebrow\">Make it actionable<\/p>/);
  assert.match(pdf, /upgradeReasons\(\$content\['upgradeReasons'\] \?\? null\)/);
  assert.ok(pdf.indexOf("renderRetakeComparison") < pdf.indexOf("executiveSummary($scores"));
  assert.ok(pdf.indexOf("coachBlock()") < pdf.indexOf("upgradeReasons($content"));

  assert.match(main, /report-full-hero-v4\.css";\nimport "\.\/report-commitment-contrast-v4\.css";/);
  assert.match(commitmentCss, /\.v4-report \.v4-commitment > h3,[\s\S]*color:\s*#ffffff\s*!important/);
  assert.match(commitmentCss, /\.v4-report \.v4-commitment > p,[\s\S]*color:\s*#ffffff\s*!important/);
  assert.match(commitmentCss, /\.v4-report \.v4-commitment > \.preview-note,[\s\S]*color:\s*#ffffff\s*!important/);
  assert.match(commitmentCss, /textarea \{[\s\S]*background:\s*#ffffff\s*!important;[\s\S]*color:\s*#2b241d\s*!important/);
  assert.match(pdf, /\.commitment-block h3,\.commitment-block p,\.commitment-block strong\{color:#fff\}/);
});
