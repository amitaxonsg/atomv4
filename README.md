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
| **Live deployed application commit** | `ed30269caf509642b773223cf61562f880ff513c` |
| Current live Git backup branch | `v4-live-backup-20260903-ed30269` |
| Previous live Git backup branch | `v4-live-backup-20260903-ecf57b8` |
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

## Approved changes — 2–3 September 2026

### Admin UAT No-Payment visibility

Admin uses `system.cash_on_delivery_enabled` as the authoritative UAT override. When disabled, the `UAT Test — No Payment` button and explanatory text must be absent. When explicitly enabled for controlled client testing, the no-payment UAT path may appear.

Primary fix:

`183027dc9ceefb156d0cad53b52664eb4b5d43b4`

### Personal checkout coffee value banner

Final approved Personal-only wording:

`For less than a cup of coffee, find out more about yourself! ✨`

Current accepted treatment:

- Personal assessment only;
- separate highlighted banner above the dark checkout panel;
- soft green-tinted background;
- green border with stronger left accent;
- lively **Libre Caslon Display** typography;
- real sparkle emoji with color-emoji fallbacks where supported;
- responsive desktop/mobile spacing;
- Stripe/UAT/report behavior unchanged.

Change history:

- initial supporting-text styling: `4c784a6cc5ffebb025f4ab27443fa03a94f2661b`
- green banner treatment: `7bf9eeb0106c14b5be7f341a3ccacef0d1d0985c`
- final wording and sparkle: `3c31cf6597002bbde3641867abf6bf778d3d0d7c`
- lively typography: `33b75de6b905ec3f650af5ad5e5bb17b7468dc85`
- regression test updated to approved copy: `ecf57b8273cbbd74e4e37f3fc29c225dd9c6b082`

### Mobile autosave race-condition fix

Rapid answering no longer allows an older failed save to leave a stale `Load failed` message after a newer save succeeds. Autosave requests are serialized and the latest successful save clears stale errors.

Implementation/regression commits:

- `03d3e998e4ee9f97ef734de06e290d934e5ddbb4`
- `d73684abf095063894b1dd90870a8ce3d2d37dc5`

### Halfway and completion milestones

Halfway and completion feedback use the approved lively milestone cards with stronger typography, gold accents, restrained animation, reduced-motion support and mobile-specific sizing.

Approved layout correction:

`4a31759fdc7b2aa28bef636167699991c152f1f4`

### 10-area radar and graphical score breakdown

The Full Development Report now maximizes the `Your 10-area radar and score breakdown` section instead of leaving a large unused area.

Approved live treatment:

- substantially larger radar chart;
- desktop layout uses the report width more efficiently;
- the 10 assessment areas are presented as clear graphical score bars/cards rather than relying on text alone;
- score labels and `x/25` values remain visible for precision;
- Low / Mid / High scale remains readable;
- graphical bars make differences between areas easier to distinguish at a glance;
- reduced dead/empty space around the chart;
- mobile stacks the radar and score graphs cleanly;
- report scoring, data values and business logic are unchanged.

Implemented, tested and deployed in:

`ed30269caf509642b773223cf61562f880ff513c`

This release passed **74/74 tests**, built successfully and deployed successfully through the V4 Apache release process. The post-deployment health endpoint returned `status: ok`. During the deployment background processing also successfully sent queued email item `287`.

## Approved visual state

The approved V4 presentation includes:

- desktop split layout: visual panel left, application content right;
- Atom Global public logo in the right/content panel;
- left visual headline/support copy positioned bottom-left on desktop;
- landing stage (`version`) intentionally uses the approved `/media/stages/reflection-portrait.png` when no CMS media assignment exists;
- inner-stage CMS/content images remain authoritative;
- mobile responsive presentation and iOS-safe controls;
- Personal green coffee value banner above checkout;
- enlarged 10-area radar plus graphical score breakdown with efficient use of report width.

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

V4 Admin is connected to the production API/database for Dashboard, Participants/history, Questionnaire experience/content, Assessments, Content stages/media, Branding, Reports/PDF, Payments/UAT, Email, Affiliates, Analytics, SEO/AEO/GEO, Settings/integrations, Admin users/permissions, Audit logs and Feedback/help.

CMS/database state is authoritative.

## Mobile autosave expected behavior

- answer change → `Saving…`;
- latest save succeeds → `Saved`;
- temporary failed save → error may be shown;
- subsequent successful latest save clears stale error automatically;
- browser refresh/resume preserves persisted answers.

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

### Post-deployment verification

```bash
echo "SOURCE:"
git rev-parse HEAD

echo "LIVE:"
cat /var/www/v4.atomglobal.com/deployed-commit.txt

curl -fsS https://v4.atomglobal.com/api/health
```

Current live application marker:

```text
ed30269caf509642b773223cf61562f880ff513c
```

## Approved V4 backup procedure

Current Git safety branch for the approved live application baseline:

```text
v4-live-backup-20260903-ed30269
```

Previous live backup:

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

That server backup includes a validated compressed MariaDB dump for `growth_alignment_v4`, protected `v4.env`, deployed commit marker, source Git HEAD, active release reference and clean Git status snapshot.

Git alone does not contain all live CMS/database configuration.

## UAT focus

Retest at minimum:

- Personal/New Joiner/Manager/Executive starts;
- 40 questions / 10 sections;
- halfway milestone after question 20;
- completion milestone after question 40;
- rapid mobile answering/autosave and stale-error recovery;
- resume persistence;
- Lite/Full Report lock;
- Personal coffee value banner and `✨` rendering on desktop/mobile;
- Pay by card flow;
- Admin UAT enabled/disabled behavior;
- Stripe open/cancel/return/retry;
- secure Full Report/PDF/email delivery;
- enlarged radar chart uses available space correctly;
- all 10 graphical score bars are readable and match their `x/25` values;
- Low / Mid / High scales display correctly;
- no large dead space remains in the radar/score section;
- mobile radar and graphical score list stack without overflow;
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
8. perform browser/mobile UAT;
9. update this README when the accepted baseline changes.

The current live V4 runtime and this README are the authoritative operational reference.
