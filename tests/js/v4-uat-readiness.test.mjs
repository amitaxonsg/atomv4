import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const read = path => fs.readFileSync(path, "utf8");
const app = read("src/components/AssessmentAppProduction.jsx");
const report = read("src/components/assessment/ReportView.jsx");
const admin = read("src/components/admin/AdminApp.jsx");
const client = read("src/api/client.js");
const routes = read("backend/public/index.php");
const reset = read("backend/bin/reset-v4-uat-data.php");

test("successful UAT fallback uses the approved report wording", () => {
  assert.match(app, />Show the Report<\/a>/);
  assert.doesNotMatch(app, />View Report<\/a>/);
  assert.match(report, />Download PDF<\/a>/);
  assert.match(report, /Email me the PDF/);
});

test("all V4 Admin modules are mounted and use live API methods", () => {
  for (const module of [
    "Dashboard", "Participants", "Questionnaire", "Assessments", "Content",
    "Branding", "Reports", "Payments", "Email", "Affiliates", "Analytics",
    "SEO", "Settings", "Audit", "Feedback", "Help",
  ]) {
    assert.match(admin, new RegExp(`${module}: [A-Za-z]+Page`));
  }
  for (const method of [
    "adminDashboard", "adminParticipants", "adminAssessments", "adminContent",
    "adminBranding", "adminReports", "adminPayments", "adminEmailTemplates",
    "adminAffiliates", "adminAnalytics", "adminSeoPages", "adminSettings",
    "adminAuditLogs", "adminFeedback",
  ]) {
    assert.match(client, new RegExp(`${method}:`));
  }
  assert.match(routes, /\/api\/admin\/affiliates/);
  assert.match(routes, /\/api\/admin\/reports/);
  assert.match(routes, /\/api\/admin\/payments/);
  assert.match(routes, /\/api\/admin\/email-templates/);
});

test("pre-UAT reset is guarded, V4-only and preserves configuration", () => {
  assert.match(reset, /RESET-V4-UAT-DATA/);
  assert.match(reset, /growth_alignment_v4/);
  assert.match(reset, /https:\/\/v4\.atomglobal\.com/);
  assert.match(reset, /'participants'/);
  assert.match(reset, /'payments'/);
  assert.match(reset, /'affiliate_clicks'/);
  assert.match(reset, /'client_feedback'/);
  assert.match(reset, /'email_queue'/);
  assert.match(reset, /'stripe_webhook_events'/);
  assert.match(reset, /'affiliates'/);
  assert.doesNotMatch(reset, /DELETE FROM `affiliates`/);
  assert.doesNotMatch(reset, /DELETE FROM `admin_users`/);
  assert.doesNotMatch(reset, /TRUNCATE/i);
});
