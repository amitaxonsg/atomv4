# V4 Stripe webhook incident note — 7 September 2026

## Scope

This note is recorded in the **V4 repository only** for operational tracking. No V4 application code, Stripe configuration, database setting, Apache configuration or deployment has been changed by this documentation update.

## Stripe alert received

Stripe reported repeated live-mode webhook delivery failures for this endpoint:

`https://head-heart.atomglobal.com/api/stripe/webhook`

Stripe's alert states:

- first failure: **4 September 2026 at 03:04:12 UTC**;
- **20** delivery attempts returned **HTTP 500**;
- Stripe considers delivery successful only when the endpoint returns an HTTP **2xx** response;
- Stripe warned that retries for the failing endpoint may stop by **13 September 2026 at 03:04:12 UTC** if the issue remains unresolved.

## Important V4 isolation note

The failing endpoint named by Stripe is on `head-heart.atomglobal.com`, while the isolated V4 deployment is `https://v4.atomglobal.com`.

Therefore, **do not assume the V4 webhook itself is failing** and do not make cross-environment changes. V4 must remain isolated from V3/earlier Head–Heart deployment resources.

Before changing V4, first confirm in Stripe Dashboard which live webhook endpoint is failing and whether that endpoint is intended to serve the V4 payment flow.

## V4 webhook logic reference

V4 exposes:

`POST /api/stripe/webhook`

The V4 webhook flow:

1. reads the raw Stripe request body and `Stripe-Signature` header;
2. loads the V4 Stripe webhook signing secret from encrypted settings/environment;
3. verifies the Stripe signature;
4. deduplicates events using `stripe_webhook_events`;
5. processes supported events including `checkout.session.completed`, `checkout.session.async_payment_failed`, `checkout.session.expired` and `charge.refunded`;
6. on successful checkout completion, updates the local payment to `paid`, unlocks the Full Report, rotates private report access, records affiliate conversion/commission where applicable, and queues payment/report emails;
7. records processing failures and raises a critical `webhook_failed` notification when an event reaches V4 processing but fails internally.

## Safe diagnosis sequence

Do **not** delete endpoints, rotate secrets, change Price IDs or deploy code until the failed request is understood.

Use this order:

- Open Stripe Dashboard in **Live mode**.
- Open the webhook destination named in Stripe's alert.
- Open one failed delivery and capture the event type, HTTP status and response/error details.
- Confirm the webhook destination URL is the intended environment.
- Confirm the signing secret for that exact live endpoint matches the secret stored for the intended application environment. Never place the secret in Git, screenshots, chat or email.
- Check the application/PHP error log at the failed request timestamp.
- If diagnosing V4 specifically, inspect `stripe_webhook_events` for a matching event and read its `status` / `failure_reason` without exposing secrets.
- Review genuine successful Stripe payments since 4 September 2026 and confirm fulfilment: local payment status `paid`, Full Report unlocked, PDF/report state correct, and confirmation/report emails queued or sent.
- After the root cause is corrected, use Stripe's resend function for a failed event and verify the endpoint returns HTTP 200–299.

## Likely failure areas to verify

For V4, possible failure points include:

- missing or mismatched Stripe webhook signing secret;
- wrong live/test webhook secret for the endpoint;
- valid event received but corresponding local checkout payment record not found;
- missing/invalid survey-session metadata;
- database exception while updating payment/report state;
- report-unlock or private-link rotation failure;
- another exception raised during webhook fulfilment.

These are diagnostic possibilities only. No root cause is confirmed until Stripe request details and server/application logs are reviewed.

## Acceptance criteria for resolution

The incident is considered technically resolved only when:

- the intended live Stripe webhook endpoint returns **HTTP 2xx** for a resent valid event;
- the event is recorded/processed successfully in the intended application environment;
- a controlled payment flow produces the expected local `paid` state;
- the Full Report unlocks correctly;
- report/PDF access remains correct;
- payment confirmation and Full Report email delivery are verified;
- no unrelated V4/V3 environment is changed.

## Change-control rule

This document records the incident only. It does **not** authorise a V4 code or production change. Any V4 fix should be limited to the confirmed failing component, reviewed before deployment, and followed by a controlled payment/webhook regression test.
