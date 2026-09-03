# V4 Live Baseline — 3 September 2026

**V4 only.**

Approved live application commit:

`7bf9eeb0106c14b5be7f341a3ccacef0d1d0985c`

Deployment URL: `https://v4.atomglobal.com/`

Deployment verification:

- 74/74 frontend tests passed.
- Vite production build passed.
- Apache release deployment completed successfully.
- `/api/health` returned `status: ok`.
- Database, migrations, storage, Stripe, Stripe webhook, email and cron were healthy.
- `feedbackGitHub:false` remains optional and is not a production blocker.

## Accepted checkout visual change

The Personal assessment checkout message:

`For less than a cup of coffee, find out more about yourself. ✦`

is now presented as a separate highlighted value banner above the dark payment panel. The treatment is Personal-only and does not change Stripe checkout, UAT No-Payment logic, report unlock logic, PDF/email delivery or CMS behavior.

Implemented in:

`7bf9eeb0106c14b5be7f341a3ccacef0d1d0985c`

## Recovery references

Pre-change Git backup branch:

`v4-prechange-backup-20260903-3c21f04`

Pre-change server backup:

`/var/backups/growth-alignment-v4/prechange-20260903-042350`

The server backup contains the deployed commit marker, source HEAD, active release reference, environment file and a validated compressed MariaDB dump for `growth_alignment_v4`.
