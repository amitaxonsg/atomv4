# GIA Mirror Audit — 18 September 2026

## Scope and protection rule

This audit covers the isolated GIA environment at `https://gia.atomglobal.com/`.

**GAA / V4 was used only as a read-only comparison source. No GAA deployment, database write, configuration change, service restart, branch movement or release-tree change was performed as part of this audit.**

## Environment

- Public URL: `https://gia.atomglobal.com/`
- Admin URL: `https://gia.atomglobal.com/admin`
- Database: `growth_alignment_gia`
- Frontend: `/var/www/gia.atomglobal.com`
- Backend: `/var/www/gia.atomglobal-backend`
- Environment file: `/etc/growth-alignment/gia.env`
- Storage: `/var/lib/growth-alignment-gia`
- Git branch: `gia-live`
- Pre-audit backup: `/var/backups/growth-alignment-gia/gia-full-20260918-041602`

## Audit result

### PASS — filesystem and application parity

- GIA frontend matched the approved GAA frontend byte-for-byte in the audited comparison.
- GIA backend matched the approved GAA backend byte-for-byte, excluding intended environment/vendor/storage differences.
- PHP syntax checks passed for the audited GIA entry points and report/PDF services.
- Apache routes resolve GIA to GIA-specific frontend/backend paths.

### PASS — database structure and migrations

- Both environments contain 47 tables.
- Migration history matches through `019_growth_alignment_escaped_terminology.sql`.
- Normalized database schema comparison passed; observed raw schema differences were limited to environment-specific AUTO_INCREMENT counters.

### PASS — CMS/content parity

The following tables matched byte-for-byte and by row count where checked:

- `assessment_tracks`
- `assessment_track_settings`
- `assessment_versions`
- `assessment_sections`
- `questions`
- `answer_options`
- `report_templates`
- `email_templates`
- `content_stages`
- `branding_revisions`
- `seo_pages`
- `roles`
- `permissions`
- `role_permissions`
- `retention_policies`
- `media_library`
- `affiliates`
- `admin_alert_recipients`

Core row-count parity included:

- assessment tracks: 4 / 4
- track settings: 4 / 4
- versions: 4 / 4
- sections: 40 / 40
- questions: 200 / 200
- answer options: 20 / 20
- report templates: 16 / 16
- email templates: 27 / 27
- content stages: 11 / 11
- branding revisions: 4 / 4
- SEO pages: 6 / 6
- roles: 9 / 9
- permissions: 17 / 17
- role permissions: 55 / 55
- retention policies: 5 / 5
- media library: 62 / 62

### PASS — admin and public configuration

- Admin user/role configuration matched between GAA and GIA.
- `/api/public/configuration` matched.
- `/api/public/assessment-experience` matched.
- Normalized non-secret global settings matched after excluding intentional environment/runtime values.

Intentional GIA differences:

- `email.public_base_url=https://gia.atomglobal.com`
- independent `system.cron_last_run`
- independent runtime rate-limit counters
- temporary GIA-only UAT no-payment override while client UAT is active

### GIA-only defects corrected during audit

1. **Wrong public email/report base URL**
   - Before: `email.public_base_url=https://gaa.atomglobal.com`
   - Corrected to: `https://gia.atomglobal.com`

2. **Incomplete background scheduler**
   - GIA previously had an email-only scheduled worker.
   - GIA now runs the full application cron:
     `/usr/bin/php /var/www/gia.atomglobal-backend/bin/cron.php`
   - The old email-only cron was removed from the active `/etc/cron.d/` path to prevent duplicate queue processing.
   - Health now reports `cron:true`.

3. **Old internal UAT reminder candidates**
   - Previously identified internal test survey sessions were suppressed from further abandoned-survey reminders before the full cron was enabled.

### PASS — background processing

Verified full GIA cron behavior:

- abandoned-survey scheduling executes
- Stripe reconciliation executes
- administrator alert processing executes
- PDF generation task participates in the full runner
- email queue processing executes
- `system.cron_last_run` updates naturally
- no duplicate standalone GIA email cron remains active
- GIA health reports:
  - database: true
  - migrations: true
  - storage: true
  - stripe: true
  - stripeWebhook: true
  - email: true
  - cron: true
  - feedbackGitHub: false (optional/non-blocking)

### PASS — four-track functional UAT

Recent completed GIA UAT coverage exists for all four tracks:

- Personal
- New Joiner
- Manager
- Executive

For the latest audited UAT completion on each track:

- assessment completed
- report generated
- UAT no-payment/manual unlock completed
- Full Report unlocked
- PDF generated successfully

### PASS — GIA report/email URL isolation

Recent UAT report/payment email payloads were checked for old domains.

Result:

- no `gaa.atomglobal.com` references
- no `v4.atomglobal.com` references
- report URLs use `https://gia.atomglobal.com/report/...`

The additional `paid_report_ready` messages observed on some UAT reports are consistent with participant-triggered report-email actions. The public report-email route queues `paid_report_ready` without creating an administrator audit-log record.


## Verified evidence from today's final audit pass

### Four-track UAT records

The latest completed GIA UAT records reviewed today were:

| Track | Session | Report | Unlock reason | PDF |
|---|---:|---:|---|---|
| Personal | 151 | 133 | `cash_on_delivery_manual` | Ready |
| New Joiner | 152 | 134 | `cash_on_delivery_manual` | Ready |
| Manager | 145 | 128 | `cash_on_delivery_manual` | Ready |
| Executive | 156 | 138 | `cash_on_delivery_manual` | Ready |

### Payment coverage observed

- Executive: 5 manual UAT payments
- Manager: 2 manual UAT payments
- New Joiner: 5 manual UAT payments
- Personal: 48 manual UAT payments
- Personal: 13 Stripe `checkout_started` records
- Personal: 4 historical Stripe `paid` records

A current real Executive Stripe payment remains the final client/Sunil UAT item.

### Cron/background-processing evidence

After the full GIA cron was installed:

- Stripe reconciliation checked the recent pending checkout records with `recovered 0; failures 0`;
- administrator alert processing returned zero pending events;
- email queue processing completed successfully;
- `system.cron_last_run` updated normally;
- `/api/health` reported `cron:true`;
- only `/etc/cron.d/growth-alignment-gia` remained active for GIA;
- no separate `process-email-queue.php` cron remained active.

Recent queue items reviewed during the audit included:

- `782` — `survey_resume_link`
- `787` — `paid_report_ready`
- `789` — `survey_resume_link`

These were normal GIA UAT participant/report activity and were not linked to `abandoned_survey_events`.

### Report-email URL evidence

Recent UAT report/payment email payloads for reports `128`, `133`, `134`, and `138` were checked.

Observed report URLs were under:

`https://gia.atomglobal.com/report/...`

No recent payload in that audited set contained:

- `gaa.atomglobal.com`
- `v4.atomglobal.com`

The additional `paid_report_ready` emails on reports 128, 133 and 134 were created after the initial UAT payment emails and are consistent with participant-side report-email actions. No matching admin report audit actions were present.

## Temporary UAT state

The GIA UAT no-payment override is currently enabled for client testing:

`system.cash_on_delivery_enabled=true`

This is intentional while UAT is active. It must be disabled after the final paid-payment UAT is accepted.

## Remaining final UAT item

**Executive real Stripe payment test — pending client/Sunil UAT.**

Current audit evidence confirms Stripe configuration, webhook health and reconciliation are operational. Historical Stripe-paid coverage exists in GIA, but the final current Executive paid-flow validation should verify:

1. real checkout opens from the Executive Lite Report;
2. Stripe payment completes successfully;
3. webhook/reconciliation records the payment;
4. Full Report unlocks;
5. secure report URL remains on `gia.atomglobal.com`;
6. PDF is available;
7. payment/report emails are delivered with GIA-only links.

After acceptance:

- disable `system.cash_on_delivery_enabled` in GIA;
- verify `/api/health` remains OK;
- record the final paid UAT result in this document/README.

## Overall status

**MIRROR AUDIT PASSED FOR CODE, SCHEMA, CMS CONTENT, CONFIGURATION, FOUR-TRACK UAT, REPORT/PDF, EMAIL URL ISOLATION AND BACKGROUND PROCESSING.**

**Final production-style Executive Stripe payment UAT remains pending.**
