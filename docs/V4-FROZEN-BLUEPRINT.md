# Growth Alignment V4 — Frozen Blueprint

Authoritative approval: Sunil Setpaul, 25 August 2026 (`V4 Blueprint confirmed`).
Consolidated answers received: 26 August 2026.

## Product and workflow

- Product name: Growth Alignment. Head and Heart remain assessment dimensions and profile terminology.
- Personal is fully separated from Professional (New Joiner, Manager and Executive).
- The approved V3 foundation remains intact: 40 live questions, 10 areas, 4 questions per page, autosave, secure resume, fresh/private sessions, responsive layouts, Lite Report, Full Development Report, PDF/email delivery, Stripe verification, refund/relock, manual UAT unlock and retest comparisons.
- Next and Back return the participant to the top of the relevant page.
- V3 branding assets and Atom Global logo remain authoritative. Personal and Professional may use distinct visual treatments from the supplied design packages without replacing the V3 application foundation.

## V4 reports

- Redesigned Full Report with a Highest 3 / Lowest 3 Executive Summary.
- Low/Mid/High ScaleBar and Head/Heart Meter presentations.
- Detailed ten-area analysis, radar, development guidance and roadmap remain.
- One- or two-area written commitment is stored in MariaDB and remains available through the private report.
- Suggested check-in and retest eligibility: 90 days.
- Talk to a Coach: Reeta Nathwani (`reeta.nathwani@atomglobal.com`) or Sunil Setpaul (`sunil.setpaul@atomglobal.com`).
- All new V4 text, images, prices and calls to action are stored as database-backed settings for the later CMS phase. No new CMS screens are part of this phase.

## Approved prices

| Track | Full Report | 90-day retest |
|---|---:|---:|
| Personal | US$4.99 | US$2.99 |
| New Joiner | US$19.95 | US$9.95 |
| Manager | US$49.95 | US$29.95 |
| Executive | US$99.95 | US$49.95 |

The former US$2 retake is discontinued. The same eligibility rule applies to every track: the original Full Report must have a verified payment and 90 days must have elapsed.

## Outstanding content value

Sunil did not provide the final payment sentence or its final report location. V4 therefore stores neutral payment wording in `reports.payment_wording` and its placement in `reports.payment_wording_location`. This content can be replaced in the database without code changes and exposed in the later CMS phase.

## Scope control

The 63-item blueprint and the consolidated answers above are frozen. During implementation and UAT, only defects against this scope are corrected. New features, pricing changes or redesigns are reviewed after V4 completion.

