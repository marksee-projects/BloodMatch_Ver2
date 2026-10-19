import { useChat } from '../context/ChatContext'
import React, { useId, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, CaretDown, CheckCircle, User } from '@phosphor-icons/react'
import { Card } from './ui/Card'
import { Button } from './ui/Button'
import { Badge } from './ui/Badge'
import { BloodTypeBlock } from './ui/BloodTypeBlock'
import ConfirmationDialog from './ConfirmationDialog'
import { useAuth } from '../context/AuthContext'
import { api } from '../services/apiClient'
import styles from './FeedCard.module.css'
import { useNotify } from '../context/NotificationContext'

function formatNeededDate(value, includeYear = false) {
  if (!value) return 'Date not provided'
  return new Date(value.replace(' ', 'T') + 'Z').toLocaleString(undefined, {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', ...(includeYear ? { year: 'numeric' } : {})
  })
}

function formatPostedTime(value) {
  if (!value) return 'Posted time not provided'
  const timestamp = new Date(value.replace(' ', 'T') + 'Z').getTime()
  if (!Number.isFinite(timestamp)) return 'Posted time not provided'
  const seconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000))
  if (seconds < 60) return 'Posted just now'
  const [count, unit] = seconds < 3600 ? [Math.floor(seconds / 60), 'minute']
    : seconds < 86400 ? [Math.floor(seconds / 3600), 'hour'] : [Math.floor(seconds / 86400), 'day']
  return `Posted ${count} ${unit}${count === 1 ? '' : 's'} ago`
}

export function FeedCard({ request, hideActions = false, defaultExpanded = false, showNeededYear = false, children }) {
  const notify = useNotify()
  const { canChat, openChatWithUser } = useChat()
  const [expanded, setExpanded] = useState(defaultExpanded)
  const [confirmingResponse, setConfirmingResponse] = useState(false)
  const { user } = useAuth()
  const navigate = useNavigate()
  const responseBusyRef = useRef(false)
  const detailsId = useId()
  const reasonId = useId()
  const queryClient = useQueryClient()
  const response = useMutation({
    mutationFn: () => api.post(`/api/requests/${request.id}/respond`),
    onSuccess: () => {
      notify({ severity: 'success', message: 'Your offer to help has been sent to the requester.' })
      setConfirmingResponse(false)
      queryClient.invalidateQueries({ queryKey: ['home-feed'] })
      queryClient.invalidateQueries({ queryKey: ['my-responses'] })
      queryClient.invalidateQueries({ queryKey: ['request-matches', request.id] })
      navigate(`/requests/${request.id}/matches`)
    },
    onError: (error) => { notify({ severity: 'error', message: error.message || 'Could not send your offer.' }); queryClient.invalidateQueries({ queryKey: ['home-feed'] }) },
    onSettled: () => { responseBusyRef.current = false }
  })
  const responded = request.responded || response.isSuccess
  const isNotEnrolled = request.reason_code === 'not_enrolled'
  const canRespond = request.can_respond === true && !responded
  const profileTo = request.can_view_requester_profile === true && Number(request.requester_id) > 0 ? `/profile/${request.requester_id}` : null
  const ProfileIdentity = profileTo ? Link : 'span'
  const confirmResponse = () => {
    if (!canRespond || response.isPending || responseBusyRef.current) return
    responseBusyRef.current = true
    response.mutate()
  }
  const responseReason = responded ? 'Your response has been sent to the requester.' : request.reason_code === 'staff_account' ? null : request.reason_text
  const urgencyVariant = ['emergency', 'critical'].includes(request.urgency)
    ? 'emergency'
    : request.urgency === 'urgent' ? 'urgent' : 'neutral'

  return (
    <Card padding="none" className={styles.feedCard}>
      <article className={styles.cardBody}>
        <header className={styles.summaryHeader}>
          <ProfileIdentity {...(profileTo ? { to: profileTo, 'aria-label': `View ${request.requester_name || 'requester'}'s profile` } : {})} className={styles.avatar}>
            <User size={22} aria-hidden="true" />
            {request.requester_profile_picture_url && <img key={request.requester_profile_picture_url} src={request.requester_profile_picture_url} alt="" onError={event => { event.currentTarget.hidden = true }} />}
          </ProfileIdentity>

          <span className={styles.identity}>
            <span className={styles.nameRow}>
              <ProfileIdentity {...(profileTo ? { to: profileTo } : {})} className={styles.requesterName}>{request.requester_name || 'Community member'}</ProfileIdentity>
              {request.requester_verification_status === 'verified' && (
                <CheckCircle size={17} weight="fill" aria-label="Verified member" />
              )}
            </span>
            <span className={styles.chapter}>{request.requester_chapter_name ? `${request.requester_chapter_name} member` : 'Chapter not assigned'}</span>
            <span className={styles.postedTime}>{formatPostedTime(request.created_at)}</span>
            {responded && <span className={styles.responseStatus}><Badge variant="neutral">Responded</Badge></span>}
          </span>

          <span className={styles.summaryMeta}>
            <Badge variant={urgencyVariant}>{request.urgency === 'critical' ? 'EMERGENCY' : request.urgency.toUpperCase()}</Badge>
            <BloodTypeBlock bloodType={request.required_blood_type} level="normal" />
            <button type="button" className={styles.summaryButton} aria-label={expanded ? 'Hide request details' : 'Show request details'} aria-expanded={expanded} aria-controls={detailsId} onClick={() => setExpanded(current => !current)}><CaretDown className={expanded ? styles.caretOpen : styles.caret} size={20} aria-hidden="true" /></button>
          </span>
        </header>

        {expanded && (
          <div id={detailsId} className={styles.expandedContent}>
            <dl className={styles.metadata}>
              <div className={styles.hospital}>
                <dt>Hospital</dt>
                <dd>{request.facility_name || 'Not provided'}</dd>
              </div>
              <div>
                <dt>Municipality</dt>
                <dd>{request.location?.municipality_name || 'Not provided'}</dd>
              </div>
              <div>
                <dt>Blood needed</dt>
                <dd>{request.quantity_units} {request.quantity_units === 1 ? 'unit' : 'units'}</dd>
              </div>
              <div>
                <dt>{request.status === 'EXPIRED' ? 'Previous deadline' : 'Needed by'}</dt>
                <dd>{formatNeededDate(request.needed_datetime, showNeededYear)}</dd>
              </div>
            </dl>

            {!hideActions && (
              <div className={styles.actionArea}>
                <footer className={styles.actions}>
                  {!responded && <Button type="button" variant="primary" disabled={!canRespond || response.isPending}
                    isLoading={response.isPending} aria-describedby={responseReason ? reasonId : undefined}
                    title={isNotEnrolled ? 'Enroll as donor to respond.' : undefined}
                    onClick={() => {
                      if (!canRespond || response.isPending) return
                      response.reset()
                      setConfirmingResponse(true)
                    }}>
                    Offer to donate
                  </Button>}
                  <Button className={styles.messageAction} variant="secondary" disabled={!canChat || Number(request.requester_id) === Number(user?.id)} onClick={() => openChatWithUser(request.requester_id)}>Request message</Button>
                </footer>
                {isNotEnrolled ? (
                  <div className={styles.enrollPrompt} role="region" aria-label="Donor enrollment required">
                    <div className={styles.enrollPromptContent}>
                      <p id={reasonId} className={styles.responseReason} role="status">{responseReason || 'Enroll as a donor before responding.'}</p>
                    </div>
                    <button
                      type="button"
                      className={styles.enrollPromptButton}
                      onClick={() => navigate('/profile')}
                      aria-label="Go to profile to enroll as a donor"
                    >
                      <span>Enroll as donor</span>
                      <ArrowRight size={14} weight="bold" aria-hidden="true" />
                    </button>
                  </div>
                ) : responseReason ? (
                  <div className={styles.eligibilityHelp}><p id={reasonId} className={styles.responseReason} role="status">{responseReason}</p>{!responded && !canRespond && <Button variant="secondary" to={request.reason_code === 'pending_report_hold' ? '/donation-reports' : '/profile'}>{request.reason_code === 'pending_report_hold' ? 'Donation reports' : 'Review donor eligibility'}</Button>}</div>
                ) : null}
                {response.isError && <p className={styles.responseReason} role="alert">{response.error.message}</p>}
              </div>
            )}
            {children}
          </div>
        )}
        <ConfirmationDialog open={confirmingResponse} title="Confirm that you can help"
          confirmLabel="Offer to donate" cancelLabel="Not now" busy={response.isPending} confirmDisabled={!canRespond}
          error={response.isError ? response.error.message : undefined}
          onConfirm={confirmResponse} onCancel={() => setConfirmingResponse(false)}>
          <p>Your response will be shared with the requester for this {request.required_blood_type} request at {request.facility_name}. The receiving facility makes the final medical eligibility decision.</p>
          {!canRespond && <p role="status">{responseReason || 'This request is no longer available for a response.'}</p>}
        </ConfirmationDialog>
      </article>
    </Card>
  )
}
