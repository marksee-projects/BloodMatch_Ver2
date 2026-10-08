import { useEffect, useId, useRef } from 'react'
import { createPortal } from 'react-dom'
import { Button } from './ui/Button'
import styles from './ConfirmationDialog.module.css'

export default function ConfirmationDialog({ open, title, children, confirmLabel, cancelLabel = 'Keep editing', destructive = false, busy = false, confirmDisabled = false, error, onConfirm, onCancel }) {
  const panelRef = useRef(null)
  const titleId = useId()
  const descriptionId = useId()
  const busyRef = useRef(busy)
  const cancelHandlerRef = useRef(onCancel)
  busyRef.current = busy
  cancelHandlerRef.current = onCancel

  useEffect(() => {
    if (!open) return undefined
    const previousFocus = document.activeElement
    const backdrop = panelRef.current.parentElement
    const background = [...document.body.children].filter((element) => element !== backdrop)
      .map((element) => ({ element, inert: element.getAttribute('inert'), hidden: element.getAttribute('aria-hidden') }))
    background.forEach(({ element }) => { element.setAttribute('inert', ''); element.setAttribute('aria-hidden', 'true') })
    const focusableSelector = 'button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href], [tabindex="0"]'
    panelRef.current.querySelector(focusableSelector)?.focus()
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault()
        event.stopImmediatePropagation()
        if (!busyRef.current) cancelHandlerRef.current()
      }
      if (event.key !== 'Tab') return
      const controls = [...panelRef.current.querySelectorAll(focusableSelector)]
      if (!controls.length) { event.preventDefault(); panelRef.current.focus(); return }
      const first = controls[0]
      const last = controls.at(-1)
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    }
    document.addEventListener('keydown', onKeyDown, true)
    return () => {
      document.removeEventListener('keydown', onKeyDown, true)
      background.forEach(({ element, inert, hidden }) => {
        if (inert === null) element.removeAttribute('inert'); else element.setAttribute('inert', inert)
        if (hidden === null) element.removeAttribute('aria-hidden'); else element.setAttribute('aria-hidden', hidden)
      })
      // Restore after the underlying form has had its inert state removed.
      window.setTimeout(() => { if (previousFocus?.isConnected) previousFocus.focus() }, 0)
    }
  }, [open])

  useEffect(() => {
    if (open && busy) panelRef.current?.focus()
  }, [open, busy])

  if (!open) return null
  const dialog = (
    <div className={styles.backdrop}>
      <section ref={panelRef} className={styles.panel} role="alertdialog" aria-modal="true" aria-labelledby={titleId} aria-describedby={descriptionId} aria-busy={busy} tabIndex={-1}>
        <h2 id={titleId}>{title}</h2>
        <div id={descriptionId} className={styles.description}>{children}</div>
        {error && <p className="alert alert-error" role="alert">{error}</p>}
        <div className={styles.actions}>
          <Button type="button" variant="secondary" disabled={busy} onClick={onCancel}>{cancelLabel}</Button>
          <Button type="button" variant={destructive ? 'destructive' : 'primary'} disabled={confirmDisabled} isLoading={busy} onClick={onConfirm}>{confirmLabel}</Button>
        </div>
      </section>
    </div>
  )
  return typeof document === 'undefined' ? dialog : createPortal(dialog, document.body)
}
