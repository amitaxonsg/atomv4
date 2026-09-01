import { dashboardData, emailTemplates, stageContent } from "./mockData";
import { experienceDefaults } from "../data/assessmentExperience";

const mode = import.meta.env.VITE_API_MODE || "mock";
const baseUrl = import.meta.env.VITE_API_BASE_URL || "/api";
const storageKey = "hhaa-v2-preview-session";
let csrfToken = "";

async function request(path, options = {}) {
  const headers = { ...(options.headers || {}) };
  const isForm = options.body instanceof FormData;
  if (!isForm && options.body !== undefined && !headers["Content-Type"]) headers["Content-Type"] = "application/json";
  if (csrfToken && !["GET", "HEAD"].includes((options.method || "GET").toUpperCase())) headers["X-CSRF-Token"] = csrfToken;
  const response = await fetch(`${baseUrl}${path}`, { credentials: "include", ...options, headers });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    const error = new Error(payload.message || "Request failed");
    error.status = response.status;
    throw error;
  }
  return payload;
}

function mockConfiguration() {
  return {
    branding: {
      canvas: "#F7F4EF", surface: "#FFFFFF", textPrimary: "#211C16", textMuted: "#726A5B",
      border: "#E4DDCF", cta: "#C9A15A", ctaHover: "#AF8540", heart: "#C1443F",
      head: "#6C8FAE", accent: "#C9A15A", navy: "#14141C",
      headingFont: 'Georgia, "Times New Roman", serif', bodyFont: "Arial, Helvetica, sans-serif",
      baseFontSize: "16", cardRadius: "8", buttonRadius: "8",
      logoUrl: "/media/brand/atom-global-wordmark-transparent.svg", emailLogoUrl: "/media/brand/atom-global-wordmark.png",
      reportLogoUrl: "/media/brand/atom-global-wordmark.png", faviconUrl: "/icon-192.png", bannerUrl: "",
    },
    stages: stageContent,
    tracks: {
      personal: { key: "personal", label: "Personal", durationMin: 15, durationMax: 15, questionCount: 50, sectionCount: 10, freeReportLabel: "Lite Report Free", paidReportLabel: "Full Report" },
      newjoiner: { key: "newjoiner", label: "New Joiner", durationMin: 15, durationMax: 15, questionCount: 50, sectionCount: 10, freeReportLabel: "Lite Report Free", paidReportLabel: "Full Report" },
      manager: { key: "manager", label: "Manager", durationMin: 15, durationMax: 18, questionCount: 50, sectionCount: 10, freeReportLabel: "Lite Report Free", paidReportLabel: "Full Report" },
      executive: { key: "executive", label: "Executive", durationMin: 18, durationMax: 20, questionCount: 50, sectionCount: 10, freeReportLabel: "Lite Report Free", paidReportLabel: "Full Report" },
    },
  };
}

function mockExperience() {
  return { tracks: Object.fromEntries(Object.entries(experienceDefaults).map(([key, value], index) => [key, { trackId: index + 1, trackKey: key, trackName: mockConfiguration().tracks[key].label, ...value }])) };
}

const mockAdmin = {
  user: { id: 1, email: "preview@atomglobal.com", displayName: "Preview Owner", roleKey: "owner", roleName: "Owner", permissions: ["*"] },
  branding: null, settings: {}, affiliates: [], seo: [], users: [], feedback: [],
  experience: mockExperience(),
  feedbackConfig: { githubRepository: "amitaxonsg/atomglobal-hhaa-v2", githubTokenConfigured: false, clientEmail: "sunil.setpaul@atomglobal.com", internalEmail: "amit@axon.com.sg", supportEmail: "amit@axon.com.sg", issuePrefix: "Client feedback" },
};

function mockFeedbackDetail(item) {
  return { ...item, updates: item.updates || [{ id: 1, updateType: "submitted", status: item.status, message: "Feedback submitted through the administration portal.", adminName: "Preview Owner", createdAt: item.createdAt }] };
}

const mock = {
  async publicConfiguration() { return mockConfiguration(); },
  async publicAssessmentExperience() { return mockAdmin.experience; },
  async createSession(payload) {
    const session = { id: crypto.randomUUID(), resumeToken: crypto.randomUUID().replaceAll("-", "") + crypto.randomUUID().replaceAll("-", ""), status: "in_progress", updatedAt: new Date().toISOString(), ...payload };
    localStorage.setItem(storageKey, JSON.stringify(session));
    return session;
  },
  async saveSession(payload) { const current = JSON.parse(localStorage.getItem(storageKey) || "{}"); const session = { ...current, ...payload, updatedAt: new Date().toISOString() }; localStorage.setItem(storageKey, JSON.stringify(session)); return session; },
  async loadSession() { return JSON.parse(localStorage.getItem(storageKey) || "null"); },
  async completeSession(payload) { return this.saveSession({ ...payload, status: "completed", completedAt: new Date().toISOString(), reportToken: "preview-report" }); },
  async createCheckout(payload) { return { preview: true, url: `${location.pathname}?payment=success&session=${payload.sessionId}` }; },
  async checkoutStatus() { return { status: "paid", reportReady: true, reportUrl: "/report/preview-report" }; },
  async getReport() { return null; },
  async adminSession() { return { user: mockAdmin.user, csrfToken: "preview" }; },
  async adminLogin() { return { user: mockAdmin.user, csrfToken: "preview" }; },
  async adminLogout() { return { loggedOut: true }; },
  async adminDashboard() { return { metrics: dashboardData.metrics.map(([label, value]) => ({ label, value })), participants: dashboardData.participants, activity: dashboardData.activity.map(action => ({ action })), failures: {} }; },
  async adminInsights() { return { daily: [], funnel: [{ label: "Started", value: 38 }, { label: "Engaged", value: 34 }, { label: "Completed", value: 29 }, { label: "Paid", value: 11 }], trackProgress: [], emailSummary: { pending: 0, sent: 0, failed: 0 } }; },
  async adminSearch(term) { return { items: dashboardData.participants.filter(item => `${item.name} ${item.email}`.toLowerCase().includes(String(term).toLowerCase())).map((item, index) => ({ type: "participant", module: "Participants", id: index + 1, title: item.name, subtitle: item.email, meta: `${item.track} · ${item.progress}%`, query: item.email })) }; },
  async adminParticipants() { return { items: dashboardData.participants.map((item, index) => ({ id: index + 1, ...item, status: item.progress === 100 ? "completed" : "in_progress" })), total: dashboardData.participants.length, page: 1, limit: 25 }; },
  async adminParticipant(id) { return { participant: { id, name: "Preview Participant", email: "preview@example.com" }, sessions: [], answers: [], reports: [], payments: [], emails: [], consents: [] }; },
  async adminAssessments() { return { items: Object.values(mockConfiguration().tracks).map((track, index) => ({ trackId: index + 1, trackKey: track.key, trackName: track.label, versionId: index + 1, versionNumber: "1.0.0", status: index ? "published" : "draft", questionCount: 50, sectionCount: 10 })) }; },
  async adminAssessmentVersion(id) { return { version: { id, version_number: "1.0.0", status: "draft", trackId: 1 }, sections: [], questions: Array.from({ length: 50 }, (_, index) => ({ id: index + 1, position: index + 1, question_text: `Preview question ${index + 1}`, scoring_direction: index % 2 ? "H" : "K", is_required: 1, is_active: 1 })), options: [], reports: [] }; },
  async adminAssessmentExperience(id) { return Object.values(mockAdmin.experience.tracks).find(item => Number(item.trackId) === Number(id)); },
  async saveAssessmentExperience(id, payload) { const item = await this.adminAssessmentExperience(id); Object.assign(item, payload); return item; },
  async cloneAssessment() { return { versionId: Date.now() }; }, async publishAssessment() { return { published: true }; },
  async saveTrackSettings() { return { saved: true }; }, async saveQuestion() { return { saved: true }; }, async saveReportTemplate() { return { saved: true }; },
  async adminContent() { return { items: stageContent }; },
  async saveContentStage(key, payload) { stageContent[key] = { ...stageContent[key], ...payload }; return stageContent[key]; },
  async adminMedia() { return { items: [] }; },
  async uploadMedia(file, metadata = {}) { return { id: Date.now(), url: URL.createObjectURL(file), fileName: file.name, ...metadata }; },
  async adminBranding() { return mockAdmin.branding || { published: mockConfiguration().branding, draft: null, draftId: null }; },
  async saveBrandingDraft(payload) { mockAdmin.branding = { published: mockConfiguration().branding, draft: payload, draftId: 1 }; return { draftId: 1 }; },
  async publishBranding() { if (mockAdmin.branding?.draft) mockAdmin.branding.published = mockAdmin.branding.draft; return { published: true }; },
  async adminReports() { return { items: [] }; }, async reportAction() { return { ok: true }; },
  async adminPayments() { return { items: [] }; },
  async adminEmailTemplates() { return { items: emailTemplates.map((name, index) => ({ id: index + 1, template_key: name.toLowerCase().replaceAll(" ", "_"), template_name: name, subject: name, html_body: `<p>${name}</p>`, text_body: name, is_active: 1 })) }; },
  async saveEmailTemplate() { return { saved: true }; },
  async testEmailTemplate(key, payload) { return { queueId: 1, templateKey: key, recipient: payload.recipient }; },
  async adminEmailQueue() { return { items: [] }; }, async retryEmail() { return { queued: true }; }, async testEmail() { return { queueId: 1 }; },
  async adminAffiliates() { return { items: mockAdmin.affiliates }; }, async saveAffiliate(payload) { mockAdmin.affiliates.push({ id: Date.now(), ...payload }); return { id: Date.now() }; },
  async adminSeoPages() { return { items: mockAdmin.seo }; }, async saveSeoPage(key, payload) { mockAdmin.seo.push({ page_key: key, ...payload }); return { saved: true }; },
  async adminSettings() { return mockAdmin.settings; }, async saveSettings(group, payload) { mockAdmin.settings[group] = payload; return { saved: true }; },
  async adminAlertRecipients() { return { items: [] }; }, async saveAlertRecipient() { return { id: 1 }; },
  async adminAuditLogs() { return { items: [] }; },
  async adminUsers() { return { items: [mockAdmin.user, ...mockAdmin.users] }; }, async saveAdminUser(payload) { mockAdmin.users.push({ id: Date.now(), ...payload }); return { id: Date.now() }; },
  async adminNotifications() { return { items: [] }; }, async acknowledgeNotification() { return { acknowledged: true }; },
  async adminAnalytics() { return { events: [], dropoff: [] }; }, async testIntegration(provider) { return { status: "success", message: `${provider} preview test passed` }; },
  async adminFeedback(query = {}) { const search = String(query.search || "").toLowerCase(); const items = mockAdmin.feedback.filter(item => (!search || `${item.title} ${item.details} ${item.moduleName}`.toLowerCase().includes(search)) && (!query.status || item.status === query.status) && (!query.priority || item.priority === query.priority)); return { items, summary: { total: items.length, new: items.filter(item => item.status === "new").length, clarification: items.filter(item => item.status === "clarification_requested").length, active: items.filter(item => ["accepted", "in_progress"].includes(item.status)).length, review: items.filter(item => item.status === "ready_for_review").length, done: items.filter(item => item.status === "done").length } }; },
  async adminFeedbackDetail(id) { const item = mockAdmin.feedback.find(row => Number(row.id) === Number(id)); if (!item) throw new Error("Feedback record not found."); return mockFeedbackDetail(item); },
  async submitFeedback(payload) { const item = { id: Date.now(), status: "new", githubSyncStatus: mockAdmin.feedbackConfig.githubTokenConfigured ? "synced" : "not_configured", githubIssueNumber: mockAdmin.feedbackConfig.githubTokenConfigured ? 101 : null, githubIssueUrl: mockAdmin.feedbackConfig.githubTokenConfigured ? "https://github.com/amitaxonsg/atomglobal-hhaa-v2/issues/101" : null, createdAt: new Date().toISOString(), updatedAt: new Date().toISOString(), ...payload }; mockAdmin.feedback.unshift(item); return mockFeedbackDetail(item); },
  async updateFeedback(id, payload) { const item = mockAdmin.feedback.find(row => Number(row.id) === Number(id)); Object.assign(item, payload, { updatedAt: new Date().toISOString() }); item.updates = [{ id: Date.now(), updateType: "status", status: item.status, message: payload.message || payload.resolution || "Status updated.", adminName: "Preview Owner", createdAt: item.updatedAt }, ...(item.updates || [])]; return mockFeedbackDetail(item); },
  async syncFeedbackGitHub(id) { const item = mockAdmin.feedback.find(row => Number(row.id) === Number(id)); item.githubSyncStatus = mockAdmin.feedbackConfig.githubTokenConfigured ? "synced" : "not_configured"; return mockFeedbackDetail(item); },
  async feedbackConfiguration() { return mockAdmin.feedbackConfig; },
  async saveFeedbackConfiguration(payload) { mockAdmin.feedbackConfig = { ...mockAdmin.feedbackConfig, ...payload, githubTokenConfigured: mockAdmin.feedbackConfig.githubTokenConfigured || Boolean(payload.githubToken) }; return { saved: true, githubTokenConfigured: mockAdmin.feedbackConfig.githubTokenConfigured }; },
};

const production = {
  publicConfiguration: () => request("/public/configuration"),
  publicAssessmentExperience: () => request("/public/assessment-experience"),
  createSession: payload => request("/survey-sessions", { method: "POST", body: JSON.stringify(payload) }),
  saveSession: ({ id, ...payload }) => request(`/survey-sessions/${id}`, { method: "PATCH", body: JSON.stringify(payload) }),
  loadSession: () => { const token = new URLSearchParams(location.search).get("resume"); return token ? request(`/survey-sessions/resume/${encodeURIComponent(token)}`) : Promise.resolve(null); },
  completeSession: ({ id, ...payload }) => request(`/survey-sessions/${id}/complete`, { method: "POST", body: JSON.stringify(payload) }),
  createCheckout: payload => request("/payments/checkout", { method: "POST", body: JSON.stringify(payload) }),
  checkoutStatus: checkout => request(`/payments/checkout-status?checkout=${encodeURIComponent(checkout)}`),
  getReport: token => request(`/reports/${encodeURIComponent(token)}`),
  adminSession: async () => { const result = await request("/admin/session"); csrfToken = result.csrfToken; return result; },
  adminLogin: async credentials => { const result = await request("/admin/login", { method: "POST", body: JSON.stringify(credentials) }); csrfToken = result.csrfToken; return result; },
  adminLogout: async () => { const result = await request("/admin/logout", { method: "POST", body: "{}" }); csrfToken = ""; return result; },
  adminDashboard: () => request("/admin/dashboard"), adminInsights: () => request("/admin/insights"), adminSearch: term => request(`/admin/search?q=${encodeURIComponent(term)}`),
  adminParticipants: (query = {}) => request(`/admin/participants?${new URLSearchParams(query)}`), adminParticipant: id => request(`/admin/participants/${id}`), exportParticipant: id => request(`/admin/participants/${id}/export`), anonymiseParticipant: id => request(`/admin/participants/${id}`, { method: "DELETE", body: "{}" }),
  adminAssessments: () => request("/admin/assessments"), adminAssessmentVersion: id => request(`/admin/assessment-versions/${id}`),
  adminAssessmentExperience: id => request(`/admin/assessment-tracks/${id}/experience`), saveAssessmentExperience: (id, payload) => request(`/admin/assessment-tracks/${id}/experience`, { method: "PUT", body: JSON.stringify(payload) }),
  cloneAssessment: (id, payload) => request(`/admin/assessment-versions/${id}/clone`, { method: "POST", body: JSON.stringify(payload) }), publishAssessment: id => request(`/admin/assessment-versions/${id}/publish`, { method: "POST", body: "{}" }), saveTrackSettings: (id, payload) => request(`/admin/assessment-tracks/${id}/settings`, { method: "PUT", body: JSON.stringify(payload) }), saveQuestion: (id, payload) => request(`/admin/questions/${id}`, { method: "PUT", body: JSON.stringify(payload) }), saveReportTemplate: (id, payload) => request(`/admin/report-templates/${id}`, { method: "PUT", body: JSON.stringify(payload) }),
  adminContent: () => request("/admin/content-stages"), saveContentStage: (key, payload) => request(`/admin/content-stages/${encodeURIComponent(key)}`, { method: "PATCH", body: JSON.stringify(payload) }), adminMedia: () => request("/admin/media"), uploadMedia: (file, metadata = {}) => { const form = new FormData(); form.append("file", file); Object.entries(metadata).forEach(([key, value]) => form.append(key, String(value))); return request("/admin/media", { method: "POST", body: form }); },
  adminBranding: () => request("/admin/branding"), saveBrandingDraft: payload => request("/admin/branding/draft", { method: "PUT", body: JSON.stringify(payload) }), publishBranding: id => request(`/admin/branding/${id}/publish`, { method: "POST", body: "{}" }),
  adminReports: () => request("/admin/reports"), reportAction: (id, action) => request(`/admin/reports/${id}/${action}`, { method: "POST", body: "{}" }), adminPayments: () => request("/admin/payments"),
  adminEmailTemplates: () => request("/admin/email-templates"), saveEmailTemplate: (key, payload) => request(`/admin/email-templates/${encodeURIComponent(key)}`, { method: "PUT", body: JSON.stringify(payload) }), testEmailTemplate: (key, payload) => request(`/admin/email-templates/${encodeURIComponent(key)}/test`, { method: "POST", body: JSON.stringify(payload) }), adminEmailQueue: (query = {}) => request(`/admin/email-queue?${new URLSearchParams(query)}`), retryEmail: id => request(`/admin/email-queue/${id}/retry`, { method: "POST", body: "{}" }), testEmail: recipient => request("/admin/email/test", { method: "POST", body: JSON.stringify({ recipient }) }),
  adminAffiliates: () => request("/admin/affiliates"), saveAffiliate: payload => payload.id ? request(`/admin/affiliates/${payload.id}`, { method: "PUT", body: JSON.stringify(payload) }) : request("/admin/affiliates", { method: "POST", body: JSON.stringify(payload) }), adminSeoPages: () => request("/admin/seo-pages"), saveSeoPage: (key, payload) => request(`/admin/seo-pages/${encodeURIComponent(key)}`, { method: "PUT", body: JSON.stringify(payload) }),
  adminSettings: (groups = []) => request(`/admin/settings${groups.length ? `?groups=${encodeURIComponent(groups.join(","))}` : ""}`), saveSettings: (group, payload) => request(`/admin/settings/${encodeURIComponent(group)}`, { method: "PUT", body: JSON.stringify(payload) }), adminAlertRecipients: () => request("/admin/alert-recipients"), saveAlertRecipient: payload => payload.id ? request(`/admin/alert-recipients/${payload.id}`, { method: "PUT", body: JSON.stringify(payload) }) : request("/admin/alert-recipients", { method: "POST", body: JSON.stringify(payload) }),
  adminAuditLogs: (query = {}) => request(`/admin/audit-logs?${new URLSearchParams(query)}`), adminUsers: () => request("/admin/users"), saveAdminUser: payload => payload.id ? request(`/admin/users/${payload.id}`, { method: "PUT", body: JSON.stringify(payload) }) : request("/admin/users", { method: "POST", body: JSON.stringify(payload) }), adminNotifications: () => request("/admin/notifications"), acknowledgeNotification: id => request(`/admin/notifications/${id}/acknowledge`, { method: "POST", body: "{}" }), adminAnalytics: () => request("/admin/analytics/funnel"), testIntegration: (provider, payload = {}) => request(`/admin/integrations/${encodeURIComponent(provider)}/test`, { method: "POST", body: JSON.stringify(payload) }),
  adminFeedback: (query = {}) => request(`/admin/feedback?${new URLSearchParams(query)}`), adminFeedbackDetail: id => request(`/admin/feedback/${id}`), submitFeedback: payload => request("/admin/feedback", { method: "POST", body: JSON.stringify(payload) }), updateFeedback: (id, payload) => request(`/admin/feedback/${id}`, { method: "PATCH", body: JSON.stringify(payload) }), syncFeedbackGitHub: id => request(`/admin/feedback/${id}/sync-github`, { method: "POST", body: "{}" }), feedbackConfiguration: () => request("/admin/feedback-configuration"), saveFeedbackConfiguration: payload => request("/admin/feedback-configuration", { method: "PUT", body: JSON.stringify(payload) }),
};

export const api = mode === "mock" ? mock : production;
export const isMockMode = mode === "mock";
