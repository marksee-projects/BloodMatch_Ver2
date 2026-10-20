import { useEffect, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from '@phosphor-icons/react'
import { liveQueryOptions, invalidateDonationViews } from '../services/liveQueries'
import { reportEventLabel, reportSnapshot, requesterResponseLabel } from '../services/reportPresentation'
import { useLocation } from 'react-router-dom'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import { useNotify } from '../context/NotificationContext'
import { Button } from './ui/Button'
import DonationDateTimeFields from './DonationDateTimeFields'
import DonationProofPanel from './DonationProofPanel'
import { donationStatus, donationTimeToUtc, donationTimeForPicker, DONATION_TIMEZONE } from '../services/donationCompletion'
import styles from './DonationReportInbox.module.css'

function when(value) {
  return value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('en-PH', { timeZone: DONATION_TIMEZONE, month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : 'Not recorded'
}

export default function DonationReportInbox(props) {
  const { user } = useAuth()
  return <ReportWorkspace key={`${user?.id}:${Boolean(props.admin)}`} {...props} />
}

function ReportWorkspace({ admin = false, onChanged }) {
  const { user, refresh } = useAuth()
  const notify = useNotify()
  const route = useLocation()
  const client = useQueryClient()
  const [scope, setScope] = useState('queue')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('')
  const listQuery = useQuery({ ...liveQueryOptions, queryKey: ['donation-reports', user?.id, admin, scope, page, filter], enabled: Boolean(user?.id), queryFn: () => {
    const params = new URLSearchParams()
    if (admin) params.set('scope', scope)
    if (admin && scope === 'history') { params.set('page', String(page)); params.set('page_size', '20'); if (filter) params.set('q', filter) }
    return api.get('/api/donation-completion?' + params)
  } })
  const reports = listQuery.data?.reports
  const error = listQuery.error?.message
  const load = () => client.invalidateQueries({ queryKey: ['donation-reports', user?.id] })
  const totalPages = Math.max(1, Math.ceil((listQuery.data?.total || 0) / 20))
  useEffect(() => { if (scope === 'history' && page > totalPages && listQuery.data) setPage(totalPages) }, [scope, page, totalPages, listQuery.data])
  const [busy, setBusy] = useState(false)
  const [notes, setNotes] = useState({})
  const [dates, setDates] = useState({})
  const [events, setEvents] = useState({})
  const [releaseChecks, setReleaseChecks] = useState({})
  const pending = useRef(false)
  const alive = useRef(false)
  useEffect(() => { alive.current = true; return () => { alive.current = false } }, [])

  const act = async (report, path, extra) => {
    if (pending.current) return
    pending.current = true; setBusy(true)
    try {
      await api.post(`/api/donation-completion/${report.id}/${path}`, { expected_version: Number(report.content_version), ...extra })
      if (!alive.current) return
      setNotes(current => ({ ...current, [report.id]: '' })); setEvents(current => { const next = { ...current }; delete next[report.id]; return next })
      invalidateDonationViews(client); await load(); await refresh(); onChanged?.()
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
  useEffect(() => {
    if (target && reports?.some(report => Number(report.id) === target)) document.getElementById('donation-report-' + target)?.scrollIntoView({ block: 'start', behavior: 'instant' })
  }, [target, Boolean(reports?.some(report => Number(report.id) === target))])
  const Heading = admin ? 'h2' : 'h1'
  return <section className={styles.inbox} aria-label={admin ? 'Donation review queue' : 'Donation reports'}>
    {!admin && <Button className={styles.back} variant="secondary" to={typeof route.state?.from === 'string' && /^\/(?!\/)/.test(route.state.from) && !route.state.from.startsWith('/donation-reports') ? route.state.from : '/'}><ArrowLeft size={18} aria-hidden="true" /> Back</Button>}
    <header className={styles.heading}>
      <div className={styles.headingText}><Heading>Donation reports</Heading></div>
    </header>
    {admin && <><nav className={styles.filters} aria-label="Donation reports"><Button variant={scope === 'queue' ? 'primary' : 'secondary'} onClick={() => { setScope('queue'); setPage(1) }}>Needs attention</Button><Button variant={scope === 'history' ? 'primary' : 'secondary'} onClick={() => { setScope('history'); setPage(1) }}>Completed donations</Button></nav></>}
    {admin && scope === 'history' && <form className={styles.search} onSubmit={e => { e.preventDefault(); setPage(1); setFilter(search.trim()) }}><label htmlFor="report-history-search">Find a completed donation</label><input id="report-history-search" value={search} maxLength={100} onChange={e => setSearch(e.target.value)} placeholder="Donor, requester, or facility" /><Button type="submit" variant="secondary">Search</Button></form>}
    {error && <div className="alert alert-error" role="alert"><p>{error}{reports && ' Showing the last loaded reports.'}</p><Button variant="secondary" onClick={() => listQuery.refetch()}>Retry</Button></div>}
    {listQuery.isPending && <p role="status">Loading reports...</p>}
    {reports?.length === 0 && <div className={styles.empty}><h3>{admin && scope === 'queue' ? 'No reports need attention' : 'No donation reports found'}</h3></div>}
    {ordered.map(report => {
      const donor = Number(report.donor_id) === Number(user.id)
      const requester = Number(report.requester_id) === Number(user.id)
      const canReview = admin && scope === 'queue' && !donor && !requester
      const unresolved = report.status === 'PENDING'
      const hasHold = Number(report.has_report_hold) === 1
      const note = notes[report.id] || ''
      return <article className={`${styles.report} ${Number(report.id) === target ? styles.targeted : ''}`} key={report.id} id={`donation-report-${report.id}`}>
        <header className={styles.reportHeader}><div><span className={styles.eyebrow}>Donation report - {report.request_status.toLowerCase()} request</span><h3>{report.donor_name} <span className={styles.arrow} aria-label="to">→</span> {report.requester_name}</h3></div><strong className={`${styles.status} ${report.status === 'CONFIRMED' ? styles.completed : ''}`}>{donationStatus(report)}</strong></header>
        <dl className={styles.details}>
          <div><dt>Facility</dt><dd>{report.facility_name}</dd></div>
          <div><dt>Donation occurred</dt><dd>{when(report.occurred_at)}</dd></div>
          <div><dt>Report submitted</dt><dd>{when(report.reported_at)}</dd></div>

          <div><dt>Requester response</dt><dd>{report.requester_response ? requesterResponseLabel(report.requester_response) : unresolved ? 'Awaiting response' : 'No acknowledgment recorded'}{report.acknowledged_at && ` · ${when(report.acknowledged_at)}`}</dd></div>
          <div><dt>Admin review</dt><dd>{report.reviewed_at ? when(report.reviewed_at) : 'Not reviewed'}</dd></div>
          <div><dt>Completion recorded</dt><dd>{report.confirmed_at ? when(report.confirmed_at) : 'Not completed'}</dd></div>

        </dl>
        {(report.report_note || report.rejection_reason || report.review_evidence || report.hold_release_basis) && <div className={styles.privateNotes}>
          {report.report_note && <p><strong>Donor reference</strong><span>{report.report_note}</span></p>}
          {report.rejection_reason && <p><strong>Rejection reason</strong><span>{report.rejection_reason}</span></p>}
          {report.review_evidence && <p><strong>Review basis</strong><span>{report.review_evidence}</span></p>}
          {report.hold_release_basis && <p><strong>Hold-release basis</strong><span>{report.hold_release_basis}</span></p>}
        </div>}
        {(unresolved || hasHold) && <p className={styles.holdNote}>{hasHold ? 'Donor hold active' : 'Pending'} · {Math.max(0, Number(report.age_hours))} hours</p>}
        {Number(report.pending_correction_count) > 0 && <p className={styles.holdNote}>Correction awaiting review · {when(report.oldest_correction_at)}</p>}
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
          {(unresolved || report.status === 'REJECTED') && <Button variant="secondary" disabled={busy || !note.trim()} onClick={() => act(report, 'review', { action: 'clarify', basis: note })}>Request clarification or proof photo</Button>}
          {report.status === 'REJECTED' && hasHold && <><label className={styles.release}><input type="checkbox" checked={!!releaseChecks[report.id]} onChange={e => setReleaseChecks(current => ({ ...current, [report.id]: e.target.checked }))} />My documented basis establishes that no donation occurred.</label><Button variant="secondary" disabled={busy || !note.trim() || !releaseChecks[report.id]} onClick={() => act(report, 'release-hold', { basis: note, no_donation_occurred: true })}>Release only this report’s hold</Button></>}
        </div>}
        {admin && (donor || requester) && <p>You cannot administratively review your own donation or request.</p>}
        <DonationProofPanel key={`${report.id}:${report.content_version}`} report={report} participant={donor || requester} canReview={canReview} onChanged={load} historyAction={<Button variant="secondary" disabled={busy} aria-expanded={Boolean(events[report.id])} aria-controls={`report-history-${report.id}`} onClick={() => events[report.id] ? setEvents(current => { const next = { ...current }; delete next[report.id]; return next }) : history(report)}>{events[report.id] ? 'Hide history' : 'History'}</Button>} />
        {events[report.id] && <ol id={`report-history-${report.id}`} className={styles.history}>{events[report.id].map(event => {
          const snapshot = reportSnapshot(event.snapshot_json)
          return <li key={event.id}>
            <div className={styles.eventHeading}><strong>{reportEventLabel(event.event_type)}</strong><span>{when(event.created_at)}</span></div>
            <p className={styles.update}>{event.actor_name || (event.actor_reference ? 'Former member' : 'System')}{event.event_type === 'requester_responded' && snapshot.requester_response ? ' - ' + requesterResponseLabel(snapshot.requester_response) : ''}</p>
            {snapshot.report_note && <p>{snapshot.report_note}</p>}
            {event.basis && <p>{event.basis}</p>}
          </li>
        })}</ol>}

      </article>
    })}
    {admin && scope === 'history' && totalPages > 1 && <nav className={styles.filters} aria-label="Completed donation pages"><Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span>Page {page} of {totalPages}</span><Button variant="secondary" disabled={page >= totalPages} onClick={() => setPage(page + 1)}>Next</Button></nav>}
  </section>
}
