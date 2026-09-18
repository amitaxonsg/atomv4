# Atom Global Growth Alignment — GIA

> **GIA ONLY — CURRENT WORKING BRANCH FOR MIRROR AUDIT / UAT**
>
> This branch documents and tracks the isolated GIA environment at `https://gia.atomglobal.com/`.
>
> **Do not modify GAA / V4 while working from this branch.** GAA is a read-only comparison source for the GIA audit.

The GIA environment mirrors the approved Growth Alignment functionality while remaining isolated from GAA in hostname, environment, database, storage, scheduled processing and generated public links.

## Current GIA control state — 18 September 2026

| Item | Current GIA value |
|---|---|
| Public URL | `https://gia.atomglobal.com/` |
| Admin URL | `https://gia.atomglobal.com/admin` |
| Repository | `amitaxonsg/atomv4` |
| GIA working branch | `gia-live` |
| GIA Git safety branch | `backup/gia-before-audit-20260918` |
| Additional doc safety branch | `backup/gia-before-backup-record-20260918` |
| Git initialization baseline | `77e3db3ec2be48e235798c6c3918dc0cf054523b` |
| Database | `growth_alignment_gia` |
| Frontend path | `/var/www/gia.atomglobal.com` |
| Backend path | `/var/www/gia.atomglobal-backend` |
| Backup root | `/var/backups/growth-alignment-gia` |
| Confirmed pre-audit backup | `/var/backups/growth-alignment-gia/gia-full-20260918-041602` |
| GAA / V4 modification policy | **READ ONLY — DO NOT TOUCH** |
| Mirror audit status | **PASSED — FINAL EXECUTIVE STRIPE UAT PENDING** |
| GIA UAT no-payment override | **ENABLED TEMPORARILY FOR CLIENT UAT** |
| GIA full cron | **ENABLED / HEALTHY** |

## Mirror-audit result

The 18 September 2026 mirror audit passed for:

- frontend and backend parity;
- database schema and migration parity;
- all four assessment tracks;
- 200-question CMS/questionnaire content;
- report templates and email templates;
- branding, media and SEO content;
- roles/permissions and admin-user configuration;
- public configuration and assessment-experience APIs;
- normalized non-secret settings;
- GIA-specific hostname/base URL isolation;
- full cron/background processing;
- UAT no-payment unlock flow on Personal, New Joiner, Manager and Executive;
- Full Report unlock and PDF generation on all four tracks;
- GIA-only report URLs in recent payment/report emails.

Detailed audit record:

`docs/GIA-MIRROR-AUDIT-20260918.md`

### Confirmed GIA-only corrections

- corrected `email.public_base_url` from GAA to `https://gia.atomglobal.com`;
- replaced the incomplete email-only scheduler with the full GIA application cron;
- removed the old standalone email cron from the active cron directory to prevent duplicate processing;
- suppressed previously identified old internal UAT reminder candidates before enabling the full scheduler.

Current GIA health reports `cron:true`.

`feedbackGitHub:false` remains optional/non-blocking.


## Work completed today — 18 September 2026

Today's GIA-only audit/repair work is complete and recorded on `gia-live`.

Verified today:

- validated the full pre-audit GIA backup at `/var/backups/growth-alignment-gia/gia-full-20260918-041602`;
- confirmed frontend and backend parity against GAA using read-only comparisons;
- confirmed 47-table schema/migration parity;
- confirmed byte-for-byte CMS/content parity for assessment, question, report, email, branding, SEO, role/permission, media, affiliate and alert-recipient configuration tables;
- confirmed admin-user parity;
- confirmed public configuration and assessment-experience API parity;
- corrected GIA `email.public_base_url` to `https://gia.atomglobal.com`;
- enabled the full GIA background cron and removed the old standalone email-only cron from the active cron directory;
- confirmed GIA health reports `cron:true`;
- confirmed no duplicate GIA email worker is active;
- verified recent cron runs reconcile Stripe with zero failures and process the email queue normally;
- verified UAT no-payment works on Executive after restoring the temporary GIA-only override to `true`;
- confirmed recent successful UAT coverage for Personal, New Joiner, Manager and Executive;
- confirmed the latest audited UAT report for all four tracks is unlocked and has a generated PDF;
- confirmed recent payment/report emails use only `gia.atomglobal.com` report URLs and contain no GAA/V4 domains;
- classified recent queue items as normal UAT/resume/report email activity rather than abandoned-survey noise.

Latest audited four-track GIA UAT evidence:

| Track | Session | Report | Unlock | PDF |
|---|---:|---:|---|---|
| Personal | 151 | 133 | UAT no-payment/manual | Ready |
| New Joiner | 152 | 134 | UAT no-payment/manual | Ready |
| Manager | 145 | 128 | UAT no-payment/manual | Ready |
| Executive | 156 | 138 | UAT no-payment/manual | Ready |

Current temporary UAT setting:

`system.cash_on_delivery_enabled=true`

Leave this enabled until Sunil completes the final real Executive Stripe payment UAT. After acceptance, set it back to `false`, verify health, and record the final sign-off.

## Remaining final UAT

The final outstanding item is a **current real Stripe payment test for the Executive assessment**, to be performed on GIA by the client/Sunil.

The test should confirm:

1. Executive Stripe checkout opens;
2. real payment completes;
3. webhook/reconciliation records the payment;
4. Full Report unlocks;
5. report URL remains under `gia.atomglobal.com`;
6. PDF is available;
7. payment/report emails contain GIA-only links.

After this test is accepted, disable the temporary GIA UAT no-payment override:

`system.cash_on_delivery_enabled=false`

Then verify GIA health again and update the audit record.

## Validated GIA pre-audit backup

The following backup was created and validated before any GIA audit or repair:

`/var/backups/growth-alignment-gia/gia-full-20260918-041602`

Validated components:

- `growth_alignment_gia.sql.gz` — MariaDB database dump;
- `gia-frontend.tar.gz` — GIA frontend;
- `gia-backend.tar.gz` — GIA backend;
- `gia-storage.tar.gz` — GIA persistent storage;
- `config/` — GIA environment/configuration backup;
- `apache/` — GIA Apache configuration backup;
- `jobs/` — GIA cron/systemd job backup;
- `manifest.txt`;
- `SHA256SUMS`.

Archive validation status:

```text
Database: OK
Frontend: OK
Backend: OK
Storage: OK
```

SHA-256:

```text
1cd01b6cdced0084ecf0e38fe2a4fac8451bdb2f5157587fdc89a4718136ff57  growth_alignment_gia.sql.gz
682e335a30153c84ef61c61c8509be2d5c33cb839bb47b57b65027cbf942f4c1  gia-frontend.tar.gz
aef0503c8c373cad080915aaa72c63564c942d1f57e6cf3bb77c23f2ae39ed49  gia-backend.tar.gz
5d442d7d80d765c539e525e09f9002e1b08c217a5977d29866307999c4525150  gia-storage.tar.gz
```

Full backup record:

`docs/GIA-BACKUP-20260918.md`

## Mandatory GAA protection rule

During GIA work:

- do not edit GAA files;
- do not alter the `growth_alignment_v4` database;
- do not change `/etc/growth-alignment/v4.env`;
- do not deploy GAA;
- do not restart or reconfigure GAA as part of GIA work;
- do not overwrite the GAA release tree;
- do not move `gaa-live-recovery` or `production-readiness-v4-mobile-final-20260902`;
- use GAA only for read-only comparisons.

## GIA change-control procedure

Before a confirmed GIA fix:

1. verify the pre-audit backup remains intact;
2. inspect the exact GIA defect;
3. make the smallest GIA-only change;
4. run syntax/tests relevant to that change;
5. compare expected versus actual GIA behavior;
6. verify `https://gia.atomglobal.com/api/health`;
7. perform browser/Admin/PDF/email UAT as relevant;
8. commit only to `gia-live`;
9. update this README when the accepted GIA baseline changes.

## Git policy

`gia-live` is the working Git branch for GIA.

The branches below remain separate and must not be moved during GIA audit work:

```text
gaa-live-recovery
production-readiness-v4-mobile-final-20260902
```

The pre-audit GIA restore/safety branches are:

```text
backup/gia-before-audit-20260918
backup/gia-before-backup-record-20260918
```

Git alone does not contain all live GIA database, environment, storage or CMS state. The validated server backup remains the authoritative restore point before the audit.
