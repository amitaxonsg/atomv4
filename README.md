# Atom Global Growth Alignment V4

> **V4 ONLY — CURRENT SOURCE OF TRUTH**
>
> This README describes the approved V4 application at `https://v4.atomglobal.com/`. Do not use V5, V3, another repository, or an older preview as a deployment, database, CMS, scoring, payment, report, or visual source of truth for V4 work.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, Stripe payments, UAT no-payment control, PDF/email delivery, analytics, affiliates, commitments and audit history.

## Current V4 baseline — 7 September 2026

| Item | Current V4 value |
|---|---|
| Public URL | `https://v4.atomglobal.com/` |
| Admin URL | `https://v4.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| Working/deployment branch | `production-readiness-v4-mobile-final-20260902` |
| **Last explicitly server-verified live application commit** | `09e445536055e67d0145070f2b56672ab5fb5f63` |
| **Accepted PDF pagination/parity baseline** | `0953ae66b5be5e1206df5d4ab37fb6beed4a8571` |
| PDF visual UAT | **PASSED — 5-page compact report, visible overall meter, Executive Summary packed onto page 1** |
| Previous PDF meter/space baseline | `09e445536055e67d0145070f2b56672ab5fb5f63` |
| Reference website/PDF parity baseline | `0b7ff92370d01e6cb2adb0bb9b598bd0c250e9e0` |
| Commitment contrast/readability baseline | `7e4d89ec30fa13f1b14c2bea938189c89482d7da` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release symlink | `/var/www/v4.atomglobal.com/current` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

> Documentation-only commits may be newer than the deployed application. `/var/www/v4.atomglobal.com/current` and `/var/www/v4.atomglobal.com/deployed-commit.txt` remain authoritative for the actual live runtime.

## Accepted Full Report website / PDF parity

The website Full Report is the visual reference for the generated Full Report PDF.

Approved parity rules:

- same participant/profile title hierarchy;
- same `x / OUT OF 250` overall score semantics;
- score is centered inside the left result box;
- `YOUR ALIGNMENT PATTERN` narrative appears beside the score;
- Head-led / current `x/250` / Heart-led meter appears below the narrative;
- PDF overall meter must visibly render its filled portion;
- Top three strengths and Development observations appear as paired cards;
- dark `Your full development report` banner is preserved;
- Executive Summary uses Highest 3 / Lowest 3 with visible proportional bars;
- 10-area breakdown uses bars only — no radar;
- report sections use the same editorial card/accent language as the website;
- commitment section remains dark with high-contrast text;
- retake, coach and `Use this report to` sections preserve the website hierarchy;
- PDF naturally paginates for A4 and does not contain interactive website buttons.

### Accepted PDF pagination / space usage

The accepted baseline `0953ae66b5be5e1206df5d4ab37fb6beed4a8571` improves Dompdf pagination so the report uses A4 space efficiently without shrinking content into unreadable text.

Approved behavior:

- A4 margins are compact but readable;
- the hero/result block remains intact;
- the overall meter uses a Dompdf-safe solid fill instead of a CSS gradient that can disappear in PDF rendering;
- Executive Summary is row-splittable at safe boundaries instead of being forced as one large indivisible block;
- 10-area score rows remain intact;
- roadmap, profile spectrum, methodology and other large sections may flow across pages;
- individual cards remain together where practical;
- `Use this report to` can break at safe row boundaries;
- empty deep-dive headings are not emitted when no deep-dive content exists;
- headings remain attached to the content they introduce where practical.

Visual UAT on the accepted PDF produced a compact **5-page** report with the overall meter visible and the Executive Summary beginning on page 1.

Key PDF commits:

- `2e586aed738a685eef9c82c6039f3956c93392ff` — align V4 Full Report PDF more closely with website hierarchy
- `0b7ff92370d01e6cb2adb0bb9b598bd0c250e9e0` — guard website/PDF parity
- `af23a2dc867d321005e78d8e4d94acac7b6fb44c` — Dompdf-safe meter fill and compact page spacing
- `09e445536055e67d0145070f2b56672ab5fb5f63` — guard PDF meter and space usage; last explicitly server-verified live PDF release
- `8596ff2cd886771f0235bb6d335e888fae231c64` — improve Executive Summary and feature pagination packing
- `0953ae66b5be5e1206df5d4ab37fb6beed4a8571` — guard accepted PDF pagination packing baseline

## Lite / Full overall-result UI

Lite and Full website reports share the same approved result-card structure:

- dark premium card;
- centered overall score with `OUT OF 250`;
- readable white/gold contrast;
- alignment narrative beside the score on desktop;
- centered meter underneath the narrative;
- mobile stacks cleanly;
- Lite keeps an explicit dark fallback so white text never becomes unreadable.

## Commitment section

The Full Report development commitment is persistent server-side functionality.

When the participant selects **Save my commitment**:

- text is stored in `report_commitments`;
- it is linked to the `generated_report_id`;
- check-in date is stored;
- reopening the same private Full Report retrieves it;
- PDF generation reads the same commitment data and includes it when generated or regenerated after the save.

Approved contrast:

- `MAKE IT ACTIONABLE` is high contrast;
- `My 90-day development commitment` is high contrast;
- body/status/saved commitment text is readable on the dark panel;
- textarea remains white with dark text;
- persistence and business logic are unchanged.

## Approved 10-area scoring semantics

The radar visual is not part of approved V4.

- 10 areas, bars only;
- each area score is 5–25;
- `5 = more Head-led`;
- `15 = balanced`;
- `25 = more Heart-led`;
- normalization is `(value - 5) / 20`;
- browser and PDF use the same meaning;
- Executive Summary Highest 3 / Lowest 3 also uses proportional bars.

Do not reintroduce the radar.

## Payment reliability state

The signed Stripe webhook remains the primary fulfilment path. V4 also has direct Stripe reconciliation to protect users when webhook delivery is delayed or missed.

Approved flow:

1. Stripe Checkout receives payment.
2. V4 verifies the exact Checkout Session and `payment_status = paid`.
3. Checkout metadata must match the assessment session and `payment_purpose = full_report`.
4. Payment is stored with amount, currency, Payment Intent and paid timestamp.
5. Full Report unlocks.
6. Secure report token/URL is stored.
7. Customer payment confirmation and `paid_report_ready` emails are queued idempotently.
8. Full Report PDF is generated and attached to email.
9. Administrator `payment_paid` notification is recorded.
10. Scheduled reconciliation protects the flow if webhook delivery is missed.

Real payment IDs `44` and `45` were used during burn-in verification. The tested sessions did not appear in `stripe_webhook_events`, so Stripe Dashboard webhook delivery still warrants operational review; reconciliation protects the customer flow meanwhile.

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

V4 Admin is connected to the production API/database for Dashboard, Participants/history, Questionnaire, Assessments, Content/media, Branding, Reports/PDF, Payments/UAT, Email, Affiliates, Analytics, SEO/AEO/GEO, Settings/integrations, Admin users/permissions, Audit logs and Feedback/help.

CMS/database state is authoritative.

Admin uses `system.cash_on_delivery_enabled` as the authoritative UAT no-payment override.

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

For report/PDF backend changes:

```bash
php backend/bin/production-report-flow-smoke-test.php \
  --confirm=RUN-PRODUCTION-REPORT-SMOKE \
  --recipient=unused-v4-smoke@example.com
```

Do not add `--send-email` unless intentionally testing live UAT email delivery.

The accepted `0953ae66...` PDF pagination candidate passed:

- **81/81 tests**;
- Vite production build;
- `PdfService.php` syntax check;
- guarded production Lite/Full Report smoke test;
- PDF generation;
- temporary test cleanup;
- clean database after smoke testing;
- visual PDF UAT.

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

Do not mark a candidate as server-verified live until the active release / deployed marker is explicitly confirmed.

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
v4-pre-pdf-pagination-pack-20260907-09e4455
v4-pre-pdf-meter-space-fix-20260907-0b7ff92
v4-pre-pdf-website-parity-20260907-9d8cb31
v4-pre-commitment-contrast-20260904
v4-pre-lite-contrast-fix-20260904-6559a26
v4-pre-reference-result-card-20260904
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
- score + `OUT OF 250` remain centered;
- Head-led / `x/250` / Heart-led meter is readable on website and PDF;
- Full Report website/PDF hierarchy matches;
- PDF uses A4 space efficiently without excessive blank areas;
- Executive Summary bars are visible;
- all 10 area bars are visible and proportional;
- commitment panel text remains readable;
- saved commitment persists after reload;
- regenerated PDF includes saved commitment;
- Pay by Card flow and reconciliation fallback;
- secure Full Report token;
- PDF/email delivery;
- administrator payment notification;
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
