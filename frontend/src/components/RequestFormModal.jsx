import { useEffect, useRef } from 'react'
import { X } from '@phosphor-icons/react'
import RequestFormPage from '../pages/RequestFormPage'
import styles from '../pages/RequestsPage.module.css'

// Shared by the request center and Profile; the existing form owns all API logic.
export default function RequestFormModal({ open, id = null, onSuccess, onClose }) {
  const panelRef = useRef(null)
  const closeButtonRef = useRef(null)
  useEffect(() => {
    if (!open) return undefined
    const previousFocus = document.activeElement
    closeButtonRef.current?.focus()
    const onKeyDown = (event) => {
      if (event.key === 'Escape') onClose()
      if (event.key !== 'Tab') return
      const controls = [...(panelRef.current?.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href], [tabindex="0"]') || [])]
        .filter((element) => element.getClientRects().length > 0)
      const first = controls[0]
      const last = controls.at(-1)
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
    }
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('keydown', onKeyDown)
      if (previousFocus?.isConnected) previousFocus.focus()
    }
  }, [open, onClose])

  if (!open) return null
  return (
    <div className={styles.modalBackdrop} role="presentation">
      <section ref={panelRef} className={styles.modalPanel} role="dialog" aria-modal="true" aria-label={id ? 'Edit request details' : 'Create blood request'}>
        <button ref={closeButtonRef} type="button" className={styles.modalClose} onClick={onClose} aria-label="Close request form"><X size={20} aria-hidden="true" /></button>
        <RequestFormPage key={id || 'create'} id={id} onSuccess={onSuccess} onCancel={onClose} />
      </section>
    </div>
  )
}
