import { useEffect, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, CalendarBlank, Drop, Hospital, PencilSimple, Plus, Users, X } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import RequestFormPage from './RequestFormPage'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'
import { Button } from '../components/ui/Button'
import { Badge } from '../components/ui/Badge'
import styles from './RequestsPage.module.css'

const STATUSES = ['ALL', 'OPEN', 'FULFILLED', 'CANCELLED', 'EXPIRED']

function formatDate(value) {
  return new Date(value.replace(' ', 'T') + 'Z').toLocaleString(undefined, {
    month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
  })
}

export default function RequestsPage() {
  const location = useLocation()
  const [message, setMessage] = useState(null)
  const [errorAlert, setErrorAlert] = useState(null)
  const [filter, setFilter] = useState('ALL')
  const [isCreating, setIsCreating] = useState(Boolean(location.state?.openCreate))
  const [editingId, setEditingId] = useState(null)
  const [cancelTarget, setCancelTarget] = useState(null)
  const closeButtonRef = useRef(null)

  const { data: requests = [], isLoading, refetch } = useQuery({
    queryKey: ['my-requests'],
    queryFn: async () => {
      const data = await api.get('/api/my/requests')
      return data.requests || []
    }
  })

  const modalOpen = isCreating || Boolean(editingId)

  useEffect(() => {
    if (!modalOpen) return undefined
    closeButtonRef.current?.focus()
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        setIsCreating(false)
        setEditingId(null)
      }
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [modalOpen])

  const closeForm = () => {
    setIsCreating(false)
    setEditingId(null)
  }

  const handleSaved = async () => {
    const wasEditing = Boolean(editingId)
    closeForm()
    await refetch()
    setMessage(wasEditing ? 'Request details updated.' : 'Blood request created and matching started.')
  }

  const onCancel = async () => {
    if (!cancelTarget) return
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post(`/api/requests/${cancelTarget.id}/cancel`)
      setMessage('Blood request cancelled.')
      setCancelTarget(null)
      await refetch()
    } catch (err) {
      setErrorAlert(err.message)
      setCancelTarget(null)
    }
  }

  if (isLoading) {
    return <div className={styles.page}><LoadingSpinner text="Loading blood requests…" minHeight="22rem" /></div>
  }

  const filteredRequests = filter === 'ALL' ? requests : requests.filter((request) => request.status === filter)

  return (
    <div className={styles.page}>
      <header className={styles.pageHeader}>
        <div>
          <p className={styles.kicker}>Request center</p>
          <h1>My blood requests</h1>
          <p>Publish a request, track responses, and manage its progress in one place.</p>
        </div>
        <Button onClick={() => setIsCreating(true)}>
          <Plus size={18} weight="bold" aria-hidden="true" /> Create blood request
        </Button>
      </header>

      {message && <div className="alert alert-success" role="status">{message}</div>}
      {errorAlert && <div className="alert alert-error" role="alert">{errorAlert}</div>}

      <nav className={styles.filters} aria-label="Filter requests by status">
        {STATUSES.map((status) => {
          const count = status === 'ALL' ? requests.length : requests.filter((request) => request.status === status).length
          return (
            <button key={status} type="button" className={filter === status ? styles.filterActive : styles.filter} onClick={() => setFilter(status)} aria-pressed={filter === status}>
              {status === 'ALL' ? 'All' : status.charAt(0) + status.slice(1).toLowerCase()} <span>{count}</span>
            </button>
          )
        })}
      </nav>

      {filteredRequests.length === 0 ? (
        <section className={styles.emptyState}>
          <Drop size={32} weight="duotone" aria-hidden="true" />
          <h2>{filter === 'ALL' ? 'Create your first blood request' : `No ${filter.toLowerCase()} requests`}</h2>
          <p>{filter === 'ALL' ? 'Add the blood requirement, facility, and deadline to begin matching.' : 'Choose another status to review your request history.'}</p>
          {filter === 'ALL' && <Button onClick={() => setIsCreating(true)}>Create blood request</Button>}
        </section>
      ) : (
        <div className={styles.requestList}>
          {filteredRequests.map((request) => (
            <article key={request.id} className={styles.requestCard}>
              <div className={styles.bloodBlock} aria-label={`Blood type ${request.required_blood_type}`}>
                <span>{request.required_blood_type}</span>
                <small>{request.quantity_units} {request.quantity_units === 1 ? 'unit' : 'units'}</small>
              </div>
              <div className={styles.requestContent}>
                <div className={styles.requestHeading}>
                  <div>
                    <h2>{request.facility_name}</h2>
                    <p><CalendarBlank size={17} aria-hidden="true" /> Needed {formatDate(request.needed_datetime)}</p>
                  </div>
                  <div className={styles.badges}>
                    <Badge variant={request.urgency === 'critical' ? 'critical' : request.urgency === 'urgent' ? 'urgent' : 'neutral'}>{request.urgency}</Badge>
                    <Badge variant={request.status === 'FULFILLED' ? 'success' : request.status === 'OPEN' ? 'brand' : 'neutral'}>{request.status}</Badge>
                  </div>
                </div>
                <div className={styles.progressRow}>
                  <span><Users size={17} aria-hidden="true" /> {request.match_count ?? 0} potential {(request.match_count ?? 0) === 1 ? 'match' : 'matches'}</span>
                  <span>{request.response_count ?? 0} {(request.response_count ?? 0) === 1 ? 'response' : 'responses'}</span>
                  {request.location?.municipality_name && <span><Hospital size={17} aria-hidden="true" /> {request.location.municipality_name}</span>}
                </div>
                <div className={styles.actions}>
                  <Button to={`/requests/${request.id}/matches`} size="sm">View potential matches <ArrowRight size={16} aria-hidden="true" /></Button>
                  {request.status === 'OPEN' && (
                    <>
                      <Button variant="secondary" size="sm" onClick={() => setEditingId(request.id)}><PencilSimple size={16} aria-hidden="true" /> Edit details</Button>
                      <button type="button" className={styles.cancelButton} onClick={() => setCancelTarget(request)}>Cancel request</button>
                    </>
                  )}
                </div>
              </div>
            </article>
          ))}
        </div>
      )}

      {modalOpen && (
        <div className={styles.modalBackdrop} role="presentation">
          <section className={styles.modalPanel} role="dialog" aria-modal="true" aria-label={editingId ? 'Edit request details' : 'Create blood request'}>
            <button ref={closeButtonRef} type="button" className={styles.modalClose} onClick={closeForm} aria-label="Close request form"><X size={20} aria-hidden="true" /></button>
            <RequestFormPage id={editingId} onSuccess={handleSaved} onCancel={closeForm} />
          </section>
        </div>
      )}

      {cancelTarget && (
        <div className={styles.modalBackdrop} role="presentation">
          <section className={styles.confirmPanel} role="alertdialog" aria-modal="true" aria-labelledby="cancel-request-title">
            <h2 id="cancel-request-title">Cancel this blood request?</h2>
            <p>The request for {cancelTarget.required_blood_type} blood at {cancelTarget.facility_name} will stop accepting responses.</p>
            <div className={styles.confirmActions}>
              <Button variant="secondary" onClick={() => setCancelTarget(null)}>Keep request</Button>
              <Button variant="destructive" onClick={onCancel}>Cancel request</Button>
            </div>
          </section>
        </div>
      )}
    </div>
  )
}
