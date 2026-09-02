# V4 Mobile/Desktop Burn-In — 2 September 2026

Target: `https://v4.atomglobal.com`

This branch contains only Growth Alignment V4 fixes. It does not change or deploy the V3 Head–Heart repository.

## Staged engineering fixes

- Mobile dropdown/input width hardening and iOS 16px form-control protection.
- Mobile Lite/Full Report payment actions stack full-width so Pay by card and UAT Test — No Payment remain reachable.
- Checkout return recovery reloads the authoritative report state if the original report tab returns with a stale Opening checkout state.
- Server-controlled `cashOnDeliveryAvailable` remains the authority for showing UAT Test — No Payment.
- CMS-uploaded public logo URLs are now honored rather than replaced with the legacy logo.
- CMS Banner image is used as the visual fallback when a stage is still bound to the retired static reflection image.
- Initial public fallback title matches Head–Heart Alignment to prevent the brief Growth Alignment title flash before CMS data arrives.
- Halfway/completion messages receive livelier typography and progress treatment.
- Personal Assessment only shows: “For less than a cup of coffee, find out more about yourself.”

## Release gate

Do not pull this branch to the V4 VPS until automated production-readiness checks are green and the remaining device/browser UAT items are explicitly recorded for Spencer retest.

Required final smoke checks after VPS pull:

1. Mobile 360px and 390/393px: change every intake dropdown and confirm no horizontal expansion.
2. Run all 40 questions / 10 sections; pay special attention to Sections 2 and 3 save/load state.
3. Confirm Lite Report remains locked before authorised payment/UAT action.
4. Confirm UAT Test — No Payment is visible/reachable when server flag is enabled (subject to Amit’s UAT timing rule).
5. Open card checkout, close/cancel it, return to the original report and confirm the control recovers and the report remains locked.
6. Confirm CMS transparent logo and Banner image render on desktop/mobile after a hard refresh.
7. Confirm no first-paint Growth Alignment title flash on the public landing page.
8. Confirm personal-only coffee message and lively halfway/completion treatment.
9. Repeat core report/PDF/email flow on desktop and mobile when the UAT no-payment pause allows it.
