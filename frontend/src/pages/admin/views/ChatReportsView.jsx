import { formatNotificationTime } from '../../../services/notifications'
import { useEffect, useState } from 'react'
import { api } from '../../../services/apiClient'
import { useNotify } from '../../../context/NotificationContext'
import { Button } from '../../../components/ui/Button'
export default function ChatReportsView() {
  const notify = useNotify()
  const [rows, setRows] = useState([])
  const [filter, setFilter] = useState('active')
  const [page, setPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [detail, setDetail] = useState(null)
  const [status, setStatus] = useState('under_review')
  const [findings, setFindings] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const load = async () => { try { const r = await api.get(`/api/admin/chat-reports?status=${filter}&page=${page}`); setRows(r.reports || []); setTotal(r.total || 0); setError('') } catch (e) { setError(e.message) } }
  useEffect(() => { load() }, [filter, page])
  const open = async (id) => { if (busy) return; setBusy(true); try { const r = await api.get(`/api/admin/chat-reports/${id}`); setDetail(r); setFindings(r.report.findings || '') } catch (e) { notify({ severity: 'error', message: e.message }) } finally { setBusy(false) } }
  const save = async (e) => { e.preventDefault(); if (busy) return; setBusy(true); try { await api.post(`/api/admin/chat-reports/${detail.report.id}/decision`, { status, findings }); setDetail(null); await load(); notify({ severity: 'success', message: 'Report updated.' }) } catch (e) { notify({ severity: 'error', message: e.message }) } finally { setBusy(false) } }
  return <section className="container"><h1>Chat reports</h1><p>Access is limited to reported messages and necessary context. Evidence access is logged.</p>
    <label htmlFor="chat-report-filter">Report status</label><select id="chat-report-filter" value={filter} onChange={e => { setFilter(e.target.value); setPage(1) }}><option value="active">Open and under review</option><option value="all">All reports</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select>
    {error && <div role="alert">{error}<Button onClick={load}>Retry</Button></div>}
    {!rows.length && !error && <p>No chat reports.</p>}
    <div className="table-container"><table><thead><tr><th>Reporter</th><th>Reason</th><th>Status</th><th>Date</th><th>Review</th></tr></thead><tbody>{rows.map(r => <tr key={r.id}><td>{r.reporter_name || 'Closed account'}</td><td>{r.reason}</td><td>{r.status}</td><td>{formatNotificationTime(r.created_at)}</td><td><Button disabled={busy} onClick={() => open(r.id)}>Review report</Button></td></tr>)}</tbody></table></div>
    <nav aria-label="Report pages"><Button variant="secondary" disabled={busy || page === 1} onClick={() => setPage(p => p-1)}>Previous</Button><span> Page {page} </span><Button variant="secondary" disabled={busy || page*25 >= total} onClick={() => setPage(p => p+1)}>Next</Button></nav>
    {detail && <section className="card"><h2>Report #{detail.report.id}</h2><p>{detail.report.reason}</p>{detail.evidence.map(m => <article key={m.message_id}><strong>{m.is_selected ? 'Reported message' : 'Context'} · Account #{m.sender_id || 'removed'}</strong><p style={{ whiteSpace: 'pre-wrap' }}>{m.body}</p><small>{formatNotificationTime(m.message_created_at)}</small></article>)}
      {['resolved', 'dismissed'].includes(detail.report.status) ? <p>{detail.report.findings}</p> : <form onSubmit={save}><div className="field"><label htmlFor="report-status">Status</label><select id="report-status" value={status} onChange={e => setStatus(e.target.value)}><option value="under_review">Under review</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select></div><div className="field"><label htmlFor="report-findings">Findings</label><textarea id="report-findings" required maxLength={2000} value={findings} onChange={e => setFindings(e.target.value)} /></div><Button type="submit" disabled={busy}>Save review</Button></form>}
      <Button variant="secondary" onClick={() => setDetail(null)}>Close review</Button>
    </section>}
  </section>
}
