import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";

const read = path => fs.readFileSync(new URL(path, import.meta.url), "utf8");
const branding = read("../../src/branding/BrandContext.jsx");
const layout = read("../../src/components/assessment/AssessmentLayout.jsx");
const assessment = read("../../src/components/AssessmentAppProduction.jsx");
const admin = read("../../src/components/admin/AdminCorePages.jsx");
const media = read("../../backend/src/Services/MediaService.php");
const routes = read("../../backend/public/index.php");
const experienceRoutes = read("../../backend/src/assessment-experience-routes.php");
const html = read("../../index.html");
const deployer = read("../../deploy/update-v4-apache.sh");

test("published Admin logo is passed through to every public BrandLogo", () => {
  assert.match(branding, /return url \|\| transparentLogoUrl/);
  assert.doesNotMatch(branding, /startsWith\("\/media-uploads\/"\)/);
  assert.match(branding, /src=\{normalisePublicLogoUrl\(branding\.logoUrl\)\}/);
});

test("published banner and favicon are consumed by the public frontend", () => {
  assert.match(layout, /stageKey === "version" && branding\.bannerUrl/);
  assert.match(branding, /link\[rel="apple-touch-icon"\]/);
  assert.match(routes, /\/api\/public\/manifest/);
  assert.match(routes, /branding\.favicon_url/);
  assert.match(html, /href="\/api\/public\/manifest"/);
});

test("Admin media accepts JPG and GIF together with modern logo and favicon formats", () => {
  assert.match(media, /'image\/jpeg' => 'jpg'/);
  assert.match(media, /'image\/gif' => 'gif'/);
  assert.match(media, /'image\/x-icon' => 'ico'/);
  assert.match(admin, /image\/jpeg,image\/png,image\/gif,image\/webp,image\/avif,image\/svg\+xml/);
  assert.match(admin, /Supported formats: JPG, PNG, GIF/);
});

test("hard refresh waits for published branding and questionnaire content", () => {
  assert.match(branding, /loaded \? children : <div className="brand-bootstrap"/);
  assert.match(assessment, /if \(!experienceLoaded\) return <div className="experience-bootstrap"/);
  assert.match(routes, /Cache-Control: no-store, max-age=0/);
  assert.match(experienceRoutes, /Cache-Control: no-store, max-age=0/);
});

test("V4 deployer pulls the active UAT branch containing Admin branding fixes", () => {
  assert.match(deployer, /BRANCH="\$\{BRANCH:-sunil-v4-smooth-checkout-crm-blueprint\}"/);
});
