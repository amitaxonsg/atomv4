# Atom Global Growth Alignment V4

> **V4 ONLY — CURRENT SOURCE OF TRUTH**
>
> This README describes the approved V4 application at `https://v4.atomglobal.com/`. Do not use V5, V3, another repository, or an older preview as a deployment, database, CMS, scoring, payment, report, or visual source of truth for V4 work.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, Stripe payments, UAT no-payment control, PDF/email delivery, analytics, affiliates, commitments and audit history.

## Current V4 baseline — 4 September 2026

| Item | Current V4 value |
|---|---|
| Public URL | `https://v4.atomglobal.com/` |
| Admin URL | `https://v4.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| Working/deployment branch | `production-readiness-v4-mobile-final-20260902` |
| **Server-verified live application commit** | `7e4d89ec30fa13f1b14c2bea938189c89482d7da` |
| Lite/Full reference result-card status | **DEPLOYED / LIVE / HEALTHY** |
| Reference result-card / PDF parity baseline | `6559a26bf78ef40f36b94a8d27ff3d09830948ee` |
| Lite contrast/readability baseline | `f05f2adaae488d3c947f49acfae96e6dce12e1d5` |
| Commitment contrast/readability fix | `7e4d89ec30fa13f1b14c2bea938189c89482d7da` |
| Pre-commitment-contrast safety branch | `v4-pre-commitment-contrast-20260904` |
| Pre-Lite-contrast safety branch | `v4-pre-lite-contrast-fix-20260904-6559a26` |
| Pre-reference-result-card safety branch | `v4-pre-reference-result-card-20260904` |
| Earlier live/payment safety branch | `v4-live-backup-20260904-9e99467` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release symlink | `/var/www/v4.atomglobal.com/current` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

The `7e4d89ec...` release was deployed successfully through the V4 Apache deployer. The deployment output confirmed:

- **81/81 frontend tests passed**;
- Vite production build passed;
- PHP syntax check passed;
- Apache release switch completed successfully;
- Stripe reconciliation background job ran with `0` failures;
- administrator alert processor completed without failure;
- email queue processor completed without failure;
- background processing is scheduled every five minutes and verified healthy;
- production `/api/health` returned `status: ok` with database, migrations, storage, Stripe, Stripe webhook configuration, email and cron healthy;
- `feedbackGitHub:false` remains optional and is not a launch blocker.

> Documentation-only commits may be newer than the deployed application. The authoritative live application is the target of `/var/www/v4.atomglobal.com/current`; `/var/www/v4.atomglobal.com/deployed-commit.txt` should match the application commit encoded in that active release.

## Approved Lite, Full and PDF overall-result UI

The Lite Report and Full Report website use the same approved reference-card composition. The generated Full Report PDF mirrors the same overall result hierarchy.

Required deployed layout:

- overall result shown clearly as `x` with **OUT OF 250**;
- score and `OUT OF 250` centered horizontally and vertically inside the dedicated left score box;
- `YOUR ALIGNMENT PATTERN` in the right narrative area;
- supporting alignment text directly below the title;
- Head-led ↔ Heart-led meter below the supporting text in the same narrative area;
- meter labels show `Head-led`, current `x/250`, and `Heart-led`;
- dark premium background with explicit readable white/gold contrast;
- Lite has a hard dark fallback so white text cannot become unreadable if advanced gradient styling is unavailable or overridden;
- mobile stacks cleanly without losing contrast or hierarchy;
- Personal and Professional tracks share the same structural rule.

Key result-card commits:

- `a01e6dafafd68f03cdc274fbfb14b52eea9161fe` — match Lite/Full website result card to approved reference
- `6c38ed57752f264f342989414ca72a1a34975a2d` — guard reference-card composition
- `6559a26bf78ef40f36b94a8d27ff3d09830948ee` — match generated Full Report PDF to approved reference
- `251d151a842f2df4b3a10b9e45282c8cedda98ed` — explicit Lite dark fallback/high-contrast styling
- `f05f2adaae488d3c947f49acfae96e6dce12e1d5` — guard Lite contrast/readability

## Commitment section — approved contrast behavior

The Full Report development commitment section uses a dark panel. All text in that panel must remain readable regardless of other report heading styles.

Approved browser behavior:

- `MAKE IT ACTIONABLE` is white/high contrast;
- `My 90-day development commitment` is white/high contrast;
- instruction/body text is white/high contrast;
- saved/check-in status text is white/high contrast;
- saved commitment text is white/high contrast;
- textarea remains white with dark readable input text;
- placeholder remains clearly readable;
- button, layout, persistence, wording and business logic are unchanged.

The commitment contrast guard is intentionally loaded after the other report styles so older heading rules cannot override it.

Approved PDF behavior:

- the commitment block remains dark;
- commitment heading and paragraph text remain explicitly white/high contrast;
- saved commitment and suggested check-in continue to render in the generated Full Report PDF.

Implementation:

- `d9d2b769d9305d940ab289e91f412d18f221c60e` — add V4 commitment contrast stylesheet
- `0ddcd74e854ce2f858ee96e0ac9ba150622010a2` — load commitment contrast stylesheet last
- `7e4d89ec30fa13f1b14c2bea938189c89482d7da` — guard web/PDF commitment contrast; accepted live release

## Development commitment persistence

When a participant selects **Save my commitment**:

- commitment text is stored in `report_commitments`;
- it is linked to the specific `generated_report_id`;
- the check-in date is stored with it;
- reopening the same private Full Report retrieves the saved commitment;
- PDF generation reads the same commitment table and includes the saved commitment/check-in date when generated or regenerated after the save.

The commitment is server-side data, not merely browser/local state.

## Approved 10-area score breakdown

The radar visual is not part of approved V4.

Approved semantics:

- 10 areas, bars only, no radar;
- each area score is 5–25;
- `5 = more Head-led`;
- `15 = balanced`;
- `25 = more Heart-led`;
- normalization is `(value - 5) / 20`;
- browser and PDF use the same meaning;
- no A–J markers;
- Executive Summary Highest 3 / Lowest 3 also uses visible proportional bars.

Do not reintroduce the radar.

## Approved Sept 4 payment reliability state

The real Pay-by-Card flow has been tested with live Stripe transactions.

Approved behavior:

1. Stripe Checkout receives payment.
2. Signed Stripe webhook remains the primary fulfilment path.
3. If webhook delivery is delayed/missed, V4 retrieves the exact Checkout Session directly from Stripe.
4. V4 marks paid only when Stripe reports `payment_status = paid` and metadata matches the assessment session with `payment_purpose = full_report`.
5. Payment details are stored, including amount, currency, Payment Intent and paid timestamp.
6. Full Report is unlocked.
7. Secure report token/URL is stored.
8. Customer confirmation and `paid_report_ready` emails are queued idempotently.
9. PDF is generated and attached to the Full Report email.
10. Administrator `payment_paid` notification is recorded.
11. Scheduled reconciliation protects customers if the success page is closed or webhook delivery is missed.

The payment success screen shows **Please don’t close this page**, percentage progress, and automatically opens the Full Report when fulfilment reaches 100%.

### Live burn-in evidence

Payment ID `44` was recovered from `checkout_started` to `paid` by verified Stripe reconciliation; report unlock, PDF generation, PDF email with provider message ID, and administrator notification were verified.

Payment ID `45` also reached `paid`, stored its Payment Intent, unlocked the Full Report, created a secure report URL, queued the PDF Full Report email and recorded the administrator notification.

The tested Checkout Sessions did not appear in `stripe_webhook_events`, so Stripe Dashboard endpoint/delivery still warrants operational review. The direct Stripe reconciliation and scheduled fallback protect the customer flow meanwhile.

## Current participant journey

V4 exposes four tracks:

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

- desktop split layout: visual panel left, application content right;
- Atom Global public logo in the right/content panel;
- landing stage `version` intentionally uses `/media/stages/reflection-portrait.png` when no CMS media assignment exists;
- inner-stage CMS/content images remain authoritative;
- mobile responsive presentation and iOS-safe controls;
- Lite and Full Reports share the approved reference result-card structure;
- Lite result card retains a dark readable fallback;
- Full Report website and PDF use the same overall score/reference hierarchy;
- commitment section uses explicit high-contrast text on its dark panel;
- Executive Summary uses visible score bars;
- 10-area breakdown uses progress bars only — no radar.

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

For report/PDF backend changes, run:

```bash
php backend/bin/production-report-flow-smoke-test.php \
  --confirm=RUN-PRODUCTION-REPORT-SMOKE \
  --recipient=unused-v4-smoke@example.com
```

Do not add `--send-email` unless intentionally testing live UAT email delivery.

CSS-only presentation changes still require `npm test` and `npm run build` before deployment.

## Standard V4 deployment

```bash
cd /srv/v4.atomglobal.com/source

sudo BRANCH=production-readiness-v4-mobile-final-20260902 \
  bash deploy/update-v4-apache.sh
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
echo "HEALTH:"
curl -fsS https://v4.atomglobal.com/api/health
```

Current server-verified application commit:

```text
7e4d89ec30fa13f1b14c2bea938189c89482d7da
```

## Approved V4 backup procedure

Before a meaningful production change:

1. create a Git safety branch from the current accepted/live V4 commit;
2. preserve V4 database/CMS state with the deployment backup or an explicit validated dump;
3. confirm the backup path before changing production;
4. keep V4 backups under `/var/backups/growth-alignment-v4`;
5. never treat an empty backup directory as valid;
6. verify compressed database backups with `gzip -t` when created manually;
7. never use V5/V3 as a V4 rollback source.

Current relevant Git safety branches:

```text
v4-pre-commitment-contrast-20260904
v4-pre-lite-contrast-fix-20260904-6559a26
v4-pre-reference-result-card-20260904
v4-pre-lite-hero-parity-20260904-0608dbc
v4-pre-full-report-meter-20260904-ec93906
v4-live-backup-20260904-9e99467
```

Confirmed older full server backup:

```text
/var/backups/growth-alignment-v4/prechange-20260903-042350
```

Git alone does not contain all live CMS/database configuration.

## UAT focus

Retest at minimum:

- all four assessment tracks;
- 40 questions / 10 sections;
- autosave/resume;
- Lite/Full Report lock;
- Lite result card remains dark/readable;
- score + `OUT OF 250` remain centered in the left score box;
- Full Report/PDF overall-result hierarchy matches;
- Head-led / `x/250` / Heart-led meter is readable;
- commitment panel heading/body/status text is readable on dark background;
- commitment textarea remains white with dark text;
- saved commitment persists after reload;
- regenerated PDF includes saved commitment with readable text;
- Pay by Card flow and reconciliation fallback;
- secure Full Report token;
- PDF/email delivery;
- administrator payment notification;
- Executive Summary bars;
- 10-area bars with no radar;
- mobile report layout;
- CMS image/logo/content edits.

## Change-control rule

**Do not mix V4 with another version or repository.**

Before any V4 production change:

1. start from the current V4 branch;
2. back up Git + database/CMS state;
3. make the smallest V4-only change;
4. run the complete automated gate;
5. review the diff;
6. deploy with the V4 Apache deployer;
7. verify active release, deployed commit marker and `/api/health`;
8. perform browser/mobile/PDF UAT;
9. update this README when the accepted baseline changes.

The V4 branch, production runtime, database/CMS state and this README are the authoritative operational references.
