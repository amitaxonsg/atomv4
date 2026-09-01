# Growth Alignment V4 — CRM/CMS Go-Live Blueprint

## Objective

Provide one clean, modern administration workspace for Growth Alignment V4 without copying V3 code or carrying forward V3's visual structure. V3 may be used only as a functional checklist.

The V4 repository already contains the required secure foundations: participant records, versioned assessments, reports, payments, email templates and queue, affiliates, roles, audit history, branding, and integration settings. The implementation should simplify and complete this foundation instead of creating a second backend.

## UAT release memory — 1 September 2026

- Treat V4 as an isolated UAT candidate; never clean or deploy V3 resources.
- The existing V4 Admin workspace and its 16 modules remain the control surface;
  V3 is a functional reference only.
- Preserve Admin users, assessment/CMS/configuration, email templates and
  affiliate definitions when preparing the clean UAT baseline.
- Remove historical participant, assessment run, answer, report/PDF, payment,
  webhook, email, affiliate activity, analytics, feedback and test/audit data
  only through the guarded `backend/bin/reset-v4-uat-data.php` command.
- Both card and temporary UAT checkout open separately. Successful processing
  must end on the professional private Full Report, whose fallback action is
  **Show the Report** and whose delivery controls include PDF download and email.
- Payment success and Full Report email delivery are attempted immediately;
  retry-safe background processing remains mandatory.
- Deploy, complete burn-in, then run the final clean-baseline reset and health
  verification before handing UAT to Spencer with Sunil copied.
- No real card transaction is included in Spencer's normal UAT unless Amit
  explicitly authorises it.

## Go-live priorities

1. The participant completes an assessment and sees the Lite Report.
2. Card checkout or the temporary UAT bypass opens in a separate tab.
3. A verified payment unlocks the Full Report, rotates the private link, generates the professional PDF, attempts immediate email delivery, and retains retry-safe queue delivery.
4. The payment tab automatically opens the Full Report when the signed Stripe webhook completes.
5. The Full Report prominently provides Download PDF, Email me the PDF, Print report, and Copy as text.
6. Administrators can find any participant, inspect the full journey, open the report, regenerate the PDF, and email a refreshed private report link.

## Admin access and security

- Admin URL: `/admin`.
- Use one initial Owner account, with additional users added later only if required.
- Store passwords only as Argon2id hashes in `admin_users`; never hard-code a password in JavaScript, PHP, Git, or a database seed.
- The requested value `59900` must not be used as the go-live password because a five-digit password is not safe for a public administration portal.
- Create a temporary password of at least 12 characters and require it to be changed before go-live.
- Retain session regeneration, CSRF validation, login rate limiting, temporary lockout, role permissions, secure cookies, and audit logging already present in V4.
- Do not expose Stripe, SMTP2GO, database, or encryption secrets in the browser or repository.

## Simple information architecture

### 1. Dashboard

Show only operational information needed each day:

- assessments started, completed, awaiting payment, and paid;
- Full Reports generated and PDFs missing;
- email delivered, retrying, and failed;
- revenue by assessment track;
- recent participants and recent failures;
- quick actions: Find participant, Send report, Create affiliate, Test email.

### 2. People

This is the main CRM view. One searchable table with filters for track, completion state, payment state, report state, email delivery state, affiliate, and date.

Opening a person shows a single timeline/drawer:

- profile and consent details;
- assessment date, track, version, answers, notes, and score;
- Lite/Full Report state and private-link expiry;
- payment state and Stripe reference;
- email delivery history;
- affiliate attribution;
- audit-safe actions: Open report, Download PDF, Email report, Rotate link and resend, Regenerate PDF, Export record.

Destructive actions such as revoke, anonymise, or delete require explicit confirmation and are recorded in the audit log.

### 3. Assessments

Use four clear track tabs: Personal, New Joiner, Manager, and Executive.

For each track:

- show the current published version and draft version;
- manage landing/intro/intake copy;
- manage the 40 public questions across 10 areas while retaining the 50-question source bank for history;
- allow text corrections only in a draft;
- lock scoring direction, stable identity, position, and section;
- preview the questionnaire before publishing;
- manage Lite and Full Report profile content;
- show a change summary before publishing;
- keep completed sessions pinned to their original version and report snapshots.

### 4. Reports and Payments

Use one operations screen with tabs:

- Reports: Lite/Full, PDF ready/missing, link expiry, views, open, regenerate, resend, lock, revoke.
- Payments: started, paid, failed, abandoned, refunded; amount, track, provider, Stripe references, and affiliate.
- UAT: a clearly marked temporary no-payment control that is disabled before production launch.

No Full Report is unlocked from a browser redirect alone. Unlocking requires the verified Stripe webhook or an authorised audited UAT/admin action.

### 5. Messaging

Combine email templates, sender settings, test delivery, and delivery history:

- edit subject, branded HTML content, and plain-text fallback;
- preview with safe sample variables;
- control sender name/address, reply-to address, and operational alert recipients;
- test one selected template to a chosen recipient;
- show provider message ID, attempts, next retry, failure reason, and manual retry;
- attach the professional PDF automatically to Full Report emails;
- attempt immediate delivery after payment and retain the queue worker as the retry safety net.

Templates needed at go-live:

- assessment resume link;
- Lite Report ready;
- payment confirmation;
- Full Report ready with PDF;
- refreshed report link;
- admin password reset;
- affiliate invitation/link.

### 6. Affiliates

For every affiliate:

- name, contact, code, campaign, active tracks, cookie duration, commission rule, and notes;
- generated link such as `https://v4.atomglobal.com/?ref=CODE` with optional direct track;
- Copy link and Email affiliate link actions;
- clicks, assessment starts, completions, paid conversions, revenue, and commission;
- active/inactive control without deleting historical attribution.

Affiliate invitation email must use the central email service and log delivery like every other message.

### 7. Settings and Audit

Settings groups:

- General: public URL, organisation details, timezone.
- Branding: public, email, and report logos; colours and typography.
- Email: SMTP/SMTP2GO, sender, reply-to, alert recipients, retry policy.
- Stripe: mode, keys, signed webhook secret, four Full Report Price IDs, retest Price IDs.
- Reports: token lifetime, PDF branding, privacy notice.
- Security: session lifetime, admin users/roles, password reset.
- UAT: temporary bypass toggle with a production warning.

Audit shows administrator, action, affected record, timestamp, and safe before/after summaries. Secrets and full sensitive payloads are never written to the audit log.

## Delivery phases

### Phase 0 — Participant payment/report journey

- Complete the new-tab checkout/UAT flow.
- Poll verified checkout state and automatically open the Full Report.
- Generate and attach PDF during immediate post-payment delivery.
- Add prominent Full Report delivery controls.
- Move the public logo to the top-right content area.

### Phase 1 — Simplified admin shell

- Reduce navigation to Dashboard, People, Assessments, Operations, Messaging, Affiliates, and Settings/Audit.
- Keep the existing V4 permission checks and APIs.
- Add responsive table/drawer behaviour for desktop and mobile.

### Phase 2 — People and report operations

- Add secure admin report preview/open action.
- Add PDF download, regenerate, email, and rotate-link actions from the person record.
- Add filters and a complete participant journey timeline.

### Phase 3 — Assessment CMS

- Consolidate questionnaire process, question bank, versions, and report profiles per track.
- Add draft preview and publish confirmation.
- Preserve immutable published versions and historical snapshots.

### Phase 4 — Messaging and affiliates

- Consolidate sender configuration, templates, provider tests, queue, and alerts.
- Add generated affiliate links, copy action, affiliate invitation email, and conversion view.

### Phase 5 — Go-live verification

- Run database migrations on a backup-restorable staging copy.
- Verify PHP syntax and Composer dependencies on the target server.
- Run all automated JavaScript tests and production build.
- Complete one UAT bypass journey and one real Stripe test-mode card journey for every track.
- Confirm Full Report web view, downloaded PDF, emailed PDF, provider message ID, retry behaviour, and report link expiry.
- Confirm refund relocks the report and invalidates PDF access.
- Disable the UAT bypass.
- Confirm production Price IDs match the displayed database amounts.
- Create the production Owner account with a strong temporary password and change it before launch.
- Deploy only after the consolidated acceptance record passes; retain immutable release and rollback instructions.

## Go-live acceptance criteria

- No dead-end thank-you page.
- No Full Report before verified payment or authorised UAT action.
- Full Report opens automatically after payment verification.
- PDF downloads successfully and contains the complete professional report.
- Payment and Full Report emails receive provider acceptance; failures retry and are visible to admin.
- An administrator can find a participant and open/email/regenerate the report without database access.
- Questionnaire changes require a draft and never rewrite completed participant history.
- Affiliate link creation, attribution, and invitation email work end to end.
- No default, weak, or hard-coded admin password exists in source or production.
- UAT bypass is disabled before public launch.
