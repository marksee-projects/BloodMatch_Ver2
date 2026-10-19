import { useEffect, useId, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/apiClient'
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
  const [busy, setBusy] = useState(false)
  const pending = useRef(false)
  const errorRef = useRef(null)
  const [error, setError] = useState('')
  const [feedback, setFeedback] = useState('')
  useEffect(() => { if (error) { errorRef.current?.focus(); errorRef.current?.scrollIntoView({ block: 'nearest' }) } }, [error])
  const { data, error: loadError, refetch } = useQuery({ queryKey: ['donation-chat-contexts', conversationId, user?.id], queryFn: () => api.get(`/api/chat/conversations/${conversationId}/donation-contexts`), enabled: Boolean(conversationId), refetchInterval: 5000, retry: false })
  const contexts = data?.contexts || []
  const requested = new URLSearchParams(route.search).get('request')
  const context = contexts.find(c => String(c.id) === selected) || contexts.find(c => String(c.request_id) === requested) || contexts[0]
  if (!context) return loadError ? <p role="alert">{loadError.message}</p> : null
  const report = context.report
  const donor = Number(context.donor_id) === Number(user.id)
  const run = async (task, success = 'Donation status updated.') => {
    if (pending.current) return
    pending.current = true; setBusy(true); setError(''); setFeedback('')
    try {
      const result = await task(); setFormOpen(false); setPhotos([]); setDate(''); setFeedback(typeof success === 'function' ? success(result) : success)
      await Promise.allSettled([refetch(), refresh()])
      client.invalidateQueries({ queryKey: ['my-responses'] }); client.invalidateQueries({ queryKey: ['my-requests'] }); client.invalidateQueries({ queryKey: ['home-feed'] })
    } catch (e) { setError(e.message); await refetch() } finally { pending.current = false; setBusy(false) }
  }
  const signal = action => run(() => api.post(`/api/donation-contexts/${context.id}/signal`, { action, expected_version: Number(report?.content_version || 0) }), action === 'prompt_donor' ? result => result.changed ? 'Reminder sent. The donor still needs to report the donation.' : 'A reminder was already sent. No additional notification was created.' : 'Coordination update recorded. This does not confirm a donation.')
  const acknowledge = response => run(() => api.post(`/api/donation-completion/${report.id}/acknowledge`, { expected_version: Number(report.content_version), response }))
  const submit = e => {
    e.preventDefault()
    run(() => {
      const instant = donationTimeToUtc(date)
      if (new Date(instant).getTime() > Date.now()) throw new Error('Choose a donation time that has already passed. Future donations cannot be reported.')
      return api.postForm('/api/donation-completion', donationReportForm(context.match_id, date, photos, context.id))
    }, 'Report submitted. Waiting for the requester to choose Donation received. Your donor hold remains until the report is resolved.')
  }
  const requestLabel = `${context.required_blood_type} · ${context.facility_name} · Request #${context.request_id}`
  const summary = report ? donationStatus(report) : context.active ? 'Donation not reported' : 'Coordination inactive'
  return <>
    <section className={styles.reminder} aria-label="Request donation status" data-donation-request={context.request_id} data-donation-report={report?.id || ''}>
      <div><strong>{summary}</strong><span title={requestLabel}>{requestLabel}</span></div>
      <Button type="button" variant="secondary" aria-haspopup="dialog" aria-expanded={modalOpen} onClick={() => { setSelected(String(context.id)); setModalOpen(true) }}>Manage donation</Button>
    </section>
    <ConfirmationDialog open={modalOpen} dialogRole="dialog" panelClassName={styles.modalPanel} title="Donation details" cancelLabel="Close" busy={busy} onCancel={() => { if (!pending.current) setModalOpen(false) }}>
    <div className={styles.card}>
    <label htmlFor={id}>Request coordination</label>
    <select disabled={busy} id={id} value={context.id} onChange={e => { setSelected(e.target.value); setFormOpen(false); setDate(''); setPhotos([]); setError(''); setFeedback('') }}>{contexts.map(c => <option key={c.id} value={c.id}>{c.required_blood_type} · {c.facility_name} · Request #{c.request_id}{Number(c.offer_cycle) !== Number(c.current_cycle) ? ' · Previous offer' : ''}</option>)}</select>
    <p><strong>{summary}</strong></p>
    {feedback && <p role="status" className={styles.feedback}>{feedback}</p>}
    <div className={styles.actions}>
      {!report && donor && <Button disabled={busy} aria-expanded={formOpen} onClick={() => { setFormOpen(!formOpen); setError(''); setFeedback('') }}>I donated</Button>}
      {!report && !donor && <div><Button variant="secondary" disabled={busy} onClick={() => signal('prompt_donor')}>Ask donor to report donation</Button><small className={styles.promptHelp}>Sends a reminder only. It does not confirm a donation.</small></div>}
      {report && context.report_in_context && !donor && report.status === 'PENDING' && !report.requester_response && report.workflow_state !== 'clarification' && <>
        <Button disabled={busy} onClick={() => acknowledge('received')}>Donation received</Button><Button variant="secondary" disabled={busy} onClick={() => acknowledge('did_not_donate')}>Did not donate</Button><Button variant="secondary" disabled={busy} onClick={() => acknowledge('not_sure')}>Not sure</Button>
      </>}
    </div>
    {formOpen && !report && <form className={styles.reportForm} onSubmit={submit} aria-busy={busy}>
      <DonationDateTimeFields value={date} onChange={setDate} /><DonationProofInput files={photos} onChange={setPhotos} disabled={busy} />
      {error && <p ref={errorRef} tabIndex={-1} role="alert" className={styles.error}>{error}</p>}
      <div className={styles.actions}><Button type="submit" isLoading={busy}>{busy ? 'Submitting…' : 'Submit donation report'}</Button><Button type="button" variant="secondary" disabled={busy} onClick={() => setFormOpen(false)}>Cancel</Button></div>
    </form>}
    {error && !formOpen && <p ref={errorRef} tabIndex={-1} role="alert" className={styles.error}>{error}</p>}
    <details><summary>Donation actions and details</summary><div className={styles.body}>
      <p>{context.quantity_units} unit(s) requested · {context.request_status.toLowerCase()}</p>
      {report && !context.report_in_context && <p>This donation report belongs to another offer cycle for this request. Open the canonical report below; actions here will not change that report.</p>}
      {report && !report.offer_context_id && <p>This report was submitted outside a recorded accepted-chat context. It remains linked to this request and donor match.</p>}
      <div className={styles.actions}>
        {(!report || (report.status === 'PENDING' && context.report_in_context)) && <Button variant="secondary" disabled={busy} onClick={() => signal('did_not_happen')}>Donation didn’t happen</Button>}
        <Button variant="secondary" disabled={busy || !context.active} onClick={() => signal('still_arranging')}>Still arranging</Button>
      </div>
      {report && <div className={styles.actions}><Button variant="secondary" to={`/donation-reports?report=${report.id}`}>{report.status === 'CONFIRMED' ? 'View report or request correction' : 'View report, proof and history'}</Button></div>}
    </div></details>
    </div>
    </ConfirmationDialog>
  </>
}
