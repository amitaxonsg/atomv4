# GAA / V4 Pre-Lite-Report Backup — 28 September 2026

This document records the production protection checkpoint created before drafting changes to the GAA Lite Report.

## Scope

This checkpoint is for the live GAA / V4 production environment only.

- Active production hostname: `https://gaa.atomglobal.com/`
- V4 compatibility hostname: `https://v4.atomglobal.com/`
- Database: `growth_alignment_v4`
- Source checkout: `/srv/v4.atomglobal.com/source`
- Active release tree: `/var/www/v4.atomglobal.com/current`
- Persistent storage: `/var/lib/growth-alignment-v4`
- Environment file: `/etc/growth-alignment/v4.env`
- GIA is out of scope and must not be modified during this GAA work.

## Git safety branches

The following safety branches preserve the pre-draft Git state:

```text
backup/gaa-before-lite-report-draft-20260928
backup/v4-before-lite-report-draft-20260928
```

Both were created before the Lite Report draft work.

## Full server backup

Backup directory:

```text
/var/backups/growth-alignment-v4/gaa-full-20260928-063927
```

Recorded size:

```text
113M
```

Backup contents recorded on the server:

- `growth_alignment_v4.sql.gz` — MariaDB production database dump
- `gaa-current-release.tar.gz` — active production release archive
- `gaa-source.tar.gz` — V4/GAA source checkout archive
- `gaa-storage.tar.gz` — persistent GAA storage archive
- `config/` — production environment/configuration copy
- `apache/` — Apache configuration copy
- `jobs/` — scheduled-job/cron copy
- `manifest.txt`
- `SHA256SUMS`

Recorded file sizes:

```text
growth_alignment_v4.sql.gz   1.5M
gaa-current-release.tar.gz   7.8M
gaa-source.tar.gz             28M
gaa-storage.tar.gz            76M
```

## SHA-256 checksums

```text
6d3dcac3bfa223579a3b9c67d1dfc125d9935408f526f669069923654dca1b0b  growth_alignment_v4.sql.gz
d507393731a20d920259e94520a0ac414a3c120efdda19375e1d8f50b7c10edb  gaa-current-release.tar.gz
e5001fa5557c93abc5850e87a960933ad5c8d7d73c14c4b0a17ad8ec991ea5d9  gaa-source.tar.gz
2987a2120e58896aa59750261dba88fcf89c676914cd0fe721dba08192dc1fcc  gaa-storage.tar.gz
```

## Change-control purpose

This checkpoint exists so the Lite Report draft can be developed with a known pre-change rollback point.

Before any deployment:

1. work from the approved GAA/V4 source and branches only;
2. do not touch GIA;
3. keep the backup above unchanged;
4. run the full automated test/build/PHP syntax gate;
5. review the code diff;
6. perform Lite/Full Report UAT;
7. deploy only after explicit approval;
8. verify active release, deployed commit marker and production health after deployment.

This documentation records the backup checkpoint only. It does not by itself change the live production application.
