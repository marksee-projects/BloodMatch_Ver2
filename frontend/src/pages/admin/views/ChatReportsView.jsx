import { useEffect, useRef, useState } from 'react'
import { ShieldCheck } from '@phosphor-icons/react'
import { formatNotificationTime } from '../../../services/notifications'
import { api } from '../../../services/apiClient'
import { useNotify } from '../../../context/NotificationContext'
import { Button } from '../../../components/ui/Button'
import { Badge } from '../../../components/ui/Badge'
import styles from './ChatReportsView.module.css'
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
  const [loading, setLoading] = useState(true)
  const version = useRef(0)
  const reviewHeading = useRef(null)
  useEffect(() => { if (detail) reviewHeading.current?.focus() }, [detail])
  const load = async () => {
    const current = ++version.current
    setLoading(true); setError('')
    try { const r = await api.get('/api/admin/chat-reports?status=' + filter + '&page=' + page); if (current === version.current) { setRows(r.reports || []); setTotal(r.total || 0) } }
    catch (e) { if (current === version.current) setError(e.message) }
    finally { if (current === version.current) setLoading(false) }
  }
  useEffect(() => { load(); return () => { ++version.current } }, [filter, page])
  const open = async id => {
    if (busy) return
    setBusy(true)
    try { const r = await api.get('/api/admin/chat-reports/' + id); setDetail(r); setFindings(r.report.findings || ''); setStatus('under_review') }
    catch (e) { notify({ severity: 'error', message: e.message }) }
    finally { setBusy(false) }
  }
  const save = async e => {
    e.preventDefault(); if (busy) return; setBusy(true)
    try { await api.post('/api/admin/chat-reports/' + detail.report.id + '/decision', { status, findings }); setDetail(null); await load(); notify({ severity: 'success', message: 'Report updated.' }) }
    catch (e) { notify({ severity: 'error', message: e.message }) }
    finally { setBusy(false) }
  }
  const label = value => ({open: 'Open', under_review: 'Under review', resolved: 'Handled', dismissed: 'Dismissed'}[value] || value)
  if (detail) {
    const reportedNames = [...new Set(detail.evidence.filter(m => Number(m.is_selected) && Number(m.sender_id) !== Number(detail.report.reporter_id)).map(m => m.sender_name || 'Deleted account'))]
    const closed = ['resolved', 'dismissed'].includes(detail.report.status)
    return <section className={styles.page} aria-labelledby="report-detail-title">
      <header className={styles.reviewHeader}><div><Button variant="secondary" disabled={busy} onClick={() => setDetail(null)}>Back to reports</Button><h1 className="sr-only" id="report-detail-title" ref={reviewHeading} tabIndex={-1}>Review conversation</h1></div><span className={styles.reference}>Reference #{detail.report.id}</span></header>
      <div className={styles.reviewGrid}>
        <section className={styles.evidencePanel} aria-label="Reported conversation evidence"><div className={styles.evidenceHeading}><h2>Messages</h2><Badge>{label(detail.report.status)}</Badge></div>
          <div className={styles.evidence} tabIndex={0} aria-label="Scroll conversation evidence">{detail.evidence.map(m => <article className={Number(m.is_selected) ? styles.selectedEvidence : styles.contextEvidence} key={m.message_id}><header><strong>{m.sender_name || 'Deleted account'}</strong><span>{Number(m.is_selected) ? 'Reported' : 'Context'}</span></header><p>{m.body}</p><small>{formatNotificationTime(m.message_created_at)}</small></article>)}</div>
        </section>
        <aside className={styles.review} aria-label="Report details and decision">
          <dl className={styles.summary}><div><dt>Reported by</dt><dd>{detail.report.reporter_name || 'Deleted account'}</dd></div><div><dt>Reported participant{reportedNames.length > 1 ? 's' : ''}</dt><dd>{reportedNames.join(', ') || 'Not identified in selected messages'}</dd></div><div><dt>Reason</dt><dd>{detail.report.reason}</dd></div><div><dt>Submitted</dt><dd>{formatNotificationTime(detail.report.created_at)}</dd></div></dl>
          {closed ? <div className={styles.findings}><h2>Findings</h2><p>{detail.report.findings}</p></div> : <form className={styles.reviewForm} onSubmit={save}><div className="field"><label htmlFor="report-status">Decision</label><select className="chat-control" id="report-status" value={status} onChange={e => setStatus(e.target.value)}><option value="under_review">Under review</option><option value="resolved">Handled</option><option value="dismissed">Dismissed</option></select></div><div className="field"><label htmlFor="report-findings">Findings</label><textarea className="chat-control" id="report-findings" required maxLength={2000} value={findings} onChange={e => setFindings(e.target.value)} /></div><small className={styles.note}>{status === 'resolved' ? 'Marking as Handled confirms offensive behavior and disables the reported user for 24 hours.' : 'Saving this decision updates the report record.'}</small><Button type="submit" disabled={busy}>Save review</Button></form>}
        </aside>
      </div>
    </section>
  }
  return <section className={styles.page}>
    <h1 className="sr-only">Chat reports</h1>
    <div className={styles.toolbar}><div className="field"><label htmlFor="chat-report-filter">Report status</label><select className="chat-control" id="chat-report-filter" value={filter} onChange={e => { setFilter(e.target.value); setPage(1) }}><option value="active">Open and under review</option><option value="all">All reports</option><option value="resolved">Handled</option><option value="dismissed">Dismissed</option></select></div><span className={styles.count}>{loading ? 'Loading...' : total + (total === 1 ? ' report' : ' reports')}</span></div>
    {error ? <div className={styles.empty} role="alert"><p>{error}</p><Button variant="secondary" onClick={load}>Retry</Button></div> : loading ? <div className={styles.empty} role="status">Loading reports...</div> : !rows.length ? <div className={styles.empty}><ShieldCheck size={32} aria-hidden="true" /><h2>{filter === 'all' ? 'No chat reports' : 'No reports in this view'}</h2></div> : <>
      <div className={styles.tableWrap}><table><thead><tr><th>Reporter</th><th>Reason</th><th>Status</th><th>Date</th><th>Review</th></tr></thead><tbody>{rows.map(r => <tr key={r.id}><td>{r.reporter_name || 'Closed account'}</td><td><span className={styles.reason}>{r.reason}</span></td><td><Badge variant={r.status === 'resolved' ? 'success' : 'neutral'}>{label(r.status)}</Badge></td><td>{formatNotificationTime(r.created_at)}</td><td><Button variant="secondary" disabled={busy} onClick={() => open(r.id)}>Review</Button></td></tr>)}</tbody></table></div>
      {total > 25 && <nav className={styles.pagination} aria-label="Report pages"><Button variant="secondary" disabled={busy || page === 1} onClick={() => setPage(p => p-1)}>Previous</Button><span>Page {page} of {Math.ceil(total / 25)}</span><Button variant="secondary" disabled={busy || page*25 >= total} onClick={() => setPage(p => p+1)}>Next</Button></nav>}
    </>}
  </section>
}
