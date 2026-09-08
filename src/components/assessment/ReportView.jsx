import React from "react";
import { api, isMockMode } from "../../api/client";
import { parseReportPayload, reportSummary, v3AreaName } from "../../data/runtimeAssessment";
import { AlignmentGauge } from "../shared/Charts";
import { ArrowRight, Check, Lock } from "../shared/Icons";
import { StageShell } from "./AssessmentLayout";

function textValue(value) {
  if (value == null) return "";
  if (typeof value === "string" || typeof value === "number") return String(value);
  return "";
}

function ScaleBar({ score, min = 5, max = 25 }) {
  const value = Math.max(min, Math.min(max, Number(score) || min));
  const percent = ((value - min) / (max - min)) * 100;
  return <div className="v4-scale" aria-label={`${value} out of ${max}`}>
    <div className="v4-scale__labels"><span>5 · Head-led</span><span>15 · Balanced</span><span>25 · Heart-led</span></div>
    <div className="v4-scale__track"><span style={{ width: `${percent}%` }} /><i style={{ left: `${percent}%` }} /></div>
  </div>;
}

function AlignmentMeter({ score }) {
  const value = Math.max(0, Math.min(250, Number(score) || 0));
  return <div className="v4-meter" aria-label={`${value} out of 250`}>
    <div className="v4-meter__labels"><span>Head-led</span><strong>{value}/250</strong><span>Heart-led</span></div>
    <div className="v4-meter__track"><span style={{ width: `${(value / 250) * 100}%` }} /></div>
  </div>;
}

function TextSection({ title, value }) {
  const text = textValue(value);
  if (!text) return null;
  return <section className="report-card"><h3>{title}</h3><p>{text}</p></section>;
}

function ListSection({ title, items, ordered = false }) {
  if (!Array.isArray(items) || !items.length) return null;
  const values = items.map(item => textValue(item)).filter(Boolean);
  if (!values.length) return null;
  const List = ordered ? "ol" : "ul";
  return <section className="report-card"><h3>{title}</h3><List>{values.map((item, index) => <li key={`${title}-${index}`}>{item}</li>)}</List></section>;
}

function Roadmap({ content }) {
  const roadmap = Array.isArray(content?.roadmap) ? content.roadmap : [];
  if (!roadmap.length) return null;
  return <section className="report-card full-report-copy">
    <h3>Development roadmap</h3>
    <p>Choose two or three changes from this roadmap to practise consistently. The goal is not to change everything at once, but to build a small number of observable habits you can revisit.</p>
    {roadmap.slice(0, 5).map((item, index) => <article key={item.area || index}>
      <h4>{item.area || `Development area ${index + 1}`}</h4>
      <p>{item.insight || item.summary || ""}</p>
      {Array.isArray(item.steps) && <ul>{item.steps.slice(0, 3).map(step => <li key={step}>{step}</li>)}</ul>}
    </article>)}
  </section>;
}

function UpgradeReasons({ items, locked = false }) {
  if (!Array.isArray(items) || !items.length) return null;
  const normalised = items.map((item, index) => {
    if (typeof item === "string") return { title: item, detail: "", key: `${index}-${item}` };
    if (!item || typeof item !== "object") return null;
    return { title: item.title || item.area || `Full Report feature ${index + 1}`, detail: item.detail || item.summary || item.insight || "", key: item.title || item.area || index };
  }).filter(Boolean);
  if (!normalised.length) return null;
  return <section className={locked ? "locked-preview locked-preview--cms" : "report-card"}>
    {!locked && <h3>Use this report to</h3>}
    {normalised.map(item => <div key={item.key} className={locked ? "locked-preview__item" : "full-report-feature"}><h4>{item.title}</h4>{item.detail && <p>{item.detail}</p>}</div>)}
  </section>;
}

function SubscaleReads({ content, trackKey }) {
  const reads = content?.subscaleReads;
  if (!reads || typeof reads !== "object" || Array.isArray(reads)) return null;
  const entries = Object.entries(reads).filter(([, value]) => textValue(value));
  if (!entries.length) return null;
  return <section className="report-card"><h3>Your 10-area deep dive</h3><div className="full-report-copy">{entries.map(([key, value]) => <article key={key}><h4>{v3AreaName(trackKey, key, key)}</h4><p>{textValue(value)}</p></article>)}</div></section>;
}

function ScoreBreakdown({ subscales, trackKey, legend }) {
  const entries = Object.entries(subscales || {}).slice(0, 10);
  if (entries.length < 3) return null;
  const columns = [entries.slice(0, 5), entries.slice(5, 10)];
  return <section className="report-card v4-score-breakdown">
    <h3>Your 10-area score breakdown</h3>
    <p className="v4-score-intro">Compare all ten areas on the same scale: <strong>5 = more Head-led</strong>, <strong>15 = balanced</strong>, and <strong>25 = more Heart-led</strong>. The progress bars make the pattern easy to compare at a glance.</p>
    <div className="v4-summary-grid v4-all-areas-grid">{columns.map((column, columnIndex) => <div key={`score-column-${columnIndex}`}>{column.map(([label, value]) => <article key={label}><div><span>{v3AreaName(trackKey, label, label)}</span><strong>{value}/25</strong></div><ScaleBar score={value} /></article>)}</div>)}</div>
    {legend && <p className="preview-note"><strong>How to read these scores:</strong> {legend}</p>}
  </section>;
}

function ExecutiveSummary({ subscales, trackKey }) {
  const entries = Object.entries(subscales || {}).map(([code, score]) => ({ code, score: Number(score) || 0 }));
  if (entries.length < 6) return null;
  const ranked = [...entries].sort((a, b) => b.score - a.score || a.code.localeCompare(b.code));
  const groups = [{ title: "Highest 3", items: ranked.slice(0, 3) }, { title: "Lowest 3", items: ranked.slice(-3).reverse() }];
  return <section className="report-card v4-executive-summary">
    <p className="eyebrow">At a glance</p><h3>Executive Summary</h3>
    <p>Your highest and lowest assessment areas show where your current pattern is strongest and where focused development may have the greatest value.</p>
    <div className="v4-summary-grid">{groups.map(group => <div key={group.title}><h4>{group.title}</h4>{group.items.map(item => <article key={item.code}><div><span>{v3AreaName(trackKey, item.code, item.code)}</span><strong>{item.score}/25</strong></div><ScaleBar score={item.score} /></article>)}</div>)}</div>
  </section>;
}

function EdgeCard({ title, edge, trackKey }) {
  if (!edge || !edge.code) return null;
  return <section className="report-card"><h3>{title}</h3><h4>{v3AreaName(trackKey, edge.code, edge.code)} · {edge.score}/25</h4><p>{edge.meaning}</p></section>;
}

function ProfileSpectrum({ items }) {
  if (!Array.isArray(items) || !items.length) return null;
  return <section className="report-card full-report-copy"><h3>Understand the Head–Heart profile spectrum</h3><p>Your profile is one point on a four-profile spectrum. The highlighted definition is your current result; the others show the neighbouring patterns and score bands.</p>{items.map(item => <article key={item.key || item.name} className={item.current ? "current-profile" : ""}><h4>{item.current ? "Your profile — " : ""}{item.name} · {item.min}–{item.max}</h4>{item.summary && <p>{item.summary}</p>}</article>)}</section>;
}

function WrittenReflections({ items }) {
  if (!Array.isArray(items) || !items.length) return null;
  return <section className="report-card full-report-copy"><h3>Your written reflections</h3><p>These are the notes you chose to add while answering the assessment. They are included because your own context can be as important as the numerical pattern.</p>{items.map((item, index) => <article key={`${item.questionPosition}-${index}`}><h4>Question {item.questionPosition}</h4>{item.question && <p><strong>{item.question}</strong></p>}<p>{item.reflection}</p></article>)}</section>;
}

function Methodology({ items }) {
  if (!items || typeof items !== "object" || Array.isArray(items)) return null;
  return <section className="report-card full-report-copy"><h3>Methodology and sourcing</h3>{Object.entries(items).map(([key, value]) => <article key={key}><h4>{key}</h4><p>{textValue(value)}</p></article>)}</section>;
}

function RetakeComparison({ comparison, trackKey }) {
  if (!comparison || !Array.isArray(comparison.areas)) return null;
  const signed = value => `${Number(value) > 0 ? "+" : ""}${Number(value)}`;
  return <section className="report-card full-report-copy">
    <h3>Your progress since the previous assessment</h3>
    <p><strong>Overall:</strong> {comparison.previousTotal} → {comparison.currentTotal} ({signed(comparison.totalChange)})</p>
    <div className="report-score-list">{comparison.areas.map(area => <div key={area.code}><span>{v3AreaName(trackKey, area.code, area.code)}</span><strong>{area.previous} → {area.current} ({signed(area.change)})</strong></div>)}</div>
    {comparison.guidance && <p>{comparison.guidance}</p>}
  </section>;
}

function RetakePlan({ report }) {
  const [state, setState] = React.useState({ busy: false, error: "" });
  const recommended = report?.retakeRecommendedAt ? new Date(report.retakeRecommendedAt) : null;
  const recommendedLabel = recommended && !Number.isNaN(recommended.getTime()) ? recommended.toLocaleDateString(undefined, { year: "numeric", month: "long", day: "numeric" }) : "about three months from now";
  const available = isMockMode || Boolean(report?.retakeCheckoutAvailable);
  const retakePrice = new Intl.NumberFormat(undefined, { style: "currency", currency: report?.retakeCurrency || "USD" }).format(Number(report?.retakePriceMinor || 0) / 100);
  const startRetake = async () => {
    if (!available || state.busy) return;
    setState({ busy: true, error: "" });
    try {
      const result = await api.createCheckout({ sessionId: report.sessionId, track: report.trackKey, affiliateCode: "__RETAKE__" });
      if (result.url) window.location.href = result.url;
      else if (result.preview) setState({ busy: false, error: "Preview mode does not create a live retake payment." });
      else throw new Error("Retake checkout is unavailable.");
    } catch (error) {
      setState({ busy: false, error: error.message });
    }
  };
  return <section className="report-card">
    <h3>3-month retake and progress check</h3>
    <p>Commit to one or two development areas and work on them consistently. Retake the full 40-question assessment around <strong>{recommendedLabel}</strong> so you can compare what shifted, what stayed stable, and where old patterns still show up under pressure.</p>
    <p><strong>Retest price: {retakePrice}.</strong> This option becomes available 90 days after the original assessment and only for participants who paid for their Full Development Report. After verified payment, a fresh 40-question retest is created and the new report compares it with the previous result.</p>
    {state.error && <p className="form-error" role="alert">{state.error}</p>}
    <button className="button button--primary" disabled={!available || state.busy} onClick={startRetake}>{state.busy ? "Opening secure checkout…" : available ? `Retest full assessment — ${retakePrice}` : "Retest available 90 days after verified paid report"}</button>
  </section>;
}

function DevelopmentCommitment({ report, token }) {
  const experience = report?.reportExperience || {};
  const [text, setText] = React.useState(report?.commitment?.text || "");
  const [state, setState] = React.useState({ busy: false, message: "", error: "" });
  const save = async () => {
    if (!token || state.busy) return;
    setState({ busy: true, message: "", error: "" });
    try {
      const response = await fetch(`/api/reports/${encodeURIComponent(token)}/commitment`, { method: "PUT", credentials: "include", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ text }) });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.message || "Unable to save commitment");
      const date = new Date(`${result.checkInDate}T00:00:00`);
      const label = Number.isNaN(date.getTime()) ? result.checkInDate : date.toLocaleDateString(undefined, { year: "numeric", month: "long", day: "numeric" });
      setState({ busy: false, error: "", message: `Saved. Suggested check-in: ${label}.` });
    } catch (error) { setState({ busy: false, message: "", error: error.message }); }
  };
  return <section className="report-card v4-commitment"><p className="eyebrow">Make it actionable</p><h3>{experience.commitmentHeading || "My 90-day development commitment"}</h3><p>{experience.commitmentPrompt || "Choose one or two development areas and write down the action you will practise consistently."}</p><textarea rows="5" maxLength="2000" value={text} onChange={event => setText(event.target.value)} placeholder="I commit to working on…" />{state.error && <p className="form-error" role="alert">{state.error}</p>}{state.message && <p className="preview-note" role="status">{state.message}</p>}<button className="button button--primary" disabled={!token || text.trim().length < 10 || state.busy} onClick={save}>{state.busy ? "Saving…" : "Save my commitment"}</button></section>;
}

function CoachCallToAction({ report }) {
  const value = report?.reportExperience || {};
  const contacts = [{ name: value.coachPrimaryName, email: value.coachPrimaryEmail }, { name: value.coachSecondaryName, email: value.coachSecondaryEmail }].filter(item => item.name && item.email);
  return <section className="report-card v4-coach"><p className="eyebrow">Optional support</p><h3>{value.coachHeading || "Talk to a Coach"}</h3><p>{value.coachBody || "Turn your report into a focused development plan with an Atom Global coach."}</p><div className="v4-coach__actions">{contacts.map(item => <a className="button button--ghost" href={`mailto:${item.email}?subject=${encodeURIComponent("Growth Alignment coaching")}`} key={item.email}>Email {item.name}</a>)}</div></section>;
}

function publicShareUrl() {
  if (typeof window !== "undefined" && window.location?.origin) return `${window.location.origin}/`;
  return "https://v4.atomglobal.com/";
}

function highlightShareText(report, summary) {
  const lines = [
    `Growth Alignment — ${report?.trackName || "Assessment"}`,
    `Profile: ${summary.profile}`,
    `Overall score: ${summary.total}/250`,
    "",
    "Your alignment pattern",
    textValue(summary.summary),
  ];
  const strengths = Array.isArray(summary.strengths) ? summary.strengths.slice(0, 3) : [];
  const observations = Array.isArray(summary.watchouts) ? summary.watchouts.slice(0, 3) : [];
  if (strengths.length) lines.push("", "Top strengths", ...strengths.map(item => `- ${textValue(item)}`));
  if (observations.length) lines.push("", "Development observations", ...observations.map(item => `- ${textValue(item)}`));
  lines.push("", `Take the Growth Alignment assessment: ${publicShareUrl()}`);
  return lines.join("\n");
}

function highlightSharePayload(report, summary) {
  return { title: "Growth Alignment highlights", text: highlightShareText(report, summary), url: publicShareUrl() };
}

async function copyHighlightText(text) {
  if (!navigator.clipboard?.writeText) return false;
  await navigator.clipboard.writeText(text);
  return true;
}

function openShareWindow(url) {
  const popup = window.open(url, "_blank", "noopener,noreferrer");
  if (popup) popup.opener = null;
}

async function shareHighlightsTo(network, report, summary) {
  const payload = highlightSharePayload(report, summary);
  if (network === "facebook") {
    copyHighlightText(payload.text).catch(() => {});
    openShareWindow(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(payload.url)}&quote=${encodeURIComponent(payload.text)}`);
    return "Facebook share opened. Highlights were also copied in case Facebook asks you to paste them.";
  }
  if (network === "linkedin") {
    copyHighlightText(payload.text).catch(() => {});
    openShareWindow(`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(payload.url)}`);
    return "LinkedIn share opened. Highlights were also copied so you can paste them into your post.";
  }
  if (network === "instagram") {
    if (navigator.share) {
      await navigator.share(payload);
      return "Choose Instagram in the share sheet to send your highlights.";
    }
    await copyHighlightText(payload.text);
    openShareWindow("https://www.instagram.com/");
    return "Highlights copied. Paste them into Instagram.";
  }
  if (navigator.share) {
    await navigator.share(payload);
    return "Highlights shared.";
  }
  await copyHighlightText(payload.text);
  return "Highlights copied — paste them into any social app or message.";
}

function ShareHighlightsButton({ report, summary, className = "button button--primary" }) {
  const [open, setOpen] = React.useState(false);
  const [message, setMessage] = React.useState("");
  const dialogRef = React.useRef(null);
  const closeRef = React.useRef(null);
  const previousFocusRef = React.useRef(null);

  React.useEffect(() => {
    if (!open) return undefined;
    previousFocusRef.current = document.activeElement;
    const previousOverflow = document.body.style.overflow;
    const onKeyDown = event => {
      if (event.key === "Escape") {
        event.preventDefault();
        setOpen(false);
        return;
      }
      if (event.key !== "Tab" || !dialogRef.current) return;
      const focusable = [...dialogRef.current.querySelectorAll('button:not([disabled]),input:not([disabled]),[href],[tabindex]:not([tabindex="-1"])')]
        .filter(node => node.offsetParent !== null);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };
    document.body.style.overflow = "hidden";
    document.addEventListener("keydown", onKeyDown);
    window.requestAnimationFrame(() => closeRef.current?.focus());
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", onKeyDown);
      previousFocusRef.current?.focus?.();
    };
  }, [open]);

  const close = () => {
    setMessage("");
    setOpen(false);
  };
  const share = async network => {
    try {
      setMessage(await shareHighlightsTo(network, report, summary));
    } catch (error) {
      if (error?.name !== "AbortError") setMessage("Sharing is unavailable in this browser. You can still copy the highlights and paste them into the app.");
    }
  };
  const copy = async () => {
    try {
      const copied = await copyHighlightText(highlightShareText(report, summary));
      setMessage(copied ? "Highlights copied — paste them into any social app or message." : "Copy is unavailable in this browser.");
    } catch {
      setMessage("Copy is unavailable in this browser.");
    }
  };

  return <div className="v4-share-highlights">
    <button className={className} type="button" aria-haspopup="dialog" aria-expanded={open} onClick={() => { setMessage(""); setOpen(true); }}>Share highlights</button>
    {open && <div className="v4-share-modal__backdrop" role="presentation" onMouseDown={event => { if (event.target === event.currentTarget) close(); }}>
      <section className="v4-share-modal" role="dialog" aria-modal="true" aria-labelledby="v4-share-modal-title" aria-describedby="v4-share-modal-description" ref={dialogRef}>
        <header className="v4-share-modal__header">
          <div><p className="eyebrow">Share</p><h3 id="v4-share-modal-title">Share highlights</h3></div>
          <button className="v4-share-modal__close" type="button" aria-label="Close share dialog" onClick={close} ref={closeRef}>×</button>
        </header>
        <p className="v4-share-modal__description" id="v4-share-modal-description">Only your result highlights and the public assessment link will be shared. Your private Full Report stays private.</p>
        <div className="v4-share-modal__link">
          <span>Public assessment link</span>
          <div><input type="text" readOnly value={publicShareUrl()} aria-label="Public assessment link" /><button className="button button--ghost" type="button" onClick={copy}>Copy highlights</button></div>
        </div>
        <p className="v4-share-modal__label">Share to</p>
        <div className="upgrade-box__actions v4-share-modal__platforms" role="group" aria-label="Share highlights to a platform">
          <button className="button button--ghost" type="button" aria-label="Share to Facebook" onClick={() => share("facebook")}>Facebook</button>
          <button className="button button--ghost" type="button" aria-label="Share to LinkedIn" onClick={() => share("linkedin")}>LinkedIn</button>
          <button className="button button--ghost" type="button" aria-label="Share to Instagram" onClick={() => share("instagram")}>Instagram</button>
          <button className="button button--ghost" type="button" aria-label="More sharing options" onClick={() => share("more")}>More apps</button>
        </div>
        {message && <p className="preview-note v4-share-modal__status" role="status">{message}</p>}
      </section>
    </div>}
  </div>;
}

function ThankYouShare({ report, summary }) {
  return <section className="report-card v4-coach v4-thank-you-share">
    <p className="eyebrow">Thank you</p>
    <h3>Thank you for taking the assessment.</h3>
    <p>If this is helpful, please share it with someone who will benefit from taking it!</p>
    <p className="preview-note"><strong>Privacy:</strong> Share highlights sends only your result title, overall score, alignment summary, top strengths and development observations. Your private Full Report, PDF, private link, reflections and detailed development content are not included.</p>
    <ShareHighlightsButton report={report} summary={summary} />
  </section>;
}

function FullReportActions({ report, token }) {
  const [state, setState] = React.useState({ message: "", busy: false });
  const email = async () => {
    if (!token || state.busy) return;
    setState({ message: "", busy: true });
    try {
      const response = await fetch(`/api/reports/${encodeURIComponent(token)}/email`, { method: "POST", credentials: "include", headers: { "Content-Type": "application/json" }, body: "{}" });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.message || "Unable to email report");
      setState({ message: "Full Development Report queued to your email with the PDF attached.", busy: false });
    } catch (error) {
      setState({ message: error.message, busy: false });
    }
  };
  return <section className="report-card"><h3>Save your full report</h3><p>Your Full Development Report is private. Email the PDF to yourself for your development work and three-month comparison.</p><div className="upgrade-box__actions"><button className="button button--ghost" disabled={!token || state.busy} onClick={email}>{state.busy ? "Queuing email…" : "Email PDF to self"}</button></div>{state.message && <p className="preview-note" role="status">{state.message}</p>}</section>;
}

function FullReportContent({ report, summary, content, token }) {
  if (!content) return <p className="preview-note">Full Report content is unavailable. Contact Atom Global support.</p>;
  return <>
    <RetakeComparison comparison={content.retakeComparison} trackKey={report?.trackKey} />
    <ExecutiveSummary subscales={report?.paid?.subscales || summary.subscales} trackKey={report?.trackKey} />
    <ScoreBreakdown subscales={report?.paid?.subscales || summary.subscales} trackKey={report?.trackKey} legend={content.radarLegend} />
    <div className="report-columns"><EdgeCard title="Sharpest Edge" edge={content.sharpestEdge} trackKey={report?.trackKey} /><EdgeCard title="Growth Edge" edge={content.growthEdge} trackKey={report?.trackKey} /></div>
    <TextSection title="Complete profile summary" value={content.summary} />
    <ListSection title="Full strengths list" items={content.strengths} />
    <ListSection title="Challenges and development areas" items={content.watchouts} />
    <TextSection title="Development areas" value={content.developmentAreas} />
    <ListSection title="Development areas" items={content.developmentAreas} />
    <TextSection title="Relationships / team" value={content.relationships} />
    <TextSection title="Personal / working style" value={content.work} />
    <ListSection title="Working-style actions" items={content.workingStyleTips} />
    <TextSection title="How you handle difficulty" value={content.handlingDifficulty} />
    <TextSection title={content.leadershipImpactLabel || "Leadership impact"} value={content.leadershipImpact} />
    <TextSection title={content.cultureFitLabel || "Culture fit reflection"} value={content.cultureFitPrompt} />
    <ListSection title="Five practical everyday actions" items={(content.growth || []).slice(0, 5)} ordered />
    <SubscaleReads content={content} trackKey={report?.trackKey} />
    <Roadmap content={content} />
    <ProfileSpectrum items={content.profileSpectrum} />
    <WrittenReflections items={content.writtenReflections} />
    <Methodology items={content.methodology} />
    <DevelopmentCommitment report={report} token={token} />
    <RetakePlan report={report} />
    <CoachCallToAction report={report} />
    <FullReportActions report={report} token={token} />
    <UpgradeReasons items={content.upgradeReasons} />
    <p className="preview-note">Your private link is time-limited. Open the PDF, email it to yourself or print a copy for your records.</p>
  </>;
}

export default function ReportView({ payload, token, onReset }) {
  const report = parseReportPayload(payload);
  const summary = reportSummary(report);
  const paidContent = report?.paid?.content || report?.paid || null;
  const unlocked = Boolean(report?.is_unlocked);
  const checkoutAvailable = isMockMode || Boolean(report?.checkoutAvailable);
  const cashOnDeliveryAvailable = Boolean(report?.cashOnDeliveryAvailable);
  const upgradePreview = report?.free?.upgradePreview || [];
  const [checkout, setCheckout] = React.useState({ busy: false, error: "" });
  const price = new Intl.NumberFormat(undefined, { style: "currency", currency: report?.currency || "USD" }).format(Number(report?.priceMinor || 0) / 100);

  const openCheckout = async () => {
    if (!checkoutAvailable) return;
    setCheckout({ busy: true, error: "" });
    try {
      const result = await api.createCheckout({ sessionId: report.sessionId, track: report.trackKey });
      if (result.preview) window.location.reload(); else window.location.href = result.url;
    } catch (error) { setCheckout({ busy: false, error: error.message }); }
  };

  const openCashOnDelivery = async () => {
    if (!cashOnDeliveryAvailable || checkout.busy) return;
    setCheckout({ busy: true, error: "" });
    try {
      const response = await fetch("/api/payments/cash-on-delivery", { method: "POST", credentials: "include", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ sessionId: report.sessionId, track: report.trackKey }) });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.message || "UAT no-payment checkout failed");
      window.location.href = result.successUrl || result.reportUrl;
    } catch (error) { setCheckout({ busy: false, error: error.message }); }
  };

  const actions = <>{onReset ? <button className="button button--ghost" onClick={onReset}>Start again</button> : <a className="button button--ghost" href="/">New assessment</a>}{unlocked && token && <a className="button button--ghost" href={`/api/reports/${encodeURIComponent(token)}/pdf`} target="_blank" rel="noreferrer">Open PDF</a>}<button className="button button--primary" onClick={() => window.print()}>Print report</button></>;

  const reportClass = report?.trackKey === "personal" ? "v4-report--personal" : "v4-report--professional";
  return <StageShell stageKey="report" current={4} actions={actions}>
    <div className={`v4-report ${reportClass}`}>
    <p className="eyebrow">Growth Alignment · {report?.trackName || "Assessment"} result</p><h1>{summary.profile}</h1>
    <p className="lead">{report?.participantName ? `${report.participantName}, this` : "This"} result was calculated by the published assessment version from your saved responses.</p>
    <section className="report-hero"><AlignmentGauge score={summary.total} /><div><h2>Your alignment pattern</h2><p>{summary.summary}</p><AlignmentMeter score={summary.total} /></div></section>
    <div className="report-columns"><section className="report-card"><h2>Top three strengths</h2><ul>{summary.strengths.slice(0, 3).map(item => <li key={item}><Check />{item}</li>)}</ul></section><section className="report-card"><h2>Development observations</h2><ul>{summary.watchouts.map(item => <li key={item}><span>—</span>{item}</li>)}</ul></section></div>
    <section className={`paid-report ${unlocked ? "unlocked" : "locked"}`}>
      <div className="paid-heading"><div><p className="eyebrow">Complete report</p><h2>{unlocked ? "Your full development report" : "This is the short version"}</h2></div>{!unlocked && <span className="lock-badge"><Lock /> Locked</span>}</div>
      {unlocked ? <FullReportContent report={report} summary={summary} content={paidContent} token={token} /> : <>
        <p>Your Full Report goes deeper into the patterns behind this result and turns them into practical development guidance.</p><UpgradeReasons items={upgradePreview} locked />
        {!upgradePreview.length && <div className="locked-preview"><div><h3>10-area score breakdown and deep dive</h3><p>Compare how your pattern shifts across decisions, relationships, conflict and pressure.</p></div><div><h3>Practical development roadmap</h3><p>Receive tailored actions, working-style guidance and track-specific development insights.</p></div></div>}
        {checkout.error && <p className="form-error" role="alert">{checkout.error}</p>}
        <div className="upgrade-box"><div><span>One-time payment</span><strong>{price}</strong><small>{report?.reportExperience?.paymentWording || "Secure payment unlocks your private Full Development Report."}</small></div><div className="upgrade-box__actions"><button className="button button--primary" disabled={!checkoutAvailable || checkout.busy} onClick={openCheckout}>{checkout.busy ? "Opening checkout…" : checkoutAvailable ? "Pay by card" : "Full Report checkout coming soon"} {checkoutAvailable && <ArrowRight />}</button>{cashOnDeliveryAvailable && <button className="button button--ghost" disabled={checkout.busy} onClick={openCashOnDelivery}>UAT Test — No Payment</button>}</div></div>
        {cashOnDeliveryAvailable && <p className="preview-note">UAT Test — No Payment is temporarily enabled for client testing. It unlocks the Full Report and queues the normal confirmation/report email with the PDF attachment without charging Stripe.</p>}
        {!checkoutAvailable && !cashOnDeliveryAvailable && <p className="preview-note">Your Lite Report is ready now. Full Report purchasing will open after Atom Global completes its secure payment configuration.</p>}
      </>}
    </section>
    <ThankYouShare report={report} summary={summary} />
    {isMockMode && <p className="preview-note">Preview mode simulates payment. Production unlocks only after a verified Stripe webhook or authorised administrator action.</p>}
    </div>
  </StageShell>;
}
