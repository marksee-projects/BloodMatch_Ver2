import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, CalendarBlank, Check, CheckCircle, Drop, Hospital, MapPin, User } from '@phosphor-icons/react'
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

export default function MatchesPage() {
  const { id } = useParams()
  const { user, refresh } = useAuth()
  const [verifyDialogOpen, setVerifyDialogOpen] = useState(false)
  const [pageData, setPageData] = useState(null)
  const [errorAlert, setErrorAlert] = useState(null)
  const [message, setMessage] = useState(null)
  const [reportingMatchId, setReportingMatchId] = useState(null)
  const [reportNote, setReportNote] = useState('')
  const [submittingReport, setSubmittingReport] = useState(false)

  const load = useCallback(() => api.get(`/api/requests/${id}/matches`).then(setPageData).catch((err) => setErrorAlert(err.message)), [id])
  useEffect(() => { load() }, [load])

  const matches = pageData?.matches || []
  const request = pageData?.request
  const viewerMode = pageData?.viewer_mode
  const ownMatch = matches.find((match) => match.is_current_user)
  const isRequesterView = viewerMode === 'requester'

  const onRespond = async (matchId) => {
    setMessage(null)
    setErrorAlert(null)
    try {
      await api.post(`/api/matches/${matchId}/respond`)
      setMessage('Your offer to donate has been sent to the requester.')
      await load()
    } catch (err) {
      if (err.status === 403 && err.details?.code === 'EMAIL_UNVERIFIED') setVerifyDialogOpen(true)
      else setErrorAlert(err.message)
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
    } catch (err) {
      setErrorAlert(err.message)
    } finally {
      setSubmittingReport(false)
    }
  }

  if (errorAlert && !pageData) {
    return <div className={styles.container}><div className="alert alert-error" role="alert">{errorAlert}</div><Button to={isRequesterView ? '/requests/mine' : '/'} variant="secondary"><ArrowLeft size={16} /> Back</Button></div>
  }
  if (!pageData || !request) return <div className={styles.container}><LoadingSpinner text="Loading request…" minHeight="24rem" /></div>

  return (
    <div className={styles.container}>
      <Link className={styles.backLink} to={isRequesterView ? '/requests/mine' : '/'}><ArrowLeft size={16} aria-hidden="true" /> {isRequesterView ? 'My blood requests' : 'Compatible requests'}</Link>

      <header className={styles.header}>
        <div>
          <p className={styles.kicker}>{isRequesterView ? 'Requester view' : 'Compatible request'}</p>
          <h1>{isRequesterView ? 'Potential matches' : 'Blood request details'}</h1>
          <p>{isRequesterView ? 'Compatible donors are ordered using eligibility and approximate proximity.' : `Your ${user?.blood_type || 'saved'} blood type is compatible with this request.`}</p>
        </div>
        <Badge variant={request.status === 'OPEN' ? 'brand' : request.status === 'FULFILLED' ? 'success' : 'neutral'}>{request.status}</Badge>
      </header>

      {message && <div className="alert alert-success" role="status">{message}</div>}
      {errorAlert && <div className="alert alert-error" role="alert">{errorAlert}</div>}

      <section className={styles.requestSummary}>
        <div className={styles.bloodBlock}><Drop size={22} weight="fill" aria-hidden="true" /><strong>{request.required_blood_type}</strong><span>{request.quantity_units} {request.quantity_units === 1 ? 'unit' : 'units'}</span></div>
        <div className={styles.summaryDetails}>
          <div><Hospital size={19} aria-hidden="true" /><span><small>Facility</small><strong>{request.facility_name}</strong></span></div>
          <div><MapPin size={19} aria-hidden="true" /><span><small>Location</small><strong>{request.location?.municipality_name || 'Bataan'}</strong></span></div>
          <div><CalendarBlank size={19} aria-hidden="true" /><span><small>Needed by</small><strong>{formatDate(request.needed_datetime)}</strong></span></div>
        </div>
        <Badge variant={request.urgency === 'critical' ? 'critical' : request.urgency === 'urgent' ? 'urgent' : 'neutral'}>{request.urgency}</Badge>
      </section>

      <section className={styles.requesterCard} aria-labelledby="requester-heading">
        <div className={styles.avatar}>
          {request.requester_profile_picture_url ? <img src={request.requester_profile_picture_url} alt="" /> : <User size={28} aria-hidden="true" />}
        </div>
        <div>
          <p className={styles.sectionLabel} id="requester-heading">Requester</p>
          <h2>{request.requester_name}</h2>
          <p>{request.requester_chapter_name || 'Chapter not assigned'}</p>
        </div>
        {request.requester_verification_status === 'verified' && <span className={styles.verified}><CheckCircle size={18} weight="fill" /> Verified member</span>}
        {request.can_view_requester_profile && <Button to={`/profile/${request.requester_id}`} variant="secondary" size="sm">View profile</Button>}
      </section>

      {!isRequesterView && (
        <section className={styles.responsePanel}>
          <div>
            <h2>{ownMatch ? 'Your response' : 'Interested in helping?'}</h2>
            <p>{ownMatch ? 'Your match status is shown here. The receiving facility makes the final clinical eligibility decision.' : 'This request matches your blood type, but your account is not currently in its eligible donor match set.'}</p>
          </div>
          {ownMatch && (ownMatch.status === 'POTENTIAL' || ownMatch.status === 'NOTIFIED') && <Button onClick={() => onRespond(ownMatch.match_id)}><Check size={18} /> I can help</Button>}
          {ownMatch?.status === 'RESPONDED' && <Button onClick={() => setReportingMatchId(ownMatch.match_id)}>Report completed donation</Button>}
          {ownMatch && <Badge variant={ownMatch.status === 'COMPLETED' ? 'success' : ownMatch.status === 'RESPONDED' ? 'brand' : 'neutral'}>{ownMatch.status}</Badge>}
          {!ownMatch && <Button to="/profile" variant="secondary">Review donor eligibility</Button>}
        </section>
      )}

      {isRequesterView && (
        <section className={styles.matchesSection}>
          <div className={styles.sectionHeading}><div><p className={styles.sectionLabel}>Matched donors</p><h2>{matches.length} potential {matches.length === 1 ? 'match' : 'matches'}</h2></div><p>Approximate distance is used here for ranking, never on Home.</p></div>
          {matches.length === 0 ? (
            <div className={styles.emptyState}><h3>No eligible donors yet</h3><p>The matching engine will update this list when compatible members become eligible and available.</p></div>
          ) : (
            <div className={styles.matchList}>
              {matches.map((match, index) => (
                <article key={match.match_id} className={styles.matchCard}>
                  <div className={styles.rank}>{index + 1}</div>
                  <div className={styles.donorIdentity}><h3>{match.display_name}</h3><p>{match.chapter_name || 'Chapter not assigned'}</p></div>
                  <div className={styles.matchMetric}><small>Availability</small><strong>{match.availability || 'Not provided'}</strong></div>
                  <div className={styles.matchMetric}><small>Distance</small><strong>{match.approximate_distance_km !== null ? `About ${match.approximate_distance_km} km` : 'Not available'}</strong></div>
                  <Badge variant={match.status === 'COMPLETED' ? 'success' : match.status === 'RESPONDED' ? 'brand' : 'neutral'}>{match.status}</Badge>
                  {match.profile_user_id && <Button to={`/profile/${match.profile_user_id}`} variant="secondary" size="sm">View profile</Button>}
                </article>
              ))}
            </div>
          )}
        </section>
      )}

      {reportingMatchId && (
        <div className={styles.modalBackdrop}>
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
