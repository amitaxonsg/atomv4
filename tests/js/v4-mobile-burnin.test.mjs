import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";

const read = path => fs.readFileSync(path, "utf8");

test("V4 mobile controls stay inside the viewport and use iOS-safe sizing", () => {
  const css = read("src/v4-mobile-burnin.css");
  assert.match(css, /overflow-x:\s*clip/);
  assert.match(css, /font-size:\s*16px\s*!important/);
  assert.match(css, /upgrade-box__actions/);
  assert.match(css, /grid-template-columns:\s*minmax\(0,\s*1fr\)/);
});

test("V4 personal payment message is scoped to Personal only", () => {
  const css = read("src/v4-mobile-burnin.css");
  assert.match(css, /\.v4-report--personal[\s\S]*For less than a cup of coffee, find out more about yourself\./);
  assert.doesNotMatch(css, /\.v4-report--professional[^\n]*::after/);
});

test("V4 checkout recovery reloads stale report state on browser return", () => {
  const source = read("src/main.jsx");
  assert.match(source, /function CheckoutReturnRecovery/);
  assert.match(source, /pageshow/);
  assert.match(source, /visibilitychange/);
  assert.match(source, /Opening \(checkout\|secure checkout\)/);
  assert.match(source, /v4\.atomglobal\.com/);
});

test("V4 CMS public logo and banner values are honored", () => {
  const source = read("src/branding/BrandContext.jsx");
  assert.match(source, /return url \|\| transparentLogoUrl/);
  assert.match(source, /applyBannerFallback/);
  assert.match(source, /nextBranding\.bannerUrl/);
  assert.doesNotMatch(source, /startsWith\("\/media-uploads\/"\) \? legacyLogoUrl/);
});

test("V4 fallback copy matches the live UAT title and lively progress treatment", () => {
  const source = read("src/data/assessmentExperience.js");
  assert.match(source, /title:\s*"Head–Heart Alignment"/);
  assert.match(source, /cardTitlePrefix:\s*"Head–Heart Alignment:"/);
  assert.match(source, /Hey, you’re halfway there!/);
  assert.match(source, /Well done — you’ve completed all 40 questions!/);
});

test("V4 report already exposes server-controlled UAT no-payment availability", () => {
  const source = read("src/components/assessment/ReportView.jsx");
  assert.match(source, /cashOnDeliveryAvailable/);
  assert.match(source, /UAT Test — No Payment/);
  assert.match(source, /\/api\/payments\/cash-on-delivery/);
});
