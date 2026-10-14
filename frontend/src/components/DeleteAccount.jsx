import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import { Button } from './ui/Button'
import ConfirmationDialog from './ConfirmationDialog'
export default function DeleteAccount() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const close = () => { if (!busy) { setOpen(false); setPassword(''); setConfirmation(''); setError(null) } }
  const remove = async () => {
    if (busy) return
    setBusy(true); setError(null)
    try { await api.post('/api/account/delete', { password, confirmation }); await logout().catch(() => {}); navigate('/', { replace: true }) }
    catch (e) { setError(e.message) }
    finally { setBusy(false) }
  }
  return <section className="card"><h2>Account settings</h2><Button variant="destructive" disabled={user?.role === 'admin'} onClick={() => setOpen(true)}>Delete account</Button>
    {user?.role === 'admin' && <p>Administrator responsibility must be transferred first.</p>}
    <ConfirmationDialog open={open} title="Permanently delete your account?" confirmLabel="Delete account" cancelLabel="Keep account" destructive busy={busy} error={error} confirmDisabled={busy || !password || confirmation !== 'DELETE'} onConfirm={remove} onCancel={close}>
      <p>You will be signed out immediately and cannot sign back in. Open requests and unresolved offers will close. Profile/contact erasure is scheduled within 30 days; necessary evidence and history follow the privacy notice.</p>
      <div className="field"><label htmlFor="delete-password">Current password</label><input id="delete-password" type="password" autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} disabled={busy} /></div>
      <div className="field"><label htmlFor="delete-confirmation">Type DELETE to confirm</label><input id="delete-confirmation" value={confirmation} onChange={e => setConfirmation(e.target.value)} disabled={busy} /></div>
    </ConfirmationDialog>
  </section>
}
