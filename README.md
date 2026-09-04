# Atom Global Growth Alignment V4

> **V4 ONLY — CURRENT SOURCE OF TRUTH**
>
> This README describes the approved V4 application at `https://v4.atomglobal.com/`. Do not use V5, V3, another repository, or an older preview as a deployment, database, CMS, scoring, payment, report or visual source of truth for V4 work.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, Stripe payments, UAT no-payment control, PDF/email delivery, analytics, affiliates, commitments and audit history.

## Current V4 baseline — 4 September 2026

| Item | Current V4 value |
|---|---|
| Public URL | `https://v4.atomglobal.com/` |
| Admin URL | `https://v4.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| Working/deployment branch | `production-readiness-v4-mobile-final-20260902` |
| **Server-verified live application commit** | `ec939068e4b3ee36dd7fa79922de8a3faa80921d` |
| Latest Full Report hero feature commit | `ad911f651b51d13ca9a6bd700b970a1185a654a1` |
| Full Report hero deployment status | **Git only / pending final gate and deployment** |
| Current pre-hero Git safety branch | `v4-pre-full-report-meter-20260904-ec93906` |
| Earlier live/payment safety branch | `v4-live-backup-20260904-9e99467` |
| Confirmed older full server backup | `/var/backups/growth-alignment-v4/prechange-20260903-042350` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release symlink | `/var/www/v4.atomglobal.com/current` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

The live `ec939068...` release was deployed successfully through the V4 Apache deployer. Production health returned `status: ok` with database, migrations, storage, Stripe, Stripe webhook configuration, email and cron healthy. `feedbackGitHub:false` remains optional and is not a launch blocker.

The live release passed **80/80 frontend tests** before deployment. The newer Full Report web/PDF hero parity change adds another dedicated regression test and must pass the complete gate before deployment.

> The authoritative live code is the target of `/var/www/v4.atomglobal.com/current`. `/var/www/v4.atomglobal.com/deployed-commit.txt` should match it. The deploy rollback now restores both the live symlink and the previous commit marker if a post-switch health check fails.

## Approved Sept 4 payment reliability state

The real Pay-by-Card flow has been tested with live Stripe transactions.

Approved behavior:

1. Stripe Checkout receives the payment.
2. The signed Stripe webhook remains the primary fulfilment path.
3. If the webhook is delayed or missed, V4 securely retrieves that exact Checkout Session directly from Stripe.
4. V4 only marks the payment paid when Stripe reports `payment_status = paid` and the Checkout metadata matches the recorded assessment session and `payment_purpose = full_report`.
5. Payment details are stored in `payments`, including amount, currency, Payment Intent and paid timestamp.
6. The generated Full Report is unlocked.
7. A fresh secure report token/URL is stored.
8. Customer payment-confirmation and `paid_report_ready` emails are queued exactly once.
9. The PDF is generated and attached to the Full Report email.
10. An administrator `payment_paid` notification event is recorded.
11. The scheduled background process also reconciles recent `checkout_started` Full Report payments, so fulfilment does not depend on the customer keeping the success page open.

The customer success screen now shows a processing state with **“Please don’t close this page”**, percentage progress, and automatically opens the Full Report when fulfilment reaches 100%.

### Live payment burn-in evidence

Payment ID `44` was recovered from `checkout_started` to `paid` by verified Stripe reconciliation. The Full Report was unlocked, the PDF was generated, the PDF email was sent with a provider message ID, and the administrator notification was recorded.

A second fresh real-card test, Payment ID `45`, also reached `paid`, stored the Payment Intent, unlocked the Full Report, created a secure report URL, queued the PDF Full Report email and recorded the administrator notification.

A separate operational issue remains: the tested Checkout Sessions did not appear in `stripe_webhook_events`, which indicates the Stripe Dashboard webhook endpoint/delivery should still be reviewed. V4 is protected by the direct Stripe reconciliation and scheduled fallback, but the webhook configuration should still be corrected.

## Approved Full Report visual state

### Overall result hero — website and PDF parity

The approved V4 Full Report direction uses one readable premium result card for both website and generated PDF.

Required layout:

- overall score uses the same **0–250** result value on web and PDF;
- `150` / `Out of 250` style score presentation is inside a dedicated centered score panel;
- the Head-led ↔ Heart-led meter sits **directly below the score inside the same score panel**;
- the meter labels show `Head-led`, the current `x/250`, and `Heart-led`;
- the score/meter panel is centered and visually contained inside the result card;
- `Your alignment pattern` and the explanatory narrative remain beside the score panel on desktop;
- mobile stacks cleanly without moving the meter outside the score panel;
- text contrast must remain clearly readable;
- Personal and Professional Full Reports share the same structural rule;
- Lite Report styling is not changed by the Full Report hero override.

Feature/parity commits:

- `c4edad01bcaf50ee07ca58a4367ba62223af7877` — add V4 Full Report result hero styling
- `46002f79f5c61897f91a974bd31e2c446e051197` — load the V4 Full Report hero stylesheet
- `a76e6298348b2982d1db7bd1a7b9e9e05ffa368d` — align PDF overall result hero with website structure
- `ad911f651b51d13ca9a6bd700b970a1185a654a1` — guard Full Report website/PDF hero parity

This feature is currently **committed to Git but not yet confirmed deployed**. Run the full gate before deploying.

### 10-area score breakdown

The radar visual is not part of approved V4.

Approved semantics:

- 10 areas, bars only, no radar;
- each area score is 5–25;
- `5 = more Head-led`;
- `15 = balanced`;
- `25 = more Heart-led`;
- bar normalization is `(value - 5) / 20`;
- browser and PDF use the same score meaning;
- no A–J markers.

The Executive Summary Highest 3 / Lowest 3 also uses visible proportional bars.

## Development commitment behavior

The Full Report commitment box is persistent server-side functionality.

When the participant selects **Save my commitment**:

- the text is stored in `report_commitments`;
- it is linked to the specific `generated_report_id`;
- the 90-day/check-in date is stored with it;
- reopening the same private Full Report retrieves the saved commitment;
- PDF generation reads the same commitment table and includes the saved commitment/check-in date when the PDF is generated or regenerated after the save.

The commitment is not merely browser/local state.

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
10. payment verification/reconciliation;
11. unlocked private Full Report;
12. PDF/email delivery;
13. optional 90-day development commitment and later retest.

## Admin / CMS wiring

V4 Admin is connected to the production API/database for Dashboard, Participants/history, Questionnaire experience/content, Assessments, Content stages/media, Branding, Reports/PDF, Payments/UAT, Email, Affiliates, Analytics, SEO/AEO/GEO, Settings/integrations, Admin users/permissions, Audit logs and Feedback/help.

CMS/database state is authoritative.

Admin uses `system.cash_on_delivery_enabled` as the authoritative UAT no-payment override. When disabled, `UAT Test — No Payment` must be absent. When explicitly enabled for controlled testing, the UAT no-payment path may appear.

## Approved visual/CMS state

The approved V4 presentation includes:

- desktop split layout: visual panel left, application content right;
- Atom Global public logo in the right/content panel;
- left visual headline/support copy positioned bottom-left on desktop;
- landing stage `version` intentionally uses `/media/stages/reflection-portrait.png` when no CMS media assignment exists;
- inner-stage CMS/content images remain authoritative;
- mobile responsive presentation and iOS-safe controls;
- Personal value banner above checkout;
- Executive Summary Highest 3 / Lowest 3 with visible score bars;
- 10-area score breakdown uses progress bars only — no radar;
- browser and generated PDF use the same score interpretation and Full Report overall-result hierarchy.

Critical landing-stage state:

```text
stage_key: version
desktop_media_id: NULL
mobile_media_id: NULL
focal_x: 52.00
focal_y: 50.00
```

Do not introduce obsolete hard-coded image fallbacks or compensate for valid CMS state with unrelated frontend overrides.

## Standard V4 pre-deployment gate

**Apache only.**

```bash
cd /srv/v4.atomglobal.com/source

git fetch origin
git checkout production-readiness-v4-mobile-final-20260902
git reset --hard origin/production-readiness-v4-mobile-final-20260902

git rev-parse HEAD
npm test
npm run build

php -l backend/src/Services/PdfService.php
php -l backend/src/Payments/StripeCheckoutReconciler.php
php -l backend/src/route-bundle.php
php -l backend/bin/reconcile-stripe-checkouts.php
php -l backend/bin/cron.php

php backend/bin/production-report-flow-smoke-test.php
```

For the Full Report hero parity candidate, the expected frontend suite is **81 tests** after the new parity regression test is included. Do not deploy if any test, build, PHP lint or required smoke test fails.

## Standard V4 deployment

```bash
cd /srv/v4.atomglobal.com/source

sudo BRANCH=production-readiness-v4-mobile-final-20260902 \
  bash deploy/update-v4-apache.sh
```

The deployer automatically creates a database dump before switching releases and runs the background processor plus health checks after the switch.

### Post-deployment verification

```bash
echo "ACTUAL LIVE RELEASE:"
readlink -f /var/www/v4.atomglobal.com/current

echo
echo "LIVE COMMIT MARKER:"
cat /var/www/v4.atomglobal.com/deployed-commit.txt

echo
echo "SOURCE:"
git rev-parse HEAD

echo
echo "HEALTH:"
curl -fsS https://v4.atomglobal.com/api/health
```

The active release path, deployed commit marker and source commit must agree after a successful deployment.

## Approved V4 backup procedure

Before a meaningful production change:

1. create a Git safety branch from the current accepted/live V4 commit;
2. preserve the V4 database/CMS state with the deployment backup or an explicit validated dump;
3. confirm the backup path before changing production;
4. keep V4 backups under `/var/backups/growth-alignment-v4`;
5. never treat an empty backup directory as a valid backup;
6. verify compressed database backups with `gzip -t` when created manually;
7. do not use a V5/V3 backup as a V4 rollback source.

Current pre-Full-Report-hero Git safety branch:

```text
v4-pre-full-report-meter-20260904-ec93906
```

Earlier live/payment safety branch:

```text
v4-live-backup-20260904-9e99467
```

Confirmed older full server backup:

```text
/var/backups/growth-alignment-v4/prechange-20260903-042350
```

That confirmed backup contains the database dump and V4 environment snapshot and previously passed `gzip -t` validation. Newer automatic deployment dumps may exist in `/var/backups/growth-alignment-v4`; do not cite a newer dump as confirmed until its exact path is captured and validated.

Git alone does not contain all live CMS/database configuration.

## UAT focus

Retest at minimum:

- Personal/New Joiner/Manager/Executive starts;
- 40 questions / 10 sections;
- halfway and completion milestones;
- rapid mobile autosave and stale-error recovery;
- resume persistence;
- Lite/Full Report lock;
- Pay by Card flow;
- payment success progress screen;
- automatic opening of the unlocked Full Report;
- missed-webhook reconciliation fallback;
- secure Full Report token creation;
- PDF generation and email attachment delivery;
- administrator payment notification;
- UAT no-payment enabled/disabled behavior;
- Full Report overall score and meter remain inside the same score card;
- Full Report score/meter is centered and readable;
- Full Report website/PDF overall-result hierarchy matches;
- Executive Summary six bars remain visible and proportional;
- all 10 score-breakdown bars are present and no radar is shown;
- all `x/25` values match their bar positions;
- 5 / 15 / 25 labels are readable;
- commitment save persists after reload;
- regenerated PDF includes the saved commitment;
- mobile Full Report stacks without overflow;
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
7. verify the actual active release, deployed commit marker and `/api/health`;
8. perform browser/mobile/PDF UAT;
9. update this README when the accepted baseline changes.

The V4 branch, production runtime, database/CMS state and this README are the authoritative operational references.
