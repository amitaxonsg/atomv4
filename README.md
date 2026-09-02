# Atom Global Growth Alignment V4

> **V4 ONLY — CURRENT SOURCE OF TRUTH**
>
> This repository and this README describe the approved V4 deployment at `https://v4.atomglobal.com/`. Do not use another project/version as a visual, deployment, database or CMS reference for V4 work.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, payments, email, analytics, affiliates and audit history.

## Approved live baseline — 2 September 2026

| Item | Approved V4 value |
|---|---|
| Public URL | `https://v4.atomglobal.com/` |
| Admin URL | `https://v4.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| Working/deployment branch | `production-readiness-v4-mobile-final-20260902` |
| Approved code commit | `02ffc7b60d49841ea8779c22ee6dcae299f4dd8d` |
| Approved Git backup branch | `v4-live-approved-backup-20260902` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release | `/var/www/v4.atomglobal.com/current` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

The approved V4 baseline passed the complete automated readiness gate: **73/73 frontend tests**, Vite production build, PHP lint/tests and the full MariaDB integration/acceptance suite.

Production health after deployment returned `status: ok` with database, migrations, storage, Stripe, Stripe webhook, email and cron healthy. `feedbackGitHub` is optional and is not a launch blocker.

## Approved visual state

The visual state currently accepted in production is part of the V4 baseline and must not be replaced by an older design.

- Desktop uses the V4 split layout: visual panel on the left and application content on the right.
- The approved Atom Global public logo is shown in the **right/content panel**.
- Left-panel headline/supporting copy is pinned to the **bottom-left** on desktop.
- The V4 landing (`version`) stage intentionally has no CMS desktop/mobile media assignment so it resolves to the deployed approved V4 hero asset at `/media/stages/reflection-portrait.png`.
- Inner stages use the approved V4 CMS/content-stage image assignments. Do not introduce obsolete image assets or hard-coded visual fallbacks.
- Mobile keeps the approved responsive V4 treatment and iOS-safe form controls.
- CMS logo and stage-image values are authoritative where configured.

### Critical landing-stage rule

The approved landing state is:

```text
stage_key: version
desktop_media_id: NULL
mobile_media_id: NULL
focal_x: 52.00
focal_y: 50.00
```

This is intentional. Do not assign a different CMS image to `version` unless Amit explicitly approves a new V4 landing image.

## Changes applied in the 2 September V4 burn-in

The following work is included in the approved V4 baseline or its live CMS/database state:

- mobile dropdown/viewport-width hardening;
- full-width mobile report/payment controls;
- iPhone/iOS 16px form-control reflow/focus protection;
- checkout-return recovery when Stripe is cancelled/closed and the page retains a stale `Opening checkout…` state;
- V4 service-worker hostname corrected to `v4.atomglobal.com`;
- correct public logo/CMS asset handling;
- approved right-side public-logo placement restored;
- approved bottom-left visual-panel copy positioning restored;
- obsolete visual fallback behavior removed from the participant/Admin runtime;
- 40-question, 10-section live questionnaire retained;
- registration, consent, autosave, resume/session restoration and database persistence retained;
- Lite Report locking and report security retained;
- Personal-only `For less than a cup of coffee, find out more about yourself.` payment supporting copy;
- stronger halfway/completion presentation;
- server-controlled UAT No-Payment availability retained;
- report/PDF/email/payment backend acceptance retained;
- CMS/Admin modules remain wired to the production API/database.

## Questionnaire and participant journey

V4 exposes four public tracks:

- Personal
- New Joiner
- Manager
- Executive

New participant sessions use 40 live questions across 10 sections. The approved source/version bank and historical snapshots remain protected for reporting/version integrity.

The participant flow covers:

1. track selection;
2. track introduction;
3. registration/intake and consent;
4. secure survey session creation;
5. 40 questions / 10 sections;
6. autosave and resume;
7. completion/scoring;
8. Lite Report;
9. Stripe or authorised UAT route for Full Report;
10. secure report link, PDF and email delivery.

## Admin / CMS coverage

The V4 Admin is wired to the production API/database for:

- Dashboard and insights
- Participants and participant history
- Questionnaire content/experience
- Assessments and protected question corrections
- Content stages and media
- Branding and public/report/email assets
- Reports and PDF actions
- Payments
- Email templates and delivery queue
- Affiliates and attribution
- Analytics/funnel
- SEO/AEO/GEO
- Settings and integrations
- Admin users/permissions
- Audit logs
- Feedback/help

Do not make a CMS visual change and then compensate for it with a hard-coded frontend fallback. V4 should render the approved CMS/database state directly.

## UAT No-Payment control

The client UAT bypass is controlled from:

**Admin → Payments → Client UAT payment bypass**

The Admin writes the system-level `cashOnDeliveryEnabled` setting. The backend checks the same setting before allowing the route.

When enabled, V4 can:

- create a clearly marked manual/UAT payment;
- unlock the Full Report;
- rotate the private report token;
- queue the normal payment-success and paid-report email/PDF flow;
- write an audit record;
- avoid a Stripe charge.

For public launch, the effective UAT No-Payment state must be **disabled**. The approved state on 2 September was explicitly set to false at the system override level.

Do not use a real card during UAT unless Amit explicitly authorises a controlled payment test.

## Database integrity verified on 2 September

The live production review confirmed:

- four assessment tracks;
- four assessment versions;
- completed sessions without report: `0`;
- orphan reports: `0`;
- orphan payments: `0`;
- existing email queue records checked with all observed queue items in `sent` state at the time of audit;
- manual UAT payments and unlocked reports reconciled;
- observed Stripe test/UAT records were `checkout_started`, not successful charged payments.

Do not delete participant/session/payment/report data during normal UAT cleanup without first reviewing relationships and taking a V4 database backup.

## Standard V4 deployment

Use Apache only for this V4 deployment.

```bash
cd /srv/v4.atomglobal.com/source

git fetch origin
git checkout production-readiness-v4-mobile-final-20260902
git reset --hard origin/production-readiness-v4-mobile-final-20260902

sudo BRANCH=production-readiness-v4-mobile-final-20260902 \
  bash deploy/update-v4-apache.sh
```

The deployer must:

- back up the V4 database;
- validate the V4 environment;
- run PHP lint/tests;
- run migrations/seed validation;
- run frontend tests/build;
- build an immutable release;
- atomically switch only the V4 release symlink;
- reload PHP-FPM/Apache;
- verify the V4 cron and health endpoint;
- roll back the V4 release automatically if a post-switch check fails.

### Post-deployment verification

```bash
cat /var/www/v4.atomglobal.com/deployed-commit.txt
curl -fsS https://v4.atomglobal.com/api/health
git status --short
```

Then perform a real browser hard refresh and manually verify desktop/mobile visuals before declaring the deployment accepted.

## Approved V4 backup procedure

Git is only part of the live state. V4 visual settings, UAT state and other operational configuration also live in MariaDB/CMS settings.

Before major changes, create a snapshot under `/var/backups/growth-alignment-v4/` containing at minimum:

- current Git commit and status;
- MariaDB backup of `growth_alignment_v4`;
- `/etc/growth-alignment/v4.env` with root-only protection;
- `content_stages` state;
- `media_library` state;
- effective UAT setting;
- current active release path/commit marker.

The approved Git safety branch is:

```text
v4-live-approved-backup-20260902
```

Do not move or overwrite this backup branch casually. Create a new dated backup branch for a future approved baseline.

## Spencer UAT continuation

Spencer should continue UAT against **only** `https://v4.atomglobal.com/` and record findings under the V4 Sunil folder in `amitaxonsg/Spencer-Project`.

Retest coverage should include:

- desktop landing and all inner pages;
- right-side public logo;
- approved left-panel image and bottom-left copy;
- mobile/iPhone/Android layout and no horizontal overflow;
- registration/intake validation;
- Personal, New Joiner, Manager and Executive starts;
- 40 questions / 10 sections;
- answer isolation, N/A, notes and section navigation;
- autosave/resume persistence;
- Lite Report and locked Full Report;
- Stripe checkout open/cancel/return/retry without a real charge unless authorised;
- controlled UAT No-Payment only when explicitly enabled for testing;
- Full Report, secure token, PDF and email;
- Admin Participants, Questionnaire, Assessments, Content, Branding, Reports, Payments, Email, Settings, Analytics, Affiliates, Audit and SEO;
- CMS edits reflected correctly on the frontend without fallback content reappearing.

Record PASS/FAIL, device/browser, exact page/track, reproduction steps and screenshots for every failure.

## Change-control rule

**Do not mix V4 with any other version or repository.**

Before changing production V4:

1. start from the approved V4 branch/baseline;
2. back up Git + database/CMS state;
3. record the requested change;
4. make the smallest V4-only change;
5. run the complete automated gate;
6. review the diff;
7. deploy with the V4 Apache deployer;
8. verify health and real desktop/mobile visuals;
9. update the V4 UAT record/README when the baseline changes.

Never infer that a similarly named image, CSS rule, migration or historical commit represents the approved V4 visual state. The current live V4 baseline and this README are authoritative.
