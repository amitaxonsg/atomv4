import React from "react";

const MAX_ATTEMPTS = 48;
const POLL_MS = 1250;

export default function PaymentSuccessHandoff() {
  React.useEffect(() => {
    if (window.location.pathname !== "/payment/success") return undefined;

    const params = new URLSearchParams(window.location.search);
    if (params.get("method") === "cash-on-delivery" || params.get("retake") === "1") return undefined;

    const checkout = String(params.get("checkout") || "").trim();
    if (!checkout) return undefined;

    let cancelled = false;
    let attempt = 0;
    let timer = null;

    const poll = async () => {
      if (cancelled) return;
      attempt += 1;

      try {
        const response = await fetch(`/api/payments/status?checkout=${encodeURIComponent(checkout)}`, {
          method: "GET",
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        const result = await response.json().catch(() => ({}));

        if (response.ok && result?.reportReady && result?.reportUrl) {
          window.setTimeout(() => {
            if (!cancelled) window.location.replace(result.reportUrl);
          }, 1200);
          return;
        }
      } catch {
        // The signed Stripe webhook and queued email remain authoritative.
      }

      if (!cancelled && attempt < MAX_ATTEMPTS) timer = window.setTimeout(poll, POLL_MS);
    };

    poll();
    return () => {
      cancelled = true;
      if (timer) window.clearTimeout(timer);
    };
  }, []);

  return null;
}
