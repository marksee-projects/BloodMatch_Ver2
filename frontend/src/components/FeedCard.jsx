import React, { useId, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { CaretDown, CheckCircle, User } from '@phosphor-icons/react'
import { Card } from './ui/Card'
import { Button } from './ui/Button'
import { Badge } from './ui/Badge'
import { BloodTypeBlock } from './ui/BloodTypeBlock'
import { api } from '../services/apiClient'
import styles from './FeedCard.module.css'

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
  const [expanded, setExpanded] = useState(defaultExpanded)
  const detailsId = useId()
  const reasonId = useId()
  const queryClient = useQueryClient()
  const response = useMutation({
    mutationFn: () => api.post(`/api/requests/${request.id}/respond`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['home-feed'] }),
    onError: () => queryClient.invalidateQueries({ queryKey: ['home-feed'] })
  })
  const responded = request.responded || response.isSuccess
  const canRespond = request.can_respond === true && !responded
  const responseReason = responded ? 'Your response has been sent to the requester.' : request.reason_code === 'staff_account' ? null : request.reason_text
  const urgencyVariant = ['emergency', 'critical'].includes(request.urgency)
    ? 'emergency'
    : request.urgency === 'urgent' ? 'urgent' : 'neutral'

  return (
    <Card padding="none" className={styles.feedCard}>
      <article className={styles.cardBody}>
        <button
          type="button"
          className={styles.summaryButton}
          aria-expanded={expanded}
          aria-controls={detailsId}
          onClick={() => setExpanded((current) => !current)}
        >
          <span className={styles.avatar}>
            {request.requester_profile_picture_url ? (
              <img src={request.requester_profile_picture_url} alt="" />
            ) : (
              <User size={22} aria-hidden="true" />
            )}
          </span>

          <span className={styles.identity}>
            <span className={styles.nameRow}>
              <span className={styles.requesterName}>{request.requester_name || 'Community member'}</span>
              {request.requester_verification_status === 'verified' && (
                <CheckCircle size={17} weight="fill" aria-label="Verified member" />
              )}
            </span>
            <span className={styles.chapter}>{request.requester_chapter_name ? `${request.requester_chapter_name} member` : 'Chapter not assigned'}</span>
            <span className={styles.postedTime}>{formatPostedTime(request.created_at)}</span>
          </span>

          <span className={styles.summaryMeta}>
            <Badge variant={urgencyVariant}>{request.urgency === 'critical' ? 'EMERGENCY' : request.urgency.toUpperCase()}</Badge>
            <BloodTypeBlock bloodType={request.required_blood_type} level="normal" />
            <CaretDown className={expanded ? styles.caretOpen : styles.caret} size={20} aria-hidden="true" />
          </span>
        </button>

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
                <dt>Needed by</dt>
                <dd>{formatNeededDate(request.needed_datetime, showNeededYear)}</dd>
              </div>
            </dl>

            {!hideActions && (
              <div>
                <footer className={styles.actions}>
                  <Button type="button" variant="primary" disabled={!canRespond || response.isPending}
                    isLoading={response.isPending} aria-describedby={responseReason ? reasonId : undefined}
                    onClick={() => response.mutate()}>
                    {responded ? 'Responded' : 'Respond'}
                  </Button>
                  <Button className={styles.viewRequest} variant="secondary" to={`/requests/${request.id}/matches`}>View request</Button>
                </footer>
                {responseReason && <p id={reasonId} className={styles.responseReason} role="status">{responseReason}</p>}
                {response.isError && <p className={styles.responseReason} role="alert">{response.error.message}</p>}
              </div>
            )}
            {children}
          </div>
        )}
      </article>
    </Card>
  )
}
