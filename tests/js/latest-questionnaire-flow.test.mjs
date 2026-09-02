import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";

const layout = readFileSync(new URL("../../src/components/assessment/AssessmentLayout.jsx", import.meta.url), "utf8");
const experience = readFileSync(new URL("../../src/data/assessmentExperience.js", import.meta.url), "utf8");
const app = readFileSync(new URL("../../src/components/AssessmentAppProduction.jsx", import.meta.url), "utf8");
const admin = readFileSync(new URL("../../src/components/admin/QuestionnairePage.jsx", import.meta.url), "utf8");
const routes = readFileSync(new URL("../../backend/src/assessment-experience-routes.php", import.meta.url), "utf8");
const service = readFileSync(new URL("../../backend/src/Services/AssessmentExperienceService.php", import.meta.url), "utf8");
const survey = readFileSync(new URL("../../backend/src/Services/SurveyService.php", import.meta.url), "utf8");
const main = readFileSync(new URL("../../src/main.jsx", import.meta.url), "utf8");
const branding = readFileSync(new URL("../../src/branding/BrandContext.jsx", import.meta.url), "utf8");
const questionnaireStyles = readFileSync(new URL("../../src/questionnaire-latest.css", import.meta.url), "utf8");

test("public questionnaire keeps the latest process inside the approved split branding", () => {
  assert.match(layout, /latest-questionnaire-shell/);
  assert.match(layout, /latest-visual-panel/);
  assert.doesNotMatch(layout, /reflection-portrait\.png/);
  assert.match(experience, /Every choice you make is cast by two votes/);
  assert.match(layout, /latest-track-card/);
  assert.match(layout, /Personal Assessment/);
  assert.match(layout, /Corporate Assessments/);
  assert.match(layout, /setCategory\("personal"\)/);
  assert.match(layout, /setCategory\("corporate"\)/);
  assert.match(layout, /Choose Personal or Corporate/);
  assert.match(layout, /Begin the free assessment/);
  assert.doesNotMatch(layout, /Powered by/);
  assert.match(main, /questionnaire-latest\.css/);
  assert.match(branding, /return url \|\| transparentLogoUrl/);
  assert.match(branding, /stages: \{ \.\.\.defaults\.stages, \.\.\.\(remote\.stages \|\| \{\}\) \}/);
  assert.doesNotMatch(branding, /applyBannerFallback/);
  assert.doesNotMatch(branding, /startsWith\("\/media-uploads\/"\).*legacyLogoUrl/);
});

test("CMS stage image remains visible above the questionnaire on mobile", () => {
  assert.match(questionnaireStyles, /@media \(max-width: 900px\)[\s\S]*\.latest-visual-panel \{[\s\S]*display: block;/);
  assert.match(questionnaireStyles, /height: clamp\(210px, 56\.25vw, 330px\)/);
  assert.match(questionnaireStyles, /\.latest-visual-panel__logo,[\s\S]*\.latest-visual-panel__copy \{[\s\S]*display: none;/);
  assert.doesNotMatch(questionnaireStyles, /@media \(max-width: 900px\)[\s\S]*?\.latest-visual-panel \{ display: none; \}/);
});

test("latest participant and question process remains wired to the real backend", () => {
  assert.match(layout, /A little more context/);
  assert.match(layout, /department/);
  assert.match(layout, /level/);
  assert.match(layout, /N\/A — doesn’t apply \/ can’t answer/);
  assert.match(layout, /latest-answer-note-dropdown/);
  assert.match(layout, /Add more \(optional\)/);
  assert.match(layout, /Is there anything else you would like to add/);
  assert.doesNotMatch(layout, /Optional — describe a specific moment/);
  assert.match(app, /createSession/);
  assert.match(app, /saveSession/);
  assert.match(app, /completeSession/);
  assert.match(app, /SelectVersion experience=\{experience\}/);
  assert.match(layout, /requestAnimationFrame\(\(\) => window\.scrollTo\(\{ top: 0, left: 0, behavior: "auto" \}\)\)/);
});

test("V4 autosave serializes requests and clears stale mobile save errors", () => {
  assert.match(app, /saveQueueRef = React\.useRef\(Promise\.resolve\(\)\)/);
  assert.match(app, /saveRevisionRef = React\.useRef\(0\)/);
  assert.match(app, /saveQueueRef\.current = saveQueueRef\.current/);
  assert.match(app, /revision !== saveRevisionRef\.current/);
  assert.match(app, /setSaveState\("saved"\);\s*setError\(""\);/);
});

test("landing, track cards and intake are editable from the admin CMS", () => {
  assert.match(admin, /Public landing and progress content/);
  assert.match(admin, /Track-card description/);
  assert.match(admin, /Department options/);
  assert.match(admin, /Level options/);
  assert.match(admin, /Question 20 milestone heading/);
  assert.match(admin, /Question 40 completion heading/);
  assert.match(routes, /\/api\/admin\/assessment-experience\/landing/);
  assert.match(service, /questionnaire\.landing/);
  assert.match(service, /questionnaire_landing\.saved/);
  assert.match(service, /UPDATE assessment_tracks SET description/);
});

test("all four published assessments are offered to new participants", () => {
  assert.match(layout, /trackOrder = \["personal", "newjoiner", "manager", "executive"\]/);
  assert.match(layout, /isPersonal \? personalTracks : corporateTracks/);
  assert.match(layout, /experience\?\.tracks\?\.\[track\.key\]/);
  assert.doesNotMatch(survey, /This assessment is not currently open for new participants/);
  assert.match(admin, /Four public assessment choices/i);
  assert.match(admin, /40 public questions/);
  assert.match(admin, /50-question source bank retained/);
});

test("admin warns that material question changes affect interpretation and history", () => {
  assert.match(admin, /Do not replace a question with a different question/);
  assert.match(admin, /full meaning change can invalidate comparisons and report interpretation/);
  assert.match(admin, /published versions are immutable/);
});
