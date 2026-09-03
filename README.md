# Atom Global Growth Alignment V4

> **V4 ONLY — CURRENT SOURCE OF TRUTH**
>
> This README describes the approved V4 application at `https://v4.atomglobal.com/`. Do not use another version/repository as a visual, deployment, database, CMS or feature reference for V4 work.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, Stripe payments, UAT no-payment control, PDF/email delivery, analytics, affiliates and audit history.

## Current V4 baseline — 3 September 2026

| Item | Current V4 value |
|---|---|
| Public URL | `https://v4.atomglobal.com/` |
| Admin URL | `https://v4.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| Working/deployment branch | `production-readiness-v4-mobile-final-20260902` |
| **Last server-verified live application commit in this record** | `0e7966a88e9c03c93b75537534424de8979af235` |
| **Latest accepted V4 code commit** | `f529c5972b750ba9f94f5e1e3e41f1a46bc8babb` |
| Latest accepted-code Git backup branch | `v4-accepted-backup-20260903-f529c59` |
| Previous accepted-code backup | `v4-live-backup-20260903-d32aedf` |
| Server-verified live backup | `v4-live-backup-20260903-0e7966a` |
| Pre-change Git backup | `v4-prechange-backup-20260903-3c21f04` |
| Confirmed pre-change server backup | `/var/backups/growth-alignment-v4/prechange-20260903-042350` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release | `/var/www/v4.atomglobal.com/current` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

> Documentation commits may be newer than the deployed application. The authoritative production runtime marker is `/var/www/v4.atomglobal.com/deployed-commit.txt`. Do not describe a newer commit as server-verified live until that marker or successful deploy output confirms the exact SHA.

The current accepted V4 code gate is **75/75 frontend tests**, successful Vite production build and clean PHP syntax for the PDF renderer. The last server-verified production health returned `status: ok` with database, migrations, storage, Stripe, Stripe webhook, email and cron healthy. `feedbackGitHub:false` remains optional and is not a launch blocker.

## Approved changes — 2–3 September 2026

### Admin UAT No-Payment visibility

Admin uses `system.cash_on_delivery_enabled` as the authoritative UAT override. When disabled, the `UAT Test — No Payment` button and explanatory text must be absent. When explicitly enabled for controlled client testing, the no-payment UAT path may appear.

Primary fix:

`183027dc9ceefb156d0cad53b52664eb4b5d43b4`

### Personal checkout coffee value banner

Final approved Personal-only wording:

`For less than a cup of coffee, find out more about yourself! ✨`

Accepted treatment:

- Personal assessment only;
- separate highlighted banner above the dark checkout panel;
- green-tinted background and stronger left accent;
- lively Libre Caslon Display typography;
- color-emoji fallbacks where supported;
- responsive desktop/mobile spacing;
- Stripe/UAT/report logic unchanged.

Key commits:

- `7bf9eeb0106c14b5be7f341a3ccacef0d1d0985c` — green banner treatment
- `3c31cf6597002bbde3641867abf6bf778d3d0d7c` — final wording and sparkle
- `33b75de6b905ec3f650af5ad5e5bb17b7468dc85` — lively typography
- `ecf57b8273cbbd74e4e37f3fc29c225dd9c6b082` — regression test aligned to final copy

### Mobile autosave race-condition fix

Rapid answering no longer allows an older failed save to leave a stale `Load failed` message after a newer save succeeds. Autosaves are serialized and the latest successful save clears stale errors.

Key commits:

- `03d3e998e4ee9f97ef734de06e290d934e5ddbb4`
- `d73684abf095063894b1dd90870a8ce3d2d37dc5`

### Halfway and completion milestones

Halfway and completion feedback use approved lively milestone cards with stronger typography, gold accents, restrained animation, reduced-motion support and mobile-specific sizing.

Approved correction:

`4a31759fdc7b2aa28bef636167699991c152f1f4`

### Executive Summary score bars

The Executive Summary Highest 3 / Lowest 3 items always display visible progress bars.

Accepted behavior:

- each item shows area name and `x/25`;
- scale labels remain `5 · Head-led`, `15 · Balanced`, `25 · Heart-led`;
- the full-width pale track remains visible;
- the filled bar remains visible and proportional to the 5–25 scale;
- no ranking or scoring logic changes.

Implementation/regression commits:

- `17eae9d80ceccd2625f29b402e837a87f24e42a0`
- `d32aedf31f488374e023655bed2eba6e3e1b6fec`

### Full 10-area score breakdown — radar removed

The radar visual is no longer part of the approved V4 Full Development Report. All ten assessment areas now use the same clear progress-bar language as the Executive Summary.

Approved browser treatment:

- heading: **Your 10-area score breakdown**;
- no radar SVG;
- no A–J markers;
- two columns on desktop, five areas per column;
- each area shows its full name and `x/25` value;
- every area uses a visible horizontal progress bar;
- all bars use the same correct **5–25 normalization**;
- interpretation remains **5 = more Head-led, 15 = balanced, 25 = more Heart-led**;
- mobile collapses to a clean single-column layout;
- report scoring and business logic are unchanged.

Approved PDF treatment:

- generated PDF contains the same ten score areas and 5–15–25 scale;
- no radar SVG is generated;
- two-column progress-bar layout mirrors the browser meaning;
- browser/PDF parity is covered by regression tests.

Change history:

- `fe955ba3226a686af102c0b3ab52ce2810455a59` — replace browser radar with full 10-area score bars
- `e985a54aa0d040261393dd0179e2953fa20c4306` — style 10-area breakdown like Executive Summary
- `cf62459ee4da5ad64b8d7473a111ce9afabf1679` — match generated PDF to bar-only breakdown
- `8d043598de254b21ba543e800e4eb3840034a797` — browser/PDF bar-breakdown parity test
- `f529c5972b750ba9f94f5e1e3e41f1a46bc8babb` — update legacy scope regression test to the approved bar-only design

The accepted V4 bar-only report baseline is:

`f529c5972b750ba9f94f5e1e3e41f1a46bc8babb`

## Approved visual state

The approved V4 presentation includes:

- desktop split layout: visual panel left, application content right;
- Atom Global public logo in the right/content panel;
- left visual headline/support copy positioned bottom-left on desktop;
- landing stage (`version`) intentionally uses `/media/stages/reflection-portrait.png` when no CMS media assignment exists;
- inner-stage CMS/content images remain authoritative;
- mobile responsive presentation and iOS-safe controls;
- Personal green coffee value banner above checkout;
- Executive Summary Highest 3 / Lowest 3 with visible score bars;
- **10-area score breakdown uses progress bars only — no radar**;
- browser and generated PDF use the same 5–25 score interpretation.

Do not introduce obsolete hard-coded image fallbacks or compensate for valid CMS state with unrelated frontend overrides.

### Critical landing-stage state

```text
stage_key: version
desktop_media_id: NULL
mobile_media_id: NULL
focal_x: 52.00
focal_y: 50.00
```

This is intentional V4 configuration.

## Current participant journey

V4 exposes four public assessment tracks:

- Personal
- New Joiner
- Manager
- Executive

The questionnaire uses **40 questions across 10 sections**.

Journey:

1. track selection;
2. introduction;
3. participant details and consent;
4. secure survey session creation;
5. 40-question assessment;
6. autosave and resume;
7. completion/scoring;
8. Lite Report;
9. Stripe checkout or explicitly enabled UAT no-payment route;
10. Full Report, secure link, PDF and email delivery.

## Admin / CMS wiring

V4 Admin is connected to the production API/database for Dashboard, Participants/history, Questionnaire experience/content, Assessments, Content stages/media, Branding, Reports/PDF, Payments/UAT, Email, Affiliates, Analytics, SEO/AEO/GEO, Settings/integrations, Admin users/permissions, Audit logs and Feedback/help.

CMS/database state is authoritative.

## Standard V4 deployment

**Apache only.**

```bash
cd /srv/v4.atomglobal.com/source

git fetch origin
git checkout production-readiness-v4-mobile-final-20260902
git reset --hard origin/production-readiness-v4-mobile-final-20260902

npm test
npm run build
php -l backend/src/Services/PdfService.php

sudo BRANCH=production-readiness-v4-mobile-final-20260902 \
  bash deploy/update-v4-apache.sh
```

### Post-deployment verification

```bash
echo "SOURCE:"
git rev-parse HEAD

echo "LIVE:"
cat /var/www/v4.atomglobal.com/deployed-commit.txt

curl -fsS https://v4.atomglobal.com/api/health
```

Last server-verified live marker currently recorded here:

```text
0e7966a88e9c03c93b75537534424de8979af235
```

Latest accepted V4 code baseline:

```text
f529c5972b750ba9f94f5e1e3e41f1a46bc8babb
```

## Approved V4 backup procedure

Latest accepted-code Git safety branch:

```text
v4-accepted-backup-20260903-f529c59
```

Previous accepted-code backup:

```text
v4-live-backup-20260903-d32aedf
```

Current server-verified live backup:

```text
v4-live-backup-20260903-0e7966a
```

Pre-change Git safety branch:

```text
v4-prechange-backup-20260903-3c21f04
```

Confirmed full pre-change server backup:

```text
/var/backups/growth-alignment-v4/prechange-20260903-042350
```

That server backup includes a validated compressed MariaDB dump for `growth_alignment_v4`, protected `v4.env`, deployed commit marker, source Git HEAD, active release reference and clean Git status snapshot.

Git alone does not contain all live CMS/database configuration.

## UAT focus

Retest at minimum:

- Personal/New Joiner/Manager/Executive starts;
- 40 questions / 10 sections;
- halfway milestone after question 20;
- completion milestone after question 40;
- rapid mobile autosave and stale-error recovery;
- resume persistence;
- Lite/Full Report lock;
- Personal coffee banner and `✨` rendering;
- Pay by card flow and UAT enabled/disabled behavior;
- secure Full Report/PDF/email delivery;
- Executive Summary six bars remain visible and proportional;
- **10-area score breakdown shows all ten progress bars and no radar**;
- all ten `x/25` values match their bar positions;
- 5 / 15 / 25 Head-led / Balanced / Heart-led labels are readable;
- generated PDF contains the same bar-only score meaning as browser;
- mobile score breakdown stacks without overflow;
- CMS image/logo/content edits reflect correctly.

## Change-control rule

**Do not mix V4 with another version or repository.**

Before any V4 production change:

1. start from the current V4 branch;
2. back up Git + database/CMS state;
3. make the smallest V4-only change;
4. run the complete automated gate;
5. review the diff;
6. deploy with the V4 Apache deployer;
7. verify the deployed commit and `/api/health`;
8. perform browser/mobile/PDF UAT;
9. update this README when the accepted baseline changes.

The V4 branch, production runtime marker and this README are the authoritative operational references.
