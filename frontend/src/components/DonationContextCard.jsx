import { useEffect, useId, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/apiClient'
import { invalidateDonationViews } from '../services/liveQueries'
import { donationReportForm, donationStatus, donationTimeToUtc } from '../services/donationCompletion'
import DonationDateTimeFields from './DonationDateTimeFields'
import DonationProofInput from './DonationProofInput'
import ConfirmationDialog from './ConfirmationDialog'
import { Button } from './ui/Button'
import styles from './DonationContextCard.module.css'
export default function DonationContextCard({ conversationId }) {
  const { user } = useAuth()
  return <ContextWorkspace key={`${user?.id}:${conversationId}`} conversationId={conversationId} />
}
function ContextWorkspace({ conversationId }) {
  const { user, refresh } = useAuth()
  const client = useQueryClient()
  const route = useLocation()
  const id = useId()
  const [selected, setSelected] = useState('')
  const [date, setDate] = useState('')
  const [photos, setPhotos] = useState([])
  const [formOpen, setFormOpen] = useState(false)
  const [modalOpen, setModalOpen] = useState(false)
  const [detailsOpen, setDetailsOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const pending = useRef(false)
  const errorRef = useRef(null)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState('')
  const [sentPrompts, setSentPrompts] = useState({})
  const [acknowledgedVersions, setAcknowledgedVersions] = useState({})
  useEffect(() => { if (error) { errorRef.current?.focus(); errorRef.current?.scrollIntoView({ block: 'nearest' }) } }, [error])
  const { data, error: loadError, refetch } = useQuery({ queryKey: ['donation-chat-contexts', conversationId, user?.id], queryFn: () => api.get(`/api/chat/conversations/${conversationId}/donation-contexts`), enabled: Boolean(conversationId), refetchInterval: 5000, retry: false })
  const contexts = data?.contexts || []
  const requested = new URLSearchParams(route.search).get('request')
  const context = contexts.find(c => String(c.id) === selected) || contexts.find(c => String(c.request_id) === requested) || contexts[0]
  useEffect(() => {
    // Incoming reports must not take over the requester's screen.
    if (context?.report?.id && Number(context.donor_id) !== Number(user.id)) {
      setModalOpen(false)
      setFormOpen(false)
    }
  }, [context?.id, context?.report?.id, user.id])
  if (!context) return loadError ? <p role="alert">{loadError.message}</p> : null
  const report = context.report
  const donor = Number(context.donor_id) === Number(user.id)
  const promptAt = sentPrompts[context.id] || context.last_donor_prompt_at
  const promptSent = Boolean(promptAt && Date.now() - new Date(promptAt.includes('T') ? promptAt : promptAt.replace(' ', 'T') + 'Z').getTime() < 86400000)
  const acknowledged = report && acknowledgedVersions[report.id] === report.content_version
  const run = async (task, success = 'Donation status updated.') => {
    if (pending.current) return
    pending.current = true; setBusy(true); setError(''); setFeedback('')
    try {
      const result = await task(); setFormOpen(false); setPhotos([]); setDate(''); setFeedback(typeof success === 'function' ? success(result) : success)
      await Promise.allSettled([refetch(), refresh()])
      invalidateDonationViews(client)
    } catch (e) { setError(e.message); await refetch() } finally { pending.current = false; setBusy(false) }
  }
  const signal = action => {
    if (action === 'prompt_donor' && promptSent) return
    return run(async () => {
      const result = await api.post(`/api/donation-contexts/${context.id}/signal`, { action, expected_version: Number(report?.content_version || 0) })
      if (action === 'prompt_donor') setSentPrompts(current => ({ ...current, [context.id]: new Date().toISOString() }))
      return result
    }, action === 'prompt_donor' ? 'The donor has been reminded. You can send another reminder after 24 hours.' : 'Coordination update recorded. This does not confirm a donation.')
  }
  const acknowledge = response => {
    if (acknowledged) return
    return run(async () => {
      const result = await api.post(`/api/donation-completion/${report.id}/acknowledge`, { expected_version: Number(report.content_version), response })
      setAcknowledgedVersions(current => ({ ...current, [report.id]: report.content_version }))
      return result
    })
  }
  const submit = e => {
    e.preventDefault()
    run(() => {
      const instant = donationTimeToUtc(date)
      if (new Date(instant).getTime() > Date.now()) throw new Error('Choose a donation time that has already passed. Future donations cannot be reported.')
      return api.postForm('/api/donation-completion', donationReportForm(context.match_id, date, photos, context.id))
    }, 'Report submitted. Waiting for the requester to choose Donation received. Your donor hold remains until the report is resolved.')
  }
  const contextLabel = c => [c.required_blood_type, c.facility_name, c.needed_datetime ? new Date(c.needed_datetime.replace(' ', 'T') + 'Z').toLocaleString('en-PH', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) : c.request_status, Number(c.offer_cycle) !== Number(c.current_cycle) ? 'Previous offer' : ''].filter(Boolean).join(' · ')
  const requestLabel = contextLabel(context)
  const summary = report ? donationStatus(report) : context.active ? 'Donation not reported' : 'Coordination inactive'
  return <>
    <section className={styles.reminder} aria-label="Request donation status" data-donation-request={context.request_id} data-donation-report={report?.id || ''} data-donation-details-open={modalOpen ? 'true' : 'false'}>
      <div><strong>{summary}</strong><span title={requestLabel}>{requestLabel}</span></div>
      <Button type="button" variant="secondary" aria-haspopup="dialog" aria-expanded={modalOpen} onClick={() => { setSelected(String(context.id)); if (donor && !report) setFormOpen(true); setModalOpen(true) }}>Report donation</Button>
    </section>
    <ConfirmationDialog open={modalOpen} dialogRole="dialog" panelClassName={styles.modalPanel} title={donor && !report ? 'Report completed donation' : 'Donation details'} cancelLabel={null} showCloseButton busy={busy} onCancel={() => { if (!pending.current) setModalOpen(false) }}>
    <div className={styles.card}>
    <label htmlFor={id}>Request coordination</label>
    <select disabled={busy} id={id} value={context.id} onChange={e => { const next = contexts.find(c => String(c.id) === e.target.value); setSelected(e.target.value); setDetailsOpen(false); setFormOpen(Number(next?.donor_id) === Number(user.id) && !next?.report); setDate(''); setPhotos([]); setError(''); setFeedback('') }}>{contexts.map(c => <option key={c.id} value={c.id}>{contextLabel(c)}</option>)}</select>
    {!(formOpen && !report) && <p><strong>{summary}</strong></p>}
    {feedback && <p role="status" className={styles.feedback}>{feedback}</p>}
    {!(formOpen && !report) && <div className={styles.actions}>
      {!report && !donor && <div><Button variant="secondary" disabled={busy || promptSent} onClick={() => signal('prompt_donor')}>{promptSent ? 'Reminder sent' : 'Ask donor to report for donation'}</Button><small className={styles.promptHelp}>{promptSent ? 'Another reminder is available after 24 hours.' : 'Sends a reminder only. It does not confirm a donation.'}</small></div>}
      {report && context.report_in_context && !donor && report.status === 'PENDING' && !report.requester_response && report.workflow_state !== 'clarification' && <>
        <Button disabled={busy || acknowledged} onClick={() => acknowledge('received')}>Donation received</Button><Button variant="secondary" disabled={busy || acknowledged} onClick={() => acknowledge('did_not_donate')}>Did not donate</Button><Button variant="secondary" disabled={busy || acknowledged} onClick={() => acknowledge('not_sure')}>Not sure</Button>
      </>}
    </div>}
    {formOpen && !report && <form className={styles.reportForm} onSubmit={submit} aria-busy={busy}>
      <DonationDateTimeFields value={date} onChange={value => { setDate(value); setError('') }} /><DonationProofInput files={photos} onChange={setPhotos} disabled={busy} />
      {error && <p ref={errorRef} tabIndex={-1} role="alert" className={styles.error}>{error}</p>}
      <div className={`${styles.actions} ${styles.formActions}`}><Button type="button" variant="secondary" disabled={busy} onClick={() => setModalOpen(false)}>Cancel</Button><Button type="submit" isLoading={busy}>{busy ? 'Submitting…' : 'Submit donation report'}</Button></div>
    </form>}
    {error && !formOpen && <p ref={errorRef} tabIndex={-1} role="alert" className={styles.error}>{error}</p>}
    {!(donor && !report) && <Button type="button" variant="secondary" aria-expanded={detailsOpen} aria-controls={id + '-details'} onClick={() => setDetailsOpen(!detailsOpen)}>{detailsOpen ? 'Hide donation details' : 'View donation details'}</Button>}
    {detailsOpen && <div id={id + '-details'} className={styles.body}>
      <p>{context.quantity_units} unit(s) requested · {context.request_status.toLowerCase()}</p>
      {report && !context.report_in_context && <p>Previous offer - open the report to manage this donation.</p>}
      <div className={styles.actions}>
        {(!report || (report.status === 'PENDING' && context.report_in_context)) && <Button variant="secondary" disabled={busy} onClick={() => signal('did_not_happen')}>Donation didn’t happen</Button>}
        <Button variant="secondary" disabled={busy || !context.active} onClick={() => signal('still_arranging')}>Still arranging</Button>
      </div>
      {report && <div className={styles.actions}><Button variant="secondary" to={`/donation-reports?report=${report.id}`} state={{ from: route.pathname + route.search }} onClick={() => setModalOpen(false)}>Open report</Button></div>}
    </div>}
    </div>
    </ConfirmationDialog>
  </>
}
