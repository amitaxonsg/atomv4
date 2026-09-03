# Atom Global Growth Alignment V4

> **V4 ONLY — CURRENT SOURCE OF TRUTH**
>
> This README describes the approved V4 production deployment at `https://v4.atomglobal.com/`. Do not use another version/repository as a visual, deployment, database, CMS or feature reference for V4 work.

Self-hosted React/Vite, PHP 8.3-FPM and MariaDB assessment platform for Atom Global Consulting, including questionnaire, CMS/Admin, Lite/Full reports, Stripe payments, UAT no-payment control, PDF/email delivery, analytics, affiliates and audit history.

## Current production baseline — 3 September 2026

| Item | Current V4 value |
|---|---|
| Public URL | `https://v4.atomglobal.com/` |
| Admin URL | `https://v4.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| Working/deployment branch | `production-readiness-v4-mobile-final-20260902` |
| **Live deployed application commit** | `ecf57b8273cbbd74e4e37f3fc29c225dd9c6b082` |
| Current live Git backup branch | `v4-live-backup-20260903-ecf57b8` |
| Pre-change Git backup branch | `v4-prechange-backup-20260903-3c21f04` |
| Pre-change server backup | `/var/backups/growth-alignment-v4/prechange-20260903-042350` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Releases | `/var/www/v4.atomglobal.com/releases` |
| Active release | `/var/www/v4.atomglobal.com/current` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Web server | Apache + PHP 8.3-FPM |

> README/documentation commits may be newer than the deployed application commit because documentation-only changes do not require a production redeploy. The authoritative live runtime marker is `/var/www/v4.atomglobal.com/deployed-commit.txt`.

The current V4 production build passed **74/74 frontend tests**, Vite production build, PHP syntax/tests and deployment health checks.

Latest production health confirmed `status: ok` with database, migrations, storage, Stripe, Stripe webhook, email and cron healthy. `feedbackGitHub:false` is optional and is not a launch blocker.

## What was updated on 2–3 September 2026

### 1. Admin UAT No-Payment visibility fix

Problem observed: Admin had **Client UAT payment bypass / Cash on Delivery** disabled, but the Lite Report checkout still showed:

- `UAT Test — No Payment`
- the UAT explanatory paragraph.

Root cause: the report payload was still reading `payments.cash_on_delivery_enabled` directly instead of honoring the system-level Admin override.

Fixed in commit:

`183027dc9ceefb156d0cad53b52664eb4b5d43b4`

`ReportService::cashOnDeliveryAvailable()` now checks:

1. `system.cash_on_delivery_enabled`
2. falls back to `payments.cash_on_delivery_enabled` only when the system override is not set.

Therefore, when Admin saves:

```text
system.cash_on_delivery_enabled = false
```

both the UAT button and UAT explanatory message are hidden after a fresh report load, and the backend UAT route remains unavailable.

### 2. Personal checkout coffee value banner

The Personal-only line is now:

`For less than a cup of coffee, find out more about yourself! ✨`

It is displayed as a separate highlighted value banner immediately above the dark checkout panel.

Current accepted treatment:

- Personal assessment only;
- soft green-tinted background;
- green border with stronger left accent;
- lively **Libre Caslon Display** typography;
- larger, expressive text with restrained weight;
- real sparkle emoji with color-emoji font fallbacks where supported by the browser/OS;
- subtle shadow and spacing;
- responsive mobile layout;
- payment/UAT/report logic unchanged.

Change history:

- initial supporting-text styling: `4c784a6cc5ffebb025f4ab27443fa03a94f2661b`
- accepted green banner treatment: `7bf9eeb0106c14b5be7f341a3ccacef0d1d0985c`
- final copy + sparkle emoji: `3c31cf6597002bbde3641867abf6bf778d3d0d7c`
- lively banner typography: `33b75de6b905ec3f650af5ad5e5bb17b7468dc85`
- regression-test update for the approved final copy: `ecf57b8273cbbd74e4e37f3fc29c225dd9c6b082`

The final release passed **74/74 tests**, built successfully, deployed successfully through the V4 Apache deployer, and returned healthy production checks.

### 3. Mobile autosave race-condition fix

Problem observed on mobile: when participants answered quickly while the previous answer was still saving, overlapping save requests could occur. An earlier failed request could leave a stale red `Load failed` message on screen even after a later save completed successfully and the header showed `Saved`.

Fixed in commit:

`03d3e998e4ee9f97ef734de06e290d934e5ddbb4`

The V4 questionnaire now:

- serializes autosave requests;
- avoids overlapping in-flight save writes;
- allows only the latest save result to control visible save/error status;
- explicitly clears the stale error after the latest save succeeds;
- keeps the participant answer/session persistence behavior unchanged.

A regression test covering autosave serialization and stale-error clearing was added in commit:

`d73684abf095063894b1dd90870a8ce3d2d37dc5`

### 4. Halfway and completion milestone presentation

The halfway and completion messages were visually improved without changing CMS-controlled wording or assessment logic.

Current treatment includes:

- stronger gold-accent card;
- milestone label/badge;
- subtle gradient/glow;
- stronger spacing and typography;
- richer completion-state accent;
- gentle entrance animation;
- reduced-motion support;
- mobile-specific sizing and spacing.

The first milestone markup attempt changed too much of `AssessmentLayout.jsx` and was correctly blocked by the automated deployment gate. The approved V4 questionnaire layout was restored and only the intended milestone markup was reapplied in commit:

`4a31759fdc7b2aa28bef636167699991c152f1f4`

The README regression expectation was then restored and the deployment passed at:

`3c21f046134e19dcbd1df656ac199c650c86fe62`

The current approved V4 live application baseline is:

`ecf57b8273cbbd74e4e37f3fc29c225dd9c6b082`

## Approved visual state

The approved production presentation must remain unchanged unless specifically requested:

- desktop split layout: image/visual panel left, application content right;
- Atom Global public logo in the right/content panel;
- left visual headline/support copy positioned bottom-left on desktop;
- landing stage (`version`) intentionally uses the deployed approved `/media/stages/reflection-portrait.png` when no CMS media assignment is present;
- inner-stage CMS/content images remain authoritative;
- mobile keeps the approved responsive presentation and iOS-safe controls;
- no obsolete hard-coded image fallback should override valid CMS settings;
- Personal checkout displays the accepted green coffee value banner above the dark payment panel;
- final Personal banner wording is `For less than a cup of coffee, find out more about yourself! ✨` using the approved lively typography treatment.

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

The live questionnaire uses **40 questions across 10 sections**.

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

V4 Admin is connected to the production API/database for:

- Dashboard and insights
- Participants/history
- Questionnaire experience/content
- Assessments and protected question corrections
- Content stages/media
- Branding and public/report/email assets
- Reports/PDF actions
- Payments and UAT control
- Email templates and delivery queue
- Affiliates/attribution
- Analytics/funnel
- SEO/AEO/GEO
- Settings/integrations
- Admin users/permissions
- Audit logs
- Feedback/help

CMS/database state is authoritative. Do not compensate for an Admin/CMS setting with an unrelated hard-coded frontend override.

## UAT No-Payment control

Admin location:

**Admin → Payments → Client UAT payment bypass**

Effective system setting:

```text
system.cash_on_delivery_enabled
```

When `true`, the controlled UAT no-charge test route may appear.

When `false`:

- `UAT Test — No Payment` must not appear;
- its explanatory paragraph must not appear;
- the backend must reject the no-payment UAT route.

For public/normal operation this setting should remain disabled unless Amit explicitly enables it for a controlled UAT test.

Server verification:

```bash
cd /srv/v4.atomglobal.com/source
php -r '
$c=require "backend/src/bootstrap.php";
$s=$c["settings"];
echo "UAT enabled: ";
var_export($s->get("system.cash_on_delivery_enabled", null));
echo PHP_EOL;
'
```

## Mobile autosave behavior

The question screen displays the save state near the progress indicator.

Expected behavior:

- answer change → `Saving…`;
- latest save succeeds → `Saved`;
- temporary failed save → error may be shown;
- a subsequent successful latest save must clear the stale error automatically.

Rapid tapping must not cause concurrent save requests to overwrite each other's UI state.

During UAT, specifically test:

- fast consecutive answer changes;
- section changes while saving;
- temporary network slowdown/interruption;
- recovery back to `Saved` without a persistent `Load failed` banner;
- browser refresh/resume confirming persisted answers.

## Standard V4 deployment

Apache only:

```bash
cd /srv/v4.atomglobal.com/source

git fetch origin
git checkout production-readiness-v4-mobile-final-20260902
git reset --hard origin/production-readiness-v4-mobile-final-20260902

sudo BRANCH=production-readiness-v4-mobile-final-20260902 \
  bash deploy/update-v4-apache.sh
```

The deployer performs database backup, environment validation, migrations/seed validation, PHP tests, frontend tests/build, immutable release creation, atomic symlink switch, PHP-FPM/Apache reload, cron verification and health checking.

### Post-deployment verification

```bash
echo "SOURCE:"
git rev-parse HEAD

echo "LIVE:"
cat /var/www/v4.atomglobal.com/deployed-commit.txt

curl -fsS https://v4.atomglobal.com/api/health
```

The live deployed marker for the current application baseline is:

```text
ecf57b8273cbbd74e4e37f3fc29c225dd9c6b082
```

## Approved V4 backup procedure

Current Git safety branch for the approved live application baseline:

```text
v4-live-backup-20260903-ecf57b8
```

Pre-change Git safety branch:

```text
v4-prechange-backup-20260903-3c21f04
```

Confirmed full pre-change server backup:

```text
/var/backups/growth-alignment-v4/prechange-20260903-042350
```

That server backup includes:

- validated compressed MariaDB dump for `growth_alignment_v4`;
- `/etc/growth-alignment/v4.env` with restricted permissions;
- deployed commit marker;
- source Git HEAD;
- active release reference;
- clean Git status snapshot.

Git alone does not contain all live CMS/database configuration.

## UAT focus after current fixes

Retest at minimum:

- Personal/New Joiner/Manager/Executive starts;
- 40 questions / 10 sections;
- halfway milestone after question 20;
- completion milestone after question 40;
- rapid mobile answering/autosave;
- stale `Load failed` clearing after successful save;
- resume persistence;
- Lite Report/Full Report lock;
- final Personal coffee value banner above checkout on desktop and mobile;
- final text reads `For less than a cup of coffee, find out more about yourself! ✨`;
- lively banner font renders correctly and the sparkle appears as an emoji where supported;
- old yellow coffee text is absent from inside the dark payment box;
- Pay by card still opens correctly;
- Admin UAT enabled → button/explanation visible;
- Admin UAT disabled → button/explanation absent;
- Stripe checkout open/cancel/return/retry;
- secure Full Report/PDF/email delivery;
- CMS image/logo/content edits reflected correctly without obsolete fallback behavior.

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
8. perform browser/mobile UAT;
9. update this README when the accepted baseline changes.

The current live V4 runtime and this README are the authoritative operational reference.
