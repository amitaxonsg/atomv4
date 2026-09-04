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
| **Server-verified live application commit** | `7940582a08ca0c5aafb3cc5f0c1b052ff34f630b` |
| Lite/Full overall-result parity status | **DEPLOYED / LIVE / HEALTHY** |
| Full Report web/PDF parity baseline | `53dafea465f4f4d7d87bda97584c3646e1c1fdab` |
| Lite/Full result-card parity commit | `7940582a08ca0c5aafb3cc5f0c1b052ff34f630b` |
| Pre-Lite-parity Git safety branch | `v4-pre-lite-hero-parity-20260904-0608dbc` |
| Pre-Full-Report-hero Git safety branch | `v4-pre-full-report-meter-20260904-ec93906` |
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

The `7940582...` release was deployed successfully through the V4 Apache deployer. The deploy output confirmed:

- **81/81 frontend tests passed**;
- Vite production build passed;
- PHP syntax check passed;
- Apache release switch completed successfully;
- Stripe reconciliation background job ran with `0` failures;
- administrator alert and email processors completed without failure;
- background processing remains scheduled every five minutes;
- production health returned `status: ok` with database, migrations, storage, Stripe, Stripe webhook configuration, email and cron healthy;
- `feedbackGitHub:false` remains optional and is not a launch blocker.

> Documentation-only commits may be newer than the deployed application. The authoritative live application is the target of `/var/www/v4.atomglobal.com/current`; `/var/www/v4.atomglobal.com/deployed-commit.txt` should match the application commit encoded in that active release.

## Approved Lite and Full Report overall-result UI

The Lite Report and Full Report website now share the same approved overall-result card structure. The generated Full Report PDF keeps the same Full Report score/meter hierarchy.

Required and deployed website behavior:

- the overall score is shown clearly as `x` with **Out of 250**;
- the score sits inside a dedicated result/score panel;
- the Head-led ↔ Heart-led meter sits **directly below the score inside the same panel**;
- the meter is centered horizontally;
- the labels show `Head-led`, the current `x/250`, and `Heart-led`;
- the result badge/title and supporting alignment narrative remain clearly readable;
- desktop keeps the score/meter panel beside `Your alignment pattern` and its supporting text;
- mobile stacks the score, meter, title and supporting text without moving the meter outside the score panel;
- Personal and Professional tracks use the same structural rule;
- the Lite Report and Full Report use the same overall visual language so the transition after payment feels consistent.

Implementation history:

- `c4edad01bcaf50ee07ca58a4367ba62223af7877` — add V4 Full Report result hero styling
- `46002f79f5c61897f91a974bd31e2c446e051197` — load V4 result hero stylesheet
- `a76e6298348b2982d1db7bd1a7b9e9e05ffa368d` — align Full Report PDF overall-result hero with website structure
- `ad911f651b51d13ca9a6bd700b970a1185a654a1` — guard Full Report website/PDF hero parity
- `53dafea465f4f4d7d87bda97584c3646e1c1fdab` — accepted/deployed Full Report web/PDF parity release
- `1792955d411182db39f36f577f5d6baf7eb66d69` — apply the same approved result-card structure to Lite and Full website reports
- `7940582a08ca0c5aafb3cc5f0c1b052ff34f630b` — guard Lite/Full result-card meter parity and accepted live release

## Approved Full Report website/PDF parity

The generated Full Report PDF uses the same overall score semantics and hierarchy as the Full Report website:

- same 0–250 overall result;
- clear `x / 250` presentation;
- meter directly below the score;
- Head-led / current score / Heart-led labels;
- `Your alignment pattern` supporting narrative;
- readable contrast and balanced visual hierarchy.

Interactive website controls are naturally not part of the PDF, and pagination may differ, but the report content, score meaning and overall result hierarchy should remain aligned.

## Approved 10-area score breakdown

The radar visual is not part of approved V4.

Approved semantics:

- 10 areas, bars only, no radar;
- each area score is 5–25;
- `5 = more Head-led`;
- `15 = balanced`;
- `25 = more Heart-led`;
- bar normalization is `(value - 5) / 20`;
- browser and PDF use the same score meaning;
- no A–J markers;
- Executive Summary Highest 3 / Lowest 3 also uses visible proportional bars.

Do not reintroduce the radar.

## Approved Sept 4 payment reliability state

The real Pay-by-Card flow has been tested with live Stripe transactions.

Approved behavior:

1. Stripe Checkout receives the payment.
2. The signed Stripe webhook remains the primary fulfilment path.
3. If the webhook is delayed or missed, V4 securely retrieves that exact Checkout Session directly from Stripe.
4. V4 only marks the payment paid when Stripe reports `payment_status = paid` and Checkout metadata matches the recorded assessment session and `payment_purpose = full_report`.
5. Payment details are stored in `payments`, including amount, currency, Payment Intent and paid timestamp.
6. The generated Full Report is unlocked.
7. A fresh secure report token/URL is stored.
8. Customer payment-confirmation and `paid_report_ready` emails are queued exactly once.
9. The PDF is generated and attached to the Full Report email.
10. An administrator `payment_paid` notification event is recorded.
11. Scheduled background reconciliation protects customers if the success page is closed or the webhook is delayed/missed.

The customer success screen shows **Please don’t close this page**, percentage progress, and automatically opens the Full Report when fulfilment reaches 100%.

### Live payment burn-in evidence

Payment ID `44` was recovered from `checkout_started` to `paid` by verified Stripe reconciliation. The Full Report was unlocked, the PDF was generated, the PDF email was sent with a provider message ID, and the administrator notification was recorded.

Payment ID `45` also reached `paid`, stored the Payment Intent, unlocked the Full Report, created a secure report URL, queued the PDF Full Report email and recorded the administrator notification.

A separate operational issue remains: the tested Checkout Sessions did not appear in `stripe_webhook_events`, so the Stripe Dashboard endpoint/delivery should still be reviewed. The direct Stripe reconciliation and scheduled fallback protect the customer flow while that operational issue is investigated.

## Development commitment behavior

The Full Report commitment box is persistent server-side functionality.

When the participant selects **Save my commitment**:

- the text is stored in `report_commitments`;
- it is linked to the specific `generated_report_id`;
- the check-in date is stored with it;
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
13. optional development commitment and later retest.

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
- Lite and Full Reports share the same overall result-card structure;
- Full Report website and PDF use the same overall score/meter hierarchy;
- Executive Summary Highest 3 / Lowest 3 has visible score bars;
- 10-area score breakdown uses progress bars only — no radar.

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
```

For report/PDF backend changes, run the guarded production report smoke test with a temporary recipient address that does not already exist in `participants`:

```bash
php backend/bin/production-report-flow-smoke-test.php \
  --confirm=RUN-PRODUCTION-REPORT-SMOKE \
  --recipient=unused-v4-smoke@example.com
```

Do not add `--send-email` unless the test is intentionally meant to exercise live UAT email delivery.

The current Lite/Full website result-card parity release passed **81 tests** and the Vite production build before deployment. The earlier Full Report PDF parity release also passed the guarded production report smoke test.

## Standard V4 deployment

```bash
cd /srv/v4.atomglobal.com/source

sudo BRANCH=production-readiness-v4-mobile-final-20260902 \
  bash deploy/update-v4-apache.sh
```

The deployer automatically creates a database dump before switching releases and runs background processing plus health checks after the switch.

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

The active release path, deployed commit marker and source application commit must agree after a successful deployment.

Current server-verified application commit:

```text
7940582a08ca0c5aafb3cc5f0c1b052ff34f630b
```

## Approved V4 backup procedure

Before a meaningful production change:

1. create a Git safety branch from the current accepted/live V4 commit;
2. preserve the V4 database/CMS state with the deployment backup or an explicit validated dump;
3. confirm the backup path before changing production;
4. keep V4 backups under `/var/backups/growth-alignment-v4`;
5. never treat an empty backup directory as a valid backup;
6. verify compressed database backups with `gzip -t` when created manually;
7. do not use a V5/V3 backup as a V4 rollback source.

Pre-Lite-parity Git safety branch:

```text
v4-pre-lite-hero-parity-20260904-0608dbc
```

Pre-Full-Report-hero Git safety branch:

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
- Lite Report score and meter remain inside the same centered score card;
- Full Report score and meter remain inside the same centered score card;
- Lite and Full overall result-card structure matches;
- Full Report website/PDF overall-result hierarchy matches;
- Pay by Card flow;
- payment success progress screen;
- automatic opening of the unlocked Full Report;
- missed-webhook reconciliation fallback;
- secure Full Report token creation;
- PDF generation and email attachment delivery;
- administrator payment notification;
- UAT no-payment enabled/disabled behavior;
- Executive Summary six bars remain visible and proportional;
- all 10 score-breakdown bars are present and no radar is shown;
- all `x/25` values match their bar positions;
- 5 / 15 / 25 labels are readable;
- commitment save persists after reload;
- regenerated PDF includes the saved commitment;
- mobile reports stack without overflow;
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
