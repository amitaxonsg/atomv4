# Atom Global Growth Alignment V4

> **V4 ONLY — CURRENT SOURCE OF TRUTH**
>
> This README describes the approved V4 application at `https://v4.atomglobal.com/`. Do not use V5, V3, another repository, or an older preview as a deployment, database, CMS, scoring, payment, report, visual, or rollback source for V4 work.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, Stripe payments, UAT no-payment control, PDF/email delivery, analytics, affiliates, commitments, retakes, sharing and audit history.

## Current V4 baseline — 10 September 2026

| Item | Current V4 value |
|---|---|
| Public URL | `https://v4.atomglobal.com/` |
| Admin URL | `https://v4.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| Working/deployment branch | `production-readiness-v4-mobile-final-20260902` |
| **Server-verified live application commit** | `113c7128656c502509f122a8d6c2bd2aa6250dd0` |
| Live application state | **DEPLOYED / LIVE / HEALTHY** |
| Current JavaScript gate | **82 tests / 82 pass / 0 fail** |
| Public Lite Report sharing | **LIVE — signed `/share/lite/` URL, Lite-only data** |
| X sharing | **LIVE — weighted 280-character calculation with assessment CTA/link preserved** |
| Facebook sharing | **LIVE — guided copy/paste flow** |
| LinkedIn sharing | **LIVE — guided copy/paste flow** |
| WhatsApp sharing | **LIVE — automatic Lite-safe highlights** |
| Accepted PDF pagination/parity baseline | `0953ae66b5be5e1206df5d4ab37fb6beed4a8571` |
| PDF visual UAT | **PASSED — compact 5-page report, visible overall meter, Executive Summary on page 1** |
| Latest confirmed full checkpoint backup | `/var/backups/growth-alignment-v4/checkpoint-20260910T110514Z-113c712` |
| Next-change backup rule | Create a fresh full backup before the next material V4 change. |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release symlink | `/var/www/v4.atomglobal.com/current` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

The `0b541e49...` V4 deployment was server-confirmed healthy on 8 September 2026 with **82/82 tests**, successful Vite build, PHP syntax checks for the public Lite Report backend changes, successful Apache release switch, Stripe reconciliation with `0` failures, administrator alert processing with `0` failures, email queue processing with `0` failures, healthy five-minute background processing, and `/api/health` returning `status: ok`.

> Documentation-only commits may be newer than the deployed application. `/var/www/v4.atomglobal.com/current` and `/var/www/v4.atomglobal.com/deployed-commit.txt` remain authoritative for the actual live runtime.

## Lite / Full overall-result UI

Lite and Full website reports share the approved result-card structure:

- dark premium result card;
- centered overall score with `OUT OF 250`;
- readable white/gold contrast;
- alignment narrative beside the score on desktop;
- Head-led / current `x/250` / Heart-led meter below the narrative;
- mobile stacks cleanly;
- Lite keeps an explicit dark fallback so white text never becomes unreadable.

Do not reintroduce the old radar visual.

## Highlight-only sharing and thank-you CTA

The participant's normal Lite/Full report ends with the thank-you/share section:

> **Thank you for taking the assessment. If this is helpful, please share it with someone who will benefit from taking it!**

### Mandatory privacy rules

The sharing feature must preserve these invariants:

- share only Lite-safe result information;
- allowed highlight data: track/result title, profile, overall score, alignment summary, top three strengths and development observations;
- never share `paid_report_json`, paid content, Full Report content, PDF path/link, commitment, roadmap, methodology, written reflections, detailed development content, participant email, participant name, private report token, private `/report/<token>` URL, or `/api/reports/<token>` URL;
- Full Report `Copy as text` remains removed;
- private Full Report self-delivery remains through `Email PDF to self`, `Open PDF` and `Print report`.

### Share Highlights modal — current live behavior

`Share highlights` appears in the closing thank-you card. It opens an accessible in-page modal with:

- title `Share with Friends`;
- read-only **Share your link** field;
- `Copy link` action;
- Facebook;
- X;
- WhatsApp;
- LinkedIn;
- no Instagram;
- no More/native-share button;
- Escape close;
- backdrop close;
- focus trapping while open and focus restoration on close;
- background scroll lock;
- print exclusion.

### Public Lite Report share link

The **Share your link** field must point to the participant's public Lite Report, not the V4 homepage and not the private Full Report URL.

Current URL form:

```text
https://v4.atomglobal.com/share/lite/<report-id>.<signed-hmac>
```

The signature is generated server-side using the V4 application key and the report ID. The corresponding public API route is:

```text
GET /api/public/reports/lite/{token}
```

The public Lite endpoint is intentionally separate from the private report-token endpoint. It returns only the free/Lite report JSON and the minimum public report metadata needed to render the Lite Report. It explicitly returns:

```text
paid_report_json = null
is_unlocked = false
pdf_available = false
checkoutAvailable = false
cashOnDeliveryAvailable = false
sharedLite = true
```

The public Lite query does not return participant name, participant email, private secure-token hash, private report URL or paid report content.

When a recipient opens the shared Lite Report:

- they see the Lite profile, score, alignment pattern, top strengths and development observations;
- private Full Report content is not available;
- they do not receive the original participant's payment controls;
- the page offers a CTA to take their own Growth Alignment assessment.

### Platform-specific share behavior

**Facebook**

Facebook's public share endpoint does not reliably prefill the user's post text. V4 therefore:

1. copies the Lite-safe highlights to the clipboard;
2. displays the `Highlights copied for Facebook` guidance panel;
3. opens Facebook only after the participant selects `Open Facebook`;
4. instructs the participant to paste into `What's on your mind?` using Ctrl+V / Cmd+V.

The Facebook URL points to the safe public Lite Report URL.

**LinkedIn**

LinkedIn uses the same guided approach:

1. copy Lite-safe highlights;
2. show `Highlights copied for LinkedIn`;
3. select `Open LinkedIn`;
4. paste the copied highlights into the LinkedIn post field.

The LinkedIn URL points to the safe public Lite Report URL.

**WhatsApp**

WhatsApp opens automatically with the full Lite-safe highlight text. The text includes the public assessment CTA and does not include private Full Report information.

**X**

X automatically opens an intent post. The X formatter is separate from the other platforms and uses X-compatible weighted character counting:

- maximum weighted length: `280`;
- URL weight: `23` characters after t.co shortening;
- Unicode characters are weighted using the supported X/twitter-text ranges;
- the post includes the track heading, profile, score and as much alignment summary as safely fits;
- the assessment CTA/link is always reserved and preserved;
- the same optimized X text is copied to clipboard as fallback.

X continues to promote the assessment homepage:

```text
Take the Growth Alignment assessment: https://v4.atomglobal.com/
```

The public Lite Report link in the modal is separate from that X assessment CTA.

### Current sharing commits

- `243d55f25dd1cd6bfb92e98a3145ac63dd95ad05` — initial highlight-only sharing and thank-you CTA
- `587390ad40ded3d8f8cad90b23934d71b6ae0b70` — privacy regression guard
- `e4adbb94b83ebbb9b197459bf6fdf632a3fb2ba0` — remove action-bar Share and add share chooser
- `57dd98f5d55f4fc526c42e6b8ed5b035bb67f802` — server-verified compact icon baseline
- `3570b2109de5457e75f72c2b95d12986e078bab3` — accessible in-page modal
- `7e2e7381d5833cbf6dc70ce101a5e0f67e364aa4` — final modal/icon specificity guard
- `79885c07567f2aec1226e4ea3a907b02b2c4e29b` — Facebook guided share flow
- `d22fa8c4905ca724b325b9f7ae9cefd4ac14bc14` — LinkedIn guided share flow
- `29aae36c1352294b80b7dc290edbbded911476ad` — X-specific 280-character optimizer
- `72f47f985827c1946e3840eda14fd97e91462242` — X weighted-character limit fix
- `0b541e491905245f27ffd541ec82051c831f0d31` — secure public Lite Report share links; **current live application baseline**

## Accepted Full Report website / PDF parity

The website Full Report remains the visual reference for the generated Full Report PDF.

Approved parity rules:

- same participant/profile title hierarchy;
- same `x / OUT OF 250` overall-score semantics;
- score centered inside the left result box;
- `YOUR ALIGNMENT PATTERN` beside the score;
- Head-led / current `x/250` / Heart-led meter below the narrative;
- PDF overall meter visibly renders its filled portion;
- Top three strengths and Development observations appear as paired cards;
- dark `Your full development report` banner is preserved;
- Executive Summary uses Highest 3 / Lowest 3 with visible proportional bars;
- 10-area breakdown uses bars only — no radar;
- commitment section remains dark with high-contrast text;
- retake, coach and `Use this report to` sections preserve the website hierarchy;
- PDF naturally paginates for A4 and does not contain interactive website buttons.

### Accepted PDF pagination / space usage

Accepted baseline:

```text
0953ae66b5be5e1206df5d4ab37fb6beed4a8571
```

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
- it is linked to `generated_report_id`;
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
6. Secure private report token/URL is stored.
7. Customer payment confirmation and `paid_report_ready` emails are queued idempotently.
8. Full Report PDF is generated and attached to email.
9. Administrator `payment_paid` notification is recorded.
10. Scheduled reconciliation protects the flow if webhook delivery is missed.

Real payment IDs `44` and `45` were used during burn-in verification. Reconciliation remains the customer-protection fallback if Stripe webhook delivery is delayed or missed.

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
9. optional Lite-safe sharing;
10. Stripe checkout or explicitly enabled UAT no-payment route;
11. payment verification/reconciliation;
12. unlocked private Full Report;
13. PDF/email delivery;
14. optional development commitment and later retest.

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

Current server-verified live application commit:

```text
0b541e491905245f27ffd541ec82051c831f0d31
```

## Approved V4 backup procedure

Before a meaningful production change:

1. create a Git safety branch from the current V4 branch head;
2. preserve the V4 database/CMS state with an explicit validated dump or the deployment backup;
3. preserve the V4 environment file;
4. preserve the active-release path;
5. preserve the deployed application commit marker;
6. preserve the current source commit marker;
7. preserve the persistent V4 storage tree;
8. keep V4 backups under `/var/backups/growth-alignment-v4`;
9. never treat an empty or partially written backup directory as valid;
10. verify the compressed database with `gzip -t`;
11. verify the storage archive with `tar -tzf`;
12. never use V5 or V3 as a V4 rollback source.

### Latest confirmed full backup

The last fully confirmed pre-change backup remains:

```text
/var/backups/growth-alignment-v4/pre-share-modal-20260908T024637Z
```

It contains the database dump, persistent storage, V4 environment, cron/config marker and release/source/deployed commit markers.

### Required next-change backup baseline

After this documentation sync and **before any further V4 production change**, create and validate this full backup directory:

```text
/var/backups/growth-alignment-v4/pre-next-change-20260908-public-lite-share-live
```

The backup must capture:

```text
growth_alignment_v4.sql.gz
growth-alignment-v4-storage.tar.gz
v4.env
growth-alignment-v4
current-release.txt
deployed-commit.txt
source-commit.txt
```

The deployed application marker in that backup must remain:

```text
0b541e491905245f27ffd541ec82051c831f0d31
```

The `source-commit.txt` value may be a later documentation-only Git commit; this is expected and must not be confused with the deployed runtime commit.

### Relevant Git safety branches

```text
v4-pre-readme-live-sync-20260908-0b541e4
v4-pre-public-lite-share-link-20260908-72f47f9
v4-pre-x-weighted-length-fix-20260908-29aae36
v4-pre-linkedin-copy-flow-20260908-79885c0
v4-pre-facebook-copy-flow-20260908-7e2e738
v4-pre-share-ui-icon-fix-20260908-e0f8a75
v4-pre-share-modal-template-20260908-c09cc2e
v4-pre-share-modal-20260908-7151c7b
v4-pre-share-icon-ui-20260908-2acf40c
v4-pre-share-platform-menu-20260907-6d27e07
v4-prechange-backup-20260907-1720-ab0c8dd
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
- Share modal contains Facebook, X, WhatsApp and LinkedIn only;
- no Instagram or More action is present;
- Facebook guided-copy flow works;
- LinkedIn guided-copy flow works;
- WhatsApp receives Lite-safe highlight text;
- X remains within its weighted 280-character limit;
- X assessment CTA/link is preserved;
- **Share your link** is a signed `/share/lite/` URL, not the homepage and not `/report/<private-token>`;
- the signed Lite link opens in an incognito/private browser;
- the shared Lite view contains only Lite-safe result content;
- shared Lite API does not expose participant name/email, private token, PDF or paid content;
- shared Lite viewer cannot purchase/unlock the original participant's Full Report;
- public shared view offers a CTA to take a new assessment;
- Pay by Card flow and reconciliation fallback;
- secure private Full Report token;
- PDF/email delivery;
- administrator payment notification;
- mobile report layout;
- CMS image/logo/content edits.

## Change-control rule

**Do not mix V4 with another version or repository.**

Before any V4 production change:

1. start from the current V4 production branch;
2. create a safety branch;
3. create and validate the full V4 backup described above;
4. make the smallest V4-only change;
5. run the complete automated gate;
6. review the diff;
7. deploy with the V4 Apache deployer;
8. verify active release, deployed commit marker and `/api/health`;
9. perform browser/mobile/PDF UAT as relevant;
10. update this README when the accepted baseline changes.

The V4 branch, production runtime, database/CMS state and this README are the authoritative operational references.
