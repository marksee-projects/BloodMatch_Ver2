import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, CalendarBlank, Check, CheckCircle, Drop, Hospital, MapPin, User, X } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import EmailVerificationDialog from '../components/EmailVerificationDialog'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'
import { Button } from '../components/ui/Button'
import { Badge } from '../components/ui/Badge'
import styles from './MatchesPage.module.css'

function formatDate(value) {
  if (!value) return 'Not provided'
  return new Date(value.replace(' ', 'T') + 'Z').toLocaleString(undefined, {
    month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
  })
}

function eligibilityMessage(user) {
  if (user?.verification_status !== 'verified') return 'Your blood type can support this request. Complete account verification before offering to donate.'
  if (!user?.donor_enrolled) return 'Your blood type can support this request. Enroll as a donor before offering to help.'
  if (user?.availability !== 'available') return 'Your blood type can support this request. Set your donor status to available to join its eligible matches.'
  return 'Your blood type can support this request, but your donor eligibility has not been added to this request yet. Review your donor details or ask an officer to refresh the matches.'
}

export default function MatchesPage() {
  const { id } = useParams()
  const { user, refresh } = useAuth()
  const [verifyDialogOpen, setVerifyDialogOpen] = useState(false)
  const [pageData, setPageData] = useState(null)
  const [errorAlert, setErrorAlert] = useState(null)
  const [message, setMessage] = useState(null)
  const [confirmation, setConfirmation] = useState(null)
  const [actionLoading, setActionLoading] = useState(false)
  const [reportingMatchId, setReportingMatchId] = useState(null)
  const [reportNote, setReportNote] = useState('')
  const [submittingReport, setSubmittingReport] = useState(false)

  const load = useCallback(() => api.get(`/api/requests/${id}/matches`).then(setPageData).catch((error) => setErrorAlert(error.message)), [id])
  useEffect(() => { load() }, [load])

  const matches = pageData?.matches || []
  const request = pageData?.request
  const viewerMode = pageData?.viewer_mode
  const ownMatch = matches.find((match) => match.is_current_user)
  const isRequesterView = viewerMode === 'requester'

  const respond = async () => {
    if (!ownMatch) return
    setActionLoading(true)
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post(`/api/matches/${ownMatch.match_id}/respond`)
      setConfirmation(null)
      setMessage('Your offer to help has been sent to the requester.')
      await load()
    } catch (error) {
      setConfirmation(null)
      if (error.status === 403 && error.details?.code === 'EMAIL_UNVERIFIED') setVerifyDialogOpen(true)
      else setErrorAlert(error.message)
    } finally {
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
    if (!reportingMatchId) return
    setSubmittingReport(true)
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post('/api/donation-reports', { match_id: reportingMatchId, note: reportNote || null })
      setMessage('Donation report sent for chapter officer confirmation.')
      setReportingMatchId(null)
      setReportNote('')
      await load()
    } catch (error) {
      setErrorAlert(error.message)
    } finally {
      setSubmittingReport(false)
    }
  }

  if (errorAlert && !pageData) {
    return <div className={styles.container}><div className="alert alert-error" role="alert">{errorAlert}</div><Button to="/" variant="secondary"><ArrowLeft size={16} /> Back</Button></div>
  }
  if (!pageData || !request) return <div className={styles.container}><LoadingSpinner text="Loading request…" minHeight="24rem" /></div>

  const statusVariant = request.status === 'OPEN' ? 'brand' : request.status === 'FULFILLED' ? 'success' : 'neutral'
  const urgencyVariant = request.urgency === 'critical' ? 'critical' : request.urgency === 'urgent' ? 'urgent' : 'neutral'

  return (
    <div className={styles.container}>
      <Link className={styles.backLink} to={isRequesterView ? '/requests/mine' : '/'}><ArrowLeft size={16} /> {isRequesterView ? 'My blood requests' : 'Compatible requests'}</Link>

      {message && <div className="alert alert-success" role="status">{message}</div>}
      {errorAlert && <div className="alert alert-error" role="alert">{errorAlert}</div>}

      <section className={styles.requestSurface} aria-labelledby="request-title">
        <div className={styles.requestTopline}>
          <div className={styles.bloodBlock}><Drop size={21} weight="fill" aria-hidden="true" /><strong>{request.required_blood_type}</strong><span>{request.quantity_units} {request.quantity_units === 1 ? 'unit' : 'units'}</span></div>
          <div className={styles.requestTitle}><h1 id="request-title">Blood request</h1><p>{request.facility_name}</p></div>
          <div className={styles.badges}><Badge variant={urgencyVariant}>{request.urgency}</Badge><Badge variant={statusVariant}>{request.status}</Badge></div>
        </div>

        <dl className={styles.requestFacts}>
          <div><dt><Hospital size={18} /> Facility</dt><dd>{request.facility_name}</dd></div>
          <div><dt><MapPin size={18} /> Location</dt><dd>{request.location?.municipality_name || 'Bataan'}</dd></div>
          <div><dt><CalendarBlank size={18} /> Needed by</dt><dd>{formatDate(request.needed_datetime)}</dd></div>
        </dl>

        <div className={styles.requesterRow}>
          <div className={styles.avatar}>{request.requester_profile_picture_url ? <img src={request.requester_profile_picture_url} alt="" /> : <User size={25} aria-hidden="true" />}</div>
          <div className={styles.requesterIdentity}><span>Requested by</span><h2>{request.requester_name}</h2><p>{request.requester_chapter_name || 'Chapter not assigned'}</p></div>
          {request.requester_verification_status === 'verified' && <span className={styles.verified}><CheckCircle size={17} weight="fill" /> Verified</span>}
          {request.can_view_requester_profile && <Button to={`/profile/${request.requester_id}`} variant="secondary" size="sm">View profile</Button>}
        </div>
      </section>

      {!isRequesterView && (
        <section className={styles.actionSurface} aria-labelledby="response-heading">
          <div className={styles.actionCopy}>
            <h2 id="response-heading">{ownMatch?.status === 'RESPONDED' ? 'You offered to help' : ownMatch ? 'You can help with this request' : 'Before you offer to help'}</h2>
            <p>{ownMatch?.status === 'RESPONDED' ? 'The requester can now see your response. You may cancel it before submitting a donation report.' : ownMatch ? 'Confirm your availability before your response is shared with the requester.' : eligibilityMessage(user)}</p>
          </div>

          <div className={styles.actionControls}>
            {ownMatch && (ownMatch.status === 'POTENTIAL' || ownMatch.status === 'NOTIFIED') && <Button onClick={() => setConfirmation('respond')}><Check size={18} /> I can help</Button>}
            {ownMatch?.status === 'RESPONDED' && <Button onClick={() => setReportingMatchId(ownMatch.match_id)}>Report completed donation</Button>}
            {ownMatch?.status === 'RESPONDED' && <Button variant="secondary" className={styles.cancelOffer} onClick={() => setConfirmation('withdraw')}><X size={18} /> Cancel offer</Button>}
            {ownMatch && <Badge variant={ownMatch.status === 'COMPLETED' ? 'success' : ownMatch.status === 'RESPONDED' ? 'brand' : 'neutral'}>{ownMatch.status}</Badge>}
            {!ownMatch && <Button to="/profile" variant="secondary">Review donor eligibility</Button>}
          </div>
        </section>
      )}

      {isRequesterView && (
        <section className={styles.matchesSurface} aria-labelledby="matches-heading">
          <div className={styles.sectionHeading}><div><h2 id="matches-heading">Potential donors</h2><p>{matches.length} eligible {matches.length === 1 ? 'match' : 'matches'}, ordered using eligibility and approximate proximity.</p></div></div>
          {matches.length === 0 ? (
            <div className={styles.emptyState}><h3>No eligible donors yet</h3><p>This list updates when compatible members become eligible and available.</p></div>
          ) : (
            <div className={styles.matchList}>
              {matches.map((match, index) => (
                <article key={match.match_id} className={styles.matchRow}>
                  <div className={styles.rank}>{index + 1}</div>
                  <div className={styles.donorIdentity}><h3>{match.display_name}</h3><p>{match.chapter_name || 'Chapter not assigned'}</p></div>
                  <div className={styles.matchMetric}><span>Availability</span><strong>{match.availability || 'Not provided'}</strong></div>
                  <div className={styles.matchMetric}><span>Distance</span><strong>{match.approximate_distance_km !== null ? `About ${match.approximate_distance_km} km` : 'Not available'}</strong></div>
                  <Badge variant={match.status === 'COMPLETED' ? 'success' : match.status === 'RESPONDED' ? 'brand' : 'neutral'}>{match.status}</Badge>
                  {match.profile_user_id && <Button to={`/profile/${match.profile_user_id}`} variant="secondary" size="sm">View profile</Button>}
                </article>
              ))}
            </div>
          )}
        </section>
      )}

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
              <Button type="button" variant={confirmation === 'withdraw' ? 'destructive' : 'primary'} isLoading={actionLoading} onClick={confirmation === 'respond' ? respond : withdraw}>{confirmation === 'respond' ? 'Confirm I can help' : 'Confirm cancellation'}</Button>
            </div>
          </div>
        </div>
      )}

      {reportingMatchId && (
        <div className={styles.modalBackdrop} role="presentation">
          <form className={styles.modalContent} onSubmit={onSubmitReport} role="dialog" aria-modal="true" aria-labelledby="report-title">
            <h2 id="report-title">Report completed donation</h2>
            <p>The report will be reviewed by a chapter officer before the donation is confirmed.</p>
            <label htmlFor="report-note">Optional note</label>
            <textarea id="report-note" value={reportNote} onChange={(event) => setReportNote(event.target.value)} maxLength={500} rows={4} />
            <div className={styles.modalActions}><Button type="button" variant="secondary" onClick={() => setReportingMatchId(null)}>Cancel</Button><Button type="submit" isLoading={submittingReport}>Send report</Button></div>
          </form>
        </div>
      )}

      <EmailVerificationDialog isOpen={verifyDialogOpen} initialEmail={user?.email} onClose={() => setVerifyDialogOpen(false)} onSuccess={() => { setVerifyDialogOpen(false); refresh() }} />
    </div>
  )
}
