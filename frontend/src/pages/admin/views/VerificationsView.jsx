import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../../../context/AuthContext'
import { liveQueryOptions } from '../../../services/liveQueries'
import { useActionNoticeState } from '../../../context/NotificationContext'
import { useEffect, useMemo, useRef, useState } from 'react'
import { ArrowLeft, ArrowRight, CheckCircle, FileText, ShieldCheck } from '@phosphor-icons/react'
import { useSearchParams } from 'react-router-dom'
import { api } from '../../../services/apiClient'
import { LoadingSpinner } from '../../../components/ui/LoadingSpinner'
import DocumentPreview from '../../../components/DocumentPreview'
import styles from './VerificationsView.module.css'

const PAGE_SIZE = 20

function formatDate(value) {
  if (!value) return 'Not available'
  const normalized = String(value).includes('T') ? String(value) : `${String(value).replace(' ', 'T')}Z`
  const date = new Date(normalized)
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString()
}

function formatMimeType(value) {
  const formats = {
    'application/pdf': 'PDF',
    'image/jpeg': 'JPEG',
    'image/png': 'PNG',
    'image/webp': 'WEBP',
  }
  return formats[value] || value || 'Unknown'
}

export default function VerificationsView() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { user } = useAuth()
  const client = useQueryClient()
  const [page, setPage] = useState(1)
  const [decision, setDecision] = useState('verified')
  const [acceptDonorCard, setAcceptDonorCard] = useState(false)
  const [reason, setReason] = useState('')
  const [message, setMessage] = useActionNoticeState(null, 'success')
  const [error, setError] = useActionNoticeState(null, 'error')
  const [submitting, setSubmitting] = useState(false)
  const [previewDocument, setPreviewDocument] = useState(null)
  const submittingRef = useRef(false)
  const rejectionReasonRef = useRef(null)

  useEffect(() => {
    if (decision === 'rejected') rejectionReasonRef.current?.focus()
  }, [decision])

  const queueQuery = useQuery({ ...liveQueryOptions, queryKey: ['admin-verifications', user?.id, page], enabled: user?.role === 'admin', queryFn: () => {
    const params = new URLSearchParams({ page: String(page), page_size: String(PAGE_SIZE) })
    return api.get('/api/admin/verifications?' + params)
  } })
  const queue = queueQuery.data
  const loading = queueQuery.isPending
  const loadQueue = () => client.invalidateQueries({ queryKey: ['admin-verifications', user?.id] })
  const requestedUserId = searchParams.get('user')
  const detailQuery = useQuery({ ...liveQueryOptions, queryKey: ['admin-verification-detail', user?.id, requestedUserId], enabled: user?.role === 'admin' && /^\d+$/.test(requestedUserId || ''), queryFn: () => api.get('/api/admin/verifications/' + requestedUserId) })
  const detail = requestedUserId ? detailQuery.data?.verification : null
  const loadingDetail = /^\d+$/.test(requestedUserId || '') && detailQuery.isPending
  useEffect(() => { if (queue && page > Math.max(1, Math.ceil(queue.total / PAGE_SIZE))) setPage(Math.max(1, Math.ceil(queue.total / PAGE_SIZE))) }, [queue, page])

  useEffect(() => {
    setPreviewDocument(null); setDecision('verified'); setAcceptDonorCard(false); setReason(''); setError(null)
  }, [requestedUserId])

  const showDetail = (userId) => {
    setMessage(null)
    setSearchParams({ user: String(userId) })
  }

  const closeDetail = () => {
    if (submittingRef.current) return
    setAcceptDonorCard(false)
    setSearchParams({}, { replace: true })
  }

  const submitDecision = async (event) => {
    event.preventDefault()
    if (!detail || submittingRef.current || loadingDetail || detail.user.verification_status !== 'pending') return
    if (decision === 'verified' && !(detail.documents || []).some(document => document.doc_type === 'national_id')) return
    if (decision === 'rejected' && !reason.trim()) {
      setError('Enter a rejection reason and instructions for the member.')
      rejectionReasonRef.current?.focus()
      return
    }
    submittingRef.current = true

    const memberName = detail.user.full_name
    const approved = decision === 'verified'
    setSubmitting(true)
    setError(null)
    setMessage(null)
    try {
      await api.post(`/api/admin/verifications/${detail.user.id}/decision`, {
        decision,
        reason: approved ? null : reason.trim(),
        accept_donor_card: approved && acceptDonorCard && Boolean(detail.user.blood_type)
          && (detail.documents || []).some(document => document.doc_type === 'donor_card'),
      })
      setMessage(
        approved
          ? `Account approved. ${memberName} has been notified of your decision.`
          : `Verification rejected. ${memberName} has been notified of your decision.`
      )
      setAcceptDonorCard(false)
      setSearchParams({}, { replace: true })
      await loadQueue()
      client.invalidateQueries({ queryKey: ['admin-dashboard'] })
      client.invalidateQueries({ queryKey: ['admin-verification-detail'] })
    } catch (err) {
      setError(err.message || 'Could not save the verification decision.')
      detailQuery.refetch()
    } finally {
      submittingRef.current = false
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
  const supplementalDocuments = (detail?.documents || []).filter(document => ['parental_consent','donor_card'].includes(document.doc_type))
  const canAcceptDonorCard = Boolean(detail?.user?.blood_type) && supplementalDocuments.some(document => document.doc_type === 'donor_card')
  const decisionDisabled = submitting || loadingDetail || detail?.user?.verification_status !== 'pending'

  if (loadingDetail && !detail && !detailQuery.error) {
    return (
      <div className={`card text-center ${styles.loadingCard}`}>
        <LoadingSpinner text="Loading verification request…" />
      </div>
    )
  }

  return (
    <div className={`container ${styles.page}`}>
      {(queueQuery.error || detailQuery.error) && <div className="alert alert-error" role="alert"><p>{(detailQuery.error || queueQuery.error).message}</p><button type="button" className="btn btn-secondary" onClick={() => { queueQuery.refetch(); if (requestedUserId) detailQuery.refetch() }}>Retry</button></div>}
      {!detail ? (
        <section className={`card ${styles.queueCard}`} aria-labelledby="verification-queue-heading">

          {error && <div className="alert alert-error" role="alert">{error}</div>}

          <header className={styles.queueToolbar}><h2 id="verification-queue-heading">Pending National ID reviews</h2></header>

          {loading ? (
            <div className={styles.loadingState}>
              <LoadingSpinner text="Loading verification requests…" />
            </div>
          ) : !queue ? <p>Verification queue unavailable. Retry to load current submissions.</p> : queue.queue.length === 0 ? (
            <div className="empty-state">
              <ShieldCheck size={32} aria-hidden="true" />
              <h3>No pending National IDs</h3>

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
                        <div className={styles.memberEmail}>{member.email}</div>
                      </td>
                      <td>{member.chapter_name || 'Unassigned'}</td>
                      <td>{member.national_id_count} file{member.national_id_count === 1 ? '' : 's'}</td>
                      <td>{formatDate(member.latest_national_id_uploaded_at)}</td>
                      <td>
                        <button type="button" className="btn btn-sm" onClick={() => showDetail(member.id)}>
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
            <div className={styles.pagination}>
              <button type="button" className="btn btn-secondary btn-sm" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Previous</button>
              <span>Page {page} of {totalPages}</span>
              <button type="button" className="btn btn-secondary btn-sm" disabled={page >= totalPages} onClick={() => setPage((value) => value + 1)}>Next</button>
            </div>
          )}
        </section>
      ) : (
        <section className={`card ${styles.reviewCard}`} aria-labelledby="verification-member-heading">
          {error && <div className="alert alert-error" role="alert">{error}</div>}

          <header className={styles.reviewHeader}>
            <div className={styles.applicantIdentity}>
              <div>
                <h2 id="verification-member-heading">{detail.user.full_name}</h2>
                <p>{detail.user.email}</p>
              </div>
            </div>
            <button type="button" className="btn btn-secondary btn-sm" onClick={closeDetail} disabled={submitting}>
              <ArrowLeft size={14} aria-hidden="true" /> Back to queue
            </button>
          </header>

          {detail.user.verification_status !== 'pending' && <p role="status" className="alert">This account is currently {detail.user.verification_status}. This notification may refer to an earlier submission.</p>}
          <dl className={styles.memberSummary}>
            <div>
              <dt>Chapter</dt>
              <dd>{detail.user.chapter_name || 'Unassigned'}</dd>
            </div>
            <div>
              <dt>Date of birth</dt>
              <dd>{detail.user.date_of_birth || 'Not provided'}</dd>
            </div>
            <div>
              <dt>Blood type</dt>
              <dd>{detail.user.blood_type || 'Not provided'}</dd>
            </div>
          </dl>

          <section className={styles.documentsSection} aria-labelledby="submitted-national-ids-heading">
            <div className={`${styles.sectionHeading} ${styles.centeredSectionHeading}`}>
              <h3 id="submitted-national-ids-heading">Submitted National ID</h3>
              <span>{nationalIds.length} file{nationalIds.length === 1 ? '' : 's'}</span>
            </div>

            {nationalIds.length === 0 ? (
              <div className="alert alert-error" role="alert">A National ID is required before this account can be approved.</div>
            ) : (
              <div className={styles.documentList}>
                {nationalIds.map((document, index) => (
                  <article className={styles.documentCard} key={document.id}>
                    <div className={styles.documentIcon} aria-hidden="true">
                      <FileText size={24} weight="duotone" />
                    </div>
                    <div className={styles.documentInfo}>
                      <div className={styles.documentTitle}>
                        <strong>National ID</strong>
                        {index === 0 && <span className="badge">Latest</span>}
                      </div>
                      <dl className={styles.documentMeta}>
                        <div><dt>Format</dt><dd>{formatMimeType(document.mime_type)}</dd></div>
                        <div><dt>Size</dt><dd>{Math.ceil(document.size_bytes / 1024)} KB</dd></div>
                        <div><dt>Uploaded</dt><dd>{formatDate(document.uploaded_at)}</dd></div>
                      </dl>
                    </div>
                    <button type="button" className="btn btn-secondary btn-sm" onClick={() => setPreviewDocument(document)}>
                      Inspect National ID
                    </button>
                  </article>
                ))}
              </div>
            )}
          </section>

          {supplementalDocuments.length > 0 && <section aria-labelledby="supplemental-documents-heading">
            <div className={styles.sectionHeading}><h3 id="supplemental-documents-heading">Supporting documents</h3></div>
            <div className={styles.documentList}>{supplementalDocuments.map(document => <article className={styles.documentCard} key={document.id}>
              <div className={styles.documentIcon} aria-hidden="true"><FileText size={24} weight="duotone" /></div>
              <div className={styles.documentInfo}>
                <strong>{document.doc_type === 'parental_consent' ? 'Parental consent' : 'Blood donor card'}</strong>
                <dl className={styles.documentMeta}>
                  <div><dt>Format</dt><dd>{formatMimeType(document.mime_type)}</dd></div>
                  <div><dt>Size</dt><dd>{Math.ceil(document.size_bytes / 1024)} KB</dd></div>
                  <div><dt>Uploaded</dt><dd>{formatDate(document.uploaded_at)}</dd></div>
                </dl>
              </div>
              <button type="button" className="btn btn-secondary btn-sm" onClick={() => setPreviewDocument(document)}>Inspect {document.doc_type === 'parental_consent' ? 'parental consent' : 'donor card'}</button>
            </article>)}</div>
          </section>}

          <form className={styles.decisionPanel} onSubmit={submitDecision}>
            <div className={`${styles.sectionHeading} ${styles.centeredSectionHeading}`}>
              <h3>Verification decision</h3>
            </div>

            <div className={styles.decisionFields}>
              {decision === 'verified' && canAcceptDonorCard && <div className="field">
                <label className={styles.donorCardOption} htmlFor="staff-accept-donor-card"><input id="staff-accept-donor-card" type="checkbox" checked={acceptDonorCard} disabled={decisionDisabled} onChange={event => setAcceptDonorCard(event.target.checked)} /> Accept the inspected donor card as administrative blood-type evidence</label>
                <small>{detail.blood_type_notice || 'Administrative verification is not medical confirmation.'}</small>
              </div>}
              {decision === 'rejected' && (
                <div className="field">
                  <label htmlFor="admin-verification-reason">Reason and instructions</label>
                  <textarea
                    ref={rejectionReasonRef}
                    id="admin-verification-reason"
                    value={reason}
                    disabled={decisionDisabled}
                    onChange={(event) => setReason(event.target.value)}
                    maxLength={500}
                    required
                    placeholder="Explain what needs to be corrected before the member submits another National ID."
                  />
                </div>
              )}
            </div>

            <div className="button-group">
              <button type="submit" className={decision === 'rejected' ? 'btn btn-danger' : 'btn'} disabled={decisionDisabled || (decision === 'verified' && nationalIds.length === 0)}>
                {submitting ? 'Saving decision…' : decision === 'verified' ? 'Approve account' : 'Submit rejection'}
              </button>
              {decision === 'verified' ? <button type="button" className="btn btn-secondary" disabled={decisionDisabled} onClick={() => { setDecision('rejected'); setAcceptDonorCard(false); setReason(''); setError(null) }}>Reject verification</button>
                : <button type="button" className="btn btn-secondary" disabled={submitting} onClick={() => { setDecision('verified'); setAcceptDonorCard(false); setReason(''); setError(null) }}>Cancel rejection</button>}
              <button type="button" className="btn btn-secondary" onClick={closeDetail} disabled={submitting}>Cancel</button>
            </div>
          </form>
          {previewDocument && <DocumentPreview key={`${detail.user.id}-${previewDocument.id}`} document={previewDocument} onClose={() => setPreviewDocument(null)} />}
        </section>
      )}
    </div>
  )
}
