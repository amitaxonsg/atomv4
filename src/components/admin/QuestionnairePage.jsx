import React from "react";
import { api } from "../../api/client";
import { adminQuestionnaireConfiguration, saveQuestionnaireLanding } from "../../api/questionnaireCms";
import { experienceDefaults, landingDefaults, questionnaireReference } from "../../data/assessmentExperience";
import { Notice, PageHeader, Spinner, useLoader } from "./AdminShared";

const fieldDefinitions = [
  ["who", "Who / current situation"],
  ["what", "Work, industry or area of focus"],
  ["where", "Location"],
  ["why", "Reason for taking the assessment"],
  ["how", "Current chapter or tenure"],
];

function normalise(item, trackKey) {
  const fallback = experienceDefaults[trackKey] || experienceDefaults.personal;
  const source = item || {};
  return {
    ...fallback,
    ...source,
    tagline: source.tagline || fallback.tagline,
    introHeadline: source.introHeadline || fallback.introHeadline,
    introBody: source.introBody || fallback.introBody,
    introOffer: source.introOffer || fallback.introOffer,
    heartLabel: source.heartLabel || fallback.heartLabel,
    heartDescription: source.heartDescription || fallback.heartDescription,
    headLabel: source.headLabel || fallback.headLabel,
    headDescription: source.headDescription || fallback.headDescription,
    intake: { ...fallback.intake, ...(source.intake || {}) },
    allowNotApplicable: source.allowNotApplicable ?? fallback.allowNotApplicable,
    allowAnswerNotes: source.allowAnswerNotes ?? fallback.allowAnswerNotes,
  };
}

function optionsToText(options) {
  return Array.isArray(options) ? options.join("\n") : "";
}

function textToOptions(value) {
  return String(value || "").split("\n").map(item => item.trim()).filter(Boolean);
}

function LandingEditor({ initial, onSaved }) {
  const [form, setForm] = React.useState({ ...landingDefaults, ...(initial || {}) });
  const [notice, setNotice] = React.useState("");
  const [busy, setBusy] = React.useState(false);

  React.useEffect(() => setForm({ ...landingDefaults, ...(initial || {}) }), [initial]);
  const update = key => event => setForm(current => ({ ...current, [key]: event.target.type === "checkbox" ? event.target.checked : event.target.value }));
  const save = async event => {
    event.preventDefault();
    setBusy(true);
    setNotice("");
    try {
      const saved = await saveQuestionnaireLanding(form);
      setForm({ ...landingDefaults, ...saved });
      setNotice("Questionnaire landing, topic-visibility rule and progress copy saved. The approved left-image branding remains controlled under Content and Branding.");
      onSaved?.();
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  };

  return <form className="admin-card editor-form questionnaire-landing-editor" onSubmit={save}>
    <div className="card-heading"><div><h2>Public landing and progress content</h2><small>The public V3 assessment is 40 questions across 10 areas. The 50-question source bank remains retained for history and rollback.</small></div></div>
    <Notice>{notice}</Notice>
    <div className="form-grid">
      <label className="form-grid__wide">Main heading<input value={form.title || ""} onChange={update("title")} /></label>
      <label className="form-grid__wide">First introduction paragraph<textarea rows="4" value={form.primaryCopy || ""} onChange={update("primaryCopy")} /></label>
      <label className="form-grid__wide">Second introduction paragraph<textarea rows="4" value={form.secondaryCopy || ""} onChange={update("secondaryCopy")} /></label>
      <label>Track-card title prefix<input value={form.cardTitlePrefix || ""} onChange={update("cardTitlePrefix")} /></label>
      <label className="check-row"><input type="checkbox" checked={Boolean(form.showBrandName)} onChange={update("showBrandName")} /> Show the Atom Global logo on mobile where the left image is hidden</label>
      <label className="check-row form-grid__wide"><input type="checkbox" checked={form.hideSectionTitles !== false} onChange={update("hideSectionTitles")} /> Hide assessment-area/topic titles while participants answer questions, so the subject of each question group is not revealed</label>
      <label className="form-grid__wide">Question 20 milestone heading<input value={form.halfwayTitle || ""} onChange={update("halfwayTitle")} /></label>
      <label className="form-grid__wide">Question 20 milestone message<textarea rows="3" value={form.halfwayBody || ""} onChange={update("halfwayBody")} /></label>
      <label className="form-grid__wide">Question 40 completion heading<input value={form.completeTitle || ""} onChange={update("completeTitle")} /></label>
      <label className="form-grid__wide">Question 40 completion message<textarea rows="3" value={form.completeBody || ""} onChange={update("completeBody")} /></label>
    </div>
    <button className="button button--primary" disabled={busy}>{busy ? "Saving…" : "Save landing and progress copy"}</button>
  </form>;
}

function TrackEditor({ track, onSaved }) {
  const loader = useLoader(() => api.adminAssessmentExperience(track.trackId), [track.trackId]);
  const [form, setForm] = React.useState(() => normalise(null, track.trackKey));
  const [notice, setNotice] = React.useState("");
  const [busy, setBusy] = React.useState(false);

  React.useEffect(() => {
    if (loader.data) setForm(normalise(loader.data, track.trackKey));
  }, [loader.data, track.trackKey]);

  if (loader.loading) return <Spinner />;

  const update = key => event => setForm(current => ({ ...current, [key]: event.target.type === "checkbox" ? event.target.checked : event.target.value }));
  const updateIntake = (key, value) => setForm(current => ({ ...current, intake: { ...current.intake, [key]: value } }));
  const save = async event => {
    event.preventDefault();
    setBusy(true);
    setNotice("");
    try {
      const saved = await api.saveAssessmentExperience(track.trackId, form);
      setForm(normalise(saved, track.trackKey));
      setNotice("Questionnaire process saved. Public metadata remains fixed at 40 questions across 10 areas. Existing participant sessions remain pinned to their original published question version and snapshots.");
      onSaved?.();
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  };

  return <form className="questionnaire-cms editor-form" onSubmit={save}>
    <Notice>{notice}</Notice><Notice type="error">{loader.error}</Notice>
    <section className="admin-card">
      <div className="card-heading"><div><h2>Track card and introduction</h2><small>Controls this public track card and the following introduction screen.</small></div></div>
      <div className="form-grid">
        <label className="form-grid__wide">Track-card description<textarea rows="3" value={form.tagline || ""} onChange={update("tagline")} /></label>
        <label className="form-grid__wide">Introduction heading<input value={form.introHeadline || `Growth Alignment: ${track.trackName}`} onChange={update("introHeadline")} /></label>
        <label className="form-grid__wide">Introduction copy<textarea rows="5" value={form.introBody || ""} onChange={update("introBody")} /></label>
        <label className="form-grid__wide">Lite/Full report offer<textarea rows="4" value={form.introOffer || ""} onChange={update("introOffer")} /><small>Use <code>{"{{price}}"}</code> where the CMS track price should appear.</small></label>
        <label>Heart label<input value={form.heartLabel || ""} onChange={update("heartLabel")} /></label>
        <label>Head label<input value={form.headLabel || ""} onChange={update("headLabel")} /></label>
        <label>Heart explanation<input value={form.heartDescription || ""} onChange={update("heartDescription")} /></label>
        <label>Head explanation<input value={form.headDescription || ""} onChange={update("headDescription")} /></label>
      </div>
    </section>

    <section className="admin-card">
      <div className="card-heading"><div><h2>Participant intake</h2><small>Name, email, age range, gender and consent are standard. Configure the context questions below.</small></div></div>
      <div className="questionnaire-intake-grid">
        {fieldDefinitions.map(([prefix, title]) => <article className="questionnaire-intake-field" key={prefix}>
          <h3>{title}</h3>
          <label>Question label<input value={form.intake?.[`${prefix}Label`] || ""} onChange={event => updateIntake(`${prefix}Label`, event.target.value)} /></label>
          <label>Options — one per line<textarea rows="8" value={optionsToText(form.intake?.[`${prefix}Options`])} onChange={event => updateIntake(`${prefix}Options`, textToOptions(event.target.value))} /></label>
        </article>)}
      </div>

      <div className="questionnaire-company-fields">
        <label className="check-row"><input type="checkbox" checked={Boolean(form.intake?.hasCompanyFields)} onChange={event => updateIntake("hasCompanyFields", event.target.checked)} /> Show department and level for selected work roles</label>
        {form.intake?.hasCompanyFields && <div className="form-grid">
          <label className="form-grid__wide">Roles that trigger company fields — one per line<textarea rows="4" value={optionsToText(form.intake?.companyRoleTriggers)} onChange={event => updateIntake("companyRoleTriggers", textToOptions(event.target.value))} /></label>
          <label>Department label<input value={form.intake?.departmentLabel || ""} onChange={event => updateIntake("departmentLabel", event.target.value)} /></label>
          <label>Level label<input value={form.intake?.levelLabel || ""} onChange={event => updateIntake("levelLabel", event.target.value)} /></label>
          <label>Department options — one per line<textarea rows="8" value={optionsToText(form.intake?.departmentOptions)} onChange={event => updateIntake("departmentOptions", textToOptions(event.target.value))} /></label>
          <label>Level options — one per line<textarea rows="8" value={optionsToText(form.intake?.levelOptions)} onChange={event => updateIntake("levelOptions", textToOptions(event.target.value))} /></label>
        </div>}
      </div>
    </section>

    <section className="admin-card">
      <div className="card-heading"><div><h2>Question response process</h2><small>The five scored choices remain versioned with each assessment.</small></div></div>
      <div className="settings-checks">
        <label className="check-row"><input type="checkbox" checked={Boolean(form.allowNotApplicable)} onChange={update("allowNotApplicable")} /> Show “N/A — doesn’t apply / can’t answer” and exclude it from scoring</label>
        <label className="check-row"><input type="checkbox" checked={Boolean(form.allowAnswerNotes)} onChange={update("allowAnswerNotes")} /> Allow an optional note beneath every question</label>
      </div>
      <Notice><strong>Do not replace a question with a different question.</strong> A full meaning change can invalidate comparisons and report interpretation. Under <strong>Assessments</strong>, published versions are immutable and drafts allow only spelling, grammar or clarity corrections while identity, section, position and scoring remain locked.</Notice>
    </section>

    <div className="questionnaire-cms__actions"><button className="button button--primary" disabled={busy}>{busy ? "Saving…" : "Save questionnaire process"}</button></div>
  </form>;
}

export default function QuestionnairePage() {
  const assessments = useLoader(() => api.adminAssessments(), []);
  const configuration = useLoader(() => adminQuestionnaireConfiguration(), []);
  const [trackKey, setTrackKey] = React.useState("personal");
  const [revision, setRevision] = React.useState(0);

  if (assessments.loading || configuration.loading) return <Spinner />;
  const rows = assessments.data?.items || [];
  const tracks = [...new Map(rows.map(item => [item.trackKey, item])).values()];
  const selected = tracks.find(item => item.trackKey === trackKey) || tracks[0];

  return <>
    <PageHeader eyebrow="V3 public process · CMS controlled" title="Questionnaire" actions={<button className="button" onClick={() => { assessments.refresh(); configuration.refresh(); }}>Refresh</button>} />
    <Notice type="error">{assessments.error || configuration.error}</Notice>
    <section className="admin-card questionnaire-reference">
      <div><strong>V3 public assessment verified</strong><span>Four public assessment choices · 10 areas · 40 public questions · 50-question source bank retained for history/rollback</span></div>
      <small>Reference SHA-256: {questionnaireReference.sourceFileSha256.slice(0, 16)}… · Questionnaire SHA-256: {questionnaireReference.questionnaireSha256.slice(0, 16)}…</small>
    </section>

    <LandingEditor initial={configuration.data?.landing} onSaved={configuration.refresh} />

    <div className="questionnaire-track-tabs" role="tablist" aria-label="Questionnaire track">
      {tracks.map(track => {
        const publicTrack = configuration.data?.tracks?.[track.trackKey] || {};
        const price = Number(publicTrack.priceMinor || 0) / 100;
        return <button role="tab" aria-selected={selected?.trackKey === track.trackKey} className={selected?.trackKey === track.trackKey ? "active" : ""} key={track.trackKey} onClick={() => setTrackKey(track.trackKey)}><strong>{track.trackName}</strong><small>{publicTrack.questionCount || 40} public questions · {publicTrack.sectionCount || 10} areas · {track.questionCount} source questions{price > 0 ? ` · USD ${price % 1 === 0 ? price.toFixed(0) : price.toFixed(2)}` : ""}</small></button>;
      })}
    </div>
    {selected && <TrackEditor key={`${selected.trackId}-${revision}`} track={selected} onSaved={() => { setRevision(value => value + 1); configuration.refresh(); }} />}
  </>;
}
