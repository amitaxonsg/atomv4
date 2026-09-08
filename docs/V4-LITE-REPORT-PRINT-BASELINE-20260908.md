# V4 Lite Report Print Baseline — 8 September 2026

Status: **APPROVED / ACCEPTED**

V4 only.

## Accepted live application commit

`a1d3c7a76c5d7745e2f9099e9459dc6de03d2dc6`

## Reference output

User-approved browser Print Report output:

`Growth Alignment Assessment _ Atom Global Consulting(1).pdf`

This is the accepted visual/content reference for the **V4 Lite Report print output**.

## Accepted Lite Report print scope

The printed Lite Report contains only the Lite Report presentation:

- Atom Global branding;
- Growth Alignment track/result title;
- profile title;
- participant introductory line;
- overall score and `OUT OF 250`;
- `Your alignment pattern` narrative;
- Head-led / current score / Heart-led meter;
- Top three strengths;
- Development observations.

The accepted one-page print output does **not** include interactive website controls or commercial UI:

- no Pay by card section;
- no UAT Test — No Payment control;
- no Full Report upgrade/payment box;
- no Share highlights controls/modal;
- no Start again button;
- no Print report button;
- no questionnaire side visual/navigation.

Browser-generated headers/footers such as date, page title, URL and page number are controlled by the browser print dialog and are not application content. For a clean saved PDF, disable browser **Headers and footers**.

## Implementation

The accepted V4 print treatment is provided by:

- `src/report-print-v4.css`;
- final stylesheet import in `src/main.jsx`;
- regression guards in `tests/js/full-report-hero-parity.test.mjs`.

The Lite Report print fix passed the V4 automated gate with **82/82 tests** and a successful Vite production build before deployment.

## Change-control rule

Do not alter this accepted Lite Report print composition unless a later V4 change is explicitly approved. Full Development Report PDF generation remains a separate server-side PDF flow and is not replaced by this browser Lite Report print baseline.
