import { useId, useState } from 'react'
import styles from './DonationProofInput.module.css'
export default function DonationProofInput({ files = [], onChange, disabled = false }) {
  const id = useId()
  const [error, setError] = useState('')
  return <div className={styles.field}>
    <label htmlFor={id}>Upload donation proof <span>(optional)</span></label>
    <p id={`${id}-hint`}>Attach a donation receipt, certificate, or donor-card photo. Hide unrelated personal details. Photos support review; they do not confirm donation.</p>
    <input id={id} type="file" multiple accept="image/jpeg,image/png,image/webp" disabled={disabled} aria-describedby={`${id}-hint ${id}-limit`} onChange={e => {
      const chosen = Array.from(e.target.files || [])
      if (chosen.length > 3 || chosen.some(f => f.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(f.type))) { setError('Choose up to three JPG, PNG or WEBP photos, 5 MB each.'); e.target.value = ''; onChange([]); return }
      setError(''); onChange(chosen)
    }} />
    <small id={`${id}-limit`}>Up to 3 photos · 5 MB each · Shared privately with this report’s participants and authorized reviewer.</small>
    {files.length > 0 && <small>{files.length} {files.length === 1 ? 'photo selected' : 'photos selected'}</small>}
    {error && <p role="alert">{error}</p>}
  </div>
}
