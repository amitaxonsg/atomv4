# V4 Report Share Highlights

Date: 7 September 2026
Scope: Atom Global V4 only (`amitaxonsg/atomv4`, branch `sunil-v4-growth-alignment-frozen`)

## Purpose

Add optional social sharing at the end of the Lite Report and the Full Development Report without exposing either report itself.

The feature is intentionally isolated from assessment scoring, report generation, payment, Stripe webhook handling, report unlocking, PDF generation, email delivery, resume tokens and private report access.

## Placement

### Lite Report

The final part of the locked Lite Report now includes **Share your Lite Report highlights** after the Full Report purchase/UAT information.

### Full Development Report

The final part of an unlocked Full Development Report now includes:

> Thank you for taking the assessment.
>
> If this is helpful, please share it with someone who will benefit from taking it!

The message is presented as a warm, coffee-style thank-you banner, followed by **Share your Full Report highlights**.

## What can be shared

Only the following safe highlight fields are prepared for sharing:

- profile name;
- top three strengths;
- the public V4 assessment root URL.

The social/share text is generated in the browser from this restricted set only.

## What must never be shared

The share feature does not include:

- Lite Report content;
- Full Development Report content;
- PDF or PDF URL;
- total or area scores;
- development observations/watchouts;
- questionnaire answers;
- participant notes or written reflections;
- participant name or email address;
- payment information;
- report access token or private report URL;
- resume token or resume URL;
- Full Report paid content.

The existing private **Copy as text** and **Email to self** actions remain private report-saving tools and are labelled separately from social sharing.

## Share actions

- **Share on LinkedIn** opens LinkedIn's standard share dialog for the public assessment URL. The safe highlight text is copied to the clipboard first when browser permission allows, so the participant may paste it into the post.
- **Share on Facebook** opens Facebook's standard share dialog with the public assessment URL and safe highlight text.
- **Copy highlights** copies only the approved highlight text and public assessment URL.

No application credential or private report token is sent to either social platform.

## Isolation / no-conflict rule

This feature is presentation-only. It does not change:

- the 40-question V4 assessment runtime;
- assessment versioning or scoring;
- Lite/Full report data generation;
- report lock/unlock logic;
- Stripe checkout or webhook processing;
- cash-on-delivery/UAT bypass logic;
- PDF generation;
- report email queueing;
- retake eligibility or payment rules;
- participant/resume/report token security.

A backup branch was created before implementation:

`backup/pre-share-highlights-2026-09-07`

## Acceptance checks

- Locked Lite Report shows the share-highlights section at the end.
- Unlocked Full Development Report shows the thank-you banner and share-highlights section at the end.
- Share preview contains profile name and at most three strengths only.
- LinkedIn/Facebook use the public assessment root URL, never the current private report URL.
- Copy highlights contains no score, answers, notes, email, PDF URL or private token.
- Existing New assessment, Open PDF, Print report, Copy as text and Email to self actions continue to work.
- Existing assessment, payment, webhook, PDF and email tests remain unchanged except for the added static privacy checks in `tests/js/report-flow.test.mjs`.
