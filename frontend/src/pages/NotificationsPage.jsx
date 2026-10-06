import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft, ArrowRight, Check, Drop } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { formatNotificationTime, notificationDeepLink as deepLink } from '../services/notifications'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'

export default function NotificationsPage() {
  const [data, setData] = useState(null)
  const [page, setPage] = useState(1)
  const [errorAlert, setErrorAlert] = useState(null)
  const [filterType, setFilterType] = useState('')
  const [filterRead, setFilterRead] = useState('')
  const pageSize = 15

  const load = useCallback(() => {
    const params = new URLSearchParams({ page, page_size: pageSize })
    if (filterType) params.set('type', filterType)
    if (filterRead) params.set('read', filterRead)

    return api
      .get(`/api/notifications?${params.toString()}`)
      .then(setData)
      .catch((err) => setErrorAlert(err.message))
  }, [page, filterType, filterRead])

  useEffect(() => {
    load()
  }, [load])

  const markRead = async (id) => {
    try {
      await api.post(`/api/notifications/${id}/read`)
      await load()
    } catch (err) {
      setErrorAlert(err.message)
    }
  }

  const markAll = async () => {
    try {
      await api.post('/api/notifications/read-all')
      await load()
    } catch (err) {
      setErrorAlert(err.message)
    }
  }

  if (errorAlert && !data) {
    return (
      <div className="container">
        <div className="alert alert-error" role="alert">{errorAlert}</div>
      </div>
    )
  }

  if (!data) {
    return (
      <div className="container">
        <div className="card text-center" style={{ padding: 'var(--space-8)' }}>
          <LoadingSpinner text="Loading notifications…" />
        </div>
      </div>
    )
  }

  const totalPages = Math.max(1, Math.ceil(data.total / pageSize))

  return (
    <div className="container">
      <header className="app-header">
        <div>
          <h1>Notifications</h1>
        </div>
        <button type="button" className="btn btn-secondary" onClick={markAll} style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
          <Check size={14} weight="regular" aria-hidden="true" /> Mark All as Read
        </button>
      </header>

      {errorAlert && <div className="alert alert-error" role="alert">{errorAlert}</div>}

      {/* Filter Bar */}
      <div className="card" style={{ marginBottom: 'var(--space-4)', padding: 'var(--space-3) var(--space-4)' }}>
        <div style={{ display: 'flex', gap: 'var(--space-4)', flexWrap: 'wrap', alignItems: 'center' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
            <label htmlFor="read-filter" style={{ fontSize: '0.8125rem', fontWeight: 600 }}>Status:</label>
            <select
              id="read-filter"
              value={filterRead}
              onChange={(e) => { setFilterRead(e.target.value); setPage(1) }}
              style={{ fontSize: '0.8125rem', padding: 'var(--space-1) var(--space-2)' }}
            >
              <option value="">All Notifications</option>
              <option value="unread">Unread Only</option>
              <option value="read">Read Only</option>
            </select>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-2)' }}>
            <label htmlFor="type-filter" style={{ fontSize: '0.8125rem', fontWeight: 600 }}>Category:</label>
            <select
              id="type-filter"
              value={filterType}
              onChange={(e) => { setFilterType(e.target.value); setPage(1) }}
              style={{ fontSize: '0.8125rem', padding: 'var(--space-1) var(--space-2)' }}
            >
              <option value="">All Categories</option>
              <option value="match.new">Match Alerts</option>
              <option value="verification.requested">Verification Requests</option>
              <option value="verification.decision">Verification Updates</option>
              <option value="verification.resubmitted">Verification Processing</option>
              <option value="donation.confirmed">Donation Confirmations</option>
              <option value="account.status_changed">Account Alerts</option>
            </select>
          </div>

          <span className="muted" style={{ fontSize: '0.8125rem', marginLeft: 'auto' }}>
            Total: {data.total}
          </span>
        </div>
      </div>

      {data.notifications.length === 0 ? (
        <div className="empty-state">
          <h3>No Notifications</h3>
          <p>You have no notifications matching the selected filter criteria.</p>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
          {data.notifications.map((n) => {
            const link = deepLink(n)
            return (
              <button
                key={n.id}
                type="button"
                className={`notif-item-fb ${!n.read_at ? 'unread' : ''}`}
                onClick={async () => {
                  if (!n.read_at) await markRead(n.id)
                  if (link) window.location.href = link
                }}
                style={{ backgroundColor: 'var(--color-surface)', border: '1px solid var(--color-border)', marginBottom: '8px' }}
                aria-label={`${n.title}. ${!n.read_at ? 'Unread' : 'Read'}.${link ? ' Opens details.' : ''}`}
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
                {!n.read_at && <div className="notif-item-unread-dot" aria-hidden="true" />}
              </button>
            )
          })}
        </div>
      )}

      {totalPages > 1 && (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 'var(--space-6)' }}>
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            disabled={page <= 1}
            onClick={() => setPage((p) => p - 1)}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}
          >
            <ArrowLeft size={14} weight="regular" aria-hidden="true" /> Previous Page
          </button>
          <span className="muted" style={{ fontSize: '0.875rem' }}>
            Page {page} of {totalPages}
          </span>
          <button
            type="button"
            className="btn btn-secondary btn-sm"
            disabled={page >= totalPages}
            onClick={() => setPage((p) => p + 1)}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.25rem' }}
          >
            Next Page <ArrowRight size={14} weight="regular" aria-hidden="true" />
          </button>
        </div>
      )}
    </div>
  )
}
