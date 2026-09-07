# V4 Documentation Index

This `docs` directory contains operational and technical documentation for the isolated Atom Global V4 deployment.

## Current V4 operational notes

- [V4 Apache deployment](./V4-APACHE-DEPLOYMENT.md)
- [V4 Stripe webhook incident — 7 September 2026](./V4-STRIPE-WEBHOOK-INCIDENT-2026-09-07.md)
- [V4 Report Share Highlights](./V4-REPORT-SHARE-HIGHLIGHTS.md)

## Report Share Highlights — 7 September 2026

V4 now includes optional sharing at the end of both report modes:

- the locked Lite Report ends with **Share your Lite Report highlights**;
- the unlocked Full Development Report ends with a warm thank-you banner and **Share your Full Report highlights**.

The Full Report closing message is:

> Thank you for taking the assessment.
>
> If this is helpful, please share it with someone who will benefit from taking it!

The social sharing layer is deliberately privacy-restricted. It prepares only the participant's **profile name**, **top three strengths** and the **public V4 assessment root URL**. It never shares the Lite Report, Full Report, PDF, scores, answers, written notes, participant email, payment details, report token or private report URL.

The feature is presentation-only and does not change V4 scoring, question flow, Stripe checkout/webhooks, report unlocking, PDF generation, email delivery, retake logic or private-token behaviour.

A rollback point was created before implementation:

`backup/pre-share-highlights-2026-09-07`

See [V4 Report Share Highlights](./V4-REPORT-SHARE-HIGHLIGHTS.md) for the exact sharing boundary and acceptance checks.

## Stripe webhook incident status

Stripe reported repeated live-mode HTTP 500 delivery failures for:

`https://head-heart.atomglobal.com/api/stripe/webhook`

The failing URL named in Stripe's alert is **not** the isolated V4 hostname (`https://v4.atomglobal.com`). The incident is being tracked here so V4 maintainers can verify Stripe routing safely without changing the wrong environment.

No V4 code, database setting, Stripe credential, Apache configuration or deployment was changed by the webhook incident documentation update.

Before any V4 webhook change:

- confirm the failing destination in Stripe Dashboard Live mode;
- inspect one failed event's response/error details;
- confirm the endpoint belongs to the intended environment;
- verify the matching webhook signing secret without exposing it;
- inspect application logs / `stripe_webhook_events` when diagnosing V4;
- resend a failed event only after the root cause is corrected and confirm HTTP 2xx;
- verify payment → Full Report unlock → PDF/email fulfilment end to end.

See the incident note for the complete safe diagnosis and acceptance checklist.

## V4 isolation rule

V4 must remain isolated from previous Head–Heart/V3 deployment resources. Do not change V3/earlier databases, source paths, credentials, virtual hosts or webhook configuration while fixing or extending V4 unless a separate change is explicitly approved.
