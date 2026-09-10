import React from "react";
import { api } from "../../api/client";
import { DataTable, Empty, Notice, PageHeader, Spinner, dateTime, money, useLoader } from "./AdminShared";

export function ReportsPage() {
  const loader = useLoader(() => api.adminReports(), []);
  const [notice, setNotice] = React.useState("");

  const action = async (item, name) => {
    if (["revoke", "lock"].includes(name) && !confirm(`${name} this report?`)) return;
    try {
      const endpoint = name === "resend" ? "rotate-and-resend" : name;
      await api.reportAction(item.id, endpoint);
      setNotice(`Report ${name} action completed.`);
      loader.refresh();
    } catch (error) {
      setNotice(error.message);
    }
  };

  return <>
    <PageHeader title="Reports" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} />
    <Notice>{notice}</Notice>
    <Notice type="error">{loader.error}</Notice>
    {loader.loading ? <Spinner /> : <section className="admin-card">
      <DataTable
        columns={["Participant", "Track", "Status", "Expires", "PDF", "Views", "Actions"]}
        rows={(loader.data?.items || []).map(item => [
          <><strong>{item.participantName}</strong><span>{item.participantEmail}</span></>,
          item.track,
          item.revokedAt ? "Revoked" : item.unlocked ? "Full report" : "Lite report",
          dateTime(item.tokenExpiresAt),
          item.pdfPath ? "Ready" : "Missing",
          item.viewCount,
          <div className="table-actions">
            <button onClick={() => action(item, item.unlocked ? "lock" : "unlock")}>{item.unlocked ? "Lock" : "Unlock"}</button>
            <button onClick={() => action(item, "resend")}>Rotate link & resend</button>
            <button onClick={() => action(item, "regenerate")}>Regenerate PDF</button>
            <button onClick={() => action(item, "revoke")}>Revoke</button>
          </div>,
        ])}
      />
    </section>}
  </>;
}

export function PaymentsPage() {
  const loader = useLoader(() => api.adminPayments(), []);
  return <>
    <PageHeader title="Payments" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} />
    <Notice type="error">{loader.error}</Notice>
    {loader.loading ? <Spinner /> : <section className="admin-card">
      <DataTable
        columns={["Participant", "Track", "Status", "Amount", "Provider", "Affiliate", "Date"]}
        rows={(loader.data?.items || []).map(item => [
          <><strong>{item.participantName}</strong><span>{item.participantEmail}</span></>,
          item.track,
          <span className={`status status--${item.status}`}>{item.status}</span>,
          money(item.amount, item.currency),
          item.provider,
          item.affiliateCode || "—",
          dateTime(item.created_at),
        ])}
      />
    </section>}
  </>;
}

export function EmailPage() {
  const templates = useLoader(() => api.adminEmailTemplates(), []);
  const queue = useLoader(() => api.adminEmailQueue(), []);
  const [selected, setSelected] = React.useState(null);
  const [testRecipient, setTestRecipient] = React.useState("");
  const [notice, setNotice] = React.useState("");

  React.useEffect(() => {
    if (!selected && templates.data?.items?.length) setSelected({ ...templates.data.items[0] });
  }, [templates.data, selected]);

  const save = async () => {
    try {
      await api.saveEmailTemplate(selected.template_key, {
        templateName: selected.template_name,
        subject: selected.subject,
        htmlBody: selected.html_body,
        textBody: selected.text_body,
        active: Boolean(selected.is_active),
      });
      setNotice("Email template saved. The central Atom Global email shell is applied automatically when sent.");
      templates.refresh();
    } catch (error) {
      setNotice(error.message);
    }
  };

  const sendTest = async () => {
    try {
      await api.testIntegration("email", { recipient: testRecipient });
      setNotice("Test email queued. Run the email worker and verify provider delivery in the queue log.");
      queue.refresh();
    } catch (error) {
      setNotice(error.message);
    }
  };

  return <>
    <PageHeader title="Email" actions={<button className="button" onClick={() => { templates.refresh(); queue.refresh(); }}>Refresh</button>} />
    <Notice>{notice}</Notice>
    <Notice type="error">{templates.error || queue.error}</Notice>

    <div className="admin-grid admin-grid--email">
      <section className="admin-card">
        <div className="card-heading"><h2>Templates</h2></div>
        <div className="admin-list">
          {(templates.data?.items || []).map(item => <button
            className={selected?.template_key === item.template_key ? "active" : ""}
            onClick={() => setSelected({ ...item })}
            key={item.template_key}
          >
            {item.template_name}
            <small>{item.is_active ? "Active" : "Inactive"}</small>
          </button>)}
        </div>
      </section>

      <section className="admin-card editor-form">
        {selected ? <>
          <div className="card-heading">
            <h2>{selected.template_name}</h2>
            <button onClick={save}>Save template</button>
          </div>
          <p className="hint">Edit the message content only. The centred logo, white card, brand colours, footer and legal links are added automatically.</p>
          <label>Template name<input value={selected.template_name} onChange={event => setSelected(current => ({ ...current, template_name: event.target.value }))} /></label>
          <label>Subject<input value={selected.subject} onChange={event => setSelected(current => ({ ...current, subject: event.target.value }))} /></label>
          <label>HTML message<textarea rows="10" value={selected.html_body} onChange={event => setSelected(current => ({ ...current, html_body: event.target.value }))} /></label>
          <label>Plain-text fallback<textarea rows="8" value={selected.text_body} onChange={event => setSelected(current => ({ ...current, text_body: event.target.value }))} /></label>
          <label className="check-row"><input type="checkbox" checked={Boolean(selected.is_active)} onChange={event => setSelected(current => ({ ...current, is_active: event.target.checked ? 1 : 0 }))} /> Active template</label>

          <div className="email-template-preview">
            <div className="email-template-preview__sheet">
              <img src="/media/brand/atom-global-wordmark.png" alt="Atom Global" />
              <div dangerouslySetInnerHTML={{ __html: selected.html_body }} />
              <small>Growth Alignment by Atom Global Consulting · Website · Privacy · Terms</small>
            </div>
          </div>
        </> : <Empty />}
      </section>
    </div>

    <section className="admin-card">
      <div className="card-heading">
        <h2>Test and delivery queue</h2>
        <div className="inline-form">
          <input type="email" placeholder="Recipient for test email" value={testRecipient} onChange={event => setTestRecipient(event.target.value)} />
          <button className="button" disabled={!testRecipient} onClick={sendTest}>Send test email</button>
        </div>
      </div>
      <DataTable
        columns={["Recipient", "Template", "Status", "Attempts", "Scheduled", "Action"]}
        rows={(queue.data?.items || []).map(item => [
          item.recipient_email,
          item.template_key,
          item.status,
          `${item.attempts}/${item.max_attempts}`,
          dateTime(item.scheduled_at),
          item.status === "failed" ? <button onClick={() => api.retryEmail(item.id).then(queue.refresh)}>Retry</button> : "—",
        ])}
      />
    </section>
  </>;
}

export function AffiliatesPage() {
  const loader = useLoader(() => api.adminAffiliates(), []);
  const [form, setForm] = React.useState({ code: "", name: "", contactName: "", contactEmail: "", campaignName: "", cookieDurationDays: 30, commissionType: "percentage", commissionValue: 0, tracks: ["personal", "newjoiner", "manager", "executive"], notes: "", active: true });
  const [notice, setNotice] = React.useState("");
  const save = async () => { await api.saveAffiliate(form); setNotice("Affiliate saved."); loader.refresh(); };

  return <>
    <PageHeader title="Affiliates" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} />
    <Notice>{notice}</Notice>
    <Notice type="error">{loader.error}</Notice>
    <div className="admin-grid">
      <section className="admin-card"><DataTable columns={["Code", "Name", "Clicks", "Conversions", "Revenue", "Commission", "Status"]} rows={(loader.data?.items || []).map(item => [item.affiliate_code, item.name, item.clicks, item.conversions, money(item.revenueMinor), money(item.commissionMinor), item.is_active ? "Active" : "Inactive"])} /></section>
      <aside className="admin-card editor-form">
        <h2>Create affiliate</h2>
        <label>Code<input value={form.code} onChange={event => setForm(current => ({ ...current, code: event.target.value }))} /></label>
        <label>Name<input value={form.name} onChange={event => setForm(current => ({ ...current, name: event.target.value }))} /></label>
        <label>Contact email<input type="email" value={form.contactEmail} onChange={event => setForm(current => ({ ...current, contactEmail: event.target.value }))} /></label>
        <label>Campaign<input value={form.campaignName} onChange={event => setForm(current => ({ ...current, campaignName: event.target.value }))} /></label>
        <label>Cookie duration days<input type="number" value={form.cookieDurationDays} onChange={event => setForm(current => ({ ...current, cookieDurationDays: Number(event.target.value) }))} /></label>
        <label>Commission type<select value={form.commissionType} onChange={event => setForm(current => ({ ...current, commissionType: event.target.value }))}><option value="percentage">Percentage</option><option value="fixed">Fixed</option></select></label>
        <label>Commission value<input type="number" step="0.01" value={form.commissionValue} onChange={event => setForm(current => ({ ...current, commissionValue: Number(event.target.value) }))} /></label>
        <button className="button button--primary" onClick={save}>Save affiliate</button>
      </aside>
    </div>
  </>;
}

export function SeoPage() {
  const loader = useLoader(() => api.adminSeoPages(), []);
  const [form, setForm] = React.useState({ pageKey: "home", path: "/", pageTitle: "Growth Alignment | Atom Global Consulting", metaDescription: "A reflective assessment balancing reason and human insight.", canonicalUrl: "https://head-heart.atomglobal.com/", robotsSetting: "index,follow", ogTitle: "Growth Alignment", ogDescription: "Pause. Reflect. Choose wisely.", heading: "Growth Alignment", introductoryContent: "", faq: [], structuredData: {}, includeInSitemap: true });
  const [notice, setNotice] = React.useState("");

  React.useEffect(() => {
    const item = loader.data?.items?.[0];
    if (item) setForm(current => ({ ...current, pageKey: item.page_key, path: item.path, pageTitle: item.page_title || "", metaDescription: item.meta_description || "", canonicalUrl: item.canonical_url || "", robotsSetting: item.robots_setting || "index,follow", ogTitle: item.og_title || "", ogDescription: item.og_description || "", heading: item.heading || "", introductoryContent: item.introductory_content || "", includeInSitemap: Boolean(item.include_in_sitemap) }));
  }, [loader.data]);

  const save = async () => { await api.saveSeoPage(form.pageKey, form); setNotice("SEO/AEO/GEO settings saved."); loader.refresh(); };

  return <>
    <PageHeader title="SEO / AEO / GEO" actions={<button className="button button--primary" onClick={save}>Save page settings</button>} />
    <Notice>{notice}</Notice>
    <Notice type="error">{loader.error}</Notice>
    <section className="admin-card editor-form">
      <div className="form-grid">
        <label>Page key<input value={form.pageKey} onChange={event => setForm(current => ({ ...current, pageKey: event.target.value }))} /></label>
        <label>Path<input value={form.path} onChange={event => setForm(current => ({ ...current, path: event.target.value }))} /></label>
        <label>Page title<input value={form.pageTitle} onChange={event => setForm(current => ({ ...current, pageTitle: event.target.value }))} /></label>
        <label>Canonical URL<input value={form.canonicalUrl} onChange={event => setForm(current => ({ ...current, canonicalUrl: event.target.value }))} /></label>
        <label className="form-grid__wide">Meta description<textarea value={form.metaDescription} onChange={event => setForm(current => ({ ...current, metaDescription: event.target.value }))} /></label>
        <label>Robots<select value={form.robotsSetting} onChange={event => setForm(current => ({ ...current, robotsSetting: event.target.value }))}><option>index,follow</option><option>noindex,nofollow</option></select></label>
        <label>Open Graph title<input value={form.ogTitle} onChange={event => setForm(current => ({ ...current, ogTitle: event.target.value }))} /></label>
        <label className="form-grid__wide">Open Graph description<textarea value={form.ogDescription} onChange={event => setForm(current => ({ ...current, ogDescription: event.target.value }))} /></label>
        <label className="check-row"><input type="checkbox" checked={form.includeInSitemap} onChange={event => setForm(current => ({ ...current, includeInSitemap: event.target.checked }))} /> Include in sitemap</label>
      </div>
    </section>
  </>;
}

const settingsGroups = {
  email: ["provider", "adminFromName", "adminFromAddress", "replyTo", "publicBaseUrl", "logoUrl", "websiteUrl", "privacyUrl", "termsUrl", "footerText", "smtpHost", "smtpPort", "smtpUsername", "smtpPassword", "smtpEncryption", "smtp2goApiKey", "reminderHours", "maxAttempts"],
  stripe: ["mode", "publishableKey", "secretKey", "webhookSecret", "pricePersonal", "priceNewjoiner", "priceManager", "priceExecutive"],
  alerts: ["types"],
  security: ["sessionLifetime", "reportTokenDays", "resumeTokenHours", "loginLimit", "loginWindowSeconds"],
  privacy: ["analyticsDefault", "incompleteRetentionDays", "completedRetentionDays"],
  turnstile: ["siteKey", "secretKey"],
};

const fieldLabels = {
  provider: "Delivery provider",
  adminFromName: "Sender name",
  adminFromAddress: "Sender email",
  replyTo: "Reply-to email",
  publicBaseUrl: "Public website base URL",
  logoUrl: "Email logo URL",
  websiteUrl: "Footer website link",
  privacyUrl: "Footer privacy link",
  termsUrl: "Footer terms link",
  footerText: "Footer company text",
  smtp2goApiKey: "SMTP2GO API key",
  reminderHours: "Reminder schedule in hours",
  maxAttempts: "Maximum delivery attempts",
  webhookSecret: "Webhook signing secret",
};

function AdminUsers() {
  const loader = useLoader(() => api.adminUsers(), []);
  const [form, setForm] = React.useState({ displayName: "", email: "", password: "", roleKey: "viewer", active: true });
  const [notice, setNotice] = React.useState("");

  const save = async () => {
    try {
      await api.saveAdminUser(form);
      setNotice("Administrator saved. New passwords must contain at least 12 characters.");
      loader.refresh();
      setForm({ displayName: "", email: "", password: "", roleKey: "viewer", active: true });
    } catch (error) {
      setNotice(error.message);
    }
  };

  return <section className="admin-card">
    <div className="card-heading"><h2>Administrators and roles</h2></div>
    <DataTable columns={["Name", "Email", "Role", "Status", "Last login"]} rows={(loader.data?.items || []).map(item => [item.displayName, item.email, item.roleName || item.roleKey, item.active === false ? "Inactive" : "Active", dateTime(item.lastLoginAt)])} />
    <div className="editor-form">
      <h3>Add administrator</h3>
      <div className="form-grid">
        <label>Name<input value={form.displayName} onChange={event => setForm(current => ({ ...current, displayName: event.target.value }))} /></label>
        <label>Email<input type="email" value={form.email} onChange={event => setForm(current => ({ ...current, email: event.target.value }))} /></label>
        <label>Temporary password<input type="password" value={form.password} onChange={event => setForm(current => ({ ...current, password: event.target.value }))} /></label>
        <label>Role<select value={form.roleKey} onChange={event => setForm(current => ({ ...current, roleKey: event.target.value }))}><option value="owner">Owner</option><option value="administrator">Administrator</option><option value="editor">Content editor</option><option value="finance">Finance</option><option value="viewer">Viewer</option></select></label>
      </div>
      <button className="button" onClick={save}>Create administrator</button>
      <Notice>{notice}</Notice>
    </div>
  </section>;
}

export function SettingsPage() {
  const [group, setGroup] = React.useState("email");
  const loader = useLoader(() => api.adminSettings(), []);
  const alerts = useLoader(() => api.adminAlertRecipients(), []);
  const [values, setValues] = React.useState({});
  const [notice, setNotice] = React.useState("");
  const [alertForm, setAlertForm] = React.useState({ name: "", email: "", alertTypes: ["email_failed", "payment_failed", "webhook_failed", "privacy_request"], active: true });
  const [testRecipient, setTestRecipient] = React.useState("");

  React.useEffect(() => { if (loader.data) setValues(loader.data); }, [loader.data]);

  const groupValues = values[group] || {};
  const save = async () => {
    try {
      await api.saveSettings(group, groupValues);
      setNotice(`${group} settings saved. Secret fields remain encrypted or in the protected environment file.`);
      loader.refresh();
    } catch (error) {
      setNotice(error.message);
    }
  };
  const addAlert = async () => { await api.saveAlertRecipient(alertForm); setNotice("Admin alert recipient saved."); alerts.refresh(); };
  const test = async provider => {
    try {
      const result = await api.testIntegration(provider, provider === "email" ? { recipient: testRecipient } : {});
      setNotice(result.message);
    } catch (error) {
      setNotice(error.message);
    }
  };

  return <>
    <PageHeader title="Settings" actions={<button className="button button--primary" onClick={save}>Save {group}</button>} />
    <Notice>{notice}</Notice>
    <Notice type="error">{loader.error}</Notice>

    <div className="settings-tabs">
      {Object.keys(settingsGroups).map(key => <button className={group === key ? "active" : ""} onClick={() => setGroup(key)} key={key}>{key}</button>)}
    </div>

    <div className="admin-grid">
      <section className="admin-card editor-form">
        <h2>{group[0].toUpperCase() + group.slice(1)} configuration</h2>
        <div className="form-grid">
          {settingsGroups[group].map(key => {
            const current = groupValues[key];
            const sensitive = ["smtpPassword", "smtp2goApiKey", "secretKey", "webhookSecret"].includes(key);
            const numeric = key.toLowerCase().includes("port") || key.toLowerCase().includes("days") || key.toLowerCase().includes("hours") || key.toLowerCase().includes("lifetime") || key.toLowerCase().includes("limit") || key.toLowerCase().includes("attempts");
            return <label key={key}>
              {fieldLabels[key] || key.replace(/([A-Z])/g, " $1")}
              <input
                type={sensitive ? "password" : numeric ? "number" : "text"}
                placeholder={current?.masked || ""}
                value={typeof current === "object" ? "" : current ?? ""}
                onChange={event => setValues(all => ({ ...all, [group]: { ...(all[group] || {}), [key]: event.target.value } }))}
              />
            </label>;
          })}
        </div>
        <p className="hint">Use SMTP2GO for staging, keep Stripe in test mode and verify delivery/webhooks before production activation. Saved secrets are masked and never returned to the browser.</p>
        <div className="integration-tests">
          <h3>Connection tests</h3>
          <div className="inline-form">
            <input type="email" placeholder="Recipient for test email" value={testRecipient} onChange={event => setTestRecipient(event.target.value)} />
            <button className="button" disabled={!testRecipient} onClick={() => test("email")}>Test email</button>
            <button className="button" onClick={() => test("stripe")}>Test Stripe</button>
          </div>
        </div>
      </section>

      <aside className="admin-card editor-form">
        <h2>Admin alerts</h2>
        <label>Name<input value={alertForm.name} onChange={event => setAlertForm(current => ({ ...current, name: event.target.value }))} /></label>
        <label>Email<input type="email" value={alertForm.email} onChange={event => setAlertForm(current => ({ ...current, email: event.target.value }))} /></label>
        <button className="button" onClick={addAlert}>Add alert recipient</button>
        <div className="admin-list">{(alerts.data?.items || []).map(item => <div key={item.id}><strong>{item.name}</strong><small>{item.email}</small></div>)}</div>
        <h2>Environment status</h2>
        <dl className="admin-definition">{Object.entries(values.environment || {}).map(([key, configured]) => <div key={key}><dt>{key.replace(/([A-Z])/g, " $1")}</dt><dd>{configured ? "Configured" : "Missing"}</dd></div>)}</dl>
      </aside>
    </div>

    <AdminUsers />
  </>;
}

export function AnalyticsPage() {
  const loader = useLoader(() => api.adminAnalytics(), []);
  const totals = {};
  (loader.data?.events || []).forEach(item => { totals[item.event_name] = (totals[item.event_name] || 0) + Number(item.eventCount || 0); });

  return <>
    <PageHeader title="Funnel and drop-off" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} />
    <Notice type="error">{loader.error}</Notice>
    {loader.loading ? <Spinner /> : <>
      <section className="metric-grid">{Object.entries(totals).map(([key, value]) => <article className="metric" key={key}><span>{key.replaceAll("_", " ")}</span><strong>{value}</strong></article>)}</section>
      <section className="admin-card"><h2>Abandoned sessions by last section</h2><DataTable columns={["Section", "Sessions"]} rows={(loader.data?.dropoff || []).map(item => [Number(item.section) + 1, item.sessions])} /></section>
    </>}
  </>;
}

export function AuditPage() {
  const loader = useLoader(() => api.adminAuditLogs({ limit: 300 }), []);
  return <>
    <PageHeader title="Audit log" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} />
    <Notice type="error">{loader.error}</Notice>
    {loader.loading ? <Spinner /> : <section className="admin-card"><DataTable columns={["Date", "Administrator", "Action", "Entity", "ID"]} rows={(loader.data?.items || []).map(item => [dateTime(item.created_at), item.adminName || "System", item.action, item.entity_type || "—", item.entity_id || "—"])} /></section>}
  </>;
}
