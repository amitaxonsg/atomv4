#!/usr/bin/env bash
set -Eeuo pipefail

DOMAIN="head-heart-staging.atomglobal.com"
BASE="https://${DOMAIN}"
SOURCE_DIR="/srv/head-heart.atomglobal.com/staging-source"
CURRENT_DIR="/var/www/head-heart-staging.atomglobal.com/current"
RECIPIENT="${1:-rico.m@axon.com.sg}"
RESOLVE=(--resolve "${DOMAIN}:443:127.0.0.1")
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

PUBLIC=FAIL
V3=FAIL
STRIPE=FAIL
UAT=FAIL
EMAIL=FAIL
MAILTRAP=FAIL

json_get() {
  local expression="$1"
  python3 -c "import json,sys; data=json.load(sys.stdin); print(${expression})"
}

printf '\n=== V3 STAGING BURN-IN ===\n'
printf 'URL: %s\nRecipient: %s\n\n' "$BASE" "$RECIPIENT"

health="$(curl -fsS "${RESOLVE[@]}" "$BASE/api/health")"
echo "Health: $health"
if printf '%s' "$health" | python3 -c 'import json,sys; d=json.load(sys.stdin); raise SystemExit(0 if d.get("status")=="ok" and d.get("environment")=="staging" and d.get("checks",{}).get("database") and d.get("checks",{}).get("migrations") and d.get("checks",{}).get("storage") else 1)'; then
  PUBLIC=PASS
fi

http_code="$(curl -sS -o "$TMP/home.html" -w '%{http_code}' "${RESOLVE[@]}" "$BASE/")"
[[ "$http_code" == "200" ]] || PUBLIC=FAIL
if grep -R --include='*.js' -Fq 'Personal Assessment' "$CURRENT_DIR/frontend/assets" \
  && grep -R --include='*.js' -Fq 'Corporate Assessments' "$CURRENT_DIR/frontend/assets" \
  && grep -R --include='*.js' -Fq 'Add more (optional)' "$CURRENT_DIR/frontend/assets" \
  && grep -R --include='*.css' -Fq 'latest-answer-note-dropdown' "$CURRENT_DIR/frontend/assets"; then
  :
else
  PUBLIC=FAIL
fi
printf 'Public site: %s (HTTP %s)\n' "$PUBLIC" "$http_code"

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
cat > "$TMP/create.json" <<JSON
{"trackKey":"personal","participant":{"name":"Rico V3 Burn-In ${stamp}","email":"${RECIPIENT}","ageRange":"35-44","gender":"Prefer not to say","role":"Individual contributor","industry":"Technology","region":"Asia","purpose":"Personal development","tenure":"6-10 years","privacyConsent":true,"transactionalConsent":true,"marketingConsent":false}}
JSON

created="$(curl -fsS "${RESOLVE[@]}" -H 'Content-Type: application/json' -d @"$TMP/create.json" "$BASE/api/survey-sessions")"
session_id="$(printf '%s' "$created" | json_get 'int(data["id"])')"
resume_token="$(printf '%s' "$created" | json_get 'data["resumeToken"]')"
question_count="$(printf '%s' "$created" | json_get 'len(data.get("assessment",{}).get("questions",[]))')"
if [[ "$question_count" == "40" ]]; then V3=PASS; fi
printf 'Assessment runtime: %s (%s questions, session %s)\n' "$V3" "$question_count" "$session_id"

python3 - "$resume_token" > "$TMP/complete.json" <<'PY'
import json,sys
print(json.dumps({"resumeToken":sys.argv[1],"section":9,"answers":[{"value":3,"note":"V3 burn-in"} for _ in range(40)]}))
PY
completed="$(curl -fsS "${RESOLVE[@]}" -H 'Content-Type: application/json' -d @"$TMP/complete.json" "$BASE/api/survey-sessions/${session_id}/complete")"
report_token="$(printf '%s' "$completed" | json_get 'data["reportToken"]')"
report="$(curl -fsS "${RESOLVE[@]}" "$BASE/api/reports/${report_token}")"
locked="$(printf '%s' "$report" | json_get 'str(bool(data.get("is_unlocked"))).lower()')"
uat_available="$(printf '%s' "$report" | json_get 'str(bool(data.get("cashOnDeliveryAvailable"))).lower()')"
stripe_available="$(printf '%s' "$report" | json_get 'str(bool(data.get("checkoutAvailable"))).lower()')"
printf 'Lite Report generated: %s; UAT option: %s; Stripe option: %s\n' "$locked" "$uat_available" "$stripe_available"

if [[ "$stripe_available" == "true" ]]; then
  stripe_body="$(printf '{"sessionId":%s,"track":"personal"}' "$session_id")"
  if stripe_checkout="$(curl -fsS "${RESOLVE[@]}" -H 'Content-Type: application/json' -d "$stripe_body" "$BASE/api/payments/checkout" 2>"$TMP/stripe.err")"; then
    stripe_url="$(printf '%s' "$stripe_checkout" | json_get 'data.get("url","")')"
    stripe_session="$(printf '%s' "$stripe_checkout" | json_get 'data.get("checkoutSessionId","")')"
    if [[ "$stripe_url" == https://checkout.stripe.com/* && "$stripe_session" == cs_test_* ]]; then STRIPE=PASS; fi
    printf 'Stripe test checkout: %s (%s)\n' "$STRIPE" "$stripe_session"
  else
    printf 'Stripe test checkout: FAIL (%s)\n' "$(cat "$TMP/stripe.err")"
  fi
else
  echo 'Stripe test checkout: FAIL (staging test credentials/webhook/price IDs are not fully configured)'
fi

if [[ "$uat_available" == "true" ]]; then
  uat_body="$(printf '{"sessionId":%s,"track":"personal"}' "$session_id")"
  if uat_checkout="$(curl -fsS "${RESOLVE[@]}" -H 'Content-Type: application/json' -d "$uat_body" "$BASE/api/payments/cash-on-delivery")"; then
    full_url="$(printf '%s' "$uat_checkout" | json_get 'data.get("reportUrl","")')"
    full_token="${full_url##*/}"
    full_report="$(curl -fsS "${RESOLVE[@]}" "$BASE/api/reports/${full_token}")"
    if printf '%s' "$full_report" | python3 -c 'import json,sys; d=json.load(sys.stdin); raise SystemExit(0 if d.get("is_unlocked") is True and d.get("paid_report_json") else 1)'; then UAT=PASS; fi
  fi
fi
printf 'UAT no-payment Full Report: %s\n' "$UAT"

printf '\n=== EMAIL CONFIG ===\n'
set +e
email_audit="$(cd "$SOURCE_DIR/backend" && php bin/email-settings-audit.php 2>&1)"
audit_rc=$?
set -e
printf '%s\n' "$email_audit"
if [[ $audit_rc -eq 0 ]]; then
  provider="$(printf '%s' "$email_audit" | json_get 'str(data.get("effective",{}).get("provider","")).lower()')"
  smtp_host="$(printf '%s' "$email_audit" | json_get 'str(data.get("effective",{}).get("smtpHost","")).lower()')"
  if [[ "$provider" == "smtp" && "$smtp_host" == *mailtrap* ]]; then MAILTRAP=PASS; fi
fi
printf 'Mailtrap configuration: %s\n' "$MAILTRAP"

printf '\n=== EMAIL DELIVERY ===\n'
set +e
mail_output="$(cd "$SOURCE_DIR/backend" && php bin/process-email-queue.php 100 2>&1)"
mail_rc=$?
set -e
printf '%s\n' "$mail_output"
queue_json="$(cd "$SOURCE_DIR/backend" && php -r '$c=require "src/bootstrap.php"; $r=$c["db"]->fetchAll("SELECT template_key,status,provider_message_id,failure_reason FROM email_queue WHERE recipient_email = ? ORDER BY id DESC LIMIT 10", [$argv[1]]); echo json_encode($r);' "$RECIPIENT")"
printf 'Recent queue: %s\n' "$queue_json"
if [[ $mail_rc -eq 0 ]] && printf '%s' "$queue_json" | python3 -c 'import json,sys; rows=json.load(sys.stdin); required={"participant_registration","survey_resume_link","assessment_completed","free_report_ready","payment_successful","paid_report_ready"}; sent={r.get("template_key") for r in rows if r.get("status")=="sent" and r.get("provider_message_id")}; raise SystemExit(0 if required.issubset(sent) else 1)'; then
  EMAIL=PASS
fi
printf 'Email queue/delivery: %s\n' "$EMAIL"

printf '\n=== BURN-IN SUMMARY ===\n'
printf 'PUBLIC=%s\nV3_40Q=%s\nSTRIPE_TEST_CHECKOUT=%s\nUAT_NO_PAYMENT=%s\nMAILTRAP=%s\nEMAIL_DELIVERY=%s\n' "$PUBLIC" "$V3" "$STRIPE" "$UAT" "$MAILTRAP" "$EMAIL"
if [[ "$PUBLIC" == PASS && "$V3" == PASS && "$STRIPE" == PASS && "$UAT" == PASS && "$MAILTRAP" == PASS && "$EMAIL" == PASS ]]; then
  echo 'READY_FOR_SUNIL=YES'
  exit 0
fi
echo 'READY_FOR_SUNIL=NO'
exit 2
