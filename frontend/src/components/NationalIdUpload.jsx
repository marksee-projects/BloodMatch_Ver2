import { useEffect, useState } from 'react'
import { FilePdf, UploadSimple, X } from '@phosphor-icons/react'
import styles from '../pages/RegisterPage.module.css'

export default function NationalIdUpload({ side, file, onChange }) {
  const [error, setError] = useState('')
  const [preview, setPreview] = useState(null)
  useEffect(() => {
    const url = file?.type.startsWith('image/') ? URL.createObjectURL(file) : null
    setPreview(url)
    return () => { if (url) URL.revokeObjectURL(url) }
  }, [file])
  const choose = selected => {
    if (!selected) return
    const message = selected.size > 5 * 1024 * 1024 ? 'Choose a file no larger than 5 MB.'
      : !selected.size ? 'Choose a non-empty file.'
        : !['image/jpeg', 'image/png', 'image/webp', 'application/pdf'].includes(selected.type) ? 'Choose a JPG, PNG, WEBP or PDF file.' : ''
    setError(message)
    if (!message) onChange(selected)
    return !message
  }
  const id = `national_id_${side}`
  return <div className="field">
    <label htmlFor={id}>National ID — {side}</label>
    <div className={styles.dropzone} onDragOver={event => event.preventDefault()} onDrop={event => { event.preventDefault(); choose(event.dataTransfer.files?.[0]) }}>
      {preview ? <img src={preview} alt={`Selected National ID ${side}`} style={{ maxWidth: '100%', maxHeight: '160px', objectFit: 'contain' }} /> : file ? <FilePdf size={28} aria-hidden="true" /> : <UploadSimple size={28} aria-hidden="true" />}
      <input id={id} type="file" accept="image/jpeg,image/png,image/webp,application/pdf" aria-describedby={`${id}_hint${error ? ` ${id}_error` : ''}`} aria-invalid={Boolean(error)} onChange={event => { if (!choose(event.target.files?.[0])) event.target.value = '' }} />
      {file && <><span style={{ overflowWrap: 'anywhere' }}>{file.name}</span><button type="button" className="btn btn-secondary btn-sm" aria-label={`Remove National ID ${side}`} onClick={() => { onChange(null); setError(''); document.getElementById(id).value = '' }}><X size={16} aria-hidden="true" /> Remove</button></>}
      <small id={`${id}_hint`}>Choose or drop a JPG, PNG, WEBP or PDF, up to 5 MB.</small>
    </div>
    {error && <small id={`${id}_error`} className="field-error" role="alert">{error}</small>}
  </div>
}
