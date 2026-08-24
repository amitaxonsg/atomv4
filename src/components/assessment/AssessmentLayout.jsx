import React from "react";
import { answerChoices, assessmentTracks } from "../../data/assessmentData";
import { landingExperience, participantBaseOptions, trackExperience } from "../../data/assessmentExperience";
import { BrandLogo, useBranding } from "../../branding/BrandContext";

export const blankParticipant = {
  name: "", email: "", ageRange: "", gender: "", role: "", industry: "", region: "", purpose: "", tenure: "",
  department: "", level: "", privacyConsent: false, transactionalConsent: false, marketingConsent: false,
};

const fallbackMeta = {
  personal: { durationMin: 12, durationMax: 15, questionCount: 40, sectionCount: 10, freeReportLabel: "Lite Report Free" },
  newjoiner: { durationMin: 12, durationMax: 15, questionCount: 40, sectionCount: 10, freeReportLabel: "Lite Report Free" },
  manager: { durationMin: 12, durationMax: 16, questionCount: 40, sectionCount: 10, freeReportLabel: "Lite Report Free" },
  executive: { durationMin: 15, durationMax: 18, questionCount: 40, sectionCount: 10, freeReportLabel: "Lite Report Free" },
};

function durationLabel(meta) {
  return meta.durationMin === meta.durationMax ? `~${meta.durationMin} minutes` : `~${meta.durationMin}–${meta.durationMax} minutes`;
}

function PublicBrand({ visible = true }) {
  if (!visible) return null;
  return <a className="latest-public-brand" href="https://www.atomglobal.com" target="_blank" rel="noreferrer"><BrandLogo /></a>;
}

function VisualPanel({ stageKey = "version" }) {
  const { stages } = useBranding();
  const stage = stages?.[stageKey] || stages?.version || {};
  const overlay = Math.max(0, Math.min(90, Number(stage.overlay ?? 40))) / 100;
  const image = stage.image || "/media/stages/reflection-portrait.png";
  return <aside
    className="latest-visual-panel"
    aria-label={stage.alt || "A reflective professional moment"}
    style={{
      backgroundImage: `linear-gradient(rgba(20,16,12,${overlay}),rgba(20,16,12,${overlay})),url("${image}")`,
      backgroundPosition: stage.focalPoint || "52% 50%",
    }}
  >
    <a className="latest-visual-panel__logo" href="https://www.atomglobal.com" target="_blank" rel="noreferrer"><BrandLogo /></a>
    <div className="latest-visual-panel__copy">
      {stage.headline && <h2>{stage.headline}</h2>}
      {stage.supporting && <p>{stage.supporting}</p>}
    </div>
  </aside>;
}

function LatestPage({ children, width = "720", className = "", brandVisible = true, actions = null, stageKey = "version" }) {
  return <div className="latest-questionnaire-shell">
    <VisualPanel stageKey={stageKey} />
    <div className="latest-questionnaire-content">
      <main className={`latest-questionnaire-page ${className}`} style={{ "--latest-default-page-width": `${width}px` }}>
        <PublicBrand visible={brandVisible} />
        {children}
        {actions && <footer className="latest-page-actions">{actions}</footer>}
      </main>
    </div>
  </div>;
}

export function AssessmentMeta({ trackKey }) {
  const { tracks } = useBranding();
  const track = assessmentTracks[trackKey];
  const meta = { ...fallbackMeta[trackKey], ...(tracks[trackKey] || {}) };
  return <div className="latest-assessment-meta">
    <i className="latest-dot latest-dot--heart" />
    <span>Head-Heart Alignment: {track.label} · {meta.freeReportLabel} · {durationLabel(meta)}</span>
    <i className="latest-dot latest-dot--head" />
  </div>;
}

export function StageShell({ children, actions, brandVisible = true, stageKey = "report" }) {
  return <LatestPage width="880" brandVisible={brandVisible} actions={actions} stageKey={stageKey}>{children}</LatestPage>;
}

function VoteCopy({ text }) {
  const value = String(text || "");
  const feel = value.indexOf("feel");
  const reason = value.indexOf("reason");
  if (feel < 0 || reason < 0 || reason <= feel) return value;
  return <>{value.slice(0, feel)}<em>feel</em>{value.slice(feel + 4, reason)}<em>reason</em>{value.slice(reason + 6)}</>;
}

export function SelectVersion({ experience, onSelect }) {
  const landing = landingExperience(experience?.landing);
  const trackOrder = ["personal", "newjoiner", "manager", "executive"];
  const tracks = trackOrder.map(key => assessmentTracks[key]).filter(Boolean);
  const personalTracks = tracks.filter(track => track.key === "personal");
  const corporateTracks = tracks.filter(track => track.key !== "personal");
  const renderTrackCard = track => {
    const remote = experience?.tracks?.[track.key] || {};
    const details = trackExperience(track.key, remote, remote.priceLabel || track.priceLabel);
    const meta = { ...fallbackMeta[track.key], ...remote };
    return <button className="latest-track-card" key={track.key} onClick={() => onSelect(track.key)}>
      <strong>{landing.cardTitlePrefix} {track.label}</strong>
      <span>{details.tagline}</span>
      <small>{meta.questionCount} questions · {meta.sectionCount} sections · {durationLabel(meta)}</small>
    </button>;
  };
  return <LatestPage width="640" className="latest-track-selection" brandVisible={landing.showBrandName} stageKey="version">
    <h1>{landing.title}</h1>
    <p className="latest-copy"><VoteCopy text={landing.primaryCopy} /></p>
    <p className="latest-copy latest-copy--last">{landing.secondaryCopy}</p>
    <section className="latest-track-group" aria-labelledby="personal-assessment-heading">
      <h2 id="personal-assessment-heading">Personal Assessment</h2>
      <div className="latest-track-cards latest-track-cards--single">{personalTracks.map(renderTrackCard)}</div>
    </section>
    <section className="latest-track-group" aria-labelledby="corporate-assessments-heading">
      <h2 id="corporate-assessments-heading">Corporate Assessments</h2>
      <div className="latest-track-cards">{corporateTracks.map(renderTrackCard)}</div>
    </section>
  </LatestPage>;
}

export function TrackIntroduction({ track, remoteExperience, onBack, onContinue }) {
  const experience = trackExperience(track.key, remoteExperience, remoteExperience?.priceLabel || track.priceLabel);
  return <LatestPage width="720" className="latest-track-introduction" stageKey={track.key}>
    <button className="latest-text-back" onClick={onBack}>← Back</button>
    <AssessmentMeta trackKey={track.key} />
    <h1>{experience.introHeadline}</h1>
    <p className="latest-copy"><VoteCopy text={experience.introBody} /></p>
    <p className="latest-copy latest-copy--last">{experience.introOffer}</p>
    <div className="latest-head-heart-grid">
      <article className="latest-heart-card"><strong>{experience.heartLabel}</strong><span>{experience.heartDescription}</span></article>
      <article className="latest-head-card"><strong>{experience.headLabel}</strong><span>{experience.headDescription}</span></article>
    </div>
    <button className="latest-primary-button" onClick={onContinue}>Begin the free assessment →</button>
  </LatestPage>;
}

function SelectField({ label, options = [], value, onChange, required = true }) {
  return <label className="latest-field"><span>{label}</span><select value={value} onChange={onChange} required={required}><option value="">Select…</option>{options.map(option => <option key={option} value={option}>{option}</option>)}</select></label>;
}

export function ParticipantDetails({ track, remoteExperience, participant, setParticipant, onBack, onContinue, error, busy }) {
  const experience = trackExperience(track.key, remoteExperience, remoteExperience?.priceLabel || track.priceLabel);
  const config = experience.intake;
  const update = key => event => setParticipant(current => ({ ...current, [key]: event.target.type === "checkbox" ? event.target.checked : event.target.value }));
  const companyTriggers = Array.isArray(config.companyRoleTriggers) ? config.companyRoleTriggers : [];
  const showCompanyFields = Boolean(config.hasCompanyFields && companyTriggers.includes(participant.role));
  const contextComplete = [participant.ageRange, participant.role, participant.industry, participant.region, participant.purpose, participant.tenure].every(Boolean);
  const companyComplete = !showCompanyFields || Boolean(participant.department && participant.level);
  const valid = participant.name.trim() && /.+@.+\..+/.test(participant.email) && contextComplete && companyComplete && participant.privacyConsent && participant.transactionalConsent;

  return <LatestPage width="480" className="latest-intake-page" stageKey="participant">
    <button className="latest-text-back" onClick={onBack}>← Back</button>
    <h1>Before you begin</h1>
    <p className="latest-copy latest-copy--last">A few details so your report can be sent to you and personalised correctly. Nothing here is identifying beyond your name and email — the rest is broad categories only.</p>
    {error && <p className="form-error" role="alert">{error}</p>}

    <div className="latest-intake-grid latest-intake-grid--identity">
      <label className="latest-field"><span>Name *</span><input autoComplete="name" value={participant.name} onChange={update("name")} placeholder="Your full name" required /></label>
      <SelectField label="Age range *" options={participantBaseOptions.ageRanges} value={participant.ageRange} onChange={update("ageRange")} />
      <SelectField label="Gender" options={participantBaseOptions.genderOptions} value={participant.gender} onChange={update("gender")} required={false} />
      <label className="latest-field"><span>Email address *</span><input type="email" autoComplete="email" value={participant.email} onChange={update("email")} placeholder="you@example.com" required /></label>
    </div>

    <div className="latest-context-divider">A little more context</div>
    <div className="latest-intake-grid latest-intake-grid--context">
      <SelectField label={config.whoLabel} options={config.whoOptions} value={participant.role} onChange={update("role")} />
      <SelectField label={config.whatLabel} options={config.whatOptions} value={participant.industry} onChange={update("industry")} />
      <SelectField label={config.whereLabel} options={config.whereOptions} value={participant.region} onChange={update("region")} />
      <SelectField label={config.whyLabel} options={config.whyOptions} value={participant.purpose} onChange={update("purpose")} />
      <SelectField label={config.howLabel} options={config.howOptions} value={participant.tenure} onChange={update("tenure")} />
      {showCompanyFields && <>
        <SelectField label={config.departmentLabel || "Department *"} options={config.departmentOptions || []} value={participant.department} onChange={update("department")} />
        <SelectField label={config.levelLabel || "Level *"} options={config.levelOptions || []} value={participant.level} onChange={update("level")} />
      </>}
    </div>

    <p className="latest-intake-note">Used to send you a copy of your report, and to help us understand who this assessment actually helps.</p>
    <fieldset className="latest-consents"><legend>Privacy and communication</legend>
      <label><input type="checkbox" checked={participant.privacyConsent} onChange={update("privacyConsent")} /><span>I consent to my answers being processed for this assessment. *</span></label>
      <label><input type="checkbox" checked={participant.transactionalConsent} onChange={update("transactionalConsent")} /><span>Send essential messages including my resume link and report. *</span></label>
      <label><input type="checkbox" checked={participant.marketingConsent} onChange={update("marketingConsent")} /><span>Send occasional Atom Global insights. Optional.</span></label>
    </fieldset>
    <button className="latest-primary-button latest-primary-button--wide" disabled={!valid || busy} onClick={onContinue}>{busy ? "Creating secure session…" : "Continue to assessment →"}</button>
  </LatestPage>;
}

function ProgressMessage({ completed, copy }) {
  const landing = landingExperience(copy);
  if (completed === 20) {
    return <div className="latest-progress-message" role="status"><strong>{landing.halfwayTitle}</strong><span>{landing.halfwayBody}</span></div>;
  }
  if (completed === 40) {
    return <div className="latest-progress-message" role="status"><strong>{landing.completeTitle}</strong><span>{landing.completeBody}</span></div>;
  }
  return null;
}

export function Questions({ track, remoteExperience, progressExperience, answers, onAnswer, onNote, section, setSection, onBack, onFinish, saveState, busy, error }) {
  const experience = trackExperience(track.key, remoteExperience, remoteExperience?.priceLabel || track.priceLabel);
  const landing = landingExperience(progressExperience);
  const hideSectionTitles = landing.hideSectionTitles !== false;
  const subscale = track.subscales[section];
  const offset = track.subscales.slice(0, section).reduce((total, item) => total + item.items.length, 0);
  const canContinue = answers.slice(offset, offset + subscale.items.length).every(answer => answer?.value != null);
  const answered = answers.filter(answer => answer?.value != null).length;
  const choices = track.answerChoices || answerChoices;
  const progress = Math.round(answered / Math.max(1, track.allItems.length) * 100);
  const saveLabel = saveState === "saving" ? "Saving…" : saveState === "saved" ? "Saved" : saveState === "error" ? "Save issue" : "";
  const lastSection = section === track.subscales.length - 1;
  const sectionEnd = offset + subscale.items.length;
  const goBack = () => section ? setSection(section - 1) : onBack();
  const goForward = () => lastSection ? onFinish() : setSection(section + 1);

  return <LatestPage width="720" className="latest-questions-page" stageKey={track.key}>
    <div className="latest-question-progress">
      <div><span>Section {section + 1} of {track.subscales.length}</span><span>{answered}/{track.allItems.length} answered{saveLabel ? ` · ${saveLabel}` : ""}</span></div>
      <i><b style={{ width: `${progress}%` }} /></i>
    </div>
    {hideSectionTitles ? <>
      <p className="latest-section-code">Section {section + 1} of {track.subscales.length}</p>
      <h1>Assessment questions</h1>
    </> : <>
      <p className="latest-section-code">{subscale.name} · Section {section + 1} of {track.subscales.length}</p>
      <h1>{subscale.name}</h1>
      <p className="latest-copy latest-copy--last">{subscale.blurb}</p>
    </>}
    {error && <p className="form-error" role="alert">{error}</p>}

    <div className="latest-question-list">{subscale.items.map((item, itemIndex) => {
      const answerIndex = offset + itemIndex;
      const current = answers[answerIndex] || { value: null, note: "" };
      const labelId = `question-${answerIndex}-label`;
      return <div className="latest-question-card" role="group" aria-labelledby={labelId} key={item.id || item.t}>
        <div className="latest-question-legend" id={labelId}><span>{answerIndex + 1}.</span> {item.t}</div>
        <div className="latest-scale-options">{choices.map((choice, choiceIndex) => {
          const value = choiceIndex + 1;
          return <label className={current.value === value ? "selected" : ""} key={choice}><input type="radio" name={`question-${answerIndex}`} checked={current.value === value} onChange={() => onAnswer(answerIndex, value)} /><strong>{value}</strong><span>{choice}</span></label>;
        })}</div>
        {experience.allowNotApplicable && <div className="latest-na-row"><label className={current.value === "NA" ? "selected" : ""}><input type="radio" name={`question-${answerIndex}`} checked={current.value === "NA"} onChange={() => onAnswer(answerIndex, "NA")} />N/A — doesn’t apply / can’t answer</label></div>}
      </div>;
    })}</div>

    {canContinue && <ProgressMessage completed={sectionEnd} copy={progressExperience} />}

    {experience.allowAnswerNotes && lastSection && <details className="latest-answer-note-dropdown latest-final-note" open={Boolean(answers[answers.length - 1]?.note)}>
      <summary>Add more (optional)</summary>
      <textarea className="latest-answer-note" rows="3" value={answers[answers.length - 1]?.note || ""} onChange={event => onNote(answers.length - 1, event.target.value)} placeholder="Is there anything else you would like to add?" />
    </details>}

    <div className="latest-question-navigation">
      <button className="latest-secondary-button" onClick={goBack}>← Back</button>
      <button className="latest-primary-button" disabled={!canContinue || busy} onClick={goForward}>{busy ? "Preparing report…" : lastSection ? "See my result →" : "Next section →"}</button>
    </div>
  </LatestPage>;
}
