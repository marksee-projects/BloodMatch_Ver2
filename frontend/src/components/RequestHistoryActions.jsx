import ConfirmationDialog from './ConfirmationDialog'
import { useState } from 'react'
import { api } from '../services/apiClient'
import { useNotify } from '../context/NotificationContext'
import { Button } from './ui/Button'
export default function RequestHistoryActions({ request, onSaved }) {
  const notify = useNotify()
  const [deadline, setDeadline] = useState('')
  const [reason, setReason] = useState('no_longer_needed')
  const [confirmClose, setConfirmClose] = useState(false)
  const [busy, setBusy] = useState(false)
  const run = async (action, body) => { if (busy) return; setBusy(true); try { await api.post(`/api/requests/${request.id}/${action}`, body); setConfirmClose(false); await onSaved(); notify({ severity: 'success', message: action === 'renew' ? 'Deadline saved.' : action === 'close' ? 'Request closed.' : 'Follow-up requested.' }) } catch (e) { notify({ severity: 'error', message: e.message }) } finally { setBusy(false) } }
  return <div style={{ display: 'grid', gap: 'var(--space-3)', marginBlock: 'var(--space-3)' }}>
    {request.closure_reason && <p>Closed: {request.closure_reason === 'need_met_elsewhere' ? 'Need met elsewhere' : 'No longer needed'}</p>}
    {request.renewed_from_id && <Button variant="secondary" to={`/requests/${request.renewed_from_id}/matches`}>View original request</Button>}
    {['OPEN', 'EXPIRED'].includes(request.status) && <form onSubmit={e => { e.preventDefault(); run('renew', { needed_datetime: new Date(deadline).toISOString().slice(0, 19).replace('T', ' ') }) }}><label htmlFor={`deadline-${request.id}`}>{request.status === 'EXPIRED' ? 'Still need blood? Renew with a future deadline' : 'Extend deadline'}</label><input id={`deadline-${request.id}`} type="datetime-local" required value={deadline} onChange={e => setDeadline(e.target.value)} /><Button type="submit" disabled={busy}>Save deadline</Button></form>}
    {request.status === 'OPEN' && <form onSubmit={e => { e.preventDefault(); setConfirmClose(true) }}><label htmlFor={`close-${request.id}`}>Close request</label><select id={`close-${request.id}`} value={reason} onChange={e => setReason(e.target.value)}><option value="no_longer_needed">No longer needed</option><option value="need_met_elsewhere">Need met elsewhere</option></select><Button type="submit" variant="secondary" disabled={busy}>Close request</Button></form>}
    <Button variant="secondary" disabled={busy} onClick={() => run('followup', {})}>Request donation follow-up</Button>
    <ConfirmationDialog open={confirmClose} title="Close this request?" confirmLabel="Close request" cancelLabel="Keep open" destructive busy={busy} onConfirm={() => run('close', { reason })} onCancel={() => setConfirmClose(false)}><p>This stops new offers without recording a verified donation.</p></ConfirmationDialog>
  </div>
}
