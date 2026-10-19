import { useState } from 'react'
import { api } from '../services/apiClient'
import { useNotify } from '../context/NotificationContext'
import { Button } from './ui/Button'
import DonationProofInput from './DonationProofInput'
import DocumentPreview from './DocumentPreview'
import styles from './DonationContextCard.module.css'
export default function DonationProofPanel({ report, participant, canReview, onChanged }) {
  const notify = useNotify()
  const [open, setOpen] = useState(false)
  const [evidence, setEvidence] = useState([])
  const [corrections, setCorrections] = useState([])
  const [photos, setPhotos] = useState([])
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [preview, setPreview] = useState(null)
  const load = async () => {
    try {
      const [proof, cases] = await Promise.all([api.get(`/api/donation-completion/${report.id}/proof`), api.get(`/api/donation-completion/${report.id}/correction-requests`)])
      setEvidence(proof.evidence || []); setCorrections(cases.corrections || []); setError('')
    } catch (e) { setError(e.message) }
  }
  const run = async task => {
    if (busy) return
    setBusy(true); setError('')
    try { await task(); setPhotos([]); setReason(''); await load(); await onChanged?.(); notify({ severity: 'success', message: 'Private report information updated.' }) }
    catch (e) { setError(e.message) } finally { setBusy(false) }
  }
  return <section><Button variant="secondary" aria-expanded={open} onClick={() => { if (!open) load(); setOpen(!open) }}>{open ? 'Hide proof and correction requests' : 'Proof and correction requests'}</Button>
    {open && <div className={styles.card}>
      {error && <p role="alert">{error}</p>}
      <h4>Private supporting photos</h4><p>Photos support review and never automatically confirm a donation.</p>
      {evidence.length === 0 && <p>No proof photos uploaded.</p>}
      <div className={styles.actions}>{evidence.map(file => <Button key={file.id} variant="secondary" onClick={() => setPreview({ ...file, id: Number(file.id), doc_type: 'donation_proof' })}>View photo · version {file.content_version}{file.retired_version ? ' · previous' : ''}</Button>)}</div>
      {participant && report.status === 'PENDING' && <><DonationProofInput files={photos} onChange={setPhotos} disabled={busy} /><p>A new photo set preserves older evidence and creates a new version. Existing disputes remain in review; the original review clock continues.</p><Button disabled={busy || !photos.length} onClick={() => run(() => { const form = new FormData();form.set('expected_version', report.content_version);photos.forEach(f => form.append('proofs[]', f));return api.postForm(`/api/donation-completion/${report.id}/proof`, form) })}>Submit proof photos</Button></>}
      {corrections.map(c => <div key={c.id}><h4>{c.resolved_at ? 'Staff response recorded' : 'Correction request awaiting staff'}</h4><p>{c.reason}</p>{c.resolution && <p>{c.resolution}</p>}{canReview && !c.resolved_at && <Button disabled={busy || !reason.trim()} onClick={() => run(() => api.post(`/api/donation-completion/${report.id}/correction-response`, { expected_version: Number(report.content_version), correction_id: Number(c.id), basis: reason }))}>Record staff response without reversing donation</Button>}</div>)}
      {((participant && report.status === 'CONFIRMED') || (canReview && corrections.some(c => !c.resolved_at))) && <div className="field"><label htmlFor={`correction-${report.id}`}>{canReview ? 'Staff response basis' : 'Reason for requesting correction'}</label><textarea className="chat-control" id={`correction-${report.id}`} maxLength={500} rows={3} value={reason} onChange={e => setReason(e.target.value)} /><p>This request does not automatically reverse credit, fulfillment or restrictions.</p>{participant && <Button disabled={busy || !reason.trim()} onClick={() => run(() => api.post(`/api/donation-completion/${report.id}/correction-request`, { expected_version: Number(report.content_version), reason }))}>Request correction</Button>}</div>}
    </div>}
    {preview && <DocumentPreview document={preview} onClose={() => setPreview(null)} />}
  </section>
}
