# Atom Global Growth Alignment V4 / GAA

> **CURRENT SOURCE OF TRUTH — V4 CODEBASE, GAA LIVE HOST**
>
> This repository is the approved Atom Global Growth Alignment V4 application. `gaa.atomglobal.com` is the active production hostname. `v4.atomglobal.com` remains part of the same V4 codebase and must not be treated as V5 or as a separate application.
>
> Do not use V3, V5, another repository, an old preview, or an older release as a deployment, database, CMS, scoring, payment, report, visual, or rollback source for this application.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, Stripe payments, UAT no-payment control, PDF/email delivery, analytics, affiliates, commitments, retakes, sharing and audit history.

## Current production baseline — 14 September 2026

| Item | Current value |
|---|---|
| Active production URL | `https://gaa.atomglobal.com/` |
| Admin URL | `https://gaa.atomglobal.com/admin` |
| V4 compatibility URL | `https://v4.atomglobal.com/` |
| Repository | `amitaxonsg/atomv4` |
| Live deployment branch | `gaa-live-recovery` |
| V4 sync branch | `production-readiness-v4-mobile-final-20260902` |
| **Server-verified deployed application commit** | `a2b8c735da1a5385e0751096dcb9b832db388991` |
| Live application state | **DEPLOYED / LIVE / HEALTHY** |
| Current JavaScript gate | **87 tests / 87 pass / 0 fail** |
| Environment file | `/etc/growth-alignment/v4.env` |
| `APP_URL` | `https://gaa.atomglobal.com` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release symlink | `/var/www/v4.atomglobal.com/current` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

The 14 September deployment completed successfully with 87/87 JavaScript tests, Vite production build success, Apache syntax OK, Stripe reconciliation with 0 failures, administrator alert processing with 0 failures, email queue processing with 0 failures, healthy five-minute background processing, and `/api/health` returning `status: ok`.

Latest confirmed health state after deployment:

```json
{
  "status": "ok",
  "checks": {
    "database": true,
    "migrations": true,
    "storage": true,
    "stripe": true,
    "stripeWebhook": true,
    "email": true,
    "feedbackGitHub": false,
    "cron": true
  },
  "environment": "production"
}
```

`feedbackGitHub=false` is optional and did not block the production health check.

> Documentation-only commits may be newer than the deployed application. `/var/www/v4.atomglobal.com/current` and `/var/www/v4.atomglobal.com/deployed-commit.txt` remain authoritative for the actual runtime release.

## Full Development Report PDF — accepted 14 September 2026

The Full Development Report PDF now includes a dedicated confidential cover page before the existing report content.

### Accepted cover behavior

Page 1 is the cover and contains:

- Atom Global Consulting logo;
- title `Growth Alignment Report`;
- participant **Full Name**;
- **Assessment Date** derived from the real assessment completion timestamp;
- **Assessment Time** calculated as elapsed completion duration from survey-session creation to completion;
- `Confidential Report`;
- `UNLEASHING HUMAN POTENTIAL`;
- `Atom Global Consulting Pte. Ltd.`;
- `Level 49, 1 Raffles Quay`;
- `Singapore 048583`.

The participant details are centered. The cover starts on page 1 with no leading blank page. The existing Full Development Report begins on page 2.

The accepted regenerated UAT PDF is **6 pages total**: one cover page plus the existing five-page report body.

### PDF implementation rules

- Cover applies to the unlocked Full Development Report PDF only.
- Lite Report behavior is unchanged.
- Participant name comes from the participant record.
- Assessment date comes from `survey_sessions.completed_at`.
- Assessment duration is calculated from `survey_sessions.created_at` to `survey_sessions.completed_at`.
- Existing report scoring, questions, Stripe flow, unlock logic, commitments, email delivery and report content remain unchanged.
- Existing report sections preserve their established order and visual hierarchy.

### Current PDF source

Primary implementation:

```text
backend/src/Services/PdfService.php
```

Regression coverage:

```text
tests/js/pdf-cover.test.mjs
```

## Accepted Full Report website / PDF parity

The website Full Report remains the visual reference for the report body.

Approved report-body rules:

- same participant/profile title hierarchy;
- same `x / OUT OF 250` overall-score semantics;
- centered overall score inside the result box;
- `YOUR ALIGNMENT PATTERN` beside the score;
- Head-led / current `x/250` / Heart-led meter below the narrative;
- Top three strengths and Development observations as paired cards;
- dark `Your full development report` banner;
- Executive Summary with Highest 3 / Lowest 3 and proportional bars;
- 10-area breakdown uses bars only — no radar;
- commitment section remains dark with high-contrast text;
- retake, coach and `Use this report to` sections preserve the website hierarchy;
- A4 pagination remains Dompdf-safe and does not include interactive website buttons.

Do not reintroduce the old radar visual.

## Lite / Full overall-result UI

Lite and Full website reports share the approved result-card structure:

- dark premium result card;
- centered overall score with `OUT OF 250`;
- readable white/gold contrast;
- alignment narrative beside the score on desktop;
- Head-led / current `x/250` / Heart-led meter below the narrative;
- mobile stacks cleanly;
- Lite keeps an explicit dark fallback so white text never becomes unreadable.

## Highlight-only sharing and privacy

Sharing must remain Lite-safe.

Allowed public highlight data:

- track/result title;
- profile;
- overall score;
- alignment summary;
- top three strengths;
- development observations.

Never expose through public sharing:

- `paid_report_json`;
- paid Full Report content;
- PDF path/link;
- commitment;
- roadmap;
- methodology;
- written reflections;
- participant email;
- participant name;
- private report token;
- private `/report/<token>` URL;
- private `/api/reports/<token>` URL.

The Share Highlights modal supports Facebook, X, WhatsApp and LinkedIn. Public Lite sharing must remain separate from the private Full Report token flow.

## Payment reliability state

The signed Stripe webhook remains the primary fulfilment path. Direct Stripe reconciliation remains the fallback when webhook delivery is delayed or missed.

Approved payment flow:

1. Stripe Checkout receives payment.
2. The application verifies the exact Checkout Session and `payment_status = paid`.
3. Checkout metadata must match the assessment session and `payment_purpose = full_report`.
4. Payment is stored with amount, currency, Payment Intent and paid timestamp.
5. Full Report unlocks.
6. Secure private report token/URL is stored.
7. Customer payment confirmation and `paid_report_ready` emails are queued idempotently.
8. Full Report PDF is generated and attached to email.
9. Administrator `payment_paid` notification is recorded.
10. Scheduled reconciliation protects the flow if webhook delivery is missed.

## Current participant journey

Tracks:

- Personal
- New Joiner
- Manager
- Executive

Questionnaire: **40 questions across 10 sections**.

Journey:

1. track selection;
2. introduction;
3. participant details and consent;
4. secure survey session creation;
5. assessment;
6. autosave/resume;
7. completion/scoring;
8. Lite Report;
9. optional Lite-safe sharing;
10. Stripe checkout or explicitly enabled UAT no-payment route;
11. payment verification/reconciliation;
12. unlocked private Full Report;
13. PDF/email delivery;
14. optional development commitment and later retest.

## Admin / CMS wiring

Admin remains connected to the production API/database for Dashboard, Participants/history, Questionnaire, Assessments, Content/media, Branding, Reports/PDF, Payments/UAT, Email, Affiliates, Analytics, SEO/AEO/GEO, Settings/integrations, Admin users/permissions, Audit logs and Feedback/help.

CMS/database state is authoritative. Git alone does not contain all live CMS/database configuration.

## Standard production pre-deployment gate

Use the V4 source checkout and the active GAA production branch.

```bash
cd /srv/v4.atomglobal.com/source

git fetch origin
git checkout gaa-live-recovery
git reset --hard origin/gaa-live-recovery

git rev-parse HEAD
npm test
npm run build

php -l backend/public/index.php
php -l backend/src/Services/ReportService.php
php -l backend/src/Services/PdfService.php
php -l backend/src/Payments/StripeCheckoutReconciler.php
php -l backend/src/route-bundle.php
php -l backend/bin/reconcile-stripe-checkouts.php
php -l backend/bin/cron.php
```

For report/PDF backend changes:

```bash
php backend/bin/production-report-flow-smoke-test.php \
  --confirm=RUN-PRODUCTION-REPORT-SMOKE \
  --recipient=unused-v4-smoke@example.com
```

Do not add `--send-email` unless intentionally testing live UAT email delivery.

Current JavaScript gate:

```text
tests 87
pass 87
fail 0
```

## Standard production deployment

Always pass the branch and domain explicitly. Do not rely on the wrapper script defaults.

```bash
cd /srv/v4.atomglobal.com/source

BRANCH=gaa-live-recovery \
DOMAIN=gaa.atomglobal.com \
./deploy/update-v4-apache.sh
```

The deployer creates a database dump before switching releases and runs background processing plus health checks after the switch.

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
echo "GAA HEALTH:"
curl -fsS https://gaa.atomglobal.com/api/health
```

Current server-verified deployed application commit:

```text
a2b8c735da1a5385e0751096dcb9b832db388991
```

## Backup and rollback rule

Before every meaningful production change:

1. create a Git safety branch from the current branch head;
2. preserve the production database/CMS state with a validated dump or deployment backup;
3. preserve `/etc/growth-alignment/v4.env`;
4. preserve the active-release path;
5. preserve the deployed application commit marker;
6. preserve the current source commit marker;
7. preserve `/var/lib/growth-alignment-v4`;
8. keep backups under `/var/backups/growth-alignment-v4`;
9. verify database archives and storage archives before trusting them;
10. never use V3 or V5 as a V4/GAA rollback source.

Relevant September 14 Git safety branches include:

```text
backup/v4-before-full-report-cover-20260914
backup/gaa-live-recovery-before-pdf-cover-20260914
backup/gaa-before-cover-layout-fix-20260914
backup/gaa-before-final-cover-fix-20260914
backup/gaa-before-readme-sync-20260914
backup/v4-before-gaa-sync-20260914
```

## Minimum UAT checklist

Retest at minimum:

- all four assessment tracks;
- 40 questions / 10 sections;
- autosave/resume;
- Lite/Full Report lock;
- Lite result card remains dark/readable;
- overall score and Head/Heart meter remain correct;
- Full Report website/PDF hierarchy matches;
- Full Report PDF cover appears on page 1 with no leading blank page;
- Full Name, Assessment Date and Assessment Time are centered on the cover;
- assessment duration reflects elapsed completion time;
- PDF report body begins on page 2;
- Executive Summary bars are visible;
- all 10 area bars are visible and proportional;
- commitment panel text remains readable;
- saved commitment persists after reload;
- regenerated PDF includes saved commitment;
- Lite-safe share behavior remains private-data safe;
- Pay by Card flow and reconciliation fallback;
- secure private Full Report token;
- PDF/email delivery;
- administrator payment notification;
- mobile report layout;
- CMS image/logo/content edits.

## Change-control rule

**Do not mix V4/GAA with another version or repository.**

Before any production change:

1. start from `gaa-live-recovery`;
2. create a safety branch;
3. create and validate the production backup;
4. make the smallest necessary change;
5. run the complete automated gate;
6. review the diff;
7. deploy with the V4 Apache deployer using explicit `BRANCH` and `DOMAIN`;
8. verify active release, deployed commit marker and `/api/health`;
9. perform browser/mobile/PDF UAT as relevant;
10. update this README when the accepted baseline changes.

The `gaa-live-recovery` branch, the synchronized V4 branch, production runtime, database/CMS state and this README are the authoritative operational references.
