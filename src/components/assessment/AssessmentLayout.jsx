import React from "react";
import { useBrand } from "../../branding/BrandContext";
import { answerChoices } from "../../data/assessmentData";
import { trackExperience, landingExperience } from "../../data/assessmentExperience";

function stageCopy(stages, key, fallbackTitle, fallbackSubtitle = "") {
  const stage = stages?.[key] || {};
  return {
    title: stage.title || fallbackTitle,
    subtitle: stage.subtitle || fallbackSubtitle,
  };
}

export function StageShell({ children, width = "720", stageKey = "version", className = "" }) {
  const brand = useBrand();
  const stage = brand.stages?.[stageKey] || {};
  const style = stage.desktopUrl ? {
    backgroundImage: `linear-gradient(rgba(20, 16, 12, ${stage.overlayOpacity ?? 0}), rgba(20, 16, 12, ${stage.overlayOpacity ?? 0})), url("${stage.desktopUrl}")`,
    backgroundPosition: `${stage.focalX ?? 50}% ${stage.focalY ?? 50}%`,
  } : undefined;
  return <div className={`latest-questionnaire-shell ${className}`}>
    <aside className="latest-visual-panel" style={style}>
      <div className="latest-visual-panel__logo"><img src={brand.logoUrl} alt="Atom Global Consulting" /></div>
      <div className="latest-visual-panel__copy">
        {stage.title && <strong>{stage.title}</strong>}
        {stage.subtitle && <span>{stage.subtitle}</span>}
      </div>
    </aside>
    <main className="latest-questionnaire-content">
      <div className="latest-questionnaire-brand"><img src={brand.logoUrl} alt="Atom Global Consulting" /></div>
      <section className="latest-questionnaire-page" style={{ "--page-width": `${width}px` }}>{children}</section>
    </main>
  </div>;
}

export function LatestPage({ children, width = "720", stageKey = "version", className = "" }) {
  return <StageShell width={width} stageKey={stageKey} className={className}>{children}</StageShell>;
}

export function SelectVersion({ experience, onSelect }) {
  const landing = landingExperience(experience?.landing);
  const [category, setCategory] = React.useState(null);
  const trackOrder = ["personal", "newjoiner", "manager", "executive"];
  const allTracks = trackOrder.map(key => experience?.tracks?.[key]).filter(Boolean);
  const personalTracks = allTracks.filter(track => track.trackKey === "personal");
  const corporateTracks = allTracks.filter(track => track.trackKey !== "personal");
  const visibleTracks = category === "personal" ? personalTracks : corporateTracks;

  return <LatestPage width="820" stageKey="version" className="latest-landing-page">
    <p className="latest-section-code">Growth Alignment</p>
    <h1>{landing.title}</h1>
    <p className="latest-copy">{landing.body}</p>
    {!category ? <>
      <p className="latest-copy latest-copy--last">Choose Personal or Corporate to begin.</p>
      <div className="latest-category-grid">
        <button className="latest-track-card" onClick={() => setCategory("personal")}>
          <strong>Personal Assessment</strong><span>Understand your own Head–Heart patterns and development priorities.</span>
        </button>
        <button className="latest-track-card" onClick={() => setCategory("corporate")}>
          <strong>Corporate Assessments</strong><span>Choose New Joiner, Manager or Executive for role-relevant reflection.</span>
        </button>
      </div>
    </> : <>
      <button className="latest-text-button" onClick={() => setCategory(null)}>← Choose Personal or Corporate</button>
      <div className="latest-track-grid">{visibleTracks.map(track => <button className="latest-track-card" onClick={() => onSelect(track.trackKey)} key={track.trackKey}>
        <strong>{track.trackName}</strong><span>{track.description}</span>
      </button>)}</div>
    </>}
  </LatestPage>;
}

export function TrackIntroduction({ track, remoteExperience, onBack, onContinue }) {
  const experience = trackExperience(track.key, remoteExperience, remoteExperience?.priceLabel || track.priceLabel);
  return <LatestPage width="720" stageKey={track.key}>
    <p className="latest-section-code">{experience.eyebrow}</p>
    <h1>{experience.title}</h1>
    <p className="latest-copy">{experience.intro}</p>
    <div className="latest-intro-meta"><span>{experience.duration}</span><span>{experience.questionCount} questions</span></div>
    <div className="latest-question-navigation">
      <button className="latest-secondary-button" onClick={onBack}>← Back</button>
      <button className="latest-primary-button" onClick={onContinue}>Begin the free assessment →</button>
    </div>
  </LatestPage>;
}

export const blankParticipant = {
  name: "", email: "", company: "", jobTitle: "", department: "", level: "",
  privacyConsent: false, transactionalConsent: false, marketingConsent: false,
};

export function ParticipantDetails({ track, remoteExperience, participant, setParticipant, onBack, onContinue, error, busy }) {
  const experience = trackExperience(track.key, remoteExperience, remoteExperience?.priceLabel || track.priceLabel);
  const update = key => event => setParticipant(current => ({ ...current, [key]: event.target.type === "checkbox" ? event.target.checked : event.target.value }));
  const valid = participant.name.trim() && participant.email.includes("@") && participant.privacyConsent && participant.transactionalConsent;
  return <LatestPage width="720" stageKey={track.key}>
    <p className="latest-section-code">A little more context</p>
    <h1>Tell us about you</h1>
    <p className="latest-copy">This helps us personalise the experience and send your private report securely.</p>
    {error && <p className="form-error" role="alert">{error}</p>}
    <div className="latest-intake-grid">
      <label className="latest-field"><span>Name *</span><input value={participant.name} onChange={update("name")} /></label>
      <label className="latest-field"><span>Email *</span><input type="email" value={participant.email} onChange={update("email")} /></label>
      <label className="latest-field"><span>Company</span><input value={participant.company} onChange={update("company")} /></label>
      <label className="latest-field"><span>Job title</span><input value={participant.jobTitle} onChange={update("jobTitle")} /></label>
      <label className="latest-field"><span>Department</span><select value={participant.department} onChange={update("department")}><option value="">Select</option>{experience.departmentOptions.map(item => <option key={item}>{item}</option>)}</select></label>
      <label className="latest-field"><span>Level</span><select value={participant.level} onChange={update("level")}><option value="">Select</option>{experience.levelOptions.map(item => <option key={item}>{item}</option>)}</select></label>
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
    return <div className="latest-progress-message latest-progress-message--halfway" role="status"><em>Milestone reached</em><strong>{landing.halfwayTitle}</strong><span>{landing.halfwayBody}</span></div>;
  }
  if (completed === 40) {
    return <div className="latest-progress-message latest-progress-message--complete" role="status"><em>Assessment complete</em><strong>{landing.completeTitle}</strong><span>{landing.completeBody}</span></div>;
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
  const moveToSection = nextSection => {
    setSection(nextSection);
    window.requestAnimationFrame(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }));
  };
  const goBack = () => section ? moveToSection(section - 1) : onBack();
  const goForward = () => lastSection ? onFinish() : moveToSection(section + 1);

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