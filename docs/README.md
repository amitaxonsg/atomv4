# V4 Documentation Index

This directory contains operational and feature documentation for the isolated Atom Global V4 deployment.

## Current V4 notes

- [V4 Apache deployment](./V4-APACHE-DEPLOYMENT.md)
- [V4 Report Share Highlights](./V4-REPORT-SHARE-HIGHLIGHTS.md)

## Report Share Highlights — 7 September 2026

V4 adds optional sharing at the end of both report modes:

- the Lite Report ends with **Share your Lite Report highlights**;
- the Full Development Report ends with a warm thank-you banner and **Share your Full Report highlights**.

The Full Report closing message is:

> Thank you for taking the assessment.
>
> If this is helpful, please share it with someone who will benefit from taking it!

The share layer is deliberately privacy-restricted. It prepares only the participant's profile name, top three strengths and the public V4 assessment root URL. It never shares the Lite Report, Full Report, PDF, scores, answers, notes, participant email, payment details, report token or private report URL.

The feature is presentation-only and must not change scoring, question flow, Stripe checkout/webhooks, report unlocking, PDF generation, email delivery, retake logic or private-token behaviour.

## Safe deployment note

The live/source branch in use on 7 September 2026 is `production-readiness-v4-mobile-final-20260902`. Share Highlights must be integrated on top of that branch rather than replacing it with the older `sunil-v4-growth-alignment-frozen` line.
