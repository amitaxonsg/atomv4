# Atom Global Growth Alignment — GIA

> **GIA ONLY — CURRENT WORKING BRANCH FOR MIRROR AUDIT**
>
> This branch documents and tracks the isolated GIA environment at `https://gia.atomglobal.com/`.
>
> **Do not modify GAA / V4 while working from this branch.** GAA is a read-only comparison source for the GIA audit.

The GIA environment is intended to mirror the approved Growth Alignment functionality while remaining isolated from GAA in hostname, environment, database, storage, scheduled processing and generated public links.

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
| Audit status | **BACKUP COMPLETE / MIRROR AUDIT PENDING** |

The Git baseline above is a safe starting point for GIA tracking. It must not be treated as proof that the current live GIA filesystem and database are identical to GAA. The live GIA server remains authoritative until the mirror audit is completed.

## Validated GIA pre-audit backup

The following backup was created and validated before any GIA audit or repair:

```text
/var/backups/growth-alignment-gia/gia-full-20260918-041602
```

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

```text
docs/GIA-BACKUP-20260918.md
```

## GIA mirror-audit objective

The audit must determine whether GIA mirrors the approved GAA functionality while preserving GIA-specific isolation.

Audit areas:

- public application and all four assessment tracks;
- questionnaire flow, autosave and resume;
- Lite Report and Full Report;
- PDF generation and the approved confidential cover;
- scoring and 10-area breakdown;
- Admin/CMS;
- participant and report history;
- Stripe checkout, webhook and reconciliation;
- UAT no-payment controls where applicable;
- email templates, queue and generated links;
- commitments and retakes;
- Lite-safe sharing;
- media and branding;
- public/base URLs;
- Apache routing;
- database schema and migrations;
- cron/background processing;
- storage and generated reports;
- old `gaa.atomglobal.com` or `v4.atomglobal.com` references that may incorrectly affect GIA.

Differences must be classified as:

1. **expected GIA isolation**;
2. **harmless historical data**;
3. **actual GIA defect requiring correction**.

Only category 3 should be repaired.

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
