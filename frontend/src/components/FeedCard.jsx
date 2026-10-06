import React from 'react'
import { CalendarBlank, CheckCircle, Drop, MapPin, User } from '@phosphor-icons/react'
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
  const urgencyVariant = request.urgency === 'critical'
    ? 'critical'
    : request.urgency === 'urgent' ? 'urgent' : 'neutral'

  return (
    <Card padding="none" className={styles.feedCard}>
      <article className={styles.cardBody}>
        <header className={styles.identityRow}>
          <div className={styles.avatar}>
            {request.requester_profile_picture_url ? (
              <img src={request.requester_profile_picture_url} alt="" />
            ) : (
              <User size={22} aria-hidden="true" />
            )}
          </div>

          <div className={styles.identity}>
            <div className={styles.nameRow}>
              <h2>{request.requester_name || 'Community member'}</h2>
              {request.requester_verification_status === 'verified' && (
                <CheckCircle size={17} weight="fill" aria-label="Verified member" />
              )}
            </div>
            <p>{request.requester_chapter_name || 'Chapter not assigned'}</p>
          </div>

          <BloodTypeBlock bloodType={request.required_blood_type} level="normal" />
        </header>

        <div className={styles.requestLine}>
          <div>
            <p className={styles.facility}>{request.facility_name}</p>
            <p className={styles.location}>{request.location?.municipality_name || 'Bataan'}</p>
          </div>
          <Badge variant={urgencyVariant}>{request.urgency}</Badge>
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
              <Button variant="secondary" size="sm" to={`/profile/${request.requester_id}`}>
                View profile
              </Button>
            )}
          </footer>
        )}
      </article>
    </Card>
  )
}
