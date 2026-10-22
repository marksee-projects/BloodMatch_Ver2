import { useActionNoticeState } from '../context/NotificationContext'
import { useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { liveQueryOptions } from '../services/liveQueries'
import { HandHeart, X } from '@phosphor-icons/react'
import { api } from '../services/apiClient'
import { useAuth } from '../context/AuthContext'
import { LoadingSpinner } from '../components/ui/LoadingSpinner'
import { Button } from '../components/ui/Button'
import { Badge } from '../components/ui/Badge'
import { FeedCard } from '../components/FeedCard'
import ConfirmationDialog from '../components/ConfirmationDialog'
import DonationDateTimeFields from '../components/DonationDateTimeFields'
import DonationProofInput from '../components/DonationProofInput'
import DonationOfferStatus, { DonationReportButton } from '../components/DonationOfferStatus'
import { donationReportForm } from '../services/donationCompletion'
import styles from './MyResponsesPage.module.css'

export default function MyResponsesPage() {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const canManageResponses = user?.role === 'member'
  const actionBusyRef = useRef(false)
  const [successMessage, setSuccessMessage] = useActionNoticeState(null, 'success')
  const [errorMessage, setErrorMessage] = useActionNoticeState(null, 'error')
  const [actionLoading, setActionLoading] = useState(false)
  const [confirmWithdrawMatchId, setConfirmWithdrawMatchId] = useState(null)
  const [reportingMatchId, setReportingMatchId] = useState(null)
  const [occurredAt, setOccurredAt] = useState('')
  const [proofPhotos, setProofPhotos] = useState([])
  const [submittingReport, setSubmittingReport] = useState(false)

  const {
    data: responses = [],
    isLoading,
    error,
    refetch
  } = useQuery({
    ...liveQueryOptions,
    queryKey: ['my-responses', user?.id],
    queryFn: async () => (await api.get('/api/my/responses')).responses || [],
    enabled: Boolean(user?.id)
  })


  const handleWithdraw = async () => {
    if (!confirmWithdrawMatchId || !canManageResponses || actionBusyRef.current) return
    actionBusyRef.current = true
    setActionLoading(true)
    setErrorMessage(null)
    setSuccessMessage(null)
    try {
      await api.post(`/api/matches/${confirmWithdrawMatchId}/withdraw`)
      setConfirmWithdrawMatchId(null)
      setSuccessMessage('Your offer to help has been cancelled. You can offer again while the request remains open.')
      queryClient.invalidateQueries({ queryKey: ['home-feed'] })
      queryClient.invalidateQueries({ queryKey: ['request-matches'] })
      await refetch()
    } catch (err) {
      setErrorMessage(err.message || 'Could not cancel your offer to help.')
    } finally {
      actionBusyRef.current = false
      setActionLoading(false)
    }
  }

  const handleSubmitReport = async (event) => {
    event.preventDefault()
    if (!reportingMatchId || !canManageResponses || actionBusyRef.current) return
    actionBusyRef.current = true
    setSubmittingReport(true)
    setErrorMessage(null)
    setSuccessMessage(null)
    try {
      await api.postForm('/api/donation-reports', donationReportForm(reportingMatchId, occurredAt, proofPhotos))
      setSuccessMessage('Donation report sent to the requester. A separate report hold applies while it is unresolved.')
      setReportingMatchId(null)
      setOccurredAt('')
      setProofPhotos([])
      queryClient.invalidateQueries({ queryKey: ['home-feed'] })
      queryClient.invalidateQueries({ queryKey: ['request-matches'] })
      await refetch()
    } catch (err) {
      setErrorMessage(err.message || 'Could not send donation report.')
    } finally {
      actionBusyRef.current = false
      setSubmittingReport(false)
    }
  }

  if (isLoading) {
    return (
      <div className={styles.container}>
        <LoadingSpinner text="Loading your responses…" minHeight="24rem" />
      </div>
    )
  }

  if (error) {
    return (
      <div className={styles.container}>
        <div className="alert alert-error" role="alert" style={{ marginBottom: 'var(--space-4)' }}>
          <p>{error.message || 'Could not load your responses.'}</p>
        </div>
        <Button variant="secondary" onClick={() => refetch()}>Try again</Button>
      </div>
    )
  }

  return (
    <div className={styles.container}>
      <header className={styles.pageHeader}>
        <h1 className={styles.pageTitle}>My Responses</h1>
      </header>





      {responses.length === 0 ? (
        <section className={styles.emptyState}>
          <div className={styles.emptyIcon} aria-hidden="true">
            <HandHeart size={36} weight="regular" />
          </div>
          <h2>You haven't responded to any blood requests yet</h2>
          <div className={styles.emptyActions}>
            <Button to="/" variant="primary">Browse compatible requests</Button>
          </div>
        </section>
      ) : (
        <div className={styles.responseList}>
          {responses.map((req) => {
            const isCompleted = req.match_status === 'COMPLETED'
            const isResponded = req.match_status === 'RESPONDED'

            return (
              <div key={req.id} className={styles.responseItem}>
                {req.status && req.status !== 'OPEN' && (
                  <div className={styles.requestStatus}>
                    <span>Request status</span>
                    <Badge variant="neutral">{req.status}</Badge>
                  </div>
                )}
              <FeedCard request={req} hideActions defaultExpanded showNeededYear
                headerActions={<>
                  {!isCompleted && <Badge variant={isResponded ? 'brand' : 'neutral'}>{req.match_status || 'RESPONDED'}</Badge>}
                </>}>
                <div className={styles.cardActions}>
                  <div className={styles.primaryAction}>
                    <Button to={`/profile/${req.requester_id}`} variant="secondary" size="sm">View profile</Button>
                    <DonationOfferStatus matchId={req.match_id} />
                  </div>
                  <div className={styles.secondaryActions}>
                    {isCompleted && <Badge variant="success">Donation confirmed</Badge>}
                    {canManageResponses && (isResponded || req.match_status === 'CLOSED') && req.match_id && (
                      <DonationReportButton size="sm" matchId={req.match_id} onClick={() => { setOccurredAt(''); setProofPhotos([]); setReportingMatchId(req.match_id) }} />
                    )}
                    {canManageResponses && isResponded && req.status === 'OPEN' && req.match_id && (
                      <Button variant="secondary" size="sm" className={styles.cancelOffer} onClick={() => setConfirmWithdrawMatchId(req.match_id)}>
                        <X size={14} aria-hidden="true" /> Cancel help
                      </Button>
                    )}
                  </div>
                </div>
              </FeedCard>
              </div>
            )
          })}
        </div>
      )}

      {/* Confirmation dialog for canceling an offer to help */}
      <ConfirmationDialog
        open={Boolean(confirmWithdrawMatchId)}
        title="Cancel your offer to help?"
        confirmLabel="Confirm cancellation"
        cancelLabel="Not now"
        destructive
        busy={actionLoading}
        onConfirm={handleWithdraw}
        onCancel={() => setConfirmWithdrawMatchId(null)}
      >
        The requester will no longer see you as a responding donor. You can offer again while this request remains open.
      </ConfirmationDialog>

      {/* Modal dialog for reporting a completed donation */}
      {reportingMatchId && (
        <div className={styles.modalBackdrop} role="presentation" onMouseDown={(e) => { if (e.target === e.currentTarget && !submittingReport) setReportingMatchId(null) }}>
          <form
            className={styles.modalContent}
            onSubmit={handleSubmitReport}
            role="dialog"
            aria-modal="true"
            aria-labelledby="report-modal-title"
          >
            <h2 id="report-modal-title" className={styles.reportTitle}>Report completed donation</h2>
            <p className={styles.reportDescription}>The requester will be asked to acknowledge this donation. Your donor offers are held while the report is unresolved.</p>
            <DonationDateTimeFields value={occurredAt} onChange={setOccurredAt} />
            <DonationProofInput files={proofPhotos} onChange={setProofPhotos} disabled={submittingReport} />
            <div className={styles.modalActions}>
              <Button type="button" variant="secondary" disabled={submittingReport} onClick={() => setReportingMatchId(null)}>
                Cancel
              </Button>
              <Button type="submit" isLoading={submittingReport}>
                Send report
              </Button>
            </div>
          </form>
        </div>
      )}
    </div>
  )
}
