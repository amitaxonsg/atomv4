import React from "react";
import { api } from "../../api/client";
import { DataTable, Notice, PageHeader, Spinner, dateTime, money, useLoader } from "./AdminShared";

function timeSpent(seconds) {
  const value = Number(seconds);
  if (!Number.isFinite(value) || value < 0) return "—";

  const total = Math.floor(value);
  const days = Math.floor(total / 86400);
  const hours = Math.floor((total % 86400) / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const secs = total % 60;

  if (days > 0) return `${days}d ${hours}h`;
  if (hours > 0) return `${hours}h ${minutes}m`;
  if (minutes > 0) return `${minutes}m ${secs}s`;
  return `${secs}s`;
}

function sessionTimeSpent(session) {
  if (!session?.completed_at || !session?.created_at) return "—";
  const start = Date.parse(session.created_at);
  const end = Date.parse(session.completed_at);
  if (!Number.isFinite(start) || !Number.isFinite(end) || end < start) return "—";
  return timeSpent((end - start) / 1000);
}

export default function AdminParticipantsPage({ initialSearch = "", initialId = null, permissions = [] }) {
  const [query, setQuery] = React.useState({ search: initialSearch, status: "", track: "", page: 1, limit: 25 });
  const [selected, setSelected] = React.useState(null);
  const [notice, setNotice] = React.useState("");
  const loader = useLoader(() => api.adminParticipants(query), [query.search, query.status, query.track, query.page]);
  const allowed = permission => permissions.includes("*") || permissions.includes(permission);

  React.useEffect(() => {
    setQuery(current => ({ ...current, search: initialSearch || "", page: 1 }));
  }, [initialSearch]);

  React.useEffect(() => {
    if (!initialId) return;
    api.adminParticipant(initialId).then(setSelected).catch(error => setNotice(error.message));
  }, [initialId]);

  const open = item => api.adminParticipant(item.id).then(setSelected).catch(error => setNotice(error.message));
  const exportRecord = () => api.exportParticipant(selected.participant.id).then(data => {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `participant-${selected.participant.id}.json`;
    link.click();
    URL.revokeObjectURL(link.href);
  });
  const anonymise = async () => {
    if (!window.confirm("Anonymise this participant and remove direct personal identifiers?")) return;
    await api.anonymiseParticipant(selected.participant.id);
    setSelected(null);
    loader.refresh();
  };

  return <>
    <PageHeader title="Participants" actions={<button className="button" onClick={loader.refresh}>Refresh</button>} />
    <Notice type="error">{loader.error || notice}</Notice>
    <div className="admin-filters">
      <input placeholder="Search name or email" value={query.search} onChange={event => setQuery(current => ({ ...current, search: event.target.value, page: 1 }))} />
      <select value={query.status} onChange={event => setQuery(current => ({ ...current, status: event.target.value, page: 1 }))}><option value="">All statuses</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="expired">Expired</option></select>
      <select value={query.track} onChange={event => setQuery(current => ({ ...current, track: event.target.value, page: 1 }))}><option value="">All tracks</option><option value="personal">Personal</option><option value="newjoiner">New Joiner</option><option value="manager">Manager</option><option value="executive">Executive</option></select>
      {(query.search || query.status || query.track) && <button className="button" onClick={() => setQuery({ search: "", status: "", track: "", page: 1, limit: 25 })}>Clear</button>}
    </div>

    {loader.loading ? <Spinner /> : <section className="admin-card">
      <DataTable columns={["Participant", "Track", "Status", "Progress", "Time spent", "Payment", "Last activity"]} rows={(loader.data?.items || []).map(item => [<><strong>{item.name}</strong><span>{item.email}</span></>, item.track, item.status, <div className="compact-progress"><i style={{ width: `${Number(item.progress || 0)}%` }} /><span>{item.progress || 0}%</span></div>, item.status === "completed" ? timeSpent(item.timeSpentSeconds) : "—", item.paymentStatus || "—", dateTime(item.lastActivityAt)])} onRow={index => open(loader.data.items[index])} />
      <div className="admin-pagination"><button className="button" disabled={query.page <= 1} onClick={() => setQuery(current => ({ ...current, page: current.page - 1 }))}>Previous</button><span>Page {query.page} · {loader.data?.total || 0} records</span><button className="button" disabled={query.page * query.limit >= (loader.data?.total || 0)} onClick={() => setQuery(current => ({ ...current, page: current.page + 1 }))}>Next</button></div>
    </section>}

    {selected && <div className="admin-drawer"><div className="admin-drawer__panel"><button className="admin-drawer__close" onClick={() => setSelected(null)}>×</button><h2>{selected.participant.name}</h2><p>{selected.participant.email}</p>
      <div className="admin-header-actions">{allowed("participants.export") && <button className="button" onClick={exportRecord}>Export</button>}{allowed("participants.delete") && <button className="button button--danger" onClick={anonymise}>Anonymise</button>}</div>
      <h3>Assessments</h3><DataTable columns={["Track", "Status", "Progress", "Time spent", "Completed"]} rows={(selected.sessions || []).map(item => [item.track, item.status, `${item.completion_percentage}%`, sessionTimeSpent(item), dateTime(item.completed_at)])} />
      <h3>Answers</h3><DataTable columns={["Question", "Answer", "Note"]} rows={(selected.answers || []).map(item => [item.questionText || item.question_position, item.is_not_applicable ? "N/A" : item.answer_value, item.note || "—"])} />
      <h3>Reports</h3><DataTable columns={["Status", "Views", "Created"]} rows={(selected.reports || []).map(item => [item.is_unlocked ? "Full" : "Lite", item.view_count, dateTime(item.created_at)])} />
      <h3>Payments</h3><DataTable columns={["Status", "Amount", "Date"]} rows={(selected.payments || []).map(item => [item.status, money(item.amount, item.currency), dateTime(item.created_at)])} />
      <h3>Email history</h3><DataTable columns={["Template", "Status", "Date"]} rows={(selected.emails || []).map(item => [item.template_key, item.status, dateTime(item.created_at)])} />
      <h3>Consent history</h3><DataTable columns={["Type", "Granted", "Date"]} rows={(selected.consents || []).map(item => [item.consent_type, item.consent_granted ? "Yes" : "No", dateTime(item.created_at)])} />
    </div></div>}
  </>;
}
