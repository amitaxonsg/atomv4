# V4 Documentation Index

This `docs` directory contains operational and technical documentation for the isolated Atom Global V4 deployment.

## Current V4 operational notes

- [V4 Apache deployment](./V4-APACHE-DEPLOYMENT.md)
- [V4 Stripe webhook incident — 7 September 2026](./V4-STRIPE-WEBHOOK-INCIDENT-2026-09-07.md)

## Stripe webhook incident status

Stripe reported repeated live-mode HTTP 500 delivery failures for:

`https://head-heart.atomglobal.com/api/stripe/webhook`

The failing URL named in Stripe's alert is **not** the isolated V4 hostname (`https://v4.atomglobal.com`). The incident is being tracked here so V4 maintainers can verify Stripe routing safely without changing the wrong environment.

No V4 code, database setting, Stripe credential, Apache configuration or deployment was changed by this documentation update.

Before any V4 change:

- confirm the failing destination in Stripe Dashboard Live mode;
- inspect one failed event's response/error details;
- confirm the endpoint belongs to the intended environment;
- verify the matching webhook signing secret without exposing it;
- inspect application logs / `stripe_webhook_events` when diagnosing V4;
- resend a failed event only after the root cause is corrected and confirm HTTP 2xx;
- verify payment → Full Report unlock → PDF/email fulfilment end to end.

See the incident note for the complete safe diagnosis and acceptance checklist.

## V4 isolation rule

V4 must remain isolated from previous Head–Heart/V3 deployment resources. Do not change V3/earlier databases, source paths, credentials, virtual hosts or webhook configuration while fixing V4 unless a separate change is explicitly approved.
