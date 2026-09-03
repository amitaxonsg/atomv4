import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import { assessmentTracks } from "../../src/data/assessmentData.js";
import { buildRuntimeTrack, V3_AREA_NAMES, V3_QUESTION_COUNT } from "../../src/data/runtimeAssessment.js";

const expectedNames = {
  personal: ["Decision-Making", "Relationships & Connection", "Emotional Awareness", "Conflict Navigation", "Trust & Intuition", "Empathy & Compassion", "Authentic Self-Expression", "Stress & Pressure Response", "Values & Life Priorities", "Communication Style"],
  newjoiner: ["Decision-Making as You Start Out", "Building Relationships at a New Job", "Emotional Awareness in a New Environment", "Handling Feedback & Early Conflict", "Trust & Intuition as a Newcomer", "Empathy for Your New Team", "Authentic Presence as the New Person", "Pressure & Imposter Moments", "What You’re Optimizing For Early On", "Communication as a New Team Member"],
  manager: ["Decision-Making", "Team Relationships & Trust", "Emotional Awareness at Work", "Conflict & Difficult Conversations", "Trust & Intuition About People", "Empathy for Your Team", "Authentic Leadership", "Stress & Pressure at Work", "What You’re Optimizing For", "Communication as a Manager"],
  executive: ["Strategic Decision-Making", "Executive Trust & Relationships", "Emotional Awareness in the C-Suite", "High-Stakes Conflict & Negotiation", "Trust & Intuition on Big Bets", "Empathy at Scale", "Authentic Executive Presence", "Pressure at the Top", "What You’re Building For", "Communication as an Executive"],
};

const read = path => fs.readFileSync(new URL(path, import.meta.url), "utf8");

test("V3 publishes 40 questions as ten four-question areas while preserving the 50-question source bank", () => {
  assert.equal(V3_QUESTION_COUNT, 40);
  for (const [trackKey, sourceTrack] of Object.entries(assessmentTracks)) {
    assert.equal(sourceTrack.allItems.length, 50, `${trackKey} source bank should remain rollback-safe at 50`);
    const runtime = buildRuntimeTrack(sourceTrack, null);
    assert.equal(runtime.allItems.length, 40, `${trackKey} runtime should publish 40`);
    assert.equal(runtime.subscales.length, 10);
    assert.ok(runtime.subscales.every(section => section.items.length === 4));
    assert.deepEqual(runtime.subscales.map(section => section.name), expectedNames[trackKey]);
  }
});

test("every CMS save and deploy path enforces 40 public questions and 10 areas", () => {
  const seed = read("../../backend/bin/seed.php");
  const migration = read("../../database/migrations/013_v3_public_question_count_40.sql");
  const cmsNormaliser = read("../../backend/bin/apply-v3-public-cms.php");
  const deployment = read("../../deploy/update-v3-apache-staging.sh");
  const experienceService = read("../../backend/src/Services/AssessmentExperienceService.php");
  const extraRoutes = read("../../backend/src/extra-routes.php");

  assert.match(seed, /3, 12, 40, 10/);
  assert.match(seed, /question_count = 40/);
  assert.doesNotMatch(seed, /question_count = 50/);
  assert.match(migration, /ats\.question_count = 40/);
  assert.match(cmsNormaliser, /V3_PUBLIC_QUESTION_COUNT = 40/);
  assert.match(cmsNormaliser, /UPDATE assessment_track_settings SET question_count = \?, section_count = \?/);
  assert.match(deployment, /php bin\/seed\.php\s+php bin\/apply-v3-public-cms\.php/s);
  assert.match(experienceService, /VALUES \(\?, \?, \?, 15, 15, \?, \?, 40, 10/);
  assert.match(experienceService, /ON DUPLICATE KEY UPDATE question_count = 40, section_count = 10/);
  assert.match(extraRoutes, /question_count = 40, section_count = 10/);
  assert.match(extraRoutes, /, 40, 10,/);
  assert.doesNotMatch(extraRoutes, /, 50, 10,/);
});

test("V3 approved stage visual is CMS-backed through the real media foreign-key schema", () => {
  const normaliser = read("../../backend/bin/apply-v3-public-cms.php");
  const schema = read("../../database/migrations/001_initial_schema.sql");

  assert.match(schema, /desktop_media_id BIGINT UNSIGNED NULL/);
  assert.match(schema, /mobile_media_id BIGINT UNSIGNED NULL/);
  assert.match(normaliser, /SELECT id FROM media_library WHERE storage_path = \?/);
  assert.match(normaliser, /INSERT INTO media_library/);
  assert.match(normaliser, /UPDATE content_stages SET desktop_media_id = \?, mobile_media_id = NULL/);
  assert.match(normaliser, /sunil-head-heart-v3\.webp/);
  assert.doesNotMatch(normaliser, /SET image_url =/);
  assert.doesNotMatch(normaliser, /mobile_image_url/);
  assert.doesNotMatch(normaliser, /overlay_opacity/);
});

test("Sunil exact area names are CMS-backed and real sessions prefer the database names", () => {
  const codes = ["DM", "RC", "EA", "CN", "TI", "EC", "AE", "SP", "VP", "CS"];
  const cmsNormaliser = read("../../backend/bin/apply-v3-public-cms.php");
  const runtime = read("../../src/data/runtimeAssessment.js");
  const pdf = read("../../backend/src/Services/PdfService.php");

  for (const trackKey of Object.keys(expectedNames)) {
    assert.deepEqual(codes.map(code => V3_AREA_NAMES[trackKey][code]), expectedNames[trackKey]);
    for (const name of expectedNames[trackKey]) assert.ok(cmsNormaliser.includes(name), `${trackKey} CMS name missing: ${name}`);
  }
  assert.match(runtime, /name: section\.name \|\| fallback\.name/);
  assert.match(runtime, /name: question\.subscaleName \|\| fallback\.name/);
  assert.doesNotMatch(runtime, /Personal Decision-Making/);
  assert.doesNotMatch(runtime, /Manager Decision-Making/);
  assert.doesNotMatch(runtime, /Executive Strategic Decision-Making/);
  assert.doesNotMatch(pdf, /Personal Decision-Making/);
  assert.doesNotMatch(pdf, /Manager Decision-Making/);
  assert.doesNotMatch(pdf, /Executive Strategic Decision-Making/);
});

test("Sunil landing, pricing, topic hiding and progress copy are CMS-backed", () => {
  const defaults = read("../../src/data/assessmentExperience.js");
  const service = read("../../backend/src/Services/AssessmentExperienceService.php");
  const normaliser = read("../../backend/bin/apply-v3-public-cms.php");
  const layout = read("../../src/components/assessment/AssessmentLayout.jsx");
  const app = read("../../src/components/AssessmentAppProduction.jsx");
  const admin = read("../../src/components/admin/QuestionnairePage.jsx");
  const mock = read("../../src/api/mockData.js");

  assert.match(defaults, /You'll answer 40 statements/);
  assert.doesNotMatch(defaults, /You'll answer 50 statements/);
  assert.match(defaults, /Take the full 40-question assessment/);
  assert.match(service, /hideSectionTitles/);
  assert.match(normaliser, /'personal'\s*=>\s*499/);
  assert.match(normaliser, /'newjoiner'\s*=>\s*2900/);
  assert.match(normaliser, /'manager'\s*=>\s*4900/);
  assert.match(normaliser, /'executive'\s*=>\s*9900/);
  assert.match(normaliser, /Align with what you feel and what you reason with\./);
  assert.match(mock, /Align with what you feel and what you reason with\./);
  assert.match(layout, /hideSectionTitles/);
  assert.match(layout, /<h1>Assessment questions<\/h1>/);
  assert.match(layout, /landing\.halfwayTitle/);
  assert.match(layout, /landing\.completeTitle/);
  assert.match(app, /progressExperience=\{experience\.landing\}/);
  assert.match(admin, /Hide assessment-area\/topic titles/);
  assert.match(admin, /Question 20 milestone heading/);
  assert.match(admin, /Question 40 completion heading/);
  assert.match(admin, /40 public questions/);
});

test("direct assessment links and UAT no-payment wording are client-ready", () => {
  const app = read("../../src/components/AssessmentAppProduction.jsx");
  const report = read("../../src/components/assessment/ReportView.jsx");
  assert.match(app, /params\.get\("track"\)/);
  assert.match(app, /assessmentTracks\[directTrack\]/);
  assert.match(app, /UAT Test — No Payment selected/);
  assert.match(report, /UAT Test — No Payment/);
  assert.doesNotMatch(report, />Cash on Delivery</);
});

test("question UI keeps absolute radio identity and does not reveal area titles when CMS rule is on", () => {
  const layout = read("../../src/components/assessment/AssessmentLayout.jsx");
  assert.match(layout, /name=\{`question-\$\{answerIndex\}`\}/);
  assert.match(layout, /hideSectionTitles \? <>/);
  assert.match(layout, /Section \{section \+ 1\} of \{track\.subscales\.length\}/);
  assert.doesNotMatch(layout, /<fieldset className="latest-question-card"/);
});

test("legacy CMS question edit route cannot alter scoring position identity or active state", () => {
  const extraRoutes = read("../../backend/src/extra-routes.php");
  assert.match(extraRoutes, /\['scoringDirection', 'position', 'required', 'active', 'sectionId', 'stableKey'\]/);
  assert.match(extraRoutes, /Only question text may be corrected/);
  assert.match(extraRoutes, /confirmMeaningUnchanged/);
  assert.doesNotMatch(extraRoutes, /UPDATE questions SET question_text = \?, scoring_direction/);
});

test("Full Development Report covers Sunil complete content and sharing scope", () => {
  const report = read("../../src/components/assessment/ReportView.jsx");
  const enhancer = read("../../backend/src/Services/V3ReportEnhancer.php");
  const pdf = read("../../backend/src/Services/PdfService.php");

  for (const phrase of ["Top three strengths", "Sharpest Edge", "Growth Edge", "Your 10-area score breakdown", "Your 10-area deep dive", "Development roadmap", "Understand the Head–Heart profile spectrum", "Your written reflections", "Methodology and sourcing", "Five practical everyday actions", "Copy as text", "Email to self"]) {
    assert.ok(report.includes(phrase), `web report missing ${phrase}`);
  }
  for (const key of ["sharpestEdge", "growthEdge", "radarLegend", "profileSpectrum", "writtenReflections", "methodology"]) assert.ok(enhancer.includes(key), `report enhancer missing ${key}`);
  assert.match(enhancer, /How You’re Coming Across/);
  assert.match(enhancer, /trackKey === 'personal'/);
  assert.match(pdf, /scoreBreakdownSection/);
  assert.doesNotMatch(pdf, /radarSvg/);
  assert.match(pdf, /Sharpest Edge/);
  assert.match(pdf, /Growth Edge/);
  assert.match(pdf, /profileSpectrum/);
  assert.match(pdf, /writtenReflections/);
  assert.match(pdf, /methodology/);
});

test("Full Report PDF is generated and attached to paid-report emails", () => {
  const delivery = read("../../backend/src/Mail/MailDeliveryService.php");
  const processor = read("../../backend/src/Mail/MailQueueProcessor.php");
  const stripe = read("../../backend/src/Payments/StripeService.php");
  const uat = read("../../backend/src/Payments/CashOnDeliveryService.php");
  const admin = read("../../backend/src/Services/ReportAdminService.php");
  const routes = read("../../backend/src/extra-routes.php");

  assert.match(delivery, /_attachments/);
  assert.match(delivery, /fileblob/);
  assert.match(delivery, /addAttachment/);
  assert.match(processor, /paid_report_ready/);
  assert.match(processor, /\$this->pdf->generate\(\$reportId\)/);
  assert.match(processor, /Growth-Alignment-Full-Development-Report\.pdf/);
  assert.match(stripe, /'reportId' => \$reportAccess\['reportId'\]/);
  assert.match(uat, /'reportId' => \$access\['reportId'\]/);
  assert.match(admin, /'reportId' => \$reportId/);
  assert.match(routes, /POST', '\/api\/reports\/\{token\}\/email/);
  assert.match(routes, /\$container\['pdf'\]->generate/);
});

test("V4 track-priced retest is restricted to a verified paid Full Report and 90-day wait", () => {
  const report = read("../../src/components/assessment/ReportView.jsx");
  const survey = read("../../backend/src/Services/SurveyService.php");
  const stripe = read("../../backend/src/Payments/StripeService.php");
  const reportService = read("../../backend/src/Services/ReportService.php");

  assert.match(report, /Retest price:/);
  assert.match(report, /90 days after the original assessment/);
  assert.match(report, /__RETAKE__/);
  assert.match(survey, /V3_QUESTION_COUNT = 40/);
  assert.doesNotMatch(survey, /All 50 questions must be answered/);
  assert.match(stripe, /RETAKE_DEFAULTS/);
  assert.match(stripe, /stripe\.retest_price_/);
  assert.match(stripe, /modify\('\+' \. \$waitDays \. ' days'\)/);
  assert.match(stripe, /hasPaidAssessment\(\$sessionId\)/);
  assert.match(stripe, /provider = \? AND status = \?/);
  assert.match(reportService, /retakeCheckoutAvailable\(int \$sessionId, string \$trackKey\)/);
  assert.match(reportService, /DATE_SUB\(NOW\(\), INTERVAL \? DAY\)/);
  assert.match(reportService, /retakeComparison/);
  assert.match(reportService, /retake_payment/);
});
