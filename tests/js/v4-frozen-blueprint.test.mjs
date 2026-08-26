import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";

const read = path => fs.readFileSync(new URL(path, import.meta.url), "utf8");

test("V4 uses Growth Alignment product branding while retaining Head and Heart dimensions", () => {
  const html = read("../../index.html");
  const manifest = read("../../public/manifest.json");
  const experience = read("../../src/data/assessmentExperience.js");
  assert.match(html, /Growth Alignment Assessment/);
  assert.match(manifest, /Growth Alignment/);
  assert.match(experience, /title: "Growth Alignment"/);
  assert.match(experience, /heartLabel: "Heart"/);
  assert.match(experience, /headLabel: "Head"/);
});

test("V4 report implements executive summary, ScaleBar, Meter, commitment and coaching", () => {
  const report = read("../../src/components/assessment/ReportView.jsx");
  for (const phrase of ["Executive Summary", "Highest 3", "Lowest 3", "function ScaleBar", "function AlignmentMeter", "DevelopmentCommitment", "Save my commitment", "CoachCallToAction", "coachPrimaryName", "coachSecondaryName"]) {
    assert.ok(report.includes(phrase), `V4 report missing ${phrase}`);
  }
});

test("V4 database migration stores commitments and approved prices", () => {
  const migration = read("../../database/migrations/014_v4_growth_alignment.sql");
  assert.match(migration, /CREATE TABLE IF NOT EXISTS report_commitments/);
  for (const price of ["499", "1995", "4995", "9995", "299", "995", "2995"]) assert.ok(migration.includes(price));
  assert.match(migration, /retest\.wait_days', '90'/);
  assert.match(migration, /reeta\.nathwani@atomglobal\.com/);
  assert.match(migration, /sunil\.setpaul@atomglobal\.com/);
});

test("V4 keeps secrets out of source and transfers them through encrypted settings", () => {
  const transfer = read("../../deploy/copy-v3-integrations-to-v4.php");
  const migration = read("../../database/migrations/014_v4_growth_alignment.sql");
  assert.match(transfer, /--allow-live-stripe/);
  assert.match(transfer, /in_array\(\$key, \$sensitive, true\)/);
  assert.doesNotMatch(migration, /sk_live_|whsec_[A-Za-z0-9]/);
});
