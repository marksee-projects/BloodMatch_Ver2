import { useCallback, useEffect, useMemo, useState } from 'react'
import { ArrowLeft, ArrowRight, FileText, ShieldCheck } from '@phosphor-icons/react'
import { useSearchParams } from 'react-router-dom'
import { api } from '../../../services/apiClient'
import { LoadingSpinner } from '../../../components/ui/LoadingSpinner'

const PAGE_SIZE = 20

function formatDate(value) {
  if (!value) return 'Not available'
  const normalized = String(value).includes('T') ? String(value) : `${String(value).replace(' ', 'T')}Z`
  const date = new Date(normalized)
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString()
}

export default function VerificationsView() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [queue, setQueue] = useState(null)
  const [chapters, setChapters] = useState([])
  const [detail, setDetail] = useState(null)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [chapterId, setChapterId] = useState('')
  const [page, setPage] = useState(1)
  const [decision, setDecision] = useState('verified')
  const [reason, setReason] = useState('')
  const [message, setMessage] = useState(null)
  const [error, setError] = useState(null)
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)

  const loadQueue = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const params = new URLSearchParams({ page: String(page), page_size: String(PAGE_SIZE) })
      if (chapterId) params.set('chapter_id', chapterId)
      setQueue(await api.get(`/api/admin/verifications?${params.toString()}`))
    } catch (err) {
      setError(err.message || 'Could not load account verification requests.')
    } finally {
      setLoading(false)
    }
  }, [chapterId, page])

  useEffect(() => {
    loadQueue()
  }, [loadQueue])

  useEffect(() => {
    api.get('/api/chapters')
      .then((data) => setChapters(data?.chapters || []))
      .catch(() => setChapters([]))
  }, [])

  const openDetail = useCallback(async (userId, updateUrl = true) => {
    setLoadingDetail(true)
    setError(null)
    setMessage(null)
    try {
      const data = await api.get(`/api/admin/verifications/${userId}`)
      setDetail(data.verification)
      setDecision('verified')
      setReason('')
      if (updateUrl) setSearchParams({ user: String(userId) })
    } catch (err) {
      setError(err.message || 'Could not load this verification request.')
      setSearchParams({})
    } finally {
      setLoadingDetail(false)
    }
  }, [setSearchParams])

  const requestedUserId = searchParams.get('user')
  useEffect(() => {
    if (requestedUserId && String(detail?.user?.id || '') !== requestedUserId && !loadingDetail) {
      openDetail(requestedUserId, false)
    }
  }, [requestedUserId, detail?.user?.id, loadingDetail, openDetail])

  const closeDetail = () => {
    setDetail(null)
    setSearchParams({})
  }

  const submitDecision = async (event) => {
    event.preventDefault()
    if (!detail) return
    setSubmitting(true)
    setError(null)
    setMessage(null)
    try {
      await api.post(`/api/admin/verifications/${detail.user.id}/decision`, {
        decision,
        reason: decision === 'rejected' ? reason.trim() : null,
        accept_donor_card: false
      })
      setMessage(
        decision === 'verified'
          ? `${detail.user.full_name}'s account has been verified.`
          : `${detail.user.full_name}'s verification was rejected and the member was notified.`
      )
      closeDetail()
      await loadQueue()
    } catch (err) {
      setError(err.message || 'Could not save the verification decision.')
    } finally {
      setSubmitting(false)
    }
  }

  const nationalIds = useMemo(() => {
    if (!detail?.documents) return []
    return detail.documents
      .filter((document) => document.doc_type === 'national_id')
      .sort((a, b) => b.id - a.id)
  }, [detail])

  const totalPages = Math.max(1, Math.ceil((queue?.total || 0) / PAGE_SIZE))

  if (loadingDetail && !detail) {
    return (
      <div className="card text-center" style={{ padding: 'var(--space-8)' }}>
        <LoadingSpinner text="Loading verification request…" />
      </div>
    )
  }

  return (
    <div className="container" style={{ padding: 'var(--space-6) 0' }}>
      {message && <div className="alert alert-success" role="status">{message}</div>}
      {error && <div className="alert alert-error" role="alert">{error}</div>}

      {!detail ? (
        <>
          <div className="card" style={{ marginBottom: 'var(--space-4)' }}>
            <div className="field" style={{ maxWidth: '360px', margin: 0 }}>
              <label htmlFor="verification-chapter">Show requests from</label>
              <select
                id="verification-chapter"
                value={chapterId}
                onChange={(event) => {
                  setChapterId(event.target.value)
                  setPage(1)
                }}
              >
                <option value="">All chapters</option>
                {chapters.map((chapter) => (
                  <option key={chapter.id} value={chapter.id}>{chapter.name}</option>
                ))}
              </select>
            </div>
          </div>

          <section className="card">
            <div className="card-header">
              <div>
                <h2 style={{ margin: 0 }}>Pending National ID reviews</h2>
                <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{queue?.total || 0} account{queue?.total === 1 ? '' : 's'} awaiting review</span>
              </div>
            </div>

            {loading ? (
              <div className="text-center" style={{ padding: 'var(--space-8)' }}>
                <LoadingSpinner text="Loading verification requests…" />
              </div>
            ) : !queue || queue.queue.length === 0 ? (
              <div className="empty-state">
                <ShieldCheck size={32} aria-hidden="true" />
                <h3>No pending National IDs</h3>
                <p>{chapterId ? 'No members in this chapter are waiting for verification.' : 'No members are waiting for verification.'}</p>
              </div>
            ) : (
              <div className="table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Member</th>
                      <th>Chapter</th>
                      <th>National ID</th>
                      <th>Submitted</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {queue.queue.map((member) => (
                      <tr key={member.id}>
                        <td>
                          <strong>{member.full_name}</strong>
                          <div className="muted" style={{ fontSize: 'var(--text-xs)', overflowWrap: 'anywhere' }}>{member.email}</div>
                        </td>
                        <td>{member.chapter_name || 'Unassigned'}</td>
                        <td>{member.national_id_count} file{member.national_id_count === 1 ? '' : 's'}</td>
                        <td>{formatDate(member.latest_national_id_uploaded_at)}</td>
                        <td>
                          <button type="button" className="btn btn-sm" onClick={() => openDetail(member.id)}>
                            Review <ArrowRight size={14} aria-hidden="true" />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {totalPages > 1 && (
              <div className="button-group" style={{ justifyContent: 'space-between', marginTop: 'var(--space-4)' }}>
                <button type="button" className="btn btn-secondary btn-sm" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</button>
                <span className="muted" style={{ alignSelf: 'center' }}>Page {page} of {totalPages}</span>
                <button type="button" className="btn btn-secondary btn-sm" disabled={page >= totalPages} onClick={() => setPage((value) => value + 1)}>Next</button>
              </div>
            )}
          </section>
        </>
      ) : (
        <section className="card">
          <div className="card-header">
            <div>
              <h2 style={{ margin: 0 }}>{detail.user.full_name}</h2>
              <p className="muted" style={{ margin: 'var(--space-1) 0 0', overflowWrap: 'anywhere' }}>{detail.user.email}</p>
            </div>
            <button type="button" className="btn btn-secondary btn-sm" onClick={closeDetail}>
              <ArrowLeft size={14} aria-hidden="true" /> Back to queue
            </button>
          </div>

          <div className="grid-3" style={{ marginBottom: 'var(--space-5)' }}>
            <div><span className="metric-label">Chapter</span><p style={{ margin: 0, fontWeight: 600 }}>{detail.user.chapter_name || 'Unassigned'}</p></div>
            <div><span className="metric-label">Date of birth</span><p style={{ margin: 0, fontWeight: 600 }}>{detail.user.date_of_birth || 'Not provided'}</p></div>
            <div><span className="metric-label">Blood type</span><p style={{ margin: 0, fontWeight: 600 }}>{detail.user.blood_type || 'Not provided'}</p></div>
          </div>

          <h3>Submitted National IDs</h3>
          {nationalIds.length === 0 ? (
            <div className="alert alert-error" role="alert">A National ID is required before this account can be approved.</div>
          ) : (
            <div className="table-container" style={{ marginBottom: 'var(--space-6)' }}>
              <table>
                <thead><tr><th>File</th><th>Format</th><th>Size</th><th>Uploaded</th><th>Action</th></tr></thead>
                <tbody>
                  {nationalIds.map((document, index) => (
                    <tr key={document.id}>
                      <td><FileText size={16} aria-hidden="true" /> National ID {index === 0 && <span className="badge">Latest</span>}</td>
                      <td>{document.mime_type}</td>
                      <td>{Math.ceil(document.size_bytes / 1024)} KB</td>
                      <td>{formatDate(document.uploaded_at)}</td>
                      <td><a className="btn btn-secondary btn-sm" href={`/api/admin/documents/${document.id}/file`} target="_blank" rel="noreferrer">Inspect file</a></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <form className="form" onSubmit={submitDecision} style={{ borderTop: '1px solid var(--color-border)', paddingTop: 'var(--space-4)' }}>
            <h3>Verification decision</h3>
            <div className="field">
              <label htmlFor="admin-verification-decision">Decision</label>
              <select id="admin-verification-decision" value={decision} onChange={(event) => setDecision(event.target.value)}>
                <option value="verified">Approve account</option>
                <option value="rejected">Reject verification</option>
              </select>
            </div>
            {decision === 'rejected' && (
              <div className="field">
                <label htmlFor="admin-verification-reason">Reason and instructions</label>
                <textarea
                  id="admin-verification-reason"
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                  maxLength={500}
                  required
                  placeholder="Explain what was unclear or invalid and what the member should upload next."
                />
              </div>
            )}
            <p className="muted" style={{ fontSize: 'var(--text-sm)' }}>
              The member will receive this decision through the notification bell and their registered email address.
            </p>
            <div className="button-group">
              <button type="submit" className={decision === 'rejected' ? 'btn btn-danger' : 'btn'} disabled={submitting || nationalIds.length === 0}>
                {submitting ? 'Saving decision…' : decision === 'verified' ? 'Approve account' : 'Reject verification'}
              </button>
              <button type="button" className="btn btn-secondary" onClick={closeDetail} disabled={submitting}>Cancel</button>
            </div>
          </form>
        </section>
      )}
    </div>
  )
}
