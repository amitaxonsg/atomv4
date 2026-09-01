# Growth Alignment V4 — Apache deployment

V4 is a separate site. It must never use the V3 source checkout, database,
storage, release directory, cron file or Apache virtual host.

| Item | V4 value |
|---|---|
| Domain | `v4.atomglobal.com` |
| Git branch | `sunil-v4-smooth-checkout-crm-blueprint` |
| Source checkout | `/srv/v4.atomglobal.com/source` |
| Web releases | `/var/www/v4.atomglobal.com` |
| Environment file | `/etc/growth-alignment/v4.env` |
| Storage | `/var/lib/growth-alignment-v4` |
| Backups | `/var/backups/growth-alignment-v4` |
| Cron | `/etc/cron.d/growth-alignment-v4` |
| Database | `growth_alignment_v4` |

## First installation

1. Point the `v4.atomglobal.com` A/AAAA record to this server and verify it
   resolves before issuing TLS.
2. Clone the repository into the V4 source path and check out the V4 branch.
3. Run `sudo deploy/install-v4-apache.sh` to create only the isolated V4
   folders, initial environment template and Apache site.
4. Create the dedicated MariaDB database and restricted database user. Fill in
   the V4 environment file with the generated V4 `APP_KEY` and database values.
5. Run `sudo CERTBOT_EMAIL=admin@atomglobal.com deploy/install-v4-apache.sh --issue-cert`.
6. Run `sudo deploy/update-v4-apache.sh`. It backs up the V4 database, runs
   migrations, builds a versioned release, switches only the V4 `current`
   symlink, configures the V4 cron, and verifies the V4 health endpoint.

## Integration transfer

After the first successful V4 deployment, transfer SMTP2GO and Stripe secrets
inside the server only. Do not copy or paste secrets into Git or this document:

```bash
cd /srv/v4.atomglobal.com/source
php deploy/copy-v3-integrations-to-v4.php \
  /srv/head-heart.atomglobal.com/source/backend/src/bootstrap.php \
  /srv/v4.atomglobal.com/source/backend/src/bootstrap.php \
  --allow-live-stripe
```

Create dedicated V4 Stripe Prices for Full Reports and retests before enabling
public payment. Price IDs are deliberately not reused from V3.

## UAT payment control and launch checklist

The temporary **UAT Test — No Payment** option is for controlled client review
only. It creates a clearly marked manual/UAT payment, unlocks the Full Report
and sends the normal report messages without creating a Stripe charge. Do not
leave it enabled once V4 is publicly launched.

After written UAT approval, disable both settings on the VPS:

```bash
cd /srv/v4.atomglobal.com/source
php -r '$c=require "backend/src/bootstrap.php"; $c["settings"]->set("payments.cash_on_delivery_enabled", "false"); $c["settings"]->set("system.cash_on_delivery_enabled", "false"); echo "UAT no-payment checkout disabled.\n";'
```

Before enabling live card checkout, create and configure eight dedicated V4
Stripe Price IDs: one Full Report and one 90-day retest price for each of
Personal, New Joiner, Manager and Executive. Finish one controlled payment,
signed webhook, report unlock and refund/relock test. The live credentials and
webhook alone do not make checkout ready; a configured V4 Price ID is required
for every public buy button.

## Burn-test procedure

The following guarded commands verify V4 database persistence, reporting/PDF
and real SMTP2GO acceptance. They clean their temporary participant, survey,
report, email-queue and generated-file records afterwards. Use newly created
test mailboxes only.

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
  --send-email \
  --confirm=RUN-PRODUCTION-REPORT-SMOKE

php backend/bin/report-flow-audit.php
curl --fail --silent --show-error --resolve v4.atomglobal.com:443:127.0.0.1 \
  https://v4.atomglobal.com/api/health
```

With `--send-email`, the report-flow burn test uses the guarded UAT checkout,
processes both post-checkout messages immediately, generates the professional
Full Development Report PDF attachment and requires a provider message ID for
the accepted `paid_report_ready` email. If immediate delivery fails, the queue
retains its normal retry state and the burn test fails instead of reporting a
false success.

## Rollback

Application rollback is an atomic change of the V4 `current` symlink to the
previous V4 release. Database migrations are forward-only; take a new backup
before any database restore decision.
