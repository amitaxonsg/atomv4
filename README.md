# Head–Heart Alignment Digital Assessment Platform

Self-hosted React, PHP 8.3 and MariaDB assessment, reporting, payment, email, feedback and administration platform for Atom Global Consulting.

This is the independent V2 project. Do not reconnect it to the original repository or former Netlify project.

## Current status — 20 July 2026

| Item | State |
|---|---|
| Public URL | `https://head-heart.atomglobal.com/` |
| Admin URL | `https://head-heart.atomglobal.com/admin` |
| VPS | `161.97.137.234` |
| Git repository | `amitaxonsg/atomglobal-hhaa-v2` |
| Default branch | `main` |
| Production-ready foundation | PR #5 merged into `main` on 20 July 2026 |
| Branding and full-audit release | PR #7 deployed successfully as commit `03392964177784f3b60db760deb64f25e5ccfe3e` |
| CMS email hardening | PR #8 merged as `98dad4a5bedf0223ca15516ea2be0d6d1ebcb46c`; production deployment pending |
| Current production baseline commit | `03392964177784f3b60db760deb64f25e5ccfe3e` until PR #8 is deployed |
| Code acceptance | Production readiness checks run #436 passed frontend, PHP, database, exact questionnaire flow, temporary submission, report, Admin visibility, four email queues and automatic test cleanup |
| Public runtime | React frontend, PHP 8.3 API and MariaDB |
| Current production release confirmed | `/var/www/head-heart.atomglobal.com/releases/20260720072119-033929641777` |
| Current production marker confirmed | `03392964177784f3b60db760deb64f25e5ccfe3e` |
| Current observed public screen | Restored left branding with Personal, New Joiner, Manager and Executive active on the right |
| Production health in last output | Database, migrations, storage, email, GitHub feedback and cron healthy |
| Stripe | Not configured; checkout and signed-webhook acceptance remain pending |
| Owner login | Confirmed for `amit@axon.com.sg` |
| Git deployment timer | `head-heart-v2-sync.timer` must remain disabled and inactive |
| Application cron | Installed and healthy |

Production Admin and database verification confirmed exactly four published `2.0.0` assessments. All obsolete `1.0.0` questionnaire versions, the unfinished old session and its test participant data were removed after verified MariaDB backups.

## V3 UAT note — 23 August 2026

The isolated `sunil-v3-clean-40q-cms` branch is the V3 UAT build. It intentionally keeps each published assessment version's approved 50-question CMS/source bank intact for version history and rollback, while every new V3 participant session publishes a 40-question runtime: 10 areas × 4 questions. Existing in-progress sessions continue using their own immutable question snapshots. Production `main` is not changed by the V3 branch until UAT/merge/deployment is explicitly completed.

## Verified production experience

The `main` branch combines the approved questionnaire process with the approved visual branding:

- responsive desktop split screen with the reflective image on the left;
- transparent Atom Global logo over the image;
- CMS stage headline, supporting copy, focal point and overlay;
- warm cream content area and current typography;
- latest `index.html` questionnaire landing, introduction, intake and question process;
- mobile single-column layout with the public logo and no image panel;
- no **Powered by Axon 1Pro** footer on participant or report pages;
- Axon attribution retained only on admin login and the protected admin sidebar.

The public questionnaire displays all four approved assessment cards: Personal, New Joiner, Manager and Executive. Every card uses its own active published assessment version and CMS content.

## Four public assessment choices

The approved public landing displays Personal, New Joiner, Manager and Executive together in the right-hand content panel.

Each card uses its own active published assessment version. Landing copy and card descriptions remain editable in Admin → Questionnaire, while questions, sections, scoring and reports remain versioned under Admin → Assessments.

The legacy `liveTrackKey` remains available for backward compatibility and deployment verification, but it no longer hides the other three public choices.

Existing secure resume links continue using their original assessment version. Completed answers, scoring, reports and PDFs stay tied to immutable snapshots.

## V4 Growth Alignment — isolated release

The frozen V4 build is on branch `sunil-v4-growth-alignment-frozen` and is
deployed separately at `https://v4.atomglobal.com`. It retains the V3 40-live-
question process and approved branding while adding the Growth Alignment report
experience, 90-day retest rules and database-backed development commitments.
No new CMS interface is included in V4; editable V4 content is stored in the
database for the following CMS phase. Apache deployment details are in
`docs/V4-APACHE-DEPLOYMENT.md`. V4 must use its own source directory, release
root, database, storage, cron file and virtual host; it must not modify V3.

## Questionnaire process retained from the supplied `index.html`

1. Display all four approved assessment choices.
2. Show the track introduction and Heart/Head explanation.
3. Collect name, email, age range and optional gender.
4. Collect five assessment-specific context fields.
5. Optionally reveal Department and Level for configured work roles.
6. Record required privacy and transactional consent; marketing stays optional.
7. V3 presents 10 autosaved sections of four participant questions each (40 runtime questions); the approved CMS/source bank remains 50 questions per track.
8. Accept five scored choices or `N/A — doesn’t apply / can’t answer`.
9. Exclude N/A from scoring.
10. Save an optional note beneath every question.
11. Support secure resume from the private email link.
12. Generate the Lite Report after completion.
13. Reveal the Full Report only after verified payment or an authorised admin action.

Reference hashes and ownership are documented in `docs/QUESTIONNAIRE-INDEX-REFERENCE.md`.

## Admin → Questionnaire

The Questionnaire workspace manages:

- public landing heading and introduction paragraphs;
- track-card title prefix and track description;
- track introduction and Lite/Full Report offer text;
- Heart and Head labels and explanations;
- participant context labels and option lists;
- conditional role triggers, Department and Level fields;
- N/A availability;
- optional answer notes.

**Admin → Content** manages the responsive left-panel images and stage copy. **Admin → Branding** manages logos, core and questionnaire colours, heading/body fonts, page/body/question/option/field sizes, participant/question widths, desktop gutter and component radii. Branding never edits assessment wording, scoring or report profile logic.

## Production database baseline reset — 20 July 2026

The production database was intentionally reset to the current approved assessment baseline after a full reference audit:

- exactly four assessment-version rows remain;
- Personal, New Joiner, Manager and Executive are all published as `2.0.0`;
- each track contains the approved 10 sections, 50 questions, five answer choices and matching report profiles;
- no `1.0.0` questionnaire version, old session, old participant, answer, score or report remains;
- the attached `index.html` reference hashes remain recorded in global settings;
- full database backups were created before every cleanup stage.

Future completed submissions must remain pinned to their immutable assessment version and snapshots. Never delete a version that has a completed session, score or report.

## CMS email configuration and secret safety

Admin → Settings → Email is the authoritative source for the delivery provider, sender name, sender email, reply-to email, public URL, email logo/footer links, SMTP settings and SMTP2GO API key.

Production safeguards introduced in PR #8:

- outbound delivery no longer falls back to `amit@axon.com.sg` for the participant sender;
- SMTP2GO receives the CMS sender identity in `Name <email@domain>` format;
- SMTP and SMTP2GO credentials are read from encrypted CMS settings;
- the browser receives only a masked secret descriptor, never the decrypted credential;
- masked objects, empty secret fields and bullet/asterisk placeholders are ignored instead of overwriting the stored encrypted value;
- an obviously truncated SMTP2GO key is rejected with a validation error;
- `backend/bin/email-settings-audit.php` reports effective non-secret settings and whether secrets are configured, but never outputs passwords or API keys.

A blank password/API-key field means **keep the current stored credential**. Paste a full new credential only when intentionally rotating it.

## Full production audit and submission smoke test

`deploy/full-production-audit.sh` verifies services, immutable Nginx paths, API health, four CMS tracks, exactly four published `2.0.0` versions, database foreign-key integrity, report linkage, email templates, branding configuration, cron and recent backups.

Run deployment/audit scripts from the Git source checkout at `/srv/head-heart.atomglobal.com/source`; immutable runtime releases contain the frontend and backend application, not the Git operations workspace.

By default the audit is read-only. To create one temporary submission, verify Admin visibility, report generation and email queues, then remove the test records automatically:

```bash
cd /srv/head-heart.atomglobal.com/source
SMOKE_RECIPIENT=amit@axon.com.sg \
SMOKE_TRACK=personal \
bash deploy/full-production-audit.sh
```

Add `SMOKE_SEND_EMAIL=1` only when four real participant-flow test messages should be delivered to the chosen clean recipient. The smoke test refuses an email already present in the participant database.

## Assessment and historical-report protection

**Admin → Assessments** retains all four tracks and their versions. The interface and API display a permanent warning:

> Do not replace an existing question with a different question. A material meaning change can invalidate comparisons and report interpretation.

Safeguards:

- published and archived versions are immutable;
- draft questions permit spelling, grammar and clarity corrections only;
- question identity, section, position, required/active state and scoring direction are locked;
- the administrator must confirm meaning and scoring intent are unchanged;
- before/after wording is recorded in the audit log;
- a materially different question requires a separately reviewed assessment version;
- existing sessions preserve question and scoring snapshots;
- completion preserves answer, score and report snapshots.

Publishing a draft archives the previous published version for new starts but does not rewrite old sessions or reports.

## Deployment routing and rollback

Production Nginx is pinned to exact immutable release paths. A previous script changed the `current` symlink without changing the Nginx path, which caused an old frontend/API to remain served.

The corrected `deploy/update-vps.sh`:

- backs up the Head–Heart Nginx site file;
- backs up MariaDB;
- builds and tests a new immutable release;
- verifies the restored left-image frontend and four-card assessment client;
- atomically repoints the exact Nginx frontend and backend paths;
- validates Nginx before reload;
- verifies `/api/health`, `/api/public/assessment-experience`, compatibility `liveTrackKey` and four managed tracks;
- restores Nginx, symlink and markers on failure;
- keeps unrelated Nginx sites untouched.

Repeated `gatorinbox.com` conflicting-server-name messages are unrelated warnings. They do not fail `nginx -t`, and this project must not change those configurations.

## Feedback, help and email

The secure admin portal includes:

- Feedback and Help sections;
- searchable feedback states and permanent timeline;
- acknowledgement to `sunil.setpaul@atomglobal.com`;
- internal notification to `amit@axon.com.sg`;
- clarification and completion emails;
- GitHub issue creation/comments/closure when the restricted token is configured;
- editable email templates with preview, selected-template test, queue and retry;
- global search across operational records and feedback.

GitHub feedback synchronisation uses a fine-grained token restricted to `amitaxonsg/atomglobal-hhaa-v2` with **Issues: read and write**. Never place tokens, passwords or keys in Git, chat, feedback text or screenshots.

## Administration coverage completed in code

- Secure login/logout, CSRF, rate limiting, roles and permissions.
- Password reset.
- Dashboard trends, funnel, progress, revenue, email health and alerts.
- Participant search, filters, history, N/A, notes, export and anonymisation.
- Questionnaire, content and branding CMS.
- Assessment version cloning, protected correction and controlled publishing.
- Lite/Full Report content, unlock/lock/revoke/resend and PDF generation.
- Payments and signed Stripe webhook processing.
- Email templates, queue, provider IDs and retry.
- Affiliates, attribution, analytics, SEO/AEO/GEO and audit logs.
- Feedback workflow, GitHub Issues synchronisation and searchable help.

## Automated verification

The merged production-ready code passed:

- frontend tests and Vite production build;
- responsive split-layout and no-public-attribution assertions;
- questionnaire reference hashes;
- CMS landing, intake and conditional fields;
- four public assessment choices and independent server-side track validation;
- PHP lint and unit tests;
- clean MySQL migrations and seed;
- production integration acceptance;
- N/A persistence/exclusion, notes, autosave, resume, completion and scoring;
- temporary participant creation, V3 40-answer persistence, score and report generation while the 50-question CMS/source bank remains intact;
- Admin participant-detail visibility and all four participant-flow email queues;
- automatic removal of temporary smoke-test records;
- masked-secret protection and CMS-only outbound sender assertions;
- audit records;
- deployment and full-production-audit script syntax validation.

## VPS layout

```text
/srv/head-heart.atomglobal.com/source
/srv/head-heart.atomglobal.com/staging-source
/var/www/head-heart.atomglobal.com/releases
/var/www/head-heart.atomglobal.com/current
/var/www/head-heart-staging.atomglobal.com/current
/etc/head-heart-alignment/app.env
/etc/head-heart-alignment/staging.env
/var/lib/head-heart-alignment
/var/lib/head-heart-alignment-staging
/var/backups/head-heart-alignment
/var/backups/head-heart-alignment-staging
```

## Next deployment acceptance

1. Deploy merged `main` using `deploy/update-vps.sh` for production, or deploy the isolated V3 branch to the documented staging paths for UAT before merge.
2. Confirm the desktop public page retains the left image and approved branding.
3. Confirm mobile hides the image and shows the transparent logo.
4. Confirm public pages do not show Powered by Axon 1Pro.
5. Confirm admin login and sidebar still show Powered by Axon 1Pro.
6. Confirm `/api/public/assessment-experience` returns all four managed tracks.
7. Confirm Personal, New Joiner, Manager and Executive appear together on the right.
8. Confirm each card starts published CMS assessment version `2.0.0`.
9. Confirm each track retains the exact approved 10 sections and 50-question CMS/source bank, while each new V3 participant session publishes exactly 40 runtime questions (4 per area).
10. Confirm every new session is pinned to published CMS version `2.0.0`.
11. Test Questionnaire CMS landing, card, introduction and intake changes.
12. Run the guarded submission smoke test and verify participant, 40 runtime answers, score, report, Admin detail and four email queues before automatic test-data cleanup.
13. Run `backend/bin/email-settings-audit.php` and confirm the CMS sender identity without exposing secrets.
14. Confirm Admin → Settings → Email can be saved with blank secret fields without changing the stored SMTP2GO key.
15. Send one selected-template email test and confirm SMTP2GO shows the CMS sender identity.
16. Configure and test Stripe test keys, Price IDs and signed webhooks, including the USD 2 retake flow before production release.
17. Run `deploy/full-production-audit.sh` (or the compatibility wrapper `deploy/final-production-audit.sh`) from the Git source checkout and retain its output.
18. Record Amit and client acceptance after production verification.

## Safe deployment rule

Every deployment must back up the database and Head–Heart Nginx site, build and test a new immutable release, repoint exact Nginx paths, verify health and questionnaire configuration, retain rollback, and keep `head-heart-v2-sync.timer` disabled. New Git commits never alter production automatically.


## V4 operational runbook — German VPS

V4 is an isolated Apache deployment at `https://v4.atomglobal.com`. It must
never modify the V3 site, database, release path, cron or virtual host.

| Purpose | V4 value |
|---|---|
| Repository | `amitaxonsg/atomv4` |
| Deployment branch | `sunil-v4-growth-alignment-frozen` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Web releases | `/var/www/v4.atomglobal.com` |
| Environment | `/etc/growth-alignment/v4.env` |
| Database | `growth_alignment_v4` |
| Storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Deploy key | `/root/.ssh/atomv4_deploy` |

### Normal V4 update

```bash
cd /srv/v4.atomglobal.com/source
git pull --ff-only origin sunil-v4-growth-alignment-frozen
./deploy/update-v4-apache.sh
```

The deployer backs up only the V4 database, runs migrations and tests, builds
the frontend, atomically switches the V4 release, verifies the V4 health route
and installs the V4 cron job. It does not alter V3.

### First-install prerequisites

Before the first release: point `v4.atomglobal.com` DNS to the VPS, create
the dedicated V4 database/environment file, enable the V4 Apache site and
issue TLS. The canonical procedure is `docs/V4-APACHE-DEPLOYMENT.md`.

### Credentials and payments

SMTP2GO and Stripe credentials are stored as encrypted V4 database settings.
Transfer them only on the VPS using
`deploy/copy-v3-integrations-to-v4.php`; never place credentials in Git,
shell history, screenshots or chat. V4 requires dedicated Stripe Product/Price
IDs for its approved V4 prices; do not reuse V3 Price IDs.


### V4 PHP-FPM safety check

Before accepting a deployment, confirm that the API executes PHP and never returns
PHP source code:

```bash
curl --fail --silent --show-error \
  --resolve v4.atomglobal.com:443:127.0.0.1 \
  https://v4.atomglobal.com/api/health
```

The response must be JSON with `"status":"ok"`. A response beginning with
`<?php` is a security incident: disable only the V4 site with
`a2dissite v4.atomglobal.com.conf && systemctl reload apache2`, correct the
V4 PHP-FPM handler, and retest before re-enabling it.


## V4 German VPS — technical handover

This section is the operational source of truth for V4. It describes the
separate live deployment completed on the German VPS and is safe to share with
future technical maintainers. It intentionally contains no passwords, API
keys, tokens, database dumps or private report links.

### Server and software

| Component | Verified V4 setup |
|---|---|
| Public URL | `https://v4.atomglobal.com` |
| VPS | German VPS, `161.97.137.234` |
| Web server | Apache HTTP Server with `mod_rewrite`, `mod_headers`, `mod_ssl`, `mod_proxy` and `mod_proxy_fcgi` |
| PHP runtime | PHP 8.3-FPM, socket `/run/php/php8.3-fpm.sock` |
| Application | React/Vite frontend, PHP API, MariaDB |
| TLS | Let's Encrypt; certificate files under `/etc/letsencrypt/live/v4.atomglobal.com/` |
| Certificate renewal | Certbot scheduled renewal |
| Git repository | `git@github.com:amitaxonsg/atomv4.git` |
| Live branch | `sunil-v4-growth-alignment-frozen` |
| Mirror branch | `main` |

### Isolation boundaries

V4 is independent from V3. Never point V4 at a V3 database, source directory,
release directory, storage path, cron file, Apache virtual host or environment
file.

| Resource | V4 location |
|---|---|
| Git source | `/srv/v4.atomglobal.com/source` |
| Immutable releases | `/var/www/v4.atomglobal.com/releases/` |
| Active release symlink | `/var/www/v4.atomglobal.com/current` |
| Environment file | `/etc/growth-alignment/v4.env` |
| Environment directory owner/mode | `root:www-data`, `0750` |
| V4 database | `growth_alignment_v4` |
| Database account | `growth_alignment_v4@localhost` |
| Persistent storage | `/var/lib/growth-alignment-v4` |
| SQL backups | `/var/backups/growth-alignment-v4` |
| Application cron | `/etc/cron.d/growth-alignment-v4` |
| Apache site | `/etc/apache2/sites-available/v4.atomglobal.com.conf` |
| V4 PHP-FPM conf | `/etc/apache2/conf-available/v4-php-fpm.conf` |
| VPS Git deploy key | `/root/.ssh/atomv4_deploy` |

The source checkout is configured with its dedicated deploy key. Do not use the
key for other repositories and do not copy the private key off the VPS.

### Standard future update

Run as root on the German VPS:

```bash
cd /srv/v4.atomglobal.com/source
git pull --ff-only origin sunil-v4-growth-alignment-frozen
./deploy/update-v4-apache.sh
```

The deployer checks source/environment requirements, backs up only the V4
database, installs PHP dependencies, runs migrations and V4 tests, builds the
frontend, creates an immutable release, switches only the V4 `current`
symlink, reloads PHP-FPM/Apache, installs the V4 cron and verifies V4 health.
A failure after a release switch restores the prior V4 release.

### Apache and API security

The V4 API is an Apache alias at `/api` routed to:

```
/var/www/v4.atomglobal.com/current/backend/public
```

Its directory block must retain all of the following:

- `AcceptPathInfo On`
- internal rewrite of non-file/non-directory routes to `index.php`
- PHP-FPM `SetHandler` using `/run/php/php8.3-fpm.sock`
- frontend rewrite that explicitly excludes `/api/`

A health response beginning with `<?php` means PHP source is being exposed.
Treat that as a security incident: disable only the V4 site, repair the PHP-FPM
handler, test locally, then re-enable it. A valid response is JSON:

```bash
curl --fail --silent --show-error \
  --resolve v4.atomglobal.com:443:127.0.0.1 \
  https://v4.atomglobal.com/api/health
```

Expected minimum health state:

```json
{"status":"ok","checks":{"database":true,"migrations":true,"storage":true,"cron":true}}
```

After integration transfer, `stripe`, `stripeWebhook` and `email` should
also be `true`. `feedbackGitHub` is optional and is not a launch blocker.

### TLS and vhost validation

DNS for `v4.atomglobal.com` must resolve to the VPS before certificate
issuance. Validate Apache after every vhost change:

```bash
apache2ctl configtest
systemctl reload apache2
certbot certificates
```

Do not modify V3 Apache sites while maintaining V4.

### Credentials and Stripe

V3 SMTP2GO and Stripe live credentials were transferred into V4 through the
server-only helper. The helper uses isolated PHP processes so the V3 and V4
Composer autoloaders do not conflict:

```bash
php deploy/copy-v3-integrations-to-v4.php \
  /srv/head-heart.atomglobal.com/source/backend/src/bootstrap.php \
  /srv/v4.atomglobal.com/source/backend/src/bootstrap.php \
  --allow-live-stripe
```

It transfers values directly into V4 encrypted settings and does not print
credential values. Never put secrets in Git, shell history, screenshots, chat
or documentation.

The V4 Stripe account credentials may match V3, but V4 must have its own
Stripe Product/Price IDs for the approved V4 prices. Do not reuse V3 Price IDs.

### V4 burn-test record — 26 August 2026

The live V4 release completed the guarded production checks successfully. The
tests use a temporary participant and automatically remove all associated
database records and generated files after verification.

| Check | Verified result |
|---|---|
| Database persistence | Participant, survey session, all 40 answers, score snapshot, consent records and report were written to `growth_alignment_v4` and then removed cleanly. |
| Report journey | Lite Report, locked Full Report, authorised Full Report unlock, private report token and PDF generation passed. |
| SMTP2GO | Registration, resume, completion and Lite Report emails were accepted by the configured provider. |
| Health | Database, migrations, storage, Stripe credentials, Stripe webhook, email and cron returned healthy. |
| Content audit | All four tracks have complete Lite/Full content and score coverage from 50–250. |

Run the guarded checks from the V4 source checkout using a new test mailbox
that is not already a participant. `--send-email` sends four genuine test
messages; it must only be used with a mailbox the operator controls.

```bash
cd /srv/v4.atomglobal.com/source
php backend/bin/production-submission-smoke-test.php \
  --recipient='new-test-mailbox@example.com' \
  --track=personal \
  --send-email \
  --confirm=RUN-PRODUCTION-SUBMISSION-SMOKE

php backend/bin/production-report-flow-smoke-test.php \
  --recipient='another-new-test-mailbox@example.com' \
  --track=personal \
  --confirm=RUN-PRODUCTION-REPORT-SMOKE

php backend/bin/report-flow-audit.php
```

### Payment modes and launch control

V4 has two deliberately separate paths:

- **Temporary UAT Test — No Payment:** enabled for Sunil's controlled review.
  It unlocks the Full Report without charging a card and records an explicitly
  marked UAT/manual payment. It must not remain enabled at public launch.
- **Stripe card checkout:** the live secret and signed webhook are present, but
  public card buttons remain disabled until the four Full Report and four
  retest V4 Stripe Price IDs are created and stored. The eight V4 prices differ
  from V3, so V3 Price IDs must never be copied over.

After written UAT approval and immediately before public launch, disable the
temporary UAT path. This takes effect from the database settings immediately;
no Apache reload is needed.

```bash
cd /srv/v4.atomglobal.com/source
php -r '$c=require "backend/src/bootstrap.php"; $c["settings"]->set("payments.cash_on_delivery_enabled", "false"); $c["settings"]->set("system.cash_on_delivery_enabled", "false"); echo "UAT no-payment checkout disabled.\n";'
```

Then create the eight dedicated V4 Stripe Products/Prices, store their Price
IDs in V4, and complete one controlled card payment, signed-webhook, report
unlock and refund/relock test before enabling card checkout for the public.

### Release acceptance checklist

- Automated suite passes: 61 tests.
- Vite production build succeeds.
- Apache configuration validates.
- V4 health returns JSON, never PHP source.
- Database, migrations, storage, Stripe, webhook, email and cron are healthy.
- Confirm a real desktop and mobile journey for both Personal and Professional:
  track selection, 40 questions in 10 groups of four, autosave/resume, report,
  responsive layout and private link.
- Configure V4 Full Report and 90-day retest Stripe Price IDs, then complete a
  controlled payment/webhook test before enabling public checkout.
- Disable the temporary UAT no-payment route after written client approval and
  before the public launch.
- Keep the V4 CMS phase deferred: V4 content/settings are database-backed, but
  no new CMS screens are included in this release.
