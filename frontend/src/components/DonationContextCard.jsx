import { useId, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/apiClient'
import { donationReportForm, donationStatus } from '../services/donationCompletion'
import DonationDateTimeFields from './DonationDateTimeFields'
import DonationProofInput from './DonationProofInput'
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
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const { data, error: loadError, refetch } = useQuery({ queryKey: ['donation-chat-contexts', conversationId, user?.id], queryFn: () => api.get(`/api/chat/conversations/${conversationId}/donation-contexts`), enabled: Boolean(conversationId), refetchInterval: 5000, retry: false })
  const contexts = data?.contexts || []
  const requested = new URLSearchParams(route.search).get('request')
  const context = contexts.find(c => String(c.id) === selected) || contexts.find(c => String(c.request_id) === requested) || contexts[0]
  if (!context) return loadError ? <p role="alert">{loadError.message}</p> : null
  const report = context.report
  const donor = Number(context.donor_id) === Number(user.id)
  const run = async task => {
    if (busy) return
    setBusy(true); setError('')
    try {
      await task(); setFormOpen(false); setPhotos([]); setDate(''); await refetch(); await refresh()
      client.invalidateQueries({ queryKey: ['my-responses'] }); client.invalidateQueries({ queryKey: ['my-requests'] }); client.invalidateQueries({ queryKey: ['home-feed'] })
    } catch (e) { setError(e.message); await refetch() } finally { setBusy(false) }
  }
  const signal = action => run(() => api.post(`/api/donation-contexts/${context.id}/signal`, { action, expected_version: Number(report?.content_version || 0) }))
  const acknowledge = response => run(() => api.post(`/api/donation-completion/${report.id}/acknowledge`, { expected_version: Number(report.content_version), response }))
  return <section className={styles.card} aria-label="Request donation status">
    <label htmlFor={id}>Request coordination</label>
    <select id={id} value={context.id} onChange={e => { setSelected(e.target.value); setFormOpen(false); setDate(''); setPhotos([]); setError('') }}>{contexts.map(c => <option key={c.id} value={c.id}>{c.required_blood_type} · {c.facility_name} · Request #{c.request_id}{Number(c.offer_cycle) !== Number(c.current_cycle) ? ' · Previous offer' : ''}</option>)}</select>
    <p><strong>{report ? donationStatus(report) : context.active ? 'Coordination ongoing · Donation not reported' : 'Coordination inactive · Donation outcome unconfirmed'}</strong></p>
    <details><summary>Donation actions and details</summary><div className={styles.body}>
      <p>{context.quantity_units} unit(s) requested · {context.request_status.toLowerCase()}. Accepted offers and messages count as zero donated units.</p>
      {report && !context.report_in_context && <p>This donation report belongs to another offer cycle for this request. Open the canonical report below; actions here will not change that report.</p>}
      {report && !report.offer_context_id && <p>This report was submitted outside a recorded accepted-chat context. It remains linked to this request and donor match.</p>}
      {context.coordination[0] && <small>Latest update: {context.coordination[0].action.replaceAll('_', ' ')}. This does not confirm donation.</small>}
      {error && <p role="alert">{error}</p>}
      <div className={styles.actions}>
        {!report && donor && <Button disabled={busy} onClick={() => setFormOpen(!formOpen)}>I donated</Button>}
        {!report && !donor && <Button disabled={busy} onClick={() => signal('prompt_donor')}>Ask donor to submit details</Button>}
        {(!report || (report.status === 'PENDING' && context.report_in_context)) && <Button variant="secondary" disabled={busy} onClick={() => signal('did_not_happen')}>Donation didn’t happen</Button>}
        <Button variant="secondary" disabled={busy || !context.active} onClick={() => signal('still_arranging')}>Still arranging</Button>
      </div>
      {formOpen && !report && <form onSubmit={e => { e.preventDefault(); run(() => api.postForm('/api/donation-completion', donationReportForm(context.match_id, date, photos, context.id))) }}>
        <p>Submit only a donation that occurred. An unresolved report creates a report-specific donor hold.</p>
        <DonationDateTimeFields value={date} onChange={setDate} /><DonationProofInput files={photos} onChange={setPhotos} disabled={busy} />
        <Button type="submit" disabled={busy}>Submit donation report</Button>
      </form>}
      {report && context.report_in_context && !donor && report.status === 'PENDING' && !report.requester_response && report.workflow_state !== 'clarification' && <div className={styles.actions}>
        <Button disabled={busy} onClick={() => acknowledge('received')}>Donation received</Button><Button variant="secondary" disabled={busy} onClick={() => acknowledge('did_not_donate')}>Did not donate</Button><Button variant="secondary" disabled={busy} onClick={() => acknowledge('not_sure')}>Not sure</Button>
      </div>}
      {report && <div className={styles.actions}><Button variant="secondary" to={`/donation-reports?report=${report.id}`}>{report.status === 'CONFIRMED' ? 'View report or request correction' : 'View report, proof and history'}</Button></div>}
    </div></details>
  </section>
}
