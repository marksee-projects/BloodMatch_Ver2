import { useEffect, useRef, useState } from 'react'
import { loadDocumentPreview } from '../services/documentPreview'
import styles from './DocumentPreview.module.css'
import { documentLabel } from '../services/documentLabels'

export default function DocumentPreview({ document: file, onClose, owner = false }) {
  const dialogRef = useRef(null)
  const [url, setUrl] = useState(null)
  const [error, setError] = useState(null)
  const [zoom, setZoom] = useState(100)
  const [retry, setRetry] = useState(0)
  const title = file.doc_type === 'donation_proof' ? 'Private donation proof' : file.doc_type === 'national_id' ? documentLabel(file) : file.doc_type === 'donor_card' ? 'Blood donor card' : 'Parental consent'
  useEffect(() => {
    const previous = window.document.activeElement
    dialogRef.current.showModal()
    return () => { dialogRef.current?.close(); if (previous?.isConnected) previous.focus() }
  }, [])
  useEffect(() => {
    const controller = new AbortController()
    let active = true
    let objectUrl = null
    setUrl(null); setError(null); setZoom(100)
    loadDocumentPreview(file, controller.signal, fetch, owner).then(blob => {
      if (!active) return
      objectUrl = URL.createObjectURL(blob)
      setUrl(objectUrl)
    }).catch(err => { if (active && err.name !== 'AbortError') setError(err.message) })
    return () => { active = false; controller.abort(); if (objectUrl) URL.revokeObjectURL(objectUrl) }
  }, [file.id, file.mime_type, retry, owner])
  return <dialog ref={dialogRef} className={styles.dialog} aria-labelledby="document-preview-title" onCancel={event => { event.preventDefault(); onClose() }}>
    <header className={styles.header}><h2 id="document-preview-title">{title}</h2><button type="button" className="btn btn-secondary" onClick={onClose} autoFocus>Close preview</button></header>
    {!url && !error && <p role="status">Loading document…</p>}
    {error && <div role="alert"><p>{error}</p><button type="button" className="btn btn-secondary" onClick={() => setRetry(value => value + 1)}>Try again</button></div>}
    {url && !error && (file.mime_type === 'application/pdf' ? <>
      <iframe className={styles.pdf} title={`${title} PDF preview`} src={url} referrerPolicy="no-referrer" />
      <p className={styles.hint}>The PDF is displayed inside this preview. If it does not load, close the preview and try again.</p>
    </> : <>
      <div className={styles.zoom}><button type="button" className="btn btn-secondary" disabled={zoom <= 100} onClick={() => setZoom(value => value - 25)}>Zoom out</button><span>{zoom}%</span><button type="button" className="btn btn-secondary" disabled={zoom >= 300} onClick={() => setZoom(value => value + 25)}>Zoom in</button></div>
      <div className={styles.imageViewport}><img src={url} alt={`Submitted ${title}`} style={{ width: `${zoom}%` }} onError={() => setError('The image could not be displayed. Close the preview and try again.')} /></div>
    </>)}
  </dialog>
}
