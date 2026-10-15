import ConfirmationDialog from './ConfirmationDialog'
import { useRef, useState } from 'react'
import { api } from '../services/apiClient'
import { useNotify } from '../context/NotificationContext'
import { Button } from './ui/Button'
import styles from './RequestHistoryActions.module.css'
export default function RequestHistoryActions({ request, onSaved }) {
  const notify = useNotify()
  const [mode, setMode] = useState(null)
  const [date, setDate] = useState('')
  const [time, setTime] = useState('')
  const [reason, setReason] = useState('no_longer_needed')
  const [confirmClose, setConfirmClose] = useState(false)
  const [busy, setBusy] = useState(false)
  const busyRef = useRef(false)
  const expired = request.status === 'EXPIRED'
  const toggle = next => setMode(current => current === next ? null : next)
  const run = async (action, body) => {
    if (busyRef.current) return
    busyRef.current = true; setBusy(true)
    try {
      await api.post('/api/requests/' + request.id + '/' + action, body)
      setConfirmClose(false); setDate(''); setTime(''); setMode(null)
      await onSaved()
      notify({ severity: 'success', message: action === 'renew' ? (expired ? 'Request renewed.' : 'Deadline extended.') : 'Request closed.' })
    } catch (e) { notify({ severity: 'error', message: e.message }) }
    finally { busyRef.current = false; setBusy(false) }
  }
  const renew = e => {
    e.preventDefault()
    const deadline = new Date(date + 'T' + time)
    if (!date || !time || !Number.isFinite(deadline.getTime()) || deadline.getTime() <= Date.now()) { notify({severity: 'warning', message: 'Choose a future date and time.'}); return }
    run('renew', {needed_datetime: deadline.toISOString().slice(0,19).replace('T',' ')})
  }
  const renewalLabel = expired ? 'Renew request' : 'Extend deadline'
  const panelId = 'request-actions-' + request.id
  return <div className={styles.actions}>
    {request.closure_reason && <p className={styles.note}>Closed: {request.closure_reason === 'need_met_elsewhere' ? 'Need met elsewhere' : 'No longer needed'}</p>}
    <div className={styles.row}>
      <Button variant="secondary" to={'/requests/' + request.id + '/matches'}>View request</Button>
      {['OPEN','EXPIRED'].includes(request.status) && <Button variant="secondary" disabled={busy} aria-expanded={mode === 'renew'} aria-controls={mode === 'renew' ? panelId : undefined} onClick={() => toggle('renew')}>{renewalLabel}</Button>}
      {request.status === 'OPEN' && <Button variant="secondary" disabled={busy} aria-expanded={mode === 'close'} aria-controls={mode === 'close' ? panelId : undefined} onClick={() => toggle('close')}>Close request</Button>}
      {request.renewed_from_id && <Button variant="secondary" to={'/requests/' + request.renewed_from_id + '/matches'}>View original request</Button>}
    </div>
    {mode === 'renew' && <form id={panelId} className={styles.form} onSubmit={renew}>
      <div className={styles.fields}><div className="field"><label htmlFor={'date-' + request.id}>New date</label><input className="chat-control" id={'date-' + request.id} type="date" required disabled={busy} value={date} onChange={e => setDate(e.target.value)} /></div><div className="field"><label htmlFor={'time-' + request.id}>New time</label><input className="chat-control" id={'time-' + request.id} type="time" required disabled={busy} value={time} onChange={e => setTime(e.target.value)} /></div></div>
      <div className={styles.formActions}><Button type="submit" disabled={busy}>{renewalLabel}</Button><Button type="button" variant="secondary" disabled={busy} onClick={() => setMode(null)}>Cancel</Button></div>
    </form>}
    {mode === 'close' && <form id={panelId} className={styles.form} onSubmit={e => { e.preventDefault(); setConfirmClose(true) }}><div className="field"><label htmlFor={'close-' + request.id}>Reason for closing</label><select className="chat-control" id={'close-' + request.id} value={reason} disabled={busy} onChange={e => setReason(e.target.value)}><option value="no_longer_needed">No longer needed</option><option value="need_met_elsewhere">Need met elsewhere</option></select></div><div className={styles.formActions}><Button type="submit" variant="secondary" disabled={busy}>Continue</Button><Button type="button" variant="secondary" disabled={busy} onClick={() => setMode(null)}>Cancel</Button></div></form>}
    <ConfirmationDialog open={confirmClose} title="Close this request?" confirmLabel="Close request" cancelLabel="Keep open" destructive busy={busy} onConfirm={() => run('close', { reason })} onCancel={() => setConfirmClose(false)}><p>This stops new offers without recording a verified donation.</p></ConfirmationDialog>
  </div>
}
