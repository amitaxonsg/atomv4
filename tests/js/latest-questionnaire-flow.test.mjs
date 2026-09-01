import test from "node:test";
import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";

const layout = readFileSync(new URL("../../src/components/assessment/AssessmentLayout.jsx", import.meta.url), "utf8");
const experience = readFileSync(new URL("../../src/data/assessmentExperience.js", import.meta.url), "utf8");
const app = readFileSync(new URL("../../src/components/AssessmentAppProduction.jsx", import.meta.url), "utf8");
const admin = readFileSync(new URL("../../src/components/admin/QuestionnairePage.jsx", import.meta.url), "utf8");
const routes = readFileSync(new URL("../../backend/src/assessment-experience-routes.php", import.meta.url), "utf8");
const service = readFileSync(new URL("../../backend/src/Services/AssessmentExperienceService.php", import.meta.url), "utf8");
const healthService = readFileSync(new URL("../../backend/src/Services/HealthService.php", import.meta.url), "utf8");
const passwordResetService = readFileSync(new URL("../../backend/src/Services/PasswordResetService.php", import.meta.url), "utf8");
const routeBundle = readFileSync(new URL("../../backend/src/route-bundle.php", import.meta.url), "utf8");
const survey = readFileSync(new URL("../../backend/src/Services/SurveyService.php", import.meta.url), "utf8");
const main = readFileSync(new URL("../../src/main.jsx", import.meta.url), "utf8");
const branding = readFileSync(new URL("../../src/branding/BrandContext.jsx", import.meta.url), "utf8");
const questionnaireStyles = readFileSync(new URL("../../src/questionnaire-latest.css", import.meta.url), "utf8");
const mockData = readFileSync(new URL("../../src/api/mockData.js", import.meta.url), "utf8");
const v4LandingImageMigration = readFileSync(new URL("../../database/migrations/017_v4_restore_supplied_landing_image.sql", import.meta.url), "utf8");
const v4AllStageImageMigration = readFileSync(new URL("../../database/migrations/018_v4_use_supplied_image_all_stages.sql", import.meta.url), "utf8");
const v4RetiredImageMigration = readFileSync(new URL("../../database/migrations/019_v4_remove_retired_head_heart_image.sql", import.meta.url), "utf8");
const v4Deployer = readFileSync(new URL("../../deploy/update-v4-apache.sh", import.meta.url), "utf8");
const sharedApacheDeployer = readFileSync(new URL("../../deploy/update-v3-apache-staging.sh", import.meta.url), "utf8");

test("public questionnaire keeps the latest process inside the approved split branding", () => {
  assert.match(layout, /latest-questionnaire-shell/);
  assert.match(layout, /latest-visual-panel/);
  assert.match(layout, /reflection-portrait\.png/);
  assert.match(mockData, /version: \{ image: "\/media\/stages\/reflection-portrait\.png"/);
  assert.equal((mockData.match(/image: "\/media\/stages\/reflection-portrait\.png"/g) || []).length, 7);
  assert.equal((mockData.match(/mobileImage: ""/g) || []).length, 7);
  assert.doesNotMatch(mockData, /sunil-head-heart-v3\.webp|reflection-mobile\.webp/);
  assert.match(v4LandingImageMigration, /stage_key = 'version'/);
  assert.match(v4LandingImageMigration, /\/media\/stages\/reflection-portrait\.png/);
  assert.match(v4AllStageImageMigration, /UPDATE content_stages AS stage/);
  assert.match(v4AllStageImageMigration, /stage\.mobile_media_id = NULL/);
  assert.doesNotMatch(v4AllStageImageMigration, /WHERE stage\.stage_key/);
  assert.equal(existsSync(new URL("../../public/media/stages/sunil-head-heart-v3.webp", import.meta.url)), false);
  assert.match(v4RetiredImageMigration, /UPDATE content_stages AS stage/);
  assert.match(v4RetiredImageMigration, /stage\.desktop_media_id = replacement\.id/);
  assert.match(v4RetiredImageMigration, /stage\.mobile_media_id = NULL/);
  assert.match(v4RetiredImageMigration, /DELETE FROM media_library/);
  assert.match(v4RetiredImageMigration, /\/media\/stages\/sunil-head-heart-v3\.webp/);
  assert.match(v4Deployer, /CMS_APPLY_SCRIPT="\$\{CMS_APPLY_SCRIPT:-\}"/);
  assert.match(sharedApacheDeployer, /CMS_APPLY_SCRIPT="\$\{CMS_APPLY_SCRIPT-bin\/apply-v3-public-cms\.php\}"/);
  assert.doesNotMatch(sharedApacheDeployer, /CMS_APPLY_SCRIPT="\$\{CMS_APPLY_SCRIPT:-bin\/apply-v3-public-cms\.php\}"/);
  assert.match(healthService, /019_v4_remove_retired_head_heart_image\.sql/);
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
  assert.doesNotMatch(branding, /startsWith\("\/media-uploads\/"\).*legacyLogoUrl/);
  assert.match(branding, /return url \|\| transparentLogoUrl/);
});

test("CMS stage image remains visible on mobile and the logo sits in the right content panel", () => {
  assert.match(questionnaireStyles, /@media \(max-width: 900px\)[\s\S]*\.latest-visual-panel \{[\s\S]*display: block;/);
  assert.match(questionnaireStyles, /height: clamp\(210px, 56\.25vw, 330px\)/);
  assert.doesNotMatch(layout, /latest-visual-panel__logo/);
  assert.match(questionnaireStyles, /\.latest-visual-panel__copy \{[\s\S]*margin-top: auto;/);
  assert.match(questionnaireStyles, /\.latest-public-brand \{[\s\S]*display: flex;[\s\S]*justify-content: flex-end;/);
  assert.match(questionnaireStyles, /object-position: right center/);
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

test("admin password reset attempts immediate delivery and retains the retry queue", () => {
  assert.match(passwordResetService, /public function request\(string \$email\): \?int/);
  assert.match(passwordResetService, /\$queueId = \$this->mailQueue->enqueue\('password_reset'/);
  assert.match(passwordResetService, /return \$queueId;/);
  assert.match(routeBundle, /\$queueId = \$container\['passwordReset'\]->request\(\$email\)/);
  assert.match(routeBundle, /\$container\['mailQueueProcessor'\]->processIds\(\[\$queueId\]\)/);
  assert.match(routeBundle, /return Response::json\(\['accepted' => true\]\)/);
});
