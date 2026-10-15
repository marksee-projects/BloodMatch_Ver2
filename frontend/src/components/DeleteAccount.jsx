import { useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import ConfirmationDialog from './ConfirmationDialog'
export default function DeleteAccount({open = false, onClose}) {
  const {user, logout} = useAuth()
  const navigate = useNavigate()
  const [password,setPassword] = useState('')
  const [confirmation,setConfirmation] = useState('')
  const [busy,setBusy] = useState(false)
  const busyRef = useRef(false)
  const [error,setError] = useState(null)
  const close = () => { if (busyRef.current) return; setPassword(''); setConfirmation(''); setError(null); onClose?.() }
  const remove = async () => {
    if (busyRef.current || user?.role === 'admin' || !password || confirmation !== 'DELETE') return
    busyRef.current = true; setBusy(true); setError(null)
    try { await api.post('/api/account/delete', {password,confirmation}); await logout().catch(() => {}); navigate('/', {replace:true}) }
    catch (e) { setError(e.message) }
    finally { busyRef.current = false; setBusy(false) }
  }
  if (user?.role === 'admin') return <ConfirmationDialog open={open} title="Account deletion unavailable" confirmLabel="Understood" cancelLabel={null} onConfirm={close} onCancel={close}><p>Administrator responsibility must be transferred before this account can be deleted.</p></ConfirmationDialog>
  return <ConfirmationDialog open={open} title="Permanently delete your account?" confirmLabel="Delete account" cancelLabel="Keep account" destructive busy={busy} error={error} confirmDisabled={busy || !password || confirmation !== 'DELETE'} onConfirm={remove} onCancel={close}>
    <div style={{display:'grid',gap:'var(--space-4)'}}><p>You will be signed out and open requests/unresolved offers will close. Profile and contact erasure is scheduled within 30 days; necessary history follows the privacy notice.</p><div className="field"><label htmlFor="delete-password">Current password</label><input className="chat-control" id="delete-password" type="password" autoComplete="current-password" value={password} disabled={busy} onChange={e => setPassword(e.target.value)} /></div><div className="field"><label htmlFor="delete-confirmation">Type DELETE to confirm</label><input className="chat-control" id="delete-confirmation" value={confirmation} disabled={busy} autoComplete="off" onChange={e => setConfirmation(e.target.value)} /></div></div>
  </ConfirmationDialog>
}
