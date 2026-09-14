import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const pdfService = fs.readFileSync("backend/src/Services/PdfService.php", "utf8");

test("Full Report PDF has a dedicated dynamic cover page", () => {
  assert.match(pdfService, /s\.created_at assessment_started_at/);
  assert.match(pdfService, /s\.completed_at/);
  assert.match(pdfService, /class="cover-page"/);
  assert.match(pdfService, /page-break-after:always/);
  assert.match(pdfService, /Growth Alignment Report/);
  assert.match(pdfService, /Full Name/);
  assert.match(pdfService, /Assessment Date/);
  assert.match(pdfService, /Assessment Time/);
  assert.match(pdfService, /Confidential Report/);
});

test("PDF cover uses participant data and elapsed assessment duration", () => {
  assert.match(pdfService, /\$participantName/);
  assert.match(pdfService, /formatAssessmentDate\(\$completed\)/);
  assert.match(pdfService, /formatAssessmentDuration/);
  assert.match(pdfService, /getTimestamp\(\) - \$start->getTimestamp\(\)/);
  assert.match(pdfService, /hours/);
  assert.match(pdfService, /minutes/);
  assert.match(pdfService, /seconds/);
});

test("PDF cover includes approved Atom Global closing copy and address", () => {
  assert.match(pdfService, /Unleashing Human Potential/);
  assert.match(pdfService, /Atom Global Consulting Pte\. Ltd\./);
  assert.match(pdfService, /Level 49, 1 Raffles Quay/);
  assert.match(pdfService, /Singapore 048583/);
  assert.match(pdfService, /branding\.report_logo_url/);
});

test("existing Full Report content remains after the cover", () => {
  assert.match(pdfService, /Your alignment pattern/);
  assert.match(pdfService, /Your full development report/);
  assert.match(pdfService, /executiveSummary/);
  assert.match(pdfService, /scoreBreakdownSection/);
  assert.match(pdfService, /roadmap/);
  assert.match(pdfService, /profileSpectrum/);
});
