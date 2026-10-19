import { useEffect, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import { useNotify } from '../context/NotificationContext'
import { Button } from './ui/Button'
import DonationDateTimeFields from './DonationDateTimeFields'
import { donationStatus, donationTimeToUtc, donationTimeForPicker, DONATION_TIMEZONE } from '../services/donationCompletion'
import styles from './DonationReportInbox.module.css'

function when(value) {
  return value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('en-PH', { timeZone: DONATION_TIMEZONE, month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : 'Not recorded — clarification needed'
}

export default function DonationReportInbox(props) {
  const { user } = useAuth()
  return <ReportWorkspace key={`${user?.id}:${Boolean(props.admin)}`} {...props} />
}

function ReportWorkspace({ admin = false, onChanged }) {
  const { user, refresh } = useAuth()
  const notify = useNotify()
  const route = useLocation()
  const [reports, setReports] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [notes, setNotes] = useState({})
  const [dates, setDates] = useState({})
  const [events, setEvents] = useState({})
  const [releaseChecks, setReleaseChecks] = useState({})
  const pending = useRef(false)
  const alive = useRef(false)
  const load = async () => {
    try {
      const data = await api.get('/api/donation-completion' + (admin ? '?scope=queue' : ''))
      if (alive.current) { setReports(data.reports || []); setError('') }
    } catch (e) { if (alive.current) setError(e.message) }
  }
  useEffect(() => {
    alive.current = true; load()
    return () => { alive.current = false }
  }, [user?.id])

  const act = async (report, path, extra) => {
    if (pending.current) return
    pending.current = true; setBusy(true)
    try {
      await api.post(`/api/donation-completion/${report.id}/${path}`, { expected_version: Number(report.content_version), ...extra })
      if (!alive.current) return
      setNotes(current => ({ ...current, [report.id]: '' })); setEvents(current => { const next = { ...current }; delete next[report.id]; return next })
      await load(); await refresh(); onChanged?.()
      notify({ severity: 'success', message: 'Donation report updated.' })
    } catch (e) {
      if (alive.current) { notify({ severity: 'error', message: e.message }); await load() }
    } finally { pending.current = false; if (alive.current) setBusy(false) }
  }
  const history = async report => {
    try { const data = await api.get(`/api/donation-completion/${report.id}/events`); if (alive.current) setEvents(current => ({ ...current, [report.id]: data.events || [] })) }
    catch (e) { if (alive.current) notify({ severity: 'error', message: e.message }) }
  }
  const revise = report => {
    try { act(report, 'revise', { occurred_at: donationTimeToUtc(dates[report.id] || donationTimeForPicker(report.occurred_at)), note: notes[report.id] || '' }) }
    catch (e) { notify({ severity: 'error', message: e.message }) }
  }
  const target = Number(new URLSearchParams(route.search).get('report'))
  const ordered = [...(reports || [])].sort((a, b) => Number(Number(b.id) === target) - Number(Number(a.id) === target))
  const Heading = admin ? 'h2' : 'h1'
  return <section className={styles.inbox} aria-label={admin ? 'Donation review queue' : 'Donation reports'}>
    <header className={styles.heading}>
      <div className={styles.headingText}><Heading>{admin ? 'Reports and unresolved holds' : 'Donation reports'}</Heading><p>Track donation acknowledgments, decisions, and private report history.</p></div>
      <Button className={styles.refresh} variant="secondary" disabled={busy} onClick={load}>Refresh</Button>
    </header>
    <div className={styles.contextNote}><p>Reports stay available after a request closes. Acknowledgment or admin confirmation is not medical verification.</p><span>All dates in Asia/Manila</span></div>
    {error && <p className="alert alert-error" role="alert">{error}</p>}
    {!reports && !error && <p>Loading reports…</p>}
    {reports?.length === 0 && <div className={styles.empty}><h3>No donation reports yet</h3><p>Reports you submit or receive will appear here.</p></div>}
    {ordered.map(report => {
      const donor = Number(report.donor_id) === Number(user.id)
      const requester = Number(report.requester_id) === Number(user.id)
      const canReview = admin && !donor && !requester
      const unresolved = report.status === 'PENDING'
      const hasHold = Number(report.has_report_hold) === 1
      const note = notes[report.id] || ''
      return <article className={`${styles.report} ${Number(report.id) === target ? styles.targeted : ''}`} key={report.id} id={`donation-report-${report.id}`}>
        <header className={styles.reportHeader}><div><span className={styles.eyebrow}>Donation report #{report.id}</span><h3>{report.donor_name} <span className={styles.arrow} aria-label="to">→</span> {report.requester_name}</h3></div><strong className={`${styles.status} ${report.status === 'CONFIRMED' ? styles.completed : ''}`}>{donationStatus(report)}</strong></header>
        <dl className={styles.details}>
          <div><dt>Facility</dt><dd>{report.facility_name}</dd></div>
          <div><dt>Donation occurred</dt><dd>{when(report.occurred_at)}</dd></div>
          <div><dt>Report submitted</dt><dd>{when(report.reported_at)}</dd></div>
          <div><dt>Requester response</dt><dd>{report.requester_response ? report.requester_response.replaceAll('_', ' ') : unresolved ? 'Awaiting response' : 'No acknowledgment recorded'}{report.acknowledged_at && ` · ${when(report.acknowledged_at)}`}</dd></div>
          <div><dt>Admin review</dt><dd>{report.reviewed_at ? when(report.reviewed_at) : 'Not reviewed'}</dd></div>
          <div><dt>Completion recorded</dt><dd>{report.confirmed_at ? when(report.confirmed_at) : 'Not completed'}</dd></div>
          <div><dt>Request status</dt><dd className={styles.requestStatus}>{report.request_status.toLowerCase()}</dd></div>
          <div><dt>Report version</dt><dd>{report.content_version}</dd></div>
        </dl>
        {(unresolved || hasHold) && <p className={styles.holdNote}><strong>Open for {Math.max(0, Number(report.age_hours))} hours.</strong> {hasHold && 'A report-specific donor hold remains active.'}</p>}
        {(report.report_note || report.rejection_reason || report.review_evidence || report.hold_release_basis) && <div className={styles.privateNotes}>
          <h4>Private supporting details</h4>
          {report.report_note && <p><strong>Donor reference</strong><span>{report.report_note}</span></p>}
          {report.rejection_reason && <p><strong>Rejection reason</strong><span>{report.rejection_reason}</span></p>}
          {report.review_evidence && <p><strong>Review basis</strong><span>{report.review_evidence}</span></p>}
          {report.hold_release_basis && <p><strong>Hold-release basis</strong><span>{report.hold_release_basis}</span></p>}
        </div>}
        {(unresolved || (canReview && report.status === 'REJECTED')) && <div className={styles.responseField}><label htmlFor={`report-note-${report.id}`}>{canReview ? 'Decision basis or clarification (required)' : 'Private response or supporting reference (optional)'}</label><textarea id={`report-note-${report.id}`} maxLength={500} rows={3} value={note} onChange={e => setNotes(current => ({ ...current, [report.id]: e.target.value }))} /></div>}
        {requester && unresolved && !report.requester_response && report.workflow_state !== 'clarification' && <div className={styles.actions}>
          <Button disabled={busy} onClick={() => act(report, 'acknowledge', { response: 'received', note })}>Donation received</Button>
          <Button variant="secondary" disabled={busy} onClick={() => act(report, 'acknowledge', { response: 'did_not_donate', note })}>Did not donate</Button>
          <Button variant="secondary" disabled={busy} onClick={() => act(report, 'acknowledge', { response: 'not_sure', note })}>Not sure</Button>
        </div>}
        {unresolved && report.workflow_state === 'clarification' && (donor || requester) && <div className={styles.actions}>
          <Button variant="secondary" disabled={busy || !note.trim()} onClick={() => act(report, 'clarification', { note })}>Send clarification</Button>
          {donor && <><DonationDateTimeFields legend="Correct donation date and time" value={dates[report.id] ?? donationTimeForPicker(report.occurred_at)} onChange={value => setDates(current => ({ ...current, [report.id]: value }))} /><Button disabled={busy || !note.trim()} onClick={() => revise(report)}>Submit corrected version</Button></>}
        </div>}
        {canReview && <div className={styles.actions}>
          {unresolved && <><Button disabled={busy || !note.trim() || !report.occurred_at} onClick={() => act(report, 'review', { action: 'confirm', basis: note })}>Confirm with documented basis</Button><Button variant="secondary" disabled={busy || !note.trim()} onClick={() => act(report, 'review', { action: 'reject', basis: note })}>Reject report</Button></>}
          {(unresolved || report.status === 'REJECTED') && <Button variant="secondary" disabled={busy || !note.trim()} onClick={() => act(report, 'review', { action: 'clarify', basis: note })}>Request clarification</Button>}
          {report.status === 'REJECTED' && hasHold && <><label className={styles.release}><input type="checkbox" checked={!!releaseChecks[report.id]} onChange={e => setReleaseChecks(current => ({ ...current, [report.id]: e.target.checked }))} />My documented basis establishes that no donation occurred.</label><Button variant="secondary" disabled={busy || !note.trim() || !releaseChecks[report.id]} onClick={() => act(report, 'release-hold', { basis: note, no_donation_occurred: true })}>Release only this report’s hold</Button></>}
        </div>}
        {admin && (donor || requester) && <p>You cannot administratively review your own donation or request.</p>}
        <footer className={styles.reportFooter}><span>Visible only to authorized participants and staff</span><Button variant="secondary" disabled={busy} aria-expanded={Boolean(events[report.id])} onClick={() => events[report.id] ? setEvents(current => { const next = { ...current }; delete next[report.id]; return next }) : history(report)}>{events[report.id] ? 'Hide report history' : 'View report history'}</Button></footer>
        {events[report.id] && <ol className={styles.history}>{events[report.id].map(event => <li key={event.id}><strong>{event.event_type.replaceAll('_', ' ')}</strong> · version {event.content_version} · {when(event.created_at)} · {event.actor_reference ? event.actor_name || `Former account ${event.actor_reference}` : 'System'}{event.basis && <p>{event.basis}</p>}<details><summary>Recorded version details</summary><p>Donation time: {when(JSON.parse(event.snapshot_json).occurred_at)}<br />Donor reference: {JSON.parse(event.snapshot_json).report_note || 'None recorded'}<br />Requester response: {JSON.parse(event.snapshot_json).requester_response?.replaceAll('_', ' ') || 'None recorded'}</p></details></li>)}</ol>}
      </article>
    })}
  </section>
}
