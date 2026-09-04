import React from "react";
import { assessmentTracks } from "../data/assessmentData";
import { buildRuntimeTrack, V3_QUESTION_COUNT } from "../data/runtimeAssessment";
import { api, isMockMode } from "../api/client";
import AdminApp from "./admin/AdminApp";
import { ParticipantDetails, Questions, SelectVersion, StageShell, TrackIntroduction, blankParticipant } from "./assessment/AssessmentLayout";
import ReportView from "./assessment/ReportView";

function PaymentStatus({ cancelled = false }) {
  const params = new URLSearchParams(window.location.search);
  const method = params.get("method") || "";
  const reportUrl = params.get("report") || "";
  const checkout = params.get("checkout") || "";
  const emailDelivery = params.get("email") || "queued";
  const uatNoPayment = method === "cash-on-delivery";
  const shouldCheckCard = !cancelled && !uatNoPayment && Boolean(checkout);
  const [retryNonce, setRetryNonce] = React.useState(0);
  const [paidState, setPaidState] = React.useState({
    checking: shouldCheckCard,
    reportUrl: "",
    error: "",
    progress: shouldCheckCard ? 25 : 0,
    stage: shouldCheckCard ? "Payment received" : "",
    pdfEmailStatus: null,
  });

  React.useEffect(() => {
    if (!shouldCheckCard) return undefined;
    let active = true;
    let attempts = 0;
    let timer = null;

    setPaidState(current => ({
      ...current,
      checking: true,
      error: "",
      progress: Math.max(25, current.progress || 0),
      stage: current.stage || "Payment received",
    }));

    const poll = async () => {
      attempts += 1;
      try {
        const response = await fetch(`/api/payments/status?checkout=${encodeURIComponent(checkout)}`, {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        const result = await response.json().catch(() => ({}));
        if (!active) return;

        if (response.ok) {
          const progress = Math.max(25, Math.min(100, Number(result?.progress) || (result?.paid ? 85 : 55)));
          setPaidState(current => ({
            ...current,
            checking: !result?.reportReady,
            progress: Math.max(current.progress || 0, progress),
            stage: result?.stage || (result?.paid ? "Unlocking Full Report" : "Verifying payment"),
            pdfEmailStatus: result?.pdfEmailStatus || current.pdfEmailStatus,
            error: "",
            reportUrl: result?.reportReady && result?.reportUrl ? result.reportUrl : current.reportUrl,
          }));

          if (result?.reportReady && result?.reportUrl) {
            timer = window.setTimeout(() => {
              if (active) window.location.replace(result.reportUrl);
            }, 900);
            return;
          }
        }
      } catch {
        // The signed Stripe webhook and verified server reconciliation remain authoritative.
      }

      if (active && attempts < 90) {
        timer = window.setTimeout(poll, 2000);
      } else if (active) {
        setPaidState(current => ({
          ...current,
          checking: false,
          error: "Your payment has been received, but the Full Report is taking longer than expected to prepare. Please check again. Your PDF report will also be sent by email once processing completes.",
        }));
      }
    };

    poll();
    return () => {
      active = false;
      if (timer) window.clearTimeout(timer);
    };
  }, [shouldCheckCard, checkout, retryNonce]);

  const resolvedReportUrl = uatNoPayment ? reportUrl : paidState.reportUrl;
  const progressLabel = `${Math.round(paidState.progress)}%`;

  return <StageShell>
    <p className="eyebrow">Secure checkout</p>
    <h1>{cancelled ? "Payment not completed" : uatNoPayment ? "UAT Test — No Payment selected" : "Payment received"}</h1>
    <p className="lead">{cancelled
      ? "Nothing was charged. Return to your private report link when you are ready to try again."
      : uatNoPayment
        ? emailDelivery === "sent"
          ? "UAT Test — No Payment is enabled for client testing. No Stripe charge was made. Your Full Report has been unlocked, and the confirmation and PDF report emails were accepted by the email provider."
          : "UAT Test — No Payment is enabled for client testing. No Stripe charge was made. Your Full Report has been unlocked, but email delivery is retrying in the background."
        : resolvedReportUrl
          ? "Payment confirmed. Your Full Development Report is ready and will open automatically. A confirmation email and your PDF Full Report are also being sent to your email."
          : "Please don’t close this page. We’re verifying your payment, unlocking your Full Development Report, and preparing your PDF email."}</p>

    {!cancelled && !uatNoPayment && <div style={{ maxWidth: 560, margin: "24px 0 18px" }}>
      <div
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-valuenow={Math.round(paidState.progress)}
        aria-label="Full Report processing progress"
        style={{ height: 14, overflow: "hidden", borderRadius: 999, background: "#e9e1d5", border: "1px solid #ddd2c3" }}
      >
        <span style={{ display: "block", width: `${paidState.progress}%`, height: "100%", borderRadius: 999, background: "linear-gradient(90deg, #6c8fae, #c9a15a 55%, #c1443f)", transition: "width .45s ease" }} />
      </div>
      <div style={{ display: "flex", justifyContent: "space-between", gap: 16, marginTop: 8, fontSize: 13 }}>
        <strong>{progressLabel}</strong>
        <span>{resolvedReportUrl ? "Opening Full Report…" : paidState.stage}</span>
      </div>
      {paidState.pdfEmailStatus && <p className="preview-note" role="status">PDF report email: {paidState.pdfEmailStatus === "sent" ? "sent" : paidState.pdfEmailStatus === "failed" ? "retry required" : "queued for delivery"}.</p>}
    </div>}

    {paidState.error && !resolvedReportUrl && <p className="preview-note" role="status">{paidState.error}</p>}
    {cancelled
      ? <a className="button button--primary" href="/">Return to assessment</a>
      : resolvedReportUrl
        ? <a className="button button--primary" href={resolvedReportUrl}>Open Full Report</a>
        : paidState.checking
          ? <button className="button button--primary" type="button" disabled>Processing Full Report…</button>
          : <button className="button button--primary" type="button" onClick={() => setRetryNonce(value => value + 1)}>Check payment status again</button>}
  </StageShell>;
}

function RemoteReport({ token }) {
  const [state, setState] = React.useState({ loading: true, report: null, error: "" });
  React.useEffect(() => {
    let active = true;
    api.getReport(token)
      .then(report => { if (active) setState({ loading: false, report, error: "" }); })
      .catch(error => { if (active) setState({ loading: false, report: null, error: error.message }); });
    return () => { active = false; };
  }, [token]);

  if (state.loading) return <StageShell><p className="lead">Loading your private report…</p></StageShell>;
  if (state.error) return <StageShell><p className="eyebrow">Private report</p><h1>Link unavailable</h1><p className="lead">This link is invalid, expired or revoked. Request a refreshed link from Atom Global support.</p></StageShell>;
  return <ReportView payload={state.report} token={token} />;
}

function attributionFromLocation() {
  const params = new URLSearchParams(window.location.search);
  return {
    affiliateCode: params.get("ref") || params.get("affiliate") || "",
    attribution: {
      landingPage: window.location.pathname + window.location.search,
      utmSource: params.get("utm_source"),
      utmMedium: params.get("utm_medium"),
      utmCampaign: params.get("utm_campaign"),
      utmContent: params.get("utm_content"),
      utmTerm: params.get("utm_term"),
    },
  };
}

function mockReport(track, answers, participant, session) {
  const totals = track.subscales.map(() => ({ score: 0, count: 0 }));
  let score = 0;
  let scored = 0;
  track.allItems.forEach((item, index) => {
    const raw = answers[index]?.value;
    if (!Number.isInteger(raw) || raw < 1 || raw > 5) return;
    const value = item.d === "K" ? 6 - raw : raw;
    score += value;
    scored += 1;
    totals[item.subIndex].score += value;
    totals[item.subIndex].count += 1;
  });
  const total = Math.round(score / Math.max(1, scored) * 50);
  const profile = track.getProfileFn(total);
  const subscales = Object.fromEntries(track.subscales.map((item, index) => [item.code, totals[index].count ? Math.round(totals[index].score / totals[index].count * 5) : 0]));
  return {
    id: 1,
    sessionId: session.id,
    participantName: participant.name,
    participantEmail: participant.email,
    trackKey: track.key,
    trackName: track.label,
    priceMinor: Math.round(Number(String(track.priceLabel || "0").replace(/[^0-9.]/g, "")) * 100),
    currency: "USD",
    is_unlocked: false,
    pdf_available: false,
    free_report_json: JSON.stringify({
      profile: profile.name,
      total,
      summary: { summary: profile.summary, strengths: profile.strengths.slice(0, 3), watchouts: profile.watchouts.slice(0, 3) },
      subscales,
    }),
    paid_report_json: null,
  };
}

export default function AssessmentAppProduction() {
  const path = window.location.pathname;
  if (path.startsWith("/admin")) return <AdminApp />;
  if (path === "/payment/success") return <PaymentStatus />;
  if (path === "/payment/cancelled") return <PaymentStatus cancelled />;
  if (path.startsWith("/report/")) return <RemoteReport token={path.split("/").filter(Boolean).at(-1)} />;

  const [stage, setStage] = React.useState("select");
  const [trackKey, setTrackKey] = React.useState(null);
  const [participant, setParticipant] = React.useState(blankParticipant);
  const [answers, setAnswers] = React.useState([]);
  const [section, setSection] = React.useState(0);
  const [session, setSession] = React.useState(null);
  const [report, setReport] = React.useState(null);
  const [experience, setExperience] = React.useState({ landing: null, tracks: {} });
  const [error, setError] = React.useState("");
  const [busy, setBusy] = React.useState(false);
  const [saveState, setSaveState] = React.useState("");
  const saveQueueRef = React.useRef(Promise.resolve());
  const saveRevisionRef = React.useRef(0);

  const fallbackTrack = trackKey ? assessmentTracks[trackKey] : null;
  const track = buildRuntimeTrack(fallbackTrack, session?.assessment);
  const remoteExperience = trackKey ? experience.tracks?.[trackKey] || {} : {};

  React.useEffect(() => {
    let active = true;
    api.publicAssessmentExperience()
      .then(data => { if (active) setExperience(data || { landing: null, tracks: {} }); })
      .catch(() => {});
    return () => { active = false; };
  }, []);

  React.useEffect(() => {
    if (!new URLSearchParams(window.location.search).has("resume")) {
      if (isMockMode) localStorage.removeItem("hhaa-v2-preview-session");
      return;
    }
    setBusy(true);
    api.loadSession()
      .then(saved => {
        if (!saved?.trackKey || saved.status === "completed") return;
        setSession(saved);
        setTrackKey(saved.trackKey);
        setParticipant({ ...blankParticipant, ...(saved.participant || {}) });
        setAnswers(saved.answers || []);
        setSection(saved.section || 0);
        setStage("questions");
      })
      .catch(loadError => {
        setError(loadError.message);
        window.history.replaceState({}, "", "/");
      })
      .finally(() => setBusy(false));
  }, []);

  React.useEffect(() => {
    if (!session?.id || !session?.resumeToken || stage !== "questions") return undefined;
    const revision = ++saveRevisionRef.current;
    const payload = { id: session.id, resumeToken: session.resumeToken, participant, answers, section };
    setSaveState("saving");
    const timer = window.setTimeout(() => {
      saveQueueRef.current = saveQueueRef.current
        .catch(() => {})
        .then(() => api.saveSession(payload))
        .then(saved => {
          if (revision !== saveRevisionRef.current) return;
          setSession(current => ({ ...current, ...saved }));
          setSaveState("saved");
          setError("");
        })
        .catch(saveError => {
          if (revision !== saveRevisionRef.current) return;
          setSaveState("error");
          setError(saveError.message);
        });
    }, 500);
    return () => window.clearTimeout(timer);
  }, [answers, section, participant, session?.id, session?.resumeToken, stage]);

  const selectTrack = key => {
    if (!assessmentTracks[key]) return;
    setError("");
    setTrackKey(key);
    const v3Track = buildRuntimeTrack(assessmentTracks[key], null);
    setAnswers(v3Track.allItems.map(() => ({ value: null, note: "" })));
    setStage("intro");
    window.scrollTo(0, 0);
  };

  React.useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.has("resume")) return;
    const directTrack = String(params.get("track") || "").toLowerCase();
    if (assessmentTracks[directTrack]) selectTrack(directTrack);
  }, []);

  const begin = async () => {
    setBusy(true);
    setError("");
    try {
      const created = await api.createSession({ trackKey, participant, section: 0, ...attributionFromLocation() });
      const count = created.assessment?.questions?.length || V3_QUESTION_COUNT;
      setAnswers(Array.from({ length: count }, () => ({ value: null, note: "" })));
      setSession(created);
      setStage("questions");
      window.history.replaceState({}, "", "/assessment");
      window.scrollTo(0, 0);
    } catch (beginError) {
      setError(beginError.message);
    } finally {
      setBusy(false);
    }
  };

  const finish = async () => {
    setBusy(true);
    setError("");
    try {
      const completed = await api.completeSession({ id: session.id, resumeToken: session.resumeToken, participant, answers, section: 9 });
      const reportPayload = isMockMode ? mockReport(track, answers, participant, session) : await api.getReport(completed.reportToken);
      setSession(current => ({ ...current, ...completed }));
      setReport(reportPayload);
      setStage("report");
      window.scrollTo(0, 0);
    } catch (finishError) {
      setError(finishError.message);
    } finally {
      setBusy(false);
    }
  };

  const reset = () => {
    localStorage.removeItem("hhaa-v2-preview-session");
    setStage("select");
    setTrackKey(null);
    setParticipant(blankParticipant);
    setAnswers([]);
    setSection(0);
    setSession(null);
    setReport(null);
    setError("");
    window.history.replaceState({}, "", "/");
    window.scrollTo(0, 0);
  };

  const updateAnswer = (index, value) => setAnswers(current => current.map((answer, answerIndex) => answerIndex === index ? { ...answer, value } : answer));
  const updateNote = (index, note) => setAnswers(current => current.map((answer, answerIndex) => answerIndex === index ? { ...answer, note } : answer));

  if (stage === "select") return <SelectVersion experience={experience} onSelect={selectTrack} />;
  if (stage === "intro" && fallbackTrack) return <TrackIntroduction track={fallbackTrack} remoteExperience={remoteExperience} onBack={() => setStage("select")} onContinue={() => setStage("details")} />;
  if (stage === "details" && fallbackTrack) return <ParticipantDetails track={fallbackTrack} remoteExperience={remoteExperience} participant={participant} setParticipant={setParticipant} onBack={() => setStage("intro")} onContinue={begin} error={error} busy={busy} />;
  if (stage === "questions" && track) return <Questions track={track} remoteExperience={remoteExperience} progressExperience={experience.landing} answers={answers} section={section} setSection={setSection} onBack={() => setStage("details")} onAnswer={updateAnswer} onNote={updateNote} onFinish={finish} saveState={saveState} busy={busy} error={error} />;
  if (stage === "report" && report) return <ReportView payload={report} token={session?.reportToken} onReset={reset} />;
  return <StageShell><p className="eyebrow">Assessment</p><h1>Preparing your experience</h1><p className="lead">{error || "Loading the published assessment…"}</p></StageShell>;
}
