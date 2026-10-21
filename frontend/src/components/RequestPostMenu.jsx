import { useEffect, useId, useRef, useState } from 'react'
import { DotsThree } from '@phosphor-icons/react'
import { Link } from 'react-router-dom'
import styles from './RequestPostMenu.module.css'

export default function RequestPostMenu({ request, onEdit, onCancel, onRenew }) {
  const [open, setOpen] = useState(false)
  const root = useRef(null)
  const trigger = useRef(null)
  const id = useId()
  const close = (restore = false) => { setOpen(false); if (restore) trigger.current?.focus() }
  useEffect(() => {
    if (!open) return
    root.current?.querySelector('[role="menuitem"]')?.focus()
    const outside = event => { if (!root.current?.contains(event.target)) close() }
    document.addEventListener('pointerdown', outside)
    return () => document.removeEventListener('pointerdown', outside)
  }, [open])
  const select = action => { close(true); action?.() }
  return <div ref={root} className={styles.root} onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget)) close() }} onKeyDown={event => {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(true) }
    if (!open && ['ArrowDown', 'ArrowUp'].includes(event.key)) { event.preventDefault(); setOpen(true); return }
    if (!open || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return
    event.preventDefault()
    const items = [...root.current.querySelectorAll('[role="menuitem"]')]
    const current = items.indexOf(document.activeElement)
    const index = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length
    items[index]?.focus()
  }}>
    <button ref={trigger} className={styles.trigger} type="button" aria-label="Request actions" aria-haspopup="menu" aria-expanded={open} aria-controls={open ? id : undefined} onClick={() => setOpen(value => !value)}><DotsThree size={24} weight="bold" aria-hidden="true" /></button>
    {open && <div id={id} className={styles.menu} role="menu" aria-label="Request actions">
      {request.status === 'OPEN' && <><button role="menuitem" type="button" onClick={() => select(onEdit)}>Edit request</button><button role="menuitem" type="button" className={styles.destructive} onClick={() => select(onCancel)}>Cancel request</button></>}
      {request.status === 'EXPIRED' && <button role="menuitem" type="button" onClick={() => select(onRenew)}>Renew request</button>}
      {!['OPEN', 'EXPIRED'].includes(request.status) && <Link role="menuitem" to={`/requests/${request.id}/matches`} onClick={() => close()}>View details</Link>}
    </div>}
  </div>
}
