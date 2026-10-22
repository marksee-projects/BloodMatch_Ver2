import { useActionNoticeState } from '../context/NotificationContext'
import { useEffect, useRef, useState } from 'react'
import { Link, Navigate, useLocation, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { liveQueryOptions } from '../services/liveQueries'
import { ArrowLeft, CalendarBlank, Check, CheckCircle, Drop, MapPin, User, X, ChatCircleDots } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { donationReportForm, donationTimeToUtc } from '../services/donationCompletion'
import DonationDateTimeFields from '../components/DonationDateTimeFields'
import DonationProofInput from '../components/DonationProofInput'
import OfferAcceptancePanel from '../components/OfferAcceptancePanel'
import DonationOfferStatus, { DonationReportButton } from '../components/DonationOfferStatus'
import { useAuth } from '../context/AuthContext'
import { useChat } from '../context/ChatContext'
import { useRequestCreation } from '../context/RequestCreationContext'
import EmailVerificationDialog from '../components/EmailVerificationDialog'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'
import { Button } from '../components/ui/Button'
import { Badge } from '../components/ui/Badge'
import styles from './MatchesPage.module.css'

function formatDate(value) {
  if (!value) return 'Not provided'
  return new Date(value.replace(' ', 'T') + 'Z').toLocaleString('en-US', {
    timeZone: 'Asia/Manila',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true
  })
}

function eligibilityMessage(user) {
  if (user?.pending_report_hold) return 'A donation report hold is active. Open Donation reports to resolve the claim before making another offer.'
  if (user?.verification_status !== 'verified') return 'Your blood type can support this request. Complete account verification before offering to donate.'
  if (!user?.donor_enrolled) return 'Your blood type can support this request. Enroll as a donor before offering to help.'
  if (user?.availability !== 'available') return 'Your blood type can support this request. Set your donor status to available to join its eligible matches.'
  return 'Your blood type can support this request, but your donor eligibility has not been added to this request yet. Review your donor details or ask an officer to refresh the matches.'
}

export default function MatchesPage() {
  const queryClient = useQueryClient()
  const { id } = useParams()
  const navigate = useNavigate()
  const location = useLocation()
  const openCreateRequest = useRequestCreation()
  const { user, refresh } = useAuth()
  const { openChatWithUser, canChat, openTabs } = useChat()
  const [verifyDialogOpen, setVerifyDialogOpen] = useState(false)
  const [errorAlert, setErrorAlert] = useActionNoticeState(null, 'error')
  const [message, setMessage] = useActionNoticeState(null, 'success')
  const [confirmation, setConfirmation] = useState(null)
  const [actionLoading, setActionLoading] = useState(false)
  const responseBusyRef = useRef(false)
  const [reportingMatchId, setReportingMatchId] = useState(null)
  const [occurredAt, setOccurredAt] = useState('')
  const [proofPhotos, setProofPhotos] = useState([])
  const [submittingReport, setSubmittingReport] = useState(false)
  const [reportError, setReportError] = useState('')
  const reportBusyRef = useRef(false)

  const { data: pageData, error: matchError, refetch: load } = useQuery({
    ...liveQueryOptions,
    queryKey: ['request-matches', id, user?.id],
    staleTime: 0,
    queryFn: () => api.get(`/api/requests/${id}/matches`),
    enabled: Boolean(id && user?.id),
    refetchInterval: query => reportingMatchId || actionLoading ? false : query.state.data?.viewer_mode === 'requester' ? 5000 : 10000
  })
  const { data: myRequests = [], isLoading: requestsLoading, error: requestsError, refetch: reloadRequests } = useQuery({
    ...liveQueryOptions,
    queryKey: ['my-requests', user?.id],
    queryFn: async () => (await api.get('/api/my/requests')).requests || [],
    enabled: Boolean(user?.id) && (!id || pageData?.viewer_mode === 'requester')
  })
  useEffect(() => {
    setErrorAlert(null)
    setMessage(null)
    setConfirmation(null)
    setReportingMatchId(null)
    setOccurredAt('')
    setProofPhotos([])
    setReportError('')
  }, [id])

  const matches = pageData?.matches || []
  const request = pageData?.request
  const viewerMode = pageData?.viewer_mode
  const ownMatch = matches.find((match) => match.is_current_user)
  const isRequesterView = viewerMode === 'requester'
  const isOwnRequest = request && String(request.requester_id) === String(user?.id)
  const hasActiveResponse = ['RESPONDED', 'COMPLETED'].includes(ownMatch?.status)
  const canRespond = !isRequesterView && request?.can_respond === true && !hasActiveResponse

  const respond = async () => {
    if (!canRespond || responseBusyRef.current) return
    responseBusyRef.current = true
    setActionLoading(true)
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post(`/api/requests/${id}/respond`)
      setConfirmation(null)
      setMessage('Your offer to help has been sent to the requester.')
      await load()
    } catch (error) {
      setConfirmation(null)
      if (error.status === 403 && ['EMAIL_UNVERIFIED', 'email_unverified'].includes(error.code)) setVerifyDialogOpen(true)
      else setErrorAlert(error.message)
      await load()
    } finally {
      responseBusyRef.current = false
      setActionLoading(false)
    }
  }

  const withdraw = async () => {
    if (!ownMatch) return
    setActionLoading(true)
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post(`/api/matches/${ownMatch.match_id}/withdraw`)
      setConfirmation(null)
      setMessage('Your offer to help has been cancelled. You can offer again while the request remains open.')
      await load()
    } catch (error) {
      setConfirmation(null)
      setErrorAlert(error.message)
    } finally {
      setActionLoading(false)
    }
  }

  const onSubmitReport = async (event) => {
    event.preventDefault()
    if (!reportingMatchId || reportBusyRef.current) return
    reportBusyRef.current = true
    setSubmittingReport(true)
    setMessage(null)
    setErrorAlert(null)
    try {
      setReportError('')
      if (new Date(donationTimeToUtc(occurredAt)).getTime() > Date.now()) throw new Error('Choose a donation time that has already passed. Future donations cannot be reported.')
      const result = await api.postForm('/api/donation-reports', donationReportForm(reportingMatchId, occurredAt, proofPhotos))
      if (result?.report?.id) queryClient.setQueriesData({ queryKey: ['donation-offer-status', reportingMatchId] }, current => current ? { ...current, report_id: result.report.id } : current)
      await queryClient.invalidateQueries({ queryKey: ['donation-offer-status'] })
      setMessage('Donation report sent to the requester for acknowledgment. Your donor offers are held while it is unresolved.')
      setReportingMatchId(null)
      setOccurredAt('')
      setProofPhotos([])
      await refresh()
      await load()
    } catch (error) {
      setReportError(error.message)
    } finally {
      reportBusyRef.current = false
      setSubmittingReport(false)
    }
  }

  const activeRequests = myRequests.filter((item) => item.status === 'OPEN')
  const selectorRequests = request && !activeRequests.some((item) => String(item.id) === String(id))
    ? [request, ...activeRequests]
    : activeRequests

  if (!id) {
    if (requestsLoading) return <div className={styles.container}><LoadingSpinner text="Loading active requests…" minHeight="24rem" /></div>
    if (requestsError) return <div className={styles.container}><div className="alert alert-error" role="alert">{requestsError.message}</div><Button variant="secondary" onClick={() => reloadRequests()}>Try again</Button></div>
    if (activeRequests.length) return <Navigate to={`/requests/${activeRequests[0].id}/matches`} replace />
    return (
      <div className={styles.container}>
        <h1 className={styles.matchesTitle}>Matches</h1>
        <section className={styles.emptyState}>
          <h2>You don't have an active blood request yet.</h2>
          <div className={styles.emptyActions}><Button onClick={openCreateRequest}>Create request</Button></div>
        </section>
      </div>
    )
  }

  if ((errorAlert || matchError) && !pageData) {
    return <div className={styles.container}><div className="alert alert-error" role="alert">{errorAlert || matchError.message}</div><Button to="/" variant="secondary"><ArrowLeft size={16} /> Back</Button></div>
  }
  if (!pageData || !request) return <div className={styles.container}><LoadingSpinner text="Loading request…" minHeight="24rem" /></div>

  const urgencyVariant = request.urgency === 'emergency' ? 'emergency' : request.urgency === 'urgent' ? 'urgent' : 'neutral'

  return (
    <div className={styles.container} data-request-workspace="true" data-chat-count={openTabs.length}>
      {isRequesterView ? (
        <header className={styles.matchesHeader}>
          <h1 className={styles.matchesTitle}>Matches</h1>
          {isOwnRequest && selectorRequests.length > 1 && <div className={styles.requestSelector}>
            <label htmlFor="matches-request">Choose a blood request</label>
            <select id="matches-request" value={id} onChange={(event) => navigate(`/requests/${event.target.value}/matches`)}>
              {selectorRequests.map((item) => (
                <option key={item.id} value={item.id} title={`${item.required_blood_type} • ${item.facility_name} • ${item.urgency}`}>
                  {item.required_blood_type} • {item.facility_name?.length > 36 ? `${item.facility_name.slice(0, 35)}…` : item.facility_name} • {item.urgency ? item.urgency.charAt(0).toUpperCase() + item.urgency.slice(1) : ''}{item.status !== 'OPEN' ? ` • ${item.status}` : ''}
                  {selectorRequests.filter((other) => other.required_blood_type === item.required_blood_type && other.facility_name === item.facility_name && other.urgency === item.urgency).length > 1 ? ` • Needed ${formatDate(item.needed_datetime)}` : ''}
                </option>
              ))}
            </select>
          </div>}
          {isOwnRequest && requestsLoading && <p role="status">Loading active requests…</p>}
          {isOwnRequest && requestsError && <div role="alert"><p>Active requests could not be loaded: {requestsError.message}</p><Button variant="secondary" onClick={() => reloadRequests()}>Try again</Button></div>}
        </header>
      ) : (
        <div style={{ display: 'flex', gap: 'var(--space-4)', alignItems: 'center', marginBottom: 'var(--space-4)' }}>
          {hasActiveResponse && (
            <Link className={styles.backLink} to="/responses" style={{ marginBottom: 0 }}>
              <ArrowLeft size={16} /> My Responses
            </Link>
          )}
          <Link className={styles.backLink} to="/" style={{ marginBottom: 0 }}>
            {hasActiveResponse ? null : <ArrowLeft size={16} />} Compatible requests
          </Link>
        </div>
      )}



      {matchError && <div className="alert alert-error" role="alert">{matchError.message}</div>}

      <section className={styles.requestSurface} aria-labelledby="request-title">
        <div className={styles.requestHeader}>
          <div className={styles.avatar}>{request.requester_profile_picture_url ? <img src={request.requester_profile_picture_url} alt="" /> : <User size={25} aria-hidden="true" />}</div>
          <div className={styles.requesterIdentity}>
            <div className={styles.requesterNameLine}>
              <h1 id="request-title">{request.requester_name || 'Community member'}</h1>
              {request.requester_verification_status === 'verified' && <CheckCircle size={18} weight="fill" aria-label="Verified account" />}
            </div>
            <p>{request.requester_chapter_name || 'Chapter not assigned'} member <span aria-hidden="true">&bull;</span> {formatDate(request.created_at || request.needed_datetime)}</p>
          </div>
          <div className={styles.requestStatus}>
            {!isRequesterView && ownMatch && <Badge variant={ownMatch.status === 'COMPLETED' ? 'success' : ownMatch.status === 'RESPONDED' ? 'brand' : 'neutral'}>{ownMatch.status}</Badge>}
            <div className={styles.bloodTypeSquare}>{request.required_blood_type}</div>
          </div>
        </div>

        <dl className={styles.requestFacts}>
          <div><dt><Drop size={18} weight="fill" /> Blood needed</dt><dd>{request.quantity_units} {request.quantity_units === 1 ? 'unit' : 'units'}</dd></div>
          <div className={styles.facilityFact}><dt><MapPin size={18} /> Facility</dt><dd>{request.facility_name}, {request.location?.municipality_name || 'Bataan'}</dd></div>
          <div><dt><CalendarBlank size={18} /> Needed by</dt><dd>{formatDate(request.needed_datetime)}</dd></div>
          <div><dt>Priority</dt><dd><Badge variant={urgencyVariant}>{request.urgency}</Badge></dd></div>
        </dl>

        {!isRequesterView && <div className={styles.requestActions}>
          <div className={styles.profileActions}>
            {!isOwnRequest && request.can_view_requester_profile && (
              <>
                <Button to={`/profile/${request.requester_id}`} variant="secondary" size="sm">View profile</Button>
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  onClick={() => hasActiveResponse ? navigate('/responses') : openChatWithUser(request.requester_id, { name: request.requester_name || 'Requester' })}
                  disabled={!canChat || request.requester_verification_status !== 'verified'}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}
                >
                  <ChatCircleDots size={16} weight="regular" /> {hasActiveResponse ? 'View offer status' : 'Request message'}
                </Button>
              </>
            )}
          </div>
          <div className={styles.donorActions}>
            {!isRequesterView && hasActiveResponse && <DonationOfferStatus matchId={ownMatch.match_id} />}
            {!isRequesterView && !hasActiveResponse && <Button disabled={!canRespond || actionLoading} onClick={() => setConfirmation('respond')}><Check size={18} /> Offer to donate</Button>}
            {!isRequesterView && ownMatch?.status === 'RESPONDED' && <DonationReportButton matchId={ownMatch.match_id} onClick={() => { setOccurredAt(''); setProofPhotos([]); setReportError(''); setReportingMatchId(ownMatch.match_id) }} />}
            {!isRequesterView && ownMatch?.status === 'RESPONDED' && <Button variant="secondary" className={styles.cancelOffer} onClick={() => setConfirmation('withdraw')}><X size={18} /> Cancel help</Button>}
            {!isRequesterView && !canRespond && !hasActiveResponse && request.reason_code !== 'staff_account' && (
              !user?.donor_enrolled ? (
                <Button to="/profile" variant="primary" style={{ display: 'inline-flex', alignItems: 'center', gap: '0.35rem' }}>
                  <Drop size={16} weight="fill" /> Enroll as donor
                </Button>
              ) : (
                <Button to="/profile" variant="secondary">Review donor eligibility</Button>
              )
            )}
          </div>
        </div>}
        {!isRequesterView && !canRespond && !hasActiveResponse && request.reason_code !== 'staff_account' && <p className={styles.eligibilityNote}>{request.reason_text || eligibilityMessage(user)}{request.eligible_again_at && ` Eligible again: ${formatDate(request.eligible_again_at)}.`}</p>}
      </section>

      {isRequesterView && <OfferAcceptancePanel key={`${user?.id}:${id}`} requestId={id} matches={matches} onRefresh={load} />}

      {confirmation && (
        <div className={styles.modalBackdrop} role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget && !actionLoading) setConfirmation(null) }}>
          <div className={styles.modalContent} role="dialog" aria-modal="true" aria-labelledby="confirmation-title">
            <h2 id="confirmation-title">{confirmation === 'respond' ? 'Confirm that you can help' : 'Cancel your offer to help?'}</h2>
            {confirmation === 'respond' ? (
              <p>Your response will be shared with the requester for this {request.required_blood_type} request at {request.facility_name}. The receiving facility makes the final medical eligibility decision.</p>
            ) : (
              <p>The requester will no longer see you as a responding donor. You can offer again while this request remains open.</p>
            )}
            <div className={styles.modalActions}>
              <Button type="button" variant="secondary" disabled={actionLoading} onClick={() => setConfirmation(null)}>Not now</Button>
              <Button type="button" variant={confirmation === 'withdraw' ? 'destructive' : 'primary'} isLoading={actionLoading} onClick={confirmation === 'respond' ? respond : withdraw}>{confirmation === 'respond' ? 'Offer to donate' : 'Confirm cancellation'}</Button>
            </div>
          </div>
        </div>
      )}

      {reportingMatchId && (
        <div className={styles.modalBackdrop} role="presentation">
          <form className={styles.modalContent} onSubmit={onSubmitReport} role="dialog" aria-modal="true" aria-labelledby="report-title">
            <h2 id="report-title" className={styles.reportTitle}>Report completed donation</h2>
            <p className={styles.reportDescription}>The requester will be asked to acknowledge this donation. Your donor offers are held while the report is unresolved.</p>
            <DonationDateTimeFields value={occurredAt} onChange={value => { setOccurredAt(value); setReportError('') }} />
            <DonationProofInput files={proofPhotos} onChange={setProofPhotos} disabled={submittingReport} />
            {reportError && <p className="alert alert-error" role="alert">{reportError}</p>}
            <div className={styles.modalActions}><Button type="button" variant="secondary" disabled={submittingReport} onClick={() => setReportingMatchId(null)}>Cancel</Button><Button type="submit" isLoading={submittingReport}>Send report</Button></div>
          </form>
        </div>
      )}

      <EmailVerificationDialog isOpen={verifyDialogOpen} initialEmail={user?.email} onClose={() => setVerifyDialogOpen(false)} onSuccess={() => { setVerifyDialogOpen(false); refresh() }} />
    </div>
  )
}
