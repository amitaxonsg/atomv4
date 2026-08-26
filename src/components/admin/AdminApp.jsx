import React from "react";
import { api, isMockMode } from "../../api/client";
import { requestAdminPasswordReset, confirmAdminPasswordReset } from "../../api/passwordReset";
import { BrandLogo, useBranding } from "../../branding/BrandContext";
import { Spinner, Notice } from "./AdminShared";
import AdminDashboardPage from "./AdminDashboardPage";
import AdminParticipantsPage from "./AdminParticipantsPage";
import QuestionnairePage from "./QuestionnairePage";
import { AssessmentsPage, BrandingPage, ContentPage } from "./AdminCorePages";
import { AffiliatesPage, AnalyticsPage, AuditPage, EmailPage, PaymentsPage, ReportsPage, SeoPage, SettingsPage } from "./AdminOperationsEnhanced";
import { FeedbackPage, HelpPage } from "./AdminSupportPages";

const sections = [
  { label: "Dashboard", group: "Overview", permission: "dashboard.view" },
  { label: "Participants", group: "Assessment", permission: "participants.view" },
  { label: "Questionnaire", group: "Assessment", permission: "assessments.manage" },
  { label: "Assessments", group: "Assessment", permission: "assessments.manage" },
  { label: "Content", group: "Experience", permission: "content.manage" },
  { label: "Branding", group: "Experience", permission: "branding.manage" },
  { label: "Reports", group: "Operations", permission: "reports.manage" },
  { label: "Payments", group: "Operations", permission: "payments.manage" },
  { label: "Email", group: "Operations", permission: "email.manage" },
  { label: "Affiliates", group: "Growth", permission: "affiliates.manage" },
  { label: "Analytics", group: "Growth", permission: "dashboard.view" },
  { label: "SEO", group: "Growth", permission: "seo.manage" },
  { label: "Settings", group: "System", permission: "settings.manage" },
  { label: "Audit", group: "System", permission: "audit.view" },
  { label: "Feedback", group: "Support", permission: "feedback.submit" },
  { label: "Help", group: "Support", permission: null },
];

function PoweredBy({ className = "" }) {
  return <a className={`powered-by-axon ${className}`} href="https://axon.com.sg/" target="_blank" rel="noreferrer">Powered by <strong>Axon 1Pro</strong></a>;
}

function AdminLogin({ onLogin }) {
  const { branding, stages } = useBranding();
  const resetToken = new URLSearchParams(window.location.search).get("token") || "";
  const [mode, setMode] = React.useState(resetToken ? "reset" : "login");
  const [form, setForm] = React.useState({ email: isMockMode ? "preview@atomglobal.com" : "", password: isMockMode ? "preview-only" : "", passwordConfirm: "" });
  const [state, setState] = React.useState({ busy: false, error: "", message: "" });
  const storyImage = branding.bannerUrl || stages?.version?.image || "/media/stages/reflection-portrait.png";

  const submitLogin = async event => {
    event.preventDefault();
    setState({ busy: true, error: "", message: "" });
    try {
      const result = await api.adminLogin({ email: form.email, password: form.password });
      onLogin(result.user);
    } catch (error) {
      setState({ busy: false, error: error.message, message: "" });
    }
  };

  const requestReset = async event => {
    event.preventDefault();
    setState({ busy: true, error: "", message: "" });
    try {
      if (!isMockMode) await requestAdminPasswordReset(form.email);
      setState({ busy: false, error: "", message: "If an active account matches that address, a secure reset email has been queued." });
    } catch (error) {
      setState({ busy: false, error: error.message, message: "" });
    }
  };

  const confirmReset = async event => {
    event.preventDefault();
    if (form.password !== form.passwordConfirm) {
      setState({ busy: false, error: "The passwords do not match.", message: "" });
      return;
    }
    setState({ busy: true, error: "", message: "" });
    try {
      await confirmAdminPasswordReset(resetToken, form.password);
      window.history.replaceState({}, "", "/admin");
      setForm(current => ({ ...current, password: "", passwordConfirm: "" }));
      setMode("login");
      setState({ busy: false, error: "", message: "Password updated. Sign in with your new password." });
    } catch (error) {
      setState({ busy: false, error: error.message, message: "" });
    }
  };

  const showLogin = () => {
    window.history.replaceState({}, "", "/admin");
    setMode("login");
    setState({ busy: false, error: "", message: "" });
  };

  return <main className="admin-login" style={{ "--admin-login-image": `url("${storyImage}")` }}>
    <section className="admin-login__frame">
      <div className="admin-login__story">
        <BrandLogo />
        <span className="admin-login__badge">Secure, self-hosted platform</span>
        <h1>Manage the complete assessment journey.</h1>
        <p>Participants, questionnaire, assessments, reports, payments, email, feedback and brand settings in one protected workspace.</p>
        <div className="admin-login__assurance"><i aria-hidden="true" /><span>PHP sessions · MariaDB · role permissions · audit history</span></div>
      </div>

      {mode === "login" && <form className="admin-login__card" onSubmit={submitLogin}>
        <div className="admin-login__mobile-logo"><BrandLogo /></div><p className="eyebrow">{isMockMode ? "Preview workspace" : "Administrator access"}</p><h2>Welcome back</h2><p className="admin-login__intro">Sign in to manage Growth Alignment.</p>
        <Notice>{state.message}</Notice><Notice type="error">{state.error}</Notice>
        <label>Email address<input type="email" autoComplete="username" value={form.email} onChange={event => setForm(current => ({ ...current, email: event.target.value }))} placeholder="name@company.com" required /></label>
        <label>Password<input type="password" autoComplete="current-password" value={form.password} onChange={event => setForm(current => ({ ...current, password: event.target.value }))} placeholder="Enter your password" required /></label>
        <button className="button button--primary admin-login__submit" disabled={state.busy}>{state.busy ? "Signing in…" : isMockMode ? "Open preview workspace" : "Sign in securely"}</button>
        {!isMockMode && <button className="admin-login__text-button" type="button" onClick={() => { setMode("forgot"); setState({ busy: false, error: "", message: "" }); }}>Forgot your password?</button>}
        <div className="admin-login__security"><span aria-hidden="true">●</span>{isMockMode ? "Preview data only. No live participant or payment records are used." : "Protected by secure sessions, CSRF validation, rate limits and role permissions."}</div>
        <PoweredBy className="admin-login__powered" />
      </form>}

      {mode === "forgot" && <form className="admin-login__card" onSubmit={requestReset}>
        <div className="admin-login__mobile-logo"><BrandLogo /></div><p className="eyebrow">Account recovery</p><h2>Reset access</h2><p className="admin-login__intro">Enter the email address used for your administrator account.</p>
        <Notice>{state.message}</Notice><Notice type="error">{state.error}</Notice>
        <label>Email address<input type="email" autoComplete="email" value={form.email} onChange={event => setForm(current => ({ ...current, email: event.target.value }))} required /></label>
        <button className="button button--primary admin-login__submit" disabled={state.busy}>{state.busy ? "Requesting…" : "Send secure reset link"}</button>
        <button className="admin-login__text-button" type="button" onClick={showLogin}>Back to sign in</button>
        <PoweredBy className="admin-login__powered" />
      </form>}

      {mode === "reset" && <form className="admin-login__card" onSubmit={confirmReset}>
        <div className="admin-login__mobile-logo"><BrandLogo /></div><p className="eyebrow">Account recovery</p><h2>Choose a new password</h2><p className="admin-login__intro">Use at least 12 characters with upper-case, lower-case, a number and a symbol.</p>
        <Notice type="error">{state.error}</Notice>
        <label>New password<input type="password" autoComplete="new-password" value={form.password} onChange={event => setForm(current => ({ ...current, password: event.target.value }))} required /></label>
        <label>Confirm new password<input type="password" autoComplete="new-password" value={form.passwordConfirm} onChange={event => setForm(current => ({ ...current, passwordConfirm: event.target.value }))} required /></label>
        <button className="button button--primary admin-login__submit" disabled={state.busy}>{state.busy ? "Updating…" : "Update password"}</button>
        <button className="admin-login__text-button" type="button" onClick={showLogin}>Cancel and return to sign in</button>
        <PoweredBy className="admin-login__powered" />
      </form>}
    </section>
  </main>;
}

const pages = {
  Dashboard: AdminDashboardPage,
  Participants: AdminParticipantsPage,
  Questionnaire: QuestionnairePage,
  Assessments: AssessmentsPage,
  Content: ContentPage,
  Branding: BrandingPage,
  Reports: ReportsPage,
  Payments: PaymentsPage,
  Email: EmailPage,
  Affiliates: AffiliatesPage,
  Analytics: AnalyticsPage,
  SEO: SeoPage,
  Settings: SettingsPage,
  Audit: AuditPage,
  Feedback: FeedbackPage,
  Help: HelpPage,
};

export default function AdminApp() {
  const [user, setUser] = React.useState(null);
  const [checking, setChecking] = React.useState(true);
  const [active, setActive] = React.useState("Dashboard");
  const [search, setSearch] = React.useState("");
  const [searchState, setSearchState] = React.useState({ loading: false, items: [], error: "" });
  const [target, setTarget] = React.useState({ module: "", query: "", id: null });

  React.useEffect(() => {
    api.adminSession().then(result => setUser(result.user)).catch(() => {}).finally(() => setChecking(false));
  }, []);

  React.useEffect(() => {
    const term = search.trim();
    if (!user || term.length < 2) {
      setSearchState({ loading: false, items: [], error: "" });
      return undefined;
    }
    setSearchState(current => ({ ...current, loading: true, error: "" }));
    const timer = window.setTimeout(() => {
      api.adminSearch(term).then(result => setSearchState({ loading: false, items: result.items || [], error: "" })).catch(error => setSearchState({ loading: false, items: [], error: error.message }));
    }, 250);
    return () => window.clearTimeout(timer);
  }, [search, user]);

  if (checking) return <Spinner />;
  if (!user) return <AdminLogin onLogin={setUser} />;

  const permissions = Array.isArray(user.permissions) ? user.permissions : [];
  const allowed = permission => !permission || permissions.includes("*") || permissions.includes(permission);
  const visibleSections = sections.filter(section => allowed(section.permission));
  const safeActive = visibleSections.some(section => section.label === active) ? active : visibleSections[0]?.label || "Dashboard";
  const ActivePage = pages[safeActive] || AdminDashboardPage;
  const logout = () => api.adminLogout().finally(() => setUser(null));
  let previousGroup = "";

  const openResult = item => {
    setActive(item.module);
    setTarget({ module: item.module, query: item.query || item.subtitle || "", id: ["participant", "feedback"].includes(item.type) ? item.id : null });
    setSearch("");
    setSearchState({ loading: false, items: [], error: "" });
  };

  return <div className="admin-shell">
    <aside className="admin-sidebar">
      <a href="/" className="admin-brand"><BrandLogo /><span>Growth Alignment</span></a>
      <nav aria-label="Administration">
        {visibleSections.map(section => {
          const showGroup = section.group !== previousGroup;
          previousGroup = section.group;
          return <React.Fragment key={section.label}>
            {showGroup && <small className="admin-nav-group">{section.group}</small>}
            <button type="button" className={section.label === safeActive ? "active" : ""} onClick={() => { setActive(section.label); setTarget({ module: "", query: "", id: null }); }}><span>{section.label}</span></button>
          </React.Fragment>;
        })}
      </nav>
      <div className="admin-sidebar__footer">
        <div className="admin-user"><i>{user.displayName?.split(" ").map(part => part[0]).join("").slice(0, 2) || "AG"}</i><span>{user.displayName}<small>{user.roleName}</small></span><button type="button" onClick={logout}>Sign out</button></div>
        <PoweredBy />
      </div>
    </aside>

    <main className="admin-main">
      <div className="admin-context-bar">
        <span>Growth Alignment</span><strong>{safeActive}</strong>
        <div className="admin-global-search">
          <input type="search" value={search} onChange={event => setSearch(event.target.value)} placeholder="Search participants, reports, payments, email…" aria-label="Search administration" />
          {(search.trim().length >= 2 || searchState.loading) && <div className="admin-search-results">
            {searchState.loading && <small>Searching…</small>}
            {searchState.error && <small className="admin-search-error">{searchState.error}</small>}
            {!searchState.loading && !searchState.error && !searchState.items.length && <small>No matching records.</small>}
            {searchState.items.map(item => <button key={`${item.type}-${item.id}`} onClick={() => openResult(item)}><span><strong>{item.title}</strong><small>{item.subtitle}</small></span><em>{item.module}<small>{item.meta}</small></em></button>)}
          </div>}
        </div>
        <em>{isMockMode ? "Preview" : "Secure workspace"}</em>
      </div>
      <ActivePage initialSearch={target.module === safeActive ? target.query : ""} initialId={target.module === safeActive ? target.id : null} permissions={permissions} user={user} />
    </main>
  </div>;
}
