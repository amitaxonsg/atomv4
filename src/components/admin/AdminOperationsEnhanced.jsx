import React from "react";
import { api } from "../../api/client";
import { DataTable, Empty, Notice, PageHeader, Spinner, dateTime, money, useLoader } from "./AdminShared";
export { AnalyticsPage, SeoPage, SettingsPage } from "./AdminOperationsPages";

function useSearch(initialSearch = "") {
  const [search, setSearch] = React.useState(initialSearch || "");
  React.useEffect(() => setSearch(initialSearch || ""), [initialSearch]);
  return [search, setSearch];
}

function includesSearch(item, search, fields) {
  const term = search.trim().toLowerCase();
  if (!term) return true;
  return fields.some(field => String(item[field] ?? "").toLowerCase().includes(term));
}

function booleanSetting(value, fallback = false) {
  if (typeof value === "boolean") return value;
  if (typeof value === "number") return value === 1;
  if (typeof value === "string") return ["1", "true", "yes", "on"].includes(value.trim().toLowerCase());
  return fallback;
}

export function ReportsPage({ initialSearch = "" }) {
  const loader = useLoader(() => api.adminReports(), []);
  const [notice, setNotice] = React.useState("");
  const [search, setSearch] = useSearch(initialSearch);
  const action = async (item, name) => {
    if (["revoke", "lock"].includes(name) && !window.confirm(`${name} this report?`)) return;
    try {
      await api.reportAction(item.id, name === "resend" ? "rotate-and-resend" : name);
      setNotice(`Report ${name} action completed.`);
      loader.refresh();
    } catch (error) { setNotice(error.message); }
  };
  const items = (loader.data?.items || []).filter(item => includesSearch(item, search, ["participantName", "participantEmail", "track", "unlockReason"]));

  return <><PageHeader title="Reports" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} /><Notice>{notice}</Notice><Notice type="error">{loader.error}</Notice>
    <div className="admin-filters"><input type="search" placeholder="Search participant, email or track" value={search} onChange={event => setSearch(event.target.value)} />{search && <button className="button" onClick={() => setSearch("")}>Clear</button>}<span>{items.length} reports</span></div>
    {loader.loading ? <Spinner /> : <section className="admin-card"><DataTable columns={["Participant", "Track", "Status", "Expires", "PDF", "Views", "Actions"]} rows={items.map(item => [<><strong>{item.participantName}</strong><span>{item.participantEmail}</span></>, item.track, item.revokedAt ? "Revoked" : item.unlocked ? "Full report" : "Lite report", dateTime(item.tokenExpiresAt), item.pdfPath ? "Ready" : "Missing", item.viewCount, <div className="table-actions"><button onClick={() => action(item, item.unlocked ? "lock" : "unlock")}>{item.unlocked ? "Lock" : "Unlock"}</button><button onClick={() => action(item, "resend")}>Rotate link & resend</button><button onClick={() => action(item, "regenerate")}>Regenerate PDF</button><button onClick={() => action(item, "revoke")}>Revoke</button></div>])} /></section>}
  </>;
}

export function PaymentsPage({ initialSearch = "" }) {
  const loader = useLoader(() => api.adminPayments(), []);
  const settings = useLoader(() => api.adminSettings(), []);
  const [search, setSearch] = useSearch(initialSearch);
  const [codEnabled, setCodEnabled] = React.useState(false);
  const [notice, setNotice] = React.useState("");

  React.useEffect(() => {
    if (!settings.data) return;
    const explicit = settings.data?.system?.cashOnDeliveryEnabled;
    const migrationDefault = settings.data?.payments?.cashOnDeliveryEnabled;
    setCodEnabled(explicit == null ? booleanSetting(migrationDefault, false) : booleanSetting(explicit, false));
  }, [settings.data]);

  const saveCod = async () => {
    try {
      await api.saveSettings("system", { cashOnDeliveryEnabled: codEnabled });
      setNotice(`UAT no-payment option ${codEnabled ? "enabled" : "disabled"}. The checkout will update on the participant's next report refresh.`);
      settings.refresh();
    } catch (error) {
      setNotice(error.message);
    }
  };

  const items = (loader.data?.items || []).filter(item => includesSearch(item, search, ["participantName", "participantEmail", "track", "status", "provider", "affiliateCode", "stripe_checkout_session_id", "stripe_payment_intent_id"]));
  return <><PageHeader title="Payments" actions={<button className="button" onClick={() => { loader.refresh(); settings.refresh(); }}>Refresh</button>} /><Notice>{notice}</Notice><Notice type="error">{loader.error || settings.error}</Notice>
    <section className="admin-card editor-form" style={{ marginBottom: 20 }}>
      <div className="card-heading"><div><h2>Client UAT payment bypass</h2><small>Temporary testing control — no Stripe charge</small></div><button className="button button--primary" onClick={saveCod}>Save UAT option</button></div>
      <label className="check-row"><input type="checkbox" checked={codEnabled} onChange={event => setCodEnabled(event.target.checked)} /> Enable “Cash on Delivery / UAT — No payment” on the participant checkout</label>
      <p className="hint">When enabled, the participant can complete the checkout flow without paying Stripe. The system records a manual UAT payment, unlocks the Full Report, rotates the private report link and queues the normal success/report emails. Disable this immediately after UAT.</p>
    </section>
    <div className="admin-filters"><input type="search" placeholder="Search participant, payment status or Stripe ID" value={search} onChange={event => setSearch(event.target.value)} />{search && <button className="button" onClick={() => setSearch("")}>Clear</button>}<span>{items.length} payments</span></div>
    {loader.loading ? <Spinner /> : <section className="admin-card"><DataTable columns={["Participant", "Track", "Status", "Amount", "Provider", "Affiliate", "Date"]} rows={items.map(item => [<><strong>{item.participantName}</strong><span>{item.participantEmail}</span></>, item.track, <span className={`status status--${item.status}`}>{item.status}</span>, money(item.amount, item.currency), item.provider, item.affiliateCode || "—", dateTime(item.created_at)])} /></section>}
  </>;
}

function templateVariables(template) {
  const source = `${template?.subject || ""}\n${template?.html_body || ""}\n${template?.text_body || ""}`;
  return [...new Set([...source.matchAll(/{{\s*([A-Za-z0-9_]+)\s*}}/g)].map(match => match[1]))];
}

function renderTemplate(content, variables) {
  return Object.entries(variables).reduce((result, [key, value]) => result.replaceAll(`{{${key}}}`, value).replaceAll(`{{ ${key} }}`, value), content || "");
}

function emailPreviewDocument(content) {
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:26px;background:#f4f0ea;color:#443c35;font:15px/1.65 Arial,Helvetica,sans-serif}.card{max-width:620px;margin:auto;background:#fff;border:1px solid #e5ddd3;border-top:4px solid #c8483f;border-radius:10px;overflow:hidden}.head{padding:28px 30px 14px;text-align:center}.head img{width:190px;max-width:70%;height:auto}.content{padding:10px 34px 32px}.content h1,.content h2,.content h3{font-family:Georgia,Times New Roman,serif;font-weight:400;color:#241f1b}.content a{display:inline-block;padding:10px 16px;border-radius:7px;background:#c8483f;color:#fff;text-decoration:none;font-weight:700}.foot{padding:20px 24px;background:#fbf8f4;border-top:1px solid #ece5dc;text-align:center;color:#7a7168;font-size:11px}</style></head><body><div class="card"><div class="head"><img src="/media/brand/atom-global-wordmark.png" alt="Atom Global"></div><div class="content">${content}</div><div class="foot">Growth Alignment by Atom Global Consulting · Website · Privacy · Terms</div></div></body></html>`;
}

const sampleValue = key => ({
  participantName: "Sample Participant", adminName: "Sample Administrator", trackName: "Manager",
  resumeUrl: "https://head-heart.atomglobal.com/?resume=sample", reportUrl: "https://head-heart.atomglobal.com/report/sample",
  freeReportUrl: "https://head-heart.atomglobal.com/report/sample", paidReportUrl: "https://head-heart.atomglobal.com/report/sample",
  paymentUrl: "https://head-heart.atomglobal.com/payment/sample", resetUrl: "https://head-heart.atomglobal.com/admin/reset-password?token=sample",
  completionPercentage: "60", reminderNumber: "1", amount: "49.00", currency: "USD", affiliateCode: "SAMPLE",
  message: "This is a safe selected-template test.", subject: "Growth Alignment template test", expiresMinutes: "60",
}[key] || `Sample ${key}`);

export function EmailPage({ initialSearch = "" }) {
  const templates = useLoader(() => api.adminEmailTemplates(), []);
  const queue = useLoader(() => api.adminEmailQueue(), []);
  const [selected, setSelected] = React.useState(null);
  const [testRecipient, setTestRecipient] = React.useState("");
  const [variables, setVariables] = React.useState({});
  const [queueSearch, setQueueSearch] = useSearch(initialSearch);
  const [notice, setNotice] = React.useState("");

  React.useEffect(() => { if (!selected && templates.data?.items?.length) setSelected({ ...templates.data.items[0] }); }, [templates.data, selected]);
  React.useEffect(() => {
    if (!selected) return;
    setVariables(Object.fromEntries(templateVariables(selected).map(key => [key, sampleValue(key)])));
  }, [selected?.template_key]);

  const save = async () => {
    try {
      await api.saveEmailTemplate(selected.template_key, { templateName: selected.template_name, subject: selected.subject, htmlBody: selected.html_body, textBody: selected.text_body, active: Boolean(selected.is_active) });
      setNotice("Template saved. The shared Atom Global email shell is applied automatically when delivered.");
      templates.refresh();
    } catch (error) { setNotice(error.message); }
  };

  const sendSelectedTest = async () => {
    try {
      const result = await api.testEmailTemplate(selected.template_key, { recipient: testRecipient, variables });
      setNotice(`${selected.template_name} queued for ${result.recipient}. The cron worker will deliver it and record provider status.`);
      window.setTimeout(queue.refresh, 1200);
    } catch (error) { setNotice(error.message); }
  };

  const queueItems = (queue.data?.items || []).filter(item => includesSearch(item, queueSearch, ["recipient_email", "template_key", "status", "provider_message_id", "failure_reason"]));
  const previewHtml = renderTemplate(selected?.html_body || "", variables);

  return <><PageHeader title="Email" actions={<button className="button" onClick={() => { templates.refresh(); queue.refresh(); }}>Refresh</button>} /><Notice>{notice}</Notice><Notice type="error">{templates.error || queue.error}</Notice>
    <div className="admin-grid admin-grid--email">
      <section className="admin-card"><div className="card-heading"><h2>Templates</h2></div><div className="admin-list">{(templates.data?.items || []).map(item => <button className={selected?.template_key === item.template_key ? "active" : ""} onClick={() => setSelected({ ...item })} key={item.template_key}>{item.template_name}<small>{item.is_active ? "Active" : "Inactive"}</small></button>)}</div></section>
      <section className="admin-card editor-form">{selected ? <>
        <div className="card-heading"><h2>{selected.template_name}</h2><button onClick={save}>Save template</button></div>
        <p className="hint">The client can edit the subject, HTML and plain-text message. Keep the listed variables in double braces. The logo, white card, brand colours, footer and legal links are added automatically.</p>
        <label>Template name<input value={selected.template_name} onChange={event => setSelected(current => ({ ...current, template_name: event.target.value }))} /></label>
        <label>Subject<input value={selected.subject} onChange={event => setSelected(current => ({ ...current, subject: event.target.value }))} /></label>
        <label>HTML message<textarea rows="10" value={selected.html_body} onChange={event => setSelected(current => ({ ...current, html_body: event.target.value }))} /></label>
        <label>Plain-text fallback<textarea rows="8" value={selected.text_body} onChange={event => setSelected(current => ({ ...current, text_body: event.target.value }))} /></label>
        <label className="check-row"><input type="checkbox" checked={Boolean(selected.is_active)} onChange={event => setSelected(current => ({ ...current, is_active: event.target.checked ? 1 : 0 }))} /> Active template</label>
        <div className="email-variable-grid"><h3>Test variables</h3>{templateVariables(selected).length ? templateVariables(selected).map(key => <label key={key}>{`{{${key}}}`}<input value={variables[key] || ""} onChange={event => setVariables(current => ({ ...current, [key]: event.target.value }))} /></label>) : <p className="hint">This template has no variables.</p>}</div>
        <div className="email-template-preview"><iframe title="Sandboxed email template preview" sandbox="" srcDoc={emailPreviewDocument(previewHtml)} /></div>
        <div className="email-test-bar"><input type="email" placeholder="Recipient for this template test" value={testRecipient} onChange={event => setTestRecipient(event.target.value)} /><button className="button button--primary" disabled={!testRecipient || !selected.is_active} onClick={sendSelectedTest}>Send selected template test</button></div>
      </> : <Empty />}</section>
    </div>

    <section className="admin-card"><div className="card-heading"><div><h2>Delivery queue</h2><small>Queued, retrying, delivered and failed messages</small></div></div>
      <div className="admin-filters"><input type="search" placeholder="Search recipient, template or status" value={queueSearch} onChange={event => setQueueSearch(event.target.value)} />{queueSearch && <button className="button" onClick={() => setQueueSearch("")}>Clear</button>}<span>{queueItems.length} messages</span></div>
      <DataTable columns={["Recipient", "Template", "Status", "Attempts", "Scheduled", "Provider ID", "Action"]} rows={queueItems.map(item => [item.recipient_email, item.template_key, item.status, `${item.attempts}/${item.max_attempts}`, dateTime(item.scheduled_at), item.provider_message_id || "—", item.status === "failed" ? <button onClick={() => api.retryEmail(item.id).then(queue.refresh)}>Retry</button> : "—"])} />
    </section>
  </>;
}

export function AffiliatesPage({ initialSearch = "" }) {
  const loader = useLoader(() => api.adminAffiliates(), []);
  const [search, setSearch] = useSearch(initialSearch);
  const [form, setForm] = React.useState({ code: "", name: "", contactName: "", contactEmail: "", campaignName: "", cookieDurationDays: 30, commissionType: "percentage", commissionValue: 0, tracks: ["personal", "newjoiner", "manager", "executive"], notes: "", active: true });
  const [notice, setNotice] = React.useState("");
  const save = async () => { try { await api.saveAffiliate(form); setNotice("Affiliate saved."); loader.refresh(); } catch (error) { setNotice(error.message); } };
  const items = (loader.data?.items || []).filter(item => includesSearch(item, search, ["affiliate_code", "name", "contact_email", "campaign_name"]));
  return <><PageHeader title="Affiliates" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} /><Notice>{notice}</Notice><Notice type="error">{loader.error}</Notice>
    <div className="admin-filters"><input type="search" placeholder="Search affiliate code, name or email" value={search} onChange={event => setSearch(event.target.value)} />{search && <button className="button" onClick={() => setSearch("")}>Clear</button>}</div>
    <div className="admin-grid"><section className="admin-card"><DataTable columns={["Code", "Name", "Clicks", "Conversions", "Revenue", "Commission", "Status"]} rows={items.map(item => [item.affiliate_code, item.name, item.clicks, item.conversions, money(item.revenueMinor), money(item.commissionMinor), item.is_active ? "Active" : "Inactive"])} /></section>
      <aside className="admin-card editor-form"><h2>Create affiliate</h2><label>Code<input value={form.code} onChange={event => setForm(current => ({ ...current, code: event.target.value }))} /></label><label>Name<input value={form.name} onChange={event => setForm(current => ({ ...current, name: event.target.value }))} /></label><label>Contact email<input type="email" value={form.contactEmail} onChange={event => setForm(current => ({ ...current, contactEmail: event.target.value }))} /></label><label>Campaign<input value={form.campaignName} onChange={event => setForm(current => ({ ...current, campaignName: event.target.value }))} /></label><label>Cookie duration days<input type="number" value={form.cookieDurationDays} onChange={event => setForm(current => ({ ...current, cookieDurationDays: Number(event.target.value) }))} /></label><label>Commission type<select value={form.commissionType} onChange={event => setForm(current => ({ ...current, commissionType: event.target.value }))}><option value="percentage">Percentage</option><option value="fixed">Fixed</option></select></label><label>Commission value<input type="number" step="0.01" value={form.commissionValue} onChange={event => setForm(current => ({ ...current, commissionValue: Number(event.target.value) }))} /></label><button className="button button--primary" onClick={save}>Save affiliate</button></aside>
    </div>
  </>;
}

export function AuditPage({ initialSearch = "" }) {
  const loader = useLoader(() => api.adminAuditLogs({ limit: 500 }), []);
  const [search, setSearch] = useSearch(initialSearch);
  const items = (loader.data?.items || []).filter(item => includesSearch(item, search, ["adminName", "adminEmail", "action", "entity_type", "entity_id"]));
  return <><PageHeader title="Audit log" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} /><Notice type="error">{loader.error}</Notice>
    <div className="admin-filters"><input type="search" placeholder="Search administrator, action or entity" value={search} onChange={event => setSearch(event.target.value)} />{search && <button className="button" onClick={() => setSearch("")}>Clear</button>}<span>{items.length} events</span></div>
    {loader.loading ? <Spinner /> : <section className="admin-card"><DataTable columns={["Date", "Administrator", "Action", "Entity", "ID"]} rows={items.map(item => [dateTime(item.created_at), item.adminName || "System", item.action, item.entity_type || "—", item.entity_id || "—"])} /></section>}
  </>;
}
