import { useCallback, useEffect, useRef, useState } from 'react'
import { X } from '@phosphor-icons/react'
import RequestFormPage from '../pages/RequestFormPage'
import ConfirmationDialog from './ConfirmationDialog'
import styles from '../pages/RequestsPage.module.css'

// Shared by the request center and Profile; the existing form owns all API logic.
export default function RequestFormModal({ open, id = null, onSuccess, onClose }) {
  const panelRef = useRef(null)
  const closeButtonRef = useRef(null)
  const [formState, setFormState] = useState({ dirty: false, submitting: false })
  const [confirmation, setConfirmation] = useState(null)
  const [confirmBusy, setConfirmBusy] = useState(false)
  const saveActionRef = useRef(null)
  const confirmationBusyRef = useRef(false)
  const requestClose = useCallback(() => {
    if (formState.submitting || confirmationBusyRef.current) return
    if (id && formState.dirty) setConfirmation('discard')
    else onClose()
  }, [formState, id, onClose])
  const closeHandlerRef = useRef(requestClose)
  closeHandlerRef.current = requestClose
  useEffect(() => {
    if (!open) { setConfirmation(null); setConfirmBusy(false); setFormState({ dirty: false, submitting: false }) }
  }, [open])
  const requestSaveConfirmation = (save) => { saveActionRef.current = save; setConfirmation('save') }
  const confirmAction = async () => {
    if (confirmationBusyRef.current) return
    if (confirmation === 'discard') { setConfirmation(null); onClose(); return }
    confirmationBusyRef.current = true
    setConfirmBusy(true)
    try { await saveActionRef.current() }
    finally { confirmationBusyRef.current = false; setConfirmBusy(false); setConfirmation(null) }
  }
  useEffect(() => {
    if (!open) return undefined
    const previousFocus = document.activeElement
    closeButtonRef.current?.focus()
    const onKeyDown = (event) => {
      if (!panelRef.current?.contains(event.target)) return
      if (event.key === 'Escape') closeHandlerRef.current()
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
  }, [open])

  if (!open) return null
  return (
    <>
    <div className={styles.modalBackdrop} role="presentation">
      <section ref={panelRef} className={styles.modalPanel} role="dialog" aria-modal="true" aria-label={id ? 'Edit request details' : 'Create blood request'}>
        <button ref={closeButtonRef} type="button" className={styles.modalClose} disabled={formState.submitting || confirmBusy} onClick={requestClose} aria-label="Close request form"><X size={20} aria-hidden="true" /></button>
        <RequestFormPage key={id || 'create'} id={id} onSuccess={onSuccess} onCancel={requestClose} onStateChange={setFormState} onConfirmSave={requestSaveConfirmation} />
      </section>
    </div>
    <ConfirmationDialog open={Boolean(confirmation)} title={confirmation === 'discard' ? 'Discard your changes?' : 'Save these changes?'}
      confirmLabel={confirmation === 'discard' ? 'Discard changes' : 'Save changes'} destructive={confirmation === 'discard'}
      busy={confirmBusy || formState.submitting} onConfirm={confirmAction} onCancel={() => setConfirmation(null)}>
      <p>{confirmation === 'discard' ? 'Your unsaved changes will be lost. Your existing request will stay unchanged.' : 'Your request will be updated with these details. Changes that affect matching will refresh its potential donors.'}</p>
    </ConfirmationDialog>
    </>
  )
}
