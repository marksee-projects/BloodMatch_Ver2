import { useEffect, useId, useRef, useState } from 'react'
import { api } from '../services/apiClient'
import { useNotify } from '../context/NotificationContext'
import { Button } from './ui/Button'
import DonationProofInput from './DonationProofInput'
import DocumentPreview from './DocumentPreview'
import styles from './DonationContextCard.module.css'

export default function DonationProofPanel({ report, participant, canReview, onChanged, historyAction }) {
  const notify = useNotify()
  const panelId = useId()
  const [correctionOpen, setCorrectionOpen] = useState(false)
  const reasonRef = useRef(null)
  const pending = useRef(false)
  const [evidence, setEvidence] = useState([])
  const [corrections, setCorrections] = useState([])
  const [photos, setPhotos] = useState([])
  const [reason, setReason] = useState('')
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [preview, setPreview] = useState(null)
  useEffect(() => { if (correctionOpen) reasonRef.current?.focus() }, [correctionOpen])
  const load = async () => {
    setLoading(true)
    try {
      const [proof, cases] = await Promise.all([api.get('/api/donation-completion/' + report.id + '/proof'), api.get('/api/donation-completion/' + report.id + '/correction-requests')])
      setEvidence(proof.evidence || []); setCorrections(cases.corrections || []); setError('')
    } catch (e) { setError(e.message) } finally { setLoading(false) }
  }
  const run = async task => {
    if (pending.current) return
    pending.current = true; setBusy(true); setError('')
    try { await task(); setPhotos([]); setReason(''); setCorrectionOpen(false); await load(); await onChanged?.(); notify({ severity: 'success', message: 'Private report information updated.' }) }
    catch (e) { setError(e.message) } finally { pending.current = false; setBusy(false) }
  }
  useEffect(() => { load() }, [report.id, report.content_version, report.status, report.pending_correction_count])
  const canRequestCorrection = participant && report.status === 'CONFIRMED'
  const canRespond = canReview && corrections.some(c => !c.resolved_at)
  return <section className={styles.proofSection}>
    <div id={panelId} aria-busy={busy || loading} className={styles.proofSection}>
      {error && <div role="alert"><p>{error}</p><Button type="button" variant="secondary" disabled={busy || loading} onClick={load}>Retry</Button></div>}
      {(loading || evidence.length > 0 || (participant && report.status === 'PENDING')) && <section className={styles.proofGroup} aria-label="Supporting photos">
        <h4>Supporting photos</h4>
        {loading ? <p role="status">Loading proof and corrections...</p> : !evidence.length && <p>No proof photos uploaded.</p>}
        <div className={styles.actions}>{evidence.map((file, index) => <Button key={file.id} type="button" variant="secondary" onClick={() => setPreview({ ...file, id: Number(file.id), doc_type: 'donation_proof' })}>View photo {index + 1}{file.retired_version ? ' - Previous submission' : ''}</Button>)}</div>
        {participant && report.status === 'PENDING' && <>
          <DonationProofInput files={photos} onChange={setPhotos} disabled={busy} />
          <Button type="button" disabled={busy || !photos.length} onClick={() => run(() => { const form = new FormData(); form.set('expected_version', report.content_version); photos.forEach(f => form.append('proofs[]', f)); return api.postForm('/api/donation-completion/' + report.id + '/proof', form) })}>Submit proof photos</Button>
        </>}
      </section>}
      {corrections.length > 0 && <section className={styles.proofGroup} aria-label="Correction history"><h4>Correction requests</h4>{corrections.map(c => <article key={c.id} className={styles.proofGroup}>
        <strong>{c.resolved_at ? 'Staff response recorded' : 'Awaiting staff response'}</strong><p>{c.reason}</p>{c.resolution && <p>{c.resolution}</p>}
        {canReview && !c.resolved_at && <Button type="button" disabled={busy || !reason.trim()} onClick={() => run(() => api.post('/api/donation-completion/' + report.id + '/correction-response', { expected_version: Number(report.content_version), correction_id: Number(c.id), basis: reason }))}>Record staff response</Button>}
      </article>)}</section>}
      <div className={styles.reportActions} role="group" aria-label="Report actions">{canRequestCorrection && <Button type="button" variant="secondary" aria-expanded={correctionOpen} aria-controls={panelId + '-correction'} disabled={busy} onClick={() => setCorrectionOpen(!correctionOpen)}>{correctionOpen ? 'Cancel correction' : 'Request correction'}</Button>}{historyAction}</div>
      {((canRequestCorrection && correctionOpen) || canRespond) && <div id={panelId + '-correction'} className={styles.proofGroup}>
        <label htmlFor={panelId + '-reason'}>{canRespond ? 'Staff response basis' : 'Reason for requesting correction'}</label>
        <textarea ref={reasonRef} id={panelId + '-reason'} disabled={busy} maxLength={500} rows={3} value={reason} onChange={e => setReason(e.target.value)} aria-describedby={panelId + '-notice'} />
        <p id={panelId + '-notice'}>A correction request does not automatically reverse donation credit, fulfillment, or restrictions.</p>
        {canRequestCorrection && <Button type="button" isLoading={busy} disabled={busy || !reason.trim()} onClick={() => run(() => api.post('/api/donation-completion/' + report.id + '/correction-request', { expected_version: Number(report.content_version), reason }))}>Submit correction request</Button>}
      </div>}
    </div>
    {preview && <DocumentPreview document={preview} onClose={() => setPreview(null)} />}
  </section>
}
