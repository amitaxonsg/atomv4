# Atom Global Growth Alignment V4

> **V4 ONLY — CURRENT SOURCE OF TRUTH**
>
> This README describes the approved V4 application at `https://v4.atomglobal.com/`. Do not use V5, V3, another repository, or an older preview as a deployment, database, CMS, scoring, payment, report, or visual source of truth for V4 work.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, Stripe payments, UAT no-payment control, PDF/email delivery, analytics, affiliates, commitments and audit history.

## Current V4 baseline — 8 September 2026

| Item | Current V4 value |
|---|---|
| Public URL | `https://v4.atomglobal.com/` |
| Admin URL | `https://v4.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| Working/deployment branch | `production-readiness-v4-mobile-final-20260902` |
| **Server-verified live application commit** | `4f19c110141020e3ab18dcad272e0a231587ebb0` |
| Live Share Highlights modal | **DEPLOYED / LIVE / HEALTHY — template-style compact UI** |
| Previous in-page modal baseline | `d9a3fa42192fd5f716459d79ef5a37f6acce0383` |
| Accepted PDF pagination/parity baseline | `0953ae66b5be5e1206df5d4ab37fb6beed4a8571` |
| PDF visual UAT | **PASSED — compact 5-page report, visible overall meter, Executive Summary packed onto page 1** |
| Commitment contrast/readability baseline | `7e4d89ec30fa13f1b14c2bea938189c89482d7da` |
| Latest confirmed full pre-change backup | `/var/backups/growth-alignment-v4/pre-share-modal-20260908T024637Z` |
| Latest share-modal safety branch | `v4-pre-share-modal-template-20260908-c09cc2e` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release symlink | `/var/www/v4.atomglobal.com/current` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

The `4f19c110...` V4 deployment was confirmed healthy with **82/82 tests**, successful Vite build, PHP syntax check, successful Apache release switch, Stripe reconciliation with `0` failures, administrator alert processing with `0` failures, email queue processing with `0` failures, healthy five-minute background processing, and `/api/health` returning `status: ok`.

> Documentation-only commits may be newer than the deployed application. `/var/www/v4.atomglobal.com/current` and `/var/www/v4.atomglobal.com/deployed-commit.txt` remain authoritative for the actual live runtime.

## Lite / Full overall-result UI

Lite and Full website reports share the same approved result-card structure:

- dark premium result card;
- centered overall score with `OUT OF 250`;
- readable white/gold contrast;
- alignment narrative beside the score on desktop;
- Head-led / current `x/250` / Heart-led meter below the narrative;
- mobile stacks cleanly;
- Lite keeps an explicit dark fallback so white text never becomes unreadable.

## Highlight-only sharing and thank-you CTA

Both Lite and Full website reports end with the same thank-you/share section:

> **Thank you for taking the assessment. If this is helpful, please share it with someone who will benefit from taking it!**

Privacy rules are mandatory:

- only Lite-safe highlights may be shared: track/result title, profile, overall score, alignment summary, top three strengths and development observations;
- the shared call-to-action uses only the public site origin/home page;
- the current private report URL is never shared;
- Full Report content is never included in the share payload;
- PDF/private link, written reflections, methodology, roadmap, commitments, detailed development content and payment/report tokens are excluded;
- the previous Full Report `Copy as text` action remains removed;
- private Full Report self-delivery remains available through `Email PDF to self`, `Open PDF` and `Print report`.

### Share Highlights modal — current live behavior

The bottom report action bar contains only:

- **New assessment**;
- **Open PDF** when the report is unlocked;
- **Print report**.

`Share highlights` appears only in the closing thank-you card. Selecting it opens an **in-page modal overlay** on top of the report rather than expanding the platform choices inline.

Approved modal behavior:

- compact white card inspired by the approved reference template;
- simple title / close `×` header;
- one clean row of circular share choices for Facebook, Instagram, LinkedIn and More;
- clean copy-link area beneath the social row;
- dimmed backdrop with centered dialog on desktop;
- mobile remains compact and responsive;
- Escape closes the dialog;
- clicking the backdrop closes the dialog;
- keyboard focus stays inside the dialog while open and returns to the trigger on close;
- background page scrolling is locked while the modal is open;
- modal is excluded from print output;
- public assessment link is shown read-only;
- `Copy highlights` continues to copy Lite-safe highlights only;
- platform buttons retain accessible labels;
- no modal/share action receives the private report URL or Full Report content.

Sharing behavior remains:

- Facebook opens Facebook's public share endpoint with the public assessment URL and copies the Lite-safe highlights for paste fallback;
- LinkedIn opens LinkedIn's public share endpoint and copies the Lite-safe highlights for paste fallback;
- Instagram uses the operating system/browser native share sheet when available; otherwise the highlights are copied and Instagram is opened for manual paste;
- **More** uses the native Web Share sheet when available and otherwise copies the highlights for pasting into any compatible app.

Key sharing commits:

- `243d55f25dd1cd6bfb92e98a3145ac63dd95ad05` — initial highlight-only sharing and thank-you CTA
- `587390ad40ded3d8f8cad90b23934d71b6ae0b70` — privacy regression guard
- `f7d5823441b64268be9a3e5bead2558965d42de5` — legacy sharing test corrected
- `e4adbb94b83ebbb9b197459bf6fdf632a3fb2ba0` — remove action-bar Share button and add platform chooser
- `95a1850e1d7243cac41aeb3e7caf3f62e5d62b27` — guard platform choices/action-bar removal
- `3c0f3730cd667e4fb942a4da87fbe90379340f74` — server-verified text-button platform chooser baseline
- `06c6588513e2ce72cdab179da2ee12c6bdc12627` — compact icon presentation for Facebook/LinkedIn/Instagram and `More`
- `57dd98f5d55f4fc526c42e6b8ed5b035bb67f802` — server-verified compact icon UI baseline
- `3570b2109de5457e75f72c2b95d12986e078bab3` — replace inline platform row with accessible in-page Share Highlights modal
- `5c01af3fa9a0a3a8dbe1214ab7e59b65ebc21b9b` — modal styling, responsive behavior and print exclusion
- `d9a3fa42192fd5f716459d79ef5a37f6acce0383` — server-verified first in-page Share Highlights modal baseline
- `1ba6a02c82971c07f6973d2a680e77d3cf42d342` — restyle modal to approved compact template visual
- `4f19c110141020e3ab18dcad272e0a231587ebb0` — regression guard and **server-verified live template-style Share Highlights modal baseline**

## Accepted Full Report website / PDF parity

The website Full Report is the visual reference for the generated Full Report PDF.

Approved parity rules:

- same participant/profile title hierarchy;
- same `x / OUT OF 250` overall score semantics;
- score centered inside the left result box;
- `YOUR ALIGNMENT PATTERN` beside the score;
- Head-led / current `x/250` / Heart-led meter below the narrative;
- PDF overall meter visibly renders its filled portion;
- Top three strengths and Development observations appear as paired cards;
- dark `Your full development report` banner is preserved;
- Executive Summary uses Highest 3 / Lowest 3 with visible proportional bars;
- 10-area breakdown uses bars only — no radar;
- report sections use the same editorial card/accent language as the website;
- commitment section remains dark with high-contrast text;
- retake, coach and `Use this report to` sections preserve the website hierarchy;
- PDF naturally paginates for A4 and does not contain interactive website buttons.

### Accepted PDF pagination / space usage

The accepted baseline `0953ae66b5be5e1206df5d4ab37fb6beed4a8571` uses A4 space efficiently without shrinking content into unreadable text.

Approved behavior:

- compact readable A4 margins;
- intact hero/result block;
- Dompdf-safe solid overall meter fill;
- Executive Summary may break only at safe row boundaries;
- 10-area score rows stay intact;
- roadmap, profile spectrum, methodology and other large sections may flow across pages;
- individual cards remain together where practical;
- `Use this report to` may break at safe row boundaries;
- empty deep-dive headings are not emitted when no deep-dive content exists.

Visual UAT produced a compact **5-page** report with the overall meter visible and the Executive Summary beginning on page 1.

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

Current JavaScript gate:

```text
tests 82
pass 82
fail 0
```

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
4f19c110141020e3ab18dcad272e0a231587ebb0
```

## Approved V4 backup procedure

Before a meaningful production change:

1. create a Git safety branch from the current V4 branch head;
2. preserve V4 database/CMS state with the deployment backup or an explicit validated dump;
3. preserve the V4 environment, active-release marker, deployed commit marker and persistent storage when taking a full pre-change backup;
4. confirm the backup path before changing production;
5. keep V4 backups under `/var/backups/growth-alignment-v4`;
6. never treat an empty backup directory as valid;
7. verify compressed database/storage backups with `gzip -t` and inspect the storage archive with `tar -tzf` when created manually;
8. never use V5/V3 as a V4 rollback source.

### Confirmed pre-share-modal full backup — 8 September 2026

```text
/var/backups/growth-alignment-v4/pre-share-modal-20260908T024637Z
```

Confirmed backup contents include:

```text
current-release.txt
deployed-commit.txt
growth-alignment-v4
growth-alignment-v4-storage.tar.gz
growth_alignment_v4.sql.gz
source-commit.txt
v4.env
```

At backup time, live release/source/deployed marker all pointed to:

```text
57dd98f5d55f4fc526c42e6b8ed5b035bb67f802
```

Relevant Git safety branches:

```text
v4-pre-share-modal-template-20260908-c09cc2e
v4-pre-share-modal-20260908-7151c7b
v4-pre-share-icon-ui-20260908-2acf40c
v4-pre-share-platform-menu-20260907-6d27e07
v4-prechange-backup-20260907-1720-ab0c8dd
v4-pre-pdf-pagination-pack-20260907-09e4455
v4-pre-pdf-meter-space-fix-20260907-0b7ff92
v4-pre-pdf-website-parity-20260907-9d8cb31
v4-pre-commitment-contrast-20260904
v4-pre-lite-contrast-fix-20260904-6559a26
v4-pre-reference-result-card-20260904
v4-live-backup-20260904-9e99467
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
- bottom action bar contains no Share button;
- thank-you card contains the single `Share highlights` button;
- clicking `Share highlights` opens the in-page modal rather than expanding inline controls;
- modal matches the approved compact white-card template;
- social choices appear as one row of circular icons;
- copy-link area appears beneath the social row;
- modal can close by `×`, Escape and backdrop click;
- modal restores focus to the Share trigger after close;
- public assessment link is shown, not the private report link;
- `Copy highlights` copies only Lite-safe highlights;
- native share / clipboard fallback contains only Lite-safe highlights;
- private Full Report/PDF/reflections/methodology/roadmap/commitment details are absent from the share payload;
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
