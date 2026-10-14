import { createContext, useCallback, useContext, useEffect, useId, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { Link } from 'react-router-dom'
import { CheckCircle, Info, WarningCircle, X } from '@phosphor-icons/react'
import styles from '../components/NotificationPopup.module.css'

// Isolated component renders can omit the app shell. The shell installs the provider.
const NotificationContext = createContext(() => {})
const labels = { success: 'Completed', info: 'Notice', warning: 'Action needed', error: 'Could not complete', emergency: 'Emergency blood request' }

function Popup({ notice, dismiss }) {
  const [hovered, setHovered] = useState(false)
  const [focused, setFocused] = useState(false)
  const paused = hovered || focused
  const remaining = useRef(6000)
  const persistent = ['warning', 'error', 'emergency'].includes(notice.severity)
  useEffect(() => {
    if (persistent || paused) return undefined
    const started = performance.now()
    const timer = window.setTimeout(() => dismiss(notice.id), remaining.current)
    return () => {
      window.clearTimeout(timer)
      remaining.current = Math.max(0, remaining.current - (performance.now() - started))
    }
  }, [notice.id, paused, persistent, dismiss])
  const Icon = notice.severity === 'success' ? CheckCircle : ['error', 'warning', 'emergency'].includes(notice.severity) ? WarningCircle : Info
  return <section className={`${styles.popup} ${styles[notice.severity] || ''}`}
    onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)}
    onFocus={() => setFocused(true)} onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setFocused(false) }}>
    <Icon size={24} weight="regular" aria-hidden="true" className={styles.icon} />
    <div className={styles.content} role={['error', 'emergency'].includes(notice.severity) ? 'alert' : 'status'} aria-atomic="true">
      <strong>{notice.title || labels[notice.severity]}</strong>
      <p>{notice.message}</p>
      {notice.action?.to && <Link className={styles.action} to={notice.action.to} onClick={() => dismiss(notice.id)}>{notice.action.label || 'View details'}</Link>}
    </div>
    <button type="button" className={styles.close} aria-label="Dismiss notification" onClick={() => dismiss(notice.id)}><X size={20} aria-hidden="true" /></button>
  </section>
}

export function NotificationProvider({ children }) {
  const [notices, setNotices] = useState([])
  const sequence = useRef(0)
  const dismiss = useCallback((id) => setNotices((items) => items.filter((item) => item.id !== id)), [])
  const notify = useCallback((input) => {
    if (!input?.message) return
    const id = ++sequence.current
    const notice = { severity: 'info', ...input, id }
    notice.dedupKey ||= `${notice.severity}:${notice.title || ''}:${notice.message}`
    setNotices((items) => {
      if (notice.dedupKey && items.some((item) => item.dedupKey === notice.dedupKey)) return items
      return [...items, notice]
    })
  }, [])
  return <NotificationContext.Provider value={notify}>
    {children}
    {typeof document !== 'undefined' && createPortal(<div className={styles.stack} role="region" aria-label="System notifications">
      {notices.slice(0, 3).map((notice) => <Popup key={notice.id} notice={notice} dismiss={dismiss} />)}
      {notices.length > 3 && <span className={styles.queued}>{notices.length - 3} more notifications queued</span>}
    </div>, document.body)}
  </NotificationContext.Provider>
}

export function useNotify() {
  const notify = useContext(NotificationContext)
  return notify
}

// Keep action state available to existing forms while publishing results once.
// Publishing in the handler lets a notice survive the handler's navigation.
export function useActionNoticeState(initial = null, severity = 'success') {
  const [value, setValue] = useState(initial)
  const current = useRef(initial)
  const source = useId()
  const notify = useNotify()
  const update = useCallback((next, options = {}) => {
    const resolved = typeof next === 'function' ? next(current.current) : next
    current.current = resolved
    setValue(resolved)
    if (resolved) notify({ severity, message: resolved, dedupKey: `${source}:${resolved}`, ...options })
  }, [notify, severity, source])
  return [value, update]
}
