import React from "react";
import { api } from "../../api/client";
import { DataTable, Empty, Notice, PageHeader, Spinner, dateTime, useLoader } from "./AdminShared";

const statusLabels = {
  new: "New",
  clarification_requested: "Clarification requested",
  accepted: "Accepted",
  in_progress: "In progress",
  ready_for_review: "Ready for review",
  done: "Done",
  declined: "Declined",
};

const modules = [
  "Public assessment", "Participant details", "Questions", "Reports", "Payments",
  "Dashboard", "Participants", "Assessments", "Content", "Branding", "Email",
  "Affiliates", "Analytics", "SEO", "Settings", "Help", "General",
];

function Summary({ summary = {} }) {
  const items = [
    ["New", summary.new || 0],
    ["Needs clarification", summary.clarification || 0],
    ["In progress", summary.active || 0],
    ["Ready for review", summary.review || 0],
    ["Done", summary.done || 0],
  ];
  return <section className="metric-grid feedback-summary">{items.map(([label, value]) => <article className="metric" key={label}><span>{label}</span><strong>{value}</strong></article>)}</section>;
}

function FeedbackDrawer({ item, canManage, onClose, onChanged }) {
  const [form, setForm] = React.useState({ status: item.status, priority: item.priority, message: "", resolution: item.resolution || "" });
  const [notice, setNotice] = React.useState("");
  const [busy, setBusy] = React.useState(false);

  React.useEffect(() => setForm({ status: item.status, priority: item.priority, message: "", resolution: item.resolution || "" }), [item.id, item.status, item.priority, item.resolution]);

  const save = async () => {
    setBusy(true);
    setNotice("");
    try {
      const updated = await api.updateFeedback(item.id, form);
      setNotice("Feedback status updated. Any required client email has been queued.");
      onChanged(updated);
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  };

  const sync = async () => {
    setBusy(true);
    setNotice("");
    try {
      const updated = await api.syncFeedbackGitHub(item.id);
      setNotice("GitHub synchronisation completed or recorded for review.");
      onChanged(updated);
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  };

  return <div className="admin-drawer"><div className="admin-drawer__panel admin-drawer__panel--wide feedback-drawer">
    <button className="admin-drawer__close" onClick={onClose} aria-label="Close feedback details">×</button>
    <p className="eyebrow">Feedback #{item.id}</p>
    <h2>{item.title}</h2>
    <div className="feedback-meta">
      <span className={`status status--${item.status}`}>{statusLabels[item.status] || item.status}</span>
      <span>{item.feedbackType}</span><span>{item.moduleName}</span><span>{item.priority} priority</span>
    </div>
    <p>{item.details}</p>
    {item.expectedOutcome && <section className="admin-card"><h3>Expected outcome</h3><p>{item.expectedOutcome}</p></section>}
    <dl className="admin-definition">
      <div><dt>Submitted by</dt><dd>{item.submitterName}<small>{item.submitterEmail}</small></dd></div>
      <div><dt>Submitted</dt><dd>{dateTime(item.createdAt)}</dd></div>
      <div><dt>Page</dt><dd>{item.pageUrl ? <a href={item.pageUrl} target="_blank" rel="noreferrer">Open page</a> : "—"}</dd></div>
      <div><dt>Attachment</dt><dd>{item.attachmentUrl ? <a href={item.attachmentUrl} target="_blank" rel="noreferrer">Open attachment</a> : "—"}</dd></div>
      <div><dt>GitHub</dt><dd>{item.githubIssueUrl ? <a href={item.githubIssueUrl} target="_blank" rel="noreferrer">Issue #{item.githubIssueNumber}</a> : String(item.githubSyncStatus || "pending").replaceAll("_", " ")}</dd></div>
    </dl>
    {item.githubLastError && <Notice type="error">GitHub sync: {item.githubLastError}</Notice>}
    <Notice>{notice}</Notice>

    {canManage && <section className="admin-card editor-form">
      <h3>Update progress</h3>
      <div className="form-grid">
        <label>Status<select value={form.status} onChange={event => setForm(current => ({ ...current, status: event.target.value }))}>{Object.entries(statusLabels).map(([key, label]) => <option value={key} key={key}>{label}</option>)}</select></label>
        <label>Priority<select value={form.priority} onChange={event => setForm(current => ({ ...current, priority: event.target.value }))}><option>low</option><option>normal</option><option>high</option><option>urgent</option></select></label>
        <label className="form-grid__wide">Message or clarification<textarea rows="4" value={form.message} onChange={event => setForm(current => ({ ...current, message: event.target.value }))} placeholder="For clarification requests, explain exactly what is needed. Sunil will be asked to reply by email." /></label>
        <label className="form-grid__wide">Completion note<textarea rows="4" value={form.resolution} onChange={event => setForm(current => ({ ...current, resolution: event.target.value }))} placeholder="Required when marking the feedback Done." /></label>
      </div>
      <div className="admin-header-actions"><button className="button button--primary" disabled={busy} onClick={save}>{busy ? "Saving…" : "Save status"}</button><button className="button" disabled={busy} onClick={sync}>Retry GitHub sync</button></div>
    </section>}

    <section className="admin-card">
      <div className="card-heading"><h3>Change history</h3></div>
      <div className="feedback-timeline">{(item.updates || []).map(update => <article key={update.id}><i /><div><strong>{statusLabels[update.status] || update.updateType}</strong><p>{update.message || "Status updated."}</p><small>{update.adminName || "System"} · {dateTime(update.createdAt)}</small></div></article>)}</div>
    </section>
  </div></div>;
}

function FeedbackConfiguration({ loader, onSaved }) {
  const [form, setForm] = React.useState({ githubRepository: "amitaxonsg/atomglobal-hhaa-v2", githubToken: "", clientEmail: "sunil.setpaul@atomglobal.com", internalEmail: "amit@axon.com.sg", supportEmail: "amit@axon.com.sg", issuePrefix: "Client feedback" });
  const [busy, setBusy] = React.useState(false);
  const [notice, setNotice] = React.useState("");

  React.useEffect(() => {
    if (!loader.data) return;
    setForm(current => ({ ...current, ...loader.data, githubToken: "" }));
  }, [loader.data]);

  const save = async () => {
    setBusy(true);
    setNotice("");
    try {
      const result = await api.saveFeedbackConfiguration(form);
      setNotice(`Feedback routing saved. GitHub token ${result.githubTokenConfigured ? "is configured" : "is still missing"}.`);
      setForm(current => ({ ...current, githubToken: "" }));
      await loader.refresh();
      onSaved(loader.data?.clientEmail || form.clientEmail);
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  };

  return <section className="admin-card editor-form feedback-configuration">
    <div className="card-heading"><div><h2>GitHub and email routing</h2><small>Owner/administrator only</small></div><span className={`status ${loader.data?.githubTokenConfigured ? "status--sent" : "status--retry"}`}>{loader.data?.githubTokenConfigured ? "GitHub connected" : "Token required"}</span></div>
    <Notice>{notice}</Notice><Notice type="error">{loader.error}</Notice>
    <div className="form-grid">
      <label>GitHub repository<input value={form.githubRepository} onChange={event => setForm(current => ({ ...current, githubRepository: event.target.value }))} placeholder="owner/repository" /></label>
      <label>Issue title prefix<input value={form.issuePrefix} onChange={event => setForm(current => ({ ...current, issuePrefix: event.target.value }))} /></label>
      <label>Client update email<input type="email" value={form.clientEmail} onChange={event => setForm(current => ({ ...current, clientEmail: event.target.value }))} /></label>
      <label>Internal notification email<input type="email" value={form.internalEmail} onChange={event => setForm(current => ({ ...current, internalEmail: event.target.value }))} /></label>
      <label>Clarification/support email<input type="email" value={form.supportEmail} onChange={event => setForm(current => ({ ...current, supportEmail: event.target.value }))} /></label>
      <label>New fine-grained GitHub token<input type="password" autoComplete="new-password" value={form.githubToken} onChange={event => setForm(current => ({ ...current, githubToken: event.target.value }))} placeholder={loader.data?.githubTokenConfigured ? "Leave blank to keep current token" : "Repository Issues: read and write"} /></label>
    </div>
    <p className="hint">Create a fine-grained token restricted to this repository with Issues read/write only. The token is encrypted in MariaDB and is never returned to the browser. Leaving the token blank keeps the current value.</p>
    <button className="button button--primary" disabled={busy || loader.loading} onClick={save}>{busy ? "Saving…" : "Save feedback routing"}</button>
  </section>;
}

export function FeedbackPage({ initialSearch = "", initialId = null, permissions = [], user }) {
  const canManage = permissions.includes("*") || permissions.includes("feedback.manage");
  const [query, setQuery] = React.useState({ search: initialSearch || "", status: "", priority: "", limit: 250 });
  const loader = useLoader(() => api.adminFeedback(query), [query.search, query.status, query.priority]);
  const configuration = useLoader(() => canManage ? api.feedbackConfiguration() : Promise.resolve(null), [canManage]);
  const [selected, setSelected] = React.useState(null);
  const [form, setForm] = React.useState({
    submitterName: user?.displayName || "Sunil Setpaul",
    submitterEmail: "sunil.setpaul@atomglobal.com",
    feedbackType: "improvement",
    moduleName: "General",
    priority: "normal",
    title: "",
    details: "",
    expectedOutcome: "",
    pageUrl: window.location.href,
    attachmentUrl: "",
  });
  const [notice, setNotice] = React.useState("");
  const [busy, setBusy] = React.useState(false);

  React.useEffect(() => setQuery(current => ({ ...current, search: initialSearch || "" })), [initialSearch]);
  React.useEffect(() => {
    if (configuration.data?.clientEmail) setForm(current => ({ ...current, submitterEmail: configuration.data.clientEmail }));
  }, [configuration.data?.clientEmail]);
  React.useEffect(() => {
    if (!initialId) return;
    api.adminFeedbackDetail(initialId).then(setSelected).catch(error => setNotice(error.message));
  }, [initialId]);

  const submit = async event => {
    event.preventDefault();
    setBusy(true);
    setNotice("");
    try {
      const created = await api.submitFeedback(form);
      setSelected(created);
      setNotice(`Feedback #${created.id} submitted. An acknowledgement email is queued and the GitHub status is ${String(created.githubSyncStatus || "pending").replaceAll("_", " ")}.`);
      setForm(current => ({ ...current, title: "", details: "", expectedOutcome: "", attachmentUrl: "", pageUrl: window.location.href }));
      loader.refresh();
    } catch (error) {
      setNotice(error.message);
    } finally {
      setBusy(false);
    }
  };

  const open = item => api.adminFeedbackDetail(item.id).then(setSelected).catch(error => setNotice(error.message));
  const changed = updated => { setSelected(updated); loader.refresh(); };

  return <>
    <PageHeader eyebrow="Client collaboration" title="Feedback" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} />
    <Notice>{notice}</Notice><Notice type="error">{loader.error}</Notice>
    <Summary summary={loader.data?.summary} />

    <div className="admin-grid feedback-layout">
      <form className="admin-card editor-form" onSubmit={submit}>
        <div className="card-heading"><div><h2>Submit feedback</h2><small>Saved in the portal, emailed and synchronised to GitHub.</small></div></div>
        <div className="form-grid">
          <label>Name<input value={form.submitterName} onChange={event => setForm(current => ({ ...current, submitterName: event.target.value }))} required /></label>
          <label>Email for updates<input type="email" value={form.submitterEmail} onChange={event => setForm(current => ({ ...current, submitterEmail: event.target.value }))} required /></label>
          <label>Type<select value={form.feedbackType} onChange={event => setForm(current => ({ ...current, feedbackType: event.target.value }))}><option value="bug">Bug</option><option value="improvement">Improvement</option><option value="content">Content change</option><option value="question">Question</option><option value="other">Other</option></select></label>
          <label>Area<select value={form.moduleName} onChange={event => setForm(current => ({ ...current, moduleName: event.target.value }))}>{modules.map(module => <option key={module}>{module}</option>)}</select></label>
          <label>Priority<select value={form.priority} onChange={event => setForm(current => ({ ...current, priority: event.target.value }))}><option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
          <label className="form-grid__wide">Title<input value={form.title} onChange={event => setForm(current => ({ ...current, title: event.target.value }))} placeholder="A short description of the requested change" required /></label>
          <label className="form-grid__wide">Details<textarea rows="7" value={form.details} onChange={event => setForm(current => ({ ...current, details: event.target.value }))} placeholder="What happened, what should change, and any steps needed to reproduce it." required /></label>
          <label className="form-grid__wide">Expected outcome<textarea rows="4" value={form.expectedOutcome} onChange={event => setForm(current => ({ ...current, expectedOutcome: event.target.value }))} placeholder="Describe what a successful result should look like." /></label>
          <label>Page URL<input type="url" value={form.pageUrl} onChange={event => setForm(current => ({ ...current, pageUrl: event.target.value }))} /></label>
          <label>Screenshot or file link<input type="url" value={form.attachmentUrl} onChange={event => setForm(current => ({ ...current, attachmentUrl: event.target.value }))} placeholder="Optional secure link" /></label>
        </div>
        <p className="hint">Sunil receives an acknowledgement and later status emails. When clarification is needed, the message asks him to reply by email and include the feedback number.</p>
        <button className="button button--primary" disabled={busy}>{busy ? "Submitting…" : "Submit feedback"}</button>
      </form>

      <section className="admin-card feedback-register">
        <div className="card-heading"><div><h2>Feedback register</h2><small>Click a row to view the complete change history.</small></div></div>
        <div className="admin-filters"><input type="search" placeholder="Search title, email, module or GitHub number" value={query.search} onChange={event => setQuery(current => ({ ...current, search: event.target.value }))} /><select value={query.status} onChange={event => setQuery(current => ({ ...current, status: event.target.value }))}><option value="">All statuses</option>{Object.entries(statusLabels).map(([key, label]) => <option value={key} key={key}>{label}</option>)}</select><select value={query.priority} onChange={event => setQuery(current => ({ ...current, priority: event.target.value }))}><option value="">All priorities</option><option>urgent</option><option>high</option><option>normal</option><option>low</option></select></div>
        {loader.loading ? <Spinner /> : (loader.data?.items || []).length ? <DataTable columns={["ID", "Feedback", "Area", "Priority", "Status", "GitHub", "Updated"]} rows={loader.data.items.map(item => [item.id, <><strong>{item.title}</strong><span>{item.submitterName}</span></>, item.moduleName, item.priority, statusLabels[item.status] || item.status, item.githubIssueNumber ? `#${item.githubIssueNumber}` : item.githubSyncStatus, dateTime(item.updatedAt)])} onRow={index => open(loader.data.items[index])} /> : <Empty>No feedback has been submitted.</Empty>}
      </section>
    </div>

    {canManage && <FeedbackConfiguration loader={configuration} onSaved={clientEmail => setForm(current => ({ ...current, submitterEmail: clientEmail }))} />}
    {selected && <FeedbackDrawer item={selected} canManage={canManage} onClose={() => setSelected(null)} onChanged={changed} />}
  </>;
}

const helpSections = [
  ["Dashboard", "Use the activity graph, conversion funnel and track-progress panels to see starts, completions, paid reports, revenue and email health. Refresh after a test or client session."],
  ["Participants", "Search by name or email, filter by track and status, then open a participant to review answers, progress, reports, payments, consent and email history. Export or anonymise only when authorised."],
  ["Assessments", "Clone a published version before editing. A version can be published only when it contains exactly 50 active questions across 10 sections. Published versions are immutable."],
  ["Content and branding", "Edit stage wording and images under Content. Prepare branding as a draft, preview it, then publish it. Browser, email and report logos are managed separately."],
  ["Reports and payments", "Reports begin as Lite reports. A Full Report is unlocked only after a verified Stripe webhook or an authorised administrator action. You can regenerate PDFs, rotate links, resend, lock or revoke reports."],
  ["Email", "Select a template, edit its subject, HTML and plain-text content, preserve the listed {{variables}}, preview it, enter a recipient and send the selected template test. The delivery queue records provider IDs, retries and failures."],
  ["Feedback", "Submit each requested change separately with a clear title, page and expected outcome. The portal logs the request, sends acknowledgement email and creates a GitHub issue when the secure token is configured. Status changes and completion notes remain in the timeline."],
  ["Security and support", "Do not share passwords, API keys or private report links in feedback. For clarification, reply to the feedback email and include its reference number. Automatic Git deployment remains disabled; production releases are deployed with backup and rollback checks."],
];

export function HelpPage() {
  const [search, setSearch] = React.useState("");
  const term = search.trim().toLowerCase();
  const items = helpSections.filter(([title, text]) => !term || `${title} ${text}`.toLowerCase().includes(term));
  return <>
    <PageHeader eyebrow="User guide" title="Help and how it works" />
    <section className="admin-card help-intro"><h2>Growth Alignment administration</h2><p>This workspace connects the participant assessment, PHP API, MariaDB records, reports, email queue, payment events and audit history. Changes made here affect the live system only after you save or publish them.</p><input type="search" placeholder="Search help topics" value={search} onChange={event => setSearch(event.target.value)} /></section>
    <div className="help-grid">{items.map(([title, text], index) => <article className="admin-card" key={title}><span>{String(index + 1).padStart(2, "0")}</span><h2>{title}</h2><p>{text}</p></article>)}</div>
    {!items.length && <Empty>No help topic matches that search.</Empty>}
    <section className="admin-card help-contact"><h2>Need clarification?</h2><p>Email <a href="mailto:amit@axon.com.sg">amit@axon.com.sg</a> with the feedback number and a screenshot or exact steps. Do not send passwords or API keys.</p></section>
  </>;
}
