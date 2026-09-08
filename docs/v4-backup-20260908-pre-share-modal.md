# V4 pre-share-modal backup — 8 September 2026

V4 only. This backup was taken before changing the Share highlights interaction to an in-page modal.

## Git safety branch

`v4-pre-share-modal-20260908-7151c7b`

Created from V4 branch head:

`7151c7b8fb57b2785b1ef051f836af23a7810b65`

## Full server backup

`/var/backups/growth-alignment-v4/pre-share-modal-20260908T024637Z`

The backup contains:

- `current-release.txt`
- `deployed-commit.txt`
- `growth-alignment-v4`
- `growth-alignment-v4-storage.tar.gz`
- `growth_alignment_v4.sql.gz`
- `source-commit.txt`
- `v4.env`

Captured archive sizes:

- persistent storage archive: approximately 29 MB
- MariaDB dump: approximately 1.1 MB

The backup command used strict shell error handling and completed after `gzip -t` validation of the database and storage archives plus `tar -tzf` validation of the storage archive.

## Verified live runtime at backup time

Active release:

`/var/www/v4.atomglobal.com/releases/20260908015314-57dd98f5d55f`

Deployed commit:

`57dd98f5d55f4fc526c42e6b8ed5b035bb67f802`

Server source commit:

`57dd98f5d55f4fc526c42e6b8ed5b035bb67f802`

The active release, deployed commit marker and server source commit matched exactly before the share-modal work began.

## Scope

This is a V4 rollback point only. Do not use V3, V5, another repository or another database as a V4 rollback source.
