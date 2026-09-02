import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const read = path => readFileSync(path, "utf8");
const admin = read("backend/src/Services/AdminService.php");
const brand = read("src/branding/BrandContext.jsx");
const pages = read("src/components/admin/AdminCorePages.jsx");
const layout = read("src/components/assessment/AssessmentLayout.jsx");
const css = read("src/questionnaire-latest.css");
const mail = read("backend/src/Mail/MailDeliveryService.php");
const audit = read("deploy/full-production-audit.sh");
const smoke = read("backend/bin/production-submission-smoke-test.php");
const readme = read("README.md");

test("branding CMS exposes questionnaire colours, type sizes and widths", () => {
  for (const token of ["questionnaire_copy", "page_title_size", "question_text_size", "intake_max_width", "question_max_width"]) {
    assert.match(admin, new RegExp(token));
  }
  for (const token of ["questionnaireCopy", "pageTitleSize", "questionTextSize", "intakeMaxWidth", "questionMaxWidth"]) {
    assert.match(brand, new RegExp(token));
    assert.match(pages, new RegExp(token));
  }
});

test("participant intake is two-column on desktop and one-column on mobile", () => {
  assert.match(layout, /latest-intake-grid--identity/);
  assert.match(layout, /latest-intake-grid--context/);
  assert.match(css, /grid-template-columns: repeat\(2, minmax\(0, 1fr\)\)/);
  assert.match(css, /latest-intake-grid \{ grid-template-columns: 1fr; \}/);
});

test("questionnaire typography and colours use branding variables", () => {
  for (const token of ["--questionnaire-title-size", "--questionnaire-body-size", "--questionnaire-question-size", "--questionnaire-option-size", "--questionnaire-input-surface", "--questionnaire-selected-surface"]) {
    assert.match(css, new RegExp(token));
  }
  assert.match(css, /\.latest-primary-button \{\s*background: var\(--gold, #c9a15a\);\s*color: #14141c;/s);
  assert.match(css, /\.latest-scale-options label\.selected \{\s*background: var\(--questionnaire-selected-surface,/s);
  assert.match(css, /color: var\(--questionnaire-selected-text, #14141c\);/);
});

test("email frame uses published branding values", () => {
  assert.match(mail, /branding\.heading_font/);
  assert.match(mail, /branding\.body_font/);
  assert.match(mail, /branding\.cta/);
  assert.match(mail, /branding\.card_radius/);
});

test("full audit requires four current v2 assessments and no old traces", () => {
  assert.match(audit, /oldVersionRows/);
  assert.match(audit, /oldVersionSessions/);
  assert.match(audit, /assessmentVersionRows/);
  assert.match(audit, /participantTemplates/);
  assert.doesNotMatch(audit, /single live-assessment control/);
});

test("guarded smoke test verifies and cleans the complete submission chain", () => {
  assert.match(smoke, /RUN-PRODUCTION-SUBMISSION-SMOKE/);
  assert.match(smoke, /All 40 frontend answer payloads were persisted/);
  assert.match(smoke, /Admin participant detail shows the generated report/);
  assert.match(smoke, /Temporary participant, session, answers, score, report/);
  assert.match(readme, /Approved V4 backup procedure/);
});
