# V4 Full Report Print Policy — 8 September 2026

## Scope

V4 only.

## Accepted behavior

- Lite Report `Print report` keeps the approved browser-print one-page Lite layout.
- Unlocked Full Report `Print report` must not browser-print the interactive website.
- Unlocked Full Report `Print report` must use the same `/api/reports/{token}/pdf` endpoint as `Open PDF` and the emailed Full Development Report attachment.
- This guarantees the participant prints the same server-generated Full Report PDF artifact used by email.
- Full Report website-only controls such as commitment editing, retake buttons, coach email buttons, Share highlights, New assessment, Open PDF and Print report are not part of the server-generated PDF artifact.

## Runtime baseline before change

Deployed application commit: `a1d3c7a76c5d7745e2f9099e9459dc6de03d2dc6`

Git/documentation branch head before this change: `c4ddd077d137b340d89372ac8735256c0c0dd407`

## Safety

This change must not modify PDF generation, scoring, payment, email delivery, CMS data, database schema or Lite Report print behavior.
