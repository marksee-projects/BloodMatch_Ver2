import { useCallback, useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { Link, useNavigate } from 'react-router-dom'
import { ArrowRight, Bell, Check, X, Drop } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { formatNotificationTime, notificationDeepLink } from '../services/notifications'

const PREVIEW_SIZE = 10

function formatBadge(count) {
  if (!count || count <= 0) return null
  return count > 99 ? '99+' : String(count)
}

/**
 * In-navbar notification center. Bell trigger + floating panel; no route
 * navigation. Reuses the existing notification API, read semantics, and
 * deep-link behavior. `variant` is `desktop` (anchored dropdown) or
 * `mobile` (bottom sheet inside the mobile navigation panel).
 */
export default function NotificationFlyout({ unread = 0, setUnread = () => {}, variant = 'desktop', onNavigate }) {
  const [open, setOpen] = useState(false)
  const [filter, setFilter] = useState('all')
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const rootRef = useRef(null)
  const sheetOutsideRef = useRef(null)
  const closeRef = useRef(null)
  const navigate = useNavigate()

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const qs = filter === 'unread' ? '&read=unread' : ''
      const d = await api.get(`/api/notifications?page=1&page_size=${PREVIEW_SIZE}${qs}`)
      setData(d)
    } catch (err) {
      setError(err.message || 'Could not load notifications.')
    } finally {
      setLoading(false)
    }
  }, [filter])

  useEffect(() => {
    if (open) {
      load()
      closeRef.current?.focus()
    } else {
      setData(null)
      setError(null)
    }
  }, [open, filter, load])

  useEffect(() => {
    if (!open) return undefined
    const onPointerDown = (e) => {
      const inTrigger = rootRef.current && rootRef.current.contains(e.target)
      const inSheet = sheetOutsideRef.current && sheetOutsideRef.current.contains(e.target)
      if (!inTrigger && !inSheet) setOpen(false)
    }
    const onKey = (e) => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKey)
    }
  }, [open ])

  const refreshUnread = useCallback(async () => {
    try {
      const d = await api.get('/api/notifications/unread-count')
      if (typeof d?.unread_count === 'number') setUnread(d.unread_count)
    } catch {
      // Unread badge keeps its last known value; list errors surface inline.
    }
  }, [setUnread])

  const openItem = async (n) => {
    const link = notificationDeepLink(n)
    if (!n.read_at) {
      try {
        const r = await api.post(`/api/notifications/${n.id}/read`)
        if (typeof r?.unread_count === 'number') {
          setUnread(r.unread_count)
        } else {
          await refreshUnread()
        }
        setData((d) =>
          d
            ? {
                ...d,
                notifications: d.notifications.map((x) =>
                  x.id === n.id ? { ...x, read_at: new Date().toISOString() } : x
                ),
              }
            : d
        )
      } catch (err) {
        setError(err.message || 'Could not mark notification as read.')
        return
      }
    }
    if (link) {
      setOpen(false)
      onNavigate?.()
      navigate(link)
    }
  }

  const markAll = async () => {
    try {
      await api.post('/api/notifications/read-all')
      await load()
      await refreshUnread()
    } catch (err) {
      setError(err.message || 'Could not mark notifications as read.')
    }
  }

  const badge = formatBadge(unread)
  const label = `Notifications${unread > 0 ? `, ${unread} unread` : ''}`

  const panel = open && (
    <div
      className={variant === 'mobile' ? 'notif-sheet' : 'notif-flyout'}
      role="dialog"
      aria-label="Notifications"
    >
      <div className="notif-flyout-header" style={{ padding: 'var(--space-3) var(--space-4)', borderBottom: 'none' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%', marginBottom: 'var(--space-3)' }}>
          <h2 className="notif-flyout-title" style={{ fontSize: 'var(--text-h3)', margin: 0, fontWeight: 700 }}>Notifications</h2>
          <div className="notif-flyout-actions">
            <button
              ref={closeRef}
              type="button"
              className="notif-close"
              onClick={() => setOpen(false)}
              aria-label="Close notifications"
            >
              <X size={16} weight="regular" aria-hidden="true" />
            </button>
          </div>
        </div>
        <div style={{ display: 'flex', gap: 'var(--space-2)' }}>
          <button type="button" className={`notif-filter-btn ${filter === 'all' ? 'active' : ''}`} style={{ minHeight: '44px', minWidth: '44px' }} onClick={() => setFilter('all')}>All</button>
          <button type="button" className={`notif-filter-btn ${filter === 'unread' ? 'active' : ''}`} style={{ minHeight: '44px', minWidth: '44px' }} onClick={() => setFilter('unread')}>Unread</button>
        </div>
      </div>

      <div className="notif-list">
        {loading && !data && <p className="muted" style={{ margin: 0 }}>Loading notifications…</p>}
        {error && !data && (
          <div>
            <div className="alert alert-error" role="alert">{error}</div>
            <button type="button" className="btn btn-secondary btn-sm" onClick={load} style={{ marginTop: 'var(--space-2)' }}>
              Retry
            </button>
          </div>
        )}
        {data && data.notifications.length === 0 && (
          <div className="notif-empty">
            <p style={{ margin: 0, fontWeight: 600 }}>No notifications</p>
            <p className="muted" style={{ margin: 0, fontSize: '0.8125rem' }}>Match alerts and account updates will appear here.</p>
          </div>
        )}
        {data && data.notifications.length > 0 && (
          <div className="notif-items">
            {data.notifications.map((n) => {
              const link = notificationDeepLink(n)
              const isUnread = !n.read_at
              return (
                <button
                  key={n.id}
                  type="button"
                  className={`notif-item-fb ${isUnread ? 'unread' : ''}`}
                  onClick={() => openItem(n)}
                  aria-label={`${n.title}. ${isUnread ? 'Unread' : 'Read'}.${link ? ' Opens details.' : ''}`}
                >
                  <div className="notif-item-avatar-wrapper">
                    <img src="/favicon-demolay.png?v=2" alt="BloodMatch" className="notif-avatar" />
                    <div className="notif-item-icon-badge">
                      <Drop size={12} weight="fill" color="#fff" />
                    </div>
                  </div>
                  <div className="notif-item-content">
                    <div className="notif-item-title-fb">{n.title}</div>
                    <div className="notif-item-body-fb">{n.body}</div>
                    <div className="notif-item-meta-fb">
                      {n.related_type === 'blood_request' && n.related_request_status && (
                        <span className="notif-item-status-fb">
                          Request Status: {n.related_request_status.charAt(0).toUpperCase() + n.related_request_status.slice(1).toLowerCase()}
                        </span>
                      )}
                      <span className="notif-item-time-fb">{formatNotificationTime(n.created_at)}</span>
                    </div>
                  </div>
                  {isUnread && <div className="notif-item-unread-dot" aria-hidden="true" />}
                </button>
              )
            })}
          </div>
        )}
        {error && data && <div className="alert alert-error" role="alert" style={{ marginTop: 'var(--space-2)' }}>{error}</div>}
      </div>

      <div className="notif-flyout-footer">
        <Link
          to="/notifications"
          className="notif-view-all"
          onClick={() => {
            setOpen(false)
            onNavigate?.()
          }}
        >
          View all notifications <ArrowRight size={14} weight="regular" aria-hidden="true" />
        </Link>
      </div>
    </div>
  )

  if (variant === 'mobile') {
    return (
      <div ref={rootRef}>
        <button
          type="button"
          className="mobile-link"
          onClick={() => setOpen((o) => !o)}
          aria-haspopup="dialog"
          aria-expanded={open}
          aria-label={label}
        >
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 'var(--space-2)' }}>
            <Bell size={16} weight="regular" aria-hidden="true" /> Notifications
          </span>
          {badge && <span className="nav-badge">{badge}</span>}
        </button>
        {/* Portaled: the sticky topbar's backdrop-filter would otherwise
            trap fixed positioning and hide the sheet. */}
        {panel &&
          createPortal(
            <div ref={sheetOutsideRef}>{panel}</div>,
            document.body
          )}
      </div>
    )
  }

  return (
    <div ref={rootRef} className="notif-wrap">
      <button
        type="button"
        className="notif-bell"
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-label={label}
        title={label}
      >
        <Bell size={17} weight="regular" aria-hidden="true" />
        {badge && <span className="nav-badge notif-badge">{badge}</span>}
      </button>
      {panel}
    </div>
  )
}
