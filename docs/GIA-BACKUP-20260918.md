# GIA Pre-Audit Backup — 18 September 2026

This document records the validated pre-audit backup for the isolated GIA environment.

## Scope

This backup is for **GIA only**.

- GAA / V4 production was not modified.
- The purpose of this checkpoint is to preserve GIA before any mirror audit or repair work.
- GAA may be used as a read-only comparison source during the audit.
- Any corrective work identified by the audit must be applied only to GIA unless separately authorized.

## Git safety points

- GIA working branch: `gia-live`
- Pre-audit Git backup branch: `backup/gia-before-audit-20260918`
- Additional pre-documentation safety branch: `backup/gia-before-backup-record-20260918`
- Shared code baseline used to initialize GIA Git tracking: `77e3db3ec2be48e235798c6c3918dc0cf054523b`

The shared baseline does **not** by itself prove that the current GIA server is byte-for-byte identical to GAA. The live GIA filesystem/database remain authoritative until the mirror audit is completed.

## Validated server backup

Backup directory:

```text
/var/backups/growth-alignment-gia/gia-full-20260918-041602
```

Recorded size at validation time: approximately **87 MB**.

Validated components:

- MariaDB database dump: `growth_alignment_gia.sql.gz`
- GIA frontend archive: `gia-frontend.tar.gz`
- GIA backend archive: `gia-backend.tar.gz`
- GIA persistent-storage archive: `gia-storage.tar.gz`
- environment/config backup directory: `config/`
- Apache configuration backup directory: `apache/`
- GIA cron/systemd job backup directory: `jobs/`
- backup manifest: `manifest.txt`
- checksum manifest: `SHA256SUMS`

Validation completed successfully for database, frontend, backend and storage archives.

## SHA-256 checksums

```text
1cd01b6cdced0084ecf0e38fe2a4fac8451bdb2f5157587fdc89a4718136ff57  growth_alignment_gia.sql.gz
682e335a30153c84ef61c61c8509be2d5c33cb839bb47b57b65027cbf942f4c1  gia-frontend.tar.gz
aef0503c8c373cad080915aaa72c63564c942d1f57e6cf3bb77c23f2ae39ed49  gia-backend.tar.gz
5d442d7d80d765c539e525e09f9002e1b08c217a5977d29866307999c4525150  gia-storage.tar.gz
```

## Change-control rule

Before changing GIA:

1. preserve this backup unchanged;
2. use GAA only for read-only comparison;
3. audit GIA health, filesystem, routes, database schema/data, Admin/CMS, email, PDF/reporting, sharing, Stripe, cron/background processing and URLs;
4. classify differences as expected isolation, harmless historical data, or actual GIA defects;
5. fix only confirmed GIA defects;
6. test GIA after every material change;
7. do not deploy to, restart, rewrite, or reconfigure GAA as part of GIA work.
