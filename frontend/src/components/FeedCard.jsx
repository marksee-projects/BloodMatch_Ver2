import React, { useId, useState } from 'react'
import { CalendarBlank, CaretDown, CheckCircle, Drop, MapPin, User } from '@phosphor-icons/react'
import { Card } from './ui/Card'
import { Button } from './ui/Button'
import { Badge } from './ui/Badge'
import { BloodTypeBlock } from './ui/BloodTypeBlock'
import styles from './FeedCard.module.css'

function formatNeededDate(value) {
  if (!value) return 'Date not provided'
  return new Date(value.replace(' ', 'T') + 'Z').toLocaleString(undefined, {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
  })
}

export function FeedCard({ request, hideActions = false }) {
  const [expanded, setExpanded] = useState(false)
  const detailsId = useId()
  const urgencyVariant = request.urgency === 'emergency'
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
            <span className={styles.chapter}>{request.requester_chapter_name || 'Chapter not assigned'}</span>
            <span className={styles.compactFacility}>{request.facility_name}</span>
          </span>

          <span className={styles.summaryMeta}>
            <Badge variant={urgencyVariant}>{request.urgency}</Badge>
            <BloodTypeBlock bloodType={request.required_blood_type} level="normal" />
            <CaretDown className={expanded ? styles.caretOpen : styles.caret} size={20} aria-hidden="true" />
          </span>
        </button>

        {expanded && (
          <div id={detailsId} className={styles.expandedContent}>
            <div className={styles.requestLine}>
              <div>
                <p className={styles.facility}>{request.facility_name}</p>
                <p className={styles.location}>{request.location?.municipality_name || 'Bataan'}</p>
              </div>
            </div>

            {request.description && <p className={styles.description}>{request.description}</p>}

            <dl className={styles.metadata}>
              <div>
                <dt><Drop size={17} weight="fill" aria-hidden="true" /> Blood needed</dt>
                <dd>{request.quantity_units} {request.quantity_units === 1 ? 'unit' : 'units'}</dd>
              </div>
              <div>
                <dt><CalendarBlank size={17} aria-hidden="true" /> Needed by</dt>
                <dd>{formatNeededDate(request.needed_datetime)}</dd>
              </div>
              <div>
                <dt><MapPin size={17} aria-hidden="true" /> Municipality</dt>
                <dd>{request.location?.municipality_name || 'Not provided'}</dd>
              </div>
            </dl>

            {!hideActions && (
              <footer className={styles.actions}>
                <Button variant="primary" size="sm" to={`/requests/${request.id}/matches`}>
                  View request
                </Button>
                {request.can_view_requester_profile && request.requester_id && (
                  <Button className={styles.profileAction} variant="secondary" size="sm" to={`/profile/${request.requester_id}`}>
                    View profile
                  </Button>
                )}
              </footer>
            )}
          </div>
        )}
      </article>
    </Card>
  )
}
