import React from "react";
import AssessmentApp from "./AssessmentApp";
import { BrandLogo, useBranding } from "../branding/BrandContext";

const apiBase = import.meta.env.VITE_API_BASE_URL || "/api";
const productionApi = import.meta.env.VITE_API_MODE === "production";

function AttributionTracker() {
  React.useEffect(() => {
    if (!productionApi) return;
    const parameters = new URLSearchParams(window.location.search);
    const affiliateCode = parameters.get("ref") || parameters.get("affiliate");
    if (!affiliateCode) return;

    const payload = {
      affiliateCode,
      landingPage: window.location.pathname + window.location.search,
      utmSource: parameters.get("utm_source"),
      utmMedium: parameters.get("utm_medium"),
      utmCampaign: parameters.get("utm_campaign"),
      utmContent: parameters.get("utm_content"),
      utmTerm: parameters.get("utm_term"),
    };
    fetch(`${apiBase}/affiliate-clicks`, {
      method: "POST",
      credentials: "include",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
      keepalive: true,
    }).catch(() => {});
  }, []);
  return null;
}

const fallbacks = {
  privacy: {
    pageTitle: "Privacy Notice | Growth Alignment",
    metaDescription: "How Atom Global Consulting processes assessment, payment and communication data.",
    heading: "Privacy Notice",
    introductoryContent: "Atom Global Consulting processes the information required to provide the assessment, save progress, deliver reports, handle payments and send essential service messages. Optional marketing consent is separate and may be withdrawn. Production retention periods and privacy contacts are managed through the administration settings and approved legal notice.",
  },
  terms: {
    pageTitle: "Terms of Use | Growth Alignment",
    metaDescription: "Terms governing use of the Growth Alignment assessment and reports.",
    heading: "Terms of Use",
    introductoryContent: "Growth Alignment is a reflective development tool, not a medical, psychological, legal or employment diagnosis. Reports should be interpreted in context. Paid report access is provided only after verified payment. The final production terms, refund policy and report disclaimer must be approved before launch.",
  },
};

function PublicContentPage({ pageKey }) {
  const { branding } = useBranding();
  const [page, setPage] = React.useState(fallbacks[pageKey]);

  React.useEffect(() => {
    let active = true;
    if (productionApi) {
      fetch(`${apiBase}/public/pages/${encodeURIComponent(pageKey)}`, { credentials: "include" })
        .then(response => response.ok ? response.json() : Promise.reject(new Error("Page unavailable")))
        .then(data => { if (active) setPage(data); })
        .catch(() => {});
    }
    return () => { active = false; };
  }, [pageKey]);

  React.useEffect(() => {
    document.title = page.pageTitle || fallbacks[pageKey].pageTitle;
    let description = document.querySelector('meta[name="description"]');
    if (!description) {
      description = document.createElement("meta");
      description.name = "description";
      document.head.appendChild(description);
    }
    description.content = page.metaDescription || "";
    let canonical = document.querySelector('link[rel="canonical"]');
    if (!canonical) {
      canonical = document.createElement("link");
      canonical.rel = "canonical";
      document.head.appendChild(canonical);
    }
    canonical.href = page.canonicalUrl || window.location.href;
  }, [page, pageKey]);

  return <main className="legal-page">
    <header className="legal-page__header"><a href="/"><BrandLogo /></a><a href="/">Return to assessment</a></header>
    <article className="legal-page__content">
      <p className="eyebrow">Growth Alignment</p>
      <h1>{page.heading || page.pageTitle}</h1>
      <div className="legal-page__copy">{String(page.introductoryContent || "").split(/\n{2,}/).map((paragraph, index) => <p key={index}>{paragraph}</p>)}</div>
      {Array.isArray(page.faq) && page.faq.length > 0 && <section><h2>Frequently asked questions</h2>{page.faq.map((item, index) => <details key={index}><summary>{item.question}</summary><p>{item.answer}</p></details>)}</section>}
      <footer><span>Atom Global Consulting</span><span style={{ color: branding.textMuted }}>Private, secure and self-hosted.</span></footer>
    </article>
  </main>;
}

export default function PublicRuntime() {
  const path = window.location.pathname.replace(/\/+$/, "") || "/";
  return <><AttributionTracker />{path === "/privacy" ? <PublicContentPage pageKey="privacy" /> : path === "/terms" ? <PublicContentPage pageKey="terms" /> : <AssessmentApp />}</>;
}
